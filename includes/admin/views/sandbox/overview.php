<?php
/**
 * Sandbox overview — the parent Sandbox page.
 *
 * Three flat cards (Blocks / Widgets / PHP Snippets), each a summary + an
 * "Open →" link into that pillar's full management screen
 * (?page=emcp-tools-widgets&view=blocks|widgets|snippets). No gradients, no
 * new visual language: reuses the existing dashboard usage-card classes
 * (.emcp-dash-ucard*) and the tier badge classes (.elementor-mcp-badge*).
 *
 * @package EMCP_Tools
 * @since   3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_sb_base_url = menu_page_url( 'emcp-tools-widgets', false );

// ----- Blocks (Pro) -----
$emcp_sb_blocks_ok = class_exists( 'EMCP_Tools_Block_Store' ) && EMCP_Tools_Block_Store::user_has_access();
$emcp_sb_blocks    = $emcp_sb_blocks_ok ? EMCP_Tools_Block_Store::instance()->list_blocks( 'any' ) : array();
$emcp_sb_bl_active = 0;
$emcp_sb_bl_draft  = 0;
foreach ( $emcp_sb_blocks as $emcp_sb_b ) {
	if ( 'active' === ( $emcp_sb_b['status'] ?? '' ) ) {
		++$emcp_sb_bl_active;
	} else {
		++$emcp_sb_bl_draft;
	}
}

// ----- Widgets (Pro) -----
$emcp_sb_widgets_ok = class_exists( 'EMCP_Tools_Widget_Store' ) && EMCP_Tools_Widget_Store::user_has_access();
$emcp_sb_widgets    = $emcp_sb_widgets_ok ? EMCP_Tools_Widget_Store::list_widgets( 'any' ) : array();
$emcp_sb_wd_active  = 0;
$emcp_sb_wd_draft   = 0;
foreach ( $emcp_sb_widgets as $emcp_sb_w ) {
	if ( 'active' === ( $emcp_sb_w['status'] ?? '' ) ) {
		++$emcp_sb_wd_active;
	} else {
		++$emcp_sb_wd_draft;
	}
}

// ----- PHP Snippets (Free, capability-gated) -----
$emcp_sb_snip_ok     = class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) && EMCP_Tools_PHP_Snippet_Store::can_edit();
$emcp_sb_snippets    = class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) ? EMCP_Tools_PHP_Snippet_Store::list_snippets( 'any' ) : array();
$emcp_sb_sn_active   = 0;
$emcp_sb_sn_draft    = 0;
foreach ( $emcp_sb_snippets as $emcp_sb_s ) {
	if ( 'active' === ( $emcp_sb_s['status'] ?? '' ) ) {
		++$emcp_sb_sn_active;
	} else {
		++$emcp_sb_sn_draft;
	}
}

$emcp_sb_upgrade_url = function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : '#';
?>

<div class="emcp-sandbox-overview">
	<h2><?php esc_html_e( 'Sandbox', 'emcp-tools' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Code your AI agent generated through the MCP tools lives here, isolated under wp-content/uploads, never in your theme, core, or other plugins.', 'emcp-tools' ); ?>
	</p>

	<div class="emcp-dash-usage-grid">

		<!-- Blocks -->
		<div class="emcp-dash-ucard">
			<span class="emcp-dash-ucard-head">
				<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--usage"><span class="dashicons dashicons-block-default" aria-hidden="true"></span></span>
				<span class="emcp-dash-ucard-title">
					<?php esc_html_e( 'Blocks', 'emcp-tools' ); ?>
					<span class="elementor-mcp-badge elementor-mcp-badge--pro"><?php esc_html_e( 'PRO', 'emcp-tools' ); ?></span>
				</span>
			</span>
			<p class="emcp-dash-ucard-sub">
				<?php esc_html_e( 'AI-generated custom Gutenberg blocks, compiled from a spec and reviewed here before they go live.', 'emcp-tools' ); ?>
			</p>
			<?php if ( $emcp_sb_blocks_ok ) : ?>
				<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_sb_bl_active + $emcp_sb_bl_draft ) ); ?></span>
				<span class="emcp-dash-ucard-foot">
					<?php
					printf(
						/* translators: 1: active block count, 2: draft block count */
						esc_html__( '%1$s active · %2$s drafts', 'emcp-tools' ),
						esc_html( number_format_i18n( $emcp_sb_bl_active ) ),
						esc_html( number_format_i18n( $emcp_sb_bl_draft ) )
					);
					?>
				</span>
			<?php else : ?>
				<p class="emcp-dash-ucard-sub">
					<?php esc_html_e( 'Requires Pro.', 'emcp-tools' ); ?>
					<a href="<?php echo esc_url( $emcp_sb_upgrade_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?></a>
				</p>
			<?php endif; ?>
			<p class="emcp-sandbox-overview-open">
				<a class="button emcp-sandbox-open-btn" href="<?php echo esc_url( $emcp_sb_base_url . '&view=blocks' ); ?>">
					<?php esc_html_e( 'Open →', 'emcp-tools' ); ?>
				</a>
			</p>
		</div>

		<!-- Widgets -->
		<div class="emcp-dash-ucard">
			<span class="emcp-dash-ucard-head">
				<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--tools"><span class="dashicons dashicons-screenoptions" aria-hidden="true"></span></span>
				<span class="emcp-dash-ucard-title">
					<?php esc_html_e( 'Widgets', 'emcp-tools' ); ?>
					<span class="elementor-mcp-badge elementor-mcp-badge--pro"><?php esc_html_e( 'PRO', 'emcp-tools' ); ?></span>
				</span>
			</span>
			<p class="emcp-dash-ucard-sub">
				<?php esc_html_e( 'AI-generated custom Elementor widgets, sandboxed and shown in the panel under "Custom (EMCP)".', 'emcp-tools' ); ?>
			</p>
			<?php if ( $emcp_sb_widgets_ok ) : ?>
				<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_sb_wd_active + $emcp_sb_wd_draft ) ); ?></span>
				<span class="emcp-dash-ucard-foot">
					<?php
					printf(
						/* translators: 1: active widget count, 2: draft widget count */
						esc_html__( '%1$s active · %2$s drafts', 'emcp-tools' ),
						esc_html( number_format_i18n( $emcp_sb_wd_active ) ),
						esc_html( number_format_i18n( $emcp_sb_wd_draft ) )
					);
					?>
				</span>
			<?php else : ?>
				<p class="emcp-dash-ucard-sub">
					<?php esc_html_e( 'Requires Pro.', 'emcp-tools' ); ?>
					<a href="<?php echo esc_url( $emcp_sb_upgrade_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?></a>
				</p>
			<?php endif; ?>
			<p class="emcp-sandbox-overview-open">
				<a class="button emcp-sandbox-open-btn" href="<?php echo esc_url( $emcp_sb_base_url . '&view=widgets' ); ?>">
					<?php esc_html_e( 'Open →', 'emcp-tools' ); ?>
				</a>
			</p>
		</div>

		<!-- PHP Snippets -->
		<div class="emcp-dash-ucard">
			<span class="emcp-dash-ucard-head">
				<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--sandbox"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span></span>
				<span class="emcp-dash-ucard-title">
					<?php esc_html_e( 'PHP Snippets', 'emcp-tools' ); ?>
					<span class="elementor-mcp-badge elementor-mcp-badge--free"><?php esc_html_e( 'FREE', 'emcp-tools' ); ?></span>
				</span>
			</span>
			<p class="emcp-dash-ucard-sub">
				<?php esc_html_e( 'Small PHP snippets an AI agent can draft, run as a shortcode or on a hook, that stay inactive until you review and activate them.', 'emcp-tools' ); ?>
			</p>
			<?php if ( $emcp_sb_snip_ok ) : ?>
				<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_sb_sn_active + $emcp_sb_sn_draft ) ); ?></span>
				<span class="emcp-dash-ucard-foot">
					<?php
					printf(
						/* translators: 1: active snippet count, 2: draft snippet count */
						esc_html__( '%1$s active · %2$s drafts', 'emcp-tools' ),
						esc_html( number_format_i18n( $emcp_sb_sn_active ) ),
						esc_html( number_format_i18n( $emcp_sb_sn_draft ) )
					);
					?>
				</span>
			<?php else : ?>
				<p class="emcp-dash-ucard-sub">
					<?php esc_html_e( 'Managing PHP snippets requires the manage_options and unfiltered_html capabilities.', 'emcp-tools' ); ?>
				</p>
			<?php endif; ?>
			<p class="emcp-sandbox-overview-open">
				<a class="button emcp-sandbox-open-btn" href="<?php echo esc_url( $emcp_sb_base_url . '&view=snippets' ); ?>">
					<?php esc_html_e( 'Open →', 'emcp-tools' ); ?>
				</a>
			</p>
		</div>

	</div>
</div>
