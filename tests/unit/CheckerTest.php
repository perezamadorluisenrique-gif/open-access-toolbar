<?php
/**
 * Tests for OATB_Checker.
 *
 * @package OpenAccessToolbar
 */

use PHPUnit\Framework\TestCase;

class CheckerTest extends TestCase {

	private function types( $html, $start = 1 ) {
		return array_column( OATB_Checker::check( $html, $start ), 'type' );
	}

	public function test_clean_content_has_no_issues() {
		$html = '<h2>Intro</h2><p>Hello <a href="/about">about us</a>.</p><figure><img src="/a.jpg" alt="A red bike"></figure>'
			. '<h3>Details</h3><label for="e">Email</label><input id="e" type="email"><button>Send</button>'
			. '<iframe src="https://www.youtube.com/embed/x" title="Video: tour"></iframe>';
		$this->assertSame( array(), OATB_Checker::check( $html ) );
	}

	public function test_empty_input_returns_nothing() {
		$this->assertSame( array(), OATB_Checker::check( "  \n " ) );
	}

	public function test_missing_alt_is_reported_with_file_name() {
		$issues = OATB_Checker::check( '<img src="https://example.com/wp-content/uploads/2026/10/photo.jpg?x=1">' );
		$this->assertSame( array( array( 'type' => 'img_alt', 'context' => 'photo.jpg' ) ), $issues );
	}

	public function test_empty_alt_is_decorative_not_an_issue() {
		$this->assertSame( array(), $this->types( '<img src="a.png" alt=""><img src="b.png" alt>' ) );
	}

	public function test_presentational_or_hidden_images_are_skipped() {
		$this->assertSame( array(), $this->types( '<img src="a.png" role="presentation"><img src="b.png" aria-hidden="true">' ) );
	}

	public function test_image_named_by_aria_label_is_fine() {
		$this->assertSame( array(), $this->types( '<img src="a.png" aria-label="Logo">' ) );
	}

	public function test_link_with_no_text_is_reported() {
		$issues = OATB_Checker::check( '<a href="https://example.com/x"><i class="icon-x"></i></a>' );
		$this->assertSame( array( array( 'type' => 'empty_link', 'context' => 'https://example.com/x' ) ), $issues );
	}

	public function test_link_with_only_nbsp_is_empty() {
		$this->assertSame( array( 'empty_link' ), $this->types( '<a href="/x">&nbsp; </a>' ) );
	}

	public function test_image_link_named_by_alt() {
		$this->assertSame( array(), $this->types( '<a href="/x"><img src="a.png" alt="Home"></a>' ) );
	}

	public function test_image_link_with_empty_alt_is_an_empty_link() {
		$this->assertSame( array( 'empty_link' ), $this->types( '<a href="/x"><img src="a.png" alt=""></a>' ) );
	}

	public function test_link_named_by_aria_label_or_svg_title() {
		$this->assertSame( array(), $this->types( '<a href="/x" aria-label="Facebook"><svg></svg></a>' ) );
		$this->assertSame( array(), $this->types( '<a href="/x"><svg><title>Facebook</title><path d=""/></svg></a>' ) );
		$this->assertSame( array(), $this->types( '<a href="/x"><svg aria-label="Facebook"></svg></a>' ) );
	}

	public function test_anchor_without_href_is_not_a_link() {
		$this->assertSame( array(), $this->types( '<a id="top"></a>' ) );
	}

	public function test_empty_button() {
		$this->assertSame( array( 'empty_button' ), $this->types( '<button class="close"><span class="x"></span></button>' ) );
		$this->assertSame( array(), $this->types( '<button aria-label="Close"><span class="x"></span></button>' ) );
	}

	public function test_heading_skip_from_title() {
		$issues = OATB_Checker::check( '<h3>Starts too deep</h3>' );
		$this->assertSame( 'heading_skip', $issues[0]['type'] );
		$this->assertSame( 'H1 followed by H3', $issues[0]['context'] );
	}

	public function test_heading_skip_inside_content_and_going_back_up_is_fine() {
		$this->assertSame( array( 'heading_skip' ), $this->types( '<h2>A</h2><h4>B</h4><h2>C</h2><h3>D</h3>' ) );
	}

	public function test_heading_start_zero_disables_title_assumption() {
		$this->assertSame( array(), $this->types( '<h3>Widget</h3>', 0 ) );
	}

	public function test_empty_heading() {
		$this->assertSame( array( 'empty_heading' ), $this->types( '<h2> </h2>' ) );
	}

	public function test_unlabelled_fields_are_reported() {
		$issues = OATB_Checker::check( '<input type="text" name="q" placeholder="Search"><select name="s"></select><textarea></textarea>' );
		$this->assertSame( array( 'form_label', 'form_label', 'form_label' ), array_column( $issues, 'type' ) );
		$this->assertSame( 'input[text] name="q" placeholder="Search"', $issues[0]['context'] );
	}

	public function test_field_labelled_by_wrapping_label_for_or_aria() {
		$html = '<label>Name <input type="text"></label>'
			. '<input id="later" type="text"><label for="later">Later label</label>'
			. '<input type="search" aria-label="Search">'
			. '<input type="hidden" name="h"><input type="submit" value="Go">';
		$this->assertSame( array(), $this->types( $html ) );
	}

	public function test_label_for_other_field_does_not_count() {
		$this->assertSame( array( 'form_label' ), $this->types( '<label for="a">A</label><input id="b">' ) );
	}

	public function test_iframe_without_title_reports_host() {
		$issues = OATB_Checker::check( '<iframe src="https://player.vimeo.com/video/1"></iframe>' );
		$this->assertSame( array( array( 'type' => 'iframe_title', 'context' => 'player.vimeo.com' ) ), $issues );
	}

	public function test_unclosed_link_is_still_judged() {
		$this->assertSame( array( 'empty_link' ), $this->types( '<p><a href="/x"></p>' ) );
	}

	public function test_text_inside_script_does_not_name_a_link() {
		$this->assertSame( array( 'empty_link' ), $this->types( '<a href="/x"><script>var a = "text";</script></a>' ) );
	}

	public function test_long_context_is_shortened() {
		$issues = OATB_Checker::check( '<a href="https://example.com/' . str_repeat( 'a', 200 ) . '"></a>' );
		$this->assertSame( 90, strlen( $issues[0]['context'] ) );
		$this->assertStringEndsWith( '...', $issues[0]['context'] );
	}

	public function test_every_type_has_label_and_fix() {
		foreach ( OATB_Checker::types() as $type ) {
			$this->assertNotEmpty( $type['label'] );
			$this->assertNotEmpty( $type['fix'] );
		}
	}
}
