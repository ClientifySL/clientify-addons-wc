
<?php
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Api.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ClientifyEndpoint.php';

class RegisterCustomPostType
{
	// create custom plugin settings menu

	function clientify_create_menu()
	{

		add_menu_page('Clientify', 'Clientify', 'administrator', __FILE__, 'clientify_settings_page', plugins_url('../public/img/logo.png', __FILE__));
	}
	public function clientify_index()
	{
		/* include admin view */
		include plugin_dir_path(dirname(__FILE__)) . 'admin/Admin-Page.php';
	}

	public function clientify_settings()
	{
		//register our settings
		register_setting('clientify-settings-group', 'CLIENTIFY_API_KEY');
		register_setting('clientify-settings-group', 'CLIENTIFY_API_LOG');
		register_setting('clientify-settings-group', 'CLIENTIFY_SCRIPT');
		register_setting('clientify-settings-group', 'CLIENTIFY_BOTTOM_SCRIPT');
		register_setting('clientify-settings-group', 'CLIENTIFY_ORDER_STATUS');

		register_setting('clientify-settings-group', 'CLIENTIFY_CART_HOUR', 6);
		register_setting('clientify-settings-store', 'CLIENTIFY_STORE_KEY');
		register_setting('clientify-settings-status', 'CLIENTIFY_STATUS' , 0);
	}

	function clientify_settings_page()
	{

		$tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
	}

	//sync abandoned cart
	function clientify_action_init()
	{

		//if (isset($_GET['sync-clientify-abandoned-carts'])) {
		global $wpdb;

		$shop_name = get_bloginfo('name');
		$msg = '';
		if (is_plugin_active('woocommerce/woocommerce.php')) {
			$cart_hour = (int)get_option('CLIENTIFY_CART_HOUR');
			$sql = 'SELECT DISTINCT c.cookie_cart_id, c.id_customer FROM ' . $wpdb->prefix . 'cart c ';
			$sql .= 'LEFT JOIN ' . $wpdb->prefix . 'clientify_abandoned_cart cac ON (cac.cookie_cart_id = c.cookie_cart_id) OR (cac.id_customer = c.id_customer) WHERE cac.cookie_cart_id IS NULL AND cac.id_customer IS NULL AND TIMESTAMPDIFF(HOUR, c.date_add, now()) >= ' . $cart_hour;
			$cookie_carts = $wpdb->get_results($sql);
			$count = 0;

			if (!empty($cookie_carts)) {

				foreach ($cookie_carts as $cookie_carts_results => $cart_data) {
					
					$this->syncAbandonedCart($cart_data);

				}
			}


			//$msg = 'Synced ' . $count . ' cart(s)';
		}

		//}
	}

	// END create custom plugin settings menu

	/* Connect and Disconnect bridge wicht clientify */

	function connect_clientify()
	{

		$key = $_POST['apikey'] !=	'' ? $_POST['apikey'] : get_option('CLIENTIFY_API_KEY');

		if ($_POST['apikey'] != '' || get_option('CLIENTIFY_API_KEY') != '') {
			/* generate uid key for connect to clientify */
			$endpoint_class = new CustomClientifyEndPoint();
			$api = new ClientifyApi;

			$key_uid = $endpoint_class->token_id();
			$url_base = $endpoint_class->GetApiUrl();

			$post_key = array(
				'ecommerce' => 'woocommerce',
				'action'    => 'connect',
				'store_key' => $key_uid,
				'store_key' => $key_uid,
				'name'      => get_option('blogname'),
				'store_url' => $url_base
			);

			$response = $api->Post_Base_Clientify($post_key, $key);
		}
		if (is_null($response) || isset($response->detail)) {

            $data= is_null($response) !=    '' ? "error" : $response->detail;

            $response = array(
                'data' => array(
                        'status' => $data
                    )
                );
            
        }else {
            foreach ($response as $obj) {
                $status = $obj->status;
            }     
            if ($status == 'success') {
    
				update_option('CLIENTIFY_STATUS', 1);
            }
        }

		echo  json_encode($response);
		die();

		//return $response;
	}

