<?php
/**
 * AI Chat System Prompt builder.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Prompt {
	public static function build(): string {
		$context = class_exists( 'EMCP_Tools_Site_Context' ) ? EMCP_Tools_Site_Context::environment_summary() : '';
		return "You are the AI Assistant inside the WordPress / Elementor editor powered by EMCP Tools Pro Unlocked.\n" .
			"You help the user build pages, optimize SEO and accessibility, manage themes, and author custom blocks.\n\n" .
			$context;
	}
}
