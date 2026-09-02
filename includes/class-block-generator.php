<?php
/**
 * Block Generator — compiles structured block JSON specs into valid WordPress block packages.
 *
 * Emits block.json (metadata, attributes, supports) and render.php (SSR output buffering).
 *
 * @package EMCP_Tools
 * @since   3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Block_Generator {

	/**
	 * Supported attribute types.
	 */
	const TYPES = array( 'string', 'boolean', 'number', 'integer', 'array', 'object' );

	/**
	 * Validate a block spec.
	 *
	 * @param array $spec Block specification.
	 * @return true|WP_Error
	 */
	public static function validate( array $spec ) {
		if ( empty( $spec['name'] ) || ! is_string( $spec['name'] ) ) {
			return new WP_Error( 'invalid_spec', __( 'Block spec must have a string "name" (e.g. "my-card").', 'emcp-tools' ) );
		}
		if ( empty( $spec['title'] ) || ! is_string( $spec['title'] ) ) {
			return new WP_Error( 'invalid_spec', __( 'Block spec must have a string "title".', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Generate files for the block package.
	 *
	 * @param array  $spec       Block spec.
	 * @param string $block_slug Full block slug (e.g. "emcp-sandbox/my-card").
	 * @return array{block_json:string,render_php:string,editor_js:string}
	 */
	public static function generate( array $spec, string $block_slug ): array {
		$attrs = (array) ( $spec['attributes'] ?? array() );
		$normalized_attrs = array();

		foreach ( $attrs as $k => $attr ) {
			$type = is_array( $attr ) && isset( $attr['type'] ) && in_array( $attr['type'], self::TYPES, true ) ? $attr['type'] : 'string';
			$def  = is_array( $attr ) && isset( $attr['default'] ) ? $attr['default'] : '';
			$normalized_attrs[ $k ] = array(
				'type'    => $type,
				'default' => $def,
			);
		}

		$block_meta = array(
			'$schema'     => 'https://schemas.wp.org/trunk/block.json',
			'apiVersion'  => 3,
			'name'        => $block_slug,
			'version'     => '1.0.0',
			'title'       => $spec['title'],
			'category'    => $spec['category'] ?? 'widgets',
			'icon'        => $spec['icon'] ?? 'block-default',
			'description' => $spec['description'] ?? '',
			'attributes'  => $normalized_attrs,
			'supports'    => array(
				'html'    => false,
				'align'   => true,
				'color'   => true,
				'spacing' => true,
			),
			'render'      => 'file:./render.php',
		);

		$template = (string) ( $spec['template'] ?? '<div class="emcp-custom-block"><h3>{{title}}</h3><p>{{content}}</p></div>' );
		$render   = EMCP_Tools_Sandbox_Template_Compiler::build_php_render( $template, $normalized_attrs );

		return array(
			'block_json' => wp_json_encode( $block_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'render_php' => $render,
			'editor_js'  => '',
		);
	}
}
