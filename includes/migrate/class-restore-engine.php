<?php
/**
 * Restore Engine — unpack a .emcp archive back onto the site.
 *
 * Order of operations (mirrors the upstream port's live-tested sequence):
 *   1. verify the manifest parses and database.sql matches its sha256 — a
 *      truncated or hand-edited archive is refused before anything runs
 *   2. import database.sql through EMCP_Tools_DB_Importer (which skips session
 *      transaction-control directives so the shared connection never rolls the
 *      whole restore back at request end)
 *   3. optionally search-replace the source site_url for this site's URL using
 *      the byte-accurate serialized/JSON engine (this is what rewrites
 *      _elementor_data on a migrated site)
 *   4. optionally place files/ back under wp-content (validated against path
 *      traversal; never writes into the backups dir or wp-config.php)
 *
 * Never touches wp-config.php or salts. Runs detached from the client when used
 * from the connector (ignore_user_abort is set by the caller).
 *
 * @package EMCP_Tools
 * @since   3.16.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Restore engine.
 *
 * @since 3.16.0
 */
class EMCP_Tools_Restore_Engine {

    const LOG_OPTION = 'emcp_migrate_restore_log';
    const LOG_CAP    = 20;

    /**
     * Restore one .emcp archive.
     *
     * @param string $filename Archive filename (basename enforced by Packager).
     * @param array  $opts {
     *     @type bool   $import_db      Import database.sql (default true).
     *     @type bool   $place_files    Place files/ entries (default true).
     *     @type bool   $search_replace Apply URL search-replace (default true; only when URLs differ).
     *     @type string $new_url        Destination URL (default home_url()).
     * }
     * @return array|\WP_Error Stats, or a WP_Error when verification fails.
     */
    public static function restore( string $filename, array $opts = array() ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'zip_missing', __( 'The PHP ZipArchive extension is required to restore an archive.', 'emcp-tools' ) );
        }
        if ( ! class_exists( 'EMCP_Tools_Packager' ) || ! class_exists( 'EMCP_Tools_DB_Importer' ) ) {
            return new WP_Error( 'engine_unavailable', __( 'The restore engine classes are not available.', 'emcp-tools' ) );
        }

        $import_db   = ! array_key_exists( 'import_db', $opts ) || ! empty( $opts['import_db'] );
        $place_files = ! array_key_exists( 'place_files', $opts ) || ! empty( $opts['place_files'] );

        $path = EMCP_Tools_Packager::archive_path( $filename );
        if ( '' === $path ) {
            return new WP_Error( 'archive_missing', __( 'Archive not found.', 'emcp-tools' ) );
        }
        $manifest = EMCP_Tools_Packager::read_manifest( $filename );
        if ( null === $manifest || empty( $manifest['emcp'] ) ) {
            return new WP_Error( 'invalid_archive', __( 'Not a valid .emcp archive (manifest missing or unreadable).', 'emcp-tools' ) );
        }
        if ( isset( $manifest['format_version'] ) && (int) $manifest['format_version'] > EMCP_Tools_Packager::FORMAT_VERSION ) {
            return new WP_Error( 'newer_format', __( 'This archive uses a newer format than this plugin can restore.', 'emcp-tools' ) );
        }

        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( 0 ); // phpcs:ignore WordPress.PHP -- restore is an admin-authorized batch op.
        }

        $stats = array(
            'action'         => 'restore',
            'filename'       => $filename,
            'files_placed'   => 0,
            'search_replace' => null,
            'errors'         => array(),
        );

        $workdir = EMCP_Tools_Packager::backup_dir() . '/.restore-' . wp_generate_password( 8, false );
        wp_mkdir_p( $workdir );
        $zip = new ZipArchive();
        if ( true !== $zip->open( $path ) ) {
            $stats['errors'][] = 'zip_open_failed';
            self::append_log( $stats );
            return $stats;
        }

        try {
            if ( $import_db && $zip->locateName( 'database.sql' ) !== false ) {
                $abort = self::restore_database( $zip, $workdir, $manifest, $opts, $stats );
                if ( $abort ) {
                    self::append_log( $stats );
                    // Cleanup happens once in the finally block below.
                    return new WP_Error( 'hash_mismatch', __( 'Archive failed its integrity check (database.sql does not match the manifest hash). Nothing was imported.', 'emcp-tools' ) );
                }
            }

            // Place files/ entries, validated against path traversal.
            if ( $place_files ) {
                $stats['files_placed'] = self::place_files( $zip );
            }
        } finally {
            $zip->close();
            self::cleanup( $workdir );
        }

        self::append_log( $stats );
        return $stats;
    }

    /**
     * Stream database.sql out of the archive, verify its hash, import it, and
     * rewrite the source URL for this site.
     *
     * @param \ZipArchive $zip      Open archive.
     * @param string      $workdir  Scratch directory.
     * @param array       $manifest Parsed manifest.
     * @param array       $opts     Restore options (search_replace, new_url).
     * @param array       $stats    In/out stats.
     * @return bool True when the DB failed integrity and the whole restore must abort.
     */
    private static function restore_database( ZipArchive $zip, string $workdir, array $manifest, array $opts, array &$stats ): bool {
        $sr_on = ! array_key_exists( 'search_replace', $opts ) || ! empty( $opts['search_replace'] );
        // Capture the destination URL before the import replaces wp_options —
        // do not rely on the in-memory option cache surviving the restore.
        $dest_url = (string) ( $opts['new_url'] ?? home_url() );

        $db_file = $workdir . '/database.sql';
        $src     = $zip->getStream( 'database.sql' );
        if ( false === $src ) {
            $stats['errors'][] = 'db_stream_failed';
            return false;
        }
        $dst = fopen( $db_file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( false === $dst ) {
            fclose( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions
            $stats['errors'][] = 'db_stream_failed';
            return false;
        }
        $copied = stream_copy_to_stream( $src, $dst );
        fclose( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        fclose( $dst ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( false === $copied ) {
            $stats['errors'][] = 'db_stream_failed';
            return false; // Extraction failed — never import a truncated dump.
        }

        $expected = isset( $manifest['database_sha256'] ) ? $manifest['database_sha256'] : '';
        $actual   = hash_file( 'sha256', $db_file );
        if ( '' !== $expected && ! hash_equals( $expected, $actual ) ) {
            $stats['errors'][] = 'hash_mismatch';
            return true; // Nothing was imported — abort the whole restore.
        }

        $import      = EMCP_Tools_DB_Importer::import_from_file( $db_file );
        $stats['db'] = ( false === $import ) ? array( 'statements' => 0, 'executed' => 0, 'skipped' => 0, 'errors' => 1 ) : $import;
        // Statement errors are reported under $stats['db'] but are NOT fatal: a
        // partial import still gets the URL rewrite below.

        // URL rewrite (source → this site) when the URLs differ. For a scoped
        // (selective-DB) archive, rewrite only the archived tables — a selective
        // restore replaces exactly those tables, so destination-native tables
        // must not be walked.
        if ( $sr_on && class_exists( 'EMCP_Tools_Serialized_Search_Replace' ) ) {
            $old_url = isset( $manifest['site_url'] ) ? (string) $manifest['site_url'] : '';
            if ( '' !== $old_url && $old_url !== $dest_url ) {
                global $wpdb;
                $engine = 'EMCP_Tools_Serialized_Search_Replace';
                $tables = $engine::data_tables( $wpdb );
                if ( isset( $manifest['scope']['db'] ) && is_array( $manifest['scope']['db'] ) ) {
                    $tables = array_values( array_intersect( $tables, array_map( 'strval', $manifest['scope']['db'] ) ) );
                }
                $total = 0;
                foreach ( $tables as $table ) {
                    $r = $engine::walk_table( $wpdb, $table, $old_url, $dest_url );
                    $total += (int) $r['affected'];
                }
                $stats['search_replace'] = array( 'old' => $old_url, 'new' => $dest_url, 'rows' => $total );
            }
        }
        return false;
    }

    /**
     * Extract files/ entries from the archive into wp-content, safely.
     *
     * @param \ZipArchive $zip Open archive.
     * @return int Number of files placed.
     */
    private static function place_files( ZipArchive $zip ): int {
        $content_dir = wp_normalize_path( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' );
        $backups_dir = wp_normalize_path( EMCP_Tools_Packager::backup_dir() );
        $placed      = 0;

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = (string) $zip->getNameIndex( $i );
            if ( 0 !== strpos( $name, 'files/' ) ) {
                continue;
            }
            $rel = substr( $name, strlen( 'files/' ) );
            if ( '' === $rel || 0 === strpos( $rel, 'wp-config.php' ) || false !== strpos( $rel, '..' ) ) {
                continue; // Never outside wp-content, never wp-config.
            }
            $dest = wp_normalize_path( $content_dir . '/' . $rel );
            if ( 0 !== strpos( $dest, $content_dir . '/' ) || 0 === strpos( $dest, $backups_dir . '/' ) || is_dir( $dest ) ) {
                continue;
            }
            $dir = dirname( $dest );
            if ( ! is_dir( $dir ) ) {
                wp_mkdir_p( $dir );
            }
            $src = $zip->getStream( $name );
            $dst = @fopen( $dest, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors -- direct file placement; caller audits.
            if ( false === $src || false === $dst ) {
                if ( is_resource( $src ) ) {
                    fclose( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions
                }
                continue;
            }
            stream_copy_to_stream( $src, $dst );
            fclose( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions
            fclose( $dst ); // phpcs:ignore WordPress.WP.AlternativeFunctions
            $placed++;
        }
        return $placed;
    }

    /**
     * Append a restore result to the capped log option.
     *
     * @param string $filename Archive name.
     * @param array  $entry    Result entry.
     */
    private static function append_log( array $entry ): void {
        $entry['time'] = gmdate( 'c' );
        $log           = (array) get_option( self::LOG_OPTION, array() );
        array_unshift( $log, $entry );
        $log = array_slice( $log, 0, self::LOG_CAP );
        update_option( self::LOG_OPTION, $log, false );
    }

    /**
     * Remove a temp work directory.
     *
     * @param string $dir Work directory.
     */
    private static function cleanup( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $it as $f ) {
            if ( $f->isDir() ) {
                @rmdir( $f->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
            } else {
                @unlink( $f->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
            }
        }
        @rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
    }

    /**
     * Read the capped restore log.
     *
     * @return array[]
     */
    public static function get_log(): array {
        return (array) get_option( self::LOG_OPTION, array() );
    }
}
