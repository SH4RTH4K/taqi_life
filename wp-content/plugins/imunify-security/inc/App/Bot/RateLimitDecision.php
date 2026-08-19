<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Immutable decision returned by RateLimiter::check().
 *
 * Three terminal outcomes are modelled:
 *   - ALLOW      — request proceeds to WordPress.
 *   - RATE_LIMIT — request is refused with 429 Too Many Requests. The
 *                  Retry-After header value is carried on the decision.
 *   - BLOCK      — request is refused with 403 Forbidden (malicious bots
 *                  and the extended-block escalation state).
 *
 * Factory constructors enforce that callers cannot mix an action with an
 * incompatible HTTP status or Retry-After value by accident.
 *
 * @since 4.0.0
 */
class RateLimitDecision {

	const ACTION_ALLOW      = 'allow';
	const ACTION_RATE_LIMIT = 'rate_limit';
	const ACTION_BLOCK      = 'block';

	/**
	 * One of the ACTION_* constants.
	 *
	 * @var string
	 */
	private $action;

	/**
	 * HTTP status code (200/429/403).
	 *
	 * @var int
	 */
	private $http_status;

	/**
	 * Retry-After seconds for rate-limited responses. 0 for allow/block.
	 *
	 * @var int
	 */
	private $retry_after;

	/**
	 * Private constructor; use the named factory methods.
	 *
	 * @param string $action      ACTION_* constant.
	 * @param int    $http_status HTTP status code.
	 * @param int    $retry_after Seconds until retry is advised (rate-limit only).
	 */
	private function __construct( $action, $http_status, $retry_after ) {
		$this->action      = $action;
		$this->http_status = (int) $http_status;
		$this->retry_after = (int) $retry_after;
	}

	/**
	 * Build an ALLOW decision (HTTP 200, no retry advice).
	 *
	 * @return self
	 */
	public static function allow() {
		return new self( self::ACTION_ALLOW, 200, 0 );
	}

	/**
	 * Build a RATE_LIMIT decision (HTTP 429) with a Retry-After hint.
	 *
	 * @param int $retry_after Seconds until retry is advised. Negative values clamp to 0.
	 * @return self
	 */
	public static function rateLimit( $retry_after ) {
		$seconds = max( 0, (int) $retry_after );
		return new self( self::ACTION_RATE_LIMIT, 429, $seconds );
	}

	/**
	 * Build a BLOCK decision (HTTP 403).
	 *
	 * @return self
	 */
	public static function block() {
		return new self( self::ACTION_BLOCK, 403, 0 );
	}

	/**
	 * Decision action (ACTION_ALLOW / ACTION_RATE_LIMIT / ACTION_BLOCK).
	 *
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * HTTP status code the caller should respond with.
	 *
	 * @return int
	 */
	public function getHttpStatus() {
		return $this->http_status;
	}

	/**
	 * Retry-After seconds. Zero when not applicable.
	 *
	 * @return int
	 */
	public function getRetryAfter() {
		return $this->retry_after;
	}

	/**
	 * Whether the request should be passed through to WordPress.
	 *
	 * @return bool
	 */
	public function isAllowed() {
		return self::ACTION_ALLOW === $this->action;
	}
}
