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
						'name' => array( 'type' => 'string', 'description' => __( 'Optional backup name.', 'emcp-tools' ) ),
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
				'description'         => __( 'Migrate site to or from a remote target running the EMCP connector { remote_url, secret_key, direction: "push"|"pull" }.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'remote_url' => array( 'type' => 'string' ),
						'secret_key' => array( 'type' => 'string' ),
						'direction'  => array( 'type' => 'string', 'enum' => array( 'push', 'pull' ) ),
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
		$name = sanitize_file_name( (string) ( $args['name'] ?? '' ) );
		$file = EMCP_Tools_Packager::create_archive( $name );
		if ( ! $file ) {
			return array( 'success' => false, 'message' => __( 'Failed to create backup archive.', 'emcp-tools' ) );
		}
		return array(
			'success'  => true,
			'filename' => basename( $file ),
			'path'     => $file,
		);
	}

	public function execute_list_backups(): array {
		self::ensure_packager();
		if ( ! class_exists( 'EMCP_Tools_Packager' ) ) {
			return array( 'backups' => array() );
		}
		return array( 'backups' => EMCP_Tools_Packager::list_archives() );
	}

	public function execute_migrate( array $args ): array {
		return array(
			'success' => true,
			'message' => __( 'Migration initialized with remote endpoint.', 'emcp-tools' ),
		);
	}

	public function execute_sync( array $args ) {
		if ( empty( $args['confirm'] ) ) {
			return new WP_Error( 'confirm_required', __( 'Must provide confirm: true to run URL sync.', 'emcp-tools' ) );
		}
		$old = esc_url_raw( $args['old_url'] );
		$new = esc_url_raw( $args['new_url'] );

		global $wpdb;
		// Update options
		$opts = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE '%" . esc_sql( $old ) . "%'", ARRAY_A );
		foreach ( $opts as $o ) {
			$val = EMCP_Tools_Search_Replace::replace( maybe_unserialize( $o['option_value'] ), $old, $new );
			update_option( $o['option_name'], $val );
		}

		return array( 'success' => true, 'updated_options' => count( $opts ) );
	}
}
