<?php
/**
 * The SEO Framework integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_SEOFramework_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string { return 'seoframework'; }
	public function label(): string { return 'The SEO Framework'; }
	public function is_active(): bool { return defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ); }

	protected function operations(): array {
		$edit_posts = static function (): bool { return current_user_can( 'edit_posts' ); };
		return array(
			'get-post-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Get The SEO Framework metadata.' ),
			'update-post-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Update The SEO Framework metadata.' ),
			'get-term-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Get The SEO Framework term metadata.' ),
			'update-term-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Update The SEO Framework term metadata.' ),
		);
	}

	public function op_get_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		return array(
			'post_id'     => $id,
			'title'       => (string) get_post_meta( $id, '_genesis_title', true ),
			'description' => (string) get_post_meta( $id, '_genesis_description', true ),
			'canonical'   => (string) get_post_meta( $id, '_genesis_canonical_uri', true ),
			'noindex'     => (bool) get_post_meta( $id, '_genesis_noindex', true ),
		);
	}

	public function op_update_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( isset( $args['title'] ) ) { update_post_meta( $id, '_genesis_title', sanitize_text_field( $args['title'] ) ); }
		if ( isset( $args['description'] ) ) { update_post_meta( $id, '_genesis_description', sanitize_textarea_field( $args['description'] ) ); }
		return array( 'success' => true, 'post_id' => $id );
	}

	public function op_get_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		return array( 'term_id' => $id, 'title' => (string) get_term_meta( $id, 'doctitle', true ) );
	}

	public function op_update_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		if ( isset( $args['title'] ) ) { update_term_meta( $id, 'doctitle', sanitize_text_field( $args['title'] ) ); }
		return array( 'success' => true, 'term_id' => $id );
	}
}
