<?php
/**
 * AI Chat Provider Interface.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface EMCP_Tools_AI_Chat_Provider {
	public function send_message( array $messages, array $tools = array(), array $options = array() );
}
