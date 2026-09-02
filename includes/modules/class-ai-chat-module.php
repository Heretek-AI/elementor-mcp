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
		return 'ai_chat';
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
		// Opt-in per user configuration.
		return false;
	}

	public function is_available(): bool {
		return true;
	}

	public static function is_enabled(): bool {
		$active = (array) get_option( self::OPTION_ACTIVE, array() );
		return in_array( 'ai_chat', $active, true );
	}

	public function register(): void {
		// Frontend editor scripts are auto-wired when active.
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
