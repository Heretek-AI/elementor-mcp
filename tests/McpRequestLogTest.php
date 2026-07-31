<?php
/**
 * MCP request log (Bug report Issue 5).
 *
 * @package EMCP_Tools
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-mcp-request-log.php';

class McpRequestLogTest extends TestCase {

	protected function setUp(): void {
		EMCP_Tools_MCP_Request_Log::clear();
		EMCP_Tools_MCP_Request_Log::$debug_override = null;
	}

	protected function tearDown(): void {
		EMCP_Tools_MCP_Request_Log::$debug_override = null;
	}

	public function test_record_appends_and_reads_back(): void {
		EMCP_Tools_MCP_Request_Log::record( array( 'tool' => 'list-plugins', 'status' => '200', 'ms' => 42, 'req_id' => 'req_1' ) );
		$log = EMCP_Tools_MCP_Request_Log::all();
		$this->assertCount( 1, $log );
		$this->assertSame( 'list-plugins', $log[0]['tool'] );
		$this->assertSame( 42, $log[0]['ms'] );
	}

	public function test_cap_drops_oldest(): void {
		for ( $i = 0; $i < EMCP_Tools_MCP_Request_Log::MAX_COUNT + 5; $i++ ) {
			EMCP_Tools_MCP_Request_Log::record( array( 'tool' => 't' . $i, 'status' => 'ok' ) );
		}
		$log = EMCP_Tools_MCP_Request_Log::all();
		$this->assertCount( EMCP_Tools_MCP_Request_Log::MAX_COUNT, $log );
		$this->assertSame( 't5', $log[0]['tool'], 'oldest 5 dropped' );
	}

	public function test_error_kept_only_under_debug(): void {
		EMCP_Tools_MCP_Request_Log::$debug_override = false;
		EMCP_Tools_MCP_Request_Log::record( array( 'tool' => 'x', 'status' => 'error', 'error' => 'boom' ) );
		$this->assertArrayNotHasKey( 'error', EMCP_Tools_MCP_Request_Log::all()[0] );

		EMCP_Tools_MCP_Request_Log::clear();
		EMCP_Tools_MCP_Request_Log::$debug_override = true;
		EMCP_Tools_MCP_Request_Log::record( array( 'tool' => 'x', 'status' => 'error', 'error' => 'boom' ) );
		$this->assertSame( 'boom', EMCP_Tools_MCP_Request_Log::all()[0]['error'] );
	}
}
