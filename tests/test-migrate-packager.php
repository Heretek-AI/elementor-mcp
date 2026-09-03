<?php
/**
 * Automated test suite for Migrate engine & EMCP_Tools_Packager.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'EMCP_TOOLS_DIR', __DIR__ . '/../' );
define( 'EMCP_TOOLS_URL', 'https://example.com/wp-content/plugins/elementor-mcp/' );
define( 'EMCP_TOOLS_VERSION', '3.16.0' );
// WordPress output-type constants used by $wpdb results.
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! defined( 'OBJECT_K' ) ) { define( 'OBJECT_K', 'OBJECT_K' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
if ( ! defined( 'ARRAY_N' ) ) { define( 'ARRAY_N', 'ARRAY_N' ); }

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
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $url ) { return filter_var( (string) $url, FILTER_SANITIZE_URL ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'sanitize_file_name' ) ) { function sanitize_file_name( $name ) { return preg_replace( '/[^a-zA-Z0-9_.-]/', '', (string) $name ); } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $cap ) { return true; } }
if ( ! function_exists( 'is_admin' ) ) { function is_admin() { return false; } }
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
if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) { return get_option( '_transient_' . $key, false ); }
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $exp = 0 ) { update_option( '_transient_' . $key, $value ); return true; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) { return true; }
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) { return true; }
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) { delete_option( '_transient_' . $key ); return true; }
}
if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) { return str_replace( array( "'", "\n" ), array( "\'", ' ' ), (string) $text ); }
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true ) { return substr( str_shuffle( 'abcdef0123456789' ), 0, $length ); }
}

/** Minimal in-memory wpdb double for exercising the migrate engine without MySQL. */
class Fake_Wpdb {
	public $last_error = '';
	public $tables     = array( 'wpx' );
	public $columns    = array( 'id', 'name' );
	public $create_sql = 'CREATE TABLE `wpx` ( `id` bigint(20) NOT NULL, `name` varchar(255) DEFAULT NULL ) ENGINE=InnoDB';
	public $rows       = array(
		array( 'id' => 1, 'name' => 'Hello http://old-site.com world' ),
		array( 'id' => 2, 'name' => 'plain' ),
	);
	public $queries = array();

