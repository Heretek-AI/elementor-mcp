<?php
/**
 * AI Chat Image Reader for vision models.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Chat_Image_Reader {
	public static function to_base64( int $attachment_id ): string {
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) { return ''; }
		$data = file_get_contents( $path );
		$mime = wp_check_filetype( $path )['type'] ?: 'image/jpeg';
		return 'data:' . $mime . ';base64,' . base64_encode( $data );
	}
}
