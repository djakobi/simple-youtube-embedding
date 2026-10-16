=== Simple YouTube Embedding ===
Contributors: djakobi
Tags: youtube, video, playlist, live stream, privacy
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, privacy-friendly YouTube grid, list and live stream embeds – no API key required.

== Description ==

* Grid or numbered list with the latest videos of a channel or playlist.
* Live stream block with countdown and "Upcoming", "Live" and "Recording" states.
* Thumbnails are stored locally; videos only load from youtube-nocookie.com after a click.
* Videos from grids and lists open in a large overlay.
* Starting a video pauses other audio/video elements and fires a `sye:play` event.
* German translation included.

Shortcodes:

`[sye_grid count="6"]`
`[sye_list playlist="PL…" count="9" visible="3" order="playlist"]`
`[sye_live video="VIDEO_ID" start="2026-11-01 10:00" title="Sunday service"]`

Full documentation: https://github.com/djakobi/simple-youtube-embedding

== Changelog ==

= 2.0.0 =
* Renamed to Simple YouTube Embedding. New shortcodes `[sye_grid]`, `[sye_list]`, `[sye_live]` with English attributes (`count`, `visible`, `order`, `channel`, `columns`, `title`). Shortcodes from 1.x are no longer supported.
* New settings page for a default channel ID.
* All texts in English, German translation included.
* Instead of a theme-specific player integration, other audio/video elements are paused and a `sye:play` event is fired.
* Calendar icon is now an inline SVG (no icon font required).

= 1.3.0 =
* "Show more videos" for grid and list.

= 1.2.0 =
* List view with numbering and selectable order. Updates from GitHub releases.

= 1.1.0 =
* Card layout, overlay for videos.

= 1.0.0 =
* First version: grid and live stream.
