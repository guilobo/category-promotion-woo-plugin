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
            $this->send_error(
                __( 'Permission denied.', 'indoortech-category-promotions' ),
                'itcp_permission_denied',
                '',
                403,
                __( 'Current user cannot manage WooCommerce.', 'indoortech-category-promotions' )
            );
        }

        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $categories = isset( $_POST['categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['categories'] ) ) : array();
        $discount   = isset( $_POST['discount'] ) ? floatval( wp_unslash( $_POST['discount'] ) ) : 0;
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
        $end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
        $action     = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : 'apply';

        if ( ! in_array( $action, array( 'apply', 'remove' ), true ) ) {
            $context = sprintf( __( 'Action received: %s', 'indoortech-category-promotions' ), $action );

            $this->send_error(
                __( 'Invalid parameters.', 'indoortech-category-promotions' ),
                'itcp_invalid_action',
                $context,
                400,
                $context
            );
        }

        if ( empty( $categories ) ) {
            $context = __( 'No categories provided in request.', 'indoortech-category-promotions' );

            $this->send_error(
                __( 'Invalid parameters.', 'indoortech-category-promotions' ),
                'itcp_missing_categories',
                $context,
                400,
                $context
            );
        }

        if ( 'apply' === $action && ( $discount <= 0 || $discount >= 100 || empty( $start_date ) || empty( $end_date ) ) ) {
            $context = sprintf(
                __( 'Discount: %s | Start: %s | End: %s', 'indoortech-category-promotions' ),
                $discount,
                $start_date,
                $end_date
            );

            $this->send_error(
                __( 'Invalid parameters.', 'indoortech-category-promotions' ),
                'itcp_invalid_discount_or_dates',
                $context,
                400,
                $context
            );
        }

        $product_ids = $this->get_products_by_categories( $categories );

        if ( empty( $product_ids ) ) {
            $context = sprintf( __( 'Categories requested: %s', 'indoortech-category-promotions' ), implode( ',', $categories ) );

            $this->send_error(
                __( 'No products found for the selected categories.', 'indoortech-category-promotions' ),
                'itcp_no_products_for_categories',
                $context,
                404,
                $context
            );
        }

        $queue = array(
            'product_ids' => array_values( array_unique( $product_ids ) ),
            'discount'    => $discount,
            'start_date'  => $start_date,
            'end_date'    => $end_date,
            'action'      => $action,
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
            $this->send_error(
                __( 'Permission denied.', 'indoortech-category-promotions' ),
                'itcp_permission_denied',
                '',
                403,
                __( 'Current user cannot manage WooCommerce.', 'indoortech-category-promotions' )
            );
        }

        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $queue = get_transient( $this->get_queue_key() );

        if ( empty( $queue ) || ! isset( $queue['product_ids'] ) ) {
            $context = __( 'Queue transient is empty or missing product_ids.', 'indoortech-category-promotions' );

            $this->send_error(
                __( 'No queue found. Please restart the process.', 'indoortech-category-promotions' ),
                'itcp_missing_queue',
                $context,
                400,
                $context
            );
        }

        $service     = new PromotionService();
        $product_ids = array_splice( $queue['product_ids'], 0, self::BATCH_SIZE );
        $action      = isset( $queue['action'] ) ? $queue['action'] : 'apply';
        $last_product_id = null;

        try {
            foreach ( $product_ids as $product_id ) {
                $last_product_id = $product_id;

                if ( 'remove' === $action ) {
                    $service->remove_promotion( $product_id );
                } else {
                    $service->apply_promotion( $product_id, $queue['discount'], $queue['start_date'], $queue['end_date'] );
                }
                $queue['processed']++;
            }
        } catch ( \Throwable $exception ) {
            $this->log_exception( $exception );

            $context = sprintf(
                __( 'Action: %s | Last product: %s | Discount: %s | Start: %s | End: %s | Remaining queue: %d', 'indoortech-category-promotions' ),
                $action,
                $last_product_id ? $last_product_id : __( 'none', 'indoortech-category-promotions' ),
                isset( $queue['discount'] ) ? $queue['discount'] : __( 'n/a', 'indoortech-category-promotions' ),
                isset( $queue['start_date'] ) ? $queue['start_date'] : __( 'n/a', 'indoortech-category-promotions' ),
                isset( $queue['end_date'] ) ? $queue['end_date'] : __( 'n/a', 'indoortech-category-promotions' ),
                isset( $queue['product_ids'] ) ? count( $queue['product_ids'] ) : 0
            );

            $this->send_error(
                __( 'An unexpected error occurred while processing products. Please try again.', 'indoortech-category-promotions' ),
                'itcp_batch_exception',
                $this->format_exception_debug( $exception ),
                500,
                $context
            );
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

    /**
     * Log exceptions to WooCommerce logger when available.
     *
     * @param \Throwable $exception Exception instance.
     */
    private function log_exception( \Throwable $exception ) {
        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->error(
                $exception->getMessage(),
                array(
                    'source' => 'indoortech-category-promotions',
                )
            );
        } else {
            error_log( $exception->getMessage() );
        }
    }

    /**
     * Send a standardized JSON error with optional debug details.
     *
     * @param string $message Error message.
     * @param string $code    Internal code to append to the message for clarity.
     * @param string $debug   Debug details to return when WP_DEBUG is enabled.
     * @param int    $status  HTTP status code.
     * @param string $context Additional public context always returned for easier debugging.
     */
    private function send_error( $message, $code = '', $debug = '', $status = 400, $context = '' ) {
        $formatted_message = $code ? sprintf( '%s (%s)', $message, $code ) : $message;
        $public_context    = $context ? (string) $context : '';

        wp_send_json_error(
            array(
                'message' => $formatted_message,
                'context' => $public_context,
                'debug'   => $this->maybe_include_debug( $debug ?: $public_context ),
            ),
            $status
        );
    }

    /**
     * Decide whether to return debug information.
     *
     * @param string $debug Debug string.
     *
     * @return string
     */
    private function maybe_include_debug( $debug ) {
        return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? (string) $debug : '';
    }

    /**
     * Build detailed exception output for debugging when WP_DEBUG is enabled.
     *
     * @param \Throwable $exception Exception instance.
     *
     * @return string
     */
    private function format_exception_debug( \Throwable $exception ) {
        $details = array(
            sprintf( 'Type: %s', get_class( $exception ) ),
            sprintf( 'Message: %s', $exception->getMessage() ),
            sprintf( 'File: %s:%d', $exception->getFile(), $exception->getLine() ),
        );

        $trace = $exception->getTraceAsString();
        if ( $trace ) {
            $details[] = 'Trace: ' . $trace;
        }

        return implode( "\n", $details );
    }
}
