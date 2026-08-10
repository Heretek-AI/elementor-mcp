<?php
/**
 * Public unit tests for EMCP_Tools_Redirect_Abilities — the DB-free surface:
 * ability registration (names + category) and the suggestion queue. CRUD
 * execution + find-broken-links classification are covered by dedicated tests
 * and the live smoke.
 *
 * @package EMCP_Tools
 */

class RedirectAbilitiesTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		emcp_test_reset();
	}

	public function test_register_declares_all_slugs_with_category() {
		$g = new EMCP_Tools_Redirect_Abilities();
		$g->register();
		$names = $g->get_ability_names();
		$this->assertContains( 'emcp-tools/list-redirects', $names );
		$this->assertContains( 'emcp-tools/create-redirect', $names );
		$this->assertContains( 'emcp-tools/update-redirect', $names );
		$this->assertContains( 'emcp-tools/delete-redirect', $names );
		// Every registered ability MUST carry category => emcp-tools.
		foreach ( $names as $n ) {
			$this->assertSame( 'emcp-tools', $GLOBALS['emcp_test']['abilities'][ $n ]['category'] ?? null, "$n missing category" );
		}
	}

	public function test_delete_redirect_is_badged_destructive() {
		$g = new EMCP_Tools_Redirect_Abilities();
		$g->register();
		$meta = $GLOBALS['emcp_test']['abilities']['emcp-tools/delete-redirect']['meta']['annotations'] ?? array();
		$this->assertTrue( $meta['destructive'] ?? false );
	}

	public function test_push_suggestion_appends_and_dedupes() {
		EMCP_Tools_Redirect_Abilities::push_suggestion( '/old-a', 'post-deleted', 5 );
		EMCP_Tools_Redirect_Abilities::push_suggestion( '/old-b', 'slug-changed', 6 );
		$q = get_option( 'emcp_tools_redirect_suggestions', array() );
		$this->assertCount( 2, $q );
		// Re-pushing /old-a de-dupes (newest wins) → still 2, /old-a last.
		EMCP_Tools_Redirect_Abilities::push_suggestion( '/old-a', 'post-deleted', 5 );
		$q = get_option( 'emcp_tools_redirect_suggestions', array() );
		$this->assertCount( 2, $q );
		$this->assertSame( '/old-a', $q[ count( $q ) - 1 ]['old_path'] );
	}

	public function test_push_suggestion_ignores_root_and_empty() {
		EMCP_Tools_Redirect_Abilities::push_suggestion( '/', 'post-deleted', 1 );
		EMCP_Tools_Redirect_Abilities::push_suggestion( '', 'post-deleted', 2 );
		$this->assertSame( array(), get_option( 'emcp_tools_redirect_suggestions', array() ) );
	}
}
