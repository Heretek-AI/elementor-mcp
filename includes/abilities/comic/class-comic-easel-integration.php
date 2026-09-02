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
		$read_names  = array_keys( EMCP_Tools_Comic_Read_Operations::op_schema() );
		$write_names = array_keys( EMCP_Tools_Comic_Write_Operations::op_schema() );

		emcp_tools_register_ability(
			'emcp-tools/comic-read',
			array(
				'label'               => __( 'Comic Easel Read', 'emcp-tools' ),
				'description'         => sprintf(
					/* translators: %1$s: comma-separated operation names */
					__( 'Read Comic Easel webcomics, multi-image strips (comic-html-below), source tracking (source_tweet_id, source_url), chapters, characters, and chronological navigation. Discovery: call with NO operation to receive each operation\'s JSON schema and an example, then call again with { operation, arguments }. Read operations: %1$s.', 'emcp-tools' ),
					implode( ', ', $read_names )
				),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'enum'        => $read_names,
							'description' => __( 'The read operation to run. Omit to list operations.', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the chosen operation (see the catalog returned when operation is omitted).', 'emcp-tools' ),
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
				'description'         => sprintf(
					/* translators: %1$s: comma-separated operation names */
					__( 'Create, update, or delete Comic Easel webcomics, multi-image strips (comic-html-below), source tracking (source_tweet_id, source_url), chapters, and characters. Discovery: call with NO operation to receive each operation\'s JSON schema and an example, then call again with { operation, arguments }. Write operations: %1$s. create-comic: status defaults to "publish" (pass "draft" to queue for review); backdate with date (ISO 8601 / Y-m-d H:i:s / unix timestamp); page 1 via featured_media_id/featured_media_url, pages 2..N via additional_images (attachment IDs or URLs); author via author_id or author (login/slug).', 'emcp-tools' ),
					implode( ', ', $write_names )
				),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'enum'        => $write_names,
							'description' => __( 'The write operation to run. Omit to list operations.', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the chosen operation (see the catalog returned when operation is omitted).', 'emcp-tools' ),
						),
					),
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
	 * Build a discovery-catalog response from an operations spec (op_schema).
	 *
	 * Backward-compatible with the earlier hand-maintained catalogs: each operation
	 * entry keeps the flat `arguments` hint map (now derived from the schema) and
	 * gains a typed `schema` plus a realistic `example`.
	 *
	 * @param string $tool        Ability name.
	 * @param string $description Catalog description.
	 * @param array  $spec        op_schema() output: name => { description, example, schema }.
	 * @return array
	 */
	private function catalog( string $tool, string $description, array $spec ): array {
		$operations = array();

		foreach ( $spec as $name => $entry ) {
			$schema   = (array) ( $entry['schema'] ?? array() );
			$required = (array) ( $schema['required'] ?? array() );
			$props    = (array) ( $schema['properties'] ?? array() );
			$args     = array();

			foreach ( $props as $arg_name => $prop ) {
				$args[ $arg_name ] = self::argument_hint( (array) $prop, in_array( $arg_name, $required, true ) );
			}

			$operations[ $name ] = array(
				'description' => (string) ( $entry['description'] ?? $name ),
				'arguments'   => $args,
				'required'    => $required,
				'schema'      => $schema,
				'example'     => (array) ( $entry['example'] ?? array() ),
			);
		}

		return array(
			'tool'        => $tool,
			'description' => $description,
			'operations'  => $operations,
		);
	}

	/**
	 * Render a short human hint for one schema property (the catalog `arguments` value).
	 *
	 * @param array $prop     Property schema.
	 * @param bool  $required Whether the property is in the operation's required list.
	 * @return string
	 */
	private static function argument_hint( array $prop, bool $required ): string {
		$type = isset( $prop['type'] ) ? $prop['type'] : 'mixed';
		if ( is_array( $type ) ) {
			$type = implode( '|', $type );
		}
		$hint = str_replace( 'integer', 'int', (string) $type );

		if ( ! empty( $prop['enum'] ) && is_array( $prop['enum'] ) ) {
			$hint .= ' (enum: ' . implode( '|', $prop['enum'] ) . ')';
		}
		if ( array_key_exists( 'default', $prop ) ) {
			$hint .= ' (default: ' . ( is_bool( $prop['default'] ) ? ( $prop['default'] ? 'true' : 'false' ) : (string) $prop['default'] ) . ')';
		}
		if ( $required ) {
			$hint .= ' (required)';
		}

		return $hint;
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
				return $this->catalog(
					'emcp-tools/comic-read',
					__( 'Read Comic Easel webcomics, multi-image strips, source tracking, chapters, and navigation.', 'emcp-tools' ),
					EMCP_Tools_Comic_Read_Operations::op_schema()
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
				return $this->catalog(
					'emcp-tools/comic-write',
					__( 'Create, update, or delete Comic Easel webcomics, multi-image strips, source metadata, and chapters.', 'emcp-tools' ),
					EMCP_Tools_Comic_Write_Operations::op_schema()
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
