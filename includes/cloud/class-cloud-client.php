<?php
/**
 * Authenticated EMCP Cloud REST client (bearer + auto-refresh).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Cloud_Client {
	const LEEWAY = 60; // refresh this many seconds before expiry.

	/**
	 * A valid access token, refreshing if near/after expiry. '' if unavailable.
	 *
	 * @return string
	 */
	public static function valid_access_token(): string {
		$c = EMCP_Tools_Cloud::get_connection();
		if ( empty( $c['access_token'] ) ) {
			return '';
		}
		if ( (int) ( $c['access_expires_at'] ?? 0 ) - self::LEEWAY <= time() ) {
			if ( ! EMCP_Tools_Cloud_Connect::refresh() ) {
				return '';
			}
			$c = EMCP_Tools_Cloud::get_connection();
		}
		return (string) ( $c['access_token'] ?? '' );
	}

	/**
	 * Authenticated JSON request to the Cloud API.
	 *
	 * @param string $method HTTP method (informational; JSON POST/GET).
	 * @param string $path   Path beginning with '/'.
	 * @param array  $body   JSON body for writes.
	 * @return array|\WP_Error
	 */
	public static function request( string $method, string $path, array $body = array() ) {
		$token = self::valid_access_token();
		if ( '' === $token ) {
			return new \WP_Error( 'not_connected', __( 'This site is not connected to EMCP Cloud.', 'emcp-tools' ) );
		}
		$res = EMCP_Tools_Cloud_Http::post_json(
			EMCP_Tools_Cloud::base_url() . $path,
			$body,
			array(
				'Authorization' => 'Bearer ' . $token,
				'X-EMCP-Method' => strtoupper( $method ),
				'Origin'        => EMCP_Tools_Cloud::base_url(),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( (int) $res['code'] < 200 || (int) $res['code'] >= 300 ) {
			return new \WP_Error( 'cloud_http_' . $res['code'], (string) ( $res['json']['error'] ?? 'cloud_error' ), array( 'status' => $res['code'] ) );
		}
		return $res['json'];
	}
}
