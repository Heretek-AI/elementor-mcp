<?php
/**
 * Shared connector transfer engine (Pro).
 *
 * Pure, static, WordPress-state-free transfer machinery: streams a .emcp
 * archive to a paired EMCP connector as HMAC-signed 2 MB packets, finalizes,
 * and polls the destination job. Used by the MCP migrate abilities and by the
 * admin Push / Sync panels so every entry point shares one transfer path with
 * one set of canonical strings and error codes.
 *
 * The methods here were moved verbatim from EMCP_Tools_Migrate_Abilities so the
 * wire behaviour (canonical strings, timeouts, WP_Error codes and resume_offset
 * payloads) is byte-identical to what already-paired 1.x connectors expect.
 *
 * @package EMCP_Tools
 * @since   3.16.0
 */

defined( 'ABSPATH' ) || exit;

class EMCP_Tools_Migration_Engine {

	/** Packet payload size in bytes (2 MB). */
	const CHUNK_SIZE = 2 * 1024 * 1024;

	/**
	 * Connector REST base for a site URL.
	 *
	 * @param string $url Site home URL.
	 * @return string Trailing-slash-less REST base.
	 */
	public static function endpoint_for_url( string $url ): string {
		return untrailingslashit( $url ) . '/wp-json/emcp-connector/v1';
	}

	/**
	 * Resolve which destination an action targets: a stored paired target
	 * (target_id) or a raw remote_url + secret_key. Single resolver shared by the
	 * MCP abilities and the admin module so both use identical validation.
	 *
	 * @param array $input { target_id?:int, remote_url?:string, secret_key?:string }.
	 * @return array{endpoint:string,secret:string,label:string,target_url:string}|\WP_Error
	 */
	public static function destination_from_input( array $input ) {
		if ( ! empty( $input['target_id'] ) ) {
			if ( ! class_exists( 'EMCP_Tools_Migrate_Targets' ) ) {
				return new WP_Error( 'engine_unavailable', __( 'The paired-targets store is not available.', 'emcp-tools' ) );
			}
			$target = EMCP_Tools_Migrate_Targets::get( (int) $input['target_id'] );
			if ( ! $target ) {
				return new WP_Error( 'target_missing', __( 'Paired target not found.', 'emcp-tools' ) );
			}
			$secret = EMCP_Tools_Migrate_Targets::get_secret( (int) $target['id'] );
			if ( '' === $secret ) {
				return new WP_Error( 'target_secret', __( 'The paired target secret could not be decrypted.', 'emcp-tools' ) );
			}
			return array(
				'endpoint'   => (string) $target['endpoint'],
				'secret'     => $secret,
				'label'      => (string) $target['label'],
				'target_url' => (string) $target['target_url'],
			);
		}

		$remote = trim( (string) ( $input['remote_url'] ?? '' ) );
		if ( '' === $remote || ! wp_http_validate_url( $remote ) ) {
			return new WP_Error( 'invalid_remote', __( 'A valid http(s) remote_url is required (or use target_id).', 'emcp-tools' ) );
		}
		$secret = (string) ( $input['secret_key'] ?? '' );
		if ( '' === $secret ) {
			return new WP_Error( 'secret_required', __( 'secret_key is required (must match EMCP_CONNECTOR_SECRET on the destination).', 'emcp-tools' ) );
		}
		$url = untrailingslashit( $remote );
		return array(
			'endpoint'   => self::endpoint_for_url( $remote ),
			'secret'     => $secret,
			'label'      => $url,
			'target_url' => $url,
		);
	}

	/**
	 * Fresh transfer id for a push.
	 *
	 * @return string
	 */
	public static function transfer_id(): string {
		return 'emcp-' . wp_generate_password( 12, false );
	}

	/**
	 * Pure packet math — number of CHUNK_SIZE packets for a byte size.
	 *
	 * @param int $size Archive size in bytes.
	 * @return int
	 */
	public static function packet_count( int $size ): int {
		return (int) ceil( $size / self::CHUNK_SIZE );
	}

