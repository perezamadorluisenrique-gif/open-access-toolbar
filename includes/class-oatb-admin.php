<?php
/**
 * Admin screens: toolbar settings (Settings > Accessibility Toolbar) and the
 * content checker (Tools > Accessibility Check).
 *
 * @package OpenAccessToolbar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens and AJAX handlers.
 */
class OATB_Admin {

	const SETTINGS_PAGE = 'open-access-toolbar';
	const CHECK_PAGE    = 'oatb-check';
	const NONCE         = 'oatb_check';

	/**
	 * Hooks the admin.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_oatb_statement', array( $this, 'create_statement' ) );
		add_action( 'wp_ajax_oatb_check_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_oatb_check_batch', array( $this, 'ajax_batch' ) );
		add_action( 'wp_ajax_oatb_site_check', array( $this, 'ajax_site_check' ) );
	}

	/**
	 * Adds the two screens.
	 */
	public function menu() {
		add_options_page(
			__( 'Accessibility Toolbar', 'open-access-toolbar' ),
			__( 'Accessibility Toolbar', 'open-access-toolbar' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_settings' )
		);
		add_management_page(
			__( 'Accessibility Check', 'open-access-toolbar' ),
			__( 'Accessibility Check', 'open-access-toolbar' ),
			'manage_options',
			self::CHECK_PAGE,
			array( $this, 'render_check' )
		);
	}

	/**
	 * Registers the option with the Settings API.
	 */
	public function register_setting() {
		register_setting(
			'oatb',
			OATB_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'OATB_Settings', 'sanitize' ),
				'default'           => OATB_Settings::defaults(),
			)
		);
	}

	/**
	 * Loads the admin script and stylesheet on our screens only.
	 *
	 * @param string $hook Screen hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( 'settings_page_' . self::SETTINGS_PAGE !== $hook && 'tools_page_' . self::CHECK_PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'oatb-admin', OATB_URL . 'assets/admin.css', array(), OATB_VERSION );
		if ( 'tools_page_' . self::CHECK_PAGE === $hook ) {
			wp_enqueue_script( 'oatb-admin', OATB_URL . 'assets/admin.js', array(), OATB_VERSION, true );
			wp_localize_script(
				'oatb-admin',
				'oatbAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE ),
					'i18n'    => array(
						/* translators: 1: posts checked so far, 2: total posts. */
						'progress' => __( 'Checked %1$d of %2$d', 'open-access-toolbar' ),
						'done'     => __( 'Check finished. Loading the results...', 'open-access-toolbar' ),
						'error'    => __( 'The check stopped because of an error. Reload the page and try again.', 'open-access-toolbar' ),
						'checking' => __( 'Checking your home page...', 'open-access-toolbar' ),
					),
				)
			);
		}
	}

	/**
	 * Settings screen.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = OATB_Settings::get();
		$name   = OATB_Settings::OPTION;
		$labels = array(
			'bottom-right' => __( 'Bottom right', 'open-access-toolbar' ),
			'bottom-left'  => __( 'Bottom left', 'open-access-toolbar' ),
			'top-right'    => __( 'Top right', 'open-access-toolbar' ),
			'top-left'     => __( 'Top left', 'open-access-toolbar' ),
		);
		$sizes  = array(
			'small'  => __( 'Small', 'open-access-toolbar' ),
			'medium' => __( 'Medium', 'open-access-toolbar' ),
			'large'  => __( 'Large', 'open-access-toolbar' ),
		);
		?>
		<div class="wrap oatb-wrap">
			<h1><?php esc_html_e( 'Accessibility Toolbar', 'open-access-toolbar' ); ?></h1>
			<p class="oatb-intro">
				<?php esc_html_e( 'The toolbar lets each visitor adjust your site to how they read best. Their choices stay in their own browser: no cookies, no account and nothing sent to any server.', 'open-access-toolbar' ); ?>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::CHECK_PAGE ) ); ?>"><?php esc_html_e( 'Check your content for accessibility problems', 'open-access-toolbar' ); ?></a>
			</p>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'oatb' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Toolbar', 'open-access-toolbar' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Show the accessibility toolbar to visitors', 'open-access-toolbar' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tools', 'open-access-toolbar' ); ?></th>
						<td>
							<fieldset class="oatb-tools">
								<legend class="screen-reader-text"><?php esc_html_e( 'Tools', 'open-access-toolbar' ); ?></legend>
								<?php foreach ( OATB_Settings::tools() as $key => $label ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[tools][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $s['tools'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oatb-position"><?php esc_html_e( 'Button position', 'open-access-toolbar' ); ?></label></th>
						<td>
							<select id="oatb-position" name="<?php echo esc_attr( $name ); ?>[position]">
								<?php foreach ( $labels as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['position'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oatb-size"><?php esc_html_e( 'Button size', 'open-access-toolbar' ); ?></label></th>
						<td>
							<select id="oatb-size" name="<?php echo esc_attr( $name ); ?>[size]">
								<?php foreach ( $sizes as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['size'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oatb-color"><?php esc_html_e( 'Button color', 'open-access-toolbar' ); ?></label></th>
						<td>
							<input type="color" id="oatb-color" name="<?php echo esc_attr( $name ); ?>[color]" value="<?php echo esc_attr( $s['color'] ); ?>">
							<p class="description"><?php esc_html_e( 'The icon is drawn in black or white, whichever has more contrast with this color.', 'open-access-toolbar' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Phones', 'open-access-toolbar' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hide_on_mobile]" value="1" <?php checked( $s['hide_on_mobile'] ); ?>> <?php esc_html_e( 'Hide the toolbar on small screens', 'open-access-toolbar' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Theme fixes', 'open-access-toolbar' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Theme fixes', 'open-access-toolbar' ); ?></legend>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[skip_link]" value="1" <?php checked( $s['skip_link'] ); ?>> <?php esc_html_e( 'Add a "Skip to content" link for keyboard users if the theme has none', 'open-access-toolbar' ); ?></label><br>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[focus_outline]" value="1" <?php checked( $s['focus_outline'] ); ?>> <?php esc_html_e( 'Always show a visible outline on the focused link or button', 'open-access-toolbar' ); ?></label>
								<p class="description"><?php esc_html_e( 'These apply to every visitor, whether or not the toolbar is shown.', 'open-access-toolbar' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oatb-statement"><?php esc_html_e( 'Accessibility statement', 'open-access-toolbar' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $name ) . '[statement_page]',
									'id'                => 'oatb-statement',
									'selected'          => (int) $s['statement_page'],
									'show_option_none'  => esc_html__( '(none)', 'open-access-toolbar' ),
									'option_none_value' => '0',
									'post_status'       => array( 'publish', 'draft' ),
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'A published page chosen here is linked from the toolbar.', 'open-access-toolbar' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Need an accessibility statement?', 'open-access-toolbar' ); ?></h2>
			<p><?php esc_html_e( 'Create a draft page with a statement outline: your commitment, what you have done, known limitations and how visitors can report a problem. Fill in the details, then publish it.', 'open-access-toolbar' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="oatb_statement">
				<?php wp_nonce_field( 'oatb_statement' ); ?>
				<?php submit_button( __( 'Create draft statement page', 'open-access-toolbar' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Creates the draft statement page and opens it in the editor.
	 */
	public function create_statement() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'open-access-toolbar' ), 403 );
		}
		check_admin_referer( 'oatb_statement' );

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => __( 'Accessibility statement', 'open-access-toolbar' ),
				'post_content' => self::statement_content(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}

		$settings                   = OATB_Settings::get();
		$settings['statement_page'] = $id;
		update_option( OATB_Settings::OPTION, $settings );

		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}

	/**
	 * Block markup for the statement outline. Bracketed parts are for the site owner to fill in.
	 *
	 * @return string
	 */
	public static function statement_content() {
		$site     = get_bloginfo( 'name' );
		$sections = array(
			array(
				'h' => __( 'Our commitment', 'open-access-toolbar' ),
				/* translators: %s: site name. */
				'p' => sprintf( __( '%s wants everyone to be able to use this website, including people who use screen readers, keyboard navigation, magnification or other assistive technology. We aim to follow the Web Content Accessibility Guidelines (WCAG) 2.2 at level AA.', 'open-access-toolbar' ), $site ),
			),
			array(
				'h' => __( 'What we have done', 'open-access-toolbar' ),
				'p' => __( '[Describe the steps you have taken, for example: images have text alternatives, headings are used in order, forms have labels, and visitors can adjust text size, spacing and contrast with the accessibility toolbar.]', 'open-access-toolbar' ),
			),
			array(
				'h' => __( 'Known limitations', 'open-access-toolbar' ),
				'p' => __( '[List any parts of the site that are not yet fully accessible, such as older PDF documents or third-party embeds, and when you plan to fix them.]', 'open-access-toolbar' ),
			),
			array(
				'h' => __( 'Feedback and contact', 'open-access-toolbar' ),
				'p' => __( 'If you have trouble using any part of this website, please tell us and we will help and work on a fix. [Add an email address, phone number or contact page link.]', 'open-access-toolbar' ),
			),
			array(
				'h' => __( 'Date of this statement', 'open-access-toolbar' ),
				/* translators: %s: date. */
				'p' => sprintf( __( 'This statement was last reviewed on %s.', 'open-access-toolbar' ), wp_date( get_option( 'date_format' ) ) ),
			),
		);

		$blocks = '';
		foreach ( $sections as $section ) {
			$blocks .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $section['h'] ) . "</h2>\n<!-- /wp:heading -->\n\n";
			$blocks .= "<!-- wp:paragraph -->\n<p>" . esc_html( $section['p'] ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		}
		return $blocks;
	}

	/**
	 * Accessibility Check screen.
	 */
	public function render_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$results = OATB_Scanner::results();
		$types   = OATB_Checker::types();
		$counts  = array_fill_keys( array_keys( $types ), 0 );
		foreach ( $results['posts'] as $post ) {
			foreach ( $post['issues'] as $issue ) {
				if ( isset( $counts[ $issue['type'] ] ) ) {
					++$counts[ $issue['type'] ];
				}
			}
		}
		$total_issues = array_sum( $counts );
		?>
		<div class="wrap oatb-wrap">
			<h1><?php esc_html_e( 'Accessibility Check', 'open-access-toolbar' ); ?></h1>
			<p class="oatb-intro"><?php esc_html_e( 'Finds common problems in your published content that make it harder to use with a screen reader or keyboard. Automated checks catch only part of what matters, so also test your site with a keyboard and a screen reader.', 'open-access-toolbar' ); ?></p>

			<div class="oatb-card">
				<h2><?php esc_html_e( 'Content', 'open-access-toolbar' ); ?></h2>
				<?php if ( $results['finished'] ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: number of posts, 2: date and time. */
							esc_html__( 'Last check: %1$s posts and pages on %2$s.', 'open-access-toolbar' ),
							esc_html( number_format_i18n( $results['total'] ) ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $results['finished'] ) )
						);
						?>
					</p>
				<?php elseif ( $results['started'] ) : ?>
					<p><?php esc_html_e( 'The last check did not finish. Run it again to see complete results.', 'open-access-toolbar' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Your content has not been checked yet.', 'open-access-toolbar' ); ?></p>
				<?php endif; ?>
				<p>
					<button type="button" class="button button-primary" id="oatb-start"><?php echo $results['started'] ? esc_html__( 'Check again', 'open-access-toolbar' ) : esc_html__( 'Check my content', 'open-access-toolbar' ); ?></button>
				</p>
				<div id="oatb-progress" class="oatb-progress" hidden>
					<progress max="100" value="0"></progress>
					<p role="status" aria-live="polite"></p>
				</div>
			</div>

			<?php if ( $results['finished'] ) : ?>
				<div class="oatb-card">
					<?php if ( 0 === $total_issues ) : ?>
						<p class="oatb-ok"><?php esc_html_e( 'No problems found in your content by the automated checks.', 'open-access-toolbar' ); ?></p>
					<?php else : ?>
						<h2>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: "N problems" text, 2: "N items" text. */
									__( '%1$s found in %2$s', 'open-access-toolbar' ),
									/* translators: %s: number of problems. */
									sprintf( _n( '%s problem', '%s problems', $total_issues, 'open-access-toolbar' ), number_format_i18n( $total_issues ) ),
									/* translators: %s: number of posts or pages. */
									sprintf( _n( '%s item', '%s items', count( $results['posts'] ), 'open-access-toolbar' ), number_format_i18n( count( $results['posts'] ) ) )
								)
							);
							?>
						</h2>
						<ul class="oatb-summary">
							<?php foreach ( $counts as $type => $count ) : ?>
								<?php
								if ( ! $count ) {
									continue;
								}
								?>
								<li>
									<strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
									<?php echo esc_html( $types[ $type ]['label'] ); ?>
									<span class="oatb-fix"><?php echo esc_html( $types[ $type ]['fix'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<table class="widefat striped oatb-results">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Item', 'open-access-toolbar' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Problems', 'open-access-toolbar' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $results['posts'] as $post_id => $post ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( '' !== $post['title'] ? $post['title'] : __( '(no title)', 'open-access-toolbar' ) ); ?></strong>
											<div class="oatb-meta"><?php echo esc_html( self::type_label( $post['type'] ) ); ?></div>
											<div class="row-actions visible">
												<?php if ( current_user_can( 'edit_post', $post_id ) ) : ?>
													<a href="<?php echo esc_url( (string) get_edit_post_link( $post_id ) ); ?>"><?php esc_html_e( 'Edit', 'open-access-toolbar' ); ?></a> |
												<?php endif; ?>
												<a href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>"><?php esc_html_e( 'View', 'open-access-toolbar' ); ?></a>
											</div>
										</td>
										<td>
											<ul class="oatb-issues">
												<?php foreach ( $post['issues'] as $issue ) : ?>
													<li>
														<?php echo esc_html( isset( $types[ $issue['type'] ] ) ? $types[ $issue['type'] ]['label'] : $issue['type'] ); ?>
														<?php if ( '' !== $issue['context'] ) : ?>
															<code><?php echo esc_html( $issue['context'] ); ?></code>
														<?php endif; ?>
													</li>
												<?php endforeach; ?>
												<?php if ( ! empty( $post['more'] ) ) : ?>
													<li>
														<?php
														/* translators: %s: number of further problems. */
														echo esc_html( sprintf( __( 'and %s more', 'open-access-toolbar' ), number_format_i18n( $post['more'] ) ) );
														?>
													</li>
												<?php endif; ?>
											</ul>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="oatb-card">
				<h2><?php esc_html_e( 'Theme', 'open-access-toolbar' ); ?></h2>
				<p><?php esc_html_e( 'Checks your home page for things your theme controls.', 'open-access-toolbar' ); ?></p>
				<p><button type="button" class="button" id="oatb-site-check"><?php esc_html_e( 'Check my theme', 'open-access-toolbar' ); ?></button></p>
				<div id="oatb-site-results" aria-live="polite"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Post type singular label.
	 *
	 * @param string $type Post type.
	 * @return string
	 */
	private static function type_label( $type ) {
		$object = get_post_type_object( $type );
		return $object ? $object->labels->singular_name : $type;
	}

	/**
	 * Common guard for the AJAX handlers.
	 */
	private function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'open-access-toolbar' ) ), 403 );
		}
	}

	/**
	 * Starts a content check.
	 */
	public function ajax_start() {
		$this->guard();
		wp_send_json_success( array( 'total' => OATB_Scanner::start() ) );
	}

	/**
	 * Checks the next batch of posts.
	 */
	public function ajax_batch() {
		$this->guard();
		$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		wp_send_json_success( OATB_Scanner::batch( $offset ) );
	}

	/**
	 * Runs the theme checks and returns them as HTML.
	 */
	public function ajax_site_check() {
		$this->guard();
		$checks = OATB_Scanner::site_check();
		if ( is_wp_error( $checks ) ) {
			wp_send_json_error(
				array(
					/* translators: %s: error message. */
					'html' => '<p class="oatb-fail">' . esc_html( sprintf( __( 'Could not load your home page: %s', 'open-access-toolbar' ), $checks->get_error_message() ) ) . '</p>',
				)
			);
		}

		$settings = OATB_Settings::get();
		$status   = array(
			'pass' => __( 'Pass', 'open-access-toolbar' ),
			'warn' => __( 'Check', 'open-access-toolbar' ),
			'fail' => __( 'Problem', 'open-access-toolbar' ),
		);
		$html     = '<ul class="oatb-site">';
		foreach ( $checks as $check ) {
			if ( 'skip' === $check['id'] && 'pass' !== $check['status'] && $settings['skip_link'] ) {
				$check['status'] = 'pass';
				$check['detail'] = __( 'This plugin adds a skip link for visitors (the setting is on).', 'open-access-toolbar' );
			}
			$html .= sprintf(
				'<li class="oatb-%1$s"><span class="oatb-badge">%2$s</span> <strong>%3$s</strong><br>%4$s</li>',
				esc_attr( $check['status'] ),
				esc_html( $status[ $check['status'] ] ),
				esc_html( $check['label'] ),
				esc_html( $check['detail'] )
			);
		}
		$html .= '</ul>';
		wp_send_json_success( array( 'html' => $html ) );
	}
}
