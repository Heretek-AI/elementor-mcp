<?php
/**
 * Sandbox > Blocks view.
 *
 * Pro users: a table of AI-generated custom Gutenberg blocks with status,
 * last-error, view spec (block.json + render.php), activate/deactivate, and
 * delete. The blocks are created by AI agents through the MCP tools and live
 * in an isolated uploads sandbox — this screen is the human management /
 * kill-switch surface. Free users: upgrade CTA.
 *
 * Modeled on sandbox/widgets.php (same markup/JS pattern, widget → block).
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_tools_bb_pro = class_exists( 'EMCP_Tools_Block_Store' ) && EMCP_Tools_Block_Store::user_has_access();
$emcp_tools_bb_url = function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : '#';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice render after a redirect, no state change.
$emcp_tools_bb_imported = isset( $_GET['imported'] ) ? sanitize_text_field( wp_unslash( $_GET['imported'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice render after a redirect, no state change.
$emcp_tools_bb_import_error = isset( $_GET['import_error'] ) ? sanitize_text_field( wp_unslash( $_GET['import_error'] ) ) : '';
?>

<p class="emcp-sandbox-back">
	<a href="<?php echo esc_url( menu_page_url( 'emcp-tools-widgets', false ) ); ?>" class="elementor-mcp-header-btn elementor-mcp-header-btn--secondary">
		<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
		<?php esc_html_e( 'Back to Sandbox', 'emcp-tools' ); ?>
	</a>
</p>

<?php if ( '1' === $emcp_tools_bb_imported ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bundle imported as a new draft block.', 'emcp-tools' ); ?></p></div>
<?php elseif ( '' !== $emcp_tools_bb_import_error ) : ?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $emcp_tools_bb_import_error ); ?></p></div>
<?php endif; ?>

<div class="elementor-mcp-widget-builder">

	<div class="elementor-mcp-pro-prompts">
		<div class="elementor-mcp-pro-prompts-header">
			<div class="elementor-mcp-pro-prompts-heading">
				<h2>
					<?php esc_html_e( 'Blocks', 'emcp-tools' ); ?>
					<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO</span>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Custom Gutenberg blocks your AI agent generated through the MCP tools, starting from a structured spec. Everything lives in an isolated sandbox under wp-content/uploads, never in your theme, core, or other plugins. Active blocks appear in the block editor inserter under "EMCP Custom".', 'emcp-tools' ); ?>
				</p>
			</div>
		</div>

		<?php if ( ! $emcp_tools_bb_pro ) : ?>

			<div class="elementor-mcp-pro-cta">
				<p>
					<?php esc_html_e( 'Custom Blocks is a Pro feature. Upgrade to let AI agents design and ship custom Gutenberg blocks in an isolated sandbox.', 'emcp-tools' ); ?>
				</p>
				<a class="button button-primary" href="<?php echo esc_url( $emcp_tools_bb_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
				</a>
			</div>

		<?php else : ?>

			<?php $emcp_tools_bb_list = EMCP_Tools_Block_Store::instance()->list_blocks( 'any' ); ?>

			<div class="notice notice-warning inline" style="margin: 12px 0;">
				<p>
					<strong><?php esc_html_e( 'Heads up:', 'emcp-tools' ); ?></strong>
					<?php esc_html_e( 'These blocks are compiled by this plugin from an AI-supplied spec (the AI never writes raw PHP or JS). Output is escaped by control type. You can deactivate or delete any block here at any time.', 'emcp-tools' ); ?>
				</p>
			</div>

			<details style="margin: 14px 0;">
				<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( '+ Import a bundle', 'emcp-tools' ); ?></summary>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 12px;">
					<?php wp_nonce_field( EMCP_Tools_Admin::NONCE_SANDBOX_BUNDLE ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( EMCP_Tools_Admin::ACTION_IMPORT_ARTIFACT ); ?>" />
					<p>
						<input type="file" name="bundle" accept="application/json,.json" required />
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Import bundle', 'emcp-tools' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Import a .json bundle exported from another site (or from Export below). Imports always land as a new inactive draft.', 'emcp-tools' ); ?></p>
				</form>
			</details>

			<?php if ( empty( $emcp_tools_bb_list ) ) : ?>

				<p class="description" style="margin-top: 16px;">
					<?php esc_html_e( 'No custom blocks yet. Ask your AI agent to create one with the create-custom-block tool.', 'emcp-tools' ); ?>
				</p>

			<?php else : ?>

				<table class="widefat striped elementor-mcp-blocks-table" data-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_blocks' ) ); ?>" style="margin-top: 16px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Block', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Machine name', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Status', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'emcp-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $emcp_tools_bb_list as $emcp_tools_b ) :
							$emcp_tools_bid    = (int) $emcp_tools_b['block_id'];
							$emcp_tools_active = ( 'active' === $emcp_tools_b['status'] );
							$emcp_tools_bcode  = "// block.json\n" . EMCP_Tools_Block_Store::instance()->get_asset( $emcp_tools_bid, 'block.json' )
								. "\n\n// render.php\n" . EMCP_Tools_Block_Store::instance()->get_asset( $emcp_tools_bid, 'render.php' );
							?>
							<tr data-block-id="<?php echo esc_attr( (string) $emcp_tools_bid ); ?>">
								<td>
									<strong><?php echo esc_html( $emcp_tools_b['title'] ); ?></strong>
									<?php if ( ! empty( $emcp_tools_b['last_error'] ) ) : ?>
										<br /><span style="color:#b32d2e;font-size:12px;">
											<?php
											printf(
												/* translators: %s: error message */
												esc_html__( 'Auto-deactivated after an error: %s', 'emcp-tools' ),
												esc_html( $emcp_tools_b['last_error'] )
											);
											?>
										</span>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $emcp_tools_b['block_name'] ); ?></code></td>
								<td>
									<span class="elementor-mcp-badge <?php echo esc_attr( $emcp_tools_active ? 'elementor-mcp-badge--pro' : '' ); ?>">
										<?php echo $emcp_tools_active ? esc_html__( 'Active', 'emcp-tools' ) : esc_html__( 'Inactive', 'emcp-tools' ); ?>
									</span>
								</td>
								<td>
									<button type="button" class="button elementor-mcp-wb-toggle" data-status="<?php echo esc_attr( $emcp_tools_active ? 'draft' : 'active' ); ?>">
										<?php echo $emcp_tools_active ? esc_html__( 'Deactivate', 'emcp-tools' ) : esc_html__( 'Activate', 'emcp-tools' ); ?>
									</button>
									<button type="button" class="button elementor-mcp-wb-delete">
										<?php esc_html_e( 'Delete', 'emcp-tools' ); ?>
									</button>
									<a class="button" href="<?php echo esc_url( EMCP_Tools_Admin::sandbox_export_url( 'block', $emcp_tools_bid ) ); ?>">
										<?php esc_html_e( 'Export', 'emcp-tools' ); ?>
									</a>
									<button
										type="button"
										class="button"
										data-emcp-code-view
										data-emcp-code-title="<?php echo esc_attr( $emcp_tools_b['title'] ); ?>"
										data-emcp-code-filename="<?php echo esc_attr( $emcp_tools_b['block_name'] ); ?>.php"
									><?php esc_html_e( 'View code', 'emcp-tools' ); ?></button>
									<pre class="emcp-code-src" hidden><?php echo esc_html( $emcp_tools_bcode ); ?></pre>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<script>
				( function () {
					var table = document.querySelector( '.elementor-mcp-blocks-table' );
					if ( ! table ) { return; }
					var nonce = table.getAttribute( 'data-nonce' ) || '';
					var ajaxUrl = window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

					function post( action, body ) {
						body.append( 'action', action );
						body.append( 'nonce', nonce );
						return fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } );
					}

					table.addEventListener( 'click', function ( e ) {
						var row = e.target.closest( 'tr[data-block-id]' );
						if ( ! row ) { return; }
						var id = row.getAttribute( 'data-block-id' );

						if ( e.target.classList.contains( 'elementor-mcp-wb-toggle' ) ) {
							e.target.disabled = true;
							var b = new FormData();
							b.append( 'block_id', id );
							b.append( 'status', e.target.getAttribute( 'data-status' ) );
							post( 'emcp_tools_toggle_block', b ).then( function ( res ) {
								if ( res && res.success ) { window.location.reload(); }
								else { e.target.disabled = false; alert( ( res && res.data && res.data.message ) || 'Failed.' ); }
							} ).catch( function () { e.target.disabled = false; } );
						}

						if ( e.target.classList.contains( 'elementor-mcp-wb-delete' ) ) {
							/* global confirm */
							if ( ! confirm( '<?php echo esc_js( __( 'Delete this block permanently? Pages using it will lose it.', 'emcp-tools' ) ); ?>' ) ) { return; }
							e.target.disabled = true;
							var d = new FormData();
							d.append( 'block_id', id );
							post( 'emcp_tools_delete_block', d ).then( function ( res ) {
								if ( res && res.success ) { row.parentNode.removeChild( row ); }
								else { e.target.disabled = false; alert( ( res && res.data && res.data.message ) || 'Failed.' ); }
							} ).catch( function () { e.target.disabled = false; } );
						}
					} );
				} )();
				</script>

			<?php endif; ?>

		<?php endif; ?>

	</div>

</div>
