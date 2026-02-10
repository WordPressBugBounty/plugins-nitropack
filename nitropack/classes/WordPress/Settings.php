<?php

namespace NitroPack\WordPress;
use NitroPack\WordPress\Settings\Subscription;
use NitroPack\WordPress\Settings\PurgeCache;
use NitroPack\WordPress\Settings\CacheWarmup;
use NitroPack\WordPress\Settings\Optimizations;
use NitroPack\WordPress\Settings\OptimizationLevel;
use NitroPack\WordPress\Settings\AutoPurge;
use NitroPack\WordPress\Settings\CPTOptimization;
use NitroPack\WordPress\Settings\GeneratePreview;
use NitroPack\WordPress\Settings\TestMode;
use NitroPack\WordPress\Settings\HTMLCompression;
use NitroPack\WordPress\Settings\BeaverBuilder;
use NitroPack\WordPress\Settings\CartCache;
use NitroPack\WordPress\Settings\StockRefresh;
use NitroPack\WordPress\Settings\EditorClearCache;
use NitroPack\WordPress\Settings\Shortcodes;
use NitroPack\WordPress\Settings\Logger;
use NitroPack\WordPress\Settings\SystemReport;

/**
 * Class Settings
 *
 * This class handles the configuration settings for NitroPack.
 *
 * @package NitroPack\WordPress
 */
class Settings {
	/**
	 * @var array $settings Configuration settings for NitroPack.
	 * 
	 * The settings array includes the following keys:
	 * - 'nitropack-webhookToken': (mixed) Token for NitroPack webhook, default is null.
	 * - 'nitropack-enableCompression': (int) Flag to enable compression, default is -1.
	 * - 'nitropack-autoCachePurge': (int) Flag to enable automatic cache purge, default is 1.
	 * - 'nitropack-cacheableObjectTypes': (array) List of cacheable object types, default is an empty array but gets updated immediately to all CPTs.
	 * - 'nitropack-distribution': (string) Distribution type, default is 'regular'.
	 */
	private $settings;

	public $cache_warmup;
	/**
	 * Grabs Subscription class
	 * @var Subscription
	 */
	public $subscription;
	/**
	 * Grabs PurgeCache class
	 * @var PurgeCache
	 */
	public $purge_cache;
	/**
	 * Grabs Optimizations class
	 * @var Optimizations
	 */
	public $optimizations;
	/**
	 * Grabs OptimizationLevel class
	 * @var OptimizationLevel
	 */
	public $optimization_level;
	/**
	 * Grabs AutoPurge class
	 * @var AutoPurge
	 */
	public $auto_purge;

	public $cpt_optimization;
	/**
	 * Grabs GeneratePreview class
	 * @var GeneratePreview
	 */
	public $generate_preview;

	/**
	 * Grabs TestMode class
	 * @var TestMode
	 */
	public $test_mode;
	/**
	 * Grabs HTMLCompression class
	 * @var HTMLCompression
	 */
	public $html_compression;
	/**
	 * Grabs BeaverBuilder class
	 * @var BeaverBuilder
	 */
	public $beaver_builder;
	/**
	 * Grabs CartCache class
	 * @var CartCache
	 */
	public $cart_cache;

	/**
	 * Grabs StockRefresh class
	 * @var StockRefresh
	 */
	public $stock_refresh;
	/**
	 * Grabs EditorClearCache class
	 * @var EditorClearCache
	 */
	public $editor_clear_cache;
	/**
	 * Grabs Shortcodes class
	 * @var Shortcodes
	 */
	public $shortcodes;
	public $logger;
	public $system_report;
	/**
	 * Settings constructor.
	 *
	 * Initializes the default required settings for the NitroPack plugin.
	 */
	function __construct( $config = null ) {
		add_action( 'admin_init', [ $this, 'move_existing_options' ] );
		$this->default_required_settings();
		//initialize each setting
		$this->generate_preview = GeneratePreview::getInstance();
		$this->purge_cache = new PurgeCache();
		$this->subscription = Subscription::getInstance();
		$this->optimizations = Optimizations::getInstance();
		$this->optimization_level = OptimizationLevel::getInstance();
		$this->auto_purge = new AutoPurge();
		$this->cpt_optimization = CPTOptimization::getInstance();
		$this->shortcodes = new Shortcodes();
		$this->cache_warmup = CacheWarmup::getInstance();
		$this->test_mode = TestMode::getInstance();
		$this->html_compression = HTMLCompression::getInstance();
		$this->beaver_builder = new BeaverBuilder();
		$this->cart_cache = new CartCache();
		$this->stock_refresh = StockRefresh::getInstance();
		$this->editor_clear_cache = new EditorClearCache();
		$this->system_report = SystemReport::getInstance();
		$this->logger = new Logger( $config );
	}

