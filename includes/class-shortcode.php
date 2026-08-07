<?php
/**
 * Shortcode wiring: [cwc_carousel] -> CWC_Settings -> CWC_Query -> CWC_Renderer.
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
 * Whitelists the configurable attributes via shortcode_atts() (unknown
 * attributes are dropped), resolves one per-instance config through
 * CWC_Settings::resolve() (SC-4, SC-5), and dispatches to the query layer:
 * `type=category` fetches terms via CWC_Query::get_categories(), otherwise
 * products via CWC_Query::query(). The renderer performs the single output
 * escape so the block render path never double-escapes (SC-2, D5).
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
	 * Resolves the per-instance config from the whitelisted attributes and
	 * dispatches by type. The legacy `ids` attribute is kept for backwards
	 * compatibility: when present it drives the manual-ID product query path
	 * instead of the resolved selection (PQ-2). Always returns a string —
	 * never echoes (SC-1).
	 *
	 * @since 0.1.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Escaped carousel HTML, or an empty string.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'type'       => '',
				'title'      => '',
				'category'   => '',
				'categories' => '',
				'mix'        => '',
				'count'      => '',
				'slides'     => '',
				'buy'        => '',
				'buy_text'   => '',
				'ids'        => '',
			),
			$atts,
			'cwc_carousel'
		);

		$settings = new CWC_Settings();
		$config   = $settings->resolve( $atts );

		$query    = new CWC_Query();
		$renderer = new CWC_Renderer();

		if ( 'category' === $config['type'] ) {
			$items = $query->get_categories( $config['categories'] );
		} else {
			if ( '' !== $atts['ids'] ) {
				$query_args = array(
					'ids' => $this->sanitize_ids( $atts['ids'] ),
				);
			} else {
				$query_args = array(
					'limit'      => $config['count'],
					'category'   => $config['category'],
					'categories' => $config['categories'],
					'mix'        => $config['mix'],
				);
			}

			$items = $query->query( $query_args );
		}

		return $renderer->render( $config, $items );
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
