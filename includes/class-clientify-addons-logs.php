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
    

        //code...
    
        /**
         * Insert a log in the database.
         */
        public static function insert_log($level, $message, $file = '', $error_line = 0) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'clientify_logs';
    
            try {
                $wpdb->insert(
                    $table_name,
                    array(
                        'timestamp' => current_time('mysql'),
                        'level' => $level,
                        'message' => $message,
                        'file' => $file,
                        'error_line' => $error_line,
                    )
                );
            } catch (Exception $e) {
                return false;
                
            }
        }
    
        /**
         * Handle PHP errors.
         */
        public static function handle_errors_php($errno, $errstr, $errfile, $errline) {
            $level = self::get_error_level($errno);
            self::insert_log($level, $errstr, $errfile, $errline);
        }
    
        /**
         * Handling uncaught exceptions.
         */
        public static function handle_exceptions($exception) {
            self::insert_log('EXCEPTION', $exception->getMessage(), $exception->getFile(), $exception->getLine());
        }
    
        /**
         * Handle PHP fatal errors..
         */
        public static function handling_fatal_errors() {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
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
    
}
