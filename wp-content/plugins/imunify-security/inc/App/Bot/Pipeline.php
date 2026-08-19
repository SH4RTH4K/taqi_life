<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Per-request bot-protection orchestrator.
 *
 * Pipeline steps, in order:
 *   1. Build request context (URI, headers, REMOTE_ADDR, resolved client IP,
 *      UA, honeypot-triggered). RealIpResolver is applied up front so a
 *      single resolved IP flows through the allowlist, classifier, and
 *      rate limiter.
 *   2. Allowlist check — if any matcher accepts, return.
 *   3. Classify — any exception from the classifier fails open.
 *   4. Rate-limit on the resolved IP — any exception fails open. Passing
 *      $remote_addr here would collapse every CDN visitor onto the edge
 *      IP's counter, either throttling legitimate users or ignoring bots.
 *   5. Responder emits 429/403 (or returns on ALLOW).
 *
 * The pipeline itself never calls exit(): only Responder does. That
 * makes the whole pipeline unit-testable and keeps the fail-open
 * promise loadable — any uncaught exception at any step converts to
 * "pass through to WordPress".
 *
 * Per-event logging is deferred to a future phase — no EventLogger
 * collaborator here. Observation hooks will be added at the same
 * call sites when the bot traffic dashboard ships.
 *
 * @since 4.0.0
 */
class Pipeline {

	/**
	 * Bot classification engine.
	 *
	 * @var Classifier
	 */
	private $classifier;

	/**
	 * Per-IP rate limiter with category-aware decision.
	 *
	 * @var RateLimiter
	 */
	private $rateLimiter;

	/**
	 * Ordered composite of AllowlistMatcher instances.
	 *
	 * @var RequestAllowlist
	 */
	private $allowlist;

	/**
	 * Response emitter; centralises exit() so Pipeline stays testable.
	 *
	 * @var Responder
	 */
	private $responder;

	/**
	 * Anti-spoofing real-client-IP resolver.
	 *
	 * Shared with Classifier so both see the same decoded client IP.
	 *
	 * @var RealIpResolver
	 */
	private $realIpResolver;

	/**
	 * 24-hour blocked-request counter feeding the dashboard widget.
	 * Optional — null means no counter is recorded, which keeps legacy
	 * Pipeline constructions in other contexts working.
	 *
	 * @var DailyCounter|null
	 */
	private $dailyCounter;

	/**
	 * Wire up the pipeline over its collaborators.
	 *
	 * @param Classifier        $classifier        Bot classifier.
	 * @param RateLimiter       $rate_limiter      Category-aware rate limiter.
	 * @param RequestAllowlist  $allowlist         Composite of AllowlistMatcher instances.
	 * @param Responder         $responder         HTTP response emitter.
	 * @param RealIpResolver    $real_ip_resolver  Anti-spoofing client-IP resolver.
	 * @param DailyCounter|null $daily_counter     Optional 24h blocked-request counter.
	 */
	public function __construct( $classifier, $rate_limiter, $allowlist, $responder, $real_ip_resolver, $daily_counter = null ) {
		$this->classifier     = $classifier;
		$this->rateLimiter    = $rate_limiter;
		$this->allowlist      = $allowlist;
		$this->responder      = $responder;
		$this->realIpResolver = $real_ip_resolver;
		$this->dailyCounter   = $daily_counter;
	}

