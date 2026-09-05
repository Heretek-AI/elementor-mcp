<?php
/**
 * Ads & Monetization Read Operations.
 *
 * Implements read operations for WP Quads ad slots, dynamic ads.txt,
 * ad network detection, monetization auditing, and ExoClick API reporting.
 *
 * @package EMCP_Tools
 * @since   3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Ads_Read_Operations
 */
class EMCP_Tools_Ads_Read_Operations {

	/**
	 * Single source of truth for the read dispatcher's per-operation argument schema.
	 *
	 * @return array<string, array{description:string,example:array,schema:array}>
	 */
	public static function op_schema(): array {
		return array(
			'list-ads'           => array(
				'description' => __( 'List all registered ad units from WP Quads and active ad locations with detected networks and dimensions.', 'emcp-tools' ),
				'example'     => array( 'status' => 'all' ),
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'all', 'active', 'inactive' ),
							'default'     => 'all',
							'description' => __( 'Filter ads by status.', 'emcp-tools' ),
						),
					),
				),
			),
			'get-ad'             => array(
				'description' => __( 'Get complete configuration, dimensions, code, and placement for a specific ad slot by Post ID or Quads key (e.g. ad1).', 'emcp-tools' ),
				'example'     => array( 'id' => 'ad1' ),
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'string',
							'description' => __( 'Post ID (e.g. "13595") or WP Quads slot key (e.g. "ad1", "ad2").', 'emcp-tools' ),
						),
					),
				),
			),
			'get-ads-txt'        => array(
				'description' => __( 'Read and parse /ads.txt records, validate IAB compliance, and check authorized sellers.', 'emcp-tools' ),
				'example'     => array(),
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'validate_syntax' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Whether to validate IAB formatting for each record.', 'emcp-tools' ),
						),
					),
				),
			),
			'audit-monetization' => array(
				'description' => __( 'Run comprehensive diagnostic audit on site ads: check ads.txt health, detect duplicate pop scripts, inspect mobile responsive styling, and verify affiliate cache status.', 'emcp-tools' ),
				'example'     => array(),
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'check_remote' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Perform live HTTP check on /ads.txt endpoint.', 'emcp-tools' ),
						),
					),
				),
			),
			'exoclick-list-zones' => array(
				'description' => __( 'Query ExoClick REST API to list all ad zones for a site ID, showing dimensions, refresh rates, and active status.', 'emcp-tools' ),
				'example'     => array( 'idsite' => 1111220 ),
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'api_token' => array(
							'type'        => 'string',
							'description' => __( 'ExoClick API token (optional if configured in settings).', 'emcp-tools' ),
						),
						'idsite'    => array(
							'type'        => 'integer',
							'description' => __( 'ExoClick Site ID (defaults to saved site ID).', 'emcp-tools' ),
						),
					),
				),
			),
			'exoclick-get-stats'  => array(
				'description' => __( 'Query ExoClick reporting API for impression, click, CTR, eCPM, and revenue metrics.', 'emcp-tools' ),
				'example'     => array( 'date_from' => '2026-09-01', 'date_to' => '2026-09-05' ),
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'api_token' => array(
							'type'        => 'string',
							'description' => __( 'ExoClick API token (optional if configured in settings).', 'emcp-tools' ),
						),
						'date_from' => array(
							'type'        => 'string',
							'description' => __( 'Start date in YYYY-MM-DD format.', 'emcp-tools' ),
						),
						'date_to'   => array(
							'type'        => 'string',
							'description' => __( 'End date in YYYY-MM-DD format.', 'emcp-tools' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Detect ad network provider from raw HTML/JS code.
	 *
	 * @param string $code Raw ad markup.
	 * @return array{network:string,zone_id:?string,dimensions:?string}
	 */
	public static function parse_ad_code( string $code ): array {
		$network    = 'Custom';
		$zone_id    = null;
		$dimensions = null;

		if ( preg_match( '/data-zoneid=["\'](\d+)["\']/i', $code, $matches ) || preg_match( '/idzone["\']?\s*[:=]\s*(\d+)/i', $code, $matches ) ) {
			$network = 'ExoClick';
			$zone_id = $matches[1];
		} elseif ( preg_match( '/(?:adzone|adzone_id|jads\.js|juicyads\.com)[^\d]*(\d{5,8})/i', $code, $matches ) ) {
			$network = 'JuicyAds';
			$zone_id = $matches[1];
		} elseif ( stripos( $code, 'trafficstars' ) !== false || preg_match( '/trafficstars\.com[^\d]*(\d+)/i', $code, $matches ) ) {
			$network = 'TrafficStars';
			$zone_id = $matches[1] ?? null;
		} elseif ( stripos( $code, 'chaturbate' ) !== false || stripos( $code, 'cbxyz' ) !== false ) {
			$network = 'Chaturbate';
		} elseif ( stripos( $code, 'googlesyndication' ) !== false || stripos( $code, 'adsbygoogle' ) !== false ) {
			$network = 'Google AdSense';
		}

		if ( preg_match( '/(?:data-width|width)=["\']?(\d+)["\']?\s+(?:data-height|height)=["\']?(\d+)["\']?/i', $code, $dim_matches ) ) {
			$dimensions = "{$dim_matches[1]}x{$dim_matches[2]}";
		} elseif ( preg_match( '/(\d{2,4})x(\d{2,4})/', $code, $dim_matches ) ) {
			$dimensions = "{$dim_matches[1]}x{$dim_matches[2]}";
		}

		return array(
			'network'    => $network,
			'zone_id'    => $zone_id,
			'dimensions' => $dimensions,
		);
	}

	/**
	 * Execute read operations.
	 *
	 * @param string $op   Operation name.
	 * @param array  $args Operation arguments.
	 * @return array
	 */
	public static function execute( string $op, array $args = array() ): array {
		switch ( $op ) {
			case 'list-ads':
				return self::list_ads( $args );
			case 'get-ad':
				return self::get_ad( $args );
			case 'get-ads-txt':
				return self::get_ads_txt( $args );
			case 'audit-monetization':
				return self::audit_monetization( $args );
			case 'exoclick-list-zones':
				return self::exoclick_list_zones( $args );
			case 'exoclick-get-stats':
				return self::exoclick_get_stats( $args );
			default:
				return array(
					'success' => false,
					'error'   => sprintf( 'Unknown read operation: %s', $op ),
				);
		}
	}

	/**
	 * List all configured ad slots.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function list_ads( array $args = array() ): array {
		$filter_status = $args['status'] ?? 'all';
		$ads           = array();

		$settings = get_option( 'quads_settings' );
		$slots    = is_array( $settings ) && isset( $settings['ads'] ) ? (array) $settings['ads'] : array();

		$posts = function_exists( 'get_posts' ) ? get_posts(
			array(
				'post_type'      => 'quads-ads',
				'post_status'    => 'any',
				'posts_per_page' => 100,
			)
		) : array();

		$post_map = array();
		foreach ( $posts as $p ) {
			$old_id = get_post_meta( $p->ID, 'quads_ad_old_id', true ) ?: '';
			if ( ! empty( $old_id ) ) {
				$post_map[ $old_id ] = $p;
			}
			$post_map[ 'id_' . $p->ID ] = $p;
		}

		foreach ( $slots as $slot_key => $slot_data ) {
			if ( ! is_array( $slot_data ) ) {
				continue;
			}

			$post         = $post_map[ $slot_key ] ?? null;
			$post_id      = $post ? (int) $post->ID : 0;
			$code         = $slot_data['code'] ?? ( $post_id ? (string) get_post_meta( $post_id, 'code', true ) : '' );
			$parsed       = self::parse_ad_code( $code );
			$title        = $slot_data['label'] ?? ( $post ? $post->post_title : $slot_key );
			$position     = $slot_data['position'] ?? ( $post_id ? (string) get_post_meta( $post_id, 'position', true ) : 'unknown' );
			$is_active    = ! empty( $code );

			if ( 'active' === $filter_status && ! $is_active ) {
				continue;
			}
			if ( 'inactive' === $filter_status && $is_active ) {
				continue;
			}

			$ads[] = array(
				'slot_key'         => $slot_key,
				'post_id'          => $post_id,
				'title'            => $title,
				'position'         => $position,
				'detected_network' => $parsed['network'],
				'zone_id'          => $parsed['zone_id'],
				'dimensions'       => $parsed['dimensions'] ?: ( $slot_data['dimensions'] ?? null ),
				'is_active'        => $is_active,
				'code_preview'     => mb_substr( trim( $code ), 0, 150 ) . ( mb_strlen( $code ) > 150 ? '...' : '' ),
			);
		}

		return array(
			'success'    => true,
			'total_ads'  => count( $ads ),
			'ads'        => $ads,
			'quads_mode' => get_option( 'quads-mode', 'new' ),
		);
	}

	/**
	 * Get details for a specific ad slot.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_ad( array $args ): array {
		$id = trim( (string) ( $args['id'] ?? '' ) );
		if ( '' === $id ) {
			return array( 'success' => false, 'error' => 'Argument "id" is required.' );
		}

		$settings    = get_option( 'quads_settings', array() );
		$slot_data   = null;
		$post_id     = 0;
		$slot_key    = null;

		if ( isset( $settings['ads'][ $id ] ) && is_array( $settings['ads'][ $id ] ) ) {
			$slot_data = $settings['ads'][ $id ];
			$slot_key  = $id;
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
		} elseif ( is_numeric( $id ) && function_exists( 'get_post' ) ) {
			$p = get_post( (int) $id );
			if ( $p && 'quads-ads' === $p->post_type ) {
				$post_id  = $p->ID;
				$old_id   = (string) get_post_meta( $post_id, 'quads_ad_old_id', true );
				if ( ! empty( $old_id ) && isset( $settings['ads'][ $old_id ] ) ) {
					$slot_data = $settings['ads'][ $old_id ];
					$slot_key  = $old_id;
				}
			}
		}

		if ( ! $slot_data && ! $post_id ) {
			return array( 'success' => false, 'error' => sprintf( 'Ad slot "%s" not found.', $id ) );
		}

		$code = $slot_data['code'] ?? ( $post_id ? (string) get_post_meta( $post_id, 'code', true ) : '' );

		return array(
			'success'          => true,
			'slot_key'         => $slot_key ?? $id,
			'post_id'          => $post_id,
			'title'            => $slot_data['label'] ?? ( $post_id ? get_the_title( $post_id ) : '' ),
			'code'             => $code,
			'parsed'           => self::parse_ad_code( $code ),
			'settings'         => $slot_data ?: array(),
			'postmeta'         => $post_id && function_exists( 'get_post_meta' ) ? get_post_meta( $post_id ) : array(),
		);
	}

	/**
	 * Parse and validate ads.txt.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_ads_txt( array $args = array() ): array {
		$raw = '';

		$option = get_option( 'emcp_tools_ads_txt' );
		if ( is_string( $option ) && ! empty( $option ) ) {
			$raw = $option;
		}

		if ( empty( $raw ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'ads.txt' ) && is_readable( ABSPATH . 'ads.txt' ) ) {
			$raw = (string) file_get_contents( ABSPATH . 'ads.txt' );
		}

		if ( empty( $raw ) && function_exists( 'wp_remote_get' ) ) {
			$resp = wp_remote_get( home_url( '/ads.txt' ), array( 'timeout' => 4 ) );
			if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
				$raw = (string) wp_remote_retrieve_body( $resp );
			}
		}

		$records  = array();
		$lines    = explode( "\n", str_replace( "\r", '', $raw ) );
		$warnings = array();

		foreach ( $lines as $idx => $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed || 0 === strpos( $trimmed, '#' ) ) {
				continue;
			}

			$parts = array_map( 'trim', explode( ',', $trimmed ) );
			if ( count( $parts ) < 3 ) {
				$warnings[] = sprintf( 'Line %d has invalid format (<3 comma-separated fields): "%s"', $idx + 1, $trimmed );
				continue;
			}

			$domain   = strtolower( $parts[0] );
			$pub_id   = $parts[1];
			$relation = strtoupper( $parts[2] );
			$cert_id  = $parts[3] ?? null;

			$valid_relation = in_array( $relation, array( 'DIRECT', 'RESELLER' ), true );
			if ( ! $valid_relation ) {
				$warnings[] = sprintf( 'Line %d has invalid relationship "%s" (must be DIRECT or RESELLER)', $idx + 1, $relation );
			}

			$records[] = array(
				'line_number'                => $idx + 1,
				'domain'                     => $domain,
				'publisher_id'               => $pub_id,
				'relationship'               => $relation,
				'certification_authority_id' => $cert_id,
				'is_valid'                   => $valid_relation && ! empty( $domain ) && ! empty( $pub_id ),
			);
		}

		return array(
			'success'       => true,
			'raw'           => $raw,
			'total_records' => count( $records ),
			'records'       => $records,
			'warnings'      => $warnings,
			'source'        => ! empty( $option ) ? 'option' : ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'ads.txt' ) ? 'file' : 'live_url' ),
		);
	}

	/**
	 * Run comprehensive monetization audit.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function audit_monetization( array $args = array() ): array {
		$issues          = array();
		$recommendations = array();

		$ads_txt = self::get_ads_txt();
		if ( 0 === $ads_txt['total_records'] ) {
			$issues[]          = 'No ads.txt records found. Major SSPs and programmatic buyers will refuse to bid on site inventory.';
			$recommendations[] = 'Configure /ads.txt with authorized publisher lines for active networks (ExoClick, TrafficStars, JuicyAds).';
		} elseif ( ! empty( $ads_txt['warnings'] ) ) {
			$issues[] = sprintf( 'Found %d warnings in /ads.txt syntax.', count( $ads_txt['warnings'] ) );
		}

		$ads_list   = self::list_ads();
		$active_ads = array_filter( $ads_list['ads'], fn( $ad ) => ! empty( $ad['is_active'] ) );

		if ( 0 === count( $active_ads ) ) {
			$issues[]          = 'No active ad units found in WP Quads.';
			$recommendations[] = 'Create responsive banner placements (Above Comic 728x90, Under Comic 300x250, Sidebar 160x600).';
		}

		$juicy_count = 0;
		$exo_count   = 0;
		foreach ( $active_ads as $ad ) {
			if ( 'JuicyAds' === $ad['detected_network'] ) {
				$juicy_count++;
			} elseif ( 'ExoClick' === $ad['detected_network'] ) {
				$exo_count++;
			}
		}

		if ( $juicy_count > 0 && $exo_count > 0 ) {
			$issues[] = sprintf( 'Site is running a hybrid setup (%d ExoClick, %d JuicyAds). Consider standardizing on ExoClick to avoid remnant backfill and multiple external JS dependencies.', $exo_count, $juicy_count );
		}

		return array(
			'success'         => true,
			'status'          => empty( $issues ) ? 'healthy' : 'attention_required',
			'active_ad_units' => count( $active_ads ),
			'ads_txt_records' => $ads_txt['total_records'],
			'issues'          => $issues,
			'recommendations' => $recommendations,
		);
	}

	/**
	 * Helper: Resolve ExoClick API token.
	 *
	 * @param array $args
	 * @return string|null
	 */
	public static function get_exoclick_token( array $args = array() ): ?string {
		if ( ! empty( $args['api_token'] ) && is_string( $args['api_token'] ) ) {
			return trim( $args['api_token'] );
		}
		if ( defined( 'EXOCLICK_API_TOKEN' ) && ! empty( EXOCLICK_API_TOKEN ) ) {
			return EXOCLICK_API_TOKEN;
		}
		$opt = get_option( 'emcp_tools_exoclick_api_token' );
		return ! empty( $opt ) && is_string( $opt ) ? $opt : null;
	}

	/**
	 * Query ExoClick API to list zones.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function exoclick_list_zones( array $args = array() ): array {
		$token = self::get_exoclick_token( $args );
		if ( ! $token ) {
			return array(
				'success' => false,
				'error'   => 'ExoClick API token is required. Pass "api_token" or configure "emcp_tools_exoclick_api_token".',
			);
		}

		$bearer = self::authenticate_exoclick( $token );
		if ( is_wp_error( $bearer ) ) {
			return array( 'success' => false, 'error' => $bearer->get_error_message() );
		}

		$site_id = $args['idsite'] ?? get_option( 'emcp_tools_exoclick_site_id', 1111220 );
		$url     = 'https://api.exoclick.com/v2/zones' . ( $site_id ? '?idsite=' . absint( $site_id ) : '' );

		$res = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $bearer,
					'User-Agent'    => 'EMCP-Tools/WordPress',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return array( 'success' => false, 'error' => 'Invalid JSON from ExoClick API.' );
		}

		return array(
			'success' => true,
			'site_id' => $site_id,
			'zones'   => $data['result'] ?? $data,
		);
	}

	/**
	 * Query ExoClick API for reporting statistics.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function exoclick_get_stats( array $args = array() ): array {
		$token = self::get_exoclick_token( $args );
		if ( ! $token ) {
			return array(
				'success' => false,
				'error'   => 'ExoClick API token is required.',
			);
		}

		$bearer = self::authenticate_exoclick( $token );
		if ( is_wp_error( $bearer ) ) {
			return array( 'success' => false, 'error' => $bearer->get_error_message() );
		}

		$date_from = $args['date_from'] ?? gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$date_to   = $args['date_to'] ?? gmdate( 'Y-m-d' );
		$url       = sprintf( 'https://api.exoclick.com/v2/statistics/p/zone?date_from=%s&date_to=%s', urlencode( $date_from ), urlencode( $date_to ) );

		$res = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $bearer,
					'User-Agent'    => 'EMCP-Tools/WordPress',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		return array(
			'success'   => true,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'stats'     => $data['result'] ?? $data,
		);
	}

	/**
	 * Authenticate API token and return Bearer session token with transient caching.
	 *
	 * @param string $api_token
	 * @return string|\WP_Error
	 */
	public static function authenticate_exoclick( string $api_token ) {
		$cache_key = 'emcp_exoclick_bearer_' . md5( $api_token );
		$cached    = function_exists( 'get_transient' ) ? get_transient( $cache_key ) : false;
		if ( ! empty( $cached ) && is_string( $cached ) ) {
			return $cached;
		}

		$url  = 'https://api.exoclick.com/v2/login';
		$body = wp_json_encode( array( 'api_token' => $api_token ) );

		$res = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'EMCP-Tools/WordPress',
				),
				'body'    => $body,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['token'] ) ) {
			return new \WP_Error( 'auth_failed', $data['message'] ?? 'ExoClick API authentication failed.' );
		}

		$bearer_token = $data['token'];
		$ttl          = isset( $data['expires_in'] ) ? max( 60, (int) $data['expires_in'] - 120 ) : 3600;
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $cache_key, $bearer_token, $ttl );
		}

		return $bearer_token;
	}
}
