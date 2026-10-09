// End-to-end smoke test on a real WordPress site.
//
// Needs: a WordPress install with this plugin active and an "admin"/"admin"
// administrator, served at BASE_URL; WP-CLI; Playwright with Chromium; axe-core.
//   WP_PATH=/path/to/wp WP_CLI="php wp-cli.phar --allow-root" BASE_URL=http://localhost:8899 node tests/e2e/smoke.mjs
// SHOTS=dir also saves the wordpress.org screenshots there.
//
// Covers: the visitor toolbar (keyboard, every tool, persistence, reset, its own
// axe audit), the settings screen, the content and theme checks, the skip link
// and focus fixes, and the statement page.

import { execSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createRequire } from 'node:module';

// require() honors NODE_PATH, so global installs work too.
const require = createRequire( import.meta.url );
const { chromium } = require( 'playwright' );
const axeSource = readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );

const WP_PATH = process.env.WP_PATH;
const WP_CLI = process.env.WP_CLI || 'wp';
const BASE = process.env.BASE_URL || 'http://localhost:8899';
const SHOTS = process.env.SHOTS || '';

let failures = 0;
const check = ( ok, label ) => {
	console.log( `${ ok ? 'PASS' : 'FAIL' }  ${ label }` );
	if ( ! ok ) failures++;
};
const wp = ( args ) => execSync( `${ WP_CLI } --path=${ WP_PATH } ${ args } 2>/dev/null`, { encoding: 'utf8' } ).trim();
const wpEval = ( code ) => {
	const file = join( mkdtempSync( join( tmpdir(), 'oatb-' ) ), 'eval.php' );
	writeFileSync( file, `<?php ${ code }` );
	return wp( `eval-file ${ file }` );
};

// Content with one of each problem, and a clean page.
const bad = wpEval( `echo wp_insert_post( array( 'post_status' => 'publish', 'post_title' => 'Bad post', 'post_content' => ${ JSON.stringify(
	'<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Too deep</h4><!-- /wp:heading -->' +
	'<!-- wp:image --><figure class="wp-block-image"><img src="/wp-content/uploads/cat.jpg"/></figure><!-- /wp:image -->' +
	'<!-- wp:html --><a href="https://example.com/fb"><i class="icon-facebook"></i></a><input type="text" name="q" placeholder="Search"><iframe src="https://player.vimeo.com/video/1"></iframe><button></button><!-- /wp:html -->'
) } ) );` );
wpEval( `wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Clean page', 'post_content' => ${ JSON.stringify(
	'<!-- wp:heading --><h2 class="wp-block-heading">Fine</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Read <a href="/about">about us</a>.</p><!-- /wp:paragraph -->'
) } ) );` );
const longPost = wpEval( `echo wp_insert_post( array( 'post_status' => 'publish', 'post_title' => 'Reading test', 'post_content' => ${ JSON.stringify(
	'<!-- wp:paragraph --><p id="probe">' + 'Lorem ipsum dolor sit amet. '.repeat( 40 ) + '</p><!-- /wp:paragraph -->'
) } ) );` );
// Twenty extra clean posts so the check needs more than one batch.
wpEval( `for ( $i = 0; $i < 20; $i++ ) { wp_insert_post( array( 'post_status' => 'publish', 'post_title' => 'Filler ' . $i, 'post_content' => '<p>Filler</p>' ) ); }` );

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || undefined } );
const context = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
// Keep the run offline and identical everywhere: the fixture embeds a Vimeo iframe.
await context.route( ( url ) => ! url.href.startsWith( BASE ), ( route ) => route.abort() );
const page = await context.newPage();
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );
// Ignore requests to outside hosts (fonts, Gravatar), which the test network may block.
page.on( 'console', ( m ) => m.type() === 'error' && ! /ERR_TUNNEL_CONNECTION_FAILED|ERR_NAME_NOT_RESOLVED|ERR_CONNECTION|Failed to load resource/.test( m.text() ) && errors.push( m.text() ) );

const postUrl = `${ BASE }/?p=${ longPost }`;
const shadow = ( selector ) => page.locator( `#oatb-root >> ${ selector }` );
const htmlClass = () => page.evaluate( () => document.documentElement.className );
const probeSize = () => page.evaluate( () => parseFloat( getComputedStyle( document.getElementById( 'probe' ) ).fontSize ) );

