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
// A freshly-installed draft is a sandbox CPT with no standard post editor, so
// get_edit_post_link() is empty. Route to its Sandbox management screen instead.
$emcp_mk_views = array( 'emcp_widget' => 'widgets', 'emcp_block' => 'blocks', 'emcp_php_snippet' => 'snippets' );
$emcp_mk_type  = $emcp_mk_id ? get_post_type( $emcp_mk_id ) : '';
$emcp_mk_edit  = isset( $emcp_mk_views[ $emcp_mk_type ] )
	? add_query_arg( array( 'page' => 'emcp-tools-widgets', 'view' => $emcp_mk_views[ $emcp_mk_type ] ), admin_url( 'admin.php' ) )
	: '';
$emcp_category = isset( $_GET['mk_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['mk_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$emcp_q        = isset( $_GET['mk_q'] ) ? sanitize_text_field( wp_unslash( $_GET['mk_q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$emcp_f_kind   = isset( $_GET['mk_kind'] ) ? sanitize_key( wp_unslash( $_GET['mk_kind'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$emcp_f_access = isset( $_GET['mk_access'] ) ? sanitize_key( wp_unslash( $_GET['mk_access'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$emcp_sort     = isset( $_GET['mk_sort'] ) ? sanitize_key( wp_unslash( $_GET['mk_sort'] ) ) : 'newest'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$emcp_page     = isset( $_GET['mk_page'] ) ? max( 1, absint( wp_unslash( $_GET['mk_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$emcp_per_page = 24;
?>
<div class="elementor-mcp-section">
	<h2><?php esc_html_e( 'Marketplace', 'emcp-tools' ); ?></h2>
	<p class="elementor-mcp-activate-note">
		<?php esc_html_e( 'Browse community and Pro blocks, widgets, snippets, and templates from EMCP Cloud and install them into this site. Installs land as a draft for you to review before publishing.', 'emcp-tools' ); ?>
	</p>

	<?php if ( 'installed' === $emcp_mk ) : ?>
		<div class="notice notice-success inline"><p>
			<?php esc_html_e( 'Installed as a draft.', 'emcp-tools' ); ?>
			<?php if ( $emcp_mk_edit ) : ?>
				<a href="<?php echo esc_url( $emcp_mk_edit ); ?>"><?php esc_html_e( 'Review it →', 'emcp-tools' ); ?></a>
			<?php endif; ?>
		</p></div>
	<?php elseif ( 'pro' === $emcp_mk ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'That item requires a paid EMCP Cloud plan. Upgrade your Cloud plan to install Pro items.', 'emcp-tools' ); ?></p></div>
	<?php elseif ( 'err' === $emcp_mk ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Could not install that item. Please try again.', 'emcp-tools' ); ?></p></div>
	<?php endif; ?>

	<?php
	if ( ! $emcp_connected ) :
		?>
		<div class="notice notice-info inline" style="margin-top:12px;">
			<p><?php esc_html_e( 'Connect this site to EMCP Cloud to browse and install marketplace items.', 'emcp-tools' ); ?></p>
			<?php if ( class_exists( 'EMCP_Tools_Cloud_Connect' ) ) :
				$emcp_connect_label = __( 'Connect to EMCP Cloud', 'emcp-tools' );
				require EMCP_TOOLS_DIR . 'includes/admin/views/partials/cloud-connect-form.php';
			else : ?>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=emcp-tools-connection' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Connect to EMCP Cloud', 'emcp-tools' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		return;
	endif;

	// Fetch one page, filtered + sorted server-side. Facets come back with the
	// response so the dropdowns always list every kind/category.
	$emcp_res = EMCP_Tools_Cloud_Sync::marketplace_list(
		array(
			'q'        => $emcp_q,
			'kind'     => $emcp_f_kind,
			'category' => $emcp_category,
			'access'   => $emcp_f_access,
			'sort'     => $emcp_sort,
			'page'     => $emcp_page,
			'per_page' => $emcp_per_page,
		)
	);
	$emcp_listings = ( ! is_wp_error( $emcp_res ) && isset( $emcp_res['listings'] ) && is_array( $emcp_res['listings'] ) ) ? $emcp_res['listings'] : array();

	// Facet options from the response (full distinct set, filter-agnostic).
	$emcp_facets = ( ! is_wp_error( $emcp_res ) && isset( $emcp_res['facets'] ) && is_array( $emcp_res['facets'] ) ) ? $emcp_res['facets'] : array();
	$emcp_kinds  = ( isset( $emcp_facets['kinds'] ) && is_array( $emcp_facets['kinds'] ) ) ? $emcp_facets['kinds'] : array();
	$emcp_cats   = ( isset( $emcp_facets['categories'] ) && is_array( $emcp_facets['categories'] ) ) ? $emcp_facets['categories'] : array();

	// Pagination meta (present only when the endpoint paginated the response).
	$emcp_total       = isset( $emcp_res['total'] ) ? (int) $emcp_res['total'] : count( $emcp_listings );
	$emcp_pages       = isset( $emcp_res['pages'] ) ? max( 1, (int) $emcp_res['pages'] ) : 1;
	$emcp_cur_page    = isset( $emcp_res['page'] ) ? max( 1, (int) $emcp_res['page'] ) : $emcp_page;
	$emcp_active_filters = ( '' !== $emcp_q ) + ( '' !== $emcp_f_kind ) + ( '' !== $emcp_category ) + ( '' !== $emcp_f_access );

	// Base URL that preserves the active filters, swapping only the page number.
	$emcp_page_href = static function ( $n ) use ( $emcp_q, $emcp_f_kind, $emcp_category, $emcp_f_access, $emcp_sort ) {
		$args = array( 'page' => 'emcp-tools-marketplace' );
		if ( '' !== $emcp_q ) {
			$args['mk_q'] = $emcp_q;
		}
		if ( '' !== $emcp_f_kind ) {
			$args['mk_kind'] = $emcp_f_kind;
		}
		if ( '' !== $emcp_category ) {
			$args['mk_cat'] = $emcp_category;
		}
		if ( '' !== $emcp_f_access ) {
			$args['mk_access'] = $emcp_f_access;
		}
		if ( 'newest' !== $emcp_sort && '' !== $emcp_sort ) {
			$args['mk_sort'] = $emcp_sort;
		}
		if ( (int) $n > 1 ) {
			$args['mk_page'] = (int) $n;
		}
		return admin_url( 'admin.php?' . http_build_query( $args ) );
	};
	?>

	<?php if ( is_wp_error( $emcp_res ) ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $emcp_res->get_error_message() ); ?></p></div>
	<?php elseif ( 0 === $emcp_total && 0 === (int) $emcp_active_filters ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'No published items yet. Check back soon.', 'emcp-tools' ); ?></p></div>
	<?php else : ?>

		<form method="get" class="emcp-mk-filters">
			<input type="hidden" name="page" value="emcp-tools-marketplace" />
			<input type="search" name="mk_q" value="<?php echo esc_attr( $emcp_q ); ?>" placeholder="<?php esc_attr_e( 'Search marketplace…', 'emcp-tools' ); ?>" class="emcp-mk-filters__search" />
			<select name="mk_kind">
				<option value=""><?php esc_html_e( 'All types', 'emcp-tools' ); ?></option>
				<?php foreach ( $emcp_kinds as $emcp_k ) : ?>
					<option value="<?php echo esc_attr( $emcp_k ); ?>" <?php selected( $emcp_f_kind, $emcp_k ); ?>><?php echo esc_html( $emcp_kind_label[ $emcp_k ] ?? $emcp_k ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! empty( $emcp_cats ) ) : ?>
				<select name="mk_cat">
					<option value=""><?php esc_html_e( 'All categories', 'emcp-tools' ); ?></option>
					<?php foreach ( $emcp_cats as $emcp_c ) : ?>
						<option value="<?php echo esc_attr( $emcp_c ); ?>" <?php selected( $emcp_category, $emcp_c ); ?>><?php echo esc_html( $emcp_c ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<select name="mk_access">
				<option value=""><?php esc_html_e( 'All access', 'emcp-tools' ); ?></option>
				<option value="community" <?php selected( $emcp_f_access, 'community' ); ?>><?php esc_html_e( 'Community', 'emcp-tools' ); ?></option>
				<option value="pro" <?php selected( $emcp_f_access, 'pro' ); ?>><?php esc_html_e( 'Pro', 'emcp-tools' ); ?></option>
			</select>
			<select name="mk_sort">
				<option value="newest" <?php selected( $emcp_sort, 'newest' ); ?>><?php esc_html_e( 'Newest', 'emcp-tools' ); ?></option>
				<option value="popular" <?php selected( $emcp_sort, 'popular' ); ?>><?php esc_html_e( 'Most installed', 'emcp-tools' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Apply', 'emcp-tools' ); ?></button>
			<?php if ( $emcp_active_filters > 0 ) : ?>
				<a class="button-link emcp-mk-filters__clear" href="<?php echo esc_url( admin_url( 'admin.php?page=emcp-tools-marketplace' ) ); ?>"><?php esc_html_e( 'Clear', 'emcp-tools' ); ?></a>
			<?php endif; ?>
		</form>

		<p class="emcp-mk-count">
			<?php
			echo esc_html( sprintf( _n( '%d result', '%d results', $emcp_total, 'emcp-tools' ), $emcp_total ) );
			if ( $emcp_pages > 1 ) {
				/* translators: 1: current page number, 2: total number of pages. */
				echo ' · ' . esc_html( sprintf( __( 'page %1$d of %2$d', 'emcp-tools' ), $emcp_cur_page, $emcp_pages ) );
			}
			?>
		</p>

		<?php if ( empty( $emcp_listings ) ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'Nothing matches those filters.', 'emcp-tools' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=emcp-tools-marketplace' ) ); ?>"><?php esc_html_e( 'Clear filters', 'emcp-tools' ); ?></a>
			</p></div>
		<?php else : ?>

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
				$emcp_author = ( isset( $emcp_l['author'] ) && is_array( $emcp_l['author'] ) ) ? $emcp_l['author'] : array();
				$emcp_a_name = (string) ( $emcp_author['name'] ?? '' );
				$emcp_a_av   = (string) ( $emcp_author['avatar'] ?? '' );
				$emcp_a_ver  = ! empty( $emcp_author['verified'] );
				$emcp_a_url  = (string) ( $emcp_author['profile_url'] ?? '' );
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
						<?php if ( '' !== $emcp_a_name ) : ?>
							<div class="emcp-mk-card__author">
								<?php if ( '' !== $emcp_a_av ) : ?>
									<img class="emcp-mk-card__av" src="<?php echo esc_url( $emcp_a_av ); ?>" alt="" loading="lazy" />
								<?php else : ?>
									<span class="emcp-mk-card__av emcp-mk-card__av--fb" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $emcp_a_name, 0, 1 ) ) ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $emcp_a_url ) : ?>
									<a class="emcp-mk-card__aname emcp-mk-card__aname--link" href="<?php echo esc_url( $emcp_a_url ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $emcp_a_name ); ?></a>
								<?php else : ?>
									<span class="emcp-mk-card__aname"><?php echo esc_html( $emcp_a_name ); ?></span>
								<?php endif; ?>
								<?php if ( $emcp_a_ver ) : ?>
									<img class="emcp-mk-card__ver" src="<?php echo esc_url( EMCP_TOOLS_URL . 'assets/img/pro.svg' ); ?>" alt="<?php esc_attr_e( 'Verified', 'emcp-tools' ); ?>" title="<?php esc_attr_e( 'Verified EMCP Pro member', 'emcp-tools' ); ?>" width="15" height="15" />
								<?php endif; ?>
							</div>
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

		<?php if ( $emcp_pages > 1 ) : ?>
			<nav class="emcp-mk-pager" aria-label="<?php esc_attr_e( 'Pagination', 'emcp-tools' ); ?>">
				<?php if ( $emcp_cur_page > 1 ) : ?>
					<a class="button emcp-mk-pager__edge" href="<?php echo esc_url( $emcp_page_href( $emcp_cur_page - 1 ) ); ?>">&larr; <?php esc_html_e( 'Prev', 'emcp-tools' ); ?></a>
				<?php else : ?>
					<span class="button disabled emcp-mk-pager__edge">&larr; <?php esc_html_e( 'Prev', 'emcp-tools' ); ?></span>
				<?php endif; ?>

				<?php
				// Windowed page numbers: first, last, current ±1, ellipses.
				$emcp_nums = array();
				if ( $emcp_pages <= 7 ) {
					for ( $i = 1; $i <= $emcp_pages; $i++ ) {
						$emcp_nums[] = $i;
					}
				} else {
					$emcp_nums[] = 1;
					$emcp_lo     = max( 2, $emcp_cur_page - 1 );
					$emcp_hi     = min( $emcp_pages - 1, $emcp_cur_page + 1 );
					if ( $emcp_lo > 2 ) {
						$emcp_nums[] = 0;
					}
					for ( $i = $emcp_lo; $i <= $emcp_hi; $i++ ) {
						$emcp_nums[] = $i;
					}
					if ( $emcp_hi < $emcp_pages - 1 ) {
						$emcp_nums[] = 0;
					}
					$emcp_nums[] = $emcp_pages;
				}
				foreach ( $emcp_nums as $emcp_n ) :
					if ( 0 === $emcp_n ) :
						?>
						<span class="emcp-mk-pager__gap" aria-hidden="true">…</span>
						<?php
					elseif ( $emcp_n === $emcp_cur_page ) :
						?>
						<span class="button button-primary emcp-mk-pager__num" aria-current="page"><?php echo esc_html( (string) $emcp_n ); ?></span>
						<?php
					else :
						?>
						<a class="button emcp-mk-pager__num" href="<?php echo esc_url( $emcp_page_href( $emcp_n ) ); ?>"><?php echo esc_html( (string) $emcp_n ); ?></a>
						<?php
					endif;
				endforeach;
				?>

				<?php if ( $emcp_cur_page < $emcp_pages ) : ?>
					<a class="button emcp-mk-pager__edge" href="<?php echo esc_url( $emcp_page_href( $emcp_cur_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'emcp-tools' ); ?> &rarr;</a>
				<?php else : ?>
					<span class="button disabled emcp-mk-pager__edge"><?php esc_html_e( 'Next', 'emcp-tools' ); ?> &rarr;</span>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>

<style>
	.emcp-mk-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 16px 0 4px; }
	.emcp-mk-filters__search { min-width: 220px; flex: 1 1 220px; max-width: 340px; }
	.emcp-mk-filters__clear { margin-left: 2px; color: #b32d2e; text-decoration: none; }
	.emcp-mk-count { font-size: 12px; color: #787c82; margin: 8px 0 0; }
	.emcp-mk-grid { display: grid; grid-template-columns: repeat( auto-fill, minmax( 240px, 1fr ) ); gap: 16px; margin-top: 12px; }
	.emcp-mk-card { display: flex; flex-direction: column; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; overflow: hidden; }
	/* Artifact screenshots are 1200x630 (1.91:1, OG ratio) — show the full image, uncropped. */
	.emcp-mk-card__shot { width: 100%; aspect-ratio: 1200 / 630; object-fit: contain; display: block; border-bottom: 1px solid #f0f0f1; background: #f6f7f7; }
	.emcp-mk-card__shot--empty { display: grid; place-items: center; font-size: 34px; font-weight: 700; color: #c3c4c7; }
	.emcp-mk-card__body { padding: 14px 16px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
	.emcp-mk-card__k { display: flex; align-items: center; gap: 8px; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #787c82; }
	.emcp-mk-card__pro { font-weight: 700; color: #4338ca; background: #eef0ff; padding: 1px 7px; border-radius: 999px; }
	.emcp-mk-card__title { font-size: 15px; margin: 2px 0 0; }
	.emcp-mk-card__sum { font-size: 13px; color: #50575e; margin: 0; flex: 1; }
	.emcp-mk-card__author { display: flex; align-items: center; gap: 7px; margin-top: 8px; }
	.emcp-mk-card__av { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; flex: none; border: 1px solid #e0e0e6; }
	.emcp-mk-card__av--fb { display: grid; place-items: center; font-size: 11px; font-weight: 700; color: #fff; background: #4338ca; }
	.emcp-mk-card__aname { font-size: 12.5px; font-weight: 600; color: #3c434a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	a.emcp-mk-card__aname--link { text-decoration: none; }
	a.emcp-mk-card__aname--link:hover { color: #2271b1; text-decoration: underline; }
	.emcp-mk-card__ver { flex: none; width: 15px; height: 15px; display: block; }
	.emcp-mk-card__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 12px; color: #787c82; margin-top: 4px; }
	.emcp-mk-card__cta { margin-top: 8px; }
	.emcp-mk-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 6px; margin: 24px 0 4px; }
	.emcp-mk-pager__num { min-width: 34px; text-align: center; justify-content: center; }
	.emcp-mk-pager__gap { padding: 0 4px; color: #787c82; }
	.emcp-mk-pager .button.disabled { pointer-events: none; opacity: .5; }
</style>
