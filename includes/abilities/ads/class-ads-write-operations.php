<?php
/**
 * Ads & Monetization Write Operations.
 *
 * Implements write operations for WP Quads ad slots, dynamic ads.txt,
 * cache flushing, and ExoClick REST API integrations.
 *
 * @package EMCP_Tools
 * @since   3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Ads_Write_Operations
 */
class EMCP_Tools_Ads_Write_Operations {

	/**
	 * Single source of truth for the write dispatcher's per-operation argument schema.
	 *
	 * @return array<string, array{description:string,example:array,schema:array}>
	 */
	public static function op_schema(): array {
		return array(
			'update-ad'           => array(
				'description' => __( 'Update an existing ad unit with dual-write synchronization across WP Quads postmeta and quads_settings.', 'emcp-tools' ),
				'example'     => array(
					'id'   => 'ad1',
					'code' => '<script async src="https://a.magsrv.com/ad-provider.js"></script><ins class="eas6a97888e2" data-zoneid="6020542"></ins><script>(AdProvider = window.AdProvider || []).push({"serve": {}});</script>',
				),
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'          => array(
							'type'        => 'string',
							'description' => __( 'Slot key (e.g. "ad1") or post ID (e.g. "13595").', 'emcp-tools' ),
						),
						'code'        => array(
							'type'        => 'string',
							'description' => __( 'New HTML/JS tag markup for this ad unit.', 'emcp-tools' ),
						),
						'title'       => array(
							'type'        => 'string',
							'description' => __( 'Optional new label/title for the ad unit.', 'emcp-tools' ),
						),
						'position'    => array(
							'type'        => 'string',
							'description' => __( 'Placement position (e.g. "custom", "before_post", "after_post").', 'emcp-tools' ),
						),
						'dimensions'  => array(
							'type'        => 'string',
							'description' => __( 'Ad dimensions (e.g. "728x90", "300x250").', 'emcp-tools' ),
						),
						'settings'    => array(
							'type'        => 'object',
							'description' => __( 'Additional WP Quads settings parameters.', 'emcp-tools' ),
						),
						'purge_cache' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Whether to purge page cache and object cache after updating.', 'emcp-tools' ),
						),
					),
				),
			),
			'create-ad'           => array(
				'description' => __( 'Create a new ad unit with dual-write to WP Quads posts and serialized options.', 'emcp-tools' ),
				'example'     => array(
					'title'    => 'Footer Ad',
					'code'     => '<ins data-zoneid="6020534"></ins>',
					'position' => 'custom',
				),
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'title', 'code' ),
					'properties' => array(
						'title'       => array(
							'type'        => 'string',
							'description' => __( 'Name / label for the ad slot.', 'emcp-tools' ),
						),
						'code'        => array(
							'type'        => 'string',
							'description' => __( 'HTML/JS tag markup for the ad unit.', 'emcp-tools' ),
						),
						'slot_key'    => array(
							'type'        => 'string',
							'description' => __( 'WP Quads slot key (e.g. "ad12"). If omitted, auto-generated based on existing keys.', 'emcp-tools' ),
						),
						'position'    => array(
							'type'        => 'string',
							'default'     => 'custom',
							'description' => __( 'Placement position.', 'emcp-tools' ),
						),
						'dimensions'  => array(
							'type'        => 'string',
							'description' => __( 'Dimensions string (e.g. "728x90").', 'emcp-tools' ),
						),
						'settings'    => array(
							'type'        => 'object',
							'description' => __( 'Additional WP Quads options dict.', 'emcp-tools' ),
						),
						'purge_cache' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Whether to flush cache after creation.', 'emcp-tools' ),
						),
					),
				),
			),
			'delete-ad'           => array(
				'description' => __( 'Delete or deactivate an ad unit from WP Quads and quads_settings with safety confirmation guard.', 'emcp-tools' ),
				'example'     => array(
					'id'      => 'ad12',
					'confirm' => true,
				),
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'id', 'confirm' ),
					'properties' => array(
						'id'          => array(
							'type'        => 'string',
							'description' => __( 'Slot key or post ID to delete.', 'emcp-tools' ),
						),
						'confirm'     => array(
							'type'        => 'boolean',
							'description' => __( 'Safety guard: must explicitly be true to execute deletion.', 'emcp-tools' ),
						),
						'purge_cache' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Whether to flush cache after deletion.', 'emcp-tools' ),
						),
					),
				),
			),
			'set-ads-txt'         => array(
				'description' => __( 'Set or append records to /ads.txt with IAB syntax validation and physical/option dual-write.', 'emcp-tools' ),
				'example'     => array(
					'append_records' => array( 'exoclick.com, 1111220, DIRECT, f6e6255c27770857' ),
				),
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'content'        => array(
							'type'        => 'string',
							'description' => __( 'Full text content to replace /ads.txt completely.', 'emcp-tools' ),
						),
						'append_records' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'List of individual record lines to append to existing /ads.txt.', 'emcp-tools' ),
						),
						'backup'         => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Whether to save previous content to backup option before changing.', 'emcp-tools' ),
						),
						'purge_cache'    => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Whether to flush page cache after updating ads.txt.', 'emcp-tools' ),
						),
					),
				),
			),
			'purge-ad-cache'      => array(
				'description' => __( 'Flush LiteSpeed page cache, WP object cache, and temporary ExoClick API transients.', 'emcp-tools' ),
				'example'     => array( 'all' => true ),
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'all' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Flush all ad and site caches.', 'emcp-tools' ),
						),
					),
				),
			),
			'exoclick-create-zone' => array(
				'description' => __( 'Create a new ad zone on ExoClick via API and optionally install tag directly into WP Quads slot.', 'emcp-tools' ),
				'example'     => array(
					'name'       => 'Header Banner',
					'dimensions' => '728x90',
					'idsite'     => 1111220,
				),
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name'               => array(
							'type'        => 'string',
							'description' => __( 'Zone name (e.g. "Above Comic - 728x90").', 'emcp-tools' ),
						),
						'dimensions'         => array(
							'type'        => 'string',
							'description' => __( 'Dimensions (e.g. "728x90", "300x250", "160x600").', 'emcp-tools' ),
						),
						'idsub_type_format'  => array(
							'type'        => 'integer',
							'description' => __( 'ExoClick sub type format ID (e.g. 1 for banner, 7 for popunder).', 'emcp-tools' ),
						),
						'idsite'             => array(
							'type'        => 'integer',
							'description' => __( 'ExoClick Site ID (defaults to saved site ID).', 'emcp-tools' ),
						),
						'api_token'          => array(
							'type'        => 'string',
							'description' => __( 'ExoClick API token (optional if configured).', 'emcp-tools' ),
						),
						'install_to_slot'    => array(
							'type'        => 'string',
							'description' => __( 'WP Quads slot key (e.g. "ad1") or "create" to automatically install generated tag.', 'emcp-tools' ),
						),
					),
				),
			),
			'exoclick-verify-site' => array(
				'description' => __( 'Verify website ownership with ExoClick by deploying verification token and triggering API confirmation.', 'emcp-tools' ),
				'example'     => array( 'idsite' => 1111220 ),
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'idsite'    => array(
							'type'        => 'integer',
							'description' => __( 'ExoClick Site ID.', 'emcp-tools' ),
						),
						'api_token' => array(
							'type'        => 'string',
							'description' => __( 'ExoClick API token (optional if configured).', 'emcp-tools' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Execute write operations.
	 *
	 * @param string $op   Operation name.
	 * @param array  $args Operation arguments.
	 * @return array
	 */
	public static function execute( string $op, array $args = array() ): array {
		switch ( $op ) {
			case 'update-ad':
				return self::update_ad( $args );
			case 'create-ad':
				return self::create_ad( $args );
			case 'delete-ad':
				return self::delete_ad( $args );
			case 'set-ads-txt':
				return self::set_ads_txt( $args );
			case 'purge-ad-cache':
				return self::purge_ad_cache( $args );
			case 'exoclick-create-zone':
				return self::exoclick_create_zone( $args );
			case 'exoclick-verify-site':
				return self::exoclick_verify_site( $args );
			default:
				return array(
					'success' => false,
					'error'   => sprintf( 'Unknown write operation: %s', $op ),
				);
		}
	}

	/**
	 * Update an ad slot with dual-write sync.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function update_ad( array $args ): array {
		$id = trim( (string) ( $args['id'] ?? '' ) );
		if ( '' === $id ) {
			return array( 'success' => false, 'error' => 'Argument "id" is required.' );
		}

		$settings  = get_option( 'quads_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( ! isset( $settings['ads'] ) || ! is_array( $settings['ads'] ) ) {
			$settings['ads'] = array();
		}

		$post_id   = 0;
		$slot_key  = null;
		$post      = null;

		if ( isset( $settings['ads'][ $id ] ) ) {
			$slot_key = $id;
			if ( function_exists( 'get_posts' ) ) {
				$found = get_posts(
					array(
						'post_type'   => 'quads-ads',
						'meta_key'    => 'quads_ad_old_id',
						'meta_value'  => $slot_key,
						'numberposts' => 1,
					)
				);
				if ( ! empty( $found ) ) {
					$post    = $found[0];
					$post_id = $post->ID;
				}
			}
		} elseif ( is_numeric( $id ) && function_exists( 'get_post' ) ) {
			$p = get_post( (int) $id );
			if ( $p && 'quads-ads' === $p->post_type ) {
				$post    = $p;
				$post_id = $p->ID;
				$old_id  = (string) get_post_meta( $post_id, 'quads_ad_old_id', true );
				$slot_key = ! empty( $old_id ) ? $old_id : 'ad_' . $post_id;
			}
		}

		if ( ! $slot_key && ! $post_id ) {
			return array( 'success' => false, 'error' => sprintf( 'Ad slot "%s" not found to update.', $id ) );
		}

		$current_slot_data = $settings['ads'][ $slot_key ] ?? array();
		if ( ! is_array( $current_slot_data ) ) {
			$current_slot_data = array();
		}

		$new_code       = isset( $args['code'] ) ? (string) $args['code'] : ( $current_slot_data['code'] ?? '' );
		$new_title      = isset( $args['title'] ) ? (string) $args['title'] : ( $current_slot_data['label'] ?? ( $post ? $post->post_title : $slot_key ) );
		$new_position   = isset( $args['position'] ) ? (string) $args['position'] : ( $current_slot_data['position'] ?? 'custom' );
		$new_dimensions = isset( $args['dimensions'] ) ? (string) $args['dimensions'] : ( $current_slot_data['dimensions'] ?? '' );

		// 1. Dual-write to quads_settings
		$current_slot_data['code']       = $new_code;
		$current_slot_data['label']      = $new_title;
		$current_slot_data['position']   = $new_position;
		if ( ! empty( $new_dimensions ) ) {
			$current_slot_data['dimensions'] = $new_dimensions;
		}
		if ( isset( $args['settings'] ) && is_array( $args['settings'] ) ) {
			$current_slot_data = array_merge( $current_slot_data, $args['settings'] );
		}
		$settings['ads'][ $slot_key ] = $current_slot_data;
		update_option( 'quads_settings', $settings );

		// 2. Dual-write to quads-ads post if exists
		if ( $post_id && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, 'code', $new_code );
			update_post_meta( $post_id, 'position', $new_position );
			if ( ! empty( $new_dimensions ) ) {
				update_post_meta( $post_id, 'dimensions', $new_dimensions );
			}
			if ( isset( $args['title'] ) && function_exists( 'wp_update_post' ) ) {
				wp_update_post(
					array(
						'ID'         => $post_id,
						'post_title' => $new_title,
					)
				);
			}
		}

		// 3. Cache flush
		if ( $args['purge_cache'] ?? true ) {
			self::purge_ad_cache();
		}

		return array(
			'success'          => true,
			'slot_key'         => $slot_key,
			'post_id'          => $post_id,
			'title'            => $new_title,
			'detected_network' => EMCP_Tools_Ads_Read_Operations::parse_ad_code( $new_code ),
			'message'          => sprintf( 'Ad slot "%s" updated successfully.', $slot_key ),
		);
	}

	/**
	 * Create a new ad slot with dual-write sync.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function create_ad( array $args ): array {
		$title = trim( (string) ( $args['title'] ?? '' ) );
		$code  = (string) ( $args['code'] ?? '' );

		if ( '' === $title || '' === $code ) {
			return array( 'success' => false, 'error' => 'Arguments "title" and "code" are required.' );
		}

		$settings = get_option( 'quads_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( ! isset( $settings['ads'] ) || ! is_array( $settings['ads'] ) ) {
			$settings['ads'] = array();
		}

		// Generate slot key if not provided
		$slot_key = trim( (string) ( $args['slot_key'] ?? '' ) );
		if ( '' === $slot_key ) {
			$max_num = 0;
			foreach ( array_keys( $settings['ads'] ) as $k ) {
				if ( preg_match( '/^ad(\d+)$/i', $k, $m ) ) {
					$max_num = max( $max_num, (int) $m[1] );
				}
			}
			$slot_key = 'ad' . ( $max_num + 1 );
		}

		$position   = $args['position'] ?? 'custom';
		$dimensions = $args['dimensions'] ?? '';
		if ( empty( $dimensions ) ) {
			$parsed     = EMCP_Tools_Ads_Read_Operations::parse_ad_code( $code );
			$dimensions = $parsed['dimensions'] ?? '';
		}

		// 1. Create quads-ads post if post type exists
		$post_id = 0;
		if ( function_exists( 'wp_insert_post' ) ) {
			$post_id = wp_insert_post(
				array(
					'post_title'  => $title,
					'post_type'   => 'quads-ads',
					'post_status' => 'publish',
				)
			);
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, 'quads_ad_old_id', $slot_key );
				update_post_meta( $post_id, 'code', $code );
				update_post_meta( $post_id, 'position', $position );
				if ( ! empty( $dimensions ) ) {
					update_post_meta( $post_id, 'dimensions', $dimensions );
				}
			} else {
				$post_id = 0;
			}
		}

		// 2. Add to quads_settings
		$slot_entry = array(
			'code'       => $code,
			'label'      => $title,
			'position'   => $position,
			'dimensions' => $dimensions,
			'ad_type'    => 'plain_text',
		);
		if ( isset( $args['settings'] ) && is_array( $args['settings'] ) ) {
			$slot_entry = array_merge( $slot_entry, $args['settings'] );
		}
		$settings['ads'][ $slot_key ] = $slot_entry;
		update_option( 'quads_settings', $settings );

		// 3. Cache purge
		if ( $args['purge_cache'] ?? true ) {
			self::purge_ad_cache();
		}

		return array(
			'success'          => true,
			'slot_key'         => $slot_key,
			'post_id'          => $post_id,
			'title'            => $title,
			'position'         => $position,
			'detected_network' => EMCP_Tools_Ads_Read_Operations::parse_ad_code( $code ),
			'message'          => sprintf( 'Ad slot "%s" created successfully.', $slot_key ),
		);
	}

	/**
	 * Delete an ad slot with safety confirmation guard.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function delete_ad( array $args ): array {
		$id      = trim( (string) ( $args['id'] ?? '' ) );
		$confirm = ! empty( $args['confirm'] );

		if ( '' === $id ) {
			return array( 'success' => false, 'error' => 'Argument "id" is required.' );
		}

		if ( ! $confirm ) {
			return array(
				'success' => false,
				'warning' => 'Confirmation required. Pass "confirm": true to delete this ad unit.',
				'id'      => $id,
			);
		}

		$settings = get_option( 'quads_settings', array() );
		$slot_key = null;
		$post_id  = 0;

		if ( isset( $settings['ads'][ $id ] ) ) {
			$slot_key = $id;
			unset( $settings['ads'][ $id ] );
			update_option( 'quads_settings', $settings );

			if ( function_exists( 'get_posts' ) ) {
				$found = get_posts(
					array(
						'post_type'   => 'quads-ads',
						'meta_key'    => 'quads_ad_old_id',
						'meta_value'  => $slot_key,
						'numberposts' => 1,
					)
				);
				if ( ! empty( $found ) ) {
					$post_id = $found[0]->ID;
				}
			}
		} elseif ( is_numeric( $id ) ) {
			$post_id = (int) $id;
			if ( function_exists( 'get_post_meta' ) ) {
				$old_id = (string) get_post_meta( $post_id, 'quads_ad_old_id', true );
				if ( ! empty( $old_id ) && isset( $settings['ads'][ $old_id ] ) ) {
					$slot_key = $old_id;
					unset( $settings['ads'][ $old_id ] );
					update_option( 'quads_settings', $settings );
				}
			}
		}

		if ( $post_id && function_exists( 'wp_delete_post' ) ) {
			wp_delete_post( $post_id, true );
		}

		if ( $args['purge_cache'] ?? true ) {
			self::purge_ad_cache();
		}

		return array(
			'success'  => true,
			'slot_key' => $slot_key ?? $id,
			'post_id'  => $post_id,
			'message'  => sprintf( 'Ad slot "%s" deleted successfully.', $id ),
		);
	}

	/**
	 * Set or append records to /ads.txt.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function set_ads_txt( array $args ): array {
		$current = EMCP_Tools_Ads_Read_Operations::get_ads_txt();
		$raw     = $current['raw'];

		// Backup
		if ( $args['backup'] ?? true ) {
			update_option(
				'emcp_tools_ads_txt_backup',
				array(
					'timestamp' => time(),
					'content'   => $raw,
				)
			);
		}

		$new_content = '';
		if ( isset( $args['content'] ) && is_string( $args['content'] ) ) {
			$new_content = trim( $args['content'] );
		} elseif ( ! empty( $args['append_records'] ) && is_array( $args['append_records'] ) ) {
			$existing_lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $raw ) ) ) );
			foreach ( $args['append_records'] as $rec ) {
				$rec = trim( (string) $rec );
				if ( ! empty( $rec ) && ! in_array( $rec, $existing_lines, true ) ) {
					$existing_lines[] = $rec;
				}
			}
			$new_content = implode( "\n", $existing_lines );
		} else {
			return array( 'success' => false, 'error' => 'Must provide either "content" or "append_records".' );
		}

		// Syntax validation
		$validation_warnings = array();
		$lines = explode( "\n", $new_content );
		foreach ( $lines as $idx => $line ) {
			$t = trim( $line );
			if ( '' === $t || 0 === strpos( $t, '#' ) ) {
				continue;
			}
			$parts = array_map( 'trim', explode( ',', $t ) );
			if ( count( $parts ) < 3 ) {
				$validation_warnings[] = sprintf( 'Line %d: insufficient fields: "%s"', $idx + 1, $t );
			} elseif ( ! in_array( strtoupper( $parts[2] ), array( 'DIRECT', 'RESELLER' ), true ) ) {
				$validation_warnings[] = sprintf( 'Line %d: invalid relationship "%s"', $idx + 1, $parts[2] );
			}
		}

		// 1. Save to option
		update_option( 'emcp_tools_ads_txt', $new_content );

		// 2. Physical write if ABSPATH is available
		$file_written = false;
		if ( defined( 'ABSPATH' ) ) {
			$file_path = ABSPATH . 'ads.txt';
			if ( ( file_exists( $file_path ) && is_writable( $file_path ) ) || ( ! file_exists( $file_path ) && is_writable( ABSPATH ) ) ) {
				$file_written = ( false !== @file_put_contents( $file_path, $new_content ) );
			}
		}

		// 3. Purge cache
		if ( $args['purge_cache'] ?? true ) {
			self::purge_ad_cache();
		}

		return array(
			'success'             => true,
			'records_count'       => count( array_filter( $lines, fn( $l ) => '' !== trim( $l ) && 0 !== strpos( trim( $l ), '#' ) ) ),
			'validation_warnings' => $validation_warnings,
			'file_written'        => $file_written,
			'saved_to_option'     => true,
			'message'             => '/ads.txt updated successfully.',
		);
	}

	/**
	 * Purge ad transients and page caches.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function purge_ad_cache( array $args = array() ): array {
		$purged = array();

		// LiteSpeed Cache purge
		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_purge_all' );
			$purged[] = 'litespeed_purge_all';
		}

		// Object Cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$purged[] = 'wp_cache_flush';
		}

		// Transient purge for ExoClick
		if ( function_exists( 'delete_transient' ) ) {
			global $wpdb;
			if ( ! empty( $wpdb ) && isset( $wpdb->options ) ) {
				$keys = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_emcp_exoclick_%'" );
				if ( is_array( $keys ) ) {
					foreach ( $keys as $k ) {
						$transient_name = str_replace( '_transient_', '', $k );
						delete_transient( $transient_name );
					}
					$purged[] = 'exoclick_transients';
				}
			}
		}

		return array(
			'success' => true,
			'purged'  => $purged,
			'message' => 'Ad caches successfully flushed.',
		);
	}

	/**
	 * Create an ExoClick zone via REST API with optional automatic installation.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function exoclick_create_zone( array $args ): array {
		$name = trim( (string) ( $args['name'] ?? '' ) );
		if ( '' === $name ) {
			return array( 'success' => false, 'error' => 'Argument "name" is required.' );
		}

		$token = EMCP_Tools_Ads_Read_Operations::get_exoclick_token( $args );
		if ( ! $token ) {
			return array( 'success' => false, 'error' => 'ExoClick API token is required.' );
		}

		$bearer = EMCP_Tools_Ads_Read_Operations::authenticate_exoclick( $token );
		if ( is_wp_error( $bearer ) ) {
			return array( 'success' => false, 'error' => $bearer->get_error_message() );
		}

		$site_id    = $args['idsite'] ?? get_option( 'emcp_tools_exoclick_site_id', 1111220 );
		$dimensions = $args['dimensions'] ?? '300x250';
		$format_id  = $args['idsub_type_format'] ?? 1; // 1 = Banner

		$body = array(
			'name'              => $name,
			'idsite'            => absint( $site_id ),
			'idsub_type_format' => absint( $format_id ),
			'description'       => 'Created by Heretek Control Core',
		);

		$res = wp_remote_post(
			'https://api.exoclick.com/v2/zones',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $bearer,
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'EMCP-Tools/WordPress',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		$zone_id = $data['idzone'] ?? ( $data['result']['idzone'] ?? null );

		if ( ! $zone_id ) {
			return array(
				'success' => false,
				'error'   => $data['message'] ?? 'Failed to create zone on ExoClick.',
				'raw'     => $data,
			);
		}

		// Generate canonical ExoClick JS banner snippet
		$snippet = sprintf(
			'<script async type="application/javascript" src="https://a.magsrv.com/ad-provider.js"></script>' . "\n" .
			'<ins class="eas6a97888e2" data-zoneid="%d"></ins>' . "\n" .
			'<script>(AdProvider = window.AdProvider || []).push({"serve": {}});</script>',
			$zone_id
		);

		$install_res = null;
		$slot_target = $args['install_to_slot'] ?? '';
		if ( ! empty( $slot_target ) ) {
			if ( 'create' === $slot_target ) {
				$install_res = self::create_ad(
					array(
						'title'      => $name,
						'code'       => $snippet,
						'dimensions' => $dimensions,
					)
				);
			} else {
				$install_res = self::update_ad(
					array(
						'id'         => $slot_target,
						'code'       => $snippet,
						'title'      => $name,
						'dimensions' => $dimensions,
					)
				);
			}
		}

		return array(
			'success'          => true,
			'zone_id'          => $zone_id,
			'site_id'          => $site_id,
			'name'             => $name,
			'tag_code'         => $snippet,
			'installation'     => $install_res,
		);
	}

	/**
	 * Verify site with ExoClick.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function exoclick_verify_site( array $args = array() ): array {
		$token = EMCP_Tools_Ads_Read_Operations::get_exoclick_token( $args );
		if ( ! $token ) {
			return array( 'success' => false, 'error' => 'ExoClick API token is required.' );
		}

		$bearer = EMCP_Tools_Ads_Read_Operations::authenticate_exoclick( $token );
		if ( is_wp_error( $bearer ) ) {
			return array( 'success' => false, 'error' => $bearer->get_error_message() );
		}

		$site_id = $args['idsite'] ?? get_option( 'emcp_tools_exoclick_site_id', 1111220 );

		// Step 1: Trigger verification check
		$res = wp_remote_post(
			'https://api.exoclick.com/v2/sites/url-verification',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $bearer,
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'EMCP-Tools/WordPress',
				),
				'body'    => wp_json_encode( array( 'idsite' => absint( $site_id ) ) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		return array(
			'success' => true,
			'site_id' => $site_id,
			'result'  => $data,
		);
	}
}
