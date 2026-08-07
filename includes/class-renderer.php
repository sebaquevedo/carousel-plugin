<?php
/**
 * Carousel renderer: escaped product cards inside Swiper markup.
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders the carousel HTML from WC_Product objects.
 *
 * Every per-product value is read from the product itself, never from the
 * global $post (which is the page hosting the shortcode, so all cards would
 * otherwise be identical). The static $rendered flag lets CWC_Assets
 * late-enqueue frontend assets only when a carousel actually rendered (CR-2).
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
	 * Returns an empty string for an empty product set — no carousel markup
	 * and no render flag, so no assets are enqueued (CR-2). The escaped title
	 * heading is rendered above the carousel only when non-empty (CR-4, D5).
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Product[] $products Products to display.
	 * @param string       $title    Optional carousel title (escaped once here).
	 * @return string Escaped carousel HTML, or an empty string.
	 */
	public function render( array $products, string $title = '' ): string {
		if ( empty( $products ) ) {
			return '';
		}

		self::$rendered = true;

		$output = '';

		if ( '' !== $title ) {
			$output .= '<h2 class="cwc-carousel__title">' . esc_html( $title ) . '</h2>';
		}

		$output .= '<div class="cwc-carousel swiper">';
		$output .= '<div class="swiper-wrapper">';

		foreach ( $products as $product ) {
			if ( $product instanceof WC_Product ) {
				$output .= $this->render_card( $product );
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
	 * @since 0.1.0
	 *
	 * @param WC_Product $product Product to render.
	 * @return string Escaped card HTML.
	 */
	private function render_card( WC_Product $product ): string {
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
			. '<div class="cwc-card__price">%4$s</div>'
			. '</div>',
			esc_url( $product->get_permalink() ),
			$image,
			esc_html( $product->get_name() ),
			$product->get_price_html()
		);
	}
}
