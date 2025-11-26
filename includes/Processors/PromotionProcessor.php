<?php
/**
 * Handle AJAX promotion processing.
 *
 * @package IndoorTech\CategoryPromotions\Processors
 */

namespace IndoorTech\CategoryPromotions\Processors;

use IndoorTech\CategoryPromotions\Services\PromotionService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class PromotionProcessor
 */
class PromotionProcessor {
    const NONCE_ACTION = 'indoortech_category_promotions_nonce';
    const TRANSIENT_KEY = 'indoortech_cp_queue_';
    const BATCH_SIZE = 5;

    /**
     * Register AJAX hooks.
     */
    public function register_hooks() {
        add_action( 'wp_ajax_itcp_start_promotion', array( $this, 'start_promotion' ) );
        add_action( 'wp_ajax_itcp_process_batch', array( $this, 'process_batch' ) );
    }

    /**
     * Start promotion processing: build queue and store it.
     */
    public function start_promotion() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'indoortech-category-promotions' ) ), 403 );
        }

        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $categories = isset( $_POST['categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['categories'] ) ) : array();
        $discount   = isset( $_POST['discount'] ) ? floatval( wp_unslash( $_POST['discount'] ) ) : 0;
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
        $end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';

        if ( empty( $categories ) || $discount <= 0 || $discount >= 100 || empty( $start_date ) || empty( $end_date ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'indoortech-category-promotions' ) ), 400 );
        }

        $product_ids = $this->get_products_by_categories( $categories );

        if ( empty( $product_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'No products found for the selected categories.', 'indoortech-category-promotions' ) ), 400 );
        }

        $queue = array(
            'product_ids' => array_values( array_unique( $product_ids ) ),
            'discount'    => $discount,
            'start_date'  => $start_date,
            'end_date'    => $end_date,
            'processed'   => 0,
            'total'       => count( $product_ids ),
        );

        set_transient( $this->get_queue_key(), $queue, HOUR_IN_SECONDS * 12 );

        wp_send_json_success(
            array(
                'total'     => $queue['total'],
                'processed' => 0,
                'message'   => __( 'Promotion initialization complete. Starting batch processing.', 'indoortech-category-promotions' ),
            )
        );
    }

    /**
     * Process a batch of products.
     */
    public function process_batch() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'indoortech-category-promotions' ) ), 403 );
        }

        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $queue = get_transient( $this->get_queue_key() );

        if ( empty( $queue ) || ! isset( $queue['product_ids'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No queue found. Please restart the process.', 'indoortech-category-promotions' ) ), 400 );
        }

        $service     = new PromotionService();
        $product_ids = array_splice( $queue['product_ids'], 0, self::BATCH_SIZE );

        foreach ( $product_ids as $product_id ) {
            $service->apply_promotion( $product_id, $queue['discount'], $queue['start_date'], $queue['end_date'] );
            $queue['processed']++;
        }

        $complete = empty( $queue['product_ids'] );

        if ( $complete ) {
            delete_transient( $this->get_queue_key() );
        } else {
            set_transient( $this->get_queue_key(), $queue, HOUR_IN_SECONDS * 12 );
        }

        $percentage = $queue['total'] > 0 ? floor( ( $queue['processed'] / $queue['total'] ) * 100 ) : 100;

        wp_send_json_success(
            array(
                'processed' => $queue['processed'],
                'total'     => $queue['total'],
                'percentage'=> $percentage,
                'complete'  => $complete,
                'message'   => $complete ? __( 'All products have been updated.', 'indoortech-category-promotions' ) : __( 'Processing next batch...', 'indoortech-category-promotions' ),
            )
        );
    }

    /**
     * Fetch product IDs by categories.
     *
     * @param array $categories Category IDs.
     *
     * @return array
     */
    private function get_products_by_categories( array $categories ) {
        $query_args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $categories,
                ),
            ),
        );

        $products = get_posts( $query_args );

        return array_map( 'absint', $products );
    }

    /**
     * Get queue key for current user.
     *
     * @return string
     */
    private function get_queue_key() {
        $user_id = get_current_user_id();

        return self::TRANSIENT_KEY . $user_id;
    }
}
