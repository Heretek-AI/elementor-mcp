<?php
/**
 * AI Chat Settings.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Settings {
	const OPTION_PROVIDER        = 'emcp_ai_chat_provider';
	const OPTION_MODEL           = 'emcp_ai_chat_model';
	const OPTION_KEY             = 'emcp_ai_chat_key_enc';
	const OPTION_CUSTOM_ENDPOINT = 'emcp_ai_chat_custom_endpoint';
	const DEFAULT_CUSTOM_ENDPOINT= 'http://localhost:11434/v1/chat/completions';

	public static function get_active_provider(): string {
		return (string) get_option( self::OPTION_PROVIDER, 'openai' );
	}

	public static function get_active_model(): string {
		return (string) get_option( self::OPTION_MODEL, 'gpt-4o' );
	}

	public static function get_api_key(): string {
		$enc = (string) get_option( self::OPTION_KEY, '' );
		return EMCP_Tools_Key_Crypto::decrypt( $enc );
	}

	public static function set_api_key( string $key ): void {
		update_option( self::OPTION_KEY, EMCP_Tools_Key_Crypto::encrypt( $key ) );
	}

	/**
	 * Get the custom OpenAI-compatible endpoint URL.
	 *
	 * @return string
	 */
	public static function get_custom_endpoint(): string {
		$ep = (string) get_option( self::OPTION_CUSTOM_ENDPOINT, self::DEFAULT_CUSTOM_ENDPOINT );
		return $ep ?: self::DEFAULT_CUSTOM_ENDPOINT;
	}

	/**
	 * Set the custom OpenAI-compatible endpoint URL.
	 *
	 * @param string $endpoint Endpoint URL.
	 */
	public static function set_custom_endpoint( string $endpoint ): void {
		update_option( self::OPTION_CUSTOM_ENDPOINT, self::normalize_endpoint( $endpoint ) );
	}

	/**
	 * Normalizes a base URL or endpoint to ensure it points to chat completions.
	 *
	 * @param string $url URL to normalize.
	 * @return string
	 */
	public static function normalize_endpoint( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$path = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );
		if ( ! preg_match( '#/chat/completions/?$#i', $path ) ) {
			$url = rtrim( $url, '/' ) . '/chat/completions';
		}

		return $url;
	}
}
