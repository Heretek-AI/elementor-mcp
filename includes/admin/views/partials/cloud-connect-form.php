<?php
/**
 * Shared "Connect to EMCP Cloud" form with the disclosed, default-on gateway consent.
 *
 * Expects (optional): $emcp_connect_label (string) — the submit button label.
 *
 * @package EMCP_Tools
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$emcp_connect_label = ( isset( $emcp_connect_label ) && '' !== $emcp_connect_label )
	? $emcp_connect_label
	: __( 'Connect to EMCP Cloud', 'emcp-tools' );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="emcp-cloud-connect-form">
	<input type="hidden" name="action" value="<?php echo esc_attr( EMCP_Tools_Cloud_Connect::ACTION_CONNECT ); ?>" />
	<?php wp_nonce_field( EMCP_Tools_Cloud_Connect::ACTION_CONNECT ); ?>
	<label class="emcp-gateway-optin">
		<input type="checkbox" name="emcp_gateway_optin" value="1" checked="checked" />
		<?php esc_html_e( 'Also let me manage this site through the EMCP gateway (recommended)', 'emcp-tools' ); ?>
		<span class="description">
			<?php esc_html_e( 'Authorizes the EMCP gateway to run MCP tools on this site on your behalf, so you can manage all your sites from a single AI connection. It never gets your password — it uses a revocable token, and only the tools you have enabled. Revoke anytime from Users → Authorized Apps or your EMCP Cloud dashboard.', 'emcp-tools' ); ?>
		</span>
	</label>
	<p><button type="submit" class="button button-primary"><?php echo esc_html( $emcp_connect_label ); ?></button></p>
</form>
