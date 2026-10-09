<?php
/**
 * Tests for the page-level checks in OATB_Scanner.
 *
 * @package OpenAccessToolbar
 */

use PHPUnit\Framework\TestCase;

class ScannerTest extends TestCase {

	private function statuses( $html ) {
		return array_column( OATB_Scanner::analyze_page( $html ), 'status', 'id' );
	}

	public function test_good_page_passes_everything() {
		$html = '<!doctype html><html lang="en-US"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
			. '<body><a class="skip-link" href="#content">Skip</a><nav><a href="/">Home</a></nav><main id="content"><p>Hi</p></main></body></html>';
		$this->assertSame( array( 'lang' => 'pass', 'zoom' => 'pass', 'skip' => 'pass', 'main' => 'pass' ), $this->statuses( $html ) );
	}

	public function test_bad_page_fails_and_warns() {
		$html = '<html><head><meta name="viewport" content="width=device-width, user-scalable=no"></head>'
			. '<body><a href="/">Home</a><a href="/a">A</a><div id="content"></div></body></html>';
		$this->assertSame( array( 'lang' => 'fail', 'zoom' => 'fail', 'skip' => 'warn', 'main' => 'warn' ), $this->statuses( $html ) );
	}

	public function test_role_main_counts_as_landmark() {
		$this->assertSame( 'pass', $this->statuses( '<html lang="es"><body><div role="main"></div></body></html>' )['main'] );
	}

	public function test_anchor_link_after_the_first_five_is_not_a_skip_link() {
		$html = '<html lang="en"><body>' . str_repeat( '<a href="/x">x</a>', 5 ) . '<a href="#content">Skip</a></body></html>';
		$this->assertSame( 'warn', $this->statuses( $html )['skip'] );
	}

	public function test_bare_hash_is_not_a_skip_link() {
		$this->assertSame( 'warn', $this->statuses( '<html lang="en"><body><a href="#">Menu</a></body></html>' )['skip'] );
	}

	/**
	 * @dataProvider viewports
	 */
	public function test_viewport_zoom( $content, $allowed ) {
		$this->assertSame( $allowed, OATB_Scanner::viewport_allows_zoom( $content ) );
	}

	public function viewports() {
		return array(
			array( 'width=device-width, initial-scale=1', true ),
			array( 'width=device-width, user-scalable=no', false ),
			array( 'width=device-width,user-scalable=0', false ),
			array( 'width=device-width, user-scalable=yes', true ),
			array( 'width=device-width, maximum-scale=1', false ),
			array( 'width=device-width, maximum-scale=1.5', false ),
			array( 'width=device-width, maximum-scale=5', true ),
		);
	}
}
