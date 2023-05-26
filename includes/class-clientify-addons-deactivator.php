<?php

/**
 * Fired during plugin deactivation
 *
 * @link       Emerson Ramirez
 * @since      1.0.0
 *
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/includes
 * @author     Emerson Ramirez <ramirezemerson1991@gmail.com>
 */
class Clientify_Addons_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {


		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class_clientify_plugin_core.php';
	
		$endpoint_class = new Clientify_Endpoint();
		$api = new Clientify_Api;
		$key_uid = get_option('CLIENTIFY_STORE_KEY');
		$url_base = $endpoint_class->get_local_api_url();
		$key = get_option('CLIENTIFY_API_KEY');
		$post_key = array(
			'ecommerce' => 'woocommerce',
			'store_key' => $key_uid,
			'action' 	=> 'uninstall',
			'name' 		=> get_option('blogname'),
			'store_url' => $url_base
		);
		$api->post_base_clientify($post_key,$key);
		update_option('CLIENTIFY_STATUS', 0);
		wp_clear_scheduled_hook('clientify_job');
	}

}
