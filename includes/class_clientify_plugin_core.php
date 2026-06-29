<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-api-connect.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-endpoint.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-helper.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-addons-logs.php';

class Clientify_Plugin_Core
{
		/**
	 * clientify_create_menu Add option menu admin page WordPress.
	 *
	 * @since    1.1.0
	 */
	function clientify_create_menu() {
		add_menu_page(
			'Clientify', // Título de la página
			'Clientify', // Título del menú
			'administrator', // Capacidad requerida
			'clientify-addons', // Slug del menú (usamos un nombre simple)
			'clientify_settings_page', // Función de callback
			plugins_url('../public/img/logo.png', __FILE__), // Ícono
			6 // Posición en el menú
		);
	}
		/**
	 * Add config page in admin panel WordPress.
	 *
	 * @since    1.1.0
	 */
	public function clientify_index()
	{
		/* include admin view */
		include plugin_dir_path(dirname(__FILE__)) . 'admin/clientify-admin-page.php';
	}
	/**
	 * Define a global config parameters for clientify.
	 *
	 * @since    1.1.0
	 */
	public function clientify_settings()
	{
		//register our settings
		register_setting('clientify-settings-group', 'CLIENTIFY_API_KEY');
		register_setting('clientify-settings-gdpr', 'CLIENTIFY_GDPR', 0);
		register_setting('clientify-settings-gdpr_text', 'CLIENTIFY_GDPR_text');
		register_setting('clientify-settings-group', 'CLIENTIFY_API_LOG');
		register_setting('clientify-settings-script', 'CLIENTIFY_SCRIPT', 0);
		register_setting('clientify-settings-group', 'CLIENTIFY_BOTTOM_SCRIPT');
		register_setting('clientify-settings-status-order', 'CLIENTIFY_ORDER_STATUS');
		register_setting('clientify-settings-group', 'CLIENTIFY_CART_HOUR', 6);
		register_setting('clientify-settings-store', 'CLIENTIFY_STORE_KEY');
		register_setting('clientify-settings-status', 'CLIENTIFY_STATUS' , 0);
		
	}
	/**
	 * Send parameters to Connect plugin with clientify. Revised.
	 *
	 * @since    1.1.0
	 *
	 */
	private function send_json( $data ) {
		while ( ob_get_level() ) ob_end_clean();
		header( 'Content-Type: application/json' );
		echo json_encode( $data );
		die();
	}

	function connect_clientify()
	{
		$key = ( isset( $_POST['apikey'] ) && $_POST['apikey'] !== '' )
			? sanitize_text_field( $_POST['apikey'] )
			: get_option( 'CLIENTIFY_API_KEY' );

		if ( empty( $key ) ) {
			$this->send_json( [ 'status' => 'error', 'message' => 'API Key vacia.' ] );
		}

		$order_process = isset( $_POST['order_process'] ) ? $_POST['order_process'] : '';
		$gdpr_status   = isset( $_POST['gdpr_status'] ) ? intval( $_POST['gdpr_status'] ) : 0;
		$gdpr_text     = sanitize_text_field( isset( $_POST['gdpr_text'] ) ? $_POST['gdpr_text'] : '' );
		if ( empty( $gdpr_text ) ) {
			$gdpr_text = 'Acepto recibir comunicaciones comerciales GDPR';
		}
		update_option( 'CLIENTIFY_ORDER_STATUS', $order_process );
		update_option( 'clientify_gdpr_text', $gdpr_text );
		update_option( 'CLIENTIFY_GDPR', $gdpr_status );

		$endpoint_class = new Clientify_Endpoint();
		$api            = new Clientify_Api;
		$key_uid        = $endpoint_class->token_id();
		$url_base       = $endpoint_class->get_local_api_url();

		$post_key = [
			'ecommerce' => 'woocommerce',
			'action'    => 'connect',
			'store_key' => $key_uid,
			'name'      => get_option( 'blogname' ),
			'store_url' => $url_base,
		];

		$response = $api->post_base_clientify( $post_key, $key );

		// Case 1: network / WP_Error
		if ( is_array( $response ) && ! empty( $response['error'] ) ) {
			Clientify_Addons_Logs::insert_log( 'ERROR', sprintf(
				'Connect Error [network]: code=%s message=%s store_url=%s',
				$response['code'] ?? 'unknown',
				$response['message'] ?? 'unknown',
				$url_base
			), __FILE__, __LINE__ );
			$this->send_json( [ 'status' => 'error', 'message' => 'No se pudo conectar con Clientify. Verifica tu conexion a internet.' ] );
		}

		$http_code   = $response['http_code'];
		$body        = $response['body'];
		$api_status  = isset( $body->data->status )  ? $body->data->status  : null;
		$api_message = isset( $body->data->message ) ? $body->data->message : null;
		$detail      = isset( $body->detail )        ? strtolower( trim( $body->detail ) ) : '';

		Clientify_Addons_Logs::insert_log( 'INFO', sprintf(
			'Connect response: http=%d body=%s',
			$http_code, wp_json_encode( $body )
		), __FILE__, __LINE__ );

		// Success
		if ( $http_code === 200 && $api_status === 'success' ) {
			update_option( 'CLIENTIFY_STATUS', 1 );
			$this->send_json( [
				'status'   => 'success',
				'message'  => 'Conexion exitosa! Tu tienda WooCommerce ha sido conectada a Clientify.',
				'open_url' => 'https://new.clientify.com/sales/ecommerce',
			] );
		}

		// HTTP 200 business-logic failures (status = failed | error)
		if ( $http_code === 200 && in_array( $api_status, [ 'failed', 'error' ], true ) ) {
			$api_message_map = [
				'other owner with this store' => 'La URL de tienda ya esta conectada a otra cuenta de Clientify.',
				'Invalid body'                => 'Los datos enviados no son validos. Verifica la URL y la API Key.',
				'Store not found'             => 'Tienda no encontrada en Clientify. Intentalo de nuevo.',
			];
			$msg = isset( $api_message_map[ $api_message ] )
				? $api_message_map[ $api_message ]
				: ( $api_message ?: 'Error al procesar la solicitud. Intentalo de nuevo.' );
			Clientify_Addons_Logs::insert_log( 'ERROR', sprintf(
				'Connect Error [business]: http=%d api_status=%s api_message=%s store_url=%s',
				$http_code, $api_status, $api_message, $url_base
			), __FILE__, __LINE__ );
			$this->send_json( [ 'status' => 'error', 'message' => $msg ] );
		}

		// HTTP code fallback (4xx/5xx)
		$http_code_map = [
			400 => 'Solicitud incorrecta (400).',
			401 => 'API Key invalida o expirada (401).',
			403 => 'Limite de tiendas alcanzado (403).',
			404 => 'Endpoint no encontrado (404). Contacta con soporte.',
			406 => 'Formato de solicitud no aceptado (406).',
			429 => 'Demasiadas solicitudes (429). Espera un momento.',
			500 => 'Error interno del servidor de Clientify (500).',
			502 => 'Servidor de Clientify no disponible (502).',
			503 => 'Servicio de Clientify no disponible (503).',
		];

		$detail_msg = $detail ?: $api_message ?: '';
		if ( isset( $http_code_map[ $http_code ] ) ) {
			$msg = $http_code_map[ $http_code ] . ( $detail_msg ? ' Detalle: ' . $detail_msg : '' );
		} elseif ( $http_code >= 400 ) {
			$msg = 'Error HTTP ' . $http_code . ( $detail_msg ? '. Detalle: ' . $detail_msg : '' );
		} else {
			$msg = 'Respuesta inesperada de Clientify. Intentalo de nuevo.';
		}

		Clientify_Addons_Logs::insert_log( 'ERROR', sprintf(
			'Connect Error [api]: http=%d api_status=%s api_message=%s detail=%s store_url=%s',
			$http_code, $api_status ?? 'null', $api_message ?? 'null', $detail ?: 'none', $url_base
		), __FILE__, __LINE__ );
		$this->send_json( [ 'status' => 'error', 'message' => $msg ] );
	}
	/**
	 * Send parameters to gdpr with clientify. Revised.
	 *
	 * @since    1.1.0
	 */
	function change_gdpr(){
		$change_gdpr = $_POST['clientify_gdpr'];
		update_option('CLIENTIFY_GDPR', $change_gdpr);
		$response = get_option('CLIENTIFY_GDPR');
		echo wp_json_encode( $response );
		die();
	}

