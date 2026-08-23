<?php
/**
 * Plugin Name: TAQI LIFE Dropshipping
 * Description: Standalone Mohasagor reseller dropshipping catalog and product manager.
 * Version: 2.0.0
 * Author: TAQI LIFE
 */

defined( 'ABSPATH' ) || exit;

/** Lightweight WordPress-native product record used by this standalone plugin. */
final class TAQI_Life_Product {
    private $id = 0;
    private $type = 'simple';
    private $data = array();
    private $meta = array();

    public function __construct( $type = 'simple', $id = 0 ) {
        $this->type = $type;
        $this->id   = absint( $id );
        if ( $this->id ) {
            $post = get_post( $this->id );
            if ( $post ) {
                $this->data = array( 'name' => $post->post_title, 'description' => $post->post_content, 'status' => $post->post_status, 'menu_order' => $post->menu_order );
                $this->type = 'taqi_product_variation' === $post->post_type ? 'variation' : ( 'variable' === get_post_meta( $this->id, '_taqi_product_type', true ) ? 'variable' : 'simple' );
            }
        }
    }
    public function get_id() { return $this->id; }
    public function get_name() { return isset( $this->data['name'] ) ? $this->data['name'] : ''; }
    public function get_children() { return array_map( 'absint', get_posts( array( 'post_type' => 'taqi_product_variation', 'post_status' => 'any', 'post_parent' => $this->id, 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) ); }
    public function is_type( $type ) { return $this->type === $type; }
    public function get_meta( $key, $single = true ) { return get_post_meta( $this->id, $key, $single ); }
    public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
    public function delete_meta_data( $key ) { $this->meta[ $key ] = null; }
    public function set_name( $value ) { $this->data['name'] = sanitize_text_field( $value ); }
    public function set_description( $value ) { $this->data['description'] = $value; }
    public function set_status( $value ) { $this->data['status'] = $value; }
    public function set_regular_price( $value ) { $this->update_meta_data( '_taqi_regular_price', $value ); }
    public function set_sale_price( $value ) { $this->update_meta_data( '_taqi_sale_price', $value ); }
    public function set_sku( $value ) { $this->update_meta_data( '_taqi_sku', sanitize_text_field( $value ) ); }
    public function set_manage_stock( $value ) { $this->update_meta_data( '_taqi_manage_stock', $value ? 'yes' : 'no' ); }
    public function set_stock_quantity( $value ) { $this->update_meta_data( '_taqi_stock_quantity', absint( $value ) ); }
    public function set_stock_status( $value ) { $this->update_meta_data( '_taqi_stock_status', sanitize_key( $value ) ); }
    public function set_category_ids( $ids ) { $this->data['category_ids'] = array_map( 'absint', (array) $ids ); }
    public function set_attributes( $attributes ) { $this->update_meta_data( '_taqi_attributes', wp_json_encode( $attributes ) ); }
    public function set_parent_id( $id ) { $this->data['parent_id'] = absint( $id ); }
    public function set_menu_order( $order ) { $this->data['menu_order'] = absint( $order ); }
    public function get_image_id() { return absint( get_post_thumbnail_id( $this->id ) ); }
    public function set_image_id( $id ) { $this->data['image_id'] = absint( $id ); }
    public function get_gallery_image_ids() { return array_map( 'absint', (array) get_post_meta( $this->id, '_taqi_gallery_image_ids', true ) ); }
    public function set_gallery_image_ids( $ids ) { $this->data['gallery_ids'] = array_map( 'absint', (array) $ids ); }
    public function save() {
        $post_type = 'variation' === $this->type ? 'taqi_product_variation' : 'taqi_product';
        $post = array( 'post_type' => $post_type, 'post_title' => isset( $this->data['name'] ) ? $this->data['name'] : 'Imported product', 'post_content' => isset( $this->data['description'] ) ? $this->data['description'] : '', 'post_status' => isset( $this->data['status'] ) ? $this->data['status'] : 'draft', 'post_parent' => isset( $this->data['parent_id'] ) ? $this->data['parent_id'] : 0, 'menu_order' => isset( $this->data['menu_order'] ) ? $this->data['menu_order'] : 0 );
        $post['ID'] = $this->id;
        $this->id = wp_insert_post( $post, true );
        if ( is_wp_error( $this->id ) ) { return 0; }
        update_post_meta( $this->id, '_taqi_product_type', $this->type );
        foreach ( $this->meta as $key => $value ) { null === $value ? delete_post_meta( $this->id, $key ) : update_post_meta( $this->id, $key, $value ); }
        if ( isset( $this->data['image_id'] ) ) { set_post_thumbnail( $this->id, $this->data['image_id'] ); }
        if ( isset( $this->data['gallery_ids'] ) ) { update_post_meta( $this->id, '_taqi_gallery_image_ids', $this->data['gallery_ids'] ); }
        if ( isset( $this->data['category_ids'] ) ) { wp_set_object_terms( $this->id, $this->data['category_ids'], 'taqi_category', false ); }
        return $this->id;
    }
    public function delete( $force = false ) { foreach ( $this->get_children() as $child ) { wp_delete_post( $child, true ); } return (bool) wp_delete_post( $this->id, true ); }
}

final class TAQI_Life_Dropshipping {

    const VERSION                       = '2.0.0';
    const OPTION_SETTINGS               = 'taqi_dropshipping_settings';
    const OPTION_LAST_TEST              = 'taqi_dropshipping_last_test';
    const OPTION_CATEGORY_MAP           = 'taqi_dropshipping_category_map';
    const OPTION_CATEGORY_RULES         = 'taqi_dropshipping_category_rules';
    const OPTION_DISCOVERED_CATEGORIES  = 'taqi_dropshipping_discovered_categories';
    const OPTION_ATTRIBUTE_MAP          = 'taqi_dropshipping_attribute_map';
    const OPTION_VARIANT_MAP            = 'taqi_dropshipping_variant_map';
    const OPTION_DISCOVERED_VARIATIONS  = 'taqi_dropshipping_discovered_variations';
    const OPTION_SCAN_DEBUG              = 'taqi_dropshipping_scan_debug';
    const OPTION_CATEGORY_API_TEST       = 'taqi_dropshipping_category_api_test';
    const OPTION_CATEGORY_API_CATALOG    = 'taqi_dropshipping_category_api_catalog';

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_content_types' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'add_meta_boxes_taqi_product', array( $this, 'add_product_meta_box' ) );
        add_action( 'wp_ajax_taqi_process_all_pages', array( $this, 'ajax_process_all_pages' ) );
    }

    public function register_content_types() {
        register_taxonomy( 'taqi_category', array( 'taqi_product' ), array( 'label' => 'Dropshipping Categories', 'public' => false, 'show_ui' => true, 'hierarchical' => true, 'show_in_rest' => false ) );
        register_post_type( 'taqi_product', array( 'label' => 'Dropshipping Products', 'public' => false, 'show_ui' => true, 'show_in_menu' => false, 'supports' => array( 'title', 'editor', 'thumbnail' ), 'capability_type' => 'post', 'map_meta_cap' => true ) );
        register_post_type( 'taqi_product_variation', array( 'label' => 'Dropshipping Variations', 'public' => false, 'show_ui' => false, 'supports' => array( 'title' ), 'capability_type' => 'post', 'map_meta_cap' => true ) );
    }

    public function admin_menu() {
        add_menu_page( 'TAQI LIFE Dropshipping', 'Dropshipping', 'manage_options', 'taqi-dropshipping', array( $this, 'dashboard_page' ), 'dashicons-cart', 56 );
        add_submenu_page(
            'taqi-dropshipping',
            'TAQI LIFE Dropshipping',
            'Dropshipping',
            'manage_options',
            'taqi-dropshipping',
            array( $this, 'dashboard_page' )
        );

        add_submenu_page(
            'taqi-dropshipping',
            'Supplier Products',
            'Supplier Products',
            'manage_options',
            'taqi-dropshipping-products',
            array( $this, 'supplier_products_page' )
        );

        add_submenu_page(
            'taqi-dropshipping',
            'Imported Products',
            'Imported Products',
            'manage_options',
            'taqi-dropshipping-imported',
            array( $this, 'imported_products_page' )
        );

        add_submenu_page(
            'taqi-dropshipping',
            'Category Mapping',
            'Category Mapping',
            'manage_options',
            'taqi-dropshipping-categories',
            array( $this, 'category_mapping_page' )
        );

        add_submenu_page(
            'taqi-dropshipping',
            'Variation Mapping',
            'Variation Mapping',
            'manage_options',
            'taqi-dropshipping-variations',
            array( $this, 'variation_mapping_page' )
        );

        add_submenu_page(
            'taqi-dropshipping',
            'Pricing Rules',
            'Pricing Rules',
            'manage_options',
            'taqi-dropshipping-pricing',
            array( $this, 'pricing_rules_page' )
        );

        add_submenu_page(
            'taqi-dropshipping',
            'Dropshipping API Settings',
            'API Settings',
            'manage_options',
            'taqi-dropshipping-settings',
            array( $this, 'settings_page' )
        );
    }

    private function defaults() {
        return array(
            'supplier_name'            => 'Mohasagor',
            'base_url'                 => 'https://mohasagor.com.bd/api/reseller',
            'category_endpoint'        => '',
            'api_key'                  => '',
            'secret_key'               => '',

            // Confirmed Mohasagor pricing model for TAQI LIFE:
            // sale_price = Cost Price, price = Maximum Selling Price.
            // Minimum Selling Price is calculated from Cost Price + configured minimum markup.
            'pricing_model_version'    => '3',
            // User-controlled pricing percentages.
            // Minimum % defines the lowest allowed selling price above Cost Price.
            // TAQI % defines the normal TAQI LIFE selling price above Cost Price.
            'minimum_markup_percent'   => '4.08',
            'taqi_markup_percent'      => '20',
            'price_rounding'           => 'none',
            'enforce_minimum_price'    => 'yes',
            'cap_at_maximum_price'     => 'yes',

            // Legacy pricing keys retained only for upgrade compatibility.
            'price_mode'               => 'taqi_percent',
            'markup_percent'           => '20',
            'fixed_markup'             => '0',

            // Legacy keys retained only for safe upgrade compatibility.
            'price_source'             => 'price',
            'cost_source'              => 'sale_price',
            'import_supplier_sale'     => 'no',

            'import_status'            => 'draft',
            'import_images'            => 'yes',
            'import_variations'        => 'yes',
        );
    }

    private function settings() {
        $saved    = get_option( self::OPTION_SETTINGS, array() );
        $saved    = is_array( $saved ) ? $saved : array();
        $settings = wp_parse_args( $saved, $this->defaults() );

        /*
         * One-time safe migration from v1.3.6 and older. Previous versions could
         * interpret API sale_price as a customer-facing WooCommerce Sale Price.
         * Reset legacy pricing to the confirmed model and the safest default.
         */
        if ( empty( $saved['pricing_model_version'] ) || '3' !== (string) $saved['pricing_model_version'] ) {
            // Preserve the user's existing custom percentage when possible, but never
            // migrate an unsafe historical value blindly. v1.3.7 defaulted to 20%.
            $legacy_taqi_percent = isset( $saved['taqi_markup_percent'] )
                ? max( 0, (float) $saved['taqi_markup_percent'] )
                : ( isset( $saved['markup_percent'] ) ? max( 0, (float) $saved['markup_percent'] ) : 20 );

            // Very old versions may contain an accidental 200% test value. Reset only
            // obviously unsafe migration values; the admin can still deliberately enter
            // any valid percentage in v1.3.8.
            if ( $legacy_taqi_percent > 100 ) {
                $legacy_taqi_percent = 20;
            }

            $settings['pricing_model_version']  = '3';
            $settings['price_mode']             = 'taqi_percent';
            $settings['minimum_markup_percent'] = isset( $saved['minimum_markup_percent'] ) ? (string) max( 0, (float) $saved['minimum_markup_percent'] ) : '4.08';
            $settings['taqi_markup_percent']    = (string) $legacy_taqi_percent;
            $settings['markup_percent']         = (string) $legacy_taqi_percent; // legacy mirror only.
            $settings['fixed_markup']           = '0';
            $settings['price_rounding']         = isset( $saved['price_rounding'] ) ? $saved['price_rounding'] : 'none';
            $settings['enforce_minimum_price']  = 'yes';
            $settings['cap_at_maximum_price']   = 'yes';
            $settings['price_source']           = 'price';
            $settings['cost_source']            = 'sale_price';
            $settings['import_supplier_sale']   = 'no';
            update_option( self::OPTION_SETTINGS, $settings, false );
        }

        return $settings;
    }

    private function supplier_key() {
        $settings = $this->settings();
        return sanitize_key( $settings['supplier_name'] ? $settings['supplier_name'] : 'supplier' );
    }

    private function product_endpoint( $page = 1 ) {
        $settings = $this->settings();
        $base     = untrailingslashit( trim( $settings['base_url'] ) );
        return add_query_arg( array( 'page' => max( 1, absint( $page ) ) ), $base . '/product' );
    }

