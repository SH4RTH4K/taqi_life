<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierAutoCheck
 * Handles automatic courier order ratio checking for new orders.
 */
class CourierAutoCheck {

    public function __construct() {
        // Hook into checkout validation to check before order is created
        // Use priority 5 to run early, before other validations
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_order_ratio_before_checkout' ), 5 );
        
        // Hook into order creation/processing (backup check)
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'check_order_ratio_on_new_order' ), 5, 1 ); // Priority 5 to run early
        add_action( 'woocommerce_new_order', array( $this, 'check_order_ratio_on_new_order' ), 5, 1 ); // Priority 5 to run early
        
        // Also hook into order status changes (in case order is created programmatically)
        add_action( 'woocommerce_order_status_pending', array( $this, 'check_order_ratio_on_status_change' ), 5, 1 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'check_order_ratio_on_status_change' ), 5, 1 );
    }

    /**
     * Check if auto-check is enabled.
     *
     * @return bool
     */
    private function is_auto_check_enabled() {
        return (bool) get_option( 'bd_courier_auto_check_order_ratio', false );
    }

    /**
     * Validate order ratio before checkout completes.
     * This runs during checkout validation to prevent order creation if ratio is low.
     */
    public function validate_order_ratio_before_checkout() {
        // Check if auto-check and reject are enabled
        if ( ! $this->is_auto_check_enabled() ) {
            return;
        }

        $reject_low_ratio = get_option( 'bd_courier_reject_low_ratio', false );
        if ( ! $reject_low_ratio ) {
            return;
        }

        // Get billing phone from POST data
        $phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
        
        if ( empty( $phone ) ) {
            return; // No phone number provided
        }

        // Format phone number
        $phone = preg_replace( '/[^0-9]/', '', $phone );
        
        if ( empty( $phone ) ) {
            return;
        }


        // Check if customer has completed orders (if yes, allow order)
        if ( $this->has_completed_orders( $phone, 0 ) ) {
            return; // Customer has completed orders, allow order
        }

        // Fetch courier data
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        $courier_data = CourierAPI::fetch_order_ratio_from_api( $phone );

        if ( $courier_data === false ) {
            // If API fails, allow order (don't block on API errors)
            $error_message = CourierAPI::get_last_error();
            return;
        }

        // Get minimum success ratio
        $min_success_ratio = (float) get_option( 'bd_courier_min_success_ratio', 50 );

        // Calculate success ratio - check multiple possible data structures
        $total_parcel = 0;
        $success_parcel = 0;

        // Try summary structure first
        if ( isset( $courier_data['summary'] ) && is_array( $courier_data['summary'] ) ) {
            $total_parcel = isset( $courier_data['summary']['total_parcel'] ) ? (int) $courier_data['summary']['total_parcel'] : 0;
            $success_parcel = isset( $courier_data['summary']['success_parcel'] ) ? (int) $courier_data['summary']['success_parcel'] : 0;
        } 
        // Try data structure
        elseif ( isset( $courier_data['data'] ) && is_array( $courier_data['data'] ) ) {
            if ( isset( $courier_data['data']['summary'] ) && is_array( $courier_data['data']['summary'] ) ) {
                $total_parcel = isset( $courier_data['data']['summary']['total_parcel'] ) ? (int) $courier_data['data']['summary']['total_parcel'] : 0;
                $success_parcel = isset( $courier_data['data']['summary']['success_parcel'] ) ? (int) $courier_data['data']['summary']['success_parcel'] : 0;
            }
        }
        // Try direct structure
        elseif ( isset( $courier_data['total_parcel'] ) ) {
            $total_parcel = (int) $courier_data['total_parcel'];
            $success_parcel = isset( $courier_data['success_parcel'] ) ? (int) $courier_data['success_parcel'] : 0;
        }


        if ( $total_parcel === 0 ) {
            return; // No data to evaluate
        }

        $success_ratio = ( $success_parcel / $total_parcel ) * 100;

        // If success ratio is below minimum, prevent order creation
        if ( $success_ratio < $min_success_ratio ) {
            
            // Get custom rejection message or use default
            $custom_message = get_option( 'bd_courier_rejection_message', '' );
            
            if ( ! empty( trim( $custom_message ) ) ) {
                // Replace parameters in custom message
                $message = str_replace(
                    array( '{ratio}', '{minimum}', '{phone}' ),
                    array( number_format( $success_ratio, 2 ), number_format( $min_success_ratio, 2 ), $phone ),
                    $custom_message
                );
            } else {
                // Get customizable error message
                $custom_message = get_option( 'bdc_error_low_ratio', 'Order cannot be placed: Courier success ratio (%.2f%%) is below minimum required (%.2f%%). Please contact support.' );
                $message = sprintf( $custom_message, $success_ratio, $min_success_ratio );
            }
            
            // Add checkout error to prevent order creation
            wc_add_notice( $message, 'error' );
        } else {
        }
    }

    /**
     * Check order ratio when a new order is created.
     *
     * @param int $order_id Order ID.
     */
    public function check_order_ratio_on_new_order( $order_id ) {
        // Check if auto-check is enabled
        if ( ! $this->is_auto_check_enabled() ) {
            return;
        }

        // Prevent duplicate checks
        if ( $this->has_been_checked( $order_id ) ) {
            return;
        }

        $this->perform_ratio_check( $order_id );
    }

    /**
     * Check order ratio when order status changes (for programmatically created orders).
     *
     * @param int $order_id Order ID.
     */
    public function check_order_ratio_on_status_change( $order_id ) {
        // Check if auto-check is enabled
        if ( ! $this->is_auto_check_enabled() ) {
            return;
        }

        // Only check if order hasn't been checked yet
        if ( $this->has_been_checked( $order_id ) ) {
            return;
        }

        $this->perform_ratio_check( $order_id );
    }

    /**
     * Check if order has already been checked.
     *
     * @param int $order_id Order ID.
     * @return bool
     */
    private function has_been_checked( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        // Check if courier data already exists
        if ( method_exists( $order, 'get_meta' ) ) {
            $courier_data = $order->get_meta( '_courier_data' );
        } else {
            $courier_data = get_post_meta( $order_id, '_courier_data', true );
        }

        return ! empty( $courier_data );
    }

    /**
     * Perform the ratio check for an order.
     *
     * @param int $order_id Order ID.
     */
    private function perform_ratio_check( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Get billing phone number
        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) {
            return;
        }

        // Format phone number (remove spaces, dashes, etc.)
        $phone = preg_replace( '/[^0-9]/', '', $phone );

        // Skip if phone is still empty after formatting
        if ( empty( $phone ) ) {
            return;
        }


        // Load CourierAPI class
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';

        // Fetch courier data
        $courier_data = CourierAPI::fetch_order_ratio_from_api( $phone );

        if ( $courier_data === false ) {
            $error_message = CourierAPI::get_last_error();
            
            // Store error in order meta for debugging
            if ( method_exists( $order, 'update_meta_data' ) ) {
                $order->update_meta_data( '_courier_data_error', $error_message ? $error_message : 'Failed to fetch courier data.' );
                $order->save();
            } else {
                update_post_meta( $order_id, '_courier_data_error', $error_message ? $error_message : 'Failed to fetch courier data.' );
            }
            return;
        }

        // Store in history table
        require_once dirname( __FILE__ ) . '/class.CourierHistory.php';
        CourierHistory::store_history( $phone, $courier_data, 'wc_order', $order_id );
        
        // Store courier data in order meta
        if ( method_exists( $order, 'update_meta_data' ) ) {
            $order->update_meta_data( '_courier_data', $courier_data );
            $order->update_meta_data( '_courier_data_checked_at', current_time( 'mysql' ) );
            $order->save();
        } else {
            update_post_meta( $order_id, '_courier_data', $courier_data );
            update_post_meta( $order_id, '_courier_data_checked_at', current_time( 'mysql' ) );
        }


        // Handle other settings if enabled (reject low ratio, etc.)
        $this->handle_additional_settings( $order, $courier_data );
    }

    /**
     * Check if phone number has any completed orders.
     *
     * @param string $phone Phone number.
     * @param int    $exclude_order_id Order ID to exclude from check.
     * @return bool True if has completed orders, false otherwise.
     */
    private function has_completed_orders( $phone, $exclude_order_id = 0 ) {
        if ( empty( $phone ) ) {
            return false;
        }

        // Format phone number (remove spaces, dashes, etc.)
        $phone = preg_replace( '/[^0-9]/', '', $phone );
        
        if ( empty( $phone ) ) {
            return false;
        }

        // Query for completed orders with this phone number
        $args = array(
            'limit' => 1,
            'status' => array( 'wc-completed', 'wc-processing' ), // Completed or processing orders
            'billing_phone' => $phone,
            'return' => 'ids',
        );

        // Exclude current order if provided
        if ( $exclude_order_id > 0 ) {
            $args['exclude'] = array( $exclude_order_id );
        }

        $orders = wc_get_orders( $args );

        $has_completed = ! empty( $orders ) && count( $orders ) > 0;

        if ( $has_completed ) {
        } else {
        }

        return $has_completed;
    }

    /**
     * Handle additional settings like rejecting low ratio orders.
     *
     * @param WC_Order $order Order object.
     * @param array    $courier_data Courier data.
     */
    private function handle_additional_settings( $order, $courier_data ) {
        // Check if reject low ratio is enabled (paid feature)
        $reject_low_ratio = get_option( 'bd_courier_reject_low_ratio', false );
        if ( ! $reject_low_ratio ) {
            return;
        }

        // Get minimum success ratio
        $min_success_ratio = (float) get_option( 'bd_courier_min_success_ratio', 50 );

        // Calculate success ratio from courier data
        if ( ! isset( $courier_data['summary'] ) || ! isset( $courier_data['summary']['total_parcel'] ) || ! isset( $courier_data['summary']['success_parcel'] ) ) {
            return;
        }

        $total_parcel = (int) $courier_data['summary']['total_parcel'];
        $success_parcel = (int) $courier_data['summary']['success_parcel'];

        if ( $total_parcel === 0 ) {
            return; // No data to evaluate
        }

        $success_ratio = ( $success_parcel / $total_parcel ) * 100;

        // If success ratio is below minimum, check if customer has completed orders
        if ( $success_ratio < $min_success_ratio ) {
            
            // Get billing phone number
            $phone = $order->get_billing_phone();
            
            // Check if this phone number has any completed orders
            if ( $this->has_completed_orders( $phone, $order->get_id() ) ) {
                
                // Store note that order was not rejected due to completed orders
                if ( method_exists( $order, 'update_meta_data' ) ) {
                    $order->update_meta_data( '_courier_low_ratio_skipped', true );
                    $order->update_meta_data( '_courier_success_ratio', $success_ratio );
                    $order->update_meta_data( '_courier_rejection_skipped_reason', __( 'Order not rejected: Customer has completed orders in the past', 'bd-courier-order-ratio-checker' ) );
                    $order->save();
                } else {
                    update_post_meta( $order->get_id(), '_courier_low_ratio_skipped', true );
                    update_post_meta( $order->get_id(), '_courier_success_ratio', $success_ratio );
                    update_post_meta( $order->get_id(), '_courier_rejection_skipped_reason', __( 'Order not rejected: Customer has completed orders in the past', 'bd-courier-order-ratio-checker' ) );
                }
                
                // Add order note
                $order->add_order_note( sprintf(
                    __( 'Low courier success ratio detected (%.2f%%), but order not rejected because customer has completed orders in the past.', 'bd-courier-order-ratio-checker' ),
                    $success_ratio
                ) );
                
                return; // Don't reject - customer has completed orders
            }
            
            // No completed orders - proceed with rejection
            
            // Load rejected orders class
            require_once dirname( __FILE__ ) . '/class.CourierRejectedOrders.php';
            
            // Get custom rejection message or use default
            $custom_message = get_option( 'bd_courier_rejection_message', '' );
            $phone = $order->get_billing_phone();
            
            if ( ! empty( trim( $custom_message ) ) ) {
                // Replace parameters in custom message for rejection reason
                $rejection_reason = str_replace(
                    array( '{ratio}', '{minimum}', '{phone}' ),
                    array( number_format( $success_ratio, 2 ), number_format( $min_success_ratio, 2 ), $phone ),
                    $custom_message
                );
                $rejection_reason = __( 'Order rejected: ', 'bd-courier-order-ratio-checker' ) . $rejection_reason;
            } else {
                // Default rejection reason
                $rejection_reason = sprintf(
                    __( 'Order rejected: Success ratio (%.2f%%) is below minimum required (%.2f%%) and customer has no completed orders', 'bd-courier-order-ratio-checker' ),
                    $success_ratio,
                    $min_success_ratio
                );
            }
            
            // Store rejected order in database
            $rejected_order_id = CourierRejectedOrders::store_rejected_order(
                $order,
                $courier_data,
                $success_ratio,
                $min_success_ratio,
                $rejection_reason
            );
            
            if ( $rejected_order_id ) {

                // Log the rejection in the rejected order logs table
                require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';

                $log_data = array(
                    'order_id' => $order->get_id(),
                    'customer_email' => $order->get_billing_email(),
                    'customer_phone' => $phone,
                    'customer_ip' => $this->get_client_ip(),
                    'customer_device_fingerprint' => $this->get_device_fingerprint(),
                    'rejection_reason' => 'low-success-rate',
                    'rejection_details' => sprintf(
                        'Success ratio %.2f%% below minimum %.2f%% required. Customer has no completed orders.',
                        $success_ratio,
                        $min_success_ratio
                    ),
                    'success_ratio' => $success_ratio,
                    'min_required_ratio' => $min_success_ratio,
                    'order_total' => $order->get_total(),
                    'order_currency' => $order->get_currency(),
                    'cart_items_count' => $order->get_item_count(),
                    'payment_method' => $order->get_payment_method_title(),
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                    'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                );

                CourierRejectedOrderLogs::log_rejection( $log_data );

                // Store rejected order ID in order meta for reference
                if ( method_exists( $order, 'update_meta_data' ) ) {
                    $order->update_meta_data( '_courier_rejected_order_id', $rejected_order_id );
                    $order->update_meta_data( '_courier_low_ratio_flag', true );
                    $order->update_meta_data( '_courier_success_ratio', $success_ratio );
                    $order->update_meta_data( '_courier_rejection_reason', $rejection_reason );
                } else {
                    update_post_meta( $order->get_id(), '_courier_rejected_order_id', $rejected_order_id );
                    update_post_meta( $order->get_id(), '_courier_low_ratio_flag', true );
                    update_post_meta( $order->get_id(), '_courier_success_ratio', $success_ratio );
                    update_post_meta( $order->get_id(), '_courier_rejection_reason', $rejection_reason );
                }
                
                // Cancel the order with custom message
                $cancel_message = ! empty( trim( $custom_message ) ) 
                    ? str_replace(
                        array( '{ratio}', '{minimum}', '{phone}' ),
                        array( number_format( $success_ratio, 2 ), number_format( $min_success_ratio, 2 ), $phone ),
                        $custom_message
                    )
                    : sprintf(
                        __( 'Order cancelled: Courier success ratio (%.2f%%) is below minimum required (%.2f%%) and customer has no completed orders', 'bd-courier-order-ratio-checker' ),
                        $success_ratio,
                        $min_success_ratio
                    );
                
                $order->update_status( 'cancelled', $cancel_message );
                
                // Add order note
                $order_note = ! empty( trim( $custom_message ) )
                    ? str_replace(
                        array( '{ratio}', '{minimum}', '{phone}' ),
                        array( number_format( $success_ratio, 2 ), number_format( $min_success_ratio, 2 ), $phone ),
                        $custom_message
                    ) . ' Rejected order ID: ' . $rejected_order_id
                    : sprintf(
                        __( 'Order automatically rejected due to low courier success ratio. Success ratio: %.2f%%, Required: %.2f%%. Customer has no completed orders. Rejected order ID: %d', 'bd-courier-order-ratio-checker' ),
                        $success_ratio,
                        $min_success_ratio,
                        $rejected_order_id
                    );
                
                $order->add_order_note( $order_note );
                
            } else {
            }
        }
    }

    /**
     * Get client IP address
     *
     * @return string
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
            'REMOTE_ADDR'
        );

        foreach ( $ip_headers as $header ) {
            if ( isset( $_SERVER[ $header ] ) ) {
                $ip = $_SERVER[ $header ];

                // Handle comma-separated IPs (like X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }

                // Validate IP
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }

    /**
     * Generate device fingerprint based on available data
     *
     * @return string
     */
    private function get_device_fingerprint() {
        $fingerprint_parts = array();

        // User agent
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $fingerprint_parts[] = md5( $_SERVER['HTTP_USER_AGENT'] );
        }

        // Screen resolution (if available via JavaScript)
        if ( isset( $_COOKIE['bd_screen_resolution'] ) ) {
            $fingerprint_parts[] = $_COOKIE['bd_screen_resolution'];
        }

        // Browser plugins/languages (if available)
        if ( isset( $_COOKIE['bd_browser_fingerprint'] ) ) {
            $fingerprint_parts[] = $_COOKIE['bd_browser_fingerprint'];
        }

        // IP address (first 2 octets for location-based fingerprinting)
        $ip = $this->get_client_ip();
        if ( strpos( $ip, '.' ) !== false ) {
            $parts = explode( '.', $ip );
            if ( count( $parts ) >= 3 ) {
                $fingerprint_parts[] = $parts[0] . '.' . $parts[1] . '.xxx.xxx';
            }
        }

        return ! empty( $fingerprint_parts ) ? md5( implode( '|', $fingerprint_parts ) ) : md5( $ip );
    }
}