	/**
	 * Send parameters to Unlink with clientify. Revised.
	 *
	 * @since    1.1.0
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

		$api_status = null;
		if ( isset( $response['body'] ) ) {
			$api_status = isset( $response['body']->data->status ) ? $response['body']->data->status : null;
		}

		update_option( 'CLIENTIFY_STATUS', 0 );
		update_option( 'CLIENTIFY_GDPR', 0 );

		if ( $api_status === 'success' ) {
			$this->send_json( [ 'status' => 'success', 'message' => 'Desconexion de Clientify realizada correctamente.' ] );
		} else {
			$http_code_dc = isset( $response['http_code'] ) ? $response['http_code'] : 'n/a';
			$detail_dc    = isset( $response['body']->detail ) ? $response['body']->detail : ( isset( $response['message'] ) ? $response['message'] : 'unknown' );
			Clientify_Addons_Logs::insert_log( 'ERROR', sprintf(
				'Disconnect Error: http=%s api_status=%s detail=%s store_key=%s',
				$http_code_dc, $api_status ?? 'null', $detail_dc, $key_uid ?? 'unknown'
			), __FILE__, __LINE__ );
			$this->send_json( [ 'status' => 'error', 'message' => 'Error al desconectar. La sesion local ha sido cerrada.' ] );
		}
	}
	/**
	 * Query contact by Id in Wocommerce. Revised.
	 *
	 * @since    1.1.0
	 * @param    int                  $user_id    The custommer's id number. 
	 */
	function get_contact($user_id)
	{
		global $wpdb;
		global $woocommerce;
		$user = get_userdata($user_id);
		$lang = get_bloginfo("language");
		$customer_meta = get_user_meta($user_id);
		$customer_phones = array();
		$site_name = get_option('blogname');
		$site_name = empty($site_name) ? 'WordPress' : $site_name;
		$customer = new WC_Customer($user_id);
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
				'email'       	  => $customer->get_email(),
				'contact_source'  => get_option('blogname'),
				'user_registered' => $user->user_registered,
				// 'identification'  => $customer_dni,
				// 'gdpr_accept' 	 => ($suscripcion == 0) ? false : true,
				'custom_fields'   => [],
				'tags'            => array(
										'woocommerce',
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
	 * Check Wocommerce is activate.Revised.
	 *
	 * @since    1.1.0
	 * @param    int                  $user_id    The custommer's id number.
	 */
	function customer_add($user_id)
	{
		//if ( is_plugin_active('woocommerce/woocommerce.php') && !is_admin() ) {
		$this->sync_hook_customer( $user_id );
		//}
	}

	/**
	 * Central dispatcher for external-plugin registrations that bypass wp_insert_user.
	 *
	 * Accepts a normalized $contact array with the following optional keys:
	 *   email      (string, required)
	 *   first_name (string)
	 *   last_name  (string)
	 *   phone      (string)  â€” also written to billing_phone user meta
	 *   city       (string)  â€” also written to billing_city user meta
	 *   tag        (string)  â€” extra tag appended to the default set (e.g. 'cf7-registration')
	 *
	 * @param array $contact Normalized contact data from the adapter.
	 * @return void
	 */
	function sync_external_registration( array $contact ) {
		$email = isset( $contact['email'] ) ? sanitize_email( $contact['email'] ) : '';
		if ( empty( $email ) ) {
			return;
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return;
		}

		// Skip if woocommerce_created_customer already synced this user in the same request.
		if ( did_action( 'woocommerce_created_customer' ) ) {
			return;
		}

		$endpoint_class = new Clientify_Endpoint();
		$site_name      = get_option( 'blogname' );
		$site_name      = empty( $site_name ) ? 'WordPress' : $site_name;
		$lang           = get_bloginfo( 'language' );

		$tags = array( 'woocommerce', $site_name );
		if ( ! empty( $contact['tag'] ) ) {
			$tags[] = sanitize_text_field( $contact['tag'] );
		}

		$data = array(
			'status'         => 'customer',
			'store_url'      => $endpoint_class->get_local_api_url(),
			'email'          => $email,
			'contact_source' => $site_name,
			'custom_fields'  => array(),
			'tags'           => $tags,
		);

		if ( ! empty( $contact['first_name'] ) ) {
			$data['first_name'] = sanitize_text_field( $contact['first_name'] );
		}
		if ( ! empty( $contact['last_name'] ) ) {
			$data['last_name'] = sanitize_text_field( $contact['last_name'] );
		}
		if ( ! empty( $lang ) ) {
			$data['custom_field'] = array(
				'field' => 'ecommerce_language',
				'value' => $lang,
			);
		}
		if ( ! empty( $contact['phone'] ) ) {
			$phone = sanitize_text_field( $contact['phone'] );
			$data['phones'] = array( array( 'phone' => $phone ) );
			update_user_meta( $user->ID, 'billing_phone', $phone );
		}
		if ( ! empty( $contact['city'] ) ) {
			$city = sanitize_text_field( $contact['city'] );
			$data['addresses'] = array( array( 'type' => 1, 'city' => $city ) );
			update_user_meta( $user->ID, 'billing_city', $city );
		}

		$api = new Clientify_Api();
		$api->post_contacts_async( $data );
	}

	// -------------------------------------------------------------------------
	// Adapters â€” one per external registration plugin
	// -------------------------------------------------------------------------

	/**
	 * Adapter: Contact Form 7 + ZUR (User Registration CF7).
	 * Hook: wpcf7_mail_sent (priority 99, after ZUR creates the user).
	 *
	 * Field map (CF7 field name â†’ contact key):
	 *   your-email     â†’ email
	 *   your-name      â†’ first_name
	 *   your-lastname  â†’ last_name
	 *   your-telephone â†’ phone
	 *   poblacion      â†’ city
	 *
	 * @param WPCF7_ContactForm $contact_form
	 */
	function sync_cf7_registration( $contact_form ) {
		static $fired = false;
		if ( $fired ) {
			return;
		}
		$fired = true;

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();

		// Supports both your-lastname and your-surname (form-dependent).
		$last_name = '';
		foreach ( array( 'your-lastname', 'your-surname' ) as $key ) {
			if ( ! empty( $posted[ $key ] ) ) {
				$last_name = $posted[ $key ];
				break;
			}
		}

		$this->sync_external_registration( array(
			'email'      => isset( $posted['your-email'] )     ? $posted['your-email']     : '',
			'first_name' => isset( $posted['your-name'] )      ? $posted['your-name']      : '',
			'last_name'  => $last_name,
			'phone'      => isset( $posted['your-telephone'] ) ? $posted['your-telephone'] : '',
			'city'       => isset( $posted['poblacion'] )      ? $posted['poblacion']      : '',
			'tag'        => 'cf7-registration',
		) );
	}

	/**
	 * Adapter: WPForms.
	 * Hook: wpforms_process_complete (priority 20).
	 *
	 * Requires manual field ID mapping in $field_map below.
	 * Find IDs in WPForms â†’ form editor â†’ field options.
	 *
	 * @param array $fields     Processed field data keyed by field ID.
	 * @param array $entry      Raw submitted entry.
	 * @param array $form_data  Form configuration.
	 * @param int   $entry_id   Saved entry ID.
	 */
	function sync_wpforms_registration( $fields, $entry, $form_data, $entry_id ) {
		// Map WPForms field IDs to contact keys. Adjust IDs to match your form.
		$field_map = array(
			'email'      => 1,   // field ID for email
			'first_name' => 2,   // field ID for first name
			'last_name'  => 3,   // field ID for last name
			'phone'      => 4,   // field ID for telephone
			'city'       => 5,   // field ID for city/poblacion
		);

		$get = function( $key ) use ( $fields, $field_map ) {
			$id = isset( $field_map[ $key ] ) ? $field_map[ $key ] : null;
			return ( $id && isset( $fields[ $id ]['value'] ) ) ? $fields[ $id ]['value'] : '';
		};

		$this->sync_external_registration( array(
			'email'      => $get( 'email' ),
			'first_name' => $get( 'first_name' ),
			'last_name'  => $get( 'last_name' ),
			'phone'      => $get( 'phone' ),
			'city'       => $get( 'city' ),
			'tag'        => 'wpforms-registration',
		) );
	}

	/**
	 * Adapter: Gravity Forms.
	 * Hook: gform_after_submission (priority 20).
	 *
	 * Requires manual field ID mapping in $field_map below.
	 * Find IDs in Gravity Forms â†’ form editor â†’ field settings.
	 *
	 * @param array $entry     Submitted entry data keyed by field ID (string).
	 * @param array $form      Form configuration.
	 */
	function sync_gravityforms_registration( $entry, $form ) {
		// Map Gravity Forms field IDs (as strings) to contact keys. Adjust to your form.
		$field_map = array(
			'email'      => '1',
			'first_name' => '2',
			'last_name'  => '3',
			'phone'      => '4',
			'city'       => '5',
		);

		$get = function( $key ) use ( $entry, $field_map ) {
			$id = isset( $field_map[ $key ] ) ? $field_map[ $key ] : null;
			return ( $id && isset( $entry[ $id ] ) ) ? $entry[ $id ] : '';
		};

		$this->sync_external_registration( array(
			'email'      => $get( 'email' ),
			'first_name' => $get( 'first_name' ),
			'last_name'  => $get( 'last_name' ),
			'phone'      => $get( 'phone' ),
			'city'       => $get( 'city' ),
			'tag'        => 'gravityforms-registration',
		) );
	}

	/**
	 * Adapter: Ultimate Member (UM).
	 * Hook: um_registration_complete (priority 20).
	 * UM does call wp_insert_user internally, but fires this hook with extra
	 * custom fields that user_register doesn't carry â€” useful for phone/city.
	 *
	 * @param int   $user_id
	 * @param array $args    Submitted form data.
	 */
	function sync_um_registration( $user_id, $args ) {
		$user = get_userdata( $user_id );

		$this->sync_external_registration( array(
			'email'      => $user ? $user->user_email : '',
			'first_name' => isset( $args['first_name'] ) ? $args['first_name'] : '',
			'last_name'  => isset( $args['last_name'] )  ? $args['last_name']  : '',
			'phone'      => isset( $args['phone'] )      ? $args['phone']      : '',
			'city'       => isset( $args['city'] )       ? $args['city']       : '',
			'tag'        => 'um-registration',
		) );
	}

	/**
	 * Send customer information to the clientify api. Revised.
	 *
	 * @since    1.1.0
	 * @param    int                  $user_id    The custommer's id number. 
	 */
	function sync_hook_customer( $user_id )
	{
		
		global $wpdb;
		global $woocommerce;
		$user = get_userdata( $user_id );
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
				'email'          => $customer->get_email(),
				'contact_source' => get_option('blogname'),
				// 'gdpr_accept' 	 => ($suscripcion == 0) ? false : true,
				'custom_fields'  => [],
				'tags'           => array(
										'woocommerce',
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
			$content_comm = get_user_meta($user_id, 'content_comm', true);

			if (!empty($content_comm) && ($content_comm === "yes")) {
				$data['gdpr_accept'] = "accept";
			}
			// Read consent from external GDPR plugin (cookie-based) if active
			$external_plugin  = self::detect_external_gdpr_plugin();
			$external_consent = self::get_consent_from_external_plugin( $external_plugin );
			if ( $external_consent !== null ) {
				$data['gdpr_accept'] = $external_consent;
			}

			// Read consent from newsletter plugin user meta (MailChimp, Klaviyo, Brevo, etc.)
			$nl_plugin = self::detect_newsletter_plugin();
			if ( $nl_plugin && ! empty( $nl_plugin['user_meta'] ) ) {
				$accept_vals  = ! empty( $nl_plugin['accept_vals'] ) ? $nl_plugin['accept_vals'] : array( '1', 'yes', 'true' );
				$nl_subscribed = get_user_meta( $user_id, $nl_plugin['user_meta'], true );
				if ( $nl_subscribed !== '' && $nl_subscribed !== false ) {
					$data['gdpr_accept'] = in_array( (string) $nl_subscribed, $accept_vals, true ) ? 'accept' : 'revoke';
				}
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
				//} elseif ( $woocommerce->customer->get_address() ) {
			}elseif ($woocommerce->customer != null) {
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

			$clientify_vk = isset($_COOKIE['vk']) ? sanitize_text_field($_COOKIE['cookie_cart_id']) : '';

			if ( isset($clientify_vk) && $clientify_vk ) {
				$data['visitor_key'] = (string)$clientify_vk;
			}
		} //end if user_id	
		//Send to api
		$api = new Clientify_Api;
		$contact = $api->post_contacts_clientify( $data );

		return $contact;
	}

	function agregar_campo_suscripcion() {
		if ( self::detect_external_gdpr_plugin() || self::detect_newsletter_plugin() ) {
			return;
		}
		woocommerce_form_field('suscripcion_newsletter', array(
			'type' => 'checkbox',
			'class' => array('form-row-wide'),
			'label' => __(get_option('CLIENTIFY_GDPR_TEXT')),
		));
	}

	function agregar_campo_suscripcion_en_checkout($checkout) {
		if ( self::detect_external_gdpr_plugin() || self::detect_newsletter_plugin() ) {
			return;
		}
		woocommerce_form_field('suscripcion_newsletter', array(
			'type' => 'checkbox',
			'class' => array('form-row-wide'),
			'label' => __(get_option('CLIENTIFY_GDPR_TEXT')),
		), $checkout->get_value('suscripcion_newsletter'));
	}
	function guardar_suscripcion($customer_id) {
		// $suscripcion = isset($_POST['suscripcion_newsletter']) ? '1' : '0';
		// update_user_meta($customer_id, 'suscripcion_newsletter', $suscripcion);
		
		if (isset($_POST['suscripcion_newsletter']) && $_POST['suscripcion_newsletter'] == "1") {
			$suscripcion = 'accept';
			$res = update_user_meta($customer_id, 'suscripcion_newsletter', $suscripcion);
		}

	}
	function guardar_campo_suscripcion_checkout() {
		$user_id = get_current_user_id();

		if ( isset( $_POST['suscripcion_newsletter'] ) ) {
			$suscripcion = 'accept';
		} elseif ( isset( $_POST['mailchimp_woocommerce_newsletter'] ) ) {
			$suscripcion = ( $_POST['mailchimp_woocommerce_newsletter'] == '1' ) ? 'accept' : 'revoke';
		} else {
			$suscripcion = 'revoke';
		}

		if ( $user_id ) {
			update_user_meta( $user_id, 'suscripcion_newsletter', $suscripcion );
		}
	}

	function agregar_checkbox_despues_privacidad() {
		if ( self::detect_external_gdpr_plugin() || self::detect_newsletter_plugin() ) {
			return;
		}
    ?>
    <div class="form-row additional-terms">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
            <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="suscripcion_newsletter" id="suscripcion_newsletter" /> <span><?php _e(get_option('CLIENTIFY_GDPR_TEXT'), 'woocommerce'); ?></span>
        </label>
    </div>
    <?php
}

	static function detect_newsletter_plugin() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$network_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
			$active_plugins  = array_merge( $active_plugins, $network_plugins );
		}
		$known = array(
			array(
				'file'        => 'mailchimp-for-woocommerce/mailchimp-woocommerce.php',
				'name'        => 'MailChimp for WooCommerce',
				'author'      => 'Mailchimp',
				'type'        => 'mailchimp',
				'post_field'  => 'mailchimp_woocommerce_newsletter',
				'user_meta'   => 'mailchimp_woocommerce_is_subscribed',
				'order_meta'  => array( 'mailchimp_woocommerce_is_subscribed', '_mailchimp_woocommerce_is_subscribed', 'mailchimp_newsletter' ),
				'accept_vals' => array( '1', 'yes', 'true' ),
				'supported'   => true,
			),
			array(
				'file'        => 'klaviyo-for-woocommerce/klaviyo.php',
				'name'        => 'Klaviyo',
				'author'      => 'Klaviyo',
				'type'        => 'klaviyo',
				'post_field'  => 'klaviyo_subscribed',
				'user_meta'   => '_klaviyo_subscribed',
				'order_meta'  => array( '_klaviyo_subscribed', 'klaviyo_subscribed' ),
				'accept_vals' => array( '1', 'yes', 'true' ),
				'supported'   => true,
			),
			array(
				'file'        => 'woocommerce-sendinblue-newsletter-subscription/sendinblue-woocommerce.php',
				'name'        => 'Brevo (Sendinblue)',
				'author'      => 'Sendinblue',
				'type'        => 'brevo',
				'post_field'  => 'sib_woo_subscription',
				'user_meta'   => 'sib_woo_subscription',
				'order_meta'  => array( 'sib_woo_subscription', '_sib_woo_subscription' ),
				'accept_vals' => array( '1', 'yes', 'true' ),
				'supported'   => true,
			),
			array(
				'file'        => 'activecampaign-for-woocommerce/activecampaign-for-woocommerce.php',
				'name'        => 'ActiveCampaign for WooCommerce',
				'author'      => 'ActiveCampaign',
				'type'        => 'activecampaign',
				'post_field'  => 'activecampaign_optin',
				'user_meta'   => 'activecampaign_optin',
				'order_meta'  => array( 'activecampaign_optin', '_activecampaign_optin' ),
				'accept_vals' => array( '1', 'yes', 'true' ),
				'supported'   => true,
			),
			array(
				'file'        => 'leadin/leadin.php',
				'name'        => 'HubSpot for WooCommerce',
				'author'      => 'HubSpot',
				'type'        => 'hubspot',
				'post_field'  => 'hs_woo_newsletter',
				'user_meta'   => 'hs_woo_newsletter',
				'order_meta'  => array( 'hs_woo_newsletter', '_hs_woo_newsletter' ),
				'accept_vals' => array( '1', 'yes', 'true' ),
				'supported'   => true,
			),
		);
		foreach ( $known as $plugin ) {
			if ( in_array( $plugin['file'], $active_plugins, true ) ) {
				return $plugin;
			}
		}
		return null;
	}

	static function get_newsletter_order_consent( $order_id, $plugin ) {
		global $wpdb;

		if ( empty( $plugin['order_meta'] ) ) {
			return null;
		}

		$meta_keys   = $plugin['order_meta'];
		$accept_vals = ! empty( $plugin['accept_vals'] ) ? $plugin['accept_vals'] : array( '1', 'yes', 'true' );

		$resolve = function( $val ) use ( $accept_vals ) {
			if ( $val === '' || $val === null || $val === false ) {
				return null;
			}
			return in_array( (string) $val, $accept_vals, true ) ? 'accept' : 'revoke';
		};

		// 1. WC Order API (transparent for both HPOS and legacy)
		$order = wc_get_order( $order_id );
		if ( $order ) {
			foreach ( $meta_keys as $key ) {
				$result = $resolve( $order->get_meta( $key ) );
				if ( $result !== null ) return $result;
			}
		}

		// 2. Direct HPOS table query
		$hpos_table = $wpdb->prefix . 'wc_orders_meta';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$hpos_table}'" ) === $hpos_table ) {
			foreach ( $meta_keys as $key ) {
				$val    = $wpdb->get_var( $wpdb->prepare(
					"SELECT meta_value FROM {$hpos_table} WHERE order_id = %d AND meta_key = %s LIMIT 1",
					$order_id, $key
				) );
				$result = $resolve( $val );
				if ( $result !== null ) return $result;
			}
		}

