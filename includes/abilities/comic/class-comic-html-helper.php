<?php
/**
 * Helper for generating and parsing the Comic Easel multi-image custom field
 * (`comic-html-below` / `ceo_html_below_comic`).
 *
 * @package EMCP_Tools
 * @since   3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Comic_Html_Helper
 */
class EMCP_Tools_Comic_Html_Helper {

	/**
	 * Build HTML markup for comic-html-below from an array of image descriptors.
	 *
	 * Each descriptor can be:
	 * - int: attachment ID
	 * - string: image URL
	 * - array: { id?: int, url?: string, alt?: string, width?: int, height?: int, class?: string }
	 *
	 * @param array $images List of image descriptors.
	 * @return string Standard HTML markup for comic-html-below.
	 */
	public static function build_html_below( array $images ): string {
		$tags = array();

		foreach ( $images as $img ) {
			if ( empty( $img ) ) {
				continue;
			}

			$id     = 0;
			$url    = '';
			$alt    = '';
			$width  = 0;
			$height = 0;
			$class  = 'alignnone size-full';

			if ( is_numeric( $img ) ) {
				$id = absint( $img );
			} elseif ( is_string( $img ) ) {
				$url = esc_url_raw( trim( $img ) );
			} elseif ( is_array( $img ) ) {
				if ( ! empty( $img['id'] ) ) {
					$id = absint( $img['id'] );
				}
				if ( ! empty( $img['url'] ) ) {
					$url = esc_url_raw( trim( (string) $img['url'] ) );
				}
				if ( isset( $img['alt'] ) ) {
					$alt = sanitize_text_field( (string) $img['alt'] );
				}
				if ( ! empty( $img['width'] ) ) {
					$width = absint( $img['width'] );
				}
				if ( ! empty( $img['height'] ) ) {
					$height = absint( $img['height'] );
				}
				if ( ! empty( $img['class'] ) ) {
					$class = sanitize_text_field( (string) $img['class'] );
				}
			}

			// If ID is known, populate details from the WordPress media library.
			if ( $id > 0 && function_exists( 'wp_get_attachment_image_src' ) ) {
				$src_info = wp_get_attachment_image_src( $id, 'full' );
				if ( is_array( $src_info ) && ! empty( $src_info[0] ) ) {
					if ( empty( $url ) ) {
						$url = $src_info[0];
					}
					if ( 0 === $width && ! empty( $src_info[1] ) ) {
						$width = (int) $src_info[1];
					}
					if ( 0 === $height && ! empty( $src_info[2] ) ) {
						$height = (int) $src_info[2];
					}
				}
				if ( '' === $alt && function_exists( 'get_post_meta' ) ) {
					$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
				}
				if ( false === strpos( $class, 'wp-image-' ) ) {
					$class .= ' wp-image-' . $id;
				}
			}

			if ( empty( $url ) ) {
				continue;
			}

			$tag = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"';
			if ( $width > 0 ) {
				$tag .= ' width="' . $width . '"';
			}
			if ( $height > 0 ) {
				$tag .= ' height="' . $height . '"';
			}
			if ( ! empty( $class ) ) {
				$tag .= ' class="' . esc_attr( trim( $class ) ) . '"';
			}
			$tag .= ' />';

			$tags[] = $tag;
		}

		return implode( "\n", $tags );
	}

