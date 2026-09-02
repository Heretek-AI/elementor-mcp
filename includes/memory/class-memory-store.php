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

	const TYPES = array( 'rule', 'fact', 'context', 'style', 'decision' );

	/** @var self|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function user_has_access(): bool {
		return current_user_can( 'manage_options' );
	}

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

	public function pending_count(): int {
		$counts = wp_count_posts( self::POST_TYPE );
		return isset( $counts->pending ) ? (int) $counts->pending : 0;
	}

	public function set_guidance_status( int $id, string $status ): bool {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		return (bool) wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => $status,
			)
		);
	}

	public function add_guidance( array $data ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => sanitize_key( $data['status'] ?? 'publish' ),
				'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
				'post_content' => wp_kses_post( $data['body'] ?? '' ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		if ( ! empty( $data['type'] ) ) {
			update_post_meta( $post_id, self::META_SEVERITY, sanitize_key( $data['type'] ) );
		}
		if ( ! empty( $data['source'] ) ) {
			update_post_meta( $post_id, '_emcp_source', sanitize_text_field( $data['source'] ) );
		}
		return $post_id;
	}

	public function update_guidance( int $id, array $data ): bool {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		$args = array( 'ID' => $id );
		if ( isset( $data['body'] ) ) {
			$args['post_content'] = wp_kses_post( $data['body'] );
		}
		if ( isset( $data['title'] ) ) {
			$args['post_title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['status'] ) ) {
			$args['post_status'] = sanitize_key( $data['status'] );
		}
		wp_update_post( $args );
		if ( isset( $data['type'] ) ) {
			update_post_meta( $id, self::META_SEVERITY, sanitize_key( $data['type'] ) );
		}
		return true;
	}

	public static function uninstall_cleanup(): void {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $pid ) {
			wp_delete_post( $pid, true );
		}
		delete_option( 'emcp_tools_memory_auto_summarize' );
		delete_option( 'emcp_tools_memory_require_approval' );
	}
}

// Auto-wire.
EMCP_Tools_Memory_Store::init();
