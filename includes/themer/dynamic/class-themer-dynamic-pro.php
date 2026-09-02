<?php
/**
 * Themer Dynamic Pro data sources.
 *
 * Adds 7 Pro bindable sources (custom-field, author-name, author-bio, author-url,
 * author-avatar, term-name, term-url) plus formatting filters to Elementor Dynamic Tags,
 * Gutenberg Block Bindings, and the Themer Dynamic compiler.
 *
 * @package EMCP_Tools
 * @since   3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Themer_Dynamic_Pro {

	/**
	 * Wire filters.
	 */
	public static function init(): void {
		add_filter( 'emcp_themer_dynamic_sources', array( __CLASS__, 'register_sources' ) );
		add_filter( 'emcp_themer_dynamic_value', array( __CLASS__, 'resolve_value' ), 10, 3 );
	}

	/**
	 * Register the 7 Pro dynamic sources.
	 *
	 * @param array $sources Existing sources.
	 * @return array
	 */
	public static function register_sources( array $sources ): array {
		$sources['custom-field'] = array(
			'label'    => __( 'Custom Field (ACF / Meta Box)', 'emcp-tools' ),
			'icon'     => 'admin-generic',
			'type'     => 'text',
			'args'     => array( 'key', 'fallback', 'format' ),
			'bindable' => true,
			'element'  => false,
		);

		$sources['author-name'] = array(
			'label'    => __( 'Author Name', 'emcp-tools' ),
			'icon'     => 'admin-users',
			'type'     => 'text',
			'args'     => array( 'format' ),
			'bindable' => true,
			'element'  => false,
		);

		$sources['author-bio'] = array(
			'label'    => __( 'Author Bio', 'emcp-tools' ),
			'icon'     => 'id',
			'type'     => 'text',
			'args'     => array( 'fallback' ),
			'bindable' => true,
			'element'  => false,
		);

		$sources['author-url'] = array(
			'label'    => __( 'Author Website URL', 'emcp-tools' ),
			'icon'     => 'admin-links',
			'type'     => 'url',
			'args'     => array( 'fallback' ),
			'bindable' => true,
			'element'  => false,
		);

		$sources['author-avatar'] = array(
			'label'    => __( 'Author Avatar URL', 'emcp-tools' ),
			'icon'     => 'format-image',
			'type'     => 'image',
			'args'     => array( 'size' ),
			'bindable' => true,
			'element'  => false,
		);

		$sources['term-name'] = array(
			'label'    => __( 'Term / Category Name', 'emcp-tools' ),
			'icon'     => 'category',
			'type'     => 'text',
			'args'     => array( 'taxonomy', 'fallback' ),
			'bindable' => true,
			'element'  => false,
		);

		$sources['term-url'] = array(
			'label'    => __( 'Term / Category URL', 'emcp-tools' ),
			'icon'     => 'admin-links',
			'type'     => 'url',
			'args'     => array( 'taxonomy', 'fallback' ),
			'bindable' => true,
			'element'  => false,
		);

		return $sources;
	}

	/**
	 * Resolve value for a Pro dynamic source.
	 *
	 * @param mixed  $value Current value (null if unresolved).
	 * @param string $key   Source key.
	 * @param array  $args  Arguments.
	 * @return array{type:string,value:mixed}|null
	 */
	public static function resolve_value( $value, string $key, array $args ) {
		if ( null !== $value ) {
			return $value;
		}

		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : (int) get_the_ID();
		$post    = $post_id ? get_post( $post_id ) : null;

		switch ( $key ) {
			case 'custom-field':
				$meta_key = (string) ( $args['key'] ?? '' );
				if ( '' === $meta_key ) {
					return null;
				}
				$val = '';
				if ( function_exists( 'get_field' ) ) {
					$acf_val = get_field( $meta_key, $post_id );
					if ( null !== $acf_val && false !== $acf_val ) {
						$val = is_array( $acf_val ) ? wp_json_encode( $acf_val ) : (string) $acf_val;
					}
				} elseif ( function_exists( 'rwmb_get_value' ) ) {
					$mb_val = rwmb_get_value( $meta_key, array(), $post_id );
					if ( null !== $mb_val && false !== $mb_val ) {
						$val = is_array( $mb_val ) ? wp_json_encode( $mb_val ) : (string) $mb_val;
					}
				}
				if ( '' === $val && $post_id ) {
					$val = (string) get_post_meta( $post_id, $meta_key, true );
				}
				if ( '' === $val && isset( $args['fallback'] ) ) {
					$val = (string) $args['fallback'];
				}
				return array( 'type' => 'text', 'value' => $val );

			case 'author-name':
				$author_id = $post ? (int) $post->post_author : (int) get_the_author_meta( 'ID' );
				$val = $author_id ? (string) get_the_author_meta( 'display_name', $author_id ) : '';
				return array( 'type' => 'text', 'value' => $val );

			case 'author-bio':
				$author_id = $post ? (int) $post->post_author : (int) get_the_author_meta( 'ID' );
				$val = $author_id ? (string) get_the_author_meta( 'description', $author_id ) : '';
				if ( '' === $val && isset( $args['fallback'] ) ) {
					$val = (string) $args['fallback'];
				}
				return array( 'type' => 'text', 'value' => $val );

			case 'author-url':
				$author_id = $post ? (int) $post->post_author : (int) get_the_author_meta( 'ID' );
				$val = $author_id ? (string) get_author_posts_url( $author_id ) : '';
				if ( '' === $val && isset( $args['fallback'] ) ) {
					$val = (string) $args['fallback'];
				}
				return array( 'type' => 'url', 'value' => $val );

			case 'author-avatar':
				$author_id = $post ? (int) $post->post_author : (int) get_the_author_meta( 'ID' );
				$size = isset( $args['size'] ) ? (int) $args['size'] : 96;
				$url = $author_id ? (string) get_avatar_url( $author_id, array( 'size' => $size ) ) : '';
				return array( 'type' => 'image', 'value' => $url );

			case 'term-name':
				$tax = (string) ( $args['taxonomy'] ?? 'category' );
				$terms = $post_id ? get_the_terms( $post_id, $tax ) : null;
				$val = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? (string) $terms[0]->name : '';
				if ( '' === $val && isset( $args['fallback'] ) ) {
					$val = (string) $args['fallback'];
				}
				return array( 'type' => 'text', 'value' => $val );

			case 'term-url':
				$tax = (string) ( $args['taxonomy'] ?? 'category' );
				$terms = $post_id ? get_the_terms( $post_id, $tax ) : null;
				$val = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? (string) get_term_link( $terms[0] ) : '';
				if ( '' === $val && isset( $args['fallback'] ) ) {
					$val = (string) $args['fallback'];
				}
				return array( 'type' => 'url', 'value' => $val );
		}

		return null;
	}
}

// Auto-wire on load.
EMCP_Tools_Themer_Dynamic_Pro::init();
