<?php
/**
 * Ads & Monetization MCP Integration.
 *
 * Exposes two dispatcher tools — `ads-read` and `ads-write` — providing comprehensive
 * abilities for WP Quads ad units, dynamic ads.txt, cache invalidation, and ExoClick API integrations.
 *
 * @package EMCP_Tools
 * @since   3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Ads_Integration
 */
class EMCP_Tools_Ads_Integration {

	/**
	 * Check if ads module or WP Quads is available.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return true;
	}

	/**
	 * Return registered ability names.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/ads-read',
			'emcp-tools/ads-write',
		);
	}

	/**
	 * Register the abilities with the WordPress Abilities API.
	 */
	public function register(): void {
		$read_names  = array_keys( EMCP_Tools_Ads_Read_Operations::op_schema() );
		$write_names = array_keys( EMCP_Tools_Ads_Write_Operations::op_schema() );

		emcp_tools_register_ability(
			'emcp-tools/ads-read',
			array(
				'label'               => __( 'Ads & Monetization Read', 'emcp-tools' ),
				'description'         => sprintf(
					/* translators: %1$s: comma-separated operation names */
					__( 'Read and inspect ad configurations, WP Quads slots, /ads.txt records, network detection, and ExoClick reporting. Pass the `operation` you want plus its `arguments`. If you omit `operation`, the tool returns its full operations catalog. Read operations: %1$s.', 'emcp-tools' ),
					implode( ', ', $read_names )
				),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'enum'        => $read_names,
							'description' => __( 'The read operation to run.', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the chosen operation (see catalog returned when operation is omitted).', 'emcp-tools' ),
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
			'emcp-tools/ads-write',
			array(
				'label'               => __( 'Ads & Monetization Write', 'emcp-tools' ),
				'description'         => sprintf(
					/* translators: %1$s: comma-separated operation names */
					__( 'Create, update, or delete WP Quads ad slots with dual-write sync, edit /ads.txt with IAB validation, flush ad caches, and manage ExoClick zones. Pass the `operation` you want plus its `arguments`. If you omit `operation`, the tool returns its full operations catalog. Write operations: %1$s.', 'emcp-tools' ),
					implode( ', ', $write_names )
				),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'enum'        => $write_names,
							'description' => __( 'The write operation to run.', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the chosen operation (see catalog returned when operation is omitted).', 'emcp-tools' ),
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
	 * Build discovery-catalog response.
	 *
	 * @param string $tool
	 * @param string $description
	 * @param array  $spec
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
	 * Short human hint for schema property.
	 *
	 * @param array $prop
	 * @param bool  $required
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
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute read operation.
	 *
	 * @param array $args
	 * @return array|\WP_Error
	 */
	public function execute_read( array $args ) {
		$op = trim( (string) ( $args['operation'] ?? '' ) );
		$in = (array) ( $args['arguments'] ?? array() );

		if ( '' === $op ) {
			return $this->catalog(
				'emcp-tools/ads-read',
				__( 'Read and inspect ad configurations, WP Quads slots, /ads.txt records, and ExoClick reporting.', 'emcp-tools' ),
				EMCP_Tools_Ads_Read_Operations::op_schema()
			);
		}

		return EMCP_Tools_Ads_Read_Operations::execute( $op, $in );
	}

	/**
	 * Execute write operation.
	 *
	 * @param array $args
	 * @return array|\WP_Error
	 */
	public function execute_write( array $args ) {
		$op = trim( (string) ( $args['operation'] ?? '' ) );
		$in = (array) ( $args['arguments'] ?? array() );

		if ( '' === $op ) {
			return $this->catalog(
				'emcp-tools/ads-write',
				__( 'Create, update, or delete WP Quads ad slots with dual-write sync, edit /ads.txt, flush ad caches, and manage ExoClick zones.', 'emcp-tools' ),
				EMCP_Tools_Ads_Write_Operations::op_schema()
			);
		}

		return EMCP_Tools_Ads_Write_Operations::execute( $op, $in );
	}
}
