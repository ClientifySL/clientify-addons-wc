<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       Emerson Ramirez
 * @since      1.0.0
 *
 * @package    Clientify_Addons
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
// $endpoint_class = new CustomClientifyEndPoint();
// $api = new ClientifyApi;




//$key_uid = $endpoint_class->token_id();
//$url_base = $endpoint_class->GetApiUrl();
// require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Api.php';
// $api = new ClientifyApi;
// $post_key = array(
// 	'ecommerce' => 'woocommerce',
// 	//'store_key' => $key_uid,
// 	'action' 	=> 'uninstall plugin',
// 	//'base_url'  => $url_base
// );
// $api->Post_Base_Clientify($post_key);

/* reset Key and clientify api key    */
update_option('CLIENTIFY_API_KEY', '');
update_option('CLIENTIFY_STORE_KEY', '');

