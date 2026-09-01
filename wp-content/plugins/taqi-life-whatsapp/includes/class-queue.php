<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Taqi_Whatsapp_Queue {
    public function __construct() {
        add_action( 'taqi_whatsapp_process_queue', array( $this, 'process_queue' ) );
        
        // Setup simple wp_cron if not scheduled
        if ( ! wp_next_scheduled( 'taqi_whatsapp_process_queue' ) ) {
            wp_schedule_event( time(), 'hourly', 'taqi_whatsapp_process_queue' ); // Ideally 1-5 mins in prod
        }
    }

    public static function add( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'taqi_whatsapp_queue';
        
        // Duplicate protection logic would go here
        
        $wpdb->insert(
            $table,
            array(
                'campaign_id' => $data['campaign_id'] ?? 0,
                'order_id' => $data['order_id'] ?? 0,
                'user_id' => $data['user_id'] ?? 0,
                'recipient' => $data['recipient'],
                'message_type' => $data['message_type'],
                'template_name' => $data['template_name'],
                'payload_json' => $data['payload_json'],
                'status' => 'pending',
                'scheduled_at' => current_time('mysql')
            )
        );
        return $wpdb->insert_id;
    }

    public function process_queue() {
        // High level queue processing stub
        global $wpdb;
        $table = $wpdb->prefix . 'taqi_whatsapp_queue';
        
        $messages = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'pending' OR status = 'retrying' LIMIT 20" );
        
        foreach ( $messages as $msg ) {
            // 1. Check privacy/opt-out if marketing
            // 2. Format payload to Meta API standard
            // 3. Send using Taqi_Whatsapp_Api_Client
            // 4. Update status (sent, failed, retrying)
            // 5. Log to wp_taqi_whatsapp_logs
            
            // Mark processed for now in this skeleton
            $wpdb->update( $table, array( 'status' => 'processed', 'sent_at' => current_time('mysql') ), array( 'id' => $msg->id ) );
        }
    }
}
