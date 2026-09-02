<?php
/**
 * Automated regression suite for the Sandbox page fatal.
 *
 * Guards the class of bug that crashed `?page=emcp-tools-widgets` on
 * shad-base.com: EMCP_Tools_Block_Store loads eagerly (so `class_exists()`
 * passes) but was missing the API its own admin views call, giving
 * `Call to undefined method EMCP_Tools_Block_Store::user_has_access()` on the
 * default overview (`views/sandbox/overview.php:41`).
 *
 * This suite asserts the store now exposes every consumer-called method and
 * that the crashing view file renders end-to-end.
 *
 * Run:  php tests/test-sandbox-blocks.php
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE );

// -------------------------------------------------------------------------
// Harness helpers
// -------------------------------------------------------------------------

/** Throws on a failed check — loud regardless of zend.assertions. */
function ok( $cond, string $msg ): void {
	if ( ! $cond ) {
		throw new RuntimeException( 'FAILED: ' . $msg );
	}
}

/** Recursively removes a temp dir so suites start clean. */
function rm_rf( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	if ( false === $items ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		is_dir( $path ) ? rm_rf( $path ) : @unlink( $path ); // phpcs:ignore
	}
	@rmdir( $dir ); // phpcs:ignore
}

define( 'ABSPATH', __DIR__ . '/../' );
define( 'EMCP_TOOLS_DIR', __DIR__ . '/../' );
define( 'EMCP_TOOLS_URL', 'https://example.com/wp-content/plugins/elementor-mcp/' );
define( 'EMCP_TOOLS_VERSION', '3.14.2' );

// -------------------------------------------------------------------------
// Mock WordPress (function_exists-guarded so this also runs under WP-CLI)
// -------------------------------------------------------------------------

if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		global $emcp_test_filters;
		$emcp_test_filters[ $tag ][] = $callback;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		global $emcp_test_filters;
		if ( ! empty( $emcp_test_filters[ $tag ] ) ) {
			foreach ( $emcp_test_filters[ $tag ] as $callback ) {
				$value = call_user_func( $callback, $value );
			}
		}
		return $value;
	}
}
if ( ! function_exists( 'register_post_type' ) ) { function register_post_type() { return true; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $opt, $default = false ) { return $default; } }
if ( ! function_exists( 'update_option' ) ) { function update_option() { return true; } }
if ( ! function_exists( 'content_url' ) ) { function content_url( $path = '' ) { return 'https://example.com/wp-content' . $path; } }

if ( ! function_exists( 'emcp_tools_fs' ) ) {
	function emcp_tools_fs() {
		return new class() {
			public function can_use_premium_code() { return true; }
			public function is_premium() { return true; }
		};
	}
}

// User capability under test.
$GLOBALS['emcp_test_user_can'] = true;
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return (bool) $GLOBALS['emcp_test_user_can'];
	}
}

// Controllable post / meta store.
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id = null ) {
		return $GLOBALS['emcp_test_posts'][ (int) $id ] ?? null;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $GLOBALS['emcp_test_meta'][ (int) $post_id ][ $key ] ?? '';
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value ) {
		$GLOBALS['emcp_test_meta'][ (int) $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}
if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false ) {
		$id = $GLOBALS['emcp_test_next_id']++;
		$GLOBALS['emcp_test_posts'][ $id ] = (object) array(
			'ID'           => $id,
			'post_type'    => $postarr['post_type'] ?? 'post',
			'post_status'  => $postarr['post_status'] ?? 'draft',
			'post_title'   => $postarr['post_title'] ?? '',
			'post_modified' => gmdate( 'Y-m-d H:i:s' ),
		);
		return $id;
	}
}
if ( ! function_exists( 'wp_update_post' ) ) { function wp_update_post( $postarr ) { return (int) ( $postarr['ID'] ?? 0 ); } }
if ( ! function_exists( 'wp_delete_post' ) ) { function wp_delete_post( $id, $force = false ) { return true; } }
if ( ! function_exists( 'get_posts' ) ) { function get_posts( $args = array() ) { return array(); } }
if ( ! function_exists( 'wp_generate_uuid4' ) ) { function wp_generate_uuid4() { return 'emcp-uuid-0001'; } }
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) ? true : mkdir( $target, 0777, true );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( trim( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', (string) $title ), '-' ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $str ) { return trim( (string) $str ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $key ) { return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); } }

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = array();
		public function __construct( $code = '', $message = '' ) {
			$this->errors = array( $code => array( $message ) );
		}
		public function get_error_code() { $k = array_keys( $this->errors ); return $k[0] ?? ''; }
		public function get_error_message() { $c = $this->get_error_code(); return $this->errors[ $c ][0] ?? ''; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $thing ) { return $thing instanceof WP_Error; } }

// WP_Query double — store lists run empty.
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $posts = array();
		public $found_posts = 0;
		public function __construct( $args = array() ) {}
		public function get_posts() { return $this->posts; }
	}
}

