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

        $time = strtotime( $date_string );

        if ( false === $time ) {
            return null;
        }

        if ( $end_of_day ) {
            $time = strtotime( '23:59:59', $time );
        }

        return $time;
    }
}
