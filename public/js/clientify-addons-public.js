( function ( $ ) {

	console.log('init //')
	let timer;
	let iti = null;
	const wcf_cart_abandonment = {

		init() {
			if (
				clientify_wcf_ca_vars._show_gdpr_message &&
				! $( '#wcf_cf_gdpr_message_block' ).length
			) {
				$( '#billing_email' ).after(
					"<span id='wcf_cf_gdpr_message_block'> <span style='font-size: xx-small'> " +
						clientify_wcf_ca_vars._gdpr_message +
						" <a style='cursor: pointer' id='wcf_ca_gdpr_no_thanks'> " +
						clientify_wcf_ca_vars._gdpr_nothanks_msg +
						' </a></span></span>'
				);
			}

			$( document ).on(
				'keyup keypress change',
				'#billing_email, #email ,#billing_phone, input.input-text, textarea.input-text, select,' +
					' .wc-block-checkout input, .wc-block-checkout select, .wc-block-components-address-form input, .wc-block-components-address-form select',
				this._getCheckoutData
			);

			// El checkout de bloques (WooCommerce Blocks) no dispara "updated_checkout" y
			// renderiza/actualiza campos via React, sin eventos de input tradicionales al
			// cargar. Reintentamos periódicamente durante los primeros segundos para
			// capturar los valores ya precargados (autocompletado del navegador, etc).
			if ( $( '.wc-block-checkout, .wp-block-woocommerce-checkout' ).length ) {
				let attempts = 0;
				const blocksPoll = setInterval( function () {
					attempts++;
					wcf_cart_abandonment._getCheckoutData();
					if ( attempts >= 10 ) {
						clearInterval( blocksPoll );
					}
				}, 1500 );
			}

			$( '#wcf_ca_gdpr_no_thanks' ).on( 'click', function () {
				wcf_cart_abandonment._set_cookie();
			} );

			$( document.body ).on( 'updated_checkout', function () {
				wcf_cart_abandonment._getCheckoutData();
			} );

			$( function () {
				setTimeout( function () {
					wcf_cart_abandonment._getCheckoutData();
				}, 800 );
			} );
		},

		_set_cookie() {
			const data = {
				wcf_ca_skip_track_data: true,
				action: 'clientify_skip_cart_tracking_gdpr',
				security: clientify_wcf_ca_vars._gdpr_nonce,
			};

			jQuery.post( clientify_wcf_ca_vars.ajaxurl, data, function ( response ) {
				if ( response.success ) {
					$( '#wcf_cf_gdpr_message_block' )
						.empty()
						.append(
							"<span style='font-size: xx-small'>" +
								clientify_wcf_ca_vars._gdpr_after_no_thanks_msg +
								'</span>'
						)
						.delay( 5000 )
						.fadeOut();
				}
			} );
		},

		_validate_email( value ) {
			let valid = true;
			if ( value.indexOf( '@' ) === -1 ) {
				valid = false;
			} else {
				const parts = value.split( '@' );
				const domain = parts[ 1 ];
				if ( domain.indexOf( '.' ) === -1 ) {
					valid = false;
				} else {
					const domainParts = domain.split( '.' );
					const ext = domainParts[ 1 ];
					if ( ext.length > 14 || ext.length < 2 ) {
						valid = false;
					}
				}
			}
			return valid;
		},

		// Lee un campo del checkout probando primero el ID clásico (shortcode
		// checkout, ej. "billing_first_name") y luego el de WooCommerce Blocks
		// (checkout nuevo, ej. "billing-first_name" con guion).
		_val( classicId, blocksId ) {
			const classicVal = jQuery( '#' + classicId ).val();
			if ( classicVal ) {
				return classicVal;
			}
			return jQuery( '#' + blocksId ).val() || '';
		},

		_getPhoneWithPrefix() {
			const $phoneField = jQuery( '#billing_phone' ).length
				? jQuery( '#billing_phone' )
				: jQuery( '#billing-phone' );
			const rawPhone = $phoneField.val() || '';
			const dialCode = $phoneField.closest( '.iti' ).find( '.iti__selected-dial-code' ).text().trim();
			if ( dialCode && rawPhone ) {
				return dialCode + rawPhone;
			}
			return rawPhone;
		},

		_getCheckoutData() {
			const wcf_email = jQuery('#billing_email').val() || jQuery('#email').val();

			if (typeof wcf_email === 'undefined') {
				return;
			}

			let wcf_phone = wcf_cart_abandonment._getPhoneWithPrefix();
			const atposition = wcf_email.indexOf( '@' );
			const dotposition = wcf_email.lastIndexOf( '.' );

			if ( typeof wcf_phone === 'undefined' || wcf_phone === null ) {
				//If phone number field does not exist on the Checkout form
				wcf_phone = '';
			}

			clearTimeout( timer );

			if (
				! (
					atposition < 1 ||
					dotposition < atposition + 2 ||
					dotposition + 2 >= wcf_email.length
				) ||
				wcf_phone.length >= 1
			) {
				//Checking if the email field is valid or phone number is longer than 1 digit
				//If Email or Phone valid
				const wcf_name = wcf_cart_abandonment._val( 'billing_first_name', 'billing-first_name' );
				const wcf_surname = wcf_cart_abandonment._val( 'billing_last_name', 'billing-last_name' );
				wcf_phone = wcf_cart_abandonment._getPhoneWithPrefix();
				const wcf_country = wcf_cart_abandonment._val( 'billing_country', 'billing-country' );
				const wcf_city = wcf_cart_abandonment._val( 'billing_city', 'billing-city' );

				//Other fields used for "Remember user input" function
				const wcf_billing_company = wcf_cart_abandonment._val( 'billing_company', 'billing-company' );
				const wcf_billing_address_1 = wcf_cart_abandonment._val( 'billing_address_1', 'billing-address_1' );
				const wcf_billing_address_2 = wcf_cart_abandonment._val( 'billing_address_2', 'billing-address_2' );
				const wcf_billing_state = wcf_cart_abandonment._val( 'billing_state', 'billing-state' );
				const wcf_billing_postcode = wcf_cart_abandonment._val( 'billing_postcode', 'billing-postcode' );
				const wcf_shipping_first_name = wcf_cart_abandonment._val( 'shipping_first_name', 'shipping-first_name' );
				const wcf_shipping_last_name = wcf_cart_abandonment._val( 'shipping_last_name', 'shipping-last_name' );
				const wcf_shipping_company = wcf_cart_abandonment._val( 'shipping_company', 'shipping-company' );
				const wcf_shipping_country = wcf_cart_abandonment._val( 'shipping_country', 'shipping-country' );
				const wcf_shipping_address_1 = wcf_cart_abandonment._val( 'shipping_address_1', 'shipping-address_1' );
				const wcf_shipping_address_2 = wcf_cart_abandonment._val( 'shipping_address_2', 'shipping-address_2' );
				const wcf_shipping_city = wcf_cart_abandonment._val( 'shipping_city', 'shipping-city' );
				const wcf_shipping_state = wcf_cart_abandonment._val( 'shipping_state', 'shipping-state' );
				const wcf_shipping_postcode = wcf_cart_abandonment._val( 'shipping_postcode', 'shipping-postcode' );
				const wcf_order_comments = wcf_cart_abandonment._val( 'order_comments', 'order-comments' );
				const shipping_cost = jQuery( '#shipping_cost' ).val();
				// Some checkout phone widgets (e.g. intl-tel-input) already keep a
				// hidden "full_phone_number" input with the E.164 number. Send it
				// along as a fallback, in case scraping the dial code out of the
				// widget's DOM (_getPhoneWithPrefix) didn't pick it up in time.
				const wcf_full_phone_number = jQuery( 'input[name="full_phone_number"]' ).val() || '';
				const data = {
					action: 'clientify_save_cart_abandonment_data',
					wcf_email,
					wcf_name,
					wcf_surname,
					wcf_phone,
					wcf_full_phone_number,
					wcf_country,
					wcf_city,
					wcf_billing_company,
					wcf_billing_address_1,
					wcf_billing_address_2,
					wcf_billing_state,
					wcf_billing_postcode,
					wcf_shipping_first_name,
					wcf_shipping_last_name,
					wcf_shipping_company,
					wcf_shipping_country,
					wcf_shipping_address_1,
					wcf_shipping_address_2,
					wcf_shipping_city,
					wcf_shipping_state,
					wcf_shipping_postcode,
					wcf_order_comments,
					shipping_cost,
					security: clientify_wcf_ca_vars._nonce,
					wcf_post_id: clientify_wcf_ca_vars._post_id,
				};

				timer = setTimeout( function () {
					if (
						wcf_cart_abandonment._validate_email( data.wcf_email )
					) {
						jQuery.post(
							clientify_wcf_ca_vars.ajaxurl,
							data, //Ajaxurl coming from localized script and contains the link to wp-admin/admin-ajax.php file that handles AJAX requests on Wordpress
							function () {
								// success response
							}
						);
					}
				}, 500 );
			} else {
				//console.log("Not a valid e-mail or phone address");
			}
		},
	};

	wcf_cart_abandonment.init();

	// CF7: after successful submission send contact to Clientify (fire-and-forget, no PHP blocking)
	document.addEventListener( 'wpcf7mailsent', function ( event ) {
		var inputs  = event.detail && event.detail.inputs ? event.detail.inputs : [];
		var payload = {
			action : 'clientify_cf7_contact_sync',
			nonce  : clientify_wcf_ca_vars._cf7_nonce,
		};
		inputs.forEach( function ( field ) { payload[ field.name ] = field.value; } );

		var body = Object.keys( payload ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( payload[ k ] );
		} ).join( '&' );

		if ( typeof fetch !== 'undefined' ) {
			fetch( clientify_wcf_ca_vars.ajaxurl, {
				method   : 'POST',
				headers  : { 'Content-Type': 'application/x-www-form-urlencoded' },
				body     : body,
				keepalive: true,
			} );
		}
	}, false );

	// En tu archivo JavaScript (tu-script-ajax.js)
jQuery(document).ready(function ($) {
    $('.woocommerce-MyAccount-navigation-link--suscripcion a').on('click', function (e) {
    console.log('click')
        e.preventDefault();
		
        // Realizar solicitud Ajax
        $.ajax({
            url: clientify_wcf_ca_vars.ajaxurl,
			cache: false,
            type: 'GET',
            data: {
                action: 'cargar_contenido_suscripcion_endpoint',
            },
            success: function (response) {
				res = JSON.parse(response)
				console.log(res.contenido)
                // Actualizar contenido en la clase woocommerce-MyAccount-content
                $('.woocommerce-MyAccount-content').html(res.contenido);

				// Cambia la URL utilizando history.pushState()
                // Cambia la URL utilizando history.pushState()
                var nuevaURL = window.location.origin + '/mi-cuenta/suscripcion';
                history.pushState(null, null, nuevaURL);
				// Agrega el evento al cambio del checkbox
                $('#suscripcion_save').on('click', function () {
                    guardarCambios();
                });
            },
            error: function (error) {
                console.error(error);
            }
        });
		
    });
	 // Función para guardar cambios
	  // Función para guardar cambios
	  function guardarCambios() {
        // Recopila todos los datos del formulario
        var formData = $('#formulario_suscripcion').serialize();
		var urlParams = new URLSearchParams(formData);

		// Obtener el valor de un parámetro específico
		var contactClienteId = urlParams.get('contact_clienti_id');
		var suscripcionNewsletter = urlParams.get('suscripcion_newsletter')
		console.log(contactClienteId,suscripcionNewsletter)
        // Realiza la solicitud Ajax
        $.ajax({
            type: 'POST',
            url: clientify_wcf_ca_vars.ajaxurl,
            data: {
                action: 'guardar_suscripcion_contact_info',
                id_clienti_cus: contactClienteId,
				status_clienti_newsletter: suscripcionNewsletter
            },
            dataType: 'json',
            success: function (response) {
                console.log(response);

            },
            error: function (error) {
                console.error(error);
            }
        });
    }
	
});

// window.addEventListener('load', function() {
// 	const input = document.querySelector('#shipping-phone');
// 	window.intlTelInput(input, {
// 	  initialCountry: 'auto',
// 	  geoIpLookup: function(callback) {
// 		fetch('https://ipapi.co/json')
// 		  .then(function(res) { return res.json(); })
// 		  .then(function(data) { callback(data.country_code); })
// 		  .catch(function() { callback(); });
// 	  }
// 	});
//   });
//input.style.paddingLeft = '20px';



} )( jQuery );