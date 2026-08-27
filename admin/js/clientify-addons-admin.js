(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

// document.addEventListener('keyup', (event) => {
// 		if (event.ctrlKey && event.altKey  && event.key == 'c') {
// 			 $('#other_config').show(1000)
// 		}
// 		setTimeout(function(){ $('#other_config').hide(1000) }, 10000);
// 	});

jQuery(document).ready(function () {
	$("#CLIENTIFY_ORDER_STATUS_names").select2({
		maximumSelectionLength: 5
	  });

	var pathname = window.location.pathname,
				   btnconnect = $("#connect"),
				   btndisconnect = $("#disconnect"),
				   classMessage = $('.message'),
				   connect = $('#connect'),
				   disconnect = $('#disconnect');

	/* detects plugin status to change the button on the frontend */
	if ( pathname === '/wp-admin/admin.php' ) {
		var apikey = $("#key").val(),
					 status = $("#status_clientify").val();

		if ( apikey !== '' && status == 1 ) {
				btnconnect.attr("disabled", true).addClass('connected').text("Conectado");
			}
		if ( apikey !== '' && status == 0 ) {
				btndisconnect.attr("disabled", true).text("Desconectado");
			}
	}

	/*  */
	function floatLabel(inputType){
		$(inputType).each(function(){
			var $this = $(this);
			if ( $this.val() != '' || $this.val() != 'blank' ) {
				$this.next().addClass("active");
				}
			// on focus add cladd active to label
			$this.focus(function(){
				$this.next().addClass("active");
			});
			//on blur check field and remove class if needed
			$this.blur(function(){
				if( $this.val() === '' || $this.val() === 'blank' ) {
					$this.next().removeClass();
				}
			});
		});
	}
	floatLabel(".floatLabel");

	// Notice grande arriba de las tabs — persiste 30s o hasta que el usuario la cierra
	function showAdminNotice(message, type) {
		var cssClass = (type === 'success') ? 'notice-success' : 'notice-error';
		$('.clientify-notice').remove();
		var $notice = $('<div class="notice ' + cssClass + ' is-dismissible clientify-notice"><p><strong>' + message + '</strong></p></div>');
		$('.nav-tab-wrapper').before($notice);
		// Hacer el dismiss nativo de WP funcional
		$notice.find('button.notice-dismiss').on('click', function() { $notice.remove(); });
		setTimeout(function () { $notice.fadeOut(600, function () { $(this).remove(); }); }, 30000);
	}

	// Mensaje pequeño junto al botón — desaparece en 4s
	function statusMessage(message, type) {
		var $msg = classMessage;
		$msg.removeClass('bridge_error bridge_success');
		$msg.addClass(type === 'success' ? 'bridge_success' : 'bridge_error');
		$msg.html('<span>' + message + '</span>').stop(true).fadeIn('fast');
		setTimeout(function () { $msg.fadeOut(500, function () { $msg.html(''); }); }, 4000);
	}

	// Mostrar notice persistida en sessionStorage tras recarga de página
	(function () {
		var pending = sessionStorage.getItem('clientify_notice');
		if (pending) {
			try {
				var n = JSON.parse(pending);
				showAdminNotice(n.message, n.type);
			} catch (e) {}
			sessionStorage.removeItem('clientify_notice');
		}
	})();

	connect.click(function() {
		var btnconnect = jQuery(this),
						 form = $('#api-form-settings'),
						 apikey = $("#key").val(),
						 orderProcess = $("#CLIENTIFY_ORDER_STATUS_names").val(),
						 api = $("#api").val(),
						 gdpr_status = $('#clientify_gdpr_check').is(':checked') ? 1 : 0,
						 gdpr_text = $('#clientify_gdpr_text').val().trim() || 'Acepto recibir comunicaciones comerciales GDPR',
						 res = 0;
		btnconnect.attr("disabled", true).text(btnconnect.data("loading-text"));
		if ( $("#key").val() === "" ) {
			statusMessage('API Key vacía', 'error');
			$("#key").focus();
			btnconnect.removeAttr("disabled").text("Conectar");
			return false;
		}

		$.ajax({
			url: ajaxurl,
			type: "POST",
			cache: false,
			data: {
				action: "connect_clientify",
				'apikey': apikey,
				'order_process': orderProcess,
				'gdpr_status': gdpr_status,
				'gdpr_text': gdpr_text
			},
			success: function(response) {
				try { res = (typeof response === 'string') ? JSON.parse(response) : response; } catch(e) { res = {}; }
				btnconnect.removeAttr("disabled").text("Conectar");
				if (res.status === 'success') {
					statusMessage('¡Conectado!', 'success');
					showAdminNotice(res.message, 'success');
					btnconnect.attr("disabled", true).text("Conectado").addClass('connected');
					sessionStorage.setItem('clientify_notice', JSON.stringify({ message: res.message, type: 'success' }));
					form.submit();
					if (res.open_url) { window.open(res.open_url, '_blank'); }
				} else {
					var msg = res.message || 'Error desconocido al conectar con Clientify.';
					if ( res.detail ) { msg += ' — ' + res.detail; }
					statusMessage('Error al conectar', 'error');
					showAdminNotice(msg, 'error');
					$("#key").focus();
				}
			},
			error: function() {
				btnconnect.removeAttr("disabled").text("Conectar");
				statusMessage('Error de red', 'error');
				showAdminNotice('No se pudo contactar con el servidor. Verifica tu conexión e inténtalo de nuevo.', 'error');
			}
		});
  	});

	disconnect.click(function() {
		var form = $('#api-form-settings'),
				   apikey = $("#key").val(),
				   btndisconnect = jQuery(this),
				   res = 0;
		btndisconnect.attr("disabled", true).text("Desconectando");

		if ( $("#key").val() === "" ) {
			statusMessage('API Key vacía', 'error');
			$("#key").focus();
			btndisconnect.removeAttr("disabled").text("Desconectar");
			return false;
		}

		$.ajax({
			url: ajaxurl,
			type: "POST",
			cache: false,
			data: {
				action: "disconnect_clientify",
				'apikey': apikey,
			},
			success: function(response) {
				try { res = (typeof response === 'string') ? JSON.parse(response) : response; } catch(e) { res = {}; }
				if (res.status === 'success') {
					statusMessage('¡Desconectado!', 'success');
					showAdminNotice(res.message, 'success');
					btndisconnect.attr("disabled", true).text("Desconectado");
					sessionStorage.setItem('clientify_notice', JSON.stringify({ message: res.message, type: 'success' }));
					form.submit();
				} else {
					statusMessage('Error al desconectar', 'error');
					showAdminNotice(res.message || 'Error al desconectar Clientify.', 'error');
					btndisconnect.removeAttr("disabled").text("Desconectar");
				}
			},
			error: function() {
				btndisconnect.removeAttr("disabled").text("Desconectar");
				statusMessage('Error de red', 'error');
				showAdminNotice('No se pudo contactar con el servidor.', 'error');
			}
		});

  	});

	$('#clientify_gdpr_check').change(function() {
		var checkboxValue = $(this).is(':checked') ? 1 : 0;


		var requestData = {
			action: "change_gdpr",
			clientify_gdpr: checkboxValue
		};


		$.ajax({
			url: ajaxurl,
			type: "POST",
			cache: false,
			data: requestData,
			success: function(response) {
				console.log(response);
			},
			error: function(xhr, status, error) {
				console.error("Error al procesar la solicitud:", error);
			}
		});
	});



});})( jQuery );
