<?php
/**
 * Essential Addons for Elementor integration (Pro).
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_EssentialAddons_Integration extends EMCP_Tools_Addon_Pack_Integration {

	public function id(): string { return 'essential-addons'; }
	public function label(): string { return 'Essential Addons for Elementor'; }
	public function is_available(): bool { return defined( 'EAEL_PLUGIN_VERSION' ) || class_exists( 'Essential_Addons_Elementor\Classes\Bootstrap' ); }

	public function get_widgets(): array {
		return array(
			array( 'name' => 'eael-adv-accordion', 'title' => 'EA Advanced Accordion' ),
			array( 'name' => 'eael-adv-tabs',      'title' => 'EA Advanced Tabs' ),
			array( 'name' => 'eael-post-grid',     'title' => 'EA Post Grid' ),
			array( 'name' => 'eael-cta-box',       'title' => 'EA Call to Action' ),
			array( 'name' => 'eael-fancy-text',    'title' => 'EA Fancy Text' ),
			array( 'name' => 'eael-pricing-table', 'title' => 'EA Pricing Table' ),
		);
	}
}
