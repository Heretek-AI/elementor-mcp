<?php
/**
 * Marketplace tab: browse published EMCP Cloud listings and install them into
 * this site (as draft artifacts). Requires an active EMCP Cloud connection.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_connected = class_exists( 'EMCP_Tools_Cloud' ) && EMCP_Tools_Cloud::is_connected();
$emcp_kind_label = array(
	'block'       => __( 'Gutenberg block', 'emcp-tools' ),
	'widget'      => __( 'Elementor widget', 'emcp-tools' ),
	'snippet'     => __( 'PHP snippet', 'emcp-tools' ),
	'php_snippet' => __( 'PHP snippet', 'emcp-tools' ),
	'template'    => __( 'Template', 'emcp-tools' ),
);
$emcp_mk       = isset( $_GET['mk'] ) ? sanitize_key( wp_unslash( $_GET['mk'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$emcp_mk_id    = isset( $_GET['mk_id'] ) ? absint( wp_unslash( $_GET['mk_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$emcp_category = isset( $_GET['mk_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['mk_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="elementor-mcp-section">
	<h2><?php esc_html_e( 'Marketplace', 'emcp-tools' ); ?></h2>
	<p class="elementor-mcp-activate-note">
		<?php esc_html_e( 'Browse community and Pro blocks, widgets, snippets, and templates from EMCP Cloud and install them into this site. Installs land as a draft for you to review before publishing.', 'emcp-tools' ); ?>
	</p>

	<?php if ( 'installed' === $emcp_mk ) : ?>
		<div class="notice notice-success inline"><p>
			<?php esc_html_e( 'Installed as a draft.', 'emcp-tools' ); ?>
			<?php if ( $emcp_mk_id ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $emcp_mk_id ) ); ?>"><?php esc_html_e( 'Edit it →', 'emcp-tools' ); ?></a>
			<?php endif; ?>
		</p></div>
	<?php elseif ( 'pro' === $emcp_mk ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'That item requires a paid EMCP Cloud plan. Upgrade your Cloud plan to install Pro items.', 'emcp-tools' ); ?></p></div>
	<?php elseif ( 'err' === $emcp_mk ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Could not install that item. Please try again.', 'emcp-tools' ); ?></p></div>
	<?php endif; ?>

	<?php
	if ( ! $emcp_connected ) :
		$emcp_connect_url = class_exists( 'EMCP_Tools_Cloud_Connect' ) ? EMCP_Tools_Cloud_Connect::connect_url() : admin_url( 'admin.php?page=emcp-tools-connection' );
		?>
		<div class="notice notice-info inline" style="margin-top:12px;"><p>
			<?php esc_html_e( 'Connect this site to EMCP Cloud to browse and install marketplace items.', 'emcp-tools' ); ?>
			<a href="<?php echo esc_url( $emcp_connect_url ); ?>" class="button button-primary" style="margin-left:8px;"><?php esc_html_e( 'Connect to EMCP Cloud', 'emcp-tools' ); ?></a>
		</p></div>
		<?php
		return;
	endif;

	$emcp_res      = EMCP_Tools_Cloud_Sync::marketplace_list( $emcp_category );
	$emcp_listings = ( ! is_wp_error( $emcp_res ) && isset( $emcp_res['listings'] ) && is_array( $emcp_res['listings'] ) ) ? $emcp_res['listings'] : array();

	// Category options from the returned set (client-side subset filter is fine).
	$emcp_all       = ( ! is_wp_error( $emcp_res ) && isset( $emcp_res['listings'] ) ) ? $emcp_res['listings'] : array();
	$emcp_cats      = array();
	foreach ( $emcp_all as $emcp_row ) {
		if ( ! empty( $emcp_row['category'] ) ) {
			$emcp_cats[ $emcp_row['category'] ] = true;
		}
	}
	$emcp_cats = array_keys( $emcp_cats );
	sort( $emcp_cats );
	?>

	<?php if ( is_wp_error( $emcp_res ) ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $emcp_res->get_error_message() ); ?></p></div>
	<?php elseif ( empty( $emcp_listings ) ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'No published items yet. Check back soon.', 'emcp-tools' ); ?></p></div>
	<?php else : ?>

		<?php if ( ! empty( $emcp_cats ) ) : ?>
			<form method="get" style="margin:14px 0;">
				<input type="hidden" name="page" value="emcp-tools-marketplace" />
				<select name="mk_cat" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'All categories', 'emcp-tools' ); ?></option>
					<?php foreach ( $emcp_cats as $emcp_c ) : ?>
						<option value="<?php echo esc_attr( $emcp_c ); ?>" <?php selected( $emcp_category, $emcp_c ); ?>><?php echo esc_html( $emcp_c ); ?></option>
					<?php endforeach; ?>
				</select>
			</form>
		<?php endif; ?>

		<div class="emcp-mk-grid">
			<?php foreach ( $emcp_listings as $emcp_l ) :
				$emcp_slug   = (string) ( $emcp_l['slug'] ?? '' );
				$emcp_title  = (string) ( $emcp_l['title'] ?? $emcp_slug );
				$emcp_kind   = (string) ( $emcp_l['kind'] ?? '' );
				$emcp_klabel = $emcp_kind_label[ $emcp_kind ] ?? $emcp_kind;
				$emcp_is_pro = ( 'pro' === ( $emcp_l['access'] ?? 'community' ) );
				$emcp_shot   = ( isset( $emcp_l['screenshots'] ) && is_array( $emcp_l['screenshots'] ) && ! empty( $emcp_l['screenshots'][0] ) ) ? (string) $emcp_l['screenshots'][0] : '';
				$emcp_prev   = (string) ( $emcp_l['preview_url'] ?? '' );
				$emcp_ins    = (int) ( $emcp_l['install_count'] ?? 0 );
				?>
				<div class="emcp-mk-card">
					<?php if ( $emcp_shot ) : ?>
						<img class="emcp-mk-card__shot" src="<?php echo esc_url( $emcp_shot ); ?>" alt="<?php echo esc_attr( $emcp_title ); ?>" loading="lazy" />
					<?php else : ?>
						<div class="emcp-mk-card__shot emcp-mk-card__shot--empty" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $emcp_klabel, 0, 1 ) ) ); ?></div>
					<?php endif; ?>
					<div class="emcp-mk-card__body">
						<div class="emcp-mk-card__k">
							<span><?php echo esc_html( $emcp_klabel ); ?><?php echo ! empty( $emcp_l['category'] ) ? ' · ' . esc_html( $emcp_l['category'] ) : ''; ?></span>
							<?php if ( $emcp_is_pro ) : ?><span class="emcp-mk-card__pro">Pro</span><?php endif; ?>
						</div>
						<h3 class="emcp-mk-card__title"><?php echo esc_html( $emcp_title ); ?></h3>
						<?php if ( ! empty( $emcp_l['summary'] ) ) : ?>
							<p class="emcp-mk-card__sum"><?php echo esc_html( $emcp_l['summary'] ); ?></p>
						<?php endif; ?>
						<div class="emcp-mk-card__foot">
							<span><?php echo esc_html( sprintf( _n( '%d install', '%d installs', $emcp_ins, 'emcp-tools' ), $emcp_ins ) ); ?></span>
							<?php if ( $emcp_prev ) : ?><a href="<?php echo esc_url( $emcp_prev ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Preview ↗', 'emcp-tools' ); ?></a><?php endif; ?>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="emcp-mk-card__cta">
							<input type="hidden" name="action" value="emcp_tools_marketplace_install" />
							<input type="hidden" name="slug" value="<?php echo esc_attr( $emcp_slug ); ?>" />
							<?php wp_nonce_field( 'emcp_tools_marketplace_install' ); ?>
							<button type="submit" class="button button-primary" style="width:100%;justify-content:center;"><?php esc_html_e( 'Install', 'emcp-tools' ); ?></button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<style>
	.emcp-mk-grid { display: grid; grid-template-columns: repeat( auto-fill, minmax( 240px, 1fr ) ); gap: 16px; margin-top: 16px; }
	.emcp-mk-card { display: flex; flex-direction: column; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; overflow: hidden; }
	.emcp-mk-card__shot { width: 100%; height: 150px; object-fit: cover; border-bottom: 1px solid #f0f0f1; background: #f6f7f7; }
	.emcp-mk-card__shot--empty { display: grid; place-items: center; font-size: 34px; font-weight: 700; color: #c3c4c7; }
	.emcp-mk-card__body { padding: 14px 16px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
	.emcp-mk-card__k { display: flex; align-items: center; gap: 8px; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #787c82; }
	.emcp-mk-card__pro { font-weight: 700; color: #4338ca; background: #eef0ff; padding: 1px 7px; border-radius: 999px; }
	.emcp-mk-card__title { font-size: 15px; margin: 2px 0 0; }
	.emcp-mk-card__sum { font-size: 13px; color: #50575e; margin: 0; flex: 1; }
	.emcp-mk-card__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 12px; color: #787c82; margin-top: 4px; }
	.emcp-mk-card__cta { margin-top: 8px; }
</style>
