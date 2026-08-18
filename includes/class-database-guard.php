<?php
/**
 * Database safety guard: validates read-only SQL for the `query` tool,
 * validates/protects table names for structured writes, captures before-image
 * snapshots, and audits. is_read_only_sql() is the safety boundary for the
 * flexible read path.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.0.0
 */
class EMCP_Tools_Database_Guard {

	const MAX_ROWS         = 1000;
	/** Server-side statement timeout for read-only queries, in seconds. */
	const MAX_QUERY_SECONDS = 10;
	const BEFORE_IMAGE_CAP = 500;

	/**
	 * Pure: normalize SQL for safe keyword scanning — replace every comment with
	 * a space and every string / backtick-identifier literal with an empty
	 * placeholder, so keywords cannot hide inside comments, strings, or quoted
	 * identifiers. Does NOT special-case /*! (the caller rejects those first).
	 *
	 * @param string $sql
	 * @return string
	 */
	public static function normalize_sql( string $sql, bool $backslash_escapes = true, bool $keep_identifiers = false, bool $ansi_quotes = false ): string {
		$out = '';
		$len = strlen( $sql );
		$i   = 0;
		while ( $i < $len ) {
			$c   = $sql[ $i ];
			$two = substr( $sql, $i, 2 );
			// MySQL only starts a comment at `--` when the second dash is followed
			// by whitespace or a control character. `1--1` is arithmetic (1 minus
			// -1), NOT a comment. Treating every `--` as a comment let a caller
			// hide the rest of the line while MySQL still executed it.
			if ( '#' === $c || ( '--' === $two && self::starts_comment( $sql, $i + 2 ) ) ) {
				$nl  = strpos( $sql, "\n", $i );
				$i   = ( false === $nl ) ? $len : $nl + 1;
				$out .= ' ';
				continue;
			}
			if ( '/*' === $two ) {
				$end = strpos( $sql, '*/', $i + 2 );
				$i   = ( false === $end ) ? $len : $end + 2;
				$out .= ' ';
				continue;
			}
			// Under ANSI_QUOTES a double quote delimits an IDENTIFIER, not a string,
			// so it must fall through to the identifier branch below. Treating it as
			// a literal blanked the token and hid a protected table inside it.
			if ( "'" === $c || ( '"' === $c && ! $ansi_quotes ) ) {
				$q = $c;
				$i++;
				while ( $i < $len ) {
					// Under NO_BACKSLASH_ESCAPES a backslash is an ordinary character,
					// so the string ends earlier than a backslash-aware scan thinks and
					// real SQL hides inside what we took for a literal.
					if ( $backslash_escapes && '\\' === $sql[ $i ] ) { $i += 2; continue; }
					if ( $sql[ $i ] === $q ) {
						if ( $i + 1 < $len && $sql[ $i + 1 ] === $q ) { $i += 2; continue; }
						$i++;
						break;
					}
					$i++;
				}
				$out .= "''";
				continue;
			}
			if ( '`' === $c || ( '"' === $c && $ansi_quotes ) ) {
				$delim = $c;
				$i++;
				$ident = '';
				while ( $i < $len ) {
					if ( $delim === $sql[ $i ] ) {
						// A doubled delimiter is a literal one inside the name.
						if ( $i + 1 < $len && $delim === $sql[ $i + 1 ] ) {
							$ident .= $delim;
							$i     += 2;
							continue;
						}
						$i++;
						break;
					}
					$ident .= $sql[ $i ];
					$i++;
				}
				// Keeping the name lets the protected-table scan see `wp_users`
				// without the caller stripping backticks first. Doing that before
				// lexing fed identifier contents back through the lexer, so an
				// alias like `x#` opened a comment and hid everything after it.
				// Blanking it keeps identifiers out of the keyword denylist, so a
				// column named `delete` is not mistaken for a write.
				$out .= $keep_identifiers ? ' ' . $ident . ' ' : '``';
				continue;
			}
			$out .= $c;
			$i++;
		}
		return $out;
	}

	/**
	 * Does a `--` at $at begin a MySQL comment?
	 *
	 * MySQL requires the second dash to be followed by whitespace or a control
	 * character. Anything else (a digit, a letter, an open paren) makes it the
	 * subtraction operator applied to a negated value.
	 *
	 * @param string $sql Raw SQL.
	 * @param int    $at  Offset just past the two dashes.
	 * @return bool
	 */
	private static function starts_comment( string $sql, int $at ): bool {
		if ( $at >= strlen( $sql ) ) {
			return false; // Nothing follows, so nothing can be hidden either way.
		}
		$next = $sql[ $at ];
		return ' ' === $next || "\t" === $next || "\n" === $next || "\r" === $next
			|| "\0" === $next || "\x0B" === $next || "\f" === $next;
	}

