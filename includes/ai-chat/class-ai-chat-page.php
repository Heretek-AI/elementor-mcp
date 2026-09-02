<?php
/**
 * AI Chat Admin Page.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Page {
	public static function render(): void {
		require_once EMCP_TOOLS_DIR . 'includes/admin/views/page-ai-chat.php';
	}
}
