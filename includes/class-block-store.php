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

	/**
	 * Whether the current user/site may manage generated blocks. Mirrors the
	 * widget store's gate: the Pro license plus `manage_options`.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public static function user_has_access(): bool {
		if ( ! function_exists( 'emcp_tools_fs' ) || ! emcp_tools_fs()->can_use_premium_code() ) {
			return false;
		}
		return current_user_can( 'manage_options' );
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
		if ( ! class_exists( 'EMCP_Tools_Block_Generator' ) ) {
			return new WP_Error( 'generator_unavailable', __( 'The block generator is not loaded.', 'emcp-tools' ) );
		}

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
		if ( ! class_exists( 'EMCP_Tools_Block_Generator' ) ) {
			return new WP_Error( 'generator_unavailable', __( 'The block generator is not loaded.', 'emcp-tools' ) );
		}

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
		if ( ! class_exists( 'EMCP_Tools_Block_Generator' ) ) {
			return new WP_Error( 'generator_unavailable', __( 'The block generator is not loaded.', 'emcp-tools' ) );
		}

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

	/**
	 * One admin-table row for a block. Mirrors the widget store's summary, but
	 * keyed for the Blocks screen (block_id / block_name, `active`/`draft`).
	 *
	 * @since 3.7.0
	 *
	 * @param int $post_id Block post ID.
	 * @return array|WP_Error
	 */
	public function summary( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Block not found.', 'emcp-tools' ) );
		}

		return array(
			'block_id'   => (int) $post_id,
			'title'      => (string) $post->post_title,
			'block_name' => (string) get_post_meta( $post_id, self::META_BLOCK_NAME, true ),
			'class_name' => (string) get_post_meta( $post_id, self::META_CLASS_NAME, true ),
			'status'     => ( 'publish' === $post->post_status ) ? 'active' : 'draft',
			'last_error' => (string) get_post_meta( $post_id, self::META_LAST_ERROR, true ),
			'updated'    => (string) $post->post_modified,
		);
	}

	/**
	 * Lists generated blocks. The overview card counts active vs draft rows.
	 *
	 * @since 3.7.0
	 *
	 * @param string $status Optional 'active' | 'draft' | 'any' (default 'any').
	 * @return array<int, array>
	 */
	public function list_blocks( string $status = 'any' ): array {
		$post_status = 'any';
		if ( 'active' === $status ) {
			$post_status = 'publish';
		} elseif ( 'draft' === $status ) {
			$post_status = 'draft';
		}

		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => $post_status,
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$out = array();
		foreach ( $query->posts as $post ) {
			$summary = $this->summary( (int) $post->ID );
			if ( ! is_wp_error( $summary ) ) {
				$out[] = $summary;
			}
		}
		return $out;
	}

	/**
	 * One page of blocks, plus the totals needed to draw a pager. The admin
	 * table renders each row's block.json / render.php, so it pages rather than
	 * pulling the whole list.
	 *
	 * @since 3.7.0
	 *
	 * @param string $status   'active', 'draft', or 'any'.
	 * @param int    $page     1-based page number.
	 * @param int    $per_page Rows per page.
	 * @return array{items:array[],total:int,page:int,pages:int,per_page:int}
	 */
	public function list_blocks_page( string $status = 'any', int $page = 1, int $per_page = EMCP_Tools_Sandbox_List_Query::PER_PAGE ): array {
		$result = EMCP_Tools_Sandbox_List_Query::page( self::POST_TYPE, $status, $page, $per_page );

		$items = array();
		foreach ( $result['ids'] as $emcp_id ) {
			$summary = $this->summary( (int) $emcp_id );
			if ( ! is_wp_error( $summary ) ) {
				$items[] = $summary;
			}
		}

		return array(
			'items'    => $items,
			'total'    => $result['total'],
			'page'     => $result['page'],
			'pages'    => $result['pages'],
			'per_page' => $result['per_page'],
		);
	}

	/**
	 * Returns a compiled block asset's contents (block.json or render.php) for
	 * the code viewer. Unknown filenames return ''.
	 *
	 * @since 3.7.0
	 *
	 * @param int    $post_id Block post ID.
	 * @param string $file    Asset filename.
	 * @return string
	 */
	public function get_asset( int $post_id, string $file ): string {
		if ( ! in_array( $file, array( 'block.json', 'render.php' ), true ) ) {
			return '';
		}
		return $this->read_file( $this->artifact_dir( $post_id ) . '/' . $file );
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

	/**
	 * Creates a block from a title + spec. Imports default to a draft so a
	 * bundle always lands inactive for human review.
	 *
	 * @since 3.7.0
	 *
	 * @param string $title  Block title.
	 * @param array  $spec   Block spec.
	 * @param string $status 'draft' (default) or 'publish'.
	 * @return int|WP_Error
	 */
	public function create_block( string $title, array $spec, string $status = 'draft' ) {
		if ( empty( $spec['title'] ) ) {
			$spec['title'] = $title;
		}
		if ( empty( $spec['name'] ) ) {
			$spec['name'] = sanitize_title( $title );
		}
		return $this->create( $spec, $status );
	}

	public function apply_bundle( array $bundle ) {
		$spec = (array) ( $bundle['spec'] ?? array() );
		$name = (string) ( $bundle['meta']['title'] ?? 'Imported Block' );
		return $this->create_block( $name, $spec );
	}

	/**
	 * Deletes all generated blocks and removes the sandbox tree. Called from
	 * the plugin's uninstall handler — generated executable code must not
	 * survive uninstall.
	 *
	 * @since 3.7.0
	 */
	public static function uninstall_cleanup(): void {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $query->posts as $block_id ) {
			wp_delete_post( (int) $block_id, true );
		}

		$store = self::instance();
		$store->rmdir_recursive( $store->subdir_path() );
	}
}

EMCP_Tools_Block_Store::init();
