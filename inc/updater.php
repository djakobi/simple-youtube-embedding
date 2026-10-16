<?php
/**
 * Updates from GitHub releases.
 *
 * Because of the "Update URI" header, WordPress asks the "update_plugins_github.com"
 * filter for new versions. We read the latest GitHub release and offer its attached
 * "simple-youtube-embedding.zip" as the update package.
 */

defined( 'ABSPATH' ) || exit;

const SYE_GITHUB_REPO = 'djakobi/simple-youtube-embedding';
const SYE_SLUG        = 'simple-youtube-embedding';

/**
 * Latest release from GitHub, cached for six hours.
 *
 * @return array{version:string, package:string, url:string, notes:string}|null
 */
function sye_latest_release() {
	$release = get_transient( 'sye_github_release' );
	if ( false !== $release ) {
		return $release ?: null;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . SYE_GITHUB_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/vnd.github+json' ),
		)
	);

	$release = array();
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( SYE_SLUG . '.zip' === ( $asset['name'] ?? '' ) ) {
				$release = array(
					'version' => ltrim( (string) $data['tag_name'], 'v' ),
					'package' => (string) $asset['browser_download_url'],
					'url'     => (string) $data['html_url'],
					'notes'   => (string) ( $data['body'] ?? '' ),
				);
				break;
			}
		}
	}

	// Cache "nothing found" as well, so GitHub is not queried on every request.
	set_transient( 'sye_github_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release ?: null;
}

add_filter(
	'update_plugins_github.com',
	function ( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( SYE_FILE ) !== $plugin_file ) {
			return $update;
		}

		$release = sye_latest_release();
		if ( ! $release ) {
			return $update;
		}

		return array(
			'slug'    => SYE_SLUG,
			'version' => $release['version'],
			'package' => $release['package'],
			'url'     => $release['url'],
		);
	},
	10,
	3
);

// Content for "View details" in the plugin list.
add_filter(
	'plugins_api',
	function ( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || SYE_SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$release = sye_latest_release();
		return (object) array(
			'name'          => 'Simple YouTube Embedding',
			'slug'          => SYE_SLUG,
			'version'       => $release['version'] ?? SYE_VERSION,
			'author'        => 'Dominik Jakobi',
			'homepage'      => 'https://github.com/' . SYE_GITHUB_REPO,
			'download_link' => $release['package'] ?? '',
			'sections'      => array(
				'description' => 'Lightweight, privacy-friendly YouTube grid, list and live stream embeds without an API key.',
				'changelog'   => $release ? wpautop( esc_html( $release['notes'] ) ) : '',
			),
		);
	},
	10,
	3
);

// Clear the release cache after an update.
add_action(
	'upgrader_process_complete',
	function () {
		delete_transient( 'sye_github_release' );
	}
);
