<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CourierAPI
 * Handles fetching courier data from the external API.
 */
class CourierAPI {

    /**
     * Get API base URL.
     *
     * @return string
     */
    private static function get_api_base_url() {
        return 'https://api.bdcourier.com/';
    }

    /**
     * Get request arguments with SSL verification settings.
     *
     * @param array $headers Request headers.
     * @param int   $timeout Request timeout.
     * @return array
     */
    private static function get_request_args( $headers, $timeout = 30 ) {
        $base_url = self::get_api_base_url();
        // Enable SSL verification for production API (api.bdcourier.com)
        // Only disable for development environments (.test, localhost, etc.)
        $sslverify = ! (
            strpos( $base_url, '.test' ) !== false ||
            strpos( $base_url, 'localhost' ) !== false ||
            strpos( $base_url, '127.0.0.1' ) !== false ||
            defined( 'WP_DEBUG' ) && WP_DEBUG
        );

        return [
            'headers' => $headers,
            'timeout' => $timeout,
            'sslverify' => $sslverify,
        ];
    }

    /**
     * Fetch courier order ratio from API.
     *
     * @param string $phone The phone number.
     * @return array|false Returns data array on success, false on error. Use get_last_error() for error details.
     */
    private static $last_error = null;

    public static function fetch_order_ratio_from_api( $phone ) {
        // Reset error at the start of each call
        self::$last_error = null;
        
        $api_token = get_option( 'bd_courier_api_token' );
        if ( empty( $api_token ) ) {
            self::$last_error = 'API token is empty.';
            return false;
        }
        
        $base_url = self::get_api_base_url();
        $url = $base_url . 'courier-check?phone=' . urlencode( $phone );
        
        
        $headers = [
            'Authorization' => 'Bearer ' . esc_attr( $api_token ),
        ];
        $response = wp_remote_get( $url, self::get_request_args( $headers, 100 ) );
        
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            self::$last_error = $error_message;
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        

        // Check HTTP status code first
        if ( $http_code !== 200 ) {
            $error_message = 'HTTP Error ' . $http_code;
            $data = json_decode( $body, true );
            if ( $data && isset( $data['message'] ) ) {
                $error_message .= ': ' . $data['message'];
            } elseif ( $data && isset( $data['error'] ) ) {
                if ( is_array( $data['error'] ) ) {
                    $error_message .= ': ' . ( isset( $data['error']['message'] ) ? $data['error']['message'] : json_encode( $data['error'] ) );
                } else {
                    $error_message .= ': ' . $data['error'];
                }
            } else {
                $error_message .= ': Request failed';
            }
            self::$last_error = $error_message;
            return false;
        }

        $data = json_decode( $body, true );
        
        // Check if JSON decode failed
        if ( $data === null && json_last_error() !== JSON_ERROR_NONE ) {
            $error_message = 'Invalid JSON response: ' . json_last_error_msg();
            if ( ! empty( $body ) ) {
                $error_message .= ' (Response: ' . substr( $body, 0, 200 ) . ')';
            }
            self::$last_error = $error_message;
            return false;
        }
        
        // Check if data is empty or not an array
        if ( ! is_array( $data ) ) {
            $error_message = 'Invalid API response format. Expected array, got: ' . gettype( $data );
            if ( ! empty( $body ) ) {
                $error_message .= ' (Response: ' . substr( $body, 0, 200 ) . ')';
            }
            self::$last_error = $error_message;
            return false;
        }
        
        // Check for courierData (old format)
        if ( isset( $data['courierData'] ) && is_array( $data['courierData'] ) && ! empty( $data['courierData'] ) ) {
            return $data['courierData'];
        }
        
        // Check for new format: status=success with data field
        if ( isset( $data['status'] ) && $data['status'] === 'success' && isset( $data['data'] ) && is_array( $data['data'] ) && ! empty( $data['data'] ) ) {
            return $data['data'];
        }
        
        // Check for data field directly (without status check)
        if ( isset( $data['data'] ) && is_array( $data['data'] ) && ! empty( $data['data'] ) ) {
            // Verify it looks like courier data (has courier names as keys with total_parcel)
            $has_courier_structure = false;
            foreach ( $data['data'] as $key => $value ) {
                if ( is_array( $value ) && isset( $value['total_parcel'] ) ) {
                    $has_courier_structure = true;
                    break;
                }
            }
            if ( $has_courier_structure ) {
                return $data['data'];
            }
        }
        
        // Extract error message from API response
        $error_message = null;
        
        // Try multiple error message locations (check data field first if it's a string)
        if ( isset( $data['data'] ) && is_string( $data['data'] ) && ! empty( trim( $data['data'] ) ) ) {
            $error_message = trim( $data['data'] );
        } elseif ( isset( $data['message'] ) && ! empty( trim( $data['message'] ) ) ) {
            $error_message = trim( $data['message'] );
        } elseif ( isset( $data['error'] ) ) {
            if ( is_array( $data['error'] ) ) {
                if ( isset( $data['error']['message'] ) && ! empty( trim( $data['error']['message'] ) ) ) {
                    $error_message = trim( $data['error']['message'] );
                } elseif ( isset( $data['error']['error'] ) && ! empty( trim( $data['error']['error'] ) ) ) {
                    $error_message = trim( $data['error']['error'] );
                } else {
                    $error_message = 'API Error: ' . json_encode( $data['error'] );
                }
            } else {
                $error_message = trim( $data['error'] );
            }
        } elseif ( isset( $data['status'] ) && $data['status'] === 'error' && isset( $data['message'] ) ) {
            $error_message = trim( $data['message'] );
        } elseif ( isset( $data['status'] ) && isset( $data['data'] ) && is_array( $data['data'] ) && isset( $data['data']['message'] ) ) {
            $error_message = trim( $data['data']['message'] );
        } elseif ( empty( $body ) ) {
            $error_message = 'Empty response from API';
        } else {
            // If no specific error message found, provide detailed information
            $error_message = 'Failed to fetch courier data. ';
            if ( isset( $data['status'] ) ) {
                $error_message .= 'Status: ' . $data['status'] . '. ';
            }
            if ( isset( $data['code'] ) ) {
                $error_message .= 'Code: ' . $data['code'] . '. ';
            }
            if ( ! empty( $body ) ) {
                $error_message .= 'Response: ' . substr( $body, 0, 300 );
            } else {
                $error_message .= 'No response body received.';
            }
        }
        
        // Ensure we always have an error message
        if ( empty( $error_message ) ) {
            $error_message = 'Failed to fetch courier data from API. Please check your API token and connection.';
        }
        
        self::$last_error = $error_message;
        return false;
    }

