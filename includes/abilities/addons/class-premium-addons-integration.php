<?php
/**
 * Premium Addons for Elementor integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_PremiumAddons_Integration extends EMCP_Tools_Addon_Pack_Integration {

	public function id(): string { return 'premium-addons'; }
	public function label(): string { return 'Premium Addons for Elementor'; }
	public function is_available(): bool { return defined( 'PREMIUM_ADDONS_VERSION' ) || class_exists( 'PremiumAddons\Admin\Includes\Admin_Helper' ); }

	public function get_widgets(): array {
		return array(
			array( 'name' => 'premium-addon-carousel',    'title' => 'Premium Carousel' ),
			array( 'name' => 'premium-addon-banner',      'title' => 'Premium Banner' ),
			array( 'name' => 'premium-addon-modal-box',   'title' => 'Premium Modal Box' ),
			array( 'name' => 'premium-addon-counter',     'title' => 'Premium Counter' ),
			array( 'name' => 'premium-addon-person',      'title' => 'Premium Team Members' ),
		);
	}
}
