<?php
/**
 * Autoloader for the plugin classes.
 *
 * @package IndoorTech\CategoryPromotions
 */

namespace IndoorTech\CategoryPromotions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Autoloader
 */
class Autoloader {
    /**
     * Register autoloader.
     */
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload classes.
     *
     * @param string $class Class name.
     */
    public static function autoload( $class ) {
        $prefix   = __NAMESPACE__ . '\\';
        $base_dir = plugin_dir_path( dirname( __FILE__ ) );

        if ( strpos( $class, $prefix ) !== 0 ) {
            return;
        }

        $relative_class = substr( $class, strlen( $prefix ) );
        $relative_class = str_replace( '\\', '/', $relative_class );
        $file           = $base_dir . 'includes/' . $relative_class . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
