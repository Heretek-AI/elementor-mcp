<?php
/**
 * Abstract base for Elementor Addon widget pack integrations.
 *
 * Exposes a single read tool for discovery and curation of available addon widgets.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class EMCP_Tools_Addon_Pack_Integration {

	abstract public function id(): string;
	abstract public function label(): string;
	abstract public function is_available(): bool;
	abstract public function get_widgets(): array;

	public function read_tool(): string {
		return 'emcp-tools/' . $this->id() . '-read';
	}

	public function get_ability_names(): array {
		return array( $this->read_tool() );
	}

	public function register(): void {
		emcp_tools_register_ability(
			$this->read_tool(),
			array(
				'label'               => $this->label() . ' Read',
				'description'         => sprintf( __( 'Discover and list widgets provided by %s.', 'emcp-tools' ), $this->label() ),
				'category'            => 'emcp-tools',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'widgets' => array( 'type' => 'array' ) ),
				),
				'permission_callback' => array( $this, 'can_read' ),
				'execute_callback'    => array( $this, 'execute_read' ),
			)
		);
	}

	public function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function execute_read( array $args = array() ): array {
		return array(
			'pack'    => $this->id(),
			'widgets' => $this->get_widgets(),
		);
	}
}
