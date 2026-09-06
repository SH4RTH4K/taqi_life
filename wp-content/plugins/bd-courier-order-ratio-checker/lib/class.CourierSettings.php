<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierSettings
 * Manages the plugin settings page using Vue.js with a modern, ModuleGarden‑inspired design.
 */
class CourierSettings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_bd_courier_settings_page' ) );
        add_action( 'wp_ajax_save_courier_settings', array( $this, 'save_courier_settings' ) );
        add_action( 'wp_ajax_check_api_connection', array( $this, 'check_api_connection' ) );
        add_action( 'wp_ajax_get_plan_info', array( $this, 'get_plan_info' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_head', array( $this, 'hide_admin_notices' ) );
        
        // Add filter for module type - must be added early
        add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 2 );

        // REST API endpoints for React frontend
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // cPanel security layers can replace REST responses with an HTML
        // challenge. Route the settings app through authenticated wp-admin,
        // with a normal form submission as the final token-save fallback.
        add_action( 'admin_init', array( $this, 'handle_admin_api_proxy' ), 0 );
        add_action( 'admin_init', array( $this, 'handle_api_token_form_fallback' ), 1 );

        // Admin notices for database issues
        add_action( 'admin_init', array( $this, 'check_database_admin_notice' ) );
        add_action( 'admin_notices', array( $this, 'show_database_admin_notice' ) );
    }
    
    /**
     * Add type="module" to React app scripts
     */
    public function add_module_type_to_scripts( $tag, $handle ) {
        if ( $handle === 'bd-courier-react-js' || $handle === 'bd-courier-react-vendor' ) {
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
     * Add the BD Courier Settings menu page.
     */
    public function add_bd_courier_settings_page() {
        add_menu_page(
            __( 'BD Courier Settings', 'bd-courier-order-ratio-checker' ),
            __( 'BD Courier Settings', 'bd-courier-order-ratio-checker' ),
            'manage_options',
            'bd-courier-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-admin-generic',
            56
        );
    }

    /**
     * Enqueue React app assets.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on the settings page - strict check
        // Exclude all order pages explicitly
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' ) {
            return;
        }
        if ( isset( $_GET['post'] ) && get_post_type( intval( $_GET['post'] ) ) === 'shop_order' ) {
            return;
        }
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            return;
        }
        if ( $hook === 'woocommerce_page_wc-orders' ) {
            return;
        }
        
        // Only proceed if it's the settings page
        if ( $hook !== 'toplevel_page_bd-courier-settings' ) {
            return;
        }

        // Get plugin root directory and main file path
        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';

        // Try to find the actual built files (they have hashed names)
        $dist_dir = $plugin_dir . '/assets/react-apps/settings-app/assets';
        $dist_root = $plugin_dir . '/assets/react-apps/settings-app';
        
        // Fallback to old location if new location doesn't exist
        if ( ! is_dir( $dist_dir ) ) {
            $dist_dir = $plugin_dir . '/courier-console-pro-main/dist/assets';
            $dist_root = $plugin_dir . '/courier-console-pro-main/dist';
        }
        
        if ( is_dir( $dist_dir ) ) {
            $css_files = glob( $dist_dir . '/*.css' );
            $js_files = glob( $dist_dir . '/*.js' );
            
            if ( ! empty( $css_files ) ) {
                $css_file = $css_files[0];
                // Determine the correct path based on which directory we're using
                $css_path = strpos( $css_file, 'assets/react-apps' ) !== false 
                    ? 'assets/react-apps/settings-app/assets/' . basename( $css_file )
                    : 'courier-console-pro-main/dist/assets/' . basename( $css_file );
                $css_url = plugins_url( $css_path, $plugin_main_file );
                $css_url = add_query_arg( 'v', BD_COURIER_VERSION, $css_url );
                
                wp_enqueue_style(
                    'bd-courier-react-css',
                    $css_url,
                    [],
                    BD_COURIER_VERSION
                );
            }
            
            // Load all JS files from dist root and assets folder as modules
            $all_js_files = array_merge(
                glob( $dist_root . '/*.js' ),
                glob( $dist_root . '/assets/*.js' )
            );
            $main_js_file = null;
            $vendor_js_file = null;
            
            foreach ( $all_js_files as $js_file ) {
                $basename = basename( $js_file );
                // Skip notifications file
                if ( strpos( $basename, 'notifications-' ) === 0 ) {
                    continue;
                }
                // Find main entry (in dist root, not in assets subfolder)
                // Check if file is in the root of dist, not in dist/assets/
                $is_in_assets_subfolder = strpos( $js_file, $dist_root . '/assets/' ) !== false;
                if ( strpos( $basename, 'index-' ) === 0 && ! $is_in_assets_subfolder ) {
                    $main_js_file = $js_file;
                }
                // Find vendor chunk (in assets folder)
                if ( strpos( $basename, 'vendor-' ) === 0 ) {
                    $vendor_js_file = $js_file;
                }
            }
            
            if ( $main_js_file ) {
                // Determine the correct path based on which directory we're using
                $main_js_path = strpos( $main_js_file, 'assets/react-apps' ) !== false 
                    ? 'assets/react-apps/settings-app/' . basename( $main_js_file )
                    : 'courier-console-pro-main/dist/' . basename( $main_js_file );
                $main_js_url = plugins_url( $main_js_path, $plugin_main_file );
                $main_js_url = add_query_arg( 'v', BD_COURIER_VERSION, $main_js_url );
                
                // Enqueue vendor chunk first if it exists (from assets folder)
                if ( $vendor_js_file ) {
                    $vendor_js_path = strpos( $vendor_js_file, 'assets/react-apps' ) !== false 
                        ? 'assets/react-apps/settings-app/assets/' . basename( $vendor_js_file )
                        : 'courier-console-pro-main/dist/assets/' . basename( $vendor_js_file );
                    $vendor_js_url = plugins_url( $vendor_js_path, $plugin_main_file );
                    $vendor_js_url = add_query_arg( 'v', BD_COURIER_VERSION, $vendor_js_url );
                    
                    wp_enqueue_script(
                        'bd-courier-react-vendor',
                        $vendor_js_url,
                        [],
                        BD_COURIER_VERSION,
                        true
                    );
                }
                
                // Enqueue main script
                wp_enqueue_script(
                    'bd-courier-react-js',
                    $main_js_url,
                    $vendor_js_file ? [ 'bd-courier-react-vendor' ] : [],
                    BD_COURIER_VERSION,
                    true
                );
                
                // Module type is added via filter in constructor

                // Localize script with WordPress REST API data
                $initial_settings = $this->get_settings();
                $initial_settings = is_object( $initial_settings ) && method_exists( $initial_settings, 'get_data' ) ? $initial_settings->get_data() : array();
                wp_localize_script( 'bd-courier-react-js', 'wpApiSettings', [
                    'root'          => esc_url_raw( rest_url() ),
                    'nonce'         => wp_create_nonce( 'wp_rest' ),
                    'fallbackUrl'   => esc_url_raw( admin_url( 'admin.php?page=bd-courier-settings&bd_courier_api_proxy=1' ) ),
                    'fallbackNonce' => wp_create_nonce( 'bd_courier_admin_api_proxy' ),
                    'initialSettings' => $initial_settings,
                ]);

                // Keep the React application unchanged on hosts where REST is
                // healthy. If a firewall returns HTML (or a network/5xx error)
                // for the token save request, submit the protected wp-admin
                // fallback form as a regular browser navigation.
                wp_add_inline_script(
                    'bd-courier-react-js',
                    <<<'JS'
(function () {
    if (window.bdCourierApiTokenFallbackInstalled || typeof window.fetch !== 'function') return;
    window.bdCourierApiTokenFallbackInstalled = true;
    var originalFetch = window.fetch.bind(window);

    function apiRoute(input) {
        var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
        var marker = '/bd-courier/v1/';
        var position = url.indexOf(marker);
        if (position === -1) return '';
        return url.slice(position + marker.length).split(/[?#]/)[0].replace(/^\/+|\/+$/g, '');
    }

    function tokenSaveRequest(input, options) {
        var route = apiRoute(input);
        var method = String((options && options.method) || (input && input.method) || 'GET').toUpperCase();
        if (method !== 'POST' || route !== 'settings') return null;
        try {
            var payload = JSON.parse((options && options.body) || '{}');
            return Object.prototype.hasOwnProperty.call(payload, 'apiToken') ? String(payload.apiToken || '') : null;
        } catch (error) {
            return null;
        }
    }

    function submitFallback(apiToken) {
        var form = document.getElementById('bd-courier-api-token-fallback-form');
        var field = document.getElementById('bd-courier-api-token-fallback-value');
        if (!form || !field) return false;
        field.value = apiToken;
        form.submit();
        return true;
    }

    window.fetch = function (input, options) {
        var apiToken = tokenSaveRequest(input, options);
        var route = apiRoute(input);
        var apiSettings = window.wpApiSettings || {};
        if (!route || !apiSettings.fallbackUrl || !apiSettings.fallbackNonce) return originalFetch(input, options);

        // The live firewall can also replace the authenticated REST GET with
        // an HTML challenge. The server already localized the current admin
        // settings, so use that trusted snapshot for the initial screen load.
        var method = String((options && options.method) || (input && input.method) || 'GET').toUpperCase();
        if (route === 'settings' && method === 'GET' && apiSettings.initialSettings) {
            return Promise.resolve(new Response(JSON.stringify(apiSettings.initialSettings), {
                status: 200,
                headers: { 'Content-Type': 'application/json' }
            }));
        }

        var separator = apiSettings.fallbackUrl.indexOf('?') === -1 ? '?' : '&';
        var proxyUrl = apiSettings.fallbackUrl + separator + 'bd_courier_route=' + encodeURIComponent(route) + '&bd_courier_proxy_nonce=' + encodeURIComponent(apiSettings.fallbackNonce);
        var proxyOptions = options ? Object.assign({}, options) : {};

        return originalFetch(proxyUrl, proxyOptions).then(function (response) {
            var contentType = response.headers && response.headers.get ? (response.headers.get('content-type') || '') : '';
            if (apiToken !== null && (response.status >= 500 || contentType.toLowerCase().indexOf('json') === -1) && submitFallback(apiToken)) {
                return new Promise(function () {});
            }
            return response;
        }).catch(function (error) {
            if (apiToken !== null && submitFallback(apiToken)) return new Promise(function () {});
            throw error;
        });
    };
}());
JS
                    ,
                    'before'
                );
            }
        }
    }

    /**
     * Render the React-powered settings page.
     */
    /**
     * Hide admin notices on plugin admin pages.
     */
    public function hide_admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        
        // Check if we're on any of our plugin pages
        $plugin_pages = array(
            'toplevel_page_bd-courier-settings',
            'toplevel_page_bd-courier-search',
            'toplevel_page_bd-courier-order-ratio',
            'woocommerce_page_bd-courier-blocked-entities',
            'woocommerce_page_bd-courier-rejected-logs',
            'woocommerce_page_bd-courier-incomplete-orders',
        );
        
        // Also check by page parameter
        $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $plugin_page_slugs = array(
            'bd-courier-settings',
            'bd-courier-search',
            'bd-courier-order-ratio',
            'bd-courier-blocked-entities',
            'bd-courier-rejected-logs',
            'bd-courier-incomplete-orders',
        );
        
        $is_plugin_page = in_array( $screen->id, $plugin_pages, true ) || in_array( $current_page, $plugin_page_slugs, true );
        
        if ( $is_plugin_page ) {
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

    /**
     * Authenticated wp-admin JSON bridge for hosts that block /wp-json/.
     * It reuses the same callbacks, validation, and response formats as REST.
     */
    public function handle_admin_api_proxy() {
        if ( empty( $_GET['bd_courier_api_proxy'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( array( 'code' => 'rest_forbidden', 'message' => 'You are not allowed to access these settings.', 'data' => array( 'status' => 403 ) ), 403 );
        }

        $nonce = isset( $_GET['bd_courier_proxy_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['bd_courier_proxy_nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bd_courier_admin_api_proxy' ) ) {
            wp_send_json( array( 'code' => 'rest_cookie_invalid_nonce', 'message' => 'The security token expired. Refresh this page and try again.', 'data' => array( 'status' => 403 ) ), 403 );
        }

        $route  = isset( $_GET['bd_courier_route'] ) ? sanitize_key( wp_unslash( $_GET['bd_courier_route'] ) ) : '';
        $method = strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET' );
        $routes = array(
            'settings'          => array(
                'GET'  => array( 'callback' => 'get_settings', 'request' => false ),
                'POST' => array( 'callback' => 'save_settings_rest', 'request' => true ),
            ),
            'connection-test'   => array( 'POST' => array( 'callback' => 'test_connection_rest', 'request' => false ) ),
            'plan-info'         => array( 'GET' => array( 'callback' => 'get_plan_info_rest', 'request' => false ) ),
            'database-check'    => array( 'GET' => array( 'callback' => 'check_database', 'request' => true ) ),
            'database-update'   => array( 'POST' => array( 'callback' => 'update_database', 'request' => true ) ),
            'migration-status'  => array( 'GET' => array( 'callback' => 'get_migration_status', 'request' => false ) ),
            'migrate-orders'    => array( 'POST' => array( 'callback' => 'migrate_orders', 'request' => true ) ),
            'complete-migration'=> array( 'POST' => array( 'callback' => 'complete_migration', 'request' => false ) ),
        );

        if ( ! isset( $routes[ $route ][ $method ] ) ) {
            wp_send_json( array( 'code' => 'rest_no_route', 'message' => 'No matching BD Courier settings route was found.', 'data' => array( 'status' => 404 ) ), 404 );
        }

        $definition = $routes[ $route ][ $method ];
        $request    = new WP_REST_Request( $method, '/bd-courier/v1/' . $route );
        $raw_body   = file_get_contents( 'php://input' );
        if ( false !== $raw_body && '' !== $raw_body ) {
            $request->set_body( $raw_body );
            $request->set_header( 'content-type', 'application/json' );
        }

        try {
            $response = $definition['request']
                ? call_user_func( array( $this, $definition['callback'] ), $request )
                : call_user_func( array( $this, $definition['callback'] ) );
        } catch ( Throwable $exception ) {
            wp_send_json( array( 'code' => 'bd_courier_proxy_error', 'message' => $exception->getMessage(), 'data' => array( 'status' => 500 ) ), 500 );
        }

        if ( is_wp_error( $response ) ) {
            $error_data = $response->get_error_data();
            $status     = is_array( $error_data ) && isset( $error_data['status'] ) ? absint( $error_data['status'] ) : 500;
            wp_send_json(
                array(
                    'code'    => $response->get_error_code(),
                    'message' => $response->get_error_message(),
                    'data'    => is_array( $error_data ) ? $error_data : array( 'status' => $status ),
                ),
                $status
            );
        }

        $response = rest_ensure_response( $response );
        wp_send_json( $response->get_data(), $response->get_status() );
    }

    /**
     * Save the API token without REST/AJAX when even the JSON bridge is blocked.
     */
    public function handle_api_token_form_fallback() {
        if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || empty( $_POST['bd_courier_api_token_fallback'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to change these settings.', 'bd-courier-order-ratio-checker' ), '', array( 'response' => 403 ) );
        }

        check_admin_referer( 'bd_courier_api_token_fallback', 'bd_courier_api_token_fallback_nonce' );
        $api_token = isset( $_POST['apiToken'] ) && is_scalar( $_POST['apiToken'] )
            ? sanitize_text_field( wp_unslash( $_POST['apiToken'] ) )
            : '';

        update_option( 'bd_courier_api_token', $api_token );
        $saved = hash_equals( $api_token, (string) get_option( 'bd_courier_api_token', '' ) );
        $url   = add_query_arg(
            'bd_courier_token_saved',
            $saved ? '1' : '0',
            admin_url( 'admin.php?page=bd-courier-settings' )
        );
        wp_safe_redirect( $url . '#api' );
        exit;
    }

    public function render_settings_page() {
        $save_status = isset( $_GET['bd_courier_token_saved'] ) ? sanitize_key( wp_unslash( $_GET['bd_courier_token_saved'] ) ) : '';
        if ( '1' === $save_status ) {
            echo '<div style="max-width:1120px;margin:18px auto 0;padding:12px 16px;border-left:4px solid #00a32a;background:#edfaef;color:#145523;"><strong>API token saved successfully.</strong> The secure wp-admin fallback was used because the live server blocked the REST request.</div>';
        } elseif ( '0' === $save_status ) {
            echo '<div style="max-width:1120px;margin:18px auto 0;padding:12px 16px;border-left:4px solid #d63638;background:#fcf0f1;color:#8a2424;"><strong>API token could not be saved.</strong> Check database permissions and available disk space.</div>';
        }
        ?>
        <form id="bd-courier-api-token-fallback-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bd-courier-settings' ) ); ?>" style="display:none;">
            <?php wp_nonce_field( 'bd_courier_api_token_fallback', 'bd_courier_api_token_fallback_nonce' ); ?>
            <input type="hidden" name="bd_courier_api_token_fallback" value="1">
            <input type="hidden" id="bd-courier-api-token-fallback-value" name="apiToken" value="">
        </form>
        <div style="max-width:1120px;margin:18px auto 0;padding:9px 14px;border-left:4px solid #2271b1;background:#f0f6fc;color:#174a6b;font-size:13px;">
            <strong>Live API save protection active.</strong> API Token → Save Settings uses a secure WordPress form and does not depend on REST/AJAX.
        </div>
        <div id="bd-courier-react-root"></div>
        <script>
        (function () {
            if (window.bdCourierDirectTokenSaveInstalled) return;
            window.bdCourierDirectTokenSaveInstalled = true;

            // Install a page-level bridge as a second layer. This remains
            // available even if a bundled script initializes before the
            // wp_add_inline_script wrapper above is emitted.
            if (!window.bdCourierDirectApiProxyInstalled && typeof window.fetch === 'function') {
                window.bdCourierDirectApiProxyInstalled = true;
                var directOriginalFetch = window.fetch.bind(window);

                function directApiRoute(input) {
                    var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                    var marker = '/bd-courier/v1/';
                    var position = url.indexOf(marker);
                    if (position === -1) return '';
                    return url.slice(position + marker.length).split(/[?#]/)[0].replace(/^\/+|\/+$/g, '');
                }

                window.fetch = function (input, options) {
                    var route = directApiRoute(input);
                    var apiSettings = window.wpApiSettings || {};
                    if (!route || !apiSettings.fallbackUrl || !apiSettings.fallbackNonce) {
                        return directOriginalFetch(input, options);
                    }

                    var method = String((options && options.method) || (input && input.method) || 'GET').toUpperCase();
                    if (route === 'settings' && method === 'GET' && apiSettings.initialSettings) {
                        return Promise.resolve(new Response(JSON.stringify(apiSettings.initialSettings), {
                            status: 200,
                            headers: { 'Content-Type': 'application/json' }
                        }));
                    }

                    var separator = apiSettings.fallbackUrl.indexOf('?') === -1 ? '?' : '&';
                    var proxyUrl = apiSettings.fallbackUrl + separator + 'bd_courier_route=' + encodeURIComponent(route) + '&bd_courier_proxy_nonce=' + encodeURIComponent(apiSettings.fallbackNonce);
                    return directOriginalFetch(proxyUrl, options);
                };
            }

            document.addEventListener('click', function (event) {
                var button = event.target && event.target.closest ? event.target.closest('button') : null;
                var root = document.getElementById('bd-courier-react-root');
                if (!button || !root || !root.contains(button) || button.textContent.trim() !== 'Save Settings') return;

                var card = button.closest('.bdc-card');
                if (!card) return;
                var labels = card.querySelectorAll('label');
                var isApiTokenCard = false;
                for (var index = 0; index < labels.length; index++) {
                    if (labels[index].textContent.trim() === 'API Token') {
                        isApiTokenCard = true;
                        break;
                    }
                }
                if (!isApiTokenCard) return;

                var tokenInput = card.querySelector('input[type="password"], input[type="text"]');
                var form = document.getElementById('bd-courier-api-token-fallback-form');
                var fallbackValue = document.getElementById('bd-courier-api-token-fallback-value');
                if (!tokenInput || !form || !fallbackValue) return;

                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                fallbackValue.value = tokenInput.value;
                button.disabled = true;
                form.submit();
            }, true);
        }());
        </script>
        <?php
    }

    /**
     * Handle AJAX request to save settings.
     */
    public function save_courier_settings() {
        if (
            ! isset( $_POST['_wpnonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'save_courier_settings_nonce' )
        ) {
            wp_send_json_error( 'Invalid nonce.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $apiToken = isset( $_POST['apiToken'] ) && is_scalar( $_POST['apiToken'] ) ? sanitize_text_field( wp_unslash( $_POST['apiToken'] ) ) : '';

        update_option( 'bd_courier_api_token', $apiToken );

        if ( ! hash_equals( $apiToken, (string) get_option( 'bd_courier_api_token', '' ) ) ) {
            wp_send_json_error( array( 'message' => 'WordPress could not save the API token. Check database permissions and available disk space.' ), 500 );
        }

        wp_send_json_success();
    }

    /**
     * Handle AJAX request to check API connection.
     */
    public function check_api_connection() {
        if (
            ! isset( $_POST['_wpnonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'save_courier_settings_nonce' )
        ) {
            wp_send_json_error( 'Invalid nonce.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $result = CourierAPI::check_api_connection();
        if ( $result && isset( $result['status'] ) && $result['status'] === 'success' ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * Handle AJAX request to get plan information.
     */
    public function get_plan_info() {
        if (
            ! isset( $_POST['_wpnonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'save_courier_settings_nonce' )
        ) {
            wp_send_json_error( 'Invalid nonce.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $result = CourierAPI::get_plan_info();
        if ( $result && isset( $result['status'] ) && $result['status'] === 'success' ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * Register REST API routes for React frontend.
     */
    public function register_rest_routes() {
        register_rest_route( 'bd-courier/v1', '/settings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_settings' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'save_settings_rest' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            ),
        ) );

        register_rest_route( 'bd-courier/v1', '/connection-test', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'test_connection_rest' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/plan-info', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_plan_info_rest' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/database-check', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'check_database' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/database-update', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'update_database' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/migration-status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_migration_status' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/migrate-orders', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'migrate_orders' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'bd-courier/v1', '/complete-migration', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'complete_migration' ),
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
     * Get settings via REST API.
     */
    public function get_settings() {
        return new WP_REST_Response( array(
            'apiToken' => get_option( 'bd_courier_api_token', '' ),
            // General settings
            'autoCheckOrderRatio' => get_option( 'bd_courier_auto_check_order_ratio', false ),
            'rejectLowRatio' => get_option( 'bd_courier_reject_low_ratio', false ),
            'minSuccessRatio' => get_option( 'bd_courier_min_success_ratio', 50 ),
            'rejectionMessage' => get_option( 'bd_courier_rejection_message', '' ),
            'collectIncompleteData' => get_option( 'bd_courier_collect_incomplete_data', false ),
            'disableDuplicateOrders' => get_option( 'bd_courier_disable_duplicate_orders', false ),
            'duplicateOrderHours' => get_option( 'bd_courier_duplicate_order_hours', 24 ),
            // Error messages
            'errorMessageBlockedEntities' => get_option( 'bdc_error_blocked_entities', 'Order Blocked: Your order cannot be processed due to security restrictions. Blocked items: %s. Please contact support if you believe this is an error.' ),
            'errorMessageBlockedEntitiesDetailed' => get_option( 'bdc_error_blocked_entities_detailed', 'Order Blocked: Your order cannot be processed due to security restrictions. The following items are blocked: %s. Please contact support if you believe this is an error.' ),
            'errorMessageDuplicateOrder' => get_option( 'bdc_error_duplicate_order', 'An order from this IP address was already placed in the last %d hour(s). Please wait before placing another order.' ),
            'errorMessageLowRatio' => get_option( 'bdc_error_low_ratio', 'Order cannot be placed: Courier success ratio (%.2f%%) is below minimum required (%.2f%%). Please contact support.' ),
        ), 200 );
    }

    /**
     * Save settings via REST API.
     */
    public function save_settings_rest( $request ) {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : array();
        
        // Save the token before any external API request. Previously the live
        // site tried to load plan information with the old token first, which
        // could time out and prevent the new token from ever reaching MySQL.
        if ( isset( $params['apiToken'] ) ) {
            $api_token = is_scalar( $params['apiToken'] ) ? sanitize_text_field( (string) $params['apiToken'] ) : '';
            update_option( 'bd_courier_api_token', $api_token );
            if ( ! hash_equals( $api_token, (string) get_option( 'bd_courier_api_token', '' ) ) ) {
                return new WP_Error( 'bd_courier_token_save_failed', 'WordPress could not save the API token. Check database permissions and available disk space.', array( 'status' => 500 ) );
            }

            // The API tab sends only apiToken. Avoid an unrelated remote plan
            // request so saving remains fast and reliable on shared hosting.
            if ( 1 === count( $params ) ) {
                return new WP_REST_Response( array( 'success' => true, 'message' => 'API token saved successfully' ), 200 );
            }
        }

        // Plan information is required only for the paid general settings.
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        $plan_info     = CourierAPI::get_plan_info();
        $plan_data     = isset( $plan_info['data'] ) ? $plan_info['data'] : array();
        $has_paid_plan = isset( $plan_data['is_free'] ) && false === $plan_data['is_free'];

        // Handle general settings if provided
        if ( isset( $params['autoCheckOrderRatio'] ) ) {
            update_option( 'bd_courier_auto_check_order_ratio', (bool) $params['autoCheckOrderRatio'] );
        }
        
        // Paid-only settings - only save if user has paid plan, otherwise force to false
        if ( isset( $params['rejectLowRatio'] ) ) {
            $value = $has_paid_plan ? (bool) $params['rejectLowRatio'] : false;
            update_option( 'bd_courier_reject_low_ratio', $value );
        }
        if ( isset( $params['minSuccessRatio'] ) ) {
            if ( $has_paid_plan ) {
                $min_ratio = absint( $params['minSuccessRatio'] );
                if ( $min_ratio >= 0 && $min_ratio <= 100 ) {
                    update_option( 'bd_courier_min_success_ratio', $min_ratio );
                }
            }
        }
        if ( isset( $params['rejectionMessage'] ) ) {
            if ( $has_paid_plan ) {
                $rejection_message = sanitize_textarea_field( $params['rejectionMessage'] );
                update_option( 'bd_courier_rejection_message', $rejection_message );
            } else {
                update_option( 'bd_courier_rejection_message', '' );
            }
        }
        if ( isset( $params['collectIncompleteData'] ) ) {
            $value = $has_paid_plan ? (bool) $params['collectIncompleteData'] : false;
            update_option( 'bd_courier_collect_incomplete_data', $value );
        }
        if ( isset( $params['disableDuplicateOrders'] ) ) {
            $value = $has_paid_plan ? (bool) $params['disableDuplicateOrders'] : false;
            update_option( 'bd_courier_disable_duplicate_orders', $value );
        }
        if ( isset( $params['duplicateOrderHours'] ) ) {
            if ( $has_paid_plan ) {
                $hours = absint( $params['duplicateOrderHours'] );
                if ( $hours >= 1 && $hours <= 168 ) {
                    update_option( 'bd_courier_duplicate_order_hours', $hours );
                }
            }
        }
        
        // Handle error messages
        if ( isset( $params['errorMessageBlockedEntities'] ) ) {
            $message = sanitize_textarea_field( $params['errorMessageBlockedEntities'] );
            update_option( 'bdc_error_blocked_entities', $message );
        }
        if ( isset( $params['errorMessageBlockedEntitiesDetailed'] ) ) {
            $message = sanitize_textarea_field( $params['errorMessageBlockedEntitiesDetailed'] );
            update_option( 'bdc_error_blocked_entities_detailed', $message );
        }
        if ( isset( $params['errorMessageDuplicateOrder'] ) ) {
            $message = sanitize_textarea_field( $params['errorMessageDuplicateOrder'] );
            update_option( 'bdc_error_duplicate_order', $message );
        }
        if ( isset( $params['errorMessageLowRatio'] ) ) {
            $message = sanitize_textarea_field( $params['errorMessageLowRatio'] );
            update_option( 'bdc_error_low_ratio', $message );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Settings saved successfully',
        ), 200 );
    }

    /**
     * Test connection via REST API.
     */
    public function test_connection_rest() {
        $result = CourierAPI::check_api_connection();

        if ( $result && isset( $result['status'] ) && $result['status'] === 'success' ) {
            return new WP_REST_Response( $result, 200 );
        } else {
            return new WP_Error( 'connection_failed', isset( $result['message'] ) ? $result['message'] : 'Connection failed', array( 'status' => 400 ) );
        }
    }

    /**
     * Get plan info via REST API.
     */
    public function get_plan_info_rest() {
        $result = CourierAPI::get_plan_info();

        // Log the result for debugging
        if ( isset( $result['data'] ) ) {
        }

        if ( $result && isset( $result['status'] ) && $result['status'] === 'success' ) {
            return new WP_REST_Response( $result, 200 );
        } else {
            $error_message = isset( $result['message'] ) ? $result['message'] : 'Failed to get plan info';
            return new WP_Error( 'plan_info_failed', $error_message, array( 'status' => 400 ) );
        }
    }

    /**
     * Check database structure for missing tables or columns.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function check_database( $request ) {
        global $wpdb;
        
        $issues = array();
        $all_ok = true;

        // Check incomplete orders table
        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        $incomplete_table = $wpdb->prefix . 'bd_courier_incomplete_orders';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$incomplete_table'" ) === $incomplete_table;
        
        if ( ! $table_exists ) {
            $issues[] = array(
                'type' => 'table',
                'table' => 'bd_courier_incomplete_orders',
                'issue' => 'Table does not exist',
                'status' => 'missing',
            );
            $all_ok = false;
        } else {
            // Define all required columns for incomplete orders table
            $required_columns = array(
                'reference_number' => 'varchar(50)',
                'courier_data' => 'longtext',
                'abandonment_reason' => 'varchar(255)',
                'billing_data' => 'longtext',
                'shipping_data' => 'longtext',
                'cart_data' => 'longtext',
                'customer_data' => 'longtext',
                'order_data' => 'longtext',
                'status' => 'varchar(50)',
                'converted_to_order_id' => 'bigint(20)',
                'phone' => 'varchar(50)',
                'email' => 'varchar(255)',
                'checkout_step' => 'varchar(50)',
                'session_id' => 'varchar(255)',
                'ip_address' => 'varchar(45)',
                'user_agent' => 'text',
                'notes' => 'text',
            );
            
            // Check each required column
            foreach ( $required_columns as $column_name => $column_type ) {
                $column_exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = %s 
                    AND TABLE_NAME = %s 
                    AND COLUMN_NAME = %s",
                    DB_NAME,
                    $incomplete_table,
                    $column_name
                ) );
                
                // Convert to boolean - COUNT(*) returns string '0' or '1'
                $column_exists = (int) $column_exists > 0;
                
                if ( ! $column_exists ) {
                    $issues[] = array(
                        'type' => 'column',
                        'table' => 'bd_courier_incomplete_orders',
                        'column' => $column_name,
                        'issue' => 'Column ' . $column_name . ' is missing',
                        'status' => 'missing',
                    );
                    $all_ok = false;
                }
            }
            
            // Check for required indexes
            $required_indexes = array(
                'reference_number' => 'reference_number',
            );
            
            foreach ( $required_indexes as $index_name => $column_name ) {
                $index_exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = %s 
                    AND TABLE_NAME = %s 
                    AND INDEX_NAME = %s",
                    DB_NAME,
                    $incomplete_table,
                    $index_name
                ) );
                
                // Convert to boolean - COUNT(*) returns string '0' or '1'
                $index_exists = (int) $index_exists > 0;
                
                if ( ! $index_exists ) {
                    $issues[] = array(
                        'type' => 'index',
                        'table' => 'bd_courier_incomplete_orders',
                        'index' => $index_name,
                        'column' => $column_name,
                        'issue' => 'Index ' . $index_name . ' on column ' . $column_name . ' is missing',
                        'status' => 'missing',
                    );
                    $all_ok = false;
                }
            }
        }

        // Check courier history table
        require_once dirname( __FILE__ ) . '/class.CourierHistory.php';
        $history_table = CourierHistory::get_table_name();
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$history_table'" ) === $history_table;
        
        if ( ! $table_exists ) {
            $issues[] = array(
                'type' => 'table',
                'table' => 'bd_courier_history',
                'issue' => 'Table does not exist',
                'status' => 'missing',
            );
            $all_ok = false;
        } else {
            // Define all required columns for history table
            $required_columns = array(
                'phone' => 'varchar(20)',
                'data' => 'longtext',
                'type' => 'varchar(20)',
                'rel_id' => 'bigint(20)',
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
            );
            
            // Check each required column
            foreach ( $required_columns as $column_name => $column_type ) {
                $column_exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = %s 
                    AND TABLE_NAME = %s 
                    AND COLUMN_NAME = %s",
                    DB_NAME,
                    $history_table,
                    $column_name
                ) );
                
                if ( ! $column_exists ) {
                    $issues[] = array(
                        'type' => 'column',
                        'table' => 'bd_courier_history',
                        'column' => $column_name,
                        'issue' => 'Column ' . $column_name . ' is missing',
                        'status' => 'missing',
                    );
                    $all_ok = false;
                }
            }
            
            // Check for required indexes
            $required_indexes = array(
                'phone' => 'phone',
                'type' => 'type',
                'rel_id' => 'rel_id',
                'created_at' => 'created_at',
            );
            
            foreach ( $required_indexes as $index_name => $column_name ) {
                $index_exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = %s 
                    AND TABLE_NAME = %s 
                    AND INDEX_NAME = %s",
                    DB_NAME,
                    $history_table,
                    $index_name
                ) );
                
                // Convert to boolean - COUNT(*) returns string '0' or '1'
                $index_exists = (int) $index_exists > 0;
                
                if ( ! $index_exists ) {
                    $issues[] = array(
                        'type' => 'index',
                        'table' => 'bd_courier_history',
                        'index' => $index_name,
                        'column' => $column_name,
                        'issue' => 'Index ' . $index_name . ' on column ' . $column_name . ' is missing',
                        'status' => 'missing',
                    );
                    $all_ok = false;
                }
            }
        }

        // Check rejected orders table
        require_once dirname( __FILE__ ) . '/class.CourierRejectedOrders.php';
        $rejected_table = $wpdb->prefix . 'bd_courier_rejected_orders';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$rejected_table'" ) === $rejected_table;
        
        if ( ! $table_exists ) {
            $issues[] = array(
                'type' => 'table',
                'table' => 'bd_courier_rejected_orders',
                'issue' => 'Table does not exist',
                'status' => 'missing',
            );
            $all_ok = false;
        } else {
            // Define all required columns for rejected orders table
            $required_columns = array(
                'original_order_id' => 'bigint(20)',
                'order_data' => 'longtext',
                'cart_data' => 'longtext',
                'customer_data' => 'longtext',
                'courier_data' => 'longtext',
                'success_ratio' => 'decimal(5,2)',
                'min_required_ratio' => 'decimal(5,2)',
                'rejection_reason' => 'text',
                'status' => 'varchar(20)',
                'converted_to_order_id' => 'bigint(20)',
            );
            
            // Check each required column
            foreach ( $required_columns as $column_name => $column_type ) {
                $column_exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = %s 
                    AND TABLE_NAME = %s 
                    AND COLUMN_NAME = %s",
                    DB_NAME,
                    $rejected_table,
                    $column_name
                ) );
                
                // Convert to boolean - COUNT(*) returns string '0' or '1'
                $column_exists = (int) $column_exists > 0;
                
                if ( ! $column_exists ) {
                    $issues[] = array(
                        'type' => 'column',
                        'table' => 'bd_courier_rejected_orders',
                        'column' => $column_name,
                        'issue' => 'Column ' . $column_name . ' is missing',
                        'status' => 'missing',
                    );
                    $all_ok = false;
                }
            }
        }

        // Check rejected order logs table
        require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';
        $rejected_logs_table = $wpdb->prefix . 'bd_courier_rejected_order_logs';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$rejected_logs_table'" ) === $rejected_logs_table;

        if ( ! $table_exists ) {
            $issues[] = array(
                'type' => 'table',
                'table' => 'bd_courier_rejected_order_logs',
                'issue' => 'Table does not exist',
                'status' => 'missing',
            );
            $all_ok = false;
        } else {
            // Define all required columns for rejected order logs table
            $required_columns = array(
                'order_id' => 'bigint(20)',
                'customer_email' => 'varchar(100)',
                'customer_phone' => 'varchar(20)',
                'customer_ip' => 'varchar(45)',
                'customer_device_fingerprint' => 'varchar(255)',
                'rejection_reason' => 'varchar(100)',
                'rejection_details' => 'text',
                'success_ratio' => 'decimal(5,2)',
                'min_required_ratio' => 'decimal(5,2)',
                'order_total' => 'decimal(10,2)',
                'order_currency' => 'varchar(3)',
                'cart_items_count' => 'int(11)',
                'payment_method' => 'varchar(100)',
                'user_agent' => 'text',
                'referrer_url' => 'text',
                'created_at' => 'datetime',
            );

            // Check each required column
            foreach ( $required_columns as $column_name => $column_type ) {
                $column_exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = %s
                    AND TABLE_NAME = %s
                    AND COLUMN_NAME = %s",
                    DB_NAME,
                    $rejected_logs_table,
                    $column_name
                ) );
                
                // Convert to boolean - COUNT(*) returns string '0' or '1'
                $column_exists = (int) $column_exists > 0;
                
                if ( ! $column_exists ) {
                    $issues[] = array(
                        'type' => 'column',
                        'table' => 'bd_courier_rejected_order_logs',
                        'column' => $column_name,
                        'issue' => 'Column ' . $column_name . ' is missing',
                        'status' => 'missing',
                    );
                    $all_ok = false;
                }
            }

            // Check for required indexes
            $required_indexes = array(
                'order_id' => 'order_id',
                'customer_email' => 'customer_email',
                'customer_phone' => 'customer_phone',
                'customer_ip' => 'customer_ip',
                'rejection_reason' => 'rejection_reason',
                'created_at' => 'created_at',
            );

            foreach ( $required_indexes as $index_name => $column_name ) {
                $index_exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = %s
                    AND TABLE_NAME = %s
                    AND INDEX_NAME = %s",
                    DB_NAME,
                    $rejected_logs_table,
                    $index_name
                ) );

                if ( ! $index_exists ) {
                    $issues[] = array(
                        'type' => 'index',
                        'table' => 'bd_courier_rejected_order_logs',
                        'index' => $index_name,
                        'column' => $column_name,
                        'issue' => 'Index ' . $index_name . ' on column ' . $column_name . ' is missing',
                        'status' => 'missing',
                    );
                    $all_ok = false;
                }
            }
        }

        // Clear admin notice transients if all is OK
        if ( $all_ok ) {
            delete_transient( 'bd_courier_db_issues' );
            delete_transient( 'bd_courier_db_check_time' );
        }
        
        return new WP_REST_Response( array(
            'success' => true,
            'all_ok' => $all_ok,
            'issues' => $issues,
            'issues_count' => count( $issues ),
        ), 200 );
    }

    /**
     * Update database structure (run migrations).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_database( $request ) {
        global $wpdb;
        
        $results = array();
        $success = true;

        // Update courier history table
        require_once dirname( __FILE__ ) . '/class.CourierHistory.php';
        try {
            CourierHistory::create_table();
            $results[] = array(
                'table' => 'bd_courier_history',
                'status' => 'updated',
                'message' => 'Table structure updated successfully',
            );
        } catch ( Exception $e ) {
            $success = false;
            $results[] = array(
                'table' => 'bd_courier_history',
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            );
        }

        // Update incomplete orders table
        require_once dirname( __FILE__ ) . '/class.CourierIncompleteOrders.php';
        $incomplete_orders = new CourierIncompleteOrders();
        
        try {
            // Run table creation if needed
            $incomplete_orders->maybe_create_table();
            
            // Run table updates
            $incomplete_orders->maybe_update_table();
            
            $results[] = array(
                'table' => 'bd_courier_incomplete_orders',
                'status' => 'updated',
                'message' => 'Table structure updated successfully',
            );
        } catch ( Exception $e ) {
            $success = false;
            $results[] = array(
                'table' => 'bd_courier_incomplete_orders',
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            );
        }

        // Update rejected orders table
        require_once dirname( __FILE__ ) . '/class.CourierRejectedOrders.php';
        $rejected_orders = new CourierRejectedOrders();

        try {
            $rejected_orders->maybe_create_table();

            $results[] = array(
                'table' => 'bd_courier_rejected_orders',
                'status' => 'updated',
                'message' => 'Table structure updated successfully',
            );
        } catch ( Exception $e ) {
            $success = false;
            $results[] = array(
                'table' => 'bd_courier_rejected_orders',
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            );
        }

        // Update rejected order logs table
        require_once dirname( __FILE__ ) . '/class.CourierRejectedOrderLogs.php';
        $rejected_logs = new CourierRejectedOrderLogs();

        try {
            $rejected_logs->maybe_create_table();

            $results[] = array(
                'table' => 'bd_courier_rejected_order_logs',
                'status' => 'updated',
                'message' => 'Table structure updated successfully',
            );
        } catch ( Exception $e ) {
            $success = false;
            $results[] = array(
                'table' => 'bd_courier_rejected_order_logs',
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            );
        }

        // Clear database check transients after update to force fresh check
        delete_transient( 'bd_courier_db_check_time' );
        delete_transient( 'bd_courier_db_issues' );
        
        return new WP_REST_Response( array(
            'success' => $success,
            'message' => $success ? 'Database updated successfully' : 'Some errors occurred during update',
            'results' => $results,
        ), $success ? 200 : 500 );
    }

    /**
     * Get migration status.
     */
    public function get_migration_status() {
        $status = CourierMigration::get_migration_status();
        $total_orders = CourierMigration::count_orders_to_migrate();
        
        return new WP_REST_Response( array(
            'status' => $status,
            'total_orders' => $total_orders,
        ), 200 );
    }

    /**
     * Migrate orders batch.
     */
    public function migrate_orders( $request ) {
        try {
            $params = $request->get_json_params();
            $batch_size = isset( $params['batch_size'] ) ? absint( $params['batch_size'] ) : 50;
            $offset = isset( $params['offset'] ) ? absint( $params['offset'] ) : 0;
            
            
            $result = CourierMigration::migrate_orders_batch( $batch_size, $offset );
            
            
            // Recalculate total remaining after migration
            $total_remaining = CourierMigration::count_orders_to_migrate();
            
            
            return new WP_REST_Response( array(
                'success' => true,
                'migrated' => $result['migrated'],
                'errors' => $result['errors'],
                'total_remaining' => $total_remaining,
                'has_more' => $total_remaining > 0,
                'status' => array(
                    'batch_completed' => true,
                    'orders_processed' => count( $result['orders_processed'] ),
                ),
            ), 200 );
        } catch ( Exception $e ) {
            
            return new WP_Error( 'migration_error', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * Complete migration.
     */
    public function complete_migration() {
        CourierMigration::complete_migration();
        
        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Migration completed successfully',
        ), 200 );
    }

    /**
     * Check database status and store issues in transient for admin notices.
     */
    public function check_database_admin_notice() {
        // Only check for admins and on admin pages
        if ( ! current_user_can( 'manage_options' ) || ! is_admin() ) {
            return;
        }

        // Skip check on settings page - let the React app handle it
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'bd-courier-settings' ) {
            return;
        }

        // Check only once per day to avoid performance issues
        $last_check = get_transient( 'bd_courier_db_check_time' );
        if ( $last_check && ( time() - $last_check ) < DAY_IN_SECONDS ) {
            return;
        }

        // Perform database check
        $check_result = $this->check_database( new WP_REST_Request( 'GET', '/bd-courier/v1/database-check' ) );

        if ( is_wp_error( $check_result ) ) {
            return;
        }

        $data = $check_result->get_data();

        // Store check time
        set_transient( 'bd_courier_db_check_time', time(), DAY_IN_SECONDS );

        // Store issues for admin notice
        if ( ! $data['all_ok'] && ! empty( $data['issues'] ) ) {
            set_transient( 'bd_courier_db_issues', $data['issues'], DAY_IN_SECONDS );
        } else {
            delete_transient( 'bd_courier_db_issues' );
        }
    }

    /**
     * Show admin notice for database issues.
     */
    public function show_database_admin_notice() {
        // Only show for admins
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $issues = get_transient( 'bd_courier_db_issues' );

        if ( empty( $issues ) ) {
            return;
        }

        $issues_count = count( $issues );

        // Create settings page URL with database tab hash
        $settings_url = admin_url( 'admin.php?page=bd-courier-settings#database' );

        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong>BD Courier Plugin:</strong>
                Database structure issues detected (<?php echo esc_html( $issues_count ); ?> issue<?php echo $issues_count !== 1 ? 's' : ''; ?>).
                <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary" style="margin-left: 10px;">
                    Fix Database Issues
                </a>
            </p>
            <p style="margin-top: 8px; margin-bottom: 0;">
                <small>
                    Go to <strong>BD Courier → Settings → Database & Migration</strong> to update your database structure.
                    This is required for the plugin to function properly.
                </small>
            </p>
        </div>
        <?php
    }
}
