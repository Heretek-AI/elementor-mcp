<?php
/**
 * Ultimate Addons for Elementor (UAE) integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_UAE_Integration {

	public function id(): string { return 'uae'; }
	public function label(): string { return 'Ultimate Addons for Elementor'; }
	public function is_available(): bool { return defined( 'UAEL_VER' ) || defined( 'HFE_VER' ); }

	public function read_tool(): string { return 'emcp-tools/uae-read'; }
	public function write_tool(): string { return 'emcp-tools/uae-write'; }
	public function get_ability_names(): array { return array( $this->read_tool(), $this->write_tool() ); }

	public function register(): void {
		emcp_tools_register_ability(
			$this->read_tool(),
			array(
				'label'               => $this->label() . ' Read',
				'description'         => __( 'Discover UAE widgets and Header/Footer templates.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => static function (): bool { return current_user_can( 'edit_posts' ); },
				'execute_callback'    => array( $this, 'execute_read' ),
			)
		);

		emcp_tools_register_ability(
			$this->write_tool(),
			array(
				'label'               => $this->label() . ' Write',
				'description'         => __( 'Manage UAE Header/Footer templates { action: "create"|"update", title, type }.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title' => array( 'type' => 'string' ),
						'type'  => array( 'type' => 'string', 'enum' => array( 'type_header', 'type_footer', 'type_before_footer', 'custom' ) ),
					),
					'required'   => array( 'title', 'type' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => static function (): bool { return current_user_can( 'publish_pages' ); },
				'execute_callback'    => array( $this, 'execute_write' ),
			)
		);
	}

	public function execute_read( array $args = array() ): array {
		$templates = get_posts( array( 'post_type' => 'elementor-hf', 'posts_per_page' => 50 ) );
		$out = array();
		foreach ( $templates as $t ) {
			$out[] = array( 'id' => $t->ID, 'title' => $t->post_title, 'type' => get_post_meta( $t->ID, 'ehf_template_type', true ) );
		}
		return array(
			'templates' => $out,
			'widgets'   => array(
				array( 'name' => 'uael-infobox', 'title' => 'Info Box' ),
				array( 'name' => 'uael-buttons', 'title' => 'Multi Buttons' ),
				array( 'name' => 'uael-heading', 'title' => 'Dual Color Heading' ),
			),
		);
	}

	public function execute_write( array $args ): array {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'elementor-hf',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $args['title'] ),
			)
		);
		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, 'ehf_template_type', sanitize_key( $args['type'] ) );
			return array( 'success' => true, 'id' => $post_id );
		}
		return array( 'success' => false );
	}
}
