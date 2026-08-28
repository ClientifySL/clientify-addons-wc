<?php
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly
if (!class_exists('Clientify_Api')) {
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class_clientify_plugin_core.php';

    class Clientify_Api
    {
        var $api_key;

        // Configuración de timeouts y reintentos
        var $max_retries = 3; // Número máximo de reintentos
        var $retry_delay = 2; // Segundos de espera entre reintentos
        var $timeout = 30; // Timeout en segundos para las llamadas HTTP

        // Configuración de la API
        var $api_url = 'https://api-plus.clientify.com/api/ecommerce/v2/';

        // Configuración de SSL y HTTP
        var $ssl_verify = true;
        var $http_version = '1.1'; // Versión de HTTP a usar

        public function __construct()
        {
            $this->api_key = get_option('CLIENTIFY_API_KEY');
        }

        /**
         * Método para hacer llamadas HTTP con reintentos automáticos
         */
        private function make_http_request($url, $args, $method = 'POST', $retry_count = 0, $no_retry = false)
        {
            if ( $retry_count > 0 ) {
                error_log("Clientify API: Reintento $retry_count para $method $url");
            }

            if ($method === 'GET') {
                $response = wp_remote_get($url, $args);
            } else {
                $response = wp_remote_post($url, $args);
            }

            if (is_wp_error($response)) {
                $error_code = $response->get_error_code();
                $error_message = $response->get_error_message();

                error_log("Clientify API Error: $error_code - $error_message (intento " . ($retry_count + 1) . ")");

                if (!$no_retry && ($error_code === 'http_request_failed' || $error_code === 'timeout') && $retry_count < $this->max_retries) {
                    error_log("Clientify API: Reintentando en " . $this->retry_delay . " segundos...");
                    return $this->make_http_request($url, $args, $method, $retry_count + 1, false);
                }

                return [
                    'error' => true,
                    'code' => $error_code,
                    'message' => $error_message,
                    'data' => $response->get_error_data(),
                    'retries' => $retry_count
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ( $response_code >= 400 ) {
                error_log("Clientify API: Error HTTP $response_code en $method $url");
            }

            return $response;
        }

        public function post_base_clientify($data, $key)
        {
            $args = array(
                'body' => json_encode($data),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Token ' . $key,
                ),
                'timeout' => 60,
                'httpversion' => $this->http_version,
                'sslverify' => $this->ssl_verify
            );

            // No retries for connect/disconnect — not idempotent
            $response = $this->make_http_request($this->api_url . 'api/ecommerce/v2/connection_by_plugin/', $args, 'POST', 0, true);

            if (is_array($response) && isset($response['error']) && $response['error']) {
                return $response;
            }

            return [
                'http_code' => (int) wp_remote_retrieve_response_code($response),
                'body'      => json_decode(wp_remote_retrieve_body($response)),
            ];
        }

        public function get_api($end_point)
        {
            $args = array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Token ' . $this->api_key
                ),
                'timeout' => $this->timeout,
                'httpversion' => $this->http_version,
                'sslverify' => $this->ssl_verify
            );

            $response = $this->make_http_request($this->api_url . $end_point, $args, 'GET');

            if (is_array($response) && isset($response['error']) && $response['error']) {
                return $response;
            }

            return wp_remote_retrieve_body($response);
        }

        public function post_contacts_clientify($data)
        {
            $args = array(
                'body' => json_encode($data),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Token ' . $this->api_key
                ),
                'timeout' => $this->timeout,
                'httpversion' => $this->http_version,
                'sslverify' => $this->ssl_verify
            );

            $response = $this->make_http_request($this->api_url . 'api/ecommerce/v2/woocommerce_listener', $args);

            if (is_array($response) && isset($response['error']) && $response['error']) {
                return $response;
            }

            return json_decode(wp_remote_retrieve_body($response));
        }

        public function post_contacts_async( $data ) {
            $args = array(
                'body'        => json_encode( $data ),
                'headers'     => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Token ' . $this->api_key,
                ),
                'timeout'     => 0.01,
                'blocking'    => false,
                'httpversion' => $this->http_version,
                'sslverify'   => $this->ssl_verify,
            );
            wp_remote_post( $this->api_url . 'api/ecommerce/v2/woocommerce_listener', $args );
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