    private function api_request_products( $page = 1 ) {
        $settings = $this->settings();

        if ( empty( $settings['base_url'] ) || empty( $settings['api_key'] ) || empty( $settings['secret_key'] ) ) {
            return new WP_Error(
                'taqi_missing_credentials',
                'API settings are incomplete. Go to WooCommerce → API Settings and save the Base URL, API Key and Secret Key.'
            );
        }

        $cache_key = 'taqi_products_' . md5( $this->product_endpoint( $page ) . '|' . $settings['api_key'] . '|' . $settings['secret_key'] );
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $response = wp_safe_remote_get(
            $this->product_endpoint( $page ),
            array(
                'timeout'     => 45,
                'redirection' => 3,
                'headers'     => array(
                    'Accept'     => 'application/json',
                    'api-key'    => trim( $settings['api_key'] ),
                    'secret-key' => trim( $settings['secret_key'] ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code   = (int) wp_remote_retrieve_response_code( $response );
        $reason = wp_remote_retrieve_response_message( $response );
        $body   = wp_remote_retrieve_body( $response );

        if ( 200 !== $code ) {
            $short_body = trim( wp_strip_all_tags( (string) $body ) );
            if ( strlen( $short_body ) > 500 ) {
                $short_body = substr( $short_body, 0, 500 ) . '…';
            }

            $message = 'Supplier API HTTP Error: ' . $code;
            if ( $reason ) {
                $message .= ' ' . $reason;
            }
            if ( $short_body ) {
                $message .= ' | Response: ' . $short_body;
            }

            return new WP_Error( 'taqi_api_http_error', $message );
        }

        $data = json_decode( $body, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
            return new WP_Error( 'taqi_invalid_json', 'Supplier API returned invalid JSON.' );
        }

        set_transient( $cache_key, $data, 60 );
        return $data;
    }

    private function linked_products_map() {
        $cached = get_transient( 'taqi_linked_products_map' );
        if ( is_array( $cached ) ) {
            return $cached;
        }
        $map = array();
        $ids = get_posts(
            array(
                'post_type'      => 'taqi_product',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'   => '_taqi_supplier',
                        'value' => $this->supplier_key(),
                    ),
                ),
            )
        );
        foreach ( $ids as $product_id ) {
            $supplier_id = (string) get_post_meta( $product_id, '_taqi_supplier_id', true );
            if ( '' === $supplier_id ) {
                continue;
            }
            $status = 'cancelled' === get_post_meta( $product_id, '_taqi_sync_status', true ) ? 'cancelled' : 'active';
            if ( ! isset( $map[ $supplier_id ] ) || 'active' === $status ) {
                $map[ $supplier_id ] = array( 'active' === $status ? 'active' : 'cancelled' => (int) $product_id );
            }
        }
        set_transient( 'taqi_linked_products_map', $map, 60 );
        return $map;
    }


    /**
     * Build a safe optional supplier endpoint URL without changing the Product API.
     * Only URLs on the same scheme/host and under the configured reseller base path
     * are accepted. Relative paths are appended to the configured base URL.
     */
    private function optional_supplier_endpoint_url( $endpoint ) {
        $settings = $this->settings();
        $base     = untrailingslashit( trim( $settings['base_url'] ) );
        $endpoint = trim( (string) $endpoint );

        if ( '' === $base || '' === $endpoint ) {
            return new WP_Error( 'taqi_optional_endpoint_missing', 'Optional API endpoint is empty.' );
        }

        if ( preg_match( '#^https?://#i', $endpoint ) ) {
            $url = esc_url_raw( $endpoint );
        } else {
            $url = $base . '/' . ltrim( $endpoint, '/' );
        }

        $base_parts = wp_parse_url( $base );
        $url_parts  = wp_parse_url( $url );

        if (
            empty( $base_parts['scheme'] ) ||
            empty( $base_parts['host'] ) ||
            empty( $url_parts['scheme'] ) ||
            empty( $url_parts['host'] ) ||
            strtolower( $base_parts['scheme'] ) !== strtolower( $url_parts['scheme'] ) ||
            strtolower( $base_parts['host'] ) !== strtolower( $url_parts['host'] )
        ) {
            return new WP_Error( 'taqi_optional_endpoint_host', 'Optional endpoint must use the same API host as the configured Base URL.' );
        }

        $base_path = isset( $base_parts['path'] ) ? trailingslashit( $base_parts['path'] ) : '/';
        $url_path  = isset( $url_parts['path'] ) ? $url_parts['path'] : '/';

        if ( 0 !== strpos( trailingslashit( $url_path ), $base_path ) ) {
            return new WP_Error( 'taqi_optional_endpoint_path', 'Optional endpoint must remain inside the configured reseller API base path.' );
        }

        return $url;
    }

    /**
     * GET-only diagnostic request for optional supplier endpoints.
     * This deliberately does NOT call or modify api_request_products().
     */
    private function api_probe_optional_endpoint( $endpoint ) {
        $settings = $this->settings();

        if ( empty( $settings['api_key'] ) || empty( $settings['secret_key'] ) ) {
            return new WP_Error( 'taqi_missing_credentials', 'API Key and Secret Key are required before probing optional endpoints.' );
        }

        $url = $this->optional_supplier_endpoint_url( $endpoint );
        if ( is_wp_error( $url ) ) {
            return $url;
        }

        $response = wp_safe_remote_get(
            $url,
            array(
                'timeout'     => 20,
                'redirection' => 2,
                'headers'     => array(
                    'Accept'     => 'application/json',
                    'api-key'    => trim( $settings['api_key'] ),
                    'secret-key' => trim( $settings['secret_key'] ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'url'     => $url,
                'code'    => 0,
                'reason'  => $response->get_error_message(),
                'data'    => null,
                'body'    => '',
            );
        }

        $code   = (int) wp_remote_retrieve_response_code( $response );
        $reason = wp_remote_retrieve_response_message( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = null;

        if ( '' !== trim( $body ) ) {
            $decoded = json_decode( $body, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                $data = $decoded;
            }
        }

        return array(
            'url'    => $url,
            'code'   => $code,
            'reason' => $reason,
            'data'   => $data,
            'body'   => $body,
        );
    }

    private function category_api_row_value( $row, $keys ) {
        if ( ! is_array( $row ) ) {
            return '';
        }

        foreach ( $keys as $key ) {
            if ( array_key_exists( $key, $row ) && is_scalar( $row[ $key ] ) ) {
                $value = sanitize_text_field( (string) $row[ $key ] );
                if ( '' !== $value ) {
                    return $value;
                }
            }
        }

        foreach ( $row as $key => $value ) {
            $normalized = $this->normalized_key( $key );
            foreach ( $keys as $expected ) {
                if ( $normalized === $this->normalized_key( $expected ) && is_scalar( $value ) ) {
                    $clean = sanitize_text_field( (string) $value );
                    if ( '' !== $clean ) {
                        return $clean;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Generic, read-only category endpoint parser.
     * It is intentionally conservative: it only records rows that look like
     * categories and never writes WooCommerce terms automatically.
     */
    private function extract_category_api_catalog_recursive( $value, $parent_path = '', $depth = 0, &$found = array() ) {
        if ( ! is_array( $value ) || $depth > 8 ) {
            return;
        }

        $name = $this->category_api_row_value(
            $value,
            array( 'name', 'title', 'label', 'category_name', 'categoryName', 'subcategory_name', 'subCategoryName' )
        );
        $id = $this->category_api_row_value(
            $value,
            array( 'id', 'category_id', 'categoryId', 'cat_id', 'subcategory_id', 'slug' )
        );

        $has_child_key = false;
        foreach ( $value as $key => $child ) {
            if (
                is_array( $child ) &&
                in_array(
                    $this->normalized_key( $key ),
                    array( 'children', 'child', 'childcategories', 'subcategories', 'subcategory', 'categories' ),
                    true
                )
            ) {
                $has_child_key = true;
                break;
            }
        }

        if ( '' !== $name && ( '' !== $id || $has_child_key ) ) {
            $path = '' !== $parent_path ? $parent_path . ' > ' . $name : $name;
            $key  = '' !== $id ? 'api:' . sanitize_key( $id ) : 'api-name:' . sanitize_title( $path );

            if ( '' !== $key ) {
                $found[ $key ] = array(
                    'id'          => $key,
                    'supplier_id' => $id,
                    'name'        => $name,
                    'path'        => $path,
                );
            }
            $parent_path = $path;
        }

        foreach ( $value as $child ) {
            if ( is_array( $child ) ) {
                if ( isset( $child[0] ) && is_array( $child[0] ) ) {
                    foreach ( $child as $row ) {
                        if ( is_array( $row ) ) {
                            $this->extract_category_api_catalog_recursive( $row, $parent_path, $depth + 1, $found );
                        }
                    }
                } else {
                    $this->extract_category_api_catalog_recursive( $child, $parent_path, $depth + 1, $found );
                }
            }
        }
    }

    private function extract_category_api_catalog( $data ) {
        $found = array();
        if ( is_array( $data ) ) {
            $this->extract_category_api_catalog_recursive( $data, '', 0, $found );
        }

        uasort(
            $found,
            function( $a, $b ) {
                return strcasecmp(
                    isset( $a['path'] ) ? $a['path'] : '',
                    isset( $b['path'] ) ? $b['path'] : ''
                );
            }
        );

        return $found;
    }

    /**
     * Safely probe likely read-only category endpoints.
     * A successful probe never changes Product API settings or import behavior.
     */
    private function probe_category_api() {
        $settings   = $this->settings();
        $candidates = array();

        if ( ! empty( $settings['category_endpoint'] ) ) {
            $candidates[] = trim( $settings['category_endpoint'] );
        }

        $candidates = array_merge(
            $candidates,
            array(
                'category',
                'categories',
                'subcategory',
                'subcategories',
                'product/category',
                'product/categories',
                'product-category',
                'product-categories',
                'category/list',
                'category-list',
            )
        );

        $candidates = array_values( array_unique( array_filter( array_map( 'trim', $candidates ) ) ) );
        $attempts   = array();
        $verified   = null;

        foreach ( $candidates as $candidate ) {
            $result = $this->api_probe_optional_endpoint( $candidate );

            if ( is_wp_error( $result ) ) {
                $attempts[] = array(
                    'endpoint' => $candidate,
                    'url'      => '',
                    'code'     => 0,
                    'result'   => $result->get_error_message(),
                );
                continue;
            }

            $catalog = array();
            if ( 200 === (int) $result['code'] && is_array( $result['data'] ) ) {
                $catalog = $this->extract_category_api_catalog( $result['data'] );
            }

            $attempts[] = array(
                'endpoint' => $candidate,
                'url'      => $result['url'],
                'code'     => (int) $result['code'],
                'result'   => $catalog
                    ? sprintf( '%d category-like records detected', count( $catalog ) )
                    : ( $result['reason'] ? $result['reason'] : 'No parseable category records' ),
            );

            if ( 200 === (int) $result['code'] && ! empty( $catalog ) ) {
                $verified = array(
                    'endpoint' => $candidate,
                    'url'      => $result['url'],
                    'count'    => count( $catalog ),
                    'catalog'  => $catalog,
                );
                break;
            }
        }

        $report = array(
            'checked'  => current_time( 'mysql' ),
            'success'  => ! empty( $verified ),
            'verified' => $verified,
            'attempts' => $attempts,
        );

        update_option( self::OPTION_CATEGORY_API_TEST, $report, false );

        if ( ! empty( $verified['catalog'] ) ) {
            update_option( self::OPTION_CATEGORY_API_CATALOG, $verified['catalog'], false );

            // Store the verified endpoint for convenience only.
            // Product imports remain on /product and are never redirected here.
            $settings['category_endpoint'] = $verified['endpoint'];
            update_option( self::OPTION_SETTINGS, $settings, false );
        }

        return $report;
    }

    private function extract_products( $data ) {
        if ( ! is_array( $data ) ) {
            return array();
        }

        $candidates = array(
            isset( $data['products'] ) ? $data['products'] : null,
            isset( $data['items'] ) ? $data['items'] : null,
            isset( $data['result'] ) ? $data['result'] : null,
            isset( $data['data']['data'] ) ? $data['data']['data'] : null,
            isset( $data['data']['products'] ) ? $data['data']['products'] : null,
            isset( $data['data'] ) ? $data['data'] : null,
        );

        foreach ( $candidates as $candidate ) {
            if ( is_array( $candidate ) && $this->is_list_of_products( $candidate ) ) {
                return array_values( $candidate );
            }
        }

        if ( $this->is_list_of_products( $data ) ) {
            return array_values( $data );
        }

        return array();
    }

    private function is_list_of_products( $value ) {
        if ( ! is_array( $value ) || empty( $value ) ) {
            return false;
        }
        $first = reset( $value );
        return is_array( $first ) && ( isset( $first['id'] ) || isset( $first['name'] ) || isset( $first['product_code'] ) );
    }

    private function extract_last_page( $data ) {
        $paths = array(
            isset( $data['last_page'] ) ? $data['last_page'] : null,
            isset( $data['meta']['last_page'] ) ? $data['meta']['last_page'] : null,
            isset( $data['data']['last_page'] ) ? $data['data']['last_page'] : null,
            isset( $data['pagination']['last_page'] ) ? $data['pagination']['last_page'] : null,
        );

        foreach ( $paths as $value ) {
            if ( is_numeric( $value ) && (int) $value > 0 ) {
                return (int) $value;
            }
        }

        return 1;
    }

    private function scalar( $value, $default = '' ) {
        if ( is_scalar( $value ) || null === $value ) {
            return null === $value ? $default : (string) $value;
        }
        return $default;
    }

    private function decode_arrayish( $value ) {
        if ( is_array( $value ) ) {
            return $value;
        }
        if ( is_string( $value ) ) {
            $decoded = json_decode( trim( $value ), true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                return $decoded;
            }
        }
        return array();
    }

    private function money_value( $value ) {
        if ( is_string( $value ) ) {
            $value = str_replace( array( ',', '৳', 'BDT', 'Tk', 'TK', ' ' ), '', $value );
        }
        return is_numeric( $value ) ? (float) $value : null;
    }

    private function round_price( $price ) {
        $settings = $this->settings();
        $mode     = isset( $settings['price_rounding'] ) ? $settings['price_rounding'] : 'none';

        switch ( $mode ) {
            case '10':
                return round( $price / 10 ) * 10;
            case '50':
                return round( $price / 50 ) * 50;
            case '100':
                return round( $price / 100 ) * 100;
            default:
                return $price;
        }
    }

    /**
     * Confirmed Mohasagor pricing semantics:
     *   sale_price = Cost Price
     *   price      = Maximum Selling Price
     * Minimum Selling Price is calculated from Cost + configured minimum markup.
     * No silent fallback is allowed between these fields.
     */
    private function supplier_cost_price( $product ) {
        if ( ! is_array( $product ) || ! array_key_exists( 'sale_price', $product ) ) {
            return null;
        }
        $value = $this->money_value( $product['sale_price'] );
        return null !== $value && $value >= 0 ? $value : null;
    }

    private function supplier_maximum_selling_price( $product ) {
        if ( ! is_array( $product ) || ! array_key_exists( 'price', $product ) ) {
            return null;
        }
        $value = $this->money_value( $product['price'] );
        return null !== $value && $value >= 0 ? $value : null;
    }

    private function supplier_minimum_selling_price( $product ) {
        if ( ! is_array( $product ) ) {
            return null;
        }

        // If Mohasagor exposes a real minimum-price field later, prefer it.
        foreach ( array( 'minimum_sale_price', 'min_sale_price', 'minimum_selling_price', 'min_selling_price' ) as $key ) {
            if ( array_key_exists( $key, $product ) ) {
                $value = $this->money_value( $product[ $key ] );
                if ( null !== $value && $value >= 0 ) {
                    return $value;
                }
            }
        }

        $cost = $this->supplier_cost_price( $product );
        if ( null === $cost ) {
            return null;
        }

        $settings = $this->settings();
        $percent  = isset( $settings['minimum_markup_percent'] ) ? max( 0, (float) $settings['minimum_markup_percent'] ) : 4.08;

        // Round UP to a whole BDT so ৳490 + 4.08% safely becomes ৳510, not ৳509.99.
        $minimum = ceil( ( $cost + ( $cost * $percent / 100 ) ) - 0.0000001 );
        $maximum = $this->supplier_maximum_selling_price( $product );

        if ( null !== $maximum && $maximum >= $cost && $minimum > $maximum ) {
            $minimum = $maximum;
        }

        return $minimum;
    }

    private function pricing_breakdown( $product ) {
        $settings = $this->settings();
        $cost     = $this->supplier_cost_price( $product );
        $minimum  = $this->supplier_minimum_selling_price( $product );
        $maximum  = $this->supplier_maximum_selling_price( $product );
        $target   = null;
        $warning  = '';

        $minimum_percent = isset( $settings['minimum_markup_percent'] )
            ? max( 0, (float) $settings['minimum_markup_percent'] )
            : 4.08;
        $taqi_percent = isset( $settings['taqi_markup_percent'] )
            ? max( 0, (float) $settings['taqi_markup_percent'] )
            : 20;

        if ( null === $cost ) {
            $warning = 'Cost Price (API sale_price) is missing, so TAQI LIFE price cannot be calculated.';
        } else {
            // TAQI LIFE selling price is always user-controlled percentage markup on Cost.
            $target = $cost + ( $cost * $taqi_percent / 100 );
            $target = $this->round_price( $target );

            if ( 'yes' === $settings['enforce_minimum_price'] && null !== $minimum && $target < $minimum ) {
                $target = $minimum;
                $warning = sprintf(
                    'TAQI markup %.2f%% is below the minimum %.2f%% for this rule; Minimum Selling Price was used.',
                    $taqi_percent,
                    $minimum_percent
                );
            }

            if ( 'yes' === $settings['cap_at_maximum_price'] && null !== $maximum && $target > $maximum ) {
                $target = $maximum;
                $warning = sprintf(
                    'TAQI markup %.2f%% calculated above the supplier Maximum Selling Price; the price was capped at the maximum.',
                    $taqi_percent
                );
            }

            if ( $target < $cost ) {
                $target  = null;
                $warning = 'Calculated selling price would be below Cost Price; price update was blocked.';
            }
        }

        $profit = null;
        $markup_percent = null;
        if ( null !== $target && null !== $cost ) {
            $profit = $target - $cost;
            if ( $cost > 0 ) {
                $markup_percent = ( $profit / $cost ) * 100;
            }
        }

        return array(
            'cost'                    => $cost,
            'minimum'                 => $minimum,
            'maximum'                 => $maximum,
            'selling'                 => $target,
            'profit'                  => $profit,
            'markup_percent'          => $markup_percent,
            'minimum_markup_percent'  => $minimum_percent,
            'taqi_markup_percent'     => $taqi_percent,
            'warning'                 => $warning,
        );
    }

    private function product_regular_price( $product ) {
        $pricing = $this->pricing_breakdown( $product );
        return isset( $pricing['selling'] ) ? $pricing['selling'] : null;
    }

    private function product_sale_price( $product, $regular_price ) {
        // API sale_price is Cost Price. Never use it as WooCommerce Sale Price.
        return null;
    }

    private function format_money( $value ) {
        $money = $this->money_value( $value );
        if ( null === $money ) {
            return '—';
        }
        return wp_kses_post( number_format_i18n( $money, 2 ) );
    }

    private function sku_owner( $sku ) {
        if ( '' === (string) $sku ) { return 0; }
        $ids = get_posts( array( 'post_type' => array( 'taqi_product', 'taqi_product_variation' ), 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => '_taqi_sku', 'value' => (string) $sku ) ) ) );
        return empty( $ids ) ? 0 : absint( $ids[0] );
    }

    private function supplier_product_id( $product ) {
        if ( isset( $product['id'] ) ) {
            return sanitize_text_field( $this->scalar( $product['id'] ) );
        }
        if ( isset( $product['product_id'] ) ) {
            return sanitize_text_field( $this->scalar( $product['product_id'] ) );
        }
        return '';
    }

    private function linked_product_ids( $supplier_id, $statuses = null ) {
        if ( '' === (string) $supplier_id ) {
            return array();
        }

        if ( null === $statuses ) {
            $statuses = array( 'publish', 'draft', 'pending', 'private' );
        }

        return get_posts(
            array(
                'post_type'      => 'taqi_product',
                'post_status'    => $statuses,
                'fields'         => 'ids',
                'posts_per_page' => 20,
                'orderby'        => 'ID',
                'order'          => 'DESC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_taqi_supplier',
                        'value' => $this->supplier_key(),
                    ),
                    array(
                        'key'   => '_taqi_supplier_id',
                        'value' => (string) $supplier_id,
                    ),
                ),
            )
        );
    }

    private function find_imported_product_id( $supplier_id ) {
        foreach ( $this->linked_product_ids( $supplier_id ) as $product_id ) {
            if ( 'cancelled' !== get_post_meta( $product_id, '_taqi_sync_status', true ) ) {
                return (int) $product_id;
            }
        }
        return 0;
    }

    private function find_cancelled_product_id( $supplier_id ) {
        foreach ( $this->linked_product_ids( $supplier_id ) as $product_id ) {
            if ( 'cancelled' === get_post_meta( $product_id, '_taqi_sync_status', true ) ) {
                return (int) $product_id;
            }
        }
        return 0;
    }

    private function find_trashed_linked_product_id( $supplier_id ) {
        $ids = $this->linked_product_ids( $supplier_id, array( 'trash' ) );
        return empty( $ids ) ? 0 : (int) $ids[0];
    }

    private function validate_linked_product( $product_id, $supplier_id, $allow_cancelled = true ) {
        $product_id = absint( $product_id );
        if ( ! $product_id || 'taqi_product' !== get_post_type( $product_id ) ) {
            return new WP_Error( 'taqi_invalid_product', 'The dropshipping product could not be found.' );
        }

        if ( ! current_user_can( 'edit_post', $product_id ) ) {
            return new WP_Error( 'taqi_product_permission', 'You do not have permission to manage this product.' );
        }

        if ( $this->supplier_key() !== get_post_meta( $product_id, '_taqi_supplier', true ) ) {
            return new WP_Error( 'taqi_supplier_mismatch', 'This product is not linked to the configured dropshipping supplier.' );
        }

        $stored_supplier_id = (string) get_post_meta( $product_id, '_taqi_supplier_id', true );
        if ( '' === $stored_supplier_id || (string) $supplier_id !== $stored_supplier_id ) {
            return new WP_Error( 'taqi_supplier_id_mismatch', 'Supplier product validation failed. No action was taken.' );
        }

        if ( ! $allow_cancelled && 'cancelled' === get_post_meta( $product_id, '_taqi_sync_status', true ) ) {
            return new WP_Error( 'taqi_sync_cancelled', 'Synchronization is cancelled for this product. Re-link it before re-syncing.' );
        }

        return new TAQI_Life_Product( 'simple', $product_id );
    }

    private function find_live_supplier_product( $supplier_id, $page_hint = 0 ) {
        $supplier_id = (string) sanitize_text_field( $supplier_id );
        if ( '' === $supplier_id ) {
            return new WP_Error( 'taqi_supplier_id_missing', 'Supplier product ID is missing.' );
        }

        $checked = array();

        if ( $page_hint > 0 ) {
            $data = $this->api_request_products( $page_hint );
            if ( ! is_wp_error( $data ) ) {
                $checked[ $page_hint ] = true;
                foreach ( $this->extract_products( $data ) as $row ) {
                    if ( $supplier_id === (string) $this->supplier_product_id( $row ) ) {
                        return array( 'product' => $row, 'page' => $page_hint );
                    }
                }
            }
        }

        $first = $this->api_request_products( 1 );
        if ( is_wp_error( $first ) ) {
            return $first;
        }

        $last_page = min( 50, max( 1, $this->extract_last_page( $first ) ) );
        if ( empty( $checked[1] ) ) {
            foreach ( $this->extract_products( $first ) as $row ) {
                if ( $supplier_id === (string) $this->supplier_product_id( $row ) ) {
                    return array( 'product' => $row, 'page' => 1 );
                }
            }
            $checked[1] = true;
        }

        for ( $page = 2; $page <= $last_page; $page++ ) {
            if ( ! empty( $checked[ $page ] ) ) {
                continue;
            }
            $data = $this->api_request_products( $page );
            if ( is_wp_error( $data ) ) {
                continue;
            }
            foreach ( $this->extract_products( $data ) as $row ) {
                if ( $supplier_id === (string) $this->supplier_product_id( $row ) ) {
                    return array( 'product' => $row, 'page' => $page );
                }
            }
        }

        return new WP_Error( 'taqi_supplier_product_not_found', 'The supplier product was not found in the current Product API catalog. The local product was not changed.' );
    }

    private function refresh_supplier_meta( $product, $supplier_product, $api_page = 0 ) {
        $supplier_id = $this->supplier_product_id( $supplier_product );
        $settings    = $this->settings();
        $sku         = isset( $supplier_product['product_code'] ) ? sanitize_text_field( $this->scalar( $supplier_product['product_code'] ) ) : '';

        $product->update_meta_data( '_taqi_supplier', $this->supplier_key() );
        $product->update_meta_data( '_taqi_supplier_name', sanitize_text_field( $settings['supplier_name'] ) );
        $product->update_meta_data( '_taqi_supplier_id', (string) $supplier_id );
        $product->update_meta_data( '_taqi_supplier_code', $sku );
        $product->update_meta_data( '_taqi_supplier_raw_payload', wp_json_encode( $supplier_product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
        $product->update_meta_data( '_taqi_sync_status', 'active' );
        $product->update_meta_data( '_taqi_last_sync_at', current_time( 'mysql' ) );
        $product->delete_meta_data( '_taqi_sync_cancelled_at' );

        if ( $api_page > 0 ) {
            $product->update_meta_data( '_taqi_supplier_api_page', absint( $api_page ) );
        }

        $supplier_category = $this->supplier_category( $supplier_product );
        if ( '' !== $supplier_category['id'] ) {
            $product->update_meta_data( '_taqi_supplier_category_id', $supplier_category['id'] );
            $product->update_meta_data( '_taqi_supplier_category_name', $supplier_category['name'] );
        }

        foreach ( array( 'price', 'reselling_price', 'sale_price', 'category_id', 'status', 'stock_status' ) as $meta_key ) {
            if ( isset( $supplier_product[ $meta_key ] ) && is_scalar( $supplier_product[ $meta_key ] ) ) {
                $product->update_meta_data( '_taqi_supplier_' . $meta_key, sanitize_text_field( (string) $supplier_product[ $meta_key ] ) );
            }
        }
    }

    private function resync_product( $product_id, $supplier_product, $api_page = 0 ) {
        $supplier_id = $this->supplier_product_id( $supplier_product );
        $product     = $this->validate_linked_product( $product_id, $supplier_id, false );
        if ( is_wp_error( $product ) ) {
            return $product;
        }

        $this->apply_category_mapping( $product, $supplier_product );
        $this->apply_stock_data( $product, $supplier_product );
        $this->refresh_supplier_meta( $product, $supplier_product, $api_page );

        $updated_variations = 0;
        if ( $product->is_type( 'variable' ) ) {
            $model = $this->build_variation_model( $supplier_product );
            $by_id = array();
            foreach ( $model['rows'] as $model_row ) {
                $raw = isset( $model_row['raw'] ) && is_array( $model_row['raw'] ) ? $model_row['raw'] : array();
                $variant_id = '';
                if ( isset( $raw['variant_id'] ) ) {
                    $variant_id = sanitize_text_field( $this->scalar( $raw['variant_id'] ) );
                } elseif ( isset( $raw['id'] ) ) {
                    $variant_id = sanitize_text_field( $this->scalar( $raw['id'] ) );
                }
                if ( '' !== $variant_id ) {
                    $by_id[ $variant_id ] = $raw;
                }
            }

            foreach ( $product->get_children() as $variation_id ) {
                $variation = new TAQI_Life_Product( 'variation', $variation_id );
                if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
                    continue;
                }
                $variant_id = (string) $variation->get_meta( '_taqi_supplier_variant_id', true );
                if ( '' === $variant_id || ! isset( $by_id[ $variant_id ] ) ) {
                    continue;
                }
                $raw = $by_id[ $variant_id ];
                $regular = $this->product_regular_price( $raw );
                if ( null !== $regular ) {
                    $variation->set_regular_price( number_format( $regular, 2, '.', '' ) );
                }
                $sale = $this->product_sale_price( $raw, $regular );
                if ( null !== $sale ) {
                    $variation->set_sale_price( number_format( $sale, 2, '.', '' ) );
                } else {
                    $variation->set_sale_price( '' );
                }
                $this->apply_stock_data( $variation, $raw );
                $variation->save();
                ++$updated_variations;
            }
        } else {
            $regular = $this->product_regular_price( $supplier_product );
            if ( null !== $regular ) {
                $product->set_regular_price( number_format( $regular, 2, '.', '' ) );
            }
            $sale = $this->product_sale_price( $supplier_product, $regular );
            if ( null !== $sale ) {
                $product->set_sale_price( number_format( $sale, 2, '.', '' ) );
            } else {
                $product->set_sale_price( '' );
            }
        }

        $product->save();

        // Safe image re-sync: keep local images and add any supplier images that are missing.
        $image_sync = $this->sync_product_images( $product, $supplier_product, $product->get_name(), true );

        return array(
            'status'             => 'resynced',
            'product_id'         => $product->get_id(),
            'supplier_id'        => $supplier_id,
            'updated_variations' => $updated_variations,
            'image_sync'         => $image_sync,
            'message'            => sprintf(
                'Product re-synced. Supplier price, stock, mapped categories and metadata were refreshed. Local title/description/images were preserved, and %d supplier image(s) were detected with %d new download(s).',
                isset( $image_sync['supplier_urls'] ) ? absint( $image_sync['supplier_urls'] ) : 0,
                isset( $image_sync['downloaded'] ) ? absint( $image_sync['downloaded'] ) : 0
            ),
        );
    }

    private function cancel_product_sync( $product_id, $supplier_id ) {
        $product = $this->validate_linked_product( $product_id, $supplier_id, true );
        if ( is_wp_error( $product ) ) {
            return $product;
        }

        if ( 'cancelled' === $product->get_meta( '_taqi_sync_status', true ) ) {
            return new WP_Error( 'taqi_already_cancelled', 'Synchronization is already cancelled for this product.' );
        }

        $product->update_meta_data( '_taqi_sync_status', 'cancelled' );
        $product->update_meta_data( '_taqi_sync_cancelled_at', current_time( 'mysql' ) );
        $product->save();

        return array(
            'status'      => 'cancelled',
            'product_id'  => $product_id,
            'supplier_id' => (string) $supplier_id,
            'message'     => 'Synchronization cancelled. The WooCommerce product was kept unchanged and will not be re-synced until you re-link it.',
        );
    }

    private function relink_product_sync( $product_id, $supplier_product, $api_page = 0 ) {
        $supplier_id = $this->supplier_product_id( $supplier_product );
        $product     = $this->validate_linked_product( $product_id, $supplier_id, true );
        if ( is_wp_error( $product ) ) {
            return $product;
        }

        $product->update_meta_data( '_taqi_sync_status', 'active' );
        $product->delete_meta_data( '_taqi_sync_cancelled_at' );
        $product->save();

        return $this->resync_product( $product_id, $supplier_product, $api_page );
    }

    private function delete_synced_product( $product_id, $supplier_id ) {
        $product = $this->validate_linked_product( $product_id, $supplier_id, true );
        if ( is_wp_error( $product ) ) {
            return $product;
        }

        $title = $product->get_name();
        $deleted = $product->delete( true );
        if ( ! $deleted ) {
            return new WP_Error( 'taqi_delete_failed', 'WooCommerce could not permanently delete the linked product. No supplier data was changed.' );
        }

        return array(
            'status'      => 'deleted',
            'product_id'  => $product_id,
            'supplier_id' => (string) $supplier_id,
            'message'     => 'Deleted synced WooCommerce product “' . $title . '”. Supplier API data was not changed. This supplier product can now be imported again.',
        );
    }

    private function looks_like_image_reference( $value ) {
        if ( ! is_scalar( $value ) ) {
            return false;
        }

        $value = trim( (string) $value );
        if ( '' === $value ) {
            return false;
        }

        $path = parse_url( $value, PHP_URL_PATH );
        $path = is_string( $path ) ? $path : $value;

        if ( preg_match( '/\.(?:jpe?g|png|webp|gif|avif|bmp)(?:$|\?)/i', $value ) ) {
            return true;
        }

        return false !== stripos( $path, '/images/' ) || false !== stripos( $path, '/uploads/' );
    }

    private function collect_image_urls_recursive( $value, &$urls, $depth = 0 ) {
        if ( $depth > 7 || null === $value || '' === $value ) {
            return;
        }

        if ( is_string( $value ) ) {
            $trimmed = trim( $value );
            if ( '' === $trimmed ) {
                return;
            }

            $json = json_decode( $trimmed, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $json ) ) {
                $this->collect_image_urls_recursive( $json, $urls, $depth + 1 );
                return;
            }

            if ( false !== strpos( $trimmed, ',' ) || false !== strpos( $trimmed, '|' ) ) {
                $parts = preg_split( '/\s*[,|]\s*/', $trimmed );
                foreach ( $parts as $part ) {
                    $this->collect_image_urls_recursive( $part, $urls, $depth + 1 );
                }
                return;
            }

            if ( $this->looks_like_image_reference( $trimmed ) ) {
                $absolute = $this->absolute_image_url( $trimmed );
                if ( $absolute ) {
                    $urls[] = $absolute;
                }
            }
            return;
        }

        if ( is_scalar( $value ) ) {
            if ( $this->looks_like_image_reference( $value ) ) {
                $absolute = $this->absolute_image_url( (string) $value );
                if ( $absolute ) {
                    $urls[] = $absolute;
                }
            }
            return;
        }

        if ( ! is_array( $value ) ) {
            return;
        }

        // Prefer common image fields first so API ordering is retained.
        foreach ( array( 'product_image', 'product_images', 'thumbnail_img', 'thumbnail', 'image', 'image_url', 'url', 'src', 'path', 'file', 'filename', 'gallery', 'images' ) as $preferred_key ) {
            if ( array_key_exists( $preferred_key, $value ) ) {
                $this->collect_image_urls_recursive( $value[ $preferred_key ], $urls, $depth + 1 );
            }
        }

        // Then inspect nested rows. Non-image scalar IDs are ignored by looks_like_image_reference().
        foreach ( $value as $key => $child ) {
            if ( in_array( $key, array( 'product_image', 'product_images', 'thumbnail_img', 'thumbnail', 'image', 'image_url', 'url', 'src', 'path', 'file', 'filename', 'gallery', 'images' ), true ) ) {
                continue;
            }
            if ( is_array( $child ) || is_string( $child ) ) {
                $this->collect_image_urls_recursive( $child, $urls, $depth + 1 );
            }
        }
    }

    private function normalize_image_urls( $value ) {
        $urls = array();
        $this->collect_image_urls_recursive( $value, $urls, 0 );
        return array_values( array_unique( array_filter( $urls ) ) );
    }

    private function supplier_image_urls( $supplier_product ) {
        if ( ! is_array( $supplier_product ) ) {
            return array();
        }

        $urls = array();
        foreach ( array( 'thumbnail_img', 'thumbnail', 'image', 'product_image', 'product_images', 'images', 'gallery' ) as $key ) {
            if ( ! empty( $supplier_product[ $key ] ) ) {
                foreach ( $this->normalize_image_urls( $supplier_product[ $key ] ) as $url ) {
                    $urls[] = $url;
                }
            }
        }

        return array_values( array_unique( array_filter( $urls ) ) );
    }

    private function absolute_image_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            return '';
        }
        if ( 0 === strpos( $url, '//' ) ) {
            return 'https:' . $url;
        }
        if ( wp_http_validate_url( $url ) ) {
            return $url;
        }
        if ( 0 === strpos( $url, '/' ) || $this->looks_like_image_reference( $url ) ) {
            $settings = $this->settings();
            $parts    = wp_parse_url( $settings['base_url'] );
            if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
                return $parts['scheme'] . '://' . $parts['host'] . '/' . ltrim( $url, '/' );
            }
        }
        return '';
    }

    private function attachment_matches_supplier_url( $attachment_id, $url ) {
        $attachment_id = absint( $attachment_id );
        if ( ! $attachment_id || ! $url ) {
            return false;
        }

        $stored = (string) get_post_meta( $attachment_id, '_taqi_supplier_image_url', true );
        if ( $stored && esc_url_raw( $stored ) === esc_url_raw( $url ) ) {
            return true;
        }

        $supplier_path = wp_parse_url( $url, PHP_URL_PATH );
        $supplier_base = $supplier_path ? rawurldecode( basename( $supplier_path ) ) : '';
        $attached_file = get_attached_file( $attachment_id );
        $attached_base = $attached_file ? basename( $attached_file ) : '';

        return $supplier_base && $attached_base && 0 === strcasecmp( $supplier_base, $attached_base );
    }

    private function find_attachment_by_supplier_url( $url ) {
        if ( ! $url ) {
            return 0;
        }

        $ids = get_posts(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array(
                        'key'   => '_taqi_supplier_image_url',
                        'value' => esc_url_raw( $url ),
                    ),
                ),
            )
        );

        return empty( $ids ) ? 0 : absint( $ids[0] );
    }

    private function sideload_image( $url, $product_id, $description = '' ) {
        if ( ! $url || ! wp_http_validate_url( $url ) ) {
            return new WP_Error( 'taqi_invalid_image_url', 'Invalid image URL.' );
        }

        $existing = $this->find_attachment_by_supplier_url( $url );
        if ( $existing ) {
            return $existing;
        }

        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $image_id = media_sideload_image( $url, $product_id, $description, 'id' );
        if ( ! is_wp_error( $image_id ) ) {
            update_post_meta( $image_id, '_taqi_supplier_image_url', esc_url_raw( $url ) );
            if ( $description ) {
                update_post_meta( $image_id, '_wp_attachment_image_alt', sanitize_text_field( $description ) );
            }
        }

        return $image_id;
    }

    /**
     * Import/sync supplier images without deleting local media.
     * Initial import uses the first supplier image as featured and the rest as gallery.
     * Re-sync keeps existing local images and adds any supplier images that are missing.
     */
    private function sync_product_images( $product, $supplier_product, $name, $merge_existing = true ) {
        $settings = $this->settings();
        if ( 'yes' !== $settings['import_images'] || ! $product || ! ( $product instanceof TAQI_Life_Product ) ) {
            return array( 'supplier_urls' => 0, 'downloaded' => 0, 'gallery_added' => 0 );
        }

        $product_id = $product->get_id();
        if ( ! $product_id ) {
            return array( 'supplier_urls' => 0, 'downloaded' => 0, 'gallery_added' => 0 );
        }

        $urls = $this->supplier_image_urls( $supplier_product );
        if ( empty( $urls ) ) {
            return array( 'supplier_urls' => 0, 'downloaded' => 0, 'gallery_added' => 0 );
        }

        $existing_featured = absint( $product->get_image_id() );
        $existing_gallery  = array_values( array_filter( array_map( 'absint', $product->get_gallery_image_ids() ) ) );
        $existing_media    = array_values( array_unique( array_filter( array_merge( array( $existing_featured ), $existing_gallery ) ) ) );
        $supplier_ids      = array();
        $downloaded        = 0;

        foreach ( $urls as $url ) {
            $existing_id = $this->find_attachment_by_supplier_url( $url );
            if ( ! $existing_id ) {
                foreach ( $existing_media as $media_id ) {
                    if ( $this->attachment_matches_supplier_url( $media_id, $url ) ) {
                        $existing_id = absint( $media_id );
                        update_post_meta( $existing_id, '_taqi_supplier_image_url', esc_url_raw( $url ) );
                        break;
                    }
                }
            }
            $image_id    = $existing_id ? $existing_id : $this->sideload_image( $url, $product_id, $name );
            if ( is_wp_error( $image_id ) || ! $image_id ) {
                continue;
            }
            if ( ! $existing_id ) {
                ++$downloaded;
            }
            $supplier_ids[] = absint( $image_id );
        }

        $supplier_ids = array_values( array_unique( array_filter( $supplier_ids ) ) );
        if ( empty( $supplier_ids ) ) {
            return array( 'supplier_urls' => count( $urls ), 'downloaded' => $downloaded, 'gallery_added' => 0 );
        }

        if ( $merge_existing ) {
            $featured_id = $existing_featured ? $existing_featured : $supplier_ids[0];
            $gallery_ids = array_values( array_unique( array_merge( $existing_gallery, $supplier_ids ) ) );
            $gallery_ids = array_values( array_filter( $gallery_ids, function( $id ) use ( $featured_id ) { return absint( $id ) !== absint( $featured_id ); } ) );
        } else {
            $featured_id = $supplier_ids[0];
            $gallery_ids = array_slice( $supplier_ids, 1 );
        }

        $before_gallery = count( $existing_gallery );
        $product->set_image_id( $featured_id );
        $product->set_gallery_image_ids( $gallery_ids );
        $product->save();

        return array(
            'supplier_urls' => count( $urls ),
            'downloaded'    => $downloaded,
            'gallery_added' => max( 0, count( $gallery_ids ) - $before_gallery ),
        );
    }

    private function normalized_key( $key ) {
        return strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $key ) );
    }

    /**
     * Mohasagor's current product payload exposes `category` but does not
     * necessarily expose a numeric category_id. When only a category name is
     * supplied, build a stable internal mapping key from the name.
     */
    private function category_key_from_name( $name ) {
        $name = sanitize_text_field( (string) $name );
        if ( '' === $name ) {
            return '';
        }

        $slug = sanitize_title( $name );
        if ( '' === $slug ) {
            $slug = md5( $name );
        }

        return 'name:' . $slug;
    }

    private function category_from_scalar( $value, $fallback_name = '' ) {
        if ( ! is_scalar( $value ) ) {
            return array( 'id' => '', 'name' => '' );
        }

        $raw = sanitize_text_field( (string) $value );
        if ( '' === $raw ) {
            return array( 'id' => '', 'name' => '' );
        }

        if ( is_numeric( $raw ) ) {
            return array(
                'id'   => $raw,
                'name' => $fallback_name ? sanitize_text_field( $fallback_name ) : 'Supplier Category #' . $raw,
            );
        }

        return array(
            'id'   => $this->category_key_from_name( $raw ),
            'name' => $raw,
        );
    }

    private function sibling_category_name( $row ) {
        if ( ! is_array( $row ) ) {
            return '';
        }

        foreach ( $row as $key => $value ) {
            $normalized = $this->normalized_key( $key );
            if ( in_array( $normalized, array( 'categoryname', 'catname', 'subcategoryname', 'productcategoryname' ), true ) && is_scalar( $value ) ) {
                $name = sanitize_text_field( (string) $value );
                if ( '' !== $name ) {
                    return $name;
                }
            }
        }

        return '';
    }

    private function supplier_categories_recursive( $value, $depth = 0 ) {
        $found = array();

        if ( ! is_array( $value ) || $depth > 6 ) {
            return $found;
        }

        $sibling_name = $this->sibling_category_name( $value );

        foreach ( $value as $key => $child ) {
            $normalized = $this->normalized_key( $key );

            if ( in_array( $normalized, array( 'categoryid', 'catid', 'productcategoryid', 'subcategoryid' ), true ) && is_scalar( $child ) ) {
                $category = $this->category_from_scalar( $child, $sibling_name );
                if ( '' !== $category['id'] ) {
                    $found[ $category['id'] ] = $category;
                }
            }

            if ( in_array( $normalized, array( 'category', 'productcategory', 'subcategory' ), true ) ) {
                if ( is_array( $child ) ) {
                    $id   = '';
                    $name = '';

                    foreach ( $child as $child_key => $child_value ) {
                        $child_normalized = $this->normalized_key( $child_key );
                        if ( '' === $id && in_array( $child_normalized, array( 'id', 'categoryid', 'catid', 'subcategoryid' ), true ) && is_scalar( $child_value ) ) {
                            $id = sanitize_text_field( (string) $child_value );
                        }
                        if ( '' === $name && in_array( $child_normalized, array( 'name', 'title', 'label', 'categoryname', 'slug' ), true ) && is_scalar( $child_value ) ) {
                            $name = sanitize_text_field( (string) $child_value );
                        }
                    }

                    if ( '' !== $id ) {
                        $found[ $id ] = array(
                            'id'   => $id,
                            'name' => $name ? $name : 'Supplier Category #' . $id,
                        );
                    } elseif ( '' !== $name ) {
                        $name_key = $this->category_key_from_name( $name );
                        if ( '' !== $name_key ) {
                            $found[ $name_key ] = array(
                                'id'   => $name_key,
                                'name' => $name,
                            );
                        }
                    }
                } elseif ( is_scalar( $child ) ) {
                    // Current Mohasagor payload uses a top-level scalar `category`
                    // value. It may be a readable category name rather than an ID.
                    $category = $this->category_from_scalar( $child, $sibling_name );
                    if ( '' !== $category['id'] ) {
                        $found[ $category['id'] ] = $category;
                    }
                }
            }

            if ( is_array( $child ) ) {
                foreach ( $this->supplier_categories_recursive( $child, $depth + 1 ) as $id => $category ) {
                    if ( ! isset( $found[ $id ] ) || 0 === strpos( $found[ $id ]['name'], 'Supplier Category #' ) ) {
                        $found[ $id ] = $category;
                    }
                }
            }
        }

        return $found;
    }

    private function supplier_categories( $product ) {
        if ( ! is_array( $product ) ) {
            return array();
        }

        $found       = array();
        $direct_name = '';

        foreach ( array( 'category_name', 'categoryName', 'cat_name', 'subcategory_name' ) as $name_key ) {
            if ( isset( $product[ $name_key ] ) && is_scalar( $product[ $name_key ] ) ) {
                $direct_name = sanitize_text_field( (string) $product[ $name_key ] );
                if ( '' !== $direct_name ) {
                    break;
                }
            }
        }

        foreach ( array( 'category_id', 'categoryId', 'cat_id', 'product_category_id', 'subcategory_id' ) as $id_key ) {
            if ( isset( $product[ $id_key ] ) && is_scalar( $product[ $id_key ] ) ) {
                $category = $this->category_from_scalar( $product[ $id_key ], $direct_name );
                if ( '' !== $category['id'] ) {
                    $found[ $category['id'] ] = $category;
                }
            }
        }

        // Explicit support for the currently observed API field:
        // category => "Category Name" (or, on some accounts, a numeric value).
        if ( array_key_exists( 'category', $product ) && is_scalar( $product['category'] ) ) {
            $category = $this->category_from_scalar( $product['category'], $direct_name );
            if ( '' !== $category['id'] ) {
                $found[ $category['id'] ] = $category;
            }
        }

        foreach ( $this->supplier_categories_recursive( $product ) as $id => $category ) {
            if ( ! isset( $found[ $id ] ) || 0 === strpos( $found[ $id ]['name'], 'Supplier Category #' ) ) {
                $found[ $id ] = $category;
            }
        }

        return $found;
    }

    private function supplier_category( $product ) {
        $categories = $this->supplier_categories( $product );
        if ( empty( $categories ) ) {
            return array( 'id' => '', 'name' => '' );
        }

        if ( is_array( $product ) ) {
            if ( isset( $product['category_id'] ) && is_scalar( $product['category_id'] ) ) {
                $preferred = $this->category_from_scalar( $product['category_id'] );
                if ( '' !== $preferred['id'] && isset( $categories[ $preferred['id'] ] ) ) {
                    return $categories[ $preferred['id'] ];
                }
            }

            if ( array_key_exists( 'category', $product ) && is_scalar( $product['category'] ) ) {
                $preferred = $this->category_from_scalar( $product['category'] );
                if ( '' !== $preferred['id'] && isset( $categories[ $preferred['id'] ] ) ) {
                    return $categories[ $preferred['id'] ];
                }
            }
        }

        return reset( $categories );
    }

    private function matches_supplier_category_filter( $product, $filter ) {
        $filter = trim( (string) $filter );
        if ( '' === $filter ) { return true; }
        $category = $this->supplier_category( $product );
        return false !== stripos( (string) $category['name'], $filter ) || false !== stripos( (string) $category['id'], $filter );
    }

    private function category_choices() {
        $terms = get_terms( array(
            'taxonomy'   => 'taqi_category',
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        $choices = array();
        foreach ( $terms as $term ) {
            $parts     = array();
            $ancestors = array_reverse( get_ancestors( $term->term_id, 'taqi_category', 'taxonomy' ) );
            foreach ( $ancestors as $ancestor_id ) {
                $ancestor = get_term( $ancestor_id, 'taqi_category' );
                if ( $ancestor && ! is_wp_error( $ancestor ) ) {
                    $parts[] = $ancestor->name;
                }
            }
            $parts[] = $term->name;
            $choices[ (int) $term->term_id ] = implode( ' > ', $parts );
        }

        natcasesort( $choices );
        return $choices;
    }

    private function ensure_category_path( $path ) {
        $path = sanitize_text_field( wp_unslash( (string) $path ) );
        if ( '' === trim( $path ) ) {
            return new WP_Error( 'taqi_empty_category_path', 'Category path is empty.' );
        }

        $segments = preg_split( '/\s*>\s*/', $path );
        $segments = array_values( array_filter( array_map( 'trim', $segments ), 'strlen' ) );
        if ( empty( $segments ) ) {
            return new WP_Error( 'taqi_invalid_category_path', 'Category path is invalid.' );
        }

        $parent_id = 0;
        foreach ( $segments as $segment ) {
            $segment = sanitize_text_field( $segment );
            if ( '' === $segment ) {
                continue;
            }

            $existing = term_exists( $segment, 'taqi_category', $parent_id );
            if ( $existing ) {
                $parent_id = is_array( $existing ) ? absint( $existing['term_id'] ) : absint( $existing );
                continue;
            }

            $created = wp_insert_term( $segment, 'taqi_category', array( 'parent' => $parent_id ) );
            if ( is_wp_error( $created ) ) {
                return $created;
            }
            $parent_id = absint( $created['term_id'] );
        }

        return $parent_id ?: new WP_Error( 'taqi_category_create_failed', 'Could not create the category path.' );
    }

    private function apply_import_category_override( $product_id, $term_id = 0, $path = '' ) {
        $term_id = absint( $term_id );
        if ( '' !== trim( (string) $path ) ) {
            $created = $this->ensure_category_path( $path );
            if ( is_wp_error( $created ) ) {
                return $created;
            }
            $term_id = absint( $created );
        }
        if ( ! $term_id || ! term_exists( $term_id, 'taqi_category' ) ) {
            return true;
        }
        $ids = array( $term_id );
        $this->add_term_with_ancestors( $ids, $term_id );
        wp_set_object_terms( $product_id, array_values( array_unique( $ids ) ), 'taqi_category', false );
        update_post_meta( $product_id, '_taqi_import_category_override', get_term_field( 'name', $term_id, 'taqi_category' ) );
        return true;
    }

    private function add_term_with_ancestors( &$ids, $term_id ) {
        $term_id = absint( $term_id );
        if ( ! $term_id || ! term_exists( $term_id, 'taqi_category' ) ) {
            return;
        }

        $ids[] = $term_id;
        foreach ( get_ancestors( $term_id, 'taqi_category', 'taxonomy' ) as $ancestor_id ) {
            $ids[] = absint( $ancestor_id );
        }
    }

    private function category_rule_matches( $rule, $supplier_product, $supplier_category_key ) {
        if ( empty( $rule['term_id'] ) || empty( $rule['keyword'] ) ) {
            return false;
        }

        $rule_supplier = isset( $rule['supplier_key'] ) ? (string) $rule['supplier_key'] : '*';
        if ( '*' !== $rule_supplier && $rule_supplier !== (string) $supplier_category_key ) {
            return false;
        }

        $field   = isset( $rule['field'] ) ? $rule['field'] : 'name';
        $name    = isset( $supplier_product['name'] ) ? $this->scalar( $supplier_product['name'] ) : '';
        $details = isset( $supplier_product['details'] ) ? wp_strip_all_tags( $this->scalar( $supplier_product['details'] ) ) : '';
        $slug    = isset( $supplier_product['slug'] ) ? $this->scalar( $supplier_product['slug'] ) : '';

        if ( 'slug' === $field ) {
            $haystack = $slug;
        } elseif ( 'name_details' === $field ) {
            $haystack = $name . ' ' . $details;
        } else {
            $haystack = $name;
        }

        return false !== stripos( $haystack, (string) $rule['keyword'] );
    }

    private function apply_category_mapping( $product, $supplier_product ) {
        $category = $this->supplier_category( $supplier_product );
        $ids      = array();
        $key      = isset( $category['id'] ) ? (string) $category['id'] : '';

        if ( '' !== $key ) {
            $map     = get_option( self::OPTION_CATEGORY_MAP, array() );
            $term_id = isset( $map[ $key ] ) ? absint( $map[ $key ] ) : 0;
            if ( $term_id ) {
                $this->add_term_with_ancestors( $ids, $term_id );
            }
        }

        $rules = get_option( self::OPTION_CATEGORY_RULES, array() );
        if ( is_array( $rules ) ) {
            foreach ( $rules as $rule ) {
                if ( is_array( $rule ) && $this->category_rule_matches( $rule, $supplier_product, $key ) ) {
                    $this->add_term_with_ancestors( $ids, absint( $rule['term_id'] ) );
                }
            }
        }

        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
        if ( $ids ) {
            $product->set_category_ids( $ids );
        }
    }

    private function collect_explicit_attribute_ids_recursive( $value, &$ids, $depth = 0 ) {
        if ( ! is_array( $value ) || $depth > 7 ) {
            return;
        }

        foreach ( $value as $key => $child ) {
            $normalized = $this->normalized_key( $key );
            if ( 'attributeid' === $normalized && is_scalar( $child ) ) {
                $id = sanitize_text_field( $this->scalar( $child ) );
                if ( '' !== $id ) {
                    $ids[] = $id;
                }
            }
            if ( is_array( $child ) ) {
                $this->collect_explicit_attribute_ids_recursive( $child, $ids, $depth + 1 );
            }
        }
    }

    private function extract_product_attribute_ids( $product ) {
        $ids = array();
        if ( ! is_array( $product ) ) {
            return $ids;
        }

        // Search only explicit attribute_id keys. Do not reinterpret generic `id`
        // values as attribute IDs because supplier variant rows may use product-specific IDs.
        $this->collect_explicit_attribute_ids_recursive( $product, $ids, 0 );

        return array_values( array_unique( array_filter( array_map( 'strval', $ids ), 'strlen' ) ) );
    }

    private function native_variation_label( $row ) {
        if ( ! is_array( $row ) ) {
            return '';
        }

        foreach ( array( 'variant_name', 'option_value', 'attribute_value', 'value', 'option', 'variant', 'label', 'size', 'color', 'colour', 'style', 'material', 'name' ) as $key ) {
            if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) {
                $value = sanitize_text_field( $this->scalar( $row[ $key ] ) );
                if ( '' !== $value ) {
                    return $value;
                }
            }
        }

        foreach ( array( 'variant', 'option', 'attribute' ) as $nested_key ) {
            if ( isset( $row[ $nested_key ] ) && is_array( $row[ $nested_key ] ) ) {
                foreach ( array( 'name', 'label', 'value', 'option', 'variant_name', 'attribute_value' ) as $key ) {
                    if ( isset( $row[ $nested_key ][ $key ] ) && is_scalar( $row[ $nested_key ][ $key ] ) ) {
                        $value = sanitize_text_field( $this->scalar( $row[ $nested_key ][ $key ] ) );
                        if ( '' !== $value ) {
                            return $value;
                        }
                    }
                }
            }
        }

        return '';
    }

    private function native_variation_attribute_name( $row ) {
        if ( ! is_array( $row ) ) {
            return '';
        }

        foreach ( array( 'attribute_name', 'attribute_label', 'option_name', 'attribute' ) as $key ) {
            if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) {
                $value = sanitize_text_field( $this->scalar( $row[ $key ] ) );
                if ( '' !== $value ) {
                    return $value;
                }
            }
        }

        foreach ( array( 'size' => 'Size', 'color' => 'Color', 'colour' => 'Color', 'style' => 'Style', 'material' => 'Material' ) as $key => $label ) {
            if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
                return $label;
            }
        }

        if ( isset( $row['attribute'] ) && is_array( $row['attribute'] ) ) {
            foreach ( array( 'name', 'label', 'attribute_name' ) as $key ) {
                if ( isset( $row['attribute'][ $key ] ) && is_scalar( $row['attribute'][ $key ] ) ) {
                    $value = sanitize_text_field( $this->scalar( $row['attribute'][ $key ] ) );
                    if ( '' !== $value ) {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    private function extract_variation_rows( $product ) {
        foreach ( array( 'product_variant', 'product_variants', 'variants', 'variations' ) as $key ) {
            if ( ! empty( $product[ $key ] ) ) {
                $rows = $this->decode_arrayish( $product[ $key ] );
                if ( isset( $rows['variant_id'] ) || isset( $rows['id'] ) || isset( $rows['name'] ) ) {
                    $rows = array( $rows );
                }
                if ( is_array( $rows ) ) {
                    return array_values( array_filter( $rows, 'is_array' ) );
                }
            }
        }
        return array();
    }

    private function has_variations( $product ) {
        return ! empty( $this->extract_variation_rows( $product ) );
    }

    private function readable_attributes_from_row( $row, $supplier_product ) {
        $attributes = array();

        if ( isset( $row['attributes'] ) ) {
            $raw = $this->decode_arrayish( $row['attributes'] );
            if ( $raw ) {
                $is_list = array_keys( $raw ) === range( 0, count( $raw ) - 1 );
                if ( $is_list ) {
                    foreach ( $raw as $item ) {
                        if ( ! is_array( $item ) ) {
                            continue;
                        }
                        $name  = '';
                        $value = '';
                        foreach ( array( 'name', 'attribute_name', 'label', 'attribute' ) as $key ) {
                            if ( isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) {
                                $name = sanitize_text_field( $this->scalar( $item[ $key ] ) );
                                if ( $name ) {
                                    break;
                                }
                            }
                        }
                        foreach ( array( 'option', 'value', 'attribute_value', 'variant_name', 'variant' ) as $key ) {
                            if ( isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) {
                                $value = sanitize_text_field( $this->scalar( $item[ $key ] ) );
                                if ( $value ) {
                                    break;
                                }
                            }
                        }
                        if ( $name && $value ) {
                            $attributes[ $name ] = $value;
                        }
                    }
                } else {
                    foreach ( $raw as $name => $value ) {
                        if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                            $attributes[ sanitize_text_field( (string) $name ) ] = sanitize_text_field( (string) $value );
                        }
                    }
                }
            }
        }

        foreach ( array( 'size' => 'Size', 'color' => 'Color', 'colour' => 'Color', 'style' => 'Style', 'material' => 'Material' ) as $key => $label ) {
            if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
                $attributes[ $label ] = sanitize_text_field( (string) $row[ $key ] );
            }
        }

        // Mohasagor Product API rows commonly use { attribute: "Color", variant: "black" }.
        if ( isset( $row['attribute'], $row['variant'] ) && is_scalar( $row['attribute'] ) && is_scalar( $row['variant'] ) ) {
            $attribute_name = sanitize_text_field( $this->scalar( $row['attribute'] ) );
            $option_value   = sanitize_text_field( $this->scalar( $row['variant'] ) );
            if ( $attribute_name && $option_value ) {
                $attributes[ $attribute_name ] = $option_value;
            }
        }

        if ( empty( $attributes ) && ! empty( $row['attribute_name'] ) ) {
            $value = '';
            foreach ( array( 'option', 'value', 'attribute_value', 'variant_name' ) as $key ) {
                if ( ! empty( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) {
                    $value = sanitize_text_field( $this->scalar( $row[ $key ] ) );
                    break;
                }
            }
            if ( $value ) {
                $attributes[ sanitize_text_field( $this->scalar( $row['attribute_name'] ) ) ] = $value;
            }
        }

        if ( empty( $attributes ) ) {
            $variant_id = '';
            if ( isset( $row['variant_id'] ) ) {
                $variant_id = sanitize_text_field( $this->scalar( $row['variant_id'] ) );
            } elseif ( isset( $row['id'] ) ) {
                $variant_id = sanitize_text_field( $this->scalar( $row['id'] ) );
            }

            if ( '' !== $variant_id ) {
                $variant_map   = get_option( self::OPTION_VARIANT_MAP, array() );
                $attribute_map = get_option( self::OPTION_ATTRIBUTE_MAP, array() );
                $attribute_ids = $this->extract_product_attribute_ids( $supplier_product );
                $attribute_id  = isset( $row['attribute_id'] ) ? sanitize_text_field( $this->scalar( $row['attribute_id'] ) ) : ( isset( $attribute_ids[0] ) ? $attribute_ids[0] : '' );

                $attribute_name = '';
                if ( $attribute_id && ! empty( $attribute_map[ $attribute_id ] ) ) {
                    $attribute_name = sanitize_text_field( $attribute_map[ $attribute_id ] );
                }

                $option_value = '';
                if ( ! empty( $variant_map[ $variant_id ] ) && is_array( $variant_map[ $variant_id ] ) ) {
                    if ( ! empty( $variant_map[ $variant_id ]['attribute_name'] ) ) {
                        $attribute_name = sanitize_text_field( $variant_map[ $variant_id ]['attribute_name'] );
                    }
                    if ( ! empty( $variant_map[ $variant_id ]['option_value'] ) ) {
                        $option_value = sanitize_text_field( $variant_map[ $variant_id ]['option_value'] );
                    }
                }

                if ( $attribute_name && $option_value ) {
                    $attributes[ $attribute_name ] = $option_value;
                }
            }
        }

        if ( empty( $attributes ) ) {
            foreach ( array( 'variant_name', 'name', 'label', 'value' ) as $key ) {
                if ( ! empty( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) {
                    $attributes['Variant'] = sanitize_text_field( $this->scalar( $row[ $key ] ) );
                    break;
                }
            }
        }

        return array_filter( $attributes, 'strlen' );
    }

    private function build_variation_model( $supplier_product ) {
        $rows       = $this->extract_variation_rows( $supplier_product );
        $model_rows = array();
        $options    = array();

        foreach ( $rows as $row ) {
            $attributes = $this->readable_attributes_from_row( $row, $supplier_product );
            if ( empty( $attributes ) ) {
                continue;
            }

            foreach ( $attributes as $name => $value ) {
                if ( ! isset( $options[ $name ] ) ) {
                    $options[ $name ] = array();
                }
                $options[ $name ][] = $value;
            }

            $model_rows[] = array(
                'raw'        => $row,
                'attributes' => $attributes,
            );
        }

        foreach ( $options as $name => $values ) {
            $options[ $name ] = array_values( array_unique( array_filter( array_map( 'strval', $values ), 'strlen' ) ) );
        }

        return array(
            'rows'       => $model_rows,
            'attributes' => $options,
            'raw_count'  => count( $rows ),
        );
    }

    private function apply_stock_data( $product, $row ) {
        foreach ( array( 'stock_quantity', 'quantity', 'stock', 'qty' ) as $key ) {
            if ( isset( $row[ $key ] ) && is_numeric( $row[ $key ] ) ) {
                $qty = max( 0, (int) $row[ $key ] );
                $product->set_manage_stock( true );
                $product->set_stock_quantity( $qty );
                $product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
                return;
            }
        }

        if ( ! empty( $row['stock_status'] ) ) {
            $status = strtolower( sanitize_text_field( $this->scalar( $row['stock_status'] ) ) );
            if ( in_array( $status, array( 'instock', 'in_stock', 'available', '1' ), true ) ) {
                $product->set_stock_status( 'instock' );
            } elseif ( in_array( $status, array( 'outofstock', 'out_of_stock', 'unavailable', '0' ), true ) ) {
                $product->set_stock_status( 'outofstock' );
            }
        }
    }

    private function set_common_product_data( $product, $supplier_product, $name, $supplier_id, $sku ) {
        $settings = $this->settings();
        $status   = in_array( $settings['import_status'], array( 'draft', 'publish', 'pending', 'private' ), true ) ? $settings['import_status'] : 'draft';

        $product->set_name( $name );
        $product->set_status( $status );

        $description = isset( $supplier_product['details'] ) ? wp_kses_post( $this->scalar( $supplier_product['details'] ) ) : '';
        if ( $description ) {
            $product->set_description( $description );
        }

        if ( $sku && ! $this->sku_owner( $sku ) ) {
            $product->set_sku( $sku );
        }

        $this->apply_category_mapping( $product, $supplier_product );
        $this->apply_stock_data( $product, $supplier_product );

        $product->update_meta_data( '_taqi_supplier', $this->supplier_key() );
        $product->update_meta_data( '_taqi_supplier_name', sanitize_text_field( $settings['supplier_name'] ) );
        $product->update_meta_data( '_taqi_supplier_id', (string) $supplier_id );
        $product->update_meta_data( '_taqi_supplier_code', $sku );

        $supplier_category = $this->supplier_category( $supplier_product );
        if ( '' !== $supplier_category['id'] ) {
            $product->update_meta_data( '_taqi_supplier_category_id', $supplier_category['id'] );
            $product->update_meta_data( '_taqi_supplier_category_name', $supplier_category['name'] );
        }

        $now = current_time( 'mysql' );
        $product->update_meta_data( '_taqi_last_imported_at', $now );
        $product->update_meta_data( '_taqi_last_sync_at', $now );
        $product->update_meta_data( '_taqi_sync_status', 'active' );
        $product->delete_meta_data( '_taqi_sync_cancelled_at' );
        $product->update_meta_data( '_taqi_supplier_raw_payload', wp_json_encode( $supplier_product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

        foreach ( array( 'price', 'reselling_price', 'sale_price', 'category_id', 'status', 'stock_status' ) as $meta_key ) {
            if ( isset( $supplier_product[ $meta_key ] ) && is_scalar( $supplier_product[ $meta_key ] ) ) {
                $product->update_meta_data( '_taqi_supplier_' . $meta_key, sanitize_text_field( (string) $supplier_product[ $meta_key ] ) );
            }
        }
    }

    private function import_product_images( $product, $supplier_product, $name ) {
        return $this->sync_product_images( $product, $supplier_product, $name, false );
    }

    private function create_simple_product( $supplier_product, $name, $supplier_id, $sku ) {
        $product = new TAQI_Life_Product( 'simple' );
        $this->set_common_product_data( $product, $supplier_product, $name, $supplier_id, $sku );

        $regular = $this->product_regular_price( $supplier_product );
        if ( null !== $regular ) {
            $product->set_regular_price( number_format( $regular, 2, '.', '' ) );
        }
        $sale = $this->product_sale_price( $supplier_product, $regular );
        if ( null !== $sale ) {
            $product->set_sale_price( number_format( $sale, 2, '.', '' ) );
        }

        if ( $this->has_variations( $supplier_product ) ) {
            $model = $this->build_variation_model( $supplier_product );
            $product->update_meta_data( '_taqi_has_supplier_variations', 'yes' );
            if ( empty( $model['rows'] ) ) {
                $product->update_meta_data( '_taqi_variation_import_note', 'Supplier variation references were detected, but readable Size/Color values are not available. Scan WooCommerce → Variation Mapping. If they remain unresolved, keep the API import safe and configure local WooCommerce variations manually if needed.' );
            }
        }

        $product_id = $product->save();
        if ( ! $product_id ) {
            return new WP_Error( 'taqi_import_failed', 'The standalone product could not be saved.' );
        }

        $this->import_product_images( $product, $supplier_product, $name );
        return $product_id;
    }

    private function create_variable_product( $supplier_product, $model, $name, $supplier_id, $sku ) {
        $product = new TAQI_Life_Product( 'variable' );
        $this->set_common_product_data( $product, $supplier_product, $name, $supplier_id, $sku );

        $attribute_objects = array();
        $position          = 0;
        foreach ( $model['attributes'] as $attribute_name => $values ) {
            if ( empty( $values ) ) {
                continue;
            }
            $attribute_objects[ sanitize_text_field( $attribute_name ) ] = array_values( $values );
            ++$position;
        }

        if ( empty( $attribute_objects ) ) {
            return new WP_Error( 'taqi_no_variation_attributes', 'No readable supplier variation attributes were available.' );
        }

        $product->set_attributes( $attribute_objects );
        $product->update_meta_data( '_taqi_has_supplier_variations', 'yes' );
        $product->update_meta_data( '_taqi_variation_import_note', 'Imported as a standalone variable product by TAQI LIFE Dropshipping.' );
        $product_id = $product->save();

        if ( ! $product_id ) {
            return new WP_Error( 'taqi_import_failed', 'The standalone variable product could not be saved.' );
        }

        $parent_regular = $this->product_regular_price( $supplier_product );
        $created        = 0;

        foreach ( $model['rows'] as $index => $model_row ) {
            $raw        = $model_row['raw'];
            $attributes = array();
            foreach ( $model_row['attributes'] as $attribute_name => $value ) {
                $attributes[ sanitize_title( $attribute_name ) ] = sanitize_text_field( $value );
            }

            if ( empty( $attributes ) ) {
                continue;
            }

            $variation = new TAQI_Life_Product( 'variation' );
            $variation->set_parent_id( $product_id );
            $variation->set_attributes( $attributes );

            $variation_regular = $this->product_regular_price( $raw );
            if ( null === $variation_regular ) {
                $variation_regular = $parent_regular;
            }
            if ( null !== $variation_regular ) {
                $variation->set_regular_price( number_format( $variation_regular, 2, '.', '' ) );
            }

            $variation_sale = $this->product_sale_price( $raw, $variation_regular );
            if ( null !== $variation_sale ) {
                $variation->set_sale_price( number_format( $variation_sale, 2, '.', '' ) );
            }

            $variation_sku = '';
            foreach ( array( 'sku', 'product_code', 'code' ) as $key ) {
                if ( ! empty( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ) {
                    $variation_sku = sanitize_text_field( $this->scalar( $raw[ $key ] ) );
                    break;
                }
            }
            if ( $variation_sku && ! $this->sku_owner( $variation_sku ) ) {
                $variation->set_sku( $variation_sku );
            }

            $this->apply_stock_data( $variation, $raw );
            $variation->set_menu_order( (int) $index );

            if ( isset( $raw['variant_id'] ) ) {
                $variation->update_meta_data( '_taqi_supplier_variant_id', sanitize_text_field( $this->scalar( $raw['variant_id'] ) ) );
            } elseif ( isset( $raw['id'] ) ) {
                $variation->update_meta_data( '_taqi_supplier_variant_id', sanitize_text_field( $this->scalar( $raw['id'] ) ) );
            }

            $variation_id = $variation->save();
            if ( $variation_id ) {
                ++$created;
            }
        }

        if ( 0 === $created ) {
            wp_delete_post( $product_id, true );
            return new WP_Error( 'taqi_variation_creation_failed', 'No WooCommerce variations could be created from the supplier data.' );
        }

        $product = new TAQI_Life_Product( 'variable', $product_id );
        if ( $product ) {
            $this->import_product_images( $product, $supplier_product, $name );
        }

        return $product_id;
    }

    private function import_product( $supplier_product, $api_page = 0, $import_category_id = 0, $import_category_path = '' ) {
        $supplier_id = $this->supplier_product_id( $supplier_product );
        if ( '' === $supplier_id ) {
            return new WP_Error( 'taqi_missing_supplier_id', 'Supplier product does not contain a product ID.' );
        }

        $import_marker_key = 'taqi_imported_' . md5( $this->supplier_key() . '|' . $supplier_id );
        $marked_product_id = absint( get_transient( $import_marker_key ) );
        if ( $marked_product_id && 'taqi_product' === get_post_type( $marked_product_id ) ) {
            return array(
                'status'      => 'duplicate',
                'product_id'  => $marked_product_id,
                'supplier_id' => $supplier_id,
                'message'     => 'Already imported and actively linked.',
            );
        }

        $existing_id = $this->find_imported_product_id( $supplier_id );
        if ( $existing_id ) {
            set_transient( $import_marker_key, $existing_id, DAY_IN_SECONDS );
            return array(
                'status'      => 'duplicate',
                'product_id'  => $existing_id,
                'supplier_id' => $supplier_id,
                'message'     => 'Already imported and actively linked.',
            );
        }

        $cancelled_id = $this->find_cancelled_product_id( $supplier_id );
        if ( $cancelled_id ) {
            return new WP_Error(
                'taqi_cancelled_product_exists',
                'This supplier product was previously imported and its sync is cancelled. Use “Re-link & Sync” instead of importing a duplicate.'
            );
        }

        $trashed_id = $this->find_trashed_linked_product_id( $supplier_id );
        if ( $trashed_id ) {
            return new WP_Error(
                'taqi_trashed_product_exists',
                'A linked WooCommerce product for this supplier item exists in Trash. Restore it or permanently delete it before importing again.'
            );
        }

        $name = isset( $supplier_product['name'] ) ? sanitize_text_field( $this->scalar( $supplier_product['name'] ) ) : '';
        if ( '' === $name ) {
            $name = 'Supplier Product ' . $supplier_id;
        }

        $sku = isset( $supplier_product['product_code'] ) ? sanitize_text_field( $this->scalar( $supplier_product['product_code'] ) ) : '';

        if ( $sku ) {
            $sku_owner = $this->sku_owner( $sku );
            if ( $sku_owner ) {
                return new WP_Error(
                    'taqi_sku_conflict',
                    'Import blocked: supplier code/SKU “' . $sku . '” is already used by WooCommerce product ID ' . absint( $sku_owner ) . '. This validation prevents duplicate products. Edit/delete/re-link the existing product first.'
                );
            }
        }

        $settings = $this->settings();
        $model    = $this->build_variation_model( $supplier_product );
        $use_variable = 'yes' === $settings['import_variations'] && $this->has_variations( $supplier_product ) && ! empty( $model['rows'] ) && ! empty( $model['attributes'] );

        if ( $use_variable ) {
            $product_id = $this->create_variable_product( $supplier_product, $model, $name, $supplier_id, $sku );
            $type       = 'variable';
        } else {
            $product_id = $this->create_simple_product( $supplier_product, $name, $supplier_id, $sku );
            $type       = 'simple';
        }

        if ( is_wp_error( $product_id ) ) {
            return $product_id;
        }

        $category_override = $this->apply_import_category_override( $product_id, $import_category_id, $import_category_path );
        if ( is_wp_error( $category_override ) ) {
            wp_delete_post( $product_id, true );
            return $category_override;
        }

        if ( $api_page > 0 ) {
            update_post_meta( $product_id, '_taqi_supplier_api_page', absint( $api_page ) );
        }
        update_post_meta( $product_id, '_taqi_sync_status', 'active' );
        update_post_meta( $product_id, '_taqi_last_sync_at', current_time( 'mysql' ) );
        set_transient( $import_marker_key, $product_id, DAY_IN_SECONDS );

        return array(
            'status'      => 'imported',
            'product_id'  => $product_id,
            'supplier_id' => $supplier_id,
            'type'        => $type,
            'message'     => 'Imported successfully and linked for synchronization.',
        );
    }

    public function dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings  = $this->settings();
        $last_test = get_option( self::OPTION_LAST_TEST, array() );
        ?>
        <div class="wrap">
            <h1>TAQI LIFE Dropshipping</h1>
            <p>WooCommerce connector for <?php echo esc_html( $settings['supplier_name'] ); ?>.</p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:1200px;margin-top:20px;">
                <div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;">
                    <h2 style="margin-top:0;">API Connection</h2>
                    <?php if ( ! empty( $last_test['success'] ) ) : ?>
                        <p style="font-size:18px;"><strong>Connected</strong> (HTTP <?php echo esc_html( $last_test['http_code'] ); ?>)</p>
                        <p>Page 1 products: <?php echo esc_html( $last_test['product_count'] ); ?><br>Last page: <?php echo esc_html( $last_test['last_page'] ); ?></p>
                    <?php else : ?>
                        <p><strong>Not tested / disconnected</strong></p>
                    <?php endif; ?>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=taqi-dropshipping-settings' ) ); ?>">API Settings</a>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;">
                    <h2 style="margin-top:0;">Supplier Products</h2>
                    <p>Browse supplier catalog and selectively import products.</p>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=taqi-dropshipping-products' ) ); ?>">Open Products</a>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;">
                    <h2 style="margin-top:0;">Category Mapping</h2>
                    <p>Map supplier category IDs to your WooCommerce categories.</p>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=taqi-dropshipping-categories' ) ); ?>">Map Categories</a>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;">
                    <h2 style="margin-top:0;">Variation Mapping</h2>
                    <p>Map supplier attribute/variant IDs to Size, Color and other customer-facing values.</p>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=taqi-dropshipping-variations' ) ); ?>">Map Variations</a>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;">
                    <h2 style="margin-top:0;">Pricing Rules</h2>
                    <p>Use supplier price directly or add percentage/fixed markup.</p>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=taqi-dropshipping-pricing' ) ); ?>">Pricing Rules</a>
                </div>
            </div>

            <div class="notice notice-info inline" style="margin:22px 0 0;max-width:1160px;">
                <p><strong>v1.3.8:</strong> keeps the working Product API isolated and adds safer product lifecycle controls, confirmed Delete, image merge re-sync, and conservative variation diagnostics. Re-sync preserves local title/description/images while adding missing supplier images and refreshing supplier-controlled price, stock, mapped categories and metadata.</p>
                <p><strong>Category safety:</strong> optional Category/Subcategory discovery remains GET-only and isolated. Manual TAQI LIFE category paths/rules never change or interrupt the Product API.</p>
            </div>
        </div>
        <?php
    }

    private function process_settings_save() {
        if ( empty( $_POST['taqi_save_settings'] ) ) {
            return '';
        }
        check_admin_referer( 'taqi_save_settings_action', 'taqi_settings_nonce' );

        $current = $this->settings();
        $new     = $current;
        $new['supplier_name'] = isset( $_POST['supplier_name'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier_name'] ) ) : $current['supplier_name'];
        $new['base_url']      = isset( $_POST['base_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['base_url'] ) ) ) : $current['base_url'];
        $new['category_endpoint'] = isset( $_POST['category_endpoint'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['category_endpoint'] ) ) ) : $current['category_endpoint'];

        $api_key = isset( $_POST['api_key'] ) ? trim( wp_unslash( $_POST['api_key'] ) ) : '';
        if ( '' !== $api_key ) {
            $new['api_key'] = sanitize_text_field( $api_key );
        }
        $secret_key = isset( $_POST['secret_key'] ) ? trim( wp_unslash( $_POST['secret_key'] ) ) : '';
        if ( '' !== $secret_key ) {
            $new['secret_key'] = sanitize_text_field( $secret_key );
        }

        $import_status = isset( $_POST['import_status'] ) ? sanitize_key( wp_unslash( $_POST['import_status'] ) ) : 'draft';
        $new['import_status'] = in_array( $import_status, array( 'draft', 'publish', 'pending', 'private' ), true ) ? $import_status : 'draft';
        $new['import_images']     = ! empty( $_POST['import_images'] ) ? 'yes' : 'no';
        $new['import_variations'] = ! empty( $_POST['import_variations'] ) ? 'yes' : 'no';

        update_option( self::OPTION_SETTINGS, $new, false );
        return 'API/import settings saved.';
    }

    private function process_test_connection() {
        if ( empty( $_POST['taqi_test_connection'] ) ) {
            return null;
        }
        check_admin_referer( 'taqi_test_connection_action', 'taqi_test_nonce' );

        $data = $this->api_request_products( 1 );
        if ( is_wp_error( $data ) ) {
            update_option(
                self::OPTION_LAST_TEST,
                array(
                    'success' => false,
                    'message' => $data->get_error_message(),
                    'checked' => current_time( 'mysql' ),
                ),
                false
            );
            return $data;
        }

        $products  = $this->extract_products( $data );
        $last_page = $this->extract_last_page( $data );
        $result    = array(
            'success'       => true,
            'http_code'     => 200,
            'product_count' => count( $products ),
            'last_page'     => $last_page,
            'checked'       => current_time( 'mysql' ),
        );
        update_option( self::OPTION_LAST_TEST, $result, false );
        return $result;
    }

    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $save_message = $this->process_settings_save();
        $test_result  = $this->process_test_connection();
        $settings     = $this->settings();
        ?>
        <div class="wrap">
            <h1>TAQI LIFE Dropshipping — API Settings</h1>

            <?php if ( $save_message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $save_message ); ?></p></div>
            <?php endif; ?>
            <?php if ( is_wp_error( $test_result ) ) : ?>
                <div class="notice notice-error"><p><strong>Connection failed.</strong> <?php echo esc_html( $test_result->get_error_message() ); ?></p></div>
            <?php elseif ( is_array( $test_result ) && ! empty( $test_result['success'] ) ) : ?>
                <div class="notice notice-success"><p><strong>API connection successful. HTTP 200.</strong> Products received on page 1: <?php echo esc_html( $test_result['product_count'] ); ?>. Last page: <?php echo esc_html( $test_result['last_page'] ); ?>.</p></div>
            <?php endif; ?>

            <form method="post" style="max-width:900px;background:#fff;border:1px solid #dcdcde;padding:22px;margin-top:18px;">
                <?php wp_nonce_field( 'taqi_save_settings_action', 'taqi_settings_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label for="supplier_name">Supplier Name</label></th><td><input name="supplier_name" id="supplier_name" type="text" class="regular-text" value="<?php echo esc_attr( $settings['supplier_name'] ); ?>"></td></tr>
                    <tr><th><label for="base_url">API Base URL</label></th><td><input name="base_url" id="base_url" type="url" class="regular-text code" value="<?php echo esc_attr( $settings['base_url'] ); ?>"><p class="description">Example: https://mohasagor.com.bd/api/reseller</p></td></tr>
                    <tr><th><label for="category_endpoint">Category API Endpoint <span style="font-weight:normal;">(optional)</span></label></th><td><input name="category_endpoint" id="category_endpoint" type="text" class="regular-text code" value="<?php echo esc_attr( $settings['category_endpoint'] ); ?>" placeholder="category or categories"><p class="description">Optional read-only endpoint/path. Leave blank if the supplier has not documented one. This setting never replaces the Product API endpoint.</p></td></tr>
                    <tr><th><label for="api_key">API Key</label></th><td><input name="api_key" id="api_key" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $settings['api_key'] ? 'Saved — leave blank to keep existing key' : 'Enter API Key' ); ?>"></td></tr>
                    <tr><th><label for="secret_key">Secret Key</label></th><td><input name="secret_key" id="secret_key" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $settings['secret_key'] ? 'Saved — leave blank to keep existing secret' : 'Enter Secret Key' ); ?>"></td></tr>
                    <tr><th><label for="import_status">Imported Product Status</label></th><td><select name="import_status" id="import_status"><option value="draft" <?php selected( $settings['import_status'], 'draft' ); ?>>Draft (recommended)</option><option value="publish" <?php selected( $settings['import_status'], 'publish' ); ?>>Published</option><option value="pending" <?php selected( $settings['import_status'], 'pending' ); ?>>Pending Review</option><option value="private" <?php selected( $settings['import_status'], 'private' ); ?>>Private</option></select></td></tr>
                    <tr><th>Images</th><td><label><input type="checkbox" name="import_images" value="1" <?php checked( $settings['import_images'], 'yes' ); ?>> Download supplier images into WordPress Media Library</label></td></tr>
                    <tr><th>Variations</th><td><label><input type="checkbox" name="import_variations" value="1" <?php checked( $settings['import_variations'], 'yes' ); ?>> Create WooCommerce variable products when readable/mapped variation values are available</label><p class="description">Scan Variation Mapping first. Unlabelled raw IDs are treated as diagnostics only and will not be guessed as Size/Color.</p></td></tr>
                </table>
                <p class="submit"><button type="submit" name="taqi_save_settings" value="1" class="button button-primary">Save API Settings</button></p>
            </form>

            <form method="post" style="margin-top:16px;">
                <?php wp_nonce_field( 'taqi_test_connection_action', 'taqi_test_nonce' ); ?>
                <button type="submit" name="taqi_test_connection" value="1" class="button button-secondary">Test API Connection</button>
            </form>
        </div>
        <?php
    }

    public function pricing_rules_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $message = '';
        $error   = '';

        if ( ! empty( $_POST['taqi_save_pricing'] ) ) {
            check_admin_referer( 'taqi_save_pricing_action', 'taqi_pricing_nonce' );
            $settings = $this->settings();

            $minimum_percent = isset( $_POST['minimum_markup_percent'] )
                ? max( 0, (float) wp_unslash( $_POST['minimum_markup_percent'] ) )
                : 4.08;
            $taqi_percent = isset( $_POST['taqi_markup_percent'] )
                ? max( 0, (float) wp_unslash( $_POST['taqi_markup_percent'] ) )
                : 20;

            // Business validation: TAQI's normal markup must not be lower than the
            // configured minimum markup. Reject the save instead of silently changing
            // the user's percentages.
            if ( $taqi_percent < $minimum_percent ) {
                $error = sprintf(
                    'TAQI Markup %% (%.2f%%) cannot be lower than Minimum Markup %% (%.2f%%). Pricing settings were not changed.',
                    $taqi_percent,
                    $minimum_percent
                );
            } else {
                $rounding = isset( $_POST['price_rounding'] ) ? sanitize_key( wp_unslash( $_POST['price_rounding'] ) ) : 'none';

                $settings['pricing_model_version']  = '3';
                $settings['price_mode']             = 'taqi_percent';
                $settings['minimum_markup_percent'] = (string) $minimum_percent;
                $settings['taqi_markup_percent']    = (string) $taqi_percent;
                $settings['markup_percent']         = (string) $taqi_percent; // compatibility mirror only.
                $settings['fixed_markup']           = '0';
                $settings['price_rounding']         = in_array( $rounding, array( 'none', '10', '50', '100' ), true ) ? $rounding : 'none';
                $settings['enforce_minimum_price']  = ! empty( $_POST['enforce_minimum_price'] ) ? 'yes' : 'no';
                $settings['cap_at_maximum_price']   = ! empty( $_POST['cap_at_maximum_price'] ) ? 'yes' : 'no';
                $settings['price_source']           = 'price';
                $settings['cost_source']            = 'sale_price';
                $settings['import_supplier_sale']   = 'no';

                update_option( self::OPTION_SETTINGS, $settings, false );
                $message = 'Pricing rules saved. Minimum % controls the lowest allowed price; TAQI % controls the normal TAQI LIFE selling price.';
            }
        }

        $settings = $this->settings();
        $example  = array( 'sale_price' => 490, 'price' => 690 );
        $example_pricing = $this->pricing_breakdown( $example );
        ?>
        <div class="wrap">
            <h1>Pricing Rules</h1>
            <?php if ( $message ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

            <div class="notice notice-info inline" style="max-width:1000px;margin-top:16px;">
                <p><strong>Mohasagor mapping:</strong> <code>sale_price</code> = <strong>Cost Price</strong>; <code>price</code> = <strong>Maximum Selling Price</strong>.</p>
                <p><strong>Your control:</strong> Minimum Selling Price = Cost + <strong>Minimum %</strong>; TAQI LIFE Selling Price = Cost + <strong>TAQI %</strong>. Product API values are never changed by these settings.</p>
            </div>

            <form method="post" style="max-width:1000px;background:#fff;border:1px solid #dcdcde;padding:22px;margin-top:18px;">
                <?php wp_nonce_field( 'taqi_save_pricing_action', 'taqi_pricing_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Supplier Cost Field</th>
                        <td><code>sale_price</code> <strong>= Cost Price</strong><p class="description">Read-only supplier value.</p></td>
                    </tr>
                    <tr>
                        <th>Supplier Maximum Field</th>
                        <td><code>price</code> <strong>= Maximum Selling Price</strong><p class="description">Read-only supplier value and optional upper safety limit.</p></td>
                    </tr>
                    <tr>
                        <th><label for="minimum_markup_percent">Minimum Markup %</label></th>
                        <td>
                            <input type="number" step="0.01" min="0" name="minimum_markup_percent" id="minimum_markup_percent" value="<?php echo esc_attr( $settings['minimum_markup_percent'] ); ?>"> %
                            <p class="description">Defines the minimum permitted selling price. Example: Cost ৳490 + 4.08% = Minimum ৳510 (rounded up to whole BDT).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="taqi_markup_percent">TAQI Markup %</label></th>
                        <td>
                            <input type="number" step="0.01" min="0" name="taqi_markup_percent" id="taqi_markup_percent" value="<?php echo esc_attr( $settings['taqi_markup_percent'] ); ?>"> %
                            <p class="description"><strong>This is the normal TAQI LIFE selling-price percentage.</strong> Example: Cost ৳490 + 20% = TAQI ৳588.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="price_rounding">Round TAQI LIFE Price</label></th>
                        <td>
                            <select name="price_rounding" id="price_rounding">
                                <option value="none" <?php selected( $settings['price_rounding'], 'none' ); ?>>No rounding</option>
                                <option value="10" <?php selected( $settings['price_rounding'], '10' ); ?>>Nearest 10</option>
                                <option value="50" <?php selected( $settings['price_rounding'], '50' ); ?>>Nearest 50</option>
                                <option value="100" <?php selected( $settings['price_rounding'], '100' ); ?>>Nearest 100</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Price Safety</th>
                        <td>
                            <label><input type="checkbox" name="enforce_minimum_price" value="1" <?php checked( $settings['enforce_minimum_price'], 'yes' ); ?>> Never allow TAQI price below calculated Minimum Selling Price</label><br>
                            <label><input type="checkbox" name="cap_at_maximum_price" value="1" <?php checked( $settings['cap_at_maximum_price'], 'yes' ); ?>> Never allow TAQI price above supplier Maximum Selling Price</label>
                            <p class="description">Recommended: keep both enabled. A price below Cost Price is always blocked.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" name="taqi_save_pricing" value="1" class="button button-primary">Save Pricing Rules</button></p>
            </form>

            <div style="max-width:1000px;background:#fff;border:1px solid #dcdcde;padding:18px;margin-top:18px;">
                <h2 style="margin-top:0;">Example Price Preview</h2>
                <table class="widefat striped" style="max-width:800px;"><tbody>
                    <tr><th>Cost Price</th><td><?php echo wp_kses_post( $this->format_money( $example_pricing['cost'] ) ); ?></td><td><code>sale_price</code></td></tr>
                    <tr><th>Minimum Markup</th><td><?php echo esc_html( number_format_i18n( (float) $settings['minimum_markup_percent'], 2 ) ); ?>%</td><td>User setting</td></tr>
                    <tr><th>Minimum Selling Price</th><td><?php echo wp_kses_post( $this->format_money( $example_pricing['minimum'] ) ); ?></td><td>Cost + Minimum %</td></tr>
                    <tr><th>TAQI Markup</th><td><?php echo esc_html( number_format_i18n( (float) $settings['taqi_markup_percent'], 2 ) ); ?>%</td><td>User setting</td></tr>
                    <tr><th>TAQI LIFE Selling Price</th><td><strong><?php echo null !== $example_pricing['selling'] ? wp_kses_post( $this->format_money( $example_pricing['selling'] ) ) : 'Blocked / unavailable'; ?></strong></td><td>Cost + TAQI %</td></tr>
                    <tr><th>Supplier Maximum Selling Price</th><td><?php echo wp_kses_post( $this->format_money( $example_pricing['maximum'] ) ); ?></td><td><code>price</code></td></tr>
                    <tr><th>TAQI Profit</th><td><?php echo null !== $example_pricing['profit'] ? wp_kses_post( $this->format_money( $example_pricing['profit'] ) ) : '—'; ?></td><td><?php echo null !== $example_pricing['markup_percent'] ? esc_html( number_format_i18n( $example_pricing['markup_percent'], 2 ) . '% actual markup' ) : '—'; ?></td></tr>
                </tbody></table>
                <?php if ( ! empty( $example_pricing['warning'] ) ) : ?><p style="color:#b32d2e;"><strong>Preview note:</strong> <?php echo esc_html( $example_pricing['warning'] ); ?></p><?php endif; ?>
                <p class="description">Preview uses Cost ৳490 and Supplier Maximum ৳690. Actual product prices come from each Product API row.</p>
            </div>
        </div>
        <?php
    }

    private function scan_supplier_catalog_metadata() {
        $first = $this->api_request_products( 1 );
        if ( is_wp_error( $first ) ) {
            return $first;
        }

        $last_page  = min( 50, max( 1, $this->extract_last_page( $first ) ) );
        $categories = array();
        $attributes = array();
        $variants   = array();
        $product_count = 0;
        $debug_sample  = array();
        $raw_variant_rows = 0;
        $unresolved_variant_rows = 0;
        $unresolved_samples = array();
        $saved_variant_map = get_option( self::OPTION_VARIANT_MAP, array() );

        for ( $page = 1; $page <= $last_page; $page++ ) {
            $data = 1 === $page ? $first : $this->api_request_products( $page );
            if ( is_wp_error( $data ) ) {
                return $data;
            }

            foreach ( $this->extract_products( $data ) as $product ) {
                $product_count++;

                if ( empty( $debug_sample ) && is_array( $product ) ) {
                    $debug_sample = array(
                        'product_id'    => isset( $product['id'] ) ? $this->scalar( $product['id'] ) : '',
                        'product_name'   => isset( $product['name'] ) ? $this->scalar( $product['name'] ) : '',
                        'top_level_keys' => array_keys( $product ),
                        'category_type'  => array_key_exists( 'category', $product ) ? gettype( $product['category'] ) : 'missing',
                        'category_value' => array_key_exists( 'category', $product )
                            ? ( is_scalar( $product['category'] )
                                ? sanitize_text_field( (string) $product['category'] )
                                : wp_json_encode( $product['category'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
                            : '',
                    );
                }

                foreach ( $this->supplier_categories( $product ) as $category ) {
                    if ( '' !== $category['id'] ) {
                        $categories[ $category['id'] ] = array( 'id' => $category['id'], 'name' => $category['name'] );
                    }
                }

                foreach ( $this->extract_product_attribute_ids( $product ) as $attribute_id ) {
                    if ( ! isset( $attributes[ $attribute_id ] ) ) {
                        $attributes[ $attribute_id ] = array( 'id' => $attribute_id, 'name' => 'Supplier Attribute #' . $attribute_id );
                    }
                }

                foreach ( $this->extract_variation_rows( $product ) as $row ) {
                    ++$raw_variant_rows;

                    $variant_id = '';
                    if ( isset( $row['variant_id'] ) && is_scalar( $row['variant_id'] ) ) {
                        $variant_id = sanitize_text_field( $this->scalar( $row['variant_id'] ) );
                    } elseif ( isset( $row['id'] ) && is_scalar( $row['id'] ) ) {
                        $variant_id = sanitize_text_field( $this->scalar( $row['id'] ) );
                    }

                    $row_attribute_ids = array();
                    $this->collect_explicit_attribute_ids_recursive( $row, $row_attribute_ids, 0 );
                    $row_attribute_ids = array_values( array_unique( array_filter( array_map( 'strval', $row_attribute_ids ), 'strlen' ) ) );
                    foreach ( $row_attribute_ids as $attribute_id ) {
                        if ( ! isset( $attributes[ $attribute_id ] ) ) {
                            $attributes[ $attribute_id ] = array( 'id' => $attribute_id, 'name' => 'Supplier Attribute #' . $attribute_id );
                        }
                    }

                    $label          = $this->native_variation_label( $row );
                    $attribute_name = $this->native_variation_attribute_name( $row );
                    $attribute_id   = isset( $row_attribute_ids[0] ) ? $row_attribute_ids[0] : '';

                    // Only expose a Variant ID for global mapping when the API provides
                    // some semantic context (label and/or explicit attribute_id).
                    // Unlabelled product-specific relation IDs are diagnostic only.
                    $saved_mapping = isset( $saved_variant_map[ $variant_id ] ) && is_array( $saved_variant_map[ $variant_id ] ) ? $saved_variant_map[ $variant_id ] : array();
                    if ( '' !== $variant_id && ( '' !== $label || '' !== $attribute_id || '' !== $attribute_name || ( ! empty( $saved_mapping['attribute_name'] ) && ! empty( $saved_mapping['option_value'] ) ) ) ) {
                        $variants[ $variant_id ] = array(
                            'id'             => $variant_id,
                            'label'          => $label ? $label : ( ! empty( $saved_mapping['option_value'] ) ? $saved_mapping['option_value'] : '' ),
                            'attribute_id'   => $attribute_id,
                            'attribute_name' => $attribute_name ? $attribute_name : ( ! empty( $saved_mapping['attribute_name'] ) ? $saved_mapping['attribute_name'] : '' ),
                        );
                    } else {
                        ++$unresolved_variant_rows;
                        if ( count( $unresolved_samples ) < 20 ) {
                            $preview = wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                            if ( strlen( (string) $preview ) > 450 ) {
                                $preview = substr( (string) $preview, 0, 450 ) . '…';
                            }
                            $unresolved_samples[] = array(
                                'product_id'   => isset( $product['id'] ) ? sanitize_text_field( $this->scalar( $product['id'] ) ) : '',
                                'product_code' => isset( $product['product_code'] ) ? sanitize_text_field( $this->scalar( $product['product_code'] ) ) : '',
                                'variant_id'   => $variant_id,
                                'row_keys'     => is_array( $row ) ? array_keys( $row ) : array(),
                                'preview'      => $preview,
                            );
                        }
                    }
                }
            }
        }

        ksort( $categories, SORT_NATURAL );
        ksort( $attributes, SORT_NATURAL );
        ksort( $variants, SORT_NATURAL );

        $variation_discovery = array(
            'attributes'          => $attributes,
            'variants'            => $variants,
            'raw_variant_rows'    => $raw_variant_rows,
            'unresolved_variants' => $unresolved_variant_rows,
            'unresolved_samples'  => $unresolved_samples,
            'scanned_at'          => current_time( 'mysql' ),
            'pages'               => $last_page,
        );

        update_option( self::OPTION_DISCOVERED_CATEGORIES, $categories, false );
        update_option( self::OPTION_DISCOVERED_VARIATIONS, $variation_discovery, false );
        update_option( self::OPTION_SCAN_DEBUG, array(
            'scanned_at'    => current_time( 'mysql' ),
            'pages'         => $last_page,
            'product_count' => $product_count,
            'sample'        => $debug_sample,
        ), false );

        return array(
            'categories'          => count( $categories ),
            'attributes'          => count( $attributes ),
            'variants'            => count( $variants ),
            'raw_variants'        => $raw_variant_rows,
            'unresolved_variants' => $unresolved_variant_rows,
            'pages'               => $last_page,
            'products'            => $product_count,
        );
    }

    public function category_mapping_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notice = '';
        $error  = '';

        if ( ! empty( $_POST['taqi_probe_category_api'] ) ) {
            check_admin_referer( 'taqi_probe_category_api_action', 'taqi_probe_category_api_nonce' );
            $probe = $this->probe_category_api();
            if ( ! empty( $probe['success'] ) && ! empty( $probe['verified'] ) ) {
                $notice = sprintf(
                    'Verified optional Category API endpoint: %s (%d category-like records). Product API remains unchanged.',
                    $probe['verified']['endpoint'],
                    $probe['verified']['count']
                );
            } else {
                $notice = 'No verified Category/Subcategory API endpoint was found by the safe GET-only probe. Product API remains unchanged; use base mapping, TAQI LIFE category paths and manual rules.';
            }
        }

        if ( ! empty( $_POST['taqi_scan_categories'] ) ) {
            check_admin_referer( 'taqi_scan_categories_action', 'taqi_scan_categories_nonce' );
            $scan = $this->scan_supplier_catalog_metadata();
            if ( is_wp_error( $scan ) ) {
                $error = $scan->get_error_message();
            } else {
                $notice = sprintf( 'Catalog scan complete: %d supplier categories found from %d products across %d API pages.', $scan['categories'], isset( $scan['products'] ) ? $scan['products'] : 0, $scan['pages'] );
            }
        }

        if ( ! empty( $_POST['taqi_create_category_path'] ) ) {
            check_admin_referer( 'taqi_create_category_path_action', 'taqi_create_category_path_nonce' );
            $path    = isset( $_POST['taqi_new_category_path'] ) ? wp_unslash( $_POST['taqi_new_category_path'] ) : '';
            $term_id = $this->ensure_category_path( $path );
            if ( is_wp_error( $term_id ) ) {
                $error = $term_id->get_error_message();
            } else {
                $notice = sprintf( 'TAQI LIFE category path created/confirmed successfully. Final category ID: %d.', $term_id );
            }
        }

        if ( ! empty( $_POST['taqi_save_category_map'] ) ) {
            check_admin_referer( 'taqi_save_category_map_action', 'taqi_category_map_nonce' );
            $discovered_for_save = get_option( self::OPTION_DISCOVERED_CATEGORIES, array() );
            $existing_map        = get_option( self::OPTION_CATEGORY_MAP, array() );
            $new_map             = is_array( $existing_map ) ? $existing_map : array();
            $actions             = ! empty( $_POST['category_action'] ) && is_array( $_POST['category_action'] ) ? wp_unslash( $_POST['category_action'] ) : array();
            $existing_values     = ! empty( $_POST['category_existing'] ) && is_array( $_POST['category_existing'] ) ? wp_unslash( $_POST['category_existing'] ) : array();
            $paths               = ! empty( $_POST['category_path'] ) && is_array( $_POST['category_path'] ) ? wp_unslash( $_POST['category_path'] ) : array();
            $save_errors         = array();

            foreach ( $discovered_for_save as $category ) {
                $supplier_key = isset( $category['id'] ) ? sanitize_text_field( (string) $category['id'] ) : '';
                if ( '' === $supplier_key ) {
                    continue;
                }

                $action = isset( $actions[ $supplier_key ] ) ? sanitize_key( $actions[ $supplier_key ] ) : 'keep';

                if ( 'ignore' === $action ) {
                    unset( $new_map[ $supplier_key ] );
                    continue;
                }

                if ( 'existing' === $action ) {
                    $term_id = isset( $existing_values[ $supplier_key ] ) ? absint( $existing_values[ $supplier_key ] ) : 0;
                    if ( $term_id && term_exists( $term_id, 'taqi_category' ) ) {
                        $new_map[ $supplier_key ] = $term_id;
                    } elseif ( $term_id ) {
                        $save_errors[] = sprintf( '%s: selected WooCommerce category does not exist.', $category['name'] );
                    }
                    continue;
                }

                if ( 'create' === $action ) {
                    $path = isset( $paths[ $supplier_key ] ) ? sanitize_text_field( $paths[ $supplier_key ] ) : '';
                    if ( '' === trim( $path ) ) {
                        $path = isset( $category['name'] ) ? $category['name'] : '';
                    }
                    $term_id = $this->ensure_category_path( $path );
                    if ( is_wp_error( $term_id ) ) {
                        $save_errors[] = sprintf( '%s: %s', $category['name'], $term_id->get_error_message() );
                    } else {
                        $new_map[ $supplier_key ] = absint( $term_id );
                    }
                }
            }

            update_option( self::OPTION_CATEGORY_MAP, $new_map, false );
            if ( $save_errors ) {
                $error = implode( ' ', $save_errors );
            } else {
                $notice = 'Category mapping saved. New TAQI LIFE categories were created where requested.';
            }
        }

        if ( ! empty( $_POST['taqi_add_category_rule'] ) ) {
            check_admin_referer( 'taqi_category_rule_action', 'taqi_category_rule_nonce' );

            $supplier_key = isset( $_POST['rule_supplier_key'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_supplier_key'] ) ) : '*';
            $field        = isset( $_POST['rule_field'] ) ? sanitize_key( wp_unslash( $_POST['rule_field'] ) ) : 'name';
            $keyword      = isset( $_POST['rule_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_keyword'] ) ) : '';
            $term_id      = isset( $_POST['rule_term_id'] ) ? absint( $_POST['rule_term_id'] ) : 0;
            $target_path  = isset( $_POST['rule_target_path'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_target_path'] ) ) : '';

            if ( '' !== $target_path ) {
                $created = $this->ensure_category_path( $target_path );
                if ( is_wp_error( $created ) ) {
                    $error = $created->get_error_message();
                } else {
                    $term_id = absint( $created );
                }
            }

            if ( ! $error ) {
                if ( '' === $keyword ) {
                    $error = 'Enter a keyword/text for the category rule.';
                } elseif ( ! $term_id || ! term_exists( $term_id, 'taqi_category' ) ) {
                    $error = 'Select an existing target category or enter a new target category path.';
                } else {
                    if ( ! in_array( $field, array( 'name', 'name_details', 'slug' ), true ) ) {
                        $field = 'name';
                    }
                    $rules   = get_option( self::OPTION_CATEGORY_RULES, array() );
                    $rules   = is_array( $rules ) ? $rules : array();
                    $rules[] = array(
                        'id'           => wp_generate_uuid4(),
                        'supplier_key' => '' !== $supplier_key ? $supplier_key : '*',
                        'field'        => $field,
                        'keyword'      => $keyword,
                        'term_id'      => $term_id,
                    );
                    update_option( self::OPTION_CATEGORY_RULES, $rules, false );
                    $notice = 'Product category rule added.';
                }
            }
        }

        if ( ! empty( $_POST['taqi_delete_category_rule'] ) ) {
            check_admin_referer( 'taqi_delete_category_rule_action', 'taqi_delete_category_rule_nonce' );
            $delete_id = sanitize_text_field( wp_unslash( $_POST['taqi_delete_category_rule'] ) );
            $rules     = get_option( self::OPTION_CATEGORY_RULES, array() );
            $kept      = array();
            if ( is_array( $rules ) ) {
                foreach ( $rules as $rule ) {
                    if ( ! is_array( $rule ) || empty( $rule['id'] ) || $rule['id'] !== $delete_id ) {
                        $kept[] = $rule;
                    }
                }
            }
            update_option( self::OPTION_CATEGORY_RULES, $kept, false );
            $notice = 'Category rule deleted.';
        }

        $discovered = get_option( self::OPTION_DISCOVERED_CATEGORIES, array() );
        $debug      = get_option( self::OPTION_SCAN_DEBUG, array() );
        $map        = get_option( self::OPTION_CATEGORY_MAP, array() );
        $rules      = get_option( self::OPTION_CATEGORY_RULES, array() );
        $choices    = $this->category_choices();
        $category_api_test    = get_option( self::OPTION_CATEGORY_API_TEST, array() );
        $category_api_catalog = get_option( self::OPTION_CATEGORY_API_CATALOG, array() );
        ?>
        <div class="wrap">
            <h1>Category Mapping</h1>
            <p><strong>TAQI LIFE controls its own category structure.</strong> Supplier categories can be mapped to an existing WooCommerce category, used to create a new TAQI LIFE category/path, or ignored.</p>
            <p class="description">The Product API remains the primary, protected source for product import. Optional Category API discovery is read-only and isolated; failure or absence of a Category API will not interrupt product browsing/import.</p>

            <?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

            <div style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:1250px;margin:18px 0;">
                <h2 style="margin-top:0;">0. Optional Category API Discovery (Safe)</h2>
                <p>This performs <strong>GET-only</strong> tests against a small whitelist of likely category/subcategory endpoints on the same configured reseller API host. It never writes to the supplier and never changes the working <code>/product</code> API.</p>

                <form method="post" style="margin-bottom:12px;">
                    <?php wp_nonce_field( 'taqi_probe_category_api_action', 'taqi_probe_category_api_nonce' ); ?>
                    <button type="submit" name="taqi_probe_category_api" value="1" class="button button-secondary">Probe Category API Safely</button>
                </form>

                <?php if ( ! empty( $category_api_test['checked'] ) ) : ?>
                    <p><strong>Last checked:</strong> <?php echo esc_html( $category_api_test['checked'] ); ?></p>
                    <?php if ( ! empty( $category_api_test['success'] ) && ! empty( $category_api_test['verified'] ) ) : ?>
                        <div class="notice notice-success inline"><p>
                            <strong>Verified:</strong>
                            <code><?php echo esc_html( $category_api_test['verified']['endpoint'] ); ?></code>
                            — <?php echo esc_html( $category_api_test['verified']['count'] ); ?> category-like records detected.
                            Product imports still use <code>/product</code>.
                        </p></div>
                    <?php else : ?>
                        <div class="notice notice-info inline"><p>
                            <strong>No verified Category API endpoint.</strong>
                            This is safe: continue using Product API base categories plus manual TAQI LIFE category paths/rules.
                        </p></div>
                    <?php endif; ?>

                    <?php if ( ! empty( $category_api_test['attempts'] ) && is_array( $category_api_test['attempts'] ) ) : ?>
                        <details style="margin-top:12px;">
                            <summary><strong>Probe diagnostics</strong></summary>
                            <table class="widefat striped" style="margin-top:8px;max-width:900px;">
                                <thead><tr><th>Endpoint</th><th>HTTP</th><th>Result</th></tr></thead>
                                <tbody>
                                <?php foreach ( $category_api_test['attempts'] as $attempt ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( isset( $attempt['endpoint'] ) ? $attempt['endpoint'] : '' ); ?></code></td>
                                        <td><?php echo esc_html( isset( $attempt['code'] ) ? $attempt['code'] : 0 ); ?></td>
                                        <td><?php echo esc_html( isset( $attempt['result'] ) ? $attempt['result'] : '' ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ( ! empty( $category_api_catalog ) && is_array( $category_api_catalog ) ) : ?>
                    <details style="margin-top:12px;">
                        <summary><strong>Verified Category API catalog preview (read-only)</strong></summary>
                        <p class="description">These records are informational only. TAQI LIFE categories are not auto-created from them.</p>
                        <ul style="columns:2;max-width:900px;">
                            <?php foreach ( array_slice( $category_api_catalog, 0, 60, true ) as $api_category ) : ?>
                                <li><?php echo esc_html( ! empty( $api_category['path'] ) ? $api_category['path'] : $api_category['name'] ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;max-width:1250px;">
                <div style="background:#fff;border:1px solid #dcdcde;padding:16px;min-width:360px;flex:1;">
                    <h2 style="margin-top:0;">1. Scan Supplier Categories</h2>
                    <form method="post">
                        <?php wp_nonce_field( 'taqi_scan_categories_action', 'taqi_scan_categories_nonce' ); ?>
                        <button type="submit" name="taqi_scan_categories" value="1" class="button">Scan Supplier Catalog</button>
                    </form>
                    <p class="description">Discovers the supplier category values returned by the reseller API.</p>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;padding:16px;min-width:360px;flex:1;">
                    <h2 style="margin-top:0;">2. Create TAQI LIFE Category Path</h2>
                    <form method="post">
                        <?php wp_nonce_field( 'taqi_create_category_path_action', 'taqi_create_category_path_nonce' ); ?>
                        <input type="text" name="taqi_new_category_path" class="regular-text" placeholder="Men > T-Shirts > Half Sleeve T-Shirts" required>
                        <button type="submit" name="taqi_create_category_path" value="1" class="button button-secondary">Create Category Path</button>
                    </form>
                    <p class="description">Use <code>&gt;</code> to create parent/child categories. Existing levels are reused automatically.</p>
                </div>
            </div>

            <?php if ( empty( $discovered ) ) : ?>
                <?php if ( ! empty( $debug['scanned_at'] ) ) : ?>
                    <div class="notice notice-warning inline"><p><strong>No supplier categories were detected.</strong> The scan processed <?php echo esc_html( isset( $debug['product_count'] ) ? $debug['product_count'] : 0 ); ?> products.</p></div>
                <?php else : ?>
                    <div class="notice notice-info inline"><p>Click <strong>Scan Supplier Catalog</strong> first.</p></div>
                <?php endif; ?>
            <?php else : ?>
                <h2 style="margin-top:28px;">3. Supplier → TAQI LIFE Base Mapping</h2>
                <p>This is the safe default category for every imported product in that supplier category. For example, map <strong>Men's Fashion</strong> to <strong>Men</strong>, not directly to a specific T-shirt leaf category.</p>
                <form method="post">
                    <?php wp_nonce_field( 'taqi_save_category_map_action', 'taqi_category_map_nonce' ); ?>
                    <table class="widefat striped" style="max-width:1250px;">
                        <thead>
                            <tr>
                                <th style="width:18%;">Supplier Category</th>
                                <th style="width:18%;">Action</th>
                                <th style="width:26%;">Map Existing</th>
                                <th style="width:26%;">Create / Map Custom Path</th>
                                <th>Current Mapping</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $discovered as $category ) :
                            $supplier_key = (string) $category['id'];
                            $mapped_id    = isset( $map[ $supplier_key ] ) ? absint( $map[ $supplier_key ] ) : 0;
                            $current      = $mapped_id && isset( $choices[ $mapped_id ] ) ? $choices[ $mapped_id ] : '—';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $category['name'] ); ?></strong><br><code><?php echo esc_html( $supplier_key ); ?></code></td>
                                <td>
                                    <select name="category_action[<?php echo esc_attr( $supplier_key ); ?>]">
                                        <option value="keep" <?php selected( $mapped_id > 0 ); ?>>Keep current</option>
                                        <option value="existing">Map existing</option>
                                        <option value="create">Create / map custom path</option>
                                        <option value="ignore" <?php selected( 0 === $mapped_id ); ?>>Ignore / remove mapping</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="category_existing[<?php echo esc_attr( $supplier_key ); ?>]" style="max-width:100%;">
                                        <option value="0">— Select category —</option>
                                        <?php foreach ( $choices as $term_id => $label ) : ?>
                                            <option value="<?php echo esc_attr( $term_id ); ?>" <?php selected( $mapped_id, $term_id ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="category_path[<?php echo esc_attr( $supplier_key ); ?>]" class="regular-text" style="max-width:100%;" placeholder="<?php echo esc_attr( $category['name'] ); ?> or Men > Clothing">
                                </td>
                                <td><?php echo esc_html( $current ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="submit"><button type="submit" name="taqi_save_category_map" value="1" class="button button-primary">Save Category Mapping</button></p>
                </form>
            <?php endif; ?>

            <hr style="margin:32px 0;">
            <h2>4. Optional Product Category Rules</h2>
            <p>Use rules only when the reseller API does not expose the product's deeper category. Rules are evaluated during new imports. A product keeps its base mapping and also receives matching rule categories.</p>

            <div style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:1250px;">
                <form method="post">
                    <?php wp_nonce_field( 'taqi_category_rule_action', 'taqi_category_rule_nonce' ); ?>
                    <table class="form-table" role="presentation"><tbody>
                        <tr>
                            <th scope="row"><label for="rule_supplier_key">Supplier category</label></th>
                            <td><select name="rule_supplier_key" id="rule_supplier_key">
                                <option value="*">All supplier categories</option>
                                <?php foreach ( $discovered as $category ) : ?>
                                    <option value="<?php echo esc_attr( $category['id'] ); ?>"><?php echo esc_html( $category['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rule_field">Match in</label></th>
                            <td><select name="rule_field" id="rule_field">
                                <option value="name">Product name</option>
                                <option value="name_details">Product name + description</option>
                                <option value="slug">Product slug</option>
                            </select></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rule_keyword">Contains text</label></th>
                            <td><input type="text" name="rule_keyword" id="rule_keyword" class="regular-text" placeholder="T-Shirt" required></td>
                        </tr>
                        <tr>
                            <th scope="row">Target category</th>
                            <td>
                                <select name="rule_term_id" style="min-width:280px;">
                                    <option value="0">— Select existing category —</option>
                                    <?php foreach ( $choices as $term_id => $label ) : ?>
                                        <option value="<?php echo esc_attr( $term_id ); ?>"><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span style="margin:0 8px;">OR</span>
                                <input type="text" name="rule_target_path" class="regular-text" placeholder="Men > T-Shirts">
                            </td>
                        </tr>
                    </tbody></table>
                    <p class="submit"><button type="submit" name="taqi_add_category_rule" value="1" class="button button-secondary">Add Category Rule</button></p>
                </form>
            </div>

            <?php if ( ! empty( $rules ) && is_array( $rules ) ) : ?>
                <?php $choices = $this->category_choices(); ?>
                <h3>Saved Category Rules</h3>
                <table class="widefat striped" style="max-width:1250px;">
                    <thead><tr><th>Supplier</th><th>Match Field</th><th>Contains</th><th>TAQI LIFE Category</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ( $rules as $rule ) :
                        if ( ! is_array( $rule ) ) { continue; }
                        $rule_supplier = isset( $rule['supplier_key'] ) ? $rule['supplier_key'] : '*';
                        $supplier_name = 'All supplier categories';
                        foreach ( $discovered as $category ) {
                            if ( (string) $category['id'] === (string) $rule_supplier ) { $supplier_name = $category['name']; break; }
                        }
                        $field_labels = array( 'name' => 'Product name', 'name_details' => 'Name + description', 'slug' => 'Slug' );
                        $field_label  = isset( $field_labels[ $rule['field'] ] ) ? $field_labels[ $rule['field'] ] : 'Product name';
                        $target_label = isset( $choices[ absint( $rule['term_id'] ) ] ) ? $choices[ absint( $rule['term_id'] ) ] : 'Category #' . absint( $rule['term_id'] );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $supplier_name ); ?></td>
                            <td><?php echo esc_html( $field_label ); ?></td>
                            <td><code><?php echo esc_html( $rule['keyword'] ); ?></code></td>
                            <td><?php echo esc_html( $target_label ); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'taqi_delete_category_rule_action', 'taqi_delete_category_rule_nonce' ); ?>
                                    <button type="submit" name="taqi_delete_category_rule" value="<?php echo esc_attr( $rule['id'] ); ?>" class="button-link-delete" onclick="return confirm('Delete this category rule?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="notice notice-info inline" style="margin-top:24px;max-width:1210px;">
                <p><strong>Recommended for the supplier example:</strong> Base map <code>Men's Fashion → Men</code>. Then create <code>Men &gt; T-Shirts</code> and add a rule such as <code>Product name contains "T-Shirt" → Men &gt; T-Shirts</code>. Do not map every Men's Fashion product directly to Half Sleeve T-Shirts because the current API only provides the broad category.</p>
            </div>
        </div>
        <?php
    }

    public function variation_mapping_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notice = '';
        $error  = '';

        if ( ! empty( $_POST['taqi_scan_variations'] ) ) {
            check_admin_referer( 'taqi_scan_variations_action', 'taqi_scan_variations_nonce' );
            $scan = $this->scan_supplier_catalog_metadata();
            if ( is_wp_error( $scan ) ) {
                $error = $scan->get_error_message();
            } else {
                $notice = sprintf(
                    'Catalog scan complete: %d explicit attribute ID(s), %d mappable variant ID(s), and %d unresolved raw variant reference(s) found across %d products.',
                    isset( $scan['attributes'] ) ? absint( $scan['attributes'] ) : 0,
                    isset( $scan['variants'] ) ? absint( $scan['variants'] ) : 0,
                    isset( $scan['unresolved_variants'] ) ? absint( $scan['unresolved_variants'] ) : 0,
                    isset( $scan['products'] ) ? absint( $scan['products'] ) : 0
                );
            }
        }

        if ( ! empty( $_POST['taqi_save_variation_map'] ) ) {
            check_admin_referer( 'taqi_save_variation_map_action', 'taqi_variation_map_nonce' );

            $attribute_map = array();
            if ( ! empty( $_POST['attribute_map'] ) && is_array( $_POST['attribute_map'] ) ) {
                foreach ( wp_unslash( $_POST['attribute_map'] ) as $id => $name ) {
                    $id   = sanitize_text_field( $id );
                    $name = sanitize_text_field( $name );
                    if ( $id && $name ) {
                        $attribute_map[ $id ] = $name;
                    }
                }
            }

            $variant_map = get_option( self::OPTION_VARIANT_MAP, array() );
            if ( ! empty( $_POST['variant_map'] ) && is_array( $_POST['variant_map'] ) ) {
                foreach ( wp_unslash( $_POST['variant_map'] ) as $id => $row ) {
                    $id = sanitize_text_field( $id );
                    if ( ! is_array( $row ) || ! $id ) {
                        continue;
                    }
                    $attribute_name = ! empty( $row['attribute_name'] ) ? sanitize_text_field( $row['attribute_name'] ) : '';
                    $option_value   = ! empty( $row['option_value'] ) ? sanitize_text_field( $row['option_value'] ) : '';
                    if ( $attribute_name || $option_value ) {
                        $variant_map[ $id ] = array( 'attribute_name' => $attribute_name, 'option_value' => $option_value );
                    }
                }
            }

            update_option( self::OPTION_ATTRIBUTE_MAP, $attribute_map, false );
            update_option( self::OPTION_VARIANT_MAP, $variant_map, false );
            $notice = 'Variation mapping saved. Product API configuration was not changed.';
        }

        $discovered    = get_option( self::OPTION_DISCOVERED_VARIATIONS, array() );
        $attribute_map = get_option( self::OPTION_ATTRIBUTE_MAP, array() );
        $variant_map   = get_option( self::OPTION_VARIANT_MAP, array() );
        $attributes    = isset( $discovered['attributes'] ) && is_array( $discovered['attributes'] ) ? $discovered['attributes'] : array();
        $variants      = isset( $discovered['variants'] ) && is_array( $discovered['variants'] ) ? $discovered['variants'] : array();
        $raw_rows      = isset( $discovered['raw_variant_rows'] ) ? absint( $discovered['raw_variant_rows'] ) : count( $variants );
        $unresolved    = isset( $discovered['unresolved_variants'] ) ? absint( $discovered['unresolved_variants'] ) : 0;
        $samples       = isset( $discovered['unresolved_samples'] ) && is_array( $discovered['unresolved_samples'] ) ? $discovered['unresolved_samples'] : array();
        $variant_page  = isset( $_GET['variant_page'] ) ? max( 1, absint( $_GET['variant_page'] ) ) : 1;
        $variant_per_page = 50;
        $variant_total = count( $variants );
        $variant_pages = max( 1, (int) ceil( $variant_total / $variant_per_page ) );
        $variant_page  = min( $variant_page, $variant_pages );
        $variant_rows  = array_slice( $variants, ( $variant_page - 1 ) * $variant_per_page, $variant_per_page );

        // v1.3.5 stored every raw variant ID, including thousands of unlabeled relation IDs.
        // Treat a fully-unlabeled legacy list as unresolved until the catalog is rescanned.
        if ( ! isset( $discovered['raw_variant_rows'] ) && ! empty( $variants ) ) {
            $legacy_unlabelled = 0;
            foreach ( $variants as $legacy_variant ) {
                if ( empty( $legacy_variant['label'] ) && empty( $legacy_variant['attribute_id'] ) && empty( $legacy_variant['attribute_name'] ) ) {
                    ++$legacy_unlabelled;
                }
            }
            if ( $legacy_unlabelled === count( $variants ) ) {
                $raw_rows   = count( $variants );
                $unresolved = count( $variants );
                $variants   = array();
            }
        }
        ?>
        <div class="wrap taqi-variation-screen">
            <style>
                .taqi-variation-screen .taqi-variation-pagination ul.page-numbers{display:flex!important;align-items:center;gap:6px;list-style:none;margin:0;padding:0}
                .taqi-variation-screen .taqi-variation-pagination ul.page-numbers li{display:block;margin:0;padding:0}
                .taqi-variation-screen .taqi-variation-pagination .page-numbers{display:inline-flex!important;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 9px;border:1px solid #c3c4c7;border-radius:5px;background:#fff;text-decoration:none;box-sizing:border-box}
                .taqi-variation-screen .taqi-variation-pagination .page-numbers.current{background:#2271b1;border-color:#2271b1;color:#fff;font-weight:600}
                .taqi-variation-screen .taqi-variation-pagination .page-numbers.dots{border:0;background:transparent}
                .taqi-variation-screen .taqi-variation-pagination .page-numbers:hover{border-color:#2271b1;color:#135e96}
                .taqi-variation-screen .taqi-variation-pagination .page-numbers.current:hover{color:#fff}
                .taqi-variation-screen .taqi-variation-pagination{display:block}
                .taqi-variation-screen .taqi-variation-pagination + *{margin-top:0}
            </style>
            <h1>Variation Mapping</h1>
            <p><strong>Safe variation policy:</strong> the Product API remains the primary source. A numeric relation/variant ID is <em>not</em> treated as Size/Color unless the API also provides a readable label, an explicit <code>attribute_id</code>, or you deliberately map it.</p>
            <?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

            <form method="post" style="margin:14px 0;">
                <?php wp_nonce_field( 'taqi_scan_variations_action', 'taqi_scan_variations_nonce' ); ?>
                <button type="submit" name="taqi_scan_variations" value="1" class="button">Scan Supplier Catalog</button>
            </form>

            <?php if ( ! empty( $discovered['scanned_at'] ) ) : ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;max-width:980px;margin:16px 0;">
                    <div style="background:#fff;border:1px solid #dcdcde;padding:14px;"><strong>Raw variant rows</strong><br><span style="font-size:22px;"><?php echo esc_html( $raw_rows ); ?></span></div>
                    <div style="background:#fff;border:1px solid #dcdcde;padding:14px;"><strong>Explicit attributes</strong><br><span style="font-size:22px;"><?php echo esc_html( count( $attributes ) ); ?></span></div>
                    <div style="background:#fff;border:1px solid #dcdcde;padding:14px;"><strong>Mappable variants</strong><br><span style="font-size:22px;"><?php echo esc_html( count( $variants ) ); ?></span></div>
                    <div style="background:#fff;border:1px solid #dcdcde;padding:14px;"><strong>Unresolved references</strong><br><span style="font-size:22px;"><?php echo esc_html( $unresolved ); ?></span></div>
                </div>
            <?php endif; ?>

            <?php if ( $unresolved > 0 ) : ?>
                <div class="notice notice-warning inline" style="max-width:1100px;">
                    <p><strong><?php echo esc_html( $unresolved ); ?> raw variant references are intentionally not shown as global mapping rows.</strong> The current API data does not give enough semantic information to prove that those IDs mean Size, Color, etc. Listing thousands of unlabeled IDs would encourage unsafe mappings.</p>
                    <p>Products can still be imported normally. If a product has unresolved supplier variants, it remains a Simple product unless readable/mapped attributes exist. You may manually configure that WooCommerce product later; this manual work does not change or break the Product API.</p>
                </div>
            <?php endif; ?>

            <?php if ( empty( $attributes ) && empty( $variants ) && empty( $samples ) ) : ?>
                <div class="notice notice-info inline" style="max-width:1100px;">
                    <p><strong>No safely mappable supplier variation metadata is currently exposed.</strong> This is not an API failure. Product browsing, import, pricing, image import and product-level stock sync continue normally.</p>
                    <p>Automatic per-variation price/stock sync will only be enabled when the supplier exposes readable option values or a documented attribute/variant endpoint.</p>
                </div>
            <?php else : ?>
                <form method="post">
                    <?php wp_nonce_field( 'taqi_save_variation_map_action', 'taqi_variation_map_nonce' ); ?>

                    <?php if ( ! empty( $attributes ) ) : ?>
                        <h2>Supplier Attribute IDs</h2>
                        <table class="widefat striped" style="max-width:900px;margin-bottom:24px;">
                            <thead><tr><th>Attribute ID</th><th>WooCommerce Attribute Label</th><th>Example</th></tr></thead>
                            <tbody>
                            <?php foreach ( $attributes as $attribute ) : $id = (string) $attribute['id']; ?>
                                <tr><td><code><?php echo esc_html( $id ); ?></code></td><td><input type="text" class="regular-text" name="attribute_map[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( isset( $attribute_map[ $id ] ) ? $attribute_map[ $id ] : '' ); ?>" placeholder="Size / Color / Material"></td><td>Example: <code>Size</code></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ( ! empty( $variants ) ) : ?>
                        <h2>Safely Mappable Supplier Variant IDs</h2>
                        <table class="widefat striped" style="max-width:1100px;">
                            <thead><tr><th>Variant ID</th><th>API Label</th><th>API Attribute</th><th>Attribute Name (override)</th><th>Option Value</th></tr></thead>
                            <tbody>
                            <?php foreach ( $variant_rows as $variant ) : $id = (string) $variant['id']; $saved = isset( $variant_map[ $id ] ) && is_array( $variant_map[ $id ] ) ? $variant_map[ $id ] : array(); ?>
                                <tr>
                                    <td><code><?php echo esc_html( $id ); ?></code></td>
                                    <td><?php echo esc_html( ! empty( $variant['label'] ) ? $variant['label'] : '—' ); ?></td>
                                    <td><?php echo esc_html( ! empty( $variant['attribute_name'] ) ? $variant['attribute_name'] : ( ! empty( $variant['attribute_id'] ) ? 'ID ' . $variant['attribute_id'] : '—' ) ); ?></td>
                                    <td><input type="text" name="variant_map[<?php echo esc_attr( $id ); ?>][attribute_name]" value="<?php echo esc_attr( isset( $saved['attribute_name'] ) ? $saved['attribute_name'] : '' ); ?>" placeholder="Size / Color"></td>
                                    <td><input type="text" name="variant_map[<?php echo esc_attr( $id ); ?>][option_value]" value="<?php echo esc_attr( isset( $saved['option_value'] ) ? $saved['option_value'] : ( ! empty( $variant['label'] ) ? $variant['label'] : '' ) ); ?>" placeholder="M / L / XL / Black"></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ( $variant_pages > 1 ) : ?>
                            <div class="tablenav" style="margin:12px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <span class="displaying-num"><?php echo esc_html( $variant_total ); ?> variants</span>
                                <?php
                                $variant_pagination = paginate_links(
                                    array(
                                        'base'      => admin_url( 'admin.php?page=taqi-dropshipping-variations&variant_page=%#%' ),
                                        'format'    => '',
                                        'current'   => $variant_page,
                                        'total'     => $variant_pages,
                                        'type'      => 'list',
                                        'prev_text' => '‹ Previous',
                                        'next_text' => 'Next ›',
                                    )
                                );
                                echo $variant_pagination ? '<span class="taqi-variation-pagination">' . wp_kses_post( $variant_pagination ) . '</span>' : '';
                                ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php
                    $unresolved_mapping_ids = array();
                    foreach ( $samples as $sample ) {
                        $sample_id = ! empty( $sample['variant_id'] ) ? (string) $sample['variant_id'] : '';
                        if ( $sample_id && ! isset( $unresolved_mapping_ids[ $sample_id ] ) ) {
                            $unresolved_mapping_ids[ $sample_id ] = $sample;
                        }
                    }
                    ?>
                    <?php if ( ! empty( $unresolved_mapping_ids ) ) : ?>
                        <h2>Resolve Unresolved Variant IDs</h2>
                        <p class="description">These are the diagnostic IDs that currently have no readable supplier label. Enter the WooCommerce attribute name and option value for each ID you recognize. The saved mapping is applied during future imports and re-syncs.</p>
                        <table class="widefat striped" style="max-width:1100px;">
                            <thead><tr><th>Variant ID</th><th>Supplier sample</th><th>Attribute Name</th><th>Option Value</th></tr></thead>
                            <tbody>
                            <?php foreach ( $unresolved_mapping_ids as $id => $sample ) :
                                $saved = isset( $variant_map[ $id ] ) && is_array( $variant_map[ $id ] ) ? $variant_map[ $id ] : array();
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html( $id ); ?></code></td>
                                    <td><code style="display:block;max-width:520px;white-space:normal;word-break:break-word;"><?php echo esc_html( isset( $sample['preview'] ) ? $sample['preview'] : '' ); ?></code></td>
                                    <td><input type="text" name="variant_map[<?php echo esc_attr( $id ); ?>][attribute_name]" value="<?php echo esc_attr( isset( $saved['attribute_name'] ) ? $saved['attribute_name'] : '' ); ?>" placeholder="Size / Color"></td>
                                    <td><input type="text" name="variant_map[<?php echo esc_attr( $id ); ?>][option_value]" value="<?php echo esc_attr( isset( $saved['option_value'] ) ? $saved['option_value'] : '' ); ?>" placeholder="M / L / XL / Black"></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <p class="submit"><button type="submit" name="taqi_save_variation_map" value="1" class="button button-primary">Save Variation Mapping</button></p>
                </form>
            <?php endif; ?>

            <?php if ( ! empty( $samples ) ) : ?>
                <details style="margin-top:22px;max-width:1100px;background:#fff;border:1px solid #dcdcde;padding:14px;">
                    <summary><strong>Unresolved variation diagnostics (sample only)</strong></summary>
                    <p class="description">These rows are read-only diagnostics. They are never written back to the supplier and are not automatically interpreted as Size/Color.</p>
                    <table class="widefat striped" style="margin-top:10px;">
                        <thead><tr><th>Product</th><th>Variant/Relation ID</th><th>Row Keys</th><th>Preview</th></tr></thead>
                        <tbody>
                        <?php foreach ( $samples as $sample ) : ?>
                            <tr>
                                <td><?php echo esc_html( ( ! empty( $sample['product_code'] ) ? $sample['product_code'] : '—' ) . ' / ID ' . ( ! empty( $sample['product_id'] ) ? $sample['product_id'] : '—' ) ); ?></td>
                                <td><code><?php echo esc_html( ! empty( $sample['variant_id'] ) ? $sample['variant_id'] : '—' ); ?></code></td>
                                <td><code><?php echo esc_html( ! empty( $sample['row_keys'] ) && is_array( $sample['row_keys'] ) ? implode( ', ', $sample['row_keys'] ) : '—' ); ?></code></td>
                                <td><code style="white-space:normal;word-break:break-word;"><?php echo esc_html( isset( $sample['preview'] ) ? $sample['preview'] : '' ); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endif; ?>

            <div class="notice notice-info inline" style="margin-top:20px;max-width:1100px;">
                <p><strong>Manual fallback:</strong> if the supplier website shows a size such as XL but the Product API only returns an unlabeled relation ID, import the product safely first. You can then create/edit local WooCommerce attributes and variations manually. Re-sync will not invent or overwrite an unresolved supplier variation mapping.</p>
            </div>
        </div>
        <?php
    }

    private function handle_all_product_pages( $action, $last_page ) {
        $results = array();
        for ( $page = 1; $page <= $last_page; $page++ ) {
            $data = $this->api_request_products( $page );
            if ( is_wp_error( $data ) ) {
                $results[] = $data;
                continue;
            }
            foreach ( $this->extract_products( $data ) as $product ) {
                $supplier_id = $this->supplier_product_id( $product );
                if ( '' === $supplier_id ) {
                    continue;
                }
                $product_id = $this->find_imported_product_id( $supplier_id );
                if ( 'all_import' === $action ) {
                    $result = $this->import_product( $product, $page );
                    if ( is_wp_error( $result ) ) {
                        $results[] = $result;
                    } else {
                        $result['supplier_id'] = $supplier_id;
                        $results[] = $result;
                    }
                } elseif ( 'all_resync' === $action && $product_id ) {
                    $results[] = $this->resync_product( $product_id, $product, $page );
                } elseif ( 'all_cancel' === $action && $product_id ) {
                    $results[] = $this->cancel_product_sync( $product_id, $supplier_id );
                }
            }
        }
        return $results;
    }

    private function handle_product_imports( $products, $api_page, $last_page = 1 ) {
        $results = array();
        if ( empty( $_POST['taqi_import_action'] ) ) {
            return $results;
        }

        check_admin_referer( 'taqi_import_products_' . $api_page, 'taqi_import_nonce' );
        $action = sanitize_key( wp_unslash( $_POST['taqi_import_action'] ) );
        $import_category_id   = isset( $_POST['taqi_import_category_id'] ) ? absint( $_POST['taqi_import_category_id'] ) : 0;
        $import_category_path = isset( $_POST['taqi_import_category_path'] ) ? sanitize_text_field( wp_unslash( $_POST['taqi_import_category_path'] ) ) : '';

        if ( in_array( $action, array( 'all_import', 'all_resync', 'all_cancel' ), true ) ) {
            return $this->handle_all_product_pages( $action, $last_page );
        }

        $product_map = array();
        foreach ( $products as $product ) {
            $id = $this->supplier_product_id( $product );
            if ( '' !== $id ) {
                $product_map[ (string) $id ] = $product;
            }
        }

        if ( in_array( $action, array( 'bulk_resync', 'bulk_cancel' ), true ) ) {
            $selected = ! empty( $_POST['supplier_ids'] ) && is_array( $_POST['supplier_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['supplier_ids'] ) ) : array();
            $selected = array_values( array_unique( array_filter( $selected, 'strlen' ) ) );
            if ( empty( $selected ) ) {
                return array( new WP_Error( 'taqi_nothing_selected', 'Select at least one linked product first.' ) );
            }
            foreach ( $selected as $supplier_id ) {
                $product_id = $this->find_imported_product_id( $supplier_id );
                if ( ! $product_id ) {
                    $results[] = new WP_Error( 'taqi_active_link_missing', 'No active synchronization was found for supplier product ' . $supplier_id . '.' );
                    continue;
                }
                if ( 'bulk_resync' === $action ) {
                    if ( ! isset( $product_map[ (string) $supplier_id ] ) ) {
                        $results[] = new WP_Error( 'taqi_product_not_on_page', 'Current supplier data for product ' . $supplier_id . ' was not found.' );
                        continue;
                    }
                    $results[] = $this->resync_product( $product_id, $product_map[ (string) $supplier_id ], $api_page );
                } else {
                    $results[] = $this->cancel_product_sync( $product_id, $supplier_id );
                }
            }
            return $results;
        }

        if ( in_array( $action, array( 'resync', 'cancel', 'relink', 'delete' ), true ) ) {
            $supplier_id = isset( $_POST['single_supplier_id'] ) ? sanitize_text_field( wp_unslash( $_POST['single_supplier_id'] ) ) : '';
            if ( '' === $supplier_id ) {
                return array( new WP_Error( 'taqi_supplier_id_missing', 'Supplier product ID is required for this action.' ) );
            }

            if ( ! isset( $product_map[ (string) $supplier_id ] ) && in_array( $action, array( 'resync', 'relink' ), true ) ) {
                return array( new WP_Error( 'taqi_product_not_on_page', 'Current supplier data for product ' . $supplier_id . ' was not found on this API page. Refresh the page and try again.' ) );
            }

            if ( 'resync' === $action ) {
                $product_id = $this->find_imported_product_id( $supplier_id );
                if ( ! $product_id ) {
                    return array( new WP_Error( 'taqi_active_link_missing', 'No actively linked WooCommerce product was found for supplier product ' . $supplier_id . '.' ) );
                }
                $results[] = $this->resync_product( $product_id, $product_map[ (string) $supplier_id ], $api_page );
                return $results;
            }

            if ( 'cancel' === $action ) {
                $product_id = $this->find_imported_product_id( $supplier_id );
                if ( ! $product_id ) {
                    return array( new WP_Error( 'taqi_active_link_missing', 'No active synchronization was found to cancel.' ) );
                }
                $results[] = $this->cancel_product_sync( $product_id, $supplier_id );
                return $results;
            }

            if ( 'relink' === $action ) {
                $product_id = $this->find_cancelled_product_id( $supplier_id );
                if ( ! $product_id ) {
                    return array( new WP_Error( 'taqi_cancelled_link_missing', 'No cancelled synchronization was found to re-link.' ) );
                }
                $results[] = $this->relink_product_sync( $product_id, $product_map[ (string) $supplier_id ], $api_page );
                return $results;
            }

            if ( 'delete' === $action ) {
                $product_id = $this->find_imported_product_id( $supplier_id );
                if ( ! $product_id ) {
                    $product_id = $this->find_cancelled_product_id( $supplier_id );
                }
                if ( ! $product_id ) {
                    return array( new WP_Error( 'taqi_linked_product_missing', 'No linked WooCommerce product was found to delete.' ) );
                }
                $results[] = $this->delete_synced_product( $product_id, $supplier_id );
                return $results;
            }
        }

        $selected = array();
        if ( 'single' === $action && isset( $_POST['single_supplier_id'] ) ) {
            $selected[] = sanitize_text_field( wp_unslash( $_POST['single_supplier_id'] ) );
        } elseif ( 'bulk' === $action && ! empty( $_POST['supplier_ids'] ) && is_array( $_POST['supplier_ids'] ) ) {
            $selected = array_map( 'sanitize_text_field', wp_unslash( $_POST['supplier_ids'] ) );
        } else {
            return array( new WP_Error( 'taqi_unknown_product_action', 'Unknown product action. No changes were made.' ) );
        }

        $selected = array_values( array_unique( array_filter( $selected, 'strlen' ) ) );
        if ( empty( $selected ) ) {
            return array( new WP_Error( 'taqi_nothing_selected', 'Select at least one supplier product to import.' ) );
        }

        foreach ( $selected as $supplier_id ) {
            if ( ! isset( $product_map[ (string) $supplier_id ] ) ) {
                $results[] = new WP_Error( 'taqi_product_not_on_page', 'Supplier product ' . $supplier_id . ' was not found on the current API page.' );
                continue;
            }

            $result = $this->import_product( $product_map[ (string) $supplier_id ], $api_page, $import_category_id, $import_category_path );
            if ( is_wp_error( $result ) ) {
                $results[] = $result;
            } else {
                $result['supplier_id'] = $supplier_id;
                $results[] = $result;
            }
        }

        return $results;
    }

    public function ajax_process_all_pages() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        check_ajax_referer( 'taqi_all_pages_ajax', 'nonce' );

        $action = isset( $_POST['batch_action'] ) ? sanitize_key( wp_unslash( $_POST['batch_action'] ) ) : '';
        $import_category_id   = isset( $_POST['import_category_id'] ) ? absint( $_POST['import_category_id'] ) : 0;
        $import_category_path = isset( $_POST['import_category_path'] ) ? sanitize_text_field( wp_unslash( $_POST['import_category_path'] ) ) : '';
        $supplier_category_filter = isset( $_POST['supplier_category_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier_category_filter'] ) ) : '';
        $page   = isset( $_POST['batch_page'] ) ? max( 1, absint( $_POST['batch_page'] ) ) : 1;
        $item   = isset( $_POST['batch_item'] ) ? max( 0, absint( $_POST['batch_item'] ) ) : 0;
        $total  = isset( $_POST['batch_total'] ) ? min( 50, max( 1, absint( $_POST['batch_total'] ) ) ) : 1;
        if ( ! in_array( $action, array( 'all_import', 'all_resync', 'all_cancel' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid batch action.' ), 400 );
        }

        set_time_limit( 120 );
        $data = $this->api_request_products( $page );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => 'Page ' . $page . ': ' . $data->get_error_message() ) );
        }

        $products = $this->extract_products( $data );
        if ( ! isset( $products[ $item ] ) ) {
            wp_send_json_success( array( 'page' => $page, 'item' => $item, 'count' => count( $products ), 'page_done' => true, 'done' => $page >= $total ) );
        }

        $product    = $products[ $item ];
        $supplier_id = $this->supplier_product_id( $product );
        $linked_map = in_array( $action, array( 'all_resync', 'all_cancel' ), true ) ? $this->linked_products_map() : array();
        $processed  = 0;
        $skipped    = 0;
        $failed     = 0;
        $errors     = array();
        $result     = null;
        if ( '' === $supplier_id ) {
            ++$skipped;
        } elseif ( ! $this->matches_supplier_category_filter( $product, $supplier_category_filter ) ) {
            ++$skipped;
        } elseif ( 'all_import' === $action ) {
                    $result = $this->import_product( $product, $page );
        } else {
            $linked = isset( $linked_map[ (string) $supplier_id ] ) ? $linked_map[ (string) $supplier_id ] : array();
            $product_id = isset( $linked['active'] ) ? absint( $linked['active'] ) : 0;
            if ( ! $product_id ) {
                ++$skipped;
            } else {
                $result = 'all_resync' === $action
                    ? $this->resync_product( $product_id, $product, $page )
                    : $this->cancel_product_sync( $product_id, $supplier_id );
            }
        }
        if ( null !== $result && is_wp_error( $result ) ) {
            ++$failed;
            $errors[] = $supplier_id . ': ' . $result->get_error_message();
        } elseif ( null !== $result ) {
            ++$processed;
        }

        wp_send_json_success(
            array(
                'page'      => $page,
                'item'      => $item,
                'count'     => count( $products ),
                'total'     => $total,
                'processed' => $processed,
                'skipped'   => $skipped,
                'failed'    => $failed,
                'errors'    => $errors,
                'done'      => $page >= $total,
            )
        );
    }

    public function supplier_products_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $api_page = isset( $_GET['supplier_page'] ) ? max( 1, absint( $_GET['supplier_page'] ) ) : 1;
        $search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $category_filter = isset( $_GET['supplier_category'] ) ? sanitize_text_field( wp_unslash( $_GET['supplier_category'] ) ) : '';
        $data     = $this->api_request_products( $api_page );
        ?>
        <div class="wrap taqi-products-screen">
            <style>
                .taqi-products-screen .taqi-page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:20px 0 18px}
                .taqi-products-screen .taqi-page-head h1{margin:0 0 6px;font-size:28px;line-height:1.2}
                .taqi-products-screen .taqi-page-head p{margin:0;color:#646970;font-size:13px}
                .taqi-products-screen .taqi-page-head-actions{display:flex;gap:8px;flex-wrap:wrap}
                .taqi-products-screen .taqi-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px;margin:0 0 16px;box-shadow:0 1px 2px #0000000a}
                .taqi-products-screen .taqi-toolbar input[type=search]{min-width:340px;border-radius:6px}
                .taqi-products-screen .taqi-summary{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px}
                .taqi-products-screen .taqi-summary span{display:inline-flex;gap:5px;align-items:center;background:#f6f7f7;border:1px solid #dcdcde;border-radius:999px;padding:5px 10px;color:#50575e;font-size:12px}
                .taqi-products-screen .taqi-import-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#f0f6fc;border:1px solid #c5d9ed;border-bottom:0;border-radius:8px 8px 0 0;padding:12px}
                .taqi-products-screen .taqi-import-bar .description{color:#50575e}
                .taqi-products-screen .taqi-batch-progress{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#fff;border:1px solid #c3c4c7;border-top:0;padding:10px 12px;margin-bottom:14px}
                .taqi-products-screen .taqi-batch-progress progress{width:220px;height:16px}
                .taqi-products-screen .taqi-batch-cancel{margin-left:auto;color:#b32d2e;border-color:#d63638}
                .taqi-products-screen .taqi-products-table{border:1px solid #dcdcde;border-radius:0 0 8px 8px;overflow:hidden;box-shadow:0 1px 2px #0000000a}
                .taqi-products-screen .taqi-products-table thead th,.taqi-products-screen .taqi-products-table thead td{background:#f6f7f7;color:#50575e;font-size:11px;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
                .taqi-products-screen .taqi-products-table tbody tr:not(.taqi-detail-row):hover{background:#f8fbff}
                .taqi-products-screen .taqi-products-table tbody td,.taqi-products-screen .taqi-products-table tbody th{vertical-align:middle;padding-top:10px;padding-bottom:10px}
                .taqi-products-screen .taqi-sl-column{width:46px;text-align:center;color:#646970;font-weight:600}
                .taqi-products-screen .taqi-products-table img{box-shadow:0 1px 3px #0002}
                .taqi-products-screen .taqi-action-buttons{white-space:nowrap;min-width:470px}
                .taqi-products-screen .taqi-action-buttons .button,.taqi-products-screen .taqi-action-buttons .button-link-delete{display:inline-block;margin:2px 4px 2px 0!important;vertical-align:middle;box-sizing:border-box;height:30px;line-height:28px}
                .taqi-products-screen .taqi-action-buttons .button-link-delete{border:1px solid #d63638;border-radius:5px;background:#fff;color:#b32d2e;padding:0 10px;line-height:28px;text-decoration:none}
                .taqi-products-screen .taqi-action-buttons .button-link-delete:hover{background:#fff0f0;color:#8a1f20}
                .taqi-products-screen .tablenav{clear:both;margin:16px 0}
                .taqi-products-screen .tablenav-pages{float:none!important;text-align:left}
                .taqi-products-screen .tablenav-pages .page-numbers{display:inline-flex!important;align-items:center;justify-content:center;min-width:32px;height:32px;margin:0 4px 0 0!important;padding:0 8px;border:1px solid #c3c4c7;border-radius:5px;text-decoration:none;box-sizing:border-box}
                .taqi-products-screen .tablenav-pages .page-numbers.current{background:#2271b1;border-color:#2271b1;color:#fff;font-weight:600}
                .taqi-products-screen .tablenav-pages .page-numbers.dots{border:0}
                .taqi-products-screen .taqi-detail-row td{background:#f8fafc!important;border-top:1px solid #dcdcde}
                .taqi-products-screen .button{border-radius:5px}
                @media (max-width:782px){.taqi-products-screen .taqi-page-head{align-items:flex-start;flex-direction:column}.taqi-products-screen .taqi-toolbar input[type=search]{min-width:0;width:100%}.taqi-products-screen .taqi-products-table{display:block;overflow-x:auto;white-space:nowrap}}
            </style>
            <div class="taqi-page-head"><div><h1>Supplier Products</h1><p>Browse the supplier catalogue, review pricing, and import products into WooCommerce.</p></div><div class="taqi-page-head-actions"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=taqi-dropshipping-imported' ) ); ?>">Imported Products</a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=taqi-dropshipping-settings' ) ); ?>">API Settings</a></div></div>
            <?php
            if ( is_wp_error( $data ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html( $data->get_error_message() ) . '</p></div>';
                echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=taqi-dropshipping-settings' ) ) . '">Open API Settings</a></p></div>';
                return;
            }

            $products  = $this->extract_products( $data );
            $last_page = max( 1, $this->extract_last_page( $data ) );
            $linked_products = $this->linked_products_map();

            $import_results = $this->handle_product_imports( $products, $api_page, $last_page );
            foreach ( $import_results as $result ) {
                if ( is_wp_error( $result ) ) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                    continue;
                }

                $status = isset( $result['status'] ) ? $result['status'] : '';
                if ( 'duplicate' === $status ) {
                    echo '<div class="notice notice-warning is-dismissible"><p>Supplier product ' . esc_html( $result['supplier_id'] ) . ' is already imported and linked. <a href="' . esc_url( get_edit_post_link( $result['product_id'] ) ) . '">Edit WooCommerce product</a>.</p></div>';
                } elseif ( 'imported' === $status ) {
                    echo '<div class="notice notice-success is-dismissible"><p>Supplier product ' . esc_html( $result['supplier_id'] ) . ' imported successfully as <strong>' . esc_html( $result['type'] ) . '</strong> and linked for sync. <a href="' . esc_url( get_edit_post_link( $result['product_id'] ) ) . '">Review product</a>.</p></div>';
                } elseif ( in_array( $status, array( 'resynced', 'cancelled', 'deleted' ), true ) ) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $result['message'] ) . '</p></div>';
                } else {
                    echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( isset( $result['message'] ) ? $result['message'] : 'Product action completed.' ) . '</p></div>';
                }
            }

            if ( $search ) {
                $needle   = strtolower( $search );
                $products = array_values(
                    array_filter(
                        $products,
                        function ( $product ) use ( $needle ) {
                            $category = $this->supplier_category( $product );
                            $haystack = strtolower( implode( ' ', array( $this->scalar( isset( $product['name'] ) ? $product['name'] : '' ), $this->scalar( isset( $product['product_code'] ) ? $product['product_code'] : '' ), $this->supplier_product_id( $product ), $category['name'], $category['id'] ) ) );
                            return false !== strpos( $haystack, $needle );
                        }
                    )
                );
            }
            if ( $category_filter ) {
                $products = array_values( array_filter( $products, function ( $product ) use ( $category_filter ) {
                    return $this->matches_supplier_category_filter( $product, $category_filter );
                } ) );
            }
            ?>

            <form method="get" class="taqi-toolbar">
                <input type="hidden" name="page" value="taqi-dropshipping-products">
                <input type="hidden" name="supplier_page" value="<?php echo esc_attr( $api_page ); ?>">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search current API page by name/code/ID/category" style="min-width:340px;">
                <input type="search" name="supplier_category" value="<?php echo esc_attr( $category_filter ); ?>" placeholder="Filter supplier category" style="min-width:220px;">
                <button class="button">Search</button>
                <?php if ( $search || $category_filter ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=taqi-dropshipping-products&supplier_page=' . $api_page ) ); ?>">Clear</a><?php endif; ?>
            </form>

            <div class="taqi-summary"><span><strong>API page</strong> <?php echo esc_html( $api_page ); ?> / <?php echo esc_html( $last_page ); ?></span><span><strong>Products shown</strong> <?php echo esc_html( count( $products ) ); ?></span><?php if ( $search ) : ?><span><strong>Search</strong> <?php echo esc_html( $search ); ?></span><?php endif; ?><?php if ( $category_filter ) : ?><span><strong>Supplier category</strong> <?php echo esc_html( $category_filter ); ?></span><?php endif; ?></div>

            <form method="post">
                <?php wp_nonce_field( 'taqi_import_products_' . $api_page, 'taqi_import_nonce' ); ?>
                <input type="hidden" name="taqi_import_action" id="taqi_import_action" value="bulk">
                <input type="hidden" name="single_supplier_id" id="single_supplier_id" value="">
                <div class="taqi-import-bar">
                    <label><strong>Import category:</strong>
                        <select name="taqi_import_category_id" style="min-width:210px;">
                            <option value="0">Use supplier/mapping category</option>
                            <?php foreach ( $this->category_choices() as $term_id => $label ) : ?><option value="<?php echo esc_attr( $term_id ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <input type="text" name="taqi_import_category_path" placeholder="Or create path: Electronics > Chargers" style="min-width:260px;">
                </div>
                <div class="taqi-import-bar"><button type="submit" class="button button-primary" onclick="document.getElementById('taqi_import_action').value='bulk';">Import Selected Page</button><button type="submit" class="button" data-bulk-action="bulk_resync">Re-sync Selected</button><button type="submit" class="button" data-bulk-action="bulk_cancel" data-confirm="Cancel synchronization for all selected linked products?">Cancel Selected</button><button type="submit" class="button button-primary" data-all-action="all_import">Import All <?php echo esc_html( $last_page ); ?> Pages</button><button type="submit" class="button" data-all-action="all_resync">Re-sync All <?php echo esc_html( $last_page ); ?> Pages</button><button type="submit" class="button" data-all-action="all_cancel" data-confirm="Cancel synchronization across all <?php echo esc_attr( $last_page ); ?> pages?">Cancel All <?php echo esc_html( $last_page ); ?> Pages</button><span class="description">Selected actions apply to this page. All-page actions run one supplier page at a time with progress.</span></div>
                <div id="taqi-batch-progress" class="taqi-batch-progress" hidden><strong id="taqi-batch-label">Preparing batch…</strong><progress id="taqi-batch-bar" value="0" max="<?php echo esc_attr( $last_page ); ?>"></progress><span id="taqi-batch-detail"></span><button type="button" id="taqi-batch-cancel" class="button taqi-batch-cancel">Cancel Batch</button></div>

                <table class="widefat striped taqi-products-table" style="table-layout:auto;">
                    <thead><tr><td class="check-column"><input type="checkbox" id="taqi-select-all"></td><th class="taqi-sl-column">SL</th><th style="width:70px;">Image</th><th>Product</th><th>Category</th><th>Code</th><th>Cost Price</th><th>Minimum Sale</th><th>Maximum Sale</th><th>TAQI Price</th><th>Type</th><th style="min-width:470px;">Action</th></tr></thead>
                    <tbody>
                    <?php if ( empty( $products ) ) : ?><tr><td colspan="12">No products found on this page.</td></tr><?php else : ?>
                        <?php foreach ( $products as $index => $product ) :
                            $supplier_id = $this->supplier_product_id( $product );
                            $name        = isset( $product['name'] ) ? $this->scalar( $product['name'] ) : ( 'Product ' . $supplier_id );
                            $code        = isset( $product['product_code'] ) ? $this->scalar( $product['product_code'] ) : '';
                            $details     = isset( $product['details'] ) ? wp_strip_all_tags( $this->scalar( $product['details'] ) ) : '';
                            $category    = $this->supplier_category( $product );
                            $thumbs      = array();
                            foreach ( array( 'thumbnail_img', 'thumbnail', 'image' ) as $key ) {
                                if ( ! empty( $product[ $key ] ) ) {
                                    $thumbs = $this->normalize_image_urls( $product[ $key ] );
                                    if ( $thumbs ) { break; }
                                }
                            }
                            $linked       = isset( $linked_products[ (string) $supplier_id ] ) ? $linked_products[ (string) $supplier_id ] : array();
                            $imported_id  = isset( $linked['active'] ) ? absint( $linked['active'] ) : 0;
                            $cancelled_id = isset( $linked['cancelled'] ) ? absint( $linked['cancelled'] ) : 0;
                            $has_vars     = $this->has_variations( $product );
                            $model        = $has_vars ? $this->build_variation_model( $product ) : array( 'rows' => array(), 'attributes' => array() );
                            $can_var     = $has_vars && ! empty( $model['rows'] );
                            $supplier_image_count = count( $this->supplier_image_urls( $product ) );
                            $detail_id   = 'taqi-detail-' . absint( $index );
                            ?>
                            <tr>
                                <th class="check-column"><?php if ( '' !== $supplier_id ) : ?><input class="taqi-product-check" type="checkbox" name="supplier_ids[]" value="<?php echo esc_attr( $supplier_id ); ?>"><?php endif; ?></th>
                                <td class="taqi-sl-column"><?php echo esc_html( ( ( $api_page - 1 ) * count( $products ) ) + $index + 1 ); ?></td>
                                <td><?php if ( ! empty( $thumbs[0] ) ) : ?><img src="<?php echo esc_url( $thumbs[0] ); ?>" alt="" width="54" height="54" loading="lazy" decoding="async" style="object-fit:cover;border:1px solid #ddd;border-radius:4px;"><?php else : ?>—<?php endif; ?></td>
                                <td><strong><?php echo esc_html( $name ); ?></strong><br><small>Supplier ID: <?php echo esc_html( $supplier_id ? $supplier_id : '—' ); ?></small></td>
                                <td><?php echo esc_html( $category['name'] ? $category['name'] : '—' ); ?><br><small>ID: <?php echo esc_html( $category['id'] ? $category['id'] : '—' ); ?></small></td>
                                <td><?php echo esc_html( $code ? $code : '—' ); ?></td>
                                <?php $row_pricing = $this->pricing_breakdown( $product ); ?>
                                <td><?php echo null !== $row_pricing['cost'] ? $this->format_money( $row_pricing['cost'] ) : '—'; ?></td>
                                <td><?php echo null !== $row_pricing['minimum'] ? $this->format_money( $row_pricing['minimum'] ) : '—'; ?></td>
                                <td><?php echo null !== $row_pricing['maximum'] ? $this->format_money( $row_pricing['maximum'] ) : '—'; ?></td>
                                <td><?php echo null !== $row_pricing['selling'] ? '<strong>' . $this->format_money( $row_pricing['selling'] ) . '</strong>' : '<span style="color:#b32d2e;">Blocked</span>'; ?></td>
                                <td><?php echo $has_vars ? ( $can_var ? '<strong>Variable ready</strong>' : '<span title="Supplier variant references exist but readable attributes are unresolved">Unresolved variants*</span>' ) : 'Simple'; ?></td>
                                <td class="taqi-action-buttons">
                                    <button type="button" class="button taqi-toggle-details" data-target="<?php echo esc_attr( $detail_id ); ?>">View</button>
                                    <?php if ( $imported_id ) : ?>
                                        <a class="button" href="<?php echo esc_url( get_edit_post_link( $imported_id ) ); ?>">Edit</a>
                                        <button type="submit" class="button button-primary taqi-product-action" data-action="resync" data-id="<?php echo esc_attr( $supplier_id ); ?>">Re-sync</button>
                                        <button type="submit" class="button taqi-product-action" data-action="cancel" data-id="<?php echo esc_attr( $supplier_id ); ?>" data-confirm="Cancel synchronization? The WooCommerce product will remain, but supplier updates will stop until you re-link it.">Cancel Sync</button>
                                        <button type="submit" class="button-link-delete taqi-product-action" style="margin-left:6px;" data-action="delete" data-id="<?php echo esc_attr( $supplier_id ); ?>" data-confirm="Permanently delete this imported WooCommerce product? This cannot be undone. Supplier API data will NOT be deleted.">Delete</button>
                                    <?php elseif ( $cancelled_id ) : ?>
                                        <a class="button" href="<?php echo esc_url( get_edit_post_link( $cancelled_id ) ); ?>">Edit Local</a>
                                        <button type="submit" class="button button-primary taqi-product-action" data-action="relink" data-id="<?php echo esc_attr( $supplier_id ); ?>">Re-link &amp; Sync</button>
                                        <button type="submit" class="button-link-delete taqi-product-action" style="margin-left:6px;" data-action="delete" data-id="<?php echo esc_attr( $supplier_id ); ?>" data-confirm="Permanently delete this cancelled WooCommerce product? This cannot be undone. Supplier API data will NOT be deleted.">Delete</button>
                                    <?php elseif ( '' !== $supplier_id ) : ?>
                                        <button type="submit" class="button button-primary taqi-single-import" data-id="<?php echo esc_attr( $supplier_id ); ?>">Import</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr id="<?php echo esc_attr( $detail_id ); ?>" style="display:none;background:#f6f7f7;"><td></td><td colspan="10"><p><strong>Supplier images detected:</strong> <?php echo esc_html( $supplier_image_count ); ?></p><p><strong>Price diagnostics:</strong> Cost (API <code>sale_price</code>) = <?php echo null !== $row_pricing['cost'] ? wp_kses_post( $this->format_money( $row_pricing['cost'] ) ) : '—'; ?> &nbsp; | &nbsp; Minimum <?php echo esc_html( number_format_i18n( $row_pricing['minimum_markup_percent'], 2 ) ); ?>% = <?php echo null !== $row_pricing['minimum'] ? wp_kses_post( $this->format_money( $row_pricing['minimum'] ) ) : '—'; ?> &nbsp; | &nbsp; TAQI <?php echo esc_html( number_format_i18n( $row_pricing['taqi_markup_percent'], 2 ) ); ?>% = <strong><?php echo null !== $row_pricing['selling'] ? wp_kses_post( $this->format_money( $row_pricing['selling'] ) ) : 'Blocked'; ?></strong> &nbsp; | &nbsp; Maximum (API <code>price</code>) = <?php echo null !== $row_pricing['maximum'] ? wp_kses_post( $this->format_money( $row_pricing['maximum'] ) ) : '—'; ?><?php if ( ! empty( $row_pricing['warning'] ) ) : ?> <span style="color:#b32d2e;"><?php echo esc_html( $row_pricing['warning'] ); ?></span><?php endif; ?></p><strong>Description:</strong> <?php echo esc_html( $details ? wp_trim_words( $details, 70, '…' ) : 'No description in this API response.' ); ?><?php if ( $has_vars && ! $can_var ) : ?><p><em>Supplier variant references were detected, but readable Size/Color values are unavailable in the current Product API payload. Variation Mapping will show only safely mappable values; unresolved IDs are diagnostic only.</em></p><?php elseif ( $can_var ) : ?><p><strong>Detected attributes:</strong> <?php echo esc_html( implode( ', ', array_keys( $model['attributes'] ) ) ); ?></p><?php endif; ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </form>

            <?php
            $pagination = paginate_links(
                array(
                    'base'      => add_query_arg( 'supplier_page', '%#%', admin_url( 'admin.php?page=taqi-dropshipping-products' ) ),
                    'format'    => '',
                    'current'   => $api_page,
                    'total'     => $last_page,
                    'type'      => 'list',
                    'prev_text' => '‹ Previous',
                    'next_text' => 'Next ›',
                )
            );
            if ( $pagination ) {
                echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $pagination ) . '</div></div>';
            }
            ?>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('taqi-select-all');
            if (selectAll) selectAll.addEventListener('change', function () { document.querySelectorAll('.taqi-product-check').forEach(function (cb) { cb.checked = selectAll.checked; }); });
            document.querySelectorAll('.taqi-toggle-details').forEach(function (btn) { btn.addEventListener('click', function () { const row = document.getElementById(btn.dataset.target); if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none'; }); });
            document.querySelectorAll('.taqi-single-import').forEach(function (btn) { btn.addEventListener('click', function () { document.getElementById('taqi_import_action').value = 'single'; document.getElementById('single_supplier_id').value = btn.dataset.id; }); });
            document.querySelectorAll('.taqi-product-action').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    if (btn.dataset.confirm && !window.confirm(btn.dataset.confirm)) {
                        event.preventDefault();
                        return;
                    }
                    document.getElementById('taqi_import_action').value = btn.dataset.action;
                    document.getElementById('single_supplier_id').value = btn.dataset.id;
                });
            });
            document.querySelectorAll('[data-bulk-action]').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    const selected = document.querySelectorAll('.taqi-product-check:checked');
                    if (!selected.length) {
                        event.preventDefault();
                        window.alert('Select at least one product first.');
                        return;
                    }
                    if (btn.dataset.confirm && !window.confirm(btn.dataset.confirm)) {
                        event.preventDefault();
                        return;
                    }
                    document.getElementById('taqi_import_action').value = btn.dataset.bulkAction;
                });
            });
            document.querySelectorAll('[data-all-action]').forEach(function (btn) {
                btn.addEventListener('click', async function (event) {
                    event.preventDefault();
                    if (btn.dataset.confirm && !window.confirm(btn.dataset.confirm)) {
                        return;
                    }
                    const progress = document.getElementById('taqi-batch-progress');
                    const bar = document.getElementById('taqi-batch-bar');
                    const label = document.getElementById('taqi-batch-label');
                    const detail = document.getElementById('taqi-batch-detail');
                    const cancelButton = document.getElementById('taqi-batch-cancel');
                    const buttons = document.querySelectorAll('[data-all-action], [data-bulk-action], .taqi-import-bar button');
                    const total = <?php echo absint( $last_page ); ?>;
                    const resumeKey = 'taqi_batch_resume_' + btn.dataset.allAction;
                    let resume = null, batchCancelled = false;
                    try { resume = JSON.parse(localStorage.getItem(resumeKey) || 'null'); } catch (ignore) {}
                    let startPage = 1, startItem = 0;
                    if (resume && resume.page && typeof resume.item === 'number') {
                        if (window.confirm('Resume this batch from page ' + resume.page + ', product ' + (resume.item + 1) + '?')) {
                            startPage = Number(resume.page); startItem = Number(resume.item);
                        } else {
                            localStorage.removeItem(resumeKey);
                        }
                    }
                    let processed = 0, skipped = 0, failed = 0, errors = [];
                    progress.hidden = false; bar.value = 0; label.textContent = 'Processing ' + btn.textContent.trim() + '…';
                    buttons.forEach(function (item) { item.disabled = true; });
                    cancelButton.disabled = false; cancelButton.hidden = false;
                    cancelButton.onclick = function () { batchCancelled = true; cancelButton.disabled = true; label.textContent = 'Stopping after current product…'; };
                    try {
                        for (let page = startPage; page <= total; page++) {
                            let item = page === startPage ? startItem : 0, count = 0;
                            while (true) {
                                if (batchCancelled) break;
                                const body = new URLSearchParams({
                                    action: 'taqi_process_all_pages',
                                    batch_action: btn.dataset.allAction,
                                    batch_page: String(page),
                                    batch_item: String(item),
                                    batch_total: String(total),
                                    import_category_id: (document.querySelector('[name="taqi_import_category_id"]') || {}).value || '0',
                                    import_category_path: (document.querySelector('[name="taqi_import_category_path"]') || {}).value || '',
                                    supplier_category_filter: (document.querySelector('[name="supplier_category"]') || {}).value || '',
                                    nonce: '<?php echo esc_js( wp_create_nonce( 'taqi_all_pages_ajax' ) ); ?>'
                                });
                                const response = await fetch(ajaxurl, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body});
                                const result = await response.json();
                                if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : 'Batch operation failed.');
                                count = Number(result.data.count || 0);
                                processed += Number(result.data.processed || 0); skipped += Number(result.data.skipped || 0); failed += Number(result.data.failed || 0);
                                if (Array.isArray(result.data.errors)) errors = errors.concat(result.data.errors);
                                bar.max = count || 1; bar.value = count ? item + 1 : 1;
                                detail.textContent = 'Page ' + page + ' of ' + total + ' · product ' + Math.min(item + 1, count) + ' of ' + count + ' · processed ' + processed + ' · skipped ' + skipped + ' · failed ' + failed;
                                if (errors.length) detail.title = errors.join('\n');
                                if (!count || item + 1 >= count) break;
                                item++; localStorage.setItem(resumeKey, JSON.stringify({page: page, item: item}));
                            }
                            if (batchCancelled) break;
                            if (page < total) localStorage.setItem(resumeKey, JSON.stringify({page: page + 1, item: 0}));
                        }
                        if (batchCancelled) {
                            label.textContent = 'Batch operation cancelled.'; detail.textContent += ' Resume is available from the next product.';
                        } else {
                            localStorage.removeItem(resumeKey); label.textContent = 'Batch operation completed.';
                        }
                    } catch (error) {
                        label.textContent = 'Batch operation stopped'; detail.textContent = error.message;
                    }
                    buttons.forEach(function (item) { item.disabled = false; });
                    cancelButton.hidden = true;
                });
            });
        });
        </script>
        <?php
    }

    private function handle_imported_product_management() {
        if ( ! empty( $_POST['taqi_bulk_status_action'] ) ) {
            check_admin_referer( 'taqi_bulk_status_action', 'taqi_bulk_status_nonce' );
            $action = sanitize_key( wp_unslash( $_POST['taqi_bulk_status_action'] ) );
            $status = 'publish' === $action ? 'publish' : ( 'unpublish' === $action ? 'draft' : '' );
            $ids    = ! empty( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['product_ids'] ) ) : array();
            $updated = 0;
            if ( $status && $ids ) {
                foreach ( array_unique( array_filter( $ids ) ) as $product_id ) {
                    if ( current_user_can( 'edit_post', $product_id ) && 'taqi_product' === get_post_type( $product_id ) ) {
                        wp_update_post( array( 'ID' => $product_id, 'post_status' => $status ) );
                        ++$updated;
                    }
                }
            }
            return array( 'message' => $updated . ( 1 === $updated ? ' product' : ' products' ) . ( 'publish' === $action ? ' published successfully.' : ' unpublished and returned to Draft.' ) );
        }

        if ( empty( $_POST['taqi_manage_imported_action'] ) ) {
            return null;
        }

        check_admin_referer( 'taqi_manage_imported_product', 'taqi_manage_imported_nonce' );

        $action      = sanitize_key( wp_unslash( $_POST['taqi_manage_imported_action'] ) );
        $product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $supplier_id = isset( $_POST['supplier_id'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier_id'] ) ) : '';

        if ( ! $product_id || '' === $supplier_id ) {
            return new WP_Error( 'taqi_management_validation', 'Product validation failed. No action was taken.' );
        }

        if ( 'cancel' === $action ) {
            return $this->cancel_product_sync( $product_id, $supplier_id );
        }

        if ( 'delete' === $action ) {
            $delete_confirmation = isset( $_POST['taqi_delete_confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['taqi_delete_confirmation'] ) ) : '';
            if ( 'DELETE' !== $delete_confirmation ) {
                return new WP_Error( 'taqi_delete_not_confirmed', 'Delete was not confirmed. No product was deleted.' );
            }
            return $this->delete_synced_product( $product_id, $supplier_id );
        }

        if ( in_array( $action, array( 'publish', 'unpublish' ), true ) ) {
            $new_status = 'publish' === $action ? 'publish' : 'draft';
            if ( $new_status !== get_post_status( $product_id ) ) {
                wp_update_post( array( 'ID' => $product_id, 'post_status' => $new_status ) );
            }
            return array( 'message' => 'publish' === $action ? 'Product published successfully.' : 'Product unpublished and returned to draft.' );
        }

        if ( in_array( $action, array( 'resync', 'relink' ), true ) ) {
            $page_hint = absint( get_post_meta( $product_id, '_taqi_supplier_api_page', true ) );
            $live      = $this->find_live_supplier_product( $supplier_id, $page_hint );
            if ( is_wp_error( $live ) ) {
                return $live;
            }

            if ( 'relink' === $action ) {
                return $this->relink_product_sync( $product_id, $live['product'], $live['page'] );
            }

            return $this->resync_product( $product_id, $live['product'], $live['page'] );
        }

        return new WP_Error( 'taqi_unknown_management_action', 'Unknown product management action. No changes were made.' );
    }

    public function imported_products_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $management_result = $this->handle_imported_product_management();

        $imported_page = isset( $_GET['imported_page'] ) ? max( 1, absint( $_GET['imported_page'] ) ) : 1;
        $query = new WP_Query(
            array(
                'post_type'      => 'taqi_product',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 50,
                'paged'          => $imported_page,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'meta_query'     => array( array( 'key' => '_taqi_supplier', 'value' => $this->supplier_key() ) ),
            )
        );
        ?>
        <div class="wrap taqi-imported-screen">
            <style>
                .taqi-imported-screen h1{margin-bottom:6px}
                .taqi-imported-screen .taqi-sync-note{max-width:none;margin:18px 0}
                .taqi-imported-screen .taqi-status-toolbar{position:sticky;top:32px;z-index:5;display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:14px 0;padding:12px;background:#fff;border:1px solid #dcdcde;border-radius:8px;box-shadow:0 1px 2px #0001}
                .taqi-imported-screen .taqi-selection-count{font-size:13px;color:#50575e;margin-left:4px}
                .taqi-imported-screen .taqi-status-table-wrap{overflow-x:auto;border:1px solid #dcdcde;border-radius:8px;background:#fff}
                .taqi-imported-screen .taqi-status-table{min-width:1240px;border:0}
                .taqi-imported-screen .taqi-status-table thead th{background:#f6f7f7;color:#50575e;font-size:11px;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
                .taqi-imported-screen .taqi-status-table td,.taqi-imported-screen .taqi-status-table th{vertical-align:middle;padding:10px 8px}
                .taqi-imported-screen .taqi-imported-sl{width:46px;text-align:center;color:#646970;font-weight:600}
                .taqi-imported-screen .taqi-status-table tbody tr:hover{background:#f8fbff}
                .taqi-imported-screen .taqi-status-actions{display:flex;align-items:center;gap:5px;flex-wrap:wrap;min-width:430px}
                .taqi-imported-screen .taqi-status-actions form{display:inline-flex;margin:0}
                .taqi-imported-screen .taqi-status-actions .button,.taqi-imported-screen .taqi-status-actions .button-link-delete{height:30px;line-height:28px;margin:0!important;box-sizing:border-box}
                .taqi-imported-screen .taqi-status-actions .button-link-delete{border:1px solid #d63638;border-radius:5px;background:#fff;color:#b32d2e;padding:0 10px;text-decoration:none}
                .taqi-imported-screen .taqi-status-actions .button-link-delete:hover{background:#fff0f0}
                .taqi-imported-screen .taqi-status-pagination{margin:16px 0}
                .taqi-imported-screen .taqi-status-pagination .page-numbers{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;margin-right:5px;border:1px solid #c3c4c7;border-radius:5px;text-decoration:none}
                .taqi-imported-screen .taqi-status-pagination .current{background:#2271b1;border-color:#2271b1;color:#fff}
            </style>
            <h1>Imported Products</h1>
            <p>Products linked to <?php echo esc_html( $this->settings()['supplier_name'] ); ?> through TAQI LIFE Dropshipping.</p>

            <div class="notice notice-info inline taqi-sync-note">
                <p><strong>Safe sync behavior:</strong> Re-sync refreshes supplier price, sale price, stock, mapped categories and supplier metadata. Your local product title, description and downloaded images are preserved; missing supplier images are added when safely detected. <strong>Cancel Sync</strong> keeps the WooCommerce product but stops synchronization. <strong>Delete</strong> permanently deletes only the local WooCommerce product; it never deletes anything from the supplier API.</p>
            </div>

            <?php if ( is_wp_error( $management_result ) ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $management_result->get_error_message() ); ?></p></div>
            <?php elseif ( is_array( $management_result ) && ! empty( $management_result['message'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $management_result['message'] ); ?></p></div>
            <?php endif; ?>

            <form method="post" id="taqi-imported-batch-form" class="taqi-status-toolbar">
                <?php wp_nonce_field( 'taqi_bulk_status_action', 'taqi_bulk_status_nonce' ); ?>
                <button type="button" class="button" id="taqi-imported-select-all-button">Select All</button>
                <button type="button" class="button" id="taqi-imported-clear-button">Clear Selection</button>
                <span class="taqi-selection-count"><strong id="taqi-imported-selected-count">0</strong> selected</span>
                <button type="submit" name="taqi_bulk_status_action" value="publish" class="button button-primary" onclick="return taqiConfirmImportedBatch('publish');">Publish Selected</button>
                <button type="submit" name="taqi_bulk_status_action" value="unpublish" class="button" onclick="return taqiConfirmImportedBatch('unpublish');">Unpublish Selected</button>
                <span class="description">Select products below. Actions apply to the current page.</span>
            </form>

            <div class="taqi-status-table-wrap"><table class="widefat striped taqi-status-table">
                <thead>
                    <tr>
                        <th class="check-column"><label title="Select all products on this page"><input type="checkbox" id="taqi-imported-select-all"> <span style="font-size:11px;">All</span></label></th>
                        <th class="taqi-imported-sl">SL</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Supplier ID</th>
                        <th>Supplier Code</th>
                        <th>Supplier Category</th>
                        <th>Woo Status</th>
                        <th>Sync Status</th>
                        <th>Last Sync</th>
                        <th>Price</th>
                        <th style="min-width:360px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! $query->have_posts() ) : ?>
                    <tr><td colspan="12">No supplier products imported yet.</td></tr>
                <?php else : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post();
                        $product_id   = get_the_ID();
                        $raw_price    = get_post_meta( $product_id, '_taqi_regular_price', true );
                        $product_type = 'variable' === get_post_meta( $product_id, '_taqi_product_type', true ) ? 'variable' : 'simple';
                        $supplier_id  = (string) get_post_meta( $product_id, '_taqi_supplier_id', true );
                        $sync_status  = (string) get_post_meta( $product_id, '_taqi_sync_status', true );
                        $sync_status  = $sync_status ? $sync_status : 'active';
                        $last_sync    = (string) get_post_meta( $product_id, '_taqi_last_sync_at', true );
                        $category     = (string) get_post_meta( $product_id, '_taqi_supplier_category_name', true );
                        if ( '' === $category ) {
                            $category = (string) get_post_meta( $product_id, '_taqi_supplier_category_id', true );
                        }
                        ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" class="taqi-imported-check" name="product_ids[]" value="<?php echo esc_attr( $product_id ); ?>"></th>
                            <td class="taqi-imported-sl"><?php echo esc_html( ( ( $imported_page - 1 ) * 50 ) + $query->current_post + 1 ); ?></td>
                            <td><strong><?php echo esc_html( get_the_title() ); ?></strong><br><small>Woo ID: <?php echo esc_html( $product_id ); ?></small></td>
                            <td><?php echo esc_html( $product_type ); ?></td>
                            <td><?php echo esc_html( $supplier_id ? $supplier_id : '—' ); ?></td>
                            <td><?php echo esc_html( get_post_meta( $product_id, '_taqi_supplier_code', true ) ); ?></td>
                            <td><?php echo esc_html( $category ? $category : '—' ); ?></td>
                            <td><?php echo esc_html( get_post_status( $product_id ) ); ?></td>
                            <td>
                                <?php if ( 'cancelled' === $sync_status ) : ?>
                                    <strong style="color:#b32d2e;">Cancelled</strong>
                                <?php else : ?>
                                    <strong style="color:#008a20;">Active</strong>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $last_sync ? $last_sync : 'Not recorded yet' ); ?></td>
                            <td><?php echo '' !== (string) $raw_price ? esc_html( number_format_i18n( (float) $raw_price, 2 ) ) : '—'; ?></td>
                            <td class="taqi-status-actions">
                                <a class="button" href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>">Edit Product</a>
                                <?php if ( 'publish' !== get_post_status( $product_id ) ) : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'taqi_manage_imported_product', 'taqi_manage_imported_nonce' ); ?>
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
                                        <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $supplier_id ); ?>">
                                        <button type="submit" name="taqi_manage_imported_action" value="publish" class="button button-primary">Publish</button>
                                    </form>
                                <?php else : ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Unpublish this product and return it to Draft status?');">
                                        <?php wp_nonce_field( 'taqi_manage_imported_product', 'taqi_manage_imported_nonce' ); ?>
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
                                        <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $supplier_id ); ?>">
                                        <button type="submit" name="taqi_manage_imported_action" value="unpublish" class="button">Unpublish</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ( 'cancelled' === $sync_status ) : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'taqi_manage_imported_product', 'taqi_manage_imported_nonce' ); ?>
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
                                        <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $supplier_id ); ?>">
                                        <button type="submit" name="taqi_manage_imported_action" value="relink" class="button button-primary">Re-link &amp; Sync</button>
                                    </form>
                                <?php else : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'taqi_manage_imported_product', 'taqi_manage_imported_nonce' ); ?>
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
                                        <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $supplier_id ); ?>">
                                        <button type="submit" name="taqi_manage_imported_action" value="resync" class="button button-primary">Re-sync</button>
                                    </form>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('Cancel synchronization? The WooCommerce product will remain unchanged and can be re-linked later.');">
                                        <?php wp_nonce_field( 'taqi_manage_imported_product', 'taqi_manage_imported_nonce' ); ?>
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
                                        <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $supplier_id ); ?>">
                                        <button type="submit" name="taqi_manage_imported_action" value="cancel" class="button">Cancel Sync</button>
                                    </form>
                                <?php endif; ?>

                                <?php $delete_form_id = 'taqi-delete-imported-' . absint( $product_id ); ?>
                                <form method="post" id="<?php echo esc_attr( $delete_form_id ); ?>" style="display:inline;" class="taqi-imported-delete-form">
                                    <?php wp_nonce_field( 'taqi_manage_imported_product', 'taqi_manage_imported_nonce' ); ?>
                                    <input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
                                    <input type="hidden" name="supplier_id" value="<?php echo esc_attr( $supplier_id ); ?>">
                                    <input type="hidden" name="taqi_manage_imported_action" value="delete">
                                    <input type="hidden" name="taqi_delete_confirmation" value="" class="taqi-delete-confirmation-value">
                                    <button type="button" class="button-link-delete taqi-open-delete-modal" style="margin-left:6px;" data-form-id="<?php echo esc_attr( $delete_form_id ); ?>" data-product-name="<?php echo esc_attr( get_the_title() ); ?>">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php endif; ?>
                </tbody>
            </table></div>

            <?php if ( $query->max_num_pages > 1 ) : ?>
                <div class="tablenav taqi-status-pagination"><div class="tablenav-pages">
                    <?php
                    echo wp_kses_post(
                        paginate_links(
                            array(
                                'base'      => admin_url( 'admin.php?page=taqi-dropshipping-imported&imported_page=%#%' ),
                                'format'    => '',
                                'current'   => $imported_page,
                                'total'     => $query->max_num_pages,
                                'prev_text' => '‹ Previous',
                                'next_text' => 'Next ›',
                            )
                        )
                    );
                    ?>
                </div></div>
            <?php endif; ?>

            <div id="taqi-delete-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.48);z-index:100000;align-items:center;justify-content:center;padding:20px;">
                <div role="dialog" aria-modal="true" aria-labelledby="taqi-delete-modal-title" style="background:#fff;border-radius:8px;box-shadow:0 12px 40px rgba(0,0,0,.28);width:min(560px,100%);padding:24px;">
                    <h2 id="taqi-delete-modal-title" style="margin-top:0;color:#b32d2e;">Confirm permanent delete</h2>
                    <p>You are about to permanently delete this <strong>local WooCommerce product</strong>:</p>
                    <p style="background:#f6f7f7;border-left:4px solid #d63638;padding:10px 12px;"><strong id="taqi-delete-product-name"></strong></p>
                    <p><strong>This cannot be undone.</strong> The supplier Product API and supplier catalog will not be changed.</p>
                    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                        <button type="button" class="button" id="taqi-delete-cancel">Cancel</button>
                        <button type="button" class="button button-primary" id="taqi-delete-confirm" style="background:#b32d2e;border-color:#b32d2e;">Delete Permanently</button>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const importedSelectAll = document.getElementById('taqi-imported-select-all');
                const importedSelectAllButton = document.getElementById('taqi-imported-select-all-button');
                const importedClearButton = document.getElementById('taqi-imported-clear-button');
                const importedSelectedCount = document.getElementById('taqi-imported-selected-count');
                function updateImportedSelection() {
                    const checks = document.querySelectorAll('.taqi-imported-check');
                    const selected = document.querySelectorAll('.taqi-imported-check:checked');
                    if (importedSelectAll) importedSelectAll.checked = checks.length > 0 && selected.length === checks.length;
                    if (importedSelectedCount) importedSelectedCount.textContent = selected.length;
                }
                if (importedSelectAll) importedSelectAll.addEventListener('change', function () {
                    document.querySelectorAll('.taqi-imported-check').forEach(function (checkbox) { checkbox.checked = importedSelectAll.checked; });
                    updateImportedSelection();
                });
                if (importedSelectAllButton) importedSelectAllButton.addEventListener('click', function () {
                    document.querySelectorAll('.taqi-imported-check').forEach(function (checkbox) { checkbox.checked = true; });
                    updateImportedSelection();
                });
                if (importedClearButton) importedClearButton.addEventListener('click', function () {
                    document.querySelectorAll('.taqi-imported-check').forEach(function (checkbox) { checkbox.checked = false; });
                    updateImportedSelection();
                });
                document.querySelectorAll('.taqi-imported-check').forEach(function (checkbox) {
                    checkbox.addEventListener('change', updateImportedSelection);
                });
                updateImportedSelection();
                window.taqiConfirmImportedBatch = function (action) {
                    const selected = document.querySelectorAll('.taqi-imported-check:checked');
                    if (!selected.length) {
                        window.alert('Select at least one product first.');
                        return false;
                    }
                    if ('unpublish' === action && !window.confirm('Unpublish all selected products and return them to Draft status?')) {
                        return false;
                    }
                    const form = document.getElementById('taqi-imported-batch-form');
                    form.querySelectorAll('.taqi-batch-product-id').forEach(function (input) { input.remove(); });
                    selected.forEach(function (checkbox) {
                        const input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'product_ids[]'; input.value = checkbox.value; input.className = 'taqi-batch-product-id';
                        form.appendChild(input);
                    });
                    return true;
                };
                const modal = document.getElementById('taqi-delete-modal');
                const nameBox = document.getElementById('taqi-delete-product-name');
                const cancelBtn = document.getElementById('taqi-delete-cancel');
                const confirmBtn = document.getElementById('taqi-delete-confirm');
                let activeForm = null;

                document.querySelectorAll('.taqi-open-delete-modal').forEach(function (button) {
                    button.addEventListener('click', function () {
                        activeForm = document.getElementById(button.dataset.formId);
                        if (!activeForm || !modal) return;
                        nameBox.textContent = button.dataset.productName || 'Selected product';
                        modal.style.display = 'flex';
                    });
                });

                function closeDeleteModal() {
                    if (modal) modal.style.display = 'none';
                    activeForm = null;
                }

                if (cancelBtn) cancelBtn.addEventListener('click', closeDeleteModal);
                if (modal) modal.addEventListener('click', function (event) {
                    if (event.target === modal) closeDeleteModal();
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modal && modal.style.display === 'flex') closeDeleteModal();
                });

                if (confirmBtn) confirmBtn.addEventListener('click', function () {
                    if (!activeForm) return;
                    const confirmation = activeForm.querySelector('.taqi-delete-confirmation-value');
                    if (confirmation) confirmation.value = 'DELETE';
                    confirmBtn.disabled = true;
                    activeForm.submit();
                });
            });
            </script>
        </div>
        <?php
    }

    public function add_product_meta_box( $post ) {
        if ( ! $post || 'taqi_product' !== $post->post_type ) {
            return;
        }
        if ( ! get_post_meta( $post->ID, '_taqi_supplier', true ) ) {
            return;
        }

        add_meta_box(
            'taqi_dropshipping_info',
            'TAQI LIFE Dropshipping Information',
            array( $this, 'render_product_meta_box' ),
            'taqi_product',
            'side',
            'default'
        );
    }

    public function render_product_meta_box( $post ) {
        $supplier_name = get_post_meta( $post->ID, '_taqi_supplier_name', true );
        $supplier_id   = get_post_meta( $post->ID, '_taqi_supplier_id', true );
        $supplier_code = get_post_meta( $post->ID, '_taqi_supplier_code', true );
        $category_id   = get_post_meta( $post->ID, '_taqi_supplier_category_id', true );
        $maximum       = get_post_meta( $post->ID, '_taqi_supplier_price', true );
        $cost          = get_post_meta( $post->ID, '_taqi_supplier_sale_price', true );
        $resell        = get_post_meta( $post->ID, '_taqi_supplier_reselling_price', true );
        $last_imported = get_post_meta( $post->ID, '_taqi_last_imported_at', true );
        $last_sync     = get_post_meta( $post->ID, '_taqi_last_sync_at', true );
        $sync_status   = get_post_meta( $post->ID, '_taqi_sync_status', true );
        $sync_status   = $sync_status ? $sync_status : 'active';
        $api_page      = get_post_meta( $post->ID, '_taqi_supplier_api_page', true );
        $variant_note  = get_post_meta( $post->ID, '_taqi_variation_import_note', true );
        $meta_pricing  = $this->pricing_breakdown( array( 'sale_price' => $cost, 'price' => $maximum, 'reselling_price' => $resell ) );
        $current_price = get_post_meta( $post->ID, '_taqi_regular_price', true );
        ?>
        <p><strong>Supplier:</strong><br><?php echo esc_html( $supplier_name ? $supplier_name : '—' ); ?></p>
        <p><strong>Supplier Product ID:</strong><br><code><?php echo esc_html( $supplier_id ? $supplier_id : '—' ); ?></code></p>
        <p><strong>Supplier Code:</strong><br><?php echo esc_html( $supplier_code ? $supplier_code : '—' ); ?></p>
        <p><strong>Supplier Category ID:</strong><br><?php echo esc_html( $category_id ? $category_id : '—' ); ?></p>
        <p><strong>Cost Price (API sale_price):</strong><br><?php echo null !== $meta_pricing['cost'] ? wp_kses_post( $this->format_money( $meta_pricing['cost'] ) ) : '—'; ?></p>
        <p><strong>Minimum Selling Price (<?php echo esc_html( number_format_i18n( $meta_pricing['minimum_markup_percent'], 2 ) ); ?>%):</strong><br><?php echo null !== $meta_pricing['minimum'] ? wp_kses_post( $this->format_money( $meta_pricing['minimum'] ) ) : '—'; ?></p>
        <p><strong>TAQI Selling Price (<?php echo esc_html( number_format_i18n( $meta_pricing['taqi_markup_percent'], 2 ) ); ?>%):</strong><br><?php echo null !== $meta_pricing['selling'] ? wp_kses_post( $this->format_money( $meta_pricing['selling'] ) ) : '—'; ?></p>
        <p><strong>Maximum Selling Price (API price):</strong><br><?php echo null !== $meta_pricing['maximum'] ? wp_kses_post( $this->format_money( $meta_pricing['maximum'] ) ) : '—'; ?></p>
        <p><strong>TAQI LIFE Current Price:</strong><br><?php echo '' !== (string) $current_price ? esc_html( number_format_i18n( (float) $current_price, 2 ) ) : '—'; ?></p>
        <p><strong>Sync Status:</strong><br><?php echo 'cancelled' === $sync_status ? '<strong style="color:#b32d2e;">Cancelled</strong>' : '<strong style="color:#008a20;">Active</strong>'; ?></p>
        <p><strong>Last Imported:</strong><br><?php echo esc_html( $last_imported ? $last_imported : '—' ); ?></p>
        <p><strong>Last Re-sync:</strong><br><?php echo esc_html( $last_sync ? $last_sync : 'Not recorded yet' ); ?></p>
        <p><strong>Supplier API Page:</strong><br><?php echo esc_html( $api_page ? $api_page : 'Unknown / legacy import' ); ?></p>
        <?php if ( $variant_note ) : ?><p style="padding:8px;background:#f6f7f7;border-left:3px solid #2271b1;"><?php echo esc_html( $variant_note ); ?></p><?php endif; ?>
        <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=taqi-dropshipping-imported' ) ); ?>">Manage Sync</a></p>
        <p class="description">Re-sync preserves local title, description and images, adds missing supplier images when detected, and refreshes supplier-controlled price, stock, mapped categories and metadata.</p>
        <?php
    }
}

TAQI_Life_Dropshipping::instance();
