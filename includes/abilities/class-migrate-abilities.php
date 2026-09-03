<?php
/**
 * Backup, Sync & Migrate MCP abilities (Pro).
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Migrate_Abilities {

	public function get_ability_names(): array {
		return array(
			'emcp-tools/create-backup',
			'emcp-tools/list-backups',
			'emcp-tools/migrate-site',
			'emcp-tools/sync-to-live',
			'emcp-tools/url-search-replace',
		);
	}

	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/create-backup',
			array(
				'label'               => __( 'Create Site Backup', 'emcp-tools' ),
				'description'         => __( 'Create a portable .emcp backup archive containing the database and site structure.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'         => array( 'type' => 'string', 'description' => __( 'Optional backup name.', 'emcp-tools' ) ),
						'include_files' => array( 'type' => 'boolean', 'description' => __( 'Also bundle site files (uploads/plugins/themes). Off by default — the DB dump is always included.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_create_backup' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/list-backups',
			array(
				'label'               => __( 'List Site Backups', 'emcp-tools' ),
				'description'         => __( 'List all available .emcp backup archives.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_list_backups' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/migrate-site',
			array(
				'label'               => __( 'Migrate Site', 'emcp-tools' ),
				'description'         => __( 'Push this site (as a .emcp archive) to a remote destination running the EMCP connector. Streams the archive in HMAC-signed 2 MB packets over the connector REST API and waits for the destination restore. Address a paired target with target_id, or pass remote_url + secret_key for a one-off push. direction is always "push" in this build.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'target_id'     => array( 'type' => 'integer', 'description' => __( 'ID of a paired destination (from the Backup & Migrate admin). Takes precedence over remote_url.', 'emcp-tools' ) ),
						'remote_url'    => array( 'type' => 'string', 'description' => __( 'Destination site URL (the connector is installed there).', 'emcp-tools' ) ),
						'secret_key'    => array( 'type' => 'string', 'description' => __( 'Shared secret (must match EMCP_CONNECTOR_SECRET on the destination).', 'emcp-tools' ) ),
						'direction'     => array( 'type' => 'string', 'enum' => array( 'push', 'pull' ), 'description' => __( '"push" uploads to the connector; "pull" reaches execute and returns a clear unsupported error.', 'emcp-tools' ) ),
						'backup_id'     => array( 'type' => 'string', 'description' => __( 'Reuse an existing backup filename instead of building a fresh one.', 'emcp-tools' ) ),
						'include_files' => array( 'type' => 'boolean', 'description' => __( 'Bundle site files when building a fresh backup (default false).', 'emcp-tools' ) ),
						'confirm'       => array( 'type' => 'boolean', 'description' => __( 'Restoring over the destination replaces its database — must be true.', 'emcp-tools' ) ),
					),
					'required'   => array( 'confirm' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_migrate' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/sync-to-live',
			array(
				'label'               => __( 'Sync to Live', 'emcp-tools' ),
				'description'         => __( 'Push a scope of this site (full, or selected tables and/or file roots) to a live destination running the EMCP connector 1.2.0+, and wait for the scoped restore. The destination imports only the archived tables/files and rewrites URLs only over those tables. Address a paired target with target_id, or pass remote_url + secret_key.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'target_id'  => array( 'type' => 'integer', 'description' => __( 'ID of a paired destination (from the Backup & Migrate admin). Takes precedence over remote_url.', 'emcp-tools' ) ),
						'remote_url' => array( 'type' => 'string', 'description' => __( 'Destination site URL (the connector is installed there).', 'emcp-tools' ) ),
						'secret_key' => array( 'type' => 'string', 'description' => __( 'Shared secret (must match EMCP_CONNECTOR_SECRET on the destination).', 'emcp-tools' ) ),
						'scope'      => array(
							'type'        => 'object',
							'description' => __( 'What to sync. Omit for a full site. tables: [] syncs the whole DB; a list syncs only those tables; "none" skips the DB. file_roots: [] syncs all files; a list of uploads/plugins/themes (or a wp-content-relative path) syncs only those roots; "none" skips files.', 'emcp-tools' ),
							'properties'  => array(
								'tables'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
								'file_roots' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
							),
						),
						'confirm'    => array( 'type' => 'boolean', 'description' => __( 'Overwrites the selected tables/files on the destination — must be true.', 'emcp-tools' ) ),
					),
					'required'   => array( 'confirm' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_sync' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/url-search-replace',
			array(
				'label'               => __( 'URL Search & Replace', 'emcp-tools' ),
				'description'         => __( 'Run a serialization-safe URL search-replace across the database (old_url → new_url). Useful after restoring/migrating to rewrite stored URLs in place. Ledger-recorded so the rewrites stay reversible in History.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'old_url' => array( 'type' => 'string', 'description' => __( 'The URL to replace.', 'emcp-tools' ) ),
						'new_url' => array( 'type' => 'string', 'description' => __( 'The replacement URL.', 'emcp-tools' ) ),
						'confirm' => array( 'type' => 'boolean', 'description' => __( 'Rewrites rows across the database — must be true.', 'emcp-tools' ) ),
					),
					'required'   => array( 'old_url', 'new_url', 'confirm' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_url_search_replace' ),
			)
		);
	}

	private static function ensure_engines(): void {
		if ( ! class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			return;
		}
		foreach ( array(
			'includes/migrate/class-packager.php',
			'includes/migrate/class-migration-engine.php',
			'includes/migrate/class-migrate-targets.php',
			'includes/migrate/class-sync-engine.php',
		) as $rel ) {
			$file = EMCP_Tools_Pro_Loader::path( $rel );
			if ( '' !== $file ) {
				require_once $file;
			}
		}
	}

	public function execute_create_backup( array $args ): array {
		self::ensure_engines();
		if ( ! class_exists( 'EMCP_Tools_Packager' ) ) {
			return array( 'success' => false, 'message' => __( 'Packager engine is not available.', 'emcp-tools' ) );
		}
		$name          = sanitize_file_name( (string) ( $args['name'] ?? '' ) );
		$include_files = ! empty( $args['include_files'] );
		$file          = EMCP_Tools_Packager::create_archive( $name, array( 'include_files' => $include_files ) );
		if ( ! $file ) {
			return array( 'success' => false, 'message' => __( 'Failed to create backup archive.', 'emcp-tools' ) );
		}
		$manifest = EMCP_Tools_Packager::read_manifest( basename( $file ) );
		return array(
			'success'      => true,
			'filename'     => basename( $file ),
			'path'         => $file,
			'size'         => size_format( filesize( $file ) ),
			'include_files'=> (bool) $include_files,
			'db_stats'     => isset( $manifest['db_stats'] ) ? $manifest['db_stats'] : array(),
		);
	}

	public function execute_list_backups(): array {
		self::ensure_engines();
		if ( ! class_exists( 'EMCP_Tools_Packager' ) ) {
			return array( 'backups' => array() );
		}
		return array( 'backups' => EMCP_Tools_Packager::list_archives() );
	}

	/**
	 * Resolve which destination an action targets (paired target or raw URL +
	 * secret). Delegates to the shared engine resolver.
	 *
	 * @param array $args Tool input.
	 * @return array{endpoint:string,secret:string,label:string,target_url:string}|WP_Error
	 */
	private function resolve_destination( array $args ) {
		if ( ! class_exists( 'EMCP_Tools_Migration_Engine' ) ) {
			return new WP_Error( 'engine_unavailable', __( 'The migrate engine is not available.', 'emcp-tools' ) );
		}
		return EMCP_Tools_Migration_Engine::destination_from_input( $args );
	}

	/**
	 * Push the site to a remote EMCP connector.
	 *
	 * @param array $args Tool input (target_id or remote_url+secret_key, direction, backup_id, include_files, confirm).
	 * @return array|WP_Error
	 */
	public function execute_migrate( array $args ) {
		self::ensure_engines();
		if ( ! class_exists( 'EMCP_Tools_Packager' ) || ! class_exists( 'EMCP_Tools_Migration_Engine' ) ) {
			return new WP_Error( 'engine_unavailable', __( 'The migrate engine is not available.', 'emcp-tools' ) );
		}
		if ( empty( $args['confirm'] ) || true !== $args['confirm'] ) {
			return new WP_Error( 'confirm_required', __( 'migrate-site overwrites the destination — provide confirm:true.', 'emcp-tools' ) );
		}
		$direction = strtolower( (string) ( $args['direction'] ?? 'push' ) );
		if ( 'pull' === $direction ) {
			return new WP_Error( 'unsupported', __( 'Pull migration is not implemented in this build — run migrate-site on the source site to push.', 'emcp-tools' ) );
		}
		if ( 'push' !== $direction ) {
			return new WP_Error( 'invalid_direction', __( 'direction must be "push".', 'emcp-tools' ) );
		}

		$dest = $this->resolve_destination( $args );
		if ( is_wp_error( $dest ) ) {
			return $dest;
		}

		// Build or reuse an archive (shared build-or-reuse helper).
		$path = EMCP_Tools_Migration_Engine::archive_for_push( $args );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$result = EMCP_Tools_Migration_Engine::push_archive( array(
			'path'        => $path,
			'endpoint'    => $dest['endpoint'],
			'secret'      => $dest['secret'],
			'transfer_id' => EMCP_Tools_Migration_Engine::transfer_id(),
		) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['message']    = __( 'Archive pushed and restored on the destination.', 'emcp-tools' );
		$result['remote_url'] = $dest['target_url'];
		return $result;
	}

	/**
	 * Push a scoped archive (full or selective) to a live destination.
	 *
	 * @param array $args Tool input (target_id or remote_url+secret_key, scope, confirm).
	 * @return array|WP_Error
	 */
	public function execute_sync( array $args ) {
		self::ensure_engines();
		if ( ! class_exists( 'EMCP_Tools_Sync_Engine' ) || ! class_exists( 'EMCP_Tools_Migration_Engine' ) ) {
			return new WP_Error( 'engine_unavailable', __( 'The sync engine is not available.', 'emcp-tools' ) );
		}

		$dest = $this->resolve_destination( $args );
		if ( is_wp_error( $dest ) ) {
			return $dest;
		}
		$opts = array(
			'confirm'  => ! empty( $args['confirm'] ),
			'endpoint' => $dest['endpoint'],
			'secret'   => $dest['secret'],
			'poll'     => true,
		);
		if ( isset( $args['scope'] ) && is_array( $args['scope'] ) ) {
			$opts['scope'] = $args['scope'];
		}

		$result = EMCP_Tools_Sync_Engine::sync_to_target( $opts );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['message'] = __( 'Scoped archive pushed and restored on the destination.', 'emcp-tools' );
		return $result;
	}

	/**
	 * Local serialized-safe URL search-replace across the database.
	 *
	 * @param array $args Tool input (old_url, new_url, confirm).
	 * @return array|WP_Error
	 */
	public function execute_url_search_replace( array $args ) {
		if ( empty( $args['confirm'] ) || true !== $args['confirm'] ) {
			return new WP_Error( 'confirm_required', __( 'Must provide confirm: true to run URL search-replace.', 'emcp-tools' ) );
		}
		if ( ! class_exists( 'EMCP_Tools_Serialized_Search_Replace' ) ) {
			return new WP_Error( 'engine_unavailable', __( 'The search-replace engine is not available.', 'emcp-tools' ) );
		}
		$old = (string) ( $args['old_url'] ?? '' );
		$new = (string) ( $args['new_url'] ?? '' );
		if ( '' === $old || '' === $new || $old === $new ) {
			return new WP_Error( 'invalid_urls', __( 'Provide two distinct, non-empty URLs (old_url and new_url).', 'emcp-tools' ) );
		}

		global $wpdb;
		$engine     = 'EMCP_Tools_Serialized_Search_Replace';
		$tables     = $engine::data_tables( $wpdb );
		$per_table  = array();
		$total      = 0;
		$partial    = false;
		$ledger     = class_exists( 'EMCP_Tools_Change_Recorder' );
		$before_cap = $ledger ? 200 : 0;

		foreach ( $tables as $table ) {
			$r = $engine::walk_table( $wpdb, $table, $old, $new, 0, $before_cap );
			if ( $r['affected'] > 0 ) {
				$per_table[ $table ] = $r['affected'];
				$total              += $r['affected'];
				if ( $r['partial'] ) {
					$partial = true;
				}
				if ( $ledger ) {
					$this->record_url_replace( $table, $r, $old, $new );
				}
			}
		}

		return array(
			'success'          => true,
			'url'              => array( 'old' => $old, 'new' => $new ),
			'total_rows_updated' => $total,
			'per_table'        => $per_table,
			'partial'          => $partial,
		);
	}

	/**
	 * Record one table's search-replace as a reversible ledger entry.
	 *
	 * @param string $table Table name.
	 * @param array  $r     walk_table() result (affected/pk/before_rows/partial).
	 * @param string $old   Old URL.
	 * @param string $new   New URL.
	 */
	private function record_url_replace( string $table, array $r, string $old, string $new ): void {
		if ( '' === (string) $r['pk'] || empty( $r['before_rows'] ) ) {
			return;
		}
		EMCP_Tools_Change_Recorder::record_db( array(
			'domain'   => 'database',
			'action'   => 'search-replace',
			'target'   => $table,
			'summary'  => sprintf(
				/* translators: 1: old URL, 2: new URL, 3: affected row count, 4: table name. */
				__( 'URL search-replace (%1$s → %2$s) updated %3$d row(s) in %4$s', 'emcp-tools' ),
				$old,
				$new,
				$r['affected'],
				$table
			),
			'rollback' => array(
				'type'        => 'db-before-image',
				'op'          => 'update',
				'table'       => $table,
				'key_cols'    => array( $r['pk'] ),
				'before_rows' => $r['before_rows'],
				'partial'     => ( ! empty( $r['partial'] ) || count( $r['before_rows'] ) >= 200 ),
			),
		) );
	}
}
