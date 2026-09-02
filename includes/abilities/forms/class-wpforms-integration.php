<?php
/**
 * WPForms Pro integration — two dispatcher tools (wpforms-read / wpforms-write).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_WPForms_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string {
		return 'wpforms';
	}

	public function label(): string {
		return 'WPForms';
	}

	public function is_active(): bool {
		return function_exists( 'wpforms' ) || class_exists( 'WPForms' );
	}

	protected function operations(): array {
		$can_manage = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		return array(
			'list-forms'   => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_list_forms' ),
				'perm' => $can_manage,
				'desc' => 'List all WPForms forms (ID, title, status, entry count).',
			),
			'get-form'     => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_form' ),
				'perm' => $can_manage,
				'desc' => 'Get one WPForms form structure by { form_id }: fields, settings, and notifications.',
			),
			'list-entries' => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_list_entries' ),
				'perm' => $can_manage,
				'desc' => 'List entries for { form_id } (limit, offset, status).',
			),
			'get-entry'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_entry' ),
				'perm' => $can_manage,
				'desc' => 'Get one entry by { entry_id }.',
			),
			'delete-entry' => array(
				'mode'    => 'write',
				'run'     => array( $this, 'op_delete_entry' ),
				'perm'    => $can_manage,
				'confirm' => true,
				'desc'    => 'Delete an entry by { entry_id, confirm: true }.',
			),
			'update-form'  => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_form' ),
				'perm' => $can_manage,
				'desc' => 'Update form settings/fields by { form_id, post_title?, post_content? }.',
			),
		);
	}

	public function op_list_forms( array $args = array() ) {
		if ( ! $this->is_active() ) {
			return new WP_Error( 'inactive', __( 'WPForms is not active.', 'emcp-tools' ) );
		}
		$forms = function_exists( 'wpforms' ) ? wpforms()->form->get( '' ) : array();
		$out   = array();
		foreach ( (array) $forms as $f ) {
			$post_obj = is_object( $f ) && isset( $f->post_title ) ? $f : get_post( is_object( $f ) ? $f->ID : $f );
			if ( $post_obj ) {
				$out[] = array(
					'id'    => $post_obj->ID,
					'title' => $post_obj->post_title,
					'date'  => $post_obj->post_date,
				);
			}
		}
		return array( 'forms' => $out, 'total' => count( $out ) );
	}

	public function op_get_form( array $args ) {
		$form_id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $form_id ) {
			return new WP_Error( 'missing_id', __( 'A form_id is required.', 'emcp-tools' ) );
		}
		$form = function_exists( 'wpforms' ) ? wpforms()->form->get( $form_id ) : get_post( $form_id );
		if ( ! $form ) {
			return new WP_Error( 'not_found', __( 'Form not found.', 'emcp-tools' ) );
		}
		return array(
			'id'      => $form_id,
			'title'   => is_object( $form ) ? ( $form->post_title ?? '' ) : '',
			'content' => is_object( $form ) ? ( $form->post_content ?? '' ) : '',
		);
	}

	public function op_list_entries( array $args ) {
		$form_id = (int) ( $args['form_id'] ?? 0 );
		$limit   = (int) ( $args['limit'] ?? 20 );
		if ( ! function_exists( 'wpforms' ) || ! isset( wpforms()->entry ) ) {
			return array( 'entries' => array(), 'total' => 0 );
		}
		$entries = wpforms()->entry->get_entries( array( 'form_id' => $form_id, 'number' => $limit ) );
		return array( 'entries' => (array) $entries, 'total' => count( (array) $entries ) );
	}

	public function op_get_entry( array $args ) {
		$entry_id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $entry_id ) {
			return new WP_Error( 'missing_id', __( 'An entry_id is required.', 'emcp-tools' ) );
		}
		if ( ! function_exists( 'wpforms' ) || ! isset( wpforms()->entry ) ) {
			return new WP_Error( 'unavailable', __( 'WPForms entries API is unavailable.', 'emcp-tools' ) );
		}
		$entry = wpforms()->entry->get( $entry_id );
		return $entry ? (array) $entry : new WP_Error( 'not_found', __( 'Entry not found.', 'emcp-tools' ) );
	}

	public function op_delete_entry( array $args ) {
		$entry_id = (int) ( $args['entry_id'] ?? 0 );
		if ( ! $entry_id ) {
			return new WP_Error( 'missing_id', __( 'An entry_id is required.', 'emcp-tools' ) );
		}
		if ( function_exists( 'wpforms' ) && isset( wpforms()->entry ) ) {
			$ok = wpforms()->entry->delete( $entry_id );
			return array( 'success' => (bool) $ok );
		}
		return new WP_Error( 'unavailable', __( 'WPForms entries API is unavailable.', 'emcp-tools' ) );
	}

	public function op_update_form( array $args ) {
		$form_id = (int) ( $args['form_id'] ?? 0 );
		if ( ! $form_id ) {
			return new WP_Error( 'missing_id', __( 'A form_id is required.', 'emcp-tools' ) );
		}
		$update_args = array( 'ID' => $form_id );
		if ( isset( $args['post_title'] ) ) {
			$update_args['post_title'] = sanitize_text_field( $args['post_title'] );
		}
		if ( isset( $args['post_content'] ) ) {
			$update_args['post_content'] = (string) $args['post_content'];
		}
		$res = wp_update_post( $update_args );
		return is_wp_error( $res ) ? $res : array( 'success' => true, 'id' => $form_id );
	}
}
