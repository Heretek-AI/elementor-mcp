<?php
/**
 * Agent Project Memory Digest.
 *
 * Extracts learnings and summary items from session activity.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Memory_Digest {

	/**
	 * Summarize recent change logs.
	 *
	 * @param int $limit Number of recent changes to digest.
	 * @return array
	 */
	public static function digest_recent_changes( int $limit = 10 ): array {
		if ( ! class_exists( 'EMCP_Tools_Change_Log' ) ) {
			return array();
		}
		return EMCP_Tools_Change_Log::list_entries( array( 'limit' => $limit ) );
	}
}
