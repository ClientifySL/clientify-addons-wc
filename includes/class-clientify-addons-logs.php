<?php
/**
 * Clase para manejar los logs del plugin.
 *
 * @package    Clientify_Addons
 * @subpackage Clientify_Addons/includes
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Clientify_Addons_Logs {
    

        private static function is_plugin_file($file_path) {
            return strpos($file_path, 'wp-content/plugins/clientify-addons') !== false;
        }
    
        /**
         * Insert a log in the database.
         */
        public static function insert_log($level, $message, $file = '', $error_line = 0) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'clientify_logs';

            // Create table if it doesn't exist yet (e.g. plugin activated before this version)
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                $charset_collate = $wpdb->get_charset_collate();
                dbDelta( "CREATE TABLE IF NOT EXISTS $table_name (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                    level varchar(50) NOT NULL,
                    message text NOT NULL,
                    file varchar(255),
                    error_line mediumint(9),
                    PRIMARY KEY (id)
                ) $charset_collate;" );
            }

            $result = $wpdb->insert(
                $table_name,
                array(
                    'timestamp'  => current_time('mysql'),
                    'level'      => $level,
                    'message'    => $message,
                    'file'       => $file,
                    'error_line' => (int) $error_line,
                ),
                array( '%s', '%s', '%s', '%s', '%d' )
            );

            return $result;
        }
    
        /**
         * Handle PHP errors.
         */
        private static $handling_error = false;

        public static function handle_errors_php($errno, $errstr, $errfile, $errline) {
            if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                return false; // Never interfere with REST API responses
            }
            if (!self::is_plugin_file($errfile)) {
                return false; // Ignorar si no es de nuestro plugin
            }
            if ( self::$handling_error ) {
                return true; // Evitar recursión infinita
            }
            self::$handling_error = true;
            $level = self::get_error_level($errno);
            self::insert_log($level, $errstr, $errfile, $errline);
            self::$handling_error = false;
            return true; // Indicar a PHP que el error fue manejado — no imprimir al output
        }
        /**
         * Handling uncaught exceptions.
         */
        public static function handle_exceptions($exception) {
            $file = $exception->getFile();
            if (!self::is_plugin_file($file)) {
                return false;
            }
            self::insert_log('EXCEPTION', $exception->getMessage(), $file, $exception->getLine());
        }
    
        /**
         * Handle PHP fatal errors..
         */
        public static function handling_fatal_errors() {
            if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                return;
            }
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                if (!self::is_plugin_file($error['file'])) {
                    return false;
                }
                $level = self::get_error_level($error['type']);
                self::insert_log($level, $error['message'], $error['file'], $error['line']);
            }
        }
    
        /**
         * Get error level as text.
         */
        private static function get_error_level($errno) {
            $levels = [
                E_ERROR => 'ERROR',
                E_WARNING => 'WARNING',
                E_PARSE => 'PARSE',
                E_NOTICE => 'NOTICE',
                E_CORE_ERROR => 'CORE_ERROR',
                E_CORE_WARNING => 'CORE_WARNING',
                E_COMPILE_ERROR => 'COMPILE_ERROR',
                E_COMPILE_WARNING => 'COMPILE_WARNING',
                E_USER_ERROR => 'USER_ERROR',
                E_USER_WARNING => 'USER_WARNING',
                E_USER_NOTICE => 'USER_NOTICE',
                E_STRICT => 'STRICT',
                E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
                E_DEPRECATED => 'DEPRECATED',
                E_USER_DEPRECATED => 'USER_DEPRECATED',
            ];
            return $levels[$errno] ?? 'UNKNOWN';
        }

        public function handle_errors_wp($function) {
            return function($message, $title = '', $args = array()) {
                self::insert_log('WP_DIE', $message);
                die($message); // O manejarlo de otra forma
            };
        }

        public static function cleanup_logs($days = 5) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'clientify_logs';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE timestamp < NOW() - INTERVAL %d DAY", 
                $days
            ));
        }
    
}
