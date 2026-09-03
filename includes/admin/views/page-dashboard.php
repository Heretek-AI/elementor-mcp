<?php
/**
 * Dashboard tab — the landing screen for EMCP Tools.
 *
 * Shows the headline stat cards (large format), a sneak-peek grid of every
 * feature area that doubles as fast navigation, a row of featured video guides,
 * and a help & resources panel. Included from EMCP_Tools_Admin::render_page(),
 * so `$this` is the admin instance.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 *
 * @var EMCP_Tools_Admin $this
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_page    = EMCP_Tools_Admin::PAGE_SLUG;
$emcp_is_free = ! function_exists( 'emcp_tools_fs' ) || ! emcp_tools_fs()->can_use_premium_code();

/**
 * Inline SVGs for the headline stat cards, keyed by the stat `key` returned by
 * EMCP_Tools_Admin::get_dashboard_stats(). Kept here (not in the class) so the
 * data method stays markup-free.
 */
$emcp_stat_svgs = array(
	'tools'      => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
	'active'     => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>',
	'pro'        => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>',
	'prompts'    => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>',
	'brand-kits' => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 5a2 2 0 012-2h3a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm6.5 9.5L12 6l3.8 1.5a1 1 0 01.56 1.3l-3 7.5a2 2 0 01-2.6 1.1l-2.26-.9zM11 4a2 2 0 114 0 2 2 0 01-4 0z"/></svg>',
	'templates'  => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 5a1 1 0 011-1h6a1 1 0 011 1v7a1 1 0 01-1 1H4a1 1 0 01-1-1V9zm10 0a1 1 0 011-1h2a1 1 0 011 1v7a1 1 0 01-1 1h-2a1 1 0 01-1-1V9z"/></svg>',
);

/**
 * Feature sneak-peek cards. `href` is the destination; `pro` badges a
 * premium-tier area; `show` gates visibility (module-backed cards drop when
 * their module is off, matching the tab nav).
 */
$emcp_features = array(
	array(
		'icon'  => 'dashicons-admin-tools',
		'title' => __( 'Machine Rites', 'emcp-tools' ),
		'desc'  => __( 'Toggle the ~140 abilities your AI client can call: Elementor, WordPress core, and Gutenberg.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-tools' ),
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-admin-links',
		'title' => __( 'Noosphere Uplink', 'emcp-tools' ),
		'desc'  => __( 'Connect Claude, Cursor, ChatGPT, copy-paste configs, app passwords, and one-click bundles.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-connection' ),
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-screenoptions',
		'title' => __( 'Tech-Modules', 'emcp-tools' ),
		'desc'  => __( 'Activate or pacify sub-cores: AI Chat, Themer, Image Optimization, Prompts, Brand Kits, and more.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-modules' ),
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-undo',
		'title' => __( 'Chrono-Ledger', 'emcp-tools' ),
		'desc'  => __( 'Review every change your AI made and roll any of them back: a unified change ledger with one-click undo.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-history' ),
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-format-chat',
		'title' => __( 'Cyber-Theurgy', 'emcp-tools' ),
		'desc'  => __( 'Directly converse with Machine Spirits inside Elementor and Gutenberg to forge pages.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-ai-chat' ),
		'pro'   => true,
		'show'  => $this->ai_chat_tab_visible(),
	),
	array(
		'icon'  => 'dashicons-layout',
		'title' => __( 'Heretek Themer', 'emcp-tools' ),
		'desc'  => __( 'Build headers, footers, and dynamic layouts with any page builder, assigned by display conditions.', 'emcp-tools' ),
		'href'  => admin_url( 'edit.php?post_type=emcp_theme_template' ),
		'show'  => class_exists( 'EMCP_Tools_Themer_Module' ) && EMCP_Tools_Themer_Module::is_enabled(),
	),
	array(
		'icon'  => 'dashicons-lightbulb',
		'title' => __( 'Canticle Library', 'emcp-tools' ),
		'desc'  => __( 'Sanctified prompt library for building pages, sections, and full sites with your AI client.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-prompts' ),
		'show'  => $this->module_tab_visible( 'prompts' ),
	),
	array(
		'icon'  => 'dashicons-art',
		'title' => __( 'Sanctified Visuals', 'emcp-tools' ),
		'desc'  => __( 'Apply curated color palettes and typography to your site\'s global styles in one click.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-brand-kits' ),
		'show'  => $this->module_tab_visible( 'brand-kits' ),
	),
	array(
		'icon'  => 'dashicons-layout',
		'title' => __( 'STC Blueprints', 'emcp-tools' ),
		'desc'  => __( 'Import professionally designed Standard Template Constructs straight into your pages.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-templates' ),
		'pro'   => true,
		'show'  => $this->module_tab_visible( 'templates' ),
	),
	array(
		'icon'  => 'dashicons-superhero',
		'title' => __( 'Adept Doctrines', 'emcp-tools' ),
		'desc'  => __( 'Install Claude Code and agent skills that train your AI how to construct with this forge.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-skills' ),
		'pro'   => true,
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-editor-code',
		'title' => __( 'Forge Sandbox', 'emcp-tools' ),
		'desc'  => __( 'Review and activate AI-authored PHP snippets behind a human approval gate, nothing runs unattended.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-widgets' ),
		'show'  => true,
	),
);
?>

