<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fopen
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fwrite
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fread
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fclose
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_flock
 * phpcs:disable WordPress.WP.AlternativeFunctions.rename_rename
 * phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Wp-cron-driven refresher for bot-protection data.
 *
 * Polls the CloudLinux mirror (`MIRROR_BASE_URL`) on a 6-hour schedule
 * wired via `scheduleHooks()` / `BotLifecycle::activate()`. Fetches
 * `description.json` first (cheap MD5 check); only downloads `all.json`
 * when its MD5 differs from the locally-cached value. Delegates JSON→PHP
 * bundle conversion to `BotDataConverter`, which writes files atomically
 * into the overlay directory (`wp-content/imunify-security/bot-data/`).
 * `BundledData` picks up overlay files on the next classification call,
 * transparently superseding the snapshot shipped under
 * `inc/App/Bot/data/`.
 *
 * Safety invariants:
 *   - Network failures and MD5 mismatches leave existing overlay files
 *     untouched (fail-open: never degrade on transient network error).
 *   - A POSIX advisory lock on `<overlay>/.refresh.lock` prevents two
 *     concurrently-firing wp-cron workers from racing each other.
 *
 * @since 4.0.0
 */
class SignatureRefresher {

	const CRON_HOOK_REFRESH    = 'imunify_security_bot_refresh';
	const LOCK_FILENAME        = '.refresh.lock';
	const MIRROR_BASE_URL      = 'https://files.imunify360.com/static/crawler-intel/v1';
	const MIRROR_MD5_OPTION    = 'imunify_security_bot_mirror_md5sum';
	const MIN_SIGNATURE_LENGTH = 4;
	const SIGNATURE_DENYLIST   = array(
		'mozilla',
		'chrome',
		'safari',
		'firefox',
		'edge',
		'opera',
		'msie',
		'trident',
		'applewebkit',
		'gecko',
		'webkit',
	);

	/**
	 * Absolute path to the overlay root.
	 *
	 * @var string
	 */
	private $overlay_dir;

	/**
	 * Injected HTTP client used to fetch source URLs.
	 *
	 * @var HttpClient
	 */
	private $http;

	/**
	 * Build a refresher bound to an overlay directory.
	 *
	 * @param string     $overlay_dir Absolute path to the overlay root.
	 * @param HttpClient $http        HTTP client (WpHttpClient in production, fake in tests).
	 */
	public function __construct( $overlay_dir, $http ) {
		$this->overlay_dir = rtrim( (string) $overlay_dir, '/' );
		$this->http        = $http;
	}

	/**
	 * Reject signature tokens that are too short or match common browser substrings.
	 *
	 * Public so `bin/update-bot-data.php` can apply the same rule when
	 * generating bundled data files, keeping bundled and cron-refreshed
	 * overlays byte-for-byte comparable.
	 *
	 * @param array $items Raw signature tokens.
	 * @return array Sanitized tokens, re-indexed.
	 */
	public static function sanitizeSignatures( $items ) {
		$out = array();
		foreach ( $items as $token ) {
			if ( strlen( $token ) < self::MIN_SIGNATURE_LENGTH ) {
				continue;
			}
			$lower = strtolower( $token );
			if ( in_array( $lower, self::SIGNATURE_DENYLIST, true ) ) {
				continue;
			}
			$out[] = $token;
		}
		return $out;
	}

	/**
	 * Bucket a sorted list of CIDRs into the shape CidrMatcher::matchesAnyBucketed() expects.
	 *
	 * Public so bin/update-bot-data.php can produce the same bucketed structure,
	 * keeping dev-bundled and cron-refreshed overlays byte-identical.
	 *
	 * @param array $sorted_ranges Sorted CIDR strings.
	 * @return array { 'ranges_by_octet' => int[] => string[], 'ranges_broad' => string[] }
	 */
	public static function bucketRanges( $sorted_ranges ) {
		$by_octet = array();
		$broad    = array();
		foreach ( $sorted_ranges as $cidr ) {
			$slash = strpos( $cidr, '/' );
			if ( false === $slash ) {
				continue;
			}
			$network    = substr( $cidr, 0, $slash );
			$prefix_str = substr( $cidr, $slash + 1 );
			if ( '' === $prefix_str || ! ctype_digit( $prefix_str ) ) {
				continue;
			}
			$prefix = (int) $prefix_str;
			$bin    = @inet_pton( $network );
			if ( false === $bin ) {
				continue;
			}
			if ( 16 === strlen( $bin ) && "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" === substr( $bin, 0, 12 ) ) {
				$bin     = substr( $bin, 12 );
				$prefix -= 96;
			}
			if ( $prefix < 8 ) {
				$broad[] = $cidr;
			} else {
				$octet                = ord( $bin[0] );
				$by_octet[ $octet ][] = $cidr;
			}
		}
		ksort( $by_octet );
		return array(
			'ranges_by_octet' => $by_octet,
			'ranges_broad'    => $broad,
		);
	}

