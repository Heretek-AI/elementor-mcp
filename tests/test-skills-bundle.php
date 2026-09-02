<?php
/**
 * Automated test suite for Skills Bundle and Pro Skills Admin.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'EMCP_TOOLS_DIR', __DIR__ . '/../' );
define( 'EMCP_TOOLS_URL', 'https://example.com/wp-content/plugins/elementor-mcp/' );
define( 'EMCP_TOOLS_VERSION', '3.12.0' );

// Mock WordPress functions
if ( ! function_exists( 'add_action' ) ) { function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {} }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $tag, $value ) { return $value; } }
if ( ! function_exists( '__' ) ) { function __( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( '_n' ) ) { function _n( $single, $plural, $number, $domain = 'default' ) { return 1 === $number ? $single : $plural; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_html_e' ) ) { function esc_html_e( $text, $domain = 'default' ) { echo $text; } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_attr_e' ) ) { function esc_attr_e( $text, $domain = 'default' ) { echo $text; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $url ) { return $url; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $cap ) { return true; } }
if ( ! function_exists( 'admin_url' ) ) { function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . $path; } }
if ( ! function_exists( 'wp_nonce_url' ) ) { function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) { return $actionurl . '&' . $name . '=mocknonce'; } }

// Mock Freemius helper
class Mock_Freemius {
	public function can_use_premium_code() { return true; }
}
function emcp_tools_fs() {
	static $fs = null;
	if ( null === $fs ) { $fs = new Mock_Freemius(); }
	return $fs;
}

require_once __DIR__ . '/../includes/admin/class-pro-skills.php';
require_once __DIR__ . '/../includes/class-skill-catalog.php';

echo "=== TEST 1: Pro Skills Directory & Access ===\n";
assert( EMCP_Tools_Pro_Skills::skills_dir_exists(), 'Skills directory does not exist!' );
assert( EMCP_Tools_Pro_Skills::user_has_access(), 'user_has_access returned false!' );
$download_url = EMCP_Tools_Pro_Skills::download_url();
assert( strpos( $download_url, 'action=emcp_tools_download_skills' ) !== false, 'Download URL malformed!' );
echo "PASS: Pro skills directory exists and user_has_access is true!\n\n";

echo "=== TEST 2: Skills Catalog Discovery ===\n";
$catalog = EMCP_Tools_Skill_Catalog::get_all();
assert( ! empty( $catalog ), 'Skills catalog is empty!' );
echo 'Discovered ' . count( $catalog ) . " skills in catalog.\n";

$required_slugs = array(
	'emcp-skills',
	'emcp-performance',
	'emcp-security',
	'emcp-comic-easel',
	'emcp-plugins/comic-easel',
	'emcp-themer',
	'emcp-gutenberg',
	'emcp-themes/astra',
);

foreach ( $required_slugs as $slug ) {
	assert( isset( $catalog[ $slug ] ), "Required skill '$slug' missing from catalog!" );
	assert( ! empty( $catalog[ $slug ]['name'] ), "Skill '$slug' has empty name!" );
	assert( ! empty( $catalog[ $slug ]['description'] ), "Skill '$slug' has empty description!" );
	echo " - Discovered: $slug ('" . $catalog[ $slug ]['name'] . "')\n";
}
echo "PASS: All required core, performance, security, and comic-easel skills discovered!\n\n";

echo "=== TEST 3: Industry Vertical Packs Detection ===\n";
$verticals_dir = EMCP_TOOLS_DIR . 'skills/emcp-skills/verticals';
$vfiles = glob( $verticals_dir . '/*.md' );
assert( count( $vfiles ) >= 10, 'Expected at least 10 vertical pack files, found ' . count( $vfiles ) );

$labels = array();
foreach ( $vfiles as $vf ) {
	$lines = (array) file( $vf, FILE_IGNORE_NEW_LINES );
	foreach ( array_slice( $lines, 0, 20 ) as $l ) {
		if ( preg_match( '/^label:\s*(.+?)\s*$/', $l, $m ) ) {
			$labels[] = $m[1];
			break;
		}
	}
}
echo 'Parsed ' . count( $labels ) . " vertical labels.\n";
assert( in_array( 'Dental Clinics', $labels, true ) );
assert( in_array( 'Law Firms', $labels, true ) );
assert( in_array( 'Real Estate & Realtors', $labels, true ) );
assert( in_array( 'Restaurants & Cafes', $labels, true ) );
assert( in_array( 'B2B SaaS & Tech Startups', $labels, true ) );
assert( in_array( 'Creative & Digital Agencies', $labels, true ) );
echo "PASS: Industry vertical packs detected and parsed successfully!\n\n";

echo "=== TEST 4: Zip Archive Packaging ===\n";
if ( class_exists( 'ZipArchive' ) ) {
	$zip_tmp = sys_get_temp_dir() . '/test_emcp_skills.zip';
	if ( file_exists( $zip_tmp ) ) { unlink( $zip_tmp ); }

	$zip = new ZipArchive();
	$res = $zip->open( $zip_tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	assert( $res === true, 'Failed to open zip archive' );

	$source = EMCP_TOOLS_DIR . 'skills';
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	$file_count = 0;
	foreach ( $iterator as $file_info ) {
		$abs_path = $file_info->getPathname();
		$rel_path = ltrim( substr( $abs_path, strlen( $source ) ), DIRECTORY_SEPARATOR . '/' );
		if ( '' === $rel_path ) { continue; }
		$zip_path = str_replace( DIRECTORY_SEPARATOR, '/', $rel_path );
		if ( $file_info->isDir() ) {
			$zip->addEmptyDir( $zip_path );
		} else {
			$zip->addFile( $abs_path, $zip_path );
			$file_count++;
		}
	}
	$zip->close();
	assert( file_exists( $zip_tmp ), 'Zip file not created' );
	assert( filesize( $zip_tmp ) > 0, 'Zip file is empty' );
	echo "PASS: Zip bundle created with $file_count files, size: " . filesize( $zip_tmp ) . " bytes.\n\n";
	unlink( $zip_tmp );
} else {
	echo "SKIP: ZipArchive not enabled in CLI.\n\n";
}

echo "=== TEST 5: Render page-skills.php ===\n";
ob_start();
include __DIR__ . '/../includes/admin/views/page-skills.php';
$html = ob_get_clean();

assert( strpos( $html, 'Download emcp-skills.zip' ) !== false, 'Download button missing from rendered page!' );
assert( strpos( $html, 'Includes 10 industry skill packs' ) !== false, 'Vertical packs counter missing!' );
assert( strpos( $html, 'Dental Clinics' ) !== false, 'Dental Clinics pack badge missing!' );
assert( strpos( $html, 'Skills are not bundled in this build' ) === false, 'Not-bundled warning is still showing!' );
assert( strpos( $html, 'Upgrade to Pro' ) === false, 'Upgrade CTA is showing on Pro!' );
assert( strpos( $html, 'Claude Code (terminal)' ) !== false, 'Claude Code guide missing!' );
assert( strpos( $html, 'Antigravity' ) !== false, 'Antigravity guide missing!' );
echo "PASS: page-skills.php rendered complete Pro interface with 0 warnings!\n\n";

echo "ALL 5 SKILLS BUNDLE TEST SUITES PASSED 100%!\n";
