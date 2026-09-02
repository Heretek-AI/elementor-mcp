<?php
/**
 * Packager — creates and unpacks .emcp portable zip archives.
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Packager {

	public static function backup_dir(): string {
		$upload = wp_upload_dir();
		$dir    = $upload['basedir'] . '/emcp-backups';
		wp_mkdir_p( $dir );
		return $dir;
	}

	public static function create_archive( string $name = '' ): string {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$dir      = self::backup_dir();
		$slug     = sanitize_file_name( $name ?: 'backup-' . gmdate( 'Y-m-d-His' ) );
		$zip_file = $dir . '/' . $slug . '.emcp';

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return '';
		}

		// Dump SQL
		$sql_file = $dir . '/temp-db.sql';
		EMCP_Tools_DB_Exporter::export_to_file( $sql_file );
		if ( file_exists( $sql_file ) ) {
			$zip->addFile( $sql_file, 'database.sql' );
		}

		// Add manifest
		$manifest = array(
			'version'    => EMCP_TOOLS_VERSION,
			'site_url'   => home_url(),
			'created_at' => gmdate( 'c' ),
		);
		$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
		$zip->close();

		if ( file_exists( $sql_file ) ) {
			unlink( $sql_file );
		}

		return $zip_file;
	}

	public static function list_archives(): array {
		$dir = self::backup_dir();
		$files = glob( $dir . '/*.emcp' ) ?: array();
		$out = array();
		foreach ( $files as $f ) {
			$out[] = array(
				'filename' => basename( $f ),
				'size'     => size_format( filesize( $f ) ),
				'date'     => gmdate( 'Y-m-d H:i:s', filemtime( $f ) ),
				'path'     => $f,
			);
		}
		return $out;
	}
}
