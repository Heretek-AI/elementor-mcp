<?php
/**
 * Paired migration targets — the {prefix}emcp_migrate_targets table plus CRUD
 * and single-use pairing-code redemption.
 *
 * A "target" is a destination site running the standalone EMCP connector. The
 * source stores one row per pair; the shared connector secret is held encrypted
 * (AES-256-CBC, key derived from the site salts — byte-identical derivation to
 * EMCP_Tools_Key_Crypto so ciphertext is portable between the two) and is never
 * returned by any list API or tool output. Engines decrypt it only to sign the
 * HMAC transfer headers.
 *
 * Pairing: the destination operator issues a single-use, 15-minute code from the
 * connector's own admin page; the source operator enters it here. redeem() posts
 * it to the connector's /pair/exchange route (the one instant the secret crosses
 * the wire), stores the returned secret encrypted, then proves it with a signed
 * GET /verify round-trip.
 *
 * Follows the EMCP_Tools_Redirect_Store / Search_Index storage pattern
 * (version-gated dbDelta, DB_VERSION const + option, maybe_install() on init).
 *
 * @package EMCP_Tools
 * @since   3.16.0
 */

defined( 'ABSPATH' ) || exit;

class EMCP_Tools_Migrate_Targets {

	const DB_VERSION        = 1;
	const DB_VERSION_OPTION = 'emcp_tools_migrate_targets_db_version';
	const SELECT_ALL        = 'SELECT * FROM ';

