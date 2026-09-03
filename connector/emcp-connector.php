<?php
/**
 * Plugin Name: EMCP Tools Connector
 * Description: Standalone bridge for receiving .emcp site backups pushed from EMCP Tools ("migrate-site"). Zero dependency on the EMCP plugin; runs on a bare WordPress install at the destination. Protects every endpoint with an HMAC-SHA256 signature keyed by the shared secret.
 * Version: 1.2.0
 * Author: Heretek AI
 * License: GPL-2.0-or-later
 *
 * The shared secret is the constant EMCP_CONNECTOR_SECRET (wp-config.php) or,
 * when that is absent, the option `emcp_connector_secret` — the site operator
 * sets one of them and shares it with the source (the `secret_key` argument of
 * emcp-tools/migrate-site, or a stored paired target). With no secret
 * configured the write endpoints refuse (the source gets a clear "not
 * configured" error instead of a silent 404).
 *
 * Push protocol (HMAC over canonical strings, all JSON):
 *   POST /wp-json/emcp-connector/v1/packet        { transfer_id, index, total, data_b64, sha256 }
 *   POST /wp-json/emcp-connector/v1/finalize      { transfer_id, sha256 }
 *   GET  /wp-json/emcp-connector/v1/job/<id>
 *   GET  /wp-json/emcp-connector/v1/status
 *
 * Pairing (1.2.0) — lets a source store this site as a paired target without
 * exchanging the raw secret out-of-band:
 *   POST /wp-json/emcp-connector/v1/pair/issue    (HMAC)  -> { code, ttl_seconds, expires_at }
 *   POST /wp-json/emcp-connector/v1/pair/exchange (open)  { code } -> { secret, site, version, capabilities }
 *   GET  /wp-json/emcp-connector/v1/verify        (HMAC)  -> { verified, site, version, capabilities }
 * The issue route is HMAC-gated (the operator scripting it already knows the
 * secret); the exchange route is intentionally open so a brand-new source can
 * redeem a single-use code without knowing the secret. HTTPS is required for
 * the exchange unless the host is loopback (localhost dev).
 *
 * @package EMCP_Connector
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Base work dir for incoming transfers + assembled archives. */
function emcp_connector_base_dir(): string {
	$dir = trailingslashit( WP_CONTENT_DIR ) . 'emcp-connector';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return $dir;
}

/** Shared secret, or '' when none is configured. */
function emcp_connector_secret(): string {
	if ( defined( 'EMCP_CONNECTOR_SECRET' ) && EMCP_CONNECTOR_SECRET ) {
		return (string) EMCP_CONNECTOR_SECRET;
	}
	return (string) get_option( 'emcp_connector_secret', '' );
}

/** Constant-time signature check over a canonical string. */
function emcp_connector_verify( string $canonical, string $signature ): bool {
	$secret = emcp_connector_secret();
	if ( '' === $secret || '' === $signature ) {
		return false;
	}
	$expected = hash_hmac( 'sha256', $canonical, $secret );
	return hash_equals( $expected, $signature );
}

/**
 * Canonical string a route's signature covers.
 *
 * WordPress consumes php://input before REST callbacks run, so signatures are
 * over a route-scoped canonical string built from the parsed JSON fields — not
 * the raw body. Data integrity is carried by the sha256 fields, which are
 * themselves part of the signed canonical. Canonical shapes (must match the
 * source exactly):
 *   packet      -> 'packet|' . sha256 . '|' . transfer_id . '|' . index
 *   finalize    -> 'finalize|' . sha256 . '|' . transfer_id
 *   job         -> 'job|' . job_id
 *   pair/issue  -> 'pair|issue|' . ts . '|' . nonce
 *   verify      -> 'verify|' . ts . '|' . nonce
 * The pair/issue + verify routes bind each signature to a per-request timestamp
 * and single-use nonce so a captured signature cannot be replayed (see
 * emcp_connector_nonce_fresh()).
 */
function emcp_connector_canonical( WP_REST_Request $request ): string {
	$params   = $request->get_json_params();
	$route    = $request->get_route();
	if ( false !== strpos( $route, '/packet' ) ) {
		$transfer = isset( $params['transfer_id'] ) ? (string) $params['transfer_id'] : '';
		$sha      = isset( $params['sha256'] ) ? (string) $params['sha256'] : '';
		$index    = isset( $params['index'] ) ? (string) $params['index'] : '';
		return 'packet|' . $sha . '|' . $transfer . '|' . $index;
	}
	if ( false !== strpos( $route, '/finalize' ) ) {
		$transfer = isset( $params['transfer_id'] ) ? (string) $params['transfer_id'] : '';
		$sha      = isset( $params['sha256'] ) ? (string) $params['sha256'] : '';
		return 'finalize|' . $sha . '|' . $transfer;
	}
	if ( false !== strpos( $route, '/job/' ) ) {
		return 'job|' . (string) $request['id'];
	}
	if ( false !== strpos( $route, '/pair/issue' ) ) {
		return 'pair|issue|' . (string) $request->get_param( 'ts' ) . '|' . (string) $request->get_param( 'nonce' );
	}
	if ( false !== strpos( $route, '/verify' ) ) {
		return 'verify|' . (string) $request->get_param( 'ts' ) . '|' . (string) $request->get_param( 'nonce' );
	}
	return '';
}

/**
 * Validate the X-EMCP-Signature header against the route canonical. The
 * pair/issue + verify routes additionally require a fresh, single-use
 * ts/nonce so their (constant-shaped) signatures cannot be replayed.
 */
