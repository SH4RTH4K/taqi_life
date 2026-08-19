<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Rolling 24-hour blocked-request counter backed by the active
 * {@see CounterStorageInterface} (Memory / Null).
 *
 * Phase-1 surface the dashboard widget reads to show "N requests
 * blocked today". Deliberately tiny: one key, one TTL, no rotation —
 * per-event logging and charts are deferred to a future phase.
 *
 * Key layout is namespaced per install via the same 16-char ABSPATH
 * hash {@see RateLimiter} uses, so multiple WordPress installs sharing
 * an APCu or Redis keyspace don't cross-pollute.
 *
 * @since 4.0.0
 */
class DailyCounter {

	/**
	 * Rolling window for the counter. 86400s = 24h.
	 */
	const WINDOW_SECONDS = 86400;

	/**
	 * Key prefix. See the site_id derivation below.
	 */
	const KEY_PREFIX = 'bot:daily_blocked:';

	/**
	 * Backing counter store (Memory / Null).
	 *
	 * @var CounterStorageInterface
	 */
	private $storage;

	/**
	 * Fully-qualified storage key including the site-id suffix.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Construct a counter bound to the given storage and site id.
	 *
	 * @param CounterStorageInterface $storage Backing counter store.
	 * @param string|null             $site_id Optional explicit site prefix.
	 *                                  Production leaves this null — the
	 *                                  constructor derives a 16-char hex
	 *                                  hash from ABSPATH. Tests pass
	 *                                  explicit values to verify key
	 *                                  isolation.
	 */
	public function __construct( $storage, $site_id = null ) {
		$this->storage = $storage;
		$this->key     = self::KEY_PREFIX . ( null !== $site_id ? $site_id : SiteScope::derive() );
	}

	/**
	 * Increment the counter. No-op if the decision represents an
	 * allowed request.
	 *
	 * @param RateLimitDecision $decision Decision returned by RateLimiter::check().
	 * @return void
	 */
	public function recordDecision( $decision ) {
		if ( $decision->isAllowed() ) {
			return;
		}
		$this->storage->increment( $this->key, self::WINDOW_SECONDS );
	}

	/**
	 * Current blocked-or-rate-limited count over the 24h window.
	 *
	 * @return int
	 */
	public function current() {
		return (int) $this->storage->get( $this->key );
	}
}
