<?php
/**
 * Automated test suite for Migrate engine & EMCP_Tools_Packager.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'EMCP_TOOLS_DIR', __DIR__ . '/../' );
define( 'EMCP_TOOLS_URL', 'https://example.com/wp-content/plugins/elementor-mcp/' );
define( 'EMCP_TOOLS_VERSION', '3.12.0' );

// Mock WordPress functions
if ( ! function_exists( 'add_action' ) ) { function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {} }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $string ) { return strip_tags( (string) $string ); } }
if ( ! function_exists( '__' ) ) { function __( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_html_e' ) ) { function esc_html_e( $text, $domain = 'default' ) { echo $text; } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_attr_e' ) ) { function esc_attr_e( $text, $domain = 'default' ) { echo $text; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'sanitize_file_name' ) ) { function sanitize_file_name( $name ) { return preg_replace( '/[^a-zA-Z0-9_.-]/', '', (string) $name ); } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $cap ) { return true; } }
if ( ! function_exists( 'check_admin_referer' ) ) { function check_admin_referer( $action ) { return true; } }
if ( ! function_exists( 'wp_nonce_field' ) ) { function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="test" />'; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $opt, $default = false ) { return $default; } }
if ( ! function_exists( 'size_format' ) ) { function size_format( $bytes ) { return $bytes . ' B'; } }
if ( ! function_exists( 'home_url' ) ) { function home_url() { return 'https://example.com'; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $data, $options = 0 ) { return json_encode( $data, $options ); } }
if ( ! function_exists( 'is_serialized' ) ) {
	function is_serialized( $data ) {
		if ( ! is_string( $data ) ) { return false; }
		$data = trim( $data );
		if ( 'N;' === $data ) { return true; }
		if ( strlen( $data ) < 4 ) { return false; }
		if ( ':' !== $data[1] ) { return false; }
		$lastc = substr( $data, -1 );
		if ( ';' !== $lastc && '}' !== $lastc ) { return false; }
		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( '"' !== substr( $data, -2, 1 ) ) { return false; }
			case 'a':
			case 'O':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$/", $data );
		}
		return false;
	}
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		$dir = sys_get_temp_dir() . '/emcp_test_uploads';
		if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
		return array( 'basedir' => $dir, 'baseurl' => 'https://example.com/uploads' );
	}
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		if ( ! is_dir( $target ) ) { return mkdir( $target, 0777, true ); }
		return true;
	}
}

// Pre-load sandbox interface + base class
require_once __DIR__ . '/../includes/sandbox/interface-sandbox-artifact.php';
require_once __DIR__ . '/../includes/sandbox/class-sandbox-store.php';

// Load Pro Loader
require_once __DIR__ . '/../includes/class-pro-loader.php';

echo "=== TEST 1: Load Runtime Classes via EMCP_Tools_Pro_Loader ===\n";
EMCP_Tools_Pro_Loader::load_runtime();

assert( class_exists( 'EMCP_Tools_Packager' ), 'EMCP_Tools_Packager not found!' );
assert( class_exists( 'EMCP_Tools_DB_Exporter' ), 'EMCP_Tools_DB_Exporter not found!' );
assert( class_exists( 'EMCP_Tools_DB_Importer' ), 'EMCP_Tools_DB_Importer not found!' );
assert( class_exists( 'EMCP_Tools_Search_Replace' ), 'EMCP_Tools_Search_Replace not found!' );
echo "PASS: All 4 Migrate engine classes loaded successfully!\n\n";

echo "=== TEST 2: Packager List Archives ===\n";
$archives = EMCP_Tools_Packager::list_archives();
assert( is_array( $archives ), 'list_archives did not return an array' );
echo 'PASS: list_archives returned array with ' . count( $archives ) . " archives.\n\n";

echo "=== TEST 3: Search and Replace Engine ===\n";
$simple = 'Hello http://old-site.com world';
$replaced = EMCP_Tools_Search_Replace::replace( $simple, 'http://old-site.com', 'https://new-site.com' );
assert( $replaced === 'Hello https://new-site.com world' );

$array_data = array( 'url' => 'http://old-site.com/foo', 'other' => 123 );
$replaced_arr = EMCP_Tools_Search_Replace::replace( $array_data, 'http://old-site.com', 'https://new-site.com' );
assert( $replaced_arr['url'] === 'https://new-site.com/foo' );

$serialized = serialize( array( 'site' => 'http://old-site.com' ) );
$replaced_ser = EMCP_Tools_Search_Replace::replace( $serialized, 'http://old-site.com', 'https://new-site.com' );
assert( is_serialized( $replaced_ser ) );
$unser = unserialize( $replaced_ser );
assert( $unser['site'] === 'https://new-site.com' );
echo "PASS: Search & Replace engine string, array, and serialized verified!\n\n";

echo "=== TEST 4: Migrate Module Registration ===\n";
require_once __DIR__ . '/../includes/modules/class-module.php';
require_once __DIR__ . '/../includes/modules/class-migrate-module.php';
$module = new EMCP_Tools_Migrate_Module();
assert( $module->id() === 'migrate' );
$module->register();
echo "PASS: Migrate module registered without error!\n\n";

echo "=== TEST 5: Render page-migrate.php without Fatal Error ===\n";
ob_start();
include __DIR__ . '/../includes/admin/views/page-migrate.php';
$html = ob_get_clean();
assert( strpos( $html, 'Backup, Sync & Migrate' ) !== false, 'page-migrate.php header missing' );
assert( strpos( $html, 'Create New Backup' ) !== false, 'page-migrate.php form missing' );
assert( strpos( $html, 'Existing Backups' ) !== false, 'page-migrate.php table missing' );
echo "PASS: page-migrate.php rendered cleanly with zero errors!\n\n";

echo "ALL 5 MIGRATE & PACKAGER TEST SUITES PASSED 100%!\n";