	/**
	 * Set default required settings in order for the plugin to work properly.
	 *
	 * @return void
	 */
	private function default_required_settings() {
		$this->settings = [
			'nitropack-webhookToken' => null,
			'nitropack-enableCompression' => -1,
			'nitropack-autoCachePurge' => 1,
			'nitropack-cacheableObjectTypes' => [],
			'nitropack-distribution' => 'regular',
		];
	}

	/**
	 * Refreshes the required settings for the plugin.
	 *
	 * This method updates the options for the webhook token and iterates through
	 * the settings to update options that are not already set in the WordPress
	 * database. If the option 'nitropack-cacheableObjectTypes' is encountered,
	 * it sets the value to the default cacheable object types.
	 *
	 * @param string|null $token Optional. The token to be used for generating the webhook token. Default is null.
	 * @return void
	 */
	public function set_required_settings( $token = null ) {

		if ( $token !== null ) {
			$this->settings['nitropack-webhookToken'] = $token;
		} else {
			// Generate a new webhook token if it is not passed
			$this->generate_webhook_token();
		}

		foreach ( $this->settings as $option => $value ) {
			if ( get_option( $option ) === false && $value !== null ) {
				if ( $option === 'nitropack-cacheableObjectTypes' ) {
					$value = $this->cpt_optimization->nitropack_get_default_cacheable_object_types();
				}
				update_option( $option, $value );
			}
		}
	}
	/**
	 * Generates a webhook token for the NitroPack settings.
	 *
	 * This function retrieves the site configuration and checks if a webhook token
	 * is already set. If a token is provided, it generates a new webhook token using
	 * the site ID from the POST request. If no site ID is provided in the POST request,
	 * it sets the webhook token to null.
	 *
	 * @param string|null $token Optional. The token to be used for generating the webhook token.
	 *                           If not provided, a new token will be generated.
	 */
	public function generate_webhook_token() {
		$siteConfig = nitropack_get_site_config();
		//grab existing from config
		if ( isset( $siteConfig['webhookToken'] ) ) {
			$this->settings['nitropack-webhookToken'] = $siteConfig['webhookToken'];
		} elseif ( isset( $siteConfig['siteId'] ) ) {
			//generate from existing siteId
			$siteId = $siteConfig['siteId'];
			$this->settings['nitropack-webhookToken'] = nitropack_generate_webhook_token( $siteId );
		} elseif ( ! empty( $_POST["siteId"] ) ) {
			//try to generate from POST
			$siteId = $_POST["siteId"];
			$this->settings['nitropack-webhookToken'] = nitropack_generate_webhook_token( $siteId );
		} else {
			$this->settings['nitropack-webhookToken'] = null;
		}
	}

	/**
	 * Move wrongly formatted nitropack options to correct ones and delete old ones.
	 * @return void
	 */
	public function move_existing_options() {
		if ( ! empty( $_GET['page'] ) && $_GET['page'] === 'nitropack' ) {
			$existing_options = [ 'np_warmup_sitemap' => 'nitropack-warmup-sitemap', 'nitropack_minimumLogLevel' => 'nitropack-minimumLogLevel' ];
			foreach ( $existing_options as $option => $new_option ) {
				if ( $old_option = get_option( $option ) ) {
					update_option( $new_option, $old_option );
					delete_option( $option );
				}
			}
		}
	}
}
