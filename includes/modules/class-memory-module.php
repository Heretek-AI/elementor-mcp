<?php
/**
 * Project Memory module.
 *
 * Exposes the Memory module toggle on the Modules tab, allowing administrators
 * to enable or disable persistent AI project memory and context injection.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Memory_Module extends EMCP_Tools_Module {

	public function id(): string {
		return 'memory';
	}

	public function title(): string {
		return __( 'Project Memory', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Allows AI agents to propose project-specific guidelines, rules, and learnings across sessions, gated by human review.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'pro';
	}

	public function default_active(): bool {
		return true;
	}

	public function is_available(): bool {
		return true;
	}

	public static function is_enabled(): bool {
		$active = (array) get_option( self::OPTION_ACTIVE, array() );
		return in_array( 'memory', $active, true );
	}

	public function register(): void {
		// Memory abilities are registered in Ability_Registrar.
	}

	public function render_settings(): void {
		$pending_count = count(
			EMCP_Tools_Memory_Store::query(
				array(
					'post_status'    => 'pending',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);
		?>
		<p class="description">
			<?php
			printf(
				/* translators: %d: pending count */
				esc_html__( 'Manage approved rules and pending suggestions on the Memory tab. (%d pending proposals awaiting review).', 'emcp-tools' ),
				(int) $pending_count
			);
			?>
		</p>
		<?php
	}
}
