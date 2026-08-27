<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       Emerson Ramirez
 * @since      1.1.0
 *
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/admin
 * @author     Emerson Ramirez <ramirezemerson1991@gmail.com>
 */
class Clientify_Addons_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.1.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.1.0
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'toplevel_page_clientify-addons' ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/clientify-addons-admin.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'select2', plugin_dir_url( __FILE__ ).'css/select2.min.css' , array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.1.0
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'toplevel_page_clientify-addons' ) {
			return;
		}

		$base_path = plugin_dir_path( __FILE__ ) . 'js/clientify-addons-admin.js';

		// Script principal del plugin
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/clientify-addons-admin.js',
			array( 'jquery' ),
			filemtime( $base_path ),
			true
		);

		// Select2
		wp_enqueue_script(
			'select2',
			plugin_dir_url( __FILE__ ) . 'js/select2.min.js',
			array( 'jquery' ),
			'4.0.13',
			true
		);
	}



}
