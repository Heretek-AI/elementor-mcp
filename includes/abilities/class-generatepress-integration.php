<?php
/**
 * GeneratePress theme integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_GeneratePress_Integration extends EMCP_Tools_Theme_Integration {

	const OPTION = 'generate_settings';

	public function id(): string { return 'generatepress'; }
	public function label(): string { return 'GeneratePress'; }
	public function is_available(): bool { return 'generatepress' === get_template(); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };
		return array(
			'get-settings'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_settings' ),
				'perm' => $can_manage,
				'desc' => 'Read GeneratePress theme settings.',
			),
			'update-settings' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_settings' ),
				'perm' => $can_manage,
				'desc' => 'Update GeneratePress theme settings { values: { key: value } }.',
			),
		);
	}

	public function op_get_settings( array $args = array() ) {
		$settings = get_option( self::OPTION, array() );
		return array( 'settings' => (array) $settings );
	}

	public function op_update_settings( array $args ) {
		$values   = (array) ( $args['values'] ?? array() );
		$existing = (array) get_option( self::OPTION, array() );
		$merged   = array_merge( $existing, $values );
		update_option( self::OPTION, $merged );
		return array( 'success' => true );
	}
}
