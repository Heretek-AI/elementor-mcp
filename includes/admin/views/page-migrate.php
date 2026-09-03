<?php
/**
 * Backup & Migrate admin view.
 *
 * Pure presentation — every action posts to the module's admin-post handler
 * (EMCP_Tools_Migrate_Module::ADMIN_ACTION), so a page refresh never re-runs a
 * restore or delete. Restore is deliberately admin-only.
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'EMCP_Tools_Migrate_Module' ) ) {
	return;
}
$emcp_action = EMCP_Tools_Migrate_Module::ADMIN_ACTION;
$emcp_notice = EMCP_Tools_Migrate_Module::consume_notice();

if ( ! class_exists( 'ZipArchive' ) ) {
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'The PHP ZipArchive extension is not installed on this server. Backups cannot be created or restored.', 'emcp-tools' ) . '</p></div>';
}

$emcp_backups = class_exists( 'EMCP_Tools_Packager' ) ? EMCP_Tools_Packager::list_archives() : array();
$emcp_log     = class_exists( 'EMCP_Tools_Restore_Engine' ) ? EMCP_Tools_Restore_Engine::get_log() : array();

if ( $emcp_notice ) {
	$emcp_class = ( 'success' === $emcp_notice['type'] ) ? 'notice-success' : 'notice-error';
	echo '<div class="notice ' . esc_attr( $emcp_class ) . ' is-dismissible"><p>' . esc_html( $emcp_notice['message'] ) . '</p></div>';
}
?>

<div class="emcp-migrate">
	<h2><?php esc_html_e( 'Backup, Sync & Migrate', 'emcp-tools' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Create portable .emcp archives of this site (database + optional files), restore them, or push them to a live site running the EMCP connector. Restoring replaces the current database with the archive — use with care.', 'emcp-tools' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 12px 0;">
		<input type="hidden" name="action" value="emcp_tools_download_connector">
		<?php wp_nonce_field( $emcp_action ); ?>
		<p>
			<button type="submit" class="button"><?php esc_html_e( 'Download Connector Plugin', 'emcp-tools' ); ?></button>
			<span class="description">
				<?php esc_html_e( 'Install this small plugin on the destination site, define EMCP_CONNECTOR_SECRET there, then use emcp-tools/migrate-site to push.', 'emcp-tools' ); ?>
			</span>
		</p>
	</form>

	<div class="postbox" style="margin-top: 20px; max-width: 760px;">
		<div class="postbox-header">
			<h3 class="hndle"><?php esc_html_e( 'Create Archive', 'emcp-tools' ); ?></h3>
		</div>
		<div class="inside">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $emcp_action ); ?>">
				<input type="hidden" name="emcp_migrate_action" value="create">
				<?php wp_nonce_field( $emcp_action ); ?>
				<p>
					<label for="emcp_backup_name"><?php esc_html_e( 'Archive name (optional):', 'emcp-tools' ); ?></label><br>
					<input type="text" name="backup_name" id="emcp_backup_name" class="regular-text" placeholder="e.g. pre-redesign-backup">
				</p>
				<p>
					<label>
						<input type="checkbox" name="include_files" value="1">
						<?php esc_html_e( 'Also bundle site files (uploads / plugins / themes). Larger archives, slower to create.', 'emcp-tools' ); ?>
					</label>
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Create .emcp Archive', 'emcp-tools' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<div class="postbox" style="margin-top: 20px;">
		<div class="postbox-header">
			<h3 class="hndle"><?php esc_html_e( 'Existing Archives', 'emcp-tools' ); ?> (<?php echo count( $emcp_backups ); ?>)</h3>
		</div>
		<div class="inside">
			<?php if ( empty( $emcp_backups ) ) : ?>
				<p><em><?php esc_html_e( 'No archives created yet.', 'emcp-tools' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Filename', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Size', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Created', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'emcp-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $emcp_backups as $emcp_b ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $emcp_b['filename'] ); ?></strong></td>
								<td><?php echo esc_html( $emcp_b['size'] ); ?></td>
								<td><?php echo esc_html( $emcp_b['date'] ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
										<input type="hidden" name="action" value="<?php echo esc_attr( $emcp_action ); ?>">
										<input type="hidden" name="emcp_migrate_action" value="download">
										<input type="hidden" name="archive" value="<?php echo esc_attr( $emcp_b['filename'] ); ?>">
										<?php wp_nonce_field( $emcp_action ); ?>
										<button type="submit" class="button"><?php esc_html_e( 'Download', 'emcp-tools' ); ?></button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;"
										onsubmit="return confirm('<?php echo esc_js( __( 'Restore replaces the current database (and files if present) with this archive. Continue?', 'emcp-tools' ) ); ?>');">
										<input type="hidden" name="action" value="<?php echo esc_attr( $emcp_action ); ?>">
										<input type="hidden" name="emcp_migrate_action" value="restore">
										<input type="hidden" name="archive" value="<?php echo esc_attr( $emcp_b['filename'] ); ?>">
										<?php wp_nonce_field( $emcp_action ); ?>
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Restore', 'emcp-tools' ); ?></button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;"
										onsubmit="return confirm('<?php echo esc_js( __( 'Delete this archive permanently?', 'emcp-tools' ) ); ?>');">
										<input type="hidden" name="action" value="<?php echo esc_attr( $emcp_action ); ?>">
										<input type="hidden" name="emcp_migrate_action" value="delete">
										<input type="hidden" name="archive" value="<?php echo esc_attr( $emcp_b['filename'] ); ?>">
										<?php wp_nonce_field( $emcp_action ); ?>
										<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'emcp-tools' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $emcp_log ) ) : ?>
		<div class="postbox" style="margin-top: 20px;">
			<div class="postbox-header">
				<h3 class="hndle"><?php esc_html_e( 'Restore History', 'emcp-tools' ); ?></h3>
			</div>
			<div class="inside">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Archive', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Result', 'emcp-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $emcp_log as $emcp_entry ) : ?>
							<tr>
								<td><?php echo esc_html( isset( $emcp_entry['time'] ) ? $emcp_entry['time'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $emcp_entry['filename'] ) ? $emcp_entry['filename'] : '' ); ?></td>
								<td>
									<?php
									$emcp_parts = array();
									if ( ! empty( $emcp_entry['db'] ) && isset( $emcp_entry['db']['errors'] ) ) {
										$emcp_parts[] = sprintf( /* translators: 1: executed, 2: errors. */ __( 'DB %1$d stmts, %2$d errors', 'emcp-tools' ), (int) $emcp_entry['db']['executed'], (int) $emcp_entry['db']['errors'] );
									}
									if ( ! empty( $emcp_entry['search_replace']['rows'] ) ) {
										$emcp_parts[] = sprintf( /* translators: %d: rewritten rows. */ __( '%d URL rewrites', 'emcp-tools' ), (int) $emcp_entry['search_replace']['rows'] );
									}
									if ( ! empty( $emcp_entry['files_placed'] ) ) {
										$emcp_parts[] = sprintf( /* translators: %d: files placed. */ __( '%d files placed', 'emcp-tools' ), (int) $emcp_entry['files_placed'] );
									}
									if ( empty( $emcp_parts ) && empty( $emcp_entry['errors'] ) ) {
										$emcp_parts[] = __( 'OK', 'emcp-tools' );
									}
									if ( ! empty( $emcp_entry['errors'] ) ) {
										$emcp_parts[] = __( 'with errors', 'emcp-tools' );
									}
									echo esc_html( implode( ' — ', $emcp_parts ) );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>
