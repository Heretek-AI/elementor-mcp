<?php
/**
 * Fluent Forms integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_FluentForms_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string { return 'fluentforms'; }
	public function label(): string { return 'Fluent Forms'; }
	public function is_active(): bool { return defined( 'FLUENTFORM' ) || function_exists( 'wpFluent' ); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };

		return array(
			'list-forms'   => array( 'mode' => 'read', 'run' => array( $this, 'op_list_forms' ), 'perm' => $can_manage, 'desc' => 'List Fluent Forms.' ),
			'get-form'     => array( 'mode' => 'read', 'run' => array( $this, 'op_get_form' ), 'perm' => $can_manage, 'desc' => 'Get one form by { form_id }.' ),
			'list-entries' => array( 'mode' => 'read', 'run' => array( $this, 'op_list_entries' ), 'perm' => $can_manage, 'desc' => 'List entries by { form_id }.' ),
			'get-entry'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_entry' ), 'perm' => $can_manage, 'desc' => 'Get entry by { entry_id }.' ),
			'delete-entry' => array( 'mode' => 'write', 'run' => array( $this, 'op_delete_entry' ), 'perm' => $can_manage, 'confirm' => true, 'desc' => 'Delete entry by { entry_id, confirm: true }.' ),
			'update-form'  => array( 'mode' => 'write', 'run' => array( $this, 'op_update_form' ), 'perm' => $can_manage, 'desc' => 'Update form by { form_id, title?, status? }.' ),
		);
	}

	public function op_list_forms( array $args = array() ) {
		if ( ! function_exists( 'wpFluent' ) ) { return new WP_Error( 'inactive', 'Fluent Forms not active.' ); }
		$forms = wpFluent()->table( 'fluentform_forms' )->select( array( 'id', 'title', 'status', 'created_at' ) )->get();
		return array( 'forms' => (array) $forms, 'total' => count( (array) $forms ) );
	}

	public function op_get_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! function_exists( 'wpFluent' ) ) { return new WP_Error( 'invalid', 'Invalid form ID.' ); }
		$form = wpFluent()->table( 'fluentform_forms' )->find( $id );
		return $form ? (array) $form : new WP_Error( 'not_found', 'Form not found.' );
	}

	public function op_list_entries( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! function_exists( 'wpFluent' ) ) { return array( 'entries' => array(), 'total' => 0 ); }
		$entries = wpFluent()->table( 'fluentform_submissions' )->where( 'form_id', $id )->get();
		return array( 'entries' => (array) $entries, 'total' => count( (array) $entries ) );
	}

	public function op_get_entry( array $args ) {
		$id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $id || ! function_exists( 'wpFluent' ) ) { return new WP_Error( 'invalid', 'Invalid entry ID.' ); }
		$entry = wpFluent()->table( 'fluentform_submissions' )->find( $id );
		return $entry ? (array) $entry : new WP_Error( 'not_found', 'Entry not found.' );
	}

	public function op_delete_entry( array $args ) {
		$id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $id || ! function_exists( 'wpFluent' ) ) { return new WP_Error( 'invalid', 'Invalid entry ID.' ); }
		wpFluent()->table( 'fluentform_submissions' )->where( 'id', $id )->delete();
		return array( 'success' => true );
	}

	public function op_update_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! function_exists( 'wpFluent' ) ) { return new WP_Error( 'invalid', 'Invalid form ID.' ); }
		$data = array();
		if ( isset( $args['title'] ) ) { $data['title'] = sanitize_text_field( $args['title'] ); }
		if ( isset( $args['status'] ) ) { $data['status'] = sanitize_text_field( $args['status'] ); }
		if ( ! empty( $data ) ) {
			wpFluent()->table( 'fluentform_forms' )->where( 'id', $id )->update( $data );
		}
		return array( 'success' => true );
	}
}
