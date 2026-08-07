<?php
/**
 * Shortcode wiring: [cwc_carousel] -> CWC_Query -> CWC_Renderer.
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers and renders the [cwc_carousel] shortcode.
 *
 * Routes default attributes through the query module and then the renderer
 * (SC-1). Unknown attributes are dropped by shortcode_atts(); the sanitized
 * title is passed to the renderer, which performs the single output escape so
 * the block render path never double-escapes (SC-2, D5).
 *
 * @since 0.1.0
 */
class CWC_Shortcode {

	/**
	 * Registers the shortcode.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_shortcode( 'cwc_carousel', array( $this, 'render' ) );
	}

	/**
	 * Renders the carousel for the shortcode.
	 *
	 * Sanitizes the shortcode attributes, queries products through CWC_Query,
	 * and renders through CWC_Renderer. Always returns a string — never
	 * echoes (SC-1). Manual IDs override the recent-products default only
	 * when the attribute is present (SC-3).
	 *
	 * @since 0.1.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Escaped carousel HTML, or an empty string.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title' => '',
				'count' => 8,
				'ids'   => '',
			),
			$atts,
			'cwc_carousel'
		);

		$title = sanitize_text_field( $atts['title'] );
		$count = absint( $atts['count'] );

		$query_args = array(
			'limit' => $count,
		);

		if ( '' !== $atts['ids'] ) {
			$query_args['ids'] = $this->sanitize_ids( $atts['ids'] );
		}

		$query    = new CWC_Query();
		$products = $query->query( $query_args );

		$renderer = new CWC_Renderer();
		return $renderer->render( $products, $title );
	}

	/**
	 * Sanitizes a raw comma/whitespace-separated product ID list.
	 *
	 * Matches the CWC_Query rules (PQ-2): wp_parse_id_list() first, then
	 * absint(), dropping values <= 0 and re-indexing the result.
	 *
	 * @since 0.1.0
	 *
	 * @param string $raw_ids Raw ID list from the shortcode attribute.
	 * @return int[] Sanitized product IDs (may be empty when all invalid).
	 */
	private function sanitize_ids( string $raw_ids ): array {
		$ids = array_map( 'absint', wp_parse_id_list( $raw_ids ) );

		return array_values(
			array_filter(
				$ids,
				static function ( $product_id ) {
					return $product_id > 0;
				}
			)
		);
	}
}
