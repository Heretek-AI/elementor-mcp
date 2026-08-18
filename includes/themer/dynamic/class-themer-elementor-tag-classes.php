<?php
/**
 * The concrete Elementor tag: one class parameterised by source key, rather
 * than a dozen near-identical subclasses.
 *
 * Required only from inside elementor/dynamic_tags/register, where the
 * Elementor base class is guaranteed to exist.
 *
 * @package EMCP_Tools
 * @since   3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EMCP_Tools_Themer_Elementor_Tag' ) && class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {

	/**
	 * @since 3.13.0
	 */
	class EMCP_Tools_Themer_Elementor_Tag extends \Elementor\Core\DynamicTags\Tag {

		/**
		 * Source key this instance renders.
		 *
		 * @var string
		 */
		private $source_key;

		/**
		 * @param string $key  Source key.
		 * @param array  $data Elementor element data.
		 */
		public function __construct( string $key = 'post-title', array $data = array() ) {
			$this->source_key = $key;
			parent::__construct( $data );
		}

		/**
		 * @return string
		 */
		public function get_name() {
			return EMCP_Tools_Themer_Elementor_Tags::tag_name( $this->source_key );
		}

		/**
		 * @return string
		 */
		public function get_title() {
			$def = EMCP_Tools_Themer_Dynamic_Catalog::get( $this->source_key );
			return $def ? $def['label'] : $this->source_key;
		}

		/**
		 * @return string
		 */
		public function get_group() {
			return EMCP_Tools_Themer_Elementor_Tags::GROUP;
		}

		/**
		 * @return string[]
		 */
		public function get_categories() {
			$def = EMCP_Tools_Themer_Dynamic_Catalog::get( $this->source_key );
			return EMCP_Tools_Themer_Elementor_Tags::categories_for( $def ? $def['type'] : 'html' );
		}

		/** Echo the resolved value. */
		public function render() {
			$settings = method_exists( $this, 'get_settings' ) ? (array) $this->get_settings() : array();
			$v        = EMCP_Tools_Themer_Dynamic::value( $this->source_key, $settings );

			if ( 'image' === $v['type'] ) {
				// An image tag fills a URL control, so the URL is what it emits.
				echo esc_url( (string) $v['value']['url'] );
				return;
			}
			if ( 'url' === $v['type'] ) {
				echo esc_url( (string) $v['value'] );
				return;
			}
			// Text and date are plain values; Elementor escapes per control, and
			// this tag is never registered for a markup source.
			echo esc_html( (string) $v['value'] );
		}
	}
}
