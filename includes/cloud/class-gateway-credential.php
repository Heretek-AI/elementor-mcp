<?php
/**
 * Stable, pre-registered "EMCP Gateway" OAuth client.
 *
 * Phase 1 of the hosted multi-site gateway: each site can self-issue a
 * revocable refresh token against its OWN OAuth server, bound to a single,
 * idempotently-provisioned client — so repeat provisioning never grows the
 * clients table with dead rows. Reuses the existing OAuth persistence layer
 * (EMCP_Tools_OAuth_Store); no second client registry or token table.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Gateway_Credential {
	const CLIENT_NAME  = 'EMCP Gateway';
	const SCOPE        = 'gateway'; // provenance/revocation label.
	const REFRESH_TTL  = 315360000; // 10y — effectively non-expiring; store computes expires_at = now+ttl (no 0-sentinel), and each gateway refresh rotates a fresh token resetting the clock. 0 would expire immediately (find_token filters expires_at > now).
	const OPTION_FLAG  = 'emcp_tools_gateway_provisioned';

	/**
	 * Ensure the stable "EMCP Gateway" OAuth client exists and return its
	 * client_id. Idempotent: reuses an existing registration (matched by
	 * name + redirect URIs) instead of minting a new client every call.
	 *
	 * @return string The gateway client's client_id ('' on failure).
	 */
	public static function ensure_client(): string {
		$uris = array( EMCP_Tools_Cloud::base_url() . '/gateway/callback' ); // registration identity only.

		$existing = EMCP_Tools_OAuth_Store::find_client_by_registration( self::CLIENT_NAME, $uris );
		if ( $existing && ! empty( $existing['client_id'] ) ) {
			return (string) $existing['client_id'];
		}

		$client = EMCP_Tools_OAuth_Store::create_client( self::CLIENT_NAME, $uris, 0 );
		return (string) ( $client['client_id'] ?? '' );
	}

	/**
	 * Self-issue a gateway-scoped refresh token bound to $user_id.
	 * The plaintext refresh token is returned once — the caller must upload it and not persist it.
	 *
	 * @param int $user_id User the token acts as.
	 * @return array{client_id:string,refresh_token:string}
	 */
	public static function issue_for_user( int $user_id ): array {
		$client_id = self::ensure_client();
		$tok       = EMCP_Tools_OAuth_Store::issue_token( 'refresh', $client_id, $user_id, self::SCOPE, self::REFRESH_TTL );
		return array(
			'client_id'     => $client_id,
			'refresh_token' => (string) ( $tok['token'] ?? '' ),
		);
	}

	/**
	 * Issue a gateway credential for $user_id and upload it to Cloud. Best-effort:
	 * on upload failure, revokes the just-issued token so no orphan lingers, and does
	 * not set the provisioned marker.
	 *
	 * @param int $user_id User the gateway acts as.
	 * @return bool True when a credential is live in Cloud.
	 */
	public static function provision( int $user_id ): bool {
		$cred = self::issue_for_user( $user_id );
		if ( empty( $cred['refresh_token'] ) ) {
			return false;
		}
		$ok = EMCP_Tools_Cloud_Client::put_gateway_credential( $cred['client_id'], $cred['refresh_token'] );
		if ( ! $ok ) {
			$row = EMCP_Tools_OAuth_Store::find_token( $cred['refresh_token'], 'refresh' );
			if ( $row ) {
				EMCP_Tools_OAuth_Store::revoke_token( (int) $row['id'] );
			}
			return false;
		}
		update_option( self::OPTION_FLAG, 1, false );
		return true;
	}
}
