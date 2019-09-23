<?php
/**
 * Plugin Name:       Distributor Groups Taxonomy Add-on
 * Description:       Distribute posts in connection groups organized via Wordpress Taxonomies
 * Version:           1.0.1
 * Author:            Novembit
 * Author URI:        https://novembit.com
 * License:           GPLv3 or later
 * Domain Path:       /lang/
 * GitHub Plugin URI:
 * Text Domain:       distributor-groups-taxonomy
 *
 * @package distributor-groups-taxonomy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'DT_GROUPS_TAXONOMY_VERSION', '1.0.1' );

/**
 * Bootstrap function
 */
function dt_groups_taxonomy_add_on_bootstrap() {
	if ( ! function_exists( '\Distributor\ExternalConnectionCPT\setup' ) ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', function() {
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( 'notice notice-error' ), esc_html( 'You need to have Distributor plug-in activated to run the Distributor Groups Taxonomy Add-on.', 'distributor-groups-taxonomy' ) );
			} );
		}
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'manager.php';
}

add_action( 'plugins_loaded', 'dt_groups_taxonomy_add_on_bootstrap' );
