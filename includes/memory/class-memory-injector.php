<?php
/**
 * Agent Project Memory Injector.
 *
 * Injects approved (published) guidance into the AI agent discovery context via `emcp_tools_discovery_memory`.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Memory_Injector {

	/**
	 * Init filter.
	 */
	public static function init(): void {
		add_filter( 'emcp_tools_discovery_memory', array( __CLASS__, 'render_memory_context' ) );
	}

	/**
	 * Assemble approved memory items into a markdown block.
	 *
	 * @param string $existing Existing memory block.
	 * @return string
	 */
	public static function render_memory_context( string $existing = '' ): string {
		if ( class_exists( 'EMCP_Tools_Memory_Module' ) && ! EMCP_Tools_Memory_Module::is_enabled() ) {
			return $existing;
		}

		$memories = EMCP_Tools_Memory_Store::query(
			array(
				'post_status'    => 'publish',
				'posts_per_page' => 20,
			)
		);

		if ( empty( $memories ) ) {
			return $existing;
		}

		$lines   = array();
		$lines[] = '## Project Memory (Approved Site Guidelines)';
		$lines[] = 'Follow these project-specific conventions established by the site administrator:';

		foreach ( $memories as $mem ) {
			$sev    = get_post_meta( $mem->ID, EMCP_Tools_Memory_Store::META_SEVERITY, true ) ?: 'info';
			$target = get_post_meta( $mem->ID, EMCP_Tools_Memory_Store::META_TARGET, true );
			$prefix = ( 'warning' === $sev || 'block' === $sev ) ? '[IMPORTANT] ' : '';
			$t_info = $target ? " ({$target})" : '';

			$lines[] = sprintf( '- **%s%s%s**: %s', $prefix, $mem->post_title, $t_info, wp_strip_all_tags( $mem->post_content ) );
		}

		return trim( $existing . "\n\n" . implode( "\n", $lines ) );
	}
}

EMCP_Tools_Memory_Injector::init();
