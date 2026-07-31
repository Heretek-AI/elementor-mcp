<?php
/**
 * Host-mismatch guard (Bug report Issue 2).
 *
 * @package EMCP_Tools
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-mcp-host-guard.php';

class McpHostGuardTest extends TestCase {

	public function test_exact_match(): void {
		$this->assertTrue(
			EMCP_Tools_MCP_Host_Guard::host_matches( 'devis.debord-toiture.com', 'devis.debord-toiture.com' )
		);
	}

	public function test_www_and_case_and_port_tolerant(): void {
		$this->assertTrue(
			EMCP_Tools_MCP_Host_Guard::host_matches( 'www.Example.com:443', 'example.com' )
		);
	}

	public function test_different_domain_is_mismatch(): void {
		$this->assertFalse(
			EMCP_Tools_MCP_Host_Guard::host_matches( 'paleturquoise-sardine-346722.hostingersite.com', 'devis.debord-toiture.com' )
		);
	}

	public function test_empty_request_host_is_not_a_mismatch(): void {
		// A missing Host header must never brick the endpoint.
		$this->assertTrue( EMCP_Tools_MCP_Host_Guard::host_matches( '', 'example.com' ) );
	}
}
