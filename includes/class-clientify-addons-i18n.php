<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       Emerson Ramirez
 * @since      1.1.0
 *
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.1.0
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/includes
 * @author     Emerson Ramirez <ramirezemerson1991@gmail.com>
 */
class Clientify_Addons_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.1.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'clientify-addons',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
