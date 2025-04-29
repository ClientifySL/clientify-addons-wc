<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!class_exists('Clientify_Api')) {
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class_clientify_plugin_core.php';

    class Clientify_Api
    {
        var $api_key;

        var $api_url = 'https://api.clientify.net/';
        // var $api_url = 'https://ecommerce-aly.ngrok.io/';

        public function __construct()
        {
            $this->api_key = get_option('CLIENTIFY_API_KEY');
        }

        public function post_base_clientify($data, $key)
        {
            $response = wp_remote_post(
                $this->api_url . 'ecommerce/v2/connection_by_plugin/',
                array(
                    'body' => json_encode($data),
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Token ' . $key
                    )
                )
            );

            if (is_wp_error($response)) {
                return [
                    'error'   => true,
                    'code'    => $response->get_error_code(),
                    'message' => $response->get_error_message(),
                    'data'    => $response->get_error_data(), // Si hay datos adicionales
                ];
            }

            return json_decode(wp_remote_retrieve_body($response));
        }

        public function get_api($end_point)
        {
            $response = wp_remote_get(
                $this->api_url . $end_point,
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Token ' . $this->api_key
                    )
                )
            );

            if (is_wp_error($response)) {
                return [
                    'error'   => true,
                    'code'    => $response->get_error_code(),
                    'message' => $response->get_error_message(),
                    'data'    => $response->get_error_data(), // Si hay datos adicionales
                ];
            }

            return wp_remote_retrieve_body($response);
        }

        public function post_contacts_clientify($data)
        {
            $response = wp_remote_post(
                $this->api_url . 'ecommerce/v2/woocommerce_listener',
                array(
                    'body' => json_encode($data),
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Token ' . $this->api_key
                    )
                )
            );

            if (is_wp_error($response)) {
                return [
                    'error'   => true,
                    'code'    => $response->get_error_code(),
                    'message' => $response->get_error_message(),
                    'data'    => $response->get_error_data(), // Si hay datos adicionales
                ];
            }

            return json_decode(wp_remote_retrieve_body($response));
        }

        public function post_order_clientify($data)
        {
            return $this->post_contacts_clientify($data); // Reusing the same method as it's identical
        }

        public function post_product_clientify($data)
        {
            return $this->post_contacts_clientify($data); // Reusing the same method as it's identical
        }
    }
}