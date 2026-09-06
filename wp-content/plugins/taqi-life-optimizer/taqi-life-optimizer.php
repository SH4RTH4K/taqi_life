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
        add_action( 'wp_enqueue_scripts', array( $this, 'ensure_astra_frontend_styles' ), 9999 );
        add_action( 'litespeed_init', array( $this, 'disable_litespeed_css_optimizations' ), 1 );
        add_filter( 'litespeed_can_optm', array( $this, 'bypass_litespeed_optimization' ), 999 );
        add_action( 'admin_init', array( $this, 'maybe_purge_litespeed_css_cache' ), 1 );
        add_filter( 'xmlrpc_enabled', '__return_false' );

    }

    /**
     * Keep LiteSpeed from combining or reducing frontend CSS.
     * Astra's theme and WooCommerce styles must remain separate and ordered.
     */
    public function disable_litespeed_css_optimizations() {
        do_action( 'litespeed_conf_force', 'optm-css_comb', false );
        do_action( 'litespeed_conf_force', 'optm-ucss', false );
        do_action( 'litespeed_conf_force', 'optm-ucss_inline', false );
        do_action( 'litespeed_conf_force', 'optm-css_async', false );
    }

    /**
     * Remove stale LiteSpeed combined/UCSS assets once after this fix is deployed.
     */
    public function maybe_purge_litespeed_css_cache() {
        if ( ! current_user_can( 'manage_options' ) || '5' === get_option( 'taqi_life_litespeed_css_reset', '' ) || false === has_action( 'litespeed_purge_all' ) ) {
            return;
        }

        do_action( 'litespeed_purge_all', 'TAQI Life CSS compatibility reset' );
        update_option( 'taqi_life_litespeed_css_reset', '5', false );
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

    /**
     * Do not let LiteSpeed page optimization rewrite Astra's frontend assets.
     * The global header is shared by every storefront page, so a damaged CSS
     * optimization cache breaks Cart, Checkout, Account, and the homepage.
     * Page caching remains enabled; only the HTML/CSS optimizer is bypassed.
     *
     * @param bool $can_optimize LiteSpeed's current optimization decision.
     * @return bool
     */
    public function bypass_litespeed_optimization( $can_optimize ) {
        if ( is_admin() ) {
            return $can_optimize;
        }

        return false;
    }

    /**
     * Load an independent, cache-busted copy of Astra's base stylesheet.
     * This remains available if an optimizer removes or serves a stale version
     * of the normal astra-theme-css handle.
     */
    public function ensure_astra_frontend_styles() {
        if ( 'astra' !== get_template() ) {
            return;
        }

        $asset = '';
        $styles = wp_styles();
        if ( isset( $styles->registered['astra-theme-css'] ) ) {
            $source = (string) $styles->registered['astra-theme-css']->src;
            $asset  = basename( (string) parse_url( $source, PHP_URL_PATH ) );
        }

        if ( ! preg_match( '/^[a-z0-9-]+(?:\\.min)?\\.css$/i', $asset ) ) {
            $asset = ( class_exists( 'Astra_Builder_Helper' ) && Astra_Builder_Helper::apply_flex_based_css() ) ? 'style-flex.min.css' : 'style.min.css';
        }

        $path = trailingslashit( get_template_directory() ) . 'assets/css/minified/' . $asset;
        if ( ! is_readable( $path ) ) {
            return;
        }

        wp_enqueue_style(
            'taqi-astra-frontend-recovery',
            trailingslashit( get_template_directory_uri() ) . 'assets/css/minified/' . $asset,
            wp_style_is( 'astra-theme-css', 'enqueued' ) ? array( 'astra-theme-css' ) : array(),
            'taqi-astra-recovery-5-' . (string) filemtime( $path ),
            'all'
        );

        wp_add_inline_style( 'taqi-astra-frontend-recovery', $this->astra_header_layout_recovery_css() );
    }

    /**
     * Keep a long primary menu inside Astra's header even if generated Astra
     * builder CSS becomes unavailable. This is deliberately structural: it
     * restores header flow without changing the configured colours or fonts.
     *
     * @return string
     */
    private function astra_header_layout_recovery_css() {
        return '
            #masthead,
            #masthead .ast-main-header-wrap,
            #masthead .ast-primary-header-bar {
                position: relative !important;
                z-index: 20;
            }

            #content,
            .site-content,
            #primary {
                clear: both;
            }

            @media (min-width: 922px) {
                #masthead #ast-mobile-header {
                    display: none !important;
                }

                #masthead #ast-desktop-header,
                #masthead .ast-main-header-wrap,
                #masthead .ast-primary-header-bar,
                #masthead .site-primary-header-wrap {
                    display: block !important;
                    width: 100%;
                    height: auto !important;
                    min-height: 0 !important;
                }

                #masthead .ast-builder-grid-row {
                    display: flex !important;
                    align-items: center;
                    justify-content: space-between;
                    gap: 18px;
                    width: min(1500px, calc(100% - 40px));
                    min-height: 110px;
                    margin: 0 auto;
                }

                #masthead .site-header-primary-section-left {
                    flex: 0 0 auto !important;
                }

                #masthead .site-header-primary-section-right {
                    display: block !important;
                    flex: 1 1 auto !important;
                    min-width: 0;
                    margin-left: auto;
                }

                #masthead .ast-builder-menu,
                #masthead .ast-main-header-bar-alignment,
                #masthead .main-header-bar-navigation,
                #masthead #primary-site-navigation-desktop,
                #masthead #primary-site-navigation-desktop .main-navigation {
                    display: block !important;
                    width: 100%;
                    min-width: 0;
                }

                #masthead #ast-hf-menu-1 {
                    display: flex !important;
                    flex-wrap: wrap !important;
                    align-items: center;
                    justify-content: flex-end;
                    column-gap: 22px;
                    row-gap: 0;
                    width: 100%;
                    margin: 0;
                    padding: 10px 0;
                }

                #masthead #ast-hf-menu-1 > li {
                    display: flex !important;
                    flex: 0 0 auto;
                    align-items: center;
                    min-height: 42px;
                    margin: 0 !important;
                    line-height: 1.3 !important;
                }

                #masthead #ast-hf-menu-1 > li > a {
                    display: flex !important;
                    align-items: center;
                    min-height: 42px;
                    padding: 0 !important;
                    line-height: 1.3 !important;
                    white-space: nowrap;
                }
            }
        ';
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
            $time_now = time();
            // Delete expired transients
            $wpdb->query( "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b WHERE a.option_name LIKE '_transient_%' AND a.option_name NOT LIKE '_transient_timeout_%' AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) ) AND b.option_value < {$time_now}" );
            $wpdb->query( "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b WHERE a.option_name LIKE '_site_transient_%' AND a.option_name NOT LIKE '_site_transient_timeout_%' AND b.option_name = CONCAT( '_site_transient_timeout_', SUBSTRING( a.option_name, 17 ) ) AND b.option_value < {$time_now}" );
            // Delete orphaned timeouts
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < {$time_now}" );
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_timeout_%' AND option_value < {$time_now}" );
            
            add_settings_error( 'taqi_messages', 'taqi_message', 'Expired transients cleared successfully.', 'updated' );
        } elseif ( $action === 'clear_orphaned_meta' ) {
            $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL" );
            add_settings_error( 'taqi_messages', 'taqi_message', 'Orphaned postmeta cleared successfully.', 'updated' );
        }
    }

    public function render_admin_page() {
        global $wpdb;
        $time_now = time();
        $expired_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < {$time_now}" );
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
                        <th>Expired Transients</th>
                        <td>
                            <p>Currently found: <strong><?php echo esc_html( $expired_count ); ?></strong></p>
                            <button type="submit" name="taqi_optimize_action" value="clear_transients" class="button button-primary">Clear Expired Transients</button>
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
