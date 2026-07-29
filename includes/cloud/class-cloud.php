<?php
/**
 * EMCP Cloud config + encrypted connection store.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helpers for the Cloud base URL, the stable per-site UUID, and the
 * encrypted OAuth token bundle. No network here.
 */
class EMCP_Tools_Cloud {
	const OPTION_CONNECTION = 'emcp_tools_cloud_connection';
	const OPTION_BASE_URL   = 'emcp_tools_cloud_base_url';
	const OPTION_SITE_UUID  = 'emcp_tools_site_uuid';
	const DEFAULT_BASE_URL  = 'https://emcptools.com';
	const SCOPES            = 'openid cloud offline_access';

	/**
	 * The EMCP Cloud base URL. Constant overrides option overrides default;
	 * filterable for staging/self-host.
	 *
	 * @return string No trailing slash.
	 */
	public static function base_url(): string {
		if ( defined( 'EMCP_TOOLS_CLOUD_URL' ) && '' !== (string) EMCP_TOOLS_CLOUD_URL ) {
			$url = (string) EMCP_TOOLS_CLOUD_URL;
		} else {
			$stored = (string) get_option( self::OPTION_BASE_URL, '' );
			$url    = '' !== $stored ? $stored : self::DEFAULT_BASE_URL;
		}
		return rtrim( (string) apply_filters( 'emcp_tools_cloud_base_url', $url ), '/' );
	}

	/**
	 * The stable per-site UUID, minted lazily on first read.
	 *
	 * @return string
	 */
	public static function site_uuid(): string {
		$uuid = (string) get_option( self::OPTION_SITE_UUID, '' );
		if ( '' === $uuid ) {
			$uuid = wp_generate_uuid4();
			update_option( self::OPTION_SITE_UUID, $uuid, false );
		}
		return $uuid;
	}

	/**
	 * Persist the token bundle, encrypted at rest.
	 *
	 * @param array $bundle { access_token, refresh_token, access_expires_at, client_id, ... }.
	 * @return void
	 */
	public static function save_connection( array $bundle ): void {
		update_option( self::OPTION_CONNECTION, EMCP_Tools_Secret::encrypt( (string) wp_json_encode( $bundle ) ), false );
	}

	/**
	 * Read the decrypted token bundle (empty array when not connected).
	 *
	 * @return array
	 */
	public static function get_connection(): array {
		$raw = (string) get_option( self::OPTION_CONNECTION, '' );
		if ( '' === $raw ) {
			return array();
		}
		$json = json_decode( EMCP_Tools_Secret::decrypt_if_needed( $raw ), true );
		return is_array( $json ) ? $json : array();
	}

	/**
	 * @return void
	 */
	public static function clear_connection(): void {
		delete_option( self::OPTION_CONNECTION );
	}

	/**
	 * @return bool
	 */
	public static function is_connected(): bool {
		$c = self::get_connection();
		return ! empty( $c['access_token'] ) || ! empty( $c['refresh_token'] );
	}

	/**
	 * Connection status for the admin card.
	 *
	 * @return array { connected:bool, base_url:string, expires_at:int, healthy:bool }.
	 */
	public static function status(): array {
		$c = self::get_connection();
		return array(
			'connected'  => self::is_connected(),
			'base_url'   => self::base_url(),
			'expires_at' => (int) ( $c['access_expires_at'] ?? 0 ),
			'healthy'    => empty( $c['unhealthy'] ),
		);
	}
}
