<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Taqi_Whatsapp_Customer {
    public function __construct() {
        add_filter( 'woocommerce_billing_fields', array( $this, 'add_whatsapp_checkout_field' ), 10, 1 );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_whatsapp_order_meta' ), 10, 2 );
        add_action( 'woocommerce_edit_account_form', array( $this, 'add_whatsapp_account_fields' ) );
        add_action( 'woocommerce_save_account_details', array( $this, 'save_whatsapp_account_fields' ) );
    }

    public function add_whatsapp_checkout_field( $fields ) {
        $settings = Taqi_Whatsapp_Settings::get_settings();
        $required = ( $settings['require_whatsapp_reg'] === 'yes' );

        $fields['billing_whatsapp'] = array(
            'label'       => 'WhatsApp Number',
            'placeholder' => '+88017XXXXXXXX',
            'required'    => $required,
            'class'       => array( 'form-row-wide' ),
            'clear'       => true,
            'priority'    => 110,
        );
        return $fields;
    }

    public function save_whatsapp_order_meta( $order_id, $data ) {
        if ( isset( $_POST['billing_whatsapp'] ) && ! empty( $_POST['billing_whatsapp'] ) ) {
            $normalized = $this->normalize_number( sanitize_text_field( $_POST['billing_whatsapp'] ) );
            update_post_meta( $order_id, '_billing_whatsapp', $normalized );

            // Save to user if logged in
            $order = wc_get_order( $order_id );
            if ( $order && $order->get_user_id() ) {
                update_user_meta( $order->get_user_id(), 'taqi_whatsapp_number', $normalized );
            }
        }
    }

    public function add_whatsapp_account_fields() {
        $user_id = get_current_user_id();
        $whatsapp = get_user_meta( $user_id, 'taqi_whatsapp_number', true );
        $marketing = get_user_meta( $user_id, 'taqi_whatsapp_marketing_optin', true );
        $orders = get_user_meta( $user_id, 'taqi_whatsapp_orders_optin', true );
        ?>
        <fieldset>
            <legend>WhatsApp Preferences</legend>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="taqi_whatsapp_number">WhatsApp Number</label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="taqi_whatsapp_number" id="taqi_whatsapp_number" value="<?php echo esc_attr( $whatsapp ); ?>" />
            </p>
            <p class="form-row">
                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input type="checkbox" name="taqi_whatsapp_orders_optin" value="1" <?php checked( $orders, '1' ); ?> />
                    <span>Receive order updates on WhatsApp</span>
                </label>
            </p>
            <p class="form-row">
                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input type="checkbox" name="taqi_whatsapp_marketing_optin" value="1" <?php checked( $marketing, '1' ); ?> />
                    <span>I agree to receive offers and promotional messages on WhatsApp</span>
                </label>
            </p>
        </fieldset>
        <?php
    }

    public function save_whatsapp_account_fields( $user_id ) {
        if ( isset( $_POST['taqi_whatsapp_number'] ) ) {
            update_user_meta( $user_id, 'taqi_whatsapp_number', $this->normalize_number( sanitize_text_field( $_POST['taqi_whatsapp_number'] ) ) );
        }
        
        $orders_optin = isset( $_POST['taqi_whatsapp_orders_optin'] ) ? '1' : '0';
        update_user_meta( $user_id, 'taqi_whatsapp_orders_optin', $orders_optin );

        $marketing_optin = isset( $_POST['taqi_whatsapp_marketing_optin'] ) ? '1' : '0';
        $old_marketing = get_user_meta( $user_id, 'taqi_whatsapp_marketing_optin', true );
        
        if ( $marketing_optin !== $old_marketing ) {
            update_user_meta( $user_id, 'taqi_whatsapp_marketing_optin', $marketing_optin );
            if ( $marketing_optin === '1' ) {
                update_user_meta( $user_id, 'taqi_whatsapp_marketing_optin_timestamp', current_time('mysql') );
            }
        }
    }

    private function normalize_number( $number ) {
        $number = preg_replace( '/[^0-9]/', '', $number );
        // Basic BD normalization rule as requested
        if ( strlen( $number ) === 11 && substr( $number, 0, 2 ) === '01' ) {
            $number = '88' . $number;
        }
        return $number;
    }
}
