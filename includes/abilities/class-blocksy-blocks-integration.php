<?php
/**
 * Blocksy Blocks integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Blocksy_Blocks_Integration extends EMCP_Tools_Theme_Integration {

	public function id(): string { return 'blocksy-blocks'; }
	public function label(): string { return 'Blocksy Blocks'; }
	public function is_available(): bool { return 'blocksy' === get_template() || defined( 'BLOCKSY_VERSION' ); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };
		return array(
			'list-blocks' => array( 'mode' => 'read', 'run' => array( $this, 'op_list_blocks' ), 'perm' => $can_manage, 'desc' => 'List Blocksy custom blocks.' ),
		);
	}

	public function op_list_blocks( array $args = array() ) {
		return array( 'blocks' => EMCP_Tools_Blocksy_Blocks_Catalog::get_blocks() );
	}
}
