<?php
/**
 * Blocksy blocks catalog metadata.
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Blocksy_Blocks_Catalog {
	const PLUGIN_FILE = 'blocksy-companion/blocksy-companion.php';

	public static function is_active(): bool {
		return defined( 'BLOCKSY_VERSION' )
			|| class_exists( 'Blocksy' )
			|| ( function_exists( 'is_plugin_active' ) && is_plugin_active( self::PLUGIN_FILE ) );
	}

	public static function get_blocks(): array {
		return array(
			'blocksy/dynamic-data'  => array( 'title' => 'Dynamic Data', 'category' => 'blocksy' ),
			'blocksy/search'        => array( 'title' => 'Search', 'category' => 'blocksy' ),
			'blocksy/socials'       => array( 'title' => 'Socials', 'category' => 'blocksy' ),
			'blocksy/breadcrumbs'   => array( 'title' => 'Breadcrumbs', 'category' => 'blocksy' ),
		);
	}
}
