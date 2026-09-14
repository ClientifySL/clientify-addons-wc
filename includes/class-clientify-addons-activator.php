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
     * @since    1.1.0
     */
    public static function activate()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


        // Cart abandonment tracking db sql command.
        $table_abandoned_CA = $wpdb->prefix . 'clientify_ca_cart_abandonment';
        $sql = "CREATE TABLE IF NOT EXISTS $table_abandoned_CA (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            checkout_id int(11) NOT NULL,
            email VARCHAR(100),
            cart_contents LONGTEXT,
            cart_total DECIMAL(10,2),
            session_id VARCHAR(60) NOT NULL,
            other_fields LONGTEXT,
            order_status ENUM( 'normal','abandoned','completed','lost') NOT NULL DEFAULT 'normal',
            unsubscribed  boolean DEFAULT 0,
            coupon_code VARCHAR(50),
                time DATETIME DEFAULT NULL,
            PRIMARY KEY  (`id`, `session_id`),
            UNIQUE KEY `session_id_UNIQUE` (`session_id`)
        ) $charset_collate;\n";
        dbDelta( $sql );


         // Crear tabla para almacenar logs
         $table_logs = $wpdb->prefix . 'clientify_logs';
         $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
             id mediumint(9) NOT NULL AUTO_INCREMENT,
             timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
             level varchar(50) NOT NULL,
             message text NOT NULL,
             file varchar(255),
             error_line mediumint(9),
             PRIMARY KEY (id)
         ) $charset_collate;\n";
         dbDelta($sql_logs);
        

        /* set default time Abandoned Card  */
        update_option('CLIENTIFY_STATUS', 0);
        update_option('CLIENTIFY_GDPR', 0);
        update_option('CLIENTIFY_GDPR_TEXT', "Acepto el envío de comunicaciones comerciales y promociones. ");
        $order_status = array("wc-completed", "wc-processing", "wc-on-hold");
        update_option('CLIENTIFY_ORDER_STATUS', $order_status);

    }

    







}
