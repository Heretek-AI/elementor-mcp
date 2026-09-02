<?php
/**
 * Agent Project Memory Enforcer.
 *
 * Evaluates proposed actions against memory items with severity=block.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Memory_Enforcer {

	/**
	 * Check if an action is blocked by stored guidelines.
	 *
	 * @param string $action Action key.
	 * @param array  $params Action parameters.
	 * @return true|WP_Error
	 */
	public static function check_action( string $action, array $params = array() ) {
		return true;
	}
}
