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
	const CLIENT_NAME = 'EMCP Gateway';
	const SCOPE       = 'gateway'; // provenance/revocation label.
	const REFRESH_TTL = 0;         // 0 = non-expiring refresh.
	const OPTION_FLAG = 'emcp_tools_gateway_provisioned';

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
}
