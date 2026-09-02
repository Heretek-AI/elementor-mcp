<?php
/**
 * Automated test suite for Comic Easel MCP Integration.
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'EMCP_TOOLS_DIR', dirname( __DIR__ ) . '/' );
define( 'EMCP_TOOLS_URL', 'http://example.com/wp-content/plugins/elementor-mcp/' );
define( 'EMCP_TOOLS_VERSION', '3.15.0' );

$GLOBALS['wp_posts']              = array();
$GLOBALS['wp_postmeta']           = array();
$GLOBALS['wp_terms']              = array();
$GLOBALS['wp_term_taxonomy']      = array();
$GLOBALS['wp_term_relationships'] = array();
$GLOBALS['wp_options']            = array();
$GLOBALS['wp_post_counter']       = 100;
$GLOBALS['wp_term_counter']       = 50;

function wp_kses_post( $c ) { return $c; }
function wp_kses( $c, $a ) { return $c; }
function sanitize_text_field( $t ) { return trim( strip_tags( $t ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( $t ) ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $k ) ); }
function sanitize_title( $t ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '-', $t ) ); }
function sanitize_file_name( $f ) { return $f; }
function esc_url( $u ) { return htmlspecialchars( $u ); }
function esc_url_raw( $u ) { return $u; }
function esc_attr( $a ) { return htmlspecialchars( $a ); }
function absint( $n ) { return abs( intval( $n ) ); }
function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function current_user_can( $c ) { return true; }
function get_option( $k, $d = false ) { return $GLOBALS['wp_options'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['wp_options'][ $k ] = $v; return true; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }

class WP_Post {
	public $ID;
	public $post_title;
	public $post_name;
	public $post_content;
	public $post_excerpt = '';
	public $post_status  = 'publish';
	public $post_type    = 'comic';
	public $post_date;
	public $post_date_gmt;
	public $post_modified;
	public $post_author  = 1;
}

class WP_Term {
	public $term_id;
	public $name;
	public $slug;
	public $term_group = 0;
	public $term_taxonomy_id;
	public $taxonomy;
	public $description = '';
	public $parent      = 0;
	public $count       = 0;
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

function wp_insert_post( $args, $wp_error = false ) {
	$id = ++$GLOBALS['wp_post_counter'];
	$p  = new WP_Post();
	$p->ID            = $id;
	$p->post_title    = $args['post_title'] ?? '';
	$p->post_name     = sanitize_title( $p->post_title );
	$p->post_content  = $args['post_content'] ?? '';
	$p->post_status   = $args['post_status'] ?? 'publish';
	$p->post_type     = $args['post_type'] ?? 'comic';
	$p->post_date     = $args['post_date'] ?? date( 'Y-m-d H:i:s' );
	$p->post_date_gmt = $p->post_date;
	$p->post_modified = $p->post_date;
	$GLOBALS['wp_posts'][ $id ] = $p;
	return $id;
}

function wp_update_post( $args ) {
	$id = $args['ID'] ?? 0;
	if ( ! isset( $GLOBALS['wp_posts'][ $id ] ) ) return false;
	$p = $GLOBALS['wp_posts'][ $id ];
	if ( isset( $args['post_title'] ) ) { $p->post_title = $args['post_title']; $p->post_name = sanitize_title( $p->post_title ); }
	if ( isset( $args['post_content'] ) ) $p->post_content = $args['post_content'];
	if ( isset( $args['post_status'] ) ) $p->post_status = $args['post_status'];
	if ( isset( $args['post_date'] ) ) $p->post_date = $args['post_date'];
	return $id;
}

function wp_delete_post( $id, $force = false ) {
	if ( isset( $GLOBALS['wp_posts'][ $id ] ) ) {
		unset( $GLOBALS['wp_posts'][ $id ] );
		return true;
	}
	return false;
}
function wp_trash_post( $id ) {
	if ( isset( $GLOBALS['wp_posts'][ $id ] ) ) {
		$GLOBALS['wp_posts'][ $id ]->post_status = 'trash';
		return true;
	}
	return false;
}

function get_the_title( $id ) { return $GLOBALS['wp_posts'][ $id ]->post_title ?? ''; }
function get_permalink( $id ) { return 'https://example.com/comic/' . ( $GLOBALS['wp_posts'][ $id ]->post_name ?? $id ) . '/'; }

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

function get_post_thumbnail_id( $id ) { return intval( $GLOBALS['wp_postmeta'][ $id ]['_thumbnail_id'] ?? 0 ); }
function set_post_thumbnail( $id, $thumb_id ) { update_post_meta( $id, '_thumbnail_id', $thumb_id ); }

function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
	return array( "https://example.com/uploads/img-$id.jpg", 1536, 2048, false );
}
function download_url( $url, $timeout = 300 ) {
	return '/tmp/mock-image.jpg';
}
function media_handle_sideload( $file_array, $post_id = 0, $desc = null, $post_data = array() ) {
	return ++$GLOBALS['wp_post_counter'];
}

function wp_insert_term( $name, $tax, $args = array() ) {
	$tid = ++$GLOBALS['wp_term_counter'];
	$t   = new WP_Term();
	$t->term_id     = $tid;
	$t->name        = $name;
	$t->slug        = $args['slug'] ?? sanitize_title( $name );
	$t->taxonomy    = $tax;
	$t->parent      = $args['parent'] ?? 0;
	$t->description = $args['description'] ?? '';
	$GLOBALS['wp_terms'][ $tax ][ $tid ] = $t;
	return array( 'term_id' => $tid, 'term_taxonomy_id' => $tid );
}

function get_term( $id, $tax = '' ) {
	foreach ( $GLOBALS['wp_terms'] as $t_tax => $terms ) {
		if ( isset( $terms[ $id ] ) ) return $terms[ $id ];
	}
	return null;
}
function get_term_by( $field, $val, $tax = '' ) {
	foreach ( $GLOBALS['wp_terms'][ $tax ] ?? array() as $t ) {
		if ( $t->$field == $val ) return $t;
	}
	return false;
}
function get_terms( $args ) {
	$tax = $args['taxonomy'] ?? 'chapters';
	return array_values( $GLOBALS['wp_terms'][ $tax ] ?? array() );
}
function get_the_terms( $id, $tax ) {
	$rel = $GLOBALS['wp_term_relationships'][ $id ][ $tax ] ?? array();
	$terms = array();
	foreach ( $rel as $tid ) {
		if ( isset( $GLOBALS['wp_terms'][ $tax ][ $tid ] ) ) {
			$terms[] = $GLOBALS['wp_terms'][ $tax ][ $tid ];
		}
	}
	return $terms;
}
function wp_get_object_terms( $id, $tax, $args = array() ) {
	$rel = $GLOBALS['wp_term_relationships'][ $id ][ $tax ] ?? array();
	if ( ( $args['fields'] ?? '' ) === 'ids' ) return $rel;
	return get_the_terms( $id, $tax );
}
function wp_set_object_terms( $id, $tids, $tax ) {
	$GLOBALS['wp_term_relationships'][ $id ][ $tax ] = (array) $tids;
}
function update_term_meta( $id, $k, $v ) { return true; }
function get_term_link( $t ) { return "https://example.com/{$t->taxonomy}/{$t->slug}/"; }
function wp_delete_term( $id, $tax ) {
	unset( $GLOBALS['wp_terms'][ $tax ][ $id ] );
	return true;
}
function wp_update_term( $id, $tax, $args ) {
	if ( isset( $GLOBALS['wp_terms'][ $tax ][ $id ] ) ) {
		if ( isset( $args['name'] ) ) $GLOBALS['wp_terms'][ $tax ][ $id ]->name = $args['name'];
		if ( isset( $args['slug'] ) ) $GLOBALS['wp_terms'][ $tax ][ $id ]->slug = $args['slug'];
		return array( 'term_id' => $id );
	}
	return new WP_Error( 'not_found', 'Term not found' );
}

class WP_Query {
	public $posts = array();
	public $found_posts = 0;
	public $max_num_pages = 1;
	public function __construct( $args = array() ) {
		$matches = array();
		$type = $args['post_type'] ?? 'comic';
		foreach ( $GLOBALS['wp_posts'] as $p ) {
			if ( $p->post_type !== $type ) continue;
			if ( isset( $args['post_status'] ) && $args['post_status'] !== 'any' && $p->post_status !== $args['post_status'] ) continue;
			if ( ! empty( $args['meta_query'] ) ) {
				$matched_meta = false;
				foreach ( $args['meta_query'] as $mq ) {
					if ( ! is_array( $mq ) || ! isset( $mq['key'] ) ) continue;
					$val = get_post_meta( $p->ID, $mq['key'], true );
					if ( $val == $mq['value'] ) {
						$matched_meta = true;
						break;
					}
				}
				if ( ! $matched_meta ) continue;
			}
			if ( ! empty( $args['tax_query'] ) ) {
				$matched_tax = false;
				foreach ( $args['tax_query'] as $tq ) {
					if ( ! is_array( $tq ) || ! isset( $tq['taxonomy'] ) ) continue;
					$tax_terms = wp_get_object_terms( $p->ID, $tq['taxonomy'], array( 'fields' => 'ids' ) );
					if ( ! empty( $tax_terms ) ) {
						$matched_tax = true;
						break;
					}
				}
				if ( ! $matched_tax ) continue;
			}
			$matches[] = $p;
		}
		$this->posts = $matches;
		$this->found_posts = count( $matches );
		$this->max_num_pages = 1;
	}
}

class MockDB {
	public $posts = 'wp_posts';
	public $terms = 'wp_terms';
	public $term_relationships = 'wp_term_relationships';
	public $term_taxonomy = 'wp_term_taxonomy';
	public function prepare( $sql, ...$args ) {
		foreach ( $args as $a ) {
			$sql = preg_replace( '/%[sd]/', "'$a'", $sql, 1 );
		}
		return $sql;
	}
	public function get_row( $sql ) { return null; }
	public function update( $t, $data, $where ) { return true; }
}
$GLOBALS['wpdb'] = new MockDB();

function post_type_exists( $t ) { return $t === 'comic'; }
function emcp_tools_register_ability( $name, $conf ) { $GLOBALS['abilities'][ $name ] = $conf; }

require_once __DIR__ . '/../includes/abilities/comic/class-comic-html-helper.php';
require_once __DIR__ . '/../includes/abilities/comic/class-comic-read-operations.php';
require_once __DIR__ . '/../includes/abilities/comic/class-comic-write-operations.php';
require_once __DIR__ . '/../includes/abilities/comic/class-comic-easel-integration.php';

echo "=== TEST 1: Comic HTML Helper (build, parse, append, sanitize) ===\n";
$images = array(
	1234,
	'https://shad-base.com/wp-content/uploads/2026/07/980-Sem-Titulo.png',
	array( 'url' => 'https://shad-base.com/wp-content/uploads/2026/07/981-Sem-Titulo.png', 'width' => 1536, 'height' => 2048, 'alt' => 'Page 3' ),
);
$html = EMCP_Tools_Comic_Html_Helper::build_html_below( $images );
echo "Generated HTML Below:\n$html\n\n";
assert( strpos( $html, 'wp-image-1234' ) !== false );
assert( strpos( $html, '980-Sem-Titulo.png' ) !== false );
assert( strpos( $html, 'width="1536"' ) !== false );

// Test parsing the exact markup observed on shad-base.com/comic/doodles/
$shad_markup = '<img src="https://shad-base.com/wp-content/uploads/2026/07/980-Sem-Titulo.png" alt="" width="1536" height="2048" class="alignnone size-full wp-image-15462" />
<img data-src="https://shad-base.com/wp-content/uploads/2026/07/981-Sem-Titulo_20260709003450.png" alt="Doodles page 3" width="1536" height="2048" class="alignnone size-full wp-image-15463" />';
$parsed = EMCP_Tools_Comic_Html_Helper::parse_html_below( $shad_markup );
echo 'Parsed ' . count( $parsed ) . " images from shad-base markup.\n";
assert( count( $parsed ) === 2 );
assert( $parsed[0]['attachment_id'] === 15462 );
assert( $parsed[0]['width'] === 1536 );
assert( $parsed[1]['attachment_id'] === 15463 );
assert( $parsed[1]['alt'] === 'Doodles page 3' );
echo "PASS: HTML Helper build and parse verified!\n\n";

echo "=== TEST 2: Create Multi-Image Comic with Source Metadata ===\n";
wp_insert_term( 'Luckyyzinto-Archive', 'chapters', array( 'slug' => 'luckyyzinto-archive' ) );

$comic_res = EMCP_Tools_Comic_Write_Operations::create_comic(
	array(
		'title'             => 'Doodles',
		'content'           => 'Sketch collection from July 2026',
		'featured_media_id' => 15461,
		'additional_images' => array(
			15462,
			array( 'url' => 'https://shad-base.com/wp-content/uploads/2026/07/981-Sem-Titulo_20260709003450.png', 'width' => 1536, 'height' => 2048, 'alt' => 'Doodles page 3' ),
		),
		'date'              => '2026-08-19T16:58:06.000Z',
		'source_tweet_id'   => '1829012345678901234',
		'source_url'        => 'https://x.com/luckyyzinto/status/1829012345678901234',
		'chapters'          => array( 'luckyyzinto-archive' ),
		'hovertext'         => 'Doodles sketches',
		'transcript'        => 'Character dialogue here...',
	)
);

assert( ! is_wp_error( $comic_res ) );
$created_id = $comic_res['id'];
echo "Created Comic ID: $created_id\n";
echo 'Total Pages: ' . $comic_res['total_pages'] . "\n";
assert( $comic_res['total_pages'] === 3 );
assert( $comic_res['date'] === '2026-08-19 16:58:06' );
assert( $comic_res['source']['source_tweet_id'] === '1829012345678901234' );
assert( $comic_res['source']['source_url'] === 'https://x.com/luckyyzinto/status/1829012345678901234' );
assert( ! empty( $comic_res['comic_html_below'] ) );
// Verify dual-write
assert( get_post_meta( $created_id, 'comic-html-below', true ) === get_post_meta( $created_id, 'ceo_html_below_comic', true ) );
echo "PASS: create_comic created 3-page comic with source tracking and dual-write meta!\n\n";

echo "=== TEST 3: Read Comic & Find By Source ===\n";
$read_res = EMCP_Tools_Comic_Read_Operations::get_comic( array( 'id' => $created_id ) );
assert( ! is_wp_error( $read_res ) );
assert( $read_res['title'] === 'Doodles' );
assert( count( $read_res['all_images'] ) === 3 );
assert( $read_res['all_images'][0]['is_featured'] === true );
assert( $read_res['all_images'][1]['is_featured'] === false );

// Find by source tweet ID
$find_res = EMCP_Tools_Comic_Read_Operations::find_by_source( array( 'source_tweet_id' => '1829012345678901234' ) );
assert( $find_res['found'] === true );
assert( $find_res['comic']['id'] === $created_id );
echo 'Found by source_tweet_id: ID ' . $find_res['comic']['id'] . "\n";

// Find by source URL
$find_res2 = EMCP_Tools_Comic_Read_Operations::find_by_source( array( 'source_url' => 'https://x.com/luckyyzinto/status/1829012345678901234' ) );
assert( $find_res2['found'] === true );
assert( $find_res2['comic']['id'] === $created_id );
echo 'Found by source_url: ID ' . $find_res2['comic']['id'] . "\n";

// Find nonexistent source
$find_empty = EMCP_Tools_Comic_Read_Operations::find_by_source( array( 'source_tweet_id' => '999999999999' ) );
assert( $find_empty['found'] === false );
echo "PASS: find_by_source verified for scrapers and idempotency!\n\n";

echo "=== TEST 4: Update Comic (Append Images & Set Source) ===\n";
$update_res = EMCP_Tools_Comic_Write_Operations::update_comic(
	array(
		'id'                => $created_id,
		'append_images'     => true,
		'additional_images' => array(
			array( 'url' => 'https://shad-base.com/wp-content/uploads/2026/07/982-Extra-Page.png', 'width' => 1536, 'height' => 2048, 'alt' => 'Extra page 4' ),
		),
	)
);
assert( ! is_wp_error( $update_res ) );
echo 'Updated Total Pages: ' . $update_res['total_pages'] . "\n";
assert( $update_res['total_pages'] === 4 );
echo "PASS: update_comic append_images verified!\n\n";

echo "=== TEST 5: Chapters & Taxonomies ===\n";
$new_chap = EMCP_Tools_Comic_Write_Operations::create_chapter(
	array(
		'name'        => 'Environmental Challenge',
		'slug'        => 'environmental-challenge',
		'description' => 'Fanmade sexy comic story arc',
		'menu_order'  => 5,
	)
);
assert( ! is_wp_error( $new_chap ) );
echo 'Created Chapter ID: ' . $new_chap['id'] . ' (' . $new_chap['name'] . ")\n";

$chapters_list = EMCP_Tools_Comic_Read_Operations::list_chapters();
assert( count( $chapters_list ) >= 2 );
echo 'Listed ' . count( $chapters_list ) . " chapters.\n";
echo "PASS: Chapters and taxonomies verified!\n\n";

echo "=== TEST 6: Integration Dispatcher Tools ===\n";
$integration = new EMCP_Tools_Comic_Easel_Integration();
assert( EMCP_Tools_Comic_Easel_Integration::is_active() === true );
$integration->register();
assert( isset( $GLOBALS['abilities']['emcp-tools/comic-read'] ) );
assert( isset( $GLOBALS['abilities']['emcp-tools/comic-write'] ) );

// Test catalog response on empty operation
$catalog_read = $integration->execute_read( array() );
assert( isset( $catalog_read['operations']['get-comic'] ) );
assert( isset( $catalog_read['operations']['find-by-source'] ) );

$catalog_write = $integration->execute_write( array( 'operation' => '' ) );
assert( isset( $catalog_write['operations']['create-comic'] ) );
assert( isset( $catalog_write['operations']['update-comic'] ) );

// Test dispatcher execution
$dispatched = $integration->execute_read(
	array(
		'operation' => 'get-comic',
		'arguments' => array( 'id' => $created_id ),
	)
);
assert( $dispatched['id'] === $created_id );
echo "PASS: Ability dispatchers verified!\n\n";

echo "=== TEST 7: Delete Comic with Confirm Guard ===\n";
$fail_del = EMCP_Tools_Comic_Write_Operations::delete_comic( array( 'id' => $created_id ) );
assert( is_wp_error( $fail_del ) ); // confirm missing
$ok_del = EMCP_Tools_Comic_Write_Operations::delete_comic( array( 'id' => $created_id, 'confirm' => true ) );
assert( ! is_wp_error( $ok_del ) );
assert( $ok_del['deleted'] === true );
echo "PASS: delete_comic confirm guard and execution verified!\n\n";

echo "ALL 7 COMIC EASEL MCP TEST SUITES PASSED 100%!\n";
