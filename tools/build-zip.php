<?php
/**
 * Builds the distributable plugin ZIP from an explicit allowlist of runtime roots.
 *
 * Dev-only tool (not shipped). The plugin ZIP is intentionally self-contained
 * (see VENDORED_ASSETS.md): only runtime files ship, and dev-only paths
 * (vendor/, tools/, openspec/, .claude/, .cursor/, .atl/, respaldo/) must never
 * end up inside it. An earlier ad-hoc rebuild shipped 2795 entries, including
 * vendor/ and editor skills, because it archived the whole working tree.
 * Shipping from a runtime allowlist makes that class of mistake structurally
 * impossible instead of merely unlikely: anything not listed below is out.
 *
 * The host PHP build has no ext-zip, so run this inside the wp-env container
 * (wp-env mounts `plugins: ["."]` by directory basename, so the container path
 * is wp-content/plugins/carousel-plugin):
 *
 *   npx @wordpress/env run cli wp eval-file wp-content/plugins/carousel-plugin/tools/build-zip.php
 *
 * @package CWC_Carousel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // CLI-only via wp eval-file; WP always defines ABSPATH.
}

$cwc_plugin_dir = dirname( __DIR__ );
$cwc_zip_file   = $cwc_plugin_dir . '/custom-woo-pro-carousel.zip';

// Root-level runtime files that ship.
$cwc_zip_root_files = array(
	'custom-woo-pro-carousel.php',
	'uninstall.php',
);

// Runtime directories that ship recursively and in full.
$cwc_zip_root_dirs = array(
	'assets',
	'includes',
	'languages',
);

// Never ship these, wherever they appear inside a runtime directory.
$cwc_zip_denied_names = array(
	'.DS_Store',
	'Thumbs.db',
	'error_log',
	'node_modules',
);

$cwc_zip_entries = array();

foreach ( $cwc_zip_root_files as $cwc_file ) {
	$cwc_path = $cwc_plugin_dir . '/' . $cwc_file;

	if ( ! is_file( $cwc_path ) ) {
		WP_CLI::error( 'Missing runtime file: ' . $cwc_file );
	}

	$cwc_zip_entries[ $cwc_file ] = $cwc_path;
}

foreach ( $cwc_zip_root_dirs as $cwc_dir ) {
	$cwc_dir_path = $cwc_plugin_dir . '/' . $cwc_dir;

	if ( ! is_dir( $cwc_dir_path ) ) {
		WP_CLI::error( 'Missing runtime directory: ' . $cwc_dir );
	}

	$cwc_iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $cwc_dir_path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ( $cwc_iterator as $cwc_item ) {
		if ( in_array( $cwc_item->getBasename(), $cwc_zip_denied_names, true ) ) {
			continue;
		}

		$cwc_relative = substr( $cwc_item->getPathname(), strlen( $cwc_plugin_dir ) + 1 );

		// ZIP entries always use forward slashes, whatever the host separator.
		$cwc_zip_entries[ str_replace( '\\', '/', $cwc_relative ) ] = $cwc_item->getPathname();
	}
}

// Deterministic entry order keeps the ZIP diffable across rebuilds.
ksort( $cwc_zip_entries );

$cwc_zip  = new ZipArchive();
$cwc_open = $cwc_zip->open( $cwc_zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE );

if ( true !== $cwc_open ) {
	WP_CLI::error( 'Failed to open ZIP for writing: ' . $cwc_zip_file );
}

foreach ( $cwc_zip_entries as $cwc_local => $cwc_absolute ) {
	$cwc_zip->addFile( $cwc_absolute, $cwc_local );
}

$cwc_count = count( $cwc_zip );

if ( ! $cwc_zip->close() ) {
	WP_CLI::error( 'Failed to write ZIP: ' . $cwc_zip_file );
}

// Read the artifact back and fail loudly if anything outside the allowlist shipped.
$cwc_verify  = new ZipArchive();
$cwc_allowed = array_merge( $cwc_zip_root_files, $cwc_zip_root_dirs );

if ( true !== $cwc_verify->open( $cwc_zip_file ) ) {
	WP_CLI::error( 'Failed to reopen ZIP for verification: ' . $cwc_zip_file );
}

$cwc_leaked = array();
$cwc_total  = count( $cwc_verify );

for ( $cwc_index = 0; $cwc_index < $cwc_total; $cwc_index++ ) {
	$cwc_name  = $cwc_verify->getNameIndex( $cwc_index );
	$cwc_slash = strpos( $cwc_name, '/' );
	$cwc_top   = ( false === $cwc_slash ) ? $cwc_name : substr( $cwc_name, 0, $cwc_slash );

	if ( ! in_array( $cwc_top, $cwc_allowed, true ) ) {
		$cwc_leaked[] = $cwc_name;
	}
}

$cwc_verify->close();

if ( ! empty( $cwc_leaked ) ) {
	WP_CLI::error(
		'Dev-only paths leaked into the ZIP: ' . implode( ', ', array_slice( $cwc_leaked, 0, 10 ) )
	);
}

WP_CLI::success(
	sprintf(
		'Built %s with %d entries (%s).',
		$cwc_zip_file,
		$cwc_count,
		size_format( filesize( $cwc_zip_file ) )
	)
);
