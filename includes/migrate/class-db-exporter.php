<?php
/**
 * Database Exporter to SQL dump.
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_DB_Exporter {

	public static function export_to_file( string $filepath ): bool {
		global $wpdb;
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		$handle = fopen( $filepath, 'w' );
		if ( ! $handle ) { return false; }

		fwrite( $handle, "-- EMCP Tools Database Dump\n-- Generated: " . gmdate( 'c' ) . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n" );

		foreach ( $tables as $table ) {
			$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
			if ( ! empty( $create[1] ) ) {
				fwrite( $handle, "DROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n" );
			}

			$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$vals = array_map( function( $v ) use ( $wpdb ) {
						return null === $v ? 'NULL' : "'" . esc_sql( $v ) . "'";
					}, $row );
					fwrite( $handle, "INSERT INTO `{$table}` VALUES (" . implode( ', ', $vals ) . ");\n" );
				}
				fwrite( $handle, "\n" );
			}
		}

		fwrite( $handle, "SET FOREIGN_KEY_CHECKS=1;\n" );
		fclose( $handle );
		return true;
	}
}
