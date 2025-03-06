<?php

/**
 * Cron Class
 *
 * @package nitropack
 */

namespace NitroPack\WordPress;

/**
 * Cron class for sheduling events.
 */
class Cron {
	/**
	 * Init class.
	 */
	public function __construct() {
		add_action( 'nitropack_remove_old_logs', [ $this, 'remove_old_logs' ] );
	}

	/**
	 * Schedule events.
	 *
	 * @return void
	 */
	public static function schedule_events() {

		if ( ! wp_next_scheduled( 'nitropack_remove_old_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'nitropack_remove_old_logs' );
		}
	}

	/**
	 * Unschedule events when plugin is deactivated.
	 *
	 * @return void
	 */
	public static function unschedule_events() {
		$timestamp = wp_next_scheduled( 'nitropack_remove_old_logs' );
		wp_unschedule_event( $timestamp, 'nitropack_remove_old_logs' );
	}

	/**
	 * Remove old logs .csv and the .zip archive.
	 * Default to 14 days.
	 * Can be filtered with the 'nitropack_remove_old_logs_interval' filter by seconds.
	 * @return void
	 */
	public function remove_old_logs() {
		$files = glob( NITROPACK_LOGS_DATA_DIR . '/*.{csv,zip}', GLOB_BRACE );
		if ( ! $files ) {
			return;
		}
		$now = time();
		$days = 14;
		$seconds = $days * 24 * 60 * 60;

		$seconds = apply_filters( 'nitropack_remove_old_logs_interval', $seconds );

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				if ( $now - filemtime( $file ) >= $seconds ) {
					unlink( $file );
				}
			}
		}

	}
}
