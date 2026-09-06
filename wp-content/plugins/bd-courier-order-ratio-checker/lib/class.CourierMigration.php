<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierMigration
 * Handles migration of order meta data to the history table
 */
class CourierMigration {

    /**
     * Get migration status
     */
    public static function get_migration_status() {
        $migration_completed = get_option( 'bdc_migration_completed', false );
        $migration_version = get_option( 'bdc_migration_version', '0' );
        
        return array(
            'completed' => $migration_completed,
            'version' => $migration_version,
            'current_version' => '3.0.7',
        );
    }

    /**
     * Count orders with courier data in meta
     */
    public static function count_orders_to_migrate() {
        global $wpdb;
        
        require_once dirname( __FILE__ ) . '/class.CourierHistory.php';
        
        $count = 0;
        $history_table = CourierHistory::get_table_name();
        
        // Check HPOS (WooCommerce 8.0+)
        if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && 
             \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $order_meta_table = $wpdb->prefix . 'wc_orders_meta';
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$order_meta_table'" ) === $order_meta_table;
            
            if ( $table_exists ) {
                $orders_table = $wpdb->prefix . 'wc_orders';
                
                // Count orders that have courier data but are NOT in history table
                // Check by order_id directly (simpler and more reliable)
                $count = $wpdb->get_var(
                    "SELECT COUNT(DISTINCT om.order_id) 
                    FROM $order_meta_table om
                    INNER JOIN $orders_table o ON om.order_id = o.id
                    LEFT JOIN $history_table h ON h.type = 'wc_order' AND h.rel_id = om.order_id
                    WHERE om.meta_key = '_courier_data' 
                    AND om.meta_value != '' 
                    AND om.meta_value IS NOT NULL
                    AND o.status NOT IN ('trash', 'wc-trash', 'deleted')
                    AND o.type = 'shop_order'
                    AND h.id IS NULL"
                );
            }
        } else {
            // Legacy post meta - exclude already migrated orders
            // Check by order_id directly (simpler and more reliable)
            $count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT pm.post_id) 
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                LEFT JOIN $history_table h ON h.type = 'wc_order' AND h.rel_id = pm.post_id
                WHERE pm.meta_key = '_courier_data' 
                AND pm.meta_value != '' 
                AND pm.meta_value IS NOT NULL
                AND p.post_type = 'shop_order'
                AND p.post_status NOT IN ('trash', 'auto-draft')
                AND h.id IS NULL"
            );
        }
        
        return (int) $count;
    }

    /**
     * Migrate orders in batches
     */
    public static function migrate_orders_batch( $batch_size = 50, $offset = 0 ) {
        global $wpdb;
        
        require_once dirname( __FILE__ ) . '/class.CourierHistory.php';
        
        $migrated = 0;
        $errors = 0;
        $orders_processed = array();
        
        // Ensure history table exists
        CourierHistory::create_table();
        
        // Check HPOS
        $is_hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && 
                   \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        
        
        if ( $is_hpos ) {
            $order_meta_table = $wpdb->prefix . 'wc_orders_meta';
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$order_meta_table'" ) === $order_meta_table;
            
            if ( $table_exists ) {
                $orders_table = $wpdb->prefix . 'wc_orders';
                
                // Get orders with courier data - only active orders (not deleted/trashed)
                $query = $wpdb->prepare(
                    "SELECT DISTINCT om.order_id, om.meta_value as courier_data,
                    phone_meta.meta_value as billing_phone
                    FROM $order_meta_table om
                    INNER JOIN $orders_table o ON om.order_id = o.id
                    LEFT JOIN $order_meta_table phone_meta ON om.order_id = phone_meta.order_id AND phone_meta.meta_key = '_billing_phone'
                    WHERE om.meta_key = '_courier_data' 
                    AND om.meta_value != '' 
                    AND om.meta_value IS NOT NULL
                    AND o.status NOT IN ('trash', 'wc-trash', 'deleted')
                    AND o.type = 'shop_order'
                    ORDER BY om.order_id ASC
                    LIMIT %d OFFSET %d",
                    $batch_size,
                    $offset
                );
                
                
                $results = $wpdb->get_results( $query );
                
                if ( $wpdb->last_error ) {
                }
                
                
                // If no results found, we've reached the end - return early
                if ( empty( $results ) ) {
                    return array(
                        'migrated' => $migrated,
                        'errors' => $errors,
                        'orders_processed' => $orders_processed,
                    );
                }
                
                foreach ( $results as $row ) {
                    $order_id = $row->order_id;
                    $courier_data_raw = $row->courier_data;
                    $courier_data = maybe_unserialize( $courier_data_raw );
                    $phone = $row->billing_phone;
                    
                    
                    // Fallback: try to get from order object if meta didn't have it
                    if ( empty( $phone ) && function_exists( 'wc_get_order' ) ) {
                        $order = wc_get_order( $order_id );
                        if ( $order && $order->get_status() !== 'trash' ) {
                            $phone = $order->get_billing_phone();
                        }
                    }
                    
                    // Validate courier data
                    if ( empty( $courier_data ) ) {
                        if ( ! empty( $courier_data_raw ) ) {
                            // Try to decode as JSON if unserialize failed
                            $courier_data = json_decode( $courier_data_raw, true );
                        }
                    }
                    
                    if ( empty( $phone ) ) {
                        continue;
                    }
                    
                    if ( empty( $courier_data ) ) {
                        continue;
                    }
                    
                    // Clean phone number
                    $phone_cleaned = preg_replace( '/[^0-9]/', '', $phone );
                    if ( empty( $phone_cleaned ) ) {
                        continue;
                    }
                    $phone = $phone_cleaned;
                    
                    // Check if already migrated
                    $existing = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM " . CourierHistory::get_table_name() . " 
                            WHERE phone = %s AND type = 'wc_order' AND rel_id = %d",
                            $phone,
                            $order_id
                        )
                    );
                    
                    if ( $existing > 0 ) {
                        continue; // Already migrated
                    }
                    
                    // Store in history table
                    $result = CourierHistory::store_history( $phone, $courier_data, 'wc_order', $order_id );
                    
                    if ( $result ) {
                        $migrated++;
                        $orders_processed[] = $order_id;
                    } else {
                        $errors++;
                        if ( $wpdb->last_error ) {
                        }
                    }
                }
            }
        } else {
            // Legacy post meta - only active orders (not deleted/trashed)
            $query = $wpdb->prepare(
                "SELECT DISTINCT pm.post_id as order_id, pm.meta_value as courier_data,
                phone_pm.meta_value as billing_phone
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                LEFT JOIN {$wpdb->postmeta} phone_pm ON pm.post_id = phone_pm.post_id AND phone_pm.meta_key = '_billing_phone'
                WHERE pm.meta_key = '_courier_data' 
                AND pm.meta_value != '' 
                AND pm.meta_value IS NOT NULL
                AND p.post_type = 'shop_order'
                AND p.post_status NOT IN ('trash', 'auto-draft')
                ORDER BY pm.post_id ASC
                LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            );
            
            
            $results = $wpdb->get_results( $query );
            
            if ( $wpdb->last_error ) {
            }
            
            
            // If no results found, we've reached the end - return early
            if ( empty( $results ) ) {
                return array(
                    'migrated' => $migrated,
                    'errors' => $errors,
                    'orders_processed' => $orders_processed,
                );
            }
            
            foreach ( $results as $row ) {
                $order_id = $row->order_id;
                $phone = $row->billing_phone;
                $courier_data = maybe_unserialize( $row->courier_data );
                
                if ( empty( $phone ) || empty( $courier_data ) ) {
                    continue;
                }
                
                // Clean phone number
                $phone = preg_replace( '/[^0-9]/', '', $phone );
                if ( empty( $phone ) ) {
                    continue;
                }
                
                // Check if already migrated
                $existing = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM " . CourierHistory::get_table_name() . " 
                        WHERE phone = %s AND type = 'wc_order' AND rel_id = %d",
                        $phone,
                        $order_id
                    )
                );
                
                if ( $existing > 0 ) {
                    continue; // Already migrated
                }
                
                // Store in history table
                $result = CourierHistory::store_history( $phone, $courier_data, 'wc_order', $order_id );
                
                if ( $result ) {
                    $migrated++;
                    $orders_processed[] = $order_id;
                } else {
                    $errors++;
                }
            }
        }
        
        
        return array(
            'migrated' => $migrated,
            'errors' => $errors,
            'orders_processed' => $orders_processed,
        );
    }

    /**
     * Complete migration
     */
    public static function complete_migration() {
        update_option( 'bdc_migration_completed', true );
        update_option( 'bdc_migration_version', '3.0.2' );
        update_option( 'bdc_migration_completed_at', current_time( 'mysql' ) );
    }

    /**
     * Reset migration status (for testing)
     */
    public static function reset_migration() {
        delete_option( 'bdc_migration_completed' );
        delete_option( 'bdc_migration_version' );
        delete_option( 'bdc_migration_completed_at' );
    }
}

