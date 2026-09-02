<?php
/**
 * Block Builder MCP abilities (Pro).
 *
 * Exposes 8 tools for AI agents to design, validate, compile, and manage custom Gutenberg blocks.
 *
 * @package EMCP_Tools
 * @since   3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Block_Builder_Abilities {

	public function get_ability_names(): array {
		return array(
			'emcp-tools/list-block-control-types',
			'emcp-tools/validate-block-spec',
			'emcp-tools/create-custom-block',
			'emcp-tools/update-custom-block',
			'emcp-tools/get-custom-block',
			'emcp-tools/list-custom-blocks',
			'emcp-tools/set-block-status',
			'emcp-tools/delete-custom-block',
		);
	}

	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-block-control-types',
			array(
				'label'               => __( 'List Block Attribute Types', 'emcp-tools' ),
				'description'         => __( 'List supported attribute types and schema capabilities for custom Gutenberg blocks.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'types' => array( 'type' => 'array' ) ) ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_list_types' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/validate-block-spec',
			array(
				'label'               => __( 'Validate Block Specification', 'emcp-tools' ),
				'description'         => __( 'Validate a block specification before compiling or installing it.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'spec' => array( 'type' => 'object' ) ),
					'required'   => array( 'spec' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'valid' => array( 'type' => 'boolean' ) ) ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_validate' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/create-custom-block',
			array(
				'label'               => __( 'Create Custom Gutenberg Block', 'emcp-tools' ),
				'description'         => __( 'Compile and install a custom Gutenberg block from a structured specification in the Sandbox.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'spec'   => array( 'type' => 'object' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ), 'default' => 'publish' ),
					),
					'required'   => array( 'spec' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'success' => array( 'type' => 'boolean' ), 'id' => array( 'type' => 'integer' ) ),
				),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_create' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/update-custom-block',
			array(
				'label'               => __( 'Update Custom Gutenberg Block', 'emcp-tools' ),
				'description'         => __( 'Update an existing custom Gutenberg block specification and recompile it.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'integer' ),
						'spec' => array( 'type' => 'object' ),
					),
					'required'   => array( 'id', 'spec' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ) ) ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_update' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/get-custom-block',
			array(
				'label'               => __( 'Get Custom Gutenberg Block', 'emcp-tools' ),
				'description'         => __( 'Retrieve the specification and status of a custom Gutenberg block by ID.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_get' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/list-custom-blocks',
			array(
				'label'               => __( 'List Custom Gutenberg Blocks', 'emcp-tools' ),
				'description'         => __( 'List all generated custom Gutenberg blocks stored in the Sandbox.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'blocks' => array( 'type' => 'array' ) ) ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_list' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/set-block-status',
			array(
				'label'               => __( 'Set Block Status', 'emcp-tools' ),
				'description'         => __( 'Activate (publish) or deactivate (draft) a custom Gutenberg block.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ) ),
					),
					'required'   => array( 'id', 'status' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ) ) ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_set_status' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/delete-custom-block',
			array(
				'label'               => __( 'Delete Custom Gutenberg Block', 'emcp-tools' ),
				'description'         => __( 'Permanently delete a custom Gutenberg block and its compiled files.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'confirm' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id', 'confirm' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ) ) ),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_delete' ),
			)
		);
	}

	public function execute_list_types(): array {
		return array( 'types' => EMCP_Tools_Block_Generator::TYPES );
	}

	public function execute_validate( array $args ) {
		$spec = (array) ( $args['spec'] ?? array() );
		$val  = EMCP_Tools_Block_Generator::validate( $spec );
		return is_wp_error( $val ) ? $val : array( 'valid' => true );
	}

	public function execute_create( array $args ) {
		$spec   = (array) ( $args['spec'] ?? array() );
		$status = (string) ( $args['status'] ?? 'publish' );
		$id     = EMCP_Tools_Block_Store::instance()->create( $spec, $status );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array( 'success' => true, 'id' => $id );
	}

	public function execute_update( array $args ) {
		$id   = (int) ( $args['id'] ?? 0 );
		$spec = (array) ( $args['spec'] ?? array() );
		$res  = EMCP_Tools_Block_Store::instance()->update( $id, $spec );
		return is_wp_error( $res ) ? $res : array( 'success' => true );
	}

	public function execute_get( array $args ) {
		$id = (int) ( $args['id'] ?? 0 );
		return EMCP_Tools_Block_Store::instance()->get( $id );
	}

	public function execute_list(): array {
		return array( 'blocks' => EMCP_Tools_Block_Store::instance()->list() );
	}

	public function execute_set_status( array $args ): array {
		$id     = (int) ( $args['id'] ?? 0 );
		$status = (string) ( $args['status'] ?? 'publish' );
		$ok     = EMCP_Tools_Block_Store::instance()->set_status( $id, $status );
		return array( 'success' => $ok );
	}

	public function execute_delete( array $args ) {
		if ( empty( $args['confirm'] ) ) {
			return new WP_Error( 'confirmation_required', __( 'Must provide confirm: true to delete a block.', 'emcp-tools' ) );
		}
		$id = (int) ( $args['id'] ?? 0 );
		$ok = EMCP_Tools_Block_Store::instance()->delete( $id );
		return array( 'success' => $ok );
	}
}
