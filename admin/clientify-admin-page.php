<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-api-connect.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-helper.php';
function clientify_settings_page()
{
	$api = new Clientify_Api;
	$helpers = new Clientify_Helper();
	$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
	$params = [
		'type_clean' => 'days' // Correcto: esto es un array
	];
	$helpers->delete_old_logs($params);
?>
	<div class="conten">
		<input type="hidden" id="api" name="api" value="<?php echo esc_url( $api->api_url ); ?>">
		<header>
			<img src="../wp-content/plugins/clientify-addons-wc/public/img/CL20horizontal.png" alt="Clientify" class="logo-clientify-responsive">
			<p>Gestiona y automatiza tu Marketing y Ventas fácilmente.</p>
		</header>

		<nav class="nav-tab-wrapper">
			<a href="?page=clientify-addons&tab=settings" class="nav-tab color-nav <?php if ($tab === 'settings') : ?>nav-tab-active<?php endif; ?>">
				<span class="dashicons dashicons-admin-generic"></span><?php _e('Settings', 'clientify'); ?>
			</a>
			<a href="?page=clientify-addons&tab=logs" class="nav-tab color-nav <?php if ($tab === 'logs') : ?>nav-tab-active<?php endif; ?>">
				<span class="dashicons dashicons-list-view"></span><?php _e('Logs', 'clientify'); ?>
			</a>
			<a href="?page=clientify-addons&tab=abandoned_carts" class="nav-tab color-nav <?php if ($tab === 'abandoned_carts') : ?>nav-tab-active<?php endif; ?>">
				<span class="dashicons dashicons-cart"></span><?php _e('Carritos Abandonados', 'clientify'); ?>
			</a>
		</nav>
		<div class="body_clientify">
			<?php
				$api_url  = get_rest_url();
				$del = str_contains($api_url, '/wp-json/') ? '' : '';
				$api_url = $api_url . 'clientify/v1/' . $del;
				switch ($tab):
					case 'settings': ?>
					<div class='general'>
						<input type="hidden" id="status_clientify" name="CLIENTIFY_STATUS" value="<?php echo esc_attr(get_option('CLIENTIFY_STATUS')); ?>">
						
						<div class="form-group"> </div>
					</div>
					
					<form id="api-form-settings" action="options.php" method="POST">
						<?php settings_fields('clientify-settings-group'); ?>
						<?php do_settings_sections('clientify-settings-group'); ?>
						<!--  General -->
						<div class="form-group">
							<h2 class="heading">Configuracion General</h2>
							<div class="controls">
								<input type="text" id="key" class="floatLabel" name="CLIENTIFY_API_KEY" value="<?php echo esc_attr(get_option('CLIENTIFY_API_KEY')); ?>">
								<label for="key"><?php echo esc_html__('Clientify Api Key', 'clientify'); ?></label>
							</div>

							<div class="controls">
								<input type="text" id="storekey" class="floatLabel" name="CLIENTIFY_STORE_KEY" value="<?php echo esc_attr(get_option('CLIENTIFY_STORE_KEY')); ?>" readonly>
								<label for="storekey"><?php echo esc_html__('Store Key', 'clientify'); ?></label>
								<div class="message"></div>
							</div>
							<div class="controls">
								<input type="text" id="clientify_gdpr_text" class="floatLabel" name="CLIENTIFY_GDPR_TEXT" value="<?php echo esc_attr(get_option('CLIENTIFY_GDPR_TEXT')); ?>">
								<label for="key"><?php echo esc_html__('Texto Personalizado para el GDPR', 'clientify'); ?></label>
							</div>
							<?php
							$external_gdpr = Clientify_Plugin_Core::detect_external_gdpr_plugin();
							$mc_plugin     = Clientify_Plugin_Core::detect_newsletter_plugin();
							$any_external  = $external_gdpr || $mc_plugin;
							?>
							<div class="controls gdpr_buttom">
								<input class="input_gdpr" type="checkbox" id="clientify_gdpr_check" name="clientify_gdpr_check" value='1' <?php checked( get_option('CLIENTIFY_GDPR'), 1 ); ?> <?php if ( $any_external ) echo 'disabled'; ?> />
								<label class="label_gdpr" for="clientify_gdpr_check"><?php _e( 'Suscripción GDPR', 'clientify' ); ?></label>
							</div>
							<?php if ( $external_gdpr ) : ?>
							<div class="clientify-gdpr-external-notice" style="margin-top:12px;padding:12px 16px;background:#f0f7ff;border-left:4px solid #0073aa;border-radius:2px;">
								<p style="margin:0 0 6px;font-weight:600;color:#0073aa;">
									<span class="dashicons dashicons-info" style="vertical-align:middle;margin-right:4px;"></span>
									Plugin GDPR externo detectado: <?php echo esc_html( $external_gdpr['name'] ); ?>
								</p>
								<p style="margin:0 0 6px;color:#444;">
									La opción GDPR propia de Clientify está <strong>desactivada</strong> porque ya usas <strong><?php echo esc_html( $external_gdpr['name'] ); ?></strong> (<?php echo esc_html( $external_gdpr['author'] ); ?>) en tu web.<br>
									El checkbox de Clientify no se mostrará en el checkout para evitar duplicados.
								</p>
								<p style="margin:0;color:#444;">
									✅ <strong>Clientify está leyendo el consentimiento</strong> desde la cookie <code><?php echo esc_html( $external_gdpr['cookie'] ); ?></code> de <?php echo esc_html( $external_gdpr['name'] ); ?> y lo enviará automáticamente con cada contacto sincronizado.
								</p>
							</div>
							<?php endif; ?>
							<?php if ( $mc_plugin ) : ?>
							<div class="clientify-gdpr-external-notice" style="margin-top:12px;padding:12px 16px;background:#fff8e1;border-left:4px solid #f0ad00;border-radius:2px;">
								<p style="margin:0 0 6px;font-weight:600;color:#a07800;">
									<span class="dashicons dashicons-email-alt" style="vertical-align:middle;margin-right:4px;"></span>
									Plugin de newsletter detectado: <?php echo esc_html( $mc_plugin['name'] ); ?>
								</p>
								<p style="margin:0 0 6px;color:#444;">
									Tu web usa <strong><?php echo esc_html( $mc_plugin['name'] ); ?></strong> (<?php echo esc_html( $mc_plugin['author'] ); ?>). El checkbox de consentimiento de Clientify no se mostrará para evitar duplicar el checkbox de <?php echo esc_html( $mc_plugin['name'] ); ?>.
								</p>
								<p style="margin:0;color:#444;">
									✅ <strong>Clientify está leyendo el consentimiento</strong> desde el campo <code><?php echo esc_html( $mc_plugin['post_field'] ); ?></code> de <?php echo esc_html( $mc_plugin['name'] ); ?> y lo sincronizará automáticamente con cada contacto.
								</p>
							</div>
							<?php endif; ?>
						</div>
						
						<!-- hide_div -->
						<div class="form-group" id="other_config">
							<h2 class="heading">Otras Configuraciones</h2>
							<div class="controls">
								<?php 
								$order_statuses_clientify = wc_get_order_statuses(); 
								$selected_options = get_option('CLIENTIFY_ORDER_STATUS');
								?>
								<select name="CLIENTIFY_ORDER_STATUS_names" id="CLIENTIFY_ORDER_STATUS_names" multiple="multiple" class="floatLabel">								
									<?php foreach ($order_statuses_clientify as $key => $order_status) : ?>
									<option value="<?php echo $key; ?>" <?php if (in_array($key, $selected_options)) { echo 'selected'; } ?>><?php echo $order_status; ?></option>
									<?php endforeach; 
									?>
								</select>
								<label for="orderstatus" class="orderlabel" style="top: -20px !important;color: #555 !important;background-color: white !important;">
								<?php _e('Order Status', 'clientify'); ?></label>
							</div>

							<br>
							<!-- <div class="controls">
								<select name="CLIENTIFY_CART_HOUR" id="CLIENTIFY_CART_HOUR" class="floatLabel">
									<option value="">
									<option value="<?php //echo esc_html( get_option('CLIENTIFY_CART_HOUR') )?>" 
													<?php //if ( esc_html( get_option('CLIENTIFY_CART_HOUR') ) != '' ) {
															//echo 'selected';
															//}  
													?>
										>
										Luego de
										<?php //echo esc_html( get_option('CLIENTIFY_CART_HOUR') ) ?> 
										Horas
									</option>

									<?php //for ($i = 1; $i < 25; $i++) {
										//echo '<option value="' . esc_attr( $i ) . '">Luego de ' . esc_html( $i ) . ' Horas</option>';
									//}	
									?>
								</select>
								<label for="fruit">Tiempo Carro Abandonado</label>
							</div> -->
							<div class="controls">
								<input type="text" id="url" class="floatLabel" name="URL_BASE" value="<?php echo $api_url ?>" readonly>
								<label for="url"><?php _e('Clientify Api Url', 'clientify'); ?></label>
							</div>
						</div>
					</form>

					<div class='general'>
						<div class="form-group clientify-button-wrapper">
							<button id="connect" class="clientify-btn clientify-btn-primary" data-loading-text="Connecting">Conectar</button>
							<button id="disconnect" class="clientify-btn clientify-btn-secondary">Desconectar</button>
						</div>
					</div>
					<?php
					break;

					case 'logs':
						global $wpdb;
						$table_name = $wpdb->prefix . 'clientify_logs';
					
						// Check if the table exists
						if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
							echo '<div class="general">';
							echo '<div class="form-group">';
							echo '<div class="notice notice-warning"><p>No se encontró la tabla de logs. Por favor, asegúrate de que el plugin esté correctamente activado.</p></div>';
							echo '</div>';
							echo '</div>';
						} else {
							// Table exists, show logs
							$logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC");
					
							echo '<div class="general">';
							echo '<div class="form-group-logs">';
							echo '<h2 class="heading">Logs de Clientify</h2>';
					
							if (empty($logs)) {
								echo '<div class="controls">';
								echo '<p>No hay registros de logs disponibles.</p>';
								echo '</div>';
							} else {
								echo '<div class="controls">';
								echo '<table class="wp-list-table widefat fixed striped">';
								echo '<thead><tr><th>ID</th><th>Fecha</th><th>Nivel</th><th>message</th><th>Archivo</th><th>Línea</th></tr></thead>';
								echo '<tbody>';
								foreach ($logs as $log) {
									echo '<tr>';
									echo '<td>' . esc_html($log->id) . '</td>';
									echo '<td>' . esc_html($log->timestamp) . '</td>';
									echo '<td>' . esc_html($log->level) . '</td>';
									echo '<td>' . esc_html($log->message) . '</td>';
									echo '<td>' . esc_html($log->file) . '</td>';
									echo '<td>' . esc_html($log->error_line) . '</td>';
									echo '</tr>';
								}
								echo '</tbody>';
								echo '</table>';
								echo '</div>';
							}
					
							echo '</div>';
							echo '</div>';
						}
						break;

					case 'abandoned_carts':
						global $wpdb;
						$cart_table = $wpdb->prefix . 'clientify_ca_cart_abandonment';

						if ($wpdb->get_var("SHOW TABLES LIKE '$cart_table'") != $cart_table) {
							echo '<div class="general">';
							echo '<div class="form-group">';
							echo '<div class="notice notice-warning"><p>No se encontró la tabla de carritos abandonados. Por favor, asegúrate de que el plugin esté correctamente activado.</p></div>';
							echo '</div>';
							echo '</div>';
							break;
						}

						$allowed_per_page = array(5, 25, 50, 100);
						$per_page         = isset($_GET['per_page']) ? intval($_GET['per_page']) : 5;
						if (!in_array($per_page, $allowed_per_page, true)) {
							$per_page = 5;
						}
						$paged       = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
						$offset      = ($paged - 1) * $per_page;
						$total_carts = (int) $wpdb->get_var("SELECT COUNT(*) FROM $cart_table");
						$total_pages = $per_page > 0 ? (int) ceil($total_carts / $per_page) : 1;

						$carts = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT id, checkout_id, session_id, email, cart_contents, cart_total, other_fields, order_status, unsubscribed, coupon_code, time FROM $cart_table ORDER BY time DESC LIMIT %d OFFSET %d",
								$per_page,
								$offset
							)
						);

						echo '<div class="general">';
						echo '<div class="form-group-logs">';
						echo '<h2 class="heading">Carritos Abandonados</h2>';

						echo '<div class="clientify-carts-toolbar">';

						echo '<div class="clientify-per-page-selector">';
						echo '<form method="get">';
						echo '<input type="hidden" name="page" value="clientify-addons">';
						echo '<input type="hidden" name="tab" value="abandoned_carts">';
						echo '<label for="clientify_per_page">' . esc_html__('Mostrar', 'clientify') . '</label>';
						echo '<select name="per_page" id="clientify_per_page" onchange="this.form.submit()">';
						foreach ($allowed_per_page as $option) {
							echo '<option value="' . esc_attr($option) . '"' . selected($per_page, $option, false) . '>' . esc_html($option) . '</option>';
						}
						echo '</select>';
						echo '</form>';
						echo '</div>';

						echo '<button type="button" id="clientify-clean-carts-btn" class="button">' . esc_html__('Limpiar carritos', 'clientify') . '</button>';

						echo '</div>';

						if (empty($carts)) {
							echo '<div class="controls">';
							echo '<p>No hay carritos abandonados registrados.</p>';
							echo '</div>';
						} else {
							echo '<div class="controls">';
							echo '<table class="wp-list-table widefat fixed striped">';
							echo '<thead><tr><th>ID</th><th>Email</th><th>Total</th><th>Estado</th><th>Cupón</th><th>Suscrito</th><th>Fecha</th><th>Detalle</th><th>Acción</th></tr></thead>';
							echo '<tbody>';
							foreach ($carts as $cart) {
								$items        = maybe_unserialize($cart->cart_contents);
								$item_count   = is_array($items) ? count($items) : 0;
								$other_fields = (object) maybe_unserialize($cart->other_fields);

								$products_json = array();
								foreach ($items as $item) {
									$product_id = isset($item['product_id']) ? $item['product_id'] : 0;
									$product    = $product_id ? wc_get_product($product_id) : false;
									$products_json[] = array(
										'name'     => $product ? $product->get_name() : sprintf('Producto #%d', $product_id),
										'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 0,
										'total'    => number_format(isset($item['line_total']) ? (float) $item['line_total'] : 0, 2),
									);
								}

								$full_name = trim(($other_fields->wcf_first_name ?? '') . ' ' . ($other_fields->wcf_last_name ?? ''));
								$address   = trim(($other_fields->wcf_billing_address_1 ?? '') . ' ' . ($other_fields->wcf_billing_address_2 ?? ''));

								$detail_json = wp_json_encode(array(
									'cartId'      => $cart->id,
									'checkoutId'  => $cart->checkout_id,
									'sessionId'   => $cart->session_id,
									'email'       => $cart->email,
									'name'        => $full_name,
									'phone'       => $other_fields->wcf_phone_number ?? '',
									'address'     => $address,
									'city'        => $other_fields->wcf_shipping_city ?? '',
									'country'     => $other_fields->wcf_shipping_country ?? '',
									'postalCode'  => $other_fields->wcf_billing_postcode ?? '',
									'shipping'    => $other_fields->wcf_shipping_cost ?? '',
									'coupon'      => $cart->coupon_code,
									'unsubscribed' => $cart->unsubscribed ? 'Sí' : 'No',
									'status'      => $cart->order_status,
									'total'       => number_format((float) $cart->cart_total, 2),
									'date'        => $cart->time,
									'products'    => $products_json,
								));

								echo '<tr>';
								echo '<td>' . esc_html($cart->id) . '</td>';
								echo '<td>' . esc_html($cart->email) . '</td>';
								echo '<td>' . esc_html(number_format((float) $cart->cart_total, 2)) . '</td>';
								echo '<td>' . esc_html($cart->order_status) . '</td>';
								echo '<td>' . esc_html($cart->coupon_code ?: '—') . '</td>';
								echo '<td>' . ($cart->unsubscribed ? esc_html__('No', 'clientify') : esc_html__('Sí', 'clientify')) . '</td>';
								echo '<td>' . esc_html($cart->time) . '</td>';
								echo '<td><button type="button" class="button clientify-view-cart-btn" data-detail="' . esc_attr($detail_json) . '">' . esc_html__('Ver', 'clientify') . ($item_count ? ' (' . esc_html($item_count) . ')' : '') . '</button></td>';
								echo '<td><button type="button" class="button button-primary clientify-send-cart-btn" data-cart-id="' . esc_attr($cart->id) . '">' . esc_html__('Enviar', 'clientify') . '</button></td>';
								echo '</tr>';
							}
							echo '</tbody>';
							echo '</table>';
							echo '</div>';

							if ($total_pages > 1) {
								echo '<div class="clientify-pagination">';
								for ($i = 1; $i <= $total_pages; $i++) {
									$page_url = esc_url(add_query_arg(array('page' => 'clientify-addons', 'tab' => 'abandoned_carts', 'per_page' => $per_page, 'paged' => $i)));
									$class    = $i === $paged ? ' class="clientify-page-number current"' : ' class="clientify-page-number"';
									echo '<a' . $class . ' href="' . $page_url . '">' . esc_html($i) . '</a>';
								}
								echo '</div>';
							}
						}

						echo '</div>';
						echo '</div>';

						// Modal de detalle del carrito.
						echo '<div id="clientify-cart-modal" class="clientify-modal" style="display:none;">';
						echo '<div class="clientify-modal-overlay"></div>';
						echo '<div class="clientify-modal-content">';
						echo '<button type="button" class="clientify-modal-close" aria-label="' . esc_attr__('Cerrar', 'clientify') . '">&times;</button>';
						echo '<h3>' . esc_html__('Detalle del carrito', 'clientify') . ' <span id="clientify-modal-cart-id"></span></h3>';
						echo '<table class="clientify-modal-table"><tbody>';
						echo '<tr><th>' . esc_html__('Cliente', 'clientify') . '</th><td id="clientify-modal-name"></td></tr>';
						echo '<tr><th>' . esc_html__('Email', 'clientify') . '</th><td id="clientify-modal-email"></td></tr>';
						echo '<tr><th>' . esc_html__('Teléfono', 'clientify') . '</th><td id="clientify-modal-phone"></td></tr>';
						echo '<tr><th>' . esc_html__('Dirección', 'clientify') . '</th><td id="clientify-modal-address"></td></tr>';
						echo '<tr><th>' . esc_html__('Cupón', 'clientify') . '</th><td id="clientify-modal-coupon"></td></tr>';
						echo '<tr><th>' . esc_html__('Envío', 'clientify') . '</th><td id="clientify-modal-shipping"></td></tr>';
						echo '<tr><th>' . esc_html__('Total', 'clientify') . '</th><td id="clientify-modal-total"></td></tr>';
						echo '<tr><th>' . esc_html__('Estado', 'clientify') . '</th><td id="clientify-modal-status"></td></tr>';
						echo '<tr><th>' . esc_html__('Suscrito', 'clientify') . '</th><td id="clientify-modal-unsubscribed"></td></tr>';
						echo '<tr><th>' . esc_html__('Checkout ID', 'clientify') . '</th><td id="clientify-modal-checkout-id"></td></tr>';
						echo '<tr><th>' . esc_html__('Session ID', 'clientify') . '</th><td id="clientify-modal-session-id"></td></tr>';
						echo '<tr><th>' . esc_html__('Fecha', 'clientify') . '</th><td id="clientify-modal-date"></td></tr>';
						echo '</tbody></table>';
						echo '<h4>' . esc_html__('Productos', 'clientify') . '</h4>';
						echo '<ul id="clientify-modal-products"></ul>';
						echo '</div>';
						echo '</div>';

						// Modal de confirmación para limpiar carritos.
						echo '<div id="clientify-clean-carts-modal" class="clientify-modal" style="display:none;">';
						echo '<div class="clientify-modal-overlay"></div>';
						echo '<div class="clientify-modal-content">';
						echo '<button type="button" class="clientify-modal-close" aria-label="' . esc_attr__('Cerrar', 'clientify') . '">&times;</button>';
						echo '<h3>' . esc_html__('Limpiar carritos abandonados', 'clientify') . '</h3>';
						echo '<p>' . esc_html__('Esta acción elimina definitivamente los registros de la tabla de carritos abandonados. No afecta a los pedidos ya completados.', 'clientify') . '</p>';
						echo '<div class="clientify-clean-carts-options">';
						echo '<label><input type="radio" name="clientify_clean_type" value="days" checked> ' . esc_html__('Eliminar carritos con más de', 'clientify') . ' <input type="number" id="clientify-clean-days" value="30" min="1" style="width:60px;"> ' . esc_html__('días', 'clientify') . '</label>';
						echo '<label><input type="radio" name="clientify_clean_type" value="all"> ' . esc_html__('Eliminar todos los carritos', 'clientify') . '</label>';
						echo '</div>';
						echo '<div class="clientify-modal-actions">';
						echo '<button type="button" class="button clientify-modal-close">' . esc_html__('Cancelar', 'clientify') . '</button>';
						echo '<button type="button" id="clientify-clean-carts-confirm" class="button button-primary">' . esc_html__('Eliminar', 'clientify') . '</button>';
						echo '</div>';
						echo '</div>';
						echo '</div>';
						break;

				endswitch;
			?>
		</div>
		<div class="sub_version">
			<p>Clientify E-commerce version <?php echo CLIENTIFY_ADDONS_VERSION ?></p>
		</div>
	</div>


<?php

}
