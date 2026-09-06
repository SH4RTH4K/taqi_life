<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierOrderRatio
 * Handles the separate Order Ratio Checker admin page.
 */
class CourierOrderRatio {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_order_ratio_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_refresh_courier_data_standalone', array( $this, 'refresh_courier_data_standalone' ) );
    }

    /**
     * Add the Order Ratio Checker menu page.
     */
    public function add_order_ratio_page() {
        add_menu_page(
            __( 'Courier Order Ratio', 'bd-courier-order-ratio-checker' ),
            __( 'Courier Order Ratio', 'bd-courier-order-ratio-checker' ),
            'manage_options',
            'bd-courier-order-ratio',
            array( $this, 'render_order_ratio_page' ),
            'dashicons-chart-line',
            57
        );
    }

    /**
     * Enqueue React app assets.
     */
    public function enqueue_assets( $hook ) {
        // Only load on our custom page
        if ( $hook !== 'toplevel_page_bd-courier-order-ratio' ) {
            return;
        }

        // Get plugin root directory and main file path
        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';

        // Load standalone version (completely separate from order edit page)
        $standalone_js = $plugin_dir . '/assets/js/order-ratio-standalone.js';
        $standalone_css = $plugin_dir . '/assets/js/order-ratio-standalone.css';
        
        if ( file_exists( $standalone_js ) ) {
            $standalone_url = plugins_url( 'assets/js/order-ratio-standalone.js', $plugin_main_file );
            $standalone_url = add_query_arg( 'v', BD_COURIER_VERSION, $standalone_url );
            
            // Enqueue CSS if it exists
            if ( file_exists( $standalone_css ) ) {
                $standalone_css_url = plugins_url( 'assets/js/order-ratio-standalone.css', $plugin_main_file );
                $standalone_css_url = add_query_arg( 'v', BD_COURIER_VERSION, $standalone_css_url );
                
                wp_enqueue_style(
                    'bdcourier-order-ratio-standalone-css',
                    $standalone_css_url,
                    [],
                    BD_COURIER_VERSION
                );
            }
            
            wp_enqueue_script(
                'bdcourier-order-ratio-standalone',
                $standalone_url,
                [],
                BD_COURIER_VERSION,
                true
            );
            
            // Enqueue notifications
            $notifications_url = plugin_dir_url( __FILE__ ) . '../assets/js/admin-notifications.js';
            $notifications_url = add_query_arg( 'v', BD_COURIER_VERSION, $notifications_url );
            wp_enqueue_script(
                'bdcourier-notifications',
                $notifications_url,
                [],
                BD_COURIER_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script(
                'bdcourier-order-ratio-standalone',
                'bdcourierSearchAjax',
                array(
                    'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                    'search_nonce'  => wp_create_nonce( 'search_courier_data_nonce' ),
                    'refresh_nonce' => wp_create_nonce( 'refresh_courier_data_nonce' ),
                )
            );
        }
    }

    /**
     * Render the Order Ratio Checker page.
     */
    /**
     * Hide admin notices on order ratio page.
     */
    public function hide_admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        
        if ( $screen->id === 'toplevel_page_bd-courier-order-ratio' || ( isset( $_GET['page'] ) && $_GET['page'] === 'bd-courier-order-ratio' ) ) {
            ?>
            <style type="text/css">
                .notice,
                .notice-error,
                .notice-success,
                .notice-warning,
                .notice-info,
                .update-nag,
                .error,
                .updated {
                    display: none !important;
                }
            </style>
            <?php
        }
    }

    public function render_order_ratio_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Courier Order Ratio Checker', 'bd-courier-order-ratio-checker' ); ?></h1>
            <div id="bdcourier-order-ratio-standalone-root"></div>
        </div>
        <?php
    }

    /**
     * Handle AJAX request to refresh courier data (standalone version).
     */
    public function refresh_courier_data_standalone() {
        check_ajax_referer( 'refresh_courier_data_nonce', 'nonce' );

        if ( ! isset( $_POST['phone'] ) || empty( $_POST['phone'] ) ) {
            wp_send_json_error( array( 'message' => 'Phone number is required.' ) );
        }

        $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ) );
        
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        $courier_data = CourierAPI::fetch_order_ratio_from_api( $phone );

        if ( $courier_data === false ) {
            $error_message = CourierAPI::get_last_error();
            wp_send_json_error( array( 'message' => $error_message ? $error_message : 'Failed to fetch courier data.' ) );
        }

        wp_send_json_success( array( 'data' => $courier_data ) );
    }
}