    /**
     * Get the last error message from fetch_order_ratio_from_api.
     *
     * @return string|null
     */
    public static function get_last_error() {
        return self::$last_error;
    }

    /**
     * Check API connection.
     *
     * @return array
     */
    public static function check_api_connection() {
        $api_token = get_option( 'bd_courier_api_token' );
        if ( empty( $api_token ) ) {
            return [
                'status' => 'error',
                'message' => 'API token is empty.',
                'raw_response' => null,
            ];
        }
        $url = self::get_api_base_url() . 'check-connection';
        $headers = [
            'Authorization' => 'Bearer ' . esc_attr( $api_token ),
        ];
        $response = wp_remote_get( $url, self::get_request_args( $headers, 30 ) );
        if ( is_wp_error( $response ) ) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message(),
                'raw_response' => null,
                'wp_error' => true,
            ];
        }
        $http_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Include raw response in the result
        $result = [
            'status' => isset( $data['status'] ) ? $data['status'] : 'error',
            'message' => isset( $data['message'] ) ? $data['message'] : 'Unknown error',
            'raw_response' => $body,
            'http_code' => $http_code,
            'data' => $data,
        ];
        
        return $result;
    }

    /**
     * Get current plan information.
     *
     * @return array
     */
    public static function get_plan_info() {
        $api_token = get_option( 'bd_courier_api_token' );
        if ( empty( $api_token ) ) {
            return [
                'status' => 'error',
                'message' => 'API token is empty.',
                'raw_response' => null,
            ];
        }
        $url = self::get_api_base_url() . 'my-plan';
        $headers = [
            'Authorization' => 'Bearer ' . esc_attr( $api_token ),
        ];
        $response = wp_remote_get( $url, self::get_request_args( $headers, 30 ) );
        if ( is_wp_error( $response ) ) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message(),
                'raw_response' => null,
                'wp_error' => true,
            ];
        }
        $http_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Log for debugging
        
        // Include raw response in the result
        // If the API returns { status: 'success', data: {...} }, extract the inner data
        $plan_data = isset( $data['data'] ) ? $data['data'] : $data;
        
        $result = [
            'status' => isset( $data['status'] ) ? $data['status'] : 'error',
            'message' => isset( $data['message'] ) ? $data['message'] : 'Unknown error',
            'raw_response' => $body,
            'http_code' => $http_code,
            'data' => $plan_data, // Use the inner data if it exists, otherwise use the full response
        ];
        
        
        return $result;
    }

    /**
     * Check if the current WordPress site is authorized for extended features.
     * Extended features (incomplete orders, fraud blocker, duplicate blocker) 
     * are only enabled for authorized WordPress sites.
     *
     * @return bool True if authorized, false otherwise
     */
    public static function is_site_authorized() {
        // Get plan info (with caching if available)
        $plan_info = self::get_plan_info();
        
        // Check if plan info is valid
        if ( ! isset( $plan_info['status'] ) || $plan_info['status'] !== 'success' ) {
            return false;
        }
        
        // Check if has_subscription is true
        $plan_data = isset( $plan_info['data'] ) ? $plan_info['data'] : array();
        if ( ! isset( $plan_data['has_subscription'] ) || ! $plan_data['has_subscription'] ) {
            return false;
        }
        
        // Get current site URL
        $current_site_url = home_url( '/', 'https' );
        // Also check without trailing slash
        $current_site_url_no_slash = rtrim( $current_site_url, '/' );
        
        // Get authorized WordPress sites from plan data
        $authorized_sites = isset( $plan_data['wordpress_sites'] ) ? $plan_data['wordpress_sites'] : array();
        
        if ( empty( $authorized_sites ) || ! is_array( $authorized_sites ) ) {
            return false;
        }
        
        // Normalize URLs for comparison (remove trailing slashes, convert to lowercase)
        $current_site_normalized = strtolower( rtrim( $current_site_url, '/' ) );
        $current_site_no_slash_normalized = strtolower( $current_site_url_no_slash );
        
        // Check if current site is in authorized sites list
        foreach ( $authorized_sites as $authorized_site ) {
            $authorized_site_normalized = strtolower( rtrim( $authorized_site, '/' ) );
            
            // Exact match
            if ( $current_site_normalized === $authorized_site_normalized || 
                 $current_site_no_slash_normalized === $authorized_site_normalized ) {
                return true;
            }
            
            // Also check with http (in case one is http and other is https)
            $current_site_http = str_replace( 'https://', 'http://', $current_site_normalized );
            $authorized_site_http = str_replace( 'https://', 'http://', $authorized_site_normalized );
            
            if ( $current_site_http === $authorized_site_http ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get authorization status with details.
     *
     * @return array Array with 'authorized' (bool), 'reason' (string), and 'is_free' (bool)
     */
    public static function get_authorization_status() {
        $plan_info = self::get_plan_info();
        
        $result = array(
            'authorized' => false,
            'reason' => 'unknown',
            'is_free' => false,
            'plan_name' => '',
        );
        
        // Check if plan info is valid
        if ( ! isset( $plan_info['status'] ) || $plan_info['status'] !== 'success' ) {
            $result['reason'] = 'plan_info_unavailable';
            return $result;
        }
        
        $plan_data = isset( $plan_info['data'] ) ? $plan_info['data'] : array();
        
        // Check if it's a free plan
        if ( isset( $plan_data['is_free'] ) && $plan_data['is_free'] === true ) {
            $result['is_free'] = true;
            $result['reason'] = 'free_plan';
            $result['plan_name'] = isset( $plan_data['plan_name'] ) ? $plan_data['plan_name'] : 'Free';
            return $result;
        }
        
        // Check if has_subscription is true
        if ( ! isset( $plan_data['has_subscription'] ) || ! $plan_data['has_subscription'] ) {
            $result['reason'] = 'no_subscription';
            $result['plan_name'] = isset( $plan_data['plan_name'] ) ? $plan_data['plan_name'] : '';
            return $result;
        }
        
        // Get current site URL
        $current_site_url = home_url( '/', 'https' );
        $current_site_url_no_slash = rtrim( $current_site_url, '/' );
        
        // Get authorized WordPress sites from plan data
        $authorized_sites = isset( $plan_data['wordpress_sites'] ) ? $plan_data['wordpress_sites'] : array();
        
        if ( empty( $authorized_sites ) || ! is_array( $authorized_sites ) ) {
            $result['reason'] = 'no_authorized_sites';
            $result['plan_name'] = isset( $plan_data['plan_name'] ) ? $plan_data['plan_name'] : '';
            return $result;
        }
        
        // Normalize URLs for comparison
        $current_site_normalized = strtolower( rtrim( $current_site_url, '/' ) );
        $current_site_no_slash_normalized = strtolower( $current_site_url_no_slash );
        
        // Check if current site is in authorized sites list
        foreach ( $authorized_sites as $authorized_site ) {
            $authorized_site_normalized = strtolower( rtrim( $authorized_site, '/' ) );
            
            // Exact match
            if ( $current_site_normalized === $authorized_site_normalized || 
                 $current_site_no_slash_normalized === $authorized_site_normalized ) {
                $result['authorized'] = true;
                $result['reason'] = 'authorized';
                $result['plan_name'] = isset( $plan_data['plan_name'] ) ? $plan_data['plan_name'] : '';
                return $result;
            }
            
            // Also check with http (in case one is http and other is https)
            $current_site_http = str_replace( 'https://', 'http://', $current_site_normalized );
            $authorized_site_http = str_replace( 'https://', 'http://', $authorized_site_normalized );
            
            if ( $current_site_http === $authorized_site_http ) {
                $result['authorized'] = true;
                $result['reason'] = 'authorized';
                $result['plan_name'] = isset( $plan_data['plan_name'] ) ? $plan_data['plan_name'] : '';
                return $result;
            }
        }
        
        $result['reason'] = 'site_not_authorized';
        $result['plan_name'] = isset( $plan_data['plan_name'] ) ? $plan_data['plan_name'] : '';
        return $result;
    }
}
