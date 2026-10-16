<?php
/**
 * Clean up when the plugin is deleted: settings, feed caches and locally stored thumbnails.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'sye_default_channel' );
delete_transient( 'sye_github_release' );

// Feed caches (transients) and "last known result" options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_sye\_feed\_%' OR option_name LIKE '\_transient\_timeout\_sye\_feed\_%' OR option_name LIKE 'sye\_feed\_%\_last'" );

// Locally stored thumbnails.
$uploads = wp_upload_dir();
$folder  = trailingslashit( $uploads['basedir'] ) . 'simple-youtube-embedding';
if ( is_dir( $folder ) ) {
	foreach ( glob( $folder . '/*.jpg' ) ?: array() as $file ) {
		wp_delete_file( $file );
	}
	@rmdir( $folder ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- only removes an empty folder
}
