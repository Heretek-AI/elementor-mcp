<?php
/**
 * Byte-accurate serialized / JSON search-replace engine.
 *
 * The legacy EMCP_Tools_Search_Replace used @unserialize() + serialize() to
 * rewrite serialized blobs. That fatals on __PHP_Incomplete_Class when a
 * serialized object's class is absent on the target (a live lesson from the
 * Backup/Restore port), and it mangled the escaped `https:\/\/` URL form that
 * Elementor's JSON-encoded _elementor_data uses (decoding then replacing
 * un-escaped the slashes and the pair never matched).
 *
 * This engine never instantiates a class:
 *  - PHP-serialized values are rewritten token-by-token with a length DELTA —
 *    the quoted representation is replaced in place and the s:LEN prefix is
 *    adjusted by (replace_len - search_len) per replaced occurrence, so escape
 *    sequences that are NOT part of the search are preserved byte-for-byte.
 *  - Everything else (raw JSON such as _elementor_data, plain text) goes
 *    through a raw pair replace over the escaped + unescaped URL variants
 *    (no length prefixes exist in JSON, so a raw str_replace is safe).
 *
 * @package EMCP_Tools
 * @since   3.16.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serialized / JSON search & replace.
 *
 * @since 3.16.0
 */
class EMCP_Tools_Serialized_Search_Replace {

    /**
     * Replace an escaped (`\/`) plus an unescaped URL occurrence in a raw
     * string value (JSON or plain text). No length prefixes to fix in JSON, so
     * a raw str_replace over both variants is byte-safe here.
     *
     * The escaped variant is replaced first so the unescaped pass never
     * re-touches the slashes the escaped pass just produced.
     *
     * @param string $value   Raw value.
     * @param string $search  Old URL (unescaped).
     * @param string $replace New URL (unescaped).
     * @return string
     */
    public static function pair_replace( string $value, string $search, string $replace ): string {
        if ( '' === $search || '' === $value || $search === $replace ) {
            return $value;
        }
        $search_esc  = str_replace( '/', '\\/', $search );
        $replace_esc = str_replace( '/', '\\/', $replace );
        if ( false !== strpos( $value, $search_esc ) ) {
            $value = str_replace( $search_esc, $replace_esc, $value );
        }
        if ( false !== strpos( $value, $search ) ) {
            $value = str_replace( $search, $replace, $value );
        }
        return $value;
    }

    /**
     * Rewrite every PHP-serialized string token whose quoted value contains the
     * search, adjusting only its s:LEN prefix. Never unserializes, so a serialized
     * object whose class is missing on the target can never fatal.
     *
     * A URL swap adds or removes no `"` or `\` bytes, so each replaced occurrence
     * changes the logical length — and therefore the s:LEN prefix — by exactly
     * ( strlen( $replace ) - strlen( $search ) ). Escape sequences elsewhere in a
     * token are preserved verbatim and never recounted.
     *
     * @param string $value   A value that is_serialized() already accepted.
     * @param string $search  Old URL.
     * @param string $replace New URL.
     * @return string Rewritten serialized value (still is_serialized()).
     */
    public static function fix_serialized_strings( string $value, string $search, string $replace ): string {
        if ( '' === $search || $search === $replace ) {
            return $value;
        }
        $delta = strlen( $replace ) - strlen( $search );

        // Match s:LEN:"..." string tokens. `\\.` consumes an escaped quote or
        // backslash so the closing quote is only the real delimiter.
        return preg_replace_callback(
            '/s:(\d+):("((?:[^"\\\\]|\\\\.)*)")/',
            static function ( array $m ) use ( $search, $replace, $delta ): string {
                $inner = $m[3]; // Quoted representation between the delimiters.
                if ( false === strpos( $inner, $search ) ) {
                    return $m[0]; // No occurrence — leave the token byte-identical.
                }
                $count = substr_count( $inner, $search );
                $new   = str_replace( $search, $replace, $inner );
                return 's:' . ( (int) $m[1] + ( $count * $delta ) ) . ':"' . $new . '"';
            },
            $value
        );
    }

