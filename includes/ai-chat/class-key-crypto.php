<?php
/**
 * Key Crypto — AES-256-CBC encryption for third-party AI provider API keys.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Key_Crypto {

	private static function secret_key(): string {
		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'emcp-fallback-salt';
		$salt .= defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
		return hash( 'sha256', $salt, true );
	}

	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) { return ''; }
		$iv = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', self::secret_key(), 0, $iv );
		return base64_encode( $iv . $cipher );
	}

	public static function decrypt( string $encrypted ): string {
		if ( '' === $encrypted ) { return ''; }
		$data = base64_decode( $encrypted );
		if ( strlen( $data ) < 17 ) { return ''; }
		$iv = substr( $data, 0, 16 );
		$cipher = substr( $data, 16 );
		$plain = openssl_decrypt( $cipher, 'AES-256-CBC', self::secret_key(), 0, $iv );
		return false !== $plain ? $plain : '';
	}
}
