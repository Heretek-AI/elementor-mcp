<?php
/**
 * Public unit tests for EMCP_Tools_Redirect_Module — identity, default-on, and
 * the static is_enabled() gate the ability registrar / admin / content wiring
 * key off.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/modules/class-module.php';
require_once dirname( __DIR__ ) . '/includes/modules/class-redirect-module.php';

class RedirectModuleTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		emcp_test_reset();
	}

	public function test_identity_and_default_on() {
		$m = new EMCP_Tools_Redirect_Module();
		$this->assertSame( 'redirects', $m->id() );
		$this->assertSame( 'free', $m->tier() );
		$this->assertTrue( $m->default_active() );
	}

	public function test_is_enabled_reads_active_modules_option() {
		$GLOBALS['emcp_test']['options']['emcp_tools_active_modules'] = array();
		$this->assertFalse( EMCP_Tools_Redirect_Module::is_enabled() );

		$GLOBALS['emcp_test']['options']['emcp_tools_active_modules'] = array( 'themer', 'redirects' );
		$this->assertTrue( EMCP_Tools_Redirect_Module::is_enabled() );
	}

	public function test_is_active_matches_is_enabled() {
		$m = new EMCP_Tools_Redirect_Module();
		$GLOBALS['emcp_test']['options']['emcp_tools_active_modules'] = array( 'redirects' );
		$this->assertTrue( $m->is_active() );
		$this->assertSame( $m->is_active(), EMCP_Tools_Redirect_Module::is_enabled() );
	}
}
