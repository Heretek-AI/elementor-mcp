<?php
/**
 * The admin pager, shared by every paged list screen.
 *
 * This started as sixty lines inline in the Marketplace view. The Sandbox
 * screens (Blocks / Widgets / PHP Snippets) needed the same control, so the
 * window maths and the markup moved here rather than being copied three more
 * times. The Marketplace now renders through this class too, so there is one
 * pager to style and one place where its behaviour is defined.
 *
 * `window()` is pure on purpose: which numbers appear around the current page
 * is the only part with logic worth pinning in a test.
 *
 * @package EMCP_Tools
 * @since   3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Windowed pagination control for admin list screens.
 *
 * @since 3.15.0
 */
class EMCP_Tools_Admin_Pager {

	/**
	 * Page counts up to this many are shown in full, with no ellipsis.
	 *
	 * @since 3.15.0
	 * @var int
	 */
	const FULL_RUN = 7;

	/**
	 * Marks an ellipsis in a `window()` result. Zero is never a page number,
	 * so it cannot collide with one.
	 *
	 * @since 3.15.0
	 * @var int
	 */
	const GAP = 0;

	/**
	 * Reads the requested page number from the query string.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Query argument holding the page number.
	 * @return int 1 or higher.
	 */
	public static function current( string $key = 'paged' ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination, no state change.
		$raw = isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : '';
		return max( 1, absint( is_scalar( $raw ) ? $raw : 0 ) );
	}

	/**
	 * The page numbers to show, with self::GAP standing in for an ellipsis.
	 *
	 * Short runs are listed in full. Longer ones keep the first page, the last
	 * page, and the current page with a neighbour either side, so the control
	 * stays a fixed width however many pages there are.
	 *
	 * @since 3.15.0
	 *
	 * @param int $current Current page (1-based).
	 * @param int $pages   Total pages.
	 * @return int[] Page numbers, with self::GAP for each ellipsis.
	 */
	public static function window( int $current, int $pages ): array {
		$pages   = max( 1, $pages );
		$current = min( max( 1, $current ), $pages );

		if ( $pages <= self::FULL_RUN ) {
			return range( 1, $pages );
		}

		// The pages worth keeping: both ends, and the current one with a
		// neighbour either side.
		$keep = array( 1, $pages );
		for ( $n = $current - 1; $n <= $current + 1; $n++ ) {
			if ( $n >= 1 && $n <= $pages ) {
				$keep[] = $n;
			}
		}
		$keep = array_unique( $keep );
		sort( $keep );

		// Close the holes between them. A hole of exactly one page becomes that
		// page: an ellipsis is wider than the single number it would hide.
		$out = array();
		foreach ( $keep as $i => $n ) {
			if ( $i > 0 ) {
				$hole = $n - $keep[ $i - 1 ];
				if ( 2 === $hole ) {
					$out[] = $n - 1;
				} elseif ( $hole > 2 ) {
					$out[] = self::GAP;
				}
			}
			$out[] = $n;
		}

		return $out;
	}

	/**
	 * Renders the pager, or an empty string when there is only one page.
	 *
	 * @since 3.15.0
	 *
	 * @param int      $current Current page (1-based).
	 * @param int      $pages   Total pages.
	 * @param callable $href    Given a page number, returns its URL.
	 * @return string Escaped HTML.
	 */
	public static function render( int $current, int $pages, callable $href ): string {
		$pages = max( 1, $pages );
		if ( $pages < 2 ) {
			return '';
		}
		$current = min( max( 1, $current ), $pages );

		$out  = '<nav class="emcp-pager" aria-label="' . esc_attr__( 'Pagination', 'emcp-tools' ) . '">';
		$out .= self::edge( $current > 1, $href, $current - 1, '&larr; ' . esc_html__( 'Prev', 'emcp-tools' ) );

		foreach ( self::window( $current, $pages ) as $n ) {
			if ( self::GAP === $n ) {
				$out .= '<span class="emcp-pager__gap" aria-hidden="true">&hellip;</span>';
			} elseif ( $n === $current ) {
				$out .= '<span class="button button-primary emcp-pager__num" aria-current="page">' . esc_html( (string) $n ) . '</span>';
			} else {
				$out .= '<a class="button emcp-pager__num" href="' . esc_url( (string) $href( $n ) ) . '">' . esc_html( (string) $n ) . '</a>';
			}
		}

		$out .= self::edge( $current < $pages, $href, $current + 1, esc_html__( 'Next', 'emcp-tools' ) . ' &rarr;' );
		$out .= '</nav>';

		return $out;
	}

	/**
	 * A Prev/Next control: a link when there is somewhere to go, otherwise a
	 * disabled placeholder so the control does not change width at the ends.
	 *
	 * @since 3.15.0
	 *
	 * @param bool     $enabled Whether the target page exists.
	 * @param callable $href    Given a page number, returns its URL.
	 * @param int      $target  Page to link to.
	 * @param string   $label   Pre-escaped label.
	 * @return string
	 */
	private static function edge( bool $enabled, callable $href, int $target, string $label ): string {
		if ( ! $enabled ) {
			return '<span class="button disabled emcp-pager__edge">' . $label . '</span>';
		}
		return '<a class="button emcp-pager__edge" href="' . esc_url( (string) $href( $target ) ) . '">' . $label . '</a>';
	}

	/**
	 * The "Showing 1-20 of 57" line that sits above a paged table.
	 *
	 * Returns a bare count when everything fits on one page: a range reading
	 * "1-4 of 4" is noise.
	 *
	 * @since 3.15.0
	 *
	 * @param int $page     Current page (1-based).
	 * @param int $per_page Rows per page.
	 * @param int $shown    Rows on this page.
	 * @param int $total    Rows in total.
	 * @return string Escaped text.
	 */
	public static function summary( int $page, int $per_page, int $shown, int $total ): string {
		if ( $total <= 0 ) {
			return '';
		}
		if ( $shown >= $total ) {
			/* translators: %d: number of items. */
			return esc_html( sprintf( _n( '%d item', '%d items', $total, 'emcp-tools' ), $total ) );
		}

		$first = ( max( 1, $page ) - 1 ) * max( 1, $per_page ) + 1;

		return esc_html(
			sprintf(
				/* translators: 1: first item number, 2: last item number, 3: total items. */
				__( 'Showing %1$d-%2$d of %3$d', 'emcp-tools' ),
				$first,
				$first + $shown - 1,
				$total
			)
		);
	}
}
