<?php
/**
 * Blocksy Extensions integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Blocksy_Extensions_Integration extends EMCP_Tools_Theme_Integration {

	public function id(): string { return 'blocksy-extensions'; }
	public function label(): string { return 'Blocksy Companion'; }
	public function is_available(): bool { return 'blocksy' === get_template() || defined( 'BLOCKSY_VERSION' ); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };
		return array(
			'list-extensions'   => array( 'mode' => 'read', 'run' => array( $this, 'op_list_extensions' ), 'perm' => $can_manage, 'desc' => 'List active Blocksy extensions.' ),
			'toggle-extension'  => array( 'mode' => 'write', 'run' => array( $this, 'op_toggle_extension' ), 'perm' => $can_manage, 'desc' => 'Toggle Blocksy extension by { name, active }.' ),
		);
	}

	public function op_list_extensions( array $args = array() ) {
		$exts = get_option( 'blocksy_active_extensions', array() );
		return array( 'extensions' => (array) $exts );
	}

	public function op_toggle_extension( array $args ) {
		$name   = sanitize_key( $args['name'] ?? '' );
		$active = (bool) ( $args['active'] ?? true );
		$exts   = (array) get_option( 'blocksy_active_extensions', array() );
		if ( $active ) {
			if ( ! in_array( $name, $exts, true ) ) { $exts[] = $name; }
		} else {
			$exts = array_values( array_diff( $exts, array( $name ) ) );
		}
		update_option( 'blocksy_active_extensions', $exts );
		return array( 'success' => true );
	}
}
