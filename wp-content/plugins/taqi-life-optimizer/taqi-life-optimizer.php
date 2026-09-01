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
}
new TAQI_Life_Optimizer();
