<?php
/**
 * AI Chat Session Store.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Store {
	const OPTION_PREFIX = 'emcp_ai_session_';

	public static function get_session( string $session_id ): array {
		return (array) get_transient( self::OPTION_PREFIX . sanitize_key( $session_id ) ) ?: array();
	}

	public static function save_session( string $session_id, array $messages ): void {
		set_transient( self::OPTION_PREFIX . sanitize_key( $session_id ), $messages, DAY_IN_SECONDS * 7 );
	}

	public static function clear_session( string $session_id ): void {
		delete_transient( self::OPTION_PREFIX . sanitize_key( $session_id ) );
	}
}
