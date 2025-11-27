<?php
/**
 * Admin page renderer.
 *
 * @package IndoorTech\CategoryPromotions\Admin
 */

namespace IndoorTech\CategoryPromotions\Admin;

use IndoorTech\CategoryPromotions\Processors\PromotionProcessor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AdminPage
 */
class AdminPage {
    /**
     * Register submenu item.
     */
    public function register_menu() {
        add_submenu_page(
            'woocommerce-marketing',
            __( 'Category Promotions', 'indoortech-category-promotions' ),
            __( 'Category Promotions', 'indoortech-category-promotions' ),
            'manage_woocommerce',
            'indoortech-category-promotions',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render admin page.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'indoortech-category-promotions' ) );
        }

        wp_enqueue_script( 'indoortech-category-promotions-admin' );
        wp_enqueue_style( 'indoortech-category-promotions-admin' );

        $categories = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            )
        );

        $start_label    = __( 'Start Date', 'indoortech-category-promotions' );
        $end_label      = __( 'End Date', 'indoortech-category-promotions' );
        $discount_label = __( 'Discount Percentage', 'indoortech-category-promotions' );
        $button_label   = __( 'Create Promotion', 'indoortech-category-promotions' );

        $data = array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( PromotionProcessor::NONCE_ACTION ),
            'i18n'       => array(
                'processing' => __( 'Processing products, please do not close this window.', 'indoortech-category-promotions' ),
                'success'    => __( 'Promotion applied successfully to all selected products.', 'indoortech-category-promotions' ),
                'error'      => __( 'An error occurred while applying the promotion. Please try again.', 'indoortech-category-promotions' ),
            ),
            'batch_size' => PromotionProcessor::BATCH_SIZE,
        );

        wp_localize_script( 'indoortech-category-promotions-admin', 'indoortechCategoryPromotions', $data );
        ?>
        <div class="wrap indoortech-category-promotions">
            <h1><?php echo esc_html__( 'Category Promotions', 'indoortech-category-promotions' ); ?></h1>
            <form id="indoortech-category-promotions-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="itcp-categories"><?php esc_html_e( 'Select Categories', 'indoortech-category-promotions' ); ?></label></th>
                        <td>
                            <select id="itcp-categories" name="categories[]" multiple="multiple" class="regular-text">
                                <?php foreach ( $categories as $category ) : ?>
                                    <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose one or more categories to discount.', 'indoortech-category-promotions' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="itcp-discount"><?php echo esc_html( $discount_label ); ?></label></th>
                        <td>
                            <input type="number" min="1" max="99" id="itcp-discount" name="discount" required class="small-text" /> %
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="itcp-start-date"><?php echo esc_html( $start_label ); ?></label></th>
                        <td>
                            <input type="date" id="itcp-start-date" name="start_date" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="itcp-end-date"><?php echo esc_html( $end_label ); ?></label></th>
                        <td>
                            <input type="date" id="itcp-end-date" name="end_date" required />
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" id="itcp-submit"><?php echo esc_html( $button_label ); ?></button>
                </p>
            </form>
            <div id="itcp-progress" class="itcp-progress" style="display:none;">
                <div class="itcp-progress-bar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <span class="itcp-progress-text">0%</span>
                </div>
            </div>
            <p id="itcp-status" class="description" style="display:none;"></p>
            <div id="itcp-message"></div>
        </div>
        <?php
    }
}
