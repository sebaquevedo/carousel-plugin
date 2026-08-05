<?php
/**
 * Plugin loader: WooCommerce dependency guard, i18n, and module wiring.
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main plugin controller.
 *
 * Registers side-effect-free infrastructure unconditionally, then guards the
 * carousel modules behind a WooCommerce presence check.
 *
 * @since 0.1.0
 */
class CWC_Plugin {

	/**
	 * Runs the plugin.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function run() {
		$this->register_l10n();
		$this->register_missing_wc_notice();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->register_auto_deactivate();
			return;
		}

		$this->register_modules();
	}

	/**
	 * Loads the plugin text domain (PB-3).
	 *
	 * Set up unconditionally so translations resolve even on the
	 * no-WooCommerce path, falling back to source strings when no .mo file
	 * exists yet.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function register_l10n() {
		load_plugin_textdomain(
			'cwc-carousel',
			false,
			dirname( plugin_basename( CWC_FILE ) ) . '/languages'
		);
	}

	/**
	 * Registers the admin notice hook (WD-3).
	 *
	 * The hook is installed before the WooCommerce check so the notice is
	 * always reachable, even when WooCommerce is missing.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function register_missing_wc_notice() {
		add_action(
			'admin_notices',
			array( $this, 'render_missing_wc_notice' )
		);
	}

	/**
	 * Renders the missing-WooCommerce admin notice (WD-3).
	 *
	 * Guarded by the activate_plugins capability and only fires while
	 * WooCommerce is still missing.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_missing_wc_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Custom Woo Pro Carousel requires WooCommerce to be installed and active.', 'cwc-carousel' )
		);
	}

	/**
	 * Registers the automatic self-deactivation hook (WD-2).
	 *
	 * Registered only on the missing-WooCommerce path and bound to admin_init
	 * so deactivate_plugins() (living in wp-admin/includes/plugin.php) is never
	 * called from a front-end or CLI request, where it would fatal.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function register_auto_deactivate() {
		add_action(
			'admin_init',
			array( $this, 'auto_deactivate' )
		);
	}

	/**
	 * Self-deactivates on admin_init when WooCommerce is absent (WD-2).
	 *
	 * Runs only in an admin context, so the plugin retires cleanly for the
	 * next admin request with no partial Carousel state left behind.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function auto_deactivate() {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		deactivate_plugins( plugin_basename( CWC_FILE ) );
	}

	/**
	 * Instantiates the carousel modules that are present.
	 *
	 * CWC_Assets and CWC_Shortcode land in later PRs of this chain; the
	 * class_exists() guards keep this foundation slice from fataling before
	 * those files exist. When present, each class registers its own hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function register_modules() {
		if ( class_exists( 'CWC_Assets' ) ) {
			new CWC_Assets();
		}

		if ( class_exists( 'CWC_Shortcode' ) ) {
			new CWC_Shortcode();
		}
	}
}
