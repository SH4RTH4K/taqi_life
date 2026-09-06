<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierIncompleteOrders
 * Manages incomplete order data collection and storage.
 */
class CourierIncompleteOrders {

    /**
     * Constructor.
     */
    public function __construct() {
        // Create table on plugin activation/load
        add_action( 'plugins_loaded', array( $this, 'maybe_create_table' ) );
        // Update table structure if needed
        add_action( 'plugins_loaded', array( $this, 'maybe_update_table' ) );
    }

    /**
     * Create table if it doesn't exist (similar to CourierRejectedOrders).
     */
    public function maybe_create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
        
        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
            return; // Table already exists
        }
        
        // Table doesn't exist, create it
        $this->create_table();
    }

    /**
     * Update table structure if needed (add missing columns).
     */
    public function maybe_update_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';
        
        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
            return; // Table doesn't exist, will be created by maybe_create_table
        }
        
        // Check and add reference_number column if missing
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'reference_number'",
            DB_NAME,
            $table_name
        ) );
        
        if ( ! $column_exists ) {
            // Add reference_number column
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN reference_number varchar(50) NULL AFTER order_number" );
            
            // Add index for reference_number
            $index_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = %s 
                AND INDEX_NAME = 'reference_number'",
                DB_NAME,
                $table_name
            ) );
            
            if ( ! $index_exists ) {
                $wpdb->query( "ALTER TABLE $table_name ADD KEY reference_number (reference_number)" );
            }
            
            // Generate reference numbers for existing records that don't have one
            $wpdb->query( $wpdb->prepare(
                "UPDATE $table_name 
                SET reference_number = CONCAT('INC-', DATE_FORMAT(created_at, '%%Y%%m%%d'), '-', LPAD(FLOOR(RAND() * 9999), 4, '0'))
                WHERE reference_number IS NULL OR reference_number = ''"
            ) );
        }
        
        // Check and add courier_data column if missing
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'courier_data'",
            DB_NAME,
            $table_name
        ) );
        
        if ( ! $column_exists ) {
            // Add courier_data column after customer_data
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN courier_data longtext NULL AFTER customer_data" );
        }
        
        // Check and add abandonment_reason column if missing
        $column_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'abandonment_reason'",
            DB_NAME,
            $table_name
        ) );
        
        if ( ! $column_exists ) {
            // Add abandonment_reason column after checkout_step
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN abandonment_reason varchar(255) NULL AFTER checkout_step" );
        }
    }

    /**
     * Create the incomplete orders table.
     */
    public function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NULL,
            order_number varchar(100) NULL,
            reference_number varchar(50) NULL,
            browser_fingerprint varchar(255) NOT NULL,
            phone varchar(50) NULL,
            email varchar(255) NULL,
            order_data longtext NULL,
            cart_data longtext NULL,
            customer_data longtext NULL,
            courier_data longtext NULL,
            billing_data longtext NULL,
            shipping_data longtext NULL,
            checkout_step varchar(50) NULL,
            abandonment_reason varchar(255) NULL,
            session_id varchar(255) NULL,
            ip_address varchar(45) NULL,
            user_agent text NULL,
            status varchar(50) DEFAULT 'incomplete',
            converted_to_order_id bigint(20) UNSIGNED NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            notes text NULL,
            PRIMARY KEY (id),
            KEY reference_number (reference_number),
            KEY browser_fingerprint (browser_fingerprint),
            KEY phone (phone),
            KEY email (email),
            KEY status (status),
            KEY created_at (created_at),
            KEY converted_to_order_id (converted_to_order_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Store incomplete order data.
     *
     * @param array $data Order data to store.
     * @return int|false Inserted ID or false on failure.
     */
    public static function store_incomplete_order( $data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

        // Generate reference number if not provided
        $reference_number = isset( $data['reference_number'] ) ? sanitize_text_field( $data['reference_number'] ) : null;
        if ( empty( $reference_number ) ) {
            $reference_number = 'INC-' . date( 'Ymd' ) . '-' . str_pad( wp_rand( 1, 9999 ), 4, '0', STR_PAD_LEFT );
        }

        // Prepare data
        $insert_data = array(
            'browser_fingerprint' => isset( $data['browser_fingerprint'] ) ? sanitize_text_field( $data['browser_fingerprint'] ) : '',
            'phone' => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : null,
            'email' => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : null,
            'reference_number' => $reference_number,
            'order_data' => isset( $data['order_data'] ) ? wp_json_encode( $data['order_data'] ) : null,
            'cart_data' => isset( $data['cart_data'] ) ? wp_json_encode( $data['cart_data'] ) : null,
            'customer_data' => isset( $data['customer_data'] ) ? wp_json_encode( $data['customer_data'] ) : null,
            'billing_data' => isset( $data['billing_data'] ) ? wp_json_encode( $data['billing_data'] ) : null,
            'shipping_data' => isset( $data['shipping_data'] ) ? wp_json_encode( $data['shipping_data'] ) : null,
            'checkout_step' => isset( $data['checkout_step'] ) ? sanitize_text_field( $data['checkout_step'] ) : null,
            'abandonment_reason' => isset( $data['abandonment_reason'] ) ? sanitize_text_field( $data['abandonment_reason'] ) : null,
            'session_id' => isset( $data['session_id'] ) ? sanitize_text_field( $data['session_id'] ) : null,
            'ip_address' => isset( $data['ip_address'] ) ? sanitize_text_field( $data['ip_address'] ) : null,
            'user_agent' => isset( $data['user_agent'] ) ? sanitize_textarea_field( $data['user_agent'] ) : null,
            'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'incomplete',
            'notes' => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
        );

        // Add order_id and order_number if order was created
        if ( isset( $data['order_id'] ) && $data['order_id'] > 0 ) {
            $insert_data['order_id'] = absint( $data['order_id'] );
            $order = wc_get_order( $data['order_id'] );
            if ( $order ) {
                $insert_data['order_number'] = $order->get_order_number();
            }
        }

        $result = $wpdb->insert( $table_name, $insert_data );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get incomplete orders.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_incomplete_orders( $args = array() ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

        $defaults = array(
            'status' => 'incomplete',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $where_values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if ( ! empty( $args['browser_fingerprint'] ) ) {
            $where[] = 'browser_fingerprint = %s';
            $where_values[] = $args['browser_fingerprint'];
        }

        if ( ! empty( $args['phone'] ) ) {
            $where[] = 'phone = %s';
            $where_values[] = $args['phone'];
        }

        if ( ! empty( $args['email'] ) ) {
            $where[] = 'email = %s';
            $where_values[] = $args['email'];
        }

        // Filter by product ID (search in cart_data JSON)
        if ( ! empty( $args['product_id'] ) ) {
            $product_id = absint( $args['product_id'] );
            $where[] = '(cart_data LIKE %s OR cart_data LIKE %s)';
            $where_values[] = '%"product_id":' . $product_id . '%';
            $where_values[] = '%"product_id":"' . $product_id . '"%';
        }

        // Filter by reference number
        if ( ! empty( $args['reference_number'] ) ) {
            $where[] = 'reference_number = %s';
            $where_values[] = $args['reference_number'];
        }

        $where_clause = implode( ' AND ', $where );

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'created_at DESC';
        }

        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY $orderby LIMIT $limit OFFSET $offset";

        if ( ! empty( $where_values ) ) {
            $query = $wpdb->prepare( $query, $where_values );
        }

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Get incomplete order by ID.
     *
     * @param int $id Incomplete order ID.
     * @return array|null
     */
    public static function get_incomplete_order( $id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if ( $result ) {
            // Decode JSON fields
            $json_fields = array( 'order_data', 'cart_data', 'customer_data', 'billing_data', 'shipping_data', 'courier_data' );
            foreach ( $json_fields as $field ) {
                if ( ! empty( $result[ $field ] ) && is_string( $result[ $field ] ) ) {
                    $decoded = json_decode( $result[ $field ], true );
                    if ( json_last_error() === JSON_ERROR_NONE ) {
                        $result[ $field ] = $decoded;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Mark incomplete order as converted.
     *
     * @param int $id Incomplete order ID.
     * @param int $new_order_id New order ID.
     * @return bool
     */
    public static function mark_as_converted( $id, $new_order_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

        return $wpdb->update(
            $table_name,
            array(
                'status' => 'converted',
                'converted_to_order_id' => absint( $new_order_id ),
            ),
            array( 'id' => absint( $id ) ),
            array( '%s', '%d' ),
            array( '%d' )
        ) !== false;
    }

    /**
     * Update incomplete order.
     *
     * @param int $id Incomplete order ID.
     * @param array $data Data to update.
     * @return bool
     */
    public static function update_incomplete_order( $id, $data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

        // Ensure updated_at is set
        if ( ! isset( $data['updated_at'] ) ) {
            $data['updated_at'] = current_time( 'mysql' );
        }

        // Prepare format array based on data keys
        $formats = array();
        foreach ( $data as $key => $value ) {
            if ( is_int( $value ) ) {
                $formats[] = '%d';
            } elseif ( is_float( $value ) ) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        // Log the update for debugging
        if ( isset( $data['abandonment_reason'] ) ) {
        }

        $result = $wpdb->update(
            $table_name,
            $data,
            array( 'id' => absint( $id ) ),
            $formats,
            array( '%d' )
        );

        if ( $result === false ) {
        } else {
        }

        return $result !== false;
    }

    /**
     * Get count of incomplete orders.
     *
     * @param string $status Status to filter by.
     * @return int
     */
    public static function get_count( $status = 'incomplete' ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

        $query = "SELECT COUNT(*) FROM $table_name WHERE status = %s";
        $count = $wpdb->get_var( $wpdb->prepare( $query, $status ) );

        return absint( $count );
    }

    /**
     * Check if browser fingerprint has recent incomplete orders.
     *
     * @param string $fingerprint Browser fingerprint.
     * @param int    $hours Hours to check back.
     * @return bool
     */
    public static function has_recent_incomplete_orders( $fingerprint, $hours = 24 ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        if ( ! $table_exists ) {
            return false;
        }

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE browser_fingerprint = %s 
            AND status = 'incomplete' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $fingerprint,
            $hours
        );

        $count = $wpdb->get_var( $query );

        return absint( $count ) > 0;
    }

    /**
     * Check if phone number has recent orders (completed or incomplete).
     *
     * @param string $phone Phone number (formatted).
     * @param int    $hours Hours to check back.
     * @return array Array of order IDs found.
     */
    public static function get_recent_orders_by_phone( $phone, $hours = 24 ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bd_courier_incomplete_orders';

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        if ( ! $table_exists ) {
            return array();
        }

        // Format phone for comparison
        $phone_formatted = preg_replace( '/[^0-9]/', '', $phone );

        $query = $wpdb->prepare(
            "SELECT DISTINCT converted_to_order_id FROM $table_name 
            WHERE (phone = %s OR phone = %s)
            AND status = 'converted'
            AND converted_to_order_id IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $phone,
            $phone_formatted,
            $hours
        );

        $order_ids = $wpdb->get_col( $query );

        return array_filter( array_map( 'absint', $order_ids ) );
    }
}

