<?php
/**
 * Agent Project Memory MCP abilities (Pro).
 *
 * Exposes three tools for agent memory operations:
 *   - remember             (proposes a new site guideline, queued for human approval)
 *   - recall               (searches approved or all project memories)
 *   - save-session-summary (records turn/session summaries and extracted learnings)
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Memory_Abilities {

	/**
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/remember',
			'emcp-tools/recall',
			'emcp-tools/save-session-summary',
		);
	}

	/**
	 * Register abilities.
	 */
	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/remember',
			array(
				'label'               => __( 'Remember Project Guideline', 'emcp-tools' ),
				'description'         => __( 'Propose a site guideline, constraint, or learning to be remembered across future agent sessions (queued as pending for admin approval).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array(
							'type'        => 'string',
							'description' => __( 'Short summary title of the rule or convention.', 'emcp-tools' ),
						),
						'guideline' => array(
							'type'        => 'string',
							'description' => __( 'Detailed description of the convention, requirement, or pattern.', 'emcp-tools' ),
						),
						'severity'  => array(
							'type'        => 'string',
							'enum'        => array( 'info', 'warning', 'block' ),
							'default'     => 'info',
							'description' => __( 'Severity/strictness level.', 'emcp-tools' ),
						),
						'target'    => array(
							'type'        => 'string',
							'description' => __( 'Optional target component or subsystem (e.g. "header", "colors", "forms").', 'emcp-tools' ),
						),
					),
					'required'   => array( 'title', 'guideline' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'status'  => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'execute_callback'    => array( $this, 'execute_remember' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/recall',
			array(
				'label'               => __( 'Recall Project Memories', 'emcp-tools' ),
				'description'         => __( 'Search and retrieve remembered project guidelines and session records.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search' => array(
							'type'        => 'string',
							'description' => __( 'Optional search keyword.', 'emcp-tools' ),
						),
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'pending', 'any' ),
							'default'     => 'publish',
							'description' => __( 'Filter by approval status (default "publish" for approved guidelines).', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'memories' => array( 'type' => 'array' ),
						'total'    => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'execute_callback'    => array( $this, 'execute_recall' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/save-session-summary',
			array(
				'label'               => __( 'Save Session Summary', 'emcp-tools' ),
				'description'         => __( 'Save a high-level summary of tasks performed during the current session, along with any key takeaways.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'summary'   => array(
							'type'        => 'string',
							'description' => __( 'Summary of changes made or actions completed.', 'emcp-tools' ),
						),
						'learnings' => array(
							'type'        => 'array',
							'description' => __( 'Optional list of guidelines or rules learned.', 'emcp-tools' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'title'     => array( 'type' => 'string' ),
									'content'   => array( 'type' => 'string' ),
									'severity'  => array( 'type' => 'string' ),
									'target'    => array( 'type' => 'string' ),
								),
								'required'   => array( 'title', 'content' ),
							),
						),
					),
					'required'   => array( 'summary' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'created_ids' => array( 'type' => 'array' ),
					),
				),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'execute_callback'    => array( $this, 'execute_save_session_summary' ),
			)
		);
	}

	public function check_read_permission(): bool {
		return current_user_can( 'read' );
	}

	public function execute_remember( array $args ) {
		$title     = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$guideline = (string) ( $args['guideline'] ?? '' );
		$severity  = (string) ( $args['severity'] ?? 'info' );
		$target    = sanitize_text_field( (string) ( $args['target'] ?? '' ) );

		if ( '' === $title || '' === $guideline ) {
			return new WP_Error( 'missing_param', __( 'Both "title" and "guideline" are required.', 'emcp-tools' ) );
		}

		$post_id = EMCP_Tools_Memory_Store::add_proposal( $title, $guideline, $severity, $target );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'success' => true,
			'id'      => $post_id,
			'status'  => 'pending',
			'message' => __( 'Guideline proposed successfully and queued for administrator approval.', 'emcp-tools' ),
		);
	}

	public function execute_recall( array $args = array() ) {
		$search = (string) ( $args['search'] ?? '' );
		$status = (string) ( $args['status'] ?? 'publish' );

		$query_args = array(
			'post_status'    => 'any' === $status ? array( 'publish', 'pending' ) : $status,
			'posts_per_page' => 50,
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		$posts = EMCP_Tools_Memory_Store::query( $query_args );
		$memories = array();

		foreach ( $posts as $post ) {
			$memories[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'guideline' => $post->post_content,
				'status'    => $post->post_status,
				'severity'  => get_post_meta( $post->ID, EMCP_Tools_Memory_Store::META_SEVERITY, true ) ?: 'info',
				'target'    => get_post_meta( $post->ID, EMCP_Tools_Memory_Store::META_TARGET, true ) ?: '',
				'date'      => $post->post_date,
			);
		}

		return array(
			'memories' => $memories,
			'total'    => count( $memories ),
		);
	}

	public function execute_save_session_summary( array $args ) {
		$summary   = (string) ( $args['summary'] ?? '' );
		$learnings = (array) ( $args['learnings'] ?? array() );

		if ( '' === $summary ) {
			return new WP_Error( 'missing_summary', __( 'A "summary" string is required.', 'emcp-tools' ) );
		}

		$ids = EMCP_Tools_Memory_Summarizer::record_session( $summary, $learnings );

		return array(
			'success'     => true,
			'created_ids' => $ids,
		);
	}
}
