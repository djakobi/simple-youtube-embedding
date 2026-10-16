/**
 * Simple YouTube Embedding
 * - Loads a video only after a click (overlay for grid/list, inline for live blocks).
 * - Pauses other <audio>/<video> elements and fires a "sye:play" event on document,
 *   so themes or custom players can react (e.g. pause an audio player).
 * - Updates countdown and status of live blocks.
 * - "Show more videos" reveals hidden entries step by step.
 */
( function () {
	'use strict';

	var strings = window.syeStrings || {};
	var LIVE_MS = ( parseFloat( strings.liveHours ) || 6 ) * 60 * 60 * 1000;
	var overlay = null;
	var opener = null;

	function t( key, fallback ) {
		return strings[ key ] || fallback;
	}

	function format( template ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return template.replace( /%(\d+\$)?s/g, function ( match, position ) {
			return position ? args[ parseInt( position, 10 ) - 1 ] : args[ i++ ];
		} );
	}

	function createIframe( videoId, title ) {
		var iframe = document.createElement( 'iframe' );
		iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent( videoId ) + '?autoplay=1&rel=0&modestbranding=1';
		iframe.title = title || 'YouTube';
		iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		iframe.allowFullscreen = true;
		iframe.className = 'sye-iframe';
		return iframe;
	}

	function beforePlay( videoId ) {
		document.querySelectorAll( 'audio, video' ).forEach( function ( media ) {
			if ( ! media.paused ) {
				media.pause();
			}
		} );
		document.dispatchEvent( new CustomEvent( 'sye:play', { detail: { videoId: videoId } } ) );
	}

	function createOverlay() {
		overlay = document.createElement( 'dialog' );
		overlay.className = 'sye sye-overlay';
		overlay.setAttribute( 'aria-label', t( 'video', 'Video' ) );

		var closeButton = document.createElement( 'button' );
		closeButton.type = 'button';
		closeButton.className = 'sye-close';
		closeButton.setAttribute( 'aria-label', t( 'close', 'Close video' ) );
		closeButton.textContent = '×';
		closeButton.addEventListener( 'click', closeOverlay );

		var frame = document.createElement( 'div' );
		frame.className = 'sye-player';

		var caption = document.createElement( 'p' );
		caption.className = 'sye-overlay-title';

		overlay.append( closeButton, frame, caption );

		// A click on the dimmed backdrop closes the overlay as well.
		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target === overlay ) {
				closeOverlay();
			}
		} );
		// Esc closes the dialog natively; remove the video in every case.
		overlay.addEventListener( 'close', function () {
			frame.textContent = '';
			if ( opener ) {
				opener.focus();
			}
		} );
		document.body.appendChild( overlay );
	}

	function openOverlay( button ) {
		if ( ! overlay ) {
			createOverlay();
		}
		var title = button.dataset.syeTitle || '';
		opener = button;
		overlay.querySelector( '.sye-player' ).replaceChildren( createIframe( button.dataset.syeVideo, title ) );
		overlay.querySelector( '.sye-overlay-title' ).textContent = title;
		overlay.showModal();
	}

	function closeOverlay() {
		if ( overlay && overlay.open ) {
			overlay.close();
		}
	}

	function play( event ) {
		var button = event.target.closest( '[data-sye-video]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		beforePlay( button.dataset.syeVideo );

		if ( button.dataset.syeMode === 'overlay' && typeof HTMLDialogElement === 'function' ) {
			openOverlay( button );
			return;
		}

		var frame = document.createElement( 'div' );
		frame.className = 'sye-player sye-playing';
		frame.appendChild( createIframe( button.dataset.syeVideo, button.getAttribute( 'aria-label' ) ) );
		button.replaceWith( frame );
	}

	function showMore( event ) {
		var button = event.target.closest( '.sye-more' );
		if ( ! button ) {
			return false;
		}
		var wrap = button.closest( '.sye-more-wrap' );
		var list = wrap && wrap.previousElementSibling;
		if ( ! list ) {
			return true;
		}

		var step = parseInt( button.dataset.syeStep, 10 ) || 3;
		var hidden = list.querySelectorAll( ':scope > .sye-card[hidden]' );
		var revealed = Array.prototype.slice.call( hidden, 0, step );
		revealed.forEach( function ( card ) {
			card.hidden = false;
		} );

		var remaining = hidden.length - revealed.length;
		if ( remaining > 0 ) {
			button.querySelector( '.sye-more-count' ).textContent = '(' + remaining + ')';
		} else {
			wrap.remove();
		}

		// Keyboard and screen reader users continue at the first new entry.
		var target = revealed[ 0 ] && revealed[ 0 ].querySelector( '.sye-player' );
		if ( target ) {
			target.focus( { preventScroll: true } );
		}
		return true;
	}

	function pad( number ) {
		return ( number < 10 ? '0' : '' ) + number;
	}

	function countdownText( ms ) {
		var s = Math.floor( ms / 1000 );
		var days = Math.floor( s / 86400 );
		var time = pad( Math.floor( ( s % 86400 ) / 3600 ) ) + ':' + pad( Math.floor( ( s % 3600 ) / 60 ) ) + ':' + pad( s % 60 );
		if ( days > 1 ) {
			return format( t( 'startsInDays', 'Starts in %1$s days, %2$s' ), days, time );
		}
		if ( days === 1 ) {
			return format( t( 'startsInDay', 'Starts in %1$s day, %2$s' ), days, time );
		}
		return format( t( 'startsIn', 'Starts in %s' ), time );
	}

	function updateLiveBlocks() {
		document.querySelectorAll( '.sye-live[data-sye-start]' ).forEach( function ( block ) {
			var start = Date.parse( block.dataset.syeStart );
			if ( isNaN( start ) ) {
				return;
			}
			var now = Date.now();
			var status = block.querySelector( '.sye-status' );
			var countdown = block.querySelector( '.sye-countdown' );
			var state, statusText, text;

			if ( now < start ) {
				state = 'upcoming';
				statusText = t( 'upcoming', 'Upcoming' );
				text = countdownText( start - now );
			} else if ( now < start + LIVE_MS ) {
				state = 'live';
				statusText = t( 'live', 'Live' );
				text = t( 'liveNow', 'Live now – click to play' );
			} else {
				state = 'recording';
				statusText = t( 'recording', 'Recording' );
				text = '';
			}

			block.dataset.syeState = state;
			if ( status && status.textContent !== statusText ) {
				status.textContent = statusText;
			}
			if ( countdown && countdown.textContent !== text ) {
				countdown.textContent = text;
			}
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		if ( ! showMore( event ) ) {
			play( event );
		}
	} );

	if ( document.querySelector( '.sye-live[data-sye-start]' ) ) {
		updateLiveBlocks();
		setInterval( updateLiveBlocks, 1000 );
	}
}() );
