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
	 *     @type int|string|array $ids        Comma/whitespace-separated product
	 *                                        IDs or an int[]; absent/null means
	 *                                        "recent products".
	 *     @type int              $limit      Maximum number of products (0 yields
	 *                                        an empty result).
	 *     @type int              $category   Single product_cat term id (scoped
	 *                                        primary mode, PQ-4).
	 *     @type int[]            $categories product_cat term ids for mix mode
	 *                                        (PQ-5).
	 *     @type bool             $mix        When true, filter by any of
	 *                                        $categories (operator IN).
	 * }
	 * @return WC_Product[]
	 */
	public function query( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'limit'      => 8,
				'ids'        => null,
				'category'   => 0,
				'categories' => array(),
				'mix'        => false,
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

		$category_filter = $this->category_filter( $args );

		if ( ! empty( $category_filter ) ) {
			$query_args['tax_query'] = $category_filter; // phpcs:ignore WordPress.DB.SlowDBQuery
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

	/**
	 * Builds the product_cat tax_query from the selection arguments.
	 *
	 * Mix mode (PQ-5) takes precedence: when mix is enabled and $categories
	 * sanitizes to one or more positive term ids, the filter matches products
	 * in ANY of those terms via a single tax_query with the default IN
	 * operator. Otherwise a single positive $category id scopes the carousel
	 * to that one term (PQ-4). An empty sanitized selection yields no filter.
	 * No raw SQL is used in either mode.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args Query arguments (category, categories, mix).
	 * @return array[] tax_query array (possibly empty).
	 */
	private function category_filter( array $args ): array {
		$categories = array_values(
			array_map(
				'absint',
				(array) $args['categories']
			)
		);
		$categories = array_values(
			array_filter(
				$categories,
				static function ( $term_id ) {
					return $term_id > 0;
				}
			)
		);

		$category = absint( $args['category'] );

		if ( $args['mix'] && ! empty( $categories ) ) {
			return array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $categories,
				),
			);
		}

		if ( $category > 0 ) {
			return array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => array( $category ),
				),
			);
		}

		return array();
	}

	/**
	 * Fetches product category terms for a category carousel.
	 *
	 * Keeps category-term data access in the query layer (D8). Terms are
	 * fetched in the exact order of the supplied ids via orderby=include and
	 * hidden terms are excluded, mirroring how the category carousel lists its
	 * explicitly chosen categories.
	 *
	 * @since 0.1.0
	 *
	 * @param int[] $term_ids Sanitized positive product_cat term ids.
	 * @return WP_Term[] Ordered category terms (may be empty).
	 */
	public function get_categories( array $term_ids = array() ): array {
		$term_ids = array_values(
			array_filter(
				array_map( 'absint', $term_ids ),
				static function ( $term_id ) {
					return $term_id > 0;
				}
			)
		);

		if ( empty( $term_ids ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $term_ids,
				'hide_empty' => true,
				'orderby'    => 'include',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}
}
