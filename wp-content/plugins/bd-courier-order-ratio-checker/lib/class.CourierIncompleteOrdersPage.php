<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierIncompleteOrdersPage
 * Manages the Incomplete Orders admin page using React.
 */
class CourierIncompleteOrdersPage {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_incomplete_orders_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_head', array( $this, 'hide_admin_notices' ) );
        
        // REST API endpoints for React frontend
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        
        // Add type="module" to incomplete orders app scripts
        add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 2 );
    }
    
    /**
     * Add type="module" to incomplete orders app scripts.
     */
    public function add_module_type_to_scripts( $tag, $handle ) {
        // Check if handle starts with bd-courier-incomplete-orders-js
        if ( strpos( $handle, 'bd-courier-incomplete-orders-js' ) === 0 ) {
            // Remove existing type attribute if present
            $tag = preg_replace( '/\s*type=["\']text\/javascript["\']/', '', $tag );
            // Add type="module" before the closing >
            if ( strpos( $tag, ' type="module"' ) === false ) {
                $tag = str_replace( ' src=', ' type="module" src=', $tag );
            }
        }
        return $tag;
    }
    
    /**
     * Hide admin notices on incomplete orders page.
     */
    public function hide_admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        
        if ( $screen->id === 'woocommerce_page_bd-courier-incomplete-orders' || ( isset( $_GET['page'] ) && $_GET['page'] === 'bd-courier-incomplete-orders' ) ) {
            ?>
            <style type="text/css">
                .notice,
                .notice-error,
                .notice-success,
                .notice-warning,
                .notice-info,
                .update-nag,
                .error,
                .updated {
                    display: none !important;
                }
            </style>
            <?php
        }
    }

    /**
     * Add Incomplete Orders page under WooCommerce menu.
     */
    public function add_incomplete_orders_page() {
        add_submenu_page(
            'woocommerce',
            __( 'Incomplete Orders', 'bd-courier-order-ratio-checker' ),
            __( 'Incomplete Orders', 'bd-courier-order-ratio-checker' ),
            'manage_woocommerce',
            'bd-courier-incomplete-orders',
            array( $this, 'render_incomplete_orders_page' )
        );
    }

    /**
     * Render the incomplete orders page.
     */
    public function render_incomplete_orders_page() {
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        $auth_status = CourierAPI::get_authorization_status();
        
        if ( ! $auth_status['authorized'] ) {
            $is_free = $auth_status['is_free'];
            $plan_name = ! empty( $auth_status['plan_name'] ) ? $auth_status['plan_name'] : '';
            ?>
            <style>
                .bdc-access-denied-container {
                    max-width: 600px;
                    margin: 40px auto;
                    padding: 40px;
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                    text-align: center;
                }
                .bdc-access-denied-icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 24px;
                    background: <?php echo $is_free ? '#f0f6fc' : '#f0f0f1'; ?>;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 40px;
                    color: <?php echo $is_free ? '#2271b1' : '#d63638'; ?>;
                }
                .bdc-access-denied-icon svg {
                    width: 48px;
                    height: 48px;
                    fill: currentColor;
                }
                .bdc-access-denied-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: #1d2327;
                    margin: 0 0 12px;
                    line-height: 1.4;
                }
                .bdc-access-denied-message {
                    font-size: 15px;
                    color: #646970;
                    margin: 0 0 24px;
                    line-height: 1.6;
                }
                .bdc-access-denied-actions {
                    margin-top: 32px;
                    padding-top: 24px;
                    border-top: 1px solid #dcdcde;
                }
                .bdc-access-denied-actions p {
                    margin: 0 0 12px;
                    font-size: 14px;
                    color: #646970;
                }
                .bdc-access-denied-actions a {
                    display: inline-block;
                    padding: 10px 20px;
                    background: <?php echo $is_free ? '#2271b1' : '#2271b1'; ?>;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 4px;
                    font-weight: 500;
                    transition: background 0.2s;
                    margin: 4px;
                }
                .bdc-access-denied-actions a:hover {
                    background: #135e96;
                    color: #fff;
                }
                .bdc-access-denied-actions a.secondary {
                    background: #f6f7f7;
                    color: #1d2327;
                    border: 1px solid #dcdcde;
                }
                .bdc-access-denied-actions a.secondary:hover {
                    background: #f0f0f1;
                    color: #1d2327;
                }
                .bdc-access-denied-site-info {
                    margin-top: 24px;
                    padding: 16px;
                    background: #f6f7f7;
                    border-radius: 4px;
                    font-size: 13px;
                    color: #646970;
                }
                .bdc-access-denied-site-info strong {
                    color: #1d2327;
                    display: block;
                    margin-bottom: 4px;
                }
                .bdc-plan-badge {
                    display: inline-block;
                    padding: 4px 12px;
                    background: <?php echo $is_free ? '#f0f6fc' : '#f0f0f1'; ?>;
                    color: <?php echo $is_free ? '#2271b1' : '#646970'; ?>;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 500;
                    margin-bottom: 16px;
                }
            </style>
            <div class="wrap">
                <div class="bdc-access-denied-container">
                    <div class="bdc-access-denied-icon">
                        <?php if ( $is_free ) : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        <?php else : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <?php if ( $is_free && ! empty( $plan_name ) ) : ?>
                        <div class="bdc-plan-badge"><?php echo esc_html( $plan_name ); ?> <?php esc_html_e( 'Plan', 'bd-courier-order-ratio-checker' ); ?></div>
                    <?php endif; ?>
                    <h2 class="bdc-access-denied-title">
                        <?php echo $is_free ? esc_html__( 'Upgrade Required', 'bd-courier-order-ratio-checker' ) : esc_html__( 'Access Denied', 'bd-courier-order-ratio-checker' ); ?>
                    </h2>
                    <p class="bdc-access-denied-message">
                        <?php if ( $is_free ) : ?>
                            <?php esc_html_e( 'This feature is available in paid plans only. Upgrade to a paid plan to access Incomplete Orders tracking and other extended features.', 'bd-courier-order-ratio-checker' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'This feature is only available for authorized WordPress sites. Login to your bdcourier account and authorize your website from the incomplete order section.', 'bd-courier-order-ratio-checker' ); ?>
                        <?php endif; ?>
                    </p>
                    <div class="bdc-access-denied-site-info">
                        <strong><?php esc_html_e( 'Current Site URL:', 'bd-courier-order-ratio-checker' ); ?></strong>
                        <span><?php echo esc_html( home_url( '/' ) ); ?></span>
                    </div>
                    <div class="bdc-access-denied-actions">
                        <?php if ( $is_free ) : ?>
                            <p><?php esc_html_e( 'Upgrade your plan to unlock this feature.', 'bd-courier-order-ratio-checker' ); ?></p>
                            <a href="https://bdcourier.com/pricing" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e( 'View Pricing Plans', 'bd-courier-order-ratio-checker' ); ?>
                            </a>
                            <a href="https://bdcourier.com/support" target="_blank" rel="noopener noreferrer" class="secondary">
                                <?php esc_html_e( 'Contact Support', 'bd-courier-order-ratio-checker' ); ?>
                            </a>
                        <?php else : ?>
                            <p><?php esc_html_e( 'Login to your bdcourier account and authorize your website from the incomplete order section.', 'bd-courier-order-ratio-checker' ); ?></p>
                            <a href="https://bdcourier.com/login" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e( 'Login to bdcourier', 'bd-courier-order-ratio-checker' ); ?>
                            </a>
                            <a href="https://bdcourier.com/support" target="_blank" rel="noopener noreferrer" class="secondary">
                                <?php esc_html_e( 'Contact Support', 'bd-courier-order-ratio-checker' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div id="bd-courier-incomplete-orders-root"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets for incomplete orders page.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our specific page
        // WooCommerce submenu pages use this format
        $is_incomplete_orders_page = ( $hook === 'woocommerce_page_bd-courier-incomplete-orders' ) || 
                                     ( isset( $_GET['page'] ) && $_GET['page'] === 'bd-courier-incomplete-orders' );
        
        if ( ! $is_incomplete_orders_page ) {
            return;
        }

        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return; // Don't load scripts if not authorized
        }

        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';
        
        // Find CSS file - get the most recent one
        $css_dir = $plugin_dir . '/assets/react-apps/incomplete-orders-app';
        // Fallback to old location if new location doesn't exist
        if ( ! is_dir( $css_dir ) ) {
            $css_dir = $plugin_dir . '/incomplete-orders-app/dist';
        }
        
        $css_files = glob( $css_dir . '/*.css' );
        if ( ! empty( $css_files ) ) {
            // Sort by modification time, most recent first
            usort( $css_files, function( $a, $b ) {
                return filemtime( $b ) - filemtime( $a );
            } );
            $css_file = $css_files[0];
            $css_filename = basename( $css_file );
            // Determine the correct path based on which directory we're using
            $css_path = strpos( $css_file, 'assets/react-apps' ) !== false 
                ? 'assets/react-apps/incomplete-orders-app/' . $css_filename
                : 'incomplete-orders-app/dist/' . $css_filename;
            $css_url = plugins_url( $css_path, $plugin_main_file );
            // Add query parameter with plugin version for cache busting
            $css_url = add_query_arg( 'v', BD_COURIER_VERSION, $css_url );
            
            wp_enqueue_style(
                'bd-courier-incomplete-orders-css',
                $css_url,
                [],
                BD_COURIER_VERSION
            );
        }
        
        // Find JS file - get the most recent one
        $js_dir = $plugin_dir . '/assets/react-apps/incomplete-orders-app';
        // Fallback to old location if new location doesn't exist
        if ( ! is_dir( $js_dir ) ) {
            $js_dir = $plugin_dir . '/incomplete-orders-app/dist';
        }
        
        $js_files = glob( $js_dir . '/*.js' );
        if ( ! empty( $js_files ) ) {
            // Sort by modification time, most recent first
            usort( $js_files, function( $a, $b ) {
                return filemtime( $b ) - filemtime( $a );
            } );
            $js_file = $js_files[0];
            $js_filename = basename( $js_file );
            // Determine the correct path based on which directory we're using
            $js_path = strpos( $js_file, 'assets/react-apps' ) !== false 
                ? 'assets/react-apps/incomplete-orders-app/' . $js_filename
                : 'incomplete-orders-app/dist/' . $js_filename;
            $js_url = plugins_url( $js_path, $plugin_main_file );
            // Add query parameter with plugin version for cache busting
            $js_url = add_query_arg( 'v', BD_COURIER_VERSION, $js_url );
            
            wp_enqueue_script(
                'bd-courier-incomplete-orders-js',
                $js_url,
                [],
                BD_COURIER_VERSION,
                true
            );
            
            // Localize script with WordPress REST API data
            wp_localize_script( 'bd-courier-incomplete-orders-js', 'wpApiSettings', array(
                'root'  => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ) );
        }
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route( 'bd-courier/v1', '/incomplete-orders', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_incomplete_orders' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/incomplete-orders/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_incomplete_order' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/incomplete-orders/(?P<id>\d+)/delete', array(
            'methods' => 'DELETE',
            'callback' => array( $this, 'delete_incomplete_order' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/incomplete-orders/(?P<id>\d+)/convert', array(
            'methods' => 'POST',
            'callback' => array( $this, 'convert_to_order' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/products', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_products' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/incomplete-orders/(?P<id>\d+)/check-ratio', array(
            'methods' => 'POST',
            'callback' => array( $this, 'check_courier_ratio' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/incomplete-orders/(?P<id>\d+)/update-order', array(
            'methods' => 'POST',
            'callback' => array( $this, 'update_order_from_incomplete' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/incomplete-orders/(?P<id>\d+)/update', array(
            'methods' => 'POST',
            'callback' => array( $this, 'update_incomplete_order' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );
    }

    /**
     * Check if user has permission to access the API.
     */
    public function check_permissions() {
        // Check if user can manage WooCommerce
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error( 'rest_forbidden', 'You do not have permission to access this resource.', array( 'status' => 403 ) );
        }
        
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return new WP_Error( 'site_not_authorized', 'This feature is only available for authorized WordPress sites. Please contact support to add your site to the authorized list.', array( 'status' => 403 ) );
        }
        
        return true;
    }

    /**
     * Get incomplete orders via REST API.
     */
    public function get_incomplete_orders( $request ) {
        // Prevent any output before JSON response
        if ( ob_get_level() ) {
            ob_clean();
        }
        
        try {
            require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
            
            $params = $request->get_query_params();
            
            $args = array(
                'status' => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '',
                'limit' => isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 50,
                'offset' => isset( $params['page'] ) ? ( absint( $params['page'] ) - 1 ) * absint( $params['per_page'] ) : 0,
                'orderby' => isset( $params['orderby'] ) ? sanitize_text_field( $params['orderby'] ) : 'created_at',
                'order' => isset( $params['order'] ) ? sanitize_text_field( $params['order'] ) : 'DESC',
            );

        // Add search by phone, email, or reference number if provided
        if ( ! empty( $params['search'] ) ) {
            $search = sanitize_text_field( $params['search'] );
            // Check if it looks like a phone number (numeric), email, or reference number
            if ( is_numeric( $search ) && strlen( $search ) >= 7 ) {
                $args['phone'] = $search;
            } elseif ( strpos( $search, '@' ) !== false ) {
                $args['email'] = $search;
            } elseif ( strpos( $search, 'INC-' ) === 0 ) {
                // Reference number search
                $args['reference_number'] = $search;
            }
        }

        // Filter by product ID if provided
        if ( ! empty( $params['product_id'] ) ) {
            $args['product_id'] = absint( $params['product_id'] );
        }

        $orders = CourierIncompleteOrders::get_incomplete_orders( $args );
        
        // Enhance orders with courier history data if available
        if ( ! class_exists( 'CourierHistory' ) ) {
            $history_file = dirname( __FILE__ ) . '/class.CourierHistory.php';
            if ( file_exists( $history_file ) ) {
                require_once $history_file;
            }
        }
        
        if ( class_exists( 'CourierHistory' ) ) {
            foreach ( $orders as &$order ) {
                // First check if order has courier_data
                if ( empty( $order['courier_data'] ) && ! empty( $order['phone'] ) ) {
                    // Try to get from history table
                    try {
                        $history = CourierHistory::get_history_by_rel( 'incomplete_order', $order['id'] );
                        if ( ! $history ) {
                            // Try latest history for this phone
                            $history = CourierHistory::get_latest_history( $order['phone'], 'incomplete_order' );
                        }
                        if ( $history && ! empty( $history->data ) ) {
                            $order['courier_data'] = wp_json_encode( $history->data );
                            $order['order_data'] = $history->data; // Also set order_data for React component
                        }
                    } catch ( Exception $e ) {
                        // Silently fail if history lookup fails
                    }
                } elseif ( ! empty( $order['courier_data'] ) ) {
                    // Decode courier_data to order_data for React
                    $decoded = json_decode( $order['courier_data'], true );
                    if ( $decoded ) {
                        $order['order_data'] = $decoded;
                    }
                }
            }
            unset( $order ); // Break reference
        }
        
        // Get total count for pagination
        global $wpdb;
        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
        $where = array( '1=1' );
        $where_values = array();
        
        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        if ( ! empty( $args['phone'] ) ) {
            $where[] = 'phone LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like( $args['phone'] ) . '%';
        }
        if ( ! empty( $args['email'] ) ) {
            $where[] = 'email LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like( $args['email'] ) . '%';
        }
        if ( ! empty( $args['reference_number'] ) ) {
            $where[] = 'reference_number = %s';
            $where_values[] = $args['reference_number'];
        }
        if ( ! empty( $args['product_id'] ) ) {
            $product_id = absint( $args['product_id'] );
            $where[] = '(cart_data LIKE %s OR cart_data LIKE %s)';
            $where_values[] = '%"product_id":' . $product_id . '%';
            $where_values[] = '%"product_id":"' . $product_id . '"%';
        }
        
        $where_clause = implode( ' AND ', $where );
        
        if ( ! empty( $where_values ) ) {
            $query = $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE $where_clause", $where_values );
        } else {
            $query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        }
        
        $total = $wpdb->get_var( $query );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $orders,
            'total' => (int) $total,
            'per_page' => $args['limit'],
            'page' => isset( $params['page'] ) ? absint( $params['page'] ) : 1,
        ), 200 );
        } catch ( Exception $e ) {
            return new WP_Error( 'server_error', 'An error occurred while fetching orders: ' . $e->getMessage(), array( 'status' => 500 ) );
        } catch ( Error $e ) {
            return new WP_Error( 'server_error', 'A fatal error occurred: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * Get single incomplete order via REST API.
     */
    public function get_incomplete_order( $request ) {
        // Prevent any output before JSON response
        if ( ob_get_level() ) {
            ob_clean();
        }
        
        try {
            require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
            
            $id = absint( $request['id'] );
            $order = CourierIncompleteOrders::get_incomplete_order( $id );

            if ( ! $order ) {
                return new WP_Error( 'not_found', 'Incomplete order not found', array( 'status' => 404 ) );
            }

            // Enhance with courier history if courier_data is empty
            if ( ! class_exists( 'CourierHistory' ) ) {
                $history_file = dirname( __FILE__ ) . '/class.CourierHistory.php';
                if ( file_exists( $history_file ) ) {
                    require_once $history_file;
                }
            }
            
            if ( class_exists( 'CourierHistory' ) ) {
                if ( empty( $order['courier_data'] ) && ! empty( $order['phone'] ) ) {
                    try {
                        $history = CourierHistory::get_history_by_rel( 'incomplete_order', $id );
                        if ( ! $history ) {
                            // Try latest history for this phone
                            $history = CourierHistory::get_latest_history( $order['phone'], 'incomplete_order' );
                        }
                        if ( $history && ! empty( $history->data ) ) {
                            $order['courier_data'] = wp_json_encode( $history->data );
                            $order['order_data'] = $history->data; // Also set order_data for React component
                        }
                    } catch ( Exception $e ) {
                        // Silently fail if history lookup fails
                    }
                } elseif ( ! empty( $order['courier_data'] ) ) {
                    // Decode courier_data to order_data for React
                    $decoded = json_decode( $order['courier_data'], true );
                    if ( $decoded ) {
                        $order['order_data'] = $decoded;
                    }
                }
            }

            return new WP_REST_Response( array(
                'success' => true,
                'data' => $order,
            ), 200 );
        } catch ( Exception $e ) {
            return new WP_Error( 'server_error', 'An error occurred: ' . $e->getMessage(), array( 'status' => 500 ) );
        } catch ( Error $e ) {
            return new WP_Error( 'server_error', 'A fatal error occurred: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * Delete incomplete order via REST API.
     */
    public function delete_incomplete_order( $request ) {
        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        
        $id = absint( $request['id'] );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
        $deleted = $wpdb->delete(
            $table_name,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( $deleted === false ) {
            return new WP_Error( 'delete_failed', 'Failed to delete incomplete order', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Incomplete order deleted successfully',
        ), 200 );
    }

    /**
     * Convert incomplete order to WooCommerce order.
     */
    public function convert_to_order( $request ) {
        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        
        $id = absint( $request['id'] );
        $incomplete_order = CourierIncompleteOrders::get_incomplete_order( $id );
        
        if ( ! $incomplete_order ) {
            return new WP_Error( 'not_found', 'Incomplete order not found', array( 'status' => 404 ) );
        }

        if ( $incomplete_order['status'] === 'converted' && ! empty( $incomplete_order['converted_to_order_id'] ) ) {
            return new WP_Error( 'already_converted', 'This incomplete order has already been converted to order #' . $incomplete_order['converted_to_order_id'], array( 'status' => 400 ) );
        }

        // Check if WooCommerce is active
        if ( ! function_exists( 'wc_create_order' ) ) {
            return new WP_Error( 'woocommerce_not_active', 'WooCommerce is not active', array( 'status' => 500 ) );
        }

        try {
            // Parse cart data
            $cart_data = is_array( $incomplete_order['cart_data'] ) ? $incomplete_order['cart_data'] : json_decode( $incomplete_order['cart_data'], true );
            $billing_data = is_array( $incomplete_order['billing_data'] ) ? $incomplete_order['billing_data'] : json_decode( $incomplete_order['billing_data'], true );
            $shipping_data = is_array( $incomplete_order['shipping_data'] ) ? $incomplete_order['shipping_data'] : json_decode( $incomplete_order['shipping_data'], true );
            $customer_data = is_array( $incomplete_order['customer_data'] ) ? $incomplete_order['customer_data'] : json_decode( $incomplete_order['customer_data'], true );

            // Create WooCommerce order
            $order = wc_create_order();
            
            if ( is_wp_error( $order ) ) {
                return new WP_Error( 'order_creation_failed', 'Failed to create WooCommerce order: ' . $order->get_error_message(), array( 'status' => 500 ) );
            }

            // Add products to order
            if ( ! empty( $cart_data['items'] ) && is_array( $cart_data['items'] ) ) {
                foreach ( $cart_data['items'] as $item ) {
                    $product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
                    $variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
                    $quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
                    
                    if ( $product_id > 0 ) {
                        $product = $variation_id > 0 ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
                        if ( $product ) {
                            $order->add_product( $product, $quantity );
                        }
                    }
                }
            }

            // Set billing address
            if ( ! empty( $billing_data ) && is_array( $billing_data ) ) {
                $order->set_billing_first_name( $billing_data['first_name'] ?? '' );
                $order->set_billing_last_name( $billing_data['last_name'] ?? '' );
                $order->set_billing_company( $billing_data['company'] ?? '' );
                $order->set_billing_address_1( $billing_data['address_1'] ?? '' );
                $order->set_billing_address_2( $billing_data['address_2'] ?? '' );
                $order->set_billing_city( $billing_data['city'] ?? '' );
                $order->set_billing_state( $billing_data['state'] ?? '' );
                $order->set_billing_postcode( $billing_data['postcode'] ?? '' );
                $order->set_billing_country( $billing_data['country'] ?? '' );
                $order->set_billing_phone( $billing_data['phone'] ?? $incomplete_order['phone'] ?? '' );
                $order->set_billing_email( $billing_data['email'] ?? $incomplete_order['email'] ?? '' );
            }

            // Set shipping address
            if ( ! empty( $shipping_data ) && is_array( $shipping_data ) ) {
                $order->set_shipping_first_name( $shipping_data['first_name'] ?? '' );
                $order->set_shipping_last_name( $shipping_data['last_name'] ?? '' );
                $order->set_shipping_company( $shipping_data['company'] ?? '' );
                $order->set_shipping_address_1( $shipping_data['address_1'] ?? '' );
                $order->set_shipping_address_2( $shipping_data['address_2'] ?? '' );
                $order->set_shipping_city( $shipping_data['city'] ?? '' );
                $order->set_shipping_state( $shipping_data['state'] ?? '' );
                $order->set_shipping_postcode( $shipping_data['postcode'] ?? '' );
                $order->set_shipping_country( $shipping_data['country'] ?? '' );
            }

            // Set customer if logged in
            if ( ! empty( $customer_data['user_id'] ) ) {
                $order->set_customer_id( absint( $customer_data['user_id'] ) );
            }

            // Set payment method if available
            if ( ! empty( $customer_data['payment_method'] ) ) {
                $order->set_payment_method( $customer_data['payment_method'] );
            }

            // Calculate totals
            $order->calculate_totals();

            // Set order status to pending
            $order->set_status( 'pending' );

            // Check courier ratio if phone is available
            $phone = $incomplete_order['phone'] ?? '';
            $courier_data = null;
            if ( ! empty( $phone ) ) {
                require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
                $courier_data = CourierAPI::fetch_order_ratio_from_api( $phone );
                
                if ( $courier_data !== false ) {
                    // Store in history table
                    if ( ! class_exists( 'CourierHistory' ) ) {
                        $history_file = dirname( __FILE__ ) . '/class.CourierHistory.php';
                        if ( file_exists( $history_file ) ) {
                            require_once $history_file;
                        }
                    }
                    
                    if ( class_exists( 'CourierHistory' ) ) {
                        try {
                            CourierHistory::store_history( $phone, $courier_data, 'wc_order', $new_order_id );
                        } catch ( Exception $e ) {
                        }
                    }
                    
                    // Store courier data in order meta
                    $order->update_meta_data( '_courier_data', $courier_data );
                    $order->update_meta_data( '_courier_data_checked_at', current_time( 'mysql' ) );
                }
            }

            // Add order note
            $reference_number = $incomplete_order['reference_number'] ?? 'INC-' . $id;
            $order->add_order_note( sprintf( 
                'Order converted from incomplete order (Reference: %s, ID: %d)', 
                $reference_number, 
                $id 
            ) );

            // Save order
            $order->save();

            $order_id = $order->get_id();

            // Update incomplete order
            CourierIncompleteOrders::mark_as_converted( $id, $order_id );

            // Update incomplete order with order number
            global $wpdb;
            $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
            $wpdb->update(
                $table_name,
                array( 'order_number' => $order->get_order_number() ),
                array( 'id' => $id ),
                array( '%s' ),
                array( '%d' )
            );

            return new WP_REST_Response( array(
                'success' => true,
                'message' => 'Order created successfully',
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'order_edit_url' => admin_url( 'post.php?post=' . $order_id . '&action=edit' ),
            ), 200 );

        } catch ( Exception $e ) {
            return new WP_Error( 'conversion_failed', 'Failed to convert incomplete order: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * Get list of WooCommerce products for filtering.
     */
    public function get_products( $request ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return new WP_Error( 'woocommerce_not_active', 'WooCommerce is not active', array( 'status' => 500 ) );
        }

        $params = $request->get_query_params();
        $search = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
        $limit = isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 50;

        $args = array(
            'limit' => $limit,
            'status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        );

        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        $products = wc_get_products( $args );
        $products_data = array();

        foreach ( $products as $product ) {
            $products_data[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
            );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $products_data,
        ), 200 );
    }

    /**
     * Check courier ratio for an incomplete order.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function check_courier_ratio( $request ) {
        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        
        $id = absint( $request['id'] );
        $incomplete_order = CourierIncompleteOrders::get_incomplete_order( $id );
        
        if ( ! $incomplete_order ) {
            return new WP_Error( 'not_found', 'Incomplete order not found', array( 'status' => 404 ) );
        }

        $phone = $incomplete_order['phone'] ?? '';
        
        if ( empty( $phone ) ) {
            return new WP_Error( 'no_phone', 'Phone number is required to check courier ratio', array( 'status' => 400 ) );
        }

        // Fetch courier data from API
        $courier_data = CourierAPI::fetch_order_ratio_from_api( $phone );
        
        if ( $courier_data === false ) {
            $error_message = CourierAPI::get_last_error();
            if ( empty( $error_message ) ) {
                $error_message = 'Failed to fetch courier data.';
            }
            return new WP_Error( 'api_error', $error_message, array( 'status' => 500 ) );
        }

        // Store courier data in history table
        if ( ! class_exists( 'CourierHistory' ) ) {
            $history_file = dirname( __FILE__ ) . '/class.CourierHistory.php';
            if ( file_exists( $history_file ) ) {
                require_once $history_file;
            }
        }
        
        if ( class_exists( 'CourierHistory' ) ) {
            try {
                CourierHistory::store_history( $phone, $courier_data, 'incomplete_order', $id );
            } catch ( Exception $e ) {
            }
        }

        // Store courier data in incomplete order
        $update_data = array(
            'courier_data' => wp_json_encode( $courier_data ),
            'updated_at' => current_time( 'mysql' ),
        );
        
        CourierIncompleteOrders::update_incomplete_order( $id, $update_data );

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Courier ratio checked successfully',
            'data' => $courier_data,
        ), 200 );
    }

    /**
     * Update WooCommerce order from incomplete order data.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_order_from_incomplete( $request ) {
        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        
        $id = absint( $request['id'] );
        $incomplete_order = CourierIncompleteOrders::get_incomplete_order( $id );
        
        if ( ! $incomplete_order ) {
            return new WP_Error( 'not_found', 'Incomplete order not found', array( 'status' => 404 ) );
        }

        if ( $incomplete_order['status'] !== 'converted' || empty( $incomplete_order['converted_to_order_id'] ) ) {
            return new WP_Error( 'not_converted', 'This incomplete order has not been converted to a WooCommerce order yet', array( 'status' => 400 ) );
        }

        // Check if WooCommerce is active
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new WP_Error( 'woocommerce_not_active', 'WooCommerce is not active', array( 'status' => 500 ) );
        }

        $order_id = absint( $incomplete_order['converted_to_order_id'] );
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return new WP_Error( 'order_not_found', 'WooCommerce order not found', array( 'status' => 404 ) );
        }

        $body = $request->get_json_params();
        $update_billing = isset( $body['update_billing'] ) && $body['update_billing'] === true;
        $update_shipping = isset( $body['update_shipping'] ) && $body['update_shipping'] === true;
        $update_items = isset( $body['update_items'] ) && $body['update_items'] === true;

        try {
            // Parse data
            $billing_data = is_array( $incomplete_order['billing_data'] ) ? $incomplete_order['billing_data'] : json_decode( $incomplete_order['billing_data'], true );
            $shipping_data = is_array( $incomplete_order['shipping_data'] ) ? $incomplete_order['shipping_data'] : json_decode( $incomplete_order['shipping_data'], true );
            $cart_data = is_array( $incomplete_order['cart_data'] ) ? $incomplete_order['cart_data'] : json_decode( $incomplete_order['cart_data'], true );

            // Update billing address
            if ( $update_billing && ! empty( $billing_data ) && is_array( $billing_data ) ) {
                $order->set_billing_first_name( $billing_data['first_name'] ?? '' );
                $order->set_billing_last_name( $billing_data['last_name'] ?? '' );
                $order->set_billing_company( $billing_data['company'] ?? '' );
                $order->set_billing_address_1( $billing_data['address_1'] ?? '' );
                $order->set_billing_address_2( $billing_data['address_2'] ?? '' );
                $order->set_billing_city( $billing_data['city'] ?? '' );
                $order->set_billing_state( $billing_data['state'] ?? '' );
                $order->set_billing_postcode( $billing_data['postcode'] ?? '' );
                $order->set_billing_country( $billing_data['country'] ?? '' );
                $order->set_billing_phone( $billing_data['phone'] ?? $incomplete_order['phone'] ?? '' );
                $order->set_billing_email( $billing_data['email'] ?? $incomplete_order['email'] ?? '' );
            }

            // Update shipping address
            if ( $update_shipping && ! empty( $shipping_data ) && is_array( $shipping_data ) ) {
                $order->set_shipping_first_name( $shipping_data['first_name'] ?? '' );
                $order->set_shipping_last_name( $shipping_data['last_name'] ?? '' );
                $order->set_shipping_company( $shipping_data['company'] ?? '' );
                $order->set_shipping_address_1( $shipping_data['address_1'] ?? '' );
                $order->set_shipping_address_2( $shipping_data['address_2'] ?? '' );
                $order->set_shipping_city( $shipping_data['city'] ?? '' );
                $order->set_shipping_state( $shipping_data['state'] ?? '' );
                $order->set_shipping_postcode( $shipping_data['postcode'] ?? '' );
                $order->set_shipping_country( $shipping_data['country'] ?? '' );
            }

            // Update order items
            if ( $update_items && ! empty( $cart_data['items'] ) && is_array( $cart_data['items'] ) ) {
                // Remove all existing items
                foreach ( $order->get_items() as $item_id => $item ) {
                    $order->remove_item( $item_id );
                }
                
                // Add new items from cart data
                foreach ( $cart_data['items'] as $item ) {
                    $product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
                    $variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
                    $quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
                    
                    if ( $product_id > 0 ) {
                        $product = $variation_id > 0 ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
                        if ( $product ) {
                            $order->add_product( $product, $quantity );
                        }
                    }
                }
            }

            // Recalculate totals
            $order->calculate_totals();

            // Add order note
            $reference_number = $incomplete_order['reference_number'] ?? 'INC-' . $id;
            $updates = array();
            if ( $update_billing ) $updates[] = 'billing';
            if ( $update_shipping ) $updates[] = 'shipping';
            if ( $update_items ) $updates[] = 'items';
            
            $order->add_order_note( sprintf( 
                'Order updated from incomplete order data (Reference: %s). Updated: %s', 
                $reference_number,
                implode( ', ', $updates )
            ) );

            // Save order
            $order->save();

            return new WP_REST_Response( array(
                'success' => true,
                'message' => 'Order updated successfully',
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'order_edit_url' => admin_url( 'post.php?post=' . $order_id . '&action=edit' ),
            ), 200 );

        } catch ( Exception $e ) {
            return new WP_Error( 'update_failed', 'Failed to update order: ' . $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * Update incomplete order data.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_incomplete_order( $request ) {
        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        
        $id = absint( $request['id'] );
        $incomplete_order = CourierIncompleteOrders::get_incomplete_order( $id );
        
        if ( ! $incomplete_order ) {
            return new WP_Error( 'not_found', 'Incomplete order not found', array( 'status' => 404 ) );
        }

        $body = $request->get_json_params();
        
        // Prepare update data
        $update_data = array();
        
        // Update phone if provided
        if ( isset( $body['phone'] ) ) {
            $update_data['phone'] = sanitize_text_field( $body['phone'] );
        }
        
        // Update email if provided
        if ( isset( $body['email'] ) ) {
            $update_data['email'] = sanitize_email( $body['email'] );
        }
        
        // Update billing data if provided
        if ( isset( $body['billing_data'] ) && is_array( $body['billing_data'] ) ) {
            $update_data['billing_data'] = wp_json_encode( $body['billing_data'] );
        }
        
        // Update shipping data if provided
        if ( isset( $body['shipping_data'] ) && is_array( $body['shipping_data'] ) ) {
            $update_data['shipping_data'] = wp_json_encode( $body['shipping_data'] );
        }
        
        // Update cart data if provided
        if ( isset( $body['cart_data'] ) && is_array( $body['cart_data'] ) ) {
            $update_data['cart_data'] = wp_json_encode( $body['cart_data'] );
        }
        
        // Update customer data if provided
        if ( isset( $body['customer_data'] ) && is_array( $body['customer_data'] ) ) {
            $update_data['customer_data'] = wp_json_encode( $body['customer_data'] );
        }
        
        // Update checkout step if provided
        if ( isset( $body['checkout_step'] ) ) {
            $update_data['checkout_step'] = sanitize_text_field( $body['checkout_step'] );
        }
        
        // Update abandonment_reason if provided
        if ( isset( $body['abandonment_reason'] ) ) {
            $update_data['abandonment_reason'] = sanitize_text_field( $body['abandonment_reason'] );
        }
        
        // Always update the updated_at timestamp
        $update_data['updated_at'] = current_time( 'mysql' );
        
        if ( empty( $update_data ) ) {
            return new WP_Error( 'no_data', 'No data provided to update', array( 'status' => 400 ) );
        }
        
        // Update the incomplete order
        $result = CourierIncompleteOrders::update_incomplete_order( $id, $update_data );
        
        if ( ! $result ) {
            global $wpdb;
            return new WP_Error( 'update_failed', 'Failed to update incomplete order', array( 'status' => 500 ) );
        }
        
        // Get updated order to verify the update
        $updated_order = CourierIncompleteOrders::get_incomplete_order( $id );
        
        // Log the updated abandonment_reason for verification
        if ( isset( $update_data['abandonment_reason'] ) ) {
        }
        
        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Incomplete order updated successfully',
            'data' => $updated_order,
        ), 200 );
    }
}