	/**
	 * Run the pipeline over a captured $_SERVER snapshot.
	 *
	 * @param array $server Raw $_SERVER snapshot.
	 * @return void
	 */
	public function run( $server ) {
		// PHP 7+ error types (TypeError, Error) descend from \Throwable,
		// not \Exception, so catching only Exception would let them
		// escape the outer fail-open wrapper. Match the dual-path
		// pattern in the mu-plugin shim and SafeInclude::load().
		if ( interface_exists( 'Throwable' ) ) {
			try {
				$this->runInner( $server );
			} catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: any uncaught throwable passes through to WordPress.
			}
			return;
		}
		try {
			$this->runInner( $server );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: any uncaught exception passes through to WordPress.
		}
	}

	/**
	 * Inner pipeline body without the fail-open wrapper.
	 *
	 * @param array $server Raw $_SERVER snapshot.
	 * @return void
	 */
	private function runInner( $server ) {
		$uri         = isset( $server['REQUEST_URI'] ) ? (string) $server['REQUEST_URI'] : '';
		$remote_addr = isset( $server['REMOTE_ADDR'] ) ? (string) $server['REMOTE_ADDR'] : '';
		$protocol    = isset( $server['SERVER_PROTOCOL'] ) ? (string) $server['SERVER_PROTOCOL'] : '';
		$ua          = isset( $server['HTTP_USER_AGENT'] ) ? (string) $server['HTTP_USER_AGENT'] : '';
		$script_name = isset( $server['SCRIPT_NAME'] ) ? (string) $server['SCRIPT_NAME'] : '';
		$headers     = self::extractHeaders( $server );
		$honeypot    = Honeypot::isTriggered( $uri, Honeypot::basePathFromScriptName( $script_name ) );

		// Resolve the real client IP once, up front. All downstream
		// consumers — allowlist, rate limiter — must see the same
		// anti-spoofed address, otherwise CDN-fronted sites either
		// throttle every visitor onto the edge IP's counter or trust a
		// spoofed header. Classifier still takes REMOTE_ADDR because it
		// runs its own internal resolution (shares this resolver).
		$client_ip = $this->realIpResolver->resolve( $headers, $remote_addr );

		$context = array(
			'uri'     => $uri,
			'headers' => $headers,
			'ip'      => $client_ip,
			'ua'      => $ua,
		);

		if ( ! $honeypot && $this->allowlist->isAllowlisted( $context ) ) {
			return;
		}

		// Per-step fail-open: a throw from classifier or rate limiter
		// must NOT propagate past this step. Use the dual-path
		// Throwable/Exception pattern (same as run()) so PHP 7+
		// TypeError/Error are caught here, not by the outer wrapper —
		// without this, an Error in the classifier would skip
		// step-level isolation and surface as an undecorated outer
		// fail-open. Phpcs ignores below: fail-open by design.
		if ( interface_exists( 'Throwable' ) ) {
			try {
				$category = $this->classifier->classify( $headers, $remote_addr, $protocol, $honeypot );
			} catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: classifier throw passes through to WordPress.
				return;
			}
		} else {
			try {
				$category = $this->classifier->classify( $headers, $remote_addr, $protocol, $honeypot );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: classifier throw passes through to WordPress.
				return;
			}
		}

		if ( interface_exists( 'Throwable' ) ) {
			try {
				$decision = $this->rateLimiter->check( $category, $client_ip );
			} catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: rate-limiter throw passes through to WordPress.
				return;
			}
		} else {
			try {
				$decision = $this->rateLimiter->check( $category, $client_ip );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: rate-limiter throw passes through to WordPress.
				return;
			}
		}

		if ( null !== $this->dailyCounter ) {
			if ( interface_exists( 'Throwable' ) ) {
				try {
					$this->dailyCounter->recordDecision( $decision );
				} catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- telemetry failure is non-fatal.
					unset( $t );
				}
			} else {
				try {
					$this->dailyCounter->recordDecision( $decision );
				} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- telemetry failure is non-fatal.
					unset( $e );
				}
			}
		}

		$this->responder->respond( $decision );
	}

	/**
	 * Reconstruct a case-preserving header map from $_SERVER.
	 *
	 * PHP exposes request headers as HTTP_* keys. We lower-case the
	 * suffix and replace underscores with dashes so downstream code
	 * sees "user-agent" / "cf-connecting-ip" the way RealIpResolver and
	 * Classifier expect.
	 *
	 * @param array $server Raw $_SERVER.
	 * @return array
	 */
	private static function extractHeaders( $server ) {
		$out = array();
		foreach ( $server as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( 0 === strpos( $key, 'HTTP_' ) ) {
				$name         = strtolower( str_replace( '_', '-', substr( $key, 5 ) ) );
				$out[ $name ] = is_scalar( $value ) ? (string) $value : '';
			}
		}
		return $out;
	}
}
