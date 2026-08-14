<?php
/**
 * Plugin Name: Custom Woo Pro Carousel
 * Description: A modular WooCommerce product carousel plugin powered by Swiper.
 * Version: 0.1.0
 * Author: Custom Woo Pro Carousel contributors
 * Requires PHP: 7.4
 * Text Domain: cwc-carousel
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 *
 * @package CWC_Carousel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * -------------------------------------------------------------------------
 * Constants (defined only once when this file is loaded twice).
 * -------------------------------------------------------------------------
 */

if ( ! defined( 'CWC_VERSION' ) ) {
	define( 'CWC_VERSION', '0.1.0' );
}

if ( ! defined( 'CWC_FILE' ) ) {
	define( 'CWC_FILE', __FILE__ );
}

if ( ! defined( 'CWC_DIR' ) ) {
	define( 'CWC_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'CWC_URL' ) ) {
	define( 'CWC_URL', plugin_dir_url( __FILE__ ) );
}

/*
 * -------------------------------------------------------------------------
 * Activation / deactivation hooks (no persisted state this slice, PB-2).
 * -------------------------------------------------------------------------
 */

/**
 * Activation callback.
 *
 * The plugin creates no database schema, options, or transients on
 * activation (PB-2), so this hook intentionally does no work.
 *
 * @since 0.1.0
 * @return void
 */
function cwc_carousel_activate() {}

register_activation_hook( __FILE__, 'cwc_carousel_activate' );

/**
 * Deactivation callback.
 *
 * There is no persisted state to remove, so deactivation is clean (PB-2).
 *
 * @since 0.1.0
 * @return void
 */
function cwc_carousel_deactivate() {}

register_deactivation_hook( __FILE__, 'cwc_carousel_deactivate' );

/*
 * -------------------------------------------------------------------------
 * Module loader — explicit require-map, no runtime Composer autoloader (D2).
 * -------------------------------------------------------------------------
 */

$cwc_includes = array(
	'class-plugin.php',
	'class-assets.php',
	'class-settings.php',
	'class-admin.php',
	'class-query.php',
	'class-renderer.php',
	'class-shortcode.php',
);

foreach ( $cwc_includes as $cwc_include ) {
	$cwc_file = CWC_DIR . 'includes/' . $cwc_include;
	if ( file_exists( $cwc_file ) ) {
		require $cwc_file;
	}
}

unset( $cwc_include, $cwc_file );

/*
 * -------------------------------------------------------------------------
 * Boot the plugin (priority 10, D1).
 * -------------------------------------------------------------------------
 */

/**
 * Boots the plugin once all plugins have loaded.
 *
 * Runs the lazy registry migration first (PB-4): when the
 * `cwc_carousel_registry` option is absent it is seeded exactly once with the
 * reserved `default` instance (copied from the legacy `cwc_carousel_options`
 * option when present, else the built-ins) plus the `productos` and
 * `categorias` seeds. The migration never runs on activation (PB-2), never
 * overwrites an existing registry (user edits preserved), and never deletes
 * the legacy option — it stays as the manual restore path.
 *
 * @since 0.1.0
 * @return void
 */
function cwc_carousel_boot() {
	if ( ! class_exists( 'CWC_Plugin' ) ) {
		return;
	}

	$cwc_registry = get_option( 'cwc_carousel_registry' );

	// Seed the registry exactly once (PB-4). A missing option goes through
	// add_option(), which is a no-op if a concurrent request already seeded it
	// (race-safe, idempotent). A present-but-corrupt non-array value is
	// repaired in place with update_option() so the broken value stops being
	// re-attempted on every request.
	if ( ! is_array( $cwc_registry ) ) {
		$cwc_settings = new CWC_Settings();
		$cwc_default  = get_option( 'cwc_carousel_options', array() );
		$cwc_seed     = array(
			'default'    => $cwc_settings->normalize( is_array( $cwc_default ) ? $cwc_default : array() ),
			'productos'  => $cwc_settings->seeds()['productos'],
			'categorias' => $cwc_settings->seeds()['categorias'],
		);

		if ( false === $cwc_registry ) {
			add_option( 'cwc_carousel_registry', $cwc_seed, '', false );
		} else {
			error_log( 'CWC_Carousel: repaired a corrupt (non-array) cwc_carousel_registry option by reseeding.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate observability for a silent-degradation repair (R4-01).
			update_option( 'cwc_carousel_registry', $cwc_seed, false );
		}
	}

	$cwc_plugin = new CWC_Plugin();
	$cwc_plugin->run();
}

add_action( 'plugins_loaded', 'cwc_carousel_boot', 10 );