<div class="emcp-dash">

	<!-- Headline stats -->
	<section class="emcp-dash-stats" aria-label="<?php esc_attr_e( 'At a glance', 'emcp-tools' ); ?>">
		<?php foreach ( $this->get_dashboard_stats() as $emcp_stat ) : ?>
			<div class="emcp-dash-stat">
				<span class="emcp-dash-stat-icon emcp-dash-stat-icon--<?php echo esc_attr( $emcp_stat['key'] ); ?>">
					<?php echo isset( $emcp_stat_svgs[ $emcp_stat['key'] ] ) ? $emcp_stat_svgs[ $emcp_stat['key'] ] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted inline SVG markup. ?>
				</span>
				<span class="emcp-dash-stat-body">
					<span class="emcp-dash-stat-value"><?php echo esc_html( number_format_i18n( $emcp_stat['value'] ) ); ?></span>
					<span class="emcp-dash-stat-label"><?php echo esc_html( $emcp_stat['label'] ); ?></span>
				</span>
			</div>
		<?php endforeach; ?>
	</section>

	<?php
	// Activity pulse: usage KPIs (Pro), change-ledger overview, most-used actions,
	// and the Sandbox item count. History + Most-used + Sandbox are free features,
	// so the whole section renders on both tiers.
	$emcp_has_usage   = class_exists( 'EMCP_Tools_Pro_Usage' );
	$emcp_usage_local = $emcp_has_usage
		? EMCP_Tools_Pro_Usage::local_summary()
		: array( 'templates' => 0, 'prompts' => 0 );

	// Change-ledger overview + most-used actions.
	$emcp_log       = class_exists( 'EMCP_Tools_Change_Log' ) ? EMCP_Tools_Change_Log::all() : array();
	$emcp_changes   = count( $emcp_log );
	$emcp_rolled    = 0;
	$emcp_last_ts   = 0;
	$emcp_action_ct = array();
	foreach ( $emcp_log as $emcp_e ) {
		if ( ! empty( $emcp_e['rolled_back'] ) ) {
			++$emcp_rolled;
		}
		if ( isset( $emcp_e['ts'] ) && (int) $emcp_e['ts'] > $emcp_last_ts ) {
			$emcp_last_ts = (int) $emcp_e['ts'];
		}
		$emcp_dom = isset( $emcp_e['domain'] ) ? (string) $emcp_e['domain'] : '';
		$emcp_act = isset( $emcp_e['action'] ) ? (string) $emcp_e['action'] : '';
		if ( '' === $emcp_dom && '' === $emcp_act ) {
			continue;
		}
		$emcp_key = $emcp_dom . '|' . $emcp_act;
		if ( ! isset( $emcp_action_ct[ $emcp_key ] ) ) {
			$emcp_action_ct[ $emcp_key ] = 0;
		}
		++$emcp_action_ct[ $emcp_key ];
	}
	arsort( $emcp_action_ct );
	$emcp_top_actions = array_slice( $emcp_action_ct, 0, 4, true );

	// Sandbox items across all three pillars (blocks + widgets + snippets):
	// active = publish, drafts = draft. Blocks/widgets are Pro CPTs; on a free
	// site they simply do not exist and wp_count_posts() returns zeros.
	$emcp_snip_active = 0;
	$emcp_snip_draft  = 0;
	if ( function_exists( 'wp_count_posts' ) ) {
		foreach ( array( 'emcp_block', 'emcp_widget', 'emcp_php_snippet' ) as $emcp_sb_cpt ) {
			$emcp_ct = wp_count_posts( $emcp_sb_cpt );
			$emcp_snip_active += ( $emcp_ct && isset( $emcp_ct->publish ) ) ? (int) $emcp_ct->publish : 0;
			$emcp_snip_draft  += ( $emcp_ct && isset( $emcp_ct->draft ) ) ? (int) $emcp_ct->draft : 0;
		}
	}
	$emcp_snip_total = $emcp_snip_active + $emcp_snip_draft;

	$emcp_url_prompts = admin_url( 'admin.php?page=' . $emcp_page . '-prompts' );
	$emcp_url_history = admin_url( 'admin.php?page=' . $emcp_page . '-history' );
	$emcp_url_sandbox = admin_url( 'admin.php?page=' . $emcp_page . '-widgets' );
	?>
	<section class="emcp-dash-section" aria-labelledby="emcp-dash-usage-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-usage-h" class="emcp-dash-section-title"><?php esc_html_e( 'Your usage', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'A quick pulse on what your AI has done on this site.', 'emcp-tools' ); ?></p>
		</div>
		<div class="emcp-dash-usage-grid">

			<!-- Usage KPIs -->
			<a class="emcp-dash-ucard" href="<?php echo esc_url( $emcp_url_prompts ); ?>">
				<span class="emcp-dash-ucard-head">
					<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--usage"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span></span>
					<span class="emcp-dash-ucard-title">
						<?php esc_html_e( 'Usage', 'emcp-tools' ); ?>
						<?php if ( ! $emcp_has_usage ) : ?>
							<span class="emcp-dash-badge emcp-dash-badge--pro"><?php esc_html_e( 'Pro', 'emcp-tools' ); ?></span>
						<?php endif; ?>
					</span>
				</span>
				<span class="emcp-dash-ucard-kpis">
					<span class="emcp-dash-ucard-kpi">
						<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_usage_local['templates'] ) ); ?></span>
						<span class="emcp-dash-ucard-sub"><?php esc_html_e( 'templates applied', 'emcp-tools' ); ?></span>
					</span>
					<span class="emcp-dash-ucard-kpi">
						<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_usage_local['prompts'] ) ); ?></span>
						<span class="emcp-dash-ucard-sub"><?php esc_html_e( 'prompts copied', 'emcp-tools' ); ?></span>
					</span>
				</span>
			</a>

			<!-- History overview -->
			<a class="emcp-dash-ucard" href="<?php echo esc_url( $emcp_url_history ); ?>">
				<span class="emcp-dash-ucard-head">
					<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--history"><span class="dashicons dashicons-undo" aria-hidden="true"></span></span>
					<span class="emcp-dash-ucard-title"><?php esc_html_e( 'History', 'emcp-tools' ); ?></span>
				</span>
				<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_changes ) ); ?></span>
				<span class="emcp-dash-ucard-sub"><?php esc_html_e( 'changes recorded', 'emcp-tools' ); ?></span>
				<span class="emcp-dash-ucard-foot">
					<?php
					if ( $emcp_last_ts > 0 ) {
						printf(
							/* translators: 1: rolled-back count, 2: human-readable time since last change */
							esc_html__( '%1$s rolled back · last %2$s ago', 'emcp-tools' ),
							esc_html( number_format_i18n( $emcp_rolled ) ),
							esc_html( human_time_diff( $emcp_last_ts, time() ) )
						);
					} else {
						esc_html_e( 'No changes recorded yet', 'emcp-tools' );
					}
					?>
				</span>
			</a>

			<!-- Most used actions -->
			<a class="emcp-dash-ucard" href="<?php echo esc_url( $emcp_url_history ); ?>">
				<span class="emcp-dash-ucard-head">
					<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--tools"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span></span>
					<span class="emcp-dash-ucard-title"><?php esc_html_e( 'Most used', 'emcp-tools' ); ?></span>
				</span>
				<?php if ( ! empty( $emcp_top_actions ) ) : ?>
					<ul class="emcp-dash-ucard-list">
						<?php
						foreach ( $emcp_top_actions as $emcp_key => $emcp_cnt ) :
							$emcp_parts = explode( '|', $emcp_key, 2 );
							$emcp_dom   = $emcp_parts[0];
							$emcp_act   = isset( $emcp_parts[1] ) ? $emcp_parts[1] : '';
							?>
							<li>
								<span class="emcp-dash-ucard-act"><?php if ( '' !== $emcp_dom ) : ?><span class="emcp-dash-ucard-dom"><?php echo esc_html( $emcp_dom ); ?> </span><?php endif; ?><?php echo esc_html( $emcp_act ); ?></span>
								<span class="emcp-dash-ucard-cnt"><?php echo esc_html( number_format_i18n( $emcp_cnt ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<span class="emcp-dash-ucard-empty"><?php esc_html_e( 'No activity yet', 'emcp-tools' ); ?></span>
				<?php endif; ?>
			</a>

			<!-- Sandbox items -->
			<a class="emcp-dash-ucard" href="<?php echo esc_url( $emcp_url_sandbox ); ?>">
				<span class="emcp-dash-ucard-head">
					<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--sandbox"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span></span>
					<span class="emcp-dash-ucard-title"><?php esc_html_e( 'Sandbox', 'emcp-tools' ); ?></span>
				</span>
				<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_snip_total ) ); ?></span>
				<span class="emcp-dash-ucard-sub"><?php esc_html_e( 'Blocks, widgets & snippets', 'emcp-tools' ); ?></span>
				<span class="emcp-dash-ucard-foot">
					<?php
					printf(
						/* translators: 1: active sandbox-item count, 2: draft sandbox-item count */
						esc_html__( '%1$s active · %2$s drafts', 'emcp-tools' ),
						esc_html( number_format_i18n( $emcp_snip_active ) ),
						esc_html( number_format_i18n( $emcp_snip_draft ) )
					);
					?>
				</span>
			</a>

		</div>
	</section>

	<!-- Feature sneak peek -->
	<section class="emcp-dash-section emcp-dash-section--toolkit" aria-labelledby="emcp-dash-features-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-features-h" class="emcp-dash-section-title"><?php esc_html_e( 'Explore your toolkit', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'Everything this plugin can do, jump straight in.', 'emcp-tools' ); ?></p>
		</div>
		<div class="emcp-dash-grid">
			<?php
			foreach ( $emcp_features as $emcp_feature ) :
				if ( empty( $emcp_feature['show'] ) ) {
					continue;
				}
				$emcp_is_pro_feature = ! empty( $emcp_feature['pro'] );
				?>
				<a class="emcp-dash-card" href="<?php echo esc_url( $emcp_feature['href'] ); ?>">
					<span class="emcp-dash-card-icon"><span class="dashicons <?php echo esc_attr( $emcp_feature['icon'] ); ?>" aria-hidden="true"></span></span>
					<span class="emcp-dash-card-body">
						<span class="emcp-dash-card-title">
							<?php echo esc_html( $emcp_feature['title'] ); ?>
							<?php if ( $emcp_is_pro_feature && $emcp_is_free ) : ?>
								<span class="emcp-dash-badge emcp-dash-badge--pro"><?php esc_html_e( 'Pro', 'emcp-tools' ); ?></span>
							<?php endif; ?>
						</span>
						<span class="emcp-dash-card-desc"><?php echo esc_html( $emcp_feature['desc'] ); ?></span>
					</span>
					<span class="emcp-dash-card-arrow dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<!-- Help & resources -->
	<section class="emcp-dash-section emcp-dash-section--help" aria-labelledby="emcp-dash-help-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-help-h" class="emcp-dash-section-title"><?php esc_html_e( 'Help &amp; resources', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'Quick links to documentation, GitHub discussions, and release updates.', 'emcp-tools' ); ?></p>
		</div>
		<?php
		$emcp_ver = class_exists( 'EMCP_Tools_GitHub_Updater' )
			? EMCP_Tools_GitHub_Updater::current_update_status()
			: array( 'current' => EMCP_TOOLS_VERSION, 'latest' => EMCP_TOOLS_VERSION, 'update_available' => false, 'update_url' => admin_url( 'plugins.php' ) );
		?>
		<?php if ( ! empty( $emcp_ver['update_available'] ) ) : ?>
			<a class="emcp-dash-version emcp-dash-version--update" href="<?php echo esc_url( $emcp_ver['update_url'] ); ?>">
				<span class="emcp-dash-version-dot" aria-hidden="true"></span>
				<span class="emcp-dash-version-text">
					<span class="emcp-dash-version-title"><?php esc_html_e( 'Update available', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-version-sub">
						<?php
						printf(
							/* translators: 1: installed version, 2: available version */
							esc_html__( 'You have v%1$s, v%2$s is ready to install.', 'emcp-tools' ),
							esc_html( $emcp_ver['current'] ),
							esc_html( $emcp_ver['latest'] )
						);
						?>
					</span>
				</span>
				<span class="emcp-dash-version-cta"><?php esc_html_e( 'Update now', 'emcp-tools' ); ?><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></span>
			</a>
		<?php else : ?>
			<div class="emcp-dash-version emcp-dash-version--ok">
				<span class="emcp-dash-version-dot" aria-hidden="true"></span>
				<span class="emcp-dash-version-text">
					<span class="emcp-dash-version-title"><?php esc_html_e( 'You\'re on the latest version', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-version-sub">
						<?php
						printf(
							/* translators: %s: installed version number */
							esc_html__( 'Heretek Control Core v%s', 'emcp-tools' ),
							esc_html( $emcp_ver['current'] )
						);
						?>
					</span>
				</span>
			</div>
		<?php endif; ?>
		<div style="margin: 6px 0 14px 0; text-align: right;">
			<a class="button button-secondary button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . $emcp_page . '&emcp_check_updates=1' ), 'emcp_check_updates' ) ); ?>">
				<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 3px; font-size: 13px; width: 13px; height: 13px; line-height: 1;"></span><?php esc_html_e( 'Check for updates', 'emcp-tools' ); ?>
			</a>
		</div>
		<div class="emcp-dash-help">
			<a class="emcp-dash-help-link" href="https://github.com/Heretek-AI/heretek-control-core#readme" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-book" aria-hidden="true"></span>
				<span>
					<span class="emcp-dash-help-title"><?php esc_html_e( 'Documentation', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-help-desc"><?php esc_html_e( 'Guides and reference for all unlocked tools.', 'emcp-tools' ); ?></span>
				</span>
			</a>
			<a class="emcp-dash-help-link" href="https://github.com/Heretek-AI/heretek-control-core/issues" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-sos" aria-hidden="true"></span>
				<span>
					<span class="emcp-dash-help-title"><?php esc_html_e( 'Issues &amp; Support', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-help-desc"><?php esc_html_e( 'Report bugs or request new features.', 'emcp-tools' ); ?></span>
				</span>
			</a>
			<a class="emcp-dash-help-link" href="https://github.com/Heretek-AI/heretek-control-core/discussions" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-groups" aria-hidden="true"></span>
				<span>
					<span class="emcp-dash-help-title"><?php esc_html_e( 'Discussions &amp; Community', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-help-desc"><?php esc_html_e( 'Share builds and get tips from other developers.', 'emcp-tools' ); ?></span>
				</span>
			</a>
			<a class="emcp-dash-help-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $emcp_page . '-changelog' ) ); ?>">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
				<span>
					<span class="emcp-dash-help-title"><?php esc_html_e( 'Changelog', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-help-desc"><?php esc_html_e( 'See what\'s new in the latest releases.', 'emcp-tools' ); ?></span>
				</span>
			</a>
		</div>
	</section>

</div>