	function disconnect_clientify()
	{

		$key = $_POST['apikey'] !=	'' ? $_POST['apikey'] : get_option('CLIENTIFY_API_KEY');

		if ($_POST['apikey'] != '' || get_option('CLIENTIFY_API_KEY') != '') {
			/* generate uid key for connect to clientify */
			$endpoint_class = new CustomClientifyEndPoint();
			$api = new ClientifyApi;

			$key_uid = get_option('CLIENTIFY_STORE_KEY');
			$url_base = $endpoint_class->GetApiUrl();

			$post_key = array(
				'ecommerce' => 'woocommerce',
				'action'    => 'disconnect',
				'store_key' => $key_uid,
				'name'      => get_option('blogname'),
				'store_url' => $url_base
			);

			$response = $api->Post_Base_Clientify($post_key, $key);
		}

		foreach ($response as $obj) {
			$status = $obj->status;
			
		}
		
		//succes/ fail / error
		if ($status == 'success' || $status == 'failed' || $status == null) {
			update_option('CLIENTIFY_STATUS', 0);
		}

		echo  json_encode($response);
		die();
	}
	/* END Connect and Disconnect bridge wicht clientify */

	/* contacs seccition  */

	function Get_contact($user_id)
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
		//$contact = $this->getContactByCustomerId($user_id, true);