	/** The targets table name. */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'emcp_migrate_targets';
	}

	/** Register the install hook. */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 20 );
	}

	/** Create/upgrade the table when the stored version is behind. */
	public static function maybe_install(): void {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) >= self::DB_VERSION ) {
			return;
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( is_readable( $upgrade ) ) {
				require_once $upgrade;
			}
		}
		if ( function_exists( 'dbDelta' ) ) {
			global $wpdb;
			$table   = self::table();
			$charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
			$sql     = "CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				label VARCHAR(191) NOT NULL,
				target_url VARCHAR(255) NOT NULL,
				endpoint VARCHAR(255) NOT NULL,
				secret_cipher TEXT NOT NULL,
				site_url VARCHAR(255) NOT NULL DEFAULT '',
				connector_version VARCHAR(20) NOT NULL DEFAULT '',
				confirmed_at DATETIME NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY created_at_idx (created_at)
			) {$charset};";
			dbDelta( $sql );
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/** Cheap version-guarded install for reads that may precede init:20. */
	private static function ensure(): void {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) < self::DB_VERSION ) {
			self::maybe_install();
		}
	}

	/**
	 * Store a paired target. The secret is always encrypted before insert.
	 *
	 * @param array $data { label, target_url, secret, site_url?, connector_version? }.
	 * @return int|\WP_Error Row id.
	 */
	public static function add( array $data ) {
		self::ensure();

		$label      = sanitize_text_field( (string) ( $data['label'] ?? '' ) );
		$target_url = esc_url_raw( trim( (string) ( $data['target_url'] ?? '' ) ) );
		$secret     = (string) ( $data['secret'] ?? '' );

		if ( '' === $label ) {
			return new WP_Error( 'label_required', __( 'A label for the paired target is required.', 'emcp-tools' ) );
		}
		if ( '' === $target_url || ! wp_http_validate_url( $target_url ) ) {
			return new WP_Error( 'invalid_target_url', __( 'A valid destination URL is required.', 'emcp-tools' ) );
		}
		if ( '' === $secret ) {
			return new WP_Error( 'secret_required', __( 'The connector shared secret is required.', 'emcp-tools' ) );
		}
		if ( self::find_by_url( $target_url ) ) {
			return new WP_Error( 'duplicate_target', __( 'A paired target for this URL already exists.', 'emcp-tools' ) );
		}

		$now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		global $wpdb;
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'label'             => $label,
				'target_url'        => $target_url,
				'endpoint'          => self::normalize_endpoint( $target_url ),
				'secret_cipher'     => self::encrypt( $secret ),
				'site_url'          => sanitize_text_field( (string) ( $data['site_url'] ?? '' ) ),
				'connector_version' => sanitize_text_field( (string) ( $data['connector_version'] ?? '' ) ),
				'confirmed_at'      => $now,
				'created_at'        => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'insert_failed', __( 'Could not store the paired target.', 'emcp-tools' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Get one target row by id. The row carries secret_cipher (encrypted) — never
	 * plaintext. For HMAC signing use get_secret().
	 *
	 * @param int $id Target id.
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		self::ensure();
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( self::SELECT_ALL . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $row ? self::cast( $row ) : null;
	}

	/** Delete a paired target by id. */
	public static function delete( int $id ): bool {
		self::ensure();
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/** All target rows (encrypted secret included; not for display). */
	public static function all(): array {
		self::ensure();
		global $wpdb;
		$rows = $wpdb->get_results( self::SELECT_ALL . self::table() . ' ORDER BY created_at DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? array_map( array( __CLASS__, 'cast' ), $rows ) : array();
	}

	/**
	 * Safe projection of the target list for admin/MCP output — no cipher, no
	 * secrets. Engines resolve plaintext via get_secret() on demand.
	 *
	 * @return array[] Each row: id, label, target_url, endpoint, site_url,
	 *                 connector_version, confirmed_at, created_at.
	 */
	public static function list_for_admin(): array {
		return array_map(
			static function ( array $row ): array {
				return self::admin_row( $row );
			},
			self::all()
		);
	}

	/**
	 * Safe projection of ONE target row (admin/MCP output) — no cipher, no
	 * secrets. Accepts a raw row or an id.
	 *
	 * @param array|int $row Raw row, or a target id.
	 * @return array|null
	 */
	public static function admin_row( $row ): ?array {
		if ( is_int( $row ) || ( is_string( $row ) && ctype_digit( (string) $row ) ) ) {
			$row = self::get( (int) $row );
		}
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			return null;
		}
		return array(
			'id'                => (int) $row['id'],
			'label'             => (string) $row['label'],
			'target_url'        => (string) $row['target_url'],
			'endpoint'          => (string) $row['endpoint'],
			'site_url'          => (string) $row['site_url'],
			'connector_version' => (string) $row['connector_version'],
			'confirmed_at'      => (string) $row['confirmed_at'],
			'created_at'        => (string) $row['created_at'],
		);
	}

	/**
	 * Decrypt a target's secret for HMAC signing only. Never echoed, listed, or
	 * serialized into tool output.
	 *
	 * @param int $id Target id.
	 * @return string Decrypted secret, or '' when the row is missing/undecryptable.
	 */
	public static function get_secret( int $id ): string {
		$row = self::get( $id );
		if ( ! $row ) {
			return '';
		}
		return self::decrypt( (string) $row['secret_cipher'] );
	}

	/** Find a target row by its destination URL (exact match). */
	public static function find_by_url( string $url ): ?array {
		self::ensure();
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( self::SELECT_ALL . self::table() . ' WHERE target_url = %s LIMIT 1', esc_url_raw( trim( $url ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $row ? self::cast( $row ) : null;
	}

	/**
	 * Source half of pairing: exchange a single-use destination code for the
	 * connector secret, store it encrypted, and prove it with a signed /verify.
	 *
	 * @param string $target_url Destination site URL (the connector is there).
	 * @param string $code       Single-use pairing code from the connector.
	 * @param string $label      Optional human label (defaults to the host).
	 * @return array|\WP_Error The stored target row (admin projection).
	 */
	public static function redeem_pairing_code( string $target_url, string $code, string $label = '' ) {
		$target_url = esc_url_raw( trim( $target_url ) );
		$code       = trim( $code );
		if ( '' === $target_url || ! wp_http_validate_url( $target_url ) ) {
			return new WP_Error( 'invalid_target_url', __( 'A valid destination URL is required.', 'emcp-tools' ) );
		}
		if ( '' === $code ) {
			return new WP_Error( 'code_required', __( 'The single-use pairing code is required.', 'emcp-tools' ) );
		}
		if ( ! self::is_https_or_loopback( $target_url ) ) {
			return new WP_Error( 'https_required', __( 'Pairing exchanges the connector secret — the destination must be reached over HTTPS (or be localhost).', 'emcp-tools' ) );
		}

		$exchange = self::pair_exchange( self::normalize_endpoint( $target_url ), $code );
		if ( is_wp_error( $exchange ) ) {
			return $exchange;
		}

		if ( '' === $label ) {
			$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $target_url ) : parse_url( $target_url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			$label = isset( $parts['host'] ) ? (string) $parts['host'] : $target_url;
		}

		$id = self::add( array(
			'label'             => $label,
			'target_url'        => $target_url,
			'secret'            => $exchange['secret'],
			'site_url'          => $exchange['site'],
			'connector_version' => $exchange['version'],
		) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( ! self::verify_endpoint( self::normalize_endpoint( $target_url ), $exchange['secret'] ) ) {
			self::delete( $id );
			return new WP_Error( 'verify_failed', __( 'The destination did not accept the stored secret — the pair was removed.', 'emcp-tools' ) );
		}

		$row = self::admin_row( $id );
		return $row ? $row : array();
	}

	/**
	 * Exchange a pairing code for the connector secret (the single instant the
	 * secret crosses the wire).
	 *
	 * @param string $endpoint Connector REST base.
	 * @param string $code     Single-use pairing code.
	 * @return array{secret:string,site:string,version:string}|\WP_Error
	 */
	private static function pair_exchange( string $endpoint, string $code ) {
		$response = wp_remote_post(
			$endpoint . '/pair/exchange',
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'code'   => $code,
					'source' => function_exists( 'home_url' ) ? home_url() : '',
				) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'pair_failed', sprintf( /* translators: %s: error message. */ __( 'Pairing request failed: %s', 'emcp-tools' ), $response->get_error_message() ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) || empty( $body['secret'] ) ) {
			$msg = is_array( $body ) && isset( $body['message'] ) ? (string) $body['message'] : sprintf( 'HTTP %d', $status );
			return new WP_Error( 'pair_rejected', sprintf( /* translators: %s: message. */ __( 'The destination rejected the pairing code: %s', 'emcp-tools' ), $msg ) );
		}
		return array(
			'secret' => (string) $body['secret'],
			'site'   => isset( $body['site'] ) ? (string) $body['site'] : '',
			'version' => isset( $body['version'] ) ? (string) $body['version'] : '',
		);
	}

	/**
	 * Prove a secret signs on the destination (signed GET /verify). Shared by
	 * pairing (redeem_pairing_code) and the admin Verify-target action.
	 *
	 * @param string $endpoint Connector REST base.
	 * @param string $secret   Connector secret.
	 * @return bool
	 */
	public static function verify_endpoint( string $endpoint, string $secret ): bool {
		$response = wp_remote_get(
			$endpoint . '/verify',
			array(
				'timeout' => 30,
				'headers' => array( 'X-EMCP-Signature' => hash_hmac( 'sha256', 'verify|', $secret ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	// ---------------------------------------------------------------------
	// Internals
	// ---------------------------------------------------------------------

	/** Cast a raw DB row to typed values. */
	private static function cast( array $row ): array {
		$row['id'] = (int) ( $row['id'] ?? 0 );
		return $row;
	}

	/** Derive the connector REST base for a destination URL. */
	private static function normalize_endpoint( string $target_url ): string {
		return untrailingslashit( $target_url ) . '/wp-json/emcp-connector/v1';
	}

	/**
	 * HTTPS (or loopback) gate for pairing: the secret only ever crosses the wire
	 * to a destination reached over TLS, unless it is this machine (localhost /
	 * 127.0.0.1 / ::1), where a local dev connector is expected to be plain http.
	 *
	 * @param string $url Destination URL.
	 * @return bool
	 */
	private static function is_https_or_loopback( string $url ): bool {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $parts ) ) {
			return false;
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( 'https' === $scheme ) {
			return true;
		}
		if ( 'http' !== $scheme ) {
			return false;
		}
		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
	}

	/**
	 * Encrypt a shared secret. Byte-identical derivation to EMCP_Tools_Key_Crypto
	 * (AES-256-CBC over hash('sha256', AUTH_KEY.SECURE_AUTH_KEY); base64(iv . ct)),
	 * reimplemented here so the targets store needs no AI-chat dependency.
	 *
	 * @param string $plain Plaintext secret.
	 * @return string Encrypted (base64) secret, '' for empty input.
	 */
	private static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		$key    = hash( 'sha256', ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' ), true );
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', $key, 0, $iv );
		return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored secret (inverse of encrypt()).
	 *
	 * @param string $encrypted Encrypted (base64) secret.
	 * @return string Plaintext, or '' when undecryptable.
	 */
	private static function decrypt( string $encrypted ): string {
		if ( '' === $encrypted ) {
			return '';
		}
		$data = base64_decode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! is_string( $data ) || strlen( $data ) < 17 ) {
			return '';
		}
		$iv     = substr( $data, 0, 16 );
		$cipher = substr( $data, 16 );
		$key    = hash( 'sha256', ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' ), true );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );
		return false !== $plain ? $plain : '';
	}
}