	/**
	 * Extract IPv4/IPv6 prefixes from a Google-shape JSON payload.
	 *
	 * Shared between bin/update-bot-data.php fetchers and cron-spec parsers so
	 * both paths apply identical parsing.
	 *
	 * @param array $data Decoded JSON array.
	 * @return array List of CIDR strings.
	 * @throws \RuntimeException When the payload does not match the expected shape.
	 */
	public static function parseGoogleShape( $data ) {
		if ( ! isset( $data['prefixes'] ) || ! is_array( $data['prefixes'] ) ) {
			throw new \RuntimeException( 'unexpected JSON shape: missing prefixes[]' );
		}
		$out = array();
		foreach ( $data['prefixes'] as $p ) {
			if ( isset( $p['ipv4Prefix'] ) ) {
				$out[] = $p['ipv4Prefix'];
			} elseif ( isset( $p['ipv6Prefix'] ) ) {
				$out[] = $p['ipv6Prefix'];
			}
		}
		if ( empty( $out ) ) {
			throw new \RuntimeException( 'no prefixes found in payload' );
		}
		return $out;
	}

	/**
	 * Acquire the refresher's POSIX advisory lock.
	 *
	 * @return resource|null Lock file handle on success, null when another worker holds the lock.
	 */
	private function acquireLock() {
		if ( ! is_dir( $this->overlay_dir ) && ! mkdir( $this->overlay_dir, 0755, true ) && ! is_dir( $this->overlay_dir ) ) {
			return null;
		}
		$path = $this->overlay_dir . '/' . self::LOCK_FILENAME;
		$h    = fopen( $path, 'c+' );
		if ( false === $h ) {
			return null;
		}
		if ( ! flock( $h, LOCK_EX | LOCK_NB ) ) {
			fclose( $h );
			return null;
		}
		return $h;
	}

	/**
	 * Release a previously-acquired lock handle.
	 *
	 * @param resource $handle File handle returned by acquireLock().
	 */
	private function releaseLock( $handle ) {
		flock( $handle, LOCK_UN );
		fclose( $handle );
	}

	/**
	 * Download all.json from the mirror if its md5 has changed, verify integrity,
	 * and convert to PHP bundle files via BotDataConverter.
	 *
	 * Fetches description.json first (small, cheap); only downloads the full
	 * all.json when its md5 entry differs from the locally-cached value.
	 *
	 * @param string $mirror_base_url Base URL of the mirror (no trailing slash).
	 */
	public function refreshFromMirror( $mirror_base_url = self::MIRROR_BASE_URL ) {
		$lock = $this->acquireLock();
		if ( null === $lock ) {
			return; // Another worker holds the refresh lock — skip this run.
		}
		try {
			$this->doMirrorRefresh( $mirror_base_url );
		} finally {
			$this->releaseLock( $lock );
		}
	}

	/**
	 * Mirror-refresh body. Always invoked under the refresh lock by
	 * refreshFromMirror(); that caller's finally guarantees the lock is
	 * released on every exit path, including thrown errors.
	 *
	 * @param string $mirror_base_url Base URL of the mirror (no trailing slash).
	 */
	private function doMirrorRefresh( $mirror_base_url ) {
		$description_body = $this->http->get( rtrim( $mirror_base_url, '/' ) . '/description.json' );
		if ( null === $description_body || '' === $description_body ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'failed to fetch description.json', array( 'bot-refresh', 'description-fetch-failed' ) );
			return;
		}

