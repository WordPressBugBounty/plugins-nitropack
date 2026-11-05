<?php

namespace NitroPack\Integration\Plugin;
use NitroPack\HttpClient\HttpClient;
class Wordfence {
	const STAGE = "late";

	public static function isActive() {
		return defined( 'WORDFENCE_VERSION' ) && class_exists( \wfConfig::class);
	}

	public function init( $stage ) {
		if ( ! self::isActive() || ! get_nitropack()->isConnected() ) {
			return;
		}

		add_action( 'admin_init', [ $this, 'allowlist_firewall_ips' ], 11 );
	}

	/**
	 * Returns the IPs managed by NitroPack in the Wordfence allowlist.
	 * Cache the result for 1 day.
	 * @return array
	 */
	private static function get_allowlisted_ips() {
		$cached = get_transient( 'nitropack-wordfence-ips' );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		try {
			$http = new HttpClient( 'https://nitropack.io/page/ips' );
			$http->timeout = 10;
			$http->fetch();

			if ( $http->getStatusCode() == 200 ) {
				$fetched = preg_split( '/\s+/', trim( (string) $http->getBody(), " \t\n\r\0\x0B" ), -1, PREG_SPLIT_NO_EMPTY );
				$fetched = array_values( array_filter( $fetched, static function ( $ip ) {
					return filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
				} ) );

				if ( ! empty( $fetched ) ) {
					set_transient( 'nitropack-wordfence-ips', $fetched, DAY_IN_SECONDS );
					return $fetched;
				}
			}
		} catch ( \Exception $e ) {
			// Unreachable endpoint: no IPs are added or removed.
		}

		return [];
	}

	/**
	 * Removes NitroPack's IPs from the Wordfence firewall allowlist.
	 * Called when NitroPack is deactivated or disconnected.
	 *
	 * @return void
	 */
	public static function remove_allowlisted_ips() {
		if ( ! self::isActive() ) {
			return;
		}

		$ips = self::get_allowlisted_ips();
		if ( empty( $ips ) ) {
			return;
		}

		$current = array_filter( array_map( 'trim', explode( ',', (string) \wfConfig::get( 'whitelisted', '' ) ) ) );
		$remaining = array_diff( $current, $ips );

		if ( count( $remaining ) === count( $current ) ) {
			return;
		}

		\wfConfig::set( 'whitelisted', implode( ',', $remaining ) );
	}

	public function allowlist_firewall_ips() {
		$ips = self::get_allowlisted_ips();

		if ( empty( $ips ) ) {
			return;
		}

		$current = array_filter( array_map( 'trim', explode( ',', (string) \wfConfig::get( 'whitelisted', '' ) ) ) );
		$missing = array_diff( $ips, $current );

		if ( empty( $missing ) ) {
			return;
		}

		\wfConfig::set( 'whitelisted', implode( ',', array_merge( $current, $missing ) ) );
	}

}
