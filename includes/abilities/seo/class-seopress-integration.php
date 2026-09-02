<?php
/**
 * SEOPress integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_SeoPress_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string { return 'seopress'; }
	public function label(): string { return 'SEOPress'; }
	public function is_active(): bool { return defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' ); }

	protected function operations(): array {
		$edit_posts = static function (): bool { return current_user_can( 'edit_posts' ); };
		return array(
			'get-post-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Get SEOPress post metadata.' ),
			'update-post-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Update SEOPress post metadata.' ),
			'get-term-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Get SEOPress term metadata.' ),
			'update-term-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Update SEOPress term metadata.' ),
		);
	}

	public function op_get_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		return array(
			'post_id'        => $id,
			'title'          => (string) get_post_meta( $id, '_seopress_titles_title', true ),
			'description'    => (string) get_post_meta( $id, '_seopress_titles_desc', true ),
			'target_keyword' => (string) get_post_meta( $id, '_seopress_analysis_target_kw', true ),
			'canonical'      => (string) get_post_meta( $id, '_seopress_robots_canonical', true ),
		);
	}

	public function op_update_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( isset( $args['title'] ) ) { update_post_meta( $id, '_seopress_titles_title', sanitize_text_field( $args['title'] ) ); }
		if ( isset( $args['description'] ) ) { update_post_meta( $id, '_seopress_titles_desc', sanitize_textarea_field( $args['description'] ) ); }
		if ( isset( $args['target_keyword'] ) ) { update_post_meta( $id, '_seopress_analysis_target_kw', sanitize_text_field( $args['target_keyword'] ) ); }
		return array( 'success' => true, 'post_id' => $id );
	}

	public function op_get_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		return array( 'term_id' => $id, 'title' => (string) get_term_meta( $id, '_seopress_titles_title', true ) );
	}

	public function op_update_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		if ( isset( $args['title'] ) ) { update_term_meta( $id, '_seopress_titles_title', sanitize_text_field( $args['title'] ) ); }
		return array( 'success' => true, 'term_id' => $id );
	}
}
