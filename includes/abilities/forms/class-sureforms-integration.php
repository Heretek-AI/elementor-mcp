<?php
/**
 * SureForms integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_SureForms_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string { return 'sureforms'; }
	public function label(): string { return 'SureForms'; }
	public function is_active(): bool { return defined( 'SUREFORMS_VERSION' ) || class_exists( 'SureForms\Plugin' ); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };
		return array(
			'list-forms'   => array( 'mode' => 'read', 'run' => array( $this, 'op_list_forms' ), 'perm' => $can_manage, 'desc' => 'List SureForms forms.' ),
			'get-form'     => array( 'mode' => 'read', 'run' => array( $this, 'op_get_form' ), 'perm' => $can_manage, 'desc' => 'Get one SureForms form by { form_id }.' ),
			'list-entries' => array( 'mode' => 'read', 'run' => array( $this, 'op_list_entries' ), 'perm' => $can_manage, 'desc' => 'List entries by { form_id }.' ),
			'get-entry'    => array( 'mode' => 'read', 'run' => array( $this, 'op_get_entry' ), 'perm' => $can_manage, 'desc' => 'Get entry by { entry_id }.' ),
			'update-form'  => array( 'mode' => 'write', 'run' => array( $this, 'op_update_form' ), 'perm' => $can_manage, 'desc' => 'Update SureForms form.' ),
		);
	}

	public function op_list_forms( array $args = array() ) {
		$forms = get_posts( array( 'post_type' => 'sureforms_form', 'posts_per_page' => 100 ) );
		$out = array();
		foreach ( $forms as $f ) { $out[] = array( 'id' => $f->ID, 'title' => $f->post_title ); }
		return array( 'forms' => $out, 'total' => count( $out ) );
	}

	public function op_get_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		$post = get_post( $id );
		return $post ? (array) $post : new WP_Error( 'not_found', 'Form not found.' );
	}

	public function op_list_entries( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		$entries = get_posts( array( 'post_type' => 'sureforms_entry', 'posts_per_page' => 50, 'post_parent' => $id ) );
		$out = array();
		foreach ( $entries as $e ) { $out[] = array( 'id' => $e->ID, 'title' => $e->post_title ); }
		return array( 'entries' => $out, 'total' => count( $out ) );
	}

	public function op_get_entry( array $args ) {
		$id = (int) ( $args['entry_id'] ?? 0 );
		$post = get_post( $id );
		return $post ? (array) $post : new WP_Error( 'not_found', 'Entry not found.' );
	}

	public function op_update_form( array $args ) {
		$id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $id ) { return new WP_Error( 'invalid', 'Invalid form ID.' ); }
		$update = array( 'ID' => $id );
		if ( isset( $args['title'] ) ) { $update['post_title'] = sanitize_text_field( $args['title'] ); }
		wp_update_post( $update );
		return array( 'success' => true );
	}
}
