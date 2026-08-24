<?php

namespace NitroPack\WordPress;
use NitroPack\Integration\Plugin;
/**
 * Sitemap is only used in CacheWarmup.php when the user switches it ON. Checks if we have SEO plugin with sitemap and if so, uses that one instead.
 * For example, if Yoast plugin is present - it will use Yoast sitemap.xml and it will display a tooltip message in Cache Warmup setting.
 */
class Sitemap {
	/**
	 * Checks if any of the supported sitemap plugins are active.
	 *
	 * @return bool True if any supported sitemap plugin is active, false otherwise.
	 */
	public function active_sitemap_plugins() {
		return
			Plugin\YoastSEO::isActive() ||
			Plugin\JetPackNP::isActive() ||
			Plugin\SquirrlySEO::isActive() ||
			Plugin\RankMathNP::isActive();
	}

	public function get_site_maps() {
		$sitemapUrls['YoastSEO'] = Plugin\YoastSEO::getSitemapURL();
		$sitemapUrls['JetPack'] = Plugin\JetPackNP::getSitemapURL();
		$sitemapUrls['SquirrlySEO'] = Plugin\SquirrlySEO::getSitemapURL();
		$sitemapUrls['RankMath'] = Plugin\RankMathNP::getSitemapURL();

		return $sitemapUrls;
	}
	/**
	 * Retrieves the default sitemap URL for WordPress.
	 *
	 * @return string|false The default sitemap URL or false if not available.
	 */
	public function get_default_sitemap() {

		$defaultSiteMap = Plugin\WPCacheHelper::getSitemapURL();
		if ( $defaultSiteMap ) {
			$this->set_sitemap_indication_msg( 'WordPress', $defaultSiteMap );
			return $defaultSiteMap;
		}

		return false;
	}
	/**
	 * Evaluates the sitemap to be used for cache warmup based on the available sitemap URLs.
	 *
	 * @param array|null $sitemapUrls An associative array of sitemap URLs from different providers.
	 * @return string|false The selected sitemap URL or false if no suitable sitemap is found.
	 */
	public function evaluate_warmup_sitemap( ?array $sitemapUrls = null ) {

		$sitemapProviders = array(
			'YoastSEO' => 'Yoast!',
			'SquirrlySEO' => 'Squirrly SEO',
			'RankMath' => 'Rank Math',
			'JetPack' => 'Jetpack',
		);

		foreach ( $sitemapProviders as $provider => $name ) {
			if ( isset( $sitemapUrls[ $provider ] ) && $sitemapUrls[ $provider ] ) {
				$this->set_sitemap_indication_msg( $name, $sitemapUrls[ $provider ] );
				return $sitemapUrls[ $provider ];
			}
		}

		return $this->get_default_sitemap();
	}
	/**
	 * Display tooltip information in Cache Warmup setting
	 *
	 * @param string $pluginName
	 * @param string $sitemapURL
	 * @return void
	 */
	private function set_sitemap_indication_msg( string $pluginName, string $sitemapURL ) {
		$sitemapURI = explode( "/", parse_url( $sitemapURL, PHP_URL_PATH ) );
		$msg = $sitemapURI[1] . ' used by ' . $pluginName;
		update_option( 'nitropack-warmup-sitemap', $msg );
	}

}