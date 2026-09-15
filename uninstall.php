<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up all plugin data from the database:
 * - Registry option (cwc_carousel_registry)
 * - Per-category image overrides (cwc_cat_image term meta)
 * - Per-category overlay titles (cwc_cat_title term meta)
 *
 * @since 0.1.0
 */

// Abort if called directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Delete the registry option.
delete_option( 'cwc_carousel_registry' );

// 2. Delete per-category term meta.
$terms = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $terms ) ) {
	foreach ( $terms as $term_id ) {
		delete_term_meta( $term_id, 'cwc_cat_image' );
		delete_term_meta( $term_id, 'cwc_cat_title' );
	}
}
