<?php
/**
 * Sync engine — full + selective one-way sync to a paired connector target.
 *
 * A "scope" is what a sync push carries: a DB part ('all' | 'none' | a table
 * allowlist) and a files part ('all' | 'none' | a set of wp-content roots).
 * build_scoped_emcp() produces a `.emcp` archive containing exactly that scope
 * (tagged `kind: sync` with a `scope` manifest entry); the destination
 * connector restores only what the archive holds, and search-replace runs only
 * when (and over the tables) the DB dump was in the archive.
 *
 * Roots are traversal-guarded and confined to wp-content (themes/plugins/uploads
 * plus a safe relative pass-through). Normalization follows "defaults → all,
 * empty → none".
 *
 * @package EMCP_Tools
 * @since   3.16.0
 */

defined( 'ABSPATH' ) || exit;

class EMCP_Tools_Sync_Engine {

	/** Operational tables never offered as sync candidates or rewritten. */
	private static function table_denylist(): array {
		global $wpdb;
		$p = $wpdb->prefix;
		return array(
			$p . 'emcp_redirects',
			$p . 'emcp_search_index',
			$p . 'emcp_migrate_targets',
			$p . 'emcp_migrate_backups',
			$p . 'emcp_migrate_jobs',
			$p . 'emcp_connector_transfers',
			$p . 'woocommerce_sessions',
			$p . 'actionscheduler_actions',
			$p . 'actionscheduler_logs',
		);
	}

	/**
	 * Normalize a raw scope to the canonical shape
	 * { db: 'all'|'none'|string[], files: 'all'|'none'|string[] }.
	 *
	 * Accepted input keys are `db`/`files` (canonical) or `tables`/`file_roots`
	 * (aliases). Omitted overall scope → both 'all'. An empty list → 'none'.
	 * Any unrecognized scalar → 'none' (fail-safe: never sync more than asked).
	 *
	 * @param array|null $scope Raw scope (or null = full).
	 * @return array Canonical scope.
	 */
	public static function normalize_scope( $scope ): array {
		$scope = is_array( $scope ) ? $scope : array();
		$db    = array_key_exists( 'db', $scope ) ? $scope['db'] : ( $scope['tables'] ?? 'all' );
		$files = array_key_exists( 'files', $scope ) ? $scope['files'] : ( $scope['file_roots'] ?? 'all' );
		return array(
			'db'    => self::normalize_part( $db ),
			'files' => self::normalize_part( $files ),
		);
	}

	/**
	 * Normalize one scope part ('all' | 'none' | list → sorted list).
	 *
	 * @param mixed $part Raw part.
	 * @return string|string[]
	 */
	private static function normalize_part( $part ) {
		if ( is_array( $part ) ) {
			$list = array_values( array_unique( array_filter( array_map( 'strval', (array) $part ) ) ) );
			sort( $list );
			return $list ? $list : 'none';
		}
		if ( is_bool( $part ) ) {
			return $part ? 'all' : 'none';
		}
		$part = (string) $part;
		if ( 'all' === $part || 'none' === $part ) {
			return $part;
		}
		// Anything else (typo/garbage) — fail safe: sync nothing in this part.
		return 'none';
	}

	/** True when a normalized scope is the full site (db all + files all). */
	public static function is_full( array $scope ): bool {
		return 'all' === $scope['db'] && 'all' === $scope['files'];
	}

