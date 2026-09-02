<?php
/**
 * Backup & Migrate admin view.
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( isset( $_POST['emcp_create_backup'] ) && check_admin_referer( 'emcp_migrate_action' ) ) {
	if ( current_user_can( 'manage_options' ) ) {
		$name = sanitize_file_name( (string) ( $_POST['backup_name'] ?? '' ) );
		$archive = EMCP_Tools_Packager::create_archive( $name );
		if ( $archive ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Backup archive "%s" created successfully.', 'emcp-tools' ), esc_html( basename( $archive ) ) ) . '</p></div>';
		}
	}
}

$backups = EMCP_Tools_Packager::list_archives();
?>

<div class="wrap elementor-mcp-migrate">
	<h2>
		<?php esc_html_e( 'Backup, Sync & Migrate', 'emcp-tools' ); ?>
		<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO UNLOCKED</span>
	</h2>
	<p class="description">
		<?php esc_html_e( 'Create portable .emcp site archives, download backups, and synchronize changes across staging and live environments.', 'emcp-tools' ); ?>
	</p>

	<div class="postbox" style="margin-top: 20px; max-width: 700px;">
		<div class="postbox-header">
			<h3 class="hndle"><?php esc_html_e( 'Create New Backup', 'emcp-tools' ); ?></h3>
		</div>
		<div class="inside">
			<form method="post" action="">
				<?php wp_nonce_field( 'emcp_migrate_action' ); ?>
				<p>
					<label for="backup_name"><?php esc_html_e( 'Backup Name (optional):', 'emcp-tools' ); ?></label><br>
					<input type="text" name="backup_name" id="backup_name" class="regular-text" placeholder="e.g. pre-redesign-backup">
				</p>
				<p>
					<button type="submit" name="emcp_create_backup" class="button button-primary"><?php esc_html_e( 'Create .emcp Archive', 'emcp-tools' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<div class="postbox" style="margin-top: 20px;">
		<div class="postbox-header">
			<h3 class="hndle"><?php esc_html_e( 'Existing Backups', 'emcp-tools' ); ?> (<?php echo count( $backups ); ?>)</h3>
		</div>
		<div class="inside">
			<?php if ( empty( $backups ) ) : ?>
				<p><em><?php esc_html_e( 'No backups created yet.', 'emcp-tools' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Filename', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Size', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Date Created', 'emcp-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $backups as $b ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $b['filename'] ); ?></strong></td>
								<td><?php echo esc_html( $b['size'] ); ?></td>
								<td><?php echo esc_html( $b['date'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
