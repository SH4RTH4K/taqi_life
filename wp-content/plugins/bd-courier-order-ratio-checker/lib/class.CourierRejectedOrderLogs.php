<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierRejectedOrderLogs
 * Handles database operations for rejected order logs.
 */
class CourierRejectedOrderLogs {

    /**
     * Table name for rejected order logs.
     */
    private static $table_name = 'bd_courier_rejected_order_logs';

    public function __construct() {
        // Create table if it doesn't exist (for existing installations)
        add_action( 'init', array( $this, 'maybe_create_table' ) );
        add_action( 'admin_menu', array( $this, 'add_rejected_logs_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_head', array( $this, 'hide_admin_notices' ) );

        // REST API endpoints for React frontend
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // AJAX handlers for logs management
        add_action( 'wp_ajax_get_rejected_order_logs', array( $this, 'ajax_get_logs' ) );
        add_action( 'wp_ajax_delete_rejected_order_log', array( $this, 'ajax_delete_log' ) );
        add_action( 'wp_ajax_bulk_delete_rejected_order_logs', array( $this, 'ajax_bulk_delete_logs' ) );
    }

    /**
     * Get table name with WordPress prefix.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    /**
     * Create the rejected order logs table.
     */
    public function create_table() {
        $this->maybe_create_table();
    }

    /**
     * Create table if it doesn't exist.
     */
    public function maybe_create_table() {
        global $wpdb;

        $table_name = self::get_table_name();

        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
            return; // Table already exists
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            customer_email varchar(100) DEFAULT NULL,
            customer_phone varchar(20) DEFAULT NULL,
            customer_ip varchar(45) DEFAULT NULL,
            customer_device_fingerprint varchar(255) DEFAULT NULL,
            rejection_reason varchar(100) NOT NULL,
            rejection_details text DEFAULT NULL,
            success_ratio decimal(5,2) DEFAULT NULL,
            min_required_ratio decimal(5,2) DEFAULT NULL,
            order_total decimal(10,2) DEFAULT NULL,
            order_currency varchar(3) DEFAULT 'BDT',
            cart_items_count int(11) DEFAULT 0,
            payment_method varchar(100) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            referrer_url text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY customer_email (customer_email),
            KEY customer_phone (customer_phone),
            KEY customer_ip (customer_ip),
            KEY rejection_reason (rejection_reason),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Log a rejected order attempt.
     *
     * @param array $log_data Log data.
     * @return int|false Log ID or false on failure.
     */
    public static function log_rejection( $log_data ) {
        global $wpdb;

        $table_name = self::get_table_name();

        // Ensure table exists
        $instance = new self();
        $instance->maybe_create_table();

        $default_data = array(
            'order_id' => null,
            'customer_email' => null,
            'customer_phone' => null,
            'customer_ip' => null,
            'customer_device_fingerprint' => null,
            'rejection_reason' => '',
            'rejection_details' => null,
            'success_ratio' => null,
            'min_required_ratio' => null,
            'order_total' => null,
            'order_currency' => 'BDT',
            'cart_items_count' => 0,
            'payment_method' => null,
            'user_agent' => null,
            'referrer_url' => null,
            'created_at' => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $log_data, $default_data );

        // Sanitize data
        $sanitized_data = array();
        foreach ( $data as $key => $value ) {
            if ( is_numeric( $value ) || is_float( $value ) ) {
                $sanitized_data[ $key ] = $value;
            } elseif ( is_array( $value ) || is_object( $value ) ) {
                $sanitized_data[ $key ] = wp_json_encode( $value );
            } else {
                $sanitized_data[ $key ] = sanitize_text_field( $value );
            }
        }

        $result = $wpdb->insert( $table_name, $sanitized_data );

        if ( $result === false ) {
            return false;
        }

        $log_id = $wpdb->insert_id;

        // Check for automatic blocking based on rejection patterns
        // Create instance to call non-static method
        $instance = new self();
        $instance->check_auto_blocking( $log_data );

        return $log_id;
    }

    /**
     * Add rejected logs submenu under incomplete orders.
     */
    public function add_rejected_logs_submenu() {
        add_submenu_page(
            'woocommerce',
            __( 'Rejected Order Logs', 'bd-courier-order-ratio-checker' ),
            __( 'Rejected Order Logs', 'bd-courier-order-ratio-checker' ),
            'manage_woocommerce',
            'bd-courier-rejected-logs',
            array( $this, 'render_rejected_logs_page' )
        );
    }

    /**
     * Hide admin notices on rejected logs page.
     */
    public function hide_admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        
        if ( $screen->id === 'woocommerce_page_bd-courier-rejected-logs' || ( isset( $_GET['page'] ) && $_GET['page'] === 'bd-courier-rejected-logs' ) ) {
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
     * Render the rejected logs page.
     */
    public function render_rejected_logs_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div id="bd-courier-rejected-logs-root"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets for rejected logs page.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our specific page
        if ( $hook !== 'woocommerce_page_bd-courier-rejected-logs' ) {
            return;
        }

        // Get plugin root directory and main file path
        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';

        // Enqueue standalone CSS (generated by Vite build)
        $css_file = $plugin_dir . '/assets/js/style.css';
        if ( file_exists( $css_file ) ) {
            $css_url = plugins_url( 'assets/js/style.css', $plugin_main_file );
            $css_url = add_query_arg( 'v', BD_COURIER_VERSION, $css_url );

            wp_enqueue_style(
                'bd-courier-rejected-logs-css',
                $css_url,
                [],
                BD_COURIER_VERSION
            );
        }

        // Enqueue standalone JavaScript
        $js_file = $plugin_dir . '/assets/js/rejected-logs-standalone.js';
        if ( file_exists( $js_file ) ) {
            $js_url = plugins_url( 'assets/js/rejected-logs-standalone.js', $plugin_main_file );
            $js_url = add_query_arg( 'v', BD_COURIER_VERSION, $js_url );

            wp_enqueue_script(
                'bd-courier-rejected-logs-js',
                $js_url,
                [],
                BD_COURIER_VERSION,
                true
            );

            // Localize script with REST API data
            wp_localize_script( 'bd-courier-rejected-logs-js', 'bdcourierRejectedLogsAjax', [
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            ]);
        }

        // Enqueue admin JavaScript handler
        $admin_js_file = $plugin_dir . '/assets/js/rejected-logs-admin.js';
        if ( file_exists( $admin_js_file ) ) {
            $admin_js_url = plugins_url( 'assets/js/rejected-logs-admin.js', $plugin_main_file );
            $admin_js_url = add_query_arg( 'v', BD_COURIER_VERSION, $admin_js_url );

            wp_enqueue_script(
                'bd-courier-rejected-logs-admin-js',
                $admin_js_url,
                ['jquery'],
                BD_COURIER_VERSION,
                true
            );

            // Localize admin script
            wp_localize_script( 'bd-courier-rejected-logs-admin-js', 'bdcourierRejectedLogsAjax', [
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            ]);
        }
    }

    /**
     * Register REST API routes for the rejected logs.
     */
    public function register_rest_routes() {
        register_rest_route( 'bd-courier/v1', '/rejected-logs', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_rejected_logs' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'page'     => array(
                        'default'           => 1,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param > 0;
                        },
                    ),
                    'per_page' => array(
                        'default'           => 20,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param > 0 && $param <= 100;
                        },
                    ),
                    'search'   => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'reason'   => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'date_from' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'date_to'   => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_rejected_log' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            ),
        ) );

        register_rest_route( 'bd-courier/v1', '/rejected-logs/bulk-delete', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'bulk_delete_rejected_logs' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args'                => array(
                'ids' => array(
                    'required'          => true,
                    'validate_callback' => function( $param ) {
                        return is_array( $param ) && ! empty( $param );
                    },
                ),
            ),
        ) );
    }

    /**
     * Check if user has permission to access the API.
     */
    public function check_permissions() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Get rejected order logs via REST API.
     */
    public function get_rejected_logs( $request ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $page = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $search = $request->get_param( 'search' );
        $reason = $request->get_param( 'reason' );
        $date_from = $request->get_param( 'date_from' );
        $date_to = $request->get_param( 'date_to' );

        $offset = ( $page - 1 ) * $per_page;

        // Build WHERE clause
        $where = array();
        $where_values = array();

        if ( ! empty( $search ) ) {
            $where[] = "(customer_email LIKE %s OR customer_phone LIKE %s OR customer_ip LIKE %s OR rejection_details LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $search ) . '%';
            $where_values = array_merge( $where_values, array( $search_term, $search_term, $search_term, $search_term ) );
        }

        if ( ! empty( $reason ) ) {
            $where[] = "rejection_reason = %s";
            $where_values[] = $reason;
        }

        if ( ! empty( $date_from ) ) {
            $where[] = "DATE(created_at) >= %s";
            $where_values[] = $date_from;
        }

        if ( ! empty( $date_to ) ) {
            $where[] = "DATE(created_at) <= %s";
            $where_values[] = $date_to;
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table_name $where_clause";
        if ( ! empty( $where_values ) ) {
            $total_count = $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) );
        } else {
            $total_count = $wpdb->get_var( $count_query );
        }

        // Get logs
        $query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values = array_merge( $where_values, array( $per_page, $offset ) );

        if ( ! empty( $where_values ) ) {
            $logs = $wpdb->get_results( $wpdb->prepare( $query, $values ), ARRAY_A );
        } else {
            $logs = $wpdb->get_results( $wpdb->prepare( $query, array( $per_page, $offset ) ), ARRAY_A );
        }

        // Format logs
        $formatted_logs = array();
        foreach ( $logs as $log ) {
            $formatted_logs[] = array(
                'id' => (int) $log['id'],
                'order_id' => $log['order_id'] ? (int) $log['order_id'] : null,
                'customer_email' => $log['customer_email'],
                'customer_phone' => $log['customer_phone'],
                'customer_ip' => $log['customer_ip'],
                'customer_device_fingerprint' => $log['customer_device_fingerprint'],
                'rejection_reason' => $log['rejection_reason'],
                'rejection_details' => $log['rejection_details'],
                'success_ratio' => $log['success_ratio'] ? (float) $log['success_ratio'] : null,
                'min_required_ratio' => $log['min_required_ratio'] ? (float) $log['min_required_ratio'] : null,
                'order_total' => $log['order_total'] ? (float) $log['order_total'] : null,
                'order_currency' => $log['order_currency'],
                'cart_items_count' => (int) $log['cart_items_count'],
                'payment_method' => $log['payment_method'],
                'user_agent' => $log['user_agent'],
                'referrer_url' => $log['referrer_url'],
                'created_at' => $log['created_at'],
                'created_at_formatted' => mysql2date( 'M j, Y g:i A', $log['created_at'] ),
            );
        }

        return new WP_REST_Response( array(
            'logs' => $formatted_logs,
            'total_count' => (int) $total_count,
            'total_pages' => ceil( $total_count / $per_page ),
            'current_page' => (int) $page,
            'per_page' => (int) $per_page,
        ), 200 );
    }

    /**
     * Delete a rejected order log via REST API.
     */
    public function delete_rejected_log( $request ) {
        global $wpdb;

        $id = $request->get_param( 'id' );
        $table_name = self::get_table_name();

        $result = $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );

        if ( $result === false ) {
            return new WP_Error( 'delete_failed', 'Failed to delete log entry', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Bulk delete rejected order logs via REST API.
     */
    public function bulk_delete_rejected_logs( $request ) {
        global $wpdb;

        $ids = $request->get_param( 'ids' );
        $table_name = self::get_table_name();

        if ( ! is_array( $ids ) || empty( $ids ) ) {
            return new WP_Error( 'invalid_ids', 'Invalid IDs provided', array( 'status' => 400 ) );
        }

        // Sanitize IDs
        $sanitized_ids = array_map( 'intval', $ids );

        $placeholders = implode( ',', array_fill( 0, count( $sanitized_ids ), '%d' ) );
        $query = "DELETE FROM $table_name WHERE id IN ($placeholders)";

        $result = $wpdb->query( $wpdb->prepare( $query, $sanitized_ids ) );

        if ( $result === false ) {
            return new WP_Error( 'bulk_delete_failed', 'Failed to delete log entries', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'deleted_count' => $result
        ), 200 );
    }

    /**
     * AJAX handler for getting logs.
     */
    public function ajax_get_logs() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $request = new WP_REST_Request( 'GET', '/bd-courier/v1/rejected-logs' );
        $request->set_query_params( $_GET );

        $response = $this->get_rejected_logs( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        } else {
            wp_send_json_success( $response->get_data() );
        }
    }

    /**
     * AJAX handler for deleting a log.
     */
    public function ajax_delete_log() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( 'Invalid log ID' );
        }

        $request = new WP_REST_Request( 'DELETE', '/bd-courier/v1/rejected-logs' );
        $request->set_param( 'id', $id );

        $response = $this->delete_rejected_log( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        } else {
            wp_send_json_success( $response->get_data() );
        }
    }

    /**
     * AJAX handler for bulk deleting logs.
     */
    public function ajax_bulk_delete_logs() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $ids = isset( $_POST['ids'] ) ? $_POST['ids'] : array();

        if ( ! is_array( $ids ) || empty( $ids ) ) {
            wp_send_json_error( 'Invalid IDs provided' );
        }

        $request = new WP_REST_Request( 'POST', '/bd-courier/v1/rejected-logs/bulk-delete' );
        $request->set_param( 'ids', $ids );

        $response = $this->bulk_delete_rejected_logs( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        } else {
            wp_send_json_success( $response->get_data() );
        }
    }

    /**
     * Get rejection reasons for filtering.
     */
    public static function get_rejection_reasons() {
        return array(
            'blocked-entity-checkout' => __( 'Blocked Entity (Blocklist)', 'bd-courier-order-ratio-checker' ),
            'duplicate-order-same-device' => __( 'Duplicate Order (Same Device)', 'bd-courier-order-ratio-checker' ),
            'duplicate-order-same-ip' => __( 'Duplicate Order (Same IP)', 'bd-courier-order-ratio-checker' ),
            'duplicate-order-same-phone' => __( 'Duplicate Order (Same Phone)', 'bd-courier-order-ratio-checker' ),
            'duplicate-order-same-email' => __( 'Duplicate Order (Same Email)', 'bd-courier-order-ratio-checker' ),
            'low-success-rate' => __( 'Low Success Rate', 'bd-courier-order-ratio-checker' ),
            'incomplete-order-timeout' => __( 'Incomplete Order Timeout', 'bd-courier-order-ratio-checker' ),
            'suspicious-activity' => __( 'Suspicious Activity', 'bd-courier-order-ratio-checker' ),
            'payment-fraud' => __( 'Payment Fraud', 'bd-courier-order-ratio-checker' ),
            'other' => __( 'Other', 'bd-courier-order-ratio-checker' ),
        );
    }

    /**
     * Check for automatic blocking based on rejection patterns.
     *
     * @param array $log_data Log data
     */
    private function check_auto_blocking( $log_data ) {
        // Skip if auto-blocking is disabled
        if ( ! get_option( 'bdc_enable_auto_blocking', true ) ) {
            return;
        }

        $phone = $log_data['customer_phone'] ?? null;
        $email = $log_data['customer_email'] ?? null;
        $ip = $log_data['customer_ip'] ?? null;
        $fingerprint = $log_data['customer_device_fingerprint'] ?? null;
        $reason = $log_data['rejection_reason'] ?? '';

        // Define auto-blocking rules
        $auto_block_rules = array(
            'duplicate-order-same-device' => array(
                'threshold' => get_option( 'bdc_auto_block_device_threshold', 5 ),
                'duration' => get_option( 'bdc_auto_block_device_duration', 86400 ), // 24 hours
                'entity_type' => 'fingerprint',
                'entity_value' => $fingerprint,
            ),
            'duplicate-order-same-ip' => array(
                'threshold' => get_option( 'bdc_auto_block_ip_threshold', 10 ),
                'duration' => get_option( 'bdc_auto_block_ip_duration', 3600 ), // 1 hour
                'entity_type' => 'ip',
                'entity_value' => $ip,
            ),
            'duplicate-order-same-phone' => array(
                'threshold' => get_option( 'bdc_auto_block_phone_threshold', 3 ),
                'duration' => get_option( 'bdc_auto_block_phone_duration', 86400 ), // 24 hours
                'entity_type' => 'phone',
                'entity_value' => $phone,
            ),
            'duplicate-order-same-email' => array(
                'threshold' => get_option( 'bdc_auto_block_email_threshold', 5 ),
                'duration' => get_option( 'bdc_auto_block_email_duration', 86400 ), // 24 hours
                'entity_type' => 'email',
                'entity_value' => $email,
            ),
            'low-success-rate' => array(
                'threshold' => get_option( 'bdc_auto_block_low_ratio_threshold', 3 ),
                'duration' => get_option( 'bdc_auto_block_low_ratio_duration', 604800 ), // 7 days
                'entity_type' => 'fingerprint',
                'entity_value' => $fingerprint,
            ),
            'suspicious-activity' => array(
                'threshold' => get_option( 'bdc_auto_block_suspicious_threshold', 2 ),
                'duration' => get_option( 'bdc_auto_block_suspicious_duration', 2592000 ), // 30 days
                'entity_type' => 'ip',
                'entity_value' => $ip,
            ),
        );

        // Check if this rejection reason has auto-blocking rules
        if ( isset( $auto_block_rules[ $reason ] ) ) {
            $rule = $auto_block_rules[ $reason ];

            if ( ! empty( $rule['entity_value'] ) && $rule['threshold'] > 0 ) {
                // Check if entity has exceeded threshold
                if ( $this->has_exceeded_rejection_threshold( $rule['entity_type'], $rule['entity_value'], $reason, $rule['threshold'] ) ) {
                    // Check if already blocked
                    if ( class_exists( 'CourierBlockedEntities' ) && ! CourierBlockedEntities::is_entity_blocked( $rule['entity_type'], $rule['entity_value'] ) ) {
                        // Auto-block the entity
                        $block_reason = sprintf(
                            'Auto-blocked due to %d %s rejections in 24 hours',
                            $rule['threshold'],
                            $reason
                        );

                        CourierBlockedEntities::block_entity(
                            $rule['entity_type'],
                            $rule['entity_value'],
                            $rule['duration'],
                            $block_reason
                        );
                    }
                }
            }
        }
    }

    /**
     * Check if an entity has exceeded the rejection threshold.
     *
     * @param string $entity_type Entity type
     * @param string $entity_value Entity value
     * @param string $reason Rejection reason
     * @param int $threshold Threshold count
     * @return bool True if exceeded threshold
     */
    private function has_exceeded_rejection_threshold( $entity_type, $entity_value, $reason, $threshold ) {
        global $wpdb;

        $table_name = self::get_table_name();

        // Count rejections for this entity in the last 24 hours
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
            WHERE rejection_reason = %s
            AND CASE
                WHEN %s = 'phone' THEN customer_phone = %s
                WHEN %s = 'email' THEN customer_email = %s
                WHEN %s = 'ip' THEN customer_ip = %s
                WHEN %s = 'fingerprint' THEN customer_device_fingerprint = %s
            END
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $reason,
            $entity_type, $entity_value,
            $entity_type, $entity_value,
            $entity_type, $entity_value,
            $entity_type, $entity_value
        ) );

        return $count >= $threshold;
    }
}
