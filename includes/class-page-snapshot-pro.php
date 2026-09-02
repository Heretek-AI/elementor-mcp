<?php
/**
 * Page Snapshot Pro sections overlay.
 *
 * Hooks into `emcp_tools_page_snapshot_sections` to inject deep `seo` and `a11y`
 * audit sections into the get-page-snapshot tool output.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Page_Snapshot_Pro {

	/**
	 * Wire snapshot pro sections.
	 */
	public static function init(): void {
		add_filter( 'emcp_tools_page_snapshot_sections', array( __CLASS__, 'inject_sections' ), 10, 4 );
	}

	/**
	 * Inject deep a11y and seo audit sections into the page snapshot.
	 *
	 * @param array $sections Current snapshot sections.
	 * @param int   $post_id  Post ID.
	 * @param array $include  Requested section keys (empty = all).
	 * @param array $args     Additional args.
	 * @return array
	 */
	public static function inject_sections( array $sections, int $post_id, array $include = array(), array $args = array() ): array {
		if ( ! $post_id ) {
			return $sections;
		}

		$want_all  = empty( $include );
		$want_a11y = $want_all || in_array( 'a11y', $include, true );
		$want_seo  = $want_all || in_array( 'seo', $include, true );

		if ( ! $want_a11y && ! $want_seo ) {
			return $sections;
		}

		// Retrieve Elementor document structure if available.
		$elements = array();
		if ( class_exists( 'EMCP_Tools_Elementor_Data' ) ) {
			$data = EMCP_Tools_Elementor_Data::instance();
			$page = $data->get_page_data( $post_id );
			if ( is_array( $page ) ) {
				$elements = $page;
			}
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$site_host = is_string( $host ) ? $host : '';

		$extracted = array();
		if ( class_exists( 'EMCP_Tools_Content_Extractor' ) && ! empty( $elements ) ) {
			$extracted = EMCP_Tools_Content_Extractor::extract( $elements, $site_host );
		}

		// 1. Accessibility audit section.
		if ( $want_a11y && class_exists( 'EMCP_Tools_A11y_Abilities' ) && ! empty( $extracted ) ) {
			$sections['a11y'] = EMCP_Tools_A11y_Abilities::build_a11y_report( $extracted );
		}

		// 2. Deep SEO audit section.
		if ( $want_seo && class_exists( 'EMCP_Tools_Seo_Abilities' ) ) {
			$seo_meta = class_exists( 'EMCP_Tools_Seo_Meta' ) ? EMCP_Tools_Seo_Meta::get( $post_id ) : array();
			$target_kw = (string) ( $args['keyword'] ?? ( $seo_meta['focus_keyword'] ?? '' ) );
			$sections['seo'] = EMCP_Tools_Seo_Abilities::build_seo_report( $extracted, $seo_meta, $target_kw );
		}

		return $sections;
	}
}

// Auto-wire on load.
EMCP_Tools_Page_Snapshot_Pro::init();
