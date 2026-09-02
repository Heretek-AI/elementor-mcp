<?php
/**
 * Project Memory admin tab view.
 *
 * Displays pending proposals from AI agents for approval/rejection,
 * plus the approved guidelines active in site context.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle approve / reject actions.
if ( isset( $_POST['emcp_memory_action'], $_POST['memory_id'] ) && check_admin_referer( 'emcp_memory_manage' ) ) {
	if ( current_user_can( 'manage_options' ) ) {
		$mem_id = (int) $_POST['memory_id'];
		$action = sanitize_key( $_POST['emcp_memory_action'] );
		if ( 'approve' === $action ) {
			EMCP_Tools_Memory_Store::approve( $mem_id );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Memory proposal approved and active in site context.', 'emcp-tools' ) . '</p></div>';
		} elseif ( 'reject' === $action ) {
			EMCP_Tools_Memory_Store::reject( $mem_id );
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Memory proposal discarded.', 'emcp-tools' ) . '</p></div>';
		}
	}
}

$pending_items = EMCP_Tools_Memory_Store::query(
	array(
		'post_status'    => 'pending',
		'posts_per_page' => 50,
	)
);

$approved_items = EMCP_Tools_Memory_Store::query(
	array(
		'post_status'    => 'publish',
		'posts_per_page' => 50,
	)
);
?>

<div class="wrap elementor-mcp-memory">
	<h2>
		<?php esc_html_e( 'Project Memory', 'emcp-tools' ); ?>
		<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO UNLOCKED</span>
	</h2>
	<p class="description">
		<?php esc_html_e( 'AI agents learn from conversations and suggest project-specific rules via the remember tool. Approved rules are automatically injected into future AI sessions.', 'emcp-tools' ); ?>
	</p>

	<!-- Pending Proposals -->
	<div class="postbox" style="margin-top: 20px;">
		<div class="postbox-header">
			<h3 class="hndle"><?php esc_html_e( 'Pending Proposals Awaiting Approval', 'emcp-tools' ); ?> (<?php echo count( $pending_items ); ?>)</h3>
		</div>
		<div class="inside">
			<?php if ( empty( $pending_items ) ) : ?>
				<p><em><?php esc_html_e( 'No pending memory proposals from AI agents.', 'emcp-tools' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rule / Summary', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Guideline Content', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Severity', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'emcp-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pending_items as $item ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $item->post_title ); ?></strong></td>
								<td><?php echo esc_html( $item->post_content ); ?></td>
								<td><code><?php echo esc_html( get_post_meta( $item->ID, EMCP_Tools_Memory_Store::META_SEVERITY, true ) ?: 'info' ); ?></code></td>
								<td>
									<form method="post" style="display:inline-block;">
										<?php wp_nonce_field( 'emcp_memory_manage' ); ?>
										<input type="hidden" name="memory_id" value="<?php echo esc_attr( $item->ID ); ?>">
										<button type="submit" name="emcp_memory_action" value="approve" class="button button-primary button-small"><?php esc_html_e( 'Approve', 'emcp-tools' ); ?></button>
										<button type="submit" name="emcp_memory_action" value="reject" class="button button-secondary button-small" onclick="return confirm('Discard this proposal?');"><?php esc_html_e( 'Reject', 'emcp-tools' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<!-- Approved Guidelines -->
	<div class="postbox" style="margin-top: 20px;">
		<div class="postbox-header">
			<h3 class="hndle"><?php esc_html_e( 'Active Site Guidelines (Injected into Agent Context)', 'emcp-tools' ); ?> (<?php echo count( $approved_items ); ?>)</h3>
		</div>
		<div class="inside">
			<?php if ( empty( $approved_items ) ) : ?>
				<p><em><?php esc_html_e( 'No guidelines currently active.', 'emcp-tools' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rule / Title', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Guideline', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Severity', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'emcp-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $approved_items as $item ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $item->post_title ); ?></strong></td>
								<td><?php echo esc_html( $item->post_content ); ?></td>
								<td><code><?php echo esc_html( get_post_meta( $item->ID, EMCP_Tools_Memory_Store::META_SEVERITY, true ) ?: 'info' ); ?></code></td>
								<td>
									<form method="post" style="display:inline-block;">
										<?php wp_nonce_field( 'emcp_memory_manage' ); ?>
										<input type="hidden" name="memory_id" value="<?php echo esc_attr( $item->ID ); ?>">
										<button type="submit" name="emcp_memory_action" value="reject" class="button button-small button-link-delete" onclick="return confirm('Delete this guideline?');"><?php esc_html_e( 'Delete', 'emcp-tools' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
