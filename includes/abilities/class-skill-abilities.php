<?php
/**
 * Agent Skills MCP abilities (Pro).
 *
 * Exposes runtime discovery and retrieval of agent domain skills:
 *   - list-skills (lists available skills, optional search query)
 *   - get-skill   (returns full SKILL.md body by slug)
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Skill_Abilities {

	/**
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/list-skills',
			'emcp-tools/get-skill',
		);
	}

	/**
	 * Register abilities.
	 */
	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-skills',
			array(
				'label'               => __( 'List Agent Skills', 'emcp-tools' ),
				'description'         => __( 'List all available agent skills and domain instructions. Optional { search } keyword filter.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search' => array(
							'type'        => 'string',
							'description' => __( 'Optional search term to filter skill names, descriptions, or slugs.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'skills' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'slug'        => array( 'type' => 'string' ),
									'name'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
								),
							),
						),
						'total'  => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'execute_callback'    => array( $this, 'execute_list_skills' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/get-skill',
			array(
				'label'               => __( 'Get Agent Skill', 'emcp-tools' ),
				'description'         => __( 'Retrieve full markdown instructions for an agent skill by slug (e.g. "emcp-themer", "emcp-gutenberg", "emcp-themes/astra").', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => __( 'The exact slug of the skill to fetch.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'slug' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'slug'    => array( 'type' => 'string' ),
						'name'    => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'execute_callback'    => array( $this, 'execute_get_skill' ),
			)
		);
	}

	/**
	 * Read permission check.
	 *
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return current_user_can( 'read' );
	}

	/**
	 * Execute list-skills.
	 *
	 * @param array $args Input args.
	 * @return array
	 */
	public function execute_list_skills( array $args = array() ): array {
		$all    = EMCP_Tools_Skill_Catalog::get_all();
		$search = strtolower( trim( (string) ( $args['search'] ?? '' ) ) );

		$skills = array();
		foreach ( $all as $slug => $meta ) {
			if ( '' !== $search ) {
				$haystack = strtolower( $meta['slug'] . ' ' . $meta['name'] . ' ' . $meta['description'] );
				if ( false === strpos( $haystack, $search ) ) {
					continue;
				}
			}
			$skills[] = array(
				'slug'        => $meta['slug'],
				'name'        => $meta['name'],
				'description' => $meta['description'],
			);
		}

		return array(
			'skills' => $skills,
			'total'  => count( $skills ),
		);
	}

	/**
	 * Execute get-skill.
	 *
	 * @param array $args Input args.
	 * @return array|WP_Error
	 */
	public function execute_get_skill( array $args ) {
		$slug = trim( (string) ( $args['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return new WP_Error( 'missing_slug', __( 'A "slug" argument is required.', 'emcp-tools' ) );
		}

		$body = EMCP_Tools_Skill_Catalog::get_body( $slug );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$all = EMCP_Tools_Skill_Catalog::get_all();
		$name = $all[ $slug ]['name'] ?? $slug;

		return array(
			'slug'    => $slug,
			'name'    => $name,
			'content' => $body,
		);
	}
}
