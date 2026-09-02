<?php
/**
 * AI Chat Usage Tracker.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Usage {
	public static function track( string $model, int $tokens ): void {
		$usage = (int) get_option( 'emcp_ai_chat_tokens_used', 0 );
		update_option( 'emcp_ai_chat_tokens_used', $usage + $tokens );
	}
}
