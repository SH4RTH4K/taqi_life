<?php
/**
 * Plugin Name: BD Courier Order Ratio Checker 
 * Plugin URI: https://rasedulhaque.com/bd-courier-order-ratio-checker
 * Description: A plugin to show customer order ratio from BD Courier with settings and search functionality. Includes fraud prevention, duplicate order detection, incomplete order tracking, and VPN/proxy detection.
 * Version: 3.1.1
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Author: Rasedul Haque
 * Author URI: https://rasedulhaque.com
 * Text Domain: bd-courier-order-ratio-checker
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 * Update URI: false
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin directory path and version.
define( 'BD_COURIER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BD_COURIER_VERSION', '3.1.1' );

/**
 * Get plugin version.
 *
 * @return string Plugin version.
 */
function bd_courier_get_version() {
    return BD_COURIER_VERSION;
}

/**
 * Load plugin text domain for translations.
 */
function bd_courier_load_textdomain() {
    load_plugin_textdomain(
        'bd-courier-order-ratio-checker',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}
add_action( 'init', 'bd_courier_load_textdomain' );

// Include necessary class files.
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierAPI.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierAdmin.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierSearch.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierSettings.php';
// require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierOrderRatio.php'; // Removed: Blank admin page
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierAutoCheck.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierRejectedOrders.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierRejectedOrderLogs.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierBlockedEntities.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierIncompleteOrders.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierOrderCollection.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierIncompleteOrdersPage.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierHistory.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierMigration.php';
require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierHistory.php';

/**
 * Initialize the plugin by instantiating all classes.
 */
function initialize_courier_plugin() {
    new CourierAdmin();
    new CourierSearch();
    new CourierSettings();
    // new CourierOrderRatio(); // Removed: Blank admin page
    new CourierAutoCheck();
    new CourierRejectedOrders();
    new CourierRejectedOrderLogs();
    new CourierBlockedEntities();
    new CourierIncompleteOrders();
    new CourierOrderCollection();
    new CourierIncompleteOrdersPage();
}
add_action( 'plugins_loaded', 'initialize_courier_plugin' );

/**
 * Create database tables on plugin activation.
 */
register_activation_hook( __FILE__, 'bd_courier_create_tables' );
function bd_courier_create_tables() {
    require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierRejectedOrders.php';
    require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierRejectedOrderLogs.php';
    require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierIncompleteOrders.php';
    require_once BD_COURIER_PLUGIN_DIR . 'lib/class.CourierHistory.php';

    $rejected_orders = new CourierRejectedOrders();
    $rejected_orders->create_table();

    $rejected_logs = new CourierRejectedOrderLogs();
    $rejected_logs->create_table();

    $incomplete_orders = new CourierIncompleteOrders();
    $incomplete_orders->create_table();

    CourierHistory::create_table();
}
