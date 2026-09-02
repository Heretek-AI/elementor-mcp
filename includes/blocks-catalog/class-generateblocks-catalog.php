<?php
/**
 * GenerateBlocks catalog metadata.
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_GenerateBlocks_Catalog {
	public static function get_blocks(): array {
		return array(
			'generateblocks/container' => array( 'title' => 'Container', 'category' => 'generateblocks' ),
			'generateblocks/grid'      => array( 'title' => 'Grid', 'category' => 'generateblocks' ),
			'generateblocks/headline'  => array( 'title' => 'Headline', 'category' => 'generateblocks' ),
			'generateblocks/button'    => array( 'title' => 'Button', 'category' => 'generateblocks' ),
			'generateblocks/image'     => array( 'title' => 'Image', 'category' => 'generateblocks' ),
			'generateblocks/query-loop'=> array( 'title' => 'Query Loop', 'category' => 'generateblocks' ),
		);
	}
}
