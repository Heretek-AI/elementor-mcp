<?php
/**
 * AI Chat Module — opt-in module for AI assistant in Elementor & Gutenberg.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Module extends EMCP_Tools_Module {

	public function id(): string {
		// Canonical id is 'ai-chat' (matches the admin tab + module_tab_visible
		// lookup). 'ai_chat' was used by the first seeded installs; is_enabled()
		// below still honours it so the module toggle keeps working across the
		// rename without flipping anyone's choice.
		return 'ai-chat';
	}

	public function title(): string {
		return __( 'AI Chat Assistant', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Floating AI Chat assistant inside Elementor and Gutenberg editors with MCP tool-calling superpowers.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'pro';
	}

	public function default_active(): bool {
		// On by default (matches the documented Pro default). The module is a
		// true kill switch now: disabling it stops the REST route and the editor
		// FAB. Admins who prefer privacy-by-default can turn it off on Modules.
		return true;
	}

	public function is_available(): bool {
		return true;
	}

	public static function is_enabled(): bool {
		$active = (array) get_option( self::OPTION_ACTIVE, array() );
		return in_array( 'ai-chat', $active, true ) || in_array( 'ai_chat', $active, true );
	}

	public function register(): void {
		// The REST route is gated on is_enabled() inside the controller's
		// rest_api_init hook; the editor bridges gate their own enqueues. A
		// disabled module therefore exposes no chat surface.
	}

	public function render_settings(): void {
		$provider = EMCP_Tools_AI_Chat_Settings::get_active_provider();
		$model    = EMCP_Tools_AI_Chat_Settings::get_active_model();
		?>
		<p class="description">
			<?php
			printf(
				esc_html__( 'Active Provider: %1$s | Model: %2$s. Configure credentials in the AI Chat tab.', 'emcp-tools' ),
				'<strong>' . esc_html( $provider ) . '</strong>',
				'<strong>' . esc_html( $model ) . '</strong>'
			);
			?>
		</p>
		<?php
	}
}
