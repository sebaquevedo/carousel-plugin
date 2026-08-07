<?php
/**
 * Admin settings page: carousel globals and per-category image overrides.
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the Carousel settings page under WooCommerce and two data paths.
 *
 * 1. Global defaults are edited with the Settings API and stored as one
 *    sanitized array in `cwc_carousel_options` with autoload disabled (AS-2);
 *    `delete_option` fully resets them because CWC_Settings falls back to
 *    its built-ins when the option is absent (CM-1).
 * 2. Per-category image overrides are stored as the `cwc_cat_image` term meta
 *    key that CWC_Renderer reads at render time (AS-3). They are saved on
 *    admin_init, guarded by a nonce and the `manage_woocommerce` capability,
 *    so term side-effects never leak into the pure option sanitizer (D7).
 *
 * The page and all saves are gated by `manage_woocommerce` (AS-1).
 *
 * @since 0.1.0
 */
class CWC_Admin {

	/**
	 * Option group for register_setting + settings_fields.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $option_group = 'cwc_options_group';

	/**
	 * Slug for the Settings API page + submenu.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $page_slug = 'cwc-carousel';

	/**
	 * Nonce action used when saving per-category image overrides.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $image_nonce_action = 'cwc_save_category_image';

	/**
	 * Name of the nonce field used when saving category image overrides.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $image_nonce_field = 'cwc_category_image_nonce';

	/**
	 * Term meta key holding the custom category image attachment id.
	 *
	 * Must match the renderer's lookup (CR-5) so an uploaded override is what
	 * the category card actually shows (AS-3).
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $meta_key = 'cwc_cat_image';

	/**
	 * Registers the admin hooks.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'save_category_images' ) );
	}

	/**
	 * Adds the Carousel submenu page under the WooCommerce menu (AS-1).
	 *
	 * The page is gated by the `manage_woocommerce` capability via the menu
	 * argument; the save handlers repeat the same check (WD-4).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Carousel', 'cwc-carousel' ),
			__( 'Carousel', 'cwc-carousel' ),
			'manage_woocommerce',
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the one option, its sanitize callback, and the settings fields.
	 *
	 * `cwc_carousel_options` is stored with autoload disabled and sanitized by
	 * a single callback (AS-2). The screen page slug doubles as the Settings
	 * API page id after add_settings_section().
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			$this->option_group,
			'cwc_carousel_options',
			array(
				'type'              => 'array',
				'autoload'          => false,
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);

		// Align the options.php save gate with the page/menu capability so a
		// Shop Manager (manage_woocommerce) who can open the page can also save
		// it (R1-W1). By default options.php requires manage_options, which
		// would 403 the same button that already wrote category images.
		add_filter(
			"option_page_capability_{$this->option_group}",
			static function () {
				return 'manage_woocommerce';
			}
		);

		add_settings_section(
			'cwc_carousel_main',
			__( 'Carousel defaults', 'cwc-carousel' ),
			array( $this, 'render_main_section' ),
			$this->page_slug
		);

		add_settings_field(
			'cwc_type',
			__( 'Carousel type', 'cwc-carousel' ),
			array( $this, 'render_type_field' ),
			$this->page_slug,
			'cwc_carousel_main'
		);

		add_settings_field(
			'cwc_categories',
			__( 'Categories', 'cwc-carousel' ),
			array( $this, 'render_categories_field' ),
			$this->page_slug,
			'cwc_carousel_main'
		);

		add_settings_field(
			'cwc_slides',
			__( 'Slides', 'cwc-carousel' ),
			array( $this, 'render_slides_field' ),
			$this->page_slug,
			'cwc_carousel_main'
		);

		add_settings_field(
			'cwc_gap',
			__( 'Gap (px)', 'cwc-carousel' ),
			array( $this, 'render_gap_field' ),
			$this->page_slug,
			'cwc_carousel_main'
		);

		add_settings_field(
			'cwc_count',
			__( 'Maximum items', 'cwc-carousel' ),
			array( $this, 'render_count_field' ),
			$this->page_slug,
			'cwc_carousel_main'
		);

		add_settings_field(
			'cwc_buy',
			__( 'Buy button', 'cwc-carousel' ),
			array( $this, 'render_buy_field' ),
			$this->page_slug,
			'cwc_carousel_main'
		);
	}

	/**
	 * Renders the page wrapper and the global settings form (AS-1).
	 *
	 * Echoes the settings form bound to the registered group and then the
	 * per-category image override block inside the same form, so both submit
	 * through the one options post and each carries its own nonce.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Carousel', 'cwc-carousel' ); ?></h1>
			<p>
				<?php esc_html_e( 'Configure the default behavior of every Shortcode Carousel.', 'cwc-carousel' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php
				settings_fields( $this->option_group );
				do_settings_sections( $this->page_slug );
				submit_button();
				$this->render_category_images();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Intro text for the global settings section.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_main_section() {
		echo '<p>' . esc_html__( 'These values apply when a shortcode does not override them.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the type select field.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_type_field() {
		$current  = $this->current();
		$selected = ( 'category' === $current['type'] ) ? 'category' : 'product';

		echo '<select name="cwc_carousel_options[type]">';
		echo '<option value="product"' . selected( $selected, 'product', false ) . '>' . esc_html__( 'Products', 'cwc-carousel' ) . '</option>';
		echo '<option value="category"' . selected( $selected, 'category', false ) . '>' . esc_html__( 'Categories', 'cwc-carousel' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Whether the carousel shows products or category cards (product_cat terms).', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the product_cat multiselect field.
	 *
	 * The chosen term ids are stored as the `categories` config used as the
	 * mix seed and the category-card set.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_categories_field() {
		$current  = $this->current();
		$selected = array_map( 'absint', $current['categories'] );
		$terms    = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<p class="description">' . esc_html__( 'No product categories found.', 'cwc-carousel' ) . '</p>';
			return;
		}

		echo '<select name="cwc_carousel_options[categories][]" multiple="multiple" size="6" class="cwc-categories-select">';
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			echo '<option value="' . esc_attr( $term->term_id ) . '"'
				. selected( in_array( (int) $term->term_id, $selected, true ), true, false )
				. '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Hold Ctrl (Cmd on Mac) to select several.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the desktop/tablet/mobile slides number fields (1-12).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_slides_field() {
		$current = $this->current();

		$output = esc_html__( 'Desktop', 'cwc-carousel' ) . ' '
			. $this->render_number( 'cwc_carousel_options[slides]', $current['slides'], 1, 12 ) . '<br />'
			. esc_html__( 'Tablet', 'cwc-carousel' ) . ' '
			. $this->render_number( 'cwc_carousel_options[slides_tablet]', $current['slides_tablet'], 1, 12 ) . '<br />'
			. esc_html__( 'Mobile', 'cwc-carousel' ) . ' '
			. $this->render_number( 'cwc_carousel_options[slides_mobile]', $current['slides_mobile'], 1, 12 );

		// phpcs:ignore WordPress.Security.EscapeOutput -- render_number() escapes every attribute; labels escaped above.
		echo $output;
	}

	/**
	 * Renders the gap field (8-64 px).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_gap_field() {
		$current = $this->current();
		echo $this->render_number( 'cwc_carousel_options[gap]', $current['gap'], 8, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_number() returns escaped HTML.
	}

	/**
	 * Renders the count field (0+).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_count_field() {
		$current = $this->current();
		echo $this->render_number( 'cwc_carousel_options[count]', $current['count'], 0, PHP_INT_MAX ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_number() returns escaped HTML.
		echo '<p class="description">' . esc_html__( '0 renders an empty carousel.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the buy toggle and label text fields.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_buy_field() {
		$current = $this->current();

		$output = '<label><input type="checkbox" name="cwc_carousel_options[buy]" value="1"'
			. checked( ! empty( $current['buy'] ), true, false ) . ' /> '
			. esc_html__( 'Show the Buy button on product cards', 'cwc-carousel' ) . '</label><br />'
			. '<label>' . esc_html__( 'Buy text', 'cwc-carousel' ) . ' '
			. '<input type="text" name="cwc_carousel_options[buy_text]" value="' . esc_attr( $current['buy_text'] ) . '" />'
			. '</label>';

		// phpcs:ignore WordPress.Security.EscapeOutput -- checked() returns escaped HTML (core escaping function).
		echo $output;
	}

	/**
	 * Renders the per-category custom image override group (AS-3).
	 *
	 * A row with a hidden attachment-id input, a preview, and an Upload button
	 * renders for each chosen category. The hidden input carries the term id so
	 * admin.js can drive wp.media per term. Saving is handled separately by
	 * save_category_images() to keep term side-effects out of the sanitizer.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_category_images() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$current  = $this->current();
		$term_ids = array_map( 'absint', $current['categories'] );

		if ( empty( $term_ids ) ) {
			echo '<p>' . esc_html__( 'Select categories above to set a custom image per category.', 'cwc-carousel' ) . '</p>';
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $term_ids,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<p>' . esc_html__( 'No selected categories were found.', 'cwc-carousel' ) . '</p>';
			return;
		}

		wp_nonce_field( $this->image_nonce_action, $this->image_nonce_field );

		echo '<h2>' . esc_html__( 'Category images', 'cwc-carousel' ) . '</h2>';
		echo '<p>' . esc_html__( 'Optionally set a custom image that overrides the WooCommerce category thumbnail.', 'cwc-carousel' ) . '</p>';

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$image_id = (int) get_term_meta( $term->term_id, $this->meta_key, true );
			$preview  = ( $image_id > 0 ) ? wp_get_attachment_image( $image_id, 'thumbnail' ) : '';

			printf(
				'<div class="cwc-category-image-row" data-term-id="%1$d">'
				. '<span class="cwc-cat-image-preview">%2$s</span>'
				. '<input type="hidden" name="cwc_cat_images[%1$d]" class="cwc-cat-image-id" value="%3$d" />'
				. '<button type="button" class="button cwc-cat-image-upload">%4$s</button>'
				. '<button type="button" class="button-link-delete cwc-cat-image-remove">%5$s</button>'
				. '<p class="cwc-cat-image-term">%6$s</p>'
				. '</div>',
				(int) $term->term_id,
				$preview, // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() escapes internally.
				absint( $image_id ),
				esc_html__( 'Choose image', 'cwc-carousel' ),
				esc_html__( 'Remove image', 'cwc-carousel' ),
				esc_html( $term->name )
			);
		}
	}

	/**
	 * Persists per-category image overrides submitted with the settings form.
	 *
	 * Runs on admin_init so it sees the same POST the Settings API processes;
	 * the form passes a dedicated nonce and capability so term writes never
	 * cross into the pure option sanitizer (D7). An empty value clears the
	 * override, the uploads the value comes from the media library.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function save_category_images() {
		if ( ! isset( $_POST['cwc_cat_images'] ) || ! is_array( $_POST['cwc_cat_images'] ) ) {
			return;
		}

		check_admin_referer( $this->image_nonce_action, $this->image_nonce_field );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$images = array_map( 'absint', wp_unslash( $_POST['cwc_cat_images'] ) );

		foreach ( $images as $term_id => $attachment_id ) {
			if ( $term_id <= 0 ) {
				continue;
			}

			$term = get_term( $term_id, 'product_cat' );

			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			if ( $attachment_id > 0 ) {
				update_term_meta( $term_id, $this->meta_key, $attachment_id );
			} else {
				delete_term_meta( $term_id, $this->meta_key );
			}
		}
	}

	/**
	 * Sanitizes the submitted global option into one well-formed array.
	 *
	 * Every stored value is validated/coerced here, exactly once (AS-2):
	 * slides 1-12, gap 8-64, count int ≥ 0, categories as positive ids, buy as
	 * a boolean, buy_text as text, type within {product, category}. Unknown or
	 * invalid keys are normalized to their defaults, never resurrected from the
	 * raw post (CM-2). The result contains only global option keys — category
	 * image overrides live in term meta via save_category_images().
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $input Raw submitted option value.
	 * @return array Sanitized global option array.
	 */
	public function sanitize_options( $input ) {
		$input = ( is_array( $input ) ) ? $input : array();
		$built = $this->builtins();

		$type = isset( $input['type'] ) ? $input['type'] : $built['type'];
		$buy  = isset( $input['buy'] ) ? $input['buy'] : $built['buy'];
		$text = isset( $input['buy_text'] ) ? $input['buy_text'] : $built['buy_text'];

		return array(
			'type'          => ( 'category' === $type ) ? 'category' : 'product',
			'categories'    => $this->sanitize_ids( isset( $input['categories'] ) ? $input['categories'] : array() ),
			'slides'        => $this->bound( $input, 'slides', 1, 12, $built['slides'] ),
			'slides_tablet' => $this->bound( $input, 'slides_tablet', 1, 12, $built['slides_tablet'] ),
			'slides_mobile' => $this->bound( $input, 'slides_mobile', 1, 12, $built['slides_mobile'] ),
			'gap'           => $this->bound( $input, 'gap', 8, 64, $built['gap'] ),
			'count'         => $this->bound( $input, 'count', 0, PHP_INT_MAX, $built['count'] ),
			'buy'           => $this->parse_bool( $buy ),
			'buy_text'      => $this->clean_text( $text, $built['buy_text'] ),
		);
	}

