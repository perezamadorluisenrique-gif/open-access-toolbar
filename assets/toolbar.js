/**
 * Open Access Toolbar: the visitor toolbar.
 *
 * Builds the button and panel inside a shadow root so theme CSS can't reach them,
 * applies the visitor's choices to the page, and remembers them in localStorage
 * (this browser only; nothing is sent anywhere).
 */
( function () {
	'use strict';

	var config = window.oatbConfig;
	if ( ! config ) {
		return;
	}

	var STORAGE_KEY = 'oatb-prefs';
	var TEXT_STEPS = [ 1, 1.1, 1.2, 1.35, 1.5 ];
	var EXCLUSIVE = { contrast_dark: 'contrast_light', contrast_light: 'contrast_dark' };
	var root = document.documentElement;

	var ICONS = {
		launcher: '<circle cx="12" cy="4" r="2"/><path d="M21 9h-6v12h-2v-6h-2v6H9V9H3V7h18z"/>',
		line_height: '<path d="M6 7h2.5L5 3.5 1.5 7H4v10H1.5L5 20.5 8.5 17H6zm4-2v2h12V5zm0 14h12v-2H10zm0-6h12v-2H10z"/>',
		letter_spacing: '<path d="M2 18h3l1-3h5l1 3h3L10.5 5h-4zm5-5.5L8.5 8l1.5 4.5zM17 21l-3-3h2v-4h2v4h2z" transform="translate(2 -2)"/>',
		readable_font: '<path d="M9.9 13.5h4.2L12 7.9zM20 2H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm-4 17-1.1-3H9.1L8 19H5.9L11 5h2l5.1 14z"/>',
		contrast_dark: '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18V4a8 8 0 0 1 0 16z"/>',
		contrast_light: '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 0 1 0-16z"/>',
		grayscale: '<path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9a1.5 1.5 0 0 0 1.1-2.5 1.5 1.5 0 0 1 1.1-2.5H16a5 5 0 0 0 5-5c0-4.4-4-8-9-8zm-5.5 9a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3-4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3 4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>',
		underline_links: '<path d="M12 17a6 6 0 0 0 6-6V3h-2.5v8a3.5 3.5 0 0 1-7 0V3H6v8a6 6 0 0 0 6 6zm-7 2v2h14v-2z"/>',
		highlight_focus: '<path d="M3 3h6v2H5v4H3zm12 0h6v6h-2V5h-4zM3 15h2v4h4v2H3zm16 4h-4v2h6v-6h-2zM12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/>',
		stop_animations: '<path d="M6 5h4v14H6zm8 0h4v14h-4z"/>',
		big_cursor: '<path d="M5 2l14 10.7-6.2.9 3.6 7.1-2.7 1.4-3.6-7.2L5 19z"/>',
		reading_guide: '<path d="M2 10h20v4H2zm0-5h20v2H2zm0 12h20v2H2z"/>'
	};

	function svg( name ) {
		return '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' + ( ICONS[ name ] || '' ) + '</svg>';
	}

	function load() {
		try {
			var saved = JSON.parse( window.localStorage.getItem( STORAGE_KEY ) || '{}' );
			return saved && typeof saved === 'object' ? saved : {};
		} catch ( e ) {
			return {};
		}
	}

	function save() {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( prefs ) );
		} catch ( e ) {
			// Private mode or storage full: the choices still apply to this page view.
		}
	}

	var prefs = load();
	prefs.tools = prefs.tools && typeof prefs.tools === 'object' ? prefs.tools : {};
	prefs.text = parseInt( prefs.text, 10 ) || 0;

	var enabled = {};
	( config.tools || [] ).forEach( function ( tool ) {
		enabled[ tool.id ] = tool;
	} );

	function cssClass( id ) {
		return 'oatb-' + id.replace( /_/g, '-' );
	}

	/* ---------- Text size: scales each element that holds text, from its original size. ---------- */

	var originals = null;

	function textElements() {
		var list = [];
		var walker = document.createTreeWalker( document.body, NodeFilter.SHOW_TEXT, {
			acceptNode: function ( node ) {
				return node.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
			}
		} );
		var seen = new Set();
		while ( walker.nextNode() ) {
			var el = walker.currentNode.parentElement;
			if ( ! el || seen.has( el ) || el.closest( '#oatb-root, script, style, noscript, svg' ) ) {
				continue;
			}
			seen.add( el );
			list.push( el );
		}
		return list;
	}

	function applyTextSize() {
		if ( ! enabled.text_size ) {
			return;
		}
		var step = Math.max( 0, Math.min( TEXT_STEPS.length - 1, prefs.text ) );
		prefs.text = step;
		if ( ! originals ) {
			if ( step === 0 ) {
				return;
			}
			// Read every size before changing any, so nested elements don't compound.
			originals = textElements().map( function ( el ) {
				return {
					el: el,
					size: parseFloat( window.getComputedStyle( el ).fontSize ) || 16,
					inline: el.style.getPropertyValue( 'font-size' ),
					priority: el.style.getPropertyPriority( 'font-size' )
				};
			} );
		}
		originals.forEach( function ( item ) {
			if ( step === 0 ) {
				if ( item.inline ) {
					item.el.style.setProperty( 'font-size', item.inline, item.priority );
				} else {
					item.el.style.removeProperty( 'font-size' );
				}
			} else {
				item.el.style.setProperty( 'font-size', ( item.size * TEXT_STEPS[ step ] ).toFixed( 2 ) + 'px', 'important' );
			}
		} );
		if ( step === 0 ) {
			originals = null;
		}
	}

	/* ---------- Script-driven tools. ---------- */

	var guide = null;

	function moveGuide( event ) {
		if ( guide ) {
			guide.style.top = ( event.clientY - 20 ) + 'px';
		}
	}

	function applyReadingGuide( on ) {
		if ( ! guide ) {
			return;
		}
		guide.hidden = ! on;
		if ( on ) {
			document.addEventListener( 'mousemove', moveGuide, { passive: true } );
		} else {
			document.removeEventListener( 'mousemove', moveGuide );
		}
	}

	var pausedMedia = [];

	function applyStopAnimations( on ) {
		if ( on ) {
			document.querySelectorAll( 'video[autoplay], audio[autoplay]' ).forEach( function ( media ) {
				if ( ! media.paused ) {
					media.pause();
					pausedMedia.push( media );
				}
			} );
		} else {
			pausedMedia.forEach( function ( media ) {
				var played = media.play();
				if ( played && played.catch ) {
					played.catch( function () {} );
				}
			} );
			pausedMedia = [];
		}
	}

	function applyTool( id, on ) {
		var tool = enabled[ id ];
		if ( ! tool ) {
			return;
		}
		if ( tool.css ) {
			root.classList.toggle( cssClass( id ), on );
		}
		if ( id === 'reading_guide' ) {
			applyReadingGuide( on );
		}
		if ( id === 'stop_animations' ) {
			applyStopAnimations( on );
		}
	}

	/* ---------- The panel. ---------- */

	var shadow;
	var launcher;
	var panel;
	var output;

	function styles() {
		var c = config.color;
		var t = config.textColor;
		var side = config.position.indexOf( 'left' ) > -1 ? 'left' : 'right';
		var vertical = config.position.indexOf( 'top' ) === 0 ? 'top' : 'bottom';
		var sizes = { small: 44, medium: 52, large: 64 };
		var s = sizes[ config.size ] || 52;
		return [
			':host{all:initial;}',
			'*{box-sizing:border-box;}',
			'.wrap{position:fixed;' + vertical + ':16px;' + side + ':16px;z-index:2147483646;font:400 15px/1.4 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#111;letter-spacing:normal;word-spacing:normal;text-align:left;}',
			'.launcher{width:' + s + 'px;height:' + s + 'px;border-radius:50%;border:2px solid #fff;background:' + c + ';color:' + t + ';cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(0,0,0,.35);padding:0;}',
			'.launcher svg{width:' + Math.round( s * 0.6 ) + 'px;height:' + Math.round( s * 0.6 ) + 'px;fill:currentColor;}',
			'button:focus-visible,a:focus-visible{outline:3px solid #f59e0b;outline-offset:2px;}',
			'.panel{position:absolute;' + side + ':0;' + ( vertical === 'top' ? 'top' : 'bottom' ) + ':' + ( s + 12 ) + 'px;width:340px;max-width:calc(100vw - 32px);max-height:calc(100vh - ' + ( s + 48 ) + 'px);overflow:auto;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.3);border:1px solid #d1d5db;}',
			'.panel[hidden]{display:none;}',
			'.head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:' + c + ';color:' + t + ';border-radius:11px 11px 0 0;}',
			'.title{font-weight:700;font-size:16px;margin:0;}',
			'.close{background:transparent;border:0;color:inherit;font-size:24px;line-height:1;cursor:pointer;width:36px;height:36px;border-radius:6px;}',
			'.body{padding:12px;}',
			'.size{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;margin-bottom:10px;}',
			'.size .label{font-weight:600;}',
			'.size .ctrl{display:flex;align-items:center;gap:6px;}',
			'.size button{width:40px;height:40px;border-radius:8px;border:1px solid #9ca3af;background:#f9fafb;color:#111;font-weight:700;font-size:16px;cursor:pointer;}',
			'.size button:disabled{opacity:.45;cursor:default;}',
			'.size output{min-width:3.2em;text-align:center;font-variant-numeric:tabular-nums;}',
			'.grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}',
			'.tool{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;min-height:76px;padding:8px;border-radius:8px;border:1px solid #d1d5db;background:#f9fafb;color:#111;font:inherit;font-size:13px;cursor:pointer;text-align:center;}',
			'.tool svg{width:24px;height:24px;fill:currentColor;}',
			'.tool[aria-pressed="true"]{background:' + c + ';color:' + t + ';border-color:' + c + ';}',
			'.foot{display:flex;flex-direction:column;gap:8px;margin-top:12px;}',
			'.reset{width:100%;padding:10px;border-radius:8px;border:1px solid #9ca3af;background:#fff;color:#111;font:inherit;font-weight:600;cursor:pointer;}',
			'.note{font-size:12px;color:#4b5563;margin:0;text-align:center;}',
			'.foot a{color:#1d4ed8;text-align:center;}',
			'.guide{position:fixed;left:0;right:0;height:40px;pointer-events:none;border-top:3px solid #111;border-bottom:3px solid #111;background:rgba(255,235,59,.18);z-index:2147483645;}',
			'.guide[hidden]{display:none;}',
			config.hideOnMobile ? '@media (max-width:600px){.wrap{display:none;}}' : '',
			'@media (prefers-reduced-motion:no-preference){.launcher{transition:transform .15s;}.launcher:hover{transform:scale(1.06);}}'
		].join( '' );
	}

	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	function updateSize() {
		if ( ! output ) {
			return;
		}
		output.textContent = Math.round( TEXT_STEPS[ prefs.text ] * 100 ) + '%';
		shadow.querySelector( '[data-size="-1"]' ).disabled = prefs.text === 0;
		shadow.querySelector( '[data-size="1"]' ).disabled = prefs.text === TEXT_STEPS.length - 1;
	}

	function updatePressed() {
		shadow.querySelectorAll( '[data-tool]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', prefs.tools[ button.getAttribute( 'data-tool' ) ] === true ? 'true' : 'false' );
		} );
	}

	function openPanel( open ) {
		panel.hidden = ! open;
		launcher.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			panel.querySelector( 'button' ).focus();
		}
	}

	function build() {
		var host = document.createElement( 'div' );
		host.id = 'oatb-root';
		document.body.appendChild( host );
		shadow = host.attachShadow( { mode: 'open' } );

		var i18n = config.i18n;
		var html = '<style>' + styles() + '</style><div class="wrap"' + ( config.rtl ? ' dir="rtl"' : '' ) + '>';
		html += '<div class="panel" id="panel" role="dialog" aria-modal="false" aria-labelledby="oatb-title" hidden>';
		html += '<div class="head"><p class="title" id="oatb-title">' + escapeHtml( i18n.title ) + '</p>';
		html += '<button type="button" class="close" aria-label="' + escapeHtml( i18n.close ) + '"><span aria-hidden="true">&times;</span></button></div><div class="body">';

		if ( enabled.text_size ) {
			html += '<div class="size" role="group" aria-labelledby="oatb-size-label"><span class="label" id="oatb-size-label">' + escapeHtml( enabled.text_size.label ) + '</span><span class="ctrl">';
			html += '<button type="button" data-size="-1" aria-label="' + escapeHtml( i18n.smaller ) + '">A&minus;</button>';
			html += '<output aria-live="polite"></output>';
			html += '<button type="button" data-size="1" aria-label="' + escapeHtml( i18n.larger ) + '">A+</button></span></div>';
		}

		html += '<div class="grid">';
		( config.tools || [] ).forEach( function ( tool ) {
			if ( tool.id === 'text_size' ) {
				return;
			}
			html += '<button type="button" class="tool" data-tool="' + escapeHtml( tool.id ) + '" aria-pressed="false">' + svg( tool.id ) + '<span>' + escapeHtml( tool.label ) + '</span></button>';
		} );
		html += '</div><div class="foot"><button type="button" class="reset">' + escapeHtml( i18n.reset ) + '</button>';
		if ( config.statementUrl ) {
			html += '<a href="' + escapeHtml( config.statementUrl ) + '">' + escapeHtml( i18n.statement ) + '</a>';
		}
		html += '<p class="note">' + escapeHtml( i18n.note ) + '</p></div></div></div>';
		html += '<button type="button" class="launcher" aria-expanded="false" aria-controls="panel" aria-label="' + escapeHtml( i18n.open ) + '">' + svg( 'launcher' ) + '</button>';
		html += '</div><div class="guide" hidden></div>';
		shadow.innerHTML = html;

		launcher = shadow.querySelector( '.launcher' );
		panel = shadow.querySelector( '.panel' );
		output = shadow.querySelector( 'output' );
		guide = shadow.querySelector( '.guide' );

		launcher.addEventListener( 'click', function () {
			openPanel( panel.hidden );
		} );
		shadow.querySelector( '.close' ).addEventListener( 'click', function () {
			openPanel( false );
			launcher.focus();
		} );
		shadow.querySelector( '.wrap' ).addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && ! panel.hidden ) {
				event.preventDefault();
				openPanel( false );
				launcher.focus();
			}
		} );

		shadow.querySelectorAll( '[data-size]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				prefs.text += parseInt( button.getAttribute( 'data-size' ), 10 );
				applyTextSize();
				updateSize();
				save();
			} );
		} );

		shadow.querySelectorAll( '[data-tool]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var id = button.getAttribute( 'data-tool' );
				var on = prefs.tools[ id ] !== true;
				if ( on && EXCLUSIVE[ id ] && prefs.tools[ EXCLUSIVE[ id ] ] ) {
					prefs.tools[ EXCLUSIVE[ id ] ] = false;
					applyTool( EXCLUSIVE[ id ], false );
				}
				prefs.tools[ id ] = on;
				applyTool( id, on );
				updatePressed();
				save();
			} );
		} );

		shadow.querySelector( '.reset' ).addEventListener( 'click', function () {
			Object.keys( prefs.tools ).forEach( function ( id ) {
				applyTool( id, false );
			} );
			prefs.tools = {};
			prefs.text = 0;
			applyTextSize();
			updatePressed();
			updateSize();
			save();
		} );
	}

	/* ---------- Site options. ---------- */

	function addSkipLink() {
		var existing = document.querySelector( 'a.skip-link, a[href="#content"], a[href="#main"], a[href="#primary"], a[href="#wp--skip-link--target"]' );
		if ( existing ) {
			return;
		}
		var target = document.querySelector( 'main, [role="main"], #content, #primary, #main' );
		if ( ! target ) {
			return;
		}
		if ( ! target.id ) {
			target.id = 'oatb-main';
		}
		if ( ! target.hasAttribute( 'tabindex' ) ) {
			target.setAttribute( 'tabindex', '-1' );
		}
		var link = document.createElement( 'a' );
		link.className = 'oatb-skip-link';
		link.href = '#' + target.id;
		link.textContent = config.i18n.skip;
		document.body.insertBefore( link, document.body.firstChild );
	}

	function init() {
		if ( config.focusOutline ) {
			root.classList.add( 'oatb-site-focus' );
		}
		if ( config.skipLink ) {
			addSkipLink();
		}
		if ( ! config.toolbar ) {
			return;
		}
		build();
		Object.keys( prefs.tools ).forEach( function ( id ) {
			if ( prefs.tools[ id ] === true ) {
				applyTool( id, true );
			}
		} );
		applyTextSize();
		updatePressed();
		updateSize();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
