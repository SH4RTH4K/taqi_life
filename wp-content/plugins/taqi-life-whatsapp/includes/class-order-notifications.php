<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Taqi_Whatsapp_Order_Notifications {
    public function __construct() {
        add_action( 'woocommerce_order_status_changed', array( $this, 'trigger_order_notifications' ), 10, 4 );
    }

    public function trigger_order_notifications( $order_id, $old_status, $new_status, $order ) {
        if ( ! Taqi_Whatsapp_Settings::is_configured() ) return;
        
        $settings = Taqi_Whatsapp_Settings::get_settings();
        
        // 1. Notify Customer
        if ( isset( $settings['customer_notifications'][$new_status] ) && $settings['customer_notifications'][$new_status] === 'yes' ) {
            $this->queue_customer_message( $order, $new_status );
        }

        // 2. Notify Owner (Implementation stubbed for architecture)
        // $this->queue_owner_message( $order, $new_status );
    }

    private function queue_customer_message( $order, $status ) {
        $whatsapp_number = $order->get_meta( '_billing_whatsapp' );
        if ( empty( $whatsapp_number ) ) {
            // Fallback to billing phone if setting allows
            $settings = Taqi_Whatsapp_Settings::get_settings();
            if ( $settings['use_billing_phone'] === 'yes' ) {
                $whatsapp_number = $order->get_billing_phone();
            }
        }

        if ( empty( $whatsapp_number ) ) return; // Skip if no number
        
        // Check consent if they are a registered user
        if ( $order->get_user_id() ) {
            $optin = get_user_meta( $order->get_user_id(), 'taqi_whatsapp_orders_optin', true );
            // If they explicitly opted out, skip. (If empty, we assume transactional is ok unless restricted)
            if ( $optin === '0' ) return;
        }

        $payload = array(
            'order_id' => $order->get_id(),
            'status' => $status,
            'total' => $order->get_total()
        );

        Taqi_Whatsapp_Queue::add( array(
            'order_id' => $order->get_id(),
            'user_id' => $order->get_user_id(),
            'recipient' => $whatsapp_number,
            'message_type' => 'transactional',
            'template_name' => 'order_' . $status,
            'payload_json' => wp_json_encode( $payload )
        ) );
    }
}
