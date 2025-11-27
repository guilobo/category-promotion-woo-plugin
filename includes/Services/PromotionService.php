<?php
/**
 * Service for promotion logic.
 *
 * @package IndoorTech\CategoryPromotions\Services
 */

namespace IndoorTech\CategoryPromotions\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class PromotionService
 */
class PromotionService {
    /**
     * Apply promotion to a product.
     *
     * @param int    $product_id Product ID.
     * @param float  $discount   Discount percentage.
     * @param string $start_date Start date (YYYY-MM-DD).
     * @param string $end_date   End date (YYYY-MM-DD).
     */
    public function apply_promotion( $product_id, $discount, $start_date, $end_date ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return;
        }

        $regular_price = $product->get_regular_price();
        if ( '' === $regular_price ) {
            return;
        }

        $sale_price = (float) $regular_price * ( 1 - ( $discount / 100 ) );
        $sale_price = wc_format_decimal( $sale_price );

        $product->set_sale_price( $sale_price );
        $product->set_date_on_sale_from( $this->parse_date( $start_date ) );
        $product->set_date_on_sale_to( $this->parse_date( $end_date, true ) );
        $product->save();
    }

    /**
     * Remove promotion from a product.
     *
     * @param int $product_id Product ID.
     */
    public function remove_promotion( $product_id ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return;
        }

        $product->set_sale_price( '' );
        $product->set_date_on_sale_from( null );
        $product->set_date_on_sale_to( null );
        $product->save();
    }

    /**
     * Parse date string to timestamp.
     *
     * @param string $date_string Date string.
     * @param bool   $end_of_day  Whether to set to end of day.
     *
     * @return int|null
     */
    private function parse_date( $date_string, $end_of_day = false ) {
        if ( empty( $date_string ) ) {
            return null;
        }

        $datetime = null;

        if ( function_exists( 'wc_string_to_datetime' ) ) {
            $datetime = wc_string_to_datetime( $date_string );
        } else {
            $timezone = function_exists( 'wc_timezone' ) ? wc_timezone() : wp_timezone();

            try {
                if ( class_exists( '\\WC_DateTime' ) ) {
                    $datetime = new \WC_DateTime( $date_string, $timezone );
                } else {
                    $datetime = new \DateTime( $date_string, $timezone );
                }
            } catch ( \Exception $e ) {
                return null;
            }
        }

        if ( is_wp_error( $datetime ) || null === $datetime ) {
            return null;
        }

        if ( $end_of_day ) {
            $datetime->set_time( 23, 59, 59 );
        } else {
            $datetime->set_time( 0, 0, 0 );
        }

        return $datetime;
    }
}
