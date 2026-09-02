<?php
/**
 * Agent Skills Catalog — parses and serves bundled SKILL.md documentation to MCP agents.
 *
 * Scans skills/ and nested domain folders (skills/domain/subdomain) for SKILL.md files.
 * Provides lookup, search, and progressive disclosure injection into the agent discovery context.
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Skill_Catalog {

	/**
	 * Relative skills directory.
	 */
	const SKILLS_DIR = 'skills';

	/**
	 * Cached catalog.
	 *
	 * @var array<string,array{slug:string,name:string,description:string,path:string}>|null
	 */
	private static $catalog = null;

	/**
	 * Initialize catalog hooks.
	 */
	public static function init(): void {
		add_filter( 'emcp_tools_discovery_skills', array( __CLASS__, 'discovery_catalog' ) );
	}

	/**
	 * Absolute path to skills directory.
	 *
	 * @return string
	 */
	public static function get_skills_path(): string {
		return EMCP_TOOLS_DIR . self::SKILLS_DIR;
	}

	/**
	 * Build or return cached catalog of all available skills.
	 *
	 * @return array<string,array{slug:string,name:string,description:string,path:string}>
	 */
	public static function get_all(): array {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}

		self::$catalog = array();
		$base_dir = self::get_skills_path();

		if ( ! is_dir( $base_dir ) || ! is_readable( $base_dir ) ) {
			return self::$catalog;
		}

		// Walk top-level and 1-level nested directories.
		$top_dirs = glob( $base_dir . '/*', GLOB_ONLYDIR ) ?: array();
		foreach ( $top_dirs as $dir ) {
			$slug = basename( $dir );
			$skill_file = $dir . '/SKILL.md';
			if ( file_exists( $skill_file ) && is_readable( $skill_file ) ) {
				self::$catalog[ $slug ] = self::parse_skill_meta( $slug, $skill_file );
			}

			// 1 nesting level allowed (e.g. emcp-themes/astra).
			$nested_dirs = glob( $dir . '/*', GLOB_ONLYDIR ) ?: array();
			foreach ( $nested_dirs as $nested ) {
				$sub_slug = $slug . '/' . basename( $nested );
				$nested_file = $nested . '/SKILL.md';
				if ( file_exists( $nested_file ) && is_readable( $nested_file ) ) {
					self::$catalog[ $sub_slug ] = self::parse_skill_meta( $sub_slug, $nested_file );
				}
			}
		}

		/**
		 * Filters the final skills catalog.
		 *
		 * @param array $catalog Slugs mapped to metadata.
		 */
		self::$catalog = (array) apply_filters( 'emcp_tools_skill_sources', self::$catalog );

		return self::$catalog;
	}

	/**
	 * Extract name and description from YAML frontmatter or heading.
	 *
	 * @param string $slug Slug.
	 * @param string $path Path to SKILL.md.
	 * @return array{slug:string,name:string,description:string,path:string}
	 */
	private static function parse_skill_meta( string $slug, string $path ): array {
		$content = (string) file_get_contents( $path );
		$name    = ucwords( str_replace( array( '-', '/' ), array( ' ', ' — ' ), $slug ) );
		$desc    = '';

		if ( preg_match( '/^---\s*\n(.*?)\n---/s', $content, $matches ) ) {
			$front = $matches[1];
			if ( preg_match( '/^name:\s*(.+)$/m', $front, $n_match ) ) {
				$name = trim( $n_match[1], " '\"" );
			}
			if ( preg_match( '/^description:\s*(.+)$/m', $front, $d_match ) ) {
				$desc = trim( $d_match[1], " '\"" );
			}
		}

		if ( '' === $desc && preg_match( '/^#\s+(.+)$/m', $content, $h_match ) ) {
			$desc = trim( $h_match[1] );
		}

		return array(
			'slug'        => $slug,
			'name'        => $name,
			'description' => $desc ?: $name,
			'path'        => $path,
		);
	}

	/**
	 * Get full skill body by slug, with path-traversal safety.
	 *
	 * @param string $slug Skill slug.
	 * @return string|WP_Error
	 */
	public static function get_body( string $slug ) {
		// Prevent path traversal. Allow alphanumeric, hyphen, underscore, and at most one slash.
		if ( false !== strpos( $slug, '..' ) || substr_count( $slug, '/' ) > 1 || ! preg_match( '/^[a-z0-9_\-\/]+$/i', $slug ) ) {
			return new WP_Error( 'invalid_slug', __( 'Invalid skill slug provided.', 'emcp-tools' ) );
		}

		$all = self::get_all();
		if ( ! isset( $all[ $slug ] ) ) {
			return new WP_Error( 'not_found', sprintf( __( 'Skill "%s" not found in catalog.', 'emcp-tools' ), esc_html( $slug ) ) );
		}

		$path = $all[ $slug ]['path'];
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'unreadable', __( 'Skill file cannot be read.', 'emcp-tools' ) );
		}

		return (string) file_get_contents( $path );
	}

	/**
	 * Format the catalog as a markdown block for progressive discovery context.
	 *
	 * @return string
	 */
	public static function discovery_catalog(): string {
		$all = self::get_all();
		if ( empty( $all ) ) {
			return '';
		}

		$lines   = array();
		$lines[] = '## Available Agent Skills';
		$lines[] = 'Use `get-skill` with a skill slug to fetch detailed instructions before performing actions in that domain:';

		foreach ( $all as $skill ) {
			$lines[] = sprintf( '- `%s`: %s — %s', $skill['slug'], $skill['name'], $skill['description'] );
		}

		return implode( "\n", $lines );
	}
}

// Auto-wire on load.
EMCP_Tools_Skill_Catalog::init();
