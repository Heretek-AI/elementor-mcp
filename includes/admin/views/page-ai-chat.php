<?php
/**
 * AI Chat Settings admin view.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( isset( $_POST['emcp_ai_save'] ) && check_admin_referer( 'emcp_ai_chat_settings' ) ) {
	if ( current_user_can( 'manage_options' ) ) {
		if ( isset( $_POST['provider'] ) ) {
			update_option( EMCP_Tools_AI_Chat_Settings::OPTION_PROVIDER, sanitize_key( $_POST['provider'] ) );
		}
		if ( isset( $_POST['model'] ) ) {
			update_option( EMCP_Tools_AI_Chat_Settings::OPTION_MODEL, sanitize_text_field( $_POST['model'] ) );
		}
		if ( ! empty( $_POST['api_key'] ) ) {
			EMCP_Tools_AI_Chat_Settings::set_api_key( trim( wp_unslash( $_POST['api_key'] ) ) );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'AI Chat settings updated.', 'emcp-tools' ) . '</p></div>';
	}
}

$active_provider = EMCP_Tools_AI_Chat_Settings::get_active_provider();
$active_model    = EMCP_Tools_AI_Chat_Settings::get_active_model();
$providers       = EMCP_Tools_AI_Providers::get_providers();
$has_key         = '' !== EMCP_Tools_AI_Chat_Settings::get_api_key();
?>

<div class="wrap elementor-mcp-ai-chat">
	<h2>
		<?php esc_html_e( 'AI Chat Assistant Settings', 'emcp-tools' ); ?>
		<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO UNLOCKED</span>
	</h2>
	<p class="description">
		<?php esc_html_e( 'Connect your preferred LLM provider to power the AI assistant inside the Elementor and Gutenberg page builders.', 'emcp-tools' ); ?>
	</p>

	<form method="post" action="" style="max-width: 600px; margin-top: 20px;">
		<?php wp_nonce_field( 'emcp_ai_chat_settings' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="provider"><?php esc_html_e( 'Provider', 'emcp-tools' ); ?></label></th>
				<td>
					<select name="provider" id="provider" class="regular-text">
						<?php foreach ( $providers as $pk => $p ) : ?>
							<option value="<?php echo esc_attr( $pk ); ?>" <?php selected( $active_provider, $pk ); ?>>
								<?php echo esc_html( $p['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="model"><?php esc_html_e( 'Model', 'emcp-tools' ); ?></label></th>
				<td>
					<input type="text" name="model" id="model" value="<?php echo esc_attr( $active_model ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'e.g. gpt-4o, claude-3-5-sonnet-20241022, or local ollama tag.', 'emcp-tools' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="api_key"><?php esc_html_e( 'API Key', 'emcp-tools' ); ?></label></th>
				<td>
					<input type="password" name="api_key" id="api_key" placeholder="<?php echo $has_key ? '••••••••••••••••' : ''; ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Encrypted with site secret key. Leave blank to keep existing key. Local models do not require an API key.', 'emcp-tools' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="submit" name="emcp_ai_save" class="button button-primary"><?php esc_html_e( 'Save Settings', 'emcp-tools' ); ?></button>
		</p>
	</form>
</div>
