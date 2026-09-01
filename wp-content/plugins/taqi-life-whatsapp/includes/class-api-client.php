<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Taqi_Whatsapp_Api_Client {
    public static function send_message( $to, $template_name, $components = array() ) {
        $settings = Taqi_Whatsapp_Settings::get_settings();
        
        if ( empty( $settings['access_token'] ) || empty( $settings['phone_number_id'] ) ) {
            return new WP_Error( 'missing_config', 'WhatsApp API is not configured completely.' );
        }

        $url = 'https://graph.facebook.com/' . $settings['api_version'] . '/' . $settings['phone_number_id'] . '/messages';
        
        $body = array(
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => array(
                'name' => $template_name,
                'language' => array( 'code' => 'en' ),
                'components' => $components
            )
        );

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['access_token'],
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode( $body ),
            'timeout' => 15
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        if ( $code >= 200 && $code < 300 ) {
            return $decoded; // Success
        } else {
            return new WP_Error( 'api_error', $decoded['error']['message'] ?? 'Unknown API Error', $decoded );
        }
    }
}
