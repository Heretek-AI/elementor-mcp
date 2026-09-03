<?php
/**
 * Tests EMCP_Tools_Schema_Compat::sanitize().
 *
 * Targeted at the union-type flatten added to fix `set-post-terms` shorting
 * (the WordPress Abilities API input validator rejects `items.type` arrays).
 * The class file is pure-array transforms and needs no WordPress.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/class-schema-compat.php';

class SanitizeSchemaUnionItemsTypeTest extends \PHPUnit\Framework\TestCase {

	public function test_flatten_items_type_integer_string_to_string(): void {
		$schema = array(
			'type'  => 'array',
			'items' => array( 'type' => array( 'integer', 'string' ) ),
		);
		$out    = EMCP_Tools_Schema_Compat::sanitize( $schema );
		$this->assertSame( 'string', $out['items']['type'] );
	}

	public function test_flatten_top_level_union_type(): void {
		$schema = array(
			'type' => array( 'integer', 'string' ),
		);
		$out    = EMCP_Tools_Schema_Compat::sanitize( $schema );
		$this->assertSame( 'string', $out['type'] );
	}

	public function test_flatten_skips_null_picks_first_non_null(): void {
		$schema = array(
			'type' => array( 'null', 'integer', 'string' ),
		);
		$out    = EMCP_Tools_Schema_Compat::sanitize( $schema );
		$this->assertSame( 'integer', $out['type'] );
	}

	public function test_flatten_all_null_falls_back_to_string(): void {
		$schema = array(
			'type' => array( 'null' ),
		);
		$out    = EMCP_Tools_Schema_Compat::sanitize( $schema );
		$this->assertSame( 'string', $out['type'] );
	}

	public function test_passthrough_string_type_unchanged(): void {
		$schema = array( 'type' => 'integer' );
		$out    = EMCP_Tools_Schema_Compat::sanitize( $schema );
		$this->assertSame( 'integer', $out['type'] );
	}

	public function test_flatten_via_recursion_through_properties(): void {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'terms' => array(
					'type'  => 'array',
					'items' => array( 'type' => array( 'integer', 'string' ) ),
				),
			),
		);
		$out    = EMCP_Tools_Schema_Compat::sanitize( $schema );
		$this->assertSame( 'string', $out['properties']['terms']['items']['type'] );
	}

	public function test_flatten_via_recursion_through_items(): void {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array( 'type' => array( 'integer', 'string' ) ),
				),
			),
		);
		$out    = EMCP_Tools_Schema_Compat::sanitize( $schema );
		$this->assertSame( 'string', $out['items']['properties']['id']['type'] );
	}
}