	/**
	 * Stream an archive to the connector as signed packets.
	 *
	 * @param string $path        Archive path.
	 * @param string $endpoint    Connector REST base.
	 * @param string $secret      Shared secret.
	 * @param string $transfer_id Transfer id for this push.
	 * @param array  $opts        Optional: 'on_packet' callback (int index, int total),
	 *                            'resume_offset' (skip packets below this index — an
	 *                            idempotent retry against a connector that already
	 *                            holds the earlier packets).
	 * @return true|\WP_Error
	 */
	public static function push_packets( string $path, string $endpoint, string $secret, string $transfer_id, array $opts = array() ) {
		$chunk_size = self::CHUNK_SIZE;
		$total      = self::packet_count( filesize( $path ) );
		$start      = max( 0, (int) ( $opts['resume_offset'] ?? 0 ) );
		$on_packet  = isset( $opts['on_packet'] ) && is_callable( $opts['on_packet'] ) ? $opts['on_packet'] : null;

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $handle ) {
			return new WP_Error( 'read_failed', __( 'Could not read the archive for upload.', 'emcp-tools' ) );
		}
		if ( $start > 0 ) {
			fseek( $handle, $start * $chunk_size ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$index = $start;
		while ( ! feof( $handle ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$data = fread( $handle, $chunk_size ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( '' === $data || false === $data ) {
				if ( feof( $handle ) ) {
					break;
				}
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new WP_Error( 'read_failed', __( 'Failed while reading the archive.', 'emcp-tools' ) );
			}
			$chunk_sha = hash( 'sha256', $data );
			$canonical = 'packet|' . $chunk_sha . '|' . $transfer_id . '|' . $index;
			$response  = wp_remote_post(
				$endpoint . '/packet',
				array(
					'timeout' => 120,
					'headers' => array(
						'Content-Type'     => 'application/json',
						'X-EMCP-Signature' => hash_hmac( 'sha256', $canonical, $secret ),
					),
					'body'    => wp_json_encode( array(
						'transfer_id' => $transfer_id,
						'index'       => $index,
						'total'       => $total,
						'data_b64'    => base64_encode( $data ),
						'sha256'      => $chunk_sha,
					) ),
				)
			);
			$error = self::packet_error( $response, $index );
			if ( is_wp_error( $error ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return $error;
			}
			$index++;
			if ( $on_packet ) {
				call_user_func( $on_packet, $index, $total );
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return true;
	}

	/**
	 * Normalize a packet upload response into a WP_Error, or null when accepted.
	 *
	 * @param mixed $response wp_remote_post result.
	 * @param int   $index    Packet index (for resume reporting).
	 * @return \WP_Error|null
	 */
	private static function packet_error( $response, int $index ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'packet_failed',
				sprintf( /* translators: 1: error message. */ __( 'Upload failed at packet %1$d: %2$s', 'emcp-tools' ), $index, $response->get_error_message() ),
				array( 'resume_offset' => $index )
			);
		}
		$code  = (int) wp_remote_retrieve_response_code( $response );
		$rbody = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $rbody ) || ! empty( $rbody['code'] ) ) {
			$msg = is_array( $rbody ) && isset( $rbody['message'] ) ? (string) $rbody['message'] : sprintf( 'HTTP %d', $code );
			return new WP_Error( 'packet_rejected', sprintf( /* translators: 1: packet index, 2: message. */ __( 'Destination rejected packet %1$d: %2$s', 'emcp-tools' ), $index, $msg ), array( 'resume_offset' => $index ) );
		}
		return null;
	}

	/**
	 * Ask the connector to assemble + verify the transfer and start the restore.
	 *
	 * @param string $path        Archive path (for its sha256).
	 * @param string $endpoint    Connector REST base.
	 * @param string $secret      Shared secret.
	 * @param string $transfer_id Transfer id just pushed.
	 * @return string|\WP_Error The destination job id.
	 */
	public static function finalize( string $path, string $endpoint, string $secret, string $transfer_id ) {
		$whole_sha = hash_file( 'sha256', $path );
		$canonical = 'finalize|' . $whole_sha . '|' . $transfer_id;
		$response  = wp_remote_post(
			$endpoint . '/finalize',
			array(
				'timeout'  => 300,
				'headers'  => array(
					'Content-Type'     => 'application/json',
					'X-EMCP-Signature' => hash_hmac( 'sha256', $canonical, $secret ),
				),
				'body'     => wp_json_encode( array( 'transfer_id' => $transfer_id, 'sha256' => $whole_sha ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'finalize_failed', $response->get_error_message() );
		}
		$code  = (int) wp_remote_retrieve_response_code( $response );
		$rbody = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $rbody ) || empty( $rbody['job_id'] ) ) {
			$msg = is_array( $rbody ) && isset( $rbody['message'] ) ? (string) $rbody['message'] : sprintf( 'HTTP %d', $code );
			return new WP_Error( 'finalize_rejected', sprintf( /* translators: %s: message. */ __( 'Destination restore did not start: %s', 'emcp-tools' ), $msg ) );
		}
		return (string) $rbody['job_id'];
	}

	/**
	 * Poll a connector job until it settles.
	 *
	 * @param string $endpoint Connector REST base.
	 * @param string $secret   Shared secret.
	 * @param string $job_id   Destination job id.
	 * @return array|null Job payload (state done/error/…), null when still pending after the bound.
	 */
	public static function poll_job( string $endpoint, string $secret, string $job_id ) {
		$job = null;
		for ( $i = 0; $i < 30; $i++ ) {
			$response = wp_remote_get(
				$endpoint . '/job/' . rawurlencode( $job_id ),
				array(
					'timeout' => 30,
					'headers' => array( 'X-EMCP-Signature' => hash_hmac( 'sha256', 'job|' . $job_id, $secret ) ),
				)
			);
			if ( ! is_wp_error( $response ) ) {
				$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				if ( is_array( $body ) && isset( $body['state'] ) ) {
					$job = $body;
					if ( 'done' === $body['state'] || 'error' === $body['state'] ) {
						break;
					}
				}
			}
			sleep( 2 ); // phpcs:ignore WordPress.PHP -- poll cadence for a remote batch job.
		}
		return $job;
	}

	/**
	 * Push an archive to a connector and wait for the destination restore.
	 *
	 * Orchestration shared by the MCP tools and the admin Push / Sync panels.
	 *
	 * @param array $opts { path, endpoint, secret, transfer_id?, poll?, on_packet?,
	 *                      resume_offset? }.
	 * @return array|\WP_Error Success array { job_id, state, archive, job, success }
	 *                         or a WP_Error from a failed push/finalize/restore.
	 */
	public static function push_archive( array $opts ) {
		$path     = (string) ( $opts['path'] ?? '' );
		$endpoint = (string) ( $opts['endpoint'] ?? '' );
		$secret   = (string) ( $opts['secret'] ?? '' );
		if ( '' === $path || '' === $endpoint || '' === $secret ) {
			return new WP_Error( 'invalid_args', __( 'A path, endpoint and secret are required to push.', 'emcp-tools' ) );
		}

		$transfer_id = (string) ( $opts['transfer_id'] ?? '' );
		if ( '' === $transfer_id ) {
			$transfer_id = self::transfer_id();
		}

		$pushed = self::push_packets( $path, $endpoint, $secret, $transfer_id, $opts );
		if ( is_wp_error( $pushed ) ) {
			return $pushed;
		}
		$job_id = self::finalize( $path, $endpoint, $secret, $transfer_id );
		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}

		$archive = basename( $path );

		// Without polling, report the handed-out job id and let the caller poll.
		if ( empty( $opts['poll'] ) ) {
			return array(
				'success' => true,
				'job_id'  => $job_id,
				'state'   => 'finalized',
				'archive' => $archive,
				'job'     => null,
			);
		}

		// Poll the job until it settles (the connector restores inline, so this
		// normally returns on the first poll; bounded at ~60 s).
		$job = self::poll_job( $endpoint, $secret, $job_id );

		$state = ( $job && isset( $job['state'] ) ) ? $job['state'] : 'unknown';
		if ( 'done' !== $state ) {
			$code = ( 'error' === $state ) ? 'restore_failed' : 'restore_pending';
			$msg  = ( 'error' === $state )
				? __( 'Destination reported an error during restore.', 'emcp-tools' )
				/* translators: %s: destination job state. */
				: sprintf( __( 'Destination restore is not finished (state: %s). Re-run the push or poll the returned job.', 'emcp-tools' ), $state );
			return new WP_Error( $code, $msg, array( 'job_id' => $job_id, 'job' => $job, 'archive' => $archive ) );
		}

		return array(
			'success' => true,
			'job_id'  => $job_id,
			'state'   => $state,
			'archive' => $archive,
			'job'     => $job,
		);
	}
}