	/**
	 * Returns the current stored option merged over built-in defaults.
	 *
	 * Reuses CWC_Settings defaults() when present so rollback via
	 * delete_option() is exact (the option reads back to built-ins). A small
	 * local built-in map keeps the page functional even when the settings model
	 * file is not loaded.
	 *
	 * @since 0.1.0
	 *
	 * @return array Current global option keyed by resolved config keys.
	 */
	private function current() {
		if ( class_exists( 'CWC_Settings' ) ) {
			return ( new CWC_Settings() )->defaults();
		}

		$option = get_option( 'cwc_carousel_options', array() );

		if ( ! is_array( $option ) ) {
			$option = array();
		}

		return wp_parse_args( $option, $this->builtins() );
	}

	/**
	 * Returns the built-in defaults for the global option.
	 *
	 * Mirrors the CWC_Settings built-ins for the fields this page edits.
	 *
	 * @since 0.1.0
	 *
	 * @return array Default global option values.
	 */
	private function builtins(): array {
		return array(
			'type'          => 'product',
			'categories'    => array(),
			'slides'        => 3,
			'slides_tablet' => 2,
			'slides_mobile' => 1,
			'gap'           => 16,
			'count'         => 8,
			'buy'           => true,
			'buy_text'      => 'Comprar',
		);
	}

