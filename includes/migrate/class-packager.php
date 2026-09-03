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
		return $dir;
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
	 * @param string $name Optional archive name ('.emcp' appended if missing).
	 * @param array  $opts {
	 *     @type bool $include_files Include uploads/plugins/themes (default false).
	 * }
	 * @return string|false Absolute archive path, or false on failure.
	 */
	public static function create_archive( string $name = '', array $opts = array() ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}
		$include_files = ! empty( $opts['include_files'] );

		$dir      = self::backup_dir();
		$name     = sanitize_file_name( $name );
		if ( '' === $name ) {
			$name = 'backup-' . gmdate( 'Y-m-d-His' );
		}
		if ( self::EXT !== substr( $name, -5 ) ) {
			$name .= self::EXT;
		}
		$zip_file = $dir . '/' . $name;
		$tmp_sql  = $dir . '/.tmp-' . wp_generate_password( 8, false ) . '.sql';

		$db_stats = EMCP_Tools_DB_Exporter::export_to_file( $tmp_sql );
		if ( false === $db_stats || ! is_file( $tmp_sql ) ) {
			@unlink( $tmp_sql ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- local temp cleanup.
			return false;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp_sql ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}

		$zip->addFile( $tmp_sql, 'database.sql' );

		$file_entries = array();
		if ( $include_files ) {
			$file_entries = self::add_files( $zip );
		}

		$manifest = array(
			'emcp'            => true,
			'format_version'  => self::FORMAT_VERSION,
			'version'         => defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '0',
			'created_at'      => gmdate( 'c' ),
			'site_url'        => home_url(),
			'siteurl_option'  => get_option( 'siteurl', home_url() ),
			'php'             => PHP_VERSION,
			'wp'              => isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '',
			'db'              => self::db_version(),
			'db_stats'        => array(
				'tables'    => isset( $db_stats['tables'] ) ? $db_stats['tables'] : 0,
				'rows'      => isset( $db_stats['rows'] ) ? $db_stats['rows'] : 0,
				'per_table' => isset( $db_stats['per_table'] ) ? $db_stats['per_table'] : array(),
				'partial'   => ! empty( $db_stats['partial'] ),
			),
			'database_sha256' => hash_file( 'sha256', $tmp_sql ),
			'files'           => count( $file_entries ),
			'file_roots'      => $file_entries,
		);
		$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$zip->close();

		@unlink( $tmp_sql ); // phpcs:ignore WordPress.WP.AlternativeFunctions
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
				'sha256'     => hash_file( 'sha256', $f ),
			);
		}
		usort( $out, static function ( $a, $b ) {
			return strcmp( $b['filename'], $a['filename'] ); // Newest (lexical timestamp) first.
		} );
		return $out;
	}

	/**
	 * Add site files (uploads/plugins/themes) to the open archive under files/.
	 *
	 * Paths are normalized before the WP_CONTENT_DIR prefix is stripped (a
	 * Windows host writes backslash paths from RecursiveDirectoryIterator, which
	 * would defeat the prefix match otherwise). Never descends into the plugin
	 * itself, the backups dir, the sandbox, or caches.
	 *
	 * @param \ZipArchive $zip Open archive.
	 * @return string[] Root → file-count map added.
	 */
	private static function add_files( ZipArchive $zip ): array {
		$content_dir = wp_normalize_path( ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) );
		$roots       = array(
			'uploads'  => self::uploads_basedir(),
			'plugins'  => wp_normalize_path( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : $content_dir . '/plugins' ),
			'themes'   => wp_normalize_path( get_theme_root() ? get_theme_root() : $content_dir . '/themes' ),
		);
		$skip_dirs   = array(
			wp_normalize_path( self::backup_dir() ),
			wp_normalize_path( defined( 'EMCP_TOOLS_DIR' ) ? EMCP_TOOLS_DIR : '' ),
			$content_dir . '/emcp-sandbox',
			$content_dir . '/cache',
			$content_dir . '/uploads/emcp-sandbox',
		);
		$added       = array();

		foreach ( $roots as $root_name => $root ) {
			$root = wp_normalize_path( $root );
			if ( ! is_dir( $root ) ) {
				continue;
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
				$zip->addFile( $file->getPathname(), $zip_name );
				$count++;
			}
			if ( $count > 0 ) {
				$added[ $root_name ] = $count;
			}
		}
		return $added;
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
