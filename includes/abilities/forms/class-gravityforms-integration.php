<?php
/**
 * Gravity Forms integration (Pro) — gf-read / gf-write over GFAPI.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_GravityForms_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string { return 'gravityforms'; }
	public function label(): string { return 'Gravity Forms'; }
	public function is_active(): bool { return class_exists( 'GFAPI' ) || class_exists( 'GFForms' ); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'gform_full_access' ) || current_user_can( 'manage_options' ); };

		return array(
			'list-forms'   => array( 'mode' => 'read', 'run' => array( $this, 'op_list_forms' ), 'perm' => $can_manage, 'desc' => 'List Gravity Forms (ID, title, active status).' ),
			'get-form'     => array( 'mode' => 'read', 'run' => array( $this, 'op_get_form' ), 'perm' => $can_manage, 'desc' => 'Get one form by { form_id }.' ),
			'list-entries' => array( 'mode' => 'read', 'run' => array( $this, 'op_list_entries' ), 'perm' => $can_manage, 'desc' => 'List entries by { form_id, page_size? }.' ),
			'get-entry'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_entry' ), 'perm' => $can_manage, 'desc' => 'Get one entry by { entry_id }.' ),
			'delete-entry' => array( 'mode' => 'write', 'run' => array( $this, 'op_delete_entry' ), 'perm' => $can_manage, 'confirm' => true, 'desc' => 'Delete an entry by { entry_id, confirm: true }.' ),
			'update-form'  => array( 'mode' => 'write', 'run' => array( $this, 'op_update_form' ), 'perm' => $can_manage, 'desc' => 'Update form by { form_id, form: object }.' ),
		);
	}

	public function op_list_forms( array $args = array() ) {
		if ( ! class_exists( 'GFAPI' ) ) { return new WP_Error( 'inactive', 'Gravity Forms GFAPI not available.' ); }
		$forms = GFAPI::get_forms();
		return array( 'forms' => $forms, 'total' => count( $forms ) );
	}

	public function op_get_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'GFAPI' ) ) { return new WP_Error( 'invalid', 'Invalid form ID or GFAPI missing.' ); }
		$f = GFAPI::get_form( $id );
		return $f ? $f : new WP_Error( 'not_found', 'Form not found.' );
	}

	public function op_list_entries( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'GFAPI' ) ) { return array( 'entries' => array(), 'total' => 0 ); }
		$entries = GFAPI::get_entries( $id );
		return array( 'entries' => is_array( $entries ) ? $entries : array(), 'total' => count( (array) $entries ) );
	}

	public function op_get_entry( array $args ) {
		$id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'GFAPI' ) ) { return new WP_Error( 'invalid', 'Invalid entry ID.' ); }
		$e = GFAPI::get_entry( $id );
		return is_wp_error( $e ) ? $e : $e;
	}

	public function op_delete_entry( array $args ) {
		$id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'GFAPI' ) ) { return new WP_Error( 'invalid', 'Invalid entry ID.' ); }
		$res = GFAPI::delete_entry( $id );
		return array( 'success' => ! is_wp_error( $res ) );
	}

	public function op_update_form( array $args ) {
		$form = (array) ( $args['form'] ?? array() );
		$id   = (int) ( $args['form_id'] ?? ( $form['id'] ?? 0 ) );
		if ( ! $id || ! class_exists( 'GFAPI' ) ) { return new WP_Error( 'invalid', 'Form ID required.' ); }
		$res = GFAPI::update_form( $form, $id );
		return is_wp_error( $res ) ? $res : array( 'success' => true );
	}
}