function emcp_connector_authed( WP_REST_Request $request ) {
	$signature = isset( $_SERVER['HTTP_X_EMCP_SIGNATURE'] ) ? (string) $_SERVER['HTTP_X_EMCP_SIGNATURE'] : '';
	if ( ! emcp_connector_verify( emcp_connector_canonical( $request ), $signature ) ) {
		return false;
	}
	$route = $request->get_route();
	if ( false !== strpos( $route, '/pair/issue' ) || false !== strpos( $route, '/verify' ) ) {
		return emcp_connector_nonce_fresh( (string) $request->get_param( 'ts' ), (string) $request->get_param( 'nonce' ) );
	}
	return true;
}

/**
 * Freshness + single-use gate for the pair/issue + verify signatures.
 *
 * Accepts a ts within +-300 s of server time and a nonce (>= 16 chars) that has
 * not been seen before; records the nonce (hashed, 1 h expiry, capped) so a
 * captured signature can never be replayed.
 *
 * @param string $ts    Unix timestamp (seconds) the caller signed.
 * @param string $nonce Caller-chosen single-use nonce.
 * @return bool
 */
function emcp_connector_nonce_fresh( string $ts, string $nonce ): bool {
	if ( '' === $ts || '' === $nonce || strlen( $nonce ) < 16 ) {
		return false;
	}
	$now = time();
	if ( abs( (int) $ts - $now ) > 300 ) {
		return false;
	}
	$key  = hash( 'sha256', $ts . '|' . $nonce );
	$used = get_option( 'emcp_connector_nonces', array() );
	if ( ! is_array( $used ) ) {
		$used = array();
	}
	$used = array_values( array_filter( $used, static function ( $e ) use ( $now ) {
		return is_array( $e ) && ( (int) ( $e['exp'] ?? 0 ) > $now );
	} ) );
	foreach ( $used as $e ) {
		if ( is_array( $e ) && hash_equals( (string) $e['h'], $key ) ) {
			return false;
		}
	}
	$used[] = array( 'h' => $key, 'exp' => $now + 3600 );
	if ( count( $used ) > 500 ) {
		$used = array_slice( $used, -500 );
	}
	update_option( 'emcp_connector_nonces', $used, false );
	return true;
}

/**
 * Whitelist a client-supplied transfer id before it can reach a filesystem path.
 *
 * The HMAC gate is the outer trust boundary; this is defense-in-depth for the
 * on-disk chunk store. Anything outside [A-Za-z0-9_-]{8,64} is rejected with an
 * empty string, so the handlers never build a path from an unvalidated value.
 *
 * @param string $raw Raw transfer id from the request body.
 * @return string The validated id, or '' when invalid.
 */
function emcp_connector_safe_transfer_id( string $raw ): string {
	return preg_match( '/^[A-Za-z0-9_-]{8,64}$/', $raw ) ? $raw : '';
}

/** Incoming transfer directory. */
function emcp_connector_transfer_dir( string $transfer_id ): string {
	$dir = emcp_connector_base_dir() . '/incoming/' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $transfer_id );
	wp_mkdir_p( $dir );
	return $dir;
}

/** Chunk file path for a transfer index. */
function emcp_connector_chunk_path( string $transfer_id, int $index ): string {
	return emcp_connector_transfer_dir( $transfer_id ) . '/' . $index . '.chunk';
}

/** Count chunks already stored for a transfer. */
function emcp_connector_chunk_count( string $transfer_id ): int {
	$files = glob( emcp_connector_transfer_dir( $transfer_id ) . '/*.chunk' );
	return $files ? count( $files ) : 0;
}

/** Current connector version. */
function emcp_connector_version(): string {
	return '1.2.0';
}

/** Connector capabilities reported by /status and pairing. */
function emcp_connector_capabilities(): array {
	return array( 'packet', 'restore', 'pair', 'scope-restore' );
}

/** The stored single-use pairing-code record, or null when none is pending. */
function emcp_connector_pair_code(): ?array {
	$rec = get_option( 'emcp_connector_pair_code' );
	return is_array( $rec ) ? $rec : null;
}

/**
 * Issue a fresh single-use pairing code (15-minute TTL). Replaces any pending
 * code. Returns array{code, ttl_seconds, expires_at} or a WP_Error when no
 * secret is configured (there is nothing to pair against).
 */
function emcp_connector_issue_pair_code() {
	if ( '' === emcp_connector_secret() ) {
		return new WP_Error( 'not_configured', 'No connector secret configured to pair against', array( 'status' => 403 ) );
	}
	$code = 'EMCP-PAIR-' . bin2hex( random_bytes( 16 ) );
	update_option( 'emcp_connector_pair_code', array(
		'hash'     => hash( 'sha256', $code ),
		'expiry'   => time() + 900,
		'used'     => false,
		'failures' => 0,
	), false );
	return array(
		'code'        => $code,
		'ttl_seconds' => 900,
		'expires_at'  => gmdate( 'Y-m-d\TH:i:s\Z', time() + 900 ),
	);
}

/**
 * Redeem a single-use pairing code for the configured secret. Marks the code
 * used on success; counts failures and invalidates the code after 5.
 *
 * @param string $code The code from /pair/issue or the admin page.
 * @return string|\WP_Error The connector secret.
 */
