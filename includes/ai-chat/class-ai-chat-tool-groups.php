<?php
/**
 * AI Chat Tool Groups.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Tool_Groups {
	public static function get_groups(): array {
		return array(
			'elementor' => array( 'label' => 'Elementor Tools', 'default' => true ),
			'content'   => array( 'label' => 'Content & Posts', 'default' => true ),
			'themer'    => array( 'label' => 'Theme Builder', 'default' => true ),
			'seo_a11y'  => array( 'label' => 'SEO & A11y', 'default' => true ),
			'sandbox'   => array( 'label' => 'Custom Widgets & Blocks', 'default' => false ),
		);
	}
}
