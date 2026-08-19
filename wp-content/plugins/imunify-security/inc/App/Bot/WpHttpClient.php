<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * HttpClient implementation that wraps wp_remote_get.
 *
 * Returns the raw body on a 2xx response; any WP_Error, non-2xx status,
 * or empty body is surfaced to the caller as null so the refresher's
 * "never overwrite on failure" contract holds without additional logic.
 *
 * @since 4.0.0
 */
class WpHttpClient implements HttpClient {

	const DEFAULT_TIMEOUT_SECONDS = 25;

	/**
	 * Upper bound on response body size (bytes) — protects against memory
	 * exhaustion if a compromised or misbehaving source returns an outsized
	 * payload. The largest bundled dataset (Azure service tags) is ~500KB,
	 * so 16MB leaves generous headroom while capping catastrophic growth.
	 */
	const DEFAULT_MAX_BODY_BYTES = 16777216;

	/**
	 * Timeout passed to wp_remote_get (seconds).
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * Maximum response body size in bytes.
	 *
	 * @var int
	 */
	private $max_body_bytes;

	/**
	 * Build the client with a configurable timeout and response-size cap.
	 *
	 * @param int $timeout        Timeout in seconds.
	 * @param int $max_body_bytes Upper bound on response body size.
	 */
	public function __construct( $timeout = self::DEFAULT_TIMEOUT_SECONDS, $max_body_bytes = self::DEFAULT_MAX_BODY_BYTES ) {
		// max() with a 1-second floor keeps a caller accidentally passing 0 (which
		// wp_remote_get interprets as "no timeout") from parking a request forever.
		$this->timeout        = max( 1, (int) $timeout );
		$this->max_body_bytes = max( 1, (int) $max_body_bytes );
	}

	/**
	 * Fetch $url via wp_remote_get, returning the body or null on failure.
	 *
	 * @param string $url Absolute URL.
	 * @return string|null
	 */
	public function get( $url ) {
		$version  = defined( 'IMUNIFY_SECURITY_VERSION' ) ? IMUNIFY_SECURITY_VERSION : '0.0.0';
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => $this->timeout,
				'sslverify'           => true,
				'user-agent'          => 'ImunifySecurity-BotData/' . $version . ' (+https://imunify360.com)',
				'limit_response_size' => $this->max_body_bytes,
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || '' === $body ) {
			return null;
		}
		return $body;
	}
}
