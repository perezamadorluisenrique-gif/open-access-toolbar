<?php
/**
 * The plugin's single option: defaults, tool list and sanitizing.
 *
 * @package OpenAccessToolbar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings stored in the `oatb_settings` option.
 */
class OATB_Settings {

	const OPTION = 'oatb_settings';

	/**
	 * Toolbar positions the visitor button can take.
	 *
	 * @var string[]
	 */
	const POSITIONS = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' );

	/**
	 * Button sizes.
	 *
	 * @var string[]
	 */
	const SIZES = array( 'small', 'medium', 'large' );

	/**
	 * Tools the visitor panel can show, keyed by id, with their labels.
	 *
	 * Keys are also the names the front-end script and stylesheet use.
	 *
	 * @return array<string,string>
	 */
	public static function tools() {
		return array(
			'text_size'       => __( 'Text size', 'open-access-toolbar' ),
			'line_height'     => __( 'Line height', 'open-access-toolbar' ),
			'letter_spacing'  => __( 'Letter spacing', 'open-access-toolbar' ),
			'readable_font'   => __( 'Readable font', 'open-access-toolbar' ),
			'contrast_dark'   => __( 'High contrast (dark)', 'open-access-toolbar' ),
			'contrast_light'  => __( 'High contrast (light)', 'open-access-toolbar' ),
			'grayscale'       => __( 'Grayscale', 'open-access-toolbar' ),
			'underline_links' => __( 'Underline links', 'open-access-toolbar' ),
			'highlight_focus' => __( 'Highlight focus', 'open-access-toolbar' ),
			'stop_animations' => __( 'Stop animations', 'open-access-toolbar' ),
			'big_cursor'      => __( 'Big cursor', 'open-access-toolbar' ),
			'reading_guide'   => __( 'Reading guide', 'open-access-toolbar' ),
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'        => true,
			'position'       => 'bottom-right',
			'color'          => '#1d4ed8',
			'size'           => 'medium',
			'tools'          => array_keys( self::tools() ),
			'hide_on_mobile' => false,
			'skip_link'      => false,
			'focus_outline'  => false,
			'statement_page' => 0,
		);
	}

	/**
	 * Current settings merged over the defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $saved ) ? array_merge( self::defaults(), $saved ) : self::defaults() );
	}

	/**
	 * Sanitizes a settings array (from the form or the database).
	 *
	 * Missing checkboxes mean "off"; missing other keys fall back to defaults.
	 *
	 * @param mixed $input Raw settings.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		foreach ( array( 'enabled', 'hide_on_mobile', 'skip_link', 'focus_outline' ) as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] );
		}

		$out['position'] = isset( $input['position'] ) && in_array( $input['position'], self::POSITIONS, true ) ? $input['position'] : $defaults['position'];
		$out['size']     = isset( $input['size'] ) && in_array( $input['size'], self::SIZES, true ) ? $input['size'] : $defaults['size'];

		$color        = isset( $input['color'] ) ? self::sanitize_hex( (string) $input['color'] ) : '';
		$out['color'] = '' !== $color ? $color : $defaults['color'];

		$tools        = isset( $input['tools'] ) && is_array( $input['tools'] ) ? $input['tools'] : array();
		$out['tools'] = array_values( array_intersect( array_keys( self::tools() ), array_map( 'strval', $tools ) ) );

		$out['statement_page'] = isset( $input['statement_page'] ) ? absint( $input['statement_page'] ) : 0;

		return $out;
	}

	/**
	 * Accepts #rgb or #rrggbb, returns lowercase #rrggbb or ''.
	 *
	 * @param string $color Color.
	 * @return string
	 */
	public static function sanitize_hex( $color ) {
		$color = strtolower( trim( $color ) );
		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m ) ) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}
		return preg_match( '/^#[0-9a-f]{6}$/', $color ) ? $color : '';
	}

	/**
	 * Black or white, whichever reads better on the given background (WCAG contrast ratio).
	 *
	 * @param string $hex Background as #rrggbb.
	 * @return string '#000000' or '#ffffff'.
	 */
	public static function text_color_for( $hex ) {
		$lum = self::luminance( $hex );
		// Contrast against white is 1.05 / (L + 0.05); against black (L + 0.05) / 0.05.
		return ( 1.05 / ( $lum + 0.05 ) ) >= ( ( $lum + 0.05 ) / 0.05 ) ? '#ffffff' : '#000000';
	}

	/**
	 * WCAG contrast ratio between two #rrggbb colors.
	 *
	 * @param string $a First color.
	 * @param string $b Second color.
	 * @return float
	 */
	public static function contrast_ratio( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Relative luminance of a #rrggbb color (WCAG 2 definition).
	 *
	 * @param string $hex Color.
	 * @return float
	 */
	private static function luminance( $hex ) {
		$hex = ltrim( self::sanitize_hex( $hex ), '#' );
		if ( '' === $hex ) {
			return 0.0;
		}
		$channels = array();
		foreach ( array( 0, 2, 4 ) as $offset ) {
			$c          = hexdec( substr( $hex, $offset, 2 ) ) / 255;
			$channels[] = $c <= 0.04045 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}