	/**
	 * Parse comic-html-below markup into structured image objects.
	 *
	 * Extracts src, attachment_id, width, height, alt, and CSS classes.
	 *
	 * @param string $html Raw HTML content of comic-html-below.
	 * @return array<int,array{src:string,attachment_id:int,width:?int,height:?int,alt:string,class:string,raw:string}>
	 */
	public static function parse_html_below( string $html ): array {
		$images = array();
		if ( empty( $html ) ) {
			return $images;
		}

		// Match all <img> tags.
		if ( ! preg_match_all( '/<img[^>]+>/i', $html, $matches ) ) {
			return $images;
		}

		foreach ( $matches[0] as $tag ) {
			$src    = '';
			$alt    = '';
			$class  = '';
			$width  = null;
			$height = null;
			$id     = 0;

			// Extract src. Also check data-src for lazy-loaded themes (like LiteSpeed on shad-base.com).
			if ( preg_match( '/\bdata-src=["\']([^"\']+)["\']/i', $tag, $m ) && ! empty( $m[1] ) ) {
				$src = $m[1];
			} elseif ( preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $m ) && ! empty( $m[1] ) ) {
				// Avoid placeholder SVG data URLs if data-src is also present.
				if ( 0 !== strpos( $m[1], 'data:image/' ) || empty( $src ) ) {
					$src = $m[1];
				}
			}

			// Extract alt.
			if ( preg_match( '/\balt=["\']([^"\']*)["\']/i', $tag, $m ) ) {
				$alt = $m[1];
			}

			// Extract class.
			if ( preg_match( '/\bclass=["\']([^"\']+)["\']/i', $tag, $m ) ) {
				$class = $m[1];
			}

			// Extract width and height.
			if ( preg_match( '/\bwidth=["\']?(\d+)["\']?/i', $tag, $m ) ) {
				$width = (int) $m[1];
			}
			if ( preg_match( '/\bheight=["\']?(\d+)["\']?/i', $tag, $m ) ) {
				$height = (int) $m[1];
			}

			// Extract wp-image-{id} from class if present.
			if ( preg_match( '/\bwp-image-(\d+)\b/i', $class, $m ) ) {
				$id = (int) $m[1];
			}

			if ( ! empty( $src ) ) {
				$images[] = array(
					'src'           => esc_url_raw( $src ),
					'attachment_id' => $id,
					'width'         => $width,
					'height'        => $height,
					'alt'           => $alt,
					'class'         => $class,
					'raw'           => $tag,
				);
			}
		}

		return $images;
	}

	/**
	 * Append additional images to existing comic-html-below markup.
	 *
	 * @param string $existing_html Current comic-html-below content.
	 * @param array  $new_images    Array of image descriptors to append.
	 * @return string Merged HTML content.
	 */
	public static function append_to_html_below( string $existing_html, array $new_images ): string {
		$new_markup = self::build_html_below( $new_images );
		if ( empty( $new_markup ) ) {
			return $existing_html;
		}

		$trimmed = trim( $existing_html );
		if ( empty( $trimmed ) ) {
			return $new_markup;
		}

		return $trimmed . "\n" . $new_markup;
	}

	/**
	 * Sanitize comic-html-below markup, allowing img, a, picture, and container tags.
	 *
	 * @param string $html Raw HTML input.
	 * @return string Sanitized HTML.
	 */
	public static function sanitize_html_below( string $html ): string {
		if ( empty( $html ) ) {
			return '';
		}

		if ( function_exists( 'current_user_can' ) && current_user_can( 'unfiltered_html' ) ) {
			return $html;
		}

		$allowed = array(
			'img'     => array(
				'src'             => true,
				'data-src'        => true,
				'alt'             => true,
				'title'           => true,
				'width'           => true,
				'height'          => true,
				'class'           => true,
				'id'              => true,
				'loading'         => true,
				'decoding'        => true,
				'fetchpriority'   => true,
				'srcset'          => true,
				'sizes'           => true,
			),
			'a'       => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
				'class'  => true,
			),
			'p'       => array( 'class' => true ),
			'div'     => array( 'class' => true, 'id' => true ),
			'span'    => array( 'class' => true ),
			'picture' => array( 'class' => true ),
			'source'  => array(
				'srcset' => true,
				'sizes'  => true,
				'type'   => true,
				'media'  => true,
			),
		);

		return function_exists( 'wp_kses' ) ? wp_kses( $html, $allowed ) : $html;
	}
}