	/**
	 * Every reading of $sql this server could plausibly take.
	 *
	 * Whether a backslash escapes inside a string literal depends on the
	 * session's NO_BACKSLASH_ESCAPES mode, which this code cannot assume. Rather
	 * than guess, produce both readings; the security checks then refuse the
	 * query when EITHER reading contains something disallowed. A legitimate
	 * query is unaffected, because the alternative reading of an innocent
	 * literal is still innocent.
	 *
	 * @param string $sql Raw SQL.
	 * @return string[] One entry when both readings agree, two when they differ.
	 */
	public static function normalize_variants( string $sql, bool $keep_identifiers = false ): array {
		$out = array();
		// Two independent sql_mode switches change how a statement tokenizes:
		// NO_BACKSLASH_ESCAPES (is a backslash an escape?) and ANSI_QUOTES (is a
		// double quote a string or an identifier?). Neither is visible to a pure
		// function and a session can carry either, so read the statement every
		// way and let the caller refuse if ANY reading is unsafe.
		foreach ( array( true, false ) as $escapes ) {
			foreach ( array( false, true ) as $ansi ) {
				$out[] = self::normalize_sql( $sql, $escapes, $keep_identifiers, $ansi );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Schemas that are never legitimate targets of this read-only tool.
	 *
	 * mysql.user holds account password hashes, and the metadata schemas expose
	 * the whole server. The protected-table list only covers the WordPress user
	 * tables, so without this a cross-schema reference walked straight past it.
	 *
	 * @var string[]
	 */
	const SYSTEM_SCHEMAS = array( 'mysql', 'information_schema', 'performance_schema', 'sys' );

	/**
	 * Does $sql reference a server system schema?
	 *
	 * Scanned with identifiers KEPT, so `mysql`.`user` is caught as well as the
	 * bare form, and whitespace around the dot is tolerated because keeping an
	 * identifier emits it padded.
	 *
	 * @param string $sql Raw SQL.
	 * @return bool
	 */
	public static function references_system_schema( string $sql ): bool {
		$names = implode( '|', array_map( 'preg_quote', self::SYSTEM_SCHEMAS ) );
		foreach ( self::normalize_variants( $sql, true ) as $variant ) {
			if ( preg_match( '/(?<![a-z0-9_])(' . $names . ')\s*\.\s*[a-z0-9_]/i', $variant ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Apply a server-side statement timeout for the next query, and return a
	 * callable that restores the previous session value.
	 *
	 * MySQL 5.7.8+ uses `max_execution_time` (milliseconds, SELECT only);
	 * MariaDB 10.1.1+ uses `max_statement_time` (seconds, as a double). We try
	 * the matching one and simply do nothing on a server that has neither, so
	 * this can never break a query on an unsupported database.
	 *
	 * @param object $wpdb WordPress database handle.
	 * @return callable Restores the prior session setting.
	 */
	public static function apply_statement_timeout( $wpdb ): callable {
		$noop = static function () {};
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return $noop;
		}
		$seconds = (int) apply_filters( 'emcp_tools_db_query_max_seconds', self::MAX_QUERY_SECONDS );
		$seconds = max( 1, $seconds );
		$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		$restore  = $noop;

		// MariaDB first: it also exposes max_statement_time, and asking for the
		// MySQL-only variable there just returns null.
		$maria = $wpdb->get_var( "SELECT @@SESSION.max_statement_time" );
		if ( null !== $maria ) {
			$prev = (float) $maria;
			$wpdb->query( $wpdb->prepare( 'SET SESSION max_statement_time = %f', (float) $seconds ) );
			$restore = static function () use ( $wpdb, $prev ) {
				$wpdb->query( $wpdb->prepare( 'SET SESSION max_statement_time = %f', $prev ) );
			};
		} else {
			$mysql = $wpdb->get_var( "SELECT @@SESSION.max_execution_time" );
			if ( null !== $mysql ) {
				$prev = (int) $mysql;
				$wpdb->query( $wpdb->prepare( 'SET SESSION max_execution_time = %d', $seconds * 1000 ) );
				$restore = static function () use ( $wpdb, $prev ) {
					$wpdb->query( $wpdb->prepare( 'SET SESSION max_execution_time = %d', $prev ) );
				};
			}
		}
		if ( null !== $suppress && method_exists( $wpdb, 'suppress_errors' ) ) {
			$wpdb->suppress_errors( $suppress );
		}
		return $restore;
	}

	/**
	 * Whether a bounding `LIMIT` can safely be appended to this statement.
	 *
	 * Only for row-producing SELECT/WITH statements that do not already carry a
	 * LIMIT (appending a second one is a syntax error). SHOW/DESCRIBE/EXPLAIN
	 * return small fixed result sets and are left alone. Pure (no DB).
	 *
	 * @param string $sql Raw SQL.
	 * @return bool
	 */
	public static function can_append_limit( string $sql ): bool {
		$norm = trim( self::normalize_sql( $sql ) );
		if ( '' === $norm ) {
			return false;
		}
		// Row-set shape only. A LIMIT elsewhere in the statement (typically in a
		// subquery) does NOT bound the outer result, so it must not stop us from
		// appending an outer bound; trailing_limit() handles the top-level case.
		return (bool) preg_match( '/^(select|with)\b/i', $norm );
	}

	/**
	 * Row count of a TOP-LEVEL trailing LIMIT, or null when the statement has
	 * none. Anchored at the end of the normalized statement, so a LIMIT inside
	 * a subquery (which bounds nothing at the outer level) returns null and the
	 * caller appends its own bound.
	 *
	 * @param string $sql Raw SQL.
	 * @return int|null
	 */
	public static function trailing_limit( string $sql ): ?int {
		$found = array();
		foreach ( self::normalize_variants( $sql ) as $variant ) {
			$found[] = self::trailing_limit_of( rtrim( trim( $variant ), "; \t\r\n" ) );
		}
		// If any reading shows no top-level bound, the statement is unbounded as
		// far as we can prove, so report null and let the caller append one.
		if ( in_array( null, $found, true ) ) {
			return null;
		}
		return max( $found );
	}

	/**
	 * Trailing LIMIT row count of ONE normalized reading.
	 *
	 * @param string $norm Normalized SQL, already right-trimmed.
	 * @return int|null
	 */
	private static function trailing_limit_of( string $norm ): ?int {
		// LIMIT <count> OFFSET <skip>
		if ( preg_match( '/\blimit\s+(\d+)\s+offset\s+\d+$/i', $norm, $m ) ) {
			return (int) $m[1];
		}
		// LIMIT <skip>, <count>
		if ( preg_match( '/\blimit\s+\d+\s*,\s*(\d+)$/i', $norm, $m ) ) {
			return (int) $m[1];
		}
		// LIMIT <count>
		if ( preg_match( '/\blimit\s+(\d+)$/i', $norm, $m ) ) {
			return (int) $m[1];
		}
		return null;
	}

	/**
	 * Return $sql guaranteed to fetch at most $max rows, or a WP_Error when the
	 * caller's own LIMIT exceeds the cap.
	 *
	 * Rewriting someone else's LIMIT in raw SQL is not safe (string literals
	 * make positional edits unreliable), so an oversized LIMIT is REFUSED
	 * rather than silently clamped.
	 *
	 * @param string $sql Raw SQL.
	 * @param int    $max Maximum rows to fetch.
	 * @return string|\WP_Error
	 */
	public static function bound_sql( string $sql, int $max ) {
		$trimmed = rtrim( trim( $sql ), "; \t\r\n" );
		$own     = self::trailing_limit( $sql );
		if ( null !== $own ) {
			if ( $own > $max ) {
				return new \WP_Error(
					'limit_too_large',
					sprintf(
						/* translators: 1: requested LIMIT, 2: maximum allowed. */
						__( 'The query asks for %1$d rows, above the %2$d row cap. Lower the LIMIT.', 'emcp-tools' ),
						$own,
						$max
					)
				);
			}
			return $trimmed; // Already bounded at or below the cap.
		}
		if ( ! self::can_append_limit( $sql ) ) {
			return $trimmed; // SHOW / DESCRIBE / EXPLAIN: inherently small.
		}
		// Newline first: a trailing line comment (-- / #) would otherwise swallow
		// the clause and the query would run unbounded.
		return $trimmed . "\n LIMIT " . (int) $max;
	}

	/**
	 * Validate that $sql is a single read-only statement. Pure (no DB).
	 *
	 * @param string $sql
	 * @return true|\WP_Error
	 */
	public static function is_read_only_sql( string $sql ) {
		// MySQL executes the body of /*! ... */ executable comments, so we cannot
		// safely strip-and-trust. Refuse any SQL containing the marker.
		if ( false !== strpos( $sql, '/*!' ) ) {
			return new \WP_Error( 'executable_comment', __( 'MySQL executable comments (/*! ... */) are not allowed.', 'emcp-tools' ) );
		}
		// Check every reading the server could take. A statement is read-only
		// only if it is read-only under ALL of them.
		foreach ( self::normalize_variants( $sql ) as $variant ) {
			$verdict = self::read_only_check( trim( $variant ) );
			if ( is_wp_error( $verdict ) ) {
				return $verdict;
			}
		}
		return true;
	}

	/**
	 * Read-only checks against ONE already-normalized reading of a statement.
	 *
	 * @param string $norm Normalized SQL.
	 * @return true|\WP_Error
	 */
	private static function read_only_check( string $norm ) {
		if ( '' === $norm ) {
			return new \WP_Error( 'empty_sql', __( 'Empty query.', 'emcp-tools' ) );
		}
		// Multi-statement: any ';' that isn't the sole trailing character.
		$no_trailing = rtrim( $norm, "; \t\r\n" );
		if ( false !== strpos( $no_trailing, ';' ) ) {
			return new \WP_Error( 'multi_statement', __( 'Multiple SQL statements are not allowed.', 'emcp-tools' ) );
		}
		// File-access vectors (comments already normalized to spaces). Note: no
		// trailing \b — the load_file(...) branch ends in '(', and '(' followed
		// by another non-word char has no word boundary, so a trailing \b would
		// (incorrectly) let LOAD_FILE through.
		if ( preg_match( '/\b(into\s+outfile|into\s+dumpfile|load_file\s*\(|load\s+data\b)/i', $norm ) ) {
			return new \WP_Error( 'file_access_blocked', __( 'File-access SQL (OUTFILE/DUMPFILE/LOAD_FILE/LOAD DATA) is not allowed.', 'emcp-tools' ) );
		}
		// First keyword must be read-only. Skip leading parens so a parenthesized
		// UNION branch, which is a valid read, is not refused.
		if ( ! preg_match( '/^([a-z]+)/i', ltrim( $norm, "( 	
" ), $m ) ) {
			return new \WP_Error( 'not_read_only', __( 'Only read-only queries are allowed.', 'emcp-tools' ) );
		}
		$kw      = strtoupper( $m[1] );
		$allowed = array( 'SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'WITH' );
		if ( ! in_array( $kw, $allowed, true ) ) {
			return new \WP_Error(
				'not_read_only',
				/* translators: %s: SQL keyword */
				sprintf( __( 'Only read-only queries are allowed (got %s).', 'emcp-tools' ), $kw )
			);
		}
		// Resource / side-effect functions. A syntactically read-only SELECT can
		// still pin a connection open (SLEEP), burn CPU (BENCHMARK), or take a
		// named lock other requests wait on (GET_LOCK), so a row limit alone does
		// not bound the work. Literals and comments are already normalized away,
		// so these match real function tokens rather than text inside strings.
		if ( preg_match( '/\b(sleep|benchmark|get_lock|release_lock|release_all_locks|is_free_lock|is_used_lock|master_pos_wait|source_pos_wait|wait_for_executed_gtid_set|sys_exec|lo_import|lo_export)\s*\(/i', $norm ) ) {
			return new \WP_Error( 'unsafe_function_blocked', __( 'Delay, lock, and other resource-consuming SQL functions are not allowed.', 'emcp-tools' ) );
		}
		// Whole-statement write/DDL denylist (literals/comments already stripped,
		// so these match only real keyword tokens, not strings or identifiers).
		if ( preg_match( '/\b(INSERT|UPDATE|DELETE|REPLACE|MERGE|DROP|TRUNCATE|ALTER|CREATE|RENAME|GRANT|REVOKE|HANDLER|CALL|LOCK|UNLOCK|PREPARE|EXECUTE|INTO)\b/i', $norm ) ) {
			return new \WP_Error( 'not_read_only', __( 'The query contains a write or unsafe keyword.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Resolve a table name against the live table list (table names cannot be
	 * parameterized). Returns the exact real name, or WP_Error.
	 *
	 * @param string $table
	 * @return string|\WP_Error
	 */
	public static function valid_table( string $table ) {
		global $wpdb;
		$table = trim( $table );
		if ( '' === $table ) {
			return new \WP_Error( 'unknown_table', __( 'A table name is required.', 'emcp-tools' ) );
		}
		$tables = (array) $wpdb->get_col( 'SHOW TABLES' );
		foreach ( $tables as $t ) {
			if ( strtolower( (string) $t ) === strtolower( $table ) ) {
				return (string) $t;
			}
		}
		return new \WP_Error( 'unknown_table', __( 'Unknown table.', 'emcp-tools' ) );
	}

	/**
	 * Pure: is $table in the protected list (case-insensitive)?
	 *
	 * @param string   $table
	 * @param string[] $protected
	 * @return bool
	 */
	public static function table_is_protected( string $table, array $protected ): bool {
		$t = strtolower( $table );
		foreach ( $protected as $p ) {
			if ( strtolower( (string) $p ) === $t ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether writes to $table are refused (users/usermeta by default).
	 *
	 * @param string $table
	 * @return bool
	 */
	public static function is_protected( string $table ): bool {
		global $wpdb;
		$protected = apply_filters( 'emcp_tools_db_protected_tables', array( $wpdb->users, $wpdb->usermeta ) );
		return self::table_is_protected( $table, (array) $protected );
	}

	/**
	 * Pure: does a read-only $sql reference any of $tables as a real identifier?
	 *
	 * Comments and string literals are stripped (so `'wp_users'` in a literal is
	 * not a reference), backticks are removed (so `` `wp_users` `` still matches),
	 * and each table is matched on word boundaries (so `wp_users_backup` does not
	 * match `wp_users`). This is the read-path counterpart to is_protected() —
	 * the `query` tool refuses any read that touches the protected user tables.
	 *
	 * @param string   $sql
	 * @param string[] $tables Real table names (e.g. {$wpdb->users}).
	 * @return bool
	 */
	public static function query_touches_tables( string $sql, array $tables ): bool {
		// Ask the lexer to KEEP identifier names rather than stripping backticks
		// beforehand: pre-stripping fed the contents of a quoted identifier back
		// through the lexer, so an alias containing #, a quote, or "-- " opened a
		// comment or string and hid the protected table that followed it.
		// A reference that appears under ANY plausible reading counts as a touch.
		$scans = array();
		foreach ( self::normalize_variants( $sql, true ) as $variant ) {
			$scans[] = strtolower( $variant );
		}
		foreach ( $tables as $t ) {
			$t = strtolower( trim( (string) $t ) );
			if ( '' === $t ) {
				continue;
			}
			foreach ( $scans as $scan ) {
				if ( preg_match( '/(?<![a-z0-9_])' . preg_quote( $t, '/' ) . '(?![a-z0-9_])/', $scan ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether a read-only $sql touches a protected table (users/usermeta by
	 * default; filter via emcp_tools_db_protected_tables).
	 *
	 * @param string $sql
	 * @return bool
	 */
	public static function query_touches_protected( string $sql ): bool {
		global $wpdb;
		$protected = apply_filters( 'emcp_tools_db_protected_tables', array( $wpdb->users, $wpdb->usermeta ) );
		return self::query_touches_tables( $sql, (array) $protected );
	}

	/**
	 * Capture the rows an equality-AND WHERE will affect, before update/delete.
	 *
	 * @param string $table A validated real table name.
	 * @param array  $where col => value (equality AND).
	 * @return array
	 */
	public static function before_image( string $table, array $where ): array {
		global $wpdb;
		if ( empty( $where ) ) {
			return array();
		}
		$cond = array();
		$vals = array();
		foreach ( $where as $col => $val ) {
			$cond[] = '`' . str_replace( '`', '', (string) $col ) . '` = %s';
			$vals[] = $val;
		}
		$sql  = "SELECT * FROM `" . str_replace( '`', '', $table ) . "` WHERE " . implode( ' AND ', $cond ) . ' LIMIT ' . (int) self::BEFORE_IMAGE_CAP;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Deprecated: DB writes are now recorded in the unified change ledger
	 * (EMCP_Tools_Change_Log) via EMCP_Tools_Change_Recorder, which is the single
	 * audit + rollback source. Kept as a no-op for backward compatibility.
	 *
	 * @deprecated 3.10.0
	 */
	public static function log(): void {}
}
