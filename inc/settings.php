<?php
/**
 * Settings page: Settings → Simple YouTube Embedding.
 * Stores the default channel used by [sye_grid] and [sye_list] when no channel or playlist is given.
 */

defined( 'ABSPATH' ) || exit;

function sye_default_channel() {
	return (string) get_option( 'sye_default_channel', '' );
}

add_action( 'admin_init', function () {
	register_setting(
		'sye_settings',
		'sye_default_channel',
		array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => function ( $value ) {
				$value = trim( (string) $value );
				if ( '' === $value || preg_match( '/^UC[A-Za-z0-9_-]{22}$/', $value ) ) {
					return $value;
				}
				add_settings_error( 'sye_default_channel', 'sye_invalid_channel', __( 'Please enter a channel ID that starts with "UC" and has 24 characters.', 'simple-youtube-embedding' ) );
				return sye_default_channel();
			},
		)
	);

	add_settings_section( 'sye_main', '', '__return_false', 'simple-youtube-embedding' );

	add_settings_field(
		'sye_default_channel',
		__( 'Default channel ID', 'simple-youtube-embedding' ),
		function () {
			printf(
				'<input type="text" id="sye_default_channel" name="sye_default_channel" value="%1$s" class="regular-text code" placeholder="UCxxxxxxxxxxxxxxxxxxxxxx" pattern="UC[A-Za-z0-9_-]{22}">
				<p class="description">%2$s</p>',
				esc_attr( sye_default_channel() ),
				esc_html__( 'Used when a shortcode has neither "channel" nor "playlist". You can find the ID on YouTube under your channel → About → Share channel → Copy channel ID.', 'simple-youtube-embedding' )
			);
		},
		'simple-youtube-embedding',
		'sye_main',
		array( 'label_for' => 'sye_default_channel' )
	);
} );

add_action( 'admin_menu', function () {
	add_options_page(
		'Simple YouTube Embedding',
		'Simple YouTube Embedding',
		'manage_options',
		'simple-youtube-embedding',
		function () {
			?>
			<div class="wrap">
				<h1>Simple YouTube Embedding</h1>
				<form action="options.php" method="post">
					<?php
					settings_fields( 'sye_settings' );
					do_settings_sections( 'simple-youtube-embedding' );
					submit_button();
					?>
				</form>
				<h2><?php esc_html_e( 'Shortcodes', 'simple-youtube-embedding' ); ?></h2>
				<p><code>[sye_grid count="6"]</code> – <?php esc_html_e( 'grid of the latest videos', 'simple-youtube-embedding' ); ?></p>
				<p><code>[sye_list playlist="PL…" count="9" visible="3"]</code> – <?php esc_html_e( 'numbered list with "Show more videos"', 'simple-youtube-embedding' ); ?></p>
				<p><code>[sye_live video="…" start="2026-11-01 10:00" title="…"]</code> – <?php esc_html_e( 'live stream with countdown', 'simple-youtube-embedding' ); ?></p>
			</div>
			<?php
		}
	);
} );

// "Settings" link in the plugin list.
add_filter( 'plugin_action_links_' . plugin_basename( SYE_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=simple-youtube-embedding' ) ) . '">' . esc_html__( 'Settings', 'simple-youtube-embedding' ) . '</a>' );
	return $links;
} );
