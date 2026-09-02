<?php
/**
 * Agent Project Memory Store.
 *
 * Registers the custom post type `emcp_memory` where post_status acts as the human-approval gate:
 *   - 'pending': agent-proposed guidance awaiting administrator review
 *   - 'publish': approved guidance injected into agent discovery context
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Memory_Store {

	const POST_TYPE = 'emcp_memory';
	const META_SEVERITY = '_emcp_severity';
	const META_TARGET   = '_emcp_target';
	const META_SESSION  = '_emcp_session';

	/**
	 * Init post type.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
	}

	/**
	 * Register emcp_memory CPT.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Project Memory', 'emcp-tools' ),
				'public'              => false,
				'show_ui'             => false,
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Add a proposed memory entry (status=pending).
	 *
	 * @param string $title       Short summary / rule title.
	 * @param string $content     Detailed guidance.
	 * @param string $severity    'info'|'warning'|'block'.
	 * @param string $target      Target component or domain.
	 * @param string $session_id  Optional session identifier.
	 * @return int|WP_Error Post ID on success.
	 */
	public static function add_proposal( string $title, string $content, string $severity = 'info', string $target = '', string $session_id = '' ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'pending',
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => wp_kses_post( $content ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_SEVERITY, sanitize_key( $severity ?: 'info' ) );
		update_post_meta( $post_id, self::META_TARGET, sanitize_text_field( $target ) );
		if ( '' !== $session_id ) {
			update_post_meta( $post_id, self::META_SESSION, sanitize_text_field( $session_id ) );
		}

		return $post_id;
	}

	/**
	 * Approve a memory proposal (sets status=publish).
	 *
	 * @param int $post_id Memory post ID.
	 * @return bool
	 */
	public static function approve( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		return (bool) wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Reject / delete a memory proposal.
	 *
	 * @param int $post_id Memory post ID.
	 * @return bool
	 */
	public static function reject( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		return (bool) wp_delete_post( $post_id, true );
	}

	/**
	 * Query memory entries.
	 *
	 * @param array $args WP_Query args.
	 * @return WP_Post[]
	 */
	public static function query( array $args = array() ): array {
		$defaults = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$query = new WP_Query( wp_parse_args( $args, $defaults ) );
		return $query->posts;
	}
}

// Auto-wire.
EMCP_Tools_Memory_Store::init();
