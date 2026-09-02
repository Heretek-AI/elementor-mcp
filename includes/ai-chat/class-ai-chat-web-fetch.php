<?php
/**
 * AI Chat Web Fetch helper.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Web_Fetch {
	public static function fetch( string $url ) {
		$resp = wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 10 ) );
		if ( is_wp_error( $resp ) ) { return $resp; }
		return wp_remote_retrieve_body( $resp );
	}
}
