<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Taqi_Whatsapp_Admin_Menu {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_init', array( $this, 'handle_saves' ) );
    }

    public function register_menus() {
        if ( ! current_user_can( 'manage_taqi_whatsapp' ) && ! current_user_can( 'manage_options' ) ) return;

        add_menu_page(
            'TAQI WhatsApp',
            'TAQI WhatsApp',
            'manage_options',
            'taqi-whatsapp',
            array( $this, 'render_dashboard' ),
            'dashicons-whatsapp',
            55
        );

        add_submenu_page( 'taqi-whatsapp', 'Dashboard', 'Dashboard', 'manage_options', 'taqi-whatsapp', array( $this, 'render_dashboard' ) );
        add_submenu_page( 'taqi-whatsapp', 'Notifications', 'Notifications', 'manage_options', 'taqi-whatsapp-notifications', array( $this, 'render_placeholder' ) );
        add_submenu_page( 'taqi-whatsapp', 'Campaigns', 'Campaigns', 'manage_options', 'taqi-whatsapp-campaigns', array( $this, 'render_placeholder' ) );
        add_submenu_page( 'taqi-whatsapp', 'Customers', 'Customers', 'manage_options', 'taqi-whatsapp-customers', array( $this, 'render_placeholder' ) );
        add_submenu_page( 'taqi-whatsapp', 'Templates', 'Message Templates', 'manage_options', 'taqi-whatsapp-templates', array( $this, 'render_placeholder' ) );
        add_submenu_page( 'taqi-whatsapp', 'Queue', 'Queue', 'manage_options', 'taqi-whatsapp-queue', array( $this, 'render_placeholder' ) );
        add_submenu_page( 'taqi-whatsapp', 'Logs', 'Message Logs', 'manage_options', 'taqi-whatsapp-logs', array( $this, 'render_placeholder' ) );
        add_submenu_page( 'taqi-whatsapp', 'Settings', 'Settings', 'manage_options', 'taqi-whatsapp-settings', array( $this, 'render_settings' ) );
    }

    public function handle_saves() {
        if ( isset( $_POST['taqi_whatsapp_save_settings'] ) && check_admin_referer( 'taqi_whatsapp_save' ) ) {
            $settings = array(
                'business_account_id' => sanitize_text_field( $_POST['business_account_id'] ?? '' ),
                'phone_number_id' => sanitize_text_field( $_POST['phone_number_id'] ?? '' ),
                'access_token' => sanitize_text_field( $_POST['access_token'] ?? '' ),
                'require_whatsapp_reg' => sanitize_text_field( $_POST['require_whatsapp_reg'] ?? 'no' )
            );
            Taqi_Whatsapp_Settings::save_settings( $settings );
            add_settings_error( 'taqi_whatsapp', 'settings_saved', 'Settings saved successfully.', 'updated' );
        }
    }

    public function render_dashboard() {
        echo '<div class="wrap"><h1>TAQI WhatsApp Dashboard</h1><p>Welcome to the WhatsApp Management System.</p></div>';
    }

    public function render_settings() {
        $settings = Taqi_Whatsapp_Settings::get_settings();
        settings_errors( 'taqi_whatsapp' );
        ?>
        <div class="wrap">
            <h1>WhatsApp API Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'taqi_whatsapp_save' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Provider</th>
                        <td><strong>Meta WhatsApp Cloud API</strong></td>
                    </tr>
                    <tr>
                        <th>WhatsApp Business Account ID</th>
                        <td><input type="text" name="business_account_id" class="regular-text" value="<?php echo esc_attr( $settings['business_account_id'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Phone Number ID</th>
                        <td><input type="text" name="phone_number_id" class="regular-text" value="<?php echo esc_attr( $settings['phone_number_id'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Access Token</th>
                        <td>
                            <input type="password" name="access_token" class="regular-text" placeholder="************************">
                            <p class="description">Leave blank to keep the currently saved token.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Require WhatsApp on Registration</th>
                        <td>
                            <select name="require_whatsapp_reg">
                                <option value="no" <?php selected( $settings['require_whatsapp_reg'], 'no' ); ?>>OFF</option>
                                <option value="yes" <?php selected( $settings['require_whatsapp_reg'], 'yes' ); ?>>ON</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="taqi_whatsapp_save_settings" class="button button-primary">Save Settings</button>
                    <button type="button" class="button button-secondary">Test Connection</button>
                </p>
            </form>
        </div>
        <?php
    }

    public function render_placeholder() {
        echo '<div class="wrap"><h1>Coming Soon</h1><p>This module is part of the TAQI WhatsApp infrastructure.</p></div>';
    }
}
