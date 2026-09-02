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

	const CATEGORY = 'emcp-custom';

	/**
	 * Guard against duplicate hook registration.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Whether custom blocks may load on this site (Pro gate).
	 *
	 * @return bool
	 */
	private function has_access(): bool {
		return function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
	}

	/**
	 * Registers Gutenberg hooks and block loader on init.
	 *
	 * @since 3.6.0
	 */
	public function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}
		self::$hooks_registered = true;

		// Register custom block category in Gutenberg.
		add_filter( 'block_categories_all', array( $this, 'register_category' ), 10, 2 );

		// Register blocks on init (priority 20).
		add_action( 'init', array( $this, 'load_blocks' ), 20 );
	}

	/**
	 * Backward compatibility static init.
	 */
	public static function init(): void {
		( new self() )->register_hooks();
	}

	/**
	 * Register the "Custom (EMCP)" block category in the Gutenberg block editor.
	 *
	 * @param array<int,array<string,mixed>> $categories Block categories.
	 * @param mixed                          $context    Editor context.
	 * @return array<int,array<string,mixed>>
	 */
	public function register_category( $categories, $context = null ): array {
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}

		if ( ! $this->has_access() ) {
			return $categories;
		}

		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && self::CATEGORY === $category['slug'] ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'Custom (EMCP)', 'emcp-tools' ),
			'icon'  => null,
		);

		return $categories;
	}

	/**
	 * Reads blocks-manifest.json and registers every active block with WordPress.
	 */
	public function load_blocks(): void {
		if ( ! $this->has_access() ) {
			return;
		}

		if ( ! class_exists( 'EMCP_Tools_Sandbox_Paths' ) ) {
			return;
		}

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
			if ( '' !== $dir && file_exists( $dir . '/block.json' ) && function_exists( 'register_block_type' ) ) {
				register_block_type( $dir );
			}
		}
	}
}
