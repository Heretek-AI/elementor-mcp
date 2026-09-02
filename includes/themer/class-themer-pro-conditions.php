<?php
/**
 * Themer Pro conditions schema enhancements.
 *
 * Adds Exclude rules, specific post/page/term/author object searches, and
 * granular target types to the Condition Builder UI schema.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Themer_Pro_Conditions {

	/**
	 * Filter the condition builder schema to inject Pro capabilities.
	 *
	 * @param array  $schema Base schema.
	 * @param string $type   Template type.
	 * @return array
	 */
	public static function filter_schema( array $schema, string $type ): array {
		// Add Exclude relation.
		$has_exclude = false;
		foreach ( $schema['relations'] as $rel ) {
			if ( 'exclude' === ( $rel['value'] ?? '' ) ) {
				$has_exclude = true;
				break;
			}
		}
		if ( ! $has_exclude ) {
			$schema['relations'][] = array(
				'value' => 'exclude',
				'label' => __( 'Exclude', 'emcp-tools' ),
			);
		}

		// Augment groups with granular selectors.
		foreach ( $schema['groups'] as &$group ) {
			if ( 'singular' === $group['value'] ) {
				$group['subs'][] = array(
					'value'    => 'specific-page',
					'label'    => __( 'Specific Page', 'emcp-tools' ),
					'selector' => 'page',
					'object'   => array( 'type' => 'post', 'post_type' => 'page' ),
				);
				$group['subs'][] = array(
					'value'    => 'specific-post',
					'label'    => __( 'Specific Post', 'emcp-tools' ),
					'selector' => 'post',
					'object'   => array( 'type' => 'post', 'post_type' => 'post' ),
				);
				$group['subs'][] = array(
					'value'    => 'by-author',
					'label'    => __( 'By Author', 'emcp-tools' ),
					'selector' => 'author',
					'object'   => array( 'type' => 'author' ),
				);
				$group['subs'][] = array(
					'value'    => 'in-category',
					'label'    => __( 'In Category', 'emcp-tools' ),
					'selector' => 'term:category',
					'object'   => array( 'type' => 'taxonomy', 'taxonomy' => 'category' ),
				);
			} elseif ( 'archive' === $group['value'] ) {
				$group['subs'][] = array(
					'value'    => 'author-archive',
					'label'    => __( 'Author Archive', 'emcp-tools' ),
					'selector' => 'author',
					'object'   => array( 'type' => 'author' ),
				);
				$group['subs'][] = array(
					'value'    => 'date-archive',
					'label'    => __( 'Date Archive', 'emcp-tools' ),
					'selector' => 'date',
				);
			}
		}
		unset( $group );

		return $schema;
	}
}