// Translation / escaping / url helpers the view files call.
if ( ! function_exists( '__' ) ) { function __( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( '_e' ) ) { function _e( $text, $domain = 'default' ) { echo $text; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_html_e' ) ) { function esc_html_e( $text, $domain = 'default' ) { echo $text; } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $text, $domain = 'default' ) { return $text; } }
if ( ! function_exists( 'esc_attr_e' ) ) { function esc_attr_e( $text, $domain = 'default' ) { echo $text; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $url ) { return $url; } }
if ( ! function_exists( 'esc_js' ) ) { function esc_js( $text ) { return $text; } }
if ( ! function_exists( 'number_format_i18n' ) ) { function number_format_i18n( $number ) { return number_format( $number ); } }
if ( ! function_exists( 'menu_page_url' ) ) { function menu_page_url( $menu_slug, $echo = true ) { return 'https://example.com/wp-admin/admin.php?page=' . $menu_slug; } }
if ( ! function_exists( 'emcp_tools_upgrade_url' ) ) { function emcp_tools_upgrade_url() { return '#'; } }
if ( ! function_exists( 'wp_create_nonce' ) ) { function wp_create_nonce( $action = -1 ) { return 'nonce'; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $data, $options = 0 ) { return json_encode( $data, $options ); } }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $args, $url = '' ) { return $url . '&x=1'; } }
if ( ! function_exists( 'admin_url' ) ) { function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . $path; } }

// Stub the two peer stores the overview renders alongside the real block store.
if ( ! class_exists( 'EMCP_Tools_Widget_Store' ) ) {
	class EMCP_Tools_Widget_Store {
		public static function user_has_access(): bool { return true; }
		public static function list_widgets( string $status = 'any' ): array { return array(); }
	}
}
if ( ! class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) ) {
	class EMCP_Tools_PHP_Snippet_Store {
		public static function can_edit(): bool { return true; }
		public static function list_snippets( string $status = 'any' ): array { return array(); }
	}
}

// -------------------------------------------------------------------------
// Load the real sandbox store chain in bootstrap order.
// -------------------------------------------------------------------------
$GLOBALS['emcp_test_posts'] = array();
$GLOBALS['emcp_test_meta']  = array();
$GLOBALS['emcp_test_next_id'] = 500;

require_once __DIR__ . '/../includes/sandbox/interface-sandbox-artifact.php';
require_once __DIR__ . '/../includes/sandbox/class-sandbox-paths.php';
require_once __DIR__ . '/../includes/sandbox/class-sandbox-store.php';
require_once __DIR__ . '/../includes/sandbox/class-sandbox-list-query.php';
require_once __DIR__ . '/../includes/class-block-store.php';

$STORE = EMCP_Tools_Block_Store::instance();

echo "=== TEST 1: Block store exposes every consumer-called method ===\n";
foreach ( array( 'user_has_access', 'summary', 'list_blocks', 'list_blocks_page', 'get_asset', 'create_block', 'uninstall_cleanup', 'apply_bundle', 'instance', 'create', 'set_status', 'delete' ) as $method ) {
	ok( method_exists( 'EMCP_Tools_Block_Store', $method ), "missing method EMCP_Tools_Block_Store::$method()" );
}
echo "PASS: all consumer-called methods present.\n";

echo "\n=== TEST 2: user_has_access() gates on license + manage_options ===\n";
$GLOBALS['emcp_test_user_can'] = true;
ok( true === EMCP_Tools_Block_Store::user_has_access(), 'expected true for admin + premium' );
$GLOBALS['emcp_test_user_can'] = false;
ok( false === EMCP_Tools_Block_Store::user_has_access(), 'expected false without manage_options' );
$GLOBALS['emcp_test_user_can'] = true;
echo "PASS: user_has_access() respects the capability gate.\n";

echo "\n=== TEST 3: summary() maps post status to active/draft row ===\n";
$GLOBALS['emcp_test_posts'] = array();
$GLOBALS['emcp_test_meta']  = array();
$GLOBALS['emcp_test_posts'][11] = (object) array(
	'ID' => 11, 'post_type' => 'emcp_block', 'post_title' => 'Cta Card',
	'post_status' => 'publish', 'post_modified' => '2026-09-01 10:00:00',
);
$GLOBALS['emcp_test_meta'][11]['_emcp_block_name']  = 'cta-card';
$GLOBALS['emcp_test_meta'][11]['_emcp_last_error']  = 'render fatal';
$GLOBALS['emcp_test_posts'][12] = (object) array(
	'ID' => 12, 'post_type' => 'emcp_block', 'post_title' => 'Draft Block',
	'post_status' => 'draft', 'post_modified' => '2026-09-01 11:00:00',
);
$row = $STORE->summary( 11 );
ok( ! is_wp_error( $row ), 'summary(11) should be a row' );
ok( 11 === $row['block_id'], 'block_id mismatch' );
ok( 'cta-card' === $row['block_name'], 'block_name mismatch' );
ok( 'active' === $row['status'], 'publish should map to active' );
ok( 'render fatal' === $row['last_error'], 'last_error mismatch' );
$row2 = $STORE->summary( 12 );
ok( 'draft' === $row2['status'], 'draft should map to draft' );
ok( is_wp_error( $STORE->summary( 999 ) ), 'unknown id should be WP_Error' );
echo "PASS: summary() row shape and status mapping correct.\n";

