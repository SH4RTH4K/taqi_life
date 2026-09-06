<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierAdmin
 * Handles admin display and AJAX endpoints for order details and orders list.
 */
class CourierAdmin {

    private static $meta_box_registered = false;

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_search_script' ] );
        // Add meta box for order details page (works for both old and HPOS)
        // Use multiple hooks to ensure it works
        add_action( 'add_meta_boxes', [ $this, 'add_courier_order_ratio_meta_box' ] );
        add_action( 'add_meta_boxes_shop_order', [ $this, 'add_courier_order_ratio_meta_box' ] );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'display_order_ratio_column_content' ], 10, 2 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_order_ratio_column' ] );
        add_action( 'wp_ajax_refresh_courier_data_edit', [ $this, 'refresh_courier_data_edit' ] );
        add_action( 'wp_ajax_refresh_courier_data_list', [ $this, 'refresh_courier_data_list' ] );
        add_action( 'wp_ajax_fetch_order_ratios', [ $this, 'fetch_order_ratios' ] );
        
        // Ensure postbox is initialized in footer (after all scripts load)
        add_action( 'admin_footer', [ $this, 'init_postbox_footer' ], 999 );
    }

    /**
     * Initialize postbox in footer to ensure it works with React components.
     */
    public function init_postbox_footer() {
        global $pagenow;
        
        // Only on order edit pages
        $is_order_edit = false;
        if ( $pagenow === 'post.php' && isset( $_GET['post'] ) && get_post_type( intval( $_GET['post'] ) ) === 'shop_order' ) {
            $is_order_edit = true;
        }
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' && isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) {
            $is_order_edit = true;
        }
        
        if ( $is_order_edit ) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Simple initialization - no observers to avoid crashes
                function initPostbox() {
                    if (typeof postboxes !== 'undefined' && typeof pagenow !== 'undefined') {
                        try {
                            postboxes.add_postbox_toggles(pagenow);
                        } catch(e) {
                            // Ignore errors
                        }
                    }
                }
                
                // Initialize once
                setTimeout(initPostbox, 500);
                setTimeout(initPostbox, 1500);
            });
            </script>
            <?php
        }
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_search_script( $hook ) {
        // Explicitly exclude settings page
        if ( $hook === 'toplevel_page_bd-courier-settings' || ( isset( $_GET['page'] ) && $_GET['page'] === 'bd-courier-settings' ) ) {
            return;
        }
        
        // Only load on WooCommerce orders pages (edit order or orders list)
        $orders_screens = [ 'post.php', 'post-new.php', 'woocommerce_page_wc-orders', 'woocommerce_page_wc-orders-network' ];
        $is_orders_page = in_array( $hook, $orders_screens, true );
        
        // Also check if we're on an order edit page by checking GET parameter
        if ( ! $is_orders_page && isset( $_GET['post'] ) && get_post_type( intval( $_GET['post'] ) ) === 'shop_order' ) {
            $is_orders_page = true;
        }
        
        // Check if we're on HPOS orders page
        if ( ! $is_orders_page && isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' ) {
            $is_orders_page = true;
        }
        
        if ( ! $is_orders_page ) {
            return;
        }
        
        // Get plugin paths early
        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';
        
        wp_enqueue_script( 'jquery' );
        
        // Use standalone vanilla JS notification system to avoid conflicts
        // This prevents loading settings app assets on order pages
        $notifications_url = plugin_dir_url( __FILE__ ) . '../assets/js/admin-notifications.js';
        $notifications_url = add_query_arg( 'v', BD_COURIER_VERSION, $notifications_url );
        wp_enqueue_script(
            'bdcourier-notifications',
            $notifications_url,
            [],
            BD_COURIER_VERSION,
            true
        );
        
        // Enqueue React order ratio checker component (only on order edit page)
        // Check for old order edit page (post.php)
        $is_order_edit = ( $hook === 'post.php' && isset( $_GET['post'] ) && get_post_type( intval( $_GET['post'] ) ) === 'shop_order' );
        
        // Check for HPOS order edit page (wc-orders with action=edit)
        if ( ! $is_order_edit && $hook === 'woocommerce_page_wc-orders' && isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            $is_order_edit = true;
        }
        
        // Also check by page parameter
        if ( ! $is_order_edit && isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' && isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) {
            $is_order_edit = true;
        }
        
        if ( $is_order_edit ) {
            // Ensure WordPress postbox script is loaded for meta box collapse/expand functionality
            wp_enqueue_script( 'postbox' );
            
            // Load from separate admin-order-ratio-checker app (completely independent)
            $order_ratio_js = $plugin_dir . '/assets/js/order-ratio-checker.js';
            $order_ratio_css = $plugin_dir . '/assets/js/order-ratio-checker.css';
            
            if ( file_exists( $order_ratio_js ) ) {
                $order_ratio_url = plugins_url( 'assets/js/order-ratio-checker.js', $plugin_main_file );
                $order_ratio_url = add_query_arg( 'v', BD_COURIER_VERSION, $order_ratio_url );
                
                // Enqueue CSS if it exists
                if ( file_exists( $order_ratio_css ) ) {
                    $css_url = plugins_url( 'assets/js/order-ratio-checker.css', $plugin_main_file );
                    $css_url = add_query_arg( 'v', BD_COURIER_VERSION, $css_url );
                    
                    wp_enqueue_style(
                        'bdcourier-order-ratio-css',
                        $css_url,
                        [],
                        BD_COURIER_VERSION
                    );
                }
                
                wp_enqueue_script(
                    'bdcourier-order-ratio-checker',
                    $order_ratio_url,
                    [ 'postbox', 'jquery' ], // Depend on postbox and jQuery
                    BD_COURIER_VERSION,
                    true
                );
                
                // Localize script with WordPress REST API data
                wp_localize_script(
                    'bdcourier-order-ratio-checker',
                    'bdcourierSearchAjax',
                    array(
                        'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                        'search_nonce'  => wp_create_nonce( 'search_courier_data_nonce' ),
                        'refresh_nonce' => wp_create_nonce( 'refresh_courier_data_nonce' ),
                    )
                );
            }
        }
        
        $admin_js_url = plugin_dir_url( __FILE__ ) . '../assets/js/admin.js';
        $admin_js_url = add_query_arg( 'v', BD_COURIER_VERSION, $admin_js_url );
        wp_enqueue_script(
            'bdcourier-search-ajax',
            $admin_js_url,
            [ 'jquery', 'bdcourier-notifications' ],
            BD_COURIER_VERSION,
            true
        );
        wp_localize_script(
            'bdcourier-search-ajax',
            'bdcourierSearchAjax',
            [
                'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                'search_nonce'  => wp_create_nonce( 'search_courier_data_nonce' ),
                'refresh_nonce' => wp_create_nonce( 'refresh_courier_data_nonce' ),
            ]
        );
        $admin_css_url = plugins_url( '../assets/css/admin.css', __FILE__ );
        $admin_css_url = add_query_arg( 'v', BD_COURIER_VERSION, $admin_css_url );
        wp_enqueue_style(
            'bdcourier-admin-css',
            $admin_css_url,
            [],
            BD_COURIER_VERSION
        );
        // Enqueue dashicons for refresh button icons.
        wp_enqueue_style( 'dashicons' );
        
        // Enqueue popup script for order list page (always enqueue for orders pages)
        $popup_js = $plugin_dir . '/assets/js/courier-popup.js';
        $popup_css = $plugin_dir . '/assets/js/courier-popup.css';
        
        if ( file_exists( $popup_js ) ) {
            $popup_js_url = plugins_url( 'assets/js/courier-popup.js', $plugin_main_file );
            $popup_js_url = add_query_arg( 'v', BD_COURIER_VERSION, $popup_js_url );
            
            if ( file_exists( $popup_css ) ) {
                $popup_css_url = plugins_url( 'assets/js/courier-popup.css', $plugin_main_file );
                $popup_css_url = add_query_arg( 'v', BD_COURIER_VERSION, $popup_css_url );
                
                wp_enqueue_style(
                    'bdcourier-popup-css',
                    $popup_css_url,
                    [],
                    BD_COURIER_VERSION
                );
            }
            
            wp_enqueue_script(
                'bdcourier-popup',
                $popup_js_url,
                [ 'jquery' ],
                BD_COURIER_VERSION,
                true
            );
            
            wp_localize_script(
                'bdcourier-popup',
                'bdcourierSearchAjax',
                array(
                    'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                    'search_nonce'  => wp_create_nonce( 'search_courier_data_nonce' ),
                    'refresh_nonce' => wp_create_nonce( 'refresh_courier_data_nonce' ),
                )
            );
        }
    }

    /**
     * Add meta box for courier order ratio on order edit pages.
     */
    public function add_courier_order_ratio_meta_box() {
        global $pagenow, $post_type, $current_screen;
        
        // Always register for shop_order post type (works for old system)
        add_meta_box(
            'bd-courier-order-ratio',
            __( 'Courier Order Ratio', 'bd-courier-order-ratio-checker' ),
            [ $this, 'render_courier_order_ratio_meta_box' ],
            'shop_order',
            'normal',
            'high'
        );
        
        // For HPOS - register for HPOS screen
        // Try to get the correct screen ID
        $hpos_screens = [];
        
        if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
            try {
                $controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
                if ( $controller && $controller->custom_orders_table_usage_is_enabled() ) {
                    $screen = wc_get_page_screen_id( 'shop-order' );
                    if ( $screen ) {
                        $hpos_screens[] = $screen;
                    }
                }
            } catch ( Exception $e ) {
                // Continue to fallback
            }
        }
        
        // Add fallback screen names
        $hpos_screens[] = 'woocommerce_page_wc-orders';
        
        // Register for all HPOS screens (WordPress will only show on matching screen)
        foreach ( array_unique( $hpos_screens ) as $screen ) {
            add_meta_box(
                'bd-courier-order-ratio-hpos',
                __( 'Courier Order Ratio', 'bd-courier-order-ratio-checker' ),
                [ $this, 'render_courier_order_ratio_meta_box' ],
                $screen,
                'normal',
                'high'
            );
        }
    }

    /**
     * Render the meta box content.
     */
    public function render_courier_order_ratio_meta_box( $post_or_order_object ) {
        // Handle both old order system (post object) and HPOS (order object)
        if ( is_a( $post_or_order_object, 'WP_Post' ) ) {
            // Old order system
            $order_id = $post_or_order_object->ID;
            $order = wc_get_order( $order_id );
        } else {
            // HPOS - order object passed directly
            $order = $post_or_order_object;
            $order_id = $order->get_id();
        }
        
        if ( ! $order ) {
            echo '<p>' . esc_html__( 'Order not found.', 'bd-courier-order-ratio-checker' ) . '</p>';
            return;
        }
        
        $customer_phone = $order->get_billing_phone();
        
        if ( ! $customer_phone ) {
            echo '<p>' . esc_html__( 'No phone number found for this order.', 'bd-courier-order-ratio-checker' ) . '</p>';
            return;
        }
        
        // Get existing courier data
        $courier_data = null;
        if ( method_exists( $order, 'get_meta' ) ) {
            // HPOS
            $courier_data = $order->get_meta( '_courier_data' );
        } else {
            // Old system
            $courier_data = get_post_meta( $order_id, '_courier_data', true );
        }
        
        // If no courier data in order meta, try to load from history table
        if ( empty( $courier_data ) && ! empty( $customer_phone ) ) {
            if ( ! class_exists( 'CourierHistory' ) ) {
                $history_file = dirname( __FILE__ ) . '/class.CourierHistory.php';
                if ( file_exists( $history_file ) ) {
                    require_once $history_file;
                }
            }
            
            if ( class_exists( 'CourierHistory' ) ) {
                try {
                    // Try to get history by order ID first
                    $history = CourierHistory::get_history_by_rel( 'wc_order', $order_id );
                    if ( ! $history || empty( $history->data ) ) {
                        // Fallback to latest history for this phone
                        $history = CourierHistory::get_latest_history( $customer_phone );
                    }
                    if ( $history && ! empty( $history->data ) ) {
                        $courier_data = $history->data;
                        // Also save to order meta for future use
                        if ( method_exists( $order, 'update_meta_data' ) ) {
                            $order->update_meta_data( '_courier_data', $courier_data );
                            $order->save();
                        } else {
                            update_post_meta( $order_id, '_courier_data', $courier_data );
                        }
                    }
                } catch ( Exception $e ) {
                }
            }
        }
        
        // Get all orders with this phone number
        $related_orders = $this->get_orders_by_phone( $customer_phone, $order_id );
        
        // Render React root container - WordPress will wrap this in proper meta box structure
        // The container should be inside the meta box's inner content area
        // Note: WordPress automatically wraps meta box content in <div class="inside">
        echo '<div id="bdcourier-order-ratio-checker-root" 
                  data-order-id="' . esc_attr( $order_id ) . '" 
                  data-phone="' . esc_attr( $customer_phone ) . '" 
                  data-initial-data="' . esc_attr( wp_json_encode( $courier_data ) ) . '"
                  data-related-orders="' . esc_attr( wp_json_encode( $related_orders ) ) . '"
                  style="width: 100%; position: relative; z-index: 1; min-height: 50px;">
              </div>';
    }

    /**
     * Add a new column for order ratio on Orders list.
     *
     * @param array $columns
     * @return array
     */
    public function add_order_ratio_column( $columns ) {
        $columns['order_ratio'] = __( 'Order Success Ratio', 'bd-courier-order-ratio-checker' );
        return $columns;
    }

    /**
     * Display courier summary in the Orders list column.
     *
     * @param string $column
     * @param int    $post_id
     */
    public function display_order_ratio_column_content( $column, $post_id ) {
        if ( 'order_ratio' === $column ) {
            $order = wc_get_order( $post_id );
            if ( ! $order ) {
                echo '<div class="bdc-order-ratio-empty">' . esc_html__( 'No order found', 'bd-courier-order-ratio-checker' ) . '</div>';
                return;
            }
            $order_id     = $order->get_id();
            $customer_phone = $order->get_billing_phone();
            
            // Try HPOS first, then fallback to post meta
            $courier_data = null;
            if ( method_exists( $order, 'get_meta' ) ) {
                $courier_data = $order->get_meta( '_courier_data' );
            } else {
                $courier_data = get_post_meta( $order_id, '_courier_data', true );
            }
            
            // If no courier data in order meta, try to load from history table
            if ( empty( $courier_data ) && ! empty( $customer_phone ) ) {
                if ( ! class_exists( 'CourierHistory' ) ) {
                    $history_file = dirname( __FILE__ ) . '/class.CourierHistory.php';
                    if ( file_exists( $history_file ) ) {
                        require_once $history_file;
                    }
                }
                
                if ( class_exists( 'CourierHistory' ) ) {
                    try {
                        // Try to get history by order ID first
                        $history = CourierHistory::get_history_by_rel( 'wc_order', $order_id );
                        if ( ! $history || empty( $history->data ) ) {
                            // Fallback to latest history for this phone
                            $history = CourierHistory::get_latest_history( $customer_phone );
                        }
                        if ( $history && ! empty( $history->data ) ) {
                            $courier_data = $history->data;
                            // Also save to order meta for future use
                            if ( method_exists( $order, 'update_meta_data' ) ) {
                                $order->update_meta_data( '_courier_data', $courier_data );
                                $order->save();
                            } else {
                                update_post_meta( $order_id, '_courier_data', $courier_data );
                            }
                        }
                    } catch ( Exception $e ) {
                    }
                }
            }
            
            // Get related orders
            $related_orders = $this->get_orders_by_phone( $customer_phone, $order_id );
            
            echo '<div id="order-ratio-' . esc_attr( $order_id ) . '" class="bdc-order-ratio-column">';
            
            if ( $courier_data && isset( $courier_data['summary'] ) ) {
                $total_parcel   = isset( $courier_data['summary']['total_parcel'] ) ? (int) $courier_data['summary']['total_parcel'] : 0;
                $success_parcel = isset( $courier_data['summary']['success_parcel'] ) ? (int) $courier_data['summary']['success_parcel'] : 0;
                $cancel_parcel  = $total_parcel - $success_parcel;
                $success_ratio  = $total_parcel > 0 ? round( ( $success_parcel / $total_parcel ) * 100, 1 ) : 0;
                $cancel_ratio   = 100 - $success_ratio;

                // Get courier data for display
                $courier_list = [];
                // Handle both data structures: $courier_data['data'] and direct $courier_data
                $couriers_to_process = [];
                if ( isset( $courier_data['data'] ) && is_array( $courier_data['data'] ) ) {
                    $couriers_to_process = $courier_data['data'];
                } elseif ( is_array( $courier_data ) ) {
                    $couriers_to_process = $courier_data;
                }
                
                if ( ! empty( $couriers_to_process ) ) {
                    foreach ( $couriers_to_process as $courier_key => $courier_info ) {
                        if ( $courier_key !== 'summary' && is_array( $courier_info ) && isset( $courier_info['total_parcel'] ) && $courier_info['total_parcel'] > 0 ) {
                            $courier_list[] = [
                                'name' => isset( $courier_info['name'] ) ? $courier_info['name'] : ucfirst( $courier_key ),
                                'logo' => isset( $courier_info['logo'] ) ? $courier_info['logo'] : '',
                                'total' => isset( $courier_info['total_parcel'] ) ? (int) $courier_info['total_parcel'] : 0,
                                'success' => isset( $courier_info['success_parcel'] ) ? (int) $courier_info['success_parcel'] : 0,
                                'ratio' => isset( $courier_info['success_ratio'] ) ? (float) $courier_info['success_ratio'] : 0,
                            ];
                        }
                    }
                }
                
                // Determine status badge
                $status_class = 'bdc-status-excellent';
                if ( $success_ratio < 50 ) {
                    $status_class = 'bdc-status-poor';
                } elseif ( $success_ratio < 70 ) {
                    $status_class = 'bdc-status-warning';
                } elseif ( $success_ratio < 85 ) {
                    $status_class = 'bdc-status-good';
                }

                // Two-line minimal display - counts and icon on first line, progress bar on second line
                echo '<div class="bdc-ratio-minimal" data-order-id="' . esc_attr( $order_id ) . '" data-phone="' . esc_attr( $customer_phone ) . '" data-initial-data="' . esc_attr( wp_json_encode( $courier_data ) ) . '" data-related-orders="' . esc_attr( wp_json_encode( $related_orders ) ) . '" title="' . esc_attr__( 'Click to view details', 'bd-courier-order-ratio-checker' ) . '">';
                
                // First line: Counts with colored separators and eye icon
                echo '<div class="bdc-minimal-counts">';
                echo '<span class="bdc-count-item bdc-count-total">' . esc_html( $total_parcel ) . '</span>';
                echo '<span class="bdc-separator bdc-separator-total">|</span>';
                echo '<span class="bdc-count-item bdc-count-success">' . esc_html( $success_parcel ) . '</span>';
                echo '<span class="bdc-separator bdc-separator-success">|</span>';
                echo '<span class="bdc-count-item bdc-count-cancel">' . esc_html( $cancel_parcel ) . '</span>';
                // Show related orders count if available
                if ( ! empty( $related_orders ) && count( $related_orders ) > 0 ) {
                    echo '<span class="bdc-separator bdc-separator-total">|</span>';
                    echo '<span class="bdc-count-item bdc-count-orders" title="' . esc_attr( count( $related_orders ) . ' previous orders' ) . '">' . esc_html( count( $related_orders ) ) . ' orders</span>';
                }
                echo '<span class="bdc-view-eye-icon dashicons dashicons-visibility"></span>';
                echo '</div>';

                // Second line: Progress bar and courier logos
                echo '<div class="bdc-minimal-second-line">';
                echo '<div class="bdc-minimal-progress-wrapper">';
                echo '<div class="bdc-minimal-progress-bar">';
                if ( $success_ratio > 0 ) {
                    echo '<div class="bdc-progress-segment bdc-progress-success" style="width:' . esc_attr( $success_ratio ) . '%;"></div>';
                }
                        if ( $cancel_ratio > 0 ) {
                    echo '<div class="bdc-progress-segment bdc-progress-cancel" style="width:' . esc_attr( $cancel_ratio ) . '%;"></div>';
                }
                echo '<span class="bdc-progress-percentage">' . esc_html( number_format( $success_ratio, 1 ) ) . '%</span>';
                echo '</div>';
                echo '</div>';
                
                echo '</div>'; // .bdc-minimal-second-line
                
                echo '</div>'; // .bdc-ratio-minimal
            } else {
                // No data state
                echo '<div class="bdc-ratio-empty">';
                echo '<div class="bdc-empty-text">' . esc_html__( 'No data available', 'bd-courier-order-ratio-checker' ) . '</div>';
                echo '<button type="button" class="bdc-check-btn" data-order-id="' . esc_attr( $order_id ) . '" data-phone="' . esc_attr( $customer_phone ) . '" data-context="list">';
                echo '<span class="dashicons dashicons-search"></span>';
                echo '<span>' . esc_html__( 'Check Ratio', 'bd-courier-order-ratio-checker' ) . '</span>';
                echo '</button>';
                echo '</div>';
            }
            echo '</div>'; // #order-ratio-{id}
        }
    }

    /**
     * Placeholder for additional ratio fetching logic.
     */
    public function fetch_order_ratios() {
        // Additional logic can be added here.
    }

    /**
     * AJAX endpoint: refresh courier data on Order Edit page.
     */
    public function refresh_courier_data_edit() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'refresh_courier_data_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Invalid nonce.', 'bd-courier-order-ratio-checker' ) );
            return;
        }
        if ( isset( $_POST['order_id'] ) && ! empty( $_POST['order_id'] ) ) {
            $order_id = intval( wp_unslash( $_POST['order_id'] ) );
        } else {
            wp_send_json_error( esc_html__( 'Invalid order ID.', 'bd-courier-order-ratio-checker' ) );
            return;
        }
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( esc_html__( 'Order not found.', 'bd-courier-order-ratio-checker' ) );
            return;
        }
        $phone = $order->get_billing_phone();
        if ( ! $phone ) {
            wp_send_json_error( esc_html__( 'No phone number found.', 'bd-courier-order-ratio-checker' ) );
            return;
        }
        $courier_data = CourierAPI::fetch_order_ratio_from_api( $phone );
        if ( $courier_data !== false && is_array( $courier_data ) ) {
            // Store in history table
            require_once dirname( __FILE__ ) . '/class.CourierHistory.php';
            CourierHistory::store_history( $phone, $courier_data, 'wc_order', $order_id );
            
            // For HPOS, use order meta instead of post meta
            if ( function_exists( 'wc_get_order' ) && method_exists( $order, 'update_meta_data' ) ) {
                $order->update_meta_data( '_courier_data', $courier_data );
                $order->save();
            } else {
            update_post_meta( $order_id, '_courier_data', $courier_data );
            }
            
            // Get related orders for popup
            $related_orders = $this->get_orders_by_phone( $phone, $order_id );
            
            // Return JSON data for React component
            wp_send_json_success( [ 
                'data' => $courier_data,
                'related_orders' => $related_orders,
                'table' => '' // Keep for backward compatibility but React will use 'data'
            ] );
        } else {
            // API call failed, try to load from history table
            if ( ! class_exists( 'CourierHistory' ) ) {
                $history_file = dirname( __FILE__ ) . '/class.CourierHistory.php';
                if ( file_exists( $history_file ) ) {
                    require_once $history_file;
                }
            }
            
            if ( class_exists( 'CourierHistory' ) ) {
                try {
                    // Try to get history by order ID first
                    $history = CourierHistory::get_history_by_rel( 'wc_order', $order_id );
                    if ( ! $history || empty( $history->data ) ) {
                        // Fallback to latest history for this phone
                        $history = CourierHistory::get_latest_history( $phone );
                    }
                    if ( $history && ! empty( $history->data ) ) {
                        $courier_data = $history->data;
                        // Save to order meta
                        if ( method_exists( $order, 'update_meta_data' ) ) {
                            $order->update_meta_data( '_courier_data', $courier_data );
                            $order->save();
                        } else {
                            update_post_meta( $order_id, '_courier_data', $courier_data );
                        }
                        
                        // Get related orders
                        $related_orders = $this->get_orders_by_phone( $phone, $order_id );
                        
                        wp_send_json_success( [ 
                            'data' => $courier_data,
                            'related_orders' => $related_orders,
                            'table' => '',
                            'from_cache' => true, // Indicate this is from history cache
                        ] );
                        return;
                    }
                } catch ( Exception $e ) {
                }
            }
            
            $error_message = CourierAPI::get_last_error();
            
            // Always send a meaningful error message
            if ( $error_message && ! empty( trim( $error_message ) ) ) {
                wp_send_json_error( esc_html( $error_message ) );
            } else {
                // Provide a more helpful default error
                $default_error = esc_html__( 'Failed to fetch courier data. Please check your API connection and try again.', 'bd-courier-order-ratio-checker' );
                wp_send_json_error( $default_error );
            }
        }
    }

    /**
     * AJAX endpoint: refresh summary on Orders List page.
     */
    public function refresh_courier_data_list() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'refresh_courier_data_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Invalid nonce.', 'bd-courier-order-ratio-checker' ) );
            return;
        }
        if ( isset( $_POST['order_id'] ) && ! empty( $_POST['order_id'] ) ) {
            $order_id = intval( wp_unslash( $_POST['order_id'] ) );
        } else {
            wp_send_json_error( esc_html__( 'Invalid order ID.', 'bd-courier-order-ratio-checker' ) );
            return;
        }
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( esc_html__( 'Order not found.', 'bd-courier-order-ratio-checker' ) );
            return;
        }
        $phone = $order->get_billing_phone();
        if ( ! $phone ) {
            wp_send_json_error( esc_html__( 'No phone number found.', 'bd-courier-order-ratio-checker' ) );
            return;
        }
        $courier_data = CourierAPI::fetch_order_ratio_from_api( $phone );
        if ( $courier_data !== false && is_array( $courier_data ) && isset( $courier_data['summary'] ) ) {
            // Store in history table
            require_once dirname( __FILE__ ) . '/class.CourierHistory.php';
            CourierHistory::store_history( $phone, $courier_data, 'wc_order', $order_id );
            
            // For HPOS, use order meta instead of post meta
            $order = wc_get_order( $order_id );
            if ( $order && method_exists( $order, 'update_meta_data' ) ) {
                $order->update_meta_data( '_courier_data', $courier_data );
                $order->save();
            } else {
            update_post_meta( $order_id, '_courier_data', $courier_data );
            }
            
            $total_parcel   = isset( $courier_data['summary']['total_parcel'] ) ? (int) $courier_data['summary']['total_parcel'] : 0;
            $success_parcel = isset( $courier_data['summary']['success_parcel'] ) ? (int) $courier_data['summary']['success_parcel'] : 0;
            $cancel_parcel  = $total_parcel - $success_parcel;
            $success_ratio  = $total_parcel > 0 ? round( ( $success_parcel / $total_parcel ) * 100, 1 ) : 0;
            $cancel_ratio   = 100 - $success_ratio;
            
            // Get related orders for popup
            $related_orders = $this->get_orders_by_phone( $phone, $order_id );
            
            // Two-line minimal HTML response - counts and icon on first line, progress bar on second line
            $response_html = '<div class="bdc-ratio-minimal" data-order-id="' . esc_attr( $order_id ) . '" data-phone="' . esc_attr( $phone ) . '" data-initial-data="' . esc_attr( wp_json_encode( $courier_data ) ) . '" data-related-orders="' . esc_attr( wp_json_encode( $related_orders ) ) . '" title="' . esc_attr__( 'Click to view details', 'bd-courier-order-ratio-checker' ) . '">';
            
            // First line: Counts with colored separators and eye icon
            $response_html .= '<div class="bdc-minimal-counts">';
            $response_html .= '<span class="bdc-count-item bdc-count-total">' . esc_html( $total_parcel ) . '</span>';
            $response_html .= '<span class="bdc-separator bdc-separator-total">|</span>';
            $response_html .= '<span class="bdc-count-item bdc-count-success">' . esc_html( $success_parcel ) . '</span>';
            $response_html .= '<span class="bdc-separator bdc-separator-success">|</span>';
            $response_html .= '<span class="bdc-count-item bdc-count-cancel">' . esc_html( $cancel_parcel ) . '</span>';
            // Show related orders count if available
            if ( ! empty( $related_orders ) && count( $related_orders ) > 0 ) {
                $response_html .= '<span class="bdc-separator bdc-separator-total">|</span>';
                $response_html .= '<span class="bdc-count-item bdc-count-orders" title="' . esc_attr( count( $related_orders ) . ' previous orders' ) . '">' . esc_html( count( $related_orders ) ) . ' orders</span>';
            }
            $response_html .= '<span class="bdc-view-eye-icon dashicons dashicons-visibility"></span>';
            $response_html .= '</div>';

            // Get courier data for display
            $courier_list = [];
            // Handle both data structures: $courier_data['data'] and direct $courier_data
            $couriers_to_process = [];
            if ( isset( $courier_data['data'] ) && is_array( $courier_data['data'] ) ) {
                $couriers_to_process = $courier_data['data'];
            } elseif ( is_array( $courier_data ) ) {
                $couriers_to_process = $courier_data;
            }
            
            if ( ! empty( $couriers_to_process ) ) {
                foreach ( $couriers_to_process as $courier_key => $courier_info ) {
                    if ( $courier_key !== 'summary' && is_array( $courier_info ) && isset( $courier_info['total_parcel'] ) && $courier_info['total_parcel'] > 0 ) {
                        $courier_list[] = [
                            'name' => isset( $courier_info['name'] ) ? $courier_info['name'] : ucfirst( $courier_key ),
                            'logo' => isset( $courier_info['logo'] ) ? $courier_info['logo'] : '',
                            'total' => isset( $courier_info['total_parcel'] ) ? (int) $courier_info['total_parcel'] : 0,
                            'success' => isset( $courier_info['success_parcel'] ) ? (int) $courier_info['success_parcel'] : 0,
                            'ratio' => isset( $courier_info['success_ratio'] ) ? (float) $courier_info['success_ratio'] : 0,
                        ];
                    }
                }
            }
            
            // Second line: Progress bar and courier logos
            $response_html .= '<div class="bdc-minimal-second-line">';
            $response_html .= '<div class="bdc-minimal-progress-wrapper">';
            $response_html .= '<div class="bdc-minimal-progress-bar">';
            if ( $success_ratio > 0 ) {
                $response_html .= '<div class="bdc-progress-segment bdc-progress-success" style="width:' . esc_attr( $success_ratio ) . '%;"></div>';
            }
                        if ( $cancel_ratio > 0 ) {
                $response_html .= '<div class="bdc-progress-segment bdc-progress-cancel" style="width:' . esc_attr( $cancel_ratio ) . '%;"></div>';
            }
            $response_html .= '<span class="bdc-progress-percentage">' . esc_html( number_format( $success_ratio, 1 ) ) . '%</span>';
            $response_html .= '</div>';
            $response_html .= '</div>';
            
            $response_html .= '</div>'; // .bdc-minimal-second-line
            
            $response_html .= '</div>'; // .bdc-ratio-minimal

            wp_send_json_success( [ 'html' => $response_html ] );
        } else {
            // API call failed, try to load from history table
            if ( ! class_exists( 'CourierHistory' ) ) {
                $history_file = dirname( __FILE__ ) . '/class.CourierHistory.php';
                if ( file_exists( $history_file ) ) {
                    require_once $history_file;
                }
            }
            
            if ( class_exists( 'CourierHistory' ) ) {
                try {
                    // Try to get history by order ID first
                    $history = CourierHistory::get_history_by_rel( 'wc_order', $order_id );
                    if ( ! $history || empty( $history->data ) ) {
                        // Fallback to latest history for this phone
                        $history = CourierHistory::get_latest_history( $phone );
                    }
                    if ( $history && ! empty( $history->data ) ) {
                        $courier_data = $history->data;
                        // Save to order meta
                        if ( method_exists( $order, 'update_meta_data' ) ) {
                            $order->update_meta_data( '_courier_data', $courier_data );
                            $order->save();
                        } else {
                            update_post_meta( $order_id, '_courier_data', $courier_data );
                        }
                        
                        // Regenerate HTML with cached data
                        $total_parcel   = isset( $courier_data['summary']['total_parcel'] ) ? (int) $courier_data['summary']['total_parcel'] : 0;
                        $success_parcel = isset( $courier_data['summary']['success_parcel'] ) ? (int) $courier_data['summary']['success_parcel'] : 0;
                        $cancel_parcel  = $total_parcel - $success_parcel;
                        $success_ratio  = $total_parcel > 0 ? round( ( $success_parcel / $total_parcel ) * 100, 1 ) : 0;
                        $cancel_ratio   = 100 - $success_ratio;
                        
                        // Get related orders for popup
                        $related_orders = $this->get_orders_by_phone( $phone, $order_id );
                        
                        // Generate HTML response (same as success case above)
                        $response_html = '<div class="bdc-ratio-minimal" data-order-id="' . esc_attr( $order_id ) . '" data-phone="' . esc_attr( $phone ) . '" data-initial-data="' . esc_attr( wp_json_encode( $courier_data ) ) . '" data-related-orders="' . esc_attr( wp_json_encode( $related_orders ) ) . '" title="' . esc_attr__( 'Click to view details', 'bd-courier-order-ratio-checker' ) . '">';
                        $response_html .= '<div class="bdc-minimal-counts">';
                        $response_html .= '<span class="bdc-count-item bdc-count-total">' . esc_html( $total_parcel ) . '</span>';
                        $response_html .= '<span class="bdc-separator bdc-separator-total">|</span>';
                        $response_html .= '<span class="bdc-count-item bdc-count-success">' . esc_html( $success_parcel ) . '</span>';
                        $response_html .= '<span class="bdc-separator bdc-separator-success">|</span>';
                        $response_html .= '<span class="bdc-count-item bdc-count-cancel">' . esc_html( $cancel_parcel ) . '</span>';
                        if ( ! empty( $related_orders ) && count( $related_orders ) > 0 ) {
                            $response_html .= '<span class="bdc-separator bdc-separator-total">|</span>';
                            $response_html .= '<span class="bdc-count-item bdc-count-orders" title="' . esc_attr( count( $related_orders ) . ' previous orders' ) . '">' . esc_html( count( $related_orders ) ) . ' orders</span>';
                        }
                        $response_html .= '<span class="bdc-view-eye-icon dashicons dashicons-visibility"></span>';
                        $response_html .= '</div>';
                        $response_html .= '<div class="bdc-minimal-second-line">';
                        $response_html .= '<div class="bdc-minimal-progress-wrapper">';
                        $response_html .= '<div class="bdc-minimal-progress-bar">';
                        if ( $success_ratio > 0 ) {
                            $response_html .= '<div class="bdc-progress-segment bdc-progress-success" style="width:' . esc_attr( $success_ratio ) . '%;"></div>';
                        }
                        if ( $cancel_ratio > 0 ) {
                            $response_html .= '<div class="bdc-progress-segment bdc-progress-cancel" style="width:' . esc_attr( $cancel_ratio ) . '%;"></div>';
                        }
                        $response_html .= '<span class="bdc-progress-percentage">' . esc_html( number_format( $success_ratio, 1 ) ) . '%</span>';
                        $response_html .= '</div>';
                        $response_html .= '</div>';
                        
                        $response_html .= '</div>';
                        $response_html .= '</div>';
                        
                        wp_send_json_success( [ 'html' => $response_html, 'from_cache' => true ] );
                        return;
                    }
                } catch ( Exception $e ) {
                }
            }
            
            $error_message = CourierAPI::get_last_error();
            
            // Always send a meaningful error message
            if ( $error_message && ! empty( trim( $error_message ) ) ) {
                wp_send_json_error( esc_html( $error_message ) );
            } else {
                // Provide a more helpful default error
                $default_error = esc_html__( 'Failed to fetch courier data. Please check your API connection and try again.', 'bd-courier-order-ratio-checker' );
                wp_send_json_error( $default_error );
            }
        }
    }

    /**
     * Get all WooCommerce orders by phone number (excluding current order).
     *
     * @param string $phone Phone number to search for
     * @param int    $exclude_order_id Order ID to exclude from results
     * @return array Array of order data
     */
    public function get_orders_by_phone( $phone, $exclude_order_id = 0 ) {
        if ( empty( $phone ) ) {
            return [];
        }

        $orders = [];
        
        // Query orders by billing phone - works for both old and HPOS
        $args = [
            'limit'        => 50, // Limit to 50 most recent orders
            'orderby'      => 'date',
            'order'        => 'DESC',
            'billing_phone' => $phone, // WooCommerce handles this for both systems
            'return'       => 'ids',
        ];

        // Exclude current order if provided
        if ( $exclude_order_id > 0 ) {
            $args['exclude'] = [ $exclude_order_id ];
        }

        $order_ids = wc_get_orders( $args );

        if ( empty( $order_ids ) ) {
            return [];
        }

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }

            // Get order date
            $date_created = $order->get_date_created();
            $formatted_date = $date_created ? $date_created->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';

            // Get edit URL - handle both old and HPOS
            $edit_url = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
            if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
                try {
                    $controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
                    if ( $controller && $controller->custom_orders_table_usage_is_enabled() ) {
                        $edit_url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
                    }
                } catch ( Exception $e ) {
                    // Fallback to old URL
                }
            }

            $orders[] = [
                'id'            => $order->get_id(),
                'order_number'  => $order->get_order_number(),
                'date'          => $formatted_date,
                'status'        => $order->get_status(),
                'total'         => (float) $order->get_total(),
                'currency'      => $order->get_currency(),
                'edit_url'      => $edit_url,
                'view_url'      => $order->get_view_order_url(),
                'item_count'    => $order->get_item_count(),
            ];
        }

        return $orders;
    }
}