		if ($user_id != 0) {
			$data = array(
				'id_customer' => $user_id,
				'email' => $customer->email,
				'contact_source' => get_option('blogname'),
				'user_registered' => $user->user_registered,
				'custom_fields' => [],
				'tags' => array(
					'woocommerce',
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

	// function create_clientify_contact($order_id)
	// {


	// 	$order = new WC_Order($order_id);
	// 	$clientify_vk = $_COOKIE['clientify_vk'];
	// 	$order_email = $order->billing_email;
	// 	$customer_phones = array();
	// 	// check if there are any users with the billing email as user or email
	// 	$email = email_exists($order_email);
	// 	$user = username_exists($order_email);

	// 	$site_name = get_option('blogname');
	// 	$site_name = empty($site_name) ? 'WordPress' : $site_name;

	// 	// if the UID is null, then it's a guest checkout
	// 	if ($user == false && $email == false) {
	// 		$data = array(
	// 			'first_name' => $order->billing_first_name,
	// 			'last_name' => $order->billing_last_name,
	// 			'email' => $order_email,
	// 			'contact_source' => get_option('blogname'),
	// 			'custom_fields' => [],
	// 			'tags' => array(
	// 				'woocommerce',
	// 				$site_name,
	// 			),
	// 		);

	// 		$street = $order->billing_address_1 . (!empty($order->billing_address_2) ? ', ' . $order->billing_address_2 : '');
	// 		$city = $order->billing_city;
	// 		$country = WC()->countries->countries[$order->billing_country];
	// 		$postal_code = $order->billing_postcode;
	// 		$visitor_key = (string)$clientify_vk;

	// 		$customer_address = array(
	// 			'type' => 1
	// 		);

	// 		if ($street) {
	// 			$customer_address['street'] = $street;
	// 		}

	// 		if ($city) {
	// 			$customer_address['city'] = $city;
	// 		}

	// 		if ($country) {
	// 			$customer_address['country'] = $country;
	// 		}

	// 		if ($postal_code) {
	// 			$customer_address['postal_code'] = $postal_code;
	// 		}

	// 		if (!empty($order->get_billing_state())) {
	// 			$customer_address['state'] = WC()->countries->get_states($order->billing_country)[$order->get_billing_state()];
	// 			if (empty($customer_address['state'])) {
	// 				unset($customer_address['state']);
	// 			}
	// 		}

	// 		$data['addresses'][] = $customer_address;

	// 		if (!empty($order->get_billing_company())) {
	// 			$data['company'] = $order->get_billing_company();
	// 		}

	// 		if (!empty($order->billing_phone) && !in_array($order->billing_phone, $customer_phones)) {
	// 			$data['phones'][] = array(
	// 				'phone' => $order->billing_phone
	// 			);
	// 			$customer_phones[] = $order->billing_phone;
	// 		}

	// 		if ($visitor_key) {
	// 			$data['visitor_key'] = $visitor_key;
	// 		}

	// 		$api = new ClientifyApi;
	// 		$guest_contact = $api->Post_Contacts_Clientify($data);
	// 		$clientify_id = null;

	// 		if (isset($guest_contact->id) && $guest_contact->id) {
	// 			$clientify_id = $guest_contact->id;
	// 		}
	// 		return $clientify_id;
	// 	}
	// }

	function getContactByCustomerId($id_customer, $update = false)
	{
		global $wpdb;
		if (is_plugin_active('woocommerce/woocommerce.php')) {
			$clientify_id = $wpdb->get_var('SELECT clientify_id FROM ' . $wpdb->prefix . 'clientify_customer WHERE id_customer = ' . (int)$id_customer);
			if (!$clientify_id) {
				$contact = $this->syncCustomer($id_customer);

				if (isset($contact->id) && $contact->id) {
					$clientify_id = $contact->id;
				}
			} elseif ($update) {
				$this->syncCustomer($id_customer);
			}
		}

		return $clientify_id;
	}

	/* END contacs seccition  */
	//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	/* customer Secction*/

	//Hook register user in woocomece
	function customer_add($user_id)
	{
		if (is_plugin_active('woocommerce/woocommerce.php') && !is_admin()) {
			$this->syncCustomer($user_id);
		}
	}
	//hook cart wc
	function customer_update_address_for_orders($user_id)
	{
		$this->syncCustomer($user_id);
	}
	/* END Customer */
	//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	/* Sync customer Secction*/

	function sync_customer()
	{
		global $wpdb;
		$sql = 'SELECT c.ID FROM ' . $wpdb->prefix . 'users c LEFT JOIN ' . $wpdb->prefix . 'clientify_customer cc ON (c.ID = cc.id_customer) WHERE cc.id_customer IS NULL';
		$customers_to_sync = $wpdb->get_results($sql);
		$count = 0;
		foreach ($customers_to_sync as $customer_to_sync) {
			$customer = $this->syncCustomer($customer_to_sync->ID);
			if (isset($customer->id)) {
				$count++;
			}
		}
		echo json_encode(array('count' => $count));
		die();
	}
	//Syns Users local sync to clientify
	function syncCustomer($user_id)
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

		$endpoint_class = new CustomClientifyEndPoint();
		$url_base = $endpoint_class->GetApiUrl();

		if ($user_id != 0) {
			$data = array(
				'status' => 'customer',
				'store_url' => $url_base,
				'email' => $customer->email,
				'contact_source' => get_option('blogname'),
				'custom_fields' => [],
				'tags' => array(
					'woocommerce',
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
			} elseif ($woocommerce->customer->get_address()) {

				$street = $woocommerce->customer->get_billing_address() . (!empty($woocommerce->customer->get_billing_address_2()) ? ', ' . $woocommerce->customer->get_billing_address_2() : '');
				$city = $woocommerce->customer->get_billing_city();
				$country = WC()->countries->countries[$woocommerce->customer->get_billing_country()];
				$postal_code = $woocommerce->customer->get_billing_postcode();
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

				if (!empty($woocommerce->customer->get_billing_state())) {
					$customer_address['state'] = WC()->countries->get_states($woocommerce->customer->get_billing_country())[$woocommerce->customer->get_billing_state()];
					if (empty($customer_address['state'])) {
						unset($customer_address['state']);
					}
				}

				$data['addresses'][] = $customer_address;

				if (!empty($woocommerce->customer->get_billing_company())) {
					$data['company'] = $woocommerce->customer->get_billing_company();
				}

				if (!empty($woocommerce->customer->get_billing_phone()) && !in_array($woocommerce->customer->get_billing_phone(), $customer_phones)) {
					$data['phones'][] = array('phone' => $woocommerce->customer->get_billing_phone());
					$customer_phones[] = $woocommerce->customer->get_billing_phone();
				}
			}

			$clientify_vk = isset($_COOKIE['vk']) ? $_COOKIE['vk'] : '';

			if (isset($clientify_vk) && $clientify_vk) {
				$data['visitor_key'] = (string)$clientify_vk;
			}
		} //end if user_id	
		//Send to api
		$api = new ClientifyApi;
		$contact = $api->Post_Contacts_Clientify($data);
		$data = array(
			'id_customer' => $user_id,
			'clientify_id' => $contact->id,
		);
		$clien_consul = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}clientify_customer WHERE id_customer = $user_id AND clientify_id = $contact->id");

		if (isset($contact->id) && empty($clien_consul)) {
			$wpdb->insert($wpdb->prefix . "clientify_customer", $data);
		}
		return $contact;
	}

	/* END Sync customer Secction*/
	//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	
	function syncOrder($order_id, $status_transition_to, $that)
	{
		global $product;
		global $wpdb;
		$endpoint_class = new CustomClientifyEndPoint();
		$url_base = $endpoint_class->GetApiUrl();
		//status of orden processin payment ...
		// get type status in select front
		$clientify_order_status = substr(get_option('CLIENTIFY_ORDER_STATUS'), 3);

		if ($status_transition_to == $clientify_order_status) {

			$items = array();
			$order = wc_get_order($order_id); //cn esto valido al llegar if
			$order_status  = $order->get_status();
			$order_data = $order->get_data();
			$id_customer = $order->get_customer_id();
			$total_price = $order->get_total();
			$lang = get_bloginfo("language");
			$products = $order->get_items();
			$currency = $order->get_currency();

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
				//'visitor_key' => (string)$this->getVisitorKeyByCartId($_COOKIE['vk']),
				'coupon' => $order_discount_total ? $order_discount_total : 0,
			);
			if (!empty($lang)) {
				$data['custom_field'] = array(
					'field' => 'ecommerce_language',
					'value' => $lang,
				);
			}
			
			$api = new ClientifyApi;
			$clientify_order = $api->Post_Order_Clientify($data);

			$res_cart_ac = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}cart WHERE id_customer  = {$id_customer} ");
			
			if (!empty($clientify_order) && $res_cart_ac != '') {
				$this->delete_cart($order_id);
			}

			return $clientify_order;
		}
	}

