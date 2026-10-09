<?php
/**
 * Unit test bootstrap: loads WordPress's HTML API from the johnpbloch/wordpress-core
 * dev dependency plus minimal stand-ins for the few WordPress functions the
 * checker and settings use, so their logic runs without a WordPress install.
 * The full plugin is exercised on a real WordPress by tests/e2e/smoke.mjs.
 *
 * @package OpenAccessToolbar
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text ) {
	return $text;
}

function _doing_it_wrong( $function_name, $message ) {
	throw new RuntimeException( $function_name . ': ' . $message );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function absint( $value ) {
	return abs( (int) $value );
}

// The HTML API decoder reads this table as a global, so declare it before loading it.
global $html5_named_character_references;
$oatb_core = dirname( __DIR__, 2 ) . '/vendor/johnpbloch/wordpress-core/wp-includes/';
require_once $oatb_core . 'class-wp-token-map.php';
foreach ( array( 'html5-named-character-references', 'class-wp-html-decoder', 'class-wp-html-attribute-token', 'class-wp-html-span', 'class-wp-html-text-replacement', 'class-wp-html-tag-processor' ) as $oatb_file ) {
	require_once $oatb_core . 'html-api/' . $oatb_file . '.php';
}

require_once dirname( __DIR__, 2 ) . '/includes/class-oatb-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oatb-checker.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oatb-scanner.php';
