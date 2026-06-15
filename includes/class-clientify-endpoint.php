<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-api-connect.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class_clientify_plugin_core.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-helper.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-addons.php';

class Clientify_Endpoint {

    public function get_local_api_url(){

        $api_url  = get_rest_url();
        $del = str_contains($api_url, '/wp-json/') ? '' : '';
        $api_url = $api_url . 'clientify/v1/' . $del;

        return $api_url;    
    }

    public function token_id(){

        $new_key = str_replace('-', '', wp_generate_uuid4());        
        update_option('CLIENTIFY_STORE_KEY' , $new_key);
        
        return $new_key;
  
    }

    public function clientify_set_endpoints(){

        register_rest_route('clientify/v1', '/pluginhandling', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'plugin_handling'),
        ));

        register_rest_route('clientify/v1', '/deactivate', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'test_conect'),
        ));

        register_rest_route('clientify/v1', '/status', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'webhooks_status'),
        ));

        register_rest_route('clientify/v1', '/contacts', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'sync_all_customer'),
        ));

        register_rest_route('clientify/v1', '/products', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'get_all_products'),
        ));
        
        register_rest_route('clientify/v1', '/setscript', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'privileged_permission_callback_permit'),
            'callback' => array($this, 'analitics_script'),
        ));

        register_rest_route('clientify/v1', '/setsend', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'setsend'),
        ));

        register_rest_route('clientify/v1', '/time_abandoned_cart', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'ac_cart'),
        ));

        register_rest_route('clientify/v1', '/orders', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'orders'),
        ));

        register_rest_route('clientify/v1', '/sync_orders', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'sync_orders'),
        ));

        register_rest_route('clientify/v1', '/abandoned', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'get_list_abandoned_cart'),
        ));

        register_rest_route('clientify/v1', '/sync_abandoned', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'sync_abandoned_cart'),
        ));

        register_rest_route('clientify/v1', '/sync_contacts', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'sync_contacts'),
        ));

        register_rest_route('clientify/v1', '/get_data', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'get_data_wordpress_server'),
        ));

        register_rest_route('clientify/v1', '/logs', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'get_clientify_logs'),
        ));

    }

    public function privileged_permission_callback($request) {
        if ($request->get_header('storekey') === get_option('CLIENTIFY_STORE_KEY')) {
            return true;
        } else {
            return new WP_Error('rest_forbidden', __('Acceso denegado. Clave incorrecta.'), array('status' => 403));
        }
    }

    public function privileged_permission_callback_permit($request) {
        
            return true;
        
    }

    function get_data_wordpress_server() {
        // Obtener datos del servidor
        $datosServidor = array(
            'os' => php_uname('s'),
            'php_version' => phpversion(),
            'server_name' => $_SERVER['SERVER_NAME']
        );
    
        // Obtener versión de WordPress
        global $wp_version;
        $datosWordpress = array(
            'wordpress_version' => $wp_version,
            'shop_name' => get_option('blogname'),
            'url_tienda' => home_url('/')
        );
        // Crear una instancia de la clase Clientify_Addons
        $clientify_addons = new Clientify_Addons();


        
        $datosModulo = array(
            'module_name'	 => $clientify_addons->get_plugin_name(),
            'module_author'	 => 'Clientify SL',
            'module_store_key' => get_option('CLIENTIFY_STORE_KEY'),
            'module_version' => CLIENTIFY_ADDONS_VERSION,
            'module_status'	 =>  get_option('CLIENTIFY_STATUS'),
            'clientify_script'	=> get_option('CLIENTIFY_SCRIPT'),
            'gdpr_text' => get_option('CLIENTIFY_GDPR_TEXT'),
            'gdpr_status' => get_option('CLIENTIFY_GDPR')
        );
    
        // Obtener información de los plugins activos
        $active_plugins = get_option('active_plugins');
        $plugins_data = array();
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $plugins_data[] = array(
                'plugin_name' => $plugin_data['Name'],
                'plugin_author' => $plugin_data['AuthorURI'],
                'plugin_version' => $plugin_data['Version']
            );
        }
    
        // Combinar datos y devolverlos
        $datosCombinados = array_merge($datosServidor, $datosWordpress, $datosModulo );
        $datosCombinados['active_plugins'] = $plugins_data;
    
        return $datosCombinados;
    }

    /*get al orders -revised*/
	public function orders($params){

		global $product;
		global $wpdb;
        
        $endpoint_class = new Clientify_Endpoint();
        $url_base = $endpoint_class->get_local_api_url();
        $created_at_min = empty($params->get_param('created_at_min')) ? date('Y-m-d') : Clientify_Helper::parse_date_flexible($params->get_param('created_at_min'), 'Y-m-d');
        $created_at_end = empty($params->get_param('created_at_end')) ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d', strtotime(Clientify_Helper::parse_date_flexible($params->get_param('created_at_end'), 'Y-m-d') . ' +1 day'));
        $per_page = max(1, intval($params->get_param('per_page') ?: 25));
        $paged    = max(1, intval($params->get_param('page') ?: 1));
        $order_status_settings = get_option('CLIENTIFY_ORDER_STATUS');

        // Paso 1: solo IDs del rango de fechas (query liviana)
        $all_ids = wc_get_orders([
            'date_created' => $created_at_min . '...' . $created_at_end,
            'status'       => $order_status_settings,
            'orderby'      => 'ID',
            'order'        => 'ASC',
            'limit'        => -1,
            'return'       => 'ids',
            'type'         => 'shop_order',
        ]);

        // Paso 2: filtrar en SQL solo los que tienen productos activos
        $valid_ids = [];
        if (!empty($all_ids)) {
            $placeholders = implode(',', array_fill(0, count($all_ids), '%d'));
            $valid_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT oi.order_id
                     FROM {$wpdb->prefix}woocommerce_order_items oi
                     INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                         ON oim.order_item_id = oi.order_item_id AND oim.meta_key = '_product_id'
                     INNER JOIN {$wpdb->posts} p
                         ON p.ID = oim.meta_value
                         AND p.post_type IN ('product','product_variation')
                         AND p.post_status NOT IN ('trash','auto-draft')
                     WHERE oi.order_id IN ($placeholders)
                     AND oi.order_item_type = 'line_item'",
                    ...$all_ids
                )
            );
            // Preservar orden original por ID ASC
            $valid_ids = array_values(array_intersect($all_ids, array_map('intval', $valid_ids)));
        }

        // Paso 3: paginar sobre IDs válidos y cargar solo los objetos de esta página
        $page_ids = array_slice($valid_ids, ($paged - 1) * $per_page, $per_page);
        $orders   = array_filter(array_map('wc_get_order', $page_ids));

        $all = array();
        foreach( $orders as $order ) {

            if ( $order->get_type() === 'shop_order_refund' ) {
                continue;
            }

            $key = 'wc-' . $order->get_status();
            if ( in_array($key, $order_status_settings) ) {// check is order status is right

                $order_data = $order->get_data();
                $id_customer = $order->get_customer_id();
                $contact = null;
                $lang = get_bloginfo("language");
                $currency = $order->get_currency();
                $total_price = $order->get_total(); 
                $products = $order->get_items();         
                $items = array();
                $coupons_tags = array();
                $order_tags = array();

                foreach ( $products as $order_product ) {
                    $categories = array();
                    $subcategories= array();
                    $join_categories = "";
                    $join_subcategories = "";
                   
                    $terms = get_the_terms($order_product['product_id'], 'product_cat');
                    if (!empty($terms)) {
                        foreach ($terms as $term) {
                            if ($term->parent == 0) {
                                // Categoría principal
                                if (!in_array($term->term_id, $categories)) {
                                    $categories[] = $term->term_id;
                                    $join_categories .= ($join_categories == "" ? "" : ",") . $term->term_id . ":" . $term->slug;
                                }
                            } else {
                                // Subcategoría
                                if (!in_array($term->term_id, $subcategories)) {
                                    $subcategories[] = $term->term_id;
                                    $parent_id = $term->parent;
                                    
                                    // Asegurarse de que la categoría principal esté añadida
                                    if (!in_array($parent_id, $categories)) {
                                        $parent_term = get_term($parent_id, 'product_cat');
                                        $categories[] = $parent_id;
                                        $join_categories .= ($join_categories == "" ? "" : ",") . $parent_id . ":" . $parent_term->slug;
                                    }
                        
                                    // Construir la cadena de subcategorías
                                    $join_subcategories .= ($join_subcategories == "" ? "" : ",") . $term->term_id . ":" . $term->slug . "|parent_id:" . $parent_id;
                                }
                            }
                        }
                    }
        
                    $join_cat = $join_categories."/".$join_subcategories;  

                    $product = $order_product->get_product();
                    if (!$product) {
                        continue;
                    }
                    try {
                        $sku = $product->get_sku();
                    } catch (Exception $e) {
                        $sku = '';
                    }

                    $image_id  = $product->get_image_id();
                    $image_url = wp_get_attachment_image_url($image_id, 'full');
                    $product_id = $order_product['product_id'];
                    $product_instance = wc_get_product($product_id);
                    $product_full_description = $product_instance->get_description();
                    $tax_amount = $order->get_item_tax($order_product, true, true);
                    $inc_tax = $tax_amount > 0 ? true : false;
                    $price = $product->get_sale_price();
                    $discount_price = floatval($order_product['subtotal']) - floatval($order_product['total']);
                    $quantity = $order_product->get_quantity();

                    if ($product->is_on_sale()) {
                        $price = $product->get_sale_price();
                    } else {
                        $price = $product->get_regular_price();
                    }

                    if ( $inc_tax ) {
                        $price = wc_get_price_including_tax($product, array('price' => $price));
                    } else {
                        $price = wc_get_price_excluding_tax($product, array('price' => $price));
                    }

                    if ($price == 0) {
                        $discount = 0;
                    } else {
                        // Calcular el descuento por unidad de producto
                        $unit_discount_price = $discount_price / $quantity;
                        $discount = ($unit_discount_price * 100) / $price;
                    }

                    $items[] = array(
                        'name'        => $order_product->get_name(),
                        'description' => $product_full_description,
                        'category'    => $join_cat,
                        'sku'         => $sku,
                        'image_url'   => $image_url,
                        'item_url'    => get_permalink($order_product['product_id']),
                        'price'       => number_format($price, 2, '.', ''),
                        'quantity'    => $quantity,
                        'discount'    => $discount != 0 ? round($discount) : 0, //$discount
                        );
                }
               
                //total discount
                $order_discount_total = $order->get_total_discount(!$inc_tax);

                $contact = 0;

                if ($id_customer){
                    $contact = $this->get_contact($id_customer);
                }else{
                    if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
                        $customer_phones = array();
                        $contact = array(
                            'email' => '',
                            'contact_source'  => get_option('blogname'),
                            'custom_fields'   => [],
                            'tags'            => array(
                                                    'woocommerce'
                                                )
                        );
                        if ( !empty($order->get_billing_email()) ) {
                            $contact['email'] = $order->get_billing_email();
                        }
                        if ( !empty($order->get_billing_first_name()) ) {
                            $contact['first_name'] = $order->get_billing_first_name();
                        }
                        if ( !empty($order->get_billing_last_name()) ) {
                            $contact['last_name'] = $order->get_billing_last_name();
                        }
                        if ( !empty($order->get_billing_company()) ) {
                            $contact['company'] = $order->get_billing_company();
                        }

                        $customer_address = array('type' => 1);
                        $street = $order->get_billing_address_1() . (!empty($order->get_billing_address_2()) ? ', ' . $order->get_billing_address_2() : '');
                        if ( !empty($street) ) {
                            $customer_address['street'] = $street;
                        }
                        if ( !empty($order->get_billing_city()) ) {
                            $customer_address['city'] = $order->get_billing_city();
                        }
                        if ( !empty($order->get_billing_state()) ) {
                            $customer_address['state'] = $order->get_billing_state();
                        }
                        if ( !empty($order->get_billing_postcode()) ) {
                            $customer_address['postal_code'] = $order->get_billing_postcode();
                        }
                        if ( !empty($order->get_billing_country()) ) {
                            $customer_address['country'] = $order->get_billing_country();
                        }
                        $contact['addresses'][] = $customer_address;
                        if ( !empty($order->get_billing_phone()) && !in_array($order->get_billing_phone(), $customer_phones ) ) {
                            $contact['phones'][] = array('phone' => $order->get_billing_phone());
                            $customer_phones[] = $order->get_billing_phone();
                        }
                        if ( !empty($lang) ) {
                            $contact['custom_field'] = array(
                                'field' => 'ecommerce_language',
                                'value' => $lang,
                            );
                        }
                    }elseif ( $order->get_shipping_first_name() || $order->get_shipping_last_name() ) {
                        $customer_phones = array();
                        $contact = array(
                            'email' => '',
                            'contact_source'  => get_option('blogname'),
                            'custom_fields'   => [],
                            'tags'            => array(
                                                    'woocommerce'
                                                )
                        );
            
                        if ( !empty($order->get_shipping_first_name()) ) {
                            $contact['first_name'] = $order->get_shipping_first_name();
                        }
                        if ( !empty($order->get_shipping_last_name()) ) {
                            $contact['last_name'] = $order->get_shipping_last_name();
                        }
                        if ( !empty($order->get_shipping_company()) ) {
                            $contact['company'] = $order->get_shipping_company();
                        }

                        $customer_address = array('type' => 1);
                        $street = $order->get_shipping_address_1() . (!empty($order->get_shipping_address_2()) ? ', ' . $order->get_shipping_address_2() : '');
                        if ( !empty($street) ) {
                            $customer_address['street'] = $street;
                        }
                        if ( !empty($order->get_shipping_city()) ) {
                            $customer_address['city'] = $order->get_shipping_city();
                        }
                        if ( !empty($order->get_shipping_state()) ) {
                            $customer_address['state'] = $order->get_shipping_state();
                        }
                        if ( !empty($order->get_shipping_postcode()) ) {
                            $customer_address['postal_code'] = $order->get_shipping_postcode();
                        }
                        if ( !empty($order->get_shipping_country()) ) {
                            $customer_address['country'] = $order->get_shipping_country();
                        }
                        $contact['addresses'][] = $customer_address;
                        
                        if ( !empty($lang) ) {
                            $contact['custom_field'] = array(
                                'field' => 'ecommerce_language',
                                'value' => $lang,
                            );
                        }
                    }
                }

                // Email siempre desde facturación de la orden
                if ( is_array($contact) && !empty($order->get_billing_email()) ) {
                    $contact['email'] = $order->get_billing_email();
                }

                $shipping = $order_data['shipping_total'];

				if ($shipping === 0 || $shipping === "0" || $shipping === '' || $shipping === null || $shipping === false ) {
					$shipping = 0; // Asegura que sea un entero 0
				}

                $coupons = $order->get_coupon_codes();
                $tipo_orden = $order->get_meta('tipodeorden');
                if (!empty($tipo_orden) && !in_array($tipo_orden, $order_tags)) {
                    $order_tags[] = $tipo_orden;
                }
            
				
                $data = array(
                    'contact' => $contact,
                    'status' => 'ordered',
                    'order_date' => $order_data['date_created']->date('Y-m-d H:i:s'),
                    'order_id' => $order->get_id(),
                    'ecommerce' => 'woocommerce',
                    'shop_name' => get_option('blogname'),
                    'order_url' => $order->get_view_order_url(),
                    'store_url' => $url_base,
                    'currency' => $currency,
                    'products' => $items,
                    'price' =>  number_format($total_price, 2, '.', ''),
                    'shipping' => $shipping,
                    'coupon' => $order_discount_total,
                    'order_tags' => $order_tags
                    );

                    if ($coupons) {
                        foreach ($coupons as $coupon_code) {
                            $coupons_tags[] = $coupon_code;
                        }
                        $data['coupon_tags'] = $coupons_tags;
                    }
                
                    if ( !empty($lang) ) {
                        $data['custom_field'] = array(
                        'field' => 'ecommerce_language',
                        'value' => $lang,
                        );
                    }
                    if($contact && !empty($items)){
                        $all [] = array($data);
                    }
                
                

                
            }//endif


        }//endforeach
		
        $total_pages = $per_page > 0 ? ceil(count($valid_ids) / $per_page) : 1;
        $response = new WP_REST_Response($all, 200);
        $response->header('X-WP-Total', count($valid_ids));
        $response->header('X-WP-TotalPages', $total_pages);

		return $response;

	}

    function webhooks_status($params)
    {
        global $wpdb;
        $param = $params->get_param('hook');
        $abandoned_table = $wpdb->prefix . 'clientify_ca_cart_abandonment';
        $abandoned_card_exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $abandoned_table)) === $abandoned_table;

        if( $param=='all' ) {
            $status= array(
                'pixel_script'   => $this->find_filter('wp_footer','clientify_api_script'),
                'abandoned_card' => $abandoned_card_exists,
                'contac'         => $this->find_filter('woocommerce_created_customer','customer_add'),
                'order'          => $this->find_filter('woocommerce_order_status_changed','sync_hook_order'),
                'product'        => $this->find_filter('woocommerce_new_product','product_published')                
            );
        return $status;   
        }else {
            return array('message' => 'param error');
        }
    }

    function find_filter( $hook = '',$action= '' ){
        global $wp_filter;
        if( empty( $hook ) || !isset( $wp_filter[$hook] ) ){
            return false;
        }else{  
                $da = $wp_filter[$hook];
                foreach ( $wp_filter[$hook] as $key ) {
                    $indice = strpos(json_encode($key),$action);
                    if( $indice ) {
                        return true;
                    }//else{ return false;}
                }if( $indice == false ) {
                    return false;
                }
        }
    }
    /* analytics */
    // public function analitics_script($params)
    // {   
    //     $set_script = $params->get_param('set_script');

    //     update_option('CLIENTIFY_SCRIPT', $set_script);

    //     return array('message' => 'success');

    // }
    public function analitics_script($params) {   
        try {
            // Aumentar el límite de tiempo de ejecución si es necesario
            set_time_limit(30); // 30 segundos
            
            // Desactivar la visualización de errores para el cliente
            error_reporting(0);
            ini_set('display_errors', 0);
            
            $set_script = $params->get_param('set_script');
            
            if ($set_script === null) {
                return new WP_REST_Response(array('message' => 'No script provided'), 400);
            }
            
            $result = update_option('CLIENTIFY_SCRIPT', $set_script);
            
            if ($result) {
                return new WP_REST_Response(array('message' => 'success'), 200);
            } else {
                return new WP_REST_Response(array('message' => 'No changes made or option not updated'), 200);
            }
            
        } catch (Exception $e) {
            // Registrar el error internamente pero devolver éxito al cliente
            error_log('Error in analitics_script: ' . $e->getMessage());
            return new WP_REST_Response(array('message' => 'success'), 200);
        }
    }
    /* change status pluging conneted or disconnect passes 0 / 1 */
    public function plugin_handling($params)
    {   
        $call = new Clientify_Plugin_Core();
        $set_send = $params->get_param("action");

        if ( $set_send == "connect" ) {
            $key_uid = $this->token_id();
            $url_base = $this->get_local_api_url();
            $post_key = array(
				'ecommerce' => 'woocommerce',
				'action'    => 'connect',
				'store_key' => $key_uid,
				'name'      => get_option('blogname'),
				'store_url' => $url_base
			);
            $key = get_option('CLIENTIFY_API_KEY');
            $api = new Clientify_Api;
            $response = $api->post_base_clientify($post_key, $key);
            update_option('CLIENTIFY_STATUS', 1);

            if ( get_option('CLIENTIFY_STATUS') == 1 ) {
                return new WP_REST_Response(array('message' => 'success','api_response' => $post_key,'data'=> $post_key), 200);
            }
            else{
                return new WP_REST_Response(array('message' => 'error', 'api_response' => $post_key), 500);
            }  

        }elseif ( $set_send == "disconnect" ) {
            $key_uid = get_option('CLIENTIFY_STORE_KEY');
			$url_base = $this->get_local_api_url();
			$post_key = array(
				'ecommerce' => 'woocommerce',
				'action'    => 'disconnect',
				'store_key' => $key_uid,
				'name'      => get_option('blogname'),
				'store_url' => $url_base
			);
            $key = get_option('CLIENTIFY_API_KEY');
            $api = new Clientify_Api;
            $response = $api->post_base_clientify($post_key, $key);
            update_option('CLIENTIFY_STATUS', 0);
            update_option('CLIENTIFY_SCRIPT', ' ');
            if ( get_option('CLIENTIFY_STATUS') == 0 ) {
                return new WP_REST_Response(array('message' => 'success', 'api_response' => $response, 'data'=> $post_key), 200);
            }
            else{
                return new WP_REST_Response(array('message' => 'error', 'api_response' => $response), 500);
            }
            
        }else {
            return new WP_REST_Response(array('message' => 'error param'), 500);
        }
    }
    /* time abandoned cart*/
    public function ac_cart($params){
        $set_time = $params->get_param('set_time');

        if ( intval($set_time) >= 1 && intval($set_time) <= 24 ) {            
            update_option('CLIENTIFY_CART_HOUR',$set_time);
            return array('message' => 'success' );
        }else{
            return array('message' => 'error range time');
        }

    }
    /* get Customers  list all*/
    public function sync_all_customer($params){

        global $wpdb;

        // Obtener parámetros y saneamiento
        $created_at_min = empty($params->get_param('created_at_min')) ? date("Y-m-d") : Clientify_Helper::parse_date_flexible($params->get_param('created_at_min'), 'Y-m-d');
        $created_at_end = empty($params->get_param('created_at_end')) ? date("Y-m-d") : Clientify_Helper::parse_date_flexible($params->get_param('created_at_end'), 'Y-m-d');
        $per_page = max(1, intval($params->get_param('per_page', 25)));
        $paged = max(1, intval($params->get_param('page', 1)));
        $offset = ($paged - 1) * $per_page;

        $contacts_page = array();

        // Preparar claves y prefijos
        $cap_key = $wpdb->prefix . 'capabilities';

        // 1) Contar usuarios (clientes registrados) en rango
        $count_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} AS u
             INNER JOIN {$wpdb->usermeta} AS um ON u.ID = um.user_id
             WHERE DATE(u.user_registered) BETWEEN %s AND %s
             AND um.meta_key = %s
             AND um.meta_value LIKE %s",
            $created_at_min,
            $created_at_end,
            $cap_key,
            '%"customer"%'
        );
        $total_users = intval($wpdb->get_var($count_query));

        // 2) Obtener users paginados: si el offset está dentro del conjunto de usuarios
        if ($offset < $total_users) {
            $users_to_fetch = min($per_page, $total_users - $offset);
            $users_query = $wpdb->prepare(
                "SELECT u.ID FROM {$wpdb->users} AS u
                 INNER JOIN {$wpdb->usermeta} AS um ON u.ID = um.user_id
                 WHERE DATE(u.user_registered) BETWEEN %s AND %s
                 AND um.meta_key = %s
                 AND um.meta_value LIKE %s
                 ORDER BY u.user_registered ASC
                 LIMIT %d OFFSET %d",
                $created_at_min,
                $created_at_end,
                $cap_key,
                '%"customer"%',
                $users_to_fetch,
                $offset
            );
            $users = $wpdb->get_results($users_query);

            foreach ($users as $u) {
                // Reutilizamos la función existente para construir el contacto
                $contact = $this->get_contact($u->ID);
                if ($contact) {
                    $contacts_page[] = $contact;
                }
            }
        }

        // 3) Si la página no está completa, completar con clientes guest (pedidos con _customer_user = 0)
        $remaining = $per_page - count($contacts_page);
        if ($remaining > 0) {
            // Calcular offset para la parte de guest en la paginación global (si la página pedida empieza después de todos los users)
            $guest_global_offset = max(0, $offset - $total_users);

            // Consultar emails únicos de pedidos guest (entre fechas) usando postmeta (más eficiente que wc_get_orders con limit -1)
            $guest_query = $wpdb->prepare(
                "SELECT DISTINCT pm_email.meta_value AS email, p.ID as order_id
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm_user ON pm_user.post_id = p.ID AND pm_user.meta_key = '_customer_user'
                 JOIN {$wpdb->postmeta} pm_email ON pm_email.post_id = p.ID AND pm_email.meta_key = '_billing_email'
                 WHERE p.post_type = 'shop_order'
                   AND DATE(p.post_date) BETWEEN %s AND %s
                   AND pm_user.meta_value = %s
                   AND pm_email.meta_value != ''
                 GROUP BY pm_email.meta_value
                 ORDER BY p.post_date ASC
                 LIMIT %d OFFSET %d",
                $created_at_min,
                $created_at_end,
                '0',
                $remaining,
                $guest_global_offset
            );

            $guest_rows = $wpdb->get_results($guest_query);

            // Evitar duplicados frente a usuarios ya añadidos
            $existing_emails = array();
            foreach ($contacts_page as $c) {
                if (!empty($c['email'])) $existing_emails[] = $c['email'];
            }

            foreach ($guest_rows as $gr) {
                if (empty($gr->email)) continue;
                if (in_array($gr->email, $existing_emails, true)) continue;

                // Cargar pedido mínimo por ID y reutilizar get_guest_contact para consistencia
                $order = wc_get_order($gr->order_id);
                if ($order) {
                    $guest_contact = $this->get_guest_contact($order);
                    if (isset($guest_contact['email']) && !in_array($guest_contact['email'], $existing_emails, true)) {
                        $contacts_page[] = $guest_contact;
                        $existing_emails[] = $guest_contact['email'];
                        if (count($contacts_page) >= $per_page) break;
                    }
                }
            }
        }

        // Responder solo con la página calculada (ya paginada por BD)
        $response = new WP_REST_Response($contacts_page, 200);
        return $response;
    }

    /*get contacts by user_id - revised*/

    function get_contact($user_id){
        global $wpdb;
        global $woocommerce;
        $user = get_userdata($user_id);
        $lang = get_bloginfo("language");
        $customer_meta = get_user_meta($user_id);
        $customer_phones = array();
        $site_name = get_option('blogname');
        $site_name = empty($site_name) ? 'WordPress' : $site_name;
        $customer = new WC_Customer($user_id);
        $suscripcion = get_user_meta($user_id, 'suscripcion_newsletter', true);
        $meta_keys = array('shipping_nif', 'vat_number', 'dni_number', 'billing_nif');
        $customer_dni = '';
        foreach ( $meta_keys as $meta_key ) {
            $meta_value = get_user_meta($user_id, $meta_key, true);
            if (!empty($meta_value) ) {
                $customer_dni = $meta_value;
            }
        }

        if ( $user_id != 0 ) {
            $data = array(
                'id_customer'     => $user_id,
                'email'           => $customer->email,
                'contact_source'  => get_option('blogname'),
                'user_registered' => $user->user_registered,
                'custom_fields'   => [],
                'tags'            => array(
                                        'WooCommerce',
                                        $site_name,
                                    )
            );
            if (get_option('CLIENTIFY_GDPR') == "1") {
				$suscripcion = get_user_meta($user_id, 'suscripcion_newsletter', true);

				// Asigna el valor de 'suscripcion' directamente a 'gdpr_accept'
				if ($suscripcion === "accept") {
					$data['gdpr_accept'] = $suscripcion;
				}
				if ($suscripcion === "revoke") {
					$data['gdpr_accept'] = $suscripcion;
				}
			}
			/*  condition to support other gdprs */
			$content_comm = get_user_meta($user_id, 'content_comm', true);
			$data['gdpr_accept'] = $content_comm;
			if (!empty($content_comm) && ($content_comm === "yes")) {
				$data['gdpr_accept'] = "accept";
			}

			if ( !empty($customer_dni) ) {
				$data['identification'] = $customer_dni;
			}

            if ( !empty(get_user_meta($user_id)['first_name'][0]) ) {
                $data['first_name'] = get_user_meta($user_id)['first_name'][0];
            }
            if ( !empty(get_user_meta($user_id)['last_name'][0]) ) {
                $data['last_name'] = get_user_meta($user_id)['last_name'][0];
            }
            if ( !empty($lang) ) {
                $data['custom_field'] = array(
                    'field' => 'ecommerce_language',
                    'value' => $lang,
                );
            }
            if ( !empty($customer_meta['billing_address_1'][0]) ) {

                if ( isset($customer_meta['billing_first_name'][0]) && !empty($customer_meta['billing_first_name'][0]) ) {
                    $data['first_name'] = $customer_meta['billing_first_name'][0];
                }
                if ( isset($customer_meta['billing_last_name'][0]) && !empty($customer_meta['billing_last_name'][0]) ) {
                    $data['last_name'] = $customer_meta['billing_last_name'][0];
                }

                $street = $customer_meta['billing_address_1'][0] . (!empty($customer_meta['billing_address_2'][0]) ? ', ' . $customer_meta['billing_address_2'][0] : '');
                $city = $customer_meta['billing_city'][0];
                $country = WC()->countries->countries[$customer_meta['billing_country'][0]];
                if (isset($customer_meta['billing_postcode'])){
                    $postal_code = $customer_meta['billing_postcode'][0];
                }else{
                    $postal_code = '';
                }
                
                $customer_address = array('type' => 1);

                if ( $street ) {
                    $customer_address['street'] = $street;
                }

                if ( $city ) {
                    $customer_address['city'] = $city;
                }

                if ( $country ) {
                    $customer_address['country'] = $country;
                }

                if ( $postal_code ) {
                    $customer_address['postal_code'] = $postal_code;
                }

                if ( !empty($customer_meta['billing_state'][0]) ) {
                    $customer_address['state'] = WC()->countries->get_states($customer_meta['billing_country'][0])[$customer_meta['billing_state'][0]];
                    if ( empty($customer_address['state']) ) {
                        unset($customer_address['state']);
                    }
                }

                $data['addresses'][] = $customer_address;

                if ( !empty($customer_meta['billing_company'][0]) ) {
                    $data['company'] = $customer_meta['billing_company'][0];
                }

                if ( !empty($customer_meta['billing_phone'][0]) && !in_array($customer_meta['billing_phone'][0], $customer_phones) ) {
                    $data['phones'][] = array('phone' => $customer_meta['billing_phone'][0]);
                    $customer_phones[] = $customer_meta['billing_phone'][0];
                }
            }
        }
        return $data;
    }

    /* get guest contact from WC_Order */
    function get_guest_contact($order) {
        $billing_email = $order->get_billing_email();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        $billing_phone = $order->get_billing_phone();
        $billing_address_1 = $order->get_billing_address_1();
        $billing_address_2 = $order->get_billing_address_2();
        $billing_city = $order->get_billing_city();
        $billing_state = $order->get_billing_state();
        $billing_postcode = $order->get_billing_postcode();
        $billing_country = $order->get_billing_country();
        $billing_company = $order->get_billing_company();
        $site_name = get_option('blogname');
        $lang = get_bloginfo("language");

        $street = $billing_address_1 . (!empty($billing_address_2) ? ', ' . $billing_address_2 : '');

        $guest_data = array(
            'id_customer'    => 0,
            'email'          => $billing_email,
            'first_name'     => $billing_first_name,
            'last_name'      => $billing_last_name,
            'contact_source' => $site_name,
            'custom_fields'  => [],
            'tags'           => array('WooCommerce', $site_name),
            'addresses'      => array(
                array(
                    'type'        => 1,
                    'street'      => $street,
                    'city'        => $billing_city,
                    'state'       => $billing_state,
                    'postal_code' => $billing_postcode,
                    'country'     => WC()->countries->countries[$billing_country] ?? $billing_country,
                )
            ),
            'company' => $billing_company,
            'phones'  => array()
        );

        if (!empty($billing_phone)) {
            $guest_data['phones'][] = array('phone' => $billing_phone);
        }

        if (!empty($lang)) {
            $guest_data['custom_field'] = array(
                'field' => 'ecommerce_language',
                'value' => $lang,
            );
        }

        return $guest_data;
    }

    /* get products -revised*/
    public function get_all_products ($params){

        $created_to = $params->get_param('created_to');
        $created_from = empty($params->get_param('created_from')) ? date('d-m-Y') : $params->get_param('created_from');
        // $created_at_end = empty($params->get_param('created_at_end')) ? date('d-m-Y') : $params->get_param('created_at_end');
        $created_at_end = empty($params->get_param('created_at_end')) ? date('d-m-Y', strtotime('+1 day')) : date('d-m-Y', strtotime($params->get_param('created_at_end') . ' +1 day'));

        $per_page = $params->get_param('per_page');
        $paged = ($params->get_param('page')) ? $params->get_param('page') : 1;
        $offset = ( $per_page * $paged ) - $per_page;

        /*$total = wc_get_products( array(			
                'date_created' => '>' . $created_from,
                'orderby'      => 'ID',
                'order'        => 'ASC',
                'return'       => 'ids',
                'limit'        => -1,
		   ));*/

        $limit = isset($per_page) ? $per_page : -1;
        $products = wc_get_products(array(
            'status'     => 'publish',
            'orderby'    => 'ID',
            'order'      => 'ASC',
            'limit'      => isset($per_page) ? $per_page : -1,
            'offset'     => $offset,
            'paged'      => $paged,
            'date_query' => array(
                                array(
                                    'column' => 'post_date',
                                    'after'  => $created_from,
                                    //allow exact matches to be returned
                                    'inclusive' => true,
                                ),
                                array(
                                    'column' => 'post_date',
                                    'before' => $created_at_end,
                                    //allow exact matches to be returned
                                    'inclusive' => true,
                                ),
                            )
        ));

        if ( isset($per_page) ) {
        $to_per = count($products)/$per_page;
        $total_pages = is_float($to_per) ? intval($to_per+1) : $to_per ;
        }

        
        $items = array();
        
        foreach ( $products as $product ) {
            $categories = array();
            $subcategories= array();
            $join_categories = "";
            $join_subcategories = "";
            
            $terms = get_the_terms($product->id, 'product_cat');

            foreach ($terms as $term) {
                if ($term->parent == 0) {
                    // Categoría principal
                    if (!in_array($term->term_id, $categories)) {
                        $categories[] = $term->term_id;
                        $join_categories .= ($join_categories == "" ? "" : ",") . $term->term_id . ":" . $term->slug;
                    }
                } else {
                    // Subcategoría
                    if (!in_array($term->term_id, $subcategories)) {
                        $subcategories[] = $term->term_id;
                        $parent_id = $term->parent;
                        
                        // Asegurarse de que la categoría principal esté añadida
                        if (!in_array($parent_id, $categories)) {
                            $parent_term = get_term($parent_id, 'product_cat');
                            $categories[] = $parent_id;
                            $join_categories .= ($join_categories == "" ? "" : ",") . $parent_id . ":" . $parent_term->slug;
                        }
            
                        // Construir la cadena de subcategorías
                        $join_subcategories .= ($join_subcategories == "" ? "" : ",") . $term->term_id . ":" . $term->slug . "|parent_id:" . $parent_id;
                    }
                }
            }

            $join_cat = $join_categories."/".$join_subcategories;  
            try {
                $sku = $product->get_sku();
            } catch (Exception $e) {
                $sku = '';
            }
            $image_id  = $product->get_image_id();
            $image_url = wp_get_attachment_image_url($image_id, 'full');

            $display_price_with_tax_shop = get_option('woocommerce_tax_display_shop'); // 'incl' or 'excl'
		    $display_price_with_tax_cart = get_option('woocommerce_tax_display_cart'); // 'incl' or 'excl'

            if ($product->get_type() == "variable") {
                foreach ($product->get_available_variations() as $variation) {
                
                        $variation_id=$variation['variation_id'];
                        $variable_product= new WC_Product_Variation( $variation_id );

                        if ( $product->get_name() == $variable_product->get_name() ) {
							
							$attributes = $variable_product->attribute_summary;

							// Dividir la cadena en partes utilizando ":"
							$partes = explode(",", $attributes);
							// Inicializar un array para almacenar las partes relevantes
							$array_parts = [];

							// Iterar sobre cada parte y guardar solo las que contienen ":"
							foreach ($partes as $parte) {
								if (strpos($parte, ":") !== false) {
									// Si la parte contiene ":", agregarla a las partes relevantes
									$array_parts[] = trim(explode(":", $parte)[1]); // Tomar solo lo que está después de ":"
								}
							}
                            $attributes_name = implode(", ", $array_parts);
                            
                            // Obtener el nombre del producto de la variación
                            $product_name = $variable_product->get_name();

							if(empty($attributes_name)){
                                $full_variation_name = $product_name;
                            }else{
                                $full_variation_name = $product_name . ' - ' . $attributes_name;
                            }
                        }else{
                            $full_variation_name = $variable_product->get_name();
                        }

                        $image_id  = $variable_product->image_id;
                        $image_url = wp_get_attachment_image_url($image_id, 'full');
 
                        if ( empty($image_url) ) {
                            $attachment_ids = $product->get_gallery_image_ids();
                            if (!empty($attachment_ids)) {
                                $first_image_id = reset($attachment_ids);
                                $image_url = wp_get_attachment_url($first_image_id);
                            }
						}
                        
                        // if ( $variable_product->is_on_sale() ) {
                        //     $price = $variable_product->get_sale_price();
                        // } else {
                        //     $price = $variable_product->get_regular_price();
                        // }

                        if ($display_price_with_tax_shop  === 'incl') {
							$price = wc_get_price_including_tax($variable_product);
						} else {
							$price = wc_get_price_excluding_tax($variable_product);
						}

                        $description = trim(strip_tags($variable_product->description));

                        if ( $description == "" ) {
                            $description = trim(strip_tags($product->description));
                        }

                        try {
                            $sku = $variable_product->sku;
                        } catch (Exception $e) {
                            $sku = '';
                        }

                        $items[] = array(
                            'id'          => $variation_id,
                            'name'        => $full_variation_name,
                            'description' => $description,
                            'category'    => $join_cat,
                            'sku'         => $sku,
                            'image_url'   => $image_url,
                            'item_url'    => get_permalink($variable_product->id),
                            'price'       => $price == '' ||   $price == NULL ? 0 : number_format($price, 2, '.', ''),
                            'currency'    => get_woocommerce_currency()
                            
                        );


                    
                }
        }
        else {

            // if ($product->is_on_sale()) {
            //     $price = $product->get_sale_price();
            // } else {
            //     $price = $product->get_regular_price();
            // }

            if ($display_price_with_tax_shop === 'incl') {
				// Obtener precio con impuestos
				$price = wc_get_price_including_tax($product);
			} else {
				// Obtener precio sin impuestos
				$price = wc_get_price_excluding_tax($product);
			}


            $items[] = array(
                'id'          => $product->id,
                'name'        => $product->get_name(),
                'description' => trim(strip_tags($product->description)),
                'category'    => $join_cat,
                'sku'         => $sku,
                'image_url'   => $image_url,
                'item_url'    => get_permalink($product->id),
                'price'       => $price == '' ||   $price == NULL ? 0 : number_format($price, 2, '.', ''),
                'currency'    => get_woocommerce_currency()
                
            );
            }
        }
        $response = new WP_REST_Response($items, 200);        
        //$response->header( 'Link', $total_pages); // maximum number of pages 
		return $response;

    }
    /* get abandonded carts - revised*/
    function get_list_abandoned_cart($params){

        global $wpdb;
        $all = array();
        $created_from = date("Y-m-d", strtotime($params->get_param('created_from')));
        $created_at_end = empty($params->get_param('created_at_end')) ? date("Y-m-d") : $params->get_param('created_at_end');

        $per_page = empty($params->get_param('per_page')) ? 0 : $params->get_param('per_page');
        $paged = empty($params->get_param('page')) ? 1 : $params->get_param('page');
		$page  = (int) $paged;
		$per_page = (int) $per_page;
		$offset = ($page - 1) * $per_page;
		$cart_abandonment_table = $wpdb->prefix . 'clientify_ca_cart_abandonment';

		if ( $created_from != 0 ) {
			$abandoned_carts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT checkout_id, session_id, id FROM {$cart_abandonment_table} WHERE DATE(time) BETWEEN %s AND %s" . ( $per_page > 0 ? " LIMIT %d OFFSET %d" : "" ),
					date("Y-m-d", strtotime($created_from)),
					date("Y-m-d", strtotime($created_at_end)),
					...( $per_page > 0 ? [ $per_page, $offset ] : [] )
				)
			);
		} else {
			$abandoned_carts = $wpdb->get_results(
				$per_page > 0
					? $wpdb->prepare( "SELECT checkout_id, session_id, id FROM {$cart_abandonment_table} LIMIT %d OFFSET %d", $per_page, $offset )
					: "SELECT checkout_id, session_id, id FROM {$cart_abandonment_table}"
			);
		}

        $endpoint_class = new Clientify_Endpoint();
        $url_base = $endpoint_class->get_local_api_url();

        $helper = new Clientify_Helper();

        foreach ( $abandoned_carts as $abandoned_cart ) {
            if ($abandoned_cart->session_id)  {
                $details          = $helper->get_checkout_details( $abandoned_cart->session_id );   
                $user_details     = (object) maybe_unserialize( $details->other_fields );
                $token_data       = array( 'wcf_session_id' => $details->session_id );
                $items = array();
                $cart_content = maybe_unserialize( $details->cart_contents );
                    
                if ( ! is_array( $cart_content ) || ! count( $cart_content ) ) {
                    return;
                }
                $total_price = 0;
                $total = 0;
                $discount = 0;
                $tax = 0;

                foreach ($cart_content as $cart_item) {
                
                    $discount = $discount + ( floatval($cart_item['line_subtotal']) - floatval($cart_item['line_total']) );
                    $total = $total + floatval($cart_item['line_subtotal']);
                    $tax = $tax + floatval($cart_item['line_tax']);
                    $shipping = 0 ;
                    $product_id = $cart_item['product_id'];
                    $categories = array();
                    $subcategories= array();
                    $terms = get_the_terms($product_id, 'product_cat');

                    $join_categories = "";
                    $join_subcategories = "";
                    
                    $product = wc_get_product($product_id);
                    $price = $product->get_price();
                    $total_price += $price;
                    $without_reduction = $price;
                    $discount = $without_reduction - $cart_item['line_total'];
                
                    if ( $price == 0 ) {
                        $discount = 0;
                    }else{
                        $discount = round( ($discount / $without_reduction) * 100, 2);
                    }

                    if (!empty($terms)) {
                        foreach ($terms as $term) {
                            if ($term->parent == 0) {
                                // Categoría principal
                                if (!in_array($term->term_id, $categories)) {
                                    $categories[] = $term->term_id;
                                    $join_categories .= ($join_categories == "" ? "" : ",") . $term->term_id . ":" . $term->slug;
                                }
                            } else {
                                // Subcategoría
                                if (!in_array($term->term_id, $subcategories)) {
                                    $subcategories[] = $term->term_id;
                                    $parent_id = $term->parent;
                                    
                                    // Asegurarse de que la categoría principal esté añadida
                                    if (!in_array($parent_id, $categories)) {
                                        $parent_term = get_term($parent_id, 'product_cat');
                                        $categories[] = $parent_id;
                                        $join_categories .= ($join_categories == "" ? "" : ",") . $parent_id . ":" . $parent_term->slug;
                                    }
                        
                                    // Construir la cadena de subcategorías
                                    $join_subcategories .= ($join_subcategories == "" ? "" : ",") . $term->term_id . ":" . $term->slug . "|parent_id:" . $parent_id;
                                }
                            }
                        }
                    }

                    $price = $product->get_sale_price();
                    $discount = $cart_item['line_subtotal'] - $cart_item['line_total'];
                    $discount_val = ($discount <= 0) ? 0 : (($discount / $cart_item['line_subtotal']) * 100) ;
                    
                    if ( $cart_item['variation_id'] ) {
                        $variable_product= new WC_Product_Variation( $cart_item['variation_id'] );
                        
                        if ( $variable_product->is_on_sale() ) {
                            $price = $variable_product->get_sale_price();
                        } else {
                            $price = $variable_product->get_regular_price();
                        }

                        $description = '';
                        $image_id  = $variable_product->image_id;
                        $image_url = wp_get_attachment_image_url($image_id, 'full');

                        if ( empty($image_url) ) {
                            $attachment_ids = $product->get_gallery_image_ids();
                            if (!empty($attachment_ids)) {
                                $first_image_id = reset($attachment_ids);
                                $image_url = wp_get_attachment_url($first_image_id);
                            }
                        }
                        if ( empty($variable_product->description) ) {
                            // En caso de que la descripción sea vacía, obtener la descripción alternativa de $product
                            $description = wp_strip_all_tags(str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $product->get_description()));
                        }
                        else{
                            $description = wp_strip_all_tags(str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $variable_product->description));
                        }

                        $join_cat = $join_categories."/".$join_subcategories;
                        
                        $items[] = array(
                            'name'        => $variable_product->get_name(),
                            'description' => $description,
                            'category'    => $join_cat,
                            'sku'         => $variable_product->sku,
                            'image_url'   => $image_url,
                            'item_url'    => $product->get_permalink($cart_item),
                            'price'       => number_format($price, 2, '.', ''),
                            'quantity'    => (int) $cart_item['quantity'],
                            'discount'    => $discount_val,
                        );
                    }
                    else{  

                        if ($product->is_on_sale()) {
                            $price = $product->get_sale_price();
                        } else {
                            $price = $product->get_regular_price();
                        }

                        if ( $price == 0 ) {
                            $discount = 0;
                        }else{
                            $discount = round( ($discount / $without_reduction) * 100, 2);
                        }  
                    
                        $join_cat = $join_categories."/".$join_subcategories;
                        try {
                            $sku = $product->get_sku();
                        } catch (Exception $e) {
                            $sku = '';
                        }
                        $items[] = array(
                            'name'        => $product->get_title(),
                            'description' => $product->get_description(),
                            'category'    => $join_cat,
                            'sku'         => $sku,
                            'image_url'   => get_the_post_thumbnail_url($product_id),
                            'item_url'    => $product->get_permalink($cart_item),
                            'price'       => number_format($price, 2, '.', ''),
                            'quantity'    => (int) $cart_item['quantity'],
                            'discount'    => $discount_val,
                        );
                       
                    } 
                }

                if (isset($user_details->wcf_shipping_cost)) {
                    $shipping = number_format($user_details->wcf_shipping_cost, 2, '.', '');
                }

                
                $data = array(
                            'status'         => 'abandoned',
                            'abandoned_date' => date('Y-m-d', strtotime($details->time)),
                            'ecommerce'      => 'woocommerce',
                            'shop_name'      => get_option('blogname'),
                            'order_url'      => $helper->get_checkout_url( $details->checkout_id, $token_data ),
                            'currency'       => get_option('woocommerce_currency'),
                            'store_url'      => $url_base,
                            'products'       => $items,
                            'price'          => $details->cart_total,
                            'shipping'       => $shipping,
                            'coupon'         =>  0,
                            
                        );
                $lang = get_bloginfo("language");
                $site_name = get_option('blogname');
                $site_name_valid = empty( $site_name ) ? 'WordPress' : $site_name;

                $data['contact'] = array(
                                    'id_customer'     => '',
                                    'email'           => $details->email,
                                    'contact_source'  => get_option('blogname'),
                                    'custom_field'   => [],
                                    'tags'            => array(
                                                            'woocommerce',
                                                            $site_name_valid,
                                                        )
                                );
                if ( !empty( $user_details->wcf_first_name ) ) {
                    $data['contact']['first_name'] = $user_details->wcf_first_name;
                }
                if ( !empty($user_details->wcf_last_name) ) {
                    $data['contact']['last_name'] = $user_details->wcf_last_name;
                }
                if ( !empty($lang) ) {
                    $data['contact']['custom_field'] = array(
                        'field' => 'ecommerce_language',
                        'value' => $lang,
                    );
                }

                $street = $user_details->wcf_billing_address_1 . $user_details->wcf_billing_address_2;
                $city = $user_details->wcf_shipping_city;
                $country = $user_details->wcf_shipping_country;
                $postal_code = $user_details->wcf_billing_postcode;
                $customer_address = array('type' => 1);

                if ( $street ) {
                    $customer_address['street'] = $street;
                }
                if ( $city ) {
                    $customer_address['city'] = $city;
                }
                if ( $country ) {
                    $customer_address['country'] = $country;
                }
                if ( $postal_code ) {
                    $customer_address['postal_code'] = $postal_code;
                }

                if ( !empty($user_details->wcf_billing_state) ) {
                    $customer_address['state'] = $user_details->wcf_billing_state;
                    if ( empty($customer_address['state']) ) {
                        unset($customer_address['state']);
                    }
                }
                $data['contact']['addresses'][] = $customer_address;

                if ( !empty($user_details->wcf_billing_company) ) {
                    $data['contact']['company'] = $user_details->wcf_billing_company;
                }

                if ( !empty($user_details->wcf_phone_number) ) {
                    if ( !isset($customer_phones) ) $customer_phones = array();
                    $data['contact']['phones'][] = array('phone' => $user_details->wcf_phone_number);
                    $customer_phones[] = $user_details->wcf_phone_number;
                }

                $data['cart_id'] = $abandoned_cart->id;
                $data['order_id'] = $abandoned_cart->id;

                $all [] = $data;

            }

        }//foreach
        
        $total_pages = $per_page > 0 ? ceil(count($valid_ids) / $per_page) : 1;
        $response = new WP_REST_Response($all, 200);
        $response->header('X-WP-Total', count($valid_ids));
        $response->header('X-WP-TotalPages', $total_pages);
        return $response;

	}

    /* sync abandonded carts - revised*/
    function sync_abandoned_cart($params){

        global $wpdb;
        $api = new Clientify_Api;
        $all = array();
        $result_sync = array();
        $created_from = date("Y-m-d", strtotime($params->get_param('created_from')));
        $created_end = empty($params->get_param('created_at_end')) ? date("Y-m-d") : $params->get_param('created_at_end');

        $per_page = empty($params->get_param('per_page')) ? 0 : $params->get_param('per_page');
        $paged = empty($params->get_param('page')) ? 1 : $params->get_param('page');
        $page     = (int) $paged;
        $per_page = (int) $per_page;
        $offset   = ($page - 1) * $per_page;
        $cart_abandonment_table = $wpdb->prefix . 'clientify_ca_cart_abandonment';

        if ( $created_from != 0 ) {
            $abandoned_carts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT checkout_id, session_id, id FROM {$cart_abandonment_table} WHERE DATE(time) BETWEEN %s AND %s" . ( $per_page > 0 ? " LIMIT %d OFFSET %d" : "" ),
                    date("Y-m-d", strtotime($created_from)),
                    date("Y-m-d", strtotime($created_end)),
                    ...( $per_page > 0 ? [ $per_page, $offset ] : [] )
                )
            );
        } else {
            $abandoned_carts = $wpdb->get_results(
                $per_page > 0
                    ? $wpdb->prepare( "SELECT checkout_id, session_id, id FROM {$cart_abandonment_table} LIMIT %d OFFSET %d", $per_page, $offset )
                    : "SELECT checkout_id, session_id, id FROM {$cart_abandonment_table}"
            );
        }

        $endpoint_class = new Clientify_Endpoint();
        $url_base = $endpoint_class->get_local_api_url();

        $helper = new Clientify_Helper();

        foreach ( $abandoned_carts as $abandoned_cart ) {
            if ($abandoned_cart->session_id)  {
                $details          = $helper->get_checkout_details( $abandoned_cart->session_id );   
                $user_details     = (object) maybe_unserialize( $details->other_fields );
                $token_data       = array( 'wcf_session_id' => $details->session_id );
                $items = array();
                $cart_content = maybe_unserialize( $details->cart_contents );
                    
                if ( ! is_array( $cart_content ) || ! count( $cart_content ) ) {
                    return;
                }
                $total_price = 0;
                $total = 0;
                $discount = 0;
                $tax = 0;

                foreach ($cart_content as $cart_item) {
                
                    $discount = $discount + ( floatval($cart_item['line_subtotal']) - floatval($cart_item['line_total']) );
                    $total = $total + floatval($cart_item['line_subtotal']);
                    $tax = $tax + floatval($cart_item['line_tax']);
                    $shipping = 0 ;
                    $product_id = $cart_item['product_id'];
                    $categories = array();
                    $subcategories= array();
                    $terms = get_the_terms($product_id, 'product_cat');
                    $join_categories = "";
                    $join_subcategories = "";
                    
                    $product = wc_get_product($product_id);
                    $price = $product->get_price();
                    $total_price += $price;
                    $without_reduction = $price;
                    $discount = $without_reduction - $cart_item['line_total'];
                
                    if ( $price == 0 ) {
                        $discount = 0;
                    }else{
                        $discount = round( ($discount / $without_reduction) * 100, 2);
                    }
                    if (!empty($terms)) {
                        foreach ($terms as $term) {
                            if ($term->parent == 0) {
                                // Categoría principal
                                if (!in_array($term->term_id, $categories)) {
                                    $categories[] = $term->term_id;
                                    $join_categories .= ($join_categories == "" ? "" : ",") . $term->term_id . ":" . $term->slug;
                                }
                            } else {
                                // Subcategoría
                                if (!in_array($term->term_id, $subcategories)) {
                                    $subcategories[] = $term->term_id;
                                    $parent_id = $term->parent;
                                    
                                    // Asegurarse de que la categoría principal esté añadida
                                    if (!in_array($parent_id, $categories)) {
                                        $parent_term = get_term($parent_id, 'product_cat');
                                        $categories[] = $parent_id;
                                        $join_categories .= ($join_categories == "" ? "" : ",") . $parent_id . ":" . $parent_term->slug;
                                    }
                        
                                    // Construir la cadena de subcategorías
                                    $join_subcategories .= ($join_subcategories == "" ? "" : ",") . $term->term_id . ":" . $term->slug . "|parent_id:" . $parent_id;
                                }
                            }
                        }
                    }
                
                    $price = $product->get_sale_price();
                    $discount = $cart_item['line_subtotal'] - $cart_item['line_total'];
                    $discount_val = ($discount <= 0) ? 0 : (($discount / $cart_item['line_subtotal']) * 100) ;
                    
                    if ( $cart_item['variation_id'] ) {
                        $variable_product= new WC_Product_Variation( $cart_item['variation_id'] );

                        if ( $variable_product->is_on_sale() ) {
                            $price = $variable_product->get_sale_price();
                        } else {
                            $price = $variable_product->get_regular_price();
                        }

                        $description = '';
                        $image_id  = $variable_product->image_id;
                        $image_url = wp_get_attachment_image_url($image_id, 'full');
   
                        if ( empty($image_url) ) {
                            $attachment_ids = $product->get_gallery_image_ids();
                            if (!empty($attachment_ids)) {
                                $first_image_id = reset($attachment_ids);
                                $image_url = wp_get_attachment_url($first_image_id);
                            }
                        }
                        if ( empty($variable_product->description) ) {
                            // En caso de que la descripción sea vacía, obtener la descripción alternativa de $product
                            $description = wp_strip_all_tags(str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $product->get_description()));
                        }
                        else{
                            $description = wp_strip_all_tags(str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $variable_product->description));
                        }

                        $join_cat = $join_categories."/".$join_subcategories;
                        
                        $items[] = array(
                            'name'        => $variable_product->get_name(),
                            'description' => $description,
                            'category'    => '',
                            'sku'         => $variable_product->sku,
                            'image_url'   => $image_url,
                            'item_url'    => $product->get_permalink($cart_item),
                            'price'       => number_format($price, 2, '.', ''),
                            'quantity'    => (int) $cart_item['quantity'],
                            'discount'    => $discount_val,
                        );
                    }
                    else{  

                        if ($product->is_on_sale()) {
                            $price = $product->get_sale_price();
                        } else {
                            $price = $product->get_regular_price();
                        }

                        if ( $price == 0 ) {
                            $discount = 0;
                        }else{
                            $discount = round( ($discount / $without_reduction) * 100, 2);
                        }
                       
                        $join_cat = $join_categories."/".$join_subcategories;
                        try {
                            $sku = $product->get_sku();
                        } catch (Exception $e) {
                            $sku = '';
                        }
                        $items[] = array(
                            'name'        => $product->get_title(),
                            'description' => $product->get_description(),
                            'category'    => $join_cat,
                            'sku'         => $sku,
                            'image_url'   => get_the_post_thumbnail_url($product_id),
                            'item_url'    => $product->get_permalink($cart_item),
                            'price'       => number_format($price, 2, '.', ''),
                            'quantity'    => (int) $cart_item['quantity'],
                            'discount'    => $discount_val,
                        );
                       
                    } 
                }
                
                if (isset($user_details->wcf_shipping_cost)) {
                    $shipping = number_format($user_details->wcf_shipping_cost, 2, '.', '');
                }
            

                $data = array(
                            'status'         => 'abandoned',
                            'abandoned_date' => date('Y-m-d', strtotime($details->time)),
                            'ecommerce'      => 'woocommerce',
                            'shop_name'      => get_option('blogname'),
                            'order_url'      => $helper->get_checkout_url( $details->checkout_id, $token_data ),
                            'currency'       => get_option('woocommerce_currency'),
                            'store_url'      => $url_base,
                            'products'       => $items,
                            'price'          => $details->cart_total,
                            'shipping'       => $shipping,
                            'coupon'         =>  0,
                            
                        );
                $lang = get_bloginfo("language");
                $site_name = get_option('blogname');
                $site_name_valid = empty( $site_name ) ? 'WordPress' : $site_name;

                $data['contact'] = array(
                                    'id_customer'     => '',
                                    'email'           => $details->email,
                                    'contact_source'  => get_option('blogname'),
                                    'custom_field'   => [],
                                    'tags'            => array(
                                                            'woocommerce',
                                                            $site_name_valid,
                                                        )
                                );
                if ( !empty( $user_details->wcf_first_name ) ) {
                    $data['contact']['first_name'] = $user_details->wcf_first_name;
                }
                if ( !empty($user_details->wcf_last_name) ) {
                    $data['contact']['last_name'] = $user_details->wcf_last_name;
                }
                if ( !empty($lang) ) {
                    $data['contact']['custom_field'] = array(
                        'field' => 'ecommerce_language',
                        'value' => $lang,
                    );
                }

                $street = $user_details->wcf_billing_address_1 . $user_details->wcf_billing_address_2;
                $city = $user_details->wcf_shipping_city;
                $country = $user_details->wcf_shipping_country;
                $postal_code = $user_details->wcf_billing_postcode;
                $customer_address = array('type' => 1);

                if ( $street ) {
                    $customer_address['street'] = $street;
                }
                if ( $city ) {
                    $customer_address['city'] = $city;
                }
                if ( $country ) {
                    $customer_address['country'] = $country;
                }
                if ( $postal_code ) {
                    $customer_address['postal_code'] = $postal_code;
                }

                if ( !empty($user_details->wcf_billing_state) ) {
                    $customer_address['state'] = $user_details->wcf_billing_state;
                    if ( empty($customer_address['state']) ) {
                        unset($customer_address['state']);
                    }
                }
                $data['contact']['addresses'][] = $customer_address;

                if ( !empty($user_details->wcf_billing_company) ) {
                    $data['contact']['company'] = $user_details->wcf_billing_company;
                }

                if ( !empty($user_details->wcf_phone_number) ) {
                    if ( !isset($customer_phones) ) $customer_phones = array();
                    $data['contact']['phones'][] = array('phone' => $user_details->wcf_phone_number);
                    $customer_phones[] = $user_details->wcf_phone_number;
                }


                $data['cart_id'] = $abandoned_cart->id;
                $data['order_id'] = $abandoned_cart->id;
            

               
                 //Send data to Clientify
                $abandoned = $api->post_order_clientify( $data );
                $result_sync [] = $abandoned;

                $all [] = $data;

            }

        }//foreach

        //Send to api
        return $result_sync;
        
    }


    public function sync_orders($params){

        global $product;
        global $wpdb;
        $api = new Clientify_Api;
        
        $endpoint_class = new Clientify_Endpoint();
        $url_base = $endpoint_class->get_local_api_url();
        $created_at_min = empty($params->get_param('created_at_min')) ? date('Y-m-d') : Clientify_Helper::parse_date_flexible($params->get_param('created_at_min'), 'Y-m-d');
        $created_at_end = empty($params->get_param('created_at_end')) ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d', strtotime(Clientify_Helper::parse_date_flexible($params->get_param('created_at_end'), 'Y-m-d') . ' +1 day'));
        $per_page = max(1, intval($params->get_param('per_page') ?: 25));
        $paged    = max(1, intval($params->get_param('page') ?: 1));
        $order_status_settings = get_option('CLIENTIFY_ORDER_STATUS');

        $all_ids = wc_get_orders([
            'date_created' => $created_at_min . '...' . $created_at_end,
            'status'       => $order_status_settings,
            'orderby'      => 'ID',
            'order'        => 'ASC',
            'limit'        => -1,
            'return'       => 'ids',
            'type'         => 'shop_order',
        ]);

        $valid_ids = [];
        if (!empty($all_ids)) {
            $placeholders = implode(',', array_fill(0, count($all_ids), '%d'));
            $valid_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT oi.order_id
                     FROM {$wpdb->prefix}woocommerce_order_items oi
                     INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                         ON oim.order_item_id = oi.order_item_id AND oim.meta_key = '_product_id'
                     INNER JOIN {$wpdb->posts} p
                         ON p.ID = oim.meta_value
                         AND p.post_type IN ('product','product_variation')
                         AND p.post_status NOT IN ('trash','auto-draft')
                     WHERE oi.order_id IN ($placeholders)
                     AND oi.order_item_type = 'line_item'",
                    ...$all_ids
                )
            );
            $valid_ids = array_values(array_intersect($all_ids, array_map('intval', $valid_ids)));
        }

        $page_ids = array_slice($valid_ids, ($paged - 1) * $per_page, $per_page);
        $orders   = array_filter(array_map('wc_get_order', $page_ids));
       
        $all = array();
        $result_sync = array();

        foreach( $orders as $order ) {
             
            if ( $order->get_type() === 'shop_order_refund' ) {
                continue;
            }

            $key = 'wc-' . $order->get_status();
            if ( in_array($key, $order_status_settings) ) {// check is order status is right
                
                $order_data = $order->get_data();
                $id_customer = $order->get_customer_id();
                $contact = null;
                $lang = get_bloginfo("language");
                $currency = $order->get_currency();
                $total_price = $order->get_total(); 
                $products = $order->get_items();         
                $items = array();
                $coupons_tags = array();
                $order_tags = array();

                foreach ( $products as $order_product ) {
                    $categories = array();
                    $subcategories= array();
                    $join_categories = "";
                    $join_subcategories = "";

                    $product = $order_product->get_product();

                    if (!$product) {
                        continue;
                    }

                    $terms = get_the_terms($order_product['product_id'], 'product_cat');

                    if (!empty($terms)) {
                        foreach ($terms as $term) {
                            if ($term->parent == 0) {
                                // Categoría principal
                                if (!in_array($term->term_id, $categories)) {
                                    $categories[] = $term->term_id;
                                    $join_categories .= ($join_categories == "" ? "" : ",") . $term->term_id . ":" . $term->slug;
                                }
                            } else {
                                // Subcategoría
                                if (!in_array($term->term_id, $subcategories)) {
                                    $subcategories[] = $term->term_id;
                                    $parent_id = $term->parent;

                                    // Asegurarse de que la categoría principal esté añadida
                                    if (!in_array($parent_id, $categories)) {
                                        $parent_term = get_term($parent_id, 'product_cat');
                                        $categories[] = $parent_id;
                                        $join_categories .= ($join_categories == "" ? "" : ",") . $parent_id . ":" . $parent_term->slug;
                                    }

                                    // Construir la cadena de subcategorías
                                    $join_subcategories .= ($join_subcategories == "" ? "" : ",") . $term->term_id . ":" . $term->slug . "|parent_id:" . $parent_id;
                                }
                            }
                        }
                    }

                    $join_cat = $join_categories."/".$join_subcategories;
                        
                    try {
                        $sku = $product->get_sku();
                    } catch (Exception $e) {
                        $sku = '';
                    }

                    $image_id  = $product->get_image_id();
                    $image_url = wp_get_attachment_image_url($image_id, 'full');
                    $product_id = $order_product['product_id'];
                    $product_instance = wc_get_product($product_id);
                    $product_full_description = $product_instance->get_description();
                    $tax_amount = $order->get_item_tax($order_product, true, true);
                    $inc_tax = $tax_amount > 0 ? true : false;
                    $quantity = $order_product->get_quantity(); 

                    if ($product->is_on_sale()) {
                        $price = $product->get_sale_price();
                    } else {
                        $price = $product->get_regular_price();
                    }

                    $discount_price = floatval($order_product['subtotal']) - floatval($order_product['total']);

                    if ( $inc_tax ) {
                        $price = wc_get_price_including_tax($product, array('price' => $price));
                    } else {
                        $price = wc_get_price_excluding_tax($product, array('price' => $price));
                    }
                    
                    if ($price == 0) {
                        $discount = 0;
                    } else {
                        // Calcular el descuento por unidad de producto
                        $unit_discount_price = $discount_price / $quantity;
                        $discount = ($unit_discount_price * 100) / $price;
                    }
                    
                    $items[] = array(
                        'name'        => $order_product->get_name(),
                        'description' => $product_full_description,
                        'category'    => $join_cat,
                        'sku'         => $sku,
                        'image_url'   => $image_url,
                        'item_url'    => get_permalink($order_product['product_id']),
                        'price'       => number_format($price, 2, '.', ''),
                        'quantity'    => $order_product->get_quantity(),
                        'discount'    => $discount != 0 ? round($discount) : 0, //$discount
                        );
                }
               
                //total discount
                $order_discount_total = $order->get_total_discount(!$inc_tax);

                $contact = 0;

                if ($id_customer){
                    $contact = $this->get_contact($id_customer);
                }else{
                    if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
                        $customer_phones = array();
                        $contact = array(
                            'email' => '',
                            'contact_source'  => get_option('blogname'),
                            'custom_fields'   => [],
                            'tags'            => array(
                                                    'woocommerce'
                                                )
                        );
                        if ( !empty($order->get_billing_email()) ) {
                            $contact['email'] = $order->get_billing_email();
                        }
                        if ( !empty($order->get_billing_first_name()) ) {
                            $contact['first_name'] = $order->get_billing_first_name();
                        }
                        if ( !empty($order->get_billing_last_name()) ) {
                            $contact['last_name'] = $order->get_billing_last_name();
                        }
                        if ( !empty($order->get_billing_company()) ) {
                            $contact['company'] = $order->get_billing_company();
                        }

                        $customer_address = array('type' => 1);
                        $street = $order->get_billing_address_1() . (!empty($order->get_billing_address_2()) ? ', ' . $order->get_billing_address_2() : '');
                        if ( !empty($street) ) {
                            $customer_address['street'] = $street;
                        }
                        if ( !empty($order->get_billing_city()) ) {
                            $customer_address['city'] = $order->get_billing_city();
                        }
                        if ( !empty($order->get_billing_state()) ) {
                            $customer_address['state'] = $order->get_billing_state();
                        }
                        if ( !empty($order->get_billing_postcode()) ) {
                            $customer_address['postal_code'] = $order->get_billing_postcode();
                        }
                        if ( !empty($order->get_billing_country()) ) {
                            $customer_address['country'] = $order->get_billing_country();
                        }
                        $contact['addresses'][] = $customer_address;
                        if ( !empty($order->get_billing_phone()) && !in_array($order->get_billing_phone(), $customer_phones ) ) {
                            $contact['phones'][] = array('phone' => $order->get_billing_phone());
                            $customer_phones[] = $order->get_billing_phone();
                        }
                        if ( !empty($lang) ) {
                            $contact['custom_field'] = array(
                                'field' => 'ecommerce_language',
                                'value' => $lang,
                            );
                        }
                    }elseif ( $order->get_shipping_first_name() || $order->get_shipping_last_name() ) {
                        $customer_phones = array();
                        $contact = array(
                            'email' => '',
                            'contact_source'  => get_option('blogname'),
                            'custom_fields'   => [],
                            'tags'            => array(
                                                    'woocommerce'
                                                )
                        );
            
                        if ( !empty($order->get_shipping_first_name()) ) {
                            $contact['first_name'] = $order->get_shipping_first_name();
                        }
                        if ( !empty($order->get_shipping_last_name()) ) {
                            $contact['last_name'] = $order->get_shipping_last_name();
                        }
                        if ( !empty($order->get_shipping_company()) ) {
                            $contact['company'] = $order->get_shipping_company();
                        }

                        $customer_address = array('type' => 1);
                        $street = $order->get_shipping_address_1() . (!empty($order->get_shipping_address_2()) ? ', ' . $order->get_shipping_address_2() : '');
                        if ( !empty($street) ) {
                            $customer_address['street'] = $street;
                        }
                        if ( !empty($order->get_shipping_city()) ) {
                            $customer_address['city'] = $order->get_shipping_city();
                        }
                        if ( !empty($order->get_shipping_state()) ) {
                            $customer_address['state'] = $order->get_shipping_state();
                        }
                        if ( !empty($order->get_shipping_postcode()) ) {
                            $customer_address['postal_code'] = $order->get_shipping_postcode();
                        }
                        if ( !empty($order->get_shipping_country()) ) {
                            $customer_address['country'] = $order->get_shipping_country();
                        }
                        $contact['addresses'][] = $customer_address;
                        
                        if ( !empty($lang) ) {
                            $contact['custom_field'] = array(
                                'field' => 'ecommerce_language',
                                'value' => $lang,
                            );
                        }
                    }
                }

                // Email siempre desde facturación de la orden
                if ( is_array($contact) && !empty($order->get_billing_email()) ) {
                    $contact['email'] = $order->get_billing_email();
                }

                $shipping = $order_data['shipping_total'];

				if ($shipping === 0 || $shipping === "0" || $shipping === '' || $shipping === null || $shipping === false ) {
					$shipping = 0; // Asegura que sea un entero 0
				}
				
                $coupons = $order->get_coupon_codes();
                $tipo_orden = $order->get_meta('tipodeorden');
                if (!empty($tipo_orden) && !in_array($tipo_orden, $order_tags)) {
                    $order_tags[] = $tipo_orden;
                }

                $data = array(
                    'contact' => $contact,
                    'status' => 'ordered',
                    'order_date' => $order_data['date_created']->date('Y-m-d H:i:s'),
                    'order_id' => $order->get_id(),
                    'ecommerce' => 'woocommerce',
                    'shop_name' => get_option('blogname'),
                    'order_url' => $order->get_view_order_url(),
                    'store_url' => $url_base,
                    'currency' => $currency,
                    'products' => $items,
                    'price' =>  number_format($total_price, 2, '.', ''),
                    'shipping' => $shipping,
                    'coupon' => $order_discount_total,
                    'order_tags' => $order_tags
                    );

                    if ($coupons) {
                        foreach ($coupons as $coupon_code) {
                            $coupons_tags[] = $coupon_code;
                        }
                        $data['coupon_tags'] = $coupons_tags;
                    }

                    if ( !empty($lang) ) {
                        $data['custom_field'] = array(
                        'field' => 'ecommerce_language',
                        'value' => $lang,
                        );
                    }

                if ( $contact && !empty($items) ) {
                    $order = $api->post_order_clientify( $data );
                    $result_sync [] = $order;
                    $all [] = array($data);
                }

                
            }//endif


        }//endforeach
        
        
        
        return $result_sync;

    }
    
    /* get Customers  list all*/
    function sync_contacts($params){


        $created_at_min = empty($params->get_param('created_at_min')) ? date('Y-m-d') : Clientify_Helper::parse_date_flexible($params->get_param('created_at_min'), 'Y-m-d');
        $created_at_end = empty($params->get_param('created_at_end')) ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d', strtotime(Clientify_Helper::parse_date_flexible($params->get_param('created_at_end'), 'Y-m-d') . ' +1 day'));
        $per_page = intval($params->get_param('per_page'));
        $paged = intval($params->get_param('page', 1));
        $url_base = $this->get_local_api_url();
        global $wpdb;
        $user_role = 'customer';
        
        $query = $wpdb->prepare(
            "SELECT u.ID
            FROM {$wpdb->users} AS u
            INNER JOIN {$wpdb->usermeta} AS um ON u.ID = um.user_id
            WHERE DATE(u.user_registered) BETWEEN %s AND %s
            AND um.meta_key = '{$wpdb->prefix}capabilities'
            AND um.meta_value LIKE %s
            ORDER BY u.user_registered ASC
            LIMIT %d OFFSET %d",
            $created_at_min,
            $created_at_end,
            '%"'.$user_role.'"%',
            $per_page,
            ($paged - 1) * $per_page
        );
        
        $users = $wpdb->get_results($query);
        
        $result_sync = array();

        foreach ( $users as $customer_to_sync ) {
            $customer = $this->get_contact( $customer_to_sync->ID );
            $customer['status'] = 'customer';
            $customer['store_url'] = $url_base;
            //Send data to Clientify
            $api = new Clientify_Api;
            $get_customer   = $api->post_contacts_clientify( $customer );
            $result_sync [] = $get_customer;
        }

        $response = new WP_REST_Response($result_sync, 200);        
		return $response;
    }

    public function get_clientify_logs($params) {
        global $wpdb;
        $helpers = new Clientify_Helper();
        $table_name = $wpdb->prefix . 'clientify_logs';

        if (isset($params['type_clean'])) {
            return  $helpers->delete_old_logs($params);
        }

        // Parámetros de paginación
        $per_page = isset($params['per_page']) ? intval($params['per_page']) : 10;
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $offset = ($page - 1) * $per_page;
    
        // Consulta para obtener los logs
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    
        // Contar el total de logs para la paginación
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
        // Devolver la respuesta con los logs y la información de paginación
        return new WP_REST_Response([
            'pagination' => [
                'total_logs' => $total_logs,
                'per_page' => $per_page,
                'page' => $page,
                'total_pages' => ceil($total_logs / $per_page),
            ],
            'table_exist' => $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name,
            'logs' => $logs,
        ], 200);
    }
}