# Simple YouTube Embedding

Lightweight, privacy-friendly YouTube embeds for WordPress: a video grid or numbered list from a channel or playlist, and a live stream block with countdown.

- **No API key.** Videos come from YouTube's public RSS feed.
- **Privacy-friendly.** Thumbnails are stored on your own server. The video only loads from `youtube-nocookie.com` after a click.
- **Light.** About 10 KB of CSS and JavaScript, loaded only on pages that use a shortcode.
- **Plays well with others.** Starting a video pauses other `<audio>`/`<video>` elements on the page and fires a `sye:play` event that custom players can listen to.

## Shortcodes

Use the shortcodes anywhere: in the block editor, in classic content, or in page builders (e.g. Elementor's "Shortcode" widget).

### Grid

```
[sye_grid count="6"]
[sye_grid channel="UCxxxxxxxxxxxxxxxxxxxxxx" count="9" columns="3"]
[sye_grid playlist="PLxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" count="9"]
```

### List

Same as the grid, but numbered and one video per row.

```
[sye_list playlist="PLxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" count="10"]
[sye_list playlist="PLxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" order="oldest"]
[sye_list playlist="PLxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" count="15" visible="3"]
```

Videos from grids and lists open in a large overlay that closes with ✕, Esc or a click on the backdrop.

#### Attributes for grid and list

| Attribute | Default | Description |
|---|---|---|
| `channel` | default channel from the settings | Channel ID (`UC…`) |
| `playlist` | – | Playlist ID (`PL…`); takes precedence over `channel` |
| `count` | `6` | Maximum number of videos, 1–15 |
| `columns` | `3` | Grid only: 1–4 columns on desktop (2 on tablets, 1 on phones) |
| `order` | `playlist` for playlists, `newest` for channels | `playlist`, `newest` or `oldest` |
| `visible` | all | Show only this many at first. A "Show more videos" button reveals the next batch of the same size. Thumbnails of hidden videos are lazy-loaded when revealed. |

### Live stream

```
[sye_live video="abc123XYZ_-" start="2026-11-01 10:00" title="Sunday service"]
[sye_live video="abc123XYZ_-"]
```

| Attribute | Description |
|---|---|
| `video` | Video ID of the scheduled stream or any video (required) |
| `start` | Start time in the site's time zone, `YYYY-MM-DD HH:MM` (optional) |
| `title` | Heading below the player (optional) |

As soon as you schedule a live stream on YouTube, it gets its own video ID. With a `start` time the block shows:

- **before the start:** an "Upcoming" badge and a countdown,
- **from the start** (for 6 hours): a "Live" badge,
- **afterwards:** a "Recording" badge. YouTube keeps the same ID for the recording.

Without `start`, the block is simply a large click-to-play video.

## Settings

*Settings → Simple YouTube Embedding* lets you set a **default channel ID**. It is used when a shortcode has neither `channel` nor `playlist`. You find the ID on YouTube under your channel → *About* → *Share channel* → *Copy channel ID*.

## Styling

The look is controlled by CSS variables on `.sye`. Override them in your theme, for example:

```css
.sye {
	--sye-accent: #1e73be;         /* play button hover, countdown, list numbers */
	--sye-live-color: #d32f2f;     /* "Live" badge */
	--sye-radius: 3px;             /* card corners */
	--sye-shadow: 0 0 6px rgba( 0, 0, 0, 0.15 );
	--sye-shadow-hover: 0 4px 16px rgba( 0, 0, 0, 0.2 );
}
```

## Integrating with other players

Before a video starts, the plugin pauses all playing `<audio>` and `<video>` elements and dispatches an event:

```js
document.addEventListener( 'sye:play', function ( event ) {
	console.log( 'Starting video', event.detail.videoId );
	// e.g. pause a custom audio player here
} );
```

## Limitations

- YouTube's public feed returns at most **15 videos** per channel or playlist.
- Feeds are cached for **one hour**. If YouTube cannot be reached, the last known result is shown.
- The live status is based on the given start time, not on the actual stream state on YouTube.
- Channel handles (`@name`) are not supported. Please use the channel ID (`UC…`).

## Translations

English is the source language. A German translation is included (`languages/`). WordPress 6.5+ loads the `.l10n.php` file; the `.po` file is its editable source.

## Installation and updates

1. Download `simple-youtube-embedding.zip` from [Releases](https://github.com/djakobi/simple-youtube-embedding/releases).
2. In WordPress: *Plugins → Add New → Upload Plugin*.

New versions then show up under *Plugins* like any other update. The plugin checks GitHub for the latest release every six hours.

## Releasing a new version

1. Bump the version in three places: `Version:` in the plugin header, `SYE_VERSION` in `simple-youtube-embedding.php`, and `Stable tag` in `readme.txt`. Add a changelog entry to `readme.txt`.
2. Commit and push.
3. Create a GitHub release with the tag `v1.2.3`. A workflow checks the version numbers, builds `simple-youtube-embedding.zip` and attaches it to the release.

The version number also busts browser caches, because CSS and JavaScript are loaded with `?ver=`.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
