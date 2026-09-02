<?php
/**
 * Database Importer.
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_DB_Importer {

	public static function import_from_file( string $filepath ): bool {
		global $wpdb;
		if ( ! file_exists( $filepath ) ) { return false; }
		$lines = file( $filepath );
		$query = '';
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed || 0 === strpos( $trimmed, '--' ) ) { continue; }
			$query .= $line;
			if ( substr( $trimmed, -1 ) === ';' ) {
				$wpdb->query( $query );
				$query = '';
			}
		}
		return true;
	}
}