// ---------- Visitor toolbar ----------
await page.goto( postUrl );
check( await page.locator( '#oatb-root' ).count() === 1, 'toolbar added to the page' );
const launcher = shadow( '.launcher' );
check( await launcher.getAttribute( 'aria-expanded' ) === 'false', 'launcher starts collapsed' );
const box = await launcher.boundingBox();
check( box && box.x > 1100 && box.y > 750, `launcher at bottom right (${ box && Math.round( box.x ) },${ box && Math.round( box.y ) })` );
const scriptAttrs = await page.$eval( 'script#oatb-toolbar-js', ( s ) => ( { defer: s.defer, src: s.src } ) );
check( scriptAttrs.defer && /assets\/toolbar\.js/.test( scriptAttrs.src ), 'toolbar script is local and deferred' );

// Keyboard: focus the launcher, open with Enter, Escape closes and returns focus.
await launcher.focus();
await page.keyboard.press( 'Enter' );
check( await shadow( '.panel' ).isVisible(), 'Enter opens the panel' );
check( await launcher.getAttribute( 'aria-expanded' ) === 'true', 'aria-expanded true when open' );
const focusedInPanel = await page.evaluate( () => document.getElementById( 'oatb-root' ).shadowRoot.activeElement?.closest( '.panel' ) !== null );
check( focusedInPanel, 'focus moves into the panel' );
await page.keyboard.press( 'Escape' );
check( ! ( await shadow( '.panel' ).isVisible() ), 'Escape closes the panel' );
const focusedLauncher = await page.evaluate( () => document.getElementById( 'oatb-root' ).shadowRoot.activeElement?.classList.contains( 'launcher' ) );
check( focusedLauncher, 'focus returns to the launcher' );

await launcher.click();
const toolCount = await shadow( '[data-tool]' ).count();
check( toolCount === 11, `11 toggle tools plus text size (${ toolCount })` );

// The toolbar must pass its own audit.
await page.addScriptTag( { content: axeSource } );
const axe = await page.evaluate( async () => {
	const r = await window.axe.run( { include: [ [ '#oatb-root' ] ] }, { resultTypes: [ 'violations' ] } );
	return r.violations.map( ( v ) => `${ v.id } (${ v.nodes.length })` );
} );
check( axe.length === 0, `axe finds no violations in the open toolbar${ axe.length ? ': ' + axe.join( ', ' ) : '' }` );

if ( SHOTS ) await page.screenshot( { path: join( SHOTS, 'screenshot-1.png' ) } );

// Text size scales without compounding.
const before = await probeSize();
await shadow( '[data-size="1"]' ).click();
await shadow( '[data-size="1"]' ).click();
const after = await probeSize();
check( Math.abs( after - before * 1.2 ) < 0.6, `text size 120% (${ before }px to ${ after }px)` );
check( await shadow( 'output' ).textContent() === '120%', 'size announced as 120%' );

// Every class-based tool toggles its class and aria-pressed.
const classTools = [ 'line_height', 'letter_spacing', 'readable_font', 'grayscale', 'underline_links', 'highlight_focus', 'stop_animations', 'big_cursor' ];
for ( const tool of classTools ) {
	await shadow( `[data-tool="${ tool }"]` ).click();
	const cls = 'oatb-' + tool.replace( /_/g, '-' );
	check( ( await htmlClass() ).split( ' ' ).includes( cls ) && await shadow( `[data-tool="${ tool }"]` ).getAttribute( 'aria-pressed' ) === 'true', `${ tool } on` );
}
const lineHeight = await page.evaluate( () => getComputedStyle( document.getElementById( 'probe' ) ).lineHeight );
check( Math.abs( parseFloat( lineHeight ) / ( await probeSize() ) - 1.8 ) < 0.05, `line height 1.8 applied (${ lineHeight })` );
const launcherFont = await page.evaluate( () => getComputedStyle( document.getElementById( 'oatb-root' ).shadowRoot.querySelector( '.tool span' ) ).fontFamily );
check( ! /Verdana/.test( launcherFont ), 'page tools do not restyle the toolbar' );

// Contrast modes are exclusive.
await shadow( '[data-tool="contrast_dark"]' ).click();
check( ( await htmlClass() ).includes( 'oatb-contrast-dark' ), 'dark contrast on' );
const bg = await page.evaluate( () => getComputedStyle( document.getElementById( 'probe' ) ).backgroundColor );
check( bg === 'rgb(0, 0, 0)', `dark contrast paints black (${ bg })` );
await shadow( '[data-tool="contrast_light"]' ).click();
const cls = await htmlClass();
check( cls.includes( 'oatb-contrast-light' ) && ! cls.includes( 'oatb-contrast-dark' ), 'light contrast replaces dark' );
check( await shadow( '[data-tool="contrast_dark"]' ).getAttribute( 'aria-pressed' ) === 'false', 'dark button no longer pressed' );