    /**
     * Recursive replace over a DB value (string, array, or scalar).
     *
     *  - string  -> serialized? fix_serialized_strings() : pair_replace()
     *  - array   -> recurse into keys and values
     *  - object  -> returned untouched (never touched: a live object here would
     *               already be an __PHP_Incomplete_Class we must not walk)
     *
     * @param mixed  $data    Value from the database.
     * @param string $search  Old URL.
     * @param string $replace New URL.
     * @return mixed
     */
    public static function replace( $data, string $search, string $replace ) {
        if ( is_string( $data ) ) {
            if ( '' === $data ) {
                return $data;
            }
            if ( function_exists( 'is_serialized' ) && is_serialized( $data ) ) {
                return self::fix_serialized_strings( $data, $search, $replace );
            }
            return self::pair_replace( $data, $search, $replace );
        }

        if ( is_array( $data ) ) {
            $out = array();
            foreach ( $data as $k => $v ) {
                $out[ self::replace( (string) $k, $search, $replace ) ] = self::replace( $v, $search, $replace );
            }
            return $out;
        }

        return $data;
    }

    /**
     * Text-bearing tables the site-wide search-replace should walk.
     *
     * All `{prefix}` tables, minus internal/operational tables whose values are
     * paths, keys, or session data that must never be URL-rewritten. Each walk
     * only touches rows that actually contain $search, so over-approximating is
     * cheap; the exclusions are the tables where a match would be a false hit.
     *
     * @param \wpdb $wpdb WordPress database.
     * @return string[] Table names.
     */
    public static function data_tables( $wpdb ): array {
        $exclude = array(
            $wpdb->prefix . 'emcp_redirects',
            $wpdb->prefix . 'emcp_migrate_targets',
            $wpdb->prefix . 'emcp_migrate_backups',
            $wpdb->prefix . 'emcp_migrate_jobs',
            $wpdb->prefix . 'emcp_search_index',
            $wpdb->prefix . 'woocommerce_sessions',
            $wpdb->prefix . 'actionscheduler_actions',
            $wpdb->prefix . 'actionscheduler_logs',
            $wpdb->prefix . 'actionscheduler_groups',
            $wpdb->prefix . 'actionscheduler_claims',
            $wpdb->options, // Walked explicitly first below.
        );
        $tables = array( $wpdb->options, $wpdb->posts, $wpdb->postmeta, $wpdb->comments, $wpdb->commentmeta, $wpdb->termmeta, $wpdb->usermeta );
        if ( $wpdb->links ) {
            $tables[] = $wpdb->links;
        }
        $rows = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB -- identifier enumeration.
        foreach ( $rows as $table ) {
            if ( 0 !== strpos( $table, $wpdb->prefix ) || in_array( $table, $exclude, true ) || in_array( $table, $tables, true ) ) {
                continue;
            }
            $tables[] = $table;
        }
        return $tables;
    }

    /**
     * Single-column primary key for a table, or '' when the table has none /
     * a composite key (those are skipped — a row cannot be addressed safely).
     *
     * @param \wpdb  $wpdb  WordPress database.
     * @param string $table Table name.
     * @return string Column name, or ''.
     */
    public static function primary_key( $wpdb, string $table ): string {
        $rows = $wpdb->get_results( "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'" ); // phpcs:ignore WordPress.DB -- identifier quoted, admin-audited path.
        if ( count( $rows ) !== 1 ) {
            return '';
        }
        return (string) $rows[0]->Column_name;
    }

