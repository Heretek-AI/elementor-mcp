<?php
/**
 * Ninja Forms integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_NinjaForms_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string { return 'ninjaforms'; }
	public function label(): string { return 'Ninja Forms'; }
	public function is_active(): bool { return class_exists( 'Ninja_Forms' ) || function_exists( 'Ninja_Forms' ); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };
		return array(
			'list-forms'   => array( 'mode' => 'read', 'run' => array( $this, 'op_list_forms' ), 'perm' => $can_manage, 'desc' => 'List Ninja Forms.' ),
			'get-form'     => array( 'mode' => 'read', 'run' => array( $this, 'op_get_form' ), 'perm' => $can_manage, 'desc' => 'Get one form by { form_id }.' ),
			'list-entries' => array( 'mode' => 'read', 'run' => array( $this, 'op_list_entries' ), 'perm' => $can_manage, 'desc' => 'List submissions by { form_id }.' ),
			'get-entry'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_entry' ), 'perm' => $can_manage, 'desc' => 'Get one submission by { entry_id }.' ),
			'delete-entry' => array( 'mode' => 'write', 'run' => array( $this, 'op_delete_entry' ), 'perm' => $can_manage, 'confirm' => true, 'desc' => 'Delete submission by { entry_id, confirm: true }.' ),
			'update-form'  => array( 'mode' => 'write', 'run' => array( $this, 'op_update_form' ), 'perm' => $can_manage, 'desc' => 'Update form by { form_id, title? }.' ),
		);
	}

	public function op_list_forms( array $args = array() ) {
		if ( ! function_exists( 'Ninja_Forms' ) ) { return new WP_Error( 'inactive', 'Ninja Forms not active.' ); }
		$forms = Ninja_Forms()->form()->get_forms();
		$out   = array();
		foreach ( (array) $forms as $f ) {
			$out[] = array( 'id' => is_object( $f ) ? $f->get_id() : 0, 'title' => is_object( $f ) ? $f->get_setting( 'title' ) : '' );
		}
		return array( 'forms' => $out, 'total' => count( $out ) );
	}

	public function op_get_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! function_exists( 'Ninja_Forms' ) ) { return new WP_Error( 'invalid', 'Invalid form ID.' ); }
		$f = Ninja_Forms()->form( $id )->get();
		return $f ? array( 'id' => $id, 'title' => $f->get_setting( 'title' ) ) : new WP_Error( 'not_found', 'Form not found.' );
	}

	public function op_list_entries( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! function_exists( 'Ninja_Forms' ) ) { return array( 'entries' => array(), 'total' => 0 ); }
		$subs = Ninja_Forms()->form( $id )->get_subs();
		return array( 'entries' => (array) $subs, 'total' => count( (array) $subs ) );
	}

	public function op_get_entry( array $args ) {
		$id = (int) ( $args['entry_id'] ?? 0 );
		$post = get_post( $id );
		return $post ? (array) $post : new WP_Error( 'not_found', 'Entry not found.' );
	}

	public function op_delete_entry( array $args ) {
		$id = (int) ( $args['entry_id'] ?? 0 );
		return array( 'success' => (bool) wp_delete_post( $id, true ) );
	}

	public function op_update_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! function_exists( 'Ninja_Forms' ) ) { return new WP_Error( 'invalid', 'Invalid form ID.' ); }
		if ( isset( $args['title'] ) ) {
			Ninja_Forms()->form( $id )->update_setting( 'title', sanitize_text_field( $args['title'] ) )->save();
		}
		return array( 'success' => true );
	}
}