	public function get_col( $sql ) {
		if ( 0 === strpos( ltrim( $sql ), 'SHOW TABLES' ) ) { return $this->tables; }
		if ( false !== strpos( $sql, 'DESCRIBE' ) ) { return $this->columns; }
		return array();
	}
	public function get_row( $sql, $output = OBJECT ) {
		if ( false !== strpos( $sql, 'SHOW CREATE TABLE' ) ) { return array( 0 => 'wpx', 1 => $this->create_sql ); }
		return null;
	}
	public function get_results( $sql, $output = OBJECT ) {
		if ( false !== strpos( $sql, 'LIMIT 0, 400' ) ) { return $this->rows; }
		return array(); // Second chunk is empty -> exporter stops.
	}
	public function _real_escape( $value ) { return addslashes( (string) $value ); }
	public function esc_like( $value ) { return $value; }
	public function db_version() { return '8.0.0'; }
	public function query( $sql ) {
		$this->queries[] = $sql;
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
assert( class_exists( 'EMCP_Tools_Serialized_Search_Replace' ), 'EMCP_Tools_Serialized_Search_Replace not found!' );
assert( class_exists( 'EMCP_Tools_Restore_Engine' ), 'EMCP_Tools_Restore_Engine not found!' );
echo "PASS: All Migrate engine classes loaded successfully!\n\n";

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
assert( strpos( $html, 'Create Archive' ) !== false, 'page-migrate.php create form missing' );
assert( strpos( $html, 'Existing Archives' ) !== false, 'page-migrate.php table missing' );
echo "PASS: page-migrate.php rendered cleanly with zero errors!\n\n";

echo "=== TEST 6: Byte-accurate serialized / JSON search-replace ===\n";
$engine = 'EMCP_Tools_Serialized_Search_Replace';
$old    = 'http://old-site.com';
$new    = 'https://new-site.com';

// Plain + JSON-escaped (\/) URL pair on a raw _elementor_data-style blob.
$json = '{"url":"http:\\/\\/old-site.com\\/page","other":"http://old-site.com"}';
$fixed_json = $engine::pair_replace( $json, $old, $new );
assert( false !== strpos( $fixed_json, 'https:\\/\\/new-site.com\\/page' ), 'escaped JSON pair not replaced' );
assert( false !== strpos( $fixed_json, 'https://new-site.com' ), 'plain JSON pair not replaced' );

// Serialized array: the whole value must stay parseable and length-correct.
$ser = serialize( array( 'site' => $old . '/home', 'nested' => array( 'deep' => $old ), 'keep' => 'no match' ) );
$fixed_ser = $engine::fix_serialized_strings( $ser, $old, $new );
assert( is_serialized( $fixed_ser ), 'fixed serialized value no longer serialized' );
$unser = unserialize( $fixed_ser );
assert( $unser['site'] === $new . '/home', 'top-level URL not rewritten' );
assert( $unser['nested']['deep'] === $new, 'nested URL not rewritten' );
assert( $unser['keep'] === 'no match', 'unrelated token damaged' );

// A serialized string token containing a URL becomes LONGER; lengths must follow.
$grow = serialize( array( 'a' => str_repeat( 'x', 200 ), 'url' => $old ) );
$fixed_grow = $engine::fix_serialized_strings( $grow, $old, $new );
assert( is_serialized( $fixed_grow ), 'grow case lost serialization' );
$un_grow = unserialize( $fixed_grow );
assert( strlen( $un_grow['a'] ) === 200, 'sibling string length corrupted by delta fix' );
assert( $un_grow['url'] === $new, 'URL not rewritten in grow case' );

// Never unserializes: a blob referencing a missing class must not fatal and must
// leave the object token byte-identical when it does not contain the search.
$foreign = 'O:9:"NoSuchCls":1:{s:5:"field";s:11:"http://x.com";}';
$fixed_foreign = $engine::fix_serialized_strings( $foreign, 'zzz-does-not-appear', $new );
assert( $fixed_foreign === $foreign, 'foreign-class token mutated when it should not be' );

// Legacy wrapper delegates to the new engine.
$legacy = EMCP_Tools_Search_Replace::replace( serialize( array( 'u' => $old ) ), $old, $new );
assert( unserialize( $legacy )['u'] === $new, 'EMCP_Tools_Search_Replace wrapper regressed' );
echo "PASS: serialized/JSON search-replace verified byte-accurate\n\n";

echo "=== TEST 7: DB exporter -> importer round trip (directive-skip + quote-safe split) ===\n";
$GLOBALS['wpdb'] = new Fake_Wpdb();
$dump = sys_get_temp_dir() . '/emcp_test_dump.sql';
$stats = EMCP_Tools_DB_Exporter::export_to_file( $dump, array( 'wpx' ) );
assert( is_array( $stats ) && isset( $stats['rows'] ) && $stats['rows'] >= 2, 'exporter did not report row stats' );
$sql = file_get_contents( $dump );
assert( false !== strpos( $sql, 'INSERT INTO `wpx`' ), 'exporter wrote no INSERTs' );

$import = EMCP_Tools_DB_Importer::import_from_file( $dump );
assert( is_array( $import ), 'importer failed to run' );
assert( $import['errors'] === 0, 'importer reported errors' );
$inserts = array_values( array_filter( $GLOBALS['wpdb']->queries, function ( $q ) {
	return 0 === strpos( $q, 'INSERT INTO `wpx`' );
} ) );
assert( count( $inserts ) === 2, 'importer did not run both INSERT statements' );

// A hostile dump with transaction-control directives + ';' inside quoted values.
$hostile = sys_get_temp_dir() . '/emcp_test_hostile.sql';
file_put_contents( $hostile, "-- header\nSET AUTOCOMMIT=0;\nSTART TRANSACTION;\nCREATE TABLE `t` ( `n` varchar(50) DEFAULT 'a;b' );\nINSERT INTO `t` VALUES ('semi;colon-in-string');\nCOMMIT;\n" );
$GLOBALS['wpdb']->queries = array();
$import2 = EMCP_Tools_DB_Importer::import_from_file( $hostile );
assert( is_array( $import2 ), 'hostile import failed' );
assert( $import2['skipped'] === 3, 'transaction-control directives were not skipped (autocommit/start/commit)' );
assert( $import2['errors'] === 0, 'hostile import had errors' );
$ran = array_values( array_filter( $GLOBALS['wpdb']->queries, function ( $q ) {
	return false !== strpos( $q, 'semi;colon-in-string' );
} ) );
assert( count( $ran ) === 1, "statement with ';' inside a string was split" );
echo "PASS: export/import round trip + directive skip + quote-safe splitting verified\n\n";

echo "=== TEST 8: Packager archive round trip (create / manifest / list / delete) ===\n";
$name = 'test-roundtrip.emcp';
$arch = EMCP_Tools_Packager::create_archive( $name, array( 'include_files' => false ) );
assert( is_string( $arch ) && is_file( $arch ), 'create_archive did not produce a file' );
$manifest = EMCP_Tools_Packager::read_manifest( $name );
assert( is_array( $manifest ) && ! empty( $manifest['emcp'] ), 'manifest missing or invalid' );
assert( isset( $manifest['database_sha256'] ) && 64 === strlen( $manifest['database_sha256'] ), 'manifest missing database hash' );
assert( isset( $manifest['site_url'] ), 'manifest missing site_url' );
$list = EMCP_Tools_Packager::list_archives();
$found = array_filter( $list, function ( $a ) use ( $name ) { return $a['filename'] === $name; } );
assert( count( $found ) === 1, 'list_archives did not include the new archive' );
assert( EMCP_Tools_Packager::delete_archive( $name ), 'delete_archive returned false' );
assert( ! is_file( $arch ), 'delete_archive left the file behind' );
echo "PASS: packager create/list/read/delete round trip verified\n\n";

echo "ALL MIGRATE & PACKAGER TEST SUITES PASSED 100%!\n";
