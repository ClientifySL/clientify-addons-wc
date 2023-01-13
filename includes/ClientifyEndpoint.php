<?php
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Api.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/RegisterCustomPostType.php';

class CustomClientifyEndPoint {

    public function GetApiUrl(){

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
    // public function token_id_ajax()
    // {
    //     $new_key = str_replace('-', '', wp_generate_uuid4());
    //     update_option('CLIENTIFY_STORE_KEY', $new_key);
    //     $api = new ClientifyApi;
    //     $post_key = array('key_store'=> $new_key);
    //     $contact = $api->Post_Base_Clientify($post_key);
    //     echo  json_encode($new_key);
    //     die();
    // }


    public function ClientifyEndPoints(){

        register_rest_route('/clientify/v1', '/connect', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'test_conect'),
        ));

        register_rest_route('/clientify/v1', '/contacts', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'sync_customer'),
        ));

        register_rest_route('/clientify/v1', '/products', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'Get_products'),
        ));
        
        register_rest_route('/clientify/v1', '/setscript', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'analitics_script'),
        ));

        register_rest_route('/clientify/v1', '/setsend', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'setsend'),
        ));

        register_rest_route('/clientify/v1', '/time_abandoned_cart', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'ac_cart'),
        ));




        register_rest_route('/clientify/v1', '/orders', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'orders'),
        ));


    }

    public function privileged_permission_callback($request) {

        if ($request->get_header('storekey') === get_option('CLIENTIFY_STORE_KEY') ) {
            return true;
        }else {
            return false;
        }
    }


    public function triggerEvents()    
    {


    }

	public function orders($params){
        
		global $product;
		global $wpdb;
        
        $endpoint_class = new CustomClientifyEndPoint();
        $url_base = $endpoint_class->GetApiUrl();

        $created_at_min = $params->get_param('created_at_min');
        $per_page = $params->get_param('per_page');
        $paged = ($params->get_param('page')) ? $params->get_param('page') : 1;
        $offset = ( $per_page * $paged ) - $per_page;

		$args = array(			
            'date_created' => '>' . $created_at_min,
            'orderby' => 'ID',
            'order' => 'ASC',
            'limit' => isset($per_page) ? $per_page : -1,
            'offset' => $offset,
            'paged' => $paged,
			'return' => 'ids'
		   ); 
        $all_total = array(			
            'date_created' => '>' . $created_at_min,
            'orderby' => 'ID',
            'order' => 'ASC',
			'return' => 'ids',
            'limit' => -1,
		   ); 

        $total = wc_get_orders($all_total);
        if (isset($per_page)) {
        $to_per = count($total)/$per_page;
        $total_pages = is_float($to_per) ? intval($to_per+1) : $to_per ;
        }
        $query = new WC_Order_Query($args);
        $orders_ids = $query->get_orders();
        $all = array();

		foreach( $orders_ids as $order_id ) {		   
		   $order = wc_get_order($order_id); //cn esto valido al llegar if
		   $order_status  = $order->get_status();
		   $order_data = $order->get_data();
		   $id_customer = $order->get_customer_id();
		   $contact = null;
		   $lang = get_bloginfo("language");
		   $products = $order->get_items();
		   $currency = $order->get_currency();           
		   $items = array();

		   foreach ($products as $order_product) {

			   $terms = get_the_terms($order_product['product_id'], 'product_cat');
			   foreach ($terms as $term) {
				   $product_cat_slug = $term->slug;
			   }

			   $product = $order_product->get_product();
			   
			   $sku = $product->get_sku();
			   $image_id  = $product->get_image_id();
			   $image_url = wp_get_attachment_image_url($image_id, 'full');
			   $product_id = $order_product['product_id'];
			   $product_instance = wc_get_product($product_id);
			   $product_full_description = $product_instance->get_description();
			   $tax_amount = $order->get_item_tax($order_product, true, true);
			   $inc_tax = $tax_amount > 0 ? true : false;
			   $price = $product->get_sale_price();

			   if ($inc_tax) {
				   $price = wc_get_price_including_tax($product, array('price' => $price));
			   } else {
				   $price = wc_get_price_excluding_tax($product, array('price' => $price));
			   }

			   $items[] = array(
				   'name' => $order_product->get_name(),
				   'description' => $product_full_description,
				   'category' => $product_cat_slug,
				   'sku' => $sku,
				   'image_url' => $image_url,
				   'item_url' => get_permalink($order_product['product_id']),
				   'price' => $price,
				   'quantity' => $order_product->get_quantity(),
				   'discount' => 0, //$discount
			   );
		   }
		   //total discount
		   $order_discount_total = $order->get_total_discount(!$inc_tax);

		   $data = array(

            'contact' => $this->Get_contact($id_customer),
            'status' => 'ordered',
            'order_date' => $order_data['date_created']->date('Y-m-d H:i:s'),
            'order_id' => $order->get_id(),
            'ecommerce' => 'woocommerce',
            'shop_name' => get_option('blogname'),
            'order_url' => $order->get_view_order_url(),
            'store_url' => $url_base,
            'currency' => $currency,
            'products' => $items,
            //'visitor_key' => (string)$this->getVisitorKeyByCartId($_COOKIE['cookie_cart_id']),
            'coupon' => $order_discount_total ? $order_discount_total : 0,

		   );
		   
		    if (!empty($lang)) {
			    $data['custom_field'] = array(
				   'field' => 'ecommerce_language',
				   'value' => $lang,
			   );
		    }
	    $all [] = array($data);
		}
        $response = new WP_REST_Response($all, 200);
        
        $response->header( 'Link', $total_pages); // maximum number of pages 
   
            //return $response;
		return $response;

	}

    function test_conect()
    {
        global $wpdb;
        
             get_option('CLIENTIFY_STATUS');
            //update_option('CLIENTIFY_STATUS',0);
            $status = get_option('CLIENTIFY_STATUS') == 0 ? 'disconnect' : 'activate';

        if($wpdb){
            $data = array(
                'ecommerce'     => 'woocommerce',
                'url_base'      => $this->GetApiUrl(),
                'db_status'     => 'success',
                'plugin_status' => $status

            ); 
        }
        
        return $data;
    }
    /* time abandoned cart*/
    public function analitics_script($params)
    {   
        $set_script = $params->get_param('set_script');

        update_option('CLIENTIFY_SCRIPT', $set_script);

        return array('message' => 'success');

    }
    /* change status plugogin conneted or disconnect*/
    public function setsend($params)
    {   
        $call = new RegisterCustomPostType();
        $set_send = $params->get_param("set_send");

        if ($set_send == "connect") {

            //update_option('CLIENTIFY_STATUS', 1);
            //return array('message' => 'connect success');
            $call->connect_clientify();

        }elseif ($set_send == "disconnect") {

            //update_option('CLIENTIFY_STATUS', 0);
            // return array('message' => 'disconnect success');
            $call->disconnect_clientify();

        }else {

            return array('message' => 'error param');
            // http_response_code(500);
            // return http_response_code();
        }
        //var_dump($params);
        //die();

    }
    /* time abandoned cart*/
    public function ac_cart($params){
        $set_time = $params->get_param('set_time');

        if (intval($set_time) >= 1 && intval($set_time) <= 24) {            
            update_option('CLIENTIFY_CART_HOUR',$set_time);
            return array('message' => 'success' );
        }else{
            return array('message' => 'error range time');
            // http_response_code(500);
            // return http_response_code();
        }
        //var_dump($params);
        //die();

    }
    /* get Customers  list all*/
    public function sync_customer(){

        global $wpdb;
        $count = 0;
        $contacs = array();
        $sql = 'SELECT ID FROM ' . $wpdb->prefix . 'users ORDER BY ID ASC';
        $customers_to_sync = $wpdb->get_results($sql);
        
        foreach ($customers_to_sync as $customer_to_sync) {
            $user_id = $customer_to_sync->ID;
            
           $customer = $this->Get_contact($customer_to_sync->ID);
            $count++;
            $contacs[$count][] = array($customer);
        }

        return $contacs;

    }
    function Get_contact($user_id){
        global $wpdb;
        global $woocommerce;
        $users_all_wp = get_users();
        $user = get_userdata($user_id);
        $current_user = wp_get_current_user();
        $lang = get_bloginfo("language");
        $customer_meta = get_user_meta($user_id);
        $customer_phones = array();
        $site_name = get_option('blogname');
        $site_name = empty($site_name) ? 'WordPress' : $site_name;
        $customer = new WC_Customer($user_id);


        if ($user_id != 0) {
            $data = array(
                'id_customer' => $user_id,
                'email' => $customer->email,
                'contact_source' => get_option('blogname'),
                'user_registered' => $user->user_registered,
                'custom_fields' => [],
                'tags' => array(
                    'WooCommerce',
                    $site_name,
                )
            );

            if (!empty(get_user_meta($user_id)['first_name'][0])) {
                $data['first_name'] = get_user_meta($user_id)['first_name'][0];
            }

            if (!empty(get_user_meta($user_id)['last_name'][0])) {
                $data['last_name'] = get_user_meta($user_id)['last_name'][0];
            }

            if (!empty($lang)) {
                $data['custom_field'] = array(
                    'field' => 'ecommerce_language',
                    'value' => $lang,
                );
            }
            if (!empty($customer_meta['billing_address_1'][0])) {

                if (isset($customer_meta['billing_first_name'][0]) && !empty($customer_meta['billing_first_name'][0])) {
                    $data['first_name'] = $customer_meta['billing_first_name'][0];
                }
                if (isset($customer_meta['billing_last_name'][0]) && !empty($customer_meta['billing_last_name'][0])) {
                    $data['last_name'] = $customer_meta['billing_last_name'][0];
                }

                $street = $customer_meta['billing_address_1'][0] . (!empty($customer_meta['billing_address_2'][0]) ? ', ' . $customer_meta['billing_address_2'][0] : '');
                $city = $customer_meta['billing_city'][0];
                $country = WC()->countries->countries[$customer_meta['billing_country'][0]];
                $postal_code = $customer_meta['billing_postcode'][0];
                $customer_address = array('type' => 1);

                if ($street) {
                    $customer_address['street'] = $street;
                }

                if ($city) {
                    $customer_address['city'] = $city;
                }

                if ($country) {
                    $customer_address['country'] = $country;
                }

                if ($postal_code) {
                    $customer_address['postal_code'] = $postal_code;
                }

                if (!empty($customer_meta['billing_state'][0])) {
                    $customer_address['state'] = WC()->countries->get_states($customer_meta['billing_country'][0])[$customer_meta['billing_state'][0]];
                    if (empty($customer_address['state'])) {
                        unset($customer_address['state']);
                    }
                }

                $data['addresses'][] = $customer_address;

                if (!empty($customer_meta['billing_company'][0])) {
                    $data['company'] = $customer_meta['billing_company'][0];
                }

                if (!empty($customer_meta['billing_phone'][0]) && !in_array($customer_meta['billing_phone'][0], $customer_phones)) {
                    $data['phones'][] = array('phone' => $customer_meta['billing_phone'][0]);
                    $customer_phones[] = $customer_meta['billing_phone'][0];
                }

            }
           
            // } elseif ($woocommerce->customer->get_address()) {
                
            //     // $street = $woocommerce->customer->get_billing_address() . (!empty($woocommerce->customer->get_billing_address_2()) ? ', ' . $woocommerce->customer->get_billing_address_2() : '');
            //     // $city = $woocommerce->customer->get_billing_city();
            //     // $country = WC()->countries->countries[$woocommerce->customer->get_billing_country()];
            //     // $postal_code = $woocommerce->customer->get_billing_postcode();
            //     // $customer_address = array('type' => 1);

            //     // if ($street) {
            //     //     $customer_address['street'] = $street;
            //     // }

            //     // if ($city) {
            //     //     $customer_address['city'] = $city;
            //     // }

            //     // if ($country) {
            //     //     $customer_address['country'] = $country;
            //     // }

            //     // if ($postal_code) {
            //     //     $customer_address['postal_code'] = $postal_code;
            //     // }

            //     // if (!empty($woocommerce->customer->get_billing_state())) {
            //     //     $customer_address['state'] = WC()->countries->get_states($woocommerce->customer->get_billing_country())[$woocommerce->customer->get_billing_state()];
            //     //     if (empty($customer_address['state'])) {
            //     //         unset($customer_address['state']);
            //     //     }
            //     // }

            //     // $data['addresses'][] = $customer_address;

            //     // if (!empty($woocommerce->customer->get_billing_company())) {
            //     //     $data['company'] = $woocommerce->customer->get_billing_company();
            //     // }

            //     // if (!empty($woocommerce->customer->get_billing_phone()) && !in_array($woocommerce->customer->get_billing_phone(), $customer_phones)) {
            //     //     $data['phones'][] = array('phone' => $woocommerce->customer->get_billing_phone());
            //     //     $customer_phones[] = $woocommerce->customer->get_billing_phone();
            //     // }
            // }

           // $clientify_vk = isset($_COOKIE['clientify_vk']) ? $_COOKIE['clientify_vk'] : '';

            // if (isset($clientify_vk) && $clientify_vk) {
            //     $data['visitor_key'] = (string)$clientify_vk;
            // }
        }

        return $data;
    }
    /* get products */
    public function Get_products ($params){

        $count = $params->get_param('count');
        $created_to = $params->get_param('created_to');
        $created_from = $params->get_param('created_from');
        $ids = $params->get_param('ids');
        //  'limit' => 4,
        //             'offset' => 1
        $p = wc_get_products(array(
            'status' => 'publish',
            'fields' => 14,
            'limit' => (int)$count,
            'date_query' => array(
                array(
                    'column' => 'post_date',
                    'after'  => $created_from,
                    //allow exact matches to be returned
                    'inclusive' => true,
                ),
                array(
                    'column' => 'post_date',
                    'before' => $created_to,
                    //allow exact matches to be returned
                    'inclusive' => true,
                ),
            )
 
        ));
        $products = array();
        foreach ($p as $product) {
            $products[] = $product->get_data();
        }

        return new WP_REST_Response($products, 200);
    }
    
}