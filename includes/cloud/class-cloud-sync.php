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
}
