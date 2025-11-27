<?php
/**
 * Plugin Name: Category Promotions for WooCommerce
 * Description: Create bulk percentage discount promotions by product category with batched processing and a progress bar.
 * Version: 1.0.0
 * Author: Guilherme Lobo
 * Author URI: https://indoortech.com.br
 * Plugin URI: https://indoortech.com.br
 * Text Domain: indoortech-category-promotions
 * Domain Path: /languages
 * License: GPLv2 or later
 *
 * @package Indoortech\CategoryPromotions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/Autoloader.php';

IndoorTech\CategoryPromotions\Autoloader::register();

function indoortech_category_promotions_run() {
    $plugin = new IndoorTech\CategoryPromotions\Plugin();
    $plugin->init();
}

add_action( 'plugins_loaded', 'indoortech_category_promotions_run' );
