<?php
/**
 * Blocksy blocks catalog metadata.
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Blocksy_Blocks_Catalog {
	public static function get_blocks(): array {
		return array(
			'blocksy/dynamic-data'  => array( 'title' => 'Dynamic Data', 'category' => 'blocksy' ),
			'blocksy/search'        => array( 'title' => 'Search', 'category' => 'blocksy' ),
			'blocksy/socials'       => array( 'title' => 'Socials', 'category' => 'blocksy' ),
			'blocksy/breadcrumbs'   => array( 'title' => 'Breadcrumbs', 'category' => 'blocksy' ),
		);
	}
}
