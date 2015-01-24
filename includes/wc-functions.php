<?php
/**
 * Save the product data meta box.
 *
 * @access public
 * @param mixed $post_id
 * @return void
 */
function dokan_process_product_meta( $post_id ) {
    global $wpdb, $woocommerce, $woocommerce_errors;

    // Add any default post meta
    add_post_meta( $post_id, 'total_sales', '0', true );

    // Get types
    $product_type       = 'simple';
    $is_downloadable    = isset( $_POST['_downloadable'] ) ? 'yes' : 'no';
    $is_virtual         = isset( $_POST['_virtual'] ) ? 'yes' : 'no';

    // Product type + Downloadable/Virtual
    wp_set_object_terms( $post_id, $product_type, 'product_type' );
    update_post_meta( $post_id, '_downloadable', $is_downloadable );
    update_post_meta( $post_id, '_virtual', $is_virtual );

    // Gallery Images
    $attachment_ids = array_filter( explode( ',', woocommerce_clean( $_POST['product_image_gallery'] ) ) );
    update_post_meta( $post_id, '_product_image_gallery', implode( ',', $attachment_ids ) );

    // Update post meta
    if ( isset( $_POST['_regular_price'] ) ) {
        update_post_meta( $post_id, '_regular_price', ( $_POST['_regular_price'] === '' ) ? '' : wc_format_decimal( $_POST['_regular_price'] ) );
    }

    if ( isset( $_POST['_sale_price'] ) ) {
        update_post_meta( $post_id, '_sale_price', ( $_POST['_sale_price'] === '' ? '' : wc_format_decimal( $_POST['_sale_price'] ) ) );
    }

    if ( isset( $_POST['_tax_status'] ) )
        update_post_meta( $post_id, '_tax_status', stripslashes( $_POST['_tax_status'] ) );

    if ( isset( $_POST['_tax_class'] ) )
        update_post_meta( $post_id, '_tax_class', stripslashes( $_POST['_tax_class'] ) );

    update_post_meta( $post_id, '_visibility', stripslashes( $_POST['_visibility'] ) );


    // Unique SKU
    $sku                = get_post_meta($post_id, '_sku', true);
    $new_sku            = woocommerce_clean( stripslashes( $_POST['_sku'] ) );
    if ( $new_sku == '' ) {
        update_post_meta( $post_id, '_sku', '' );
    } elseif ( $new_sku !== $sku ) {
        if ( ! empty( $new_sku ) ) {
            if (
                $wpdb->get_var( $wpdb->prepare("
                    SELECT $wpdb->posts.ID
                    FROM $wpdb->posts
                    LEFT JOIN $wpdb->postmeta ON ($wpdb->posts.ID = $wpdb->postmeta.post_id)
                    WHERE $wpdb->posts.post_type = 'product'
                    AND $wpdb->posts.post_status = 'publish'
                    AND $wpdb->postmeta.meta_key = '_sku' AND $wpdb->postmeta.meta_value = '%s'
                 ", $new_sku ) )
                ) {
                $woocommerce_errors[] = __( 'Product SKU must be unique.', 'woocommerce' );
            } else {
                update_post_meta( $post_id, '_sku', $new_sku );
            }
        } else {
            update_post_meta( $post_id, '_sku', '' );
        }
    }



    // Sales and prices

    $date_from = isset( $_POST['_sale_price_dates_from'] ) ? $_POST['_sale_price_dates_from'] : '';
    $date_to   = isset( $_POST['_sale_price_dates_to'] ) ? $_POST['_sale_price_dates_to'] : '';

    // Dates
    if ( $date_from ) {
        update_post_meta( $post_id, '_sale_price_dates_from', strtotime( $date_from ) );
    } else {
        update_post_meta( $post_id, '_sale_price_dates_from', '' );
    }

    if ( $date_to ) {
        update_post_meta( $post_id, '_sale_price_dates_to', strtotime( $date_to ) );
    } else {
        update_post_meta( $post_id, '_sale_price_dates_to', '' );
    }

    if ( $date_to && ! $date_from ) {
        update_post_meta( $post_id, '_sale_price_dates_from', strtotime( 'NOW', current_time( 'timestamp' ) ) );
    }

    // Update price if on sale
    if ( '' !== $_POST['_sale_price'] && '' == $date_to && '' == $date_from ) {
        update_post_meta( $post_id, '_price', wc_format_decimal( $_POST['_sale_price'] ) );
    } else {
        update_post_meta( $post_id, '_price', ( $_POST['_regular_price'] === '' ) ? '' : wc_format_decimal( $_POST['_regular_price'] ) );
    }

    if ( '' !== $_POST['_sale_price'] && $date_from && strtotime( $date_from ) < strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
        update_post_meta( $post_id, '_price', wc_format_decimal( $_POST['_sale_price'] ) );
    }

    if ( $date_to && strtotime( $date_to ) < strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
        update_post_meta( $post_id, '_price', ( $_POST['_regular_price'] === '' ) ? '' : wc_format_decimal( $_POST['_regular_price'] ) );
        update_post_meta( $post_id, '_sale_price_dates_from', '' );
        update_post_meta( $post_id, '_sale_price_dates_to', '' );
    }

    // reset price is discounted checkbox was not checked
    if ( ! isset( $_POST['_discounted_price'] ) ) {
        update_post_meta( $post_id, '_price', wc_format_decimal( $_POST['_regular_price'] ) );
        update_post_meta( $post_id, '_regular_price', wc_format_decimal( $_POST['_regular_price'] ) );
        update_post_meta( $post_id, '_sale_price', '' );
    }

    // Sold Individuall
    if ( isset( $_POST['_sold_individually'] ) ) {
        update_post_meta( $post_id, '_sold_individually', 'yes' );
    } else {
        update_post_meta( $post_id, '_sold_individually', '' );
    }

    // Stock Data
    if ( get_option('woocommerce_manage_stock') == 'yes' ) {

        if ( ! empty( $_POST['_manage_stock'] ) ) {

            // Manage stock
            update_post_meta( $post_id, '_stock', (int) $_POST['_stock'] );
            update_post_meta( $post_id, '_stock_status', stripslashes( $_POST['_stock_status'] ) );
            update_post_meta( $post_id, '_backorders', stripslashes( $_POST['_backorders'] ) );
            update_post_meta( $post_id, '_manage_stock', 'yes' );

            // Check stock level
            if ( $product_type !== 'variable' && $_POST['_backorders'] == 'no' && (int) $_POST['_stock'] < 1 )
                update_post_meta( $post_id, '_stock_status', 'outofstock' );

        } else {

            // Don't manage stock
            update_post_meta( $post_id, '_stock', '' );
            update_post_meta( $post_id, '_stock_status', stripslashes( $_POST['_stock_status'] ) );
            update_post_meta( $post_id, '_backorders', stripslashes( $_POST['_backorders'] ) );
            update_post_meta( $post_id, '_manage_stock', 'no' );

        }

    } else {

        update_post_meta( $post_id, '_stock_status', stripslashes( $_POST['_stock_status'] ) );

    }

    // Upsells
    if ( isset( $_POST['upsell_ids'] ) ) {
        $upsells = array();
        $ids = $_POST['upsell_ids'];
        foreach ( $ids as $id )
            if ( $id && $id > 0 )
                $upsells[] = $id;

        update_post_meta( $post_id, '_upsell_ids', $upsells );
    } else {
        delete_post_meta( $post_id, '_upsell_ids' );
    }

    // Cross sells
    if ( isset( $_POST['crosssell_ids'] ) ) {
        $crosssells = array();
        $ids = $_POST['crosssell_ids'];
        foreach ( $ids as $id )
            if ( $id && $id > 0 )
                $crosssells[] = $id;

        update_post_meta( $post_id, '_crosssell_ids', $crosssells );
    } else {
        delete_post_meta( $post_id, '_crosssell_ids' );
    }

    // Downloadable options
    if ( $is_downloadable == 'yes' ) {

        // file paths will be stored in an array keyed off md5(file path)
        if ( isset( $_POST['_wc_file_urls'] ) ) {
            $files = array();

            $file_names    = isset( $_POST['_wc_file_names'] ) ? array_map( 'wc_clean', $_POST['_wc_file_names'] ) : array();
            $file_urls     = isset( $_POST['_wc_file_urls'] ) ? array_map( 'esc_url_raw', array_map( 'trim', $_POST['_wc_file_urls'] ) ) : array();
            $file_url_size = sizeof( $file_urls );

            for ( $i = 0; $i < $file_url_size; $i ++ ) {
                if ( ! empty( $file_urls[ $i ] ) )
                    $files[ md5( $file_urls[ $i ] ) ] = array(
                        'name' => $file_names[ $i ],
                        'file' => $file_urls[ $i ]
                    );
            }

            // grant permission to any newly added files on any existing orders for this product prior to saving
            do_action( 'woocommerce_process_product_file_download_paths', $post_id, 0, $files );

            update_post_meta( $post_id, '_downloadable_files', $files );
        }
    }

    // Do action for product type
    do_action( 'woocommerce_process_product_meta_simple', $post_id );
    do_action( 'dokan_process_product_meta', $post_id );

    // Clear cache/transients
    wc_delete_product_transients( $post_id );
}



/**
 * Monitors a new order and attempts to create sub-orders
 *
 * If an order contains products from multiple vendor, we can't show the order
 * to each seller dashboard. That's why we need to divide the main order to
 * some sub-orders based on the number of sellers.
 *
 * @param int $parent_order_id
 * @return void
 */
function dokan_create_sub_order( $parent_order_id ) {

    $parent_order = new WC_Order( $parent_order_id );
    $order_items = $parent_order->get_items();

    $sellers = array();
    foreach ($order_items as $item) {
        $seller_id = get_post_field( 'post_author', $item['product_id'] );
        $sellers[$seller_id][] = $item;
    }

    // return if we've only ONE seller
    if ( count( $sellers ) == 1 ) {
        $temp = array_keys( $sellers );
        $seller_id = reset( $temp );
        wp_update_post( array( 'ID' => $parent_order_id, 'post_author' => $seller_id ) );
        return;
    }

    // flag it as it has a suborder
    update_post_meta( $parent_order_id, 'has_sub_order', true );

    // seems like we've got multiple sellers
    foreach ($sellers as $seller_id => $seller_products ) {
        dokan_create_seller_order( $parent_order, $seller_id, $seller_products );
    }
}

add_action( 'woocommerce_checkout_update_order_meta', 'dokan_create_sub_order' );



/**
 * Creates a sub order
 *
 * @param int $parent_order
 * @param int $seller_id
 * @param array $seller_products
 */
function dokan_create_seller_order( $parent_order, $seller_id, $seller_products ) {
    $order_data = apply_filters( 'woocommerce_new_order_data', array(
        'post_type'     => 'shop_order',
        'post_title'    => sprintf( __( 'Order &ndash; %s', 'woocommerce' ), strftime( _x( '%b %d, %Y @ %I:%M %p', 'Order date parsed by strftime', 'woocommerce' ) ) ),
        'post_status'   => 'wc-pending',
        'ping_status'   => 'closed',
        'post_excerpt'  => isset( $posted['order_comments'] ) ? $posted['order_comments'] : '',
        'post_author'   => $seller_id,
        'post_parent'   => $parent_order->id,
        'post_password' => uniqid( 'order_' )   // Protects the post just in case
    ) );

    $order_id = wp_insert_post( $order_data );

    if ( $order_id && !is_wp_error( $order_id ) ) {

        $order_total = $order_tax = 0;
        $product_ids = array();

        do_action( 'woocommerce_new_order', $order_id );

        // now insert line items
        foreach ($seller_products as $item) {
            $order_total += (float) $item['line_total'];
            $order_tax += (float) $item['line_tax'];
            $product_ids[] = $item['product_id'];

            $item_id = wc_add_order_item( $order_id, array(
                'order_item_name' => $item['name'],
                'order_item_type' => 'line_item'
            ) );

            if ( $item_id ) {
                wc_add_order_item_meta( $item_id, '_qty', $item['qty'] );
                wc_add_order_item_meta( $item_id, '_tax_class', $item['tax_class'] );
                wc_add_order_item_meta( $item_id, '_product_id', $item['product_id'] );
                wc_add_order_item_meta( $item_id, '_line_subtotal', $item['line_subtotal'] );
                wc_add_order_item_meta( $item_id, '_line_total', $item['line_total'] );
                wc_add_order_item_meta( $item_id, '_line_tax', $item['line_tax'] );
                wc_add_order_item_meta( $item_id, '_line_subtotal_tax', $item['line_subtotal_tax'] );
            }
        } // foreach

        $bill_ship = array(
            '_billing_country', '_billing_first_name', '_billing_last_name', '_billing_company',
            '_billing_address_1', '_billing_address_2', '_billing_city', '_billing_state', '_billing_postcode',
            '_billing_email', '_billing_phone', '_shipping_country', '_shipping_first_name', '_shipping_last_name',
            '_shipping_company', '_shipping_address_1', '_shipping_address_2', '_shipping_city',
            '_shipping_state', '_shipping_postcode'
        );

        // save billing and shipping address
        foreach ($bill_ship as $val) {
            $order_key = ltrim( $val, '_' );
            update_post_meta( $order_id, $val, $parent_order->$order_key );
        }

        // calculate the total
        $order_in_total = $order_total + $shipping_cost + $order_tax;

        // set order meta
        update_post_meta( $order_id, '_payment_method',         $parent_order->payment_method );
        update_post_meta( $order_id, '_payment_method_title',   $parent_order->payment_method_title );

        update_post_meta( $order_id, '_order_shipping',         woocommerce_format_decimal( $shipping_cost ) );
        update_post_meta( $order_id, '_cart_discount',          '0' );
        update_post_meta( $order_id, '_order_tax',              woocommerce_format_decimal( $order_tax ) );
        update_post_meta( $order_id, '_order_shipping_tax',     '0' );
        update_post_meta( $order_id, '_order_total',            woocommerce_format_decimal( $order_in_total ) );
        update_post_meta( $order_id, '_order_key',              apply_filters('woocommerce_generate_order_key', uniqid('order_') ) );
        update_post_meta( $order_id, '_customer_user',          $parent_order->customer_user );
        update_post_meta( $order_id, '_order_currency',         get_post_meta( $parent_order->id, '_order_currency', true ) );
        update_post_meta( $order_id, '_prices_include_tax',     $parent_order->prices_include_tax );
        update_post_meta( $order_id, '_customer_ip_address',    get_post_meta( $parent_order->id, '_customer_ip_address', true ) );
        update_post_meta( $order_id, '_customer_user_agent',    get_post_meta( $parent_order->id, '_customer_user_agent', true ) );

        do_action( 'dokan_checkout_update_order_meta', $order_id, $seller_id );
    } // if order
}

/**
 * Validates seller registration form from my-account page
 *
 * @param WP_Error $error
 * @return \WP_Error
 */
function dokan_seller_registration_errors( $error ) {
    $allowed_roles = apply_filters( 'dokan_register_user_role', array( 'customer', 'seller' ) );

    // is the role name allowed or user is trying to manipulate?
    if ( isset( $_POST['role'] ) && !in_array( $_POST['role'], $allowed_roles ) ) {
        return new WP_Error( 'role-error', __( 'Cheating, eh?', 'dokan' ) );
    }

    $role = $_POST['role'];

    if ( $role == 'seller' ) {

        $first_name = trim( $_POST['fname'] );
        if ( empty( $first_name ) ) {
            return new WP_Error( 'fname-error', __( 'Please enter your first name.', 'dokan' ) );
        }

        $last_name = trim( $_POST['lname'] );
        if ( empty( $last_name ) ) {
            return new WP_Error( 'lname-error', __( 'Please enter your last name.', 'dokan' ) );
        }

        $address = trim( $_POST['address'] );
        if ( empty( $address ) ) {
            return new WP_Error( 'address-error', __( 'Please enter your address.', 'dokan' ) );
        }

        $phone = trim( $_POST['phone'] );
        if ( empty( $phone ) ) {
            return new WP_Error( 'phone-error', __( 'Please enter your phone number.', 'dokan' ) );
        }
    }

    return $error;
}

add_filter( 'woocommerce_process_registration_errors', 'dokan_seller_registration_errors' );
add_filter( 'registration_errors', 'dokan_seller_registration_errors' );



/**
 * Inject first and last name to WooCommerce for new seller registraion
 *
 * @param array $data
 * @return array
 */
function dokan_new_customer_data( $data ) {
    $allowed_roles = array( 'customer', 'seller' );
    $role = ( isset( $_POST['role'] ) && in_array( $_POST['role'], $allowed_roles ) ) ? $_POST['role'] : 'customer';

    $data['role'] = $role;

    if ( $role == 'seller' ) {
        $data['first_name']    = strip_tags( $_POST['fname'] );
        $data['last_name']     = strip_tags( $_POST['lname'] );
        $data['user_nicename'] = sanitize_title( $_POST['shopurl'] );
    }

    return $data;
}

add_filter( 'woocommerce_new_customer_data', 'dokan_new_customer_data');



/**
 * Adds default dokan store settings when a new seller registers
 *
 * @param int $user_id
 * @param array $data
 * @return void
 */
function dokan_on_create_seller( $user_id, $data ) {
    if ( $data['role'] != 'seller' ) {
        return;
    }

    $dokan_settings = array(
        'store_name'     => strip_tags( $_POST['shopname'] ),
        'social'         => array(),
        'payment'        => array(),
        'phone'          => $_POST['phone'],
        'show_email'     => 'no',
        'address'        => strip_tags( $_POST['address'] ),
        'location'       => '',
        'dokan_category' => '',
        'banner'         => 0,
    );

    update_user_meta( $user_id, 'dokan_profile_settings', $dokan_settings );

    Dokan_Email::init()->new_seller_registered_mail( $user_id );
}

add_action( 'woocommerce_created_customer', 'dokan_on_create_seller', 10, 2);



/**
 * Get featured products
 *
 * Shown on homepage
 *
 * @param int $per_page
 * @return \WP_Query
 */
function dokan_get_featured_products( $per_page = 9) {
    $featured_query = new WP_Query( apply_filters( 'dokan_get_featured_products', array(
        'posts_per_page'      => $per_page,
        'post_type'           => 'product',
        'ignore_sticky_posts' => 1,
        'meta_query'          => array(
            array(
                'key'     => '_visibility',
                'value'   => array('catalog', 'visible'),
                'compare' => 'IN'
            ),
            array(
                'key'   => '_featured',
                'value' => 'yes'
            )
        )
    ) ) );

    return $featured_query;
}

/**
 * Get latest products
 *
 * Shown on homepage
 *
 * @param int $per_page
 * @return \WP_Query
 */
function dokan_get_latest_products( $per_page = 9) {
    $featured_query = new WP_Query( apply_filters( 'dokan_get_featured_products', array(
        'posts_per_page'      => $per_page,
        'post_type'           => 'product',
        'ignore_sticky_posts' => 1,
        'meta_query'          => array(
            array(
                'key'     => '_visibility',
                'value'   => array('catalog', 'visible'),
                'compare' => 'IN'
            )
    ) ) ) );

    return $featured_query;
}



/**
 * Get best selling products
 *
 * Shown on homepage
 *
 * @param int $per_page
 * @return \WP_Query
 */
function dokan_get_best_selling_products( $per_page = 8 ) {

    $args = array(
        'post_type'           => 'product',
        'post_status'         => 'publish',
        'ignore_sticky_posts' => 1,
        'posts_per_page'      => $per_page,
        'meta_key'            => 'total_sales',
        'orderby'             => 'meta_value_num',
        'meta_query'          => array(
            array(
                'key'     => '_visibility',
                'value'   => array( 'catalog', 'visible' ),
                'compare' => 'IN'
            ),
        )
    );

    $best_selling_query = new WP_Query( apply_filters( 'dokan_best_selling_query', $args ) );

    return $best_selling_query;
}



/**
 * Get top rated products
 *
 * Shown on homepage
 *
 * @param int $per_page
 * @return \WP_Query
 */
function dokan_get_top_rated_products( $per_page = 8 ) {

    $args = array(
        'post_type'             => 'product',
        'post_status'           => 'publish',
        'ignore_sticky_posts'   => 1,
        'posts_per_page'        => $per_page,
        'meta_query'            => array(
            array(
                'key'           => '_visibility',
                'value'         => array('catalog', 'visible'),
                'compare'       => 'IN'
            )
        )
    );

    add_filter( 'posts_clauses', array( 'WC_Shortcodes', 'order_by_rating_post_clauses' ) );

    $top_rated_query = new WP_Query( apply_filters( 'dokan_top_rated_query', $args ) );

    remove_filter( 'posts_clauses', array( 'WC_Shortcodes', 'order_by_rating_post_clauses' ) );

    return $top_rated_query;
}



/**
 * Get products on-sale
 *
 * Shown on homepage
 *
 * @param type $per_page
 * @param type $paged
 * @return \WP_Query
 */
function dokan_get_on_sale_products( $per_page = 10, $paged = 1 ) {
    // Get products on sale
    $product_ids_on_sale = wc_get_product_ids_on_sale();

    $args = array(
        'posts_per_page'    => $per_page,
        'no_found_rows'     => 1,
        'paged'             => $paged,
        'post_status'       => 'publish',
        'post_type'         => 'product',
        'post__in'          => array_merge( array( 0 ), $product_ids_on_sale ),
        'meta_query'        => array(
            array(
                'key'       => '_visibility',
                'value'     => array('catalog', 'visible'),
                'compare'   => 'IN'
            ),
            array(
                'key'       => '_stock_status',
                'value'     => 'instock',
                'compare'   => '='
            )
        )
    );

    return new WP_Query( apply_filters( 'dokan_on_sale_products_query', $args ) );
}



/**
 * Get current balance of a seller
 *
 * Total = SUM(net_amount) - SUM(withdraw)
 *
 * @global WPDB $wpdb
 * @param type $seller_id
 * @param type $formatted
 * @return type
 */
function dokan_get_seller_balance( $seller_id, $formatted = true ) {
    global $wpdb;

    $cache_key = 'dokan_seller_balance_' . $seller_id;
    $earning   = wp_cache_get( $cache_key, 'dokan' );

    if ( false === $earning ) {
        $sql = "SELECT SUM(net_amount) as earnings,
            (SELECT SUM(amount) FROM {$wpdb->prefix}dokan_withdraw WHERE user_id = %d AND status = 1) as withdraw
            FROM {$wpdb->prefix}dokan_orders
            WHERE seller_id = %d AND order_status = 'wc-completed'";

        $result = $wpdb->get_row( $wpdb->prepare( $sql, $seller_id, $seller_id ) );
        $earning = $result->earnings - $result->withdraw;

        wp_cache_set( $cache_key, $earning, 'dokan' );
    }

    if ( $formatted ) {
        return wc_price( $earning );
    }

    return $earning;
}

/**
 * Get seller rating
 *
 * @global WPDB $wpdb
 * @param type $seller_id
 * @return type
 */
function dokan_get_seller_rating( $seller_id ) {
    global $wpdb;

    $sql = "SELECT AVG(cm.meta_value) as average, COUNT(wc.comment_ID) as count FROM $wpdb->posts p
        INNER JOIN $wpdb->comments wc ON p.ID = wc.comment_post_ID
        LEFT JOIN $wpdb->commentmeta cm ON cm.comment_id = wc.comment_ID
        WHERE p.post_author = %d AND p.post_type = 'product' AND p.post_status = 'publish'
        AND ( cm.meta_key = 'rating' OR cm.meta_key IS NULL) AND wc.comment_approved = 1
        ORDER BY wc.comment_post_ID";

    $result = $wpdb->get_row( $wpdb->prepare( $sql, $seller_id ) );

    return array( 'rating' => number_format( $result->average, 2), 'count' => (int) $result->count );
}


/**
 * Get seller rating in a readable rating format
 *
 * @param int $seller_id
 * @return void
 */
function dokan_get_readable_seller_rating( $seller_id ) {
    $rating = dokan_get_seller_rating( $seller_id );

    if ( ! $rating['count'] ) {
        echo __( 'No ratings found yet!', 'dokan' );
        return;
    }

    $long_text = _n( __( '%s rating from %d review', 'dokan' ), __( '%s rating from %d reviews', 'dokan' ), $rating['count'], 'dokan' );
    $text = sprintf( __( 'Rated %s out of %d', 'dokan' ), $rating['rating'], number_format( 5 ) );
    $width = ( $rating['rating']/5 ) * 100;
    ?>
        <span class="seller-rating">
            <span title="<?php echo esc_attr( $text ); ?>" class="star-rating" itemtype="http://schema.org/Rating" itemscope="" itemprop="reviewRating">
                <span class="width" style="width: <?php echo $width; ?>%"></span>
                <span style=""><strong itemprop="ratingValue"><?php echo $rating['rating']; ?></strong></span>
            </span>
        </span>

        <span class="text"><a><?php printf( $long_text, $rating['rating'], $rating['count'] ); ?></a></span>

    <?php
}

/**
 * Exclude child order emails for customers
 *
 * A hacky and dirty way to do this from this action. Because there is no easy
 * way to do this by removing action hooks from WooCommerce. It would be easier
 * if they were from functions. Because they are added from classes, we can't
 * remove those action hooks. Thats why we are doing this from the phpmailer_init action
 * by returning a fake phpmailer class.
 *
 * @param  array $attr
 * @return array
 */
function dokan_exclude_child_customer_receipt( &$phpmailer ) {
    $subject      = $phpmailer->Subject;

    // order receipt
    $sub_receipt  = __( 'Your {site_title} order receipt from {order_date}', 'woocommerce' );
    $sub_download = __( 'Your {site_title} order from {order_date} is complete', 'woocommerce' );

    $sub_receipt  = str_replace( array('{site_title}', '{order_date}'), array(wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), ''), $sub_receipt);
    $sub_download = str_replace( array('{site_title}', '{order_date} is complete'), array(wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), ''), $sub_download);

    // not a customer receipt mail
    if ( ( stripos( $subject, $sub_receipt ) === false ) && ( stripos( $subject, $sub_download ) === false ) ) {
        return;
    }

    $message = $phpmailer->Body;
    $pattern = '/Order: #(\d+)/';
    preg_match( $pattern, $message, $matches );

    if ( isset( $matches[1] ) ) {
        $order_id = $matches[1];
        $order    = get_post( $order_id );

        // we found a child order
        if ( ! is_wp_error( $order ) && $order->post_parent != 0 ) {
            $phpmailer = new DokanFakeMailer();
        }
    }
}

add_action( 'phpmailer_init', 'dokan_exclude_child_customer_receipt' );

/**
 * A fake mailer class to replace phpmailer
 */
class DokanFakeMailer {
    public function Send() {}
}
