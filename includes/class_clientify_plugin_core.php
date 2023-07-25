<?php
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-api-connect.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-endpoint.php';

class Clientify_Plugin_Core
{
		/**
	 * clientify_create_menu Add option menu admin page WordPress.
	 *
	 * @since    1.0.0
	 */
	function clientify_create_menu()
	{
		add_menu_page('Clientify', 'Clientify', 'administrator', __FILE__, 'clientify_settings_page', plugins_url('../public/img/logo.png', __FILE__));
	}
		/**
	 * Add config page in admin panel WordPress.
	 *
	 * @since    1.0.0
	 */
	public function clientify_index()
	{
		/* include admin view */
		include plugin_dir_path(dirname(__FILE__)) . 'admin/clientify-admin-page.php';
	}
	/**
	 * Define a global config parameters for clientify.
	 *
	 * @since    1.0.0
	 */
	public function clientify_settings()
	{
		//register our settings
		register_setting('clientify-settings-group', 'CLIENTIFY_API_KEY');
		register_setting('clientify-settings-group', 'CLIENTIFY_API_LOG');
		register_setting('clientify-settings-group', 'CLIENTIFY_SCRIPT');
		register_setting('clientify-settings-group', 'CLIENTIFY_BOTTOM_SCRIPT');
		register_setting('clientify-settings-status-order', 'CLIENTIFY_ORDER_STATUS');
		register_setting('clientify-settings-group', 'CLIENTIFY_CART_HOUR', 6);
		register_setting('clientify-settings-store', 'CLIENTIFY_STORE_KEY');
		register_setting('clientify-settings-status', 'CLIENTIFY_STATUS' , 0);
	}
	/**
	 * Check if there any abandoned carts.
	 *
	 * @since    1.0.0
	 */
	function clientify_action_init()
	{
		global $wpdb;
		if (is_plugin_active('woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php')) {

			if ( is_plugin_active('woocommerce/woocommerce.php') ){
				$cart_hour = (int)get_option('CLIENTIFY_CART_HOUR');
				$sql = 'SELECT wccca.session_id FROM ' . $wpdb->prefix . 'cartflows_ca_cart_abandonment wccca ';
				$sql .= 'LEFT JOIN ' . $wpdb->prefix . 'clientify_abandoned_cart wcac ON wccca.id = wcac.cartflows_id AND wccca.email = wcac.cartflows_email WHERE wcac.cartflows_id IS null AND wccca.order_status = "abandoned" AND TIMESTAMPDIFF(HOUR, wccca.time , now()) >= ' . $cart_hour;
				$carts = $wpdb->get_results($sql);

				if ( !empty( $carts ) ) {
					foreach ( $carts as $carts_results => $cart_data ) {					
						$clientify_cart = $this->sync_hook_abandoned_cart($cart_data);
						
						$insert_clientify = $clientify_cart->data_insert;
						
						$search_abadonet_clientify = $wpdb->get_results('SELECT id_clientify_abandoned_cart FROM ' . $wpdb->prefix . 'clientify_abandoned_cart where cartflows_id = "' . $id_cartslow->id . '"');
						
						if( empty($search_abadonet_clientify) && $clientify_cart->status == 'success'){
									
									$wpdb->insert($wpdb->prefix . "clientify_abandoned_cart", $insert_clientify);
								
						}elseif ($clientify_cart->status == 'error') {
							$pattern = "/string=u'([^']+)'/";
							if (preg_match($pattern, $clientify_cart->data, $matches)) {
								$errorMessage = $matches[1];
							}
							if($errorMessage == 'Abandoned cart is already registered' && empty($search_abadonet_clientify)){
								$wpdb->insert($wpdb->prefix . "clientify_abandoned_cart", $insert_clientify);
							}
						}
					}
				}
			}

		}else{
			if ( is_plugin_active('woocommerce/woocommerce.php') ){
				$cart_hour = (int)get_option('CLIENTIFY_CART_HOUR');
				$sql = 'SELECT DISTINCT c.cookie_cart_id, c.id_customer FROM ' . $wpdb->prefix . 'cart c ';
				$sql .= 'LEFT JOIN ' . $wpdb->prefix . 'clientify_abandoned_cart cac ON (cac.cookie_cart_id = c.cookie_cart_id) OR (cac.id_customer = c.id_customer) WHERE cac.cookie_cart_id IS NULL AND cac.id_customer IS NULL AND TIMESTAMPDIFF(HOUR, c.date_add, now()) >= ' . $cart_hour;
				$cookie_carts = $wpdb->get_results($sql);
				$count = 0;
				if ( !empty( $cookie_carts ) ) {
					foreach ( $cookie_carts as $cookie_carts_results => $cart_data ) {					
						$this->sync_hook_abandoned_cart($cart_data);
					}
				}
			}
		}
	}
	/**
	 * Send parameters to Connect plugin with clientify.
	 *
	 * @since    1.0.0
	 *
	 */
	function connect_clientify()
	{
		$key = $_POST['apikey'] !=	'' ? $_POST['apikey'] : get_option('CLIENTIFY_API_KEY');
		if ( $_POST['apikey'] != '' || get_option('CLIENTIFY_API_KEY') != '' ) {
			update_option('CLIENTIFY_ORDER_STATUS', $_POST['order_process']);
			/* generate uid key for connect to clientify */
			$endpoint_class = new Clientify_Endpoint();
			$api = new Clientify_Api;
			$key_uid = $endpoint_class->token_id();
			$url_base = $endpoint_class->get_local_api_url();
			
			$post_key = array(
				'ecommerce' => 'woocommerce',
				'action'    => 'connect',
				'store_key' => $key_uid,
				'name'      => get_option('blogname'),
				'store_url' => $url_base
			);
			$response = $api->post_base_clientify($post_key, $key);
		}
		if ( is_null($response) || isset($response->detail) ) {

            $data= is_null($response) != '' ? "error" : $response->detail;
            $response = array(
                'data' => array(
                        	'status' => $data
                    	)
                );
            
        }else {
            foreach ( $response as $obj ) {
                $status = $obj->status;
            }     
            if ( $status == 'success' ) {
    
				update_option('CLIENTIFY_STATUS', 1);
				
            }
        }
		echo  json_encode($response);
		die();
	}
	/**
	 * Send parameters to Unlink with clientify.
	 *
	 * @since    1.0.0
	 */
	function disconnect_clientify()
	{
		$key = $_POST['apikey'] !=	'' ? $_POST['apikey'] : get_option('CLIENTIFY_API_KEY');
		if ( $_POST['apikey'] != '' || get_option('CLIENTIFY_API_KEY') != '' ) {
			/* generate uid key for connect to clientify */
			$endpoint_class = new Clientify_Endpoint();
			$api = new Clientify_Api;
			$key_uid = get_option('CLIENTIFY_STORE_KEY');
			$url_base = $endpoint_class->get_local_api_url();

			$post_key = array(
				'ecommerce' => 'woocommerce',
				'action'    => 'disconnect',
				'store_key' => $key_uid,
				'name'      => get_option('blogname'),
				'store_url' => $url_base
			);
			$response = $api->post_base_clientify($post_key, $key);
		}
		foreach ( $response as $obj ) {
			$status = $obj->status;	
		}
		//success/ fail / error
		if ( $status == 'success' || $status == 'failed' || $status == null ) {
			update_option('CLIENTIFY_STATUS', 0);
		}
		echo  json_encode($response);
		die();
	}
	/**
	 * Query contact by Id in Wocommerce.
	 *
	 * @since    1.0.0
	 * @param    int                  $user_id    The custommer's id number.
	 */
	function get_contact($user_id)
	{
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
		$meta_keys = array('shipping_nif', 'vat_number', 'dni_number');
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
				'email'       	  => $customer->email,
				'contact_source'  => get_option('blogname'),
				'user_registered' => $user->user_registered,
				'identification'  => $customer_dni,
				'custom_fields'   => [],
				'tags'            => array(
										'woocommerce',
										$site_name,
									)
			);

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
				$postal_code = $customer_meta['billing_postcode'][0];
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

