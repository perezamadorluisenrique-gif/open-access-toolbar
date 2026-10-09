<?php
/**
 * Removes the plugin's options when it is deleted, on every site of a network.
 * The statement page, if one was created, is the site owner's content and is left in place.
 *
 * @package OpenAccessToolbar
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$oatb_sites = is_multisite() ? get_sites(
	array(
		'fields' => 'ids',
		'number' => 0,
	)
) : array( 0 );

foreach ( $oatb_sites as $oatb_site ) {
	if ( $oatb_site ) {
		switch_to_blog( $oatb_site );
	}
	delete_option( 'oatb_settings' );
	delete_option( 'oatb_check' );
	if ( $oatb_site ) {
		restore_current_blog();
	}
}
