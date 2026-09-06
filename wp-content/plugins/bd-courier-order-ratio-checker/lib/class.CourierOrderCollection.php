<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierOrderCollection
 * Handles incomplete order data collection and duplicate order prevention.
 */
class CourierOrderCollection {

    /**
     * Constructor.
     */
    public function __construct() {
        // Enqueue browser fingerprint script on checkout
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_fingerprint_script' ) );
        
        // Prevent duplicate orders - MUST run FIRST before collecting incomplete data
        add_action( 'woocommerce_checkout_process', array( $this, 'check_duplicate_orders' ), 5 );
        
        // Collect incomplete order data - runs AFTER duplicate check
        add_action( 'woocommerce_checkout_process', array( $this, 'collect_incomplete_order_data' ), 10 );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'mark_incomplete_as_complete' ), 10, 1 );
        
        // AJAX endpoint to save incomplete order data
        add_action( 'wp_ajax_bdc_save_incomplete_order', array( $this, 'save_incomplete_order_ajax' ) );
        add_action( 'wp_ajax_nopriv_bdc_save_incomplete_order', array( $this, 'save_incomplete_order_ajax' ) );
        
        // AJAX endpoint for real-time updates
        add_action( 'wp_ajax_bdc_update_incomplete_order', array( $this, 'update_incomplete_order_ajax' ) );
        add_action( 'wp_ajax_nopriv_bdc_update_incomplete_order', array( $this, 'update_incomplete_order_ajax' ) );
    }

    /**
     * Enqueue browser fingerprint script on checkout pages.
     */
    public function enqueue_fingerprint_script() {
        // Only load on checkout page - support both WooCommerce and CartFlows
        $is_checkout_page = is_checkout() || 
                           ( is_page() && (
                               has_shortcode( get_post()->post_content ?? '', 'cartflows_checkout' ) ||
                               strpos( get_post()->post_content ?? '', 'cartflows-checkout' ) !== false ||
                               $this->is_cartflows_checkout_page()
                           ) );
        
        if ( ! $is_checkout_page ) {
            return;
        }

        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';
        
        $fingerprint_js = $plugin_dir . '/assets/js/browser-fingerprint.js';
        $checkout_fingerprint_js = $plugin_dir . '/assets/js/checkout-fingerprint.js';
        
        // Enqueue jQuery first if not already enqueued
        wp_enqueue_script( 'jquery' );
        
        if ( file_exists( $fingerprint_js ) ) {
            $fingerprint_url = plugins_url( 'assets/js/browser-fingerprint.js', $plugin_main_file );
            $fingerprint_url = add_query_arg( 'v', BD_COURIER_VERSION, $fingerprint_url );
            wp_enqueue_script(
                'bdc-browser-fingerprint',
                $fingerprint_url,
                array(), // No dependencies - standalone
                BD_COURIER_VERSION,
                false // Load in header so it's available early
            );
        }
        
        // Enqueue SweetAlert2 first if available
        $sweetalert_file = $plugin_dir . '/assets/js/sweetalert2.all.min.js';
        if ( file_exists( $sweetalert_file ) ) {
            $sweetalert_url = plugins_url( 'assets/js/sweetalert2.all.min.js', $plugin_main_file );
            $sweetalert_url = add_query_arg( 'v', BD_COURIER_VERSION, $sweetalert_url );
            wp_enqueue_script(
                'sweetalert2',
                $sweetalert_url,
                array(),
                BD_COURIER_VERSION,
                false // Load in header so it's available early
            );
        }
        
        if ( file_exists( $checkout_fingerprint_js ) ) {
            $dependencies = array( 'jquery', 'wc-checkout' );
            if ( file_exists( $sweetalert_file ) ) {
                $dependencies[] = 'sweetalert2'; // Make checkout script depend on SweetAlert2
            }
            
            $checkout_fingerprint_url = plugins_url( 'assets/js/checkout-fingerprint.js', $plugin_main_file );
            $checkout_fingerprint_url = add_query_arg( 'v', BD_COURIER_VERSION, $checkout_fingerprint_url );
            wp_enqueue_script(
                'bdc-checkout-fingerprint',
                $checkout_fingerprint_url,
                $dependencies,
                BD_COURIER_VERSION,
                true // Load in footer
            );
            
            // Localize script with AJAX URL
            wp_localize_script( 'bdc-checkout-fingerprint', 'bdcFingerprintAjax', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'bdc_incomplete_order_nonce' ),
            ) );
            
            // Also localize blocked entities validation settings
            wp_localize_script( 'bdc-checkout-fingerprint', 'bdcCheckoutValidation', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'bdc_validate_checkout_blocked_entities' ),
            ) );
        }
    }

    /**
     * Collect incomplete order data during checkout.
     * Note: Data collection still works even if site is not authorized,
     * but blocking/checking features require authorization.
     */
    public function collect_incomplete_order_data() {
        // Check if feature is enabled
        $collect_incomplete = get_option( 'bd_courier_collect_incomplete_data', false );
        if ( ! $collect_incomplete ) {
            return;
        }

        // Check if site is authorized for extended features
        // If not authorized, data collection still works but won't be used for blocking
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return;
        }

        // Get browser fingerprint from POST data
        $fingerprint = isset( $_POST['bdc_browser_fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['bdc_browser_fingerprint'] ) ) : '';
        
        // Also check REQUEST in case it's not in POST
        if ( empty( $fingerprint ) && isset( $_REQUEST['bdc_browser_fingerprint'] ) ) {
            $fingerprint = sanitize_text_field( wp_unslash( $_REQUEST['bdc_browser_fingerprint'] ) );
        }
        
        if ( empty( $fingerprint ) ) {
            return; // No fingerprint, can't track
        }
        
        // Check if this fingerprint already has a WooCommerce order (pending, processing, on-hold, or completed)
        // First check directly in WooCommerce orders by fingerprint meta
        if ( function_exists( 'wc_get_orders' ) ) {
            $valid_statuses = array( 'pending', 'processing', 'on-hold', 'completed' );
            $args = array(
                'limit' => 1,
                'status' => $valid_statuses,
                'meta_query' => array(
                    array(
                        'key' => '_bdc_browser_fingerprint',
                        'value' => $fingerprint,
                        'compare' => '=',
                    ),
                ),
            );

            $existing_orders = wc_get_orders( $args );

            if ( ! empty( $existing_orders ) ) {
                // This fingerprint already has a WooCommerce order, skip collection
                return;
            }
        }
        
        // Also check incomplete orders table for converted orders AND incomplete orders
        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        global $wpdb;
        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        if ( $table_exists ) {
            // Check for incomplete orders with this fingerprint - NO TIME RESTRICTION
            // Don't create new incomplete orders if one already exists, regardless of age
            $query = $wpdb->prepare(
                "SELECT id FROM $table_name 
                WHERE browser_fingerprint = %s 
                AND status = 'incomplete'
                LIMIT 1",
                $fingerprint
            );
            
            $existing_incomplete_id = $wpdb->get_var( $query );
            
            if ( ! empty( $existing_incomplete_id ) ) {
                // There's already an incomplete order, skip creating a new one
                return;
            }
            
            // Check for converted orders with this fingerprint
            $query = $wpdb->prepare(
                "SELECT converted_to_order_id FROM $table_name 
                WHERE browser_fingerprint = %s 
                AND converted_to_order_id IS NOT NULL
                ORDER BY created_at DESC
                LIMIT 10",
                $fingerprint
            );
            
            $converted_order_ids = $wpdb->get_col( $query );
            
            if ( ! empty( $converted_order_ids ) && function_exists( 'wc_get_order' ) ) {
                $valid_statuses = array( 'pending', 'processing', 'on-hold', 'completed' );
                foreach ( $converted_order_ids as $order_id ) {
                    $order = wc_get_order( $order_id );
                    if ( $order && in_array( $order->get_status(), $valid_statuses ) ) {
                        // This fingerprint already has a pending/active WooCommerce order, skip collection
                        return;
                    }
                }
            }
        }
        
        // Get customer data first to check for existing orders
        $phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
        $email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';

        // Format phone number
        $phone_formatted = '';
        if ( ! empty( $phone ) ) {
            $phone_formatted = preg_replace( '/[^0-9]/', '', $phone );
        }

        // Check for existing WooCommerce orders before creating incomplete order
        // Priority: 1. Fingerprint, 2. Phone, 3. Email
        $valid_statuses = array( 'pending', 'processing', 'on-hold', 'completed' );
        $existing_order_found = false;

        // PRIORITY 1: Check by FINGERPRINT
        if ( ! empty( $fingerprint ) ) {
            
            // Method 1: Direct database query (most reliable)
            global $wpdb;
            $order_meta_table = $wpdb->prefix . 'wc_orders_meta';
            $orders_table = $wpdb->prefix . 'wc_orders';
            
            // Check if HPOS tables exist (new WooCommerce structure)
            $hpos_exists = $wpdb->get_var( "SHOW TABLES LIKE '$order_meta_table'" ) === $order_meta_table;
            
            if ( $hpos_exists ) {
                // HPOS structure - direct query
                $query = $wpdb->prepare(
                    "SELECT o.id FROM $orders_table o
                    INNER JOIN $order_meta_table om ON o.id = om.order_id
                    WHERE om.meta_key = '_bdc_browser_fingerprint'
                    AND om.meta_value = %s
                    AND o.status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed')
                    LIMIT 1",
                    $fingerprint
                );
                
                $order_id = $wpdb->get_var( $query );
                
                if ( ! empty( $order_id ) ) {
                    $existing_order_found = true;
                }
            } else {
                // Old structure - use postmeta
                $postmeta_table = $wpdb->prefix . 'postmeta';
                $posts_table = $wpdb->prefix . 'posts';
                
                $query = $wpdb->prepare(
                    "SELECT p.ID FROM $posts_table p
                    INNER JOIN $postmeta_table pm ON p.ID = pm.post_id
                    WHERE pm.meta_key = '_bdc_browser_fingerprint'
                    AND pm.meta_value = %s
                    AND p.post_type = 'shop_order'
                    AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed')
                    LIMIT 1",
                    $fingerprint
                );
                
                $order_id = $wpdb->get_var( $query );
                
                if ( ! empty( $order_id ) ) {
                    $existing_order_found = true;
                }
            }
            
            // Method 2: Fallback using wc_get_orders (if database query didn't find anything)
            if ( ! $existing_order_found && function_exists( 'wc_get_orders' ) ) {
                try {
                    $args = array(
                        'limit' => 10,
                        'status' => $valid_statuses,
                        'meta_query' => array(
                            array(
                                'key' => '_bdc_browser_fingerprint',
                                'value' => $fingerprint,
                                'compare' => '=',
                            ),
                        ),
                    );

                    $orders = wc_get_orders( $args );
                    

                    if ( ! empty( $orders ) ) {
                        $existing_order_found = true;
                    }
                } catch ( Exception $e ) {
                }
            }
        }

        // PRIORITY 2: Check by PHONE (only if fingerprint check didn't find anything)
        if ( ! $existing_order_found && ! empty( $phone_formatted ) && strlen( $phone_formatted ) >= 7 && function_exists( 'wc_get_orders' ) ) {
            $args = array(
                'limit' => 1,
                'status' => $valid_statuses,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_billing_phone',
                        'value' => $phone_formatted,
                        'compare' => '=',
                    ),
                    array(
                        'key' => '_billing_phone',
                        'value' => $phone,
                        'compare' => '=',
                    ),
                ),
            );

            $orders = wc_get_orders( $args );

            // Also check manually for better phone matching
            if ( empty( $orders ) ) {
                $all_recent_orders = wc_get_orders( array(
                    'limit' => 100,
                    'status' => $valid_statuses,
                ) );

                foreach ( $all_recent_orders as $order ) {
                    $order_phone = $order->get_billing_phone();
                    if ( ! empty( $order_phone ) ) {
                        $order_phone_formatted = preg_replace( '/[^0-9]/', '', $order_phone );
                        if ( $order_phone_formatted === $phone_formatted ) {
                            $orders[] = $order;
                            break;
                        }
                    }
                }
            }

            if ( ! empty( $orders ) ) {
                $existing_order_found = true;
            }
        }

        // PRIORITY 3: Check by EMAIL (only if fingerprint and phone checks didn't find anything)
        if ( ! $existing_order_found && ! empty( $email ) && function_exists( 'wc_get_orders' ) ) {
            $args = array(
                'limit' => 1,
                'status' => $valid_statuses,
                'billing_email' => $email,
            );

            $orders = wc_get_orders( $args );

            if ( ! empty( $orders ) ) {
                $existing_order_found = true;
            }
        }

        // If existing order found, skip incomplete order collection
        if ( $existing_order_found ) {
            return;
        }

        // Only store if phone number is valid (at least 7 digits)
        if ( empty( $phone_formatted ) || strlen( $phone_formatted ) < 7 ) {
            return; // No valid phone number, don't store
        }


        // Get cart data - comprehensive collection
        $cart_data = array();
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            $cart_items_data = array();
            
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $product = $cart_item['data'];
                $cart_items_data[] = array(
                    'product_id' => $cart_item['product_id'],
                    'variation_id' => $cart_item['variation_id'],
                    'quantity' => $cart_item['quantity'],
                    'product_name' => $product->get_name(),
                    'product_price' => $product->get_price(),
                    'product_sku' => $product->get_sku(),
                    'line_total' => $cart_item['line_total'],
                    'line_subtotal' => $cart_item['line_subtotal'],
                    'line_tax' => $cart_item['line_tax'],
                );
            }
            
            $cart_data = array(
                'items' => $cart_items_data,
                'cart_total' => WC()->cart->get_total( 'edit' ),
                'cart_subtotal' => WC()->cart->get_subtotal(),
                'cart_tax' => WC()->cart->get_total_tax(),
                'cart_shipping' => WC()->cart->get_shipping_total(),
                'cart_discount' => WC()->cart->get_discount_total(),
                'item_count' => WC()->cart->get_cart_contents_count(),
                'currency' => get_woocommerce_currency(),
            );
        }

        // Get billing and shipping data
        $billing_data = array();
        $shipping_data = array();
        
        if ( isset( $_POST['billing_first_name'] ) ) {
            $billing_data = array(
                'first_name' => isset( $_POST['billing_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) : '',
                'last_name' => isset( $_POST['billing_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) ) : '',
                'company' => isset( $_POST['billing_company'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_company'] ) ) : '',
                'address_1' => isset( $_POST['billing_address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ) ) : '',
                'address_2' => isset( $_POST['billing_address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_address_2'] ) ) : '',
                'city' => isset( $_POST['billing_city'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_city'] ) ) : '',
                'state' => isset( $_POST['billing_state'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_state'] ) ) : '',
                'postcode' => isset( $_POST['billing_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ) ) : '',
                'country' => isset( $_POST['billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '',
                'phone' => isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '',
                'email' => $email,
            );
        }

        if ( isset( $_POST['ship_to_different_address'] ) && $_POST['ship_to_different_address'] ) {
            $shipping_data = array(
                'first_name' => isset( $_POST['shipping_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_first_name'] ) ) : '',
                'last_name' => isset( $_POST['shipping_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_last_name'] ) ) : '',
                'company' => isset( $_POST['shipping_company'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_company'] ) ) : '',
                'address_1' => isset( $_POST['shipping_address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_address_1'] ) ) : '',
                'address_2' => isset( $_POST['shipping_address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_address_2'] ) ) : '',
                'city' => isset( $_POST['shipping_city'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_city'] ) ) : '',
                'state' => isset( $_POST['shipping_state'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_state'] ) ) : '',
                'postcode' => isset( $_POST['shipping_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_postcode'] ) ) : '',
                'country' => isset( $_POST['shipping_country'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_country'] ) ) : '',
            );
        } else {
            $shipping_data = $billing_data;
        }

        // Determine checkout step
        $checkout_step = 'processing';
        if ( ! isset( $_POST['payment_method'] ) ) {
            $checkout_step = 'billing_info';
        } elseif ( ! isset( $_POST['terms'] ) ) {
            $checkout_step = 'payment_method';
        }

        // Get customer data (user info if logged in)
        $customer_data = array();
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $customer_data = array(
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
            );
        }

        // Get payment method
        $payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';
        if ( ! empty( $payment_method ) ) {
            $customer_data['payment_method'] = $payment_method;
        }

        // Get session and IP info
        $session_id = '';
        if ( function_exists( 'WC' ) && WC()->session ) {
            $session_id = WC()->session->get_customer_id();
        }
        if ( empty( $session_id ) ) {
            $session_id = wp_generate_password( 32, false );
        }
        
        $ip_address = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        // Prepare incomplete order data
        $incomplete_data = array(
            'browser_fingerprint' => $fingerprint,
            'phone' => $phone_formatted, // Store formatted phone
            'email' => $email,
            'cart_data' => $cart_data,
            'billing_data' => $billing_data,
            'shipping_data' => $shipping_data,
            'customer_data' => $customer_data,
            'checkout_step' => $checkout_step,
            'session_id' => $session_id,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'status' => 'incomplete',
        );

        // Check if we already have an incomplete order for this fingerprint
        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        $existing = CourierIncompleteOrders::get_incomplete_orders( array(
            'browser_fingerprint' => $fingerprint,
            'status' => 'incomplete',
            'limit' => 1,
        ) );

        if ( ! empty( $existing ) ) {
            // Update existing incomplete order
            global $wpdb;
            $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
            $existing_id = $existing[0]['id'];
            
            $update_data = array(
                'phone' => $incomplete_data['phone'],
                'email' => $incomplete_data['email'],
                'cart_data' => wp_json_encode( $incomplete_data['cart_data'] ),
                'billing_data' => wp_json_encode( $incomplete_data['billing_data'] ),
                'shipping_data' => wp_json_encode( $incomplete_data['shipping_data'] ),
                'customer_data' => wp_json_encode( $incomplete_data['customer_data'] ),
                'checkout_step' => $incomplete_data['checkout_step'],
                'updated_at' => current_time( 'mysql' ),
            );
            
            $result = $wpdb->update(
                $table_name,
                $update_data,
                array( 'id' => $existing_id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            
            if ( $result !== false ) {
            } else {
            }
        } else {
            // Store new incomplete order
            $result = CourierIncompleteOrders::store_incomplete_order( $incomplete_data );
            
            if ( $result ) {
            } else {
            }
        }
    }

    /**
     * Mark incomplete order as complete when order is processed.
     * Deletes incomplete orders with the same phone number when customer places order.
     * Also stores fingerprint in order meta for duplicate checking.
     *
     * @param int $order_id Order ID.
     */
    public function mark_incomplete_as_complete( $order_id ) {
        $collect_incomplete = get_option( 'bd_courier_collect_incomplete_data', false );
        if ( ! $collect_incomplete ) {
            return;
        }

        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Get fingerprint from POST data (if available) and store in order meta
        $fingerprint = isset( $_POST['bdc_browser_fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['bdc_browser_fingerprint'] ) ) : '';
        if ( empty( $fingerprint ) && isset( $_REQUEST['bdc_browser_fingerprint'] ) ) {
            $fingerprint = sanitize_text_field( wp_unslash( $_REQUEST['bdc_browser_fingerprint'] ) );
        }
        
        // Store fingerprint and IP address in order meta for duplicate checking
        if ( ! empty( $fingerprint ) ) {
            $order->update_meta_data( '_bdc_browser_fingerprint', $fingerprint );
            $order->save();
        }

        // Store client IP address for duplicate checking
        $client_ip = $this->get_client_ip();
        if ( ! empty( $client_ip ) ) {
            $order->update_meta_data( '_bdc_client_ip', $client_ip );
            $order->save();
        }

        $phone = $order->get_billing_phone();
        if ( ! empty( $phone ) ) {
            $phone = preg_replace( '/[^0-9]/', '', $phone );
        }

        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        
        // Find incomplete orders for this phone number
        $incomplete_orders = array();
        if ( ! empty( $phone ) ) {
            $incomplete_orders = CourierIncompleteOrders::get_incomplete_orders( array(
                'phone' => $phone,
                'status' => 'incomplete',
                'limit' => 100, // Get all incomplete orders for this phone
            ) );
        }

        // Also check by email if no phone match
        if ( empty( $incomplete_orders ) && ! empty( $order->get_billing_email() ) ) {
            $incomplete_orders = CourierIncompleteOrders::get_incomplete_orders( array(
                'email' => $order->get_billing_email(),
                'status' => 'incomplete',
                'limit' => 100,
            ) );
        }

        // Delete incomplete orders when customer successfully places order
        if ( ! empty( $incomplete_orders ) ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
            
            foreach ( $incomplete_orders as $incomplete_order ) {
                $deleted = $wpdb->delete(
                    $table_name,
                    array( 'id' => $incomplete_order['id'] ),
                    array( '%d' )
                );
                
                if ( $deleted !== false ) {
                }
            }
        }
    }

    /**
     * Check blocked entities (IP, phone, email, fingerprint) during checkout.
     * This runs at the start of check_duplicate_orders() to block known fraudsters first.
     */
    private function check_blocked_entities() {
        // Skip validation for logged-in users with manage_woocommerce capability
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return;
        }

        // Get blocked entities class
        require_once dirname( __FILE__ ) . '/class.CourierBlockedEntities.php';

        $phone_raw = isset( $_POST['billing_phone'] ) ? sanitize_text_field( $_POST['billing_phone'] ) : '';
        $email = isset( $_POST['billing_email'] ) ? sanitize_email( $_POST['billing_email'] ) : '';
        $fingerprint = isset( $_POST['bdc_browser_fingerprint'] ) ? sanitize_text_field( $_POST['bdc_browser_fingerprint'] ) : '';
        
        // Get client IP
        $ip = $this->get_client_ip();
        
        // Clean phone number
        $phone = preg_replace( '/[^0-9]/', '', $phone_raw );


        $blocked_entities = array();
        $blocked_types = array();

        // Check phone number
        if ( $phone && CourierBlockedEntities::is_entity_blocked( CourierBlockedEntities::BLOCK_TYPE_PHONE, $phone ) ) {
            $blocked_entities[] = 'Phone number';
            $blocked_types[] = CourierBlockedEntities::BLOCK_TYPE_PHONE;
        }

        // Check IP address
        if ( ! empty( $ip ) ) {
            $ip_trimmed = trim( $ip );
            if ( CourierBlockedEntities::is_entity_blocked( CourierBlockedEntities::BLOCK_TYPE_IP, $ip_trimmed ) ) {
                $blocked_entities[] = 'IP address';
                $blocked_types[] = CourierBlockedEntities::BLOCK_TYPE_IP;
            }
        }

        // Check email address
        if ( $email && CourierBlockedEntities::is_entity_blocked( CourierBlockedEntities::BLOCK_TYPE_EMAIL, $email ) ) {
            $blocked_entities[] = 'Email address';
            $blocked_types[] = CourierBlockedEntities::BLOCK_TYPE_EMAIL;
        }

        // Check browser fingerprint
        if ( $fingerprint && CourierBlockedEntities::is_entity_blocked( CourierBlockedEntities::BLOCK_TYPE_FINGERPRINT, $fingerprint ) ) {
            $blocked_entities[] = 'Browser fingerprint';
            $blocked_types[] = CourierBlockedEntities::BLOCK_TYPE_FINGERPRINT;
        }

        if ( ! empty( $blocked_entities ) ) {
            // Build error message
            $blocked_items_list = implode( ', ', $blocked_entities );
            // Get customizable error message
            $custom_message = get_option( 'bdc_error_blocked_entities', 'Order Blocked: Your order cannot be processed due to security restrictions. Blocked items: %s. Please contact support if you believe this is an error.' );
            $error_message = sprintf( $custom_message, $blocked_items_list );


            // Add error notice
            $notice_id = 'bdc_blocked_entity_' . md5( implode( '_', $blocked_types ) );
            wc_add_notice( $error_message, 'error', array( 'id' => $notice_id ) );

            // Set session flag
            if ( function_exists( 'WC' ) && WC()->session ) {
                WC()->session->set( 'bdc_order_blocked', true );
                WC()->session->set( 'bdc_blocked_reason', $error_message );
            }

            // Log rejection
            if ( class_exists( 'CourierRejectedOrderLogs' ) ) {
                $rejection_details = sprintf(
                    'Order rejected due to blocked entity check. Blocked items: %s. This is a security restriction.',
                    $blocked_items_list
                );

                CourierRejectedOrderLogs::log_rejection( array(
                    'order_id' => null,
                    'customer_email' => $email,
                    'customer_phone' => $phone,
                    'customer_ip' => $ip,
                    'customer_device_fingerprint' => $fingerprint,
                    'rejection_reason' => 'blocked-entity-checkout',
                    'rejection_details' => $rejection_details,
                ) );
            }

            // Stop checkout processing
            return;
        }
    }

    /**
     * Get client IP address (same method as CourierBlockedEntities).
     * Includes private/localhost IPs for blocking purposes.
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $ip_headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = $_SERVER[ $header ];

                // Handle comma-separated IPs (like X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }

                // Validate IP (including private/localhost IPs for blocking purposes)
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
    }

    /**
     * Check for duplicate orders and prevent if enabled.
     * Checks by email, phone, and browser fingerprint.
     */
    public function check_duplicate_orders() {
        // First check blocked entities (IP, phone, email, fingerprint)
        // This runs BEFORE duplicate check to block known fraudsters
        $this->check_blocked_entities();
        // Check if feature is enabled
        $disable_duplicates = get_option( 'bd_courier_disable_duplicate_orders', false );
        if ( ! $disable_duplicates ) {
            return;
        }

        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return;
        }

        $duplicate_hours = absint( get_option( 'bd_courier_duplicate_order_hours', 24 ) );
        if ( $duplicate_hours < 1 ) {
            return;
        }

        // Get email, phone, and fingerprint
        $email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
        $phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
        $fingerprint = isset( $_POST['bdc_browser_fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['bdc_browser_fingerprint'] ) ) : '';

        // Also check in REQUEST in case it's not in POST
        if ( empty( $fingerprint ) && isset( $_REQUEST['bdc_browser_fingerprint'] ) ) {
            $fingerprint = sanitize_text_field( wp_unslash( $_REQUEST['bdc_browser_fingerprint'] ) );
        }

        // Debug logging - log all POST data keys for debugging

        // Format phone number (remove all non-numeric characters)
        $phone_formatted = '';
        if ( ! empty( $phone ) ) {
            $phone_formatted = preg_replace( '/[^0-9]/', '', $phone );
        }

        $time_threshold = time() - ( $duplicate_hours * HOUR_IN_SECONDS );
        $valid_statuses = array( 'pending', 'processing', 'on-hold', 'completed' );

        // ============================================
        // PRIORITY 1: Check by FINGERPRINT FIRST
        // This is the most important check - prevents duplicates from same device
        // ============================================
        // This prevents multiple orders from the same device regardless of email/phone
        if ( ! empty( $fingerprint ) ) {
            
            // First, check WooCommerce orders directly by fingerprint stored in order meta
            if ( function_exists( 'wc_get_orders' ) ) {
                $args = array(
                    'limit' => 10,
                    'status' => $valid_statuses,
                    'date_created' => '>' . date( 'Y-m-d H:i:s', $time_threshold ),
                    'meta_query' => array(
                        array(
                            'key' => '_bdc_browser_fingerprint',
                            'value' => $fingerprint,
                            'compare' => '=',
                        ),
                    ),
                );

                $orders = wc_get_orders( $args );

                if ( ! empty( $orders ) ) {

                    // Log the duplicate order rejection
                    require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';

                    $log_data = array(
                        'customer_email' => $email,
                        'customer_phone' => $phone_formatted,
                        'customer_ip' => $this->get_client_ip(),
                        'customer_device_fingerprint' => $fingerprint,
                        'rejection_reason' => 'duplicate-order-same-device',
                        'rejection_details' => sprintf(
                            'Duplicate order blocked. Existing order #%d from same device within %d hours.',
                            $orders[0]->get_id(),
                            $duplicate_hours
                        ),
                        'order_total' => $orders[0]->get_total(),
                        'order_currency' => $orders[0]->get_currency(),
                        'cart_items_count' => $orders[0]->get_item_count(),
                        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                        'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                    );

                    CourierRejectedOrderLogs::log_rejection( $log_data );

                    wc_add_notice(
                        sprintf(
                            __( 'An order from this device was already placed in the last %d hour(s). Please wait before placing another order.', 'bd-courier-order-ratio-checker' ),
                            $duplicate_hours
                        ),
                        'error'
                    );
                    return;
                }
            }
            
            // Also check incomplete orders table for converted orders AND incomplete orders
            require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
            global $wpdb;
            $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
            
            // Check if table exists
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
            
            if ( $table_exists ) {
                // First check if there are any actual WooCommerce orders with this fingerprint
                // If no WooCommerce orders exist, we should allow new orders even if incomplete orders exist
                $has_woocommerce_orders = false;
                
                // Check HPOS structure
                $order_meta_table = $wpdb->prefix . 'wc_orders_meta';
                $orders_table = $wpdb->prefix . 'wc_orders';
                $hpos_exists = $wpdb->get_var( "SHOW TABLES LIKE '$order_meta_table'" ) === $order_meta_table;
                
                if ( $hpos_exists ) {
                    $woo_order_id = $wpdb->get_var( $wpdb->prepare(
                        "SELECT o.id FROM $orders_table o
                        INNER JOIN $order_meta_table om ON o.id = om.order_id
                        WHERE om.meta_key = '_bdc_browser_fingerprint'
                        AND om.meta_value = %s
                        AND o.status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed')
                        LIMIT 1",
                        $fingerprint
                    ) );
                    $has_woocommerce_orders = ! empty( $woo_order_id );
                } else {
                    // Old structure
                    $postmeta_table = $wpdb->prefix . 'postmeta';
                    $posts_table = $wpdb->prefix . 'posts';
                    $woo_order_id = $wpdb->get_var( $wpdb->prepare(
                        "SELECT p.ID FROM $posts_table p
                        INNER JOIN $postmeta_table pm ON p.ID = pm.post_id
                        WHERE pm.meta_key = '_bdc_browser_fingerprint'
                        AND pm.meta_value = %s
                        AND p.post_type = 'shop_order'
                        AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed')
                        LIMIT 1",
                        $fingerprint
                    ) );
                    $has_woocommerce_orders = ! empty( $woo_order_id );
                }
                
                // Only check incomplete orders if there are actual WooCommerce orders
                // If no WooCommerce orders exist, allow new orders (incomplete orders will be cleaned up)
                if ( $has_woocommerce_orders ) {
                    // Check for incomplete orders with this fingerprint (within time window)
                    // This prevents creating new orders if there's already an incomplete order in progress
                    $query = $wpdb->prepare(
                        "SELECT id, created_at FROM $table_name 
                        WHERE browser_fingerprint = %s 
                        AND status = 'incomplete'
                        AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
                        ORDER BY created_at DESC
                        LIMIT 1",
                        $fingerprint,
                        $duplicate_hours
                    );
                    
                    $incomplete_orders = $wpdb->get_results( $query, ARRAY_A );
                    
                    if ( ! empty( $incomplete_orders ) ) {
                        $incomplete_order = $incomplete_orders[0];
                        $created_timestamp = strtotime( $incomplete_order['created_at'] );
                        
                        if ( $created_timestamp > $time_threshold ) {

                            // Log the incomplete order rejection
                            // Temporarily disabled for debugging
                            /*
                            require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';

                            $log_data = array(
                                'customer_email' => $email,
                                'customer_phone' => $phone_formatted,
                                'customer_ip' => $this->get_client_ip(),
                                'customer_device_fingerprint' => $fingerprint,
                                'rejection_reason' => 'incomplete-order-timeout',
                                'rejection_details' => sprintf(
                                    'Incomplete order #%d found from same device. Must complete or cancel before placing new order.',
                                    $incomplete_order['id']
                                ),
                                'cart_items_count' => isset($incomplete_order['cart_items']) ? count(json_decode($incomplete_order['cart_items'], true)) : 0,
                                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                                'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                            );

                            CourierRejectedOrderLogs::log_rejection( $log_data );
                            */

                            wc_add_notice(
                                __( 'You have an incomplete order in progress from this device. Please complete or cancel it before placing a new order.', 'bd-courier-order-ratio-checker' ),
                                'error'
                            );
                            return;
                        }
                    }
                } else {
                    // No WooCommerce orders exist - clean up old incomplete orders and allow new orders
                    
                    // Delete old incomplete orders for this fingerprint
                    $wpdb->query( $wpdb->prepare(
                        "DELETE FROM $table_name 
                        WHERE browser_fingerprint = %s 
                        AND status = 'incomplete'",
                        $fingerprint
                    ) );
                    
                }
                
                // Check for converted orders with this fingerprint
                $query = $wpdb->prepare(
                    "SELECT converted_to_order_id FROM $table_name 
                    WHERE browser_fingerprint = %s 
                    AND converted_to_order_id IS NOT NULL
                    AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
                    ORDER BY created_at DESC
                    LIMIT 50",
                    $fingerprint,
                    $duplicate_hours
                );
                
                $converted_order_ids = $wpdb->get_col( $query );

                if ( ! empty( $converted_order_ids ) && function_exists( 'wc_get_order' ) ) {
                    foreach ( $converted_order_ids as $order_id ) {
                        $order = wc_get_order( $order_id );
                        if ( $order && in_array( $order->get_status(), $valid_statuses ) ) {
                            $order_date = $order->get_date_created();
                            if ( $order_date ) {
                                $order_timestamp = $order_date->getTimestamp();
                                
                                // Check if order is within the time threshold
                                if ( $order_timestamp > $time_threshold ) {

                                    // Log the duplicate order rejection
                                    require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';

                                    $log_data = array(
                                        'customer_email' => $email,
                                        'customer_phone' => $phone_formatted,
                                        'customer_ip' => $this->get_client_ip(),
                                        'customer_device_fingerprint' => $fingerprint,
                                        'rejection_reason' => 'duplicate-order-same-device',
                                        'rejection_details' => sprintf(
                                            'Duplicate order blocked. Existing converted order #%d from same device within %d hours.',
                                            $order->get_id(),
                                            $duplicate_hours
                                        ),
                                        'order_total' => $order->get_total(),
                                        'order_currency' => $order->get_currency(),
                                        'cart_items_count' => $order->get_item_count(),
                                        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                                        'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                                    );

                                    CourierRejectedOrderLogs::log_rejection( $log_data );

                                    wc_add_notice(
                                        sprintf(
                                            __( 'An order from this device was already placed in the last %d hour(s). Please wait before placing another order.', 'bd-courier-order-ratio-checker' ),
                                            $duplicate_hours
                                        ),
                                        'error'
                                    );
                                    return;
                                }
                            }
                        }
                    }
                }
            } else {
            }
        } else {
        }

        // ============================================
        // PRIORITY 2: Check by IP ADDRESS (only if fingerprint check passed)
        // ============================================
        $client_ip = $this->get_client_ip();
        if ( ! empty( $client_ip ) ) {

            // Check WooCommerce orders by IP address in order meta
            $args = array(
                'limit' => 5,
                'status' => $valid_statuses,
                'date_created' => '>' . date( 'Y-m-d H:i:s', $time_threshold ),
                'meta_query' => array(
                    array(
                        'key' => '_bdc_client_ip',
                        'value' => $client_ip,
                        'compare' => '=',
                    ),
                ),
            );

            $orders = wc_get_orders( $args );

            if ( ! empty( $orders ) ) {

                // Log the duplicate order rejection by IP
                require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';

                $log_data = array(
                    'customer_email' => $email,
                    'customer_phone' => $phone_formatted,
                    'customer_ip' => $client_ip,
                    'customer_device_fingerprint' => $fingerprint,
                    'rejection_reason' => 'duplicate-order-same-ip',
                    'rejection_details' => sprintf(
                        'Duplicate order blocked. Existing order #%d with same IP address within %d hours.',
                        $orders[0]->get_id(),
                        $duplicate_hours
                    ),
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                    'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                );

                CourierRejectedOrderLogs::log_rejection( $log_data );

                // Get customizable error message
                $custom_message = get_option( 'bdc_error_duplicate_order', 'An order from this IP address was already placed in the last %d hour(s). Please wait before placing another order.' );
                wc_add_notice(
                    sprintf( $custom_message, $duplicate_hours ),
                    'error'
                );
                return;
            }

            // Also check incomplete orders by IP address
            require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
            global $wpdb;
            $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
                // Check for incomplete orders with same IP
                $query = $wpdb->prepare(
                    "SELECT id, created_at FROM $table_name
                    WHERE ip_address = %s
                    AND status = 'incomplete'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
                    ORDER BY created_at DESC
                    LIMIT 5",
                    $client_ip,
                    $duplicate_hours
                );

                $incomplete_orders = $wpdb->get_results( $query, ARRAY_A );

                if ( ! empty( $incomplete_orders ) ) {

                    // Log the duplicate incomplete order rejection by IP
                    require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';

                    $log_data = array(
                        'customer_email' => $email,
                        'customer_phone' => $phone_formatted,
                        'customer_ip' => $client_ip,
                        'customer_device_fingerprint' => $fingerprint,
                        'rejection_reason' => 'incomplete-order-timeout',
                        'rejection_details' => sprintf(
                            'Incomplete order #%d found from same IP address. Must complete or cancel before placing new order.',
                            $incomplete_orders[0]['id']
                        ),
                        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                        'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                    );

                    CourierRejectedOrderLogs::log_rejection( $log_data );

                    wc_add_notice(
                        __( 'You have an incomplete order in progress from this IP address. Please complete or cancel it before placing a new order.', 'bd-courier-order-ratio-checker' ),
                        'error'
                    );
                    return;
                }
            }
        }

        // ============================================
        // PRIORITY 3: Check by PHONE (only if fingerprint and IP checks passed)
        // ============================================
        if ( ! empty( $phone_formatted ) ) {
            // First check WooCommerce orders by phone
            $args = array(
                'limit' => 10,
                'status' => $valid_statuses,
                'date_created' => '>' . date( 'Y-m-d H:i:s', $time_threshold ),
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_billing_phone',
                        'value' => $phone_formatted,
                        'compare' => '=',
                    ),
                    array(
                        'key' => '_billing_phone',
                        'value' => $phone, // Original phone with formatting
                        'compare' => '=',
                    ),
                ),
            );

            $orders = wc_get_orders( $args );

            // Also check if any order has matching phone (normalized comparison)
            if ( empty( $orders ) ) {
                // Get all recent orders and check phone manually for better matching
                $all_recent_orders = wc_get_orders( array(
                    'limit' => 100,
                    'status' => $valid_statuses,
                    'date_created' => '>' . date( 'Y-m-d H:i:s', $time_threshold ),
                ) );

                foreach ( $all_recent_orders as $order ) {
                    $order_phone = $order->get_billing_phone();
                    if ( ! empty( $order_phone ) ) {
                        $order_phone_formatted = preg_replace( '/[^0-9]/', '', $order_phone );
                        if ( $order_phone_formatted === $phone_formatted ) {
                            $orders[] = $order;
                            break;
                        }
                    }
                }
            }

            // Also check incomplete orders converted to orders
            require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
            $converted_order_ids = CourierIncompleteOrders::get_recent_orders_by_phone( $phone, $duplicate_hours );
            
            if ( ! empty( $converted_order_ids ) ) {
                foreach ( $converted_order_ids as $order_id ) {
                    $order = wc_get_order( $order_id );
                    if ( $order && in_array( $order->get_status(), $valid_statuses ) ) {
                        $order_date = $order->get_date_created();
                        if ( $order_date ) {
                            $order_timestamp = $order_date->getTimestamp();
                            if ( $order_timestamp > $time_threshold ) {
                                $orders[] = $order;
                                break;
                            }
                        }
                    }
                }
            }

            if ( ! empty( $orders ) ) {

                // Log the duplicate order rejection by phone
                require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';

                $log_data = array(
                    'customer_email' => $email,
                    'customer_phone' => $phone_formatted,
                    'customer_ip' => $this->get_client_ip(),
                    'customer_device_fingerprint' => $fingerprint,
                    'rejection_reason' => 'duplicate-order-same-phone',
                    'rejection_details' => sprintf(
                        'Duplicate order blocked. Existing order #%d with same phone number within %d hours.',
                        $orders[0]->get_id(),
                        $duplicate_hours
                    ),
                    'order_total' => $orders[0]->get_total(),
                    'order_currency' => $orders[0]->get_currency(),
                    'cart_items_count' => $orders[0]->get_item_count(),
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                    'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                );

                CourierRejectedOrderLogs::log_rejection( $log_data );

                wc_add_notice(
                    sprintf(
                        __( 'An order with this phone number was already placed in the last %d hour(s). Please wait before placing another order.', 'bd-courier-order-ratio-checker' ),
                        $duplicate_hours
                    ),
                    'error'
                );
                return;
            }
        }

        // ============================================
        // PRIORITY 3: Check by EMAIL (only if fingerprint and phone checks passed)
        // ============================================
        if ( ! empty( $email ) ) {
            $args = array(
                'limit' => 1,
                'status' => $valid_statuses,
                'date_created' => '>' . $time_threshold,
                'billing_email' => $email,
            );

            $orders = wc_get_orders( $args );

            if ( ! empty( $orders ) ) {

                // Log the duplicate order rejection by email
                require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';

                $log_data = array(
                    'customer_email' => $email,
                    'customer_phone' => $phone_formatted,
                    'customer_ip' => $this->get_client_ip(),
                    'customer_device_fingerprint' => $fingerprint,
                    'rejection_reason' => 'duplicate-order-same-email',
                    'rejection_details' => sprintf(
                        'Duplicate order blocked. Existing order #%d with same email address within %d hours.',
                        $orders[0]->get_id(),
                        $duplicate_hours
                    ),
                    'order_total' => $orders[0]->get_total(),
                    'order_currency' => $orders[0]->get_currency(),
                    'cart_items_count' => $orders[0]->get_item_count(),
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                    'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                );

                CourierRejectedOrderLogs::log_rejection( $log_data );

                wc_add_notice(
                    sprintf(
                        __( 'An order with this email address was already placed in the last %d hour(s). Please wait before placing another order.', 'bd-courier-order-ratio-checker' ),
                        $duplicate_hours
                    ),
                    'error'
                );
                return;
            }
        }

    }

    /**
     * AJAX handler to save incomplete order data.
     */
    public function save_incomplete_order_ajax() {
        check_ajax_referer( 'bdc_incomplete_order_nonce', 'nonce' );

        $collect_incomplete = get_option( 'bd_courier_collect_incomplete_data', false );
        if ( ! $collect_incomplete ) {
            wp_send_json_error( array( 'message' => 'Feature not enabled' ) );
        }

        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            // Return success but don't save - allows frontend to continue without interruption
            wp_send_json_success( array( 'message' => 'Data collection skipped (site not authorized)' ) );
            return;
        }

        $fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['fingerprint'] ) ) : '';
        if ( empty( $fingerprint ) ) {
            wp_send_json_error( array( 'message' => 'No fingerprint provided' ) );
        }

        // This would be called from JavaScript to save incomplete order data
        // Implementation can be extended based on specific needs
        
        wp_send_json_success( array( 'message' => 'Incomplete order data saved' ) );
    }

    /**
     * AJAX handler to update incomplete order data in real-time.
     */
    public function update_incomplete_order_ajax() {
        check_ajax_referer( 'bdc_incomplete_order_nonce', 'nonce' );

        $collect_incomplete = get_option( 'bd_courier_collect_incomplete_data', false );
        if ( ! $collect_incomplete ) {
            wp_send_json_error( array( 'message' => 'Feature not enabled' ) );
        }

        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            // Return success but don't update - allows frontend to continue without interruption
            wp_send_json_success( array( 
                'message' => 'Data update skipped (site not authorized)',
                'skipped' => true
            ) );
            return;
        }

        // Get browser fingerprint
        $fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['fingerprint'] ) ) : '';
        if ( empty( $fingerprint ) ) {
            wp_send_json_error( array( 'message' => 'No fingerprint provided' ) );
        }

        // Get phone number - this is required
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        if ( ! empty( $phone ) ) {
            $phone = preg_replace( '/[^0-9]/', '', $phone );
        }

        // Only proceed if phone number is valid (at least 7 digits)
        if ( empty( $phone ) || strlen( $phone ) < 7 ) {
            wp_send_json_error( array( 'message' => 'Valid phone number required (minimum 7 digits)' ) );
        }

        // Get other form data
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $billing_data = isset( $_POST['billing_data'] ) ? json_decode( stripslashes( $_POST['billing_data'] ), true ) : array();
        $shipping_data = isset( $_POST['shipping_data'] ) ? json_decode( stripslashes( $_POST['shipping_data'] ), true ) : array();
        $cart_data = isset( $_POST['cart_data'] ) ? json_decode( stripslashes( $_POST['cart_data'] ), true ) : array();
        $checkout_step = isset( $_POST['checkout_step'] ) ? sanitize_text_field( wp_unslash( $_POST['checkout_step'] ) ) : 'billing_info';

        // If cart data is empty or incomplete, try to get it from WooCommerce cart
        if ( empty( $cart_data ) || ( isset( $cart_data['items'] ) && empty( $cart_data['items'] ) ) ) {
            $cart_data = $this->get_cart_data_from_woocommerce();
        }

        // Get session and IP info
        $session_id = '';
        if ( function_exists( 'WC' ) && WC()->session ) {
            $session_id = WC()->session->get_customer_id();
        }
        if ( empty( $session_id ) ) {
            $session_id = wp_generate_password( 32, false );
        }
        
        $ip_address = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        // Get customer data (user info if logged in)
        $customer_data = array();
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $customer_data = array(
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
            );
        }

        // Get payment method if provided
        $payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';
        if ( ! empty( $payment_method ) ) {
            $customer_data['payment_method'] = $payment_method;
        }

        // Check for existing WooCommerce orders before creating/updating incomplete order
        // Priority: 1. Fingerprint, 2. Phone, 3. Email
        $valid_statuses = array( 'pending', 'processing', 'on-hold', 'completed' );
        $existing_order_found = false;

        // PRIORITY 1: Check by FINGERPRINT
        if ( ! empty( $fingerprint ) ) {
            
            // Direct database query (most reliable)
            global $wpdb;
            $order_meta_table = $wpdb->prefix . 'wc_orders_meta';
            $orders_table = $wpdb->prefix . 'wc_orders';
            
            // Check if HPOS tables exist
            $hpos_exists = $wpdb->get_var( "SHOW TABLES LIKE '$order_meta_table'" ) === $order_meta_table;
            
            if ( $hpos_exists ) {
                // HPOS structure
                $query = $wpdb->prepare(
                    "SELECT o.id FROM $orders_table o
                    INNER JOIN $order_meta_table om ON o.id = om.order_id
                    WHERE om.meta_key = '_bdc_browser_fingerprint'
                    AND om.meta_value = %s
                    AND o.status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed')
                    LIMIT 1",
                    $fingerprint
                );
                
                $order_id = $wpdb->get_var( $query );
                
                if ( ! empty( $order_id ) ) {
                    $existing_order_found = true;
                }
            } else {
                // Old structure
                $postmeta_table = $wpdb->prefix . 'postmeta';
                $posts_table = $wpdb->prefix . 'posts';
                
                $query = $wpdb->prepare(
                    "SELECT p.ID FROM $posts_table p
                    INNER JOIN $postmeta_table pm ON p.ID = pm.post_id
                    WHERE pm.meta_key = '_bdc_browser_fingerprint'
                    AND pm.meta_value = %s
                    AND p.post_type = 'shop_order'
                    AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed')
                    LIMIT 1",
                    $fingerprint
                );
                
                $order_id = $wpdb->get_var( $query );
                
                if ( ! empty( $order_id ) ) {
                    $existing_order_found = true;
                }
            }
            
            // Fallback using wc_get_orders
            if ( ! $existing_order_found && function_exists( 'wc_get_orders' ) ) {
                try {
                    $args = array(
                        'limit' => 1,
                        'status' => $valid_statuses,
                        'meta_query' => array(
                            array(
                                'key' => '_bdc_browser_fingerprint',
                                'value' => $fingerprint,
                                'compare' => '=',
                            ),
                        ),
                    );

                    $orders = wc_get_orders( $args );

                    if ( ! empty( $orders ) ) {
                        $existing_order_found = true;
                    }
                } catch ( Exception $e ) {
                }
            }
        }

        // PRIORITY 2: Check by PHONE (only if fingerprint check didn't find anything)
        if ( ! $existing_order_found && ! empty( $phone ) && strlen( $phone ) >= 7 && function_exists( 'wc_get_orders' ) ) {
            $args = array(
                'limit' => 1,
                'status' => $valid_statuses,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_billing_phone',
                        'value' => $phone,
                        'compare' => '=',
                    ),
                ),
            );

            $orders = wc_get_orders( $args );

            // Manual phone matching
            if ( empty( $orders ) ) {
                $all_recent_orders = wc_get_orders( array(
                    'limit' => 100,
                    'status' => $valid_statuses,
                ) );

                foreach ( $all_recent_orders as $order ) {
                    $order_phone = $order->get_billing_phone();
                    if ( ! empty( $order_phone ) ) {
                        $order_phone_formatted = preg_replace( '/[^0-9]/', '', $order_phone );
                        if ( $order_phone_formatted === $phone ) {
                            $orders[] = $order;
                            break;
                        }
                    }
                }
            }

            if ( ! empty( $orders ) ) {
                $existing_order_found = true;
            }
        }

        // PRIORITY 3: Check by EMAIL (only if fingerprint and phone checks didn't find anything)
        if ( ! $existing_order_found && ! empty( $email ) && function_exists( 'wc_get_orders' ) ) {
            $args = array(
                'limit' => 1,
                'status' => $valid_statuses,
                'billing_email' => $email,
            );

            $orders = wc_get_orders( $args );

            if ( ! empty( $orders ) ) {
                $existing_order_found = true;
            }
        }

        // If existing order found, skip incomplete order creation/update
        if ( $existing_order_found ) {
            wp_send_json_success( array( 
                'message' => 'WooCommerce order already exists, skipping incomplete order update',
                'skipped' => true
            ) );
            return;
        }

        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        
        // Check if we already have an incomplete order for this fingerprint
        $existing = CourierIncompleteOrders::get_incomplete_orders( array(
            'browser_fingerprint' => $fingerprint,
            'status' => 'incomplete',
            'limit' => 1,
        ) );

        if ( ! empty( $existing ) ) {
            // Update existing incomplete order
            global $wpdb;
            $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
            $existing_id = $existing[0]['id'];
            
            $update_data = array(
                'phone' => $phone,
                'email' => $email,
                'cart_data' => wp_json_encode( $cart_data ),
                'billing_data' => wp_json_encode( $billing_data ),
                'shipping_data' => wp_json_encode( $shipping_data ),
                'customer_data' => wp_json_encode( $customer_data ),
                'checkout_step' => $checkout_step,
                'updated_at' => current_time( 'mysql' ),
            );
            
            $result = $wpdb->update(
                $table_name,
                $update_data,
                array( 'id' => $existing_id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            
            if ( $result !== false ) {
                wp_send_json_success( array( 
                    'message' => 'Incomplete order updated',
                    'id' => $existing_id 
                ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to update incomplete order' ) );
            }
        } else {
            // Store new incomplete order
            $incomplete_data = array(
                'browser_fingerprint' => $fingerprint,
                'phone' => $phone,
                'email' => $email,
                'cart_data' => $cart_data,
                'billing_data' => $billing_data,
                'shipping_data' => $shipping_data,
                'customer_data' => $customer_data,
                'checkout_step' => $checkout_step,
                'session_id' => $session_id,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'status' => 'incomplete',
            );

            $result = CourierIncompleteOrders::store_incomplete_order( $incomplete_data );
            
            if ( $result ) {
                wp_send_json_success( array( 
                    'message' => 'Incomplete order stored',
                    'id' => $result 
                ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to store incomplete order' ) );
            }
        }
    }

    /**
     * Get cart data from WooCommerce cart object.
     *
     * @return array
     */
    private function get_cart_data_from_woocommerce() {
        $cart_data = array(
            'items' => array(),
            'cart_total' => '0',
            'cart_subtotal' => '0',
            'cart_tax' => '0',
            'cart_shipping' => '0',
            'cart_discount' => '0',
            'item_count' => 0,
            'currency' => get_woocommerce_currency(),
        );

        if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
            return $cart_data;
        }

        $cart_items_data = array();
        
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            $cart_items_data[] = array(
                'product_id' => $cart_item['product_id'],
                'variation_id' => $cart_item['variation_id'],
                'quantity' => $cart_item['quantity'],
                'product_name' => $product->get_name(),
                'product_price' => $product->get_price(),
                'product_sku' => $product->get_sku(),
                'line_total' => $cart_item['line_total'],
                'line_subtotal' => $cart_item['line_subtotal'],
                'line_tax' => $cart_item['line_tax'],
            );
        }
        
        $cart_data = array(
            'items' => $cart_items_data,
            'cart_total' => WC()->cart->get_total( 'edit' ),
            'cart_subtotal' => WC()->cart->get_subtotal(),
            'cart_tax' => WC()->cart->get_total_tax(),
            'cart_shipping' => WC()->cart->get_shipping_total(),
            'cart_discount' => WC()->cart->get_discount_total(),
            'item_count' => WC()->cart->get_cart_contents_count(),
            'currency' => get_woocommerce_currency(),
        );

        return $cart_data;
    }

    /**
     * Check if current page is a CartFlows checkout page.
     *
     * @return bool
     */
    private function is_cartflows_checkout_page() {
        // Check if CartFlows is active and we're on a CartFlows checkout
        if ( ! function_exists( 'cartflows' ) ) {
            return false;
        }
        
        global $post;
        if ( ! $post ) {
            return false;
        }
        
        // Check if it's a CartFlows checkout page
        $cartflows_step = get_post_meta( $post->ID, 'wcf-step-type', true );
        return $cartflows_step === 'checkout';
    }

}

