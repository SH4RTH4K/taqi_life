<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierSearch
 * Handles the Courier Search admin page and its AJAX endpoint.
 */
class CourierSearch {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_bd_courier_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_head', array( $this, 'hide_admin_notices' ) );
        add_action( 'wp_ajax_search_courier_data', array( $this, 'search_courier_data' ) );
        add_action( 'wp_ajax_nopriv_search_courier_data', array( $this, 'search_courier_data' ) );
        
        // Admin AJAX for image generation HTML
        add_action( 'wp_ajax_get_image_html', array( $this, 'get_image_html' ) );
        add_action( 'wp_ajax_nopriv_get_image_html', array( $this, 'get_image_html' ) );
        
        // Direct HTML page for image rendering (no React)
        add_action( 'wp_ajax_render_image_page', array( $this, 'render_image_page' ) );
        add_action( 'wp_ajax_nopriv_render_image_page', array( $this, 'render_image_page' ) );
        
        // REST API endpoints for React frontend
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        
        // Add type="module" to search app scripts
        add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 2 );
    }
    
    /**
     * Add type="module" to search app scripts.
     */
    public function add_module_type_to_scripts( $tag, $handle ) {
        if ( $handle === 'bd-courier-search-js' ) {
            // Remove existing type attribute if present
            $tag = preg_replace( '/\s*type=["\']text\/javascript["\']/', '', $tag );
            // Add type="module" before the closing >
            if ( strpos( $tag, ' type="module"' ) === false ) {
                $tag = str_replace( ' src=', ' type="module" src=', $tag );
            }
        }
        return $tag;
    }

    /**
     * Hide admin notices on search page.
     */
    public function hide_admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        
        if ( $screen->id === 'toplevel_page_bd-courier-search' || ( isset( $_GET['page'] ) && $_GET['page'] === 'bd-courier-search' ) ) {
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

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_bd-courier-search' ) {
            return;
        }

        // Enqueue jQuery and html-to-image for image generation
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script(
            'html-to-image',
            'https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.min.js',
            [],
            '1.11.11',
            true
        );

        // Get plugin root directory and main file path
        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';

        // Check if files exist before getting filemtime
        $css_file = $plugin_dir . '/assets/react-apps/search-app/assets/index.css';
        $js_file = $plugin_dir . '/assets/react-apps/search-app/assets/index.js';

        // Try to find the actual built files (they have hashed names)
        $dist_dir = $plugin_dir . '/assets/react-apps/search-app/assets';
        
        // Fallback to old location if new location doesn't exist
        if ( ! is_dir( $dist_dir ) ) {
            $dist_dir = $plugin_dir . '/courier-search-app/dist/assets';
        }
        
        if ( is_dir( $dist_dir ) ) {
            $css_files = glob( $dist_dir . '/*.css' );
            $js_files = glob( $dist_dir . '/*.js' );
            
            if ( ! empty( $css_files ) ) {
                $css_file = $css_files[0];
                // Determine the correct path based on which directory we're using
                $css_path = strpos( $css_file, 'assets/react-apps' ) !== false 
                    ? 'assets/react-apps/search-app/assets/' . basename( $css_file )
                    : 'courier-search-app/dist/assets/' . basename( $css_file );
                $css_url = plugins_url( $css_path, $plugin_main_file );
                $css_url = add_query_arg( 'v', BD_COURIER_VERSION, $css_url );
                
                wp_enqueue_style(
                    'bd-courier-search-css',
                    $css_url,
                    [],
                    BD_COURIER_VERSION
                );
            }
            
            if ( ! empty( $js_files ) ) {
                $js_file = $js_files[0];
                // Determine the correct path based on which directory we're using
                $js_path = strpos( $js_file, 'assets/react-apps' ) !== false 
                    ? 'assets/react-apps/search-app/assets/' . basename( $js_file )
                    : 'courier-search-app/dist/assets/' . basename( $js_file );
                $js_url = plugins_url( $js_path, $plugin_main_file );
                $js_url = add_query_arg( 'v', BD_COURIER_VERSION, $js_url );
                
                wp_enqueue_script(
                    'bd-courier-search-js',
                    $js_url,
                    [],
                    BD_COURIER_VERSION,
                    true
                );

                // Get API configuration for debugging
                $api_token = get_option( 'bd_courier_api_token' );
                $api_base_url = 'https://api.bdcourier.com/'; // This should match CourierAPI::get_api_base_url()
                $api_endpoint = $api_base_url . 'courier-check';
                
                // Localize script with WordPress REST API data
                wp_localize_script( 'bd-courier-search-js', 'wpApiSettings', [
                    'root'  => esc_url_raw( rest_url() ),
                    'nonce' => wp_create_nonce( 'wp_rest' ),
                ]);
                
                // Localize logo base URL
                wp_localize_script( 'bd-courier-search-js', 'bdcourierLogoBaseUrl', [
                    'baseUrl' => plugins_url( 'assets/images/', $plugin_main_file ),
                ]);
                
                // Localize API info for debugging
                wp_localize_script( 'bd-courier-search-js', 'bdcourierApiInfo', [
                    'endpoint' => $api_endpoint,
                    'baseUrl' => $api_base_url,
                    'keySet' => ! empty( $api_token ),
                    'keyLength' => $api_token ? strlen( $api_token ) : 0,
                ]);
                
                // Localize admin-ajax URL and nonce for image generation
                wp_localize_script( 'bd-courier-search-js', 'bdCourierImageAjax', [
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'bd_courier_image_nonce' ),
                    'logoUrl' => plugins_url( 'assets/images/logo.png', $plugin_main_file ),
                ]);
            }
        }
    }


    public function add_bd_courier_menu() {
        add_menu_page(
            __( 'Courier Search', 'bd-courier-order-ratio-checker' ),
            __( 'Courier Search', 'bd-courier-order-ratio-checker' ),
            'manage_options',
            'bd-courier-search',
            array( $this, 'render_search_page' ),
            'dashicons-search',
            25
        );
    }

    public function render_search_page() {
        ?>
        <div id="bd-courier-search-root"></div>
        <?php
    }

    /**
     * Register REST API routes for React frontend.
     */
    public function register_rest_routes() {
        register_rest_route( 'bd-courier/v1', '/search', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'search_courier_rest' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );
        
        register_rest_route( 'bd-courier/v1', '/generate-image', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'generate_image_rest' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );
    }

    /**
     * Check if user has permission to access the API.
     */
    public function check_permissions() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Search courier data via REST API.
     */
    public function search_courier_rest( $request ) {
        
        if ( ! isset( $request['phone'] ) || empty( $request['phone'] ) ) {
            return new WP_Error( 'missing_phone', 'Phone number is required', array( 'status' => 400 ) );
        }

        // Check if API token is configured
        $api_token = get_option( 'bd_courier_api_token' );
        if ( empty( $api_token ) ) {
            return new WP_Error( 
                'no_api_token', 
                'API token is not configured. Please configure it in BD Courier Settings.', 
                array( 'status' => 400 ) 
            );
        }

        $phone = sanitize_text_field( $request['phone'] );
        
        // Call the API directly to get detailed response
        $api_token = get_option( 'bd_courier_api_token' );
        $base_url = 'https://api.bdcourier.com/';
        $url = $base_url . 'courier-check?phone=' . urlencode( $phone );
        
        $headers = [
            'Authorization' => 'Bearer ' . esc_attr( $api_token ),
        ];
        
        // Get SSL verify setting - enable for production API
        $sslverify = ! (
            strpos( $base_url, '.test' ) !== false ||
            strpos( $base_url, 'localhost' ) !== false ||
            strpos( $base_url, '127.0.0.1' ) !== false ||
            ( defined( 'WP_DEBUG' ) && WP_DEBUG )
        );
        
        $args = [
            'headers' => $headers,
            'timeout' => 100,
            'sslverify' => $sslverify,
        ];
        
        
        $response = wp_remote_get( $url, $args );
        
        $api_http_code = null;
        $api_response_body = null;
        $api_error = null;
        $courier_data = null;
        
        if ( is_wp_error( $response ) ) {
            $api_error = $response->get_error_message();
        } else {
            $api_http_code = wp_remote_retrieve_response_code( $response );
            $api_response_body = wp_remote_retrieve_body( $response );
            
            
            $data = json_decode( $api_response_body, true );
            
            // Check for data in 'data' property (new API format)
            if ( isset( $data['data'] ) && is_array( $data['data'] ) && ! empty( $data['data'] ) ) {
                $courier_data = $data['data'];
            }
            // Fallback: Check for courierData (old API format)
            elseif ( isset( $data['courierData'] ) && is_array( $data['courierData'] ) && ! empty( $data['courierData'] ) ) {
                $courier_data = $data['courierData'];
            } else {
                $api_error = 'API response does not contain courier data';
                if ( $api_http_code !== 200 ) {
                    $api_error = 'HTTP ' . $api_http_code;
                }
                if ( isset( $data['message'] ) ) {
                    $api_error .= ': ' . $data['message'];
                }
            }
        }

        if ( $courier_data && is_array( $courier_data ) && ! empty( $courier_data ) ) {
            // Store in history table for manual checks
            require_once dirname( __FILE__ ) . '/class.CourierHistory.php';
            CourierHistory::store_history( $phone, $courier_data, 'manual', null );
            
            $total_sum = 0;
            $success_sum = 0;
            $formatted_data = array();

            foreach ( $courier_data as $courier => $courier_info ) {
                if ( 'summary' !== $courier && is_array( $courier_info ) ) {
                    // Handle both string and integer values (API sometimes returns strings)
                    $total_parcel = isset( $courier_info['total_parcel'] ) ? (int) $courier_info['total_parcel'] : 0;
                    $success_parcel = isset( $courier_info['success_parcel'] ) ? (int) $courier_info['success_parcel'] : 0;

                    $total_sum += $total_parcel;
                    $success_sum += $success_parcel;

                    $formatted_data[ $courier ] = array(
                        'total_parcel' => $total_parcel,
                        'success_parcel' => $success_parcel,
                        'logo' => isset( $courier_info['logo'] ) ? $courier_info['logo'] : null,
                        'name' => isset( $courier_info['name'] ) ? $courier_info['name'] : ucfirst( $courier ),
                    );
                }
            }
            
            // If summary exists in the data, use it; otherwise calculate from individual couriers
            if ( isset( $courier_data['summary'] ) && is_array( $courier_data['summary'] ) ) {
                $summary = $courier_data['summary'];
                $total_sum = isset( $summary['total_parcel'] ) ? (int) $summary['total_parcel'] : $total_sum;
                $success_sum = isset( $summary['success_parcel'] ) ? (int) $summary['success_parcel'] : $success_sum;
            }

            $return_sum = $total_sum - $success_sum;
            $success_percent = $total_sum > 0 ? round( ( $success_sum / $total_sum ) * 100 ) : 0;
            $fail_percent = 100 - $success_percent;

            $response_data = array(
                'success' => true,
                'data' => array(
                    'data' => $formatted_data,
                    'summary' => array(
                        'total' => $total_sum,
                        'success' => $success_sum,
                        'returned' => $return_sum,
                        'success_percent' => $success_percent,
                        'fail_percent' => $fail_percent,
                    ),
                ),
            );
            
            
            return new WP_REST_Response( $response_data, 200 );
        } else {
            
            // Build detailed error message
            $error_message = 'Failed to fetch data from API endpoint: courier-check.';
            if ( $api_error ) {
                $error_message .= ' Error: ' . $api_error;
            }
            if ( $api_http_code ) {
                $error_message .= ' HTTP Code: ' . $api_http_code;
            }
            if ( $api_response_body ) {
                $decoded_body = json_decode( $api_response_body, true );
                if ( isset( $decoded_body['message'] ) ) {
                    $error_message .= ' API Message: ' . $decoded_body['message'];
                }
                // Include first 500 chars of response for debugging
                $error_message .= ' Response (first 500 chars): ' . substr( $api_response_body, 0, 500 );
            }
            $error_message .= ' Check WordPress error log for full details.';
            
            return new WP_Error( 
                'search_failed', 
                $error_message,
                array( 
                    'status' => 400,
                    'api_error' => $api_error,
                    'http_code' => $api_http_code,
                    'response_body' => $api_response_body,
                ) 
            );
        }
    }

    /**
     * Get HTML for image generation via admin-ajax.
     */
    public function get_image_html() {
        check_ajax_referer( 'bd_courier_image_nonce', 'nonce' );

        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
        $result_data = isset( $_POST['result'] ) ? json_decode( stripslashes( $_POST['result'] ), true ) : null;

        if ( ! $result_data || ! isset( $result_data['data'] ) || ! isset( $result_data['summary'] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid result data provided.' ) );
        }

        $courier_data = $result_data['data'];
        $summary = $result_data['summary'];

        // Calculate percentages
        $total_value = (int) $summary['success'] + (int) $summary['returned'];
        $success_percent = $total_value > 0 ? round( ( (int) $summary['success'] / $total_value ) * 100 ) : 0;
        $return_percent = 100 - $success_percent;

        // Generate HTML matching the exact design with custom CSS classes to prevent conflicts
        ob_start();
        ?>
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <title>Courier Order Statistics</title>
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <style>
            .bdcr-report-root * {
              box-sizing: border-box;
            }
            
            :root {
              --bdcr-bg: #f4f7ff;
              --bdcr-card: #ffffff;
              --bdcr-text: #0f172a;
              --bdcr-muted: #64748b;
              --bdcr-line: #e7ecf5;
              --bdcr-blue: #2563eb;
              --bdcr-green: #16a34a;
              --bdcr-green-soft: #eafff1;
              --bdcr-red: #ef4444;
              --bdcr-red-soft: #fff1f2;
              --bdcr-radius: 16px;
              --bdcr-shadow: 0 15px 40px rgba(15,23,42,.08);
              --bdcr-font: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            }
            
            body {
              margin: 0;
              font-family: var(--bdcr-font);
              background: var(--bdcr-bg);
              color: var(--bdcr-text);
            }
            
            .bdcr-report-root {
              margin: 0;
              font-family: var(--bdcr-font);
              background: var(--bdcr-bg);
              color: var(--bdcr-text);
            }

            .bdcr-report-container {
              max-width: 1200px;
              margin: 30px auto;
              padding: 0 16px;
            }

            .bdcr-report-header {
              background: #fff;
              border-radius: var(--bdcr-radius);
              box-shadow: var(--bdcr-shadow);
              padding: 18px 20px;
              display: flex;
              justify-content: space-between;
              align-items: center;
            }

            .bdcr-report-header h1 {
              margin: 0;
              font-size: 22px;
            }
            
            .bdcr-report-header p {
              margin: 4px 0 0;
              color: var(--bdcr-muted);
              font-size: 13px;
            }

            .bdcr-report-phone {
              display: flex;
              align-items: center;
            }
            
            .bdcr-report-phone-label {
              color: var(--bdcr-muted);
              font-size: 13px;
              margin-right: 8px;
            }
            
            .bdcr-report-phone-number {
              font-weight: 700;
              font-size: 16px;
              color: var(--bdcr-text);
            }

            .bdcr-report-grid {
              display: grid;
              grid-template-columns: 1.1fr .9fr;
              gap: 16px;
              margin-top: 16px;
            }
            
            @media (max-width: 900px) {
              .bdcr-report-grid {
                grid-template-columns: 1fr;
              }
            }

            .bdcr-report-card {
              background: #fff;
              border-radius: var(--bdcr-radius);
              box-shadow: var(--bdcr-shadow);
              border: 1px solid var(--bdcr-line);
              overflow: hidden;
            }

            .bdcr-report-card-header {
              padding: 14px 16px;
              border-bottom: 1px solid var(--bdcr-line);
            }

            .bdcr-report-card-header h2 {
              margin: 0;
              font-size: 14px;
              text-transform: uppercase;
              letter-spacing: .1em;
            }

            .bdcr-report-table {
              width: 100%;
              border-collapse: collapse;
            }
            
            .bdcr-report-table th,
            .bdcr-report-table td {
              padding: 12px 14px;
              border-bottom: 1px solid var(--bdcr-line);
              text-align: left;
            }
            
            .bdcr-report-table th {
              font-size: 12px;
              color: #334155;
              text-transform: uppercase;
              letter-spacing: .08em;
              background: #f8fafc;
            }
            
            .bdcr-report-table .courier-logo {
              width: 80px;
              height: auto;
              object-fit: contain;
              padding: 4px;
              background: #fff;
              border: 1px solid var(--bdcr-line);
              border-radius: 8px;
            }
            
            .bdcr-report-table .success {
              color: var(--bdcr-green);
              font-weight: 800;
            }
            
            .bdcr-report-table .return {
              color: var(--bdcr-red);
              font-weight: 800;
            }
            
            .bdcr-report-table .summary {
              background: #f1f5ff;
              font-weight: 900;
            }

            .bdcr-report-summary-box {
              padding: 16px;
            }
            
            .bdcr-report-summary-box h3 {
              margin: 0;
              font-size: 20px;
            }
            
            .bdcr-report-summary-box p {
              color: var(--bdcr-muted);
              font-size: 13px;
            }

            .bdcr-report-stats {
              display: grid;
              grid-template-columns: 1fr 1fr;
              gap: 12px;
              margin-top: 14px;
            }

            .bdcr-report-stat {
              border-radius: 14px;
              padding: 16px;
              font-weight: 800;
            }
            
            .bdcr-report-stat.success {
              background: var(--bdcr-green-soft);
              border: 1px solid rgba(22,163,74,.25);
            }
            
            .bdcr-report-stat.return {
              background: var(--bdcr-red-soft);
              border: 1px solid rgba(239,68,68,.25);
            }
            
            .bdcr-report-stat .value {
              font-size: 34px;
            }

            .bdcr-report-progress {
              margin-top: 16px;
              background: #fff;
              border-radius: 14px;
              border: 1px solid var(--bdcr-line);
              padding: 14px;
            }
            
            .bdcr-report-bar {
              height: 12px;
              background: #e5e7eb;
              border-radius: 999px;
              overflow: hidden;
              margin-top: 6px;
            }
            
            .bdcr-report-bar span {
              display: block;
              height: 100%;
              background: linear-gradient(90deg, #16a34a, #22c55e);
            }
            
            .bdcr-powered-by {
              text-align: center;
              padding: 24px 16px;
              margin-top: 24px;
              margin-bottom: 20px;
              color: var(--bdcr-muted);
              font-size: 13px;
              font-weight: 500;
              border-top: 1px solid var(--bdcr-line);
              background: #fff;
              border-radius: 0 0 var(--bdcr-radius) var(--bdcr-radius);
              min-height: 60px;
              display: flex;
              align-items: center;
              justify-content: center;
            }
            
            .bdcr-powered-by a {
              color: var(--bdcr-blue);
              text-decoration: none;
              font-weight: 600;
            }
            
            .bdcr-powered-by a:hover {
              text-decoration: underline;
            }
            
            .bdcr-report-container {
              padding-bottom: 20px;
            }
          </style>
        </head>
        <body>
        <div class="bdcr-report-root">
        <div class="bdcr-report-container">
          <div class="bdcr-report-header">
            <div>
              <h1>Search Results</h1>
              <p>Courier order statistics by phone number</p>
            </div>
            <div class="bdcr-report-phone">
              <span class="bdcr-report-phone-label">Phone:</span>
              <span class="bdcr-report-phone-number"><?php echo esc_html( $phone ); ?></span>
            </div>
          </div>

          <div class="bdcr-report-grid">
            <div class="bdcr-report-card">
              <div class="bdcr-report-card-header"><h2>Courier Breakdown</h2></div>
              <table class="bdcr-report-table">
                <thead>
                  <tr>
                    <th>Courier</th>
                    <th>Total</th>
                    <th>Success</th>
                    <th>Return</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $courier_entries = array_filter( $courier_data, function( $key ) {
                      return $key !== 'summary';
                  }, ARRAY_FILTER_USE_KEY );
                  
                  foreach ( $courier_entries as $courier => $data ) {
                      $courier_name = ! empty( $data['name'] ) ? $data['name'] : ucfirst( $courier );
                      $total_parcel = isset( $data['total_parcel'] ) ? (int) $data['total_parcel'] : 0;
                      $success_parcel = isset( $data['success_parcel'] ) ? (int) $data['success_parcel'] : 0;
                      $returned_parcel = $total_parcel - $success_parcel;
                      
                      // Get logo path
                      $plugin_dir = dirname( dirname( __FILE__ ) );
                      $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';
                      $logo_path = $plugin_dir . '/assets/images/' . strtolower( $courier ) . '-logo.png';
                      $logo_url = '';
                      if ( file_exists( $logo_path ) ) {
                          $logo_url = plugins_url( 'assets/images/' . strtolower( $courier ) . '-logo.png', $plugin_main_file );
                      }
                      ?>
                      <tr>
                        <td>
                          <?php if ( $logo_url ) : ?>
                            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $courier_name ); ?>" class="courier-logo" />
                          <?php else : ?>
                            <?php echo esc_html( $courier_name ); ?>
                          <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $total_parcel ); ?></td>
                        <td class="success"><?php echo esc_html( $success_parcel ); ?></td>
                        <td class="return"><?php echo esc_html( $returned_parcel ); ?></td>
                      </tr>
                      <?php
                  }
                  ?>
                  <tr class="summary">
                    <td>Summary</td>
                    <td><?php echo esc_html( $summary['total'] ); ?></td>
                    <td class="success"><?php echo esc_html( $summary['success'] ); ?></td>
                    <td class="return"><?php echo esc_html( $summary['returned'] ); ?></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="bdcr-report-card">
              <div class="bdcr-report-summary-box">
                <h3>Success vs Returned</h3>
                <p>Order distribution overview</p>

                <div class="bdcr-report-stats">
                  <div class="bdcr-report-stat success">
                    Success
                    <div class="value"><?php echo esc_html( $summary['success'] ); ?></div>
                    <?php echo esc_html( $success_percent ); ?>%
                  </div>
                  <div class="bdcr-report-stat return">
                    Returned
                    <div class="value"><?php echo esc_html( $summary['returned'] ); ?></div>
                    <?php echo esc_html( $return_percent ); ?>%
                  </div>
                </div>

                <div class="bdcr-report-progress">
                  <strong>Success Rate</strong>
                  <div class="bdcr-report-bar">
                    <span style="width:<?php echo esc_attr( $success_percent ); ?>%"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="bdcr-powered-by">
            Search Powered By: <a href="https://bdcourier.com" target="_blank" rel="noopener noreferrer">bdcourier.com</a>
          </div>
        </div>
        </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * Render image page directly (no React, standalone HTML)
     */
    public function render_image_page() {
        // Verify nonce - handle both GET and POST
        $nonce = '';
        if ( isset( $_GET['nonce'] ) ) {
            $nonce = sanitize_text_field( $_GET['nonce'] );
        } elseif ( isset( $_POST['nonce'] ) ) {
            $nonce = sanitize_text_field( $_POST['nonce'] );
        }
        
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'bd_courier_image_nonce' ) ) {
            wp_die( 'Invalid request - nonce verification failed' );
        }

        // Get phone number - handle both GET and POST
        $phone = '';
        if ( isset( $_GET['phone'] ) ) {
            $phone = sanitize_text_field( $_GET['phone'] );
        } elseif ( isset( $_POST['phone'] ) ) {
            $phone = sanitize_text_field( $_POST['phone'] );
        }
        
        // Handle both GET and POST for result data
        $result_data_json = '';
        if ( isset( $_GET['result'] ) ) {
            $result_data_json = urldecode( $_GET['result'] );
        } elseif ( isset( $_POST['result'] ) ) {
            $result_data_json = stripslashes( $_POST['result'] );
        }
        
        $result_data = ! empty( $result_data_json ) ? json_decode( $result_data_json, true ) : null;

        if ( ! $result_data || ! isset( $result_data['data'] ) || ! isset( $result_data['summary'] ) ) {
            // Log for debugging
            wp_die( 'Invalid result data provided. Please try again.' );
        }

        $courier_data = $result_data['data'];
        $summary = $result_data['summary'];

        // Calculate percentages
        $total_value = (int) $summary['success'] + (int) $summary['returned'];
        $success_percent = $total_value > 0 ? round( ( (int) $summary['success'] / $total_value ) * 100 ) : 0;
        $return_percent = 100 - $success_percent;

        // Output the HTML page directly
        $this->output_image_html( $phone, $courier_data, $summary, $success_percent, $return_percent );
        exit;
    }

    /**
     * Output the HTML for image generation
     */
    private function output_image_html( $phone, $courier_data, $summary, $success_percent, $return_percent ) {
        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';
        ?>
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <title>Courier Order Statistics</title>
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <style>
            .bdcr-report-root * {
              box-sizing: border-box;
            }
            
            :root {
              --bdcr-bg: #f4f7ff;
              --bdcr-card: #ffffff;
              --bdcr-text: #0f172a;
              --bdcr-muted: #64748b;
              --bdcr-line: #e7ecf5;
              --bdcr-blue: #2563eb;
              --bdcr-green: #16a34a;
              --bdcr-green-soft: #eafff1;
              --bdcr-red: #ef4444;
              --bdcr-red-soft: #fff1f2;
              --bdcr-radius: 16px;
              --bdcr-shadow: 0 15px 40px rgba(15,23,42,.08);
              --bdcr-font: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            }
            
            body {
              margin: 0;
              font-family: var(--bdcr-font);
              background: var(--bdcr-bg);
              color: var(--bdcr-text);
            }
            
            .bdcr-report-root {
              margin: 0;
              font-family: var(--bdcr-font);
              background: var(--bdcr-bg);
              color: var(--bdcr-text);
            }

            .bdcr-report-container {
              max-width: 1200px;
              margin: 30px auto;
              padding: 0 16px;
            }

            .bdcr-report-header {
              background: #fff;
              border-radius: var(--bdcr-radius);
              box-shadow: var(--bdcr-shadow);
              padding: 18px 20px;
              display: flex;
              justify-content: space-between;
              align-items: center;
            }

            .bdcr-report-header h1 {
              margin: 0;
              font-size: 22px;
            }
            
            .bdcr-report-header p {
              margin: 4px 0 0;
              color: var(--bdcr-muted);
              font-size: 13px;
            }

            .bdcr-report-phone {
              display: flex;
              align-items: center;
            }
            
            .bdcr-report-phone-label {
              color: var(--bdcr-muted);
              font-size: 13px;
              margin-right: 8px;
            }
            
            .bdcr-report-phone-number {
              font-weight: 700;
              font-size: 16px;
              color: var(--bdcr-text);
            }

            .bdcr-report-grid {
              display: grid;
              grid-template-columns: 1.1fr .9fr;
              gap: 16px;
              margin-top: 16px;
            }
            
            @media (max-width: 900px) {
              .bdcr-report-grid {
                grid-template-columns: 1fr;
              }
            }

            .bdcr-report-card {
              background: #fff;
              border-radius: var(--bdcr-radius);
              box-shadow: var(--bdcr-shadow);
              border: 1px solid var(--bdcr-line);
              overflow: hidden;
            }

            .bdcr-report-card-header {
              padding: 14px 16px;
              border-bottom: 1px solid var(--bdcr-line);
            }

            .bdcr-report-card-header h2 {
              margin: 0;
              font-size: 14px;
              text-transform: uppercase;
              letter-spacing: .1em;
            }

            .bdcr-report-table {
              width: 100%;
              border-collapse: collapse;
            }
            
            .bdcr-report-table th,
            .bdcr-report-table td {
              padding: 12px 14px;
              border-bottom: 1px solid var(--bdcr-line);
              text-align: left;
            }
            
            .bdcr-report-table th {
              font-size: 12px;
              color: #334155;
              text-transform: uppercase;
              letter-spacing: .08em;
              background: #f8fafc;
            }
            
            .bdcr-report-table .courier-logo {
              width: 80px;
              height: auto;
              object-fit: contain;
              padding: 4px;
              background: #fff;
              border: 1px solid var(--bdcr-line);
              border-radius: 8px;
            }
            
            .bdcr-report-table .success {
              color: var(--bdcr-green);
              font-weight: 800;
            }
            
            .bdcr-report-table .return {
              color: var(--bdcr-red);
              font-weight: 800;
            }
            
            .bdcr-report-table .summary {
              background: #f1f5ff;
              font-weight: 900;
            }

            .bdcr-report-summary-box {
              padding: 16px;
            }
            
            .bdcr-report-summary-box h3 {
              margin: 0;
              font-size: 20px;
            }
            
            .bdcr-report-summary-box p {
              color: var(--bdcr-muted);
              font-size: 13px;
            }

            .bdcr-report-stats {
              display: grid;
              grid-template-columns: 1fr 1fr;
              gap: 12px;
              margin-top: 14px;
            }

            .bdcr-report-stat {
              border-radius: 14px;
              padding: 16px;
              font-weight: 800;
            }
            
            .bdcr-report-stat.success {
              background: var(--bdcr-green-soft);
              border: 1px solid rgba(22,163,74,.25);
            }
            
            .bdcr-report-stat.return {
              background: var(--bdcr-red-soft);
              border: 1px solid rgba(239,68,68,.25);
            }
            
            .bdcr-report-stat .value {
              font-size: 34px;
            }

            .bdcr-report-progress {
              margin-top: 16px;
              background: #fff;
              border-radius: 14px;
              border: 1px solid var(--bdcr-line);
              padding: 14px;
            }
            
            .bdcr-report-bar {
              height: 12px;
              background: #e5e7eb;
              border-radius: 999px;
              overflow: hidden;
              margin-top: 6px;
            }
            
            .bdcr-report-bar span {
              display: block;
              height: 100%;
              background: linear-gradient(90deg, #16a34a, #22c55e);
            }
            
            .bdcr-powered-by {
              text-align: center;
              padding: 24px 16px;
              margin-top: 24px;
              margin-bottom: 20px;
              color: var(--bdcr-muted);
              font-size: 13px;
              font-weight: 500;
              border-top: 1px solid var(--bdcr-line);
              background: #fff;
              border-radius: 0 0 var(--bdcr-radius) var(--bdcr-radius);
              min-height: 60px;
              display: flex;
              align-items: center;
              justify-content: center;
            }
            
            .bdcr-powered-by a {
              color: var(--bdcr-blue);
              text-decoration: none;
              font-weight: 600;
            }
            
            .bdcr-powered-by a:hover {
              text-decoration: underline;
            }
            
            .bdcr-report-container {
              padding-bottom: 20px;
            }
          </style>
        </head>
        <body>
        <div class="bdcr-report-root">
        <div class="bdcr-report-container">
          <div class="bdcr-report-header">
            <div>
              <h1>Search Results</h1>
              <p>Courier order statistics by phone number</p>
            </div>
            <div class="bdcr-report-phone">
              <span class="bdcr-report-phone-label">Phone:</span>
              <span class="bdcr-report-phone-number"><?php echo esc_html( $phone ); ?></span>
            </div>
          </div>

          <div class="bdcr-report-grid">
            <div class="bdcr-report-card">
              <div class="bdcr-report-card-header"><h2>Courier Breakdown</h2></div>
              <table class="bdcr-report-table">
                <thead>
                  <tr>
                    <th>Courier</th>
                    <th>Total</th>
                    <th>Success</th>
                    <th>Return</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $courier_entries = array_filter( $courier_data, function( $key ) {
                      return $key !== 'summary';
                  }, ARRAY_FILTER_USE_KEY );
                  
                  foreach ( $courier_entries as $courier => $data ) {
                      $courier_name = ! empty( $data['name'] ) ? $data['name'] : ucfirst( $courier );
                      $total_parcel = isset( $data['total_parcel'] ) ? (int) $data['total_parcel'] : 0;
                      $success_parcel = isset( $data['success_parcel'] ) ? (int) $data['success_parcel'] : 0;
                      $returned_parcel = $total_parcel - $success_parcel;
                      
                      // Get logo path
                      $logo_path = $plugin_dir . '/assets/images/' . strtolower( $courier ) . '-logo.png';
                      $logo_url = '';
                      if ( file_exists( $logo_path ) ) {
                          $logo_url = plugins_url( 'assets/images/' . strtolower( $courier ) . '-logo.png', $plugin_main_file );
                      }
                      ?>
                      <tr>
                        <td>
                          <?php if ( $logo_url ) : ?>
                            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $courier_name ); ?>" class="courier-logo" />
                          <?php else : ?>
                            <?php echo esc_html( $courier_name ); ?>
                          <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $total_parcel ); ?></td>
                        <td class="success"><?php echo esc_html( $success_parcel ); ?></td>
                        <td class="return"><?php echo esc_html( $returned_parcel ); ?></td>
                      </tr>
                      <?php
                  }
                  ?>
                  <tr class="summary">
                    <td>Summary</td>
                    <td><?php echo esc_html( $summary['total'] ); ?></td>
                    <td class="success"><?php echo esc_html( $summary['success'] ); ?></td>
                    <td class="return"><?php echo esc_html( $summary['returned'] ); ?></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="bdcr-report-card">
              <div class="bdcr-report-summary-box">
                <h3>Success vs Returned</h3>
                <p>Order distribution overview</p>

                <div class="bdcr-report-stats">
                  <div class="bdcr-report-stat success">
                    Success
                    <div class="value"><?php echo esc_html( $summary['success'] ); ?></div>
                    <?php echo esc_html( $success_percent ); ?>%
                  </div>
                  <div class="bdcr-report-stat return">
                    Returned
                    <div class="value"><?php echo esc_html( $summary['returned'] ); ?></div>
                    <?php echo esc_html( $return_percent ); ?>%
                  </div>
                </div>

                <div class="bdcr-report-progress">
                  <strong>Success Rate</strong>
                  <div class="bdcr-report-bar">
                    <span style="width:<?php echo esc_attr( $success_percent ); ?>%"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="bdcr-powered-by">
            Search Powered By: <a href="https://bdcourier.com" target="_blank" rel="noopener noreferrer">bdcourier.com</a>
          </div>
        </div>
        </div>
        </body>
        </html>
        <?php
    }

    /**
     * Generate image via REST API.
     */
    public function generate_image_rest( $request ) {
        if ( ! function_exists( 'imagecreatetruecolor' ) ) {
            return new WP_Error( 'gd_not_available', 'GD library is not available on this server.', array( 'status' => 500 ) );
        }

        $phone = isset( $request['phone'] ) ? sanitize_text_field( $request['phone'] ) : '';
        $result_data = isset( $request['result'] ) ? $request['result'] : null;

        if ( ! $result_data || ! isset( $result_data['data'] ) || ! isset( $result_data['summary'] ) ) {
            return new WP_Error( 'invalid_data', 'Invalid result data provided.', array( 'status' => 400 ) );
        }

        $courier_data = $result_data['data'];
        $summary = $result_data['summary'];

        // Canvas dimensions - mobile-friendly width
        $dpr = 2;
        $container_padding = 16 * $dpr;
        $top_margin = 30 * $dpr;
        $card_gap = 16 * $dpr;
        $max_width = 800 * $dpr; // Mobile-friendly width (800px instead of 1200px)
        $table_card_width = (int)( $max_width * 1.1 / 2.0 );
        $stats_card_width = (int)( $max_width * 0.9 / 2.0 );
        $card_radius = 16 * $dpr;
        
        $header_height = 80 * $dpr;
        $row_height = 50 * $dpr;
        $courier_entries = array_filter( $courier_data, function( $key ) {
            return $key !== 'summary';
        }, ARRAY_FILTER_USE_KEY );
        $table_rows = count( $courier_entries ) + 1;
        $table_height = 56 * $dpr + ( $table_rows * $row_height );
        
        $stats_card_height = 320 * $dpr;
        $card_height = max( $table_height, $stats_card_height );
        
        $total_width = $max_width + ( $container_padding * 2 );
        $total_height = $top_margin + $header_height + $card_gap + $card_height + $container_padding;

        // Create image
        $image = imagecreatetruecolor( $total_width, $total_height );
        imagealphablending( $image, false );

        // Colors from the HTML design
        $bg_color = imagecolorallocate( $image, 244, 247, 255 );
        $white = imagecolorallocate( $image, 255, 255, 255 );
        $text_dark = imagecolorallocate( $image, 15, 23, 42 );
        $text_muted = imagecolorallocate( $image, 100, 116, 139 );
        $line_color = imagecolorallocate( $image, 231, 236, 245 );
        $success_green = imagecolorallocate( $image, 22, 163, 74 );
        $success_bg = imagecolorallocate( $image, 234, 255, 241 );
        $return_red = imagecolorallocate( $image, 239, 68, 68 );
        $return_bg = imagecolorallocate( $image, 255, 241, 242 );
        $table_header_bg = imagecolorallocate( $image, 248, 250, 252 );
        $summary_bg = imagecolorallocate( $image, 241, 245, 255 );
        $progress_bg = imagecolorallocate( $image, 229, 231, 235 );

        // Background
        imagefilledrectangle( $image, 0, 0, $total_width, $total_height, $bg_color );

        // Helper function for rounded rectangles
        $draw_rounded_rect = function( $img, $x1, $y1, $x2, $y2, $radius, $color ) {
            imagefilledrectangle( $img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color );
            imagefilledrectangle( $img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color );
            imagefilledellipse( $img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color );
            imagefilledellipse( $img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color );
            imagefilledellipse( $img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color );
            imagefilledellipse( $img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color );
        };

        // Font handling - try TTF first, fallback to built-in
        $font_path = __DIR__ . '/../assets/fonts/arial.ttf';
        $use_ttf = file_exists( $font_path );
        
        // Font sizes matching HTML (in points, will be converted for TTF)
        $font_size_title = 22; // 22px from HTML
        $font_size_subtitle = 13; // 13px from HTML
        $font_size_card_header = 14; // 14px from HTML
        $font_size_table_header = 12; // 12px from HTML
        $font_size_table_row = 14; // 14px from HTML
        $font_size_stats_title = 20; // 20px from HTML
        $font_size_stat_label = 12; // 12px from HTML
        $font_size_stat_value = 34; // 34px from HTML
        $font_size_progress_label = 14; // 14px from HTML
        
        // Helper function to draw text with TTF or built-in fonts
        $draw_text = function( $img, $size, $x, $y, $color, $text ) use ( $font_path, $use_ttf, $dpr ) {
            if ( $use_ttf ) {
                imagettftext( $img, $size * $dpr, 0, $x, $y, $color, $font_path, $text );
            } else {
                // Built-in fonts: scale size to approximate
                $builtin_size = max( 1, min( 5, (int)( $size / 6 ) ) );
                imagestring( $img, $builtin_size, $x, $y - ( $builtin_size * 7 ), $text, $color );
            }
        };

        // Header card
        $header_x = $container_padding;
        $header_y = $top_margin;
        $header_w = $max_width;
        $header_h = $header_height;
        
        $draw_rounded_rect( $image, $header_x, $header_y, $header_x + $header_w, $header_y + $header_h, $card_radius, $white );
        imagerectangle( $image, $header_x, $header_y, $header_x + $header_w, $header_y + $header_h, $line_color );

        // Header text
        $draw_text( $image, $font_size_title, $header_x + 20 * $dpr, $header_y + 50 * $dpr, $text_dark, 'Search Results' );
        $draw_text( $image, $font_size_subtitle, $header_x + 20 * $dpr, $header_y + 70 * $dpr, $text_muted, 'Courier order statistics by phone number' );

        // Table card
        $table_card_x = $container_padding;
        $table_card_y = $top_margin + $header_height + $card_gap;
        $table_card_w = $table_card_width;
        $table_card_h = $card_height;
        
        $draw_rounded_rect( $image, $table_card_x, $table_card_y, $table_card_x + $table_card_w, $table_card_y + $table_card_h, $card_radius, $white );
        imagerectangle( $image, $table_card_x, $table_card_y, $table_card_x + $table_card_w, $table_card_y + $table_card_h, $line_color );

        // Card header
        $card_header_h = 42 * $dpr;
        imagefilledrectangle( $image, $table_card_x, $table_card_y, $table_card_x + $table_card_w, $table_card_y + $card_header_h, $white );
        imageline( $image, $table_card_x, $table_card_y + $card_header_h, $table_card_x + $table_card_w, $table_card_y + $card_header_h, $line_color );
        
        $draw_text( $image, $font_size_card_header, $table_card_x + 16 * $dpr, $table_card_y + 35 * $dpr, $text_dark, 'COURIER BREAKDOWN' );

        // Table header
        $table_x = $table_card_x;
        $table_y = $table_card_y + $card_header_h;
        $table_w = $table_card_w;
        $header_row_h = 48 * $dpr;
        
        imagefilledrectangle( $image, $table_x, $table_y, $table_x + $table_w, $table_y + $header_row_h, $table_header_bg );
        imageline( $image, $table_x, $table_y + $header_row_h, $table_x + $table_w, $table_y + $header_row_h, $line_color );

        $header_text_color = imagecolorallocate( $image, 51, 65, 85 );
        $draw_text( $image, $font_size_table_header, $table_x + 14 * $dpr, $table_y + 35 * $dpr, $header_text_color, 'COURIER' );
        $draw_text( $image, $font_size_table_header, $table_x + 180 * $dpr, $table_y + 35 * $dpr, $header_text_color, 'TOTAL' );
        $draw_text( $image, $font_size_table_header, $table_x + 280 * $dpr, $table_y + 35 * $dpr, $header_text_color, 'SUCCESS' );
        $draw_text( $image, $font_size_table_header, $table_x + 380 * $dpr, $table_y + 35 * $dpr, $header_text_color, 'RETURN' );

        // Table rows
        $row_y = $table_y + $header_row_h;
        $index = 0;

        foreach ( $courier_entries as $courier => $data ) {
            if ( $index > 0 ) {
                imageline( $image, $table_x + 14 * $dpr, $row_y, $table_x + $table_w - 14 * $dpr, $row_y, $line_color );
            }

            $courier_name = ! empty( $data['name'] ) ? $data['name'] : ucfirst( $courier );
            $draw_text( $image, $font_size_table_row, $table_x + 14 * $dpr, $row_y + 35 * $dpr, $text_dark, $courier_name );

            $total_parcel = isset( $data['total_parcel'] ) ? (int) $data['total_parcel'] : 0;
            $success_parcel = isset( $data['success_parcel'] ) ? (int) $data['success_parcel'] : 0;
            $returned_parcel = $total_parcel - $success_parcel;

            $draw_text( $image, $font_size_table_row, $table_x + 180 * $dpr, $row_y + 35 * $dpr, $text_dark, (string) $total_parcel );
            $draw_text( $image, $font_size_table_row, $table_x + 280 * $dpr, $row_y + 35 * $dpr, $success_green, (string) $success_parcel );
            $draw_text( $image, $font_size_table_row, $table_x + 380 * $dpr, $row_y + 35 * $dpr, $return_red, (string) $returned_parcel );

            $row_y += $row_height;
            $index++;
        }

        // Summary row
        imagefilledrectangle( $image, $table_x, $row_y, $table_x + $table_w, $row_y + $row_height, $summary_bg );
        imageline( $image, $table_x, $row_y, $table_x + $table_w, $row_y, $line_color );
        
        $draw_text( $image, $font_size_table_row, $table_x + 14 * $dpr, $row_y + 35 * $dpr, $text_dark, 'Summary' );
        $draw_text( $image, $font_size_table_row, $table_x + 180 * $dpr, $row_y + 35 * $dpr, $text_dark, (string) $summary['total'] );
        $draw_text( $image, $font_size_table_row, $table_x + 280 * $dpr, $row_y + 35 * $dpr, $success_green, (string) $summary['success'] );
        $draw_text( $image, $font_size_table_row, $table_x + 380 * $dpr, $row_y + 35 * $dpr, $return_red, (string) $summary['returned'] );

        // Stats card
        $stats_card_x = $table_card_x + $table_card_w + $card_gap;
        $stats_card_y = $table_card_y;
        $stats_card_w = $stats_card_width;
        $stats_card_h = $card_height;
        
        $draw_rounded_rect( $image, $stats_card_x, $stats_card_y, $stats_card_x + $stats_card_w, $stats_card_y + $stats_card_h, $card_radius, $white );
        imagerectangle( $image, $stats_card_x, $stats_card_y, $stats_card_x + $stats_card_w, $stats_card_y + $stats_card_h, $line_color );

        // Stats card content
        $stats_padding = 16 * $dpr;
        $stats_content_y = $stats_card_y + $stats_padding;
        
        $draw_text( $image, $font_size_stats_title, $stats_card_x + $stats_padding, $stats_content_y + 40 * $dpr, $text_dark, 'Success vs Returned' );
        $draw_text( $image, $font_size_subtitle, $stats_card_x + $stats_padding, $stats_content_y + 60 * $dpr, $text_muted, 'Order distribution overview' );

        // Stat boxes
        $stats_y = $stats_content_y + 60 * $dpr;
        $stat_box_height = 90 * $dpr;
        $stat_box_width = ( $stats_card_w - $stats_padding * 3 ) / 2;
        $stat_gap = 12 * $dpr;

        $success_value = (int) $summary['success'];
        $returned_value = (int) $summary['returned'];
        $total_value = $success_value + $returned_value;
        $success_percent = $total_value > 0 ? round( ( $success_value / $total_value ) * 100 ) : 0;
        $return_percent = 100 - $success_percent;

        // Success stat box
        $success_box_x = $stats_card_x + $stats_padding;
        $success_box_y = $stats_y;
        $success_border = imagecolorallocate( $image, 22, 163, 74 );
        imagefilledrectangle( $image, $success_box_x, $success_box_y, $success_box_x + $stat_box_width, $success_box_y + $stat_box_height, $success_bg );
        imagerectangle( $image, $success_box_x, $success_box_y, $success_box_x + $stat_box_width, $success_box_y + $stat_box_height, $success_border );
        
        $success_label_color = imagecolorallocate( $image, 5, 46, 22 );
        $draw_text( $image, $font_size_stat_label, $success_box_x + 16 * $dpr, $success_box_y + 30 * $dpr, $success_label_color, 'Success' );
        $draw_text( $image, $font_size_stat_value, $success_box_x + 16 * $dpr, $success_box_y + 75 * $dpr, $success_green, (string) $success_value );
        $draw_text( $image, $font_size_stat_label, $success_box_x + 16 * $dpr, $success_box_y + 95 * $dpr, $success_label_color, $success_percent . '%' );

        // Returned stat box
        $return_box_x = $success_box_x + $stat_box_width + $stat_gap;
        $return_box_y = $stats_y;
        $return_border = imagecolorallocate( $image, 239, 68, 68 );
        imagefilledrectangle( $image, $return_box_x, $return_box_y, $return_box_x + $stat_box_width, $return_box_y + $stat_box_height, $return_bg );
        imagerectangle( $image, $return_box_x, $return_box_y, $return_box_x + $stat_box_width, $return_box_y + $stat_box_height, $return_border );
        
        $return_label_color = imagecolorallocate( $image, 127, 29, 29 );
        $draw_text( $image, $font_size_stat_label, $return_box_x + 16 * $dpr, $return_box_y + 30 * $dpr, $return_label_color, 'Returned' );
        $draw_text( $image, $font_size_stat_value, $return_box_x + 16 * $dpr, $return_box_y + 75 * $dpr, $return_red, (string) $returned_value );
        $draw_text( $image, $font_size_stat_label, $return_box_x + 16 * $dpr, $return_box_y + 95 * $dpr, $return_label_color, $return_percent . '%' );

        // Progress bar
        $progress_y = $stats_y + $stat_box_height + 16 * $dpr;
        $progress_h = 52 * $dpr;
        $progress_w = $stats_card_w - $stats_padding * 2;
        $progress_x = $stats_card_x + $stats_padding;
        
        imagefilledrectangle( $image, $progress_x, $progress_y, $progress_x + $progress_w, $progress_y + $progress_h, $white );
        imagerectangle( $image, $progress_x, $progress_y, $progress_x + $progress_w, $progress_y + $progress_h, $line_color );
        
        $draw_text( $image, $font_size_progress_label, $progress_x + 14 * $dpr, $progress_y + 30 * $dpr, $text_dark, 'Success Rate' );
        
        $bar_y = $progress_y + 28 * $dpr;
        $bar_h = 12 * $dpr;
        $bar_w = $progress_w - 28 * $dpr;
        $bar_x = $progress_x + 14 * $dpr;
        imagefilledrectangle( $image, $bar_x, $bar_y, $bar_x + $bar_w, $bar_y + $bar_h, $progress_bg );
        
        if ( $total_value > 0 ) {
            $fill_width = (int)( $bar_w * ( $success_percent / 100 ) );
            $progress_green = imagecolorallocate( $image, 34, 197, 94 );
            imagefilledrectangle( $image, $bar_x, $bar_y, $bar_x + $fill_width, $bar_y + $bar_h, $progress_green );
        }

        // Output image
        ob_start();
        imagepng( $image );
        $image_data = ob_get_clean();
        imagedestroy( $image );

        $base64 = base64_encode( $image_data );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => 'data:image/png;base64,' . $base64,
        ), 200 );
    }

   public function search_courier_data() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'search_courier_data_nonce' ) ) {
        wp_send_json_error( __( 'Invalid nonce.', 'bd-courier-order-ratio-checker' ) );
    }

    if ( empty( $_POST['phone'] ) ) {
        wp_send_json_error( __( 'Phone number is required.', 'bd-courier-order-ratio-checker' ) );
    }

    $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ) );
    $courier_data = CourierAPI::fetch_order_ratio_from_api( $phone );

    if ( $courier_data ) {
        // Store in history table for manual checks
        require_once dirname( __FILE__ ) . '/class.CourierHistory.php';
        CourierHistory::store_history( $phone, $courier_data, 'manual', null );
        $total_sum   = 0;
        $success_sum = 0;

        ob_start();
        ?>
        <style>
            .progress-bar-container {
                background: #f1f1f1;
                border-radius: 5px;
                overflow: hidden;
                margin-top: 10px;
                height: 20px;
            }
            .progress-bar-success {
                background-color: #34C759;
                height: 100%;
                float: left;
                text-align: center;
                color: white;
                line-height: 20px;
                font-size: 12px;
            }
            .progress-bar-fail {
                background-color: #E73534;
                height: 100%;
                float: left;
                text-align: center;
                color: white;
                line-height: 20px;
                font-size: 12px;
            }
        </style>
        <table class="bd-courier-table bangla">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'কুরিয়ার', 'bd-courier-order-ratio-checker' ); ?></th>
                    <th><?php esc_html_e( 'মোট', 'bd-courier-order-ratio-checker' ); ?></th>
                    <th><?php esc_html_e( 'সফল', 'bd-courier-order-ratio-checker' ); ?></th>
                    <th><?php esc_html_e( 'রিটার্ন', 'bd-courier-order-ratio-checker' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ( $courier_data as $courier => $data ) {
                    if ( 'summary' !== $courier && is_array( $data ) ) {
                        $total_parcel   = isset( $data['total_parcel'] ) ? (int) $data['total_parcel'] : 0;
                        $success_parcel = isset( $data['success_parcel'] ) ? (int) $data['success_parcel'] : 0;
                        $return_parcel  = $total_parcel - $success_parcel;

                        $total_sum   += $total_parcel;
                        $success_sum += $success_parcel;

                        $logo_path = plugin_dir_url( __FILE__ ) . '../assets/images/' . strtolower( $courier ) . '-logo.png';
                        ?>
                        <tr>
                            <td>
                                <img src="<?php echo esc_url( $logo_path ); ?>" alt="<?php echo esc_attr( ucfirst( $courier ) . ' লোগো' ); ?>" class="bdcrc-courier-logo bangla">
                            </td>
                            <td class="bangla"><?php echo esc_html( $total_parcel ); ?></td>
                            <td class="bangla"><?php echo esc_html( $success_parcel ); ?></td>
                            <td class="bangla"><?php echo esc_html( $return_parcel ); ?></td>
                        </tr>
                        <?php
                    }
                }

                $return_sum      = $total_sum - $success_sum;
                $success_percent = $total_sum > 0 ? round( ( $success_sum / $total_sum ) * 100 ) : 0;
                $fail_percent    = 100 - $success_percent;
                ?>
                <tr style="font-weight: bold; background: #ecf0f1;">
                    <td><?php esc_html_e( 'সারাংশ', 'bd-courier-order-ratio-checker' ); ?></td>
                    <td class="bangla"><?php echo esc_html( $total_sum ); ?></td>
                    <td class="bangla"><?php echo esc_html( $success_sum ); ?></td>
                    <td class="bangla"><?php echo esc_html( $return_sum ); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="progress-bar-container">
            <div class="progress-bar-success" style="width: <?php echo esc_attr( $success_percent ); ?>%;">
                <?php echo esc_html( $success_percent ); ?>% সফল
            </div>
            <div class="progress-bar-fail" style="width: <?php echo esc_attr( $fail_percent ); ?>%;">
                <?php echo esc_html( $fail_percent ); ?>% রিটার্ন
            </div>
        </div>
        <?php
        $table_html = ob_get_clean();
        wp_send_json_success( [ 'table' => $table_html ] );
    } else {
        wp_send_json_error( __( 'Failed to fetch data from API.', 'bd-courier-order-ratio-checker' ) );
    }
}

}