<?php
/**
 * Shared template compiler for sandbox artifacts (widgets and blocks).
 *
 * Provides safe HTML compilation, attribute binding, and variable interpolation.
 *
 * @package EMCP_Tools
 * @since   3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Sandbox_Template_Compiler {

	/**
	 * Compile a template string with given variables.
	 *
	 * @param string $template Template string with {{var}} or {{#tag}} tokens.
	 * @param array  $vars     Variable dictionary.
	 * @return string
	 */
	public static function compile( string $template, array $vars ): string {
		return preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}/',
			static function ( $matches ) use ( $vars ) {
				$key = $matches[1];
				if ( isset( $vars[ $key ] ) ) {
					return esc_html( (string) $vars[ $key ] );
				}
				return '';
			},
			$template
		);
	}

	/**
	 * Generate PHP render body from a spec template.
	 *
	 * @param string $html Template HTML.
	 * @param array  $attributes Block/widget attributes.
	 * @return string PHP code.
	 */
	public static function build_php_render( string $html, array $attributes ): string {
		$code   = array();
		$code[] = '<?php';
		$code[] = '/** Auto-generated render template */';
		$code[] = 'if ( ! defined( "ABSPATH" ) ) { exit; }';
		$code[] = '$attrs = $attributes ?? array();';
		$code[] = '?>';

		// Replace tokens with PHP echo.
		$parsed = preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}/',
			static function ( $matches ) {
				$var = $matches[1];
				return '<?php echo esc_html( $attrs["' . esc_sql( $var ) . '"] ?? "" ); ?>';
			},
			$html
		);

		$code[] = $parsed;

		return implode( "\n", $code );
	}
}
