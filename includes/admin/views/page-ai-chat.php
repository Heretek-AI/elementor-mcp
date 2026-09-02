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
		if ( isset( $_POST['custom_endpoint'] ) ) {
			EMCP_Tools_AI_Chat_Settings::set_custom_endpoint( sanitize_text_field( wp_unslash( $_POST['custom_endpoint'] ) ) );
		}
		if ( ! empty( $_POST['api_key'] ) ) {
			EMCP_Tools_AI_Chat_Settings::set_api_key( trim( wp_unslash( $_POST['api_key'] ) ) );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'AI Chat settings updated.', 'emcp-tools' ) . '</p></div>';
	}
}

$active_provider = EMCP_Tools_AI_Chat_Settings::get_active_provider();
$active_model    = EMCP_Tools_AI_Chat_Settings::get_active_model();
$custom_endpoint = EMCP_Tools_AI_Chat_Settings::get_custom_endpoint();
$providers       = EMCP_Tools_AI_Providers::get_providers();
$has_key         = '' !== EMCP_Tools_AI_Chat_Settings::get_api_key();
?>

<div class="wrap elementor-mcp-ai-chat">
	<h2>
		<?php esc_html_e( 'AI Chat Assistant Settings', 'emcp-tools' ); ?>
		<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO UNLOCKED</span>
	</h2>
	<p class="description">
		<?php esc_html_e( 'Connect your preferred LLM provider or local OpenAI-compatible endpoint to power the AI assistant inside the Elementor and Gutenberg page builders.', 'emcp-tools' ); ?>
	</p>

	<form method="post" action="" style="max-width: 650px; margin-top: 20px;">
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
			<tr id="row-custom-endpoint" style="<?php echo ( 'custom' === $active_provider ) ? '' : 'display: none;'; ?>">
				<th scope="row"><label for="custom_endpoint"><?php esc_html_e( 'Endpoint URL', 'emcp-tools' ); ?></label></th>
				<td>
					<input type="url" name="custom_endpoint" id="custom_endpoint" value="<?php echo esc_attr( $custom_endpoint ); ?>" placeholder="http://localhost:11434/v1/chat/completions" class="regular-text" style="width: 100%; max-width: 500px;">
					<p class="description">
						<?php esc_html_e( 'Enter any OpenAI-compatible chat completions endpoint (e.g. http://localhost:11434/v1/chat/completions for Ollama, http://localhost:1234/v1/chat/completions for LM Studio, https://api.deepseek.com/v1/chat/completions, or a custom self-hosted proxy). If a base URL is entered, /chat/completions is appended automatically.', 'emcp-tools' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="model"><?php esc_html_e( 'Model', 'emcp-tools' ); ?></label></th>
				<td>
					<input type="text" name="model" id="model" value="<?php echo esc_attr( $active_model ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'e.g. gpt-4o, claude-3-5-sonnet-20241022, deepseek-chat, llama3.3, or your custom local model name.', 'emcp-tools' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="api_key"><?php esc_html_e( 'API Key', 'emcp-tools' ); ?></label></th>
				<td>
					<input type="password" name="api_key" id="api_key" placeholder="<?php echo $has_key ? '••••••••••••••••' : ''; ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Encrypted with site secret key. Leave blank to keep existing key. Local models (Ollama, LM Studio, LocalAI) do not require an API key.', 'emcp-tools' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="submit" style="display: flex; gap: 10px; align-items: center;">
			<button type="submit" name="emcp_ai_save" class="button button-primary"><?php esc_html_e( 'Save Settings', 'emcp-tools' ); ?></button>
			<button type="button" id="btn-test-ai-connection" class="button button-secondary"><?php esc_html_e( 'Test Connection', 'emcp-tools' ); ?></button>
			<span id="ai-test-spinner" class="spinner" style="float: none; margin: 0;"></span>
		</p>
		<div id="ai-test-result" style="margin-top: 15px; display: none;"></div>
	</form>
</div>

<script>
(function() {
	var provider = document.getElementById('provider');
	var rowEndpoint = document.getElementById('row-custom-endpoint');
	var btnTest = document.getElementById('btn-test-ai-connection');
	var spinner = document.getElementById('ai-test-spinner');
	var resultBox = document.getElementById('ai-test-result');

	function toggleEndpoint() {
		if (provider.value === 'custom') {
			rowEndpoint.style.display = '';
		} else {
			rowEndpoint.style.display = 'none';
		}
	}

	provider.addEventListener('change', toggleEndpoint);

	btnTest.addEventListener('click', function(e) {
		e.preventDefault();
		btnTest.disabled = true;
		spinner.classList.add('is-active');
		resultBox.style.display = 'none';
		resultBox.className = '';

		var formData = new FormData();
		formData.append('action', 'emcp_tools_ai_test_connection');
		formData.append('nonce', document.getElementById('_wpnonce').value);
		formData.append('provider', provider.value);
		formData.append('model', document.getElementById('model').value.trim());
		formData.append('api_key', document.getElementById('api_key').value.trim());
		formData.append('custom_endpoint', document.getElementById('custom_endpoint').value.trim());

		fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData
		})
		.then(function(res) { return res.json(); })
		.then(function(data) {
			btnTest.disabled = false;
			spinner.classList.remove('is-active');
			resultBox.style.display = 'block';

			if (data.success) {
				resultBox.className = 'notice notice-success inline';
				var previewHtml = (data.data && data.data.preview) ? '<br><small><strong>Response preview:</strong> ' + escapeHtml(data.data.preview) + '</small>' : '';
				resultBox.innerHTML = '<p><strong>✓ ' + escapeHtml(data.data.message) + '</strong> (Latency: ' + escapeHtml(data.data.latency) + ')' + previewHtml + '</p>';
			} else {
				resultBox.className = 'notice notice-error inline';
				var errMsg = (data.data && data.data.message) ? data.data.message : 'Unknown error';
				resultBox.innerHTML = '<p><strong>✕ ' + escapeHtml(errMsg) + '</strong></p>';
			}
		})
		.catch(function(err) {
			btnTest.disabled = false;
			spinner.classList.remove('is-active');
			resultBox.style.display = 'block';
			resultBox.className = 'notice notice-error inline';
			resultBox.innerHTML = '<p><strong>✕ Request failed:</strong> ' + escapeHtml(err.message) + '</p>';
		});
	});

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.innerText = str;
		return div.innerHTML;
	}
})();
</script>
