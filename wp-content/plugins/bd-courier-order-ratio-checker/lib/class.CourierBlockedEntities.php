<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierBlockedEntities
 * Manages blocked entities (phone, IP, email, browser fingerprint) for courier fraud prevention
 */
class CourierBlockedEntities {

    /**
     * Table name for blocked entities.
     */
    private static $table_name = 'bd_courier_blocked_entities';

    /**
     * Block types.
     */
    const BLOCK_TYPE_PHONE = 'phone';
    const BLOCK_TYPE_IP = 'ip';
    const BLOCK_TYPE_EMAIL = 'email';
    const BLOCK_TYPE_FINGERPRINT = 'fingerprint';

    /**
     * Block durations in seconds.
     */
    const DURATION_1_HOUR = 3600;      // 1 hour
    const DURATION_6_HOURS = 21600;    // 6 hours
    const DURATION_24_HOURS = 86400;   // 24 hours
    const DURATION_7_DAYS = 604800;    // 7 days
    const DURATION_30_DAYS = 2592000;  // 30 days
    const DURATION_PERMANENT = null;   // Permanent block

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_create_table' ) );
        add_action( 'admin_menu', array( $this, 'add_blocked_entities_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_head', array( $this, 'hide_admin_notices' ) );

        // REST API endpoints
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // AJAX handlers
        add_action( 'wp_ajax_add_blocked_entity', array( $this, 'ajax_add_blocked_entity' ) );
        add_action( 'wp_ajax_remove_blocked_entity', array( $this, 'ajax_remove_blocked_entity' ) );
        add_action( 'wp_ajax_bulk_remove_blocked_entities', array( $this, 'ajax_bulk_remove_blocked_entities' ) );
        add_action( 'wp_ajax_bdc_validate_checkout_blocked_entities', array( $this, 'ajax_validate_checkout_blocked_entities' ) );
        add_action( 'wp_ajax_nopriv_bdc_validate_checkout_blocked_entities', array( $this, 'ajax_validate_checkout_blocked_entities' ) );

        // Checkout validation - Priority 1 (highest) to check blocklist FIRST before any other validations
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_blocked_entities' ), 1 );
        
        // Enqueue checkout scripts - support both WooCommerce and CartFlows
        add_action( 'woocommerce_before_checkout_form', array( $this, 'add_checkout_validation_scripts' ) );
        add_action( 'cartflows_checkout_before_form', array( $this, 'add_checkout_validation_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_checkout_scripts' ) );
        
        // Backup: Prevent order creation if somehow it gets past validation
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'prevent_blocked_order_creation' ), 1, 1 );
    }

    /**
     * Get table name with WordPress prefix.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    /**
     * Create the blocked entities table.
     */
    public function create_table() {
        $this->maybe_create_table();
    }

    /**
     * Create table if it doesn't exist.
     */
    public function maybe_create_table() {
        global $wpdb;

        $table_name = self::get_table_name();

        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
            return; // Table already exists
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type varchar(20) NOT NULL,
            entity_value text NOT NULL,
            blocked_by bigint(20) UNSIGNED NOT NULL,
            blocked_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NULL,
            is_permanent tinyint(1) DEFAULT 0,
            block_reason text DEFAULT NULL,
            unblocked_by bigint(20) UNSIGNED NULL,
            unblocked_at datetime NULL,
            PRIMARY KEY (id),
            KEY entity_type_value (entity_type(10), entity_value(100)),
            KEY expires_at (expires_at),
            KEY blocked_at (blocked_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Block an entity.
     *
     * @param string $entity_type Type of entity (phone, ip, email, fingerprint)
     * @param string $entity_value Value to block
     * @param int|null $duration Duration in seconds, null for permanent
     * @param string|null $reason Reason for blocking
     * @return int|false Block ID or false on failure
     */
    public static function block_entity( $entity_type, $entity_value, $duration = null, $reason = null ) {
        global $wpdb;

        $table_name = self::get_table_name();

        // Ensure table exists
        $instance = new self();
        $instance->maybe_create_table();

        $current_user_id = get_current_user_id();
        $now = current_time( 'mysql' );

        $data = array(
            'entity_type' => $entity_type,
            'entity_value' => $entity_value,
            'blocked_by' => $current_user_id,
            'blocked_at' => $now,
            'is_permanent' => $duration === null ? 1 : 0,
            'block_reason' => $reason,
        );

        if ( $duration !== null ) {
            $data['expires_at'] = date( 'Y-m-d H:i:s', strtotime( $now ) + $duration );
        }

        $result = $wpdb->insert( $table_name, $data );

        if ( $result === false ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Unblock an entity.
     *
     * @param string $entity_type Type of entity
     * @param string $entity_value Value to unblock
     * @return bool True on success, false on failure
     */
    public static function unblock_entity( $entity_type, $entity_value ) {
        global $wpdb;

        $table_name = self::get_table_name();
        $current_user_id = get_current_user_id();
        $now = current_time( 'mysql' );

        $result = $wpdb->update(
            $table_name,
            array(
                'unblocked_by' => $current_user_id,
                'unblocked_at' => $now,
            ),
            array(
                'entity_type' => $entity_type,
                'entity_value' => $entity_value,
                'unblocked_at' => null, // Only update active blocks
            ),
            array( '%d', '%s' ),
            array( '%s', '%s', '%s' )
        );

        if ( $result === false ) {
            return false;
        }

        return true;
    }

    /**
     * Check if an entity is blocked.
     *
     * @param string $entity_type Type of entity
     * @param string $entity_value Value to check
     * @return bool True if blocked, false otherwise
     */
    public static function is_entity_blocked( $entity_type, $entity_value ) {
        global $wpdb;

        // Ensure table exists
        $instance = new self();
        $instance->maybe_create_table();

        $table_name = self::get_table_name();

        // Trim and sanitize entity value
        $entity_value = trim( $entity_value );
        
        if ( empty( $entity_value ) ) {
            return false;
        }

        // First, try exact match
        $query = $wpdb->prepare(
            "SELECT id FROM $table_name
            WHERE entity_type = %s
            AND TRIM(entity_value) = %s
            AND unblocked_at IS NULL
            AND (expires_at IS NULL OR expires_at > NOW())",
            $entity_type,
            $entity_value
        );


        $result = $wpdb->get_var( $query );

        if ( $wpdb->last_error ) {
        }

        // If no exact match found, try with LIKE to catch any whitespace issues
        if ( empty( $result ) ) {
            $query_like = $wpdb->prepare(
                "SELECT id FROM $table_name
                WHERE entity_type = %s
                AND entity_value LIKE %s
                AND unblocked_at IS NULL
                AND (expires_at IS NULL OR expires_at > NOW())",
                $entity_type,
                '%' . $wpdb->esc_like( $entity_value ) . '%'
            );
            
            $result = $wpdb->get_var( $query_like );
            
            if ( ! empty( $result ) ) {
            }
        }

        $is_blocked = ! empty( $result );

        // Debug: Show all blocked entries for this type
        $all_blocked = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, entity_value, unblocked_at, expires_at, expires_at > NOW() as is_expired FROM $table_name
            WHERE entity_type = %s
            AND unblocked_at IS NULL
            ORDER BY blocked_at DESC
            LIMIT 10",
            $entity_type
        ), ARRAY_A );
        
        if ( ! empty( $all_blocked ) ) {
            
            // Check if our value matches any of them (for debugging)
            foreach ( $all_blocked as $blocked_entry ) {
                $stored_value = trim( $blocked_entry['entity_value'] );
                if ( $stored_value === $entity_value ) {
                }
            }
        } else {
        }

        return $is_blocked;
    }

    /**
     * Get all blocked entities with pagination.
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_blocked_entities( $args = array() ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'entity_type' => '',
            'search' => '',
            'status' => 'active', // active, expired, all
        );

        $args = wp_parse_args( $args, $defaults );

        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        // Build WHERE clause
        $where = array();
        $where_values = array();

        // Entity type filter
        if ( ! empty( $args['entity_type'] ) ) {
            $where[] = 'entity_type = %s';
            $where_values[] = $args['entity_type'];
        }

        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $where[] = 'entity_value LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        // Status filter
        if ( $args['status'] === 'active' ) {
            $where[] = 'unblocked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())';
        } elseif ( $args['status'] === 'expired' ) {
            $where[] = 'unblocked_at IS NULL AND expires_at IS NOT NULL AND expires_at <= NOW()';
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table_name $where_clause";
        if ( ! empty( $where_values ) ) {
            $total_count = $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) );
        } else {
            $total_count = $wpdb->get_var( $count_query );
        }

        // Get entities
        $query = "SELECT * FROM $table_name $where_clause ORDER BY blocked_at DESC LIMIT %d OFFSET %d";
        $values = array_merge( $where_values, array( $args['per_page'], $offset ) );

        if ( ! empty( $where_values ) ) {
            $entities = $wpdb->get_results( $wpdb->prepare( $query, $values ), ARRAY_A );
        } else {
            $entities = $wpdb->get_results( $wpdb->prepare( $query, array( $args['per_page'], $offset ) ), ARRAY_A );
        }

        // Format entities
        $formatted_entities = array();
        foreach ( $entities as $entity ) {
            $formatted_entities[] = array(
                'id' => (int) $entity['id'],
                'entity_type' => $entity['entity_type'],
                'entity_value' => $entity['entity_value'],
                'blocked_by' => (int) $entity['blocked_by'],
                'blocked_at' => $entity['blocked_at'],
                'blocked_at_formatted' => mysql2date( 'M j, Y g:i A', $entity['blocked_at'] ),
                'expires_at' => $entity['expires_at'],
                'expires_at_formatted' => $entity['expires_at'] ? mysql2date( 'M j, Y g:i A', $entity['expires_at'] ) : null,
                'is_permanent' => (bool) $entity['is_permanent'],
                'block_reason' => $entity['block_reason'],
                'unblocked_by' => $entity['unblocked_by'] ? (int) $entity['unblocked_by'] : null,
                'unblocked_at' => $entity['unblocked_at'],
                'unblocked_at_formatted' => $entity['unblocked_at'] ? mysql2date( 'M j, Y g:i A', $entity['unblocked_at'] ) : null,
                'is_expired' => $entity['expires_at'] && strtotime( $entity['expires_at'] ) <= time(),
                'is_active' => is_null( $entity['unblocked_at'] ) && ( is_null( $entity['expires_at'] ) || strtotime( $entity['expires_at'] ) > time() ),
            );
        }

        return array(
            'entities' => $formatted_entities,
            'total_count' => (int) $total_count,
            'total_pages' => ceil( $total_count / $args['per_page'] ),
            'current_page' => (int) $args['page'],
            'per_page' => (int) $args['per_page'],
        );
    }

    /**
     * Add blocked entities submenu.
     */
    public function add_blocked_entities_submenu() {
        add_submenu_page(
            'woocommerce',
            __( 'Blocked Entities', 'bd-courier-order-ratio-checker' ),
            __( 'Blocked Entities', 'bd-courier-order-ratio-checker' ),
            'manage_woocommerce',
            'bd-courier-blocked-entities',
            array( $this, 'render_blocked_entities_page' )
        );
    }

    /**
     * Render the blocked entities page.
     */
    public function render_blocked_entities_page() {
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        $auth_status = CourierAPI::get_authorization_status();
        
        if ( ! $auth_status['authorized'] ) {
            $is_free = $auth_status['is_free'];
            $plan_name = ! empty( $auth_status['plan_name'] ) ? $auth_status['plan_name'] : '';
            ?>
            <style>
                .bdc-access-denied-container {
                    max-width: 600px;
                    margin: 40px auto;
                    padding: 40px;
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                    text-align: center;
                }
                .bdc-access-denied-icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 24px;
                    background: <?php echo $is_free ? '#f0f6fc' : '#f0f0f1'; ?>;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 40px;
                    color: <?php echo $is_free ? '#2271b1' : '#d63638'; ?>;
                }
                .bdc-access-denied-icon svg {
                    width: 48px;
                    height: 48px;
                    fill: currentColor;
                }
                .bdc-access-denied-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: #1d2327;
                    margin: 0 0 12px;
                    line-height: 1.4;
                }
                .bdc-access-denied-message {
                    font-size: 15px;
                    color: #646970;
                    margin: 0 0 24px;
                    line-height: 1.6;
                }
                .bdc-access-denied-actions {
                    margin-top: 32px;
                    padding-top: 24px;
                    border-top: 1px solid #dcdcde;
                }
                .bdc-access-denied-actions p {
                    margin: 0 0 12px;
                    font-size: 14px;
                    color: #646970;
                }
                .bdc-access-denied-actions a {
                    display: inline-block;
                    padding: 10px 20px;
                    background: <?php echo $is_free ? '#2271b1' : '#2271b1'; ?>;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 4px;
                    font-weight: 500;
                    transition: background 0.2s;
                    margin: 4px;
                }
                .bdc-access-denied-actions a:hover {
                    background: #135e96;
                    color: #fff;
                }
                .bdc-access-denied-actions a.secondary {
                    background: #f6f7f7;
                    color: #1d2327;
                    border: 1px solid #dcdcde;
                }
                .bdc-access-denied-actions a.secondary:hover {
                    background: #f0f0f1;
                    color: #1d2327;
                }
                .bdc-access-denied-site-info {
                    margin-top: 24px;
                    padding: 16px;
                    background: #f6f7f7;
                    border-radius: 4px;
                    font-size: 13px;
                    color: #646970;
                }
                .bdc-access-denied-site-info strong {
                    color: #1d2327;
                    display: block;
                    margin-bottom: 4px;
                }
                .bdc-plan-badge {
                    display: inline-block;
                    padding: 4px 12px;
                    background: <?php echo $is_free ? '#f0f6fc' : '#f0f0f1'; ?>;
                    color: <?php echo $is_free ? '#2271b1' : '#646970'; ?>;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 500;
                    margin-bottom: 16px;
                }
            </style>
            <div class="wrap">
                <div class="bdc-access-denied-container">
                    <div class="bdc-access-denied-icon">
                        <?php if ( $is_free ) : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        <?php else : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <?php if ( $is_free && ! empty( $plan_name ) ) : ?>
                        <div class="bdc-plan-badge"><?php echo esc_html( $plan_name ); ?> <?php esc_html_e( 'Plan', 'bd-courier-order-ratio-checker' ); ?></div>
                    <?php endif; ?>
                    <h2 class="bdc-access-denied-title">
                        <?php echo $is_free ? esc_html__( 'Upgrade Required', 'bd-courier-order-ratio-checker' ) : esc_html__( 'Access Denied', 'bd-courier-order-ratio-checker' ); ?>
                    </h2>
                    <p class="bdc-access-denied-message">
                        <?php if ( $is_free ) : ?>
                            <?php esc_html_e( 'This feature is available in paid plans only. Upgrade to a paid plan to access Fraud Blocker and other extended features.', 'bd-courier-order-ratio-checker' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'This feature is only available for authorized WordPress sites. Login to your bdcourier account and authorize your website from the incomplete order section.', 'bd-courier-order-ratio-checker' ); ?>
                        <?php endif; ?>
                    </p>
                    <div class="bdc-access-denied-site-info">
                        <strong><?php esc_html_e( 'Current Site URL:', 'bd-courier-order-ratio-checker' ); ?></strong>
                        <span><?php echo esc_html( home_url( '/' ) ); ?></span>
                    </div>
                    <div class="bdc-access-denied-actions">
                        <?php if ( $is_free ) : ?>
                            <p><?php esc_html_e( 'Upgrade your plan to unlock this feature.', 'bd-courier-order-ratio-checker' ); ?></p>
                            <a href="https://bdcourier.com/pricing" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e( 'View Pricing Plans', 'bd-courier-order-ratio-checker' ); ?>
                            </a>
                            <a href="https://bdcourier.com/support" target="_blank" rel="noopener noreferrer" class="secondary">
                                <?php esc_html_e( 'Contact Support', 'bd-courier-order-ratio-checker' ); ?>
                            </a>
                        <?php else : ?>
                            <p><?php esc_html_e( 'Login to your bdcourier account and authorize your website from the incomplete order section.', 'bd-courier-order-ratio-checker' ); ?></p>
                            <a href="https://bdcourier.com/login" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e( 'Login to bdcourier', 'bd-courier-order-ratio-checker' ); ?>
                            </a>
                            <a href="https://bdcourier.com/support" target="_blank" rel="noopener noreferrer" class="secondary">
                                <?php esc_html_e( 'Contact Support', 'bd-courier-order-ratio-checker' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div id="bd-courier-blocked-entities-root"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets for blocked entities page.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our specific page
        if ( $hook !== 'woocommerce_page_bd-courier-blocked-entities' ) {
            return;
        }

        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return; // Don't load scripts if not authorized
        }

        // Get plugin root directory and main file path
        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';

        // Enqueue standalone CSS from build
        $css_file = $plugin_dir . '/assets/js/style.css';
        if ( file_exists( $css_file ) ) {
            $css_url = plugins_url( 'assets/js/style.css', $plugin_main_file );
            $css_url = add_query_arg( 'v', BD_COURIER_VERSION, $css_url );

            wp_enqueue_style(
                'bd-courier-blocked-entities-css',
                $css_url,
                [],
                BD_COURIER_VERSION
            );
        }

        // Also enqueue custom CSS for additional styles
        $custom_css_file = $plugin_dir . '/assets/css/rejected-logs.css';
        if ( file_exists( $custom_css_file ) ) {
            $custom_css_url = plugins_url( 'assets/css/rejected-logs.css', $plugin_main_file );
            $custom_css_url = add_query_arg( 'v', BD_COURIER_VERSION, $custom_css_url );

            wp_enqueue_style(
                'bd-courier-blocked-entities-custom-css',
                $custom_css_url,
                ['bd-courier-blocked-entities-css'],
                BD_COURIER_VERSION
            );
        }

        // Enqueue standalone JavaScript
        $js_file = $plugin_dir . '/assets/js/rejected-logs-standalone.js';
        if ( file_exists( $js_file ) ) {
            $js_url = plugins_url( 'assets/js/rejected-logs-standalone.js', $plugin_main_file );
            $js_url = add_query_arg( 'v', BD_COURIER_VERSION, $js_url );

            wp_enqueue_script(
                'bd-courier-blocked-entities-js',
                $js_url,
                [],
                BD_COURIER_VERSION,
                true
            );

            // Localize script
            wp_localize_script( 'bd-courier-blocked-entities-js', 'bdcourierBlockedEntitiesAjax', [
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            ]);
        }
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route( 'bd-courier/v1', '/blocked-entities', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_blocked_entities_api' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'page'       => array(
                        'default'           => 1,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param > 0;
                        },
                    ),
                    'per_page'   => array(
                        'default'           => 20,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param > 0 && $param <= 100;
                        },
                    ),
                    'entity_type' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'search'     => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'status'     => array(
                        'default'           => 'active',
                        'validate_callback' => function( $param ) {
                            return in_array( $param, array( 'active', 'expired', 'all' ) );
                        },
                    ),
                ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'add_blocked_entity_api' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'entity_type' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return in_array( $param, array( self::BLOCK_TYPE_PHONE, self::BLOCK_TYPE_IP, self::BLOCK_TYPE_EMAIL, self::BLOCK_TYPE_FINGERPRINT ) );
                        },
                    ),
                    'entity_value' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'duration'     => array(
                        'default'           => null,
                        'validate_callback' => function( $param ) {
                            return $param === null || ( is_numeric( $param ) && $param > 0 );
                        },
                    ),
                    'reason'       => array(
                        'default'           => null,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ) );

        register_rest_route( 'bd-courier/v1', '/blocked-entities/(?P<id>\d+)', array(
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'remove_blocked_entity_api' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            ),
        ) );

        register_rest_route( 'bd-courier/v1', '/blocked-entities/bulk-remove', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'bulk_remove_blocked_entities_api' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args'                => array(
                'ids' => array(
                    'required'          => true,
                    'validate_callback' => function( $param ) {
                        return is_array( $param ) && ! empty( $param );
                    },
                ),
            ),
        ) );

        register_rest_route( 'bd-courier/v1', '/blocked-entities/validate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'validate_blocked_entities_api' ),
            'permission_callback' => '__return_true', // Allow public access for checkout validation
            'args'                => array(
                'phone'       => array(
                    'default'           => null,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'ip'          => array(
                    'default'           => null,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'email'       => array(
                    'default'           => null,
                    'sanitize_callback' => 'sanitize_email',
                ),
                'fingerprint' => array(
                    'default'           => null,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Check if user has permission to access the API.
     */
    public function check_permissions() {
        // Check if user can manage WooCommerce
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return false;
        }
        
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return false;
        }
        
        return true;
    }

    /**
     * Get blocked entities via REST API.
     */
    public function get_blocked_entities_api( $request ) {
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return new WP_Error( 'site_not_authorized', 'This feature is only available for authorized WordPress sites. Please contact support to add your site to the authorized list.', array( 'status' => 403 ) );
        }

        $args = array(
            'page'        => $request->get_param( 'page' ),
            'per_page'    => $request->get_param( 'per_page' ),
            'entity_type' => $request->get_param( 'entity_type' ),
            'search'      => $request->get_param( 'search' ),
            'status'      => $request->get_param( 'status' ),
        );

        $result = $this->get_blocked_entities( $args );

        return new WP_REST_Response( $result, 200 );
    }

    /**
     * Add blocked entity via REST API.
     */
    public function add_blocked_entity_api( $request ) {
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return new WP_Error( 'site_not_authorized', 'This feature is only available for authorized WordPress sites. Please contact support to add your site to the authorized list.', array( 'status' => 403 ) );
        }

        $entity_type = $request->get_param( 'entity_type' );
        $entity_value = $request->get_param( 'entity_value' );
        $duration = $request->get_param( 'duration' );
        $reason = $request->get_param( 'reason' );

        $result = self::block_entity( $entity_type, $entity_value, $duration, $reason );

        if ( $result === false ) {
            return new WP_Error( 'block_failed', 'Failed to block entity', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'id' => $result,
            'message' => 'Entity blocked successfully'
        ), 201 );
    }

    /**
     * Remove blocked entity via REST API.
     */
    public function remove_blocked_entity_api( $request ) {
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return new WP_Error( 'site_not_authorized', 'This feature is only available for authorized WordPress sites. Please contact support to add your site to the authorized list.', array( 'status' => 403 ) );
        }

        $id = $request->get_param( 'id' );

        global $wpdb;
        $table_name = self::get_table_name();

        $result = $wpdb->update(
            $table_name,
            array(
                'unblocked_by' => get_current_user_id(),
                'unblocked_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            return new WP_Error( 'unblock_failed', 'Failed to unblock entity', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Entity unblocked successfully' ), 200 );
    }

    /**
     * Bulk remove blocked entities via REST API.
     */
    public function bulk_remove_blocked_entities_api( $request ) {
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return new WP_Error( 'site_not_authorized', 'This feature is only available for authorized WordPress sites. Please contact support to add your site to the authorized list.', array( 'status' => 403 ) );
        }

        $ids = $request->get_param( 'ids' );

        if ( ! is_array( $ids ) || empty( $ids ) ) {
            return new WP_Error( 'invalid_ids', 'Invalid IDs provided', array( 'status' => 400 ) );
        }

        global $wpdb;
        $table_name = self::get_table_name();

        $current_user_id = get_current_user_id();
        $now = current_time( 'mysql' );

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $query = "UPDATE $table_name SET unblocked_by = %d, unblocked_at = %s WHERE id IN ($placeholders)";

        $values = array_merge( array( $current_user_id, $now ), $ids );

        $result = $wpdb->query( $wpdb->prepare( $query, $values ) );

        if ( $result === false ) {
            return new WP_Error( 'bulk_unblock_failed', 'Failed to unblock entities', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'unblocked_count' => $result,
            'message' => "$result entities unblocked successfully"
        ), 200 );
    }

    /**
     * Validate blocked entities via REST API.
     */
    public function validate_blocked_entities_api( $request ) {
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            // Return not blocked if site is not authorized
            // This allows frontend to continue without interruption
            return new WP_REST_Response( array(
                'is_blocked' => false,
                'blocked_entities' => array(),
            ), 200 );
        }

        $phone = $request->get_param( 'phone' );
        $ip = $request->get_param( 'ip' );
        $email = $request->get_param( 'email' );
        $fingerprint = $request->get_param( 'fingerprint' );

        $blocked_entities = array();

        if ( $phone && self::is_entity_blocked( self::BLOCK_TYPE_PHONE, $phone ) ) {
            $blocked_entities[] = array(
                'type' => self::BLOCK_TYPE_PHONE,
                'value' => $phone,
            );
        }

        if ( $ip && self::is_entity_blocked( self::BLOCK_TYPE_IP, $ip ) ) {
            $blocked_entities[] = array(
                'type' => self::BLOCK_TYPE_IP,
                'value' => $ip,
            );
        }

        if ( $email && self::is_entity_blocked( self::BLOCK_TYPE_EMAIL, $email ) ) {
            $blocked_entities[] = array(
                'type' => self::BLOCK_TYPE_EMAIL,
                'value' => $email,
            );
        }

        if ( $fingerprint && self::is_entity_blocked( self::BLOCK_TYPE_FINGERPRINT, $fingerprint ) ) {
            $blocked_entities[] = array(
                'type' => self::BLOCK_TYPE_FINGERPRINT,
                'value' => $fingerprint,
            );
        }

        return new WP_REST_Response( array(
            'is_blocked' => ! empty( $blocked_entities ),
            'blocked_entities' => $blocked_entities,
        ), 200 );
    }

    /**
     * AJAX handler for validating blocked entities during checkout.
     */
    public function ajax_validate_checkout_blocked_entities() {
        
        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            // Return success (not blocked) if site is not authorized
            // This allows frontend to continue without interruption
            wp_send_json_success( array(
                'is_blocked' => false,
                'blocked_entities' => array(),
            ) );
            return;
        }

        // Basic rate limiting check
        $this->check_rate_limit();

        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
        $ip = isset( $_POST['ip'] ) ? sanitize_text_field( $_POST['ip'] ) : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( $_POST['fingerprint'] ) : '';

        // If IP not provided, try to get it from server
        if ( empty( $ip ) ) {
            $ip = $this->get_client_ip();
        }


        $blocked_entities = array();

        if ( $phone && self::is_entity_blocked( self::BLOCK_TYPE_PHONE, $phone ) ) {
            $blocked_entities[] = array(
                'type' => self::BLOCK_TYPE_PHONE,
                'value' => $phone,
            );
        }

        if ( $ip && self::is_entity_blocked( self::BLOCK_TYPE_IP, $ip ) ) {
            $blocked_entities[] = array(
                'type' => self::BLOCK_TYPE_IP,
                'value' => $ip,
            );
        }

        if ( $email && self::is_entity_blocked( self::BLOCK_TYPE_EMAIL, $email ) ) {
            $blocked_entities[] = array(
                'type' => self::BLOCK_TYPE_EMAIL,
                'value' => $email,
            );
        }

        if ( $fingerprint && self::is_entity_blocked( self::BLOCK_TYPE_FINGERPRINT, $fingerprint ) ) {
            $blocked_entities[] = array(
                'type' => self::BLOCK_TYPE_FINGERPRINT,
                'value' => $fingerprint,
            );
        }

        $is_blocked = ! empty( $blocked_entities );

        wp_send_json_success( array(
            'is_blocked' => $is_blocked,
            'blocked_entities' => $blocked_entities,
        ) );
    }

    /**
     * Basic rate limiting for checkout validation.
     */
    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $transient_key = 'bdc_blocked_validation_rate_' . md5( $ip );

        $attempts = get_transient( $transient_key );
        if ( $attempts === false ) {
            $attempts = 0;
        }

        // Allow max 10 requests per minute
        if ( $attempts >= 10 ) {
            wp_send_json_error( 'Rate limit exceeded. Please try again later.' );
            exit;
        }

        set_transient( $transient_key, $attempts + 1, 60 ); // 1 minute
    }

    /**
     * AJAX handler for adding blocked entity.
     */
    public function ajax_add_blocked_entity() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $entity_type = isset( $_POST['entity_type'] ) ? sanitize_text_field( $_POST['entity_type'] ) : '';
        $entity_value = isset( $_POST['entity_value'] ) ? sanitize_text_field( $_POST['entity_value'] ) : '';
        $duration = isset( $_POST['duration'] ) && $_POST['duration'] !== '' ? intval( $_POST['duration'] ) : null;
        $reason = isset( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : '';

        if ( empty( $entity_type ) || empty( $entity_value ) ) {
            wp_send_json_error( 'Entity type and value are required' );
        }

        $result = self::block_entity( $entity_type, $entity_value, $duration, $reason );

        if ( $result === false ) {
            wp_send_json_error( 'Failed to block entity' );
        } else {
            wp_send_json_success( array( 'id' => $result, 'message' => 'Entity blocked successfully' ) );
        }
    }

    /**
     * AJAX handler for removing blocked entity.
     */
    public function ajax_remove_blocked_entity() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( 'Invalid entity ID' );
        }

        global $wpdb;
        $table_name = self::get_table_name();

        $result = $wpdb->update(
            $table_name,
            array(
                'unblocked_by' => get_current_user_id(),
                'unblocked_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            wp_send_json_error( 'Failed to unblock entity' );
        } else {
            wp_send_json_success( array( 'message' => 'Entity unblocked successfully' ) );
        }
    }

    /**
     * AJAX handler for bulk removing blocked entities.
     */
    public function ajax_bulk_remove_blocked_entities() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $ids = isset( $_POST['ids'] ) ? $_POST['ids'] : array();

        if ( ! is_array( $ids ) || empty( $ids ) ) {
            wp_send_json_error( 'Invalid IDs provided' );
        }

        global $wpdb;
        $table_name = self::get_table_name();

        $current_user_id = get_current_user_id();
        $now = current_time( 'mysql' );

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $query = "UPDATE $table_name SET unblocked_by = %d, unblocked_at = %s WHERE id IN ($placeholders)";

        $values = array_merge( array( $current_user_id, $now ), array_map( 'intval', $ids ) );

        $result = $wpdb->query( $wpdb->prepare( $query, $values ) );

        if ( $result === false ) {
            wp_send_json_error( 'Failed to unblock entities' );
        } else {
            wp_send_json_success( array( 'unblocked_count' => $result, 'message' => "$result entities unblocked successfully" ) );
        }
    }

    /**
     * Validate blocked entities during checkout.
     */
    public function validate_checkout_blocked_entities() {
        
        // Skip validation for logged-in users with manage_woocommerce capability
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Check if site is authorized for extended features
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return;
        }
        

        $phone_raw = isset( $_POST['billing_phone'] ) ? sanitize_text_field( $_POST['billing_phone'] ) : '';
        $email = isset( $_POST['billing_email'] ) ? sanitize_email( $_POST['billing_email'] ) : '';
        $ip = $this->get_client_ip();
        $fingerprint = isset( $_POST['bdc_fingerprint'] ) ? sanitize_text_field( $_POST['bdc_fingerprint'] ) : '';


        // Clean phone number - remove non-numeric characters
        $phone = preg_replace( '/[^0-9]/', '', $phone_raw );

        $blocked_entities = array();
        $blocked_types = array();

        // Check phone number (priority check - should be checked first)
        if ( $phone && self::is_entity_blocked( self::BLOCK_TYPE_PHONE, $phone ) ) {
            $blocked_entities[] = 'Phone number';
            $blocked_types[] = self::BLOCK_TYPE_PHONE;
        }

        // Check IP address (including localhost/private IPs for blocking)
        if ( ! empty( $ip ) ) {
            $ip_trimmed = trim( $ip );
            
            $is_ip_blocked = self::is_entity_blocked( self::BLOCK_TYPE_IP, $ip_trimmed );
            
            if ( $is_ip_blocked ) {
            $blocked_entities[] = 'IP address';
            $blocked_types[] = self::BLOCK_TYPE_IP;
            } else {
            }
        } else {
        }

        // Check email address
        if ( $email && self::is_entity_blocked( self::BLOCK_TYPE_EMAIL, $email ) ) {
            $blocked_entities[] = 'Email address';
            $blocked_types[] = self::BLOCK_TYPE_EMAIL;
        }

        // Check browser fingerprint
        if ( $fingerprint && self::is_entity_blocked( self::BLOCK_TYPE_FINGERPRINT, $fingerprint ) ) {
            $blocked_entities[] = 'Browser fingerprint';
            $blocked_types[] = self::BLOCK_TYPE_FINGERPRINT;
        }

        if ( ! empty( $blocked_entities ) ) {
            // Get order total if available
            $order_total = 0;
            $cart_total = WC()->cart ? WC()->cart->get_total( 'edit' ) : 0;
            if ( $cart_total ) {
                $order_total = floatval( $cart_total );
            }

            // Get cart items count
            $cart_items_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;

            // Get payment method
            $payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( $_POST['payment_method'] ) : '';

            // Get user agent and referrer
            $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
            $referrer_url = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '';

            // Log rejection to rejected order logs
            if ( class_exists( 'CourierRejectedOrderLogs' ) ) {
                $rejection_details = sprintf(
                    'Order rejected due to blocked entity check. Blocked items: %s. This is a security restriction.',
                    implode( ', ', $blocked_entities )
                );

                CourierRejectedOrderLogs::log_rejection( array(
                    'order_id' => null,
                    'customer_email' => $email,
                    'customer_phone' => $phone,
                    'customer_ip' => $ip,
                    'customer_device_fingerprint' => $fingerprint,
                    'rejection_reason' => 'blocked-entity-checkout',
                    'rejection_details' => $rejection_details,
                    'order_total' => $order_total,
                    'order_currency' => get_woocommerce_currency(),
                    'cart_items_count' => $cart_items_count,
                    'payment_method' => $payment_method,
                    'user_agent' => $user_agent,
                    'referrer_url' => $referrer_url,
                ) );
            }

            // Build detailed error message with specific blocked items
            $blocked_items_list = '';
            foreach ( $blocked_entities as $index => $entity ) {
                if ( $index > 0 ) {
                    $blocked_items_list .= ', ';
                }
                $blocked_items_list .= $entity;
            }
            
            // Get customizable error message
            $custom_message = get_option( 'bdc_error_blocked_entities_detailed', 'Order Blocked: Your order cannot be processed due to security restrictions. The following items are blocked: %s. Please contact support if you believe this is an error.' );
            $error_message = sprintf( $custom_message, $blocked_items_list );
            
            
            // Add error notice - WooCommerce will automatically stop checkout processing when error notices exist
            // Using a unique ID to prevent duplicate notices
            $notice_id = 'bdc_blocked_entity_' . md5( implode( '_', $blocked_types ) );
            wc_add_notice( $error_message, 'error', array( 'id' => $notice_id ) );
            
            // Verify notice was added
            $notices = wc_get_notices( 'error' );
            
            // Also set a flag in session to prevent order creation
            if ( function_exists( 'WC' ) && WC()->session ) {
                WC()->session->set( 'bdc_order_blocked', true );
                WC()->session->set( 'bdc_blocked_reason', $error_message );
            }
            
            // Prevent order processing
            // WooCommerce checks for error notices and stops processing automatically
            // We also set a session flag as backup
            return;
        }
    }

    /**
     * Backup check: Prevent order creation if blocked entity was detected.
     * This runs after order processing as a safety net.
     *
     * @param int $order_id Order ID.
     */
    public function prevent_blocked_order_creation( $order_id ) {
        // Check if site is authorized
        require_once dirname( __FILE__ ) . '/class.CourierAPI.php';
        if ( ! CourierAPI::is_site_authorized() ) {
            return;
        }

        // Check session flag
        if ( function_exists( 'WC' ) && WC()->session && WC()->session->get( 'bdc_order_blocked' ) ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                
                // Cancel the order
                $order->update_status( 'cancelled', 'Order cancelled: Blocked entity detected during checkout validation.' );
                
                // Clear session flag
                WC()->session->__unset( 'bdc_order_blocked' );
                WC()->session->__unset( 'bdc_blocked_reason' );
            }
            return;
        }

        // Double-check: Verify the order's IP/phone/email against blocked entities
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $phone = $order->get_billing_phone();
        $email = $order->get_billing_email();
        $ip = $order->get_meta( '_bdc_client_ip' );
        
        if ( empty( $ip ) ) {
            $ip = $this->get_client_ip();
        }

        $blocked = false;
        $blocked_reason = '';

        if ( ! empty( $phone ) ) {
            $phone_clean = preg_replace( '/[^0-9]/', '', $phone );
            if ( self::is_entity_blocked( self::BLOCK_TYPE_PHONE, $phone_clean ) ) {
                $blocked = true;
                $blocked_reason = 'Phone number is blocked';
            }
        }

        if ( ! $blocked && ! empty( $ip ) ) {
            if ( self::is_entity_blocked( self::BLOCK_TYPE_IP, trim( $ip ) ) ) {
                $blocked = true;
                $blocked_reason = 'IP address is blocked';
            }
        }

        if ( ! $blocked && ! empty( $email ) ) {
            if ( self::is_entity_blocked( self::BLOCK_TYPE_EMAIL, $email ) ) {
                $blocked = true;
                $blocked_reason = 'Email address is blocked';
            }
        }

        if ( $blocked ) {
            $order->update_status( 'cancelled', 'Order cancelled: ' . $blocked_reason . '.' );
            
            // Add order note
            $order->add_order_note( 'Order automatically cancelled: ' . $blocked_reason . ' (IP: ' . $ip . ', Phone: ' . substr( $phone, 0, 5 ) . '..., Email: ' . substr( $email, 0, 5 ) . '...)' );
        }
    }

    /**
     * Add checkout validation scripts.
     */
    public function add_checkout_validation_scripts() {
        // Skip for logged-in users with manage_woocommerce capability
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Prevent duplicate enqueues
        static $scripts_enqueued = false;
        if ( $scripts_enqueued ) {
            return;
        }
        $scripts_enqueued = true;

        $plugin_dir = dirname( dirname( __FILE__ ) );
        $plugin_main_file = $plugin_dir . '/bd-courier-order-ratio-checker.php';

        // Enqueue SweetAlert2 for popup errors
        $sweetalert_file = $plugin_dir . '/assets/js/sweetalert2.all.min.js';
        if ( file_exists( $sweetalert_file ) ) {
            $sweetalert_url = plugins_url( 'assets/js/sweetalert2.all.min.js', $plugin_main_file );
            $sweetalert_url = add_query_arg( 'v', BD_COURIER_VERSION, $sweetalert_url );
            wp_enqueue_script(
                'sweetalert2',
                $sweetalert_url,
                array(),
                BD_COURIER_VERSION,
                false // Load in header so it's available early
            );
            
            // Add custom CSS for blocked modal
            wp_add_inline_style( 'woocommerce-general', '
                .bdc-blocked-modal {
                    border-radius: 12px !important;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
                }
                .bdc-blocked-modal .swal2-title {
                    padding: 0 !important;
                    margin-bottom: 0 !important;
                }
                .bdc-blocked-modal .swal2-html-container {
                    margin: 0 !important;
                    padding: 0 !important;
                }
                .bdc-blocked-modal .swal2-confirm {
                    border-radius: 8px !important;
                    padding: 12px 30px !important;
                    font-size: 16px !important;
                    font-weight: 600 !important;
                    box-shadow: 0 4px 12px rgba(214, 54, 56, 0.3) !important;
                    transition: all 0.3s ease !important;
                }
                .bdc-blocked-modal .swal2-confirm:hover {
                    transform: translateY(-2px) !important;
                    box-shadow: 0 6px 16px rgba(214, 54, 56, 0.4) !important;
                }
                .bdc-blocked-modal .swal2-icon {
                    margin: 0 auto 20px !important;
                    width: 64px !important;
                    height: 64px !important;
                }
            ' );
        }

        $checkout_js_file = $plugin_dir . '/assets/js/checkout-fingerprint.js';
        $dependencies = array( 'jquery' );
        if ( file_exists( $sweetalert_file ) ) {
            $dependencies[] = 'sweetalert2'; // Make checkout script depend on SweetAlert2
        }

        $checkout_js_url = plugins_url( 'assets/js/checkout-fingerprint.js', $plugin_main_file );
        $checkout_js_url = add_query_arg( 'v', BD_COURIER_VERSION, $checkout_js_url );
        wp_enqueue_script(
            'bd-courier-checkout-validation',
            $checkout_js_url,
            $dependencies,
            BD_COURIER_VERSION,
            true
        );

        wp_localize_script( 'bd-courier-checkout-validation', 'bdcCheckoutValidation', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'bdc_validate_checkout_blocked_entities' ),
        ) );
    }

    /**
     * Maybe enqueue checkout scripts if on checkout page (CartFlows compatibility).
     */
    public function maybe_enqueue_checkout_scripts() {
        // Check if we're on a checkout page (WooCommerce or CartFlows)
        $is_checkout_page = is_checkout() || 
                            is_page() && ( 
                                has_shortcode( get_post()->post_content ?? '', 'cartflows_checkout' ) ||
                                strpos( get_post()->post_content ?? '', 'cartflows-checkout' ) !== false ||
                                $this->is_cartflows_checkout()
                            );
        
        if ( $is_checkout_page ) {
            $this->add_checkout_validation_scripts();
        }
    }

    /**
     * Check if current page is a CartFlows checkout page.
     *
     * @return bool
     */
    private function is_cartflows_checkout() {
        // Check if CartFlows is active and we're on a CartFlows checkout
        if ( ! function_exists( 'cartflows' ) ) {
            return false;
        }
        
        global $post;
        if ( ! $post ) {
            return false;
        }
        
        // Check if it's a CartFlows checkout page
        $cartflows_step = get_post_meta( $post->ID, 'wcf-step-type', true );
        return $cartflows_step === 'checkout';
    }

    /**
     * Get client IP address.
     * For blocking purposes, we need to check ALL IPs including localhost/private IPs.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $ip_headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = $_SERVER[ $header ];

                // Handle comma-separated IPs (like X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }

                // Validate IP (including private/localhost IPs for blocking purposes)
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get available block durations.
     *
     * @return array
     */
    public static function get_block_durations() {
        return array(
            self::DURATION_1_HOUR => __( '1 Hour', 'bd-courier-order-ratio-checker' ),
            self::DURATION_6_HOURS => __( '6 Hours', 'bd-courier-order-ratio-checker' ),
            self::DURATION_24_HOURS => __( '24 Hours', 'bd-courier-order-ratio-checker' ),
            self::DURATION_7_DAYS => __( '7 Days', 'bd-courier-order-ratio-checker' ),
            self::DURATION_30_DAYS => __( '30 Days', 'bd-courier-order-ratio-checker' ),
            self::DURATION_PERMANENT => __( 'Permanent', 'bd-courier-order-ratio-checker' ),
        );
    }

    /**
     * Get available block types.
     *
     * @return array
     */
    public static function get_block_types() {
        return array(
            self::BLOCK_TYPE_PHONE => __( 'Phone Number', 'bd-courier-order-ratio-checker' ),
            self::BLOCK_TYPE_IP => __( 'IP Address', 'bd-courier-order-ratio-checker' ),
            self::BLOCK_TYPE_EMAIL => __( 'Email Address', 'bd-courier-order-ratio-checker' ),
            self::BLOCK_TYPE_FINGERPRINT => __( 'Browser Fingerprint', 'bd-courier-order-ratio-checker' ),
        );
    }

    /**
     * Hide admin notices on blocked entities page.
     */
    public function hide_admin_notices() {
        $screen = get_current_screen();
        if ( $screen && ( $screen->id === 'woocommerce_page_bd-courier-blocked-entities' || ( isset( $_GET['page'] ) && $_GET['page'] === 'bd-courier-blocked-entities' ) ) ) {
            echo '<style type="text/css">
                .notice, .notice-error, .notice-success, .notice-warning, .notice-info, .update-nag, .error, .updated {
                    display: none !important;
                }
            </style>';
        }
    }
}
