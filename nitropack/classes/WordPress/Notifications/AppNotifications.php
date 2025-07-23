<?php

namespace NitroPack\WordPress\Notifications;
use NitroPack\HttpClient\HttpClient;
use \NitroPack\SDK\Filesystem;

/* 
 * Class AppNotifications
 *
 * This class handles the notification.json file, mostly used for app notifications.
 *
 * @package NitroPack\WordPress\AppNotifications
 */

class AppNotifications {
	private static $instance = NULL;
	private $cacheTtl = 3600;
	private $getSiteId;
	private $notifications;

	public function __construct() {
		$this->getSiteId = get_nitropack()->getSiteId();
		$this->notifications = NULL;
	}
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new AppNotifications();
		}

		return self::$instance;
	}
	public function get( $type = NULL ) {
		if ( $this->notifications === NULL ) {
			$this->load();
		}

		if ( isset( $this->notifications[ $this->getSiteId ] ) ) {
			$result = $this->notifications[ $this->getSiteId ];
		
			if ( $type ) {
				$notifications = isset( $result['notifications'][ $type ] ) ? $result['notifications'][ $type ] : [];
			} else {
				$notifications = $result['notifications'];
			}
		} else {
			$notifications = [];
		}

		return apply_filters( 'get_nitropack_notifications', $notifications, $type );
	}

	private function load() {
		$this->notifications = [];

		$notificationsFile = nitropack_trailingslashit( NITROPACK_DATA_DIR ) . 'notifications.json';
		if ( Filesystem::fileExists( $notificationsFile ) ) {
			$this->notifications = json_decode( Filesystem::fileGetContents( $notificationsFile ), true );
			if ( ! empty( $this->notifications ) && isset( $this->notifications[ $this->getSiteId ] ) ) {
				$result = $this->notifications[ $this->getSiteId ];
				if ( $result['last_modified'] + $this->cacheTtl > time() ) { // The cache is still fresh
					$this->removeExpiredSystemNotifications();
					return;
				}
			}
		}

		if ( get_nitropack()->isConnected() ) {
			try {
				$result = $this->fetch();
				$this->notifications[ $this->getSiteId ] = [ 
					'last_modified' => time(),
					'notifications' => $result
				];
				Filesystem::filePutContents( $notificationsFile, json_encode( $this->notifications ) );
			} catch (\Exception $e) {
				$this->notifications[ $this->getSiteId ] = [ // We need this entry in order to make use of the cache logic
					'last_modified' => time(),
					'error' => $e->getMessage(),
					'notifications' => []
				];
				Filesystem::filePutContents( $notificationsFile, json_encode( $this->notifications ) );
			}
		}
	}

	private function fetch() {
		$notificationsUrl = get_nitropack_integration_url( 'notifications_json' );
		$client = new HttpClient( $notificationsUrl );
		$client->setHeader( "x-nitro-platform", "wordpress" );
		$client->fetch();
		$resp = $client->getStatusCode() == 200 ? json_decode( $client->getBody(), true ) : false;
		return $resp ? $resp['notifications'] : [];
	}

	private function removeExpiredSystemNotifications() {
		if ( isset( $this->notifications[ $this->getSiteId ]['notifications']['system'] ) ) {
			date_default_timezone_set( 'UTC' );
			foreach ( $this->notifications[ $this->getSiteId ]['notifications']['system'] as $key => $notification ) {
				if ( strtotime( $notification['end_date'] ) < time() ) {
					unset( $this->notifications[ $this->getSiteId ]['notifications']['system'][ $key ] );
				}
			}
		}
	}
}