<?php
/**
 * Backup & Migrate admin view.
 *
 * Pure presentation — every action posts to the module's admin-post handler
 * (EMCP_Tools_Migrate_Module::ADMIN_ACTION), so a page refresh never re-runs a
 * restore, push, or delete. Restore is deliberately admin-only; pairing and
 * push/sync run from here as well.
 *
 * Sub-views (nav tabs): archives (default) | push | sync.
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
$emcp_view   = EMCP_Tools_Migrate_Module::migrate_view();
// PAGE_SLUG lives on the admin class; fall back when this view is included
// outside the admin render (tests), where the class is not loaded.
$emcp_page_slug = ( class_exists( 'EMCP_Tools_Admin' ) && defined( 'EMCP_Tools_Admin::PAGE_SLUG' ) ) ? EMCP_Tools_Admin::PAGE_SLUG : 'emcp-tools';
$emcp_page      = admin_url( 'admin.php?page=' . $emcp_page_slug . '-migrate' );

if ( in_array( $emcp_view, array( 'push', 'sync' ), true ) ) {
	$emcp_js = class_exists( 'EMCP_Tools_Pro_Loader' ) ? EMCP_Tools_Pro_Loader::url( 'assets/js/migrate.js' ) : '';
	if ( '' !== $emcp_js ) {
		wp_enqueue_script( 'emcp-tools-migrate', $emcp_js, array(), EMCP_Tools_Pro_Loader::asset_version( 'assets/js/migrate.js' ), true );
	}
}

if ( ! class_exists( 'ZipArchive' ) ) {
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'The PHP ZipArchive extension is not installed on this server. Backups cannot be created or restored.', 'emcp-tools' ) . '</p></div>';
}

if ( $emcp_notice ) {
	$emcp_class = ( 'success' === $emcp_notice['type'] ) ? 'notice-success' : 'notice-error';
	echo '<div class="notice ' . esc_attr( $emcp_class ) . ' is-dismissible"><p>' . esc_html( $emcp_notice['message'] ) . '</p></div>';
}

$emcp_has_wpdb = isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] );
$emcp_targets = ( $emcp_has_wpdb && class_exists( 'EMCP_Tools_Migrate_Targets' ) ) ? EMCP_Tools_Migrate_Targets::list_for_admin() : array();
$emcp_backups = class_exists( 'EMCP_Tools_Packager' ) ? EMCP_Tools_Packager::list_archives() : array();
$emcp_log     = class_exists( 'EMCP_Tools_Restore_Engine' ) ? EMCP_Tools_Restore_Engine::get_log() : array();
$emcp_tables  = ( $emcp_has_wpdb && class_exists( 'EMCP_Tools_Sync_Engine' ) ) ? EMCP_Tools_Sync_Engine::candidate_tables() : array();
$emcp_roots   = class_exists( 'EMCP_Tools_Sync_Engine' ) ? EMCP_Tools_Sync_Engine::candidate_file_roots() : array();
?>

<div class="emcp-migrate">
	<h2><?php esc_html_e( 'Backup, Sync & Migrate', 'emcp-tools' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Create portable .emcp archives of this site (database + optional files), restore them, or push/sync them to a live site running the EMCP connector. Restoring replaces the current database with the archive — use with care.', 'emcp-tools' ); ?>
	</p>

	<nav class="nav-tab-wrapper">
		<?php foreach ( array(
			'archives' => __( 'Archives', 'emcp-tools' ),
			'push'     => __( 'Push to Site', 'emcp-tools' ),
			'sync'     => __( 'Sync', 'emcp-tools' ),
		) as $emcp_tab => $emcp_tab_label ) : ?>
			<a class="nav-tab <?php echo ( $emcp_view === $emcp_tab ) ? 'nav-tab-active' : ''; ?>"
				href="<?php echo esc_url( $emcp_page . ( 'archives' !== $emcp_tab ? '&view=' . $emcp_tab : '' ) ); ?>">
				<?php echo esc_html( $emcp_tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'archives' === $emcp_view ) : ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 12px 0;">
			<input type="hidden" name="action" value="emcp_tools_download_connector">
			<?php wp_nonce_field( $emcp_action ); ?>
			<p>
				<button type="submit" class="button"><?php esc_html_e( 'Download Connector Plugin', 'emcp-tools' ); ?></button>
				<span class="description">
					<?php esc_html_e( 'Install this small plugin on the destination site, define EMCP_CONNECTOR_SECRET there, then pair it on the "Push to Site" tab (or use emcp-tools/migrate-site) to push.', 'emcp-tools' ); ?>
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

	<?php elseif ( 'push' === $emcp_view ) : ?>

		<?php $emcp_has_engines = class_exists( 'EMCP_Tools_Packager' ) && class_exists( 'EMCP_Tools_Migration_Engine' ); ?>

		<div class="postbox" style="margin-top: 20px;">
			<div class="postbox-header">
				<h3 class="hndle"><?php esc_html_e( 'Paired Destinations', 'emcp-tools' ); ?> (<?php echo count( $emcp_targets ); ?>)</h3>
			</div>
			<div class="inside">
				<?php if ( empty( $emcp_targets ) ) : ?>
					<p><em><?php esc_html_e( 'No paired destinations yet. Generate a single-use pairing code on the destination\'s EMCP Connector settings page, then add it below.', 'emcp-tools' ); ?></em></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Label', 'emcp-tools' ); ?></th>
								<th><?php esc_html_e( 'URL', 'emcp-tools' ); ?></th>
								<th><?php esc_html_e( 'Connector', 'emcp-tools' ); ?></th>
								<th><?php esc_html_e( 'Paired', 'emcp-tools' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'emcp-tools' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $emcp_targets as $emcp_t ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $emcp_t['label'] ); ?></strong></td>
									<td><code><?php echo esc_html( $emcp_t['target_url'] ); ?></code></td>
									<td><?php echo esc_html( $emcp_t['connector_version'] ? $emcp_t['connector_version'] : '—' ); ?></td>
									<td><?php echo esc_html( $emcp_t['confirmed_at'] ); ?></td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
											<input type="hidden" name="action" value="<?php echo esc_attr( $emcp_action ); ?>">
											<input type="hidden" name="emcp_migrate_action" value="verify_target">
											<input type="hidden" name="target_id" value="<?php echo (int) $emcp_t['id']; ?>">
											<?php wp_nonce_field( $emcp_action ); ?>
											<button type="submit" class="button"><?php esc_html_e( 'Verify', 'emcp-tools' ); ?></button>
										</form>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;"
											onsubmit="return confirm('<?php echo esc_js( __( 'Remove this paired destination?', 'emcp-tools' ) ); ?>');">
											<input type="hidden" name="action" value="<?php echo esc_attr( $emcp_action ); ?>">
											<input type="hidden" name="emcp_migrate_action" value="delete_target">
											<input type="hidden" name="target_id" value="<?php echo (int) $emcp_t['id']; ?>">
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

		<div class="postbox" style="margin-top: 20px; max-width: 760px;">
			<div class="postbox-header">
				<h3 class="hndle"><?php esc_html_e( 'Add a Paired Destination', 'emcp-tools' ); ?></h3>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'On the destination site (EMCP Connector → settings), generate a single-use pairing code (valid 15 minutes) and enter it here with the destination URL. The destination connector must be reachable over HTTPS unless it is localhost.', 'emcp-tools' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $emcp_action ); ?>">
					<input type="hidden" name="emcp_migrate_action" value="add_target">
					<?php wp_nonce_field( $emcp_action ); ?>
					<p>
						<label for="emcp_target_label"><?php esc_html_e( 'Label:', 'emcp-tools' ); ?></label><br>
						<input type="text" name="label" id="emcp_target_label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Production', 'emcp-tools' ); ?>">
					</p>
					<p>
						<label for="emcp_target_url"><?php esc_html_e( 'Destination URL:', 'emcp-tools' ); ?></label><br>
						<input type="url" name="target_url" id="emcp_target_url" class="regular-text" placeholder="https://live.example.com" required>
					</p>
					<p>
						<label for="emcp_pairing_code"><?php esc_html_e( 'Single-use pairing code:', 'emcp-tools' ); ?></label><br>
						<input type="text" name="pairing_code" id="emcp_pairing_code" class="regular-text code" placeholder="EMCP-PAIR-…" autocomplete="off" required>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Pair & Verify', 'emcp-tools' ); ?></button>
					</p>
				</form>
			</div>
		</div>

		<div class="postbox" style="margin-top: 20px; max-width: 760px;">
			<div class="postbox-header">
				<h3 class="hndle"><?php esc_html_e( 'Push a Full Site', 'emcp-tools' ); ?></h3>
			</div>
			<div class="inside">
				<?php if ( ! $emcp_has_engines ) : ?>
					<p class="description"><?php esc_html_e( 'The migrate engine is not available in this build.', 'emcp-tools' ); ?></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="emcp-migrate-long">
						<input type="hidden" name="action" value="<?php echo esc_attr( $emcp_action ); ?>">
						<input type="hidden" name="emcp_migrate_action" value="push">
						<?php wp_nonce_field( $emcp_action ); ?>
						<p>
							<label for="emcp_push_target"><?php esc_html_e( 'Destination:', 'emcp-tools' ); ?></label>
							<select name="target_id" id="emcp_push_target">
								<?php foreach ( $emcp_targets as $emcp_t ) : ?>
									<option value="<?php echo (int) $emcp_t['id']; ?>"><?php echo esc_html( $emcp_t['label'] . ' — ' . $emcp_t['target_url'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<details style="margin-top:6px"><summary class="description"><?php esc_html_e( 'Advanced: raw URL + shared secret (no pairing)', 'emcp-tools' ); ?></summary>
								<p>
									<label for="emcp_push_remote_url"><?php esc_html_e( 'Destination URL:', 'emcp-tools' ); ?></label><br>
									<input type="url" name="remote_url" id="emcp_push_remote_url" class="regular-text" placeholder="https://live.example.com">
									<br>
									<label for="emcp_push_secret_key"><?php esc_html_e( 'Shared secret:', 'emcp-tools' ); ?></label><br>
									<input type="password" name="secret_key" id="emcp_push_secret_key" class="regular-text" placeholder="<?php esc_attr_e( 'Shared secret', 'emcp-tools' ); ?>" autocomplete="off">
								</p>
							</details>
						</p>
						<p>
							<label for="emcp_push_archive"><?php esc_html_e( 'Archive source:', 'emcp-tools' ); ?></label><br>
							<label><input type="radio" name="archive_source" value="build" checked> <?php esc_html_e( 'Build a fresh full archive', 'emcp-tools' ); ?></label>
							<label style="margin-left:10px"><input type="checkbox" name="include_files" value="1"> <?php esc_html_e( '…with site files', 'emcp-tools' ); ?></label>
							<br>
							<?php if ( ! empty( $emcp_backups ) ) : ?>
								<label><input type="radio" name="archive_source" value="existing"> <?php esc_html_e( 'Use an existing archive:', 'emcp-tools' ); ?></label>
								<label for="emcp_push_archive_select" class="screen-reader-text"><?php esc_html_e( 'Choose an archive', 'emcp-tools' ); ?></label>
								<select name="archive" id="emcp_push_archive_select">
									<?php foreach ( $emcp_backups as $emcp_b ) : ?>
										<option value="<?php echo esc_attr( $emcp_b['filename'] ); ?>"><?php echo esc_html( $emcp_b['filename'] . ' (' . $emcp_b['size'] . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</p>
						<p>
							<label>
								<input type="checkbox" name="confirm" value="1">
								<?php esc_html_e( 'I understand this replaces the database (and any files in the archive) on the destination.', 'emcp-tools' ); ?>
							</label>
						</p>
						<p class="emcp-working-note description" style="display:none; color:#b32d2e;"><?php esc_html_e( 'Working — pushing a large archive can take minutes. Do not close this tab.', 'emcp-tools' ); ?></p>
						<p>
							<button type="submit" class="button button-primary" data-working="<?php esc_attr_e( 'Pushing…', 'emcp-tools' ); ?>"><?php esc_html_e( 'Push Site', 'emcp-tools' ); ?></button>
						</p>
					</form>
				<?php endif; ?>
			</div>
		</div>

	<?php elseif ( 'sync' === $emcp_view ) : ?>

		<?php $emcp_has_sync = class_exists( 'EMCP_Tools_Sync_Engine' ); ?>

		<div class="postbox" style="margin-top: 20px; max-width: 840px;">
			<div class="postbox-header">
				<h3 class="hndle"><?php esc_html_e( 'Sync Scope', 'emcp-tools' ); ?></h3>
			</div>
			<div class="inside">
				<?php if ( ! $emcp_has_sync ) : ?>
					<p class="description"><?php esc_html_e( 'The sync engine is not available in this build.', 'emcp-tools' ); ?></p>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'Push a full or selective scope to a paired destination running the EMCP connector 1.2.0+. The destination imports only what this archive holds, and rewrites URLs only over the tables that were imported.', 'emcp-tools' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="emcp-migrate-long">
						<input type="hidden" name="action" value="<?php echo esc_attr( $emcp_action ); ?>">
						<input type="hidden" name="emcp_migrate_action" value="sync">
						<?php wp_nonce_field( $emcp_action ); ?>
						<p>
							<label for="emcp_sync_target"><?php esc_html_e( 'Destination:', 'emcp-tools' ); ?></label>
							<select name="target_id" id="emcp_sync_target">
								<?php foreach ( $emcp_targets as $emcp_t ) : ?>
									<option value="<?php echo (int) $emcp_t['id']; ?>"><?php echo esc_html( $emcp_t['label'] . ' — ' . $emcp_t['target_url'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>

						<h4><?php esc_html_e( 'Database', 'emcp-tools' ); ?></h4>
						<p>
							<label><input type="radio" name="db_mode" value="all" checked> <?php esc_html_e( 'All tables', 'emcp-tools' ); ?></label>
							<label style="margin-left:10px"><input type="radio" name="db_mode" value="none"> <?php esc_html_e( 'Skip database', 'emcp-tools' ); ?></label>
							<label style="margin-left:10px"><input type="radio" name="db_mode" value="selected"> <?php esc_html_e( 'Selected tables', 'emcp-tools' ); ?></label>
						</p>
						<div id="emcp_db_tables" style="display:none; max-height:200px; overflow:auto; border:1px solid #ccd0d4; padding:8px; margin-bottom:10px;">
							<?php if ( empty( $emcp_tables ) ) : ?>
								<p class="description"><?php esc_html_e( 'No prefixed tables found.', 'emcp-tools' ); ?></p>
							<?php else : ?>
								<label style="display:block; margin-bottom:4px;"><input type="checkbox" class="emcp-tables-all"> <?php esc_html_e( 'Select all', 'emcp-tools' ); ?></label>
								<?php foreach ( $emcp_tables as $emcp_table ) : ?>
									<label style="display:block;"><input type="checkbox" name="tables[]" value="<?php echo esc_attr( $emcp_table ); ?>"> <code><?php echo esc_html( $emcp_table ); ?></code></label>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>

						<h4><?php esc_html_e( 'Files', 'emcp-tools' ); ?></h4>
						<p>
							<label><input type="radio" name="files_mode" value="all" checked> <?php esc_html_e( 'All files', 'emcp-tools' ); ?></label>
							<label style="margin-left:10px"><input type="radio" name="files_mode" value="none"> <?php esc_html_e( 'Skip files', 'emcp-tools' ); ?></label>
							<label style="margin-left:10px"><input type="radio" name="files_mode" value="selected"> <?php esc_html_e( 'Selected roots', 'emcp-tools' ); ?></label>
						</p>
						<div id="emcp_file_roots" style="display:none; margin-bottom:10px;">
							<?php foreach ( $emcp_roots as $emcp_root_key => $emcp_root ) : ?>
								<label style="display:block;">
									<input type="checkbox" name="file_roots[]" value="<?php echo esc_attr( $emcp_root_key ); ?>">
									<strong><?php echo esc_html( $emcp_root['label'] ); ?></strong>
									<span class="description"> — <?php echo esc_html( $emcp_root['note'] ); ?></span>
								</label>
							<?php endforeach; ?>
							<p>
								<label for="emcp_pass_through"><?php esc_html_e( 'Extra wp-content-relative path (optional):', 'emcp-tools' ); ?></label><br>
								<input type="text" name="pass_through" id="emcp_pass_through" class="regular-text" placeholder="languages">
							</p>
						</div>

						<p>
							<label>
								<input type="checkbox" name="confirm" value="1">
								<?php esc_html_e( 'I understand this overwrites the selected tables/files on the destination.', 'emcp-tools' ); ?>
							</label>
						</p>
						<p class="emcp-working-note description" style="display:none; color:#b32d2e;"><?php esc_html_e( 'Working — syncing can take minutes. Do not close this tab.', 'emcp-tools' ); ?></p>
						<p>
							<button type="submit" class="button button-primary" data-working="<?php esc_attr_e( 'Syncing…', 'emcp-tools' ); ?>"><?php esc_html_e( 'Run Sync', 'emcp-tools' ); ?></button>
						</p>
					</form>
				<?php endif; ?>
			</div>
		</div>

	<?php endif; ?>
</div>
