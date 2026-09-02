<?php
/**
 * Yoast SEO integration (Pro) — yoast-read / yoast-write over Yoast post/term meta.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Yoast_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string { return 'yoast'; }
	public function label(): string { return 'Yoast SEO'; }
	public function is_active(): bool { return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Frontend' ); }

	protected function operations(): array {
		$edit_posts = static function (): bool { return current_user_can( 'edit_posts' ); };

		return array(
			'get-post-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get Yoast SEO metadata for a post by { post_id } (title, desc, focuskw, canonical, noindex).',
			),
			'update-post-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update Yoast SEO metadata for a post by { post_id, title?, description?, focus_keyword?, canonical?, noindex? }.',
			),
			'get-term-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get Yoast SEO metadata for a taxonomy term by { term_id, taxonomy? }.',
			),
			'update-term-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update Yoast SEO metadata for a taxonomy term by { term_id, taxonomy?, title?, description? }.',
			),
		);
	}

	public function op_get_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'post_id required' ); }
		return array(
			'post_id'        => $id,
			'title'          => (string) get_post_meta( $id, '_yoast_wpseo_title', true ),
			'description'    => (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ),
			'focus_keyword'  => (string) get_post_meta( $id, '_yoast_wpseo_focuskw', true ),
			'canonical'      => (string) get_post_meta( $id, '_yoast_wpseo_canonical', true ),
			'noindex'        => '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true ),
			'opengraph_desc' => (string) get_post_meta( $id, '_yoast_wpseo_opengraph-description', true ),
		);
	}

	public function op_update_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'post_id required' ); }
		if ( isset( $args['title'] ) ) { update_post_meta( $id, '_yoast_wpseo_title', sanitize_text_field( $args['title'] ) ); }
		if ( isset( $args['description'] ) ) { update_post_meta( $id, '_yoast_wpseo_metadesc', sanitize_textarea_field( $args['description'] ) ); }
		if ( isset( $args['focus_keyword'] ) ) { update_post_meta( $id, '_yoast_wpseo_focuskw', sanitize_text_field( $args['focus_keyword'] ) ); }
		if ( isset( $args['canonical'] ) ) { update_post_meta( $id, '_yoast_wpseo_canonical', esc_url_raw( $args['canonical'] ) ); }
		if ( isset( $args['noindex'] ) ) { update_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', $args['noindex'] ? '1' : '2' ); }
		return array( 'success' => true, 'post_id' => $id );
	}

	public function op_get_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'term_id required' ); }
		$meta = get_option( 'wpseo_taxonomy_meta', array() );
		$tax  = (string) ( $args['taxonomy'] ?? 'category' );
		$data = $meta[ $tax ][ $id ] ?? array();
		return array(
			'term_id'     => $id,
			'title'       => $data['wpseo_title'] ?? '',
			'description' => $data['wpseo_desc'] ?? '',
		);
	}

	public function op_update_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'term_id required' ); }
		$tax  = (string) ( $args['taxonomy'] ?? 'category' );
		$meta = get_option( 'wpseo_taxonomy_meta', array() );
		if ( ! isset( $meta[ $tax ] ) ) { $meta[ $tax ] = array(); }
		if ( ! isset( $meta[ $tax ][ $id ] ) ) { $meta[ $tax ][ $id ] = array(); }
		if ( isset( $args['title'] ) ) { $meta[ $tax ][ $id ]['wpseo_title'] = sanitize_text_field( $args['title'] ); }
		if ( isset( $args['description'] ) ) { $meta[ $tax ][ $id ]['wpseo_desc'] = sanitize_textarea_field( $args['description'] ); }
		update_option( 'wpseo_taxonomy_meta', $meta );
		return array( 'success' => true, 'term_id' => $id );
	}
}
