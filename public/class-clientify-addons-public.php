<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       Emerson Ramirez
 * @since      1.1.0
 *
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/public
 * @author     Emerson Ramirez <ramirezemerson1991@gmail.com>
 */
class Clientify_Addons_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.1.0
	 */
	public function enqueue_styles() {

		if ( ! get_option('CLIENTIFY_STATUS') ) {
			return;
		}

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Clientify_Addons_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Clientify_Addons_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/clientify-addons-public.css', array(), $this->version, 'all' );
		// wp_enqueue_style( 'prefix_initial', 'https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.7/build/css/intlTelInput.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.1.0
	 */
	public function enqueue_scripts() {

		if ( ! get_option('CLIENTIFY_STATUS') ) {
			return;
		}

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Clientify_Addons_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Clientify_Addons_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		//wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/clientify-addons-public.js', array( 'jquery' ), $this->version, false );
		//wp_localize_script($this->plugin_name, 'clientify_ajax', array('ajax_url' => admin_url('admin-ajax.php')));

		$vars = array(
		'ajaxurl'                   => admin_url( 'admin-ajax.php' ),
		'_nonce'                    => wp_create_nonce( 'clientify_save_cart_abandonment_data' ),
		'_gdpr_nonce'               => wp_create_nonce( 'clientify_skip_cart_tracking_gdpr' ),
		'_cf7_nonce'                => wp_create_nonce( 'clientify_cf7_contact_sync' ),
		'_post_id'                  => get_the_ID(),
		'_show_gdpr_message'        => ( '' ),
		'_gdpr_message'             => get_option( 'wcf_ca_gdpr_message' ),
		'_gdpr_nothanks_msg'        => __( 'No Thanks', 'woo-cart-abandonment-recovery' ),
		'_gdpr_after_no_thanks_msg' => __( 'You won\'t receive further emails from us, thank you!', 'woo-cart-abandonment-recovery' ),
		'enable_ca_tracking'        => true,
	);



    wp_enqueue_script('clientify-script', plugins_url('../public/js/clientify-addons-public.js', __FILE__), array('jquery','prefix_script'), '1.0', true);
	wp_localize_script( 'clientify-script', 'clientify_wcf_ca_vars', $vars );
	wp_localize_script('clientify-script', 'clientify_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
	wp_enqueue_script( 'prefix_script', 'https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.7/build/js/intlTelInput.min.js', array( 'jquery' ), $this->version, true );
	}

}
