<?php
/**
 * Packager — creates, lists, and inspects portable .emcp zip archives.
 *
 * A .emcp is a ZipArchive containing:
 *   database.sql    — portable SQL dump (EMCP_Tools_DB_Exporter)
 *   files/...       — optional site files (uploads/plugins/themes), opt-in
 *   manifest.json   — format version, source URL, DB hash + row stats, file count
 *
 * Restore (EMCP_Tools_Restore_Engine) verifies the manifest hash before it
 * imports anything, so a truncated or hand-edited archive is refused.
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Packager {

	const FORMAT_VERSION = 2;

	/** Portable archive extension. */
	const EXT = '.emcp';

	/**
	 * Absolute directory .emcp archives live in (uploads/emcp-backups).
	 *
	 * @return string
	 */
	public static function backup_dir(): string {
		$upload = wp_upload_dir();
		$dir    = $upload['basedir'] . '/emcp-backups';
		wp_mkdir_p( $dir );
		self::write_guard_files( $dir );
		return $dir;
	}

	/**
	 * Drop web-denial guards into the backups dir so .emcp archives (full DB
	 * dumps) are not servable by guessing a filename.
	 *
	 * Note: .htaccess only shields Apache; nginx/other hosts must protect the
	 * dir themselves. Downloads always go through the nonce'd admin handler.
	 *
	 * @param string $dir Backups directory.
	 */
	private static function write_guard_files( string $dir ): void {
		$htaccess = $dir . '/.htaccess';
		if ( ! is_file( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors -- guard file in the plugin's own dir.
			@file_put_contents( $htaccess, "# Deny web access to .emcp site archives (database dumps).\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" );
		}
		if ( ! is_file( $dir . '/index.html' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			@file_put_contents( $dir . '/index.html', '' );
		}
	}

	/**
	 * Resolve an archive name to a safe absolute path inside the backups dir.
	 *
	 * @param string $name Filename (basename enforced).
	 * @return string Absolute path, or '' for an invalid name.
	 */
	public static function archive_path( string $name ): string {
		$name = sanitize_file_name( $name );
		if ( '' === $name || self::EXT !== substr( $name, -5 ) || false !== strpos( $name, '/' ) || false !== strpos( $name, '..' ) ) {
			return '';
		}
		$path = wp_normalize_path( self::backup_dir() . '/' . $name );
		if ( is_file( $path ) ) {
			return $path;
		}
		return '';
	}

	/**
	 * Create a .emcp archive: DB dump + manifest, plus optional site files.
	 *
	 * Scope (back-compat: omitting every scope option keeps the current full
	 * behaviour — full DB dump + optional full file set — and readers treat an
	 * archive without a `scope` manifest key as full).
	 *
	 * @param string $name Optional archive name ('.emcp' appended if missing).
	 * @param array  $opts {
	 *     @type string|array  $kind         'backup' (default) or 'sync' (tag only).
	 *     @type string|array  $db           'all' (default), 'none', or an array of
	 *                                       table names to dump (empty array = 'none').
	 *     @type bool          $include_files Include uploads/plugins/themes (legacy;
	 *                                        equivalent to file_roots = 'all').
	 *     @type string|array  $file_roots    'all' | 'none' | an array of absolute dirs
	 *                                        (or a token => absolute-dir map) to bundle
	 *                                        under files/.
	 * }
	 * @return string|false Absolute archive path, or false on failure.
	 */
	public static function create_archive( string $name = '', array $opts = array() ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$kind = ( 'sync' === ( $opts['kind'] ?? '' ) ) ? 'sync' : 'backup';

		// DB scope: 'all' | 'none' | table allowlist.
		$tables   = null; // null = full dump.
		$scope_db = 'all';
		if ( array_key_exists( 'db', $opts ) ) {
			$db = $opts['db'];
			if ( is_array( $db ) ) {
				$tables = array_values( array_unique( array_filter( array_map( 'strval', $db ) ) ) );
				if ( $tables ) {
					$scope_db = $tables;
				} else {
					$tables   = null; // No tables chosen — treated as none.
					$scope_db = 'none';
				}
			} elseif ( 'none' === $db ) {
				$scope_db = 'none';
			}
		}

		// File scope: 'all' | 'none' | token => absolute-dir map.
		$file_roots = null; // null = no files.
		if ( array_key_exists( 'file_roots', $opts ) ) {
			$file_roots = $opts['file_roots'];
		} elseif ( ! empty( $opts['include_files'] ) ) {
			$file_roots = 'all'; // Legacy flag ≈ full file set.
		}

		$want_files = false;
		$scope_files = 'none';
		$roots_map   = array();
		if ( 'all' === $file_roots ) {
			$want_files = true;
			$scope_files = 'all';
		} elseif ( is_array( $file_roots ) && $file_roots ) {
			$want_files = true;
			foreach ( $file_roots as $key => $path ) {
				if ( ! is_string( $path ) || '' === $path ) {
					continue;
				}
				$roots_map[ is_int( $key ) ? self::root_key( $path ) : (string) $key ] = $path;
			}
			$scope_files = array_keys( $roots_map );
		}

		$dir  = self::backup_dir();
		$name = sanitize_file_name( $name );
		if ( '' === $name ) {
			// Random suffix so the filename is not date-guessable on the web.
			$name = ( 'sync' === $kind ? 'sync-' : 'backup-' ) . gmdate( 'Y-m-d-His' ) . '-' . wp_generate_password( 6, false );
		}
		if ( self::EXT !== substr( $name, -5 ) ) {
			$name .= self::EXT;
		}
		$zip_file = $dir . '/' . $name;
		$tmp_sql  = $dir . '/.tmp-' . wp_generate_password( 8, false ) . '.sql';

		$has_db   = ( 'none' !== $scope_db );
		$db_stats = null;
		if ( $has_db ) {
			$db_stats = is_array( $tables )
				? EMCP_Tools_DB_Exporter::export_to_file( $tmp_sql, $tables )
				: EMCP_Tools_DB_Exporter::export_to_file( $tmp_sql );
			if ( false === $db_stats || ! is_file( $tmp_sql ) ) {
				@unlink( $tmp_sql ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- local temp cleanup.
				return false;
			}
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			if ( $has_db ) {
				@unlink( $tmp_sql ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
			return false;
		}

		$ok = true;
		if ( $has_db ) {
			$ok = ( true === $zip->addFile( $tmp_sql, 'database.sql' ) );
		}

		$file_entries = array();
		if ( $ok && $want_files ) {
			$file_entries = self::add_files( $zip, $roots_map );
			$ok           = is_array( $file_entries ); // add_files returns false on a failed addFile.
		}

		$manifest = array(
			'emcp'           => true,
			'format_version' => self::FORMAT_VERSION,
			'version'        => defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '0',
			'kind'           => $kind,
			'scope'          => array(
				'db'    => $scope_db,
				'files' => $scope_files,
			),
			'created_at'     => gmdate( 'c' ),
			'site_url'       => home_url(),
			'siteurl_option' => get_option( 'siteurl', home_url() ),
			'php'            => PHP_VERSION,
			'wp'             => isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '',
			'db'             => self::db_version(),
		);
		if ( $has_db ) {
			$manifest['db_stats'] = array(
				'tables'    => isset( $db_stats['tables'] ) ? $db_stats['tables'] : 0,
				'rows'      => isset( $db_stats['rows'] ) ? $db_stats['rows'] : 0,
				'per_table' => isset( $db_stats['per_table'] ) ? $db_stats['per_table'] : array(),
				'partial'   => ! empty( $db_stats['partial'] ),
			);
			$manifest['database_sha256'] = hash_file( 'sha256', $tmp_sql );
		}
		$manifest['files']      = count( $file_entries );
		$manifest['file_roots'] = $file_entries;

		if ( $ok ) {
			$ok = ( false !== $zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) );
		}
		$closed = $zip->close();

		if ( $has_db ) {
			@unlink( $tmp_sql ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! $ok || true !== $closed ) {
			@unlink( $zip_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- remove the partial archive.
			return false;
		}
		return is_file( $zip_file ) ? $zip_file : false;
	}

	/**
	 * Read + parse the manifest inside an archive.
	 *
	 * @param string $name Archive filename.
	 * @return array|null Manifest, or null when unreadable/invalid.
	 */
	public static function read_manifest( string $name ) {
		$path = self::archive_path( $name );
		if ( '' === $path ) {
			return null;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return null;
		}
		$json = $zip->getFromName( 'manifest.json' );
		$zip->close();
		if ( false === $json ) {
			return null;
		}
		$manifest = json_decode( $json, true );
		return is_array( $manifest ) ? $manifest : null;
	}

	/**
	 * Delete an archive by filename. Admin-only callers.
	 *
	 * @param string $name Archive filename.
	 * @return bool
	 */
	public static function delete_archive( string $name ): bool {
		$path = self::archive_path( $name );
		if ( '' === $path ) {
			return false;
		}
		return @unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- file deletion.
	}

	/**
	 * List existing archives with metadata.
	 *
	 * @return array[]
	 */
	public static function list_archives(): array {
		$dir   = self::backup_dir();
		$files = glob( $dir . '/*' . self::EXT ) ?: array(); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$out   = array();
		foreach ( $files as $f ) {
			$size = filesize( $f );
			$out[] = array(
				'filename'   => basename( $f ),
				'path'       => $f,
				'size'       => size_format( $size ),
				'size_bytes' => (int) $size,
				'date'       => gmdate( 'Y-m-d H:i:s', filemtime( $f ) ),
				// No sha256 here: hashing every archive reads its full contents on
				// a plain listing. Integrity lives in the manifest hash checked at
				// restore time (and in the per-archive hash when one is requested).
			);
		}
		usort( $out, static function ( $a, $b ) {
			return strcmp( $b['filename'], $a['filename'] ); // Newest (lexical timestamp) first.
		} );
		return $out;
	}

	/**
	 * Add site files to the open archive under files/.
	 *
	 * With no roots given (or an empty map) the canonical three roots are walked
	 * (uploads/plugins/themes — the legacy full set). A keyed token => absolute-dir
	 * map limits the walk to exactly those roots (selective sync). Paths are
	 * normalized before the WP_CONTENT_DIR prefix is stripped (a Windows host
	 * writes backslash paths from RecursiveDirectoryIterator, which would defeat
	 * the prefix match otherwise). Never descends into the plugin itself, the
	 * backups dir, the sandbox, or caches.
	 *
	 * @param \ZipArchive $zip   Open archive.
	 * @param array       $roots Token => absolute dir map (empty = the three roots).
	 * @return string[]|false Root → file-count map, or false when an addFile fails.
	 */
	private static function add_files( ZipArchive $zip, array $roots = array() ) {
		$content_dir = wp_normalize_path( ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) );
		if ( empty( $roots ) ) {
			$roots = array(
				'uploads' => self::uploads_basedir(),
				'plugins' => wp_normalize_path( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : $content_dir . '/plugins' ),
				'themes'  => wp_normalize_path( get_theme_root() ? get_theme_root() : $content_dir . '/themes' ),
			);
		}
		$skip_dirs = array(
			wp_normalize_path( self::backup_dir() ),
			wp_normalize_path( defined( 'EMCP_TOOLS_DIR' ) ? EMCP_TOOLS_DIR : '' ),
			$content_dir . '/emcp-sandbox',
			$content_dir . '/cache',
			$content_dir . '/uploads/emcp-sandbox',
		);
		$added     = array();

		foreach ( $roots as $root_name => $root ) {
			$count = self::add_root_files( $zip, (string) $root, $skip_dirs, $content_dir );
			if ( false === $count ) {
				return false; // addFile failed — never report a partial bundle as complete.
			}
			if ( $count > 0 ) {
				$added[ (string) $root_name ] = $count;
			}
		}
		return $added;
	}

	/**
	 * Walk one root into the archive under files/, pruning the skip directories.
	 *
	 * @param \ZipArchive $zip         Open archive.
	 * @param string      $root        Absolute root directory (normalized later).
	 * @param string[]    $skip_dirs   Directories never descended into.
	 * @param string      $content_dir Normalized wp-content path.
	 * @return int|false Files added, or false when an addFile failed.
	 */
	private static function add_root_files( ZipArchive $zip, string $root, array $skip_dirs, string $content_dir ) {
		$root = wp_normalize_path( $root );
		if ( ! is_dir( $root ) ) {
			return 0;
		}
		$count = 0;
		$it    = self::file_iterator( $root, $skip_dirs );
		foreach ( $it as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$zip_name = self::zip_relative_name( wp_normalize_path( $file->getPathname() ), $content_dir );
			if ( '' === $zip_name ) {
				continue; // Outside wp-content — never bundled.
			}
			if ( ! $zip->addFile( $file->getPathname(), $zip_name ) ) {
				return false; // Propagate failure so create_archive cannot report a complete bundle.
			}
			$count++;
		}
		return $count;
	}

	/**
	 * Recursive file iterator over a root, pruning excluded directories.
	 *
	 * @param string   $root      Root directory (normalized).
	 * @param string[] $skip_dirs Directories never descended into.
	 * @return \RecursiveIteratorIterator
	 */
	private static function file_iterator( string $root, array $skip_dirs ) {
		return new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				static function ( $current ) use ( $skip_dirs ): bool {
					if ( $current->isDir() ) {
						return ! self::dir_is_skipped( $current->getPathname(), $skip_dirs );
					}
					return true;
				}
			),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
	}

	/**
	 * Whether a directory is inside an excluded path.
	 *
	 * @param string   $path      Normalized path.
	 * @param string[] $skip_dirs Excluded roots.
	 * @return bool
	 */
	private static function dir_is_skipped( string $path, array $skip_dirs ): bool {
		$path = wp_normalize_path( $path );
		foreach ( $skip_dirs as $skip ) {
			if ( '' !== $skip && ( $path === $skip || 0 === strpos( $path, $skip . '/' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Archive name for a file: files/<rel-to-wp-content>, or '' when the file
	 * is outside wp-content.
	 *
	 * @param string $local       Normalized absolute path.
	 * @param string $content_dir Normalized wp-content path.
	 * @return string
	 */
	private static function zip_relative_name( string $local, string $content_dir ): string {
		if ( 0 !== strpos( $local, $content_dir . '/' ) ) {
			return '';
		}
		return 'files/' . ltrim( substr( $local, strlen( $content_dir ) ), '/' );
	}

	/**
	 * Uploads base dir (normalized).
	 *
	 * @return string
	 */
	private static function uploads_basedir(): string {
		$upload = wp_upload_dir();
		return wp_normalize_path( $upload['basedir'] );
	}

	/**
	 * Stable manifest key for an absolute file root: its wp-content-relative path
	 * with non-alphanumerics collapsed (uploads → 'uploads', plugins/foo → 'plugins-foo').
	 *
	 * @param string $abs Normalized absolute directory.
	 * @return string
	 */
	private static function root_key( string $abs ): string {
		$content_dir = wp_normalize_path( ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) );
		$rel         = $abs;
		if ( 0 === strpos( $abs, $content_dir . '/' ) ) {
			$rel = substr( $abs, strlen( $content_dir ) );
		}
		$rel = trim( $rel, '/' );
		if ( '' === $rel ) {
			return 'content';
		}
		$rel = preg_replace( '/[^a-zA-Z0-9]+/', '-', $rel );
		$rel = trim( (string) $rel, '-' );
		return '' !== $rel ? $rel : 'root';
	}

	/**
	 * MySQL server version via wpdb when available.
	 *
	 * @return string
	 */
	private static function db_version(): string {
		global $wpdb;
		if ( isset( $wpdb ) && is_callable( array( $wpdb, 'db_version' ) ) ) {
			return (string) $wpdb->db_version();
		}
		return '';
	}
}
