<?php
/**
 * Redirects admin tab — manage 301/302 redirects, review suggested redirects
 * from AI deletes/renames, and run the broken-link scan. All actions route
 * through EMCP_Tools_Redirect_Store + the change ledger (reversible in History).
 *
 * Rendered inside EMCP_Tools_Admin::render_page() ($this = the admin instance).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_store_ok    = class_exists( 'EMCP_Tools_Redirect_Store' );
$emcp_rows        = $emcp_store_ok ? EMCP_Tools_Redirect_Store::all( array( 'limit' => 500 ) ) : array();
$emcp_suggestions = get_option( 'emcp_tools_redirect_suggestions', array() );
$emcp_suggestions = is_array( $emcp_suggestions ) ? $emcp_suggestions : array();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice + edit prefill on an admin-gated page.
$emcp_notice = isset( $_GET['notice'] ) ? sanitize_text_field( wp_unslash( $_GET['notice'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- edit prefill only.
$emcp_edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
$emcp_edit    = ( $emcp_edit_id && $emcp_store_ok ) ? EMCP_Tools_Redirect_Store::get( $emcp_edit_id ) : null;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- prefill source from an accepted suggestion.
$emcp_prefill_source = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';

$emcp_notices = array(
	'created' => array( 'ok', __( 'Redirect created.', 'emcp-tools' ) ),
	'updated' => array( 'ok', __( 'Redirect updated.', 'emcp-tools' ) ),
	'deleted' => array( 'ok', __( 'Redirect deleted (reversible from History).', 'emcp-tools' ) ),
);
?>
<div class="emcp-redirects">
	<style>
		.emcp-redirects { max-width: 1000px; color: #f1f1f5; }
		.emcp-redirects .emcp-rd-card {
			background: var(--mcp-white, #111116);
			border: 1px solid var(--mcp-gray-200, #1f1f27);
			border-radius: 8px;
			padding: 24px;
			margin: 0 0 24px;
			color: #f1f1f5;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
		}
		.emcp-redirects .emcp-rd-card h2 { margin-top: 0; color: #ffffff; font-family: var(--mcp-font-heading); }
		.emcp-redirects .emcp-rd-card .description { color: #a1a1aa; font-size: 13px; line-height: 1.5; }
		.emcp-redirects .emcp-rd-form label { display: block; font-weight: 600; margin: 12px 0 6px; color: #ffffff; font-size: 13px; }
		.emcp-redirects .emcp-rd-form input[type="text"],
		.emcp-redirects .emcp-rd-form input[type="url"],
		.emcp-redirects .emcp-rd-form input[type="number"],
		.emcp-redirects .emcp-rd-form select {
			width: 100%;
			max-width: 520px;
			background: #14141a;
			color: #ffffff;
			border: 1px solid var(--mcp-gray-200, #1f1f27);
			border-radius: 6px;
			padding: 8px 12px;
			box-sizing: border-box;
		}
		.emcp-redirects .emcp-rd-form input:focus,
		.emcp-redirects .emcp-rd-form select:focus {
			border-color: var(--mcp-primary, #dc2626);
			outline: none;
			box-shadow: 0 0 0 1px var(--mcp-primary, #dc2626);
		}
		.emcp-redirects .emcp-rd-row { display: flex; gap: 24px; flex-wrap: wrap; }
		.emcp-redirects .emcp-rd-notice {
			padding: 12px 16px;
			border-radius: 6px;
			margin: 0 0 20px;
			border-left: 4px solid var(--mcp-primary, #dc2626);
			background: rgba(220, 38, 38, 0.12);
			color: #fecaca;
			border-top: 1px solid rgba(220, 38, 38, 0.2);
			border-right: 1px solid rgba(220, 38, 38, 0.2);
			border-bottom: 1px solid rgba(220, 38, 38, 0.2);
		}
		.emcp-redirects .emcp-rd-notice.err {
			border-left-color: #ef4444;
			background: rgba(239, 68, 68, 0.15);
			color: #fee2e2;
		}
		.emcp-redirects .emcp-rd-sugg {
			border-left: 4px solid #f59e0b;
			background: rgba(245, 158, 11, 0.12);
			border: 1px solid rgba(245, 158, 11, 0.25);
			border-left-width: 4px;
			padding: 12px 16px;
			border-radius: 6px;
			margin: 0 0 12px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			color: #fef3c7;
		}
		.emcp-redirects code {
			background: #050507;
			color: #f1f1f5;
			padding: 2px 7px;
			border-radius: 4px;
			border: 1px solid var(--mcp-gray-200, #1f1f27);
			font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
		}
		.emcp-redirects table.widefat {
			background: var(--mcp-white, #111116) !important;
			color: #dedee6 !important;
			border: 1px solid var(--mcp-gray-200, #1f1f27) !important;
			border-radius: 6px;
			box-shadow: none;
			border-collapse: separate;
			border-spacing: 0;
			overflow: hidden;
		}
		.emcp-redirects table.widefat thead th {
			background: #16161d !important;
			color: #ffffff !important;
			border-bottom: 1px solid var(--mcp-gray-200, #1f1f27) !important;
			font-weight: 700;
			padding: 10px 12px;
		}
		.emcp-redirects table.widefat td {
			color: #dedee6 !important;
			border-top: 1px solid var(--mcp-gray-200, #1f1f27) !important;
			padding: 10px 12px;
			vertical-align: middle;
		}
		.emcp-redirects table.widefat.striped > tbody > :nth-child(odd) {
			background: #14141a !important;
		}
		.emcp-redirects table.widefat.striped > tbody > :nth-child(even) {
			background: #111116 !important;
		}
		.emcp-redirects table.widefat a {
			color: #f87171 !important;
			text-decoration: none;
		}
		.emcp-redirects table.widefat a:hover {
			text-decoration: underline;
			color: #ffffff !important;
		}
		.emcp-redirects .emcp-rd-off { opacity: .55; }
	</style>

	<?php if ( isset( $emcp_notices[ $emcp_notice ] ) ) : ?>
		<div class="emcp-rd-notice"><?php echo esc_html( $emcp_notices[ $emcp_notice ][1] ); ?></div>
	<?php elseif ( 0 === strpos( $emcp_notice, 'error' ) ) : ?>
		<div class="emcp-rd-notice err"><?php echo esc_html__( 'Could not save the redirect.', 'emcp-tools' ) . ' ' . esc_html( substr( $emcp_notice, 6 ) ); ?></div>
	<?php endif; ?>

	<?php if ( ! $emcp_store_ok ) : ?>
		<div class="emcp-rd-notice err"><?php esc_html_e( 'The redirect store is unavailable.', 'emcp-tools' ); ?></div>
	<?php else : ?>

	<div class="emcp-rd-card">
		<h2><?php echo $emcp_edit ? esc_html__( 'Edit Redirect', 'emcp-tools' ) : esc_html__( 'Add Redirect', 'emcp-tools' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Send an old path to a new URL or post. Use this after deleting or renaming a page so old links keep working.', 'emcp-tools' ); ?></p>
		<form class="emcp-rd-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="emcp_tools_redirect_save" />
			<?php wp_nonce_field( 'emcp_tools_redirect_save' ); ?>
			<input type="hidden" name="redirect_id" value="<?php echo (int) ( $emcp_edit['id'] ?? 0 ); ?>" />

			<label for="emcp-rd-source"><?php esc_html_e( 'Source path', 'emcp-tools' ); ?></label>
			<input type="text" id="emcp-rd-source" name="source" placeholder="/old-page" required
				value="<?php echo esc_attr( $emcp_edit['source_path'] ?? $emcp_prefill_source ); ?>" />

			<div class="emcp-rd-row">
				<div style="flex:1 1 320px;">
					<label for="emcp-rd-target"><?php esc_html_e( 'Target URL', 'emcp-tools' ); ?></label>
					<input type="text" id="emcp-rd-target" name="target" placeholder="https://example.com/new-page"
						value="<?php echo esc_attr( ( empty( $emcp_edit['target_post_id'] ) ? ( $emcp_edit['target'] ?? '' ) : '' ) ); ?>" />
				</div>
				<div style="flex:0 0 200px;">
					<label for="emcp-rd-post"><?php esc_html_e( 'or Target post ID', 'emcp-tools' ); ?></label>
					<input type="number" id="emcp-rd-post" name="target_post_id" min="0" style="width:120px;"
						value="<?php echo esc_attr( ! empty( $emcp_edit['target_post_id'] ) ? (int) $emcp_edit['target_post_id'] : '' ); ?>" />
				</div>
			</div>

			<div class="emcp-rd-row">
				<div>
					<label for="emcp-rd-code"><?php esc_html_e( 'Status code', 'emcp-tools' ); ?></label>
					<select id="emcp-rd-code" name="status_code">
						<option value="301" <?php selected( (int) ( $emcp_edit['status_code'] ?? 301 ), 301 ); ?>><?php esc_html_e( '301 Permanent', 'emcp-tools' ); ?></option>
						<option value="302" <?php selected( (int) ( $emcp_edit['status_code'] ?? 301 ), 302 ); ?>><?php esc_html_e( '302 Temporary', 'emcp-tools' ); ?></option>
					</select>
				</div>
				<div>
					<label><?php esc_html_e( 'Query string', 'emcp-tools' ); ?></label>
					<label style="font-weight:400;"><input type="checkbox" name="ignore_query" value="1" <?php checked( ! isset( $emcp_edit['ignore_query'] ) || ! empty( $emcp_edit['ignore_query'] ) ); ?> /> <?php esc_html_e( 'Match regardless of ?query', 'emcp-tools' ); ?></label>
				</div>
			</div>

			<p style="margin-top:16px;">
				<button type="submit" class="button button-primary"><?php echo $emcp_edit ? esc_html__( 'Update redirect', 'emcp-tools' ) : esc_html__( 'Add redirect', 'emcp-tools' ); ?></button>
				<?php if ( $emcp_edit ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=emcp-tools-redirects' ) ); ?>"><?php esc_html_e( 'Cancel', 'emcp-tools' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<?php if ( ! empty( $emcp_suggestions ) ) : ?>
	<div class="emcp-rd-card">
		<h2><?php esc_html_e( 'Suggested redirects', 'emcp-tools' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Old URLs left dead by a delete or rename. Add a redirect so visitors and search engines do not hit a 404.', 'emcp-tools' ); ?></p>
		<?php foreach ( array_reverse( $emcp_suggestions ) as $emcp_s ) :
			$emcp_old = (string) ( $emcp_s['old_path'] ?? '' );
			if ( '' === $emcp_old ) {
				continue;
			}
			?>
			<div class="emcp-rd-sugg">
				<span><code><?php echo esc_html( $emcp_old ); ?></code> — <?php echo esc_html( 'slug-changed' === ( $emcp_s['reason'] ?? '' ) ? __( 'renamed', 'emcp-tools' ) : __( 'deleted', 'emcp-tools' ) ); ?></span>
				<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=emcp-tools-redirects&source=' . rawurlencode( $emcp_old ) ) . '#emcp-rd-source' ); ?>"><?php esc_html_e( 'Add redirect', 'emcp-tools' ); ?></a>
			</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="emcp-rd-card">
		<h2><?php esc_html_e( 'Active redirects', 'emcp-tools' ); ?> <span class="count">(<?php echo (int) count( $emcp_rows ); ?>)</span></h2>
		<?php if ( empty( $emcp_rows ) ) : ?>
			<p><?php esc_html_e( 'No redirects yet.', 'emcp-tools' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source', 'emcp-tools' ); ?></th>
						<th><?php esc_html_e( 'Target', 'emcp-tools' ); ?></th>
						<th><?php esc_html_e( 'Code', 'emcp-tools' ); ?></th>
						<th><?php esc_html_e( 'Hits', 'emcp-tools' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'emcp-tools' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $emcp_rows as $emcp_r ) :
					$emcp_tgt = ! empty( $emcp_r['target_post_id'] )
						? sprintf( __( 'post #%d', 'emcp-tools' ), (int) $emcp_r['target_post_id'] )
						: (string) $emcp_r['target'];
					?>
					<tr class="<?php echo empty( $emcp_r['enabled'] ) ? 'emcp-rd-off' : ''; ?>">
						<td><code><?php echo esc_html( $emcp_r['source_path'] ); ?></code></td>
						<td><?php echo esc_html( $emcp_tgt ); ?></td>
						<td><?php echo (int) $emcp_r['status_code']; ?></td>
						<td><?php echo (int) $emcp_r['hits']; ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=emcp-tools-redirects&edit=' . (int) $emcp_r['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'emcp-tools' ); ?></a> |
							<a href="<?php echo esc_url( EMCP_Tools_Admin::redirect_toggle_url( (int) $emcp_r['id'] ) ); ?>"><?php echo empty( $emcp_r['enabled'] ) ? esc_html__( 'Enable', 'emcp-tools' ) : esc_html__( 'Disable', 'emcp-tools' ); ?></a> |
							<a href="<?php echo esc_url( EMCP_Tools_Admin::redirect_delete_url( (int) $emcp_r['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this redirect? (reversible from History)', 'emcp-tools' ) ); ?>');" style="color:#b32d2e;"><?php esc_html_e( 'Delete', 'emcp-tools' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php endif; ?>
</div>
