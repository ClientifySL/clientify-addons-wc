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

    public function ClientifyEndPoints(){

        register_rest_route('/clientify/v1', '/pluginhandling', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'plugin_handling'),
        ));

        register_rest_route('/clientify/v1', '/deactivate', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'test_conect'),
        ));

        register_rest_route('/clientify/v1', '/status', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'webhooks_status'),
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

        register_rest_route('/clientify/v1', '/abandoned', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'privileged_permission_callback'),
            'callback' => array($this, 'gat_list_abandoned_cart'),
        ));


    }
    public function privileged_permission_callback($request) {

        if ($request->get_header('storekey') === get_option('CLIENTIFY_STORE_KEY') ) {
            return true;
        }else {
            return false;
        }
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
           $total_price = $order->get_total();          
		   $items = array();

		   foreach ($products as $order_product) {

               $categories = array();
               $sub_categories= array();
               $new_categories = [];
               $terms = get_the_terms($order_product['product_id'], 'product_cat');
               var_dump($terms);
               foreach ($terms as $term) {
                    if ($term->parent == 0){                    
                        $categories[] = $term->term_id.":".$term->slug;
                    }else{
                        foreach ($categories as $cat ) {
                            $cat_data = explode(":",$cat);													
                            (int)$cat_data[0] == $term->parent ? $cat = true : $cat = false;
                        }
                        if(!$cat){
                            $term_search = get_term_by('id', $term->parent, 'product_cat');
                            $categories[] = $term_search->term_id.":".$term_search->slug;
                        }
                        $sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term->parent;
                    }          
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
               $discount_price = floatval($order_product['subtotal']) - floatval($order_product['total']);

			   if ($inc_tax) {
				   $price = wc_get_price_including_tax($product, array('price' => $price));
			   } else {
				   $price = wc_get_price_excluding_tax($product, array('price' => $price));
			   }
               foreach($categories as $cat_clean){
                    if (!in_array($cat_clean, $new_categories))
                        $new_categories[] = $cat_clean;
                }

                  

               $join_cat = implode(",",$new_categories)."/".implode(",",$sub_categories);
			   $discount = ($discount_price * 100) / $price;
               $items[] = array(
				   'name' => $order_product->get_name(),
				   'description' => $product_full_description,
				   'category' => $join_cat,
				   'sku' => $sku,
				   'image_url' => $image_url,
				   'item_url' => get_permalink($order_product['product_id']),
				   'price' => $price,
				   'quantity' => $order_product->get_quantity(),
				   'discount' => $discount != 0 ? $discount : 0, //$discount
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
            'price' => $total_price,
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

    function webhooks_status($params)
    {
        global $wpdb;
        $param = $params->get_param('hook');
        
        if($param=='all'){
            $status= array(
                'pixel_script' => $this->find_filter('wp_footer','clientify_api_script'),
                'abandoned_card' => $this->find_filter('clientify_job','clientify_action_init'),
                'contac' => $this->find_filter('user_register','customer_add'),
                'order' => $this->find_filter('woocommerce_order_status_changed','syncOrder'),
                'product' => $this->find_filter('woocommerce_new_product','productPublished')                
            );
        return $status;   
        }else{

            return array('message' => 'param error');
        }

        //      get_option('CLIENTIFY_STATUS');
        //     //update_option('CLIENTIFY_STATUS',0);
        //     $status = get_option('CLIENTIFY_STATUS') == 0 ? 'disconnect' : 'activate';

        // if($wpdb){
        //     $data = array(
        //         'ecommerce'     => 'woocommerce',
        //         'url_base'      => $this->GetApiUrl(),
        //         'db_status'     => 'success',
        //         'plugin_status' => $status

        //     ); 
       // }
       
        
        //return $this->print_filters_for($hook,$param );
    }

    function find_filter( $hook = '',$action= '' ){
        global $wp_filter;
        if( empty( $hook ) || !isset( $wp_filter[$hook] ) ){
            return false;
        }else{  
                $da = $wp_filter[$hook];
                foreach ($wp_filter[$hook] as $key) {
                    $indice = strpos(json_encode($key),$action);
                    if($indice){return true;}//else{ return false;}
                }if($indice == false){return false;}
            //return $da;
        }

    }
    /* time abandoned cart*/
    public function analitics_script($params)
    {   
        $set_script = $params->get_param('set_script');

        update_option('CLIENTIFY_SCRIPT', $set_script);

        return array('message' => 'success');

    }
    /* change status pluging conneted or disconnect passes 0 / 1 */
    public function plugin_handling($params)
    {   
        $call = new RegisterCustomPostType();
        $set_send = $params->get_param("action");

        if ($set_send == "connect") {
            $key_uid = $this->token_id();
            $url_base = $this->GetApiUrl();
            $post_key = array(
				'ecommerce' => 'woocommerce',
				'action'    => 'connect',
				'store_key' => $key_uid,
				'name'      => get_option('blogname'),
				'store_url' => $url_base
			);
            update_option('CLIENTIFY_STATUS', 1);

            if (get_option('CLIENTIFY_STATUS') == 1) {
                return new WP_REST_Response(array('message' => 'success','data'=> $post_key), 200);
            }
            else{
                return new WP_REST_Response(array('message' => 'error'), 500);
            }  

        }elseif ($set_send == "disconnect") {
            $key_uid = get_option('CLIENTIFY_STORE_KEY');
			$url_base = $this->GetApiUrl();
			$post_key = array(
				'ecommerce' => 'woocommerce',
				'action'    => 'disconnect',
				'store_key' => $key_uid,
				'name'      => get_option('blogname'),
				'store_url' => $url_base
			);
            update_option('CLIENTIFY_STATUS', 0);
            if (get_option('CLIENTIFY_STATUS') == 0) {
                return new WP_REST_Response(array('message' => 'success','data'=> $post_key), 200);
            }
            else{
                return new WP_REST_Response(array('message' => 'error'), 500);
            }
            
        }else {
            return new WP_REST_Response(array('message' => 'error param'), 500);
        }
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

    }
    /* get Customers  list all*/
    public function sync_customer($params){

        $created_at_min = $params->get_param('created_at_min');
        $per_page = $params->get_param('per_page');
        $paged = ($params->get_param('page')) ? $params->get_param('page') : 1;
        $offset = ( $per_page * $paged ) - $per_page;

		$args = array(			
            'date_created' => '>' . $created_at_min,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ID',
            'role' => 'customer',
            'number' => isset($per_page) ? $per_page : -1,
            'offset' => $offset,
            'paged' => $paged,
		   );
        $all_total = array(			
            'date_created' => '>' . $created_at_min,
            'orderby' => 'ID',
            'order' => 'ASC',
			'fields' => 'ID',
            'limit' => -1,
            'role' => 'customer',
		   ); 
        $total = get_users($all_total);
        if (isset($per_page)) {
        $to_per = count($total)/$per_page;
        $total_pages = is_float($to_per) ? intval($to_per+1) : $to_per ;
        }
        $contacs = array();
        $customer_query = new WP_User_Query($args);
        
        foreach ($customer_query->get_results() as $customer_to_sync) {
            $customer = $this->Get_contact($customer_to_sync);
            $contacs[] = $customer;
        }

        $response = new WP_REST_Response($contacs, 200);        
        $response->header( 'Link', $total_pages); // maximum number of pages 
		return $response;
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

        $created_to = $params->get_param('created_to');
        $created_from = $params->get_param('created_from');
        $per_page = $params->get_param('per_page');
        $paged = ($params->get_param('page')) ? $params->get_param('page') : 1;
        $offset = ( $per_page * $paged ) - $per_page;

        $total = wc_get_products( array(			
            'date_created' => '>' . $created_from,
            'orderby' => 'ID',
            'order' => 'ASC',
			'return' => 'ids',
            'limit' => -1,
		   ));
           if (isset($per_page)) {
            $to_per = count($total)/$per_page;
            $total_pages = is_float($to_per) ? intval($to_per+1) : $to_per ;
            }

        $p = wc_get_products(array(
            'status' => 'publish',
            'orderby' => 'ID',
            'order' => 'ASC',
            'limit' => isset($per_page) ? $per_page : -1,
            'offset' => $offset,
            'paged' => $paged,
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
        $items = array();
        
        foreach ($p as $product) {
            $categories = array();
            $sub_categories= array();
            $new_categories = [];
            $terms = get_the_terms($product->id, 'product_cat');
            foreach ($terms as $term) {
                if ($term->parent == 0){                    
                    $categories[] = $term->term_id.":".$term->slug;
                }else{
                    foreach ($categories as $cat ) {
                        $cat_data = explode(":",$cat);													
                        (int)$cat_data[0] == $term->parent ? $cat = true : $cat = false;
                    }
                    if(!$cat){
                        $term_search = get_term_by('id', $term->parent, 'product_cat');
                        $categories[] = $term_search->term_id.":".$term_search->slug;
                    }
                    $sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term->parent;
                }          
            }       
            $sku = $product->get_sku();
            $image_id  = $product->get_image_id();
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            foreach($categories as $cat_clean){
                if (!in_array($cat_clean, $new_categories))
                    $new_categories[] = $cat_clean;
            }  
            $join_cat = implode(",",$new_categories)."/".implode(",",$sub_categories); 
            $items[] = array(
                'id' => $product->id,
                'name' => $product->get_name(),
                'description' => trim(strip_tags($product->description)),
                'category' => $join_cat,
                'sku' => $sku,
                'image_url' => $image_url,
                'item_url' => get_permalink($product->id),
                'price' => $product->regular_price == '' ||   $product->regular_price == NULL ? 0 : $product->regular_price,
                'currency' => get_woocommerce_currency()
                
            );
        }
        $response = new WP_REST_Response($items, 200);        
        $response->header( 'Link', $total_pages); // maximum number of pages 
		return $response;

    }

    function gat_list_abandoned_cart($params){

        
        global $wpdb;
        $all = array();
        $created_from = $params->get_param('created_from');
        $created_end = empty($params->get_param('created_end')) ? date("Y-m-d") : $params->get_param('created_end');

        $per_page = empty($params->get_param('per_page')) ? 0 : $params->get_param('per_page');
        $paged = empty($params->get_param('page')) ? 1 : $params->get_param('page');
		$page =(int)(!isset($paged)) ? 1 : $paged;
		$per_page = (int)$params["per_page"];
		$date_null = $created_from != 0 ? "between  '".$created_from."'  and '".$created_end."'" : '';
		$limit = $per_page != 0 ? 'LIMIT '.(($page-1)*$per_page).' , '.$per_page.'' : '' ;


        $total = $wpdb->get_results("SELECT DISTINCT c.cookie_cart_id, c.id_customer FROM ". $wpdb->prefix ."clientify_abandoned_cart c  WHERE DATE(date_add)  ".$date_null."");

        $order_ids = "SELECT DISTINCT c.cookie_cart_id, c.id_customer,c.id_clientify_abandoned_cart FROM ". $wpdb->prefix . "clientify_abandoned_cart c  WHERE DATE(date_add)  ".$date_null." ORDER BY c.id_customer ".$limit;
        if (isset($per_page)) {
            $to_per = count($total)/$per_page;
            $total_pages = is_float($to_per) ? intval($to_per+1) : $to_per ;
            }

        $cookie_carts = $wpdb->get_results($order_ids);

        if (!empty($cookie_carts)) {
            foreach ($cookie_carts as $cookie_carts_results => $cookie_cart_id) {

            $endpoint_class = new CustomClientifyEndPoint();
            $url_base = $endpoint_class->GetApiUrl();
            $id_contac = is_null($cookie_cart_id->id_customer) || $cookie_cart_id->id_customer == '' ? $cookie_cart_id->cookie_cart_id : $cookie_cart_id->id_customer;
            $table_name = is_null($cookie_cart_id->id_customer) || $cookie_cart_id->id_customer == '' ? 'cookie_cart_id' : 'id_customer';
            $carts = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'cart where '. $table_name .' = "' . $id_contac . '"');		
            $items = array();
            $total_price = 0;

            foreach ($carts as $cart_item_key => $cart_item) {
                
                $contact = null;
                $visitor_key = null;

                if ($cart_item->id_customer) {
                    $contact = $cookie_cart_id->id_customer;
                } else {
                    $visitor_key = (string)$cookie_cart_id->cookie_cart_id;
                }
                if (empty($contact) && empty($visitor_key)) {
                    return false;
                }
                $product_id = $cart_item->id_product;
                $categories = array();
                $sub_categories= array();
                $terms = get_the_terms($product_id, 'product_cat');
                foreach ($terms as $term) {
                    if ($term->parent == 0){                    
                        $categories[] = $term->term_id.":".$term->slug;
                    }else{
                        $sub_categories[] = $term->parent.":".$term->slug;
                    }          
                }

                $cart_date = $cart_item->date_add;
                $product = wc_get_product($product_id);

                $price = $product->get_price();
                $without_reduction = $product->get_regular_price();
                $discount = $without_reduction - $price;
                if ($discount) {
                $discount = round( ($discount / $without_reduction) * 100, 2);
                }

                $price = $product->get_sale_price();
                
                if (empty($price)) {
                    $price = round($product->get_regular_price(), 2);
                }	
                $total_price += $price;
                $join_cat = implode(",",$categories)."/".implode(",",$sub_categories);
                $items[] = array(
                    'name' => $product->get_title(),
                    'description' => $product->get_description(),
                    'category' => $join_cat,
                    'sku' => $product->get_sku(),
                    'image_url' => get_the_post_thumbnail_url($product_id),
                    'item_url' => $product->get_permalink($cart_item),
                    'price' => $price,
                    'quantity' => $cart_item->quantity,
                    'discount' => 0,
                );		
            }
            $cart_page_id = wc_get_page_id( 'cart' );
            $cart_page_url = $cart_page_id ? get_permalink( $cart_page_id ) : '';	

            $data = array(
                'status' => 'abandoned',
                'abandoned_date' => date('Y-m-d', strtotime($cart_date)),
                'cart_id' => $cookie_cart_id->id_clientify_abandoned_cart,
                'order_id' => $cookie_cart_id->id_clientify_abandoned_cart,
                'ecommerce' => 'woocommerce',
                'shop_name' => get_option('blogname'),
                'order_url' => $cart_page_url.$cookie_cart_id->id_clientify_abandoned_cart,
                'currency' => get_option('woocommerce_currency'),
                'store_url' => $url_base,
                'products' => $items,
                'price'	=> $total_price,
                'coupon' =>  0
            );
            if ($contact) {
                $data['contact'] = $this->Get_contact($cookie_cart_id->id_customer);
            } else {
                $data['visitor_key'] = $visitor_key;
            }     
            $all [] = $data;
        }
}
        $response = new WP_REST_Response($all, 200);        
        $response->header( 'Link', $total_pages); // maximum number of pages 
        return $response;
		
	}
    
}