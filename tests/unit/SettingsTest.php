<?php
/**
 * Tests for OATB_Settings.
 *
 * @package OpenAccessToolbar
 */

use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {

	public function test_empty_input_turns_checkboxes_off_and_keeps_other_defaults() {
		$out = OATB_Settings::sanitize( array() );
		$this->assertFalse( $out['enabled'] );
		$this->assertSame( 'bottom-right', $out['position'] );
		$this->assertSame( '#1d4ed8', $out['color'] );
		$this->assertSame( array(), $out['tools'] );
	}

	public function test_invalid_values_fall_back() {
		$out = OATB_Settings::sanitize(
			array(
				'position'       => 'center<script>',
				'size'           => 'huge',
				'color'          => 'red',
				'tools'          => array( 'grayscale', 'evil', 'text_size' ),
				'statement_page' => '-7',
			)
		);
		$this->assertSame( 'bottom-right', $out['position'] );
		$this->assertSame( 'medium', $out['size'] );
		$this->assertSame( '#1d4ed8', $out['color'] );
		$this->assertSame( array( 'text_size', 'grayscale' ), $out['tools'], 'Unknown tools dropped, canonical order kept.' );
		$this->assertSame( 7, $out['statement_page'] );
	}

	public function test_hex_colors() {
		$this->assertSame( '#aabbcc', OATB_Settings::sanitize_hex( '#ABC' ) );
		$this->assertSame( '#123456', OATB_Settings::sanitize_hex( ' #123456 ' ) );
		$this->assertSame( '', OATB_Settings::sanitize_hex( '#12345' ) );
		$this->assertSame( '', OATB_Settings::sanitize_hex( 'javascript:alert(1)' ) );
	}

	public function test_text_color_has_readable_contrast() {
		$this->assertSame( '#ffffff', OATB_Settings::text_color_for( '#1d4ed8' ) );
		$this->assertSame( '#000000', OATB_Settings::text_color_for( '#fde047' ) );
		foreach ( array( '#1d4ed8', '#fde047', '#777777', '#ff0000', '#00ff00', '#000000', '#ffffff' ) as $bg ) {
			$this->assertGreaterThanOrEqual( 4.5, OATB_Settings::contrast_ratio( $bg, OATB_Settings::text_color_for( $bg ) ), $bg );
		}
	}

	public function test_contrast_ratio_extremes() {
		$this->assertEqualsWithDelta( 21.0, OATB_Settings::contrast_ratio( '#000000', '#ffffff' ), 0.01 );
		$this->assertEqualsWithDelta( 1.0, OATB_Settings::contrast_ratio( '#777777', '#777777' ), 0.01 );
	}
}
