<?php
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-clientify-api-connect.php';
function clientify_settings_page()
{
	$api = new Clientify_Api;
	$tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
?>
	<div class="conten">
		<input type="hidden" id="api" name="api" value="<?php echo $api->api_url; ?>">
		<header>
			<img src="../wp-content/plugins/clientify-addons-wc/public/img/clientify.svg" alt="Clientify" class="logo-clientify-responsive">
			<p>Gestiona y automatiza tu Marketing y Ventas fácilmente.</p>
		</header>

		<nav class="nav-tab-wrapper">
			<a href="?page=clientify-addons/includes/class_clientify_plugin_core.php&tab=settings" class="nav-tab color-nav <?php if ($tab === 'settings') : ?>nav-tab-active<?php endif; ?>">
				<span class="dashicons dashicons-admin-generic"></span><?php _e('Settings', 'clientify'); ?></a>
		</nav>
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
							<label for="key"><?php _e('Clientify Api Key', 'clientify'); ?></label>
						</div>

						<div class="controls">
							<input type="text" id="storekey" class="floatLabel" name="CLIENTIFY_STORE_KEY" value="<?php echo esc_attr(get_option('CLIENTIFY_STORE_KEY')); ?>" readonly>
							<label for="storekey"><?php _e('Store Key', 'clientify'); ?></label>
							<div class="message"></div>
						</div>
					</div>
					
					<div class="form-group hide_div" id="other_config">
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
						<div class="controls">
							<select name="CLIENTIFY_CART_HOUR" id="CLIENTIFY_CART_HOUR" class="floatLabel">
								<option value="">
								<option value="<?php echo get_option('CLIENTIFY_CART_HOUR')?>" 
												<?php if ( get_option('CLIENTIFY_CART_HOUR') != '' ) {
														echo 'selected';
														}  
												?>
									>
									Luego de
									<?php echo get_option('CLIENTIFY_CART_HOUR'); ?> 
									Horas
								</option>

								<?php for ($i = 1; $i < 25; $i++) {
									echo '<option value="' . $i . '">Luego de ' . $i . ' Horas</option>';
								}	?>
							</select>
							<label for="fruit">Tiempo Carro Abandonado</label>
						</div>
						<div class="controls">
							<input type="text" id="url" class="floatLabel" name="URL_BASE" value="<?php echo $api_url ?>" readonly>
							<label for="url"><?php _e('API Url', 'clientify'); ?></label>
						</div>
					</div>
				</form>

				<div class='general'>
					<div class="form-group">
						<button id="connect" class="connect-class" data-loading-text="Connecting">Conectar</button>
						<button id="disconnect" class="disconnect-class">Desconectar</button>
						<div class="controls">
						</div>
					</div>
				</div>
				<?php
				break;
			endswitch;
		?>
		<div class="sub_version">
			<p>Clientify E-commerce version 0.0.1</p>
		</div>
	</div>


<?php
}
