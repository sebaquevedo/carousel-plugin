<?php
/**
 * Compiles languages/cwc-carousel-es_ES.mo from the hand-authored .po.
 *
 * Dev-only tool (not shipped): WordPress bundles POMO under wp-includes/pomo,
 * so the compile runs inside the wp-env container where the plugin is
 * mounted (wp-env mounts `plugins: ["."]` by directory basename, so the
 * container path is wp-content/plugins/carousel-plugin):
 *
 *   npx @wordpress/env run cli wp eval-file wp-content/plugins/carousel-plugin/tools/make-mo.php
 *
 * The plugin's load_plugin_textdomain() looks up cwc-carousel-{locale}.mo in
 * /languages, so the catalog MUST keep the {domain}-{locale} file name.
 *
 * @package CWC_Carousel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // CLI-only via wp eval-file; WP always defines ABSPATH.
}

require_once ABSPATH . 'wp-includes/pomo/po.php';
require_once ABSPATH . 'wp-includes/pomo/mo.php';

$cwc_plugin_dir = dirname( __DIR__ );
$cwc_po_file    = $cwc_plugin_dir . '/languages/cwc-carousel-es_ES.po';
$cwc_mo_file    = $cwc_plugin_dir . '/languages/cwc-carousel-es_ES.mo';

if ( ! file_exists( $cwc_po_file ) ) {
	WP_CLI::error( 'PO file not found: ' . $cwc_po_file );
}

$cwc_po = new PO();
$cwc_ok = $cwc_po->import_from_file( $cwc_po_file );

if ( ! $cwc_ok ) {
	WP_CLI::error( 'Failed to import ' . $cwc_po_file );
}

$cwc_mo          = new MO();
$cwc_mo->headers = $cwc_po->headers;

foreach ( $cwc_po->entries as $cwc_entry ) {
	$cwc_mo->add_entry( $cwc_entry );
}

$cwc_written = $cwc_mo->export_to_file( $cwc_mo_file );

if ( ! $cwc_written ) {
	WP_CLI::error( 'Failed to write ' . $cwc_mo_file );
}

WP_CLI::success( 'Compiled ' . $cwc_mo_file );
