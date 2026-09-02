<?php
/**
 * Themer Pro granular selector matchers.
 *
 * Extends the free matcher registry with specific post/page IDs, taxonomies/terms,
 * authors, date archives, child posts, user roles, and exclude rule inversion.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Themer_Pro_Matchers {

	/**
	 * Register Pro matchers onto the registry filter.
	 *
	 * @param array $matchers Existing matchers.
	 * @return array
	 */
	public static function add_matchers( array $matchers ): array {
		$matchers['post'] = array(
			'specificity' => 50,
			'callback'    => static function ( array $rule, array $ctx ): bool {
				if ( empty( $ctx['is_singular'] ) || empty( $ctx['post_id'] ) ) {
					return false;
				}
				$target_id = (int) EMCP_Tools_Themer_Matcher_Registry::param( $rule );
				return (int) $ctx['post_id'] === $target_id;
			},
		);

		$matchers['page'] = array(
			'specificity' => 50,
			'callback'    => static function ( array $rule, array $ctx ): bool {
				if ( empty( $ctx['is_page'] ) && ( empty( $ctx['is_singular'] ) || ( $ctx['post_type'] ?? '' ) !== 'page' ) ) {
					return false;
				}
				$target_id = (int) EMCP_Tools_Themer_Matcher_Registry::param( $rule );
				return ! empty( $ctx['post_id'] ) && (int) $ctx['post_id'] === $target_id;
			},
		);

		$matchers['term'] = array(
			'specificity' => 40,
			'callback'    => static function ( array $rule, array $ctx ): bool {
				$tax     = EMCP_Tools_Themer_Matcher_Registry::param( $rule );
				$term_id = (int) EMCP_Tools_Themer_Matcher_Registry::param2( $rule );

				if ( ! empty( $ctx['is_tax'] ) || ! empty( $ctx['is_category'] ) || ! empty( $ctx['is_tag'] ) ) {
					return ( $ctx['queried_taxonomy'] ?? '' ) === $tax && (int) ( $ctx['queried_term_id'] ?? 0 ) === $term_id;
				}

				if ( ! empty( $ctx['is_singular'] ) && ! empty( $ctx['post_id'] ) ) {
					return has_term( $term_id, $tax, (int) $ctx['post_id'] );
				}

				return false;
			},
		);

		$matchers['author'] = array(
			'specificity' => 30,
			'callback'    => static function ( array $rule, array $ctx ): bool {
				$author_id = (int) EMCP_Tools_Themer_Matcher_Registry::param( $rule );
				if ( ! empty( $ctx['is_author'] ) ) {
					return (int) ( $ctx['queried_author_id'] ?? 0 ) === $author_id;
				}
				if ( ! empty( $ctx['is_singular'] ) && ! empty( $ctx['post_id'] ) ) {
					$post = get_post( (int) $ctx['post_id'] );
					return $post && (int) $post->post_author === $author_id;
				}
				return false;
			},
		);

		$matchers['date'] = array(
			'specificity' => 20,
			'callback'    => static function ( array $rule, array $ctx ): bool {
				return ! empty( $ctx['is_date'] );
			},
		);

		$matchers['child-of'] = array(
			'specificity' => 45,
			'callback'    => static function ( array $rule, array $ctx ): bool {
				if ( empty( $ctx['is_singular'] ) || empty( $ctx['post_id'] ) ) {
					return false;
				}
				$parent_id = (int) EMCP_Tools_Themer_Matcher_Registry::param( $rule );
				$ancestors = get_post_ancestors( (int) $ctx['post_id'] );
				return in_array( $parent_id, $ancestors, true );
			},
		);

		$matchers['user-role'] = array(
			'specificity' => 25,
			'callback'    => static function ( array $rule, array $ctx ): bool {
				if ( ! is_user_logged_in() ) {
					return false;
				}
				$user = wp_get_current_user();
				$role = EMCP_Tools_Themer_Matcher_Registry::param( $rule );
				return in_array( $role, (array) $user->roles, true );
			},
		);

		return $matchers;
	}
}
