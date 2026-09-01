<?php
/**
 * Plugin Name: TAQI LIFE WhatsApp
 * Description: Standalone WhatsApp Notification & Campaign System for TAQI LIFE. Integrates with WooCommerce using Meta Cloud API.
 * Version: 1.0.0
 * Author: TAQI LIFE
 * Text Domain: taqi-life-whatsapp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Constants
define( 'TAQI_WHATSAPP_VERSION', '1.0.0' );
define( 'TAQI_WHATSAPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAQI_WHATSAPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// HPOS Compatibility
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Autoloader/Requires
$includes = array(
    'includes/class-installer.php',
    'includes/class-settings.php',
    'includes/class-api-client.php',
    'includes/class-queue.php',
    'includes/class-customer-whatsapp.php',
    'includes/class-order-notifications.php',
    'includes/admin/class-admin-menu.php'
);

foreach ( $includes as $file ) {
    if ( file_exists( TAQI_WHATSAPP_PLUGIN_DIR . $file ) ) {
        require_once TAQI_WHATSAPP_PLUGIN_DIR . $file;
    }
}

// Activation Hook
register_activation_hook( __FILE__, array( 'Taqi_Whatsapp_Installer', 'activate' ) );

// Initialize Plugin
function taqi_whatsapp_init() {
    // Check WooCommerce dependency gracefully
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'taqi_whatsapp_missing_wc_notice' );
        return;
    }

    if ( class_exists( 'Taqi_Whatsapp_Settings' ) ) {
        Taqi_Whatsapp_Settings::init();
    }
    if ( class_exists( 'Taqi_Whatsapp_Admin_Menu' ) ) {
        new Taqi_Whatsapp_Admin_Menu();
    }
    if ( class_exists( 'Taqi_Whatsapp_Customer' ) ) {
        new Taqi_Whatsapp_Customer();
    }
    if ( class_exists( 'Taqi_Whatsapp_Order_Notifications' ) ) {
        new Taqi_Whatsapp_Order_Notifications();
    }
    if ( class_exists( 'Taqi_Whatsapp_Queue' ) ) {
        new Taqi_Whatsapp_Queue();
    }
}
add_action( 'plugins_loaded', 'taqi_whatsapp_init' );

function taqi_whatsapp_missing_wc_notice() {
    echo '<div class="error"><p><strong>TAQI LIFE WhatsApp</strong> requires WooCommerce to be installed and active for order/customer integration.</p></div>';
}
