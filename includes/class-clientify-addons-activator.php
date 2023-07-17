<?php

/**
 * Fired during plugin activation
 *
 * @link       Emerson Ramirez
 * @since      0.0.1
 *
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.0.1
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/includes
 * @author     Emerson Ramirez <ramirezemerson1991@gmail.com>
 */
class Clientify_Addons_Activator
{

   
    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $table_cart = $wpdb->prefix . 'cart';

        $charset_collate = $wpdb->get_charset_collate();

        $cart_table_sql = "CREATE TABLE IF NOT EXISTS $table_cart (
            `id_cart` int(10) NOT NULL AUTO_INCREMENT,
            `cookie_cart_id` varchar(128) DEFAULT NULL ,
            `id_customer` int(10) DEFAULT NULL,
            `id_product` int(10) NOT NULL,
            `quantity` int(10) NOT NULL,
            `currency` varchar(32) NOT NULL,
            `language` varchar(32) NOT NULL,
            `date_add` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `date_upd` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_cart`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
        dbDelta($cart_table_sql);

        $table_clientify_abandoned_cart = $wpdb->prefix . 'clientify_abandoned_cart';

        $clientify_abandoned_cart_sql = "CREATE TABLE IF NOT EXISTS $table_clientify_abandoned_cart (
            `id_clientify_abandoned_cart` int(11) NOT NULL AUTO_INCREMENT,
            `cookie_cart_id` varchar(128) DEFAULT NULL,
            `clientify_id` int(128) DEFAULT NULL,
            `date_add` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_clientify_abandoned_cart`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        dbDelta($clientify_abandoned_cart_sql);

        $table_clientify_customer = $wpdb->prefix . 'clientify_customer';

        $clientify_customer_sql = "CREATE TABLE IF NOT EXISTS $table_clientify_customer (
            `id_clientify_customer` int(11) NOT NULL AUTO_INCREMENT,
            `id_customer` int(11) NOT NULL,
            `clientify_id` int(11) DEFAULT NULL,
            `date_add` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_clientify_customer`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        dbDelta($clientify_customer_sql);

        // if duplicate records exist, they are deleted
        $clean_old_dat_dupply = "DELETE t1 FROM $table_clientify_customer t1 INNER JOIN $table_clientify_customer t2 
                                WHERE t1.id_clientify_customer  > t2.id_clientify_customer  
                                AND t1.clientify_id = t2.clientify_id";
        $wpdb->query($clean_old_dat_dupply);

        $table_clientify_visitor_cart = $wpdb->prefix . 'clientify_visitor_cart';

        $clientify_visitor_cart_sql = "CREATE TABLE IF NOT EXISTS $table_clientify_visitor_cart (
            `id_clientify_visitor_cart` int(11) NOT NULL AUTO_INCREMENT,
            `cookie_cart_id` varchar(32) NOT NULL,
            `visitor_key` varchar(64) NOT NULL,
            `date_add` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_clientify_visitor_cart`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        dbDelta($clientify_visitor_cart_sql);

        $alter_col_size = $clientify_visitor_cart_sql = "ALTER TABLE $table_clientify_visitor_cart CHANGE `visitor_key` `visitor_key` VARCHAR(64)";
        dbDelta($alter_col_size);

        // $alter_col_visitor = $clientify_abandoned_cart_sql = "ALTER TABLE $table_clientify_abandoned_cart ADD COLUMN `id_customer` INT(11) NULL DEFAULT NULL AFTER `cookie_cart_id`";
        // dbDelta($alter_col_visitor );

        $table_name = $wpdb->prefix . "clientify_abandoned_cart";
        $sql = "ALTER TABLE $table_name
                        ADD COLUMN `id_customer` INT(11) NULL DEFAULT NULL AFTER cookie_cart_id";
        $wpdb->query($sql);

        if (is_plugin_active('woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php')) {

            $table_cart = $wpdb->prefix . 'cart';
            $table_abandoned = $wpdb->prefix . 'clientify_abandoned_cart';
            $max_cart_number = $wpdb->get_var( "SELECT MAX(id_cart) FROM $table_cart" );
            $max_abandoned_number = $wpdb->get_var( "SELECT MAX(id_clientify_abandoned_cart) FROM $table_abandoned" );
            
            $wpdb->delete( $table_cart, array('id_customer' => null), array( '%d' ) );
            $wpdb->delete( $table_abandoned, array('id_customer' => null), array( '%d' ) );

            $wpdb->query( "ALTER TABLE $table_cart AUTO_INCREMENT = " . (int) $max_cart_number );
            $wpdb->query( "ALTER TABLE $table_abandoned AUTO_INCREMENT = " . (int) $max_abandoned_number );

            $sql = "SELECT session_id  FROM ".$wpdb->prefix ."cartflows_ca_cart_abandonment wccca";
            $data = $wpdb->get_results($sql);        
            foreach ( $data as $data_cartflow ) {
                $cart_flow = Cartflows_Ca_Helper::get_instance()->get_checkout_details( $data_cartflow->session_id );
                
                $cart_content = maybe_unserialize( $cart_flow->cart_contents );
                $date = $cart_flow->time;
                $user = get_user_by( 'email', $cart_flow->email );

                if (!$user->id) {
                    $sql = "SELECT user_id FROM ".$wpdb->prefix ."usermeta WHERE meta_key = 'billing_email' AND meta_value = '".$cart_flow->email."' LIMIT 1 ";
                    $data = $wpdb->get_results($sql);
                    $user_id = $data[0]->user_id;
                }else {
                    $user_id = $user->id;
                }
                $cart_item_data = array();
				if (is_array($cart_content) || is_object($cart_content) ) {
                    foreach ( $cart_content as $cart_item ){
                        
                        $cart_item_data = array(

                            'id_customer' => $user_id,
                            'id_product'  => $cart_item['product_id'],
                            'quantity'    => $cart_item['quantity'],
                            'currency'    => get_option('woocommerce_currency'),
                            'language'    => get_bloginfo("language"),
                            'date_add'    => $date,
                            
                        );
                        $sql = "SELECT *  FROM ".$wpdb->prefix ."cart WHERE id_customer=".$user_id." AND id_product=".$cart_item['product_id']."";
                        $data = $wpdb->get_results($sql);
                        
                            if ( $data[0]->id_customer == $user_id && $data[0]->id_product == $cart_item['product_id'] ) {
                                // Condición para la actualización (por ejemplo, basada en un identificador único)
                                $condicion = array(
                                    'id_customer' => $user_id,
                                    'id_product'  => $cart_item['product_id'],
                                );
                                $wpdb->update($wpdb->prefix . "cart", $cart_item_data, $condicion);
                                
                            }else {
                                $wpdb->insert($wpdb->prefix . "cart", $cart_item_data);
                            }
					}  
				}
            }
        }

        /* set default time Abandoned Card  */
        update_option('CLIENTIFY_STATUS', 0);
        update_option('CLIENTIFY_CART_HOUR', 6);
        $order_status = array("wc-completed", "wc-processing");
        update_option('CLIENTIFY_ORDER_STATUS', $order_status);

        function myprefix_custom_cron_schedule($schedules)
        {
            $schedules['every_six_hours'] = array(
                'interval' => 5900, //5900 Every  hours
                'display'  =>  __('5900 segundos'),
            );
            return $schedules;
        }
        add_filter('cron_schedules', 'myprefix_custom_cron_schedule');

        //Schedule an action if it's not already scheduled
        if ( !wp_next_scheduled('clientify_job') ) {
            wp_schedule_event(current_time('timestamp'), 'hourly', 'clientify_job');
        }
    }

    







}
