<?php
/**
 * Generic Pro-feature upsell fallback (free build).
 *
 * Shown when a Pro-only tab (AI Chat, Skills, …) is opened but the private Pro
 * overlay is absent, so the feature's own view file does not exist. Self-
 * contained: uses core WordPress button classes + a small scoped style block so
 * it renders without any Pro CSS (ai-chat.css et al. ship only in the Pro zip).
 * Flat colors, indigo accent, no gradients (brand design rules).
 *
 * Expects: $emcp_upsell_feature (string) — the feature name to headline.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_upsell_feature = isset( $emcp_upsell_feature ) ? (string) $emcp_upsell_feature : __( 'This feature', 'emcp-tools' );
$emcp_upsell_url     = function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : 'https://emcptools.com/pricing';
?>

<style>
	.emcp-pro-upsell { max-width: 640px; margin: 24px 0; padding: 40px 32px; text-align: center;
		background: var(--mcp-white, #111116); border: 1px solid var(--mcp-gray-200, #1f1f27); border-radius: 10px; box-shadow: 0 4px 14px rgba(0,0,0,.4); }
	.emcp-pro-upsell__badge { display: inline-block; margin-bottom: 16px; padding: 4px 12px;
		font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
		color: var(--mcp-primary); background: rgba(220, 38, 38, 0.15); border: 1px solid rgba(220, 38, 38, 0.35); border-radius: 999px; }
	.emcp-pro-upsell h2 { margin: 0 0 10px; font-size: 22px; color: #ffffff; font-family: var(--mcp-font-heading); }
	.emcp-pro-upsell p { margin: 0 auto 24px; max-width: 480px; color: var(--mcp-gray-500); font-size: 14px; line-height: 1.6; }
	.emcp-pro-upsell .button-hero { background: var(--mcp-primary); border-color: var(--mcp-primary); color: #fff; font-weight: 600; }
	.emcp-pro-upsell .button-hero:hover { background: var(--mcp-primary-hover); border-color: var(--mcp-primary-hover); }
</style>

<div class="emcp-pro-upsell">
	<span class="emcp-pro-upsell__badge"><?php esc_html_e( 'EMCP Pro', 'emcp-tools' ); ?></span>
	<h2>
		<?php
		/* translators: %s: Pro feature name, e.g. "AI Chat". */
		printf( esc_html__( '%s is an EMCP Pro feature', 'emcp-tools' ), esc_html( $emcp_upsell_feature ) );
		?>
	</h2>
	<p><?php esc_html_e( 'Upgrade to EMCP Pro to unlock this feature along with the full Pro toolkit: AI Chat in your editor, deep plugin integrations (ACF, WooCommerce, SEO and form plugins), the SEO & Accessibility toolkit, the Widget Builder, 50+ premium prompts, the templates library, and more.', 'emcp-tools' ); ?></p>
	<a class="button button-primary button-hero" href="<?php echo esc_url( $emcp_upsell_url ); ?>" target="_blank" rel="noopener noreferrer">
		<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
	</a>
</div>
