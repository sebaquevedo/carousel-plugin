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
 * `type=category` fetches direct child terms via
 * CWC_Query::get_child_categories() when `subcategories` is set, otherwise
 * the explicit categories via CWC_Query::get_categories(); other types fetch
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
	 * dispatches by type. `name` selects a registered instance as the merge
	 * base (absent → reserved `default`, SC-8) and `slides_tablet`,
	 * `slides_mobile` and `gap` are whitelisted so per-instance overrides
	 * reach resolve() instead of being dropped (SC-9). `cover`,
	 * `subcategories` and `title_align` are likewise whitelisted so cover-mode
	 * attributes reach resolve(); an empty string (missing att) falls through
	 * to the instance/builtin default, and coercion lives in CWC_Settings
	 * (SC-10, CM-11). The legacy `ids`
	 * attribute is kept for backwards compatibility: when present it drives
	 * the manual-ID product query path instead of the resolved selection
	 * (PQ-2). The category branch dispatches on `subcategories`: when true it
	 * lists the direct children of the parent `category` term via
	 * CWC_Query::get_child_categories() (taking precedence over the explicit
	 * `categories` selection, D3); otherwise the explicit `categories`/`category`
	 * selection is used as today (PQ-6). Always returns a string — never echoes
	 * (SC-1).
	 *
	 * @since 0.1.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Escaped carousel HTML, or an empty string.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'name'          => '',
				'type'          => '',
				'title'         => '',
				'category'      => '',
				'categories'    => '',
				'mix'           => '',
				'count'         => '',
				'slides'        => '',
				'slides_tablet' => '',
				'slides_mobile' => '',
				'gap'           => '',
				'arrows'        => '',
				'pagination'    => '',
				'buy'           => '',
				'buy_text'      => '',
				'cover'         => '',
				'subcategories' => '',
				'title_align'   => '',
				'ids'           => '',
			),
			$atts,
			'cwc_carousel'
		);

		$settings = new CWC_Settings();
		$config   = $settings->resolve( $atts );

		$query    = new CWC_Query();
		$renderer = new CWC_Renderer();

		if ( 'category' === $config['type'] ) {
			$items = ! empty( $config['subcategories'] )
				? $query->get_child_categories( $config['category'] )
				: $query->get_categories( $config['categories'] );
		} else {
			if ( '' !== $atts['ids'] ) {
				$query_args = array(
					'ids' => $settings->sanitize_ids( $atts['ids'] ),
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
}
