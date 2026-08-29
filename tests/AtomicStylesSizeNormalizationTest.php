<?php
/**
 * Regression coverage for issue #126: friendly atomic sizes must not be
 * silently cast to the wrong number or unit.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/class-atomic-props.php';
require_once dirname( __DIR__ ) . '/includes/class-atomic-styles.php';

class AtomicStylesSizeNormalizationTest extends \PHPUnit\Framework\TestCase {

	public function test_embedded_percentage_is_preserved(): void {
		$props = EMCP_Tools_Atomic_Styles::build_common_props( array( 'width' => '100%' ) );

		$this->assertSame( 100.0, $props['width']['value']['size'] );
		$this->assertSame( '%', $props['width']['value']['unit'] );
	}

	public function test_css_function_uses_custom_unit_without_changing_the_value(): void {
		$value = 'clamp(56px, 8vw, 120px)';
		$props = EMCP_Tools_Atomic_Styles::build_common_props( array( 'padding_top' => $value ) );
		$size  = $props['padding']['value']['block-start'];

		$this->assertSame( $value, $size['value']['size'] );
		$this->assertSame( 'custom', $size['value']['unit'] );
	}

	public function test_var_expression_is_preserved_for_gap(): void {
		$value = 'var(--layout-gap, 24px)';
		$props = EMCP_Tools_Atomic_Styles::build_flex_props( array( 'gap' => $value ) );

		$this->assertSame( $value, $props['gap']['value']['size'] );
		$this->assertSame( 'custom', $props['gap']['value']['unit'] );
	}

	public function test_numeric_inputs_remain_backward_compatible(): void {
		$props = EMCP_Tools_Atomic_Styles::build_common_props(
			array(
				'min_height'      => '50',
				'min_height_unit' => 'vh',
			)
		);

		$this->assertSame( 50.0, $props['min-height']['value']['size'] );
		$this->assertSame( 'vh', $props['min-height']['value']['unit'] );
	}

	public function test_conflicting_embedded_and_explicit_units_are_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Conflicting units for "width"' );

		EMCP_Tools_Atomic_Styles::build_common_props(
			array(
				'width'      => '100%',
				'width_unit' => 'px',
			)
		);
	}

	public function test_unsafe_custom_value_is_rejected_instead_of_cast_to_zero(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid size value for "width"' );

		EMCP_Tools_Atomic_Styles::build_common_props( array( 'width' => '10px; color:red' ) );
	}
}
