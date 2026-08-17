<?php
/**
 * Frontend asset manager: on-demand Swiper + carousel enqueue.
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers asset handles and enqueues them only where a carousel renders.
 *
 * Uses a dual trigger (D3): a content pre-scan on wp_enqueue_scripts plus a
 * render-flag fallback on wp_footer for paths the pre-scan misses (widgets,
 * nested shortcodes). Nothing loads on pages without a carousel (FA-2).
 *
 * @since 0.1.0
 */
class CWC_Assets {

	/**
	 * Whether frontend assets were already enqueued this request.
	 *
	 * Guards the render-flag fallback so a page flagged by both triggers never
	 * double-enqueues (FA-2).
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	private $enqueued = false;

	/**
	 * Registers the asset hooks.
	 *
	 * The wp_enqueue_scripts and wp_footer hooks are front-end-only, so the
	 * class never touches the admin unless a carousel renders there.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_from_content' ), 20 );
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_from_render_flag' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Registers the frontend asset handles.
	 *
	 * The vendored Swiper bundle is pinned to 14.0.7 in its version argument so
	 * it can never silently update (FA-1). CSS and JS handles use CWC_URL so
	 * they resolve from the local plugin directory, never a CDN. Both scripts
	 * load in the footer so the render-flag fallback can still print them via
	 * wp_print_footer_scripts() after wp_head has already fired (FA-2 scenario
	 * 3).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'cwc-swiper',
			CWC_URL . 'assets/vendor/swiper/swiper-bundle.min.css',
			array(),
			'14.0.7'
		);

		wp_register_script(
			'cwc-swiper',
			CWC_URL . 'assets/vendor/swiper/swiper-bundle.min.js',
			array(),
			'14.0.7',
			true
		);

		wp_register_style(
			'cwc-carousel',
			CWC_URL . 'assets/css/carousel.css',
			array( 'cwc-swiper' ),
			CWC_VERSION
		);

		wp_register_script(
			'cwc-carousel-frontend',
			CWC_URL . 'assets/js/frontend.js',
			array( 'cwc-swiper' ),
			CWC_VERSION,
			true
		);
	}

	/**
	 * Enqueues frontend assets when the post content contains the shortcode.
	 *
	 * WordPress fires wp_enqueue_scripts before the_content, so this header
	 * pre-scan is the only way to enqueue from the head on a page that holds the
	 * `[cwc_carousel]` shortcode (FA-2 scenario 1). get_post() returns null on
	 * non-singular views, which is handled by the guard.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function maybe_enqueue_from_content() {
		if ( $this->enqueued ) {
			return;
		}

		$post = get_post();

		if ( ! $post || ! has_shortcode( $post->post_content, 'cwc_carousel' ) ) {
			return;
		}

		$this->enqueue_frontend();
	}

	/**
	 * Late-enqueues frontend assets when a carousel rendered this request.
	 *
	 * Catches paths the content pre-scan misses (widgets, nested or
	 * programmatically-invoked shortcodes) by reading the renderer's static
	 * flag. CWC_Renderer::render() sets CWC_Renderer::$rendered when it emits
	 * carousel markup (CR-2). Core then prints the late styles/scripts via
	 * print_late_styles() (prio 19) and wp_print_footer_scripts() (prio 20)
	 * (FA-2 scenario 3).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function maybe_enqueue_from_render_flag() {
		if ( $this->enqueued ) {
			return;
		}

		if ( ! class_exists( 'CWC_Renderer' ) || ! CWC_Renderer::$rendered ) {
			return;
		}

		$this->enqueue_frontend();
	}

	/**
	 * Enqueues the carousel frontend assets (idempotent via $enqueued).
	 *
	 * Ordering is handled by handle dependencies: cwc-carousel-frontend depends
	 * on cwc-swiper, so Swiper is always printed before the initiative script.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function enqueue_frontend() {
		$this->enqueued = true;

		wp_enqueue_style( 'cwc-swiper' );
		wp_enqueue_style( 'cwc-carousel' );

		wp_enqueue_script( 'cwc-swiper' );
		wp_enqueue_script( 'cwc-carousel-frontend' );
	}

	/**
	 * Enqueues admin assets only on the Carousel settings screen.
	 *
	 * Runs on admin_enqueue_scripts but loads nothing on any other admin
	 * screen (D7, FA-5): the screen hook suffix of the Carousel submenu is
	 * `-page_cwc-carousel` (WooCommerce parent), and the page itself is gated
	 * by the `manage_woocommerce` capability. On that page the enqueue pulls
	 * WooCommerce's own enhanced-select (selectWoo) and admin stylesheet for
	 * the picker widgets PRs 3-4 render, jquery-ui-sortable for the chip
	 * drag & drop, the plugin admin.css for the chip chrome, and wp.media for
	 * the category-image uploader. WC registers `wc-enhanced-select` and
	 * `woocommerce_admin_styles` on every admin request — only the enqueue is
	 * screen-gated — so enqueueing the handles here is sufficient. The script
	 * receives its translatable strings through `cwcCarouselAdmin` via
	 * wp_localize_script, so admin.js never hardcodes literals (FA-5).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function enqueue_admin_assets() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! is_string( $screen->id ) || false === strpos( $screen->id, 'cwc-carousel' ) ) {
			return;
		}

		// Select2 chrome and AJAX search come from WooCommerce's own handles,
		// both registered on every admin request (FA-5, D3).
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );

		// The plugin stylesheet styles the chip list, drag handle and
		// empty-state CTA (D3); it depends on the WC admin styles so the
		// Select2 chrome loads first. The handle intentionally mirrors the
		// script handle — WordPress keeps separate style/script registries,
		// matching WooCommerce's own `woocommerce_admin` pair.
		wp_enqueue_style(
			'cwc-carousel-admin',
			CWC_URL . 'assets/css/admin.css',
			array( 'woocommerce_admin_styles' ),
			CWC_VERSION
		);

		// wp.media primes the uploader admin.js drives; jquery-ui-sortable
		// powers the chip drag & drop; wc-enhanced-select is a dependency of
		// the pickers PRs 3-4 wire up.
		wp_enqueue_media();
		wp_enqueue_script(
			'cwc-carousel-admin',
			CWC_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wc-enhanced-select' ),
			CWC_VERSION,
			true
		);

		// All JS-facing strings live in cwcCarouselAdmin, so the media
		// uploader and the picker chips never carry hardcoded literals
		// (FA-5). Picker strings PRs 3-4 add extend this same object.
		wp_localize_script(
			'cwc-carousel-admin',
			'cwcCarouselAdmin',
			array(
				'mediaTitle'  => __( 'Select an image for the category', 'cwc-carousel' ),
				'mediaButton' => __( 'Use this image', 'cwc-carousel' ),
				'sortHandle'  => __( 'Drag to reorder', 'cwc-carousel' ),
				'removeChip'  => __( 'Remove', 'cwc-carousel' ),
			)
		);
	}
}
