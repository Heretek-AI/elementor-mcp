<?php
/**
 * Database Exporter to a portable SQL dump.
 *
 * Streams table-by-table with bounded per-chunk fetches (LIMIT/OFFSET, 400 rows
 * per pass) so a large site never loads a whole table into PHP memory. Each row
 * is written as one explicit-column INSERT so import does not depend on column
 * order, and every value is escaped through the wpdb connection.
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_DB_Exporter {

	const CHUNK = 400;

	/**
	 * Export every table (or an explicit subset) to a SQL file.
	 *
	 * @param string   $filepath Destination .sql file.
	 * @param string[] $tables   Optional table allowlist ('' = all tables).
	 * @return array|false Stats on success, false when the file could not be written.
	 */
	public static function export_to_file( string $filepath, array $tables = array() ) {
		global $wpdb;

		$handle = @fopen( $filepath, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- local dump file.
		if ( false === $handle ) {
			return false;
		}

		fwrite( $handle, "-- EMCP Tools Database Dump\n-- Generated: " . gmdate( 'c' ) . "\n-- Charset: " . ( defined( 'DB_CHARSET' ) ? DB_CHARSET : 'utf8mb4' ) . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$all      = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB -- identifier enumeration.
		$selected = empty( $tables ) ? $all : array_values( array_intersect( $all, $tables ) );
		$stats    = array(
			'file'      => $filepath,
			'tables'    => count( $selected ),
			'rows'      => 0,
			'per_table' => array(),
		);

		foreach ( $selected as $table ) {
			$table_rows = self::dump_table( $handle, $wpdb, $table );
			if ( null === $table_rows ) {
				$stats['partial']   = true;
				$stats['per_table'][ $table ] = 'ERROR';
				continue;
			}
			$stats['rows'] += $table_rows;
			$stats['per_table'][ $table ] = $table_rows;
		}

		fwrite( $handle, "\nSET FOREIGN_KEY_CHECKS=1;\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $stats;
	}

	/**
	 * Dump a single table: DROP + CREATE preamble, then one INSERT per row.
	 *
	 * @param resource $handle  Open file handle.
	 * @param \wpdb    $wpdb    WordPress database.
	 * @param string   $table   Table name.
	 * @return int|null Row count, or null on error (table skipped, dump continues).
	 */
	private static function dump_table( $handle, $wpdb, string $table ) {
		$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N ); // phpcs:ignore WordPress.DB -- identifier quoted.
		if ( empty( $create[1] ) ) {
			return null;
		}
		fwrite( $handle, "DROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$columns = $wpdb->get_col( "DESCRIBE `{$table}`" ); // phpcs:ignore WordPress.DB -- identifier quoted.
		if ( empty( $columns ) ) {
			return 0;
		}
		$col_list = '`' . implode( '`, `', $columns ) . '`';

		$offset = 0;
		$rows   = 0;
		do {
			$limit = self::CHUNK;
			// phpcs:ignore WordPress.DB -- integers only; explicit column list from DESCRIBE.
			$chunk = $wpdb->get_results(
				"SELECT {$col_list} FROM `{$table}` LIMIT {$offset}, {$limit}",
				ARRAY_A
			);
			if ( null === $chunk ) {
				return null;
			}
			foreach ( $chunk as $row ) {
				$values = array();
				foreach ( $columns as $col ) {
					$values[] = self::sql_value( $wpdb, $row[ $col ] );
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- values escaped via sql_value() per column.
				fwrite( $handle, "INSERT INTO `{$table}` ({$col_list}) VALUES (" . implode( ', ', $values ) . ");\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$rows++;
			}
			$offset += $limit;
		} while ( count( $chunk ) === $limit );

		fwrite( $handle, "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $rows;
	}

	/**
	 * Format one value for SQL, escaped through the live wpdb connection.
	 *
	 * @param \wpdb $wpdb WordPress database.
	 * @param mixed $value Column value.
	 * @return string SQL literal.
	 */
	private static function sql_value( $wpdb, $value ) {
		if ( null === $value ) {
			return 'NULL';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		$escaped = $wpdb->_real_escape( (string) $value );
		return "'" . $escaped . "'";
	}
}
