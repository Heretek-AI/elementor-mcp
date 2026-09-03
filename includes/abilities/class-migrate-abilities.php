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
				'description'         => __( 'Push this site (as a .emcp archive) to a remote target running the EMCP connector. Streams the archive in HMAC-signed 2 MB packets over the connector REST API and waits for the destination restore. direction is always "push" in this build.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'remote_url'    => array( 'type' => 'string', 'description' => __( 'Destination site URL (the connector is installed there).', 'emcp-tools' ) ),
						'secret_key'    => array( 'type' => 'string', 'description' => __( 'Shared secret (must match EMCP_CONNECTOR_SECRET on the destination).', 'emcp-tools' ) ),
						'direction'     => array( 'type' => 'string', 'enum' => array( 'push' ), 'description' => __( 'Only "push" is supported; "pull" returns unsupported.', 'emcp-tools' ) ),
						'backup_id'     => array( 'type' => 'string', 'description' => __( 'Reuse an existing backup filename instead of building a fresh one.', 'emcp-tools' ) ),
						'include_files' => array( 'type' => 'boolean', 'description' => __( 'Bundle site files when building a fresh backup (default false).', 'emcp-tools' ) ),
						'confirm'       => array( 'type' => 'boolean', 'description' => __( 'Restoring over the destination replaces its database — must be true.', 'emcp-tools' ) ),
					),
					'required'   => array( 'remote_url', 'secret_key' ),
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
				'description'         => __( 'Execute serialized search and replace across the database for URL changes { old_url, new_url, confirm: true }.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'old_url' => array( 'type' => 'string' ),
						'new_url' => array( 'type' => 'string' ),
						'confirm' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'old_url', 'new_url', 'confirm' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_sync' ),
			)
		);
	}

	private static function ensure_packager(): void {
		if ( ! class_exists( 'EMCP_Tools_Packager' ) && class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			$packager = EMCP_Tools_Pro_Loader::path( 'includes/migrate/class-packager.php' );
			if ( '' !== $packager ) {
				require_once $packager;
			}
		}
	}

	public function execute_create_backup( array $args ): array {
		self::ensure_packager();
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
		self::ensure_packager();
		if ( ! class_exists( 'EMCP_Tools_Packager' ) ) {
			return array( 'backups' => array() );
		}
		return array( 'backups' => EMCP_Tools_Packager::list_archives() );
	}

	/**
	 * Push the site to a remote EMCP connector.
	 *
	 * Builds (or reuses) a .emcp archive, streams it to the destination in
	 * signed 2 MB packets, finalizes, and waits for the connector's restore.
	 *
	 * @param array $args Tool input (remote_url, secret_key, direction, backup_id, include_files).
	 * @return array|WP_Error
	 */
	public function execute_migrate( array $args ) {
		self::ensure_packager();
		if ( ! class_exists( 'EMCP_Tools_Packager' ) ) {
			return new WP_Error( 'engine_unavailable', __( 'Packager engine is not available.', 'emcp-tools' ) );
		}

		$remote = trim( (string) ( $args['remote_url'] ?? '' ) );
		if ( '' === $remote || ! wp_http_validate_url( $remote ) ) {
			return new WP_Error( 'invalid_remote', __( 'A valid http(s) remote_url is required.', 'emcp-tools' ) );
		}
		$secret = (string) ( $args['secret_key'] ?? '' );
		if ( '' === $secret ) {
			return new WP_Error( 'secret_required', __( 'secret_key is required (must match EMCP_CONNECTOR_SECRET on the destination).', 'emcp-tools' ) );
		}
		$direction = strtolower( (string) ( $args['direction'] ?? 'push' ) );
		if ( 'pull' === $direction ) {
			return new WP_Error( 'unsupported', __( 'Pull migration is not implemented in this build — run migrate-site on the source site to push.', 'emcp-tools' ) );
		}
		if ( 'push' !== $direction ) {
			return new WP_Error( 'invalid_direction', __( 'direction must be "push".', 'emcp-tools' ) );
		}
		if ( empty( $args['confirm'] ) || true !== $args['confirm'] ) {
			return new WP_Error( 'confirm_required', __( 'migrate-site overwrites the destination — provide confirm:true.', 'emcp-tools' ) );
		}

		// Build or reuse an archive.
		$path = '';
		$backup_id = sanitize_file_name( (string) ( $args['backup_id'] ?? '' ) );
		if ( '' !== $backup_id ) {
			$path = EMCP_Tools_Packager::archive_path( $backup_id );
			if ( '' === $path ) {
				return new WP_Error( 'backup_missing', __( 'backup_id does not match an existing archive.', 'emcp-tools' ) );
			}
		} else {
			$include_files = ! empty( $args['include_files'] );
			$path          = EMCP_Tools_Packager::create_archive( '', array( 'include_files' => $include_files ) );
			if ( ! $path ) {
				return new WP_Error( 'create_failed', __( 'Could not build the site archive before pushing.', 'emcp-tools' ) );
			}
		}

		$endpoint    = untrailingslashit( $remote ) . '/wp-json/emcp-connector/v1';
		$transfer_id = 'emcp-' . wp_generate_password( 12, false );

		$pushed = self::push_archive_packets( $path, $endpoint, $secret, $transfer_id );
		if ( is_wp_error( $pushed ) ) {
			return $pushed;
		}
		$job_id = self::push_finalize( $path, $endpoint, $secret, $transfer_id );
		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}

		// Poll the job until it settles (the connector restores inline, so this
		// normally returns on the first poll; bounded at ~60 s).
		$job = self::poll_job( $endpoint, $secret, $job_id );

		$state = ( $job && isset( $job['state'] ) ) ? $job['state'] : 'unknown';
		if ( 'error' === $state ) {
			return new WP_Error( 'restore_failed', __( 'Destination reported an error during restore.', 'emcp-tools' ), array( 'job' => $job ) );
		}

		return array(
			'success'    => true,
			'message'    => __( 'Archive pushed and restored on the destination.', 'emcp-tools' ),
			'remote_url' => untrailingslashit( $remote ),
			'job_id'     => $job_id,
			'state'      => $state,
			'archive'    => basename( $path ),
			'job'        => $job,
		);
	}

	/**
	 * Stream an archive to the connector as signed 2 MB packets.
	 *
	 * @param string $path        Archive path.
	 * @param string $endpoint    Connector REST base.
	 * @param string $secret      Shared secret.
	 * @param string $transfer_id Transfer id for this push.
	 * @return true|\WP_Error
	 */
	private function push_archive_packets( string $path, string $endpoint, string $secret, string $transfer_id ) {
		$chunk_size = 2 * 1024 * 1024; // 2 MB.
		$total      = (int) ceil( filesize( $path ) / $chunk_size );

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $handle ) {
			return new WP_Error( 'read_failed', __( 'Could not read the archive for upload.', 'emcp-tools' ) );
		}
		$index = 0;
		while ( ! feof( $handle ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$data = fread( $handle, $chunk_size ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( '' === $data || false === $data ) {
				if ( feof( $handle ) ) {
					break;
				}
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new WP_Error( 'read_failed', __( 'Failed while reading the archive.', 'emcp-tools' ) );
			}
			$chunk_sha = hash( 'sha256', $data );
			$canonical = 'packet|' . $chunk_sha . '|' . $transfer_id . '|' . $index;
			$response  = wp_remote_post(
				$endpoint . '/packet',
				array(
					'timeout' => 120,
					'headers' => array(
						'Content-Type'     => 'application/json',
						'X-EMCP-Signature' => hash_hmac( 'sha256', $canonical, $secret ),
					),
					'body'    => wp_json_encode( array(
						'transfer_id' => $transfer_id,
						'index'       => $index,
						'total'       => $total,
						'data_b64'    => base64_encode( $data ),
						'sha256'      => $chunk_sha,
					) ),
				)
			);
			$error = $this->packet_error( $response, $index );
			if ( is_wp_error( $error ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return $error;
			}
			$index++;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return true;
	}

	/**
	 * Normalize a packet upload response into a WP_Error, or null when accepted.
	 *
	 * @param mixed $response wp_remote_post result.
	 * @param int   $index    Packet index (for resume reporting).
	 * @return \WP_Error|null
	 */
	private function packet_error( $response, int $index ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'packet_failed',
				sprintf( /* translators: 1: error message. */ __( 'Upload failed at packet %1$d: %2$s', 'emcp-tools' ), $index, $response->get_error_message() ),
				array( 'resume_offset' => $index )
			);
		}
		$code  = (int) wp_remote_retrieve_response_code( $response );
		$rbody = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $rbody ) || ! empty( $rbody['code'] ) ) {
			$msg = is_array( $rbody ) && isset( $rbody['message'] ) ? (string) $rbody['message'] : sprintf( 'HTTP %d', $code );
			return new WP_Error( 'packet_rejected', sprintf( /* translators: 1: packet index, 2: message. */ __( 'Destination rejected packet %1$d: %2$s', 'emcp-tools' ), $index, $msg ), array( 'resume_offset' => $index ) );
		}
		return null;
	}

	/**
	 * Ask the connector to assemble + verify the transfer and start the restore.
	 *
	 * @param string $path        Archive path (for its sha256).
	 * @param string $endpoint    Connector REST base.
	 * @param string $secret      Shared secret.
	 * @param string $transfer_id Transfer id just pushed.
	 * @return string|\WP_Error The destination job id.
	 */
	private function push_finalize( string $path, string $endpoint, string $secret, string $transfer_id ) {
		$whole_sha = hash_file( 'sha256', $path );
		$canonical = 'finalize|' . $whole_sha . '|' . $transfer_id;
		$response  = wp_remote_post(
			$endpoint . '/finalize',
			array(
				'timeout'  => 300,
				'headers'  => array(
					'Content-Type'     => 'application/json',
					'X-EMCP-Signature' => hash_hmac( 'sha256', $canonical, $secret ),
				),
				'body'     => wp_json_encode( array( 'transfer_id' => $transfer_id, 'sha256' => $whole_sha ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'finalize_failed', $response->get_error_message() );
		}
		$code  = (int) wp_remote_retrieve_response_code( $response );
		$rbody = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $rbody ) || empty( $rbody['job_id'] ) ) {
			$msg = is_array( $rbody ) && isset( $rbody['message'] ) ? (string) $rbody['message'] : sprintf( 'HTTP %d', $code );
			return new WP_Error( 'finalize_rejected', sprintf( /* translators: %s: message. */ __( 'Destination restore did not start: %s', 'emcp-tools' ), $msg ) );
		}
		return (string) $rbody['job_id'];
	}

	/**
	 * Poll a connector job until it settles.
	 *
	 * @param string $endpoint Connector REST base.
	 * @param string $secret   Shared secret.
	 * @param string $job_id   Destination job id.
	 * @return array|null Job payload (state done/error/…), null when still pending after the bound.
	 */
	private function poll_job( string $endpoint, string $secret, string $job_id ) {
		$job = null;
		for ( $i = 0; $i < 30; $i++ ) {
			$response = wp_remote_get(
				$endpoint . '/job/' . rawurlencode( $job_id ),
				array(
					'timeout' => 30,
					'headers' => array( 'X-EMCP-Signature' => hash_hmac( 'sha256', 'job|' . $job_id, $secret ) ),
				)
			);
			if ( ! is_wp_error( $response ) ) {
				$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				if ( is_array( $body ) && isset( $body['state'] ) ) {
					$job = $body;
					if ( 'done' === $body['state'] || 'error' === $body['state'] ) {
						break;
					}
				}
			}
			sleep( 2 ); // phpcs:ignore WordPress.PHP -- poll cadence for a remote batch job.
		}
		return $job;
	}

	public function execute_sync( array $args ) {
		if ( empty( $args['confirm'] ) || true !== $args['confirm'] ) {
			return new WP_Error( 'confirm_required', __( 'Must provide confirm: true to run URL sync.', 'emcp-tools' ) );
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
				if ( $ledger && '' !== $r['pk'] && ! empty( $r['before_rows'] ) ) {
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
							'partial'     => ( $r['partial'] || count( $r['before_rows'] ) >= $before_cap ),
						),
					) );
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
}
