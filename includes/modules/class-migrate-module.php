<?php
/**
 * Backup & Migrate module (Pro).
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Migrate_Module extends EMCP_Tools_Module {

	/** Nonce action for the migrate admin-post handler. */
	const ADMIN_ACTION = 'emcp_tools_migrate_admin';

	public function id(): string { return 'migrate'; }
	public function title(): string { return __( 'Backup, Sync & Migrate', 'emcp-tools' ); }
	public function description(): string { return __( 'Create portable .emcp site backups, restore them, and sync changes between environments.', 'emcp-tools' ); }
	public function tier(): string { return 'pro'; }
	public function default_active(): bool { return true; }
	public function is_available(): bool { return true; }

	public static function is_enabled(): bool {
		$active = (array) get_option( self::OPTION_ACTIVE, array() );
		return in_array( 'migrate', $active, true );
	}

	public function register(): void {
		// The engines live in the runtime loader, but a caller that reaches here
		// before load_runtime() (never in practice) should still not fatal.
		if ( ! class_exists( 'EMCP_Tools_Packager' ) ) {
			$packager = EMCP_Tools_Pro_Loader::path( 'includes/migrate/class-packager.php' );
			if ( '' !== $packager ) {
				require_once $packager;
			}
		}

		if ( is_admin() ) {
			add_action( 'admin_post_' . self::ADMIN_ACTION, array( $this, 'handle_admin_post' ) );
			add_action( 'admin_post_emcp_tools_download_connector', array( $this, 'handle_download_connector' ) );
		}
	}

	/** URL of the Backup & Migrate admin tab. */
	private static function migrate_page_url(): string {
		return admin_url( 'admin.php?page=' . \EMCP_Tools_Admin::PAGE_SLUG . '-migrate' );
	}

	/** Stream a installable connector-plugin zip (emcp-connector/). */
	public function handle_download_connector(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to download the connector.', 'emcp-tools' ) );
		}
		check_admin_referer( self::ADMIN_ACTION );
		$this->stream_connector_zip();
	}

	/** Zip connector/emcp-connector.php into a standalone plugin folder + stream it. */
	private function stream_connector_zip(): void {
		$source = EMCP_Tools_Pro_Loader::path( 'connector/emcp-connector.php' );
		if ( '' === $source || ! is_readable( $source ) ) {
			$this->set_notice( 'error', __( 'Connector plugin file not found in this build.', 'emcp-tools' ) );
			wp_safe_redirect( self::migrate_page_url() );
			exit;
		}
		$tmp = EMCP_Tools_Packager::backup_dir() . '/.emcp-connector-' . wp_generate_password( 6, false ) . '.zip';
		$zip = new ZipArchive();
		if ( true === $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			$zip->addFile( $source, 'emcp-connector/emcp-connector.php' );
			$zip->close();
		}
		if ( ! is_file( $tmp ) ) {
			$this->set_notice( 'error', __( 'Could not build the connector zip.', 'emcp-tools' ) );
			wp_safe_redirect( self::migrate_page_url() );
			exit;
		}
		self::drain_output_buffers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="emcp-connector.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- binary download.
		@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		exit;
	}

	/**
	 * Admin-post dispatcher for the Backup & Migrate tab (create/restore/delete/
	 * download). Every action is nonce-checked and manage_options-gated; restore
	 * and delete are intentionally admin-only (no MCP tool deletes or restores).
	 */
	public function handle_admin_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage backups.', 'emcp-tools' ) );
		}
		check_admin_referer( self::ADMIN_ACTION );

		$action = sanitize_key( (string) ( $_POST['emcp_migrate_action'] ?? '' ) );
		$file   = sanitize_file_name( (string) ( $_POST['archive'] ?? '' ) );

		switch ( $action ) {
			case 'create':
				$this->admin_create_backup();
				break;

			case 'restore':
				$this->admin_restore( $file );
				break;

			case 'delete':
				$this->admin_delete( $file );
				break;

			case 'download':
				$this->admin_download( $file );
				break;

			default:
				$this->set_notice( 'error', __( 'Unknown action.', 'emcp-tools' ) );
		}

		wp_safe_redirect( self::migrate_page_url() );
		exit;
	}

	/** Create an archive from the admin form. */
	private function admin_create_backup(): void {
		$name          = sanitize_file_name( (string) ( $_POST['backup_name'] ?? '' ) );
		$include_files = ! empty( $_POST['include_files'] );
		$path          = EMCP_Tools_Packager::create_archive( $name, array( 'include_files' => $include_files ) );
		if ( $path ) {
			/* translators: %s: archive filename. */
			$this->set_notice( 'success', sprintf( __( 'Archive "%s" created.', 'emcp-tools' ), basename( $path ) ) );
		} else {
			$this->set_notice( 'error', __( 'Failed to create archive. Is ZipArchive available and the backups dir writable?', 'emcp-tools' ) );
		}
	}

	/** Restore an archive (verification + import + optional URL rewrite + files). */
	private function admin_restore( string $file ): void {
		if ( '' === $file ) {
			$this->set_notice( 'error', __( 'No archive selected to restore.', 'emcp-tools' ) );
			return;
		}
		if ( ! class_exists( 'EMCP_Tools_Restore_Engine' ) ) {
			$this->set_notice( 'error', __( 'Restore engine is not available.', 'emcp-tools' ) );
			return;
		}
		$result = EMCP_Tools_Restore_Engine::restore( $file );
		if ( is_wp_error( $result ) ) {
			$this->set_notice( 'error', $result->get_error_message() );
			return;
		}
		$detail = array();
		if ( ! empty( $result['db'] ) ) {
			$db = $result['db'];
			$detail[] = sprintf(
				/* translators: 1: executed statements, 2: skipped directives, 3: errors. */
				__( 'DB: %1$d executed, %2$d directives skipped, %3$d errors', 'emcp-tools' ),
				(int) $db['executed'],
				(int) $db['skipped'],
				(int) $db['errors']
			);
		}
		if ( ! empty( $result['search_replace'] ) ) {
			$detail[] = sprintf(
				/* translators: 1: rows rewritten, 2: old URL, 3: new URL. */
				__( 'URLs rewritten: %1$d row(s) (%2$s → %3$s)', 'emcp-tools' ),
				(int) $result['search_replace']['rows'],
				(string) $result['search_replace']['old'],
				(string) $result['search_replace']['new']
			);
		}
		if ( ! empty( $result['files_placed'] ) ) {
			$detail[] = sprintf( /* translators: %d: files placed. */ __( 'Files placed: %d', 'emcp-tools' ), (int) $result['files_placed'] );
		}
		/* translators: %s: detail summary. */
		$this->set_notice( 'success', sprintf( __( 'Restore of "%s" finished. %s', 'emcp-tools' ), $file, implode( ' — ', $detail ) ) );
	}

	/** Delete an archive (admin-only). */
	private function admin_delete( string $file ): void {
		if ( '' === $file ) {
			$this->set_notice( 'error', __( 'No archive selected to delete.', 'emcp-tools' ) );
			return;
		}
		if ( EMCP_Tools_Packager::delete_archive( $file ) ) {
			/* translators: %s: archive filename. */
			$this->set_notice( 'success', sprintf( __( 'Archive "%s" deleted.', 'emcp-tools' ), $file ) );
		} else {
			/* translators: %s: archive filename. */
			$this->set_notice( 'error', sprintf( __( 'Could not delete archive "%s".', 'emcp-tools' ), $file ) );
		}
	}

	/** Stream an archive to the browser and stop. */
	private function admin_download( string $file ): void {
		$path = EMCP_Tools_Packager::archive_path( $file );
		if ( '' === $path ) {
			$this->set_notice( 'error', __( 'Archive not found for download.', 'emcp-tools' ) );
			return;
		}
		self::drain_output_buffers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- binary download.
		exit;
	}

	/** Discard buffered output so a binary download is never polluted by it. */
	private static function drain_output_buffers(): void {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}

	/** Flash a one-shot admin notice via transient. */
	private function set_notice( string $type, string $message ): void {
		set_transient( 'emcp_tools_migrate_notice', array( 'type' => $type, 'message' => $message ), 30 );
	}

	/**
	 * Read + clear the one-shot notice (called from the admin view).
	 *
	 * @return array{type:string,message:string}|null
	 */
	public static function consume_notice() {
		$notice = get_transient( 'emcp_tools_migrate_notice' );
		if ( is_array( $notice ) ) {
			delete_transient( 'emcp_tools_migrate_notice' );
			return $notice;
		}
		return null;
	}

	public function render_settings(): void {
		?>
		<p class="description">
			<?php esc_html_e( 'Create and restore portable .emcp archives from the Backup & Migrate tab.', 'emcp-tools' ); ?>
		</p>
		<?php
	}
}
