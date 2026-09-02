<?php
/**
 * Agent Project Memory Summarizer.
 *
 * Consolidates session summaries into durable memory entries.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Memory_Summarizer {

	/**
	 * Record a completed session summary.
	 *
	 * @param string $summary    Summary text.
	 * @param array  $learnings  List of learned guidelines or rules.
	 * @param string $session_id Optional session identifier.
	 * @return int[] IDs of created memory proposals.
	 */
	public static function record_session( string $summary, array $learnings = array(), string $session_id = '' ): array {
		$created_ids = array();

		// Save the session summary as a pending note.
		$sum_id = EMCP_Tools_Memory_Store::add_proposal(
			sprintf( __( 'Session Summary (%s)', 'emcp-tools' ), current_time( 'Y-m-d H:i' ) ),
			$summary,
			'info',
			'session',
			$session_id
		);
		if ( ! is_wp_error( $sum_id ) ) {
			$created_ids[] = $sum_id;
		}

		// Save each learning individually for modular review.
		foreach ( $learnings as $item ) {
			$title   = is_array( $item ) ? ( $item['title'] ?? 'Learned Guideline' ) : 'Learned Guideline';
			$content = is_array( $item ) ? ( $item['content'] ?? (string) $item ) : (string) $item;
			$sev     = is_array( $item ) ? ( $item['severity'] ?? 'info' ) : 'info';
			$target  = is_array( $item ) ? ( $item['target'] ?? '' ) : '';

			$lid = EMCP_Tools_Memory_Store::add_proposal( $title, $content, $sev, $target, $session_id );
			if ( ! is_wp_error( $lid ) ) {
				$created_ids[] = $lid;
			}
		}

		return $created_ids;
	}
}
