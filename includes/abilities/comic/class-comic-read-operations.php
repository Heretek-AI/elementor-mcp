<?php
/**
 * Comic Easel Read Operations.
 *
 * Implements read operations for the comic CPT, multi-image strips (comic-html-below),
 * source tracking (source_tweet_id, source_url), navigation, chapters, characters,
 * and locations.
 *
 * @package EMCP_Tools
 * @since   3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Comic_Read_Operations
 */
class EMCP_Tools_Comic_Read_Operations {

	/**
	 * Resolves the active comic post type slug.
	 *
	 * @return string
	 */
	public static function get_comic_slug(): string {
		if ( function_exists( 'ceo_pluginfo' ) ) {
			$slug = ceo_pluginfo( 'custom_post_type_slug_name' );
			if ( ! empty( $slug ) && is_string( $slug ) ) {
				return $slug;
			}
		}
		$cfg = get_option( 'comiceasel-config' );
		if ( is_array( $cfg ) && ! empty( $cfg['custom_post_type_slug_name'] ) && is_string( $cfg['custom_post_type_slug_name'] ) ) {
			return $cfg['custom_post_type_slug_name'];
		}
		return 'comic';
	}

	/**
	 * Get full details of a single comic post.
	 *
	 * @param array $args Arguments: { id?: int, slug?: string }
	 * @return array|\WP_Error
	 */
	public static function get_comic( array $args ) {
		$post = self::resolve_comic_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post_id = $post->ID;

		// Featured media (Page 1)
		$featured_id  = (int) get_post_thumbnail_id( $post_id );
		$featured_url = '';
		$featured_w   = null;
		$featured_h   = null;
		$featured_alt = '';

		if ( $featured_id > 0 && function_exists( 'wp_get_attachment_image_src' ) ) {
			$src = wp_get_attachment_image_src( $featured_id, 'full' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$featured_url = $src[0];
				$featured_w   = (int) $src[1];
				$featured_h   = (int) $src[2];
			}
			$featured_alt = (string) get_post_meta( $featured_id, '_wp_attachment_image_alt', true );
		}

		// Multi-image field (Pages 2..N)
		$html_below = (string) get_post_meta( $post_id, 'comic-html-below', true );
		if ( '' === $html_below ) {
			$html_below = (string) get_post_meta( $post_id, 'ceo_html_below_comic', true );
		}
		$additional_images = EMCP_Tools_Comic_Html_Helper::parse_html_below( $html_below );

		// Assemble all_images in reading sequence
		$all_images = array();
		if ( ! empty( $featured_url ) ) {
			$all_images[] = array(
				'page'          => 1,
				'src'           => $featured_url,
				'attachment_id' => $featured_id,
				'width'         => $featured_w,
				'height'        => $featured_h,
				'alt'           => $featured_alt,
				'is_featured'   => true,
			);
		}
		$page_num = count( $all_images ) + 1;
		foreach ( $additional_images as $img ) {
			$all_images[] = array(
				'page'          => $page_num++,
				'src'           => $img['src'],
				'attachment_id' => $img['attachment_id'],
				'width'         => $img['width'],
				'height'        => $img['height'],
				'alt'           => $img['alt'],
				'is_featured'   => false,
			);
		}

		// Taxonomies
		$chapters   = self::get_term_summaries( $post_id, 'chapters' );
		$characters = self::get_term_summaries( $post_id, 'characters' );
		$locations  = self::get_term_summaries( $post_id, 'locations' );
		$tags       = self::get_term_summaries( $post_id, 'post_tag' );

		// Navigation
		$nav = self::calculate_navigation( $post );

		// Author details
		$author_id   = (int) $post->post_author;
		$author_name = '';
		if ( function_exists( 'get_the_author_meta' ) ) {
			$author_name = (string) get_the_author_meta( 'display_name', $author_id );
		}

		return array(
			'id'                => $post_id,
			'slug'              => $post->post_name,
			'title'             => get_the_title( $post_id ),
			'url'               => get_permalink( $post_id ),
			'status'            => $post->post_status,
			'date'              => $post->post_date,
			'date_gmt'          => $post->post_date_gmt,
			'modified'          => $post->post_modified,
			'author'            => array(
				'id'   => $author_id,
				'name' => $author_name,
			),
			'content'           => $post->post_content,
			'excerpt'           => $post->post_excerpt,
			'featured_media'    => array(
				'id'     => $featured_id,
				'url'    => $featured_url,
				'width'  => $featured_w,
				'height' => $featured_h,
				'alt'    => $featured_alt,
			),
			'comic_html_below'  => $html_below,
			'additional_images' => $additional_images,
			'all_images'        => $all_images,
			'total_pages'       => count( $all_images ),
			'source'            => array(
				'source_tweet_id' => (string) get_post_meta( $post_id, 'source_tweet_id', true ),
				'source_url'      => (string) get_post_meta( $post_id, 'source_url', true ),
			),
			'comic_meta'        => array(
				'hovertext'       => (string) get_post_meta( $post_id, 'comic-hovertext', true ),
				'transcript'      => (string) get_post_meta( $post_id, 'transcript', true ),
				'content_warning' => (string) get_post_meta( $post_id, 'comic-content-warning', true ),
				'html_above'      => (string) get_post_meta( $post_id, 'comic-html-above', true ),
			),
			'taxonomies'        => array(
				'chapters'   => $chapters,
				'characters' => $characters,
				'locations'  => $locations,
				'tags'       => $tags,
			),
			'navigation'        => $nav,
		);
	}