echo "\n=== TEST 4: overview.php renders (the previously-fatal file) ===\n";
$_GET = array();
ob_start();
try {
	include __DIR__ . '/../includes/admin/views/sandbox/overview.php';
	$html = (string) ob_get_clean();
	ok( false !== strpos( $html, 'Sandbox' ), 'overview output missing Sandbox heading' );
	ok( false !== strpos( $html, 'Blocks' ), 'overview output missing Blocks card' );
	echo "PASS: overview.php rendered with no fatal.\n";
} catch ( Throwable $e ) {
	ob_end_clean();
	throw $e;
}

echo "\n=== TEST 5: get_asset() reads whitelisted block files from the sandbox ===\n";
$tmp = sys_get_temp_dir() . '/emcp-test-sandbox';
rm_rf( $tmp );
add_filter( 'emcp_tools_sandbox_dir', function () use ( $tmp ) { return $tmp; } );
$artifact = $STORE->artifact_dir( 123 );
ok( wp_mkdir_p( $artifact ), 'could not create temp artifact dir' );
file_put_contents( $artifact . '/block.json', '{"name":"x"}' );
file_put_contents( $artifact . '/render.php', '<?php // render' );
ok( '{"name":"x"}' === $STORE->get_asset( 123, 'block.json' ), 'block.json content mismatch' );
ok( '<?php // render' === $STORE->get_asset( 123, 'render.php' ), 'render.php content mismatch' );
ok( '' === $STORE->get_asset( 123, 'index.php' ), 'out-of-whitelist filename must return empty' );
ok( '' === $STORE->get_asset( 999, 'block.json' ), 'missing artifact must return empty' );
echo "PASS: get_asset() round-trips whitelisted files only.\n";

echo "\n=== TEST 6: apply_bundle() → create_block() creates without fatal ===\n";
// The generator is deferred to the MCP surface in the real build; stub it so
// create()'s new class_exists guard has something to pass.
if ( ! class_exists( 'EMCP_Tools_Block_Generator' ) ) {
	class EMCP_Tools_Block_Generator {
		public static function validate( $spec ) { return true; }
		public static function generate( $spec, $slug ) {
			return array(
				'block_json' => wp_json_encode( array( 'name' => $slug ) ),
				'render_php' => '<?php // generated',
			);
		}
	}
}
$before = count( $GLOBALS['emcp_test_posts'] );
$new_id = $STORE->apply_bundle(
	array(
		'meta' => array( 'title' => 'Imported Block' ),
		'spec' => array( 'name' => 'imported', 'title' => 'Imported Block' ),
	)
);
ok( ! is_wp_error( $new_id ), 'apply_bundle() returned an error: ' . ( is_wp_error( $new_id ) ? $new_id->get_error_message() : '' ) );
ok( is_int( $new_id ) && $new_id > 0, 'apply_bundle() should return a new post id' );
ok( count( $GLOBALS['emcp_test_posts'] ) === $before + 1, 'expected one block post created' );
// Import lands as a draft (inactive) by default.
$created = $GLOBALS['emcp_test_posts'][ $new_id ] ?? null;
ok( $created && 'draft' === $created->post_status, 'import should land as a draft' );
echo "PASS: apply_bundle() creates a draft block (post $new_id) with no fatal.\n";

echo "\n=== TEST 7: list_blocks() / list_blocks_page() return the admin shapes ===\n";
$GLOBALS['emcp_test_posts'] = array();
$GLOBALS['emcp_test_meta']  = array();
ok( is_array( $STORE->list_blocks( 'any' ) ), 'list_blocks(any) should be an array' );
ok( is_array( $STORE->list_blocks( 'active' ) ), 'list_blocks(active) should be an array' );
$paged = $STORE->list_blocks_page( 'any', 1 );
foreach ( array( 'items', 'total', 'page', 'pages', 'per_page' ) as $key ) {
	ok( array_key_exists( $key, $paged ), "list_blocks_page() missing '$key' key" );
}
ok( is_array( $paged['items'] ), 'paged items should be an array' );
ok( EMCP_Tools_Sandbox_List_Query::PER_PAGE === $paged['per_page'], 'per_page should default to the shared constant' );
echo "PASS: list_blocks / list_blocks_page return the shapes blocks.php consumes.\n";

echo "\nAll sandbox-block suites passed.\n";
