<?php
/**
 * Comic Easel MCP Integration.
 *
 * Exposes two dispatcher tools — `comic-read` and `comic-write` — providing comprehensive
 * abilities for webcomics, multi-image strips (comic-html-below), source tracking (source_tweet_id,
 * source_url), chapters, characters, and chronological navigation.
 *
 * @package EMCP_Tools
 * @since   3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Comic_Easel_Integration
 */
class EMCP_Tools_Comic_Easel_Integration {

	/**
	 * Check if Comic Easel is installed and active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		if ( post_type_exists( 'comic' ) ) {
			return true;
		}
		if ( function_exists( 'ceo_display_comic' ) || defined( 'CEO_VERSION' ) ) {
			return true;
		}
		if ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'comic-easel/comiceasel.php' ) || is_plugin_active( 'elementor-mcp/review/comic-easel/comiceasel.php' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return registered ability names.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/comic-read',
			'emcp-tools/comic-write',
		);
	}

	/**
	 * Register the abilities with the WordPress Abilities API.
	 */
	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/comic-read',
			array(
				'label'               => __( 'Comic Easel Read', 'emcp-tools' ),
				'description'         => __( 'Read Comic Easel webcomics, multi-image strips (comic-html-below), source tracking (source_tweet_id, source_url), chapters, characters, and chronological navigation. Call with no operation to list catalog.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'description' => __( 'The read operation to run. One of: get-comic, list-comics, find-by-source, get-navigation, list-chapters, list-characters, list-locations, get-settings. Omit to list operations.', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the chosen operation.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'can_read' ),
				'execute_callback'    => array( $this, 'execute_read' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/comic-write',
			array(
				'label'               => __( 'Comic Easel Write', 'emcp-tools' ),
				'description'         => __( 'Create, update, or delete Comic Easel webcomics, multi-image strips (comic-html-below), source tracking metadata, chapters, and characters. Call with no operation to list catalog.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'description' => __( 'The write operation to run. One of: create-comic, update-comic, delete-comic, create-chapter, update-chapter, delete-chapter, create-character, set-source. Omit to list operations.', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the chosen operation.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'operation' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'can_write' ),
				'execute_callback'    => array( $this, 'execute_write' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Permission check for read operations.
	 *
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( 'read' ) || current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check for write operations.
	 *
	 * @return bool
	 */
	public function can_write(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Execute a read operation.
	 *
	 * @param array $args Tool arguments.
	 * @return array|\WP_Error
	 */
	public function execute_read( array $args ) {
		$op = trim( (string) ( $args['operation'] ?? '' ) );
		$in = (array) ( $args['arguments'] ?? array() );

		switch ( $op ) {
			case 'get-comic':
				return EMCP_Tools_Comic_Read_Operations::get_comic( $in );

			case 'list-comics':
				return EMCP_Tools_Comic_Read_Operations::list_comics( $in );

			case 'find-by-source':
				return EMCP_Tools_Comic_Read_Operations::find_by_source( $in );

			case 'get-navigation':
				if ( empty( $in['id'] ) && empty( $in['slug'] ) ) {
					return new \WP_Error( 'missing_param', __( 'get-navigation requires either id or slug in arguments.', 'emcp-tools' ) );
				}
				$comic = EMCP_Tools_Comic_Read_Operations::get_comic( $in );
				if ( is_wp_error( $comic ) ) {
					return $comic;
				}
				return array(
					'comic_id'   => $comic['id'],
					'title'      => $comic['title'],
					'navigation' => $comic['navigation'],
				);

			case 'list-chapters':
				return EMCP_Tools_Comic_Read_Operations::list_chapters( $in );

			case 'list-characters':
				return EMCP_Tools_Comic_Read_Operations::list_characters();

			case 'list-locations':
				return EMCP_Tools_Comic_Read_Operations::list_locations();

			case 'get-settings':
				return EMCP_Tools_Comic_Read_Operations::get_settings();

			case '':
				return array(
					'tool'        => 'emcp-tools/comic-read',
					'description' => __( 'Read Comic Easel webcomics, multi-image strips, source tracking, chapters, and navigation.', 'emcp-tools' ),
					'operations'  => array(
						'get-comic'       => array(
							'description' => __( 'Get full comic details: featured image, multi-image strip, source metadata, taxonomies, and navigation.', 'emcp-tools' ),
							'arguments'   => array( 'id' => 'int (optional)', 'slug' => 'string (optional)' ),
						),
						'list-comics'     => array(
							'description' => __( 'List comics with filtering by chapter, character, location, tag, status, and chronological story order.', 'emcp-tools' ),
							'arguments'   => array( 'chapter' => 'slug|id', 'character' => 'slug|id', 'status' => 'string', 'order' => 'ASC|DESC', 'page' => 'int', 'per_page' => 'int' ),
						),
						'find-by-source'  => array(
							'description' => __( 'Find an existing comic by source_tweet_id or source_url (idempotency check for scrapers / n8n).', 'emcp-tools' ),
							'arguments'   => array( 'source_tweet_id' => 'string (optional)', 'source_url' => 'string (optional)' ),
						),
						'get-navigation'  => array(
							'description' => __( 'Get First/Previous/Next/Latest and In-Chapter navigation links for a comic.', 'emcp-tools' ),
							'arguments'   => array( 'id' => 'int', 'slug' => 'string' ),
						),
						'list-chapters'   => array(
							'description' => __( 'List all chapters and story arcs in hierarchy with page counts and menu order.', 'emcp-tools' ),
							'arguments'   => array( 'parent' => 'int (optional)' ),
						),
						'list-characters' => array(
							'description' => __( 'List all comic characters with post counts.', 'emcp-tools' ),
							'arguments'   => array(),
						),
						'list-locations'  => array(
							'description' => __( 'List all comic locations.', 'emcp-tools' ),
							'arguments'   => array(),
						),
						'get-settings'    => array(
							'description' => __( 'Get Comic Easel plugin configuration settings and active post type slug.', 'emcp-tools' ),
							'arguments'   => array(),
						),
					),
				);

			default:
				return new \WP_Error(
					'invalid_operation',
					sprintf(
						/* translators: %s: operation name */
						__( 'Unknown read operation "%s". Call with no operation to list catalog.', 'emcp-tools' ),
						$op
					)
				);
		}
	}

	/**
	 * Execute a write operation.
	 *
	 * @param array $args Tool arguments.
	 * @return array|\WP_Error
	 */
	public function execute_write( array $args ) {
		$op = trim( (string) ( $args['operation'] ?? '' ) );
		$in = (array) ( $args['arguments'] ?? array() );

		switch ( $op ) {
			case 'create-comic':
				return EMCP_Tools_Comic_Write_Operations::create_comic( $in );

			case 'update-comic':
				return EMCP_Tools_Comic_Write_Operations::update_comic( $in );

			case 'delete-comic':
				return EMCP_Tools_Comic_Write_Operations::delete_comic( $in );

			case 'create-chapter':
				return EMCP_Tools_Comic_Write_Operations::create_chapter( $in );

			case 'update-chapter':
				return EMCP_Tools_Comic_Write_Operations::update_chapter( $in );

			case 'delete-chapter':
				return EMCP_Tools_Comic_Write_Operations::delete_chapter( $in );

			case 'create-character':
				return EMCP_Tools_Comic_Write_Operations::create_character( $in );

			case 'set-source':
				return EMCP_Tools_Comic_Write_Operations::set_source( $in );

			case '':
				return array(
					'tool'        => 'emcp-tools/comic-write',
					'description' => __( 'Create, update, or delete Comic Easel webcomics, multi-image strips, source metadata, and chapters.', 'emcp-tools' ),
					'operations'  => array(
						'create-comic'     => array(
							'description' => __( 'Create a comic post with featured image, multi-image strip (comic-html-below), source tracking, and chapters.', 'emcp-tools' ),
							'arguments'   => array( 'title' => 'string (required)', 'content' => 'string', 'featured_media_url' => 'string', 'featured_media_id' => 'int', 'additional_images' => 'array of IDs or URLs', 'source_tweet_id' => 'string', 'source_url' => 'string', 'chapters' => 'array', 'hovertext' => 'string', 'transcript' => 'string' ),
						),
						'update-comic'     => array(
							'description' => __( 'Update comic post details, images, additional_images (append or replace), source tracking, or metadata.', 'emcp-tools' ),
							'arguments'   => array( 'id' => 'int (required)', 'title' => 'string', 'additional_images' => 'array', 'append_images' => 'bool', 'source_tweet_id' => 'string', 'source_url' => 'string' ),
						),
						'delete-comic'     => array(
							'description' => __( 'Trash or permanently delete a comic post (requires confirm: true).', 'emcp-tools' ),
							'arguments'   => array( 'id' => 'int (required)', 'force' => 'bool (optional)', 'confirm' => 'bool (required: true)' ),
						),
						'create-chapter'   => array(
							'description' => __( 'Create a chapter / story arc term with optional parent and menu order.', 'emcp-tools' ),
							'arguments'   => array( 'name' => 'string (required)', 'slug' => 'string', 'parent' => 'int', 'description' => 'string', 'menu_order' => 'int' ),
						),
						'update-chapter'   => array(
							'description' => __( 'Update chapter term name, slug, parent, description, or menu order.', 'emcp-tools' ),
							'arguments'   => array( 'id' => 'int (required)', 'name' => 'string', 'menu_order' => 'int' ),
						),
						'delete-chapter'   => array(
							'description' => __( 'Delete a chapter term (requires confirm: true).', 'emcp-tools' ),
							'arguments'   => array( 'id' => 'int (required)', 'confirm' => 'bool (required: true)' ),
						),
						'create-character' => array(
							'description' => __( 'Create a character term.', 'emcp-tools' ),
							'arguments'   => array( 'name' => 'string (required)', 'description' => 'string' ),
						),
						'set-source'       => array(
							'description' => __( 'Quick helper to attach source_tweet_id and source_url to an existing comic post.', 'emcp-tools' ),
							'arguments'   => array( 'id' => 'int (required)', 'source_tweet_id' => 'string', 'source_url' => 'string' ),
						),
					),
				);

			default:
				return new \WP_Error(
					'invalid_operation',
					sprintf(
						/* translators: %s: operation name */
						__( 'Unknown write operation "%s". Call with no operation to list catalog.', 'emcp-tools' ),
						$op
					)
				);
		}
	}
}
