<?php
/**
 * Front end: loads the toolbar script and stylesheet for visitors.
 *
 * The toolbar itself is built by assets/toolbar.js inside a shadow root, so
 * theme styles cannot break it and it cannot break the theme.
 *
 * @package OpenAccessToolbar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end loader.
 */
class OATB_Frontend {

	/**
	 * Tools that work by adding a class to <html>; the rest are handled by script.
	 *
	 * @var string[]
	 */
	const CLASS_TOOLS = array(
		'line_height',
		'letter_spacing',
		'readable_font',
		'contrast_dark',
		'contrast_light',
		'grayscale',
		'underline_links',
		'highlight_focus',
		'stop_animations',
		'big_cursor',
	);

	/**
	 * Hooks the front end.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the assets when the toolbar or one of the site fixes is on.
	 */
	public function enqueue() {
		$settings = OATB_Settings::get();
		$toolbar  = $settings['enabled'] && ! empty( $settings['tools'] );

		/**
		 * Filters whether the visitor toolbar shows on the current request.
		 *
		 * @param bool $show Whether to show it.
		 */
		$toolbar = (bool) apply_filters( 'oatb_show_toolbar', $toolbar );

		if ( ! $toolbar && ! $settings['skip_link'] && ! $settings['focus_outline'] ) {
			return;
		}

		wp_enqueue_style( 'oatb-page', OATB_URL . 'assets/page.css', array(), OATB_VERSION );

		if ( $toolbar ) {
			// Applies the visitor's saved choices before the page paints, so there is no flash.
			wp_register_script( 'oatb-early', false, array(), OATB_VERSION, false );
			wp_enqueue_script( 'oatb-early' );
			wp_add_inline_script( 'oatb-early', $this->early_script( $settings['tools'], $settings['hide_on_mobile'] ) );
		}

		wp_enqueue_script(
			'oatb-toolbar',
			OATB_URL . 'assets/toolbar.js',
			array(),
			OATB_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script( 'oatb-toolbar', 'window.oatbConfig = ' . wp_json_encode( $this->config( $settings, $toolbar ) ) . ';', 'before' );
	}

	/**
	 * Small inline script that restores the visitor's class-based choices from localStorage.
	 *
	 * @param string[] $tools          Enabled tools.
	 * @param bool     $hide_on_mobile Whether the toolbar is hidden on small screens (then nothing is restored there).
	 * @return string
	 */
	private function early_script( array $tools, $hide_on_mobile ) {
		$allowed = array_values( array_intersect( self::CLASS_TOOLS, $tools ) );
		$skip    = $hide_on_mobile ? 'if(window.matchMedia&&matchMedia("(max-width: 600px)").matches){return;}' : '';
		return '(function(){try{' . $skip . 'var a=' . wp_json_encode( $allowed ) . ',p=JSON.parse(localStorage.getItem("oatb-prefs")||"{}"),t=p&&p.tools||{},c=document.documentElement.classList;'
			. 'for(var i=0;i<a.length;i++){if(t[a[i]]===true){c.add("oatb-"+a[i].replace(/_/g,"-"));}}}catch(e){}})();';
	}

	/**
	 * Configuration handed to toolbar.js.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param bool                $toolbar  Whether the toolbar shows.
	 * @return array<string,mixed>
	 */
	private function config( array $settings, $toolbar ) {
		$labels = OATB_Settings::tools();
		$tools  = array();
		if ( $toolbar ) {
			foreach ( $settings['tools'] as $key ) {
				$tools[] = array(
					'id'    => $key,
					'label' => $labels[ $key ],
					'css'   => in_array( $key, self::CLASS_TOOLS, true ),
				);
			}
		}

		$statement = '';
		if ( $settings['statement_page'] && 'publish' === get_post_status( $settings['statement_page'] ) ) {
			$statement = get_permalink( $settings['statement_page'] );
		}

		return array(
			'toolbar'      => $toolbar,
			'tools'        => $tools,
			'position'     => $settings['position'],
			'size'         => $settings['size'],
			'color'        => $settings['color'],
			'textColor'    => OATB_Settings::text_color_for( $settings['color'] ),
			'hideOnMobile' => $settings['hide_on_mobile'],
			'skipLink'     => $settings['skip_link'],
			'focusOutline' => $settings['focus_outline'],
			'statementUrl' => $statement ? esc_url_raw( $statement ) : '',
			'rtl'          => is_rtl(),
			'i18n'         => array(
				'open'      => __( 'Accessibility tools', 'open-access-toolbar' ),
				'title'     => __( 'Accessibility tools', 'open-access-toolbar' ),
				'close'     => __( 'Close', 'open-access-toolbar' ),
				'smaller'   => __( 'Smaller text', 'open-access-toolbar' ),
				'larger'    => __( 'Larger text', 'open-access-toolbar' ),
				'reset'     => __( 'Reset all', 'open-access-toolbar' ),
				'statement' => __( 'Accessibility statement', 'open-access-toolbar' ),
				'skip'      => __( 'Skip to content', 'open-access-toolbar' ),
				'note'      => __( 'Your choices are saved in this browser only.', 'open-access-toolbar' ),
			),
		);
	}
}
