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
	// var storeKey = $('#storekey');
	// var updateStoreKey = $('#updateStoreKey');
	
	var pathname = window.location.pathname;
	var btnconnect = $("#connect");
	var btndisconnect = $("#disconnect");
  	var classMessage = $('.message');	
	var connect = $('#connect');
	var disconnect = $('#disconnect');
	/* detects plugin status to change the button on the frontend */
	if (pathname === '/wp-admin/admin.php') {
		var apikey = $("#key").val();
		var status = $("#status_clientify").val();

		if (apikey !== '' && status == 1 ) {
				btnconnect.attr("disabled", true).addClass('connected').text("Conectado");
        		
			}
		if (apikey !== '' && status == 0 ) {
				btndisconnect.attr("disabled", true).text("Desconectado");
        		
			}	
	}

	/*  */
	function floatLabel(inputType){
		$(inputType).each(function(){
			var $this = $(this);
			if ($this.val() != '' || $this.val() != 'blank') {
					
				$this.next().addClass("active");
				}
			// on focus add cladd active to label
			$this.focus(function(){
				$this.next().addClass("active");
			});
			//on blur check field and remove class if needed
			$this.blur(function(){
				if($this.val() === '' || $this.val() === 'blank'){
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
		let toke_val = 0;
		var btnconnect = jQuery(this);
		var form = $('#api-form-settings');
		var apikey = $("#key").val();
		var orderProcess = $("#CLIENTIFY_ORDER_STATUS_names").val();
		var api = $("#api").val();
		var res = 0;
		btnconnect.attr("disabled", true).text(btnconnect.data("loading-text"));

		if($("#key").val() == ""){
        	statusMessage('Error de Conexión Clientify API Key Vacía','error');
        	$("#key").focus();       // Esta función coloca el foco de escritura del usuario en el campo Nombre directamente.
			btnconnect.removeAttr("disabled").text("Conectar").addClass('connect-class');
        return false;
    	}else{

			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				data: {
					action: "connect_clientify",
					'apikey': apikey,
					'order_process': orderProcess,
				},
				success: function(response) {
					//toke_val = typeof response.detail == "undefined";
					res = JSON.parse(response);
					console.log(response + ' real')
					console.log(response['status'])

						if (res.detail === "Invalid token.") {

							statusMessage('Error de conexión token','error');
							$("#key").focus();
							btnconnect.removeAttr("disabled").text("Conectar").addClass('connect-class');

						}else {

							if (res === "null" || res === "" || res.data['status'] == "error" || res.data['status'] == "Invalid token." || res['status'] == "store_id field not found" ||
							res.data['status'] == "failed" ) {

								if(res.data['status'] == "failed"){
									statusMessage('other owner with this store')
									$("#key").focus();
								}else if(res.data['status'] == "Invalid token."){
									statusMessage('Error Invalid Token','error')
									$("#key").focus();
								}else{
									statusMessage('Error de conexión Clientify','error')
									$("#key").focus();
								}
									btnconnect.removeAttr("disabled").text("Conectar").addClass('connect-class');		
								
							}else {		
								if (res.data['status'] == 'success') {
									statusMessage('Conexión Clientify Exitosa','success');
									btnconnect.attr("disabled", true).text("Conectado").addClass('connected');
									form.submit();
									$("#key").focus();
									var win = window.open('http://app.clientify.com/ecommerce/settingsv2/list-store/woocommerce', '_blank');
									//$(location).attr('href', 'https://ecommerce.ngrok.io/ecommerce/settingsv2/list-store/woocommerce')
									
								}
								if (response == '' || response == 0 ) {
									statusMessage('Error al conectar Clientify API Key Vacía','error');
									$("#key").focus();  // Esta función coloca el foco de escritura del usuario en el campo Nombre directamente.
									return false;
								}		
						
							}
						}
					//console.log(typeof res.detail === 'Invalid token.')

				}
			});	
		}		
  	});

	disconnect.click(function() {
		var form = $('#api-form-settings');
		var apikey = $("#key").val();
		var btndisconnect = jQuery(this);
		var res = 0;
		btndisconnect.attr("disabled", true).text("Desconectando");

		if($("#key").val() == ""){
        	statusMessage('Error al Conectar Clientify Api Key Vacía','error');
        	$("#key").focus();       // Esta función coloca el foco de escritura del usuario en el campo Nombre directamente.
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
					res = JSON.parse(response);	
					console.log(response + ' real')			
					console.log(res.detail);
					if (res === null || res.detail == "Invalid token." || res.data['status'] == "failed"  ) {
						statusMessage('Error Al Desconectar Clientify','error');
						$("#key").focus();
						location.reload();
						
					}else{
						if (res.data['status'] == 'success') {
							statusMessage('Desconexión Clientify Exitosa','success');
							btndisconnect.attr("disabled", true).text("Desconectado");
							$("#key").focus();
							//setTimeout(() => {  console.log("World!"); }, 3000);
							
							form.submit();
							$("#key").focus();
						}else{
							statusMessage('Error Al Desconectar Clientify','error');
							form.submit();
							$("#key").focus();  // Esta función coloca el foco de escritura del usuario en el campo Nombre directamente.
						}
					}		
				}
			});

		}
		
  	});
  
	//   updateStoreKey.click(function() {
// 		$.ajax({
// 		url: ajaxurl,
// 		type: "POST",
// 		cache: false,
// 		data: {
			
// 			action: "token_id",
			
// 		},
// 		success: function(response) {
			
// 			if (response == '' || response == 0 ) {
// 			statusMessage('Can not update Key','error');
// 			return;
// 			}
// 			updateKeyInput(response);
// 			console.log(response);
// 		}
// 		});
//   	});

// 	function updateKeyInput(store_key){
// 		statusMessage('Key Updated Successfully!','success');
//     	$('#storekey').val(store_key.replace(/['"]+/g, ''));
//   	};

// 	jQuery(".sync_customers").click(function (e) {
// 	var sync = jQuery(this);
// 	var sync_txt = sync.text();
// 	var count = 0; 
// 	sync.attr("disabled", true).text(sync.data("loading-text"));
// 		jQuery.ajax({
// 		url: ajaxurl,
// 		type: "POST",
// 		data: {
// 			"sync-customers": 1,
// 			action: "sync_customer",
// 		},
// 		success: function (response) {
// 			console.log("response", response);
// 			count = JSON.parse(response);
// 			sync.removeAttr("disabled").text(sync_txt);
// 			alert("Synced " + count.count + " Customers");
// 		},
// 		});
// 	});

// setTimeout(function() {

// 	var vk = '633adda330674';

// 	if (typeof vk != 'undefined') {
		
// 		console.log(vk)
// 		ajax_url = '';
// 		var ajax_url = clientify_ajax.ajaxurl;

// 		var http = new XMLHttpRequest();
// 		http.open('POST', ajax_url, true);		
// 		http.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;');
// 		http.send('action=update_vk&vk=' + vk);
// 	}
// }, 10);






});
	
	  
})( jQuery );