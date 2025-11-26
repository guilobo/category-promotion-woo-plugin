<?php
/**
 * Main plugin bootstrap.
 *
 * @package IndoorTech\CategoryPromotions
 */

namespace IndoorTech\CategoryPromotions;

use IndoorTech\CategoryPromotions\Admin\AdminPage;
use IndoorTech\CategoryPromotions\Processors\PromotionProcessor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Plugin
 */
class Plugin {
    const VERSION = '1.0.0';

    /**
     * Initialize hooks.
     */
    public function init() {
        $this->define_constants();
        $this->load_textdomain();

        add_action( 'init', array( $this, 'maybe_load_dependencies' ) );
        add_action( 'admin_init', array( $this, 'register_assets' ) );
        add_action( 'admin_menu', array( $this, 'register_menu' ) );

        $processor = new PromotionProcessor();
        $processor->register_hooks();
    }

    /**
     * Define constants for the plugin.
     */
    private function define_constants() {
        if ( ! defined( 'INDOORTECH_CP_VERSION' ) ) {
            define( 'INDOORTECH_CP_VERSION', self::VERSION );
        }
        if ( ! defined( 'INDOORTECH_CP_PATH' ) ) {
            define( 'INDOORTECH_CP_PATH', plugin_dir_path( dirname( __FILE__ ) ) );
        }
        if ( ! defined( 'INDOORTECH_CP_URL' ) ) {
            define( 'INDOORTECH_CP_URL', plugin_dir_url( dirname( __FILE__ ) ) );
        }
    }

    /**
     * Load plugin text domain.
     */
    private function load_textdomain() {
        load_plugin_textdomain( 'indoortech-category-promotions', false, dirname( plugin_basename( INDOORTECH_CP_PATH . 'category-promotions-for-woocommerce.php' ) ) . '/languages' );
    }

    /**
     * Ensure WooCommerce is active.
     */
    public function maybe_load_dependencies() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
        }
    }

    /**
     * Display a notice if WooCommerce is missing.
     */
    public function woocommerce_missing_notice() {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Category Promotions for WooCommerce requires WooCommerce to be installed and active.', 'indoortech-category-promotions' ) . '</p></div>';
        }
    }

    /**
     * Register admin assets.
     */
    public function register_assets() {
        wp_register_script(
            'indoortech-category-promotions-admin',
            INDOORTECH_CP_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            self::VERSION,
            true
        );

        wp_register_style(
            'indoortech-category-promotions-admin',
            INDOORTECH_CP_URL . 'assets/css/admin.css',
            array(),
            self::VERSION
        );
    }

    /**
     * Register admin menu.
     */
    public function register_menu() {
        $admin_page = new AdminPage();
        $admin_page->register_menu();
    }
}
