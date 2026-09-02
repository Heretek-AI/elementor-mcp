<?php
/**
 * Rank Math SEO integration (Pro) — rankmath-read / rankmath-write.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_RankMath_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string { return 'rankmath'; }
	public function label(): string { return 'Rank Math'; }
	public function is_active(): bool { return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ); }

	protected function operations(): array {
		$edit_posts = static function (): bool { return current_user_can( 'edit_posts' ); };
		return array(
			'get-post-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Get Rank Math SEO metadata.' ),
			'update-post-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Update Rank Math SEO metadata.' ),
			'get-term-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Get Rank Math term SEO metadata.' ),
			'update-term-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Update Rank Math term SEO metadata.' ),
		);
	}

	public function op_get_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'post_id required' ); }
		return array(
			'post_id'       => $id,
			'title'         => (string) get_post_meta( $id, 'rank_math_title', true ),
			'description'   => (string) get_post_meta( $id, 'rank_math_description', true ),
			'focus_keyword' => (string) get_post_meta( $id, 'rank_math_focus_keyword', true ),
			'canonical'     => (string) get_post_meta( $id, 'rank_math_canonical_url', true ),
			'robots'        => (array) get_post_meta( $id, 'rank_math_robots', true ),
		);
	}

	public function op_update_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'post_id required' ); }
		if ( isset( $args['title'] ) ) { update_post_meta( $id, 'rank_math_title', sanitize_text_field( $args['title'] ) ); }
		if ( isset( $args['description'] ) ) { update_post_meta( $id, 'rank_math_description', sanitize_textarea_field( $args['description'] ) ); }
		if ( isset( $args['focus_keyword'] ) ) { update_post_meta( $id, 'rank_math_focus_keyword', sanitize_text_field( $args['focus_keyword'] ) ); }
		if ( isset( $args['canonical'] ) ) { update_post_meta( $id, 'rank_math_canonical_url', esc_url_raw( $args['canonical'] ) ); }
		return array( 'success' => true, 'post_id' => $id );
	}

	public function op_get_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'term_id required' ); }
		return array(
			'term_id'     => $id,
			'title'       => (string) get_term_meta( $id, 'rank_math_title', true ),
			'description' => (string) get_term_meta( $id, 'rank_math_description', true ),
		);
	}

	public function op_update_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'term_id required' ); }
		if ( isset( $args['title'] ) ) { update_term_meta( $id, 'rank_math_title', sanitize_text_field( $args['title'] ) ); }
		if ( isset( $args['description'] ) ) { update_term_meta( $id, 'rank_math_description', sanitize_textarea_field( $args['description'] ) ); }
		return array( 'success' => true, 'term_id' => $id );
	}
}
