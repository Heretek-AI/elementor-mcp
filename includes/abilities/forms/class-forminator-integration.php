<?php
/**
 * Forminator integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Forminator_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string { return 'forminator'; }
	public function label(): string { return 'Forminator'; }
	public function is_active(): bool { return class_exists( 'Forminator_API' ) || class_exists( 'Forminator' ); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };
		return array(
			'list-forms'   => array( 'mode' => 'read', 'run' => array( $this, 'op_list_forms' ), 'perm' => $can_manage, 'desc' => 'List Forminator forms.' ),
			'get-form'     => array( 'mode' => 'read', 'run' => array( $this, 'op_get_form' ), 'perm' => $can_manage, 'desc' => 'Get one Forminator form by { form_id }.' ),
			'list-entries' => array( 'mode' => 'read', 'run' => array( $this, 'op_list_entries' ), 'perm' => $can_manage, 'desc' => 'List entries by { form_id }.' ),
			'get-entry'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_entry' ), 'perm' => $can_manage, 'desc' => 'Get one entry by { form_id, entry_id }.' ),
			'delete-entry' => array( 'mode' => 'write', 'run' => array( $this, 'op_delete_entry' ), 'perm' => $can_manage, 'confirm' => true, 'desc' => 'Delete entry by { form_id, entry_id, confirm: true }.' ),
			'update-form'  => array( 'mode' => 'write', 'run' => array( $this, 'op_update_form' ), 'perm' => $can_manage, 'desc' => 'Update Forminator form title/settings.' ),
		);
	}

	public function op_list_forms( array $args = array() ) {
		if ( ! class_exists( 'Forminator_API' ) ) { return new WP_Error( 'inactive', 'Forminator API not active.' ); }
		$forms = Forminator_API::get_forms( null, 1, 100 );
		$out = array();
		foreach ( (array) $forms as $f ) {
			$out[] = array( 'id' => is_object( $f ) ? ( $f->id ?? 0 ) : ( $f['id'] ?? 0 ), 'title' => is_object( $f ) ? ( $f->name ?? '' ) : ( $f['name'] ?? '' ) );
		}
		return array( 'forms' => $out, 'total' => count( $out ) );
	}

	public function op_get_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'Forminator_API' ) ) { return new WP_Error( 'invalid', 'Invalid form ID.' ); }
		$f = Forminator_API::get_form( $id );
		return is_wp_error( $f ) ? $f : (array) $f;
	}

	public function op_list_entries( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id || ! class_exists( 'Forminator_API' ) ) { return array( 'entries' => array(), 'total' => 0 ); }
		$entries = Forminator_API::get_entries( $id );
		return array( 'entries' => (array) $entries, 'total' => count( (array) $entries ) );
	}

	public function op_get_entry( array $args ) {
		$form_id  = (int) ( $args['form_id'] ?? 0 );
		$entry_id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $form_id || ! $entry_id || ! class_exists( 'Forminator_API' ) ) { return new WP_Error( 'invalid', 'Invalid form ID or entry ID.' ); }
		$entry = Forminator_API::get_entry( $form_id, $entry_id );
		return is_wp_error( $entry ) ? $entry : (array) $entry;
	}

	public function op_delete_entry( array $args ) {
		$form_id  = (int) ( $args['form_id'] ?? 0 );
		$entry_id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $form_id || ! $entry_id || ! class_exists( 'Forminator_API' ) ) { return new WP_Error( 'invalid', 'Invalid IDs.' ); }
		$res = Forminator_API::delete_entry( $form_id, $entry_id );
		return array( 'success' => ! is_wp_error( $res ) );
	}

	public function op_update_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post ) { return new WP_Error( 'not_found', 'Form not found.' ); }
		if ( isset( $args['title'] ) ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $args['title'] ) ) );
		}
		return array( 'success' => true );
	}
}
