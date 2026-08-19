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
	 * each instance carries its own Swiper options (CR-7) — the resolved
	 * contract always carries the 13 extended keys (CM-9), so every instance
	 * exposes them (CR-11). The wrapper gains a nav-position modifier class
	 * only when the position differs from the default bottom-right, and a style
	 * attribute listing one CSS custom property per non-empty nav color
	 * (CR-11, D6); legacy markup therefore stays byte-identical in wrapper
	 * class and style.
	 *
	 * The two side modes extend the corners (CR-11 amendment, D6 amendment):
	 * `sides-inside` is the corner path — the container carries the
	 * `cwc-carousel--nav-sides-inside` class and the color variables, and the
	 * arrows stay inside `.swiper` where Swiper places them absolutely
	 * (carousel.css centers them vertically). `sides-outside` wraps the whole
	 * carousel in a `.cwc-carousel-shell` flex wrapper: the prev/next buttons
	 * render as flanking SIBLINGS of the slider, the shell carries the nav
	 * class and the color variables, and the inner `.swiper` keeps the base
	 * classes plus `data-cwc-config` (so frontend.js still selects
	 * `.cwc-carousel.swiper` and reads the config from the slider itself).
	 * The shell only renders when the position is `sides-outside` AND arrows
	 * are enabled — without buttons there is nothing to flank, so the corner
	 * path (class only) applies instead. Default + the four corners emit
	 * today's markup byte-for-byte.
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
			$title_class = 'cwc-carousel__title';

			if ( 'center' === $config['title_align'] ) {
				$title_class .= ' cwc-carousel__title--center';
			} elseif ( 'right' === $config['title_align'] ) {
				$title_class .= ' cwc-carousel__title--right';
			}

			$output .= '<h2 class="' . esc_attr( $title_class ) . '">' . esc_html( $config['title'] ) . '</h2>';
		}

		// The shell only makes sense when there are buttons to flank the
		// slider with: `sides-outside` with arrows off falls back to the
		// corner path (class on the container, no shell) so no dead wrapper
		// markup is emitted (CR-11 amendment).
		$is_sides_outside = 'sides-outside' === $config['nav_position'] && ! empty( $config['arrows'] );

		$container_class = 'cwc-carousel swiper';

		if ( 'category' === $config['type'] && ! empty( $config['cover'] ) ) {
			$container_class .= ' cwc-carousel--cover';
		}

		// Nav-position modifier class (CR-11, D6): emitted only when the
		// position differs from the default bottom-right, so the default emits
		// today's class-less markup (BC). resolve()/normalize() guarantee the
		// position whitelist (CM-13); the empty() guard also keeps a raw legacy
		// config from emitting a stray class or a PHP notice. For
		// sides-outside the class travels on the SHELL, not on the inner
		// slider — the slider keeps its base classes so frontend.js selects it
		// exactly as today (`.cwc-carousel.swiper`, no double init).
		$nav_class = '';

		if ( ! empty( $config['nav_position'] ) && 'bottom-right' !== $config['nav_position'] ) {
			$nav_class = ' cwc-carousel--nav-' . $config['nav_position'];
		}

		if ( ! $is_sides_outside ) {
			$container_class .= $nav_class;
		}

		// Nav-color CSS variables (CR-11, D6): one custom property per
		// non-empty key, iterated over the shared nav_color_keys() list so the
		// emitted set can never drift from normalize()'s coercion set (D2).
		// Empty colors emit no variable — carousel.css falls back to the theme
		// default (FA-3). The whole style attribute is escaped once at the
		// single output point below. For sides-outside the variables move to
		// the shell so the flanking buttons (siblings of `.swiper`, not
		// descendants) still inherit them.
		$nav_colors = array();
		$settings   = new CWC_Settings();

		foreach ( $settings->nav_color_keys() as $color_key ) {
			if ( empty( $config[ $color_key ] ) ) {
				continue;
			}

			// CR-11 variable names are kebab-case: strip the key prefix and
			// fold the remaining underscore (border_hover -> border-hover).
			$suffix       = str_replace( array( 'nav_color_', '_' ), array( '', '-' ), $color_key );
			$nav_colors[] = '--cwc-nav-color-' . $suffix . ':' . $config[ $color_key ];
		}

		$style_attr = empty( $nav_colors )
			? ''
			: ' style="' . esc_attr( implode( ';', $nav_colors ) . ';' ) . '"';

		// The shell's class is escaped as one unit with the nav modifier; the
		// inner slider keeps the base classes and carries `data-cwc-config`
		// (frontend.js reads the config from the slider, CR-7).
		if ( $is_sides_outside ) {
			$output .= '<div class="cwc-carousel-shell' . esc_attr( $nav_class ) . '"' . $style_attr . '>';
			$output .= '<div class="swiper-button-prev"></div>';
		}

		$output .= '<div class="' . esc_attr( $container_class ) . '"' . ( $is_sides_outside ? '' : $style_attr ) . ' data-cwc-config="' . esc_attr( wp_json_encode( $config ) ) . '">';
		$output .= '<div class="swiper-wrapper">';

		if ( 'category' === $config['type'] ) {
			foreach ( $items as $term ) {
				if ( $term instanceof WP_Term ) {
					$output .= $this->render_category_card( $term, $config );
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

		if ( ! empty( $config['pagination'] ) ) {
			$output .= '<div class="swiper-pagination"></div>';
		}

		// Arrows render INSIDE the slider for every mode except sides-outside,
		// where they flank it as shell siblings (opened before the slider
		// above, closed after it below).
		if ( ! $is_sides_outside && ! empty( $config['arrows'] ) ) {
			$output .= '<div class="swiper-button-prev"></div>';
			$output .= '<div class="swiper-button-next"></div>';
		}

		$output .= '</div>';

		if ( $is_sides_outside ) {
			$output .= '<div class="swiper-button-next"></div>';
			$output .= '</div>';
		}

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
	 * Renders the product's full category-path breadcrumb.
	 *
	 * Shows the complete root-to-leaf product_cat path for every assigned
	 * term, independent of the carousel's `category` scoping (CR-10). Input
	 * order is pinned by `wc_get_product_term_ids` (name-ASC) and preserved
	 * by `get_terms(orderby=include)`; for each pinned term the root-to-leaf
	 * path is rebuilt via `array_reverse( get_ancestors() )` + the term
	 * itself, the DEEPEST path wins, and on a depth tie the first pinned term
	 * (name-ASC first) wins. Every segment is escaped and joined with a
	 * static separator; the full path also rides in the title attribute.
	 * Products without any category render nothing, as before.
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

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $term_ids,
				'orderby'    => 'include',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$best_path = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			// get_ancestors() returns lowest-first; reverse to root-to-leaf,
			// then append the term itself (CR-10).
			$path   = array_reverse( get_ancestors( $term->term_id, 'product_cat' ) );
			$path[] = $term->term_id;

			// Strictly-greater keeps the FIRST pinned term on a depth tie
			// (name-ASC wins per wc_get_product_term_ids order).
			if ( count( $path ) > count( $best_path ) ) {
				$best_path = $path;
			}
		}

		if ( empty( $best_path ) ) {
			return '';
		}

		$path_terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $best_path,
				'orderby'    => 'include',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $path_terms ) || empty( $path_terms ) ) {
			return '';
		}

		$segments = array();

		foreach ( $path_terms as $path_term ) {
			if ( $path_term instanceof WP_Term && '' !== $path_term->name ) {
				$segments[] = $path_term->name;
			}
		}

		if ( empty( $segments ) ) {
			return '';
		}

		// Keep raw names in $label and escape once at each output point, so the
		// title attribute is not double-escaped (esc_html + esc_attr would
		// re-encode entities like &amp; -> &amp;amp;).
		$label = implode( ' › ', $segments );

		return '<div class="cwc-card__category-line" title="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</div>';
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
	 * placeholder renders when neither exists (CR-5). With cover mode enabled
	 * the whole card becomes an archive link with a full-bleed image, a
	 * single-opacity overlay and a centered title on top, and no button or
	 * caption below (CR-9); the cover title is the sanitized term meta
	 * `cwc_cat_title` when non-empty, otherwise the escaped term name. With
	 * cover off, the legacy image + name-below markup is produced unchanged.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Term $term   Category term to render.
	 * @param array   $config Resolved carousel configuration.
	 * @return string Escaped category card HTML, or an empty string.
	 */
	private function render_category_card( WP_Term $term, array $config ): string {
		$image = $this->category_card_image( $term );

		$link = get_term_link( $term, 'product_cat' );

		if ( is_wp_error( $link ) ) {
			return '';
		}

		if ( ! empty( $config['cover'] ) ) {
			$title = get_term_meta( $term->term_id, 'cwc_cat_title', true );
			$title = sanitize_text_field( (string) $title );

			if ( '' === $title ) {
				$title = $term->name;
			}

			return sprintf(
				'<div class="swiper-slide cwc-category-card cwc-category-card--cover">'
				. '<a class="cwc-category-card__link" href="%1$s">'
				. '<span class="cwc-category-card__media">'
				. '%2$s'
				. '<span class="cwc-category-card__overlay" aria-hidden="true"></span>'
				. '<span class="cwc-category-card__cover-title">%3$s</span>'
				. '</span>'
				. '</a>'
				. '</div>',
				esc_url( $link ),
				$image,
				esc_html( $title )
			);
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

	/**
	 * Returns the category card image markup.
	 *
	 * Resolves the image via the per-category custom upload (term meta
	 * `cwc_cat_image`), then the WooCommerce category thumbnail
	 * (`thumbnail_id`); a safe placeholder renders when neither exists or the
	 * attachment is missing/deleted (CR-5). Shared by the cover and legacy
	 * card branches.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Term $term Category term to resolve the image for.
	 * @return string Escaped image HTML.
	 */
	private function category_card_image( WP_Term $term ): string {
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

		return $image;
	}
}
