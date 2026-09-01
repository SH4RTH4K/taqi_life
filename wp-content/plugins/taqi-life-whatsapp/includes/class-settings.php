<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Taqi_Whatsapp_Settings {
    const OPTION_NAME = 'taqi_whatsapp_settings';
    
    public static function init() {
        // Init settings if needed
    }

    public static function get_settings() {
        $defaults = array(
            'provider' => 'meta',
            'business_account_id' => '',
            'phone_number_id' => '',
            'access_token' => '',
            'webhook_verify_token' => wp_generate_password(24, false),
            'api_version' => 'v19.0',
            'business_display_number' => '',
            'require_whatsapp_reg' => 'no',
            'use_billing_phone' => 'no',
            'owner_numbers' => array(),
            'customer_notifications' => array(
                'new_order' => 'yes',
                'processing' => 'yes',
                'completed' => 'yes',
                'cancelled' => 'yes'
            )
        );
        $saved = get_option( self::OPTION_NAME, array() );
        return wp_parse_args( $saved, $defaults );
    }

    public static function save_settings( $new_settings ) {
        $current = self::get_settings();
        
        // Preserve access token if empty string submitted
        if ( isset( $new_settings['access_token'] ) && empty( $new_settings['access_token'] ) ) {
            $new_settings['access_token'] = $current['access_token'];
        }

        $merged = wp_parse_args( $new_settings, $current );
        update_option( self::OPTION_NAME, $merged );
    }

    public static function is_configured() {
        $settings = self::get_settings();
        return ! empty( $settings['phone_number_id'] ) && ! empty( $settings['access_token'] );
    }
}