function emcp_connector_consume_pair_code( string $code ) {
	$rec = emcp_connector_pair_code();
	if ( ! $rec || empty( $rec['hash'] ) ) {
		return new WP_Error( 'code_invalid', 'No pairing code is pending on this destination', array( 'status' => 403 ) );
	}
	if ( (int) ( $rec['expiry'] ?? 0 ) < time() || ! empty( $rec['used'] ) ) {
		delete_option( 'emcp_connector_pair_code' );
		return new WP_Error( 'code_invalid', 'Pairing code expired or already used', array( 'status' => 403 ) );
	}
	if ( ! hash_equals( (string) $rec['hash'], hash( 'sha256', $code ) ) ) {
		$failures = (int) ( $rec['failures'] ?? 0 ) + 1;
		if ( $failures >= 5 ) {
			delete_option( 'emcp_connector_pair_code' );
			return new WP_Error( 'code_invalid', 'Too many failed pairing attempts', array( 'status' => 403 ) );
		}
		$rec['failures'] = $failures;
		update_option( 'emcp_connector_pair_code', $rec, false );
		return new WP_Error( 'code_invalid', 'Invalid pairing code', array( 'status' => 403 ) );
	}
	delete_option( 'emcp_connector_pair_code' );
	return emcp_connector_secret();
}

/**
 * HTTPS (or loopback) gate for the secret-carrying exchange route.
 *
 * Loopback is decided from server-side state — the site's configured home_url()
 * host, then $_SERVER['SERVER_ADDR'] — never the client-supplied Host header.
 */
