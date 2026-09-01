<?php
/**
 * Plugin Name: TAQI Life Optimizer
 * Description: Core performance tweaks for WooCommerce and WordPress to speed up customer browsing.
 * Version: 1.0.0
 * Author: TAQI LIFE
 */

defined( 'ABSPATH' ) || exit;

class TAQI_Life_Optimizer {

    public function __construct() {
        add_action( 'init', array( $this, 'disable_emojis' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'optimize_woocommerce_scripts' ), 99 );
        add_filter( 'xmlrpc_enabled', '__return_false' );
    }

    public function disable_emojis() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' ); 
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' ); 
    }

    public function optimize_woocommerce_scripts() {
        if ( function_exists( 'is_woocommerce' ) ) {
            if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
                wp_dequeue_script( 'wc-cart-fragments' );
            }
        }
    }

    // Step 2: Database Optimization
    public function add_admin_menu() {
        add_menu_page(
            'TAQI Optimizer',
            'TAQI Optimizer',
            'manage_options',
            'taqi-optimizer',
            array( $this, 'render_admin_page' ),
            'dashicons-performance',
            80
        );
    }

    public function handle_admin_actions() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['taqi_optimize_action'] ) ) {
            return;
        }
        
        check_admin_referer( 'taqi_optimize_action_nonce' );
        
        global $wpdb;
        $action = $_POST['taqi_optimize_action'];
        
        if ( $action === 'clear_transients' ) {
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
            add_settings_error( 'taqi_messages', 'taqi_message', 'All transients cleared successfully.', 'updated' );
        } elseif ( $action === 'clear_orphaned_meta' ) {
            $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL" );
            add_settings_error( 'taqi_messages', 'taqi_message', 'Orphaned postmeta cleared successfully.', 'updated' );
        }
    }

    public function render_admin_page() {
        global $wpdb;
        $transient_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
        $orphaned_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL" );
        
        settings_errors( 'taqi_messages' );
        ?>
        <div class="wrap">
            <h1>TAQI Life Optimizer - Database Cleanup</h1>
            <p>Use these tools to clean up database bloat left behind by dropshipping imports.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'taqi_optimize_action_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Expired & Cached Transients</th>
                        <td>
                            <p>Currently found: <strong><?php echo esc_html( $transient_count ); ?></strong></p>
                            <button type="submit" name="taqi_optimize_action" value="clear_transients" class="button button-primary">Clear All Transients</button>
                        </td>
                    </tr>
                    <tr>
                        <th>Orphaned Post Meta</th>
                        <td>
                            <p>Currently found: <strong><?php echo esc_html( $orphaned_count ); ?></strong></p>
                            <button type="submit" name="taqi_optimize_action" value="clear_orphaned_meta" class="button button-primary">Clear Orphaned Meta</button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <?php
    }
}

$optimizer = new TAQI_Life_Optimizer();
// Hook admin menu and actions outside to ensure they run at the right time
add_action( 'admin_menu', array( $optimizer, 'add_admin_menu' ) );
add_action( 'admin_init', array( $optimizer, 'handle_admin_actions' ) );

