<?php
/**
 * Plugin Name:       Growsfields
 * Plugin URI:        https://github.com/IassineIahaia/growsfields
 * Description:       Reusable custom block plugin built from the starter theme, with its own fields engine, post types, security hardening, and build pipeline.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Iassine Iahaia
 * Author URI:        https://github.com/IassineIahaia
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       growsfields
 * Domain Path:       /languages
 *
 * @package Growsfields
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'GS_VERSION', '0.1.0' );
define( 'GS_PATH', plugin_dir_path( __FILE__ ) );
define( 'GS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Composer autoload.
 */
if ( file_exists( GS_PATH . 'vendor/autoload.php' ) ) {
	require_once GS_PATH . 'vendor/autoload.php';
} else {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Growsfields: Composer dependencies are missing. Run "composer install" in the plugin folder.', 'growsfields' );
			echo '</p></div>';
		}
	);
	return;
}

/**
 * Boot the plugin once all active plugins have loaded.
 */
add_action(
	'plugins_loaded',
	function () {
		( new \Growsfields\Plugin() )->boot();
	}
);

/**
 * Activation / deactivation hooks.
 */
register_activation_hook(
	__FILE__,
	function () {
		( new \Growsfields\Plugin() )->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		( new \Growsfields\Plugin() )->deactivate();
	}
);
