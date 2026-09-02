<?php
/**
 * Block Loader — automatically registers active sandbox Gutenberg blocks with WordPress.
 *
 * Reads blocks-manifest.json and registers them via register_block_type().
 *
 * @package EMCP_Tools
 * @since   3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Block_Loader {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'load_blocks' ), 20 );
	}

	public static function load_blocks(): void {
		$manifest_file = EMCP_Tools_Sandbox_Paths::base_dir() . '/blocks-manifest.json';
		if ( ! file_exists( $manifest_file ) || ! is_readable( $manifest_file ) ) {
			return;
		}

		$data = json_decode( (string) file_get_contents( $manifest_file ), true );
		if ( ! is_array( $data ) ) {
			return;
		}

		foreach ( $data as $entry ) {
			$dir = (string) ( $entry['dir'] ?? '' );
			if ( '' !== $dir && file_exists( $dir . '/block.json' ) ) {
				register_block_type( $dir );
			}
		}
	}
}

EMCP_Tools_Block_Loader::init();
