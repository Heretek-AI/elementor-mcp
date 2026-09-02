<?php
/**
 * GenerateBlocks integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_GenerateBlocks_Integration extends EMCP_Tools_Theme_Integration {

	public function id(): string { return 'generateblocks'; }
	public function label(): string { return 'GenerateBlocks'; }
	public function is_available(): bool { return defined( 'GENERATEBLOCKS_VERSION' ); }

	protected function operations(): array {
		$can_manage = static function (): bool { return current_user_can( 'manage_options' ); };
		return array(
			'list-blocks'          => array( 'mode' => 'read', 'run' => array( $this, 'op_list_blocks' ), 'perm' => $can_manage, 'desc' => 'List GenerateBlocks blocks.' ),
			'get-block-defaults'   => array( 'mode' => 'read', 'run' => array( $this, 'op_get_defaults' ), 'perm' => $can_manage, 'desc' => 'Get block defaults.' ),
			'update-block-defaults'=> array( 'mode' => 'write', 'run' => array( $this, 'op_update_defaults' ), 'perm' => $can_manage, 'desc' => 'Update block defaults.' ),
		);
	}

	public function op_list_blocks( array $args = array() ) {
		return array( 'blocks' => EMCP_Tools_GenerateBlocks_Catalog::get_blocks() );
	}

	public function op_get_defaults( array $args = array() ) {
		return array( 'defaults' => get_option( 'generateblocks_defaults', array() ) );
	}

	public function op_update_defaults( array $args ) {
		$vals = (array) ( $args['defaults'] ?? array() );
		$cur  = (array) get_option( 'generateblocks_defaults', array() );
		update_option( 'generateblocks_defaults', array_merge( $cur, $vals ) );
		return array( 'success' => true );
	}
}
