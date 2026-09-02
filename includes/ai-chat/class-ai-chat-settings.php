<?php
/**
 * AI Chat Settings.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Settings {
	const OPTION_PROVIDER = 'emcp_ai_chat_provider';
	const OPTION_MODEL    = 'emcp_ai_chat_model';
	const OPTION_KEY      = 'emcp_ai_chat_key_enc';

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
}
