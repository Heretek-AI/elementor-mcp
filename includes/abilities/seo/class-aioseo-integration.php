<?php
/**
 * All in One SEO (AIOSEO) integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AIOSEO_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string { return 'aioseo'; }
	public function label(): string { return 'All in One SEO'; }
	public function is_active(): bool { return defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\Plugin\AIOSEO' ); }

	protected function operations(): array {
		$edit_posts = static function (): bool { return current_user_can( 'edit_posts' ); };
		return array(
			'get-post-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Get AIOSEO post metadata.' ),
			'update-post-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Update AIOSEO post metadata.' ),
			'get-term-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Get AIOSEO term metadata.' ),
			'update-term-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Update AIOSEO term metadata.' ),
		);
	}

	public function op_get_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'post_id required' ); }
		return array(
			'post_id'     => $id,
			'title'       => (string) get_post_meta( $id, '_aioseo_title', true ),
			'description' => (string) get_post_meta( $id, '_aioseo_description', true ),
			'keywords'    => (string) get_post_meta( $id, '_aioseo_keywords', true ),
			'canonical'   => (string) get_post_meta( $id, '_aioseo_canonical_url', true ),
		);
	}

	public function op_update_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'post_id required' ); }
		if ( isset( $args['title'] ) ) { update_post_meta( $id, '_aioseo_title', sanitize_text_field( $args['title'] ) ); }
		if ( isset( $args['description'] ) ) { update_post_meta( $id, '_aioseo_description', sanitize_textarea_field( $args['description'] ) ); }
		if ( isset( $args['keywords'] ) ) { update_post_meta( $id, '_aioseo_keywords', sanitize_text_field( $args['keywords'] ) ); }
		return array( 'success' => true, 'post_id' => $id );
	}

	public function op_get_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		return array( 'term_id' => $id, 'title' => (string) get_term_meta( $id, '_aioseo_title', true ) );
	}

	public function op_update_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		if ( isset( $args['title'] ) ) { update_term_meta( $id, '_aioseo_title', sanitize_text_field( $args['title'] ) ); }
		return array( 'success' => true, 'term_id' => $id );
	}
}
