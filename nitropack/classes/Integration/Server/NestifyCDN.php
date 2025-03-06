<?php

namespace NitroPack\Integration\Server;

/* Purge Nestify CDN cache on NitroPack purge cache */

class NestifyCDN {
	const STAGE = "very_early";
	public function init( $stage ) {
		if ( defined( 'NESTIFY_CDN_SITE_ID' ) ) {
			add_action( 'nitropack_execute_purge_url', [ $this, 'log_purge_action' ], 10, 1 );
			add_action( 'nitropack_execute_purge_all', [ $this, 'log_purge_action' ], 10, 1 );
		}
	}
	/* Purge Nestify CDN cache on NitroPack purge cache. Used in WordPress Plugin called CDN Cache Helper */
	public function log_purge_action( $url = 'all' ) {
		static $files = array();
		static $shutdown_hook_registered = false;

		if ( $url !== '' ) {
			$files[] = $url;
		}

		if ( ! $shutdown_hook_registered ) {
			try {
				$siteConfig = nitropack_get_site_config();
				if ( $siteConfig && ! empty( $siteConfig['home_url'] ) ) {
					$data = array( 'url' => $_SERVER['HTTP_HOST'], 'files' => $files );
					$cdn_url = 'https://my.nestify.io/cdn/purge/' . NESTIFY_CDN_SITE_ID . '/purge';
					$client = new \NitroPack\HttpClient\HttpClient( $cdn_url );
					$client->setHeader( "Accept", "application/json" );
					$client->setHeader( "Content-Type", "application/json" );
					$client->setPostData( json_encode( $data ) );
					$client->timeout = 5;
					$client->fetch( false, "POST" );
				}

			} catch (\Exception $e) {

				error_log( ' Exception: ' . $e->getMessage() );
			}
			$shutdown_hook_registered = true;
		}
	}
}
