<?php
/**
 * AI Chat Elementor Editor integration.
 *
 * Enqueues the floating AI Chat widget inside the Elementor builder preview when enabled.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Elementor_Editor {

	public static function init(): void {
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue(): void {
		if ( ! class_exists( 'EMCP_Tools_AI_Chat_Module' ) || ! EMCP_Tools_AI_Chat_Module::is_enabled() ) {
			return;
		}

		wp_enqueue_style( 'emcp-ai-chat-css', plugins_url( 'assets/css/ai-chat.css', EMCP_TOOLS_BASENAME ), array(), EMCP_TOOLS_VERSION );
		wp_enqueue_script( 'emcp-ai-chat-js', plugins_url( 'assets/js/ai-chat.js', EMCP_TOOLS_BASENAME ), array( 'jquery' ), EMCP_TOOLS_VERSION, true );
		wp_localize_script(
			'emcp-ai-chat-js',
			'emcpAiChat',
			array(
				'endpoint' => rest_url( 'emcp/v1/chat' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'context'  => 'elementor',
			)
		);
	}
}

EMCP_Tools_Elementor_Editor::init();
