<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierRejectedOrders
 * Handles database operations for rejected orders.
 */
class CourierRejectedOrders {

    /**
     * Table name for rejected orders.
     */
    private static $table_name = 'bd_courier_rejected_orders';

    public function __construct() {
        // Create table if it doesn't exist (for existing installations)
        add_action( 'admin_init', array( $this, 'maybe_create_table' ) );
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
     * Create the rejected orders table.
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
            original_order_id bigint(20) UNSIGNED DEFAULT NULL,
            order_data longtext NOT NULL,
            cart_data longtext NOT NULL,
            customer_data longtext NOT NULL,
            courier_data longtext DEFAULT NULL,
            success_ratio decimal(5,2) DEFAULT NULL,
            min_required_ratio decimal(5,2) DEFAULT NULL,
            rejection_reason text DEFAULT NULL,
            status varchar(20) DEFAULT 'rejected',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            converted_at datetime DEFAULT NULL,
            converted_to_order_id bigint(20) UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY original_order_id (original_order_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Store a rejected order.
     *
     * @param WC_Order $order Order object.
     * @param array    $courier_data Courier data.
     * @param float    $success_ratio Success ratio.
     * @param float    $min_required_ratio Minimum required ratio.
     * @param string   $rejection_reason Rejection reason.
     * @return int|false Rejected order ID or false on failure.
     */
    public static function store_rejected_order( $order, $courier_data, $success_ratio, $min_required_ratio, $rejection_reason = '' ) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Ensure table exists
        $instance = new self();
        $instance->maybe_create_table();
        
        // Prepare order data
        $order_data = array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_key' => $order->get_order_key(),
            'order_date' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : current_time( 'mysql' ),
            'order_status' => $order->get_status(),
            'order_total' => $order->get_total(),
            'order_currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
        );

        // Prepare cart data (items)
        $cart_data = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $cart_data[] = array(
                'item_id' => $item_id,
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'tax' => $item->get_total_tax(),
                'sku' => $product ? $product->get_sku() : '',
            );
        }

        // Prepare customer data
        $customer_data = array(
            'billing' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ),
            'shipping' => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
            ),
            'customer_id' => $order->get_customer_id(),
            'customer_ip_address' => $order->get_customer_ip_address(),
            'customer_user_agent' => $order->get_customer_user_agent(),
        );

        // Insert into database
        $result = $wpdb->insert(
            $table_name,
            array(
                'original_order_id' => $order->get_id(),
                'order_data' => wp_json_encode( $order_data ),
                'cart_data' => wp_json_encode( $cart_data ),
                'customer_data' => wp_json_encode( $customer_data ),
                'courier_data' => wp_json_encode( $courier_data ),
                'success_ratio' => $success_ratio,
                'min_required_ratio' => $min_required_ratio,
                'rejection_reason' => $rejection_reason ? $rejection_reason : sprintf(
                    __( 'Order rejected: Success ratio (%.2f%%) is below minimum required (%.2f%%)', 'bd-courier-order-ratio-checker' ),
                    $success_ratio,
                    $min_required_ratio
                ),
                'status' => 'rejected',
                'created_at' => current_time( 'mysql' ),
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%f',
                '%f',
                '%s',
                '%s',
                '%s',
            )
        );

        if ( $result === false ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get rejected order by ID.
     *
     * @param int $id Rejected order ID.
     * @return object|null
     */
    public static function get_rejected_order( $id ) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ) );
    }

    /**
     * Get all rejected orders.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_rejected_orders( $args = array() ) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        $defaults = array(
            'status' => 'rejected',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $where = array( '1=1' );
        $where_values = array();
        
        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = ! empty( $where_values ) ? $wpdb->prepare( implode( ' AND ', $where ), $where_values ) : implode( ' AND ', $where );
        
        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'created_at DESC';
        }
        
        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY $orderby LIMIT $limit OFFSET $offset";
        
        return $wpdb->get_results( $query );
    }

    /**
     * Get count of rejected orders.
     *
     * @param string $status Status to filter by.
     * @return int
     */
    public static function get_count( $status = 'rejected' ) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s",
            $status
        ) );
    }

    /**
     * Mark rejected order as converted.
     *
     * @param int $rejected_order_id Rejected order ID.
     * @param int $new_order_id New order ID.
     * @return bool
     */
    public static function mark_as_converted( $rejected_order_id, $new_order_id ) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        return (bool) $wpdb->update(
            $table_name,
            array(
                'status' => 'converted',
                'converted_at' => current_time( 'mysql' ),
                'converted_to_order_id' => $new_order_id,
            ),
            array( 'id' => $rejected_order_id ),
            array( '%s', '%s', '%d' ),
            array( '%d' )
        );
    }

    /**
     * Delete rejected order.
     *
     * @param int $id Rejected order ID.
     * @return bool
     */
    public static function delete_rejected_order( $id ) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        return (bool) $wpdb->delete(
            $table_name,
            array( 'id' => $id ),
            array( '%d' )
        );
    }
}

