<?php
/**
 * Deep Serialized Search & Replace Engine.
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Search_Replace {

	public static function replace( $data, string $search, string $replace ) {
		if ( is_string( $data ) ) {
			// Check if serialized.
			if ( is_serialized( $data ) ) {
				$unserialized = @unserialize( $data );
				if ( false !== $unserialized || 'b:0;' === $data ) {
					return serialize( self::replace( $unserialized, $search, $replace ) );
				}
			}
			return str_replace( $search, $replace, $data );
		}

		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $k => $v ) {
				$out[ self::replace( $k, $search, $replace ) ] = self::replace( $v, $search, $replace );
			}
			return $out;
		}

		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $prop => $val ) {
				$data->$prop = self::replace( $val, $search, $replace );
			}
			return $data;
		}

		return $data;
	}
}
