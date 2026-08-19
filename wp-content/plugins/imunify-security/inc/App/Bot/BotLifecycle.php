<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Activation / deactivation / uninstall for the bot-protection feature.
 *
 * WordPress-facing glue: builds the real collaborators and delegates the
 * install-then-loopback decision to BotShimManager. Also owns the bot
 * WP-Cron schedules and the teardown of every artefact this feature
 * persists — the mu-plugin shim, the rate-limit / block tables, and the
 * bot-owned options.
 *
 * @since 4.0.0
 */
class BotLifecycle {

	/**
	 * WP-Cron hook name for periodic storage cleanup.
	 */
	const CRON_HOOK_STORAGE_CLEANUP = 'imunify_security_bot_storage_cleanup';

	/**
	 * Custom WP-Cron recurrence: every 6 hours (4x/day).
	 */
	const CLEANUP_INTERVAL_SECONDS = 21600;

	/**
	 * Activation entry point. Installs the mu-plugin shim only when the
	 * server-level ai_bot_protection gate is on.
	 *
	 * @return bool Whether the shim is installed and passed the safety test,
	 *              or true when the feature gate is off and nothing was installed.
	 */
	public static function activate() {
		$mu_dir = self::muPluginDir();
		if ( '' === $mu_dir ) {
			return false;
		}
		self::scheduleStorageCleanup();
		SignatureRefresher::scheduleHooks();
		$home_url       = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? (string) WP_CONTENT_DIR : '';
		$cfg            = MuLoader::loadPluginConfig( $wp_content_dir );
		return self::shimManager( $mu_dir )->install( $home_url, $cfg );
	}

	/**
	 * Deactivation entry point. Clears cron and removes the shim — temporary
	 * artefacts only, never persisted data.
	 *
	 * @return bool
	 */
	public static function deactivate() {
		self::unscheduleStorageCleanup();
		$mu_dir = self::muPluginDir();
		if ( '' === $mu_dir ) {
			return true;
		}
		return self::shimManager( $mu_dir )->remove();
	}

	/**
	 * Remove every bot-protection artefact that survives deactivation: the
	 * rate-limit / block tables, the bot-owned options, and the shim.
	 *
	 * @return void
	 */
	public static function uninstall() {
		self::unscheduleStorageCleanup();
		self::dropBotTables();
		delete_option( SignatureRefresher::MIRROR_MD5_OPTION );
		delete_option( MuPluginSelfHealer::OPTION_NAME );
		delete_option( LoopbackStatus::OPTION_NAME );
		$mu_dir = self::muPluginDir();
		if ( '' !== $mu_dir ) {
			self::shimManager( $mu_dir )->remove();
		}
	}

	/**
	 * Register the cron_schedules filter and the cleanup action hooks.
	 *
	 * Called from the mu-plugin bootstrap (MuLoader) on every request so the
	 * custom interval and action handlers are always available to WP-Cron,
	 * even on requests where the main plugin file is not loaded yet.
	 *
	 * @return void
	 */
	public static function registerCleanupHooks() {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'cron_schedules', array( __CLASS__, 'addCleanupSchedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		SignatureRefresher::scheduleHooks();
		add_action( self::CRON_HOOK_STORAGE_CLEANUP, array( __CLASS__, 'runStorageCleanup' ) );
		add_action( SignatureRefresher::CRON_HOOK_REFRESH, array( __CLASS__, 'runSignatureRefresh' ) );
	}

	/**
	 * Add the 'imunify_six_hours' recurrence to WP-Cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function addCleanupSchedule( $schedules ) {
		if ( ! isset( $schedules['imunify_six_hours'] ) ) {
			$schedules['imunify_six_hours'] = array(
				'interval' => self::CLEANUP_INTERVAL_SECONDS,
				'display'  => 'Every 6 hours',
			);
		}
		return $schedules;
	}

	/**
	 * WP-Cron callback: run storage cleanup in a fail-safe wrapper.
	 *
	 * Accepts an optional $wp_content_dir to allow unit testing without
	 * relying on the WP_CONTENT_DIR constant.
	 *
	 * @param string|null $wp_content_dir Absolute path to wp-content, or null to use WP_CONTENT_DIR.
	 * @return void
	 */
	public static function runStorageCleanup( $wp_content_dir = null ) {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return;
		}
		if ( null === $wp_content_dir ) {
			if ( ! defined( 'WP_CONTENT_DIR' ) ) {
				return;
			}
			$wp_content_dir = (string) WP_CONTENT_DIR;
		}
		// Skip when protection is off: cleanup() rebuilds the MEMORY counter table
		// via DROP+CREATE, so an ungated run recreates an empty table the instant
		// the gate closes. Skip rather than self-cancel like the refresh cron —
		// this event is scheduled only from activate(), so unscheduling it here
		// would strand it until the next reactivation.
		if ( ! MuLoader::loadPluginConfig( $wp_content_dir )->isAiBotProtectionEnabled() ) {
			return;
		}
		if ( ! OptOutFlag::load( $wp_content_dir )->isEnabled() ) {
			return;
		}
		try {
			$storage = DbStorageFactory::detect( $wpdb );
			$storage['block']->cleanup();
			$storage['counter']->cleanup();
		} catch ( \Exception $e ) {
			$transient_key = 'imunify_security_error_bot_cleanup_failed';
			if ( function_exists( 'get_transient' ) && ! get_transient( $transient_key ) ) {
				if ( function_exists( 'set_transient' ) ) {
					set_transient( $transient_key, true, 3600 );
				}
				do_action(
					'imunify_security_set_error',
					E_WARNING,
					'Bot storage cleanup failed: ' . $e->getMessage(),
					__FILE__,
					__LINE__,
					array(
						'fingerprint' => array( 'bot_storage_cleanup_failed', get_class( $e ) ),
					)
				);
			}
		}
	}

