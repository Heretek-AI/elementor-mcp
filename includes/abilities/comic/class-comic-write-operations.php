<?php
/**
 * Comic Easel Write Operations.
 *
 * Implements creation, updating, and deletion of comic posts, multi-image strips
 * (`comic-html-below`), source tracking (`source_tweet_id`, `source_url`),
 * chapters, and characters.
 *
 * @package EMCP_Tools
 * @since   3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Comic_Write_Operations
 */
class EMCP_Tools_Comic_Write_Operations {

	/**
	 * Create a comic post.
	 *
	 * @param array $args Creation parameters.
	 * @return array|\WP_Error
	 */
	public static function create_comic( array $args ) {
		$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		if ( '' === $title ) {
			return new \WP_Error( 'missing_title', __( 'Comic title is required.', 'emcp-tools' ) );
		}

		$slug = EMCP_Tools_Comic_Read_Operations::get_comic_slug();

		$post_data = array(
			'post_type'    => $slug,
			'post_title'   => $title,
			'post_content' => isset( $args['content'] ) ? wp_kses_post( (string) $args['content'] ) : '',
			'post_status'  => sanitize_key( (string) ( $args['status'] ?? 'publish' ) ),
		);

		if ( ! empty( $args['date'] ) ) {
			$post_data['post_date'] = sanitize_text_field( (string) $args['date'] );
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$warnings = array();

		// Handle featured media (Page 1)
		$featured_id = 0;
		if ( ! empty( $args['featured_media_id'] ) ) {
			$featured_id = absint( $args['featured_media_id'] );
		} elseif ( ! empty( $args['featured_media_url'] ) ) {
			$sideload = self::sideload_image_url( (string) $args['featured_media_url'], $post_id, $title . ' - Page 1' );
			if ( is_wp_error( $sideload ) ) {
				$warnings[] = 'featured_media: ' . $sideload->get_error_message();
			} else {
				$featured_id = (int) $sideload;
			}
		}
		if ( $featured_id > 0 ) {
			set_post_thumbnail( $post_id, $featured_id );
		}

		// Handle multi-image strip (comic-html-below)
		$additional_images = (array) ( $args['additional_images'] ?? array() );
		$resolved_images   = array();
		$page_counter      = 2;

		foreach ( $additional_images as $img_desc ) {
			if ( is_string( $img_desc ) && preg_match( '#^https?://#i', $img_desc ) ) {
				// Sideload remote image to media library
				$page_title = sprintf( '%s - Page %d', $title, $page_counter++ );
				$att_id     = self::sideload_image_url( $img_desc, $post_id, $page_title );
				if ( is_wp_error( $att_id ) ) {
					$warnings[] = 'additional_image: ' . $att_id->get_error_message();
					$resolved_images[] = $img_desc; // keep as URL
				} else {
					$resolved_images[] = (int) $att_id;
				}
			} elseif ( is_array( $img_desc ) && ! empty( $img_desc['url'] ) && empty( $img_desc['id'] ) && preg_match( '#^https?://#i', (string) $img_desc['url'] ) ) {
				$page_title = ! empty( $img_desc['alt'] ) ? (string) $img_desc['alt'] : sprintf( '%s - Page %d', $title, $page_counter++ );
				$att_id     = self::sideload_image_url( (string) $img_desc['url'], $post_id, $page_title );
				if ( is_wp_error( $att_id ) ) {
					$warnings[] = 'additional_image: ' . $att_id->get_error_message();
					$resolved_images[] = $img_desc;
				} else {
					$img_desc['id']    = (int) $att_id;
					$resolved_images[] = $img_desc;
				}
			} else {
				$resolved_images[] = $img_desc;
			}
		}

		$html_below = '';
		if ( ! empty( $resolved_images ) ) {
			$html_below = EMCP_Tools_Comic_Html_Helper::build_html_below( $resolved_images );
		}
		if ( isset( $args['comic_html_below'] ) ) {
			$raw_below = EMCP_Tools_Comic_Html_Helper::sanitize_html_below( (string) $args['comic_html_below'] );
			$html_below = ! empty( $html_below ) ? $html_below . "\n" . $raw_below : $raw_below;
		}

		if ( '' !== $html_below ) {
			// Write both keys for full Comic Easel and automation tool compatibility
			update_post_meta( $post_id, 'comic-html-below', $html_below );
			update_post_meta( $post_id, 'ceo_html_below_comic', $html_below );
		}

		// Source metadata
		if ( isset( $args['source_tweet_id'] ) ) {
			update_post_meta( $post_id, 'source_tweet_id', sanitize_text_field( (string) $args['source_tweet_id'] ) );
		}
		if ( isset( $args['source_url'] ) ) {
			update_post_meta( $post_id, 'source_url', esc_url_raw( (string) $args['source_url'] ) );
		}

		// Comic presentation metadata
		if ( isset( $args['comic_html_above'] ) ) {
			update_post_meta( $post_id, 'comic-html-above', EMCP_Tools_Comic_Html_Helper::sanitize_html_below( (string) $args['comic_html_above'] ) );
		}
		if ( isset( $args['hovertext'] ) ) {
			update_post_meta( $post_id, 'comic-hovertext', sanitize_text_field( (string) $args['hovertext'] ) );
		}
		if ( isset( $args['transcript'] ) ) {
			update_post_meta( $post_id, 'transcript', wp_kses_post( (string) $args['transcript'] ) );
		}
		if ( isset( $args['content_warning'] ) ) {
			update_post_meta( $post_id, 'comic-content-warning', sanitize_text_field( (string) $args['content_warning'] ) );
		}

		// Assign taxonomies
		self::assign_terms( $post_id, 'chapters', $args['chapters'] ?? array() );
		self::assign_terms( $post_id, 'characters', $args['characters'] ?? array() );
		self::assign_terms( $post_id, 'locations', $args['locations'] ?? array() );
		self::assign_terms( $post_id, 'post_tag', $args['tags'] ?? array() );

		$result = EMCP_Tools_Comic_Read_Operations::get_comic( array( 'id' => $post_id ) );
		if ( is_array( $result ) && ! empty( $warnings ) ) {
			$result['warnings'] = $warnings;
		}
		return $result;
	}

	/**
	 * Update an existing comic post.
	 *
	 * @param array $args Update parameters.
	 * @return array|\WP_Error
	 */
	public static function update_comic( array $args ) {
		$post_id = absint( $args['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'invalid_id', __( 'A valid comic ID is required.', 'emcp-tools' ) );
		}

		$slug = EMCP_Tools_Comic_Read_Operations::get_comic_slug();
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== $slug ) {
			return new \WP_Error( 'comic_not_found', __( 'Comic post not found.', 'emcp-tools' ) );
		}

		$post_data = array( 'ID' => $post_id );
		if ( isset( $args['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( (string) $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( (string) $args['content'] );
		}
		if ( isset( $args['status'] ) ) {
			$post_data['post_status'] = sanitize_key( (string) $args['status'] );
		}
		if ( isset( $args['date'] ) ) {
			$post_data['post_date'] = sanitize_text_field( (string) $args['date'] );
		}

		if ( count( $post_data ) > 1 ) {
			wp_update_post( $post_data );
		}

		$warnings = array();

		// Update featured media
		if ( ! empty( $args['featured_media_id'] ) ) {
			set_post_thumbnail( $post_id, absint( $args['featured_media_id'] ) );
		} elseif ( ! empty( $args['featured_media_url'] ) ) {
			$sideload = self::sideload_image_url( (string) $args['featured_media_url'], $post_id, get_the_title( $post_id ) );
			if ( is_wp_error( $sideload ) ) {
				$warnings[] = 'featured_media: ' . $sideload->get_error_message();
			} else {
				set_post_thumbnail( $post_id, (int) $sideload );
			}
		}

		// Update multi-image strip
		$append_images = ! empty( $args['append_images'] );
		if ( isset( $args['additional_images'] ) && is_array( $args['additional_images'] ) ) {
			$resolved_images = array();
			$page_counter    = 2;
			foreach ( $args['additional_images'] as $img_desc ) {
				if ( is_string( $img_desc ) && preg_match( '#^https?://#i', $img_desc ) ) {
					$page_title = sprintf( '%s - Page %d', get_the_title( $post_id ), $page_counter++ );
					$att_id     = self::sideload_image_url( $img_desc, $post_id, $page_title );
					if ( is_wp_error( $att_id ) ) {
						$warnings[] = 'additional_image: ' . $att_id->get_error_message();
						$resolved_images[] = $img_desc;
					} else {
						$resolved_images[] = (int) $att_id;
					}
				} else {
					$resolved_images[] = $img_desc;
				}
			}

			if ( $append_images ) {
				$existing_html = (string) get_post_meta( $post_id, 'comic-html-below', true );
				$new_html      = EMCP_Tools_Comic_Html_Helper::append_to_html_below( $existing_html, $resolved_images );
			} else {
				$new_html = EMCP_Tools_Comic_Html_Helper::build_html_below( $resolved_images );
			}

			update_post_meta( $post_id, 'comic-html-below', $new_html );
			update_post_meta( $post_id, 'ceo_html_below_comic', $new_html );
		} elseif ( isset( $args['comic_html_below'] ) ) {
			$clean_html = EMCP_Tools_Comic_Html_Helper::sanitize_html_below( (string) $args['comic_html_below'] );
			update_post_meta( $post_id, 'comic-html-below', $clean_html );
			update_post_meta( $post_id, 'ceo_html_below_comic', $clean_html );
		}

		// Source metadata
		if ( isset( $args['source_tweet_id'] ) ) {
			update_post_meta( $post_id, 'source_tweet_id', sanitize_text_field( (string) $args['source_tweet_id'] ) );
		}
		if ( isset( $args['source_url'] ) ) {
			update_post_meta( $post_id, 'source_url', esc_url_raw( (string) $args['source_url'] ) );
		}

		// Presentation metadata
		if ( isset( $args['comic_html_above'] ) ) {
			update_post_meta( $post_id, 'comic-html-above', EMCP_Tools_Comic_Html_Helper::sanitize_html_below( (string) $args['comic_html_above'] ) );
		}
		if ( isset( $args['hovertext'] ) ) {
			update_post_meta( $post_id, 'comic-hovertext', sanitize_text_field( (string) $args['hovertext'] ) );
		}
		if ( isset( $args['transcript'] ) ) {
			update_post_meta( $post_id, 'transcript', wp_kses_post( (string) $args['transcript'] ) );
		}
		if ( isset( $args['content_warning'] ) ) {
			update_post_meta( $post_id, 'comic-content-warning', sanitize_text_field( (string) $args['content_warning'] ) );
		}

		// Taxonomies
		if ( isset( $args['chapters'] ) ) {
			self::assign_terms( $post_id, 'chapters', $args['chapters'] );
		}
		if ( isset( $args['characters'] ) ) {
			self::assign_terms( $post_id, 'characters', $args['characters'] );
		}
		if ( isset( $args['locations'] ) ) {
			self::assign_terms( $post_id, 'locations', $args['locations'] );
		}
		if ( isset( $args['tags'] ) ) {
			self::assign_terms( $post_id, 'post_tag', $args['tags'] );
		}

		$result = EMCP_Tools_Comic_Read_Operations::get_comic( array( 'id' => $post_id ) );
		if ( is_array( $result ) && ! empty( $warnings ) ) {
			$result['warnings'] = $warnings;
		}
		return $result;
	}

	/**
	 * Delete a comic post.
	 *
	 * @param array $args Arguments: { id: int, force?: bool, confirm: bool }
	 * @return array|\WP_Error
	 */
	public static function delete_comic( array $args ) {
		$post_id = absint( $args['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'invalid_id', __( 'A valid comic ID is required.', 'emcp-tools' ) );
		}

		if ( empty( $args['confirm'] ) ) {
			return new \WP_Error( 'confirm_required', __( 'Deleting a comic requires confirm: true.', 'emcp-tools' ) );
		}

		$slug = EMCP_Tools_Comic_Read_Operations::get_comic_slug();
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== $slug ) {
			return new \WP_Error( 'comic_not_found', __( 'Comic post not found.', 'emcp-tools' ) );
		}

		$force   = ! empty( $args['force'] );
		$deleted = $force ? wp_delete_post( $post_id, true ) : wp_trash_post( $post_id );

		if ( ! $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Could not delete comic post.', 'emcp-tools' ) );
		}

		return array(
			'id'      => $post_id,
			'deleted' => true,
			'trashed' => ! $force,
			'message' => $force ? __( 'Comic permanently deleted.', 'emcp-tools' ) : __( 'Comic moved to trash.', 'emcp-tools' ),
		);
	}

	/**
	 * Create a chapter term.
	 *
	 * @param array $args Arguments: { name: string, slug?: string, parent?: int, description?: string, menu_order?: int }
	 * @return array|\WP_Error
	 */
	public static function create_chapter( array $args ) {
		$name = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
		if ( '' === $name ) {
			return new \WP_Error( 'missing_name', __( 'Chapter name is required.', 'emcp-tools' ) );
		}

		$term_args = array();
		if ( ! empty( $args['slug'] ) ) {
			$term_args['slug'] = sanitize_title( (string) $args['slug'] );
		}
		if ( ! empty( $args['parent'] ) ) {
			$term_args['parent'] = absint( $args['parent'] );
		}
		if ( isset( $args['description'] ) ) {
			$term_args['description'] = sanitize_textarea_field( (string) $args['description'] );
		}

		$term = wp_insert_term( $name, 'chapters', $term_args );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = (int) $term['term_id'];

		if ( isset( $args['menu_order'] ) ) {
			global $wpdb;
			$menu_order = intval( $args['menu_order'] );
			$wpdb->update( $wpdb->terms, array( 'menu_order' => $menu_order ), array( 'term_id' => $term_id ) );
			update_term_meta( $term_id, 'menu_order', $menu_order );
		}

		$created = get_term( $term_id, 'chapters' );
		return array(
			'id'          => $term_id,
			'name'        => $created->name,
			'slug'        => $created->slug,
			'parent'      => $created->parent,
			'description' => $created->description,
			'url'         => get_term_link( $created ),
		);
	}

	/**
	 * Update a chapter term.
	 *
	 * @param array $args Arguments: { id: int, name?: string, slug?: string, parent?: int, description?: string, menu_order?: int }
	 * @return array|\WP_Error
	 */
	public static function update_chapter( array $args ) {
		$term_id = absint( $args['id'] ?? 0 );
		if ( $term_id <= 0 ) {
			return new \WP_Error( 'invalid_id', __( 'A valid chapter ID is required.', 'emcp-tools' ) );
		}

		$term_args = array();
		if ( isset( $args['name'] ) ) {
			$term_args['name'] = sanitize_text_field( (string) $args['name'] );
		}
		if ( isset( $args['slug'] ) ) {
			$term_args['slug'] = sanitize_title( (string) $args['slug'] );
		}
		if ( isset( $args['parent'] ) ) {
			$term_args['parent'] = absint( $args['parent'] );
		}
		if ( isset( $args['description'] ) ) {
			$term_args['description'] = sanitize_textarea_field( (string) $args['description'] );
		}

		$updated = wp_update_term( $term_id, 'chapters', $term_args );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		if ( isset( $args['menu_order'] ) ) {
			global $wpdb;
			$menu_order = intval( $args['menu_order'] );
			$wpdb->update( $wpdb->terms, array( 'menu_order' => $menu_order ), array( 'term_id' => $term_id ) );
			update_term_meta( $term_id, 'menu_order', $menu_order );
		}

		$term = get_term( $term_id, 'chapters' );
		return array(
			'id'          => $term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'parent'      => $term->parent,
			'description' => $term->description,
			'url'         => get_term_link( $term ),
		);
	}

	/**
	 * Delete a chapter term.
	 *
	 * @param array $args Arguments: { id: int, confirm: bool }
	 * @return array|\WP_Error
	 */
	public static function delete_chapter( array $args ) {
		$term_id = absint( $args['id'] ?? 0 );
		if ( $term_id <= 0 ) {
			return new \WP_Error( 'invalid_id', __( 'A valid chapter ID is required.', 'emcp-tools' ) );
		}

		if ( empty( $args['confirm'] ) ) {
			return new \WP_Error( 'confirm_required', __( 'Deleting a chapter requires confirm: true.', 'emcp-tools' ) );
		}

		$deleted = wp_delete_term( $term_id, 'chapters' );
		if ( is_wp_error( $deleted ) || ! $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Could not delete chapter term.', 'emcp-tools' ) );
		}

		return array(
			'id'      => $term_id,
			'deleted' => true,
			'message' => __( 'Chapter deleted successfully.', 'emcp-tools' ),
		);
	}

	/**
	 * Create a character term.
	 *
	 * @param array $args Arguments: { name: string, description?: string }
	 * @return array|\WP_Error
	 */
	public static function create_character( array $args ) {
		$name = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
		if ( '' === $name ) {
			return new \WP_Error( 'missing_name', __( 'Character name is required.', 'emcp-tools' ) );
		}

		$term_args = array();
		if ( isset( $args['description'] ) ) {
			$term_args['description'] = sanitize_textarea_field( (string) $args['description'] );
		}

		$term = wp_insert_term( $name, 'characters', $term_args );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$created = get_term( (int) $term['term_id'], 'characters' );
		return array(
			'id'          => $created->term_id,
			'name'        => $created->name,
			'slug'        => $created->slug,
			'description' => $created->description,
			'url'         => get_term_link( $created ),
		);
	}

	/**
	 * Set source tracking fields on an existing comic post.
	 *
	 * @param array $args Arguments: { id: int, source_tweet_id?: string, source_url?: string }
	 * @return array|\WP_Error
	 */
	public static function set_source( array $args ) {
		$post_id = absint( $args['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'invalid_id', __( 'A valid comic ID is required.', 'emcp-tools' ) );
		}

		$slug = EMCP_Tools_Comic_Read_Operations::get_comic_slug();
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== $slug ) {
			return new \WP_Error( 'comic_not_found', __( 'Comic post not found.', 'emcp-tools' ) );
		}

		$updated = array();
		if ( isset( $args['source_tweet_id'] ) ) {
			$tid = sanitize_text_field( (string) $args['source_tweet_id'] );
			update_post_meta( $post_id, 'source_tweet_id', $tid );
			$updated['source_tweet_id'] = $tid;
		}
		if ( isset( $args['source_url'] ) ) {
			$surl = esc_url_raw( (string) $args['source_url'] );
			update_post_meta( $post_id, 'source_url', $surl );
			$updated['source_url'] = $surl;
		}

		return array(
			'id'      => $post_id,
			'updated' => $updated,
			'message' => __( 'Source tracking metadata updated.', 'emcp-tools' ),
		);
	}

	// ── Private helpers ──────────────────────────────────────────────────

	/**
	 * Assign taxonomy terms to a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param mixed  $terms Term IDs, slugs, or names.
	 */
	private static function assign_terms( int $post_id, string $taxonomy, $terms ): void {
		if ( empty( $terms ) ) {
			return;
		}
		$term_array = is_array( $terms ) ? $terms : array( $terms );
		$ids        = array();

		foreach ( $term_array as $t ) {
			if ( is_numeric( $t ) ) {
				$ids[] = absint( $t );
			} elseif ( is_string( $t ) && '' !== trim( $t ) ) {
				$existing = get_term_by( 'name', $t, $taxonomy );
				if ( ! $existing ) {
					$existing = get_term_by( 'slug', $t, $taxonomy );
				}
				if ( $existing instanceof \WP_Term ) {
					$ids[] = $existing->term_id;
				} else {
					$created = wp_insert_term( $t, $taxonomy );
					if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
						$ids[] = (int) $created['term_id'];
					}
				}
			}
		}

		if ( ! empty( $ids ) ) {
			wp_set_object_terms( $post_id, $ids, $taxonomy );
		}
	}

	/**
	 * Sideload a remote image URL into the WordPress media library with SSRF guarding.
	 *
	 * @param string $url Remote image URL.
	 * @param int    $post_id Parent comic post ID.
	 * @param string $title Attachment title.
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure.
	 */
	private static function sideload_image_url( string $url, int $post_id, string $title = '' ) {
		$url = esc_url_raw( trim( $url ) );
		if ( empty( $url ) ) {
			return new \WP_Error( 'empty_url', __( 'Empty image URL.', 'emcp-tools' ) );
		}

		// Validate safe remote URL if EMCP_Tools_Url_Guard is available
		if ( class_exists( 'EMCP_Tools_Url_Guard' ) && ! EMCP_Tools_Url_Guard::is_safe_remote_url( $url ) ) {
			return new \WP_Error( 'unsafe_url', __( 'Remote image URL was rejected by SSRF guard.', 'emcp-tools' ) );
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			if ( file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( file_exists( ABSPATH . 'wp-admin/includes/media.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
			}
			if ( file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			return new \WP_Error( 'sideload_unavailable', __( 'media_handle_sideload function is unavailable.', 'emcp-tools' ) );
		}

		// Download temporary file
		if ( class_exists( 'EMCP_Tools_Url_Guard' ) && method_exists( 'EMCP_Tools_Url_Guard', 'safe_download' ) ) {
			$tmp_file = EMCP_Tools_Url_Guard::safe_download( $url, 30 );
		} else {
			$tmp_file = download_url( $url, 30 );
		}

		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		$url_path = wp_parse_url( $url, PHP_URL_PATH );
		$filename = $url_path ? basename( $url_path ) : 'comic-page.jpg';
		if ( ! preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $filename ) ) {
			$filename .= '.jpg';
		}

		$file_array = array(
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp_file,
		);

		$post_data = array(
			'post_title' => $title ? sanitize_text_field( $title ) : sanitize_file_name( $filename ),
		);

		$att_id = media_handle_sideload( $file_array, $post_id, null, $post_data );

		if ( is_wp_error( $att_id ) ) {
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}
			return $att_id;
		}

		return (int) $att_id;
	}
}
