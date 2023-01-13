<?php
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/GetDataClientify.php';



$query_orders = new RegisterDataClientify;

function clientify_settings_page()
{
	$tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
?>
	<div class="conten">
		<header>
			<img src="../wp-content/plugins/clientify-addons/public/img/clientify.svg" alt="Clientify" class="logo-clientify-responsive">
			<!-- <h1>Clientify <span>with Forms</span></h1> -->
			<p>Gestiona y automatiza tu Marketing y Ventas fácilmente.</p>
		</header>
		<nav class="nav-tab-wrapper">
			<a href="?page=clientify-addons/includes/RegisterCustomPostType.php&tab=settings" class="nav-tab color-nav <?php if ($tab === 'settings') : ?>nav-tab-active<?php endif; ?>">
				<span class="dashicons dashicons-admin-generic"></span><?php _e('Settings', 'clientify'); ?></a>
			<a href="?page=clientify-addons/includes/RegisterCustomPostType.php&tab=logs" class="nav-tab color-nav <?php if ($tab === 'logs') : ?>nav-tab-active<?php endif; ?>">
				<span class="dashicons dashicons-media-document"></span><?php _e('Logs', 'clientify'); ?></a>
		</nav>
		<?php
		$api_url  = get_rest_url();
		$del = str_contains($api_url, '/wp-json/') ? '' : '';
		$api_url = $api_url . 'clientify/v1/' . $del;
		switch ($tab):
			case 'settings': ?>
				<div class='general'>
					<input type="hidden" id="status_clientify" name="CLIENTIFY_STATUS" value="<?php echo esc_attr(get_option('CLIENTIFY_STATUS')); ?>">
					<div class="form-group">
						<!-- <h2 class="heading">Configuracion General</h2> -->

					</div>

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
							<!-- <button id="updateStoreKey" class="btn-update-store-key">Update Store Key</button> -->
						</div>
						<div class="controls">
							<input type="text" id="storekey" class="floatLabel" name="CLIENTIFY_STORE_KEY" value="<?php echo esc_attr(get_option('CLIENTIFY_STORE_KEY')); ?>" readonly>
							<label for="storekey"><?php _e('Store Key', 'clientify'); ?></label>
							<div class="message"></div>
							<!-- <button id="updateStoreKey" class="custom-class">Update Store Key</button> -->
						</div>
						<div class="controls">
							<input type="text" id="url" class="floatLabel" name="URL_BASE" value="<?php echo $api_url ?>" readonly>
							<label for="url"><?php _e('API Url', 'clientify'); ?></label>
						</div>
					</div>
					<div class=" form-group">
						<h2 class="heading">Otras Configuraciones</h2>
						<!-- <div class="controls">
							<input type="text" id="key" class="floatLabel" name="CLIENTIFY_API_KEY" value="<?php echo esc_attr(get_option('CLIENTIFY_API_KEY')); ?>">
							<label for="key"><?php _e('Key', 'clientify'); ?></label>

						</div> -->
						<!-- <div class="radio-but">
							<spam class="clientify-label"><?php //_e('Request Log', 'clientify'); 
															?></spam>
							<div class="onoffswitch">
								<input type="checkbox" name="CLIENTIFY_API_LOG" class="onoffswitch-checkbox" id="myonoffswitch2" <?php if (get_option('CLIENTIFY_API_LOG') == 'on') {
																																		echo "checked='checked'";
																																	} ?>>
								<label class="onoffswitch-label" for="myonoffswitch2">
									<div class="onoffswitch-inner">
										<div class="onoffswitch-active">
											<div class="onoffswitch-switch"><?php // _e('Yes', 'clientify'); 
																			?></div>
										</div>
										<div class="onoffswitch-inactive">
											<div class="onoffswitch-switch"><?php //_e('No', 'clientify'); 
																			?></div>
										</div>
									</div>
								</label>
							</div>
						</div> -->
						<div class="controls">
							<?php $order_statuses_clientify = wc_get_order_statuses(); ?>

							<select name="CLIENTIFY_ORDER_STATUS" id="CLIENTIFY_ORDER_STATUS" class="floatLabel">
								<?php foreach ($order_statuses_clientify as $key => $order_status) : ?>
									<option value="<?php echo $key ?>" <?php if (get_option('CLIENTIFY_ORDER_STATUS') == $key) { ?> selected <?php } ?>>
										<?php echo $order_status ?>
									</option>
								<?php endforeach; ?>
							</select>
							<label for="orderstatus"><?php _e('Order Status', 'clientify'); ?></label>
						</div>

						<div class="controls">
							<select name="CLIENTIFY_CART_HOUR" id="CLIENTIFY_CART_HOUR" class="floatLabel" style="pointer-events: none;" readonly>
								<option value="">
								<option value="<?php echo get_option('CLIENTIFY_CART_HOUR')
												?>" <?php if (get_option('CLIENTIFY_CART_HOUR') != '') {
														echo 'selected';
													}  ?>>
									Luego de
									<?php echo get_option('CLIENTIFY_CART_HOUR');
									?> Horas
								</option>
								<?php for ($i = 1; $i < 25; $i++) {
									echo '<option value="' . $i . '">Luego de ' . $i . ' Horas</option>';
								}	?>
							</select>
							<label for="fruit">Tiempo Carro Abandonado</label>
						</div>
					</div>
					<!--  More -->
					<!-- <div class="form-group">
						<h2 class="heading">Script</h2>
						<div class="controls">
							<textarea name="CLIENTIFY_SCRIPT" id="CLIENTIFY_SCRIPT" class="floatLabel" id="comments"><?php //echo esc_attr(get_option('CLIENTIFY_SCRIPT')); ?></textarea>
							<label for="comments">Script Analitics</label>
							<?php //submit_button('Conectar', 'custom-class'); 
							?>
						</div>
					</div>  -->
					<!--  More -->
					<!-- <div class="form-group">
						<h2 class="heading">Utilidades</h2>
						
					</div> -->
				</form>
				<div class='general'>
					<div class="form-group">
						<button id="connect" class="connect-class" data-loading-text="Connecting">Conectar</button>
						<button id="disconnect" class="disconnect-class">Desconectar</button>
						<div class="controls">
						</div>
					</div>
				</div>
				<!-- <div class="controls">
					<button type="button" class="sync_customers custom-class-utils button button-primary" name="sync_customers" data-loading-text="Syncing">
						<?php // _e('Sync Customers', 'clientify');
						?>
					</button>
				</div> -->
			<?php

				//Add crom lost cart
				/*if ($api_key = get_option('CLIENTIFY_API_KEY')) {
							manage_clientify_cron('add');
						}*/
				break;

			case 'logs':
				global $wpdb;
				$customer_logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}clientify_customer ORDER BY date_add DESC LIMIT 10");
				$abandoned_cart_logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}clientify_abandoned_cart ORDER BY date_add DESC LIMIT 10");
				//$order_logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}clientify_order ORDER BY date_add DESC");
				$get_type_orders = get_option('CLIENTIFY_ORDER_STATUS');
				//var_dump($get_type_orders);
				$order_logs = $wpdb->get_results("SELECT DISTINCT pm.meta_value AS user_id,pm.post_id AS order_id,cli.clientify_id,
														p.post_status AS  type_orders , p.post_date AS  date_order
    													FROM {$wpdb->prefix}postmeta AS pm
														LEFT JOIN {$wpdb->prefix}posts AS p  ON pm.post_id = p.ID
														LEFT JOIN {$wpdb->prefix}postmeta AS pm2  ON p.ID = pm2.post_id
														LEFT JOIN {$wpdb->prefix}clientify_customer AS cli  ON cli.id_customer = pm.meta_value
														WHERE p.post_type = 'shop_order' AND pm.meta_key = '_customer_user' AND p.post_status = '{$get_type_orders}' 
														ORDER BY pm.meta_value ASC, pm.post_id DESC LIMIT 10
					");
			?>
				<form class="form-horizontal clientify-log-form">
					<legend><span class="dashicons dashicons-buddicons-buddypress-logo"></span><?php _e('Customer', 'clientify'); ?></legend>

					<table class="wp-list-table widefat fixed striped table-view-list posts">
						<thead>
							<th><?php _e('Customer #', 'clientify'); ?></th>
							<th><?php _e('Clientify ID', 'clientify'); ?></th>
							<th><?php _e('Date Time', 'clientify'); ?></th>
						</thead>
						<tbody>
							<?php
							if ($customer_logs) :
								foreach ($customer_logs as $customer) : ?>
									<tr>
										<td><?php echo $customer->id_customer ?></td>
										<td><?php echo $customer->clientify_id; ?></td>
										<td><?php echo $customer->date_add; ?></td>
									</tr>
								<?php endforeach;
							else : ?>
								<tr>
									<td colspan="3"><?php _e('No Data', 'clientify'); ?></td>
								</tr>
							<?php endif;

							?>
						</tbody>
					</table>
				</form>

				<form class="form-horizontal clientify-log-form">
					<legend><span class="dashicons dashicons-cart"></span><?php _e('Abandoned Cart', 'clientify'); ?></legend>

					<table class="wp-list-table widefat fixed striped table-view-list posts">
						<thead>
							<th><?php _e('Clientify ID', 'clientify'); ?></th>
							<th><?php _e('Date Time', 'clientify'); ?></th>
						</thead>
						<tbody>
							<?php
							if ($abandoned_cart_logs) :
								foreach ($abandoned_cart_logs as $cart) : ?>
									<tr>
										<td><?php echo $cart->clientify_id; ?></td>
										<td><?php echo $cart->date_add; ?></td>
									</tr>
								<?php endforeach;
							else : ?>
								<tr>
									<td colspan="3"><?php _e('No Data', 'clientify'); ?></td>
								</tr>
							<?php endif;

							?>
						</tbody>
					</table>
				</form>


				<form class="form-horizontal clientify-log-form">
					<legend><span class="dashicons dashicons-list-view"></span><?php _e('Order', 'clientify'); ?></legend>

					<table class="wp-list-table widefat fixed striped table-view-list posts">
						<thead">
							<th><?php _e('Order #', 'clientify'); ?></th>
							<th><?php _e('Clientify ID', 'clientify'); ?></th>
							<th><?php _e('Date Time', 'clientify'); ?></th>
							</thead>
							<tbody>
								<?php

								if ($order_logs) :
									foreach ($order_logs as $order) : ?>
										<tr>
											<td><?php echo $order->order_id; ?></td>
											<td><?php echo $order->clientify_id; ?></td>
											<td><?php echo $order->date_order; ?></td>
										</tr>
									<?php endforeach;
								else : ?>
									<tr>
										<td colspan="3"><?php _e('No Data', 'clientify'); ?></td>
									</tr>
								<?php endif;

								?>
							</tbody>
					</table>
				</form>
		<?php
				break;
		endswitch;
		?>

		<div class="sub_version">
			<p>Clientify E-commerce version 1.0.0</p>
		</div>
	</div>


<?php
}
