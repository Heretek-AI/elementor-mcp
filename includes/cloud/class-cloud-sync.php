<?php
/**
 * EMCP Cloud sync: push/pull sandbox artifact bundles + config via the Cloud API.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Cloud_Sync {
	/**
	 * @return EMCP_Tools_Sandbox_Cloud_Abilities
	 */
	private static function abilities(): EMCP_Tools_Sandbox_Cloud_Abilities {
		return new EMCP_Tools_Sandbox_Cloud_Abilities();
	}

	private static function not_connected(): \WP_Error {
		return new \WP_Error( 'not_connected', __( 'This site is not connected to EMCP Cloud.', 'emcp-tools' ) );
	}

	/**
	 * Workspace plan + usage.
	 *
	 * @return array|\WP_Error
	 */
	public static function status() {
		return EMCP_Tools_Cloud_Client::get( '/api/cloud/v1/me' );
	}

	/**
	 * List the account's cloud artifacts (optionally by kind).
	 *
	 * @param string $kind Optional kind filter.
	 * @return array|\WP_Error
	 */
	public static function list_remote( string $kind = '' ) {
		$path = '/api/cloud/v1/artifacts' . ( '' !== $kind ? '?kind=' . rawurlencode( $kind ) : '' );
		return EMCP_Tools_Cloud_Client::get( $path );
	}

	/**
	 * Back up a local sandbox artifact to the cloud.
	 *
	 * @param string $kind Artifact kind (block/widget/snippet).
	 * @param int    $id   Local artifact id.
	 * @return array|\WP_Error
	 */
	public static function backup( string $kind, int $id ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$art = self::abilities()->resolve_artifact( $kind );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		$bundle = $art->to_bundle( $id );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}
		return EMCP_Tools_Cloud_Client::put(
			'/api/cloud/v1/artifacts',
			array(
				'artifact_uuid'    => (string) ( $bundle['uuid'] ?? '' ),
				'kind'             => (string) ( $bundle['kind'] ?? $kind ),
				'title'            => (string) ( $bundle['meta']['title'] ?? '' ),
				'origin_site_uuid' => EMCP_Tools_Cloud::site_uuid(),
				'bundle'           => (string) wp_json_encode( $bundle ),
				'checksum'         => (string) ( $bundle['checksum'] ?? '' ),
			)
		);
	}

	/**
	 * Pull a cloud artifact into this site (imports as a new local draft).
	 *
	 * @param string $artifact_uuid Cloud artifact uuid.
	 * @param string $kind          Artifact kind (falls back to the bundle's kind).
	 * @return array|\WP_Error
	 */
	public static function pull( string $artifact_uuid, string $kind = '' ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$res = EMCP_Tools_Cloud_Client::get( '/api/cloud/v1/artifacts/' . rawurlencode( $artifact_uuid ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$bundle = json_decode( (string) ( $res['bundle'] ?? '' ), true );
		if ( ! is_array( $bundle ) ) {
			return new \WP_Error( 'bad_bundle', __( 'The cloud artifact could not be read.', 'emcp-tools' ) );
		}
		$art = self::abilities()->resolve_artifact( '' !== $kind ? $kind : (string) ( $bundle['kind'] ?? '' ) );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		$new_id = $art->apply_bundle( $bundle );
		return is_wp_error( $new_id ) ? $new_id : array( 'id' => (int) $new_id );
	}

	/**
	 * Push a config blob (settings/brand_kit/tool_toggles) to the cloud.
	 *
	 * @param string $type Config type.
	 * @param array  $data Config data.
	 * @return array|\WP_Error
	 */
	public static function push_config( string $type, array $data ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		return EMCP_Tools_Cloud_Client::put(
			'/api/cloud/v1/config/' . rawurlencode( $type ),
			array( 'scope' => 'site', 'site_uuid' => EMCP_Tools_Cloud::site_uuid(), 'data' => (string) wp_json_encode( $data ) )
		);
	}

	/**
	 * Pull a config blob from the cloud.
	 *
	 * @param string $type Config type.
	 * @return array|\WP_Error
	 */
	public static function pull_config( string $type ) {
		return EMCP_Tools_Cloud_Client::get(
			'/api/cloud/v1/config/' . rawurlencode( $type ) . '?site_uuid=' . rawurlencode( EMCP_Tools_Cloud::site_uuid() )
		);
	}

	/**
	 * Browse published marketplace listings (public; no connection required, but
	 * we still route through the client for a consistent base URL).
	 *
	 * @param string $category Optional category filter.
	 * @return array|\WP_Error
	 */
	public static function marketplace_list( string $category = '' ) {
		return EMCP_Tools_Cloud_Client::get(
			'/api/cloud/v1/marketplace' . ( '' !== $category ? '?category=' . rawurlencode( $category ) : '' )
		);
	}

	/**
	 * Website submit-page URL for publishing a backed-up artifact to the
	 * marketplace. The artifact must already be pushed to the cloud (its
	 * stable uuid is what the submit page looks up). '' if unresolvable.
	 *
	 * @param string $kind block|widget|snippet.
	 * @param int    $id   Local artifact id.
	 * @return string
	 */
	public static function publish_url( string $kind, int $id ): string {
		$art = self::abilities()->resolve_artifact( $kind );
		if ( ! $art ) {
			return '';
		}
		$uuid = (string) $art->uuid( $id );
		if ( '' === $uuid ) {
			return '';
		}
		return trailingslashit( EMCP_Tools_Cloud::base_url() ) . 'account/marketplace/submit?artifact=' . rawurlencode( $uuid );
	}

	/**
	 * Publish one of your cloud artifacts as a marketplace listing (pending
	 * moderation).
	 *
	 * @param string $artifact_uuid Cloud artifact uuid.
	 * @param string $title         Listing title.
	 * @param string $summary       Short description.
	 * @param string $category      Category.
	 * @return array|\WP_Error
	 */
	public static function marketplace_publish( string $artifact_uuid, string $title, string $summary = '', string $category = '' ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		return EMCP_Tools_Cloud_Client::put(
			'/api/cloud/v1/marketplace',
			array( 'artifact_uuid' => $artifact_uuid, 'title' => $title, 'summary' => $summary, 'category' => $category )
		);
	}

	/**
	 * Install a marketplace listing into this site (imports as a new draft).
	 *
	 * @param string $slug Listing slug.
	 * @return array|\WP_Error
	 */
	public static function marketplace_install( string $slug ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$res = EMCP_Tools_Cloud_Client::request(
			'POST',
			'/api/cloud/v1/marketplace/' . rawurlencode( $slug ) . '/install',
			array( 'site_uuid' => EMCP_Tools_Cloud::site_uuid() )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$bundle = json_decode( (string) ( $res['bundle'] ?? '' ), true );
		if ( ! is_array( $bundle ) ) {
			return new \WP_Error( 'bad_bundle', __( 'The listing bundle could not be read.', 'emcp-tools' ) );
		}
		$art = self::abilities()->resolve_artifact( (string) ( $res['kind'] ?? $bundle['kind'] ?? '' ) );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		$new_id = $art->apply_bundle( $bundle );
		return is_wp_error( $new_id ) ? $new_id : array( 'id' => (int) $new_id );
	}

	/**
	 * Marketplace state for a local artifact (published? pending update? updatable?).
	 * Drives the Sandbox buttons.
	 *
	 * @param string $kind block|widget|snippet.
	 * @param int    $id   Local artifact id.
	 * @return array|\WP_Error
	 */
	public static function marketplace_state( string $kind, int $id ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$art = self::abilities()->resolve_artifact( $kind );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		$uuid = (string) $art->uuid( $id );
		if ( '' === $uuid ) {
			return new \WP_Error( 'no_uuid', __( 'Artifact has no cloud id yet.', 'emcp-tools' ) );
		}
		return EMCP_Tools_Cloud_Client::get( '/api/cloud/v1/marketplace/state?artifact_uuid=' . rawurlencode( $uuid ) );
	}

	/**
	 * Push an update to an already-published marketplace listing: re-push the
	 * bundle (which creates a new cloud version) and flag the listing as a
	 * pending update for re-review.
	 *
	 * @param string $kind      block|widget|snippet.
	 * @param int    $id        Local artifact id.
	 * @param string $changelog Optional author notes.
	 * @return array|\WP_Error
	 */
	public static function push_update( string $kind, int $id, string $changelog = '' ) {
		$backup = self::backup( $kind, $id );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		$art = self::abilities()->resolve_artifact( $kind );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		return EMCP_Tools_Cloud_Client::request(
			'POST',
			'/api/cloud/v1/marketplace/update',
			array( 'artifact_uuid' => (string) $art->uuid( $id ), 'changelog' => $changelog )
		);
	}

	/**
	 * Public marketplace URL for a published listing.
	 *
	 * @param string $slug Listing slug.
	 * @return string
	 */
	public static function marketplace_view_url( string $slug ): string {
		return trailingslashit( EMCP_Tools_Cloud::base_url() ) . 'marketplace/' . rawurlencode( $slug );
	}
}
