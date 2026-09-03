<?php
/**
 * Database Importer for .emcp SQL dumps.
 *
 * Streams the dump line-by-line and splits statements with a quote-aware
 * scanner, so a semicolon inside a quoted value (or a CREATE TABLE column
 * comment) can never truncate a statement. Session transaction-control
 * directives (SET AUTOCOMMIT / START TRANSACTION / COMMIT / BEGIN / ROLLBACK)
 * are deliberately skipped — running them on the shared $wpdb connection would
 * otherwise leave autocommit off and silently roll every later write back at
 * request end (a live-caught bug in the original Backup/Restore port).
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_DB_Importer {

	/** Directives that must never run on the live connection. */
	const SKIP_PREFIXES = array(
		'SET AUTOCOMMIT',
		'START TRANSACTION',
		'BEGIN',
		'COMMIT',
		'ROLLBACK',
	);

	/**
	 * Import a .sql dump into the current database.
	 *
	 * @param string $filepath Path to the dump.
	 * @return array|false { statements, executed, skipped, errors, error_details } or false when the file is missing.
	 */
	public static function import_from_file( string $filepath ) {
		if ( ! is_file( $filepath ) ) {
			return false;
		}
		global $wpdb;
		$stats = array(
			'statements'    => 0,
			'executed'      => 0,
			'skipped'       => 0,
			'errors'        => 0,
			'error_details' => array(),
		);

		$handle  = fopen( $filepath, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $handle ) {
			return false;
		}
		$buffer = '';
		while ( false !== ( $line = fgets( $handle ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$buffer .= $line;
			while ( null !== ( $stmt = self::extract_statement( $buffer ) ) ) {
				$stats['statements']++;
				$trimmed = self::strip_leading_comments( $stmt );
				if ( self::is_skip_directive( $trimmed ) ) {
					$stats['skipped']++;
					continue;
				}
				$result = $wpdb->query( $trimmed ); // phpcs:ignore WordPress.DB -- validated dump statement; admin-authorized restore.
				if ( false === $result ) {
					$stats['errors']++;
					if ( count( $stats['error_details'] ) < 20 ) {
						$stats['error_details'][] = array(
							'error' => $wpdb->last_error,
							'stmt'  => self::truncate( $trimmed, 200 ),
						);
					}
				} else {
					$stats['executed']++;
				}
			}
		}
		// Trailing statement with no final ';' — execute it if anything remains.
		$buffer = self::strip_leading_comments( $buffer );
		if ( '' !== trim( $buffer ) && ! self::is_skip_directive( trim( $buffer ) ) ) {
			$stats['statements']++;
			$trimmed = trim( $buffer );
			$result = $wpdb->query( $trimmed ); // phpcs:ignore WordPress.DB -- validated dump statement.
			if ( false === $result ) {
				$stats['errors']++;
			} else {
				$stats['executed']++;
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $stats;
	}

	/**
	 * Drop full-line `--` comments that lead a statement so a directive after a
	 * dump banner (which begins with `--`) is still recognised as such.
	 *
	 * @param string $stmt Statement (may include leading comment lines).
	 * @return string Comment-free statement.
	 */
	private static function strip_leading_comments( string $stmt ): string {
		$out = preg_replace( '/^[ \t]*--[^\r\n]*(?:\r?\n|$)/m', '', $stmt );
		return null === $out ? $stmt : trim( $out );
	}

	/**
	 * Pull one complete statement (ending in a top-level ';') off the buffer.
	 *
	 * @param string $buffer In/out accumulator (reference).
	 * @return string|null The statement including its trailing ';', or null when no complete statement exists yet.
	 */
	private static function extract_statement( string &$buffer ) {
		$len = strlen( $buffer );
		$i   = 0;
		$sq  = false; // Single-quoted string.
		$dq  = false; // Double-quoted string.
		$bt  = false; // Backtick-quoted identifier.
		$lc  = false; // Line comment (-- to EOL).
		$bc  = false; // Block comment (/* ... */).
		while ( $i < $len ) {
			$c = $buffer[ $i ];
			if ( $lc ) {
				if ( "\n" === $c ) {
					$lc = false;
				}
			} elseif ( $bc ) {
				if ( '*' === $c && isset( $buffer[ $i + 1 ] ) && '/' === $buffer[ $i + 1 ] ) {
					$bc = false;
					$i++;
				}
			} elseif ( $sq || $dq || $bt ) {
				if ( '\\' === $c ) {
					$i++; // Skip escaped character.
				} elseif ( $sq && "'" === $c ) {
					$sq = false;
				} elseif ( $dq && '"' === $c ) {
					$dq = false;
				} elseif ( $bt && '`' === $c ) {
					$bt = false;
				}
			} else {
				if ( "'" === $c ) {
					$sq = true;
				} elseif ( '"' === $c ) {
					$dq = true;
				} elseif ( '`' === $c ) {
					$bt = true;
				} elseif ( '-' === $c && isset( $buffer[ $i + 1 ] ) && '-' === $buffer[ $i + 1 ] ) {
					$lc = true;
					$i++;
				} elseif ( '/' === $c && isset( $buffer[ $i + 1 ] ) && '*' === $buffer[ $i + 1 ] ) {
					$bc = true;
					$i++;
				} elseif ( ';' === $c ) {
					$stmt  = substr( $buffer, 0, $i + 1 );
					$buffer = substr( $buffer, $i + 1 );
					return $stmt;
				}
			}
			$i++;
		}
		return null;
	}

	/**
	 * Whether a trimmed statement is a session transaction-control directive.
	 *
	 * @param string $stmt Trimmed statement.
	 * @return bool
	 */
	private static function is_skip_directive( string $stmt ): bool {
		$upper = strtoupper( $stmt );
		foreach ( self::SKIP_PREFIXES as $prefix ) {
			if ( 0 === strpos( $upper, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Truncate a statement for an error report.
	 *
	 * @param string $stmt Statement.
	 * @param int    $max  Max chars.
	 * @return string
	 */
	private static function truncate( string $stmt, int $max = 200 ): string {
		if ( strlen( $stmt ) <= $max ) {
			return $stmt;
		}
		return substr( $stmt, 0, $max ) . '…';
	}
}
