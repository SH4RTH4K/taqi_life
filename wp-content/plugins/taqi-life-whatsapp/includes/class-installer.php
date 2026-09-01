<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Taqi_Whatsapp_Installer {
    public static function activate() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $charset_collate = $wpdb->get_charset_collate();

        $queue_table = $wpdb->prefix . 'taqi_whatsapp_queue';
        $sql_queue = "CREATE TABLE $queue_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned DEFAULT 0,
            order_id bigint(20) unsigned DEFAULT 0,
            user_id bigint(20) unsigned DEFAULT 0,
            recipient varchar(20) NOT NULL,
            message_type varchar(50) NOT NULL,
            template_name varchar(100) DEFAULT '',
            payload_json longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempt_count int(11) NOT NULL DEFAULT 0,
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            error_code varchar(50) DEFAULT '',
            error_message text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";

        $logs_table = $wpdb->prefix . 'taqi_whatsapp_logs';
        $sql_logs = "CREATE TABLE $logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date datetime DEFAULT CURRENT_TIMESTAMP,
            recipient varchar(20) NOT NULL,
            customer_id bigint(20) unsigned DEFAULT 0,
            order_id bigint(20) unsigned DEFAULT 0,
            campaign_id bigint(20) unsigned DEFAULT 0,
            message_type varchar(50) NOT NULL,
            template_name varchar(100) DEFAULT '',
            status varchar(20) NOT NULL,
            api_message_id varchar(100) DEFAULT '',
            error text DEFAULT '',
            PRIMARY KEY  (id),
            KEY status (status),
            KEY recipient (recipient)
        ) $charset_collate;";

        dbDelta( $sql_queue );
        dbDelta( $sql_logs );

        // Add custom capabilities to admin
        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->add_cap( 'manage_taqi_whatsapp' );
            $role->add_cap( 'send_taqi_whatsapp_campaigns' );
        }
    }
}
