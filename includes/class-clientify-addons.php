<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       Emerson Ramirez
 * @since      1.0.0
 *
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/includes
 * @author     Emerson Ramirez <ramirezemerson1991@gmail.com>
 */
class Clientify_Addons {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Clientify_Addons_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'CLIENTIFY_ADDONS_VERSION' ) ) {
			$this->version = CLIENTIFY_ADDONS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'clientify-addons';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->create_endpoint();//define endpoint
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Clientify_Addons_Loader. Orchestrates the hooks of the plugin.
	 * - Clientify_Addons_i18n. Defines internationalization functionality.
	 * - Clientify_Addons_Admin. Defines all hooks for the admin area.
	 * - Clientify_Addons_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-clientify-addons-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-clientify-addons-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-clientify-addons-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-clientify-addons-public.php';

		/* Include Custon Post Type */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/RegisterCustomPostType.php';

		/* EndPoints clientify */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ClientifyEndpoint.php';

		/* register data class clientify */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/GetDataClientify.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Api.php';

		$this->loader = new Clientify_Addons_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Clientify_Addons_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Clientify_Addons_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Define the Custom Endpoint for API Boilerplate.
	 *
	 * Create the Route, Custom Endpoint & data for API Boilerplate.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function create_endpoint()
	{

		$plugin_endpoint = new CustomClientifyEndPoint($this->get_plugin_name(), $this->get_version(), $this->get_option_name());

		// Add Admin Notice if Below WordPress version 4.7 & WordPress API plugin is not installed
		//$this->loader->add_action('admin_notices', $plugin_endpoint, 'api_boilerplate_nag_message');

		// Construct Custom Endpoint
		$this->loader->add_action('rest_api_init', $plugin_endpoint, 'ClientifyEndPoints');

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Clientify_Addons_Admin( $this->get_plugin_name(), $this->get_version() );
		$register_custom_post_type = new RegisterCustomPostType();
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		//$this->loader->add_action('wp_ajax_update_vk', $register_custom_post_type, 'clientify_update_vk');
		//$this->loader->add_action('wp_ajax_nopriv_update_vk', $register_custom_post_type, 'clientify_update_vk');
		//$this->loader->add_action('wp_login', $register_custom_post_type, 'update_cookie_cart', 200, 2);

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {


		$plugin_public = new Clientify_Addons_Public( $this->get_plugin_name(), $this->get_version() );
		$register_custom_post_type = new RegisterCustomPostType();
		$clientify_wp_api = new CustomClientifyEndPoint();

		if(get_option('CLIENTIFY_STATUS') != 0){
			/*Hook For clientify*/
			$this->loader->add_action('clientify_job', $register_custom_post_type, 'clientify_action_init');
			$this->loader->add_action('wp_footer', $register_custom_post_type, 'clientify_api_script');
			$this->loader->add_action('user_register', $register_custom_post_type, 'customer_add', 10, 1 );
			$this->loader->add_action( 'woocommerce_order_status_changed', $register_custom_post_type,'syncOrder', 10, 3);
			/* Abandoned Cart Process*/	
			$this->loader->add_action('woocommerce_add_to_cart', $register_custom_post_type,'clientify_save_add_to_cart', 10, 2);
			$this->loader->add_action('woocommerce_update_cart_action_cart_updated',$register_custom_post_type, 'clientify_cart_updated', 20, 1);
			$this->loader->add_action('woocommerce_remove_cart_item', $register_custom_post_type, 'delete_item_cart');
			$this->loader->add_action( 'woocommerce_thankyou', $register_custom_post_type,'delete_cart' );	
			/* End Hooks */ 
		}
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		$this->loader->add_action('rest_api_init', $clientify_wp_api, 'ClientifyEndPoints');
		
		$this->loader->add_action('admin_init', $register_custom_post_type, 'clientify_settings');
		$this->loader->add_action( 'init', $register_custom_post_type,'clientify_action_init', 10, 1 );
	
		$this->loader->add_action('admin_menu', $register_custom_post_type ,'clientify_create_menu');
		$this->loader->add_action('admin_menu', $register_custom_post_type, 'clientify_index', 10, 2);		
		
		$this->loader->add_action('wp_ajax_connect_clientify', $register_custom_post_type, 'connect_clientify');
		$this->loader->add_action('wp_ajax_nopriv_connect_clientify', $register_custom_post_type, 'connect_clientify');
		
		$this->loader->add_action('wp_ajax_disconnect_clientify', $register_custom_post_type, 'disconnect_clientify');
		$this->loader->add_action('wp_ajax_nopriv_disconnect_clientify', $register_custom_post_type, 'disconnect_clientify');
		
		
		
		//$this->loader->add_action('wp_ajax_sync_customer',$register_custom_post_type, 'sync_customer');
		//$this->loader->add_action('wp_ajax_nopriv_sync_customer',$register_custom_post_type, 'sync_customer');

		//$this->loader->add_action('wp_ajax_token_id', $clientify_wp_api, 'token_id_ajax');
		//$this->loader->add_action('wp_ajax_nopriv_token_id', $clientify_wp_api, 'token_id_ajax');


		/* AC CART*/
		//$this->loader->add_action('wp_ajax_update_vk', $register_custom_post_type,'clientify_update_vk' );
		//$this->loader->add_action('wp_ajax_nopriv_update_vk', $register_custom_post_type,'clientify_update_vk' );

		//$this->loader->add_action( 'woocommerce_thankyou', $register_custom_post_type,'syncOrder', 10, 2 );		
		

			

		//$this->loader->add_action('admin_init', $optionadmin, 'clientify_settings_page');
		

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Clientify_Addons_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
	public function get_option_name()
	{

		return $this->option_name;
	}

}
