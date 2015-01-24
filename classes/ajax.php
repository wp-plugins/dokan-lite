<?php
/**
 * Ajax handler for Dokan
 *
 * @package Dokan
 */
class Dokan_Ajax {

    /**
     * Singleton object
     *
     * @staticvar boolean $instance
     * @return \self
     */
    public static function init() {

        static $instance = false;

        if ( !$instance ) {
            $instance = new self;
        }

        return $instance;
    }

    /**
     * Init ajax handlers
     *
     * @return void
     */
    function init_ajax() {
        //withdraw note
        $withdraw = Dokan_Template_Withdraw::init();
        add_action( 'wp_ajax_withdraw_ajax_submission', array( $withdraw, 'withdraw_ajax' ) );

        //settings
        $settings = Dokan_Template_Settings::init();
        add_action( 'wp_ajax_dokan_settings', array( $settings, 'ajax_settings' ) );

        add_action( 'wp_ajax_dokan-mark-order-complete', array( $this, 'complete_order' ) );
        add_action( 'wp_ajax_dokan-mark-order-processing', array( $this, 'process_order' ) );

        add_action( 'wp_ajax_dokan_change_status', array( $this, 'change_order_status' ) );

        add_action( 'wp_ajax_dokan_toggle_seller', array( $this, 'toggle_seller_status' ) );

        add_action( 'wp_ajax_shop_url', array($this, 'shop_url_check') );
        add_action( 'wp_ajax_nopriv_shop_url', array($this, 'shop_url_check') );

        add_filter( 'woocommerce_cart_item_name', array($this, 'seller_info_checkout'), 10, 2 );
    }

    /**
     * Injects seller name on checkout page
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    function seller_info_checkout( $item_data, $cart_item ) {
        $info   = dokan_get_store_info( $cart_item['data']->post->post_author );
        $seller = sprintf( __( '<strong>Seller:</strong> %s', 'dokan' ), $info['store_name'] );
        $data   = $item_data . $seller;

        return apply_filters( 'dokan_seller_info_checkout', $data, $info, $item_data, $cart_item );
    }

    /**
     * chop url check
     */
    function shop_url_check() {
        global $user_ID;

        if ( !wp_verify_nonce( $_POST['_nonce'], 'dokan_reviews' ) ) {
            wp_send_json_error( array(
                'type' => 'nonce',
                'message' => __( 'Are you cheating?', 'dokan' )
            ) );
        }

        $url_slug = $_POST['url_slug'];
        $check    = true;
        $user     = get_user_by( 'slug', $url_slug );

        if ( $user != '' ) {
            $check = false;
        }

        // check if a customer wants to migrate, his username should be available
        if ( is_user_logged_in() && dokan_is_user_customer( $user_ID ) ) {
            $current_user = wp_get_current_user();

            if ( $current_user->user_nicename == $user->user_nicename ) {
                $check = true;
            }
        }

        echo $check;
    }

    /**
     * Mark a order as complete
     *
     * Fires from seller dashboard in frontend
     */
    function complete_order() {
        if ( !is_admin() ) {
            die();
        }

        if ( !current_user_can( 'dokandar' ) || dokan_get_option( 'order_status_change', 'dokan_selling', 'on' ) != 'on' ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'dokan' ) );
        }

        if ( !check_admin_referer( 'dokan-mark-order-complete' ) ) {
            wp_die( __( 'You have taken too long. Please go back and retry.', 'dokan' ) );
        }

        $order_id = isset($_GET['order_id']) && (int) $_GET['order_id'] ? (int) $_GET['order_id'] : '';
        if ( !$order_id ) {
            die();
        }

        if ( !dokan_is_seller_has_order( get_current_user_id(), $order_id ) ) {
            wp_die( __( 'You do not have permission to change this order', 'dokan' ) );
        }

        $order = new WC_Order( $order_id );
        $order->update_status( 'completed' );

        wp_safe_redirect( wp_get_referer() );
        die();
    }

    /**
     * Mark a order as processing
     *
     * Fires from frontend seller dashboard
     */
    function process_order() {
        if ( !is_admin() ) {
            die();
        }

        if ( !current_user_can( 'dokandar' ) && dokan_get_option( 'order_status_change', 'dokan_selling', 'on' ) != 'on' ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'dokan' ) );
        }

        if ( !check_admin_referer( 'dokan-mark-order-processing' ) ) {
            wp_die( __( 'You have taken too long. Please go back and retry.', 'dokan' ) );
        }

        $order_id = isset( $_GET['order_id'] ) && (int) $_GET['order_id'] ? (int) $_GET['order_id'] : '';
        if ( !$order_id ) {
            die();
        }

        if ( !dokan_is_seller_has_order( get_current_user_id(), $order_id ) ) {
            wp_die( __( 'You do not have permission to change this order', 'dokan' ) );
        }

        $order = new WC_Order( $order_id );
        $order->update_status( 'processing' );

        wp_safe_redirect( wp_get_referer() );
    }


    /**
     * Update a order status
     *
     * @return void
     */
    function change_order_status() {

        check_ajax_referer( 'dokan_change_status' );

        $order_id     = intval( $_POST['order_id'] );
        $order_status = $_POST['order_status'];

        $order = new WC_Order( $order_id );
        $order->update_status( $order_status );

        $statuses     = wc_get_order_statuses();
        $status_label = isset( $statuses[$order_status] ) ? $statuses[$order_status] : $order_status;
        $status_class = dokan_get_order_status_class( $order_status );

        echo '<label class="dokan-label dokan-label-' . $status_class . '">' . $status_label . '</label>';
        exit;
    }


    /**
     * Enable/disable seller selling capability from admin seller listing page
     *
     * @return type
     */
    function toggle_seller_status() {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }

        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        $status = sanitize_text_field( $_POST['type'] );

        if ( $user_id && in_array( $status, array( 'yes', 'no' ) ) ) {
            update_user_meta( $user_id, 'dokan_enable_selling', $status );

            if ( $status == 'no' ) {
                $this->make_products_pending( $user_id );
            }
        }
        exit;
    }

    /**
     * Make all the products to pending once a seller is deactivated for selling
     *
     * @param int $seller_id
     */
    function make_products_pending( $seller_id ) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'author' => $seller_id,
            'orderby' => 'post_date',
            'order' => 'DESC'
        );

        $product_query = new WP_Query( $args );
        $products = $product_query->get_posts();

        if ( $products ) {
            foreach ($products as $pro) {
                wp_update_post( array( 'ID' => $pro->ID, 'post_status' => 'pending' ) );
            }
        }
    }
}