		// 3. Legacy postmeta
		foreach ( $meta_keys as $key ) {
			$result = $resolve( get_post_meta( $order_id, $key, true ) );
			if ( $result !== null ) return $result;
		}

		return null;
	}

	static function detect_external_gdpr_plugin() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$network_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
			$active_plugins  = array_merge( $active_plugins, $network_plugins );
		}

		$known = array(
			array(
				'file'      => 'gdpr-cookie-compliance/gdpr-cookie-compliance.php',
				'name'      => 'GDPR Cookie Compliance',
				'author'    => 'Moove Agency',
				'type'      => 'moove',
				'cookie'    => 'moove_gdpr_popup',
				'supported' => true,
			),
			array(
				'file'      => 'cookieyes-legalmonster/cookieyes.php',
				'name'      => 'CookieYes',
				'author'    => 'CookieYes',
				'type'      => 'cookieyes',
				'cookie'    => 'cookieyes-consent',
				'supported' => true,
			),
			array(
				'file'      => 'complianz-gdpr/complianz-gdpr.php',
				'name'      => 'Complianz',
				'author'    => 'Really Simple Plugins',
				'type'      => 'complianz',
				'cookie'    => 'cmplz_consent',
				'supported' => true,
			),
			array(
				'file'      => 'cookie-notice/cookie-notice.php',
				'name'      => 'Cookie Notice & Compliance',
				'author'    => 'dFactory',
				'type'      => 'cookie_notice',
				'cookie'    => 'cookie_notice_accepted',
				'supported' => true,
			),
			array(
				'file'      => 'gdpr-cookie-consent/gdpr-cookie-consent.php',
				'name'      => 'Cookie Law Info',
				'author'    => 'WebToffee',
				'type'      => 'webtoffee',
				'cookie'    => 'cookielawinfo-checkbox-marketing',
				'supported' => true,
			),
		);

		foreach ( $known as $plugin ) {
			if ( in_array( $plugin['file'], $active_plugins, true ) ) {
				return $plugin;
			}
		}
		return null;
	}

	static function get_consent_from_external_plugin( $plugin ) {
		if ( ! $plugin || empty( $plugin['cookie'] ) ) {
			return null;
		}

		$cookie_name = $plugin['cookie'];
		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			return null;
		}

		$raw = stripslashes( $_COOKIE[ $cookie_name ] );

		switch ( $plugin['type'] ) {
			case 'moove':
				$data = json_decode( $raw, true );
				if ( ! is_array( $data ) ) return null;
				$accepted = ( ! empty( $data['thirdparty'] ) && $data['thirdparty'] == 1 )
				         || ( ! empty( $data['advanced'] )    && $data['advanced']    == 1 )
				         || ( ! empty( $data['marketing'] )   && $data['marketing']   == 1 );
				return $accepted ? 'accept' : 'revoke';

			case 'cookieyes':
				// Format: "consentid:xxx,consent:yes,action:yes,analytics:yes,marketing:yes"
				$parts = array();
				foreach ( explode( ',', $raw ) as $pair ) {
					$kv = explode( ':', $pair, 2 );
					if ( count( $kv ) === 2 ) {
						$parts[ trim( $kv[0] ) ] = trim( $kv[1] );
					}
				}
				$accepted = ( isset( $parts['marketing'] ) && $parts['marketing'] === 'yes' )
				         || ( isset( $parts['consent']   ) && $parts['consent']   === 'yes' );
				return $accepted ? 'accept' : 'revoke';

			case 'complianz':
				$data = json_decode( $raw, true );
				if ( ! is_array( $data ) ) return null;
				$accepted = ! empty( $data['marketing'] ) && $data['marketing'] == 1;
				return $accepted ? 'accept' : 'revoke';

			case 'cookie_notice':
				return ( $raw === 'true' || $raw === '1' ) ? 'accept' : 'revoke';

			case 'webtoffee':
				return ( $raw === 'yes' ) ? 'accept' : 'revoke';
		}

		return null;
	}

	function register_block_checkout_gdpr_field() {
		if ( self::detect_external_gdpr_plugin() || self::detect_newsletter_plugin() ) {
			return;
		}
		// WC 8.9+: native additional checkout fields (handles UI automatically)
		if ( function_exists( 'woocommerce_register_additional_checkout_fields' ) ) {
			woocommerce_register_additional_checkout_fields( array(
				'id'       => 'clientify-addons/gdpr_consent',
				'label'    => get_option( 'CLIENTIFY_GDPR_TEXT', __( 'Acepto recibir comunicaciones comerciales GDPR', 'clientify-addons' ) ),
				'location' => 'order',
				'type'     => 'checkbox',
				'required' => false,
			) );
			return;
		}

		// WC 6.5–8.8: register Store API extension schema so JS can send data
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data( array(
				'endpoint'        => 'checkout',
				'namespace'       => 'clientify-addons',
				'schema_callback' => function () {
					return array(
						'gdpr_consent' => array(
							'description' => __( 'GDPR consent', 'clientify-addons' ),
							'type'        => array( 'boolean', 'null' ),
							'context'     => array( 'view', 'edit' ),
							'readonly'    => false,
						),
					);
				},
				'schema_type'     => ARRAY_A,
			) );
		}
	}

	function enqueue_block_checkout_gdpr_script() {
		if ( self::detect_external_gdpr_plugin() || self::detect_newsletter_plugin() ) {
			return;
		}
		if ( ! is_checkout() ) {
			return;
		}
		// Only needed when NOT using the WC 8.9+ native additional fields API
		if ( function_exists( 'woocommerce_register_additional_checkout_fields' ) ) {
			return;
		}
		wp_enqueue_script(
			'clientify-gdpr-blocks',
			plugins_url( '../public/js/clientify-gdpr-blocks.js', __FILE__ ),
			array( 'wp-data' ),
			CLIENTIFY_ADDONS_VERSION,
			true
		);
		wp_localize_script( 'clientify-gdpr-blocks', 'clientify_gdpr_params', array(
			'gdpr_text'       => get_option( 'CLIENTIFY_GDPR_TEXT', __( 'Acepto recibir comunicaciones comerciales GDPR', 'clientify-addons' ) ),
			'external_plugin' => self::detect_external_gdpr_plugin(),
		) );
	}

	function save_block_checkout_gdpr( $order, $request ) {
		$user_id    = $order->get_customer_id();
		$gdpr_value = null;

		if ( function_exists( 'woocommerce_get_checkout_field_value_from_request' ) ) {
			$gdpr_value = woocommerce_get_checkout_field_value_from_request( $request, 'clientify-addons/gdpr_consent' );
		}

		if ( $gdpr_value === null ) {
			$extensions = $request->get_param( 'extensions' );
			if ( isset( $extensions['clientify-addons']['gdpr_consent'] ) ) {
				$gdpr_value = (bool) $extensions['clientify-addons']['gdpr_consent'];
			}
		}

		if ( $gdpr_value === null ) {
			return;
		}

		$suscripcion = $gdpr_value ? 'accept' : 'revoke';

		if ( $user_id ) {
			update_user_meta( $user_id, 'suscripcion_newsletter', $suscripcion );
		}

		$order->update_meta_data( '_clientify_gdpr_accept', $suscripcion );
		$order->save();
	}