				if ( !empty($customer_meta['billing_phone'][0]) && !in_array($customer_meta['billing_phone'][0], $customer_phones ) ) {
					$data['phones'][] = array('phone' => $customer_meta['billing_phone'][0]);
					$customer_phones[] = $customer_meta['billing_phone'][0];
				}
			}
		}
		return $data;
	}
	/**
	 * Check Wocommerce is activate.
	 *
	 * @since    1.0.0
	 * @param    int                  $user_id    The custommer's id number.
	 */
	function customer_add($user_id)
	{
		if ( is_plugin_active('woocommerce/woocommerce.php') && !is_admin() ) {
			$this->sync_hook_customer( $user_id );
		}
	}
	/**
	 * Send customer information to the clientify api.
	 *
	 * @since    1.0.0
	 * @param    int                  $user_id    The custommer's id number.
	 */
	function sync_hook_customer( $user_id )
	{
		global $wpdb;
		global $woocommerce;
		$users_all_wp = get_users();
		$user = get_userdata( $user_id );
		$current_user = wp_get_current_user();
		$lang = get_bloginfo("language");
		$customer_meta = get_user_meta( $user_id );
		$customer_phones = array();
		$site_name = get_option('blogname');
		$site_name = empty( $site_name ) ? 'WordPress' : $site_name;
		$customer = new WC_Customer( $user_id );

		$endpoint_class = new Clientify_Endpoint();
		$url_base = $endpoint_class->get_local_api_url();

		if ($user_id != 0) {
			$data = array(
				'status'         => 'customer',
				'store_url'      => $url_base,
				'email'          => $customer->email,
				'contact_source' => get_option('blogname'),
				'custom_fields'  => [],
				'tags'           => array(
										'woocommerce',
										$site_name,
									)
			);
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
				$postal_code = $customer_meta['billing_postcode'][0];
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
					$customer_address['state'] = WC()->countries->get_states( $customer_meta['billing_country'][0] )[$customer_meta['billing_state'][0]];
					if (empty($customer_address['state'])) {
						unset( $customer_address['state'] );
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
			} elseif ( $woocommerce->customer->get_address() ) {

				$street = $woocommerce->customer->get_billing_address() . (!empty($woocommerce->customer->get_billing_address_2()) ? ', ' . $woocommerce->customer->get_billing_address_2() : '');
				$city = $woocommerce->customer->get_billing_city();
				$country = WC()->countries->countries[$woocommerce->customer->get_billing_country()];
				$postal_code = $woocommerce->customer->get_billing_postcode();
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
				if ( !empty($woocommerce->customer->get_billing_state()) ) {
					$customer_address['state'] = WC()->countries->get_states( $woocommerce->customer->get_billing_country() )[$woocommerce->customer->get_billing_state()];
					if (empty($customer_address['state'])) {
						unset( $customer_address['state'] );
					}
				}

				$data['addresses'][] = $customer_address;

				if ( !empty($woocommerce->customer->get_billing_company()) ) {
					$data['company'] = $woocommerce->customer->get_billing_company();
				}
				if ( !empty($woocommerce->customer->get_billing_phone()) && !in_array($woocommerce->customer->get_billing_phone(), $customer_phones) ) {
					$data['phones'][] = array( 'phone' => $woocommerce->customer->get_billing_phone() );
					$customer_phones[] = $woocommerce->customer->get_billing_phone();
				}
			}

			$clientify_vk = isset($_COOKIE['vk']) ? $_COOKIE['vk'] : '';

			if ( isset($clientify_vk) && $clientify_vk ) {
				$data['visitor_key'] = (string)$clientify_vk;
			}
		} //end if user_id	
		//Send to api
		$api = new Clientify_Api;
		$contact = $api->post_contacts_clientify( $data );
		$data = array(
				'id_customer' => $user_id,
				'clientify_id' => $contact->id,
			);
		$clien_consul = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}clientify_customer WHERE id_customer = $user_id AND clientify_id = $contact->id");

		if ( isset($contact->id) && empty($clien_consul) ) {
			$wpdb->insert($wpdb->prefix . "clientify_customer", $data);
		}
		return $contact;
	}
	/**
	 * search and send product data to the clientify api.
	 *
	 * @since    1.0.0
	 * @param    int                  $prodcut_id    The product's id number.
	 */
	function product_published($product_id){

		$endpoint_class = new Clientify_Endpoint();
		$product = wc_get_product( $product_id );
		$url_base = $endpoint_class->get_local_api_url();
		$new_categories = [];
		$categories = array();
        $sub_categories= array();
		$terms = get_the_terms($product_id, 'product_cat');
		foreach ( $terms as $term ) {
			if ( $term->parent == 0 ){                                           
				$categories[] = $term->term_id.":".$term->slug;
			}else{
				foreach ( $categories as $cat ) {
					$cat_data = explode(":",$cat);													
					(int)$cat_data[0] == $term->parent ? $cat = true : $cat = false;
				}
				if( !$cat ){                            
					$term_search = get_term_by('id', $term->parent, 'product_cat');
					if( $term_search->parent == 0 ){
						$categories[] = $term_search->term_id.":".$term_search->slug;
					}
					else{
						$sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term_search->parent;
					}                            
				}
				if ( !in_array($term->term_id.":".$term->slug."|parent_id:".$term_search->parent, $sub_categories) ) {
						$sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term->parent;
					}
				foreach ( $categories as $key => $value ) {                            
					foreach( $sub_categories as $key => $value_sub ){
						$explode = explode("|", $value_sub);
						$arr = array($explode[0]);
						if ( in_array($value, $arr) ) {
							unset( $categories[$key] );
						}
					}                            
				  }                        
			}          
		}
		$sku = $product->get_sku();
		$image_id  = $product->get_image_id();
		$image_url = wp_get_attachment_image_url($image_id, 'full');
		$product_instance = wc_get_product($product_id);
		$product_full_description = $product_instance->get_description();
		$price = $product->price;

		$regular_price = $product->get_regular_price(); // Obtiene el precio regular del producto
		$sale_price = $product->get_sale_price(); // Obtiene el precio de venta del producto
		if ( $sale_price != $regular_price && $sale_price != '' ) {
			$price = $sale_price;
		} else {
			$price = $regular_price;
		}

		foreach( $categories as $cat_clean ){
			if ( !in_array($cat_clean, $new_categories) )
				$new_categories[] = $cat_clean;
		}
		$join_cat = implode(",",$new_categories)."/".implode(",",$sub_categories);
		$item = array(
			'status'              => 'product',
			'id'                  => $product_id,
			'name'                => $product->get_name(),
			'description'         => $product_full_description,
			'price'               => $price == 0 ? 0 : $price,
			'item_url'            => get_permalink($product_id),
			'currency'            => get_option('woocommerce_currency'),
			'category'            => $join_cat,
			'sku'                 => $sku,
			'product_picture_url' => $image_url,
			'store_url'           => $url_base
		);
		$api = new Clientify_Api;
		$clientify_product = $api->post_product_plientify( $item );
		return $clientify_product;
	}
	/**
	 * Collects and sorts data from an order by executing hook woocommerce_order_status_changed or 
	 * woocommerce_thankyou, and sends to clientify.
	 *
	 * @since    1.0.0
	 * @param    int                  $order_id    The order id number.
	 * @param    string               $old_status    The old staus.
	 * @param    string               $new_status    The new status.
	 */
	function sync_hook_order($order_id, $old_status, $new_status)
	{
		global $product;
		global $wpdb;
		$endpoint_class = new Clientify_Endpoint();
		$url_base = $endpoint_class->get_local_api_url();
		//status of orden processin payment ...
		// get type status in select front
		$clientify_order_status = get_option('CLIENTIFY_ORDER_STATUS');
		$order_status_settings = array();
        
        foreach ($clientify_order_status as $value) {
            array_push($order_status_settings, $value);

           }

		//foreach ( $clientify_order_status as $key => $order_status ) :
			if ( in_array('wc-'.$new_status, $order_status_settings ) ) { 	

				$items = array();
				$order = wc_get_order($order_id); //cn esto valido al llegar if
				$order_status  = $order->get_status();
				$order_data = $order->get_data();
				$id_customer = $order->get_customer_id();
				$total_price = $order->get_total();
				$lang = get_bloginfo("language");
				$products = $order->get_items();
				$currency = $order->get_currency();

				foreach ( $products as $order_product ) {

					$categories = array();
					$sub_categories= array();
					$new_categories = [];
					$terms = get_the_terms( $order_product['product_id'], 'product_cat' );
					foreach ( $terms as $term ) {
						if ( $term->parent == 0 ){                                           
							$categories[] = $term->term_id.":".$term->slug;
						}else{
							foreach ( $categories as $cat ) {
								$cat_data = explode(":",$cat);													
								(int)$cat_data[0] == $term->parent ? $cat = true : $cat = false;
							}
							if( !$cat ){                            
								$term_search = get_term_by('id', $term->parent, 'product_cat');
								if( $term_search->parent == 0 ){
									$categories[] = $term_search->term_id.":".$term_search->slug;
								}
								else{
									$sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term_search->parent;
								}                            
							}
							if ( !in_array($term->term_id.":".$term->slug."|parent_id:".$term_search->parent, $sub_categories) ) {
									$sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term->parent;
								}
							foreach ( $categories as $key => $value ) {                            
								foreach( $sub_categories as $key => $value_sub ){
									$explode = explode("|", $value_sub);
									$arr = array($explode[0]);
									if (in_array($value, $arr)) {
										unset( $categories[$key] );
									}
								}                            
							}                        
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
					
					if ( $inc_tax ) {
						$price = wc_get_price_including_tax($product, array('price' => $price));
					} else {
						$price = wc_get_price_excluding_tax($product, array('price' => $price));
					}
					foreach( $categories as $cat_clean ){
						if ( !in_array($cat_clean, $new_categories) )
							$new_categories[] = $cat_clean;
					}  
					$join_cat = implode(",",$new_categories)."/".implode(",",$sub_categories);
					$discount = ($discount_price * 100) / $price;
					$items[] = array(
						'name'        => $order_product->get_name(),
						'id'          => $product_id,
						'description' => $product_full_description,
						'category'    => $join_cat,
						'sku'         => $sku,
						'image_url'   => $image_url,
						'item_url'    => get_permalink($order_product['product_id']),
						'price'       => $price,
						'quantity'    => $order_product->get_quantity(),
						'discount'    => $discount != 0 ? $discount : 0, //$discount
					);

				}

				$data = array(

					'contact'    => $this->get_contact($id_customer),
					'status'     => 'ordered',
					'order_date' => $order_data['date_created']->date('Y-m-d H:i:s'),
					'order_id'   => $order->get_id(),
					'ecommerce'  => 'woocommerce',
					'shop_name'  => get_option('blogname'),
					'order_url'  => $order->get_view_order_url(),
					'store_url'  => $url_base,
					'currency'   => $currency,
					'products'   => $items,
					'shipping' 	 => $order_data['shipping_total'],
					'price'	     => $total_price,
					'coupon'     => 0,
				);
				if (!empty($lang)) {
					$data['custom_field'] = array(
							'field' => 'ecommerce_language',
							'value' => $lang,
						);
				}		
				
				$api = new Clientify_Api;
				$clientify_order = $api->post_order_clientify($data);
				$res_cart_ac = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}cart WHERE id_customer  = {$id_customer} ");
				
				if ( !empty($clientify_order) && $res_cart_ac != '' ) {
					$this->delete_cart($order_id);
				}
				return $clientify_order;
			}
		//endforeach;
	}
		/**
	 * Insert script for analitycs of clientify in the wp_footer hook
	 *
	 * @since    1.0.0
	 */
	function clientify_api_script(){

		$bottom_script = get_option('CLIENTIFY_SCRIPT');
		if ( !empty($bottom_script) ) {
			printf("<script src='%s'></script>",esc_attr($bottom_script));
		}
	}
	/**
	 * Insert product id carts table to track abandoned carts
	 *
	 * @since    1.0.0
	 * @param    int                  $prodcut_id    The product id number.
	 */
	function clientify_save_add_to_cart($cart_item_key, $product_id)
	{
		global $wpdb;
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			$quantity = $cart_item['quantity'];
			$user = wp_get_current_user();
			$lang = get_bloginfo("language");
			$currency = get_option('woocommerce_currency');
			$id_cart = $wpdb->get_var('SELECT id_cart FROM ' . $wpdb->prefix . 'cart WHERE id_customer = ' . (int)$user->ID . ' AND id_product = ' . (int)$product_id . '');
			$sql = $wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'cart WHERE id_customer = ' . (int)$user->ID . ' AND id_product = ' . (int)$product_id . ' ');
			$data =	$wpdb->get_results($sql);
			
			if( $user->ID ){
					if ( !$id_cart ) {
						$save_cart = array(
							'cookie_cart_id' => $_COOKIE['vk'] == '' ? NULL : $_COOKIE['vk'],
							'id_customer'    => $user->ID,
							'id_product'     => $product_id,
							'quantity'       => $quantity,
							'currency'       => $currency,
							'language'       => $lang
						);
						sleep(2);
						$wpdb->insert($wpdb->prefix . "cart", $save_cart);
				} else {
					$wpdb->query("UPDATE " . $wpdb->prefix . "cart SET quantity = " . $quantity . " WHERE id_cart = " . (int)$id_cart);
				}
			}		
		}
	}
	/**
	 * Update Carts table according to id_cart 
	 * 
	 * @since    1.0.0
	 * @param 	 int					$cart_update ID cart in table cart
	 */
	function clientify_cart_updated($cart_updated)
	{
		if ( $cart_updated ) {
			$this->clientify_save_add_to_cart(null, null);
		}
	}
	/**
	 * Delete cart if purchase is complete
	 *
	 * @since    1.0.0
	 * @param    int                  $order_id    The order id number.

	 */
	function delete_cart($order_id)
	{
		global $wpdb;
		$order = wc_get_order($order_id);
		$order_customer_id = $order->get_customer_id();
		$wpdb->query('DELETE FROM ' . $wpdb->prefix . 'cart WHERE id_customer = ' . (int)$order_customer_id . ' ');
		$wpdb->query('DELETE FROM ' . $wpdb->prefix . 'clientify_abandoned_cart WHERE id_customer = ' . (int)$order_customer_id . ' ');
	}
	/**
	 * Delete item from temporaly cart
	 *
	 * @since    1.0.0
	 * @param    int                  $order_id    The order id number.

	 */
	function delete_item_cart($cart_item)
	{
		global $wpdb;
		$user = wp_get_current_user();

		foreach ( WC()->cart->get_cart() as $instance => $cart_items ) {

			if ( $cart_items['key'] === $cart_item ) {
				$product_id = $cart_items['product_id'];
			}
		}
		$id = $product_id;
		$cookie = $_COOKIE['vk'];
		$table = $wpdb->prefix . 'cart';
		$response = $wpdb->delete($table, array('id_product' => $id,'id_customer' =>$user->ID ));
		$var = "SELECT count(*) FROM {$wpdb->prefix}cart WHERE id_customer ={$user->ID}";
		$count_cart =	$wpdb->get_var($var);

		if ( $count_cart == 0 ) {

			$id_ac = $user->ID;
			$table_ac = $wpdb->prefix . 'clientify_abandoned_cart';
			$response =	$wpdb->delete($table_ac, array('id_customer' => $id_ac));
		}
	}
		/**
	 * Collects abandoned carts and sends them to clientify.
	 *
	 * @since    1.0.0
	 * @param    string                  $cookie_cart_id    The id number in tab abandoned_cart.
	 */
	function sync_hook_abandoned_cart($cookie_cart_id)
	{
		
		if(get_option('CLIENTIFY_STATUS') != 0){
			global $wpdb;
			$endpoint_class = new Clientify_Endpoint();
			$url_base = $endpoint_class->get_local_api_url();

			if(is_plugin_active('woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php')){
			foreach ( $cookie_cart_id as $sesson_id ) {
				
				

				if ( $sesson_id ) {
					$details          = Cartflows_Ca_Helper::get_instance()->get_checkout_details( $sesson_id );
					$user_details     = (object) maybe_unserialize( $details->other_fields );

					$cart_abandonment = Cartflows_Ca_Helper::get_instance();
					$token_data       = array( 'wcf_session_id' => $details->session_id );
					
				}

				$cart_content = maybe_unserialize( $details->cart_contents );
				
				if ( ! is_array( $cart_content ) || ! count( $cart_content ) ) {
					return;
				}
				$items = array();
				$total_price = 0;

				$total    = 0;
				$discount = 0;
				$tax      = 0;
					foreach ( $cart_content as $cart_item ){

						$discount  = $discount + ( $cart_item['line_subtotal'] - $cart_item['line_total'] );
						$total     = $total + $cart_item['line_subtotal'];
						$tax       = $tax + $cart_item['line_tax'];
						
						$shipping = $discount + ( $details->cart_total - $total ) - $tax ;
						
								$contact = null;
								$visitor_key = null;

						
						$product_id = $cart_item['product_id'];
						$categories = array();
						$sub_categories= array();
						$terms = get_the_terms($product_id, 'product_cat');
						foreach ( $terms as $term ) {
							if ( $term->parent == 0 ){                                           
								$categories[] = $term->term_id.":".$term->slug;
							}else{
								foreach ( $categories as $cat ) {
									$cat_data = explode(":",$cat);													
									(int)$cat_data[0] == $term->parent ? $cat = true : $cat = false;
								}
								if( !$cat ){                            
									$term_search = get_term_by('id', $term->parent, 'product_cat');
									if($term_search->parent == 0){
										$categories[] = $term_search->term_id.":".$term_search->slug;
									}
									else{
										$sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term_search->parent;
									}                            
								}
								if ( !in_array($term->term_id.":".$term->slug."|parent_id:".$term_search->parent, $sub_categories) ) {
										$sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term->parent;
									}
								foreach ( $categories as $key => $value ) {                            
									foreach( $sub_categories as $key => $value_sub ){
										$explode = explode("|", $value_sub);
										$arr = array($explode[0]);
										if (in_array($value, $arr)) {
										unset($categories[$key]);
										}
									}                            
								}                        
							}          
						}
						$cart_date = $cart_item->date_add;
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
						
						$price = $product->get_sale_price();
						if ( empty($price) ) {
							$price = $product->get_regular_price();
						}	
						$join_cat = implode(",",$categories)."/".implode(",",$sub_categories);
						
						$items[] = array(
							'name'        => $product->get_title(),
							'description' => $product->get_description(),
							'category'    => $join_cat,
							'sku'         => $product->get_sku(),
							'image_url'   => get_the_post_thumbnail_url($product_id),
							'item_url'    => $product->get_permalink($cart_item),
							'price'       => $price,
							'quantity'    => (int) $cart_item['quantity'],
							'discount'    => $discount,
						);
						
						
					}
					
				$data = array(
					'status'         => 'abandoned',
					'abandoned_date' => date('Y-m-d', strtotime($details->time)),
					'ecommerce'      => 'woocommerce',
					'shop_name'      => get_option('blogname'),
					'order_url'      => $cart_abandonment->get_checkout_url( $details->checkout_id, $token_data ),
					'currency'       => get_option('woocommerce_currency'),
					'store_url'      => $url_base,
					'products'       => $items,
					'price'	         => $details->cart_total,
					'shipping'	     => $shipping,
					'coupon'         =>  0
					
				);
				
				$lang = get_bloginfo("language");
				$site_name = get_option('blogname');
				$site_name_valid = empty( $site_name ) ? 'WordPress' : $site_name;
				
				$data['contact'] = array(
							'id_customer'     => '',
							'email'       	  => $details->email,
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
							$data['contact']['phones'][] = array('phone' => $user_details->wcf_phone_number);
							$customer_phones[] = $user_details->wcf_phone_number;
						}

						
				
				
				$sql = 'SELECT wccca.id FROM ' . $wpdb->prefix . 'cartflows_ca_cart_abandonment wccca WHERE session_id="'.$sesson_id.'"';
            	$id_cartslow = $wpdb->get_row($sql);

				
				$data['cart_id'] = $id_cartslow->id;
				$data['order_id'] = $id_cartslow->id;
				
				$api = new Clientify_Api;
				$clientify_cart = $api->post_order_clientify($data);

				$insert_clientify = array(
					'cartflows_id' 	  => $id_cartslow->id,
					'cartflows_email' => $details->email,
					'date_add' 		  => $details->time
				); 

				$clientify_cart->data_insert = $insert_clientify;
				
				return $clientify_cart;
	
			}
	
			}else{

				$id_contac = is_null($cookie_cart_id->id_customer) || $cookie_cart_id->id_customer == '' ? $cookie_cart_id->cookie_cart_id : $cookie_cart_id->id_customer;
				$table_name = is_null($cookie_cart_id->id_customer) || $cookie_cart_id->id_customer == '' ? 'cookie_cart_id' : 'id_customer';
				$carts = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'cart where '. $table_name .' = "' . $id_contac . '"');		
				$items = array();
				$total_price = 0;
				foreach ( $carts as $cart_item_key => $cart_item ) {
					
					$contact = null;
					$visitor_key = null;

					if ( $cart_item->id_customer ) {
						$contact = $cookie_cart_id->id_customer;
					} else {
						$visitor_key = (string)$cookie_cart_id->cookie_cart_id;
					}
					if ( empty($contact) && empty($visitor_key) ) {
						return false;
					}
					$product_id = $cart_item->id_product;
					$categories = array();
					$sub_categories= array();
					$terms = get_the_terms($product_id, 'product_cat');
					foreach ( $terms as $term ) {
						if ( $term->parent == 0 ){                                           
							$categories[] = $term->term_id.":".$term->slug;
						}else{
							foreach ( $categories as $cat ) {
								$cat_data = explode(":",$cat);													
								(int)$cat_data[0] == $term->parent ? $cat = true : $cat = false;
							}
							if( !$cat ){                            
								$term_search = get_term_by('id', $term->parent, 'product_cat');
								if($term_search->parent == 0){
									$categories[] = $term_search->term_id.":".$term_search->slug;
								}
								else{
									$sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term_search->parent;
								}                            
							}
							if ( !in_array($term->term_id.":".$term->slug."|parent_id:".$term_search->parent, $sub_categories) ) {
									$sub_categories[] = $term->term_id.":".$term->slug."|parent_id:".$term->parent;
								}
							foreach ( $categories as $key => $value ) {                            
								foreach( $sub_categories as $key => $value_sub ){
									$explode = explode("|", $value_sub);
									$arr = array($explode[0]);
									if (in_array($value, $arr)) {
									unset($categories[$key]);
									}
								}                            
							}                        
						}          
					}
					$cart_date = $cart_item->date_add;
					$product = wc_get_product($product_id);
					$price = $product->get_price();
					$total_price += $price;
					$without_reduction = $product->get_regular_price();
					$discount = $without_reduction - $price;
					if ( $discount ) {
						$discount = round( ($discount / $without_reduction) * 100, 2);
					}

					$price = $product->get_sale_price();
					if ( empty($price) ) {
						$price = $product->get_regular_price();
					}	
					$join_cat = implode(",",$categories)."/".implode(",",$sub_categories);

					$items[] = array(
						'name'        => $product->get_title(),
						'description' => $product->get_description(),
						'category'    => $join_cat,
						'sku'         => $product->get_sku(),
						'image_url'   => get_the_post_thumbnail_url($product_id),
						'item_url'    => $product->get_permalink($cart_item),
						'price'       => $price,
						'quantity'    => $cart_item->quantity,
						'discount'    => 0,
					);		
				}
				$cart_page_id = wc_get_page_id( 'cart' );
				$cart_page_url = $cart_page_id ? get_permalink( $cart_page_id ) : '';	

				$data = array(
					'status'         => 'abandoned',
					'abandoned_date' => date('Y-m-d', strtotime($cart_date)),
					'ecommerce'      => 'woocommerce',
					'shop_name'      => get_option('blogname'),
					'order_url'      => $cart_page_url.$cookie_cart_id->id_clientify_abandoned_cart,
					'currency'       => get_option('woocommerce_currency'),
					'store_url'      => $url_base,
					'products'       => $items,
					'price'	         => $total_price,
					'coupon'         =>  0
				);
				if ( $contact ) {
					$data['contact'] = $this->get_contact($cookie_cart_id->id_customer);
				} else {
					$data['visitor_key'] = $visitor_key;
				}

				$in_aban = array(
					'cookie_cart_id' => is_null($cookie_cart_id->cookie_cart_id) || $cookie_cart_id->cookie_cart_id == '' ? NULL : $cookie_cart_id->cookie_cart_id,
					'id_customer'    => is_null($cart_item->id_customer) || $cart_item->id_customer == '' ? NULL : $cart_item->id_customer,
					'date_add'		 => $cart_date
				);
				$search_duplicate = $wpdb->get_results('SELECT id_clientify_abandoned_cart FROM ' . $wpdb->prefix . 'clientify_abandoned_cart where '. $table_name .' = "' . $id_contac . '"');
				
				if( $search_duplicate != 0 ){
					$search_duplicate2 = $wpdb->get_results('SELECT id_clientify_abandoned_cart FROM ' . $wpdb->prefix . 'clientify_abandoned_cart where '. $table_name .' = "' . $id_contac . '"');
						if( $search_duplicate2 != 0 ){
							$wpdb->insert($wpdb->prefix . "clientify_abandoned_cart", $in_aban);
						}
				}else{
					$wpdb->update( $wpdb->prefix . "clientify_abandoned_cart",  $in_aban, array( 'id_clientify_abandoned_cart'=>$search_duplicate ) );	
				}
				
				$id_cart = $wpdb->get_results('SELECT id_clientify_abandoned_cart FROM ' . $wpdb->prefix . 'clientify_abandoned_cart where '. $table_name .' = "' . $id_contac . '"');
				$data['cart_id'] = $id_cart[0]->id_clientify_abandoned_cart;
				$data['order_id'] = $id_cart[0]->id_clientify_abandoned_cart;
			}//if pg cartflow
			
			$api = new Clientify_Api;
			$clientify_cart = $api->post_order_clientify($data);
			

			if ( $clientify_cart->status == 'error' ) {
				$errordata = $clientify_cart->data;
				if( strpos($errordata, 'Abandoned cart is already registered') == true ){
					return true;
				}else{
					$wpdb->delete(
						$wpdb->prefix . 'clientify_abandoned_cart', 		// table name with dynamic prefix
						['id_clientify_abandoned_cart' => $id_cart[0]->id_clientify_abandoned_cart], 						// which id need to delete
						['%d'], 							// make sure the id format
					);
				}			
			}
		}else {
			return true;
		}
	}
	/**
	 * for long-standing customers we handle temporary cookie.
	 *
	 * @since    1.0.0
	 */
	public function clientify_update_vk()
	{
		global $wpdb;

		$user = wp_get_current_user();

		if ( isset($_POST['vk']) && $_POST['vk'] && !$user->ID ) {
			if ( $_COOKIE['cookie_cart_id'] ) {
				$id_clientify_visitor_cart = $wpdb->get_var('SELECT id_clientify_visitor_cart FROM ' . $wpdb->prefix . 'clientify_visitor_cart WHERE cookie_cart_id = "' . $_COOKIE['cookie_cart_id'] . '"');

				if ( !$id_clientify_visitor_cart ) {
					$cookie_cart_id = $_COOKIE['cookie_cart_id'];
					$visitor_key = $_POST['vk'];
					$date_add = current_time('mysql');
					$wpdb->query("INSERT INTO " . $wpdb->prefix . "clientify_visitor_cart (cookie_cart_id, visitor_key, date_add) VALUES ('$cookie_cart_id', '$visitor_key', '$date_add')");
				}
			}
		}
		exit;
	}
}