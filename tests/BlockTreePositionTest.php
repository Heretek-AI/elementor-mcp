<?php
/**
 * EMCP_Tools_Block_Tree::position_is_valid() — the guard against silently
 * dropping a block when an index path does not resolve.
 *
 * insert() no-ops on an unresolvable path and returns the tree unchanged, so a
 * caller that saved the result reported success while losing the block. Every
 * block-pack add-block now validates the position first. Caught live while
 * building a full Kadence page: three nested infoboxes vanished and the tool
 * still said "ok".
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/class-block-tree.php';

class BlockTreePositionTest extends \PHPUnit\Framework\TestCase {

	/** A two-level tree: row (with 2 columns) + a heading. */
	private function tree(): array {
		return array(
			array(
				'blockName'   => 'kadence/rowlayout',
				'attrs'       => array(),
				'innerBlocks' => array(
					array( 'blockName' => 'kadence/column', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ),
					array( 'blockName' => 'kadence/column', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ),
				),
				'innerHTML'   => '',
				'innerContent' => array( null, null ),
			),
			array( 'blockName' => 'kadence/advancedheading', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ),
		);
	}

	public function test_append_and_prepend_need_no_path() {
		$t = $this->tree();
		$this->assertTrue( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => 'append' ) ) );
		$this->assertTrue( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => 'prepend' ) ) );
		// Default mode (no mode key) is append.
		$this->assertTrue( EMCP_Tools_Block_Tree::position_is_valid( $t, array() ) );
	}

	public function test_valid_top_level_and_nested_paths() {
		$t = $this->tree();
		$this->assertTrue( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => 'inside', 'path' => array( 0 ) ) ) );
		$this->assertTrue( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => 'inside', 'path' => array( 0, 1 ) ) ) );
		$this->assertTrue( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => 'after', 'path' => array( 1 ) ) ) );
	}

	public function test_out_of_range_paths_are_invalid() {
		$t = $this->tree();
		// The exact live failure: a raw parse_blocks index (separators counted)
		// pointing past the real tree.
		$this->assertFalse( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => 'inside', 'path' => array( 8, 0 ) ) ) );
		$this->assertFalse( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => 'inside', 'path' => array( 99, 5 ) ) ) );
		// Second level out of range.
		$this->assertFalse( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => 'inside', 'path' => array( 0, 9 ) ) ) );
		// A leaf has no children to descend into.
		$this->assertFalse( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => 'inside', 'path' => array( 1, 0 ) ) ) );
	}

	public function test_inside_before_after_require_a_path() {
		$t = $this->tree();
		foreach ( array( 'inside', 'before', 'after' ) as $mode ) {
			$this->assertFalse( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => $mode ) ) );
			$this->assertFalse( EMCP_Tools_Block_Tree::position_is_valid( $t, array( 'mode' => $mode, 'path' => array() ) ) );
		}
	}

	/** The behaviour the guard exists to prevent: insert() silently no-ops. */
	public function test_insert_is_a_silent_noop_on_a_bad_path() {
		$t   = $this->tree();
		$new = array( array( 'blockName' => 'kadence/infobox', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
		$out = EMCP_Tools_Block_Tree::insert( $t, $new, array( 'mode' => 'inside', 'path' => array( 99, 5 ) ) );
		$this->assertEquals( $t, $out, 'insert() returns the tree unchanged — hence the guard' );
	}
}