	/**
	 * Coerces a raw numeric field into an integer within the given bounds.
	 *
	 * @since 0.1.0
	 *
	 * @param array  $input   Raw option value.
	 * @param string $key     Field key.
	 * @param int    $min     Inclusive minimum.
	 * @param int    $max     Inclusive maximum.
	 * @param int    $fallback Fallback when the key is absent.
	 * @return int Bounded integer.
	 */
	private function bound( array $input, string $key, int $min, int $max, int $fallback ): int {
		if ( ! isset( $input[ $key ] ) ) {
			return $fallback;
		}

		return min( $max, max( $min, absint( $input[ $key ] ) ) );
	}

	/**
	 * Coerces a raw id list into non-zero positive integers.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $raw Raw categories value.
	 * @return int[] Sanitized positive integer IDs (may be empty).
	 */
	private function sanitize_ids( $raw ): array {
		if ( is_array( $raw ) ) {
			$ids = array_map( 'absint', $raw );
		} else {
			$ids = array_map( 'absint', wp_parse_id_list( (string) $raw ) );
		}

		return array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return $id > 0;
				}
			)
		);
	}

	/**
	 * Coerces a truthy/falsy flag into a boolean.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value Raw boolean-ish value.
	 * @return bool Normalized boolean.
	 */
	private function parse_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return (bool) $value;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitizes a short text value, falling back to the provided default.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed  $value Raw text value.
	 * @param string $fallback Fallback text.
	 * @return string Sanitized text.
	 */
	private function clean_text( $value, string $fallback ): string {
		$text = sanitize_text_field( (string) $value );

		return ( '' === $text ) ? $fallback : $text;
	}

	/**
	 * Renders a bounded number input.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name  Input name.
	 * @param int    $value Current value.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @return string Escaped input HTML.
	 */
	private function render_number( string $name, int $value, int $min, int $max ): string {
		return sprintf(
			'<input type="number" name="%1$s" value="%2$d" min="%3$d" max="%4$d" />',
			esc_attr( $name ),
			$value,
			$min,
			$max
		);
	}
}