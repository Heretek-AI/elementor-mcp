<?php
/**
 * AI Chat REST API Controller.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Controller {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'emcp/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_chat' ),
				'permission_callback' => static function (): bool { return current_user_can( 'edit_posts' ); },
			)
		);
	}

	public static function handle_chat( WP_REST_Request $request ) {
		$messages = (array) $request->get_param( 'messages' );
		if ( empty( $messages ) ) {
			return new WP_Error( 'missing_messages', __( 'Messages array required.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$provider_key = EMCP_Tools_AI_Chat_Settings::get_active_provider();
		$api_key      = EMCP_Tools_AI_Chat_Settings::get_api_key();
		$model        = EMCP_Tools_AI_Chat_Settings::get_active_model();

		$providers = EMCP_Tools_AI_Providers::get_providers();
		if ( ! isset( $providers[ $provider_key ] ) ) {
			return new WP_Error( 'invalid_provider', __( 'Provider not configured.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$endpoint = $providers[ $provider_key ]['endpoint'];
		$payload  = array(
			'model'    => $model,
			'messages' => array_merge(
				array( array( 'role' => 'system', 'content' => EMCP_Tools_AI_Chat_Prompt::build() ) ),
				$messages
			),
		);

		$headers = array(
			'Content-Type' => 'application/json',
		);
		if ( 'anthropic' === $provider_key ) {
			$headers['x-api-key'] = $api_key;
			$headers['anthropic-version'] = '2023-06-01';
		} else {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 45,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return rest_ensure_response( $body );
	}
}

EMCP_Tools_AI_Chat_Controller::init();
