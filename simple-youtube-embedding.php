<?php
/**
 * Plugin Name: Simple YouTube Embedding
 * Plugin URI: https://github.com/djakobi/simple-youtube-embedding
 * Description: Lightweight, privacy-friendly YouTube embeds: a video grid or numbered list from a channel or playlist, and a live stream block with countdown. No API key required. Thumbnails are served locally and videos only load from youtube-nocookie.com after a click.
 * Version: 2.0.0
 * Author: Dominik Jakobi
 * Author URI: https://github.com/djakobi
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Text Domain: simple-youtube-embedding
 * Domain Path: /languages
 * Update URI: https://github.com/djakobi/simple-youtube-embedding
 *
 * Shortcodes:
 *   [sye_grid channel="UC…" count="6"]
 *   [sye_list playlist="PL…" count="9" visible="3" order="playlist|newest|oldest"]
 *   [sye_live video="VIDEO_ID" start="2026-11-01 10:00" title="Sunday service"]
 */

defined( 'ABSPATH' ) || exit;

const SYE_VERSION       = '2.0.0';
const SYE_FILE          = __FILE__;
const SYE_CACHE_SECONDS = HOUR_IN_SECONDS;
const SYE_MAX_VIDEOS    = 15; // The public YouTube feed returns at most 15 entries.
const SYE_LIVE_HOURS    = 6;  // How long a stream counts as "live" after its start time.

require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/updater.php';

add_action( 'init', function () {
	load_plugin_textdomain( 'simple-youtube-embedding', false, dirname( plugin_basename( SYE_FILE ) ) . '/languages' );

	wp_register_style( 'simple-youtube-embedding', plugins_url( 'assets/simple-youtube-embedding.css', SYE_FILE ), array(), SYE_VERSION );
	wp_register_script( 'simple-youtube-embedding', plugins_url( 'assets/simple-youtube-embedding.js', SYE_FILE ), array(), SYE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script(
		'simple-youtube-embedding',
		'syeStrings',
		array(
			'liveHours'   => SYE_LIVE_HOURS,
			'upcoming'    => __( 'Upcoming', 'simple-youtube-embedding' ),
			'live'        => __( 'Live', 'simple-youtube-embedding' ),
			'recording'   => __( 'Recording', 'simple-youtube-embedding' ),
			'liveNow'     => __( 'Live now – click to play', 'simple-youtube-embedding' ),
			/* translators: %s: remaining time, e.g. 02:15:09 */
			'startsIn'    => __( 'Starts in %s', 'simple-youtube-embedding' ),
			/* translators: 1: number of days, 2: remaining time, e.g. 02:15:09 */
			'startsInDay' => __( 'Starts in %1$s day, %2$s', 'simple-youtube-embedding' ),
			/* translators: 1: number of days, 2: remaining time, e.g. 02:15:09 */
			'startsInDays' => __( 'Starts in %1$s days, %2$s', 'simple-youtube-embedding' ),
			'video'       => __( 'Video', 'simple-youtube-embedding' ),
			'close'       => __( 'Close video', 'simple-youtube-embedding' ),
		)
	);

	add_shortcode( 'sye_grid', 'sye_shortcode_grid' );
	add_shortcode( 'sye_list', 'sye_shortcode_list' );
	add_shortcode( 'sye_live', 'sye_shortcode_live' );
} );

function sye_enqueue_assets() {
	wp_enqueue_style( 'simple-youtube-embedding' );
	wp_enqueue_script( 'simple-youtube-embedding' );
}

function sye_is_video_id( $id ) {
	return is_string( $id ) && preg_match( '/^[A-Za-z0-9_-]{11}$/', $id );
}

/**
 * Reads the public YouTube feed of a channel or playlist.
 * Results are cached for an hour; if a request fails, the last good result is used.
 *
 * @param string $type   'channel' or 'playlist'.
 * @param string $source Channel ID (UC…) or playlist ID (PL…).
 * @return array<int, array{id:string, title:string, published:int}>
 */
function sye_get_feed( $type, $source ) {
	$param = 'playlist' === $type ? 'playlist_id' : 'channel_id';
	if ( ! preg_match( '/^[A-Za-z0-9_-]{10,64}$/', $source ) ) {
		return array();
	}

	$key    = 'sye_feed_' . md5( $param . $source );
	$videos = get_transient( $key );
	if ( is_array( $videos ) ) {
		return $videos;
	}

	$response = wp_remote_get( 'https://www.youtube.com/feeds/videos.xml?' . $param . '=' . rawurlencode( $source ), array( 'timeout' => 10 ) );
	$videos   = array();

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( wp_remote_retrieve_body( $response ) );
		libxml_use_internal_errors( $previous );

		if ( $xml ) {
			foreach ( $xml->entry as $entry ) {
				$yt = $entry->children( 'http://www.youtube.com/xml/schemas/2015' );
				$id = (string) $yt->videoId;
				if ( sye_is_video_id( $id ) ) {
					$videos[] = array(
						'id'        => $id,
						'title'     => (string) $entry->title,
						'published' => (int) strtotime( (string) $entry->published ),
					);
				}
			}
		}
	}

	if ( $videos ) {
		set_transient( $key, $videos, SYE_CACHE_SECONDS );
		update_option( $key . '_last', $videos, false );
		return $videos;
	}

	// Request failed: show the last known result and retry in ten minutes.
	$videos = get_option( $key . '_last', array() );
	set_transient( $key, $videos, 10 * MINUTE_IN_SECONDS );
	return $videos;
}

