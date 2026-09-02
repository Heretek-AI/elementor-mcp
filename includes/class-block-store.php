<?php
/**
 * Block Store — source of truth and sandbox manager for custom Gutenberg blocks.
 *
 * @package EMCP_Tools
 * @since   3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Block_Store extends EMCP_Tools_Sandbox_Store {

	const POST_TYPE        = 'emcp_block';
	const META_SPEC        = '_emcp_block_spec';
	const META_BLOCK_NAME  = '_emcp_block_name';
	const META_CLASS_NAME  = '_emcp_class_name';
	const META_LAST_ERROR  = '_emcp_last_error';

	/** @var self|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function kind(): string {
		return 'block';
	}

	protected function sandbox_subdir(): string {
		return 'blocks';
	}

	protected function manifest_filename(): string {
		return 'blocks-manifest.json';
	}

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
	}

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'              => false,
				'show_ui'             => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
			)
		);
	}

	public function create( array $spec, string $status = 'publish' ) {
		$val = EMCP_Tools_Block_Generator::validate( $spec );
		if ( is_wp_error( $val ) ) {
			return $val;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => $status,
				'post_title'  => sanitize_text_field( $spec['title'] ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_SPEC, $spec );
		update_post_meta( $post_id, self::META_BLOCK_NAME, sanitize_key( $spec['name'] ) );

		$compile = $this->compile( $post_id, $spec );
		if ( is_wp_error( $compile ) ) {
			return $compile;
		}

		$this->rebuild_manifest();
		return $post_id;
	}

	public function update( int $post_id, array $spec ) {
		$val = EMCP_Tools_Block_Generator::validate( $spec );
		if ( is_wp_error( $val ) ) {
			return $val;
		}

		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Block not found.', 'emcp-tools' ) );
		}

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => sanitize_text_field( $spec['title'] ),
			)
		);

		update_post_meta( $post_id, self::META_SPEC, $spec );
		update_post_meta( $post_id, self::META_BLOCK_NAME, sanitize_key( $spec['name'] ) );

		$compile = $this->compile( $post_id, $spec );
		if ( is_wp_error( $compile ) ) {
			return $compile;
		}

		$this->bump_version( $post_id );
		$this->rebuild_manifest();
		return true;
	}

	public function compile( int $post_id, array $spec ) {
		$this->ensure_sandbox();
		$dir = $this->artifact_dir( $post_id );
		wp_mkdir_p( $dir );

		$block_slug = 'emcp-sandbox/' . sanitize_key( $spec['name'] );
		$files = EMCP_Tools_Block_Generator::generate( $spec, $block_slug );

		$this->write_file( $dir . '/block.json', $files['block_json'] );
		$this->write_file( $dir . '/render.php', $files['render_php'] );

		return true;
	}

	public function get( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Block not found.', 'emcp-tools' ) );
		}

		return array(
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'status'     => $post->post_status,
			'name'       => get_post_meta( $post->ID, self::META_BLOCK_NAME, true ),
			'spec'       => get_post_meta( $post->ID, self::META_SPEC, true ),
			'sync'       => $this->sync_meta( $post->ID ),
		);
	}

	public function list( array $args = array() ): array {
		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 100,
		);
		$posts = get_posts( $query_args );
		$list  = array();
		foreach ( $posts as $p ) {
			$item = $this->get( $p->ID );
			if ( ! is_wp_error( $item ) ) {
				$list[] = $item;
			}
		}
		return $list;
	}

	public function set_status( int $post_id, string $status ): bool {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			)
		);

		$this->rebuild_manifest();
		return true;
	}

	public function delete( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$this->rmdir_recursive( $this->artifact_dir( $post_id ) );
		wp_delete_post( $post_id, true );
		$this->rebuild_manifest();
		return true;
	}

	public function rebuild_manifest(): void {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
			)
		);

		$manifest = array();
		foreach ( $posts as $p ) {
			$manifest[] = array(
				'id'   => $p->ID,
				'name' => get_post_meta( $p->ID, self::META_BLOCK_NAME, true ),
				'dir'  => $this->artifact_dir( $p->ID ),
			);
		}

		$this->write_file( $this->manifest_path(), wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
	}

	public function checksum( int $id ): string {
		$spec = (array) get_post_meta( $id, self::META_SPEC, true );
		return hash( 'sha256', wp_json_encode( $spec ) );
	}

	public function to_bundle( int $id ) {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Block not found.' );
		}
		$spec = (array) get_post_meta( $id, self::META_SPEC, true );
		$sm   = $this->sync_meta( $id );
		return array(
			'kind'       => 'block',
			'uuid'       => $sm['uuid'],
			'meta'       => array(
				'title'       => $post->post_title,
				'description' => $post->post_excerpt,
				'author'      => (string) ( wp_get_current_user()->user_login ?? '' ),
				'license'     => 'GPL-2.0-or-later',
			),
			'spec'       => $spec,
			'version'    => max( 1, (int) ( $sm['version'] ?? 1 ) ),
			'updated_at' => $sm['updated_at'] ?? gmdate( 'c' ),
		);
	}

	public function apply_bundle( array $bundle ) {
		$spec = (array) ( $bundle['spec'] ?? array() );
		$name = (string) ( $bundle['meta']['title'] ?? 'Imported Block' );
		return $this->create_block( $name, $spec );
	}
}

EMCP_Tools_Block_Store::init();
