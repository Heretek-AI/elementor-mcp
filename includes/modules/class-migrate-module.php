<?php
/**
 * Backup & Migrate module (Pro).
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Migrate_Module extends EMCP_Tools_Module {

	public function id(): string { return 'migrate'; }
	public function title(): string { return __( 'Backup, Sync & Migrate', 'emcp-tools' ); }
	public function description(): string { return __( 'Create portable .emcp site backups, migrate between environments, and sync changes.', 'emcp-tools' ); }
	public function tier(): string { return 'pro'; }
	public function default_active(): bool { return true; }
	public function is_available(): bool { return true; }

	public static function is_enabled(): bool {
		$active = (array) get_option( self::OPTION_ACTIVE, array() );
		return in_array( 'migrate', $active, true );
	}

	public function register(): void {}

	public function render_settings(): void {
		?>
		<p class="description">
			<?php esc_html_e( 'Manage site backups and migrations directly from the Backup & Migrate tab.', 'emcp-tools' ); ?>
		</p>
		<?php
	}
}
