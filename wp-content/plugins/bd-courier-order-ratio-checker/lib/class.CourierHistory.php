<?php
/**
 * Courier History Management
 * 
 * Stores all courier ratio checks in a common table
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CourierHistory {
    
    /**
     * Get table name
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'bd_courier_history';
    }
    
    /**
     * Create the table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            data longtext NOT NULL,
            type varchar(20) NOT NULL COMMENT 'manual, wc_order, incomplete_order',
            rel_id bigint(20) UNSIGNED NULL COMMENT 'wc_order_id or incomplete_order_id',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY phone (phone),
            KEY type (type),
            KEY rel_id (rel_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Store courier check history
     * 
     * @param string $phone Phone number
     * @param array $data Courier data
     * @param string $type Type: 'manual', 'wc_order', 'incomplete_order'
     * @param int|null $rel_id Related ID (order_id or incomplete_order_id)
     * @return int|false Insert ID on success, false on failure
     */
    public static function store_history( $phone, $data, $type = 'manual', $rel_id = null ) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Check if table exists first
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        if ( ! $table_exists ) {
            // Try to create table
            self::create_table();
            // Check again
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
            if ( ! $table_exists ) {
                return false;
            }
        }
        
        try {
            // Check if record exists for this phone + type + rel_id combination
            $existing = null;
            if ( $rel_id ) {
                $existing = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE phone = %s AND type = %s AND rel_id = %d ORDER BY updated_at DESC LIMIT 1",
                    $phone,
                    $type,
                    $rel_id
                ) );
            }
            
            $data_json = json_encode( $data );
            $now = current_time( 'mysql' );
            
            if ( $existing ) {
                // Update existing record
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'data' => $data_json,
                        'updated_at' => $now,
                    ),
                    array( 'id' => $existing->id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
                
                return $result !== false ? $existing->id : false;
            } else {
                // Insert new record
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'phone' => $phone,
                        'data' => $data_json,
                        'type' => $type,
                        'rel_id' => $rel_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ),
                    array( '%s', '%s', '%s', '%d', '%s', '%s' )
                );
                
                return $result ? $wpdb->insert_id : false;
            }
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    /**
     * Get latest history for a phone number
     * 
     * @param string $phone Phone number
     * @param string|null $type Optional type filter
     * @return object|null History record or null
     */
    public static function get_latest_history( $phone, $type = null ) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Check if table exists first
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        if ( ! $table_exists ) {
            return null;
        }
        
        try {
            if ( $type ) {
                $result = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE phone = %s AND type = %s ORDER BY updated_at DESC LIMIT 1",
                    $phone,
                    $type
                ) );
            } else {
                $result = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE phone = %s ORDER BY updated_at DESC LIMIT 1",
                    $phone
                ) );
            }
            
            if ( $result && $result->data ) {
                $result->data = json_decode( $result->data, true );
            }
            
            return $result;
        } catch ( Exception $e ) {
            return null;
        }
    }
    
    /**
     * Get history by related ID
     * 
     * @param string $type Type: 'wc_order' or 'incomplete_order'
     * @param int $rel_id Related ID
     * @return object|null History record or null
     */
    public static function get_history_by_rel( $type, $rel_id ) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Check if table exists first
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        if ( ! $table_exists ) {
            return null;
        }
        
        try {
            $result = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table_name WHERE type = %s AND rel_id = %d ORDER BY updated_at DESC LIMIT 1",
                $type,
                $rel_id
            ) );
            
            if ( $result && $result->data ) {
                $result->data = json_decode( $result->data, true );
            }
            
            return $result;
        } catch ( Exception $e ) {
            return null;
        }
    }
    
    /**
     * Get all history for a phone number
     * 
     * @param string $phone Phone number
     * @param int $limit Limit results
     * @return array History records
     */
    public static function get_all_history( $phone, $limit = 10 ) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE phone = %s ORDER BY updated_at DESC LIMIT %d",
            $phone,
            $limit
        ) );
        
        foreach ( $results as $result ) {
            if ( $result->data ) {
                $result->data = json_decode( $result->data, true );
            }
        }
        
        return $results;
    }
}

