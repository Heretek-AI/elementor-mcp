<?php
/**
 * EMCP Cloud OAuth client: DCR -> authorize -> callback -> token -> refresh -> revoke.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Cloud_Connect {
	const ACTION_CONNECT    = 'emcp_tools_cloud_connect';
	const ACTION_CALLBACK   = 'emcp_tools_cloud_callback';
	const ACTION_DISCONNECT = 'emcp_tools_cloud_disconnect';
	const PENDING_TRANSIENT = 'emcp_tools_cloud_pending';

	/**
	 * Register the admin-post handlers (called from the module's register()).
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION_CONNECT, array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_' . self::ACTION_CALLBACK, array( __CLASS__, 'handle_callback' ) );
		add_action( 'admin_post_' . self::ACTION_DISCONNECT, array( __CLASS__, 'handle_disconnect' ) );
	}

	/**
	 * @return string The admin-post callback the provider redirects back to.
	 */
	public static function redirect_uri(): string {
		return admin_url( 'admin-post.php?action=' . self::ACTION_CALLBACK );
	}

	/**
	 * @return string Origin header value for token/refresh/revoke (the website origin).
	 */
	private static function origin(): string {
		return EMCP_Tools_Cloud::base_url();
	}

	/**
	 * Dynamic Client Registration: create a public PKCE client.
	 *
	 * @return string|\WP_Error The client_id, or an error.
	 */
	public static function register_client() {
		$res = EMCP_Tools_Cloud_Http::post_json(
			EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/register',
			array(
				'redirect_uris'              => array( self::redirect_uri() ),
				'token_endpoint_auth_method' => 'none',
				'client_name'                => (string) get_bloginfo( 'name' ),
				'scope'                      => EMCP_Tools_Cloud::SCOPES,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$id   = (string) ( $res['json']['client_id'] ?? '' );
		$code = (int) $res['code'];
		if ( ( 200 !== $code && 201 !== $code ) || '' === $id ) {
			return new \WP_Error( 'dcr_failed', __( 'Could not register this site with EMCP Cloud.', 'emcp-tools' ) );
		}
		return $id;
	}

	/**
	 * Build the browser authorize URL (PKCE + state carrying site identity).
	 *
	 * @param string $client_id Registered client id.
	 * @param string $verifier  PKCE verifier (challenge derived here).
	 * @param string $csrf      Opaque CSRF token embedded in state.
	 * @return string
	 */
	public static function authorize_url( string $client_id, string $verifier, string $csrf ): string {
		$state = EMCP_Tools_OAuth_Util::base64url_encode(
			(string) wp_json_encode(
				array(
					'site_uuid' => EMCP_Tools_Cloud::site_uuid(),
					'name'      => (string) get_bloginfo( 'name' ),
					'csrf'      => $csrf,
				)
			)
		);
		$params = array(
			'response_type'         => 'code',
			'client_id'             => $client_id,
			'redirect_uri'          => self::redirect_uri(),
			'scope'                 => EMCP_Tools_Cloud::SCOPES,
			'state'                 => $state,
			'code_challenge'        => EMCP_Tools_OAuth_Util::code_challenge_s256( $verifier ),
			'code_challenge_method' => 'S256',
		);
		return EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/authorize?' . http_build_query( $params );
	}

	/**
	 * Exchange an authorization code for tokens (form-encoded + Origin). On
	 * success, saves the connection bundle and returns it.
	 *
	 * @param string $code      Authorization code.
	 * @param string $verifier  PKCE verifier.
	 * @param string $client_id Client id.
	 * @return array|\WP_Error
	 */
	public static function exchange_code( string $code, string $verifier, string $client_id ) {
		$res = EMCP_Tools_Cloud_Http::post_form(
			EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/token',
			array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => self::redirect_uri(),
				'client_id'     => $client_id,
				'code_verifier' => $verifier,
			),
			array( 'Origin' => self::origin() )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$j = $res['json'];
		if ( 200 !== (int) $res['code'] || empty( $j['access_token'] ) ) {
			return new \WP_Error( 'token_failed', __( 'EMCP Cloud rejected the connection.', 'emcp-tools' ) );
		}
		$bundle = array(
			'access_token'      => (string) $j['access_token'],
			'refresh_token'     => (string) ( $j['refresh_token'] ?? '' ),
			'access_expires_at' => time() + (int) ( $j['expires_in'] ?? 3600 ),
			'client_id'         => $client_id,
			'connected_at'      => time(),
		);
		EMCP_Tools_Cloud::save_connection( $bundle );
		return $bundle;
	}

	/**
	 * Refresh the stored access token. Returns false and marks the connection
	 * unhealthy on failure.
	 *
	 * @return bool
	 */
	public static function refresh(): bool {
		$c = EMCP_Tools_Cloud::get_connection();
		if ( empty( $c['refresh_token'] ) || empty( $c['client_id'] ) ) {
			return false;
		}
		$res = EMCP_Tools_Cloud_Http::post_form(
			EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/token',
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => (string) $c['refresh_token'],
				'client_id'     => (string) $c['client_id'],
			),
			array( 'Origin' => self::origin() )
		);
		if ( is_wp_error( $res ) || 200 !== (int) $res['code'] || empty( $res['json']['access_token'] ) ) {
			$c['unhealthy'] = true;
			EMCP_Tools_Cloud::save_connection( $c );
			return false;
		}
		$j                      = $res['json'];
		$c['access_token']      = (string) $j['access_token'];
		$c['refresh_token']     = (string) ( $j['refresh_token'] ?? $c['refresh_token'] );
		$c['access_expires_at'] = time() + (int) ( $j['expires_in'] ?? 3600 );
		unset( $c['unhealthy'] );
		EMCP_Tools_Cloud::save_connection( $c );
		return true;
	}

	/**
	 * Best-effort remote revoke of the refresh token.
	 *
	 * @return void
	 */
	public static function revoke_remote(): void {
		$c = EMCP_Tools_Cloud::get_connection();
		if ( empty( $c['refresh_token'] ) ) {
			return;
		}
		EMCP_Tools_Cloud_Http::post_form(
			EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/revoke',
			array(
				'token'     => (string) $c['refresh_token'],
				'client_id' => (string) ( $c['client_id'] ?? '' ),
			),
			array( 'Origin' => self::origin() )
		);
	}

	// ── Admin-post handlers ──────────────────────────────────────────────────

	private static function guard_cap(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
	}

	private static function back( string $flag ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=emcp-tools-connection&' . $flag . '#emcp-conn-main' ) );
		exit;
	}

	/**
	 * Outbound: DCR, then redirect the browser to authorize. Nonce-protected.
	 *
	 * @return void
	 */
	public static function handle_connect(): void {
		self::guard_cap();
		check_admin_referer( self::ACTION_CONNECT );
		$client_id = self::register_client();
		if ( is_wp_error( $client_id ) ) {
			self::back( 'cloud_error=dcr' );
		}
		$verifier = EMCP_Tools_OAuth_Util::generate_code_verifier();
		$csrf     = EMCP_Tools_OAuth_Util::generate_token();
		set_transient( self::PENDING_TRANSIENT, array( 'verifier' => $verifier, 'csrf' => $csrf, 'client_id' => $client_id ), 600 );
		// The authorize URL is on the Cloud host, not this site. wp_safe_redirect()
		// blocks off-site hosts (falling back to wp-admin), so allow the Cloud host
		// for this one deliberate redirect to our own service.
		$cloud_host = wp_parse_url( EMCP_Tools_Cloud::base_url(), PHP_URL_HOST );
		if ( $cloud_host ) {
			add_filter(
				'allowed_redirect_hosts',
				static function ( $hosts ) use ( $cloud_host ) {
					$hosts[] = $cloud_host;
					return $hosts;
				}
			);
		}
		wp_safe_redirect( self::authorize_url( (string) $client_id, $verifier, $csrf ) );
		exit;
	}

	/**
	 * Inbound provider redirect: validate STATE (not a nonce), exchange the code.
	 *
	 * @return void
	 */
	public static function handle_callback(): void {
		self::guard_cap();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- provider redirect; validated by state below.
		$code     = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state_in = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$pending = get_transient( self::PENDING_TRANSIENT );
		delete_transient( self::PENDING_TRANSIENT );
		if ( ! is_array( $pending ) || '' === $code ) {
			self::back( 'cloud_error=state' );
		}
		$decoded = json_decode( EMCP_Tools_OAuth_Util::base64url_decode( $state_in ), true );
		$csrf    = is_array( $decoded ) ? (string) ( $decoded['csrf'] ?? '' ) : '';
		if ( ! EMCP_Tools_OAuth_Util::secure_equals( (string) $pending['csrf'], $csrf ) ) {
			self::back( 'cloud_error=state' );
		}
		$bundle = self::exchange_code( $code, (string) $pending['verifier'], (string) $pending['client_id'] );
		self::back( is_wp_error( $bundle ) ? 'cloud_error=token' : 'cloud_connected=1' );
	}

	/**
	 * Disconnect: revoke remotely + clear local. Nonce-protected.
	 *
	 * @return void
	 */
	public static function handle_disconnect(): void {
		self::guard_cap();
		check_admin_referer( self::ACTION_DISCONNECT );
		self::revoke_remote();
		EMCP_Tools_Cloud::clear_connection();
		self::back( 'cloud_disconnected=1' );
	}

	/**
	 * @return string Nonce'd connect button URL.
	 */
	public static function connect_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_CONNECT ), self::ACTION_CONNECT );
	}

	/**
	 * @return string Nonce'd disconnect button URL.
	 */
	public static function disconnect_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_DISCONNECT ), self::ACTION_DISCONNECT );
	}
}