	/**
	 * List comics matching filters.
	 *
	 * @param array $args Filter arguments.
	 * @return array
	 */
	public static function list_comics( array $args ): array {
		$slug      = self::get_comic_slug();
		$page      = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page  = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$status    = sanitize_key( (string) ( $args['status'] ?? 'publish' ) );
		$order     = strtoupper( (string) ( $args['order'] ?? 'ASC' ) ) === 'DESC' ? 'DESC' : 'ASC';
		$orderby   = sanitize_key( (string) ( $args['orderby'] ?? 'date' ) );
		$search    = sanitize_text_field( (string) ( $args['search'] ?? '' ) );

		$query_args = array(
			'post_type'      => $slug,
			'post_status'    => ( 'any' === $status ) ? 'any' : $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'order'          => $order,
			'orderby'        => $orderby,
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		// Taxonomy filters
		$tax_query = array();
		if ( ! empty( $args['chapter'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'chapters',
				'field'    => is_numeric( $args['chapter'] ) ? 'term_id' : 'slug',
				'terms'    => $args['chapter'],
			);
		}
		if ( ! empty( $args['character'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'characters',
				'field'    => is_numeric( $args['character'] ) ? 'term_id' : 'slug',
				'terms'    => $args['character'],
			);
		}
		if ( ! empty( $args['location'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'locations',
				'field'    => is_numeric( $args['location'] ) ? 'term_id' : 'slug',
				'terms'    => $args['location'],
			);
		}
		if ( ! empty( $args['tag'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'post_tag',
				'field'    => is_numeric( $args['tag'] ) ? 'term_id' : 'slug',
				'terms'    => $args['tag'],
			);
		}
		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$query_args['tax_query'] = $tax_query;
		}

		$query  = new \WP_Query( $query_args );
		$comics = array();

		foreach ( $query->posts as $p ) {
			$p_id         = $p->ID;
			$thumb_id     = (int) get_post_thumbnail_id( $p_id );
			$thumb_url    = '';
			if ( $thumb_id > 0 && function_exists( 'wp_get_attachment_image_src' ) ) {
				$src = wp_get_attachment_image_src( $thumb_id, 'medium' );
				if ( is_array( $src ) && ! empty( $src[0] ) ) {
					$thumb_url = $src[0];
				}
			}

			$html_below = (string) get_post_meta( $p_id, 'comic-html-below', true );
			$add_count  = count( EMCP_Tools_Comic_Html_Helper::parse_html_below( $html_below ) );
			$total_pgs  = ( ! empty( $thumb_url ) ? 1 : 0 ) + $add_count;

			$comics[] = array(
				'id'              => $p_id,
				'slug'            => $p->post_name,
				'title'           => get_the_title( $p_id ),
				'url'             => get_permalink( $p_id ),
				'date'            => $p->post_date,
				'status'          => $p->post_status,
				'thumbnail_url'   => $thumb_url,
				'total_pages'     => $total_pgs,
				'source_tweet_id' => (string) get_post_meta( $p_id, 'source_tweet_id', true ),
				'source_url'      => (string) get_post_meta( $p_id, 'source_url', true ),
				'chapters'        => self::get_term_summaries( $p_id, 'chapters' ),
			);
		}

		return array(
			'comics'      => $comics,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	/**
	 * Find a comic by source_tweet_id or source_url.
	 *
	 * Used by automation scripts and scrapers (e.g. n8n) for idempotency.
	 *
	 * @param array $args Arguments: { source_tweet_id?: string, source_url?: string }
	 * @return array|\WP_Error
	 */
	public static function find_by_source( array $args ) {
		$tweet_id   = isset( $args['source_tweet_id'] ) ? sanitize_text_field( (string) $args['source_tweet_id'] ) : '';
		$source_url = isset( $args['source_url'] ) ? esc_url_raw( (string) $args['source_url'] ) : '';

		if ( '' === $tweet_id && '' === $source_url ) {
			return new \WP_Error(
				'missing_source_param',
				__( 'Please provide either source_tweet_id or source_url to look up.', 'emcp-tools' )
			);
		}

		$meta_query = array( 'relation' => 'OR' );
		if ( '' !== $tweet_id ) {
			$meta_query[] = array(
				'key'     => 'source_tweet_id',
				'value'   => $tweet_id,
				'compare' => '=',
			);
		}
		if ( '' !== $source_url ) {
			$meta_query[] = array(
				'key'     => 'source_url',
				'value'   => $source_url,
				'compare' => '=',
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'      => self::get_comic_slug(),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => $meta_query,
			)
		);

		if ( empty( $query->posts ) ) {
			return array(
				'found'   => false,
				'message' => __( 'No comic found matching source criteria.', 'emcp-tools' ),
			);
		}

		$post = reset( $query->posts );
		return array(
			'found' => true,
			'comic' => self::get_comic( array( 'id' => $post->ID ) ),
		);
	}

	/**
	 * Calculate full comic navigation links.
	 *
	 * @param \WP_Post $post Target comic post.
	 * @return array
	 */
	public static function calculate_navigation( \WP_Post $post ): array {
		global $wpdb;
		$slug = self::get_comic_slug();

		$current_date = $post->post_date;

		// First comic
		$first = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_date ASC LIMIT 1",
				$slug
			)
		);

		// Previous comic
		$prev = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_date < %s ORDER BY post_date DESC LIMIT 1",
				$slug,
				$current_date
			)
		);

		// Next comic
		$next = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_date > %s ORDER BY post_date ASC LIMIT 1",
				$slug,
				$current_date
			)
		);

		// Latest comic
		$latest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1",
				$slug
			)
		);

		// In-chapter navigation
		$chapter_terms = wp_get_object_terms( $post->ID, 'chapters', array( 'fields' => 'ids' ) );
		$in_chap_prev  = null;
		$in_chap_next  = null;

		if ( ! empty( $chapter_terms ) && ! is_wp_error( $chapter_terms ) ) {
			$chap_ids = implode( ',', array_map( 'absint', (array) $chapter_terms ) );

			$in_chap_prev = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, p.post_name FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					 WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_date < %s
					 AND tt.taxonomy = 'chapters' AND tt.term_id IN ($chap_ids)
					 ORDER BY p.post_date DESC LIMIT 1",
					$slug,
					$current_date
				)
			);

			$in_chap_next = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, p.post_name FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					 WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_date > %s
					 AND tt.taxonomy = 'chapters' AND tt.term_id IN ($chap_ids)
					 ORDER BY p.post_date ASC LIMIT 1",
					$slug,
					$current_date
				)
			);
		}

		return array(
			'first'            => $first ? self::format_nav_item( $first ) : null,
			'previous'         => $prev ? self::format_nav_item( $prev ) : null,
			'next'             => $next ? self::format_nav_item( $next ) : null,
			'latest'           => $latest ? self::format_nav_item( $latest ) : null,
			'in_chapter_prev'  => $in_chap_prev ? self::format_nav_item( $in_chap_prev ) : null,
			'in_chapter_next'  => $in_chap_next ? self::format_nav_item( $in_chap_next ) : null,
		);
	}

	/**
	 * List chapters taxonomy terms.
	 *
	 * @param array $args Arguments: { parent?: int }
	 * @return array
	 */
	public static function list_chapters( array $args = array() ): array {
		$get_args = array(
			'taxonomy'   => 'chapters',
			'hide_empty' => false,
		);
		if ( isset( $args['parent'] ) ) {
			$get_args['parent'] = absint( $args['parent'] );
		}

		$terms = get_terms( $get_args );
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$list = array();
		foreach ( $terms as $term ) {
			$list[] = array(
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'parent'      => $term->parent,
				'count'       => $term->count,
				'description' => $term->description,
				'url'         => get_term_link( $term ),
			);
		}
		return $list;
	}

	/**
	 * List characters taxonomy terms.
	 *
	 * @return array
	 */
	public static function list_characters(): array {
		$terms = get_terms( array( 'taxonomy' => 'characters', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$list = array();
		foreach ( $terms as $t ) {
			$list[] = array(
				'id'          => $t->term_id,
				'name'        => $t->name,
				'slug'        => $t->slug,
				'count'       => $t->count,
				'description' => $t->description,
				'url'         => get_term_link( $t ),
			);
		}
		return $list;
	}

	/**
	 * List locations taxonomy terms.
	 *
	 * @return array
	 */
	public static function list_locations(): array {
		$terms = get_terms( array( 'taxonomy' => 'locations', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$list = array();
		foreach ( $terms as $t ) {
			$list[] = array(
				'id'          => $t->term_id,
				'name'        => $t->name,
				'slug'        => $t->slug,
				'count'       => $t->count,
				'description' => $t->description,
				'url'         => get_term_link( $t ),
			);
		}
		return $list;
	}

	/**
	 * Read Comic Easel configuration options.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$config = get_option( 'comiceasel-config', array() );
		return array(
			'comic_post_type_slug' => self::get_comic_slug(),
			'config'               => is_array( $config ) ? $config : array(),
		);
	}

	// ── Private helpers ──────────────────────────────────────────────────

	/**
	 * Resolves a comic post from id or slug.
	 *
	 * @param array $args { id?: int, slug?: string }
	 * @return \WP_Post|\WP_Error
	 */
	private static function resolve_comic_post( array $args ) {
		$slug = self::get_comic_slug();

		if ( ! empty( $args['id'] ) ) {
			$post = get_post( absint( $args['id'] ) );
			if ( $post instanceof \WP_Post && $post->post_type === $slug ) {
				return $post;
			}
		}

		if ( ! empty( $args['slug'] ) ) {
			$post_name = sanitize_title( (string) $args['slug'] );
			$posts     = get_posts(
				array(
					'name'        => $post_name,
					'post_type'   => $slug,
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);
			if ( ! empty( $posts ) && $posts[0] instanceof \WP_Post ) {
				return $posts[0];
			}
		}

		return new \WP_Error( 'comic_not_found', __( 'Comic post not found.', 'emcp-tools' ) );
	}

	/**
	 * Get term summaries for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	private static function get_term_summaries( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			$out[] = array(
				'id'     => $t->term_id,
				'name'   => $t->name,
				'slug'   => $t->slug,
				'parent' => $t->parent,
			);
		}
		return $out;
	}

	/**
	 * Format navigation post object into a link item.
	 *
	 * @param object $item DB row object.
	 * @return array
	 */
	private static function format_nav_item( $item ): array {
		return array(
			'id'    => (int) $item->ID,
			'title' => (string) $item->post_title,
			'slug'  => (string) $item->post_name,
			'url'   => get_permalink( (int) $item->ID ),
		);
	}
}
