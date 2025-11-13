<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
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
        var $api_url = 'https://api.clientify.net/';
        // var $api_url = 'https://ecommerce-aly.ngrok.io/';
        
        // Configuración de SSL y HTTP
        var $ssl_verify = true; // Verificación SSL (false para desarrollo, true para producción)
        var $http_version = '1.1'; // Versión de HTTP a usar

        public function __construct()
        {
            $this->api_key = get_option('CLIENTIFY_API_KEY');
        }

        /**
         * Método para hacer llamadas HTTP con reintentos automáticos
         */
        private function make_http_request($url, $args, $method = 'POST', $retry_count = 0)
        {
            // Log de la llamada
            error_log("Clientify API: Intentando llamada $method a $url (intento " . ($retry_count + 1) . ")");
            
            if ($method === 'GET') {
                $response = wp_remote_get($url, $args);
            } else {
                $response = wp_remote_post($url, $args);
            }
            
            if (is_wp_error($response)) {
                $error_code = $response->get_error_code();
                $error_message = $response->get_error_message();
                
                error_log("Clientify API Error: $error_code - $error_message (intento " . ($retry_count + 1) . ")");
                
                // Si es un timeout y no hemos excedido el número de reintentos
                if (($error_code === 'http_request_failed' || $error_code === 'timeout') && $retry_count < $this->max_retries) {
                    error_log("Clientify API: Reintentando en " . $this->retry_delay . " segundos...");
                    
                    // Esperar antes del reintento
                    sleep($this->retry_delay);
                    
                    // Reintentar la llamada
                    return $this->make_http_request($url, $args, $method, $retry_count + 1);
                }
                
                return [
                    'error'   => true,
                    'code'    => $error_code,
                    'message' => $error_message,
                    'data'    => $response->get_error_data(),
                    'retries' => $retry_count
                ];
            }
            
            // Log de respuesta exitosa
            $response_code = wp_remote_retrieve_response_code($response);
            error_log("Clientify API: Respuesta exitosa con código $response_code");
            
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
                'timeout' => $this->timeout,
                'httpversion' => $this->http_version,
                'sslverify' => $this->ssl_verify
            );

            $response = $this->make_http_request($this->api_url . 'ecommerce/v2/connection_by_plugin/', $args);

            if (is_array($response) && isset($response['error']) && $response['error']) {
                return $response;
            }

            return json_decode(wp_remote_retrieve_body($response));
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

            $response = $this->make_http_request($this->api_url . 'ecommerce/v2/woocommerce_listener', $args);

            if (is_array($response) && isset($response['error']) && $response['error']) {
                return $response;
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