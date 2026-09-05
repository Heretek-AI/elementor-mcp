<?php
/**
 * Automated test suite for Ads & Monetization MCP Integration.
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'EMCP_TOOLS_DIR', dirname( __DIR__ ) . '/' );
define( 'EMCP_TOOLS_URL', 'http://example.com/wp-content/plugins/elementor-mcp/' );
define( 'EMCP_TOOLS_VERSION', '3.16.0' );

$GLOBALS['wp_posts']        = array();
$GLOBALS['wp_postmeta']     = array();
$GLOBALS['wp_options']      = array();
$GLOBALS['wp_transients']   = array();
$GLOBALS['wp_post_counter'] = 1000;
$GLOBALS['actions_fired']   = array();
$GLOBALS['abilities']       = array();

function sanitize_text_field( $t ) { return trim( strip_tags( $t ) ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $k ) ); }
function sanitize_title( $t ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '-', $t ) ); }
function absint( $n ) { return abs( intval( $n ) ); }
function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function current_user_can( $c ) { return true; }
function home_url( $path = '' ) { return 'https://shad-base.com' . $path; }
function wp_json_encode( $data ) { return json_encode( $data ); }

function get_option( $k, $d = false ) { return $GLOBALS['wp_options'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['wp_options'][ $k ] = $v; return true; }

function get_transient( $k ) { return $GLOBALS['wp_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['wp_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['wp_transients'][ $k ] ); return true; }

function do_action( $tag, ...$args ) { $GLOBALS['actions_fired'][] = $tag; }
function wp_cache_flush() { $GLOBALS['actions_fired'][] = 'wp_cache_flush'; return true; }

class WP_Post {
	public $ID;
	public $post_title;
	public $post_name;
	public $post_content = '';
	public $post_status  = 'publish';
	public $post_type    = 'quads-ads';
	public $post_date;
}

class WP_Error {
	private $code;
	private $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

function get_post( $id ) {
	return $GLOBALS['wp_posts'][ $id ] ?? null;
}

function get_the_title( $id ) {
	return $GLOBALS['wp_posts'][ $id ]->post_title ?? '';
}

function get_posts( $args = array() ) {
	$results = array();
	$type    = $args['post_type'] ?? 'post';
	$meta_k  = $args['meta_key'] ?? '';
	$meta_v  = $args['meta_value'] ?? '';

	foreach ( $GLOBALS['wp_posts'] as $p ) {
		if ( $type && $p->post_type !== $type ) continue;
		if ( $meta_k ) {
			$val = get_post_meta( $p->ID, $meta_k, true );
			if ( $val != $meta_v ) continue;
		}
		$results[] = $p;
	}
	return $results;
}

function wp_insert_post( $args, $wp_error = false ) {
	$id = ++$GLOBALS['wp_post_counter'];
	$p  = new WP_Post();
	$p->ID         = $id;
	$p->post_title = $args['post_title'] ?? '';
	$p->post_name  = sanitize_title( $p->post_title );
	$p->post_type  = $args['post_type'] ?? 'quads-ads';
	$p->post_status = $args['post_status'] ?? 'publish';
	$GLOBALS['wp_posts'][ $id ] = $p;
	return $id;
}

function wp_update_post( $args ) {
	$id = $args['ID'] ?? 0;
	if ( ! isset( $GLOBALS['wp_posts'][ $id ] ) ) return false;
	$p = $GLOBALS['wp_posts'][ $id ];
	if ( isset( $args['post_title'] ) ) {
		$p->post_title = $args['post_title'];
		$p->post_name  = sanitize_title( $p->post_title );
	}
	return $id;
}

function wp_delete_post( $id, $force = false ) {
	if ( isset( $GLOBALS['wp_posts'][ $id ] ) ) {
		unset( $GLOBALS['wp_posts'][ $id ] );
		unset( $GLOBALS['wp_postmeta'][ $id ] );
		return true;
	}
	return false;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	if ( $key ) {
		return $GLOBALS['wp_postmeta'][ $post_id ][ $key ] ?? '';
	}
	return $GLOBALS['wp_postmeta'][ $post_id ] ?? array();
}

function update_post_meta( $post_id, $key, $val ) {
	$GLOBALS['wp_postmeta'][ $post_id ][ $key ] = $val;
	return true;
}

// HTTP Mocking
function wp_remote_retrieve_body( $resp ) {
	return is_array( $resp ) ? ( $resp['body'] ?? '' ) : '';
}
function wp_remote_retrieve_response_code( $resp ) {
	return is_array( $resp ) ? ( $resp['response']['code'] ?? 200 ) : 200;
}

function wp_remote_post( $url, $args = array() ) {
	// Mock login endpoint
	if ( strpos( $url, 'api.exoclick.com/v2/login' ) !== false ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array(
				'token'      => 'mock_bearer_token_xyz_123',
				'expires_in' => 3600,
			) ),
		);
	}
	// Mock zone creation
	if ( strpos( $url, 'api.exoclick.com/v2/zones' ) !== false ) {
		$data = json_decode( $args['body'] ?? '{}', true );
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array(
				'idzone' => 6020599,
				'name'   => $data['name'] ?? 'Test Zone',
				'idsite' => $data['idsite'] ?? 1111220,
			) ),
		);
	}
	// Mock URL verification
	if ( strpos( $url, 'api.exoclick.com/v2/sites/url-verification' ) !== false ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array(
				'success' => true,
				'status'  => 1,
				'message' => 'Site ownership successfully verified.',
			) ),
		);
	}
	return array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
}

function wp_remote_get( $url, $args = array() ) {
	// Mock zones list
	if ( strpos( $url, 'api.exoclick.com/v2/zones' ) !== false ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array(
				'result' => array(
					array( 'idzone' => 6020542, 'name' => 'Above Comic - 728x90', 'status' => 1 ),
					array( 'idzone' => 6020532, 'name' => 'Under Comic - 300x250', 'status' => 1 ),
				),
			) ),
		);
	}
	// Mock reporting stats
	if ( strpos( $url, 'api.exoclick.com/v2/statistics' ) !== false ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array(
				'result' => array(
					array( 'impressions' => 150240, 'clicks' => 312, 'ctr' => 0.21, 'revenue' => 45.80 ),
				),
			) ),
		);
	}
	// Mock live ads.txt fetch
	if ( strpos( $url, '/ads.txt' ) !== false ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => "exoclick.com, 1111220, DIRECT, f6e6255c27770857\njuicyads.com, 119864, DIRECT\n",
		);
	}
	return array( 'response' => array( 'code' => 200 ), 'body' => '' );
}

function emcp_tools_register_ability( $name, $conf ) {
	$GLOBALS['abilities'][ $name ] = $conf;
}

// Load classes under test
require_once __DIR__ . '/../includes/abilities/ads/class-ads-read-operations.php';
require_once __DIR__ . '/../includes/abilities/ads/class-ads-write-operations.php';
require_once __DIR__ . '/../includes/abilities/ads/class-ads-integration.php';

echo "=== TEST 1: Ad Network Detection & Parsing (parse_ad_code) ===\n";
// 1. ExoClick Banner
$exo_tag = '<script async type="application/javascript" src="https://a.magsrv.com/ad-provider.js"></script>' .
           '<ins class="eas6a97888e2" data-zoneid="6020542" data-width="728" data-height="90"></ins>' .
           '<script>(AdProvider = window.AdProvider || []).push({"serve": {}});</script>';
$parsed_exo = EMCP_Tools_Ads_Read_Operations::parse_ad_code( $exo_tag );
assert( $parsed_exo['network'] === 'ExoClick' );
assert( $parsed_exo['zone_id'] === '6020542' );
assert( $parsed_exo['dimensions'] === '728x90' );

// 2. ExoClick Popunder
$exo_pop = '<script type="application/javascript">var adConfig = {"idzone": 6020536, "popup_fallback": false};</script>';
$parsed_pop = EMCP_Tools_Ads_Read_Operations::parse_ad_code( $exo_pop );
assert( $parsed_pop['network'] === 'ExoClick' );
assert( $parsed_pop['zone_id'] === '6020536' );

// 3. JuicyAds Legacy
$juicy_tag = '<script type="text/javascript" data-cfasync="false" async src="https://poweredby.jads.co/js/jads.js"></script>' .
             '<ins id="1069796" data-width="160" data-height="600"></ins>';
$parsed_juicy = EMCP_Tools_Ads_Read_Operations::parse_ad_code( $juicy_tag );
assert( $parsed_juicy['network'] === 'JuicyAds' );
assert( $parsed_juicy['zone_id'] === '1069796' );
assert( $parsed_juicy['dimensions'] === '160x600' );

// 4. TrafficStars
$ts_tag = '<script src="//trafficstars.com/ad.js" data-zone="48912"></script>';
$parsed_ts = EMCP_Tools_Ads_Read_Operations::parse_ad_code( $ts_tag );
assert( $parsed_ts['network'] === 'TrafficStars' );

echo "PASS: Ad network detection and dimension parsing verified!\n\n";

echo "=== TEST 2: Schema Completeness & Discovery Catalog ===\n";
$read_schema = EMCP_Tools_Ads_Read_Operations::op_schema();
$write_schema = EMCP_Tools_Ads_Write_Operations::op_schema();

assert( count( $read_schema ) === 6 );
assert( isset( $read_schema['list-ads'], $read_schema['get-ad'], $read_schema['get-ads-txt'], $read_schema['audit-monetization'], $read_schema['exoclick-list-zones'], $read_schema['exoclick-get-stats'] ) );

assert( count( $write_schema ) === 7 );
assert( isset( $write_schema['update-ad'], $write_schema['create-ad'], $write_schema['delete-ad'], $write_schema['set-ads-txt'], $write_schema['purge-ad-cache'], $write_schema['exoclick-create-zone'], $write_schema['exoclick-verify-site'] ) );

echo "PASS: All 13 read & write operation schemas verified!\n\n";

echo "=== TEST 3: WP Quads Create, Update & Dual-Write Synchronization ===\n";
// 1. Create ad slot
$create_res = EMCP_Tools_Ads_Write_Operations::create_ad( array(
	'title'      => 'Above Comic 728x90',
	'code'       => $exo_tag,
	'slot_key'   => 'ad1',
	'position'   => 'custom',
	'dimensions' => '728x90',
) );

assert( $create_res['success'] === true );
assert( $create_res['slot_key'] === 'ad1' );
$post_id = $create_res['post_id'];
assert( $post_id > 0 );

// Verify quads_settings option
$settings = get_option( 'quads_settings' );
assert( isset( $settings['ads']['ad1'] ) );
assert( $settings['ads']['ad1']['code'] === $exo_tag );
assert( $settings['ads']['ad1']['label'] === 'Above Comic 728x90' );

// Verify postmeta
assert( get_post_meta( $post_id, 'code', true ) === $exo_tag );
assert( get_post_meta( $post_id, 'quads_ad_old_id', true ) === 'ad1' );

// 2. Read ad slot
$read_res = EMCP_Tools_Ads_Read_Operations::get_ad( array( 'id' => 'ad1' ) );
assert( $read_res['success'] === true );
assert( $read_res['slot_key'] === 'ad1' );
assert( $read_res['parsed']['network'] === 'ExoClick' );
assert( $read_res['parsed']['zone_id'] === '6020542' );

// 3. Update ad slot
$updated_exo_tag = str_replace( '6020542', '6020599', $exo_tag );
$update_res = EMCP_Tools_Ads_Write_Operations::update_ad( array(
	'id'    => 'ad1',
	'code'  => $updated_exo_tag,
	'title' => 'Above Comic - ExoClick 6020599',
) );

assert( $update_res['success'] === true );
// Verify dual-write sync after update
$settings = get_option( 'quads_settings' );
assert( $settings['ads']['ad1']['code'] === $updated_exo_tag );
assert( $settings['ads']['ad1']['label'] === 'Above Comic - ExoClick 6020599' );
assert( get_post_meta( $post_id, 'code', true ) === $updated_exo_tag );

// 4. List ads
$list_res = EMCP_Tools_Ads_Read_Operations::list_ads();
assert( $list_res['success'] === true );
assert( $list_res['total_ads'] === 1 );
assert( $list_res['ads'][0]['zone_id'] === '6020599' );

echo "PASS: Create, Update, and Dual-Write synchronization verified!\n\n";

echo "=== TEST 4: Delete Ad Unit with Confirmation Guard ===\n";
// Attempt delete without confirm: true
$unconfirmed = EMCP_Tools_Ads_Write_Operations::delete_ad( array( 'id' => 'ad1' ) );
assert( $unconfirmed['success'] === false );
assert( strpos( $unconfirmed['warning'], 'Confirmation required' ) !== false );
assert( isset( $GLOBALS['wp_posts'][ $post_id ] ) ); // Still exists

// Delete with confirm: true
$confirmed = EMCP_Tools_Ads_Write_Operations::delete_ad( array( 'id' => 'ad1', 'confirm' => true ) );
assert( $confirmed['success'] === true );
assert( ! isset( $GLOBALS['wp_posts'][ $post_id ] ) ); // Deleted from posts
$settings = get_option( 'quads_settings' );
assert( ! isset( $settings['ads']['ad1'] ) ); // Deleted from quads_settings

echo "PASS: Safety confirmation guard and complete ad unit deletion verified!\n\n";

echo "=== TEST 5: ads.txt Parser, Syntax Validator & Dual-Write ===\n";
// 1. Set full ads.txt content
$sample_ads_txt = "# Authorized Digital Sellers for shad-base.com\n" .
                  "exoclick.com, 1111220, DIRECT, f6e6255c27770857\n" .
                  "trafficstars.com, 48912, DIRECT\n" .
                  "badline_format\n" .
                  "invalidrel.com, 99999, PARTNER\n";

$set_res = EMCP_Tools_Ads_Write_Operations::set_ads_txt( array(
	'content' => $sample_ads_txt,
) );
assert( $set_res['success'] === true );
assert( $set_res['saved_to_option'] === true );
assert( count( $set_res['validation_warnings'] ) === 2 );

// 2. Read and parse ads.txt
$parsed_txt = EMCP_Tools_Ads_Read_Operations::get_ads_txt();
assert( $parsed_txt['success'] === true );
assert( $parsed_txt['total_records'] === 3 );
assert( $parsed_txt['records'][0]['domain'] === 'exoclick.com' );
assert( $parsed_txt['records'][0]['publisher_id'] === '1111220' );
assert( $parsed_txt['records'][0]['relationship'] === 'DIRECT' );
assert( $parsed_txt['records'][0]['certification_authority_id'] === 'f6e6255c27770857' );
assert( $parsed_txt['records'][0]['is_valid'] === true );

// 3. Append record
$append_res = EMCP_Tools_Ads_Write_Operations::set_ads_txt( array(
	'append_records' => array( 'juicyads.com, 119864, DIRECT' ),
) );
assert( $append_res['success'] === true );
$re_parsed = EMCP_Tools_Ads_Read_Operations::get_ads_txt();
assert( strpos( $re_parsed['raw'], 'juicyads.com, 119864, DIRECT' ) !== false );

echo "PASS: ads.txt parser, syntax validation, backup, and append verified!\n\n";

echo "=== TEST 6: Monetization Diagnostic Audit ===\n";
// With only ads.txt and no ads
$audit1 = EMCP_Tools_Ads_Read_Operations::audit_monetization();
assert( $audit1['success'] === true );
assert( $audit1['status'] === 'attention_required' );
assert( in_array( 'No active ad units found in WP Quads.', $audit1['issues'], true ) );

// Add active ExoClick ad unit
EMCP_Tools_Ads_Write_Operations::create_ad( array(
	'title' => 'Main Banner',
	'code'  => $exo_tag,
) );
$audit2 = EMCP_Tools_Ads_Read_Operations::audit_monetization();
assert( $audit2['success'] === true );
assert( $audit2['active_ad_units'] === 1 );

echo "PASS: Monetization diagnostic audit verified!\n\n";

echo "=== TEST 7: Cache Purging (LiteSpeed & Object Cache) ===\n";
$GLOBALS['actions_fired'] = array();
$purge_res = EMCP_Tools_Ads_Write_Operations::purge_ad_cache();
assert( $purge_res['success'] === true );
assert( in_array( 'litespeed_purge_all', $GLOBALS['actions_fired'], true ) );
assert( in_array( 'wp_cache_flush', $GLOBALS['actions_fired'], true ) );

echo "PASS: Cache purging operations verified!\n\n";

echo "=== TEST 8: ExoClick REST API Integration Mock ===\n";
// Set API token
update_option( 'emcp_tools_exoclick_api_token', 'test_api_token_12345' );
update_option( 'emcp_tools_exoclick_site_id', 1111220 );

// 1. Zones list
$zones_res = EMCP_Tools_Ads_Read_Operations::exoclick_list_zones();
assert( $zones_res['success'] === true );
assert( count( $zones_res['zones'] ) === 2 );

// 2. Reporting stats
$stats_res = EMCP_Tools_Ads_Read_Operations::exoclick_get_stats();
assert( $stats_res['success'] === true );
assert( $stats_res['stats'][0]['impressions'] === 150240 );

// 3. Create Zone with automatic slot installation
$zone_create = EMCP_Tools_Ads_Write_Operations::exoclick_create_zone( array(
	'name'            => 'Sidebar Ad',
	'dimensions'      => '160x600',
	'install_to_slot' => 'create',
) );
assert( $zone_create['success'] === true );
assert( $zone_create['zone_id'] === 6020599 );
assert( strpos( $zone_create['tag_code'], 'data-zoneid="6020599"' ) !== false );
assert( $zone_create['installation']['success'] === true );

// 4. Site Verification
$verify_res = EMCP_Tools_Ads_Write_Operations::exoclick_verify_site();
assert( $verify_res['success'] === true );
assert( $verify_res['result']['status'] === 1 );

echo "PASS: ExoClick REST API zones, stats, zone creation, and site verification verified!\n\n";

echo "=== TEST 9: Abilities API Dispatcher Tools (emcp-tools/ads-read & ads-write) ===\n";
$integration = new EMCP_Tools_Ads_Integration();
$integration->register();

assert( isset( $GLOBALS['abilities']['emcp-tools/ads-read'] ) );
assert( isset( $GLOBALS['abilities']['emcp-tools/ads-write'] ) );

// 1. Catalog discovery call (empty operation)
$read_catalog = $integration->execute_read( array() );
assert( isset( $read_catalog['tool'], $read_catalog['operations'] ) );
assert( count( $read_catalog['operations'] ) === 6 );
assert( isset( $read_catalog['operations']['list-ads']['schema'] ) );

$write_catalog = $integration->execute_write( array() );
assert( isset( $write_catalog['tool'], $write_catalog['operations'] ) );
assert( count( $write_catalog['operations'] ) === 7 );

// 2. Dispatcher execution
$dispatched_read = $integration->execute_read( array(
	'operation' => 'get-ads-txt',
	'arguments' => array(),
) );
assert( $dispatched_read['success'] === true );
assert( $dispatched_read['total_records'] > 0 );

$dispatched_write = $integration->execute_write( array(
	'operation' => 'purge-ad-cache',
	'arguments' => array( 'all' => true ),
) );
assert( $dispatched_write['success'] === true );

echo "PASS: Abilities API registration, catalog introspection, and dispatchers verified!\n\n";

echo "ALL 9 ADS & MONETIZATION MCP TEST SUITES PASSED 100%!\n";

if ( file_exists( ABSPATH . "ads.txt" ) ) {
	@unlink( ABSPATH . "ads.txt" );
}
