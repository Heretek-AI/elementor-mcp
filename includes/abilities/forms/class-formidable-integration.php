<?php
/**
 * Formidable Forms integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Formidable_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string { return 'formidable'; }
	public function label(): string { return 'Formidable Forms'; }
	public function is_active(): bool { return class_exists( 'FrmForm' ) || class_exists( 'FrmAppHelper' ); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };
		return array(
			'list-forms'   => array( 'mode' => 'read', 'run' => array( $this, 'op_list_forms' ), 'perm' => $can_manage, 'desc' => 'List Formidable Forms.' ),
			'get-form'     => array( 'mode' => 'read', 'run' => array( $this, 'op_get_form' ), 'perm' => $can_manage, 'desc' => 'Get one form by { form_id }.' ),
			'list-entries' => array( 'mode' => 'read', 'run' => array( $this, 'op_list_entries' ), 'perm' => $can_manage, 'desc' => 'List entries by { form_id }.' ),
			'get-entry'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_entry' ), 'perm' => $can_manage, 'desc' => 'Get one entry by { entry_id }.' ),
			'delete-entry' => array( 'mode' => 'write', 'run' => array( $this, 'op_delete_entry' ), 'perm' => $can_manage, 'confirm' => true, 'desc' => 'Delete entry by { entry_id, confirm: true }.' ),
			'update-form'  => array( 'mode' => 'write', 'run' => array( $this, 'op_update_form' ), 'perm' => $can_manage, 'desc' => 'Update form by { form_id, name? }.' ),
		);
	}

	public function op_list_forms( array $args = array() ) {
		if ( ! class_exists( 'FrmForm' ) ) { return new WP_Error( 'inactive', 'Formidable not active.' ); }
		$forms = FrmForm::getAll();
		return array( 'forms' => (array) $forms, 'total' => count( (array) $forms ) );
	}

	public function op_get_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'FrmForm' ) ) { return new WP_Error( 'invalid', 'Invalid form ID.' ); }
		$f = FrmForm::getOne( $id );
		return $f ? (array) $f : new WP_Error( 'not_found', 'Form not found.' );
	}

	public function op_list_entries( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'FrmEntry' ) ) { return array( 'entries' => array(), 'total' => 0 ); }
		$entries = FrmEntry::getAll( array( 'form_id' => $id ) );
		return array( 'entries' => (array) $entries, 'total' => count( (array) $entries ) );
	}

	public function op_get_entry( array $args ) {
		$id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'FrmEntry' ) ) { return new WP_Error( 'invalid', 'Invalid entry ID.' ); }
		$e = FrmEntry::getOne( $id );
		return $e ? (array) $e : new WP_Error( 'not_found', 'Entry not found.' );
	}

	public function op_delete_entry( array $args ) {
		$id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'FrmEntry' ) ) { return new WP_Error( 'invalid', 'Invalid entry ID.' ); }
		return array( 'success' => (bool) FrmEntry::destroy( $id ) );
	}

	public function op_update_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'FrmForm' ) ) { return new WP_Error( 'invalid', 'Invalid form ID.' ); }
		$data = array();
		if ( isset( $args['name'] ) ) { $data['name'] = sanitize_text_field( $args['name'] ); }
		if ( ! empty( $data ) ) { FrmForm::update( $id, $data ); }
		return array( 'success' => true );
	}
}
