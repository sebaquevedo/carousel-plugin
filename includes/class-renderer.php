<?php
/**
 * Carousel renderer: escaped product and category cards inside Swiper markup.
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders the carousel HTML from the resolved config and item set.
 *
 * Dispatch is config-driven: `type=product` renders WC_Product cards (image +
 * category line + gated Buy button), `type=category` renders WP_Term cards
 * (category image + name, whole card linking to the archive). Every value is
 * read from the product/term itself, never from the global $post. The static
 * $rendered flag lets CWC_Assets late-enqueue frontend assets only when a
 * carousel actually rendered (CR-2).
 *
 * @since 0.1.0
 */
class CWC_Renderer {

	/**
	 * Whether a carousel has rendered on this request.
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	public static $rendered = false;

	/**
	 * Renders the carousel markup.
	 *
	 * Returns an empty string for an empty item set — no carousel markup and
	 * no render flag, so no assets are enqueued (CR-2). The escaped title
	 * heading renders above the carousel only when non-empty (CR-4). The
	 * resolved config is emitted once, as an escaped JSON data attribute, so
	 * each instance carries its own Swiper options (CR-7).
	 *
	 * @since 0.1.0
	 *
	 * @param array                  $config Resolved carousel configuration.
	 * @param WC_Product[]|WP_Term[] $items Products or category terms to display.
	 * @return string Escaped carousel HTML, or an empty string.
	 */
	public function render( array $config, array $items ): string {
		if ( empty( $items ) ) {
			return '';
		}

		self::$rendered = true;

		$output = '';

		if ( ! empty( $config['title'] ) ) {
			$output .= '<h2 class="cwc-carousel__title">' . esc_html( $config['title'] ) . '</h2>';
		}

		$output .= '<div class="cwc-carousel swiper" data-cwc-config="' . esc_attr( wp_json_encode( $config ) ) . '">';
		$output .= '<div class="swiper-wrapper">';

		if ( 'category' === $config['type'] ) {
			foreach ( $items as $term ) {
				if ( $term instanceof WP_Term ) {
					$output .= $this->render_category_card( $term );
				}
			}
		} else {
			foreach ( $items as $product ) {
				if ( $product instanceof WC_Product ) {
					$output .= $this->render_card( $product, $config );
				}
			}
		}

		$output .= '</div>';

		$output .= '<div class="swiper-pagination"></div>';
		$output .= '<div class="swiper-button-prev"></div>';
		$output .= '<div class="swiper-button-next"></div>';

		$output .= '</div>';

		return $output;
	}

	/**
	 * Renders a single product card.
	 *
	 * Handles the WooCommerce thumbnail via the product's own image (escaped by
	 * WC), a safe placeholder otherwise. Adds the shallow category term line
	 * and the gated Buy button when the card's config and availability allow.
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Product $product Product to render.
	 * @param array      $config  Resolved carousel configuration.
	 * @return string Escaped card HTML.
	 */
	private function render_card( WC_Product $product, array $config ): string {
		if ( has_post_thumbnail( $product->get_id() ) ) {
			$image = $product->get_image(
				'woocommerce_thumbnail',
				array( 'class' => 'cwc-card__image' )
			);
		} else {
			$image = '<span class="cwc-card__image cwc-card__image--placeholder" aria-hidden="true"></span>';
		}

		return sprintf(
			'<div class="swiper-slide cwc-card">'
			. '<a class="cwc-card__link" href="%1$s">'
			. '%2$s'
			. '<h3 class="cwc-card__title">%3$s</h3>'
			. '</a>'
			. '%4$s'
			. '<div class="cwc-card__price">%5$s</div>'
			. '%6$s'
			. '</div>',
			esc_url( $product->get_permalink() ),
			$image,
			esc_html( $product->get_name() ),
			$this->render_category_line( $product ),
			$product->get_price_html(),
			$this->render_buy( $product, $config )
		);
	}

	/**
	 * Renders the product's primary category line.
	 *
	 * Uses a shallow look at the first assigned product_cat term (escaped), or
	 * nothing when the product has no category.
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Product $product Product to render category for.
	 * @return string Escaped category line HTML, or an empty string.
	 */
	private function render_category_line( WC_Product $product ): string {
		$term_ids = wc_get_product_term_ids( $product->get_id(), 'product_cat' );

		if ( empty( $term_ids ) ) {
			return '';
		}

		$term = get_term( (int) $term_ids[0], 'product_cat' );

		if ( ! $term instanceof WP_Term || '' === $term->name ) {
			return '';
		}

		return '<div class="cwc-card__category-line">' . esc_html( $term->name ) . '</div>';
	}

	/**
	 * Renders the gated Buy link for a product card.
	 *
	 * Outputs a Buy link (styled as a button) to the product page only when
	 * the instance enables buying AND the product is purchasable AND available
	 * (in stock or backorders allowed). Any other state yields no button while
	 * the rest of the card still renders (CR-6).
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Product $product Product to gate the Buy button for.
	 * @param array      $config  Resolved carousel configuration.
	 * @return string Escaped Buy link HTML, or an empty string.
	 */
	private function render_buy( WC_Product $product, array $config ): string {
		if ( empty( $config['buy'] ) ) {
			return '';
		}

		if ( ! $product->is_purchasable() || ( ! $product->is_in_stock() && ! $product->backorders_allowed() ) ) {
			return '';
		}

		$label = isset( $config['buy_text'] ) ? $config['buy_text'] : 'Comprar';

		return '<a class="cwc-card__buy" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Renders a single category card.
	 *
	 * Uses the per-category custom upload (term meta `cwc_cat_image`) when
	 * present, falling back to the WooCommerce category thumbnail; a safe
	 * placeholder renders when neither exists (CR-5). The whole card links to
	 * the category archive via get_term_link().
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Term $term Category term to render.
	 * @return string Escaped category card HTML, or an empty string.
	 */
	private function render_category_card( WP_Term $term ): string {
		$image_id = (int) get_term_meta( $term->term_id, 'cwc_cat_image', true );

		if ( $image_id <= 0 ) {
			$image_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		}

		if ( $image_id > 0 ) {
			$image = wp_get_attachment_image(
				$image_id,
				'woocommerce_thumbnail',
				false,
				array( 'class' => 'cwc-category-card__image' )
			);
		} else {
			$image = '';
		}

		// A deleted/missing attachment yields an empty string from
		// wp_get_attachment_image(); fall back to the placeholder so the card
		// never renders an empty <a> region (CR-5).
		if ( '' === trim( (string) $image ) ) {
			$image = '<span class="cwc-category-card__image cwc-category-card__image--placeholder" aria-hidden="true"></span>';
		}

		$link = get_term_link( $term, 'product_cat' );

		if ( is_wp_error( $link ) ) {
			return '';
		}

		return sprintf(
			'<div class="swiper-slide cwc-category-card">'
			. '<a class="cwc-category-card__link" href="%1$s">'
			. '%2$s'
			. '<h3 class="cwc-card__title">%3$s</h3>'
			. '</a>'
			. '</div>',
			esc_url( $link ),
			$image,
			esc_html( $term->name )
		);
	}
}