/**
 * Stores the thumbnail locally so that nothing is loaded from Google before a click.
 * Falls back to the image on YouTube if saving fails.
 */
function sye_thumbnail_url( $id, $size = 'hqdefault' ) {
	$uploads = wp_upload_dir();
	$file    = $id . '-' . $size . '.jpg';
	$path    = trailingslashit( $uploads['basedir'] ) . 'simple-youtube-embedding/' . $file;
	$url     = trailingslashit( $uploads['baseurl'] ) . 'simple-youtube-embedding/' . $file;
	$remote  = 'https://i.ytimg.com/vi/' . $id . '/' . $size . '.jpg';

	if ( file_exists( $path ) ) {
		return $url;
	}

	$response = wp_remote_get( $remote, array( 'timeout' => 10 ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		// Not every video has a maxres thumbnail.
		return 'maxresdefault' === $size ? sye_thumbnail_url( $id, 'hqdefault' ) : $remote;
	}

	wp_mkdir_p( dirname( $path ) );
	if ( false === file_put_contents( $path, wp_remote_retrieve_body( $response ) ) ) {
		return $remote;
	}
	return $url;
}

function sye_privacy_note() {
	return '<p class="sye-note">' . esc_html__( 'Playing a video connects to YouTube.', 'simple-youtube-embedding' ) . '</p>';
}

function sye_calendar_icon() {
	return '<svg class="sye-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 16H5V10h14v10Zm0-12H5V6h14v2Zm-7 5H7v5h5v-5Z"/></svg>';
}

function sye_shortcode_list( $atts ) {
	$atts         = (array) $atts;
	$atts['view'] = 'list';
	return sye_shortcode_grid( $atts );
}

function sye_shortcode_grid( $atts ) {
	$atts = shortcode_atts(
		array(
			'channel'  => sye_default_channel(),
			'playlist' => '',
			'count'    => 6,
			'columns'  => 3,
			'view'     => 'grid', // grid | list
			'order'    => '',     // playlist | newest | oldest (default: playlist order for playlists, newest for channels)
			'visible'  => 0,      // > 0: show this many, reveal the rest with "Show more videos"
		),
		$atts,
		'sye_grid'
	);

	if ( ! $atts['playlist'] && ! $atts['channel'] ) {
		return current_user_can( 'edit_posts' )
			? '<p><strong>Simple YouTube Embedding:</strong> ' . esc_html__( 'Please add a channel or playlist ID, or set a default channel under Settings → Simple YouTube Embedding.', 'simple-youtube-embedding' ) . '</p>'
			: '';
	}

	$videos = $atts['playlist']
		? sye_get_feed( 'playlist', $atts['playlist'] )
		: sye_get_feed( 'channel', $atts['channel'] );

	$order = $atts['order'] ? $atts['order'] : ( $atts['playlist'] ? 'playlist' : 'newest' );
	if ( 'newest' === $order ) {
		usort( $videos, fn( $a, $b ) => $b['published'] <=> $a['published'] );
	} elseif ( 'oldest' === $order ) {
		usort( $videos, fn( $a, $b ) => $a['published'] <=> $b['published'] );
	}
	$videos = array_slice( $videos, 0, max( 1, min( SYE_MAX_VIDEOS, (int) $atts['count'] ) ) );

	if ( ! $videos ) {
		return current_user_can( 'edit_posts' ) ? '<!-- Simple YouTube Embedding: no videos found -->' : '';
	}

	sye_enqueue_assets();
	$is_list = 'list' === $atts['view'];
	$visible = max( 0, (int) $atts['visible'] );
	if ( $visible >= count( $videos ) ) {
		$visible = 0;
	}

	// A <button> rather than <a href="youtube.com/watch…">: some themes automatically turn
	// YouTube links into iframes, which would load YouTube on page load.
	$html = $is_list
		? '<ol class="sye sye-list">'
		: '<div class="sye sye-grid" style="--sye-columns:' . max( 1, min( 4, (int) $atts['columns'] ) ) . '">';

	foreach ( $videos as $index => $video ) {
		// Hidden entries use the "hidden" attribute, so their lazy thumbnails are not loaded yet.
		$html .= sprintf(
			'<%1$s class="sye-card"%2$s>%3$s
				<button type="button" class="sye-player" data-sye-video="%4$s" data-sye-mode="overlay" data-sye-title="%5$s" aria-label="%6$s" aria-haspopup="dialog">
					<img src="%7$s" alt="" loading="lazy" decoding="async" width="480" height="270">
					<span class="sye-play" aria-hidden="true"></span>
				</button>
				<div class="sye-text">
					<h3 class="sye-title">%5$s</h3>
					<time class="sye-date" datetime="%8$s">%9$s%10$s</time>
				</div>
			</%1$s>',
			$is_list ? 'li' : 'article',
			$visible && $index >= $visible ? ' hidden' : '',
			$is_list ? '<span class="sye-number" aria-hidden="true">' . ( $index + 1 ) . '</span>' : '',
			esc_attr( $video['id'] ),
			esc_html( $video['title'] ),
			/* translators: %s: video title */
			esc_attr( sprintf( __( 'Play video: %s', 'simple-youtube-embedding' ), $video['title'] ) ),
			esc_url( sye_thumbnail_url( $video['id'] ) ),
			esc_attr( gmdate( 'c', $video['published'] ) ),
			sye_calendar_icon(),
			esc_html( wp_date( get_option( 'date_format' ), $video['published'] ) )
		);
	}

	$html .= $is_list ? '</ol>' : '</div>';

	if ( $visible ) {
		$html .= sprintf(
			'<p class="sye sye-more-wrap"><button type="button" class="sye-more" data-sye-step="%1$d">%2$s <span class="sye-more-count">(%3$d)</span></button></p>',
			$visible,
			esc_html__( 'Show more videos', 'simple-youtube-embedding' ),
			count( $videos ) - $visible
		);
	}

	return $html . sye_privacy_note();
}

function sye_shortcode_live( $atts ) {
	$atts = shortcode_atts(
		array(
			'video' => '',
			'start' => '',
			'title' => '',
		),
		$atts,
		'sye_live'
	);

	if ( ! sye_is_video_id( $atts['video'] ) ) {
		return current_user_can( 'edit_posts' )
			? '<p><strong>Simple YouTube Embedding:</strong> ' . esc_html__( 'Please provide a valid video ID, e.g. video="abc123XYZ_-".', 'simple-youtube-embedding' ) . '</p>'
			: '';
	}

	sye_enqueue_assets();

	$start = '';
	if ( $atts['start'] ) {
		$date  = date_create( $atts['start'], wp_timezone() );
		$start = $date ? $date->format( 'c' ) : '';
	}

	$title = $atts['title'] ? '<h2 class="sye-live-title">' . esc_html( $atts['title'] ) . '</h2>' : '';
	$time  = $start
		? '<p class="sye-live-time">' . esc_html( wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime( $start ) ) ) . '</p>'
		: '';

	return sprintf(
		'<section class="sye sye-live" data-sye-start="%1$s">
			<button type="button" class="sye-player" data-sye-video="%2$s" aria-label="%3$s">
				<img src="%4$s" alt="" decoding="async" width="1280" height="720">
				<span class="sye-status" aria-live="polite"></span>
				<span class="sye-play" aria-hidden="true"></span>
			</button>
			<div class="sye-live-info">%5$s%6$s<p class="sye-countdown" aria-live="polite"></p></div>
		</section>%7$s',
		esc_attr( $start ),
		esc_attr( $atts['video'] ),
		esc_attr( $atts['title'] ? sprintf( /* translators: %s: video title */ __( 'Play video: %s', 'simple-youtube-embedding' ), $atts['title'] ) : __( 'Play video', 'simple-youtube-embedding' ) ),
		esc_url( sye_thumbnail_url( $atts['video'], 'maxresdefault' ) ),
		$title,
		$time,
		sye_privacy_note()
	);
}