// Reading guide follows the mouse.
await shadow( '[data-tool="reading_guide"]' ).click();
await page.mouse.move( 300, 400 );
const guideTop = await page.evaluate( () => document.getElementById( 'oatb-root' ).shadowRoot.querySelector( '.guide' ).style.top );
check( guideTop === '380px', `reading guide follows the mouse (${ guideTop })` );

// Choices persist across pages and are applied before paint.
await page.goto( `${ BASE }/?p=${ bad }` );
const early = await page.evaluate( () => document.documentElement.className );
check( early.includes( 'oatb-contrast-light' ) && early.includes( 'oatb-grayscale' ), 'choices restored on the next page' );
await page.goto( postUrl );
check( Math.abs( ( await probeSize() ) - before * 1.2 ) < 0.6, 'text size restored on the next page' );
const stored = await page.evaluate( () => localStorage.getItem( 'oatb-prefs' ) );
check( /"text":2/.test( stored ), 'stored in localStorage only' );
check( ( await context.cookies() ).every( ( c ) => ! /oatb/i.test( c.name ) ), 'no cookies set by the toolbar' );

// Reset clears everything.
await launcher.click();
await shadow( '.reset' ).click();
check( ! /oatb-/.test( await htmlClass() ), 'reset removes every page class' );
check( Math.abs( ( await probeSize() ) - before ) < 0.1, 'reset restores the text size' );
check( await shadow( '[aria-pressed="true"]' ).count() === 0, 'reset unpresses every tool' );

// ---------- Phones ----------
// The whole panel, header included, must fit the visible viewport.
for ( const [ width, height ] of [ [ 375, 667 ], [ 667, 375 ] ] ) {
	const phone = await browser.newContext( { viewport: { width, height }, isMobile: true, hasTouch: true } );
	await phone.route( ( url ) => ! url.href.startsWith( BASE ), ( route ) => route.abort() );
	const p = await phone.newPage();
	await p.goto( postUrl );
	await p.locator( '#oatb-root >> .launcher' ).click();
	const rect = await p.evaluate( () => {
		const r = document.getElementById( 'oatb-root' ).shadowRoot.querySelector( '.panel' ).getBoundingClientRect();
		return { top: r.top, bottom: r.bottom, h: window.innerHeight };
	} );
	check( rect.top >= 0 && rect.bottom <= rect.h, `panel fits a ${ width }x${ height } phone (top ${ Math.round( rect.top ) }, bottom ${ Math.round( rect.bottom ) } of ${ rect.h })` );
	check( await p.locator( '#oatb-root >> .close' ).isVisible(), `close button visible at ${ width }x${ height }` );
	const touchTools = await p.locator( '#oatb-root >> [data-tool="reading_guide"], [data-tool="big_cursor"]' ).evaluateAll( ( els ) => els.filter( ( e ) => e.offsetParent !== null ).length );
	check( touchTools === 0, 'reading guide and big cursor hidden on touch screens' );
	await phone.close();
}

// "Hide on small screens" must not leave a tool on with no toolbar to undo it.
const withHide = { enabled: true, hide_on_mobile: true, tools: [ 'text_size', 'line_height', 'underline_links' ], position: 'bottom-right', color: '#1d4ed8', size: 'medium' };
wp( `option update oatb_settings '${ JSON.stringify( withHide ) }' --format=json` );
const narrow = await browser.newContext( { viewport: { width: 1000, height: 800 } } );
await narrow.route( ( url ) => ! url.href.startsWith( BASE ), ( route ) => route.abort() );
await narrow.addInitScript( () => localStorage.setItem( 'oatb-prefs', JSON.stringify( { tools: { underline_links: true }, text: 2 } ) ) );
const n = await narrow.newPage();
await n.goto( postUrl );
const wideSize = await n.evaluate( () => parseFloat( getComputedStyle( document.getElementById( 'probe' ) ).fontSize ) );
check( ( await n.evaluate( () => document.documentElement.className ) ).includes( 'oatb-underline-links' ), 'wide screen: saved tool applied' );
await n.setViewportSize( { width: 500, height: 800 } );
await n.waitForFunction( () => ! document.documentElement.classList.contains( 'oatb-underline-links' ) );
check( true, 'narrowing below 600px lifts the saved tool while the toolbar is hidden' );
const narrowSize = await n.evaluate( () => parseFloat( getComputedStyle( document.getElementById( 'probe' ) ).fontSize ) );
check( narrowSize < wideSize, `and the text size (${ wideSize }px to ${ narrowSize }px)` );
await n.goto( postUrl );
check( ! ( await n.evaluate( () => document.documentElement.className ) ).includes( 'oatb-underline-links' ), 'small screen load: saved tool not restored' );
await n.setViewportSize( { width: 1000, height: 800 } );
await n.waitForFunction( () => document.documentElement.classList.contains( 'oatb-underline-links' ) );
check( true, 'widening again restores it' );
await narrow.close();
wp( 'option delete oatb_settings' );

