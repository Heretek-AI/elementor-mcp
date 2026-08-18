<?php
/**
 * Elementor dynamic tags backed by the Themer dynamic sources.
 *
 * Elementor's dynamic-tag engine ships in FREE Elementor: Tag, Data_Tag and
 * Manager all live in the free plugin, and free registers no tags of its own.
 * Registering here therefore gives free Elementor users a working dynamic
 * picker on every dynamic-capable control, which is otherwise a Pro feature.
 *
 * The concrete tag class extends an Elementor base class, so it is defined in a
 * separate file required only inside the registration callback, where Elementor
 * is guaranteed to have loaded.
 *
 * @package EMCP_Tools
 * @since   3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.13.0
 */
class EMCP_Tools_Themer_Elementor_Tags {

	const GROUP = 'emcp-themer';

	/** Hook Elementor's registration point. No-op when Elementor is absent. */
	public static function init(): void {
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register' ) );
	}

	/**
	 * Elementor tag name for a source key.
	 *
	 * @param string $key Source key.
	 * @return string
	 */
	public static function tag_name( string $key ): string {
		return 'emcp-' . $key;
	}

	/**
	 * Elementor control categories a value type may fill.
	 *
	 * An empty array means the source is not offered anywhere, which is how
	 * markup sources are kept out of a heading's picker.
	 *
	 * @param string $type Value type.
	 * @return string[]
	 */
	public static function categories_for( string $type ): array {
		switch ( $type ) {
			case 'text':
			case 'date':
				return array( 'text', 'number' );
			case 'url':
				return array( 'url' );
			case 'image':
				return array( 'image' );
		}
		return array();
	}

	/**
	 * Source keys that become tags.
	 *
	 * @return string[]
	 */
	public static function taggable_keys(): array {
		$out = array();
		foreach ( EMCP_Tools_Themer_Dynamic_Catalog::bindable() as $key => $def ) {
			if ( self::categories_for( $def['type'] ) ) {
				$out[] = (string) $key;
			}
		}
		return $out;
	}

	/**
	 * Register the group and one tag per bindable source.
	 *
	 * @param object $manager Elementor dynamic tags manager.
	 */
	public static function register( $manager ): void {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}
		if ( method_exists( $manager, 'register_group' ) ) {
			$manager->register_group( self::GROUP, array( 'title' => __( 'EMCP Themer', 'emcp-tools' ) ) );
		}
		require_once __DIR__ . '/class-themer-elementor-tag-classes.php';
		if ( ! class_exists( 'EMCP_Tools_Themer_Elementor_Tag' ) ) {
			return;
		}
		foreach ( self::taggable_keys() as $key ) {
			$manager->register( new EMCP_Tools_Themer_Elementor_Tag( $key ) );
		}
	}
}
