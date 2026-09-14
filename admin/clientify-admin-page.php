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
		
				endswitch;
			?>
		</div>
		<div class="sub_version">
			<p>Clientify E-commerce version <?php echo CLIENTIFY_ADDONS_VERSION ?></p>
		</div>
	</div>


<?php

}