// ---------- Admin ----------
await page.goto( `${ BASE }/wp-login.php` );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'admin' );
await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] ).catch( async ( e ) => {
	console.log( 'Login failed at ' + page.url() + ': ' + ( await page.textContent( 'body' ) ).replace( /\s+/g, ' ' ).slice( 0, 400 ) );
	throw e;
} );

// Content check across several batches.
await page.goto( `${ BASE }/wp-admin/tools.php?page=oatb-check` );
await page.click( '#oatb-start' );
await page.waitForSelector( '.oatb-results, .oatb-ok', { timeout: 60000 } );
const rows = await page.$$eval( '.oatb-results tbody tr', ( trs ) => trs.map( ( tr ) => tr.textContent.replace( /\s+/g, ' ' ) ) );
check( rows.length === 1 && rows[ 0 ].includes( 'Bad post' ), `only the bad post is listed (${ rows.length } rows)` );
const row = rows[ 0 ] || '';
for ( const label of [ 'Image without alt text', 'Link with no text', 'Button with no text', 'Skipped heading level', 'Form field without a label', 'Embedded frame without a title' ] ) {
	check( row.includes( label ), `content check finds: ${ label }` );
}
check( row.includes( 'cat.jpg' ) && row.includes( 'player.vimeo.com' ), 'issues carry context' );
check( /6 problems found in 1 item\b/.test( await page.textContent( '.oatb-wrap' ) ), 'summary counts' );
check( /Last check: 2[3-9] posts and pages/.test( await page.textContent( '.oatb-wrap' ) ), 'every published item counted' );

// Theme check through a real loopback request.
await page.click( '#oatb-site-check' );
await page.waitForSelector( '.oatb-site li, p.oatb-fail', { timeout: 30000 } );
if ( await page.locator( 'p.oatb-fail' ).count() ) console.log( await page.textContent( 'p.oatb-fail' ) );
const site = await page.$$eval( '.oatb-site li', ( lis ) => lis.map( ( li ) => li.className + ':' + li.textContent ) );
check( site.length === 4, `theme check reports 4 items (${ site.length })` );
check( site.some( ( s ) => s.startsWith( 'oatb-pass' ) && s.includes( 'Page language' ) ), 'theme check: language passes on a default theme' );
if ( SHOTS ) {
	await page.setViewportSize( { width: 1280, height: 1400 } );
	await page.screenshot( { path: join( SHOTS, 'screenshot-3.png' ) } );
}

// Settings: position, a removed tool, the site fixes.
await page.goto( `${ BASE }/wp-admin/options-general.php?page=open-access-toolbar` );
if ( SHOTS ) await page.screenshot( { path: join( SHOTS, 'screenshot-2.png' ) } );
await page.selectOption( '#oatb-position', 'top-left' );
await page.uncheck( 'input[value="grayscale"]' );
await page.check( 'input[name="oatb_settings[skip_link]"]' );
await page.check( 'input[name="oatb_settings[focus_outline]"]' );
await page.click( '#submit' );
await page.waitForSelector( '#setting-error-settings_updated, .notice-success' );
const saved = JSON.parse( wp( 'option get oatb_settings --format=json' ) );
check( saved.position === 'top-left' && ! saved.tools.includes( 'grayscale' ) && saved.skip_link === true, 'settings saved and sanitized' );

await page.goto( postUrl );
const leftBox = await shadow( '.launcher' ).boundingBox();
check( leftBox && leftBox.x < 100 && leftBox.y < 100, 'launcher moved to top left' );
check( await shadow( '[data-tool="grayscale"]' ).count() === 0, 'removed tool is gone' );
check( ( await htmlClass() ).includes( 'oatb-site-focus' ), 'focus outline fix on' );
const skipLinks = await page.locator( 'a.skip-link, a.oatb-skip-link' ).count();
check( skipLinks === 1, `exactly one skip link with the theme's own (${ skipLinks })` );

