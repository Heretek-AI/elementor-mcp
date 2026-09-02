<?php
/**
 * EMCP Themer Pro orchestrator.
 *
 * Attaches granular matchers, condition schemas, unlimited quotas,
 * and priority resolution to the free Themer seams.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Themer_Pro {

	/**
	 * Wire Themer Pro hooks and filters.
	 */
	public static function init(): void {
		// 1. Unlimited quota: unlock template creation cap (free default is 1 per type).
		add_filter( 'emcp_themer_quota', array( __CLASS__, 'unlimited_quota' ), 10, 2 );

		// 2. Granular matchers.
		if ( class_exists( 'EMCP_Tools_Themer_Pro_Matchers' ) ) {
			add_filter( 'emcp_themer_matchers', array( 'EMCP_Tools_Themer_Pro_Matchers', 'add_matchers' ) );
		}

		// 3. Condition builder UI schema.
		if ( class_exists( 'EMCP_Tools_Themer_Pro_Conditions' ) ) {
			add_filter( 'emcp_themer_condition_schema', array( 'EMCP_Tools_Themer_Pro_Conditions', 'filter_schema' ), 10, 2 );
		}

		// 4. Exclude rules filter for render controller.
		add_filter( 'emcp_themer_filter_matched_templates', array( __CLASS__, 'apply_exclude_rules' ), 10, 3 );
	}

	/**
	 * Return unlimited cap for any template type.
	 *
	 * @param int    $cap  Default cap.
	 * @param string $type Template type.
	 * @return int
	 */
	public static function unlimited_quota( $cap, string $type ): int {
		return PHP_INT_MAX;
	}

	/**
	 * Apply exclude rules to matched templates. If a template has an exclude rule matching the current context,
	 * it is dropped from candidate matches.
	 *
	 * @param array $candidates Matched template posts or definitions.
	 * @param array $ctx        Current request context.
	 * @param mixed $registry   Matcher registry.
	 * @return array
	 */
	public static function apply_exclude_rules( array $candidates, array $ctx, $registry ): array {
		if ( empty( $candidates ) || ! is_object( $registry ) ) {
			return $candidates;
		}

		$filtered = array();
		foreach ( $candidates as $candidate ) {
			$post_id = is_object( $candidate ) && isset( $candidate->ID ) ? (int) $candidate->ID : (int) ( $candidate['id'] ?? 0 );
			if ( ! $post_id ) {
				$filtered[] = $candidate;
				continue;
			}

			$rules = get_post_meta( $post_id, EMCP_Tools_Themer_Index::META_CONDITIONS, true );
			$is_excluded = false;

			if ( is_array( $rules ) ) {
				foreach ( $rules as $rule ) {
					if ( 'exclude' === ( $rule['relation'] ?? '' ) ) {
						if ( method_exists( $registry, 'matches' ) && $registry->matches( $rule, $ctx ) ) {
							$is_excluded = true;
							break;
						}
					}
				}
			}

			if ( ! $is_excluded ) {
				$filtered[] = $candidate;
			}
		}

		return $filtered;
	}
}
