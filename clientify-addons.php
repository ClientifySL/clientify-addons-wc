<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              Clientify SL
 * @since             1.0.0
 * @package           Clientify-ecommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Clientify-ecommerce
 * Plugin URI:        http://clientify.com/
 * Description:       Conecta woocommerce con Clientify para automatizar el marketing de tu tienda online.
 * Version:           1.0.0
 * Author:            Clientify SL
 * Author URI:        Clientify SL
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       clientify-addons
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CLIENTIFY_ADDONS_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-clientify-addons-activator.php
 */
function activate_clientify_addons() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-clientify-addons-activator.php';
	Clientify_Addons_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-clientify-addons-deactivator.php
 */
function deactivate_clientify_addons() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-clientify-addons-deactivator.php';
	Clientify_Addons_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_clientify_addons' );
register_deactivation_hook( __FILE__, 'deactivate_clientify_addons' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-clientify-addons.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_clientify_addons() {

	$plugin = new Clientify_Addons();
	$plugin->run();

}
run_clientify_addons();