// Función para mostrar la sección de gestión de suscripciones
function mostrar_seccion_gestion_suscripcion() {
    // Verifica si el usuario está autenticado
    if (is_user_logged_in()) {
        // Contenido de la sección de gestión de suscripciones
        $contenido_suscripcion = '<h2>Suscripción GDPR</h2>';
        $contenido_suscripcion .= '<form method="post" action="" id="formulario_suscripcion">';

        $user_id = get_current_user_id();
        $suscripcion = get_user_meta($user_id, 'suscripcion_newsletter', true);

        $contenido_suscripcion .= '<p class="form-row form-row-wide">';
        $contenido_suscripcion .= '<input type="hidden" id="contact_clienti_id" name="contact_clienti_id" value="'.$user_id.'" />';
        $contenido_suscripcion .= '<input type="checkbox" id="suscripcion_newsletter" name="suscripcion_newsletter" value="1" ' . checked('accept', $suscripcion, false) . ' />';
        $contenido_suscripcion .= '<label for="suscripcion_newsletter">' . get_option('CLIENTIFY_GDPR_TEXT') . '</label>';
        $contenido_suscripcion .= '</p>';

        $contenido_suscripcion .= '<p><button type="button"  class="button woocommerce" id="suscripcion_save">Guardar cambios</button></p>';
        $contenido_suscripcion .= '</form>';
        echo json_encode(array('contenido' => $contenido_suscripcion));
        wp_die();
    }
}


// En tu archivo functions.php o un archivo separado
function guardar_suscripcion_contact_info() {


	if (isset($_POST['id_clienti_cus'])) {
        $user_id = intval($_POST['id_clienti_cus']);
        if (isset($_POST['status_clienti_newsletter'])) {
            $status = $_POST['status_clienti_newsletter'];

            // Convertimos 1 a 'accept' y 0 a 'revoke'
            $suscripcion = ($status == '1') ? 'accept' : 'revoke';
        } else {
            // Valor predeterminado si no se proporciona el status
            $suscripcion = 'revoke';
        }

        $response = update_user_meta($user_id, 'suscripcion_newsletter', $suscripcion);
		$get_meta = get_user_meta($user_id, 'suscripcion_newsletter');

        // Aquí podrías ejecutar cualquier otra acción necesaria, como agregar al cliente
        $this->customer_add($user_id);

        echo json_encode(array('success' => true,'response'=>$response, 'data'
	=>$get_meta)); // Respondemos con éxito si llegamos aquí
    } 
    wp_die();
}

function agregar_opcion_suscripcion($menu_items) {
    $menu_items['suscripcion'] = __('GDPR', 'woocommerce');
    return $menu_items;
}

	/**
	 * search and send product data to the clientify api. Revised.
	 *
	 * @since    1.1.0
	 * @param    int                  $prodcut_id    The product's id number.
	 */
	function product_published($product_id){
		$clientify_product = null;

		$endpoint_class = new Clientify_Endpoint();
		$product = wc_get_product( $product_id );
		$url_base = $endpoint_class->get_local_api_url();
		$categories = array();
        $subcategories= array();
		$join_categories = "";
        $join_subcategories = "";
		$terms = get_the_terms($product_id, 'product_cat');
		
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
		// $join_cat = ($join_categories != "" && $join_subcategories != "") ? $join_categories . "/" . $join_subcategories : $join_categories . $join_subcategories;  
		try {
			$sku = $product->get_sku();
		} catch (Exception $e) {
			$sku = '';
		}
		$image_id  = $product->get_image_id();
		$image_url = wp_get_attachment_image_url($image_id, 'full');
		$product_instance = wc_get_product($product_id);
		$product_full_description = $product_instance->get_description();
		$display_price_with_tax_shop = get_option('woocommerce_tax_display_shop'); // 'incl' or 'excl'
		$display_price_with_tax_cart = get_option('woocommerce_tax_display_cart'); // 'incl' or 'excl'

		if ($product->get_type() == "variable") {
            foreach ($product->get_available_variations() as $variations) {
						$variation_id=$variations['variation_id'];
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

						if ($display_price_with_tax_shop  === 'incl') {
							$price = wc_get_price_including_tax($variable_product);
						} else {
							$price = wc_get_price_excluding_tax($variable_product);
						}
                        
                        // if ( $variable_product->is_on_sale() ) {
                        //     $price = $variable_product->get_sale_price();
                        // } else {
                        //     $price = $variable_product->get_regular_price();
                        // }

						
                        $description = trim(strip_tags($variable_product->description));

                        if ($description == "") {
                            $description = trim(strip_tags($product->description));
                        }

						try {
                            $sku = $variable_product->sku;
                        } catch (Exception $e) {
                            $sku = '';
                        }

	
                        $item = array(
							'status'      => 'product',
                            'id'          => $variation_id,
                            'name'        => $full_variation_name,
                            'description' => $description,
                            'category'    => $join_cat,
                            'sku'         => $sku,
                            'product_picture_url'   => $image_url,
                            'item_url'    => get_permalink($variable_product->id),
                            'price'       => $price == '' ||   $price == NULL ? 0 : number_format($price, 2, '.', ''),
                            'currency'    => get_woocommerce_currency(),
							'store_url'   => $url_base
                            
                        );

					$api = new Clientify_Api;
					$clientify_product = $api->post_product_clientify( $item );
            	
            }
        }
        else {

			if ($display_price_with_tax_shop === 'incl') {
				// Obtener precio con impuestos
				$price = wc_get_price_including_tax($product);
			} else {
				// Obtener precio sin impuestos
				$price = wc_get_price_excluding_tax($product);
			}

			// if ($product->is_on_sale()) {
			// 	$price = $product->get_sale_price();
			// } else {
			// 	$price = $product->get_regular_price();
			// }

			if (empty($image_url)) {
				$attachment_ids = $product->get_gallery_image_ids();
				if (!empty($attachment_ids)) {
					$first_image_id = reset($attachment_ids);
					$image_url = wp_get_attachment_url($first_image_id);
				}
			}

		$item = array(
			'status'              => 'product',
			'id'                  => $product_id,
			'name'                => $product->get_name(),
			'description'         => $product_full_description,
			'price' 			  => $price == 0 ? 0 : number_format((float)$price, 2, '.', ''),
			'item_url'            => get_permalink($product_id),
			'currency'            => get_option('woocommerce_currency'),
			'category'            => $join_cat,
			'sku'                 => $sku,
			'product_picture_url' => $image_url,
			'store_url'           => $url_base
		);

		$api = new Clientify_Api;
		$clientify_product = $api->post_product_clientify( $item );
		
	}

		return $clientify_product;
	}
	/**
	 * Collects and sorts data from an order by executing hook woocommerce_order_status_changed or 
	 * woocommerce_thankyou, and sends to clientify. Revised.
	 *
	 * @since    2.0.0
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
		$order_status_settings = get_option('CLIENTIFY_ORDER_STATUS');

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
				$order_tags = array();
				
				foreach ( $products as $order_product ) {

					$categories = array();
					$subcategories= array();
					
					$terms = get_the_terms( $order_product['product_id'], 'product_cat' );

					$join_categories = "";
                    $join_subcategories = "";
					if (!empty($terms)) {
						foreach ($terms as $term) {
							if ($term->parent == 0) {
								// CategorÃ­a principal
								if (!in_array($term->term_id, $categories)) {
									$categories[] = $term->term_id;
									$join_categories .= ($join_categories == "" ? "" : ",") . $term->term_id . ":" . $term->slug;
								}
							} else {
								// SubcategorÃ­a
								if (!in_array($term->term_id, $subcategories)) {
									$subcategories[] = $term->term_id;
									$parent_id = $term->parent;
									
									// Asegurarse de que la categorÃ­a principal estÃ© aÃ±adida
									if (!in_array($parent_id, $categories)) {
										$parent_term = get_term($parent_id, 'product_cat');
										$categories[] = $parent_id;
										$join_categories .= ($join_categories == "" ? "" : ",") . $parent_id . ":" . $parent_term->slug;
									}
						
									// Construir la cadena de subcategorÃ­as
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
					
					// Obtener el ID de la variaciÃ³n
					$variation_id = $order_product->get_variation_id();

					if ($variation_id != 0) {

						$variable_product = new WC_Product_Variation($variation_id);
						$image_id = $variable_product->image_id;
						$image_url = wp_get_attachment_url($image_id);

						if ($product->get_title() == $variable_product->get_name()) {

							$attributes = $variable_product->attribute_summary;

							// Dividir la cadena en partes utilizando ":"
							$partes = explode(",", $attributes);
							// Inicializar un array para almacenar las partes relevantes
							$array_parts = [];

							// Iterar sobre cada parte y guardar solo las que contienen ":"
							foreach ($partes as $parte) {
								if (strpos($parte, ":") !== false) {
									// Si la parte contiene ":", agregarla a las partes relevantes
									$array_parts[] = trim(explode(":", $parte)[1]); // Tomar solo lo que estÃ¡ despuÃ©s de ":"
								}
							}
							$attributes_name = implode(", ", $array_parts);

							// Obtener el nombre del producto de la variaciÃ³n
							$product_name = $variable_product->get_name();

							if (empty($attributes_name)) {
								$full_variation_name = $product_name;
							} else {
								$full_variation_name = $product_name . ' - ' . $attributes_name;
							}
						} else {
							$full_variation_name = $variable_product->get_name();
						}


						$price = $variable_product->regular_price;

						$description = trim(strip_tags($variable_product->description));

						if ($description == "") {
							$description = trim(strip_tags($product->description));
						}
						

						$items[] = array(
							'name' => $full_variation_name,
							'id' => $variation_id,
							'description' => $description,
							'category' => $join_cat,
							'sku' => $variable_product->sku,
							'image_url' => $image_url,
							'item_url' => get_permalink($variable_product->id),
							'price' => $price == '' || $price == NULL ? 0 : number_format($price, 2, '.', ''),
							'quantity' => $quantity,
							'discount' => $discount != 0 ? number_format(round($discount), 1, '.', ',') : 0, //$discount

						);

					} else {
						$items[] = array(
							'name' => $order_product->get_name(),
							'id' => $product_id,
							'description' => $product_full_description,
							'category' => $join_cat,
							'sku' => $sku,
							'image_url' => $image_url,
							'item_url' => get_permalink($order_product['product_id']),
							'price' => number_format($price, 2, '.', ''),
							'quantity' => $quantity,
							'discount' => $discount != 0 ? number_format(round($discount), 1, '.', ',') : 0, //$discount
						);

					}

				}

				$contact = 0;

				if ($id_customer){
					$contact = $this->get_contact($id_customer);
					if ($contact && get_option('CLIENTIFY_GDPR') == "1") {
						$suscripcion = get_user_meta($id_customer, 'suscripcion_newsletter', true);
						if ($suscripcion === "accept" || $suscripcion === "revoke") {
							$contact['gdpr_accept'] = $suscripcion;
						}
					}
					if ($contact) {
						$content_comm = get_user_meta($id_customer, 'content_comm', true);
						if (!empty($content_comm) && $content_comm === "yes") {
							$contact['gdpr_accept'] = "accept";
						}
					}
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

				// Email siempre desde facturaciÃ³n de la orden al enviar a Clientify
				if ( is_array($contact) && !empty($order->get_billing_email()) ) {
					$contact['email'] = $order->get_billing_email();
				}

				// GDPR para contactos guest: leer del order meta guardado por block checkout
				if ( is_array($contact) && !$id_customer ) {
					$order_gdpr = $order->get_meta( '_clientify_gdpr_accept' );
					if ( ! empty( $order_gdpr ) ) {
						$contact['gdpr_accept'] = $order_gdpr;
					}
				}

				// Read from external GDPR plugin cookie (applies to both registered and guest)
				if ( is_array( $contact ) ) {
					$external_plugin  = self::detect_external_gdpr_plugin();
					$external_consent = self::get_consent_from_external_plugin( $external_plugin );
					if ( $external_consent !== null ) {
						$contact['gdpr_accept'] = $external_consent;
					}
				}

				// Read consent from newsletter plugin order meta (MailChimp, Klaviyo, Brevo, etc.)
				if ( is_array( $contact ) ) {
					$nl_plugin = self::detect_newsletter_plugin();
					if ( $nl_plugin ) {
						$nl_consent = self::get_newsletter_order_consent( $order->get_id(), $nl_plugin );
						if ( $nl_consent !== null ) {
							$contact['gdpr_accept'] = $nl_consent;
						}
					}
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

					'contact'    => $contact,
					'status'     => 'ordered',
					'order_date' => $order_data['date_created']->date('Y-m-d H:i:s'),
					'order_id'   => $order->get_id(),
					'ecommerce'  => 'woocommerce',
					'shop_name'  => get_option('blogname'),
					'order_url'  => $order->get_view_order_url(),
					'store_url'  => $url_base,
					'currency'   => $currency,
					'products'   => $items,
					'shipping' 	 => $shipping,
					'price'	     =>  number_format($total_price, 2, '.', ''),
					'coupon'     => 0,
					'order_tags' => $order_tags
				);

				if ($coupons) {
					foreach ($coupons as $coupon_code) {
						$coupons_tags[] = $coupon_code;
					}
					$data['coupon_tags'] = $coupons_tags;
				}
				if (!empty($lang)) {
					$data['custom_field'] = array(
							'field' => 'ecommerce_language',
							'value' => $lang,
						);
				}
				
				if($contact && !empty($items)){
					$api = new Clientify_Api;
					$clientify_order = $api->post_order_clientify($data);

					return $clientify_order;
				}
			}
		//endforeach;
	}
		/**
	 * Insert script for analitycs of clientify in the wp_footer hook. Revised.
	 *
	 * @since    1.1.0
	 */
	function clientify_api_script(){

		$bottom_script = get_option('CLIENTIFY_SCRIPT');
		if ( !empty($bottom_script) ) {
			printf("<script src='%s'></script>",esc_attr($bottom_script));
		}
	}
	/**
	 * Insert product id carts table to track abandoned carts. DEPRECATED
	 *
	 * @since    1.1.0
	 * @param    int                  $prodcut_id    The product id number.
	 */
	function clientify_save_add_to_cart($cart_item_key, $product_id)
	{
		/*global $wpdb;
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
		}*/
	}
	/**
	 * Update Carts table according to id_cart. DEPRECATED
	 * 
	 * @since    1.1.0
	 * @param 	 int					$cart_update ID cart in table cart
	 */
	function clientify_cart_updated($cart_updated)
	{
		/*if ( $cart_updated ) {
			$this->clientify_save_add_to_cart(null, null);
		}*/
	}
	/**
	 * Save session_id to order when order is being created (BEFORE saved).
	 * This is critical for payment gateways like Redsys that notify via server-to-server POST,
	 * where there's no user session available when the payment is completed.
	 *
	 * Hook: woocommerce_checkout_create_order (ANTES de guardar la orden)
	 * Este hook recibe el objeto $order y $data
	 *
	 * @since    1.1.0
	 * @param    WC_Order             $order       The order object (not saved yet).
	 * @param    array                $data        Checkout form data.
	 *
	 */
	function save_session_id_to_order($order, $data) {
		// Verificar que WC()->session estÃ© disponible
		if (WC()->session) {
			$wcf_session_id = WC()->session->get( 'wcf_session_id' );
			
			// Si existe un session_id, guardarlo en los metadatos de la orden
			if ($wcf_session_id) {
				// No necesitamos $order->save() aquÃ­ porque la orden se guarda automÃ¡ticamente despuÃ©s
				$order->update_meta_data( '_wcf_session_id', $wcf_session_id );
			}
		}
	}

	/**
	 * Save session_id to order for WooCommerce Blocks checkout.
	 * Similar to save_session_id_to_order but for the Blocks checkout flow.
	 *
	 * Hook: woocommerce_store_api_checkout_order_processed
	 *
	 * @since    1.1.0
	 * @param    WC_Order             $order       The order object.
	 *
	 */
	function save_session_id_to_order_blocks($order) {
		// Verificar que WC()->session estÃ© disponible
		if (WC()->session) {
			$wcf_session_id = WC()->session->get( 'wcf_session_id' );
			
			// Si existe un session_id, guardarlo en los metadatos de la orden
			if ($wcf_session_id) {
				$order->update_meta_data( '_wcf_session_id', $wcf_session_id );
				$order->save(); // En Blocks sÃ­ necesitamos guardar explÃ­citamente
			}
		}
	}

	/**
	 * Delete cart if purchase is complete.
	 *
	 * @since    1.1.0
	 * @param    int                  $order_id    The order id number.

	 */
	function delete_cart($order_id) {
		$order = wc_get_order( $order_id );
		
		if (!$order) {
			return;
		}
		

		$wcf_session_id = $order->get_meta('_wcf_session_id');
		
	
		if (empty($wcf_session_id) && WC()->session) {
			$wcf_session_id = WC()->session->get( 'wcf_session_id' );
			
			if ($wcf_session_id) {
				$order->update_meta_data( '_wcf_session_id', $wcf_session_id );
				$order->save();
			}
		}
		
		if ($wcf_session_id) {
			global $wpdb;
			$cart_abandonment_table = $wpdb->prefix . 'clientify_ca_cart_abandonment';
			$wpdb->delete( $cart_abandonment_table, array( 'session_id' => $wcf_session_id ) ); // db call ok; no cache ok.
		}
	}

	/**
	 * Delete item from temporaly cart. DEPRECATED
	 *
	 * @since    1.1.0
	 * @param    int                  $order_id    The order id number.

	 */
	function delete_item_cart($cart_item)
	{
		/*global $wpdb;
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
		}*/
	}


	/**
	 * Collects abandoned carts and sends them to clientify. DEPRECATED
	 *
	 * @since    1.1.0
	 * @param    string                  $cookie_cart_id    The id number in tab abandoned_cart.
	 */
	function sync_hook_abandoned_cart_v2($cookie_cart_id)
	{
		/*	
		if(get_option('CLIENTIFY_STATUS') != 0){
			global $wpdb;
			$endpoint_class = new Clientify_Endpoint();
			$url_base = $endpoint_class->get_local_api_url();
			$helper = new Clientify_Helper;

			
			foreach ( $cookie_cart_id as $sesson_id ) {

				if ( $sesson_id ) {
					$details          = $helper->get_checkout_details( $sesson_id );
					$user_details     = (object) maybe_unserialize( $details->other_fields );
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
							'price'       => number_format($price, 2, '.', ','),
							'quantity'    => (int) $cart_item['quantity'],
							'discount'    => $discount,
						);
						
						
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
					'price'	         => number_format($details->cart_total, 2, '.', ','),
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

						
				
				
				$sql = 'SELECT wccca.id FROM ' . $wpdb->prefix . 'clientify_ca_cart_abandonment wccca WHERE session_id="'.$sesson_id.'"';
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
		}*/
	}

	/**
	 * Save cart abandonment tracking and schedule new event. Revised.
	 *
	 * @since 1.1.0
	 */
	function clientify_save_cart_abandonment_data() {
		
		check_ajax_referer( 'clientify_save_cart_abandonment_data', 'security' );
		$post_data = $this->sanitize_post_data( 'clientify_save_cart_abandonment_data' );

		if ( isset( $post_data['wcf_email'] ) ) {
			$user_email = sanitize_email( $post_data['wcf_email'] );

			global $wpdb;
			//WC()->session->set( 'wcf_session_id', '11122344477' );
			
			
            $cart_abandonment_table = $wpdb->prefix . 'clientify_ca_cart_abandonment';

			// Verify if email is already exists.
			$session_id               = WC()->session->get( 'wcf_session_id' );
			$session_checkout_details = null;

			
			if ( isset( $session_id ) ) {
				
				$helper = new Clientify_Helper;
				$session_checkout_details = $helper->get_checkout_details( $session_id );
			 	
			 } else {
			 	
			 	$session_checkout_details = $this->get_checkout_details_by_email( $user_email );
			 	if ( $session_checkout_details ) {
			 		$session_id = $session_checkout_details->session_id;
			 		WC()->session->set( 'wcf_session_id', $session_id );
			 	} else {
		 		$session_id = md5( uniqid( wp_rand(), true ) );
			 	}
			 }
			
			
			$checkout_details = $this->prepare_abandonment_data( $post_data );
			
			
			if ( isset( $session_checkout_details ) && 'completed' === $session_checkout_details->order_status ) {


				WC()->session->__unset( 'wcf_session_id' );
				$session_id = md5( uniqid( wp_rand(), true ) );
			}
			

			if ( isset( $checkout_details['cart_total'] ) && $checkout_details['cart_total'] > 0 ) {

			 	if ( ( ! is_null( $session_id ) ) && ! is_null( $session_checkout_details ) ) {

			 		// Updating row in the Database where users Session id = same as prevously saved in Session.
					$wpdb->update(
			 			$cart_abandonment_table,
						$checkout_details,
			 			array( 'session_id' => $session_id )
			 		); // db call ok; no cache ok.

			 	} else {

			 		$checkout_details['session_id'] = sanitize_text_field( $session_id );
			 		// Inserting row into Database.
			 		$wpdb->insert(
			 			$cart_abandonment_table,
			 			$checkout_details
			 		); // db call ok; no cache ok.

			 		// Storing session_id in WooCommerce session.
					WC()->session->set( 'wcf_session_id', $session_id );

			 	}
			 } else {
			 	$wpdb->delete( $cart_abandonment_table, array( 'session_id' => sanitize_key( $session_id ) ) ); // db call ok; no cache ok.
			 }

			 wp_send_json_success();
		}
	}

	 /* Get the checkout details for the user. Revised
	 *
	 * @param string $email user email.
	 * @since 1.1.0
	 */
	public function get_checkout_details_by_email( $email ) {
		global $wpdb;
		$cart_abandonment_table = $wpdb->prefix . 'clientify_ca_cart_abandonment';
		$result                 = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$cart_abandonment_table} WHERE email = %s AND `order_status` IN ( %s, %s )", $email, 'abandoned', 'normal' ) //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		); // db call ok; no cache ok.
		return $result;
	}

	/**
	 * Sanitize post array.
	 *
	 * @param string $action action name to verify nonce. Revised.
	 *
	 * @return array
	 */
	public function sanitize_post_data( $action ) {
		

		check_ajax_referer( $action, 'security' );

		$input_post_values = array(
			'wcf_billing_company'     => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_email'               => array(
				'default'  => '',
				'sanitize' => FILTER_SANITIZE_EMAIL,
			),
			'wcf_billing_address_1'   => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_billing_address_2'   => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_billing_state'       => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_billing_postcode'    => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_shipping_first_name' => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_shipping_last_name'  => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_shipping_company'    => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_shipping_country'    => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_shipping_address_1'  => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_shipping_address_2'  => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_shipping_city'       => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_shipping_state'      => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_shipping_postcode'   => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_order_comments'      => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_name'                => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_surname'             => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_phone'               => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_country'             => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_city'                => array(
				'default'  => '',
				'sanitize' => 'FILTER_SANITIZE_STRING',
			),
			'wcf_post_id'             => array(
				'default'  => 0,
				'sanitize' => FILTER_SANITIZE_NUMBER_INT,
			),
			// Add the new field for shipping cost
			'shipping_cost' => array(
				'default' => 0.0,
				'sanitize' => FILTER_SANITIZE_NUMBER_FLOAT,
				'flags' => FILTER_FLAG_ALLOW_FRACTION,
			),

		);

		$sanitized_post = array();
		foreach ( $input_post_values as $key => $input_post_value ) {

			if ( isset( $_POST[ $key ] ) ) {
				if ( 'FILTER_SANITIZE_STRING' === $input_post_value['sanitize'] ) {
					$sanitized_post[ $key ] = $this->sanitize_text_filter( $key, 'POST' );
				} else {
					$sanitized_post[$key] = filter_input(INPUT_POST, $key, $input_post_value['sanitize']);

				}
			} else {
				$sanitized_post[ $key ] = $input_post_value['default'];
			}
		}
		return $sanitized_post;

	}

	/**
	 * Sanitize text field.
	 *
	 * @param string $key field key to sanitize. Revised.
	 * @param string $method method type.
	 */
	function sanitize_text_filter( $key, $method = 'POST' ) {

		$sanitized_value = '';
		//phpcs:disable WordPress.Security.NonceVerification
		if ( 'POST' === $method && isset( $_POST[ $key ] ) ) {
			$sanitized_value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}

		if ( 'GET' === $method && isset( $_GET[ $key ] ) ) {
			$sanitized_value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}
		//phpcs:enable WordPress.Security.NonceVerification
		return $sanitized_value;
	}

	/**
	 * Prepare cart data to save for abandonment. Revised
	 *
	 * @param array $post_data post data.
	 * @return array
	 */
	function prepare_abandonment_data( $post_data = array() ) {


		if ( function_exists( 'WC' ) ) {

			// Retrieving cart total value and currency.
			$cart_total = WC()->cart->total;

			$payment_gateway = WC()->session->chosen_payment_method;

			// Retrieving cart products and their quantities.
			$products = WC()->cart->get_cart();
			$shipping_cost = (float) WC()->cart->get_shipping_total() ?: 0;
			
			$current_time = current_time( 'Y-m-d H:i:s' );

			$other_fields = array(
				'wcf_billing_company'     => $post_data['wcf_billing_company'],
				'wcf_billing_address_1'   => $post_data['wcf_billing_address_1'],
				'wcf_billing_address_2'   => $post_data['wcf_billing_address_2'],
				'wcf_billing_state'       => $post_data['wcf_billing_state'],
				'wcf_billing_postcode'    => $post_data['wcf_billing_postcode'],
				'wcf_shipping_first_name' => $post_data['wcf_shipping_first_name'],
				'wcf_shipping_last_name'  => $post_data['wcf_shipping_last_name'],
				'wcf_shipping_company'    => $post_data['wcf_shipping_company'],
				'wcf_shipping_country'    => $post_data['wcf_shipping_country'],
				'wcf_shipping_address_1'  => $post_data['wcf_shipping_address_1'],
				'wcf_shipping_address_2'  => $post_data['wcf_shipping_address_2'],
				'wcf_shipping_city'       => $post_data['wcf_shipping_city'],
				'wcf_shipping_state'      => $post_data['wcf_shipping_state'],
				'wcf_shipping_postcode'   => $post_data['wcf_shipping_postcode'],
				'wcf_order_comments'      => $post_data['wcf_order_comments'],
				'wcf_first_name'          => $post_data['wcf_name'],
				'wcf_last_name'           => $post_data['wcf_surname'],
				'wcf_phone_number'        => $post_data['wcf_phone'],
				'wcf_location'            => $post_data['wcf_country'] . ', ' . $post_data['wcf_city'],
				'wcf_shipping_cost'       => $shipping_cost
			);

			$checkout_details = apply_filters(
				'woo_ca_session_abandoned_data',
				array(
					'email'         => $post_data['wcf_email'],
					'cart_contents' => maybe_serialize( $products ),
					'cart_total'    => sanitize_text_field( $cart_total ),
					'time'          => sanitize_text_field( $current_time ),
					'other_fields'  => maybe_serialize( $other_fields ),
					'checkout_id'   => $post_data['wcf_post_id'],
				)
			);

		}
		return $checkout_details;
	}

	function custom_add_country_code_field($fields) {
		$fields['billing']['billing_country_code'] = array(
			'type' => 'select',
			'label' => __('CÃ³digo del PaÃ­s', 'woocommerce'),
			'required' => true,
			'options' => array(
				'1' => '+1 (EE. UU.)',
				'44' => '+44 (Reino Unido)',
				// Agrega mÃ¡s opciones segÃºn sea necesario
			),
			'class' => array('form-row-wide'),
			'clear' => true,
		);
	
		return $fields;
	}

	function custom_display_country_code_field($checkout) {
		$fields = $checkout->get_checkout_fields('billing');
		$country_code_field = $fields['billing_country_code'];
	
		echo '<div class="form-row form-row-wide">';
		woocommerce_form_field('billing_country_code', $country_code_field, $checkout->get_value('billing_country_code'));
		echo '</div>';
	}

	function custom_validate_country_code_field() {
		if (!isset($_POST['billing_country_code']) || empty($_POST['billing_country_code'])) {
			wc_add_notice(__('Por favor, seleccione un cÃ³digo de paÃ­s.'), 'error');
		}
	}

	function custom_add_country_code_to_phone_number($posted_data) {
		if (isset($posted_data['billing_country_code'])) {
			$country_code = $posted_data['billing_country_code'];
			$phone_number = isset($posted_data['billing_phone']) ? $posted_data['billing_phone'] : '';
	
			if (!empty($phone_number)) {
				$posted_data['billing_phone'] = '+' . $country_code . ' ' . $phone_number;
			}
		}
	
		return $posted_data;
	}

}
