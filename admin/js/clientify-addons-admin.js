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

document.addEventListener('keyup', (event) => {
		if (event.ctrlKey && event.altKey  && event.key == 'c') {
			 $('#other_config').show(1000)
		}
		setTimeout(function(){ $('#other_config').hide(1000) }, 10000);
	});

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
	// just add a class of "floatLabel to the input field!"
	/* displays the message in the menssage div */
	function statusMessage(message, status) {
    	if (status == 'success') {
      		classMessage.removeClass('bridge_error');
    	} else {
      		classMessage.addClass('bridge_error');
    	}
    	classMessage.html('<span>' + message + '</span>');
    	classMessage.fadeIn("slow");
    	classMessage.fadeOut(7000);
    	var messageClear = setTimeout(function(){
      	classMessage.html('');
    	}, 3000);
    	clearTimeout(messageClear);
  	};

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
		if( $("#key").val() == "" ) {
        	statusMessage('Error de Conexión Clientify API Key Vacía','error');
        	$("#key").focus();       // Esta función coloca el foco de escritura del usuario en el campo Nombre directamente.
			btnconnect.removeAttr("disabled").text("Conectar").addClass( 'connect-class' );
        return false;
    	} else {

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
					try { res = JSON.parse(response); } catch(e) { res = {}; }
					if ( res.status === 'success' ) {
						statusMessage(res.message || 'Conexión Clientify Exitosa', 'success');
						btnconnect.attr("disabled", true).text("Conectado").addClass('connected');
						form.submit();
						if ( res.open_url ) { window.open(res.open_url, '_blank'); }
					} else {
						var msg = res.message || 'Error de conexión Clientify';
						if ( res.detail ) { msg += ' — ' + res.detail; }
						statusMessage(msg, 'error');
						$("#key").focus();
						btnconnect.removeAttr("disabled").text("Conectar").addClass('connect-class');
					}
				}
			});	
		}		
  	});

	disconnect.click(function() {
		var form = $('#api-form-settings'),
				   apikey = $("#key").val(),
				   btndisconnect = jQuery(this),
				   res = 0;
		btndisconnect.attr("disabled", true).text("Desconectando");

		if( $("#key").val() == "" ){
        	statusMessage('Error al Conectar Clientify Api Key Vacía','error');
        	$("#key").focus(); 
        return false;
    	}else{

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "disconnect_clientify",
					'apikey': apikey,
				},
				success: function(response) {
					try { res = JSON.parse(response); } catch(e) { res = {}; }
					if ( res.status === 'success' ) {
						statusMessage(res.message || 'Desconexión Clientify Exitosa', 'success');
						btndisconnect.attr("disabled", true).text("Desconectado");
						form.submit();
					} else {
						statusMessage(res.message || 'Error al desconectar Clientify', 'error');
						location.reload();
					}
				}
			});
		}
		
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