		$manifest = json_decode( $description_body, true );
		if ( ! is_array( $manifest ) || ! isset( $manifest['items'] ) || ! is_array( $manifest['items'] ) ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'description.json: unexpected shape', array( 'bot-refresh', 'description-shape-invalid' ) );
			return;
		}

		$entry = null;
		foreach ( $manifest['items'] as $item ) {
			if ( isset( $item['name'] ) && 'all.json' === $item['name'] ) {
				$entry = $item;
				break;
			}
		}
		if ( null === $entry || ! isset( $entry['url'] ) ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'all.json not found in description.json', array( 'bot-refresh', 'all-json-not-in-manifest' ) );
			return;
		}

		$remote_md5 = isset( $entry['md5sum'] )
			? (string) $entry['md5sum']
			: ( isset( $entry['md5'] ) ? (string) $entry['md5'] : '' );
		if ( '' === $remote_md5 ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'all.json entry has no md5sum in description.json', array( 'bot-refresh', 'all-json-no-md5sum' ) );
			return;
		}

		$base           = rtrim( $mirror_base_url, '/' );
		$allowed_scheme = wp_parse_url( $base, PHP_URL_SCHEME );
		$allowed_host   = wp_parse_url( $base, PHP_URL_HOST );
		$entry_url      = (string) $entry['url'];
		$entry_scheme   = wp_parse_url( $entry_url, PHP_URL_SCHEME );
		$entry_host     = wp_parse_url( $entry_url, PHP_URL_HOST );
		if ( null === $allowed_host || $entry_scheme !== $allowed_scheme || $entry_host !== $allowed_host ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'all.json URL does not match expected mirror origin — discarding', array( 'bot-refresh', 'all-json-url-origin-mismatch' ) );
			return;
		}

		if ( $remote_md5 === $this->readMirrorMd5() && $this->overlayHasData() ) {
			return;
		}

		$body = $this->http->get( (string) $entry['url'] );
		if ( null === $body || '' === $body ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'failed to fetch all.json', array( 'bot-refresh', 'all-json-fetch-failed' ) );
			return;
		}

		if ( md5( $body ) !== $remote_md5 ) { // nosemgrep: php.lang.security.weak-crypto.weak-crypto -- integrity check against server-published checksum, not cryptography.
			BundledData::reportFailOpenError( 'refreshFromMirror', 'all.json MD5 mismatch — discarding', array( 'bot-refresh', 'all-json-md5-mismatch' ) );
			return;
		}

		try {
			$written = ( new BotDataConverter( $this->overlay_dir ) )->convert( $body );
			if ( $written > 0 ) {
				$this->saveMirrorMd5( $remote_md5 );
			}
		} catch ( \Exception $e ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'convert failed: ' . $e->getMessage(), array( 'bot-refresh', 'convert-failed' ) );
		}
	}

	/**
	 * Read the md5sum stored from the last successfully applied all.json.
	 *
	 * @return string Stored md5sum, or '' when none has been applied yet.
	 */
	private function readMirrorMd5() {
		return (string) get_option( self::MIRROR_MD5_OPTION, '' );
	}

	/**
	 * Persist the md5sum of the last successfully applied all.json.
	 *
	 * @param string $md5 md5sum the mirror reported for the applied all.json.
	 */
	private function saveMirrorMd5( $md5 ) {
		update_option( self::MIRROR_MD5_OPTION, $md5, false );
	}

	/**
	 * Whether the overlay holds at least one converted bundle file.
	 *
	 * Guards the md5sum short-circuit: a matching stored md5sum must not skip the
	 * download when the overlay data has been wiped, otherwise the site would run
	 * indefinitely on bundled data with no way to repopulate until the mirror changes.
	 *
	 * @return bool
	 */
	private function overlayHasData() {
		$files = glob( $this->overlay_dir . '/*/*.php' );
		return is_array( $files ) && count( $files ) > 0;
	}

	/**
	 * Register the daily wp-cron events driving the mirror refresh.
	 *
	 * Called from BotLifecycle::activate() so events are scheduled
	 * on plugin activation and survive across site requests.
	 */
	public static function scheduleHooks() {
		$current = \wp_get_schedule( self::CRON_HOOK_REFRESH );
		if ( false !== $current && 'imunify_six_hours' !== $current ) {
			\wp_clear_scheduled_hook( self::CRON_HOOK_REFRESH );
			$current = false;
		}
		if ( false === $current ) {
			\wp_schedule_event(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- cron jitter offset, not security
				\time() + \mt_rand( 0, 21599 ),
				'imunify_six_hours',
				self::CRON_HOOK_REFRESH
			);
		}
	}
}