	/**
	 * WP-Cron callback: pull the latest bot-data overlay from the mirror.
	 *
	 * Accepts an optional $wp_content_dir to allow unit testing without
	 * relying on the WP_CONTENT_DIR constant.
	 *
	 * @param string|null $wp_content_dir Absolute path to wp-content, or null to use WP_CONTENT_DIR.
	 * @return void
	 */
	public static function runSignatureRefresh( $wp_content_dir = null ) {
		if ( null === $wp_content_dir ) {
			if ( ! defined( 'WP_CONTENT_DIR' ) ) {
				return;
			}
			$wp_content_dir = (string) WP_CONTENT_DIR;
		}
		if ( ! MuLoader::loadPluginConfig( $wp_content_dir )->isAiBotProtectionEnabled() ) {
			self::unscheduleRefresh();
			return;
		}
		if ( ! OptOutFlag::load( $wp_content_dir )->isEnabled() ) {
			self::unscheduleRefresh();
			return;
		}
		$overlay_dir = rtrim( $wp_content_dir, '/' ) . '/imunify-security/bot-data';
		$refresher   = new SignatureRefresher( $overlay_dir, new WpHttpClient() );
		$refresher->refreshFromMirror();
	}

	/**
	 * Schedule the cleanup cron event if not already scheduled.
	 *
	 * @return void
	 */
	private static function scheduleStorageCleanup() {
		if ( ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}
		// Ensure the custom recurrence is registered before scheduling.
		// activate() runs from the main plugin hooks (not MuLoader),
		// so registerCleanupHooks() hasn't fired yet for this request.
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'cron_schedules', array( __CLASS__, 'addCleanupSchedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_STORAGE_CLEANUP ) ) {
			wp_schedule_event( time(), 'imunify_six_hours', self::CRON_HOOK_STORAGE_CLEANUP );
		}
	}

	/**
	 * Remove the cleanup cron events.
	 *
	 * @return void
	 */
	private static function unscheduleStorageCleanup() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK_STORAGE_CLEANUP );
			wp_clear_scheduled_hook( SignatureRefresher::CRON_HOOK_REFRESH );
		}
	}

	/**
	 * Remove only the signature-refresh cron event.
	 *
	 * Called from runSignatureRefresh() when bot protection is disabled so
	 * the cron self-cancels rather than firing harmlessly every 6 hours.
	 *
	 * @return void
	 */
	private static function unscheduleRefresh() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( SignatureRefresher::CRON_HOOK_REFRESH );
		}
	}

	/**
	 * Drop all bot-protection database tables.
	 *
	 * Suppresses errors so a missing table or DB permission issue never
	 * prevents the rest of the uninstall from completing.
	 *
	 * @return void
	 */
	private static function dropBotTables() {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return;
		}
		$suppress = $wpdb->suppress_errors( true );
		$prefix   = $wpdb->prefix;
		$tables   = array(
			$prefix . 'imunify_bot_rl',
			$prefix . 'imunify_bot_blocks',
			$prefix . 'imunify_bot_blocks_active',
			$prefix . 'imunify_bot_violations',
		);
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
		$wpdb->suppress_errors( $suppress );
	}

	/**
	 * Build the shim manager with its real collaborators.
	 *
	 * @param string $mu_dir Absolute path to wp-content/mu-plugins.
	 * @return BotShimManager
	 */
	private static function shimManager( $mu_dir ) {
		return new BotShimManager( new MuPluginInstaller( $mu_dir ), new LoopbackSafetyTest(), new LoopbackStatus() );
	}

	/**
	 * Resolve WPMU_PLUGIN_DIR, or '' when unavailable.
	 *
	 * @return string
	 */
	private static function muPluginDir() {
		return defined( 'WPMU_PLUGIN_DIR' ) && '' !== WPMU_PLUGIN_DIR
			? (string) WPMU_PLUGIN_DIR
			: '';
	}
}