// A theme without a skip link gets ours.
const pluginFile = join( WP_PATH, 'wp-content/mu-plugins/no-skip.php' );
execSync( `mkdir -p ${ join( WP_PATH, 'wp-content/mu-plugins' ) }` );
writeFileSync( pluginFile, "<?php add_action( 'init', function () { remove_action( 'wp_footer', 'the_block_template_skip_link' ); remove_action( 'wp_enqueue_scripts', 'wp_enqueue_block_template_skip_link' ); }, 99 );" );
await page.goto( postUrl );
check( await page.locator( 'a.skip-link' ).count() === 0, 'core skip link removed for the test' );
const ours = page.locator( 'a.oatb-skip-link' );
check( await ours.count() === 1, 'plugin adds a skip link when the theme has none' );
await page.keyboard.press( 'Tab' );
check( await ours.evaluate( ( a ) => a === document.activeElement ), 'skip link is the first Tab stop' );
await page.keyboard.press( 'Enter' );
const target = await page.evaluate( () => document.activeElement && document.activeElement.tagName );
check( target === 'MAIN', `skip link moves focus to main (${ target })` );

// Statement page.
await page.goto( `${ BASE }/wp-admin/options-general.php?page=open-access-toolbar` );
await page.click( 'form[action*="admin-post.php"] input[type=submit]' );
await page.waitForURL( /post\.php\?post=\d+&action=edit/ );
const statementId = Number( new URL( page.url() ).searchParams.get( 'post' ) );
check( wp( `post get ${ statementId } --field=post_status` ) === 'draft', 'statement page created as a draft' );
check( /Feedback and contact/.test( wp( `post get ${ statementId } --field=post_content` ) ), 'statement outline content' );
check( JSON.parse( wp( 'option get oatb_settings --format=json' ) ).statement_page === statementId, 'statement page selected in settings' );
wp( `post update ${ statementId } --post_status=publish` );
await page.goto( postUrl );
await shadow( '.launcher' ).click();
const statementHref = await shadow( '.foot a' ).getAttribute( 'href' );
check( /page_id=|accessibility-statement/.test( statementHref || '' ), `toolbar links the published statement (${ statementHref })` );

// Turning the toolbar off removes it but keeps the fixes.
const off = JSON.parse( wp( 'option get oatb_settings --format=json' ) );
off.enabled = false;
wp( `option update oatb_settings '${ JSON.stringify( off ) }' --format=json` );
await page.goto( postUrl );
check( await page.locator( '#oatb-root' ).count() === 0, 'toolbar off: nothing rendered' );
check( await page.locator( 'a.oatb-skip-link' ).count() === 1, 'toolbar off: skip link fix still applies' );
off.skip_link = false;
off.focus_outline = false;
wp( `option update oatb_settings '${ JSON.stringify( off ) }' --format=json` );
const plain = await ( await fetch( postUrl ) ).text();
check( ! /oatb/.test( plain ), 'everything off: no plugin assets on the page' );

// Uninstall cleans up its options.
wp( 'plugin deactivate open-access-toolbar' );
wp( 'plugin uninstall open-access-toolbar --skip-delete' );
check( wp( 'option list --search=oatb_* --field=option_name' ).split( '\n' ).filter( Boolean ).length === 0, 'uninstall removes its options' );
execSync( `rm -f ${ pluginFile }` );

check( errors.length === 0, `no JavaScript errors${ errors.length ? ': ' + errors.join( ' | ' ) : '' }` );
const debugLog = join( WP_PATH, 'debug.log' );
const log = existsSync( debugLog )
	? readFileSync( debugLog, 'utf8' ).split( '\n' ).filter( ( l ) => /PHP (Fatal|Parse)/.test( l ) || ( /PHP (Notice|Warning|Deprecated)/.test( l ) && /plugins\/open-access-toolbar\//.test( l ) ) )
	: [];
check( log.length === 0, `no PHP errors, and no warnings or notices from the plugin${ log.length ? ': ' + log.join( ' | ' ) : '' }` );

await browser.close();
console.log( failures ? `\n${ failures } check(s) failed` : '\nAll checks passed' );
process.exit( failures ? 1 : 0 );
