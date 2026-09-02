<?php
/**
 * SureRank SEO integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_SureRank_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string { return 'surerank'; }
	public function label(): string { return 'SureRank'; }
	public function is_active(): bool { return defined( 'SURERANK_VERSION' ) || class_exists( 'SureRank\Plugin' ); }

	protected function operations(): array {
		$edit_posts = static function (): bool { return current_user_can( 'edit_posts' ); };
		return array(
			'get-post-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Get SureRank post metadata.' ),
			'update-post-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_post_seo' ), 'perm' => $edit_posts, 'desc' => 'Update SureRank post metadata.' ),
			'get-term-seo'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Get SureRank term metadata.' ),
			'update-term-seo' => array( 'mode' => 'write', 'run' => array( $this, 'op_update_term_seo' ), 'perm' => $edit_posts, 'desc' => 'Update SureRank term metadata.' ),
		);
	}

	public function op_get_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		return array(
			'post_id'     => $id,
			'title'       => (string) get_post_meta( $id, '_surerank_title', true ),
			'description' => (string) get_post_meta( $id, '_surerank_description', true ),
		);
	}

	public function op_update_post_seo( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( isset( $args['title'] ) ) { update_post_meta( $id, '_surerank_title', sanitize_text_field( $args['title'] ) ); }
		if ( isset( $args['description'] ) ) { update_post_meta( $id, '_surerank_description', sanitize_textarea_field( $args['description'] ) ); }
		return array( 'success' => true, 'post_id' => $id );
	}

	public function op_get_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		return array( 'term_id' => $id, 'title' => (string) get_term_meta( $id, '_surerank_title', true ) );
	}

	public function op_update_term_seo( array $args ) {
		$id = (int) ( $args['term_id'] ?? 0 );
		if ( isset( $args['title'] ) ) { update_term_meta( $id, '_surerank_title', sanitize_text_field( $args['title'] ) ); }
		return array( 'success' => true, 'term_id' => $id );
	}
}