function emcp_connector_pair_exchange_allowed(): bool {
	if ( is_ssl() ) {
		return true;
	}
	$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( in_array( $home, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
		return true;
	}
	$addr = isset( $_SERVER['SERVER_ADDR'] ) ? trim( (string) $_SERVER['SERVER_ADDR'], '[]' ) : '';
	return in_array( $addr, array( '127.0.0.1', '::1' ), true );
}

/** POST /pair/issue (HMAC) — issue a single-use code (for scripting). */
function emcp_connector_handle_pair_issue() {
	$issued = emcp_connector_issue_pair_code();
	if ( is_wp_error( $issued ) ) {
		return $issued;
	}
	return rest_ensure_response( $issued );
}

/** POST /pair/exchange (open) — redeem a code for the secret, HTTPS-gated. */
function emcp_connector_handle_pair_exchange( WP_REST_Request $request ) {
	if ( '' === emcp_connector_secret() ) {
		return new WP_Error( 'not_configured', 'No connector secret configured on this destination', array( 'status' => 403 ) );
	}
	if ( ! emcp_connector_pair_exchange_allowed() ) {
		return new WP_Error( 'https_required', 'Pairing exchange must run over HTTPS (or a loopback host)', array( 'status' => 403 ) );
	}
	$body   = $request->get_json_params();
	$code   = isset( $body['code'] ) ? (string) $body['code'] : '';
	$secret = emcp_connector_consume_pair_code( $code );
	if ( is_wp_error( $secret ) ) {
		return $secret;
	}
	return rest_ensure_response( array(
		'secret'       => $secret,
		'site'         => home_url(),
		'version'      => emcp_connector_version(),
		'capabilities' => emcp_connector_capabilities(),
	) );
}

/** GET /verify (HMAC) — prove a stored secret signs on this destination. */
function emcp_connector_handle_verify() {
	return rest_ensure_response( array(
		'verified'     => true,
		'site'         => home_url(),
		'version'      => emcp_connector_version(),
		'capabilities' => emcp_connector_capabilities(),
	) );
}

add_action( 'rest_api_init', function () {
	$namespace = 'emcp-connector/v1';

	register_rest_route( $namespace, '/status', array(
		'methods'             => 'GET',
		'callback'            => function () {
			return rest_ensure_response( array(
				'active'       => true,
				'site'         => home_url(),
				'version'      => emcp_connector_version(),
				'configured'   => ( '' !== emcp_connector_secret() ),
				'capabilities' => emcp_connector_capabilities(),
			) );
		},
		'permission_callback' => '__return_true',
	) );

	register_rest_route( $namespace, '/packet', array(
		'methods'             => 'POST',
		'callback'            => 'emcp_connector_handle_packet',
		'permission_callback' => 'emcp_connector_authed',
	) );

	register_rest_route( $namespace, '/finalize', array(
		'methods'             => 'POST',
		'callback'            => 'emcp_connector_handle_finalize',
		'permission_callback' => 'emcp_connector_authed',
	) );

	register_rest_route( $namespace, '/job/(?P<id>[a-zA-Z0-9_-]+)', array(
		'methods'             => 'GET',
		'callback'            => 'emcp_connector_handle_job',
		'permission_callback' => 'emcp_connector_authed',
	) );

	register_rest_route( $namespace, '/pair/issue', array(
		'methods'             => 'POST',
		'callback'            => 'emcp_connector_handle_pair_issue',
		'permission_callback' => 'emcp_connector_authed',
	) );

	register_rest_route( $namespace, '/pair/exchange', array(
		'methods'             => 'POST',
		'callback'            => 'emcp_connector_handle_pair_exchange',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( $namespace, '/verify', array(
		'methods'             => 'GET',
		'callback'            => 'emcp_connector_handle_verify',
		'permission_callback' => 'emcp_connector_authed',
	) );
} );

/** Receive one chunk; idempotent per index. */
function emcp_connector_handle_packet( WP_REST_Request $request ) {
	$body = $request->get_json_params();
	$transfer_id = emcp_connector_safe_transfer_id( isset( $body['transfer_id'] ) ? (string) $body['transfer_id'] : '' );
	$index       = isset( $body['index'] ) ? (int) $body['index'] : -1;
	$data_b64    = isset( $body['data_b64'] ) ? (string) $body['data_b64'] : '';
	$chunk_sha   = isset( $body['sha256'] ) ? (string) $body['sha256'] : '';

	if ( '' === $transfer_id || $index < 0 || '' === $data_b64 ) {
		return new WP_Error( 'bad_packet', 'A valid transfer_id, a non-negative index, and data_b64 are required', array( 'status' => 400 ) );
	}
	$chunk = base64_decode( $data_b64, true );
	if ( false === $chunk || '' === $chunk_sha || ! hash_equals( $chunk_sha, hash( 'sha256', $chunk ) ) ) {
		return new WP_Error( 'bad_packet', 'chunk failed its sha256 check', array( 'status' => 400 ) );
	}

	$path = emcp_connector_chunk_path( $transfer_id, $index );
	if ( is_file( $path ) ) {
		return rest_ensure_response( array( 'received' => $index, 'duplicate' => true ) );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions -- raw binary chunk.
	if ( false === file_put_contents( $path, $chunk ) ) {
		return new WP_Error( 'storage', 'could not store chunk', array( 'status' => 500 ) );
	}
	return rest_ensure_response( array( 'received' => $index, 'stored' => true, 'chunks' => emcp_connector_chunk_count( $transfer_id ) ) );
}

/** Assemble + verify the whole archive, then restore it. */
function emcp_connector_handle_finalize( WP_REST_Request $request ) {
	if ( '' === emcp_connector_secret() ) {
		return new WP_Error( 'not_configured', 'No EMCP_CONNECTOR_SECRET configured on this destination', array( 'status' => 403 ) );
	}
	$body         = $request->get_json_params();
	$transfer_id  = emcp_connector_safe_transfer_id( isset( $body['transfer_id'] ) ? (string) $body['transfer_id'] : '' );
	$whole_sha    = isset( $body['sha256'] ) ? (string) $body['sha256'] : '';
	if ( '' === $transfer_id || '' === $whole_sha ) {
		return new WP_Error( 'bad_request', 'A valid transfer_id and sha256 are required', array( 'status' => 400 ) );
	}

	$dir    = emcp_connector_transfer_dir( $transfer_id );
	$chunks = glob( $dir . '/*.chunk' );
	if ( ! $chunks ) {
		return new WP_Error( 'empty_transfer', 'no chunks received', array( 'status' => 400 ) );
	}
	// Concatenate in index order. $transfer_id passed emcp_connector_safe_transfer_id()
	// above (alnum/-/_ only, 8-64 chars) and the route is HMAC-gated, so it cannot
	// carry a path separator. // NOSONAR -- transfer store is resume-keyed by id.
	sort( $chunks, SORT_NATURAL );
	$assembled = emcp_connector_base_dir() . '/assembled-' . $transfer_id . '.emcp';
	// NOSONAR -- see whitelist rationale above: $transfer_id is validated, not raw input.
	$out       = fopen( $assembled, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $out ) {
		return new WP_Error( 'storage', 'could not open assembled archive', array( 'status' => 500 ) );
	}
	foreach ( $chunks as $chunk ) {
		fwrite( $out, file_get_contents( $chunk ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.WP.Capabilities -- local binary concat.
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	if ( ! hash_equals( $whole_sha, hash_file( 'sha256', $assembled ) ) ) {
		@unlink( $assembled ); // NOSONAR -- path from whitelisted transfer id (see above). phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		return new WP_Error( 'hash_mismatch', 'assembled archive failed its sha256 check', array( 'status' => 400 ) );
	}

	$job_id = 'emcp-' . wp_generate_password( 10, false );
	if ( function_exists( 'ignore_user_abort' ) ) {
		ignore_user_abort( true ); // Restore keeps running if the source disconnects.
	}
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 0 ); // phpcs:ignore WordPress.PHP -- batch restore.
	}

	$stats = emcp_connector_restore_archive( $assembled );
	$stats['job_id']    = $job_id;
	$stats['state']     = empty( $stats['errors'] ) ? 'done' : 'error';
	update_option( 'emcp_connector_job_' . $job_id, $stats, false );

	// Clean incoming transfer.
	foreach ( $chunks as $chunk ) {
		@unlink( $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
	}
	@unlink( $assembled ); // NOSONAR -- path from whitelisted transfer id (see above). phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
	@rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors

	return rest_ensure_response( array( 'job_id' => $job_id, 'state' => $stats['state'] ) );
}

/** Poll a completed restore job. */
function emcp_connector_handle_job( WP_REST_Request $request ) {
	$id    = (string) $request['id'];
	$stats = get_option( 'emcp_connector_job_' . $id, null );
	if ( ! is_array( $stats ) ) {
		return new WP_Error( 'not_found', 'unknown job', array( 'status' => 404 ) );
	}
	return rest_ensure_response( $stats );
}

// ---------------------------------------------------------------------------
// Self-contained restore (unzip -> import -> search-replace -> place files).
// Zero EMCP plugin dependencies — only WP core + $wpdb.
// ---------------------------------------------------------------------------

/** Extract one complete statement (ending at a top-level ';') from a buffer. */
function emcp_connector_extract_statement( string &$buffer ) { // NOSONAR -- a per-character SQL quote/comment state machine has no meaningful decomposition below its branch table.
	$len = strlen( $buffer );
	$i   = 0;
	$sq = $dq = $bt = $lc = $bc = false;
	while ( $i < $len ) {
		$c = $buffer[ $i ];
		if ( $lc ) {
			if ( "\n" === $c ) { $lc = false; }
		} elseif ( $bc ) {
			if ( '*' === $c && isset( $buffer[ $i + 1 ] ) && '/' === $buffer[ $i + 1 ] ) { $bc = false; $i++; }
		} elseif ( $sq || $dq || $bt ) {
			if ( '\\' === $c ) {
				$i++;
			} elseif ( $sq && "'" === $c ) { $sq = false; }
			elseif ( $dq && '"' === $c ) { $dq = false; }
			elseif ( $bt && '`' === $c ) { $bt = false; }
		} else {
			if ( "'" === $c ) { $sq = true; }
			elseif ( '"' === $c ) { $dq = true; }
			elseif ( '`' === $c ) { $bt = true; }
			elseif ( '-' === $c && isset( $buffer[ $i + 1 ] ) && '-' === $buffer[ $i + 1 ] ) { $lc = true; $i++; }
			elseif ( '/' === $c && isset( $buffer[ $i + 1 ] ) && '*' === $buffer[ $i + 1 ] ) { $bc = true; $i++; }
			elseif ( ';' === $c ) {
				$stmt   = substr( $buffer, 0, $i + 1 );
				$buffer = substr( $buffer, $i + 1 );
				return $stmt;
			}
		}
		$i++;
	}
	return null;
}

/** Skip session transaction-control directives (never run on the live connection). */
function emcp_connector_is_skip( string $stmt ): bool {
	$upper = strtoupper( $stmt );
	foreach ( array( 'SET AUTOCOMMIT', 'START TRANSACTION', 'BEGIN', 'COMMIT', 'ROLLBACK' ) as $prefix ) {
		if ( 0 === strpos( $upper, $prefix ) ) {
			return true;
		}
	}
	return false;
}

/** Import a dump through the destination $wpdb. */
function emcp_connector_import( string $file ) {
	$stats = array( 'statements' => 0, 'executed' => 0, 'skipped' => 0, 'errors' => 0, 'error_details' => array() );
	$fh    = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $fh ) {
		$stats['errors'] = 1;
		return $stats;
	}
	$buffer = '';
	while ( false !== ( $line = fgets( $fh ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		$buffer .= $line;
		while ( null !== ( $stmt = emcp_connector_extract_statement( $buffer ) ) ) {
			$stats['statements']++;
			$trim = trim( $stmt );
			if ( emcp_connector_is_skip( $trim ) ) {
				$stats['skipped']++;
				continue;
			}
			emcp_connector_run_dump_statement( $trim, $stats );
		}
	}
	$buffer = trim( $buffer );
	if ( '' !== $buffer && 0 !== strpos( $buffer, '--' ) && ! emcp_connector_is_skip( $buffer ) ) {
		$stats['statements']++;
		emcp_connector_run_dump_statement( $buffer, $stats );
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $stats;
}

/**
 * Execute one dump statement through the live connection, updating stats.
 *
 * @param string $sql   Trimmed statement (directives already filtered).
 * @param array  $stats In/out counters.
 */
function emcp_connector_run_dump_statement( string $sql, array &$stats ): void {
	global $wpdb;
	$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB -- dump statement, connector restore.
	if ( false === $result ) {
		$stats['errors']++;
		if ( count( $stats['error_details'] ) < 10 ) {
			$stats['error_details'][] = $wpdb->last_error;
		}
	} else {
		$stats['executed']++;
	}
}

/** Byte-accurate replacement over a raw string (plain + escaped JSON URL pairs). */
function emcp_connector_pair_replace( string $value, string $search, string $replace ): string {
	if ( '' === $search || '' === $value || $search === $replace ) {
		return $value;
	}
	$search_esc  = str_replace( '/', '\\/', $search );
	$replace_esc = str_replace( '/', '\\/', $replace );
	if ( false !== strpos( $value, $search_esc ) ) {
		$value = str_replace( $search_esc, $replace_esc, $value );
	}
	if ( false !== strpos( $value, $search ) ) {
		$value = str_replace( $search, $replace, $value );
	}
	return $value;
}

/** Rewrite serialized s:LEN tokens without unserializing (length-delta method). */
function emcp_connector_fix_serialized( string $value, string $search, string $replace ): string {
	if ( '' === $search || $search === $replace ) {
		return $value;
	}
	$delta = strlen( $replace ) - strlen( $search );
	return preg_replace_callback(
		'/s:(\d+):("((?:[^"\\\\]|\\\\.)*)")/',
		static function ( array $m ) use ( $search, $replace, $delta ): string {
			$inner = $m[3];
			if ( false === strpos( $inner, $search ) ) {
				return $m[0];
			}
			$count = substr_count( $inner, $search );
			return 's:' . ( (int) $m[1] + ( $count * $delta ) ) . ':"' . str_replace( $search, $replace, $inner ) . '"';
		},
		$value
	);
}

/** Dispatch a DB value through the serialized/JSON-aware replacer. */
function emcp_connector_replace_value( $value, string $search, string $replace ) {
	if ( is_string( $value ) && '' !== $value ) {
		return is_serialized( $value )
			? emcp_connector_fix_serialized( $value, $search, $replace )
			: emcp_connector_pair_replace( $value, $search, $replace );
	}
	return $value;
}

/**
 * Search-replace URL across the destination's data tables.
 *
 * @param string        $old_url Source URL.
 * @param string        $new_url Destination URL.
 * @param string[]|null $only    Optional table allowlist (archived tables).
 * @return int Rows rewritten.
 */
function emcp_connector_search_replace( string $old_url, string $new_url, ?array $only = null ): array {
	if ( '' === $old_url || $old_url === $new_url ) {
		return array( 'rows' => 0, 'partial' => false );
	}
	global $wpdb;
	$result  = array( 'rows' => 0, 'partial' => false );
	foreach ( emcp_connector_data_tables( $only ) as $table ) {
		$pks = $wpdb->get_results( "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'" ); // phpcs:ignore WordPress.DB
		if ( count( $pks ) !== 1 ) {
			continue; // Composite/no-PK tables are skipped — rows can't be addressed safely.
		}
		$pk        = (string) $pks[0]->Column_name;
		$text_cols = emcp_connector_text_columns( $table );
		if ( empty( $text_cols ) ) {
			continue;
		}
		$per_table = emcp_connector_rewrite_rows( $table, $pk, $text_cols, $old_url, $new_url );
		$result['rows']    += $per_table['rows'];
		$result['partial'] = $result['partial'] || $per_table['partial'];
	}
	return $result;
}

/**
 * Text-bearing tables the search-replace walks (all prefixed tables minus
 * operational ones whose values are paths/session data). Pass $only to restrict
 * the walk to an allowlist (the tables actually imported from a scoped archive).
 *
 * @param string[]|null $only Optional table allowlist.
 * @return string[] Table names.
 */
function emcp_connector_data_tables( ?array $only = null ): array {
	global $wpdb;
	$exclude = array(
		$wpdb->prefix . 'emcp_connector_transfers',
		$wpdb->prefix . 'emcp_redirects',
		$wpdb->prefix . 'emcp_migrate_targets',
		$wpdb->prefix . 'emcp_migrate_backups',
		$wpdb->prefix . 'emcp_migrate_jobs',
		$wpdb->prefix . 'woocommerce_sessions',
		$wpdb->prefix . 'actionscheduler_actions',
		$wpdb->prefix . 'actionscheduler_logs',
	);
	$tables = array( $wpdb->options, $wpdb->posts, $wpdb->postmeta, $wpdb->comments, $wpdb->commentmeta, $wpdb->termmeta, $wpdb->usermeta );
	if ( $wpdb->links ) {
		$tables[] = $wpdb->links;
	}
	foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $table ) { // phpcs:ignore WordPress.DB
		if ( 0 === strpos( $table, $wpdb->prefix ) && ! in_array( $table, $exclude, true ) && ! in_array( $table, $tables, true ) ) {
			$tables[] = $table;
		}
	}
	if ( is_array( $only ) && $only ) {
		$only   = array_map( 'strval', $only );
		$tables = array_values( array_intersect( $tables, $only ) );
	}
	return $tables;
}

/**
 * char/text/json columns of a table (the only ones worth rewriting).
 *
 * @param string $table Table name.
 * @return string[] Column names.
 */
function emcp_connector_text_columns( string $table ): array {
	global $wpdb;
	$text_cols = array();
	foreach ( (array) $wpdb->get_results( "DESCRIBE `{$table}`" ) as $col ) { // phpcs:ignore WordPress.DB -- identifier quoted.
		$type = strtolower( (string) $col->Type );
		if ( false !== strpos( $type, 'char' ) || false !== strpos( $type, 'text' ) || false !== strpos( $type, 'json' ) ) {
			$text_cols[] = $col->Field;
		}
	}
	return $text_cols;
}

/**
 * Fetch rows of one table containing $old_url and rewrite the changed columns.
 *
 * @param string   $table    Table name.
 * @param string   $pk       Single-column primary key.
 * @param string[] $text_cols Text columns to scan/rewrite.
 * @param string   $old_url  Source URL.
 * @param string   $new_url  Destination URL.
 * @return array  { rows: int, partial: bool } — partial when more than the
 *                per-table cap (5000) matched and were left for a later run.
 */
function emcp_connector_rewrite_rows( string $table, string $pk, array $text_cols, string $old_url, string $new_url ): array {
	global $wpdb;
	$like  = '%' . $wpdb->esc_like( $old_url ) . '%';
	$where = array();
	$args  = array();
	foreach ( $text_cols as $col ) {
		$where[] = "`{$col}` LIKE %s";
		$args[]  = $like;
	}
	$sql  = "SELECT `{$pk}`, `" . implode( '`, `', $text_cols ) . "` FROM `{$table}` WHERE (" . implode( ' OR ', $where ) . ') LIMIT 5001';
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB -- prepared; identifier-quoted.
	if ( null === $rows ) {
		return array( 'rows' => 0, 'partial' => false );
	}
	$partial = count( $rows ) > 5000;
	if ( $partial ) {
		$rows = array_slice( $rows, 0, 5000 );
	}

	$updated = 0;
	foreach ( $rows as $row ) {
		$changes = array();
		foreach ( $text_cols as $col ) {
			if ( ! isset( $row[ $col ] ) || ! is_string( $row[ $col ] ) ) {
				continue;
			}
			$new = emcp_connector_replace_value( $row[ $col ], $old_url, $new_url );
			if ( $new !== $row[ $col ] ) {
				$changes[ $col ] = $new;
			}
		}
		if ( $changes ) {
			$wpdb->update( $table, $changes, array( $pk => $row[ $pk ] ) ); // phpcs:ignore WordPress.DB -- keyed by PK.
			$updated++;
		}
	}
	return array( 'rows' => $updated, 'partial' => $partial );
}

/** Place files/ entries under wp-content (traversal-guarded). */
function emcp_connector_place_files( ZipArchive $zip ): int {
	$content = wp_normalize_path( WP_CONTENT_DIR );
	$placed  = 0;
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = (string) $zip->getNameIndex( $i );
		if ( 0 !== strpos( $name, 'files/' ) ) {
			continue;
		}
		$rel = substr( $name, 6 );
		if ( '' === $rel || 0 === strpos( $rel, 'wp-config.php' ) || false !== strpos( $rel, '..' ) ) {
			continue;
		}
		$dest = wp_normalize_path( $content . '/' . $rel );
		if ( 0 !== strpos( $dest, $content . '/' ) || is_dir( $dest ) ) {
			continue;
		}
		wp_mkdir_p( dirname( $dest ) );
		$src = $zip->getStream( $name );
		$dst = @fopen( $dest, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		if ( false === $src || false === $dst ) {
			if ( is_resource( $src ) ) { fclose( $src ); } // phpcs:ignore WordPress.WP.AlternativeFunctions
			continue;
		}
		stream_copy_to_stream( $src, $dst );
		fclose( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $dst ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$placed++;
	}
	return $placed;
}

/** Remove a directory tree (restore cleanup). */
function emcp_connector_cleanup( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		if ( $f->isDir() ) {
			@rmdir( $f->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		} else {
			@unlink( $f->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		}
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
}

/** Full restore of an assembled .emcp archive. */
function emcp_connector_restore_archive( string $archive ): array {
	$stats = array( 'state' => 'running', 'errors' => array(), 'files_placed' => 0, 'search_replace' => null, 'db' => null );
	if ( ! class_exists( 'ZipArchive' ) ) {
		$stats['errors'][] = 'zip_missing';
		return $stats;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $archive ) ) {
		$stats['errors'][] = 'zip_open_failed';
		return $stats;
	}
	$manifest_raw = $zip->getFromName( 'manifest.json' );
	if ( false === $manifest_raw ) {
		$zip->close();
		$stats['errors'][] = 'manifest_missing';
		return $stats;
	}
	$manifest = json_decode( $manifest_raw, true );
	if ( ! is_array( $manifest ) ) {
		$zip->close();
		$stats['errors'][] = 'manifest_invalid';
		return $stats;
	}

	$work = emcp_connector_base_dir() . '/restore-' . wp_generate_password( 8, false );
	wp_mkdir_p( $work );

	// Capture the destination URL BEFORE the import replaces wp_options — the
	// in-memory option cache usually survives, but we must not depend on it.
	$dest_url = home_url();

	// 1) DB (verify hash, then import).
	emcp_connector_restore_db( $zip, $manifest, $work, $stats );

	// 2) URL search-replace (source -> this destination). Runs only when a DB
	// dump was actually in the archive and imported ($did_db), even after a
	// partial import that had statement errors — those are reported under
	// $stats['db'], not fatal. For a scoped (selective-DB) archive, rewrite only
	// the archived tables, never destination-native ones.
	if ( empty( $stats['errors'] ) && isset( $stats['db'] ) && ! empty( $manifest['site_url'] ) ) {
		$scope_db = null;
		if ( isset( $manifest['scope']['db'] ) && is_array( $manifest['scope']['db'] ) ) {
			$scope_db = array_map( 'strval', $manifest['scope']['db'] );
		}
		$sr = emcp_connector_search_replace( (string) $manifest['site_url'], $dest_url, $scope_db );
		if ( $sr['rows'] > 0 ) {
			$stats['search_replace'] = array(
				'rows'    => $sr['rows'],
				'partial' => ! empty( $sr['partial'] ),
				'from'    => (string) $manifest['site_url'],
				'to'      => $dest_url,
			);
		}
	}

	// 3) Files.
	$stats['files_placed'] = emcp_connector_place_files( $zip );
	$zip->close();
	emcp_connector_cleanup( $work );

	return $stats;
}

/**
 * Stream database.sql out of the archive, verify its manifest hash, import it.
 *
 * @param \ZipArchive $zip      Open archive.
 * @param array       $manifest Parsed manifest.
 * @param string      $work     Work directory.
 * @param array       $stats    In/out restore stats.
 */
function emcp_connector_restore_db( ZipArchive $zip, array $manifest, string $work, array &$stats ): void {
	// Cross-prefix guard: dumps write literal table names, so an archive from a
	// different DB prefix would import foreign-prefixed tables here.
	if ( ! empty( $manifest['db_prefix'] ) ) {
		global $wpdb;
		if ( (string) $manifest['db_prefix'] !== (string) $wpdb->prefix ) {
			$stats['errors'][] = 'prefix_mismatch';
			return;
		}
	}
	$src = $zip->getStream( 'database.sql' );
	if ( false === $src ) {
		// No database.sql in the archive — a files-only (or DB-scoped-out) sync.
		// Absence of a DB is not an error; search-replace is gated on $stats['db']
		// below. A manifest that CLAIMS a dump but cannot stream one is truncation.
		if ( ! empty( $manifest['database_sha256'] ) ) {
			$stats['errors'][] = 'db_stream_failed';
		}
		return;
	}
	$db_file = $work . '/database.sql';
	$dst     = fopen( $db_file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $dst ) {
		fclose( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$stats['errors'][] = 'db_stream_failed';
		return;
	}
	stream_copy_to_stream( $src, $dst );
	fclose( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fclose( $dst ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	$expected = isset( $manifest['database_sha256'] ) ? $manifest['database_sha256'] : '';
	$actual   = hash_file( 'sha256', $db_file );
	// A missing manifest hash is not an integrity failure (older/foreign
	// archives): import proceeds. A present-but-mismatched hash aborts. Import
	// statement errors stay under $stats['db'] (visible, non-fatal) so a
	// partial import still gets the URL rewrite; $stats['errors'] is reserved
	// for fatal failures (stream/hash), which drive the job state.
	if ( '' !== $expected && ! hash_equals( $expected, $actual ) ) {
		$stats['errors'][] = 'hash_mismatch';
		return;
	}
	$stats['db'] = emcp_connector_import( $db_file );
}

// ---------------------------------------------------------------------------
// Minimal admin page: configure the option secret + issue pairing codes.
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_options_page(
		'EMCP Connector',
		'EMCP Connector',
		'manage_options',
		'emcp-connector',
		'emcp_connector_admin_page'
	);
} );

add_action( 'admin_post_emcp_connector_admin', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not permitted', 'emcp-connector' ) );
	}
	check_admin_referer( 'emcp_connector_admin' );
	$action = isset( $_POST['connector_action'] ) ? sanitize_key( wp_unslash( $_POST['connector_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- check_admin_referer above.
	if ( 'issue_code' === $action ) {
		$issued = emcp_connector_issue_pair_code();
		if ( is_wp_error( $issued ) ) {
			set_transient( 'emcp_connector_admin_notice', array( 'type' => 'error', 'message' => $issued->get_error_message() ), 60 );
		} else {
			/* translators: %s: the pairing code. */
			set_transient( 'emcp_connector_admin_notice', array( 'type' => 'success', 'message' => sprintf( 'Pairing code issued (single use, 15 minutes): %s', $issued['code'] ) ), 60 );
		}
	} elseif ( 'save_secret' === $action ) {
		if ( defined( 'EMCP_CONNECTOR_SECRET' ) && EMCP_CONNECTOR_SECRET ) {
			set_transient( 'emcp_connector_admin_notice', array( 'type' => 'error', 'message' => 'The secret is defined by EMCP_CONNECTOR_SECRET in wp-config.php and cannot be changed here.' ), 60 );
		} else {
			// Store the secret verbatim (wp_unslash only): it is used byte-for-byte
			// for HMAC and returned unchanged by pairing, so sanitize_text_field()
			// must not rewrite whitespace or special characters.
			$secret = isset( $_POST['secret'] ) ? (string) wp_unslash( $_POST['secret'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- check_admin_referer above, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verbatim secret.
			update_option( 'emcp_connector_secret', $secret, false );
			set_transient( 'emcp_connector_admin_notice', array( 'type' => 'success', 'message' => 'Connector secret saved.' ), 60 );
		}
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=emcp-connector' ) );
	exit;
} );

/** Render the connector admin page. */
function emcp_connector_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$notice = get_transient( 'emcp_connector_admin_notice' );
	if ( is_array( $notice ) ) {
		delete_transient( 'emcp_connector_admin_notice' );
	}
	$secret_defined = defined( 'EMCP_CONNECTOR_SECRET' ) && EMCP_CONNECTOR_SECRET;
	$configured     = '' !== emcp_connector_secret();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'EMCP Tools Connector', 'emcp-connector' ); ?></h1>
		<?php if ( is_array( $notice ) ) : ?>
			<div class="notice notice-<?php echo 'error' === $notice['type'] ? 'error' : 'success'; ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>
		<p><?php echo esc_html( 'Version ' . emcp_connector_version() . '. This site receives .emcp backups pushed from an EMCP Tools source.' ); ?></p>

		<div class="card" style="max-width: 640px;">
			<h2><?php esc_html_e( 'Secret', 'emcp-connector' ); ?></h2>
			<?php if ( $configured ) : ?>
				<p><strong><?php esc_html_e( 'Configured', 'emcp-connector' ); ?></strong>
					<?php echo $secret_defined ? esc_html__( '(from wp-config.php)', 'emcp-connector' ) : esc_html__( '(stored option)', 'emcp-connector' ); ?>
				</p>
			<?php else : ?>
				<p><strong style="color:#b32d2e"><?php esc_html_e( 'Not configured', 'emcp-connector' ); ?></strong>
					<?php esc_html_e( '— pushes are refused until a secret is set.', 'emcp-connector' ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! $secret_defined ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'emcp_connector_admin' ); ?>
					<input type="hidden" name="action" value="emcp_connector_admin" />
					<input type="hidden" name="connector_action" value="save_secret" />
					<p>
						<label for="emcp_connector_secret"><?php esc_html_e( 'Shared secret', 'emcp-connector' ); ?></label><br>
						<input type="password" name="secret" id="emcp_connector_secret" class="regular-text" autocomplete="off" />
					</p>
					<?php submit_button( __( 'Save secret', 'emcp-connector' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>

		<div class="card" style="max-width: 640px;">
			<h2><?php esc_html_e( 'Pair a source', 'emcp-connector' ); ?></h2>
			<p><?php esc_html_e( 'Generate a single-use pairing code (valid 15 minutes) and send it to the source operator out-of-band. The source exchanges it for the shared secret once, over HTTPS.', 'emcp-connector' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'emcp_connector_admin' ); ?>
				<input type="hidden" name="action" value="emcp_connector_admin" />
				<input type="hidden" name="connector_action" value="issue_code" />
				<?php submit_button( __( 'Generate pairing code', 'emcp-connector' ), 'primary', 'submit', false, $configured ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
		</div>
	</div>
	<?php
}
