<?php
/**
 * Product query builder: pure WooCommerce data access for carousels.
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Builds and executes product queries for the carousel shortcode.
 *
 * Side-effect-free wrapper around WC_Product_Query (via wc_get_products()):
 * no raw SQL, no options/transients/log output, and a filter seam that later
 * slices can extend with category/tag/on-sale/ordering arguments (PQ-3).
 *
 * @since 0.1.0
 */
class CWC_Query {

	/**
	 * Runs a product query.
	 *
	 * Defaults to recent published products (orderby date, descending) when no
	 * manual IDs are supplied (PQ-1). When an `ids` argument is present, the
	 * list is parsed with wp_parse_id_list(), sanitized with absint(), values
	 * <= 0 are dropped, and the requested order is preserved via
	 * orderby=post__in (PQ-2). An explicit but fully-invalid ID list yields an
	 * empty result — the recent-products default is NOT substituted
	 * (PQ-2/SC-3). A limit of zero returns an empty result before any query
	 * runs (PQ-1, D7).
	 *
	 * @since 0.1.0
	 *
	 * @param array $args {
	 *     Optional query arguments.
	 *
	 *     @type int|string|array $ids   Comma/whitespace-separated product IDs
	 *                                   or an int[]; absent/null means "recent
	 *                                   products".
	 *     @type int              $limit Maximum number of products (0 yields
	 *                                   an empty result).
	 * }
	 * @return WC_Product[]
	 */
	public function query( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'limit' => 8,
				'ids'   => null,
			)
		);

		$limit = absint( $args['limit'] );

		if ( 0 === $limit ) {
			return array();
		}

		$query_args = array(
			'limit'   => $limit,
			'status'  => 'publish',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		if ( null !== $args['ids'] ) {
			$ids = $args['ids'];

			if ( ! is_array( $ids ) ) {
				$ids = wp_parse_id_list( (string) $ids );
			}

			$ids = array_map( 'absint', $ids );
			$ids = array_values(
				array_filter(
					$ids,
					static function ( $product_id ) {
						return $product_id > 0;
					}
				)
			);

			if ( empty( $ids ) ) {
				return array();
			}

			$query_args['include'] = $ids;
			$query_args['orderby'] = 'post__in';
		}

		/**
		 * Filters the query arguments passed to wc_get_products().
		 *
		 * @since 0.1.0
		 *
		 * @param array $query_args Arguments for wc_get_products().
		 */
		$query_args = apply_filters( 'cwc_carousel_query_args', $query_args );

		return wc_get_products( $query_args );
	}
}