	/**
	 * Prefixed, non-operational tables a selective DB sync may offer.
	 *
	 * @return string[]
	 */
	public static function candidate_tables(): array {
		global $wpdb;
		$exclude = self::table_denylist();
		$out     = array();
		foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $table ) { // phpcs:ignore WordPress.DB -- read-only discovery.
			if ( 0 === strpos( $table, $wpdb->prefix ) && ! in_array( $table, $exclude, true ) ) {
				$out[] = $table;
			}
		}
		sort( $out );
		return $out;
	}

	/**
	 * Curated file-root tokens a selective file sync may offer.
	 *
	 * @return array[] Token → { label, note }.
	 */
	public static function candidate_file_roots(): array {
		return array(
			'uploads' => array( 'label' => __( 'Uploads', 'emcp-tools' ), 'note' => __( 'Media Library + anything under wp-content/uploads', 'emcp-tools' ) ),
			'plugins' => array( 'label' => __( 'Plugins', 'emcp-tools' ), 'note' => __( 'Installed plugins (wp-content/plugins)', 'emcp-tools' ) ),
			'themes'  => array( 'label' => __( 'Themes', 'emcp-tools' ), 'note' => __( 'Installed themes (wp-content/themes)', 'emcp-tools' ) ),
		);
	}

	/**
	 * Resolve a file-root token (or wp-content-relative pass-through path) to a
	 * normalized absolute directory, or '' when invalid/excluded.
	 *
	 * @param string $token 'themes' | 'plugins' | 'uploads', or a relative path
	 *                      under wp-content (e.g. 'languages').
	 * @return string Normalized absolute dir, or ''.
	 */
	public static function file_root_path( string $token ): string {
		$token   = trim( $token );
		$curated = self::curated_root( $token );
		if ( '' !== $curated ) {
			return $curated;
		}
		return self::confined_pass_through( $token );
	}

	/**
	 * Resolve a curated root token (uploads/plugins/themes) to an absolute dir,
	 * or '' when the token is unknown/missing/excluded.
	 *
	 * @param string $token Root token.
	 * @return string
	 */
	private static function curated_root( string $token ): string {
		if ( ! in_array( $token, array( 'uploads', 'plugins', 'themes' ), true ) ) {
			return '';
		}
		$content = self::content_dir();
		if ( 'uploads' === $token ) {
			$root = wp_upload_dir()['basedir'];
		} elseif ( 'plugins' === $token ) {
			$root = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : $content . '/plugins';
		} else {
			$root = get_theme_root() ? get_theme_root() : $content . '/themes'; // phpcs:ignore WordPress.WP.DiscouragedFunctions -- token root.
		}
		$abs = wp_normalize_path( $root );
		return ( is_dir( $abs ) && ! self::is_path_excluded( $abs ) ) ? $abs : '';
	}

	/**
	 * Resolve a wp-content-relative pass-through path to a confined absolute dir,
	 * or '' when invalid (traversal), outside wp-content, or excluded.
	 *
	 * @param string $raw Raw pass-through path.
	 * @return string
	 */
	private static function confined_pass_through( string $raw ): string {
		$content = self::content_dir();
		if ( '' === $raw || false !== strpos( $raw, '..' ) ) {
			return '';
		}
		$abs = $raw;
		if ( 0 !== strpos( $abs, '/' ) ) {
			$abs = $content . '/' . ltrim( $abs, '/' );
		}
		$abs = rtrim( wp_normalize_path( $abs ), '/' );
		if ( $abs === $content || 0 !== strpos( $abs, $content . '/' ) ) {
			return '';
		}
		if ( self::is_path_excluded( $abs ) || ! is_dir( $abs ) ) {
			return '';
		}
		return $abs;
	}

	/**
	 * Whether an absolute path is an operational directory a sync must never use
	 * as a root (caches, backups, sandboxes, this plugin, the connector).
	 *
	 * @param string $abs Normalized absolute path.
	 * @return bool
	 */
	public static function is_path_excluded( string $abs ): bool {
		$content = self::content_dir();
		$abs     = rtrim( wp_normalize_path( $abs ), '/' );
		$exclude = array(
			$content . '/cache',
			$content . '/emcp-sandbox',
			$content . '/emcp-connector',
			$content . '/uploads/emcp-backups',
			$content . '/uploads/emcp-sandbox',
			$content . '/plugins/emcp-connector',
			wp_normalize_path( defined( 'EMCP_TOOLS_DIR' ) ? EMCP_TOOLS_DIR : '' ),
		);
		foreach ( $exclude as $dir ) {
			if ( '' !== $dir && ( $abs === $dir || 0 === strpos( $abs, $dir . '/' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build a scoped sync archive.
	 *
	 * @param string     $name     Archive name (defaults to sync-<timestamp>).
	 * @param array|null $scope    Raw scope (null = full site).
	 * @param array      $opts     Passed through to the packager.
	 * @return array|\WP_Error { path, scope } or an error from the packager.
	 */
	public static function build_scoped_emcp( string $name = '', $scope = null, array $opts = array() ) {
		if ( ! class_exists( 'EMCP_Tools_Packager' ) ) {
			return new WP_Error( 'engine_unavailable', __( 'The packager engine is not available.', 'emcp-tools' ) );
		}
		$scope = self::normalize_scope( $scope );

		$db_mode    = $scope['db'];
		$file_roots = self::scope_file_roots( $scope['files'] );
		if ( is_wp_error( $file_roots ) ) {
			return $file_roots;
		}

		$packager_opts = array_merge( $opts, array( 'kind' => 'sync' ) );
		if ( 'none' === $db_mode ) {
			$packager_opts['db'] = 'none';
		} elseif ( is_array( $db_mode ) ) {
			$packager_opts['db'] = $db_mode;
		}
		if ( 'none' === $scope['files'] ) {
			$packager_opts['file_roots'] = 'none';
		} else {
			$packager_opts['file_roots'] = $file_roots;
		}

		$path = EMCP_Tools_Packager::create_archive( $name, $packager_opts );
		if ( ! $path ) {
			return new WP_Error( 'create_failed', __( 'Could not build the scoped sync archive.', 'emcp-tools' ) );
		}
		return array( 'path' => $path, 'scope' => $scope );
	}

	/**
	 * Sync a scope to a paired target (full or selective one-way push).
	 *
	 * @param array $opts { target_id:int (or endpoint+secret), scope:array|null,
	 *                     name:string, confirm:bool, poll:bool }.
	 * @return array|\WP_Error Success array { success, target, archive, scope,
	 *                         job_id, state, job }.
	 */
	public static function sync_to_target( array $opts ) {
		if ( empty( $opts['confirm'] ) || true !== $opts['confirm'] ) {
			return new WP_Error( 'confirm_required', __( 'sync-to-live overwrites tables/files on the destination — provide confirm:true.', 'emcp-tools' ) );
		}
		if ( ! class_exists( 'EMCP_Tools_Migration_Engine' ) ) {
			return new WP_Error( 'engine_unavailable', __( 'The transfer engine is not available.', 'emcp-tools' ) );
		}

		$dest = self::push_destination( $opts );
		if ( is_wp_error( $dest ) ) {
			return $dest;
		}
		$scope = self::normalize_scope( $opts['scope'] ?? null );

		// Selective (non-full) scopes need a destination connector that restores
		// only the archived tables/files (>= 1.2.0). Full pushes still work on
		// older connectors, so only gate when the scope is narrowed.
		if ( ! self::is_full( $scope ) && ! self::connector_supports_scope_restore( $dest['target'], $dest['endpoint'] ) ) {
			return new WP_Error(
				'connector_upgrade_required',
				__( 'This scope needs a destination connector 1.2.0+ (scope-restore). Push a full site, or upgrade the connector on the destination.', 'emcp-tools' )
			);
		}

		$built = self::build_scoped_emcp( (string) ( $opts['name'] ?? '' ), $scope );
		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$result = EMCP_Tools_Migration_Engine::push_archive( array(
			'path'     => $built['path'],
			'endpoint' => $dest['endpoint'],
			'secret'   => $dest['secret'],
			'poll'     => ! empty( $opts['poll'] ),
		) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['success'] = true;
		$result['target']  = $dest['target']
			? array( 'id' => (int) $dest['target']['id'], 'label' => (string) $dest['target']['label'], 'target_url' => (string) $dest['target']['target_url'] )
			: array( 'endpoint' => $dest['endpoint'] );
		$result['scope']   = $scope;
		return $result;
	}

	// ---------------------------------------------------------------------
	// Internals
	// ---------------------------------------------------------------------

	/**
	 * Resolve a sync push's destination: a stored paired target (target_id) or a
	 * raw endpoint + secret passed by the caller (already resolved upstream).
	 *
	 * @param array $opts Sync options.
	 * @return array{endpoint:string,secret:string,target:?array}|\WP_Error
	 */
	private static function push_destination( array $opts ) {
		if ( ! empty( $opts['target_id'] ) ) {
			$target = class_exists( 'EMCP_Tools_Migrate_Targets' ) ? EMCP_Tools_Migrate_Targets::get( (int) $opts['target_id'] ) : null;
			if ( ! $target ) {
				return new WP_Error( 'target_missing', __( 'Paired target not found.', 'emcp-tools' ) );
			}
			$secret = EMCP_Tools_Migrate_Targets::get_secret( (int) $target['id'] );
			if ( '' === $secret ) {
				return new WP_Error( 'target_secret', __( 'The paired target secret could not be decrypted.', 'emcp-tools' ) );
			}
			return array(
				'endpoint' => (string) $target['endpoint'],
				'secret'   => $secret,
				'target'   => $target,
			);
		}

		$endpoint = untrailingslashit( (string) ( $opts['endpoint'] ?? '' ) );
		$secret   = (string) ( $opts['secret'] ?? '' );
		if ( '' === $endpoint || '' === $secret ) {
			return new WP_Error( 'target_required', __( 'A paired target_id (or endpoint + secret) is required.', 'emcp-tools' ) );
		}
		return array(
			'endpoint' => $endpoint,
			'secret'   => $secret,
			'target'   => null,
		);
	}

	/** Resolve a normalized files part to a token => abs-dir map for the packager. */
	private static function scope_file_roots( $files ) {
		if ( 'all' === $files ) {
			return 'all';
		}
		if ( 'none' === $files || ! is_array( $files ) ) {
			return 'none';
		}
		$map = array();
		foreach ( $files as $key => $token ) {
			$abs = self::file_root_path( (string) $token );
			if ( '' !== $abs ) {
				$map[ is_int( $key ) ? self::root_label( $abs ) : (string) $token ] = $abs;
			}
		}
		return $map;
	}

	/** Label for a pass-through root when the caller gave a bare list. */
	private static function root_label( string $abs ): string {
		$content = self::content_dir();
		$rel     = trim( str_replace( $content, '', $abs ), '/' );
		$rel     = preg_replace( '/[^a-zA-Z0-9]+/', '-', (string) $rel );
		return trim( (string) $rel, '-' ) ?: 'root';
	}

	/**
	 * Whether the destination connector advertises scope-restore. Uses the
	 * connector_version stored at pair time when available, else a /status probe.
	 *
	 * @param array|null $target   Stored target row (optional).
	 * @param string     $endpoint Connector REST base.
	 * @return bool
	 */
	private static function connector_supports_scope_restore( ?array $target, string $endpoint ): bool {
		if ( $target && ! empty( $target['connector_version'] ) ) {
			return version_compare( (string) $target['connector_version'], '1.2.0', '>=' );
		}
		$response = wp_remote_get( $endpoint . '/status', array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['capabilities'] ) ) {
			return false;
		}
		return in_array( 'scope-restore', (array) $body['capabilities'], true );
	}

	/** Normalized wp-content path (no trailing slash). */
	private static function content_dir(): string {
		return rtrim( wp_normalize_path( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ), '/' );
	}
}
