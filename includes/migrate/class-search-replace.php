<?php
/**
 * Deep Serialized Search & Replace Engine.
 *
 * Thin compatibility wrapper over EMCP_Tools_Serialized_Search_Replace — the
 * byte-accurate engine that never unserializes (see that class for why the old
 * @unserialize() + serialize() path was replaced).
 *
 * @package EMCP_Tools
 * @since   3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Search_Replace {

	public static function replace( $data, string $search, string $replace ) {
		return EMCP_Tools_Serialized_Search_Replace::replace( $data, $search, $replace );
	}
}
