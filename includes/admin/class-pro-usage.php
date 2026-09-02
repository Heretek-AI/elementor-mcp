<?php
/**
 * Pro Usage tracker.
 *
 * Tracks local usage of templates and prompts to render usage badges and counts.
 *
 * @package EMCP_Tools
 * @since   1.7.1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Pro_Usage {
	const OPTION_COUNTS = 'emcp_tools_usage_counts';

	public static function get_counts(): array {
		return (array) get_option( self::OPTION_COUNTS, array() );
	}

	public static function record_use( string $slug, string $type = 'template' ): void {
		$counts = self::get_counts();
		$key    = $type . ':' . $slug;
		$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
		update_option( self::OPTION_COUNTS, $counts );
	}

	public static function local_summary(): array {
		$counts    = self::get_counts();
		$templates = 0;
		$prompts   = 0;
		foreach ( $counts as $k => $c ) {
			if ( 0 === strpos( $k, 'template:' ) ) {
				$templates += (int) $c;
			} elseif ( 0 === strpos( $k, 'prompt:' ) ) {
				$prompts += (int) $c;
			}
		}
		return array(
			'templates' => $templates,
			'prompts'   => $prompts,
		);
	}
}