	/* END Sync Orders Secction*/

	function clientify_api_script()
	{

		$bottom_script = get_option('CLIENTIFY_SCRIPT');
		if (!empty($bottom_script)) {

			echo $bottom_script;
		}
	}

	/*  ABANDON CART FUNTIONS  */

	function clientify_save_add_to_cart($cart_item_key, $product_id)
	{
		global $wpdb;
		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			$product_id = $cart_item['product_id'];
			$quantity = $cart_item['quantity'];
			$user = wp_get_current_user();
			$lang = get_bloginfo("language");
			$currency = get_option('woocommerce_currency');
			$id_cart = $wpdb->get_var('SELECT id_cart FROM ' . $wpdb->prefix . 'cart WHERE id_customer = ' . (int)$user->ID . ' AND id_product = ' . (int)$product_id . '');
			$sql = $wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'cart WHERE id_customer = ' . (int)$user->ID . ' AND id_product = ' . (int)$product_id . ' ');
			$data =	$wpdb->get_results($sql);
			
			if($user->ID){
				
					if (!$id_cart) {
						$save_cart = array(
							'cookie_cart_id' => $_COOKIE['vk'] == '' ? NULL : $_COOKIE['vk'],
							'id_customer' => $user->ID,
							'id_product' => $product_id,
							'quantity' => $quantity,
							'currency' => $currency,
							'language' => $lang
						);
				
					//guardo y actualizo la cookie 
					// foreach ($data_cookie as $up_cookie) {
					// 	//$cookie_local_db = $up_cookie->cookie_cart_id;
					// 	$user_local_db = intval($up_cookie->id_customer);
					// 	//$product_local_db = $up_cookie->id_product;
					// }
					//valido si la cokkie existe si no creo uno nuevo
					// if ($user->ID == $user_local_db) {
					// 	$user_login = wp_get_current_user();
					// 	$res = $this->update_cookie_cart($user_login, $user);
					// } else {
						sleep(2);
						$wpdb->insert($wpdb->prefix . "cart", $save_cart);
					// }
				} else {
					$wpdb->query("UPDATE " . $wpdb->prefix . "cart SET quantity = " . $quantity . " WHERE id_cart = " . (int)$id_cart);
				}
			}		
			
		}
	}

	function clientify_cart_updated($cart_updated)
	{
		if ($cart_updated) {
			$this->clientify_save_add_to_cart(null, null);
		}
	}

	function delete_cart($order_id)
	{
		global $wpdb;
		
		$order = wc_get_order($order_id);
		$order_customer_id = $order->get_customer_id();
		$wpdb->query('DELETE FROM ' . $wpdb->prefix . 'cart WHERE id_customer = ' . (int)$order_customer_id . ' ');
		$wpdb->query('DELETE FROM ' . $wpdb->prefix . 'clientify_abandoned_cart WHERE id_customer = ' . (int)$order_customer_id . ' ');
		//setcookie('cookie_cart_id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
	}
	function delete_item_cart($cart_item)
	{
		global $wpdb;
		$user = wp_get_current_user();

		foreach (WC()->cart->get_cart() as $instance => $cart_items) {

			if ($cart_items['key'] === $cart_item) {
				$product_id = $cart_items['product_id'];
			}
		}
		$id = $product_id;
		$cookie = $_COOKIE['vk'];
		$table = $wpdb->prefix . 'cart';

		$response = $wpdb->delete($table, array('id_product' => $id,'id_customer' =>$user->ID ));
		$var = "SELECT count(*) FROM {$wpdb->prefix}cart WHERE id_customer ={$user->ID}";
		$count_cart =	$wpdb->get_var($var);

		if ($count_cart == 0) {

			$id_ac = $user->ID;
			$table_ac = $wpdb->prefix . 'clientify_abandoned_cart';
			$response =	$wpdb->delete($table_ac, array('id_customer' => $id_ac));
		}
	}
	function syncAbandonedCart($cookie_cart_id)
	{
		global $wpdb;
		$endpoint_class = new CustomClientifyEndPoint();
		$url_base = $endpoint_class->GetApiUrl();
		$id_contac = is_null($cookie_cart_id->id_customer) || $cookie_cart_id->id_customer == '' ? $cookie_cart_id->cookie_cart_id : $cookie_cart_id->id_customer;
		$table_name = is_null($cookie_cart_id->id_customer) || $cookie_cart_id->id_customer == '' ? 'cookie_cart_id' : 'id_customer';
		$carts = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'cart where '. $table_name .' = "' . $id_contac . '"');		
		$items = array();
		
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
			$terms = get_the_terms($product_id, 'product_cat');

			foreach ($terms as $term) {
				$product_cat = $term->name;
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
				$price = $product->get_regular_price();
			}	

			$items[] = array(
				'name' => $product->get_title(),
				'description' => $product->get_description(),
				'category' => $product_cat,
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
			'cart_id' => is_null($cookie_cart_id->id_customer) ? $cookie_cart_id->cookie_cart_id : $cookie_cart_id->id_customer,
			'ecommerce' => 'woocommerce',
			'shop_name' => get_option('blogname'),
			'order_url' => $cart_page_url,
			'currency' => get_option('woocommerce_currency'),
			'store_url' => $url_base,
			'products' => $items,
			'coupon' =>  0
		);
		if ($contact) {
			$data['contact'] = $this->Get_contact($cookie_cart_id->id_customer);
		} else {
			$data['visitor_key'] = $visitor_key;
		}
		$api = new ClientifyApi;
		$clientify_cart = $api->Post_Order_Clientify($data);

		$in_aban = array(
			'cookie_cart_id' => is_null($cookie_cart_id->cookie_cart_id) || $cookie_cart_id->cookie_cart_id == '' ? NULL : $cookie_cart_id->cookie_cart_id,
			'id_customer' => is_null($cart_item->id_customer) || $cart_item->id_customer == '' ? NULL : $cart_item->id_customer,
		);
		
		if ($clientify_cart->status != 'error') {
			$wpdb->insert($wpdb->prefix . "clientify_abandoned_cart", $in_aban);
		}
	}
	/* END ABANDON CART FUNTIONS */
	//Visitor

	public function clientify_update_vk()
	{
		var_dump($_POST);
		die();
		global $wpdb;

		$user = wp_get_current_user();

		if (isset($_POST['vk']) && $_POST['vk'] && !$user->ID) {
			if ($_COOKIE['cookie_cart_id']) {
				$id_clientify_visitor_cart = $wpdb->get_var('SELECT id_clientify_visitor_cart FROM ' . $wpdb->prefix . 'clientify_visitor_cart WHERE cookie_cart_id = "' . $_COOKIE['cookie_cart_id'] . '"');

				if (!$id_clientify_visitor_cart) {
					$cookie_cart_id = $_COOKIE['cookie_cart_id'];
					$visitor_key = $_POST['vk'];
					$date_add = current_time('mysql');

					$wpdb->query("INSERT INTO " . $wpdb->prefix . "clientify_visitor_cart (cookie_cart_id, visitor_key, date_add) VALUES ('$cookie_cart_id', '$visitor_key', '$date_add')");
				}
			}
		}

		$clientify_vk = $_POST['vk'];
		//setcookie( 'clientify_vk', $clientify_vk, time() + 3600, COOKIEPATH, COOKIE_DOMAIN   );

		exit;
	}

	//order validation  visitor
	// function getVisitorKeyByCartId($cookie_cart_id)
	// {
	// 	global $wpdb;
	// 	return $wpdb->get_var('SELECT visitor_key FROM ' . $wpdb->prefix . 'clientify_visitor_cart WHERE cookie_cart_id = "' . $cookie_cart_id . '"');
	// }
	// public function orders(){
	// 	global $product;
	// 	global $wpdb;
	// 	$endpoint_class = new CustomClientifyEndPoint();
	// 	$url_base = $endpoint_class->GetApiUrl();
	// 	$args = array(
	// 		'limit' => 9999,
	// 		'return' => 'ids'
	// 	   );
	// 	   $query = new WC_Order_Query( $args );
	// 	   $orders_ids = $query->get_orders();
	// 	//    $data = [];
	// 	//    foreach( $orders as $order_id ) {
	// 	// 		$data = wc_get_order( $order_id );
	// 	// 		//$data = $data->get_user_id();
	// 	// 		var_dump($data);
	
	// 	//    }
	// 	$all = array();

	// 	foreach( $orders_ids as $order_id ) {		   
	// 	   $order = wc_get_order($order_id); //cn esto valido al llegar if
	// 	   $order_status  = $order->get_status();
	// 	   $order_data = $order->get_data();
	// 	   $id_customer = $order->get_customer_id();

	// 	   $contact = null;
	// 	   $lang = get_bloginfo("language");
	// 	   $products = $order->get_items();
	// 	   $currency = $order->get_currency();

	// 	   $items = array();
	// 	   foreach ($products as $order_product) {

	// 		   $terms = get_the_terms($order_product['product_id'], 'product_cat');
	// 		   foreach ($terms as $term) {
	// 			   $product_cat_slug = $term->slug;
	// 		   }

	// 		   $product = $order_product->get_product();
			   
	// 		   $sku = $product->get_sku();
	// 		   $image_id  = $product->get_image_id();
	// 		   $image_url = wp_get_attachment_image_url($image_id, 'full');
	// 		   $product_id = $order_product['product_id'];
	// 		   $product_instance = wc_get_product($product_id);
	// 		   $product_full_description = $product_instance->get_description();
	// 		   $tax_amount = $order->get_item_tax($order_product, true, true);
	// 		   $inc_tax = $tax_amount > 0 ? true : false;
	// 		   $price = $product->get_sale_price();


	// 		//    if ($inc_tax) {
	// 		// 	   $price = wc_get_price_including_tax($product, array('price' => $price));
	// 		//    } else {
	// 		// 	   $price = wc_get_price_excluding_tax($product, array('price' => $price));
	// 		//    }

	// 		   $items[] = array(
	// 			   'name' => $order_product->get_name(),
	// 			   'description' => $product_full_description,
	// 			   'category' => $product_cat_slug,
	// 			   'sku' => $sku,
	// 			   'image_url' => $image_url,
	// 			   'item_url' => get_permalink($order_product['product_id']),
	// 			   'price' => $price,
	// 			   'quantity' => $order_product->get_quantity(),
	// 			   'discount' => 0, //$discount
	// 		   );
	// 	   }
	// 	//    //total discount
	// 	//    $order_discount_total = $order->get_total_discount(!$inc_tax);

	// 	   $data = array(
	// 		   'action' => 'order',
	// 		   'store_url' => $url_base,
	// 		   //'contact' => $this->Get_contact($id_customer),
	// 		   //'visitor_key' => (string)$this->getVisitorKeyByCartId($_COOKIE['cookie_cart_id']),
	// 		   'status' => 'ordered',
	// 		   'order_date' => $order_data['date_created']->date('Y-m-d H:i:s'),
	// 		   'order_id' => $order->get_id(),
	// 		   'ecommerce' => 'woocommerce',
	// 		   'shop_name' => get_option('blogname'),
	// 		   'order_url' => $order->get_view_order_url(),
	// 		   'currency' => $currency,
	// 		   'items' => $items,
	// 		  // 'coupon' => $order_discount_total ? $order_discount_total : 0,
	// 	   );
		   
	// 	//    if (!empty($lang)) {
	// 	// 	   $data['custom_field'] = array(
	// 	// 		   'field' => 'ecommerce_language',
	// 	// 		   'value' => $lang,
	// 	// 	   );
	// 	  // }
	// 	   $all [] = array($data);
	// 	}
	// 	return $all;
	// 	//var_dump($items);
	// 	// $args =  array(
	// 	//     'limit' => -1,
	// 	//     'orderby' => 'date',
	// 	//     'order' => 'DESC',
	// 	// );
	
	// 	//$orders = wc_get_orders( $args );
	
	// 	//return json_encode($data);
	// }
}