    /**
     * Walk one table and rewrite every text row containing $search.
     *
     * Only char/text/json columns are considered; only rows that contain the
     * search are fetched; only columns that actually change are written back.
     * Returns the rows touched (their full before-image) so the caller can hand
     * them to the change ledger for History rollback.
     *
     * @param \wpdb   $wpdb         WordPress database.
     * @param string  $table        Table name.
     * @param string  $search       Old URL.
     * @param string  $replace      New URL.
     * @param int     $max_rows     Optional hard cap on rows walked per table (0 = unlimited).
     * @param int     $before_cap   Max before-images returned (for the ledger); further rows are still rewritten but flagged partial.
     * @return array  { table, pk, affected, columns, partial, before_rows }
     */
    public static function walk_table( $wpdb, string $table, string $search, string $replace, int $max_rows = 0, int $before_cap = 200 ): array {
        $result = array(
            'table'       => $table,
            'pk'          => '',
            'affected'    => 0,
            'columns'     => array(),
            'partial'     => false,
            'before_rows' => array(),
        );
        if ( '' === $search || '' === $table || $search === $replace ) {
            return $result;
        }

        // Text columns only.
        $columns = array();
        $describe = $wpdb->get_results( "DESCRIBE `{$table}`" ); // phpcs:ignore WordPress.DB -- identifier quoted.
        foreach ( (array) $describe as $col ) {
            $type = strtolower( (string) $col->Type );
            if ( false !== strpos( $type, 'char' ) || false !== strpos( $type, 'text' ) || false !== strpos( $type, 'json' ) ) {
                $columns[] = $col->Field;
            }
        }
        if ( empty( $columns ) ) {
            return $result;
        }
        $result['columns'] = $columns;

        $pk = self::primary_key( $wpdb, $table );
        if ( '' === $pk ) {
            $result['partial'] = true; // Composite/no-PK: cannot update rows safely.
            return $result;
        }
        $result['pk'] = $pk;

        $like        = '%' . $wpdb->esc_like( $search ) . '%';
        $where_parts = array();
        $where_args  = array();
        foreach ( $columns as $col ) {
            $where_parts[] = "`{$col}` LIKE %s";
            $where_args[]  = $like;
        }
        $where_sql = implode( ' OR ', $where_parts );
        $limit     = ( $max_rows > 0 ) ? $max_rows : 2000;
        $fetch     = $limit + 1; // +1 detects that more matching rows remain.
        $args      = array_merge( $where_args, array( $fetch ) );
        $sql       = "SELECT `{$pk}`, `" . implode( '`, `', $columns ) . "` FROM `{$table}` WHERE ({$where_sql}) LIMIT %d";

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB -- prepared; identifier-quoted above.
        if ( null === $rows ) {
            $result['partial'] = true;
            return $result;
        }
        if ( count( $rows ) > $limit ) {
            $result['partial'] = true;
            $rows              = array_slice( $rows, 0, $limit );
        }

        foreach ( $rows as $row ) {
            $changes = array();
            foreach ( $columns as $col ) {
                if ( ! array_key_exists( $col, $row ) || ! is_string( $row[ $col ] ) ) {
                    continue;
                }
                $old = $row[ $col ];
                if ( false === strpos( $old, $search ) ) {
                    continue;
                }
                $new = self::replace( $old, $search, $replace );
                if ( $new !== $old ) {
                    $changes[ $col ] = $new;
                }
            }
            if ( empty( $changes ) ) {
                continue;
            }
            $wpdb->update( $table, $changes, array( $pk => $row[ $pk ] ) ); // phpcs:ignore WordPress.DB -- keyed by PK.
            $result['affected']++;
            // before_cap === 0 means the caller wants no before-images (e.g. no
            // change ledger available); that is not truncation, so partial stays
            // untouched. Partial is reserved for real row/key/query truncation.
            if ( $before_cap > 0 ) {
                if ( count( $result['before_rows'] ) < $before_cap ) {
                    $result['before_rows'][] = $row;
                } else {
                    $result['partial'] = true;
                }
            }
        }
        return $result;
    }
}
