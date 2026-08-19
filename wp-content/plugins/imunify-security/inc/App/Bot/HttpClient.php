<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Minimal HTTP GET abstraction used by SignatureRefresher.
 *
 * Production code wires this to wp_remote_get via WpHttpClient; tests
 * inject a fake that returns pre-set payloads. Implementations return
 * the response body as a string on success or null on any failure
 * (network error, non-2xx response, timeout), giving the refresher a
 * deterministic "keep existing data" path.
 *
 * @since 4.0.0
 */
interface HttpClient {

	/**
	 * Perform a GET request and return the response body, or null on failure.
	 *
	 * @param string $url Absolute URL to fetch.
	 * @return string|null Response body on success, null on any failure.
	 */
	public function get( $url );
}
