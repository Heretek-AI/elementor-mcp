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

		// Boot the paired-targets store (version-gated dbDelta on init:20).
		if ( class_exists( 'EMCP_Tools_Migrate_Targets' ) ) {
			EMCP_Tools_Migrate_Targets::init();
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

			case 'add_target':
				$this->admin_add_target();
				break;

			case 'delete_target':
				$this->admin_delete_target( (int) ( $_POST['target_id'] ?? 0 ) );
				break;

			case 'verify_target':
				$this->admin_verify_target( (int) ( $_POST['target_id'] ?? 0 ) );
				break;

			case 'push':
				$this->admin_push();
				break;

			case 'sync':
				$this->admin_sync();
				break;

			default:
				$this->set_notice( 'error', __( 'Unknown action.', 'emcp-tools' ) );
		}

		$view = self::migrate_view();
		wp_safe_redirect( self::migrate_page_url() . ( 'archives' !== $view ? '&view=' . rawurlencode( $view ) : '' ) );
		exit;
	}

	/**
	 * Which sub-view the migrate page is showing: 'archives' (default) | 'push' | 'sync'.
	 *
	 * @return string
	 */
	public static function migrate_view(): string {
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display-only.
		return in_array( $view, array( 'push', 'sync' ), true ) ? $view : 'archives';
	}

	/** Raise execution limits for a long, blocking push/sync admin-post. */
	private static function run_blocking(): void {
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 ); // phpcs:ignore WordPress.PHP -- batch push.
		}
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

	/**
	 * Resolve the push/sync destination from the submitted form (a stored paired
	 * target or a raw remote_url + secret_key). Delegates to the shared engine
	 * resolver so admin and MCP use identical validation.
	 *
	 * @return array{endpoint:string,secret:string,label:string,target_url:string}|\WP_Error
	 */
	private function read_destination() {
		if ( ! class_exists( 'EMCP_Tools_Migration_Engine' ) ) {
			return new WP_Error( 'engine_unavailable', __( 'The migrate engine is not available.', 'emcp-tools' ) );
		}
		return EMCP_Tools_Migration_Engine::destination_from_input( (array) $_POST );
	}

	/** Pair a destination from the admin form (single-use code exchange). */
	private function admin_add_target(): void {
		if ( ! class_exists( 'EMCP_Tools_Migrate_Targets' ) ) {
			$this->set_notice( 'error', __( 'The paired-targets store is not available.', 'emcp-tools' ) );
			return;
		}
		$label = sanitize_text_field( (string) ( $_POST['label'] ?? '' ) );
		$url   = esc_url_raw( trim( (string) ( $_POST['target_url'] ?? '' ) ) );
		$code  = sanitize_text_field( (string) ( $_POST['pairing_code'] ?? '' ) );
		if ( '' === $url || '' === $code ) {
			$this->set_notice( 'error', __( 'A destination URL and the single-use pairing code are required.', 'emcp-tools' ) );
			return;
		}
		$result = EMCP_Tools_Migrate_Targets::redeem_pairing_code( $url, $code, $label );
		if ( is_wp_error( $result ) ) {
			$this->set_notice( 'error', $result->get_error_message() );
			return;
		}
		/* translators: 1: target label, 2: target URL. */
		$this->set_notice( 'success', sprintf( __( 'Target "%1$s" paired and verified (%2$s).', 'emcp-tools' ), $result['label'] ?? $label, $result['target_url'] ?? $url ) );
	}

	/** Delete a paired target. */
	private function admin_delete_target( int $id ): void {
		if ( ! class_exists( 'EMCP_Tools_Migrate_Targets' ) ) {
			$this->set_notice( 'error', __( 'The paired-targets store is not available.', 'emcp-tools' ) );
			return;
		}
		if ( $id < 1 || ! EMCP_Tools_Migrate_Targets::get( $id ) ) {
			$this->set_notice( 'error', __( 'Paired target not found.', 'emcp-tools' ) );
			return;
		}
		EMCP_Tools_Migrate_Targets::delete( $id );
		$this->set_notice( 'success', __( 'Paired target removed.', 'emcp-tools' ) );
	}

	/** Prove a stored target's secret still signs on the destination. */
	private function admin_verify_target( int $id ): void {
		$target = EMCP_Tools_Migrate_Targets::get( $id );
		if ( ! $target ) {
			$this->set_notice( 'error', __( 'Paired target not found.', 'emcp-tools' ) );
			return;
		}
		$secret = EMCP_Tools_Migrate_Targets::get_secret( $id );
		if ( EMCP_Tools_Migrate_Targets::verify_endpoint( (string) $target['endpoint'], $secret ) ) {
			/* translators: %s: target label. */
			$this->set_notice( 'success', sprintf( __( 'Target "%s" verified.', 'emcp-tools' ), $target['label'] ) );
			return;
		}
		/* translators: %s: target label. */
		$this->set_notice( 'error', sprintf( __( 'Target "%s" could not be verified — the destination rejected the stored secret (or is unreachable). Re-pair the target.', 'emcp-tools' ), $target['label'] ) );
	}

	/** Push a full archive to a destination (fresh build or an existing one). */
	private function admin_push(): void {
		if ( ! class_exists( 'EMCP_Tools_Packager' ) || ! class_exists( 'EMCP_Tools_Migration_Engine' ) ) {
			$this->set_notice( 'error', __( 'The migrate engine is not available.', 'emcp-tools' ) );
			return;
		}
		if ( empty( $_POST['confirm'] ) ) {
			$this->set_notice( 'error', __( 'Confirm the destination overwrite to push.', 'emcp-tools' ) );
			return;
		}
		$dest = $this->read_destination();
		if ( is_wp_error( $dest ) ) {
			$this->set_notice( 'error', $dest->get_error_message() );
			return;
		}

		$archive_source = isset( $_POST['archive_source'] ) ? sanitize_key( (string) $_POST['archive_source'] ) : 'build';
		$path_opts      = array( 'include_files' => ! empty( $_POST['include_files'] ) );
		if ( 'existing' === $archive_source ) {
			$path_opts['backup_id'] = sanitize_file_name( (string) ( $_POST['archive'] ?? '' ) );
			if ( '' === $path_opts['backup_id'] ) {
				$this->set_notice( 'error', __( 'Pick an existing archive to push.', 'emcp-tools' ) );
				return;
			}
		}
		$path = EMCP_Tools_Migration_Engine::archive_for_push( $path_opts );
		if ( is_wp_error( $path ) ) {
			$this->set_notice( 'error', $path->get_error_message() );
			return;
		}

		self::run_blocking();
		$result = EMCP_Tools_Migration_Engine::push_archive( array(
			'path'     => $path,
			'endpoint' => $dest['endpoint'],
			'secret'   => $dest['secret'],
		) );
		if ( is_wp_error( $result ) ) {
			$this->set_notice( 'error', $result->get_error_message() );
			return;
		}
		/* translators: 1: destination label, 2: job id, 3: archive name. */
		$this->set_notice( 'success', sprintf( __( 'Push to "%1$s" finished. Job %2$s, archive %3$s.', 'emcp-tools' ), $dest['label'], $result['job_id'], $result['archive'] ) );
	}

	/** Sync a scope (full or selective) to a paired destination. */
	private function admin_sync(): void {
		if ( ! class_exists( 'EMCP_Tools_Sync_Engine' ) ) {
			$this->set_notice( 'error', __( 'The sync engine is not available.', 'emcp-tools' ) );
			return;
		}
		if ( empty( $_POST['confirm'] ) ) {
			$this->set_notice( 'error', __( 'Confirm the destination overwrite to sync.', 'emcp-tools' ) );
			return;
		}
		$dest = $this->read_destination();
		if ( is_wp_error( $dest ) ) {
			$this->set_notice( 'error', $dest->get_error_message() );
			return;
		}

		$opts = array(
			'confirm'  => true,
			'poll'     => true,
			'scope'    => $this->read_sync_scope(),
			'endpoint' => $dest['endpoint'],
			'secret'   => $dest['secret'],
		);

		self::run_blocking();
		$result = EMCP_Tools_Sync_Engine::sync_to_target( $opts );
		if ( is_wp_error( $result ) ) {
			$this->set_notice( 'error', $result->get_error_message() );
			return;
		}
		$scope = ( isset( $result['scope'] ) && is_array( $result['scope'] ) ) ? $result['scope'] : array();
		$db    = $this->scope_part_summary( isset( $scope['db'] ) ? $scope['db'] : 'all' );
		$files = $this->scope_part_summary( isset( $scope['files'] ) ? $scope['files'] : 'all' );
		/* translators: 1: destination label, 2: DB scope, 3: files scope, 4: job id. */
		$this->set_notice( 'success', sprintf( __( 'Sync to "%1$s" finished (DB: %2$s, files: %3$s). Job %4$s.', 'emcp-tools' ), $dest['label'], $db, $files, $result['job_id'] ) );
	}

	/**
	 * Read the sync scope from the submitted form into engine input:
	 * { db: 'all'|'none'|tables[], files: 'all'|'none'|file-roots[] }.
	 *
	 * @return array
	 */
	private function read_sync_scope(): array {
		return array(
			'db'    => $this->read_sync_db_part(),
			'files' => $this->read_sync_files_part(),
		);
	}

	/** DB part of the submitted sync scope. */
	private function read_sync_db_part() {
		$db_mode = isset( $_POST['db_mode'] ) ? sanitize_key( (string) $_POST['db_mode'] ) : 'all';
		if ( 'none' === $db_mode ) {
			return 'none';
		}
		if ( 'selected' === $db_mode ) {
			$tables = array();
			foreach ( (array) ( $_POST['tables'] ?? array() ) as $table ) {
				$table = sanitize_text_field( (string) $table );
				if ( '' !== $table ) {
					$tables[] = $table;
				}
			}
			return $tables; // Empty list → engine normalizes to 'none'.
		}
		return 'all';
	}

	/** Files part of the submitted sync scope. */
	private function read_sync_files_part() {
		$files_mode = isset( $_POST['files_mode'] ) ? sanitize_key( (string) $_POST['files_mode'] ) : 'all';
		if ( 'none' === $files_mode ) {
			return 'none';
		}
		if ( 'selected' === $files_mode ) {
			$roots = array();
			foreach ( (array) ( $_POST['file_roots'] ?? array() ) as $root ) {
				$root = sanitize_key( (string) $root );
				if ( in_array( $root, array( 'uploads', 'plugins', 'themes' ), true ) ) {
					$roots[] = $root;
				}
			}
			$pass_through = sanitize_text_field( (string) ( $_POST['pass_through'] ?? '' ) );
			if ( '' !== $pass_through ) {
				$roots[] = $pass_through;
			}
			return $roots; // Empty list → engine normalizes to 'none'.
		}
		return 'all';
	}

	/**
	 * Human summary of one normalized scope part for the success notice.
	 *
	 * @param mixed $part Scope part ('all' | 'none' | array).
	 * @return string
	 */
	private function scope_part_summary( $part ): string {
		if ( is_array( $part ) ) {
			return sprintf( /* translators: %d: count. */ __( '%d selected', 'emcp-tools' ), count( $part ) );
		}
		if ( 'none' === $part ) {
			return __( 'none', 'emcp-tools' );
		}
		return __( 'all', 'emcp-tools' );
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
