<?php
/**
 * Plugin Name:       Open Access Toolbar
 * Plugin URI:        https://github.com/perezamadorluisenrique-gif/open-access-toolbar
 * Description:       A light, private accessibility toolbar for your visitors (text size, spacing, contrast, readable font and more) plus a checker that finds common accessibility problems in your content. No account, no cloud, no tracking.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Enrique
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       open-access-toolbar
 *
 * @package OpenAccessToolbar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OATB_VERSION', '1.0.0' );
define( 'OATB_FILE', __FILE__ );
define( 'OATB_DIR', plugin_dir_path( __FILE__ ) );
define( 'OATB_URL', plugin_dir_url( __FILE__ ) );

require_once OATB_DIR . 'includes/class-oatb-settings.php';
require_once OATB_DIR . 'includes/class-oatb-frontend.php';
require_once OATB_DIR . 'includes/class-oatb-checker.php';
require_once OATB_DIR . 'includes/class-oatb-scanner.php';
require_once OATB_DIR . 'includes/class-oatb-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		if ( is_admin() ) {
			( new OATB_Admin() )->register();
		} else {
			( new OATB_Frontend() )->register();
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . OATB_Admin::SETTINGS_PAGE ) ),
				esc_html__( 'Settings', 'open-access-toolbar' )
			)
		);
		return $links;
	}
);
