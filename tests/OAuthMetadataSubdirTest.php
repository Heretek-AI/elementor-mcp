<?php
/**
 * Public unit test for EMCP_Tools_OAuth_Metadata::strip_home_path() /
 * path_matches() — the fix for OAuth discovery on subdirectory installs.
 *
 * A subdirectory install (WordPress at /gpt-build/) receives the RFC 9728
 * protected-resource request as `/gpt-build/.well-known/oauth-protected-resource`,
 * but the well-known paths are site-root-relative. Without stripping the home
 * path, the advertised metadata URL 404s and OAuth discovery (ChatGPT, Claude,
 * any RFC 9728 client) dead-ends. This pins the path handling.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/oauth/class-oauth-metadata.php';

class OAuthMetadataSubdirTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		emcp_test_reset();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['emcp_test']['home_url'] );
	}

	/** Root install (home path empty): paths pass through unchanged. */
	public function test_root_install_is_a_noop() {
		$GLOBALS['emcp_test']['home_url'] = 'https://example.test';
		$this->assertSame(
			'/.well-known/oauth-protected-resource',
			EMCP_Tools_OAuth_Metadata::strip_home_path( '/.well-known/oauth-protected-resource' )
		);
	}

	/** Subdirectory install: the home-path prefix is stripped so the match works. */
	public function test_subdirectory_prefix_is_stripped() {
		$GLOBALS['emcp_test']['home_url'] = 'https://demo.emcptools.com/gpt-build';
		$this->assertSame(
			'/.well-known/oauth-protected-resource',
			EMCP_Tools_OAuth_Metadata::strip_home_path( '/gpt-build/.well-known/oauth-protected-resource' )
		);
	}

	/** The RFC 9728 resource-scoped variant is stripped + still matches. */
	public function test_subdirectory_resource_scoped_variant() {
		$GLOBALS['emcp_test']['home_url'] = 'https://demo.emcptools.com/gpt-build';
		$stripped = EMCP_Tools_OAuth_Metadata::strip_home_path(
			'/gpt-build/.well-known/oauth-protected-resource/gpt-build/wp-json/mcp/emcp-tools-server'
		);
		$this->assertSame( '/.well-known/oauth-protected-resource/gpt-build/wp-json/mcp/emcp-tools-server', $stripped );
		$this->assertTrue(
			EMCP_Tools_OAuth_Metadata::path_matches( $stripped, EMCP_Tools_OAuth_Metadata::PATH_PROTECTED_RESOURCE )
		);
	}

	/** A non-well-known path under the subdir is left alone (won't match). */
	public function test_unrelated_subdir_path_unchanged() {
		$GLOBALS['emcp_test']['home_url'] = 'https://demo.emcptools.com/gpt-build';
		$this->assertSame(
			'/gpt-build-other/page',
			EMCP_Tools_OAuth_Metadata::strip_home_path( '/gpt-build-other/page' )
		);
	}

	/** path_matches: exact + resource-scoped true, sibling false. */
	public function test_path_matches_semantics() {
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::path_matches( '/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource' ) );
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::path_matches( '/.well-known/oauth-protected-resource/wp-json/mcp/x', '/.well-known/oauth-protected-resource' ) );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::path_matches( '/.well-known/oauth-protected-resource-other', '/.well-known/oauth-protected-resource' ) );
	}
}
