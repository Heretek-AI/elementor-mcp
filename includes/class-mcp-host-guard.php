<?php
/**
 * MCP host-mismatch guard.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Refuses MCP requests whose Host header does not match this site's home host,
 * so a connector still pointed at an old (renamed/temporary) domain fails
 * loudly with an actionable error instead of a generic "tool execution error".
 *
 * See bug report Issue 2 (a connector kept a stale hostname after a site URL
 * change; tool listing still worked but every execution failed opaquely).
 */
class EMCP_Tools_MCP_Host_Guard {

	/**
	 * Normalise a host for comparison: lowercase, strip trailing port and a
	 * leading "www.".
	 *
	 * @param string $host Raw host.
	 * @return string Normalised host.
	 */
	private static function norm( string $host ): string {
		$host = strtolower( trim( $host ) );
		$host = (string) preg_replace( '/:\d+$/', '', $host );   // strip :443
		$host = (string) preg_replace( '/^www\./', '', $host );  // strip www.
		return $host;
	}

	/**
	 * Pure host comparison. An empty request host is treated as a match so a
	 * missing Host header can never brick the endpoint (fail-open).
	 *
	 * @param string $request_host Incoming request host.
	 * @param string $home_host    This site's home_url() host.
	 * @return bool True when the hosts match (or the request host is empty).
	 */
	public static function host_matches( string $request_host, string $home_host ): bool {
		$req = self::norm( $request_host );
		if ( '' === $req ) {
			return true;
		}
		return $req === self::norm( $home_host );
	}

	/**
	 * rest_pre_dispatch filter — acts only on the MCP server route.
	 *
	 * @param mixed  $result  Dispatch result (passed through untouched on match).
	 * @param mixed  $server  REST server (unused).
	 * @param mixed  $request WP_REST_Request.
	 * @return mixed The original result, or a WP_Error on host mismatch.
	 */
	public static function guard( $result, $server, $request ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $result;
		}
		if ( 0 !== strpos( (string) $request->get_route(), '/mcp/emcp-tools-server' ) ) {
			return $result;
		}
		/**
		 * Filter: disable the MCP host guard (e.g. for unusual reverse-proxy setups).
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'emcp_tools_mcp_host_guard_enabled', true ) ) {
			return $result;
		}

		$req_host  = (string) ( $request->get_header( 'host' ) ? $request->get_header( 'host' ) : ( isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( self::host_matches( $req_host, $home_host ) ) {
			return $result;
		}

		return new WP_Error(
			'emcp_host_mismatch',
			sprintf(
				/* translators: %s: the correct MCP endpoint URL. */
				__( 'Site URL mismatch: this connector is pointed at an old domain. The MCP endpoint now lives at %s — reconnect using that URL.', 'emcp-tools' ),
				esc_url_raw( home_url( '/wp-json/mcp/emcp-tools-server' ) )
			),
			array( 'status' => 421 ) // 421 Misdirected Request.
		);
	}
}
