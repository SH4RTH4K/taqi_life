<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Taqi_Whatsapp_Api_Client {
    private $settings;

    public function __construct() {
        $this->settings = Taqi_Whatsapp_Settings::get_settings();
    }

    public static function send_message( $to, $message_data ) {
        $instance = new self();
        return $instance->process_send_message( $to, $message_data );
    }

    public function process_send_message( $to, $message_data ) {
        if ( ! $this->settings ) {
            return new WP_Error( 'whatsapp_config', 'Settings not loaded.' );
        }

        $provider = $this->settings['provider'] ?? 'meta';

        if ( $provider === 'gateway' ) {
            $gateway_url = rtrim( $this->settings['gateway_url'] ?? '', '/' );
            if ( empty( $gateway_url ) ) {
                return new WP_Error( 'whatsapp_config', 'Gateway URL is not configured.' );
            }

            $text = '';
            if ( isset( $message_data['text']['body'] ) ) {
                $text = $message_data['text']['body'];
            } elseif ( isset( $message_data['template']['name'] ) ) {
                $text = "Template Message Triggered: " . $message_data['template']['name'];
            }

            $payload = array(
                'phone' => $to,
                'message' => $text
            );

            $response = wp_remote_post( $gateway_url . '/send', array(
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 15,
            ) );

        } else {
            if ( empty( $this->settings['phone_number_id'] ) || empty( $this->settings['access_token'] ) ) {
                return new WP_Error( 'whatsapp_config', 'Meta Phone Number ID or Access Token is missing.' );
            }

            $endpoint = "https://graph.facebook.com/{$this->settings['api_version']}/{$this->settings['phone_number_id']}/messages";

            $payload = array_merge( array(
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
            ), $message_data );

            $response = wp_remote_post( $endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->settings['access_token'],
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 15,
            ) );
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code >= 400 ) {
            return new WP_Error( 'whatsapp_api_error', 'API returned HTTP ' . $status_code, $data );
        }

        return $data;
    }
}
