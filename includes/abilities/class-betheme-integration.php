<?php
/**
 * BeTheme & BeBuilder integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_BeTheme_Integration extends EMCP_Tools_Theme_Integration {

	public function id(): string { return 'betheme'; }
	public function label(): string { return 'BeTheme'; }
	public function is_available(): bool { return 'betheme' === get_template(); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };
		return array(
			'get-theme-options'     => array( 'mode' => 'read', 'run' => array( $this, 'op_get_options' ), 'perm' => $can_manage, 'desc' => 'Read BeTheme theme options.' ),
			'update-theme-options'  => array( 'mode' => 'write', 'run' => array( $this, 'op_update_options' ), 'perm' => $can_manage, 'desc' => 'Update BeTheme theme options { options: { key: value } }.' ),
			'get-bebuilder-content' => array( 'mode' => 'read', 'run' => array( $this, 'op_get_bebuilder' ), 'perm' => $can_manage, 'desc' => 'Get BeBuilder page items by { post_id }.' ),
			'update-bebuilder-content'=> array( 'mode' => 'write', 'run' => array( $this, 'op_update_bebuilder' ), 'perm' => $can_manage, 'desc' => 'Update BeBuilder page items by { post_id, items }.' ),
		);
	}

	public function op_get_options( array $args = array() ) {
		$opts = get_option( 'mfn_theme_options', array() );
		return array( 'options' => (array) $opts );
	}

	public function op_update_options( array $args ) {
		$vals = (array) ( $args['options'] ?? array() );
		$cur  = (array) get_option( 'mfn_theme_options', array() );
		update_option( 'mfn_theme_options', array_merge( $cur, $vals ) );
		return array( 'success' => true );
	}

	public function op_get_bebuilder( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		$items = get_post_meta( $id, 'mfn-page-items', true );
		return array( 'post_id' => $id, 'items' => $items );
	}

	public function op_update_bebuilder( array $args ) {
		$id = (int) ( $args['post_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'missing_id', 'post_id required' ); }
		if ( isset( $args['items'] ) ) {
			update_post_meta( $id, 'mfn-page-items', $args['items'] );
		}
		return array( 'success' => true, 'post_id' => $id );
	}
}
