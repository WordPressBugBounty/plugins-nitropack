<?php
namespace NitroPack\WordPress\Settings;

use NitroPack\WordPress\Settings\OptimizationLevel;

/** Handles generation of "Preview Site" (onboarding process => /admin.php?page=nitropack&onboarding=1) for different optimization levels
 * Passes automatically onboarding for all current users when they visit the nitropack page
 * Enables caching for logged-in users in preview mode
 * AJAX handlers to check if preview cache is ready and to finalize onboarding
 */
class GeneratePreview {
	private static $instance = NULL;
	public function __construct() {
		add_action( 'admin_init', [ $this, 'pass_onboarding_for_current_users' ] );
		add_filter( 'nitropack_passes_cookie_requirements', [ $this, 'enable_cache_for_logged_in_preview_users' ] );
		add_action( 'wp_ajax_nitropack_generate_homepage_preview', [ $this, 'nitropack_generate_homepage_preview' ] );
		add_action( 'wp_ajax_nitropack_is_homepage_preview_cached', [ $this, 'nitropack_is_homepage_preview_cached' ] );
		add_action( 'wp_ajax_nitropack_passed_onboarding', [ $this, 'nitropack_passed_onboarding' ] );

	}
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new GeneratePreview();
		}
		return self::$instance;
	}
	/**
	 * Pass onboarding for current users when they visit the nitropack page
	 * and display onboarding for inital users when connect NitroPack
	 * @return void
	 */
	public function pass_onboarding_for_current_users() {
		if ( ! get_nitropack()->isConnected() )
			return;

		$onboarding = get_option( 'nitropack-onboardingPassed' );
		if ( ! empty( $_GET['page'] ) && $_GET['page'] === 'nitropack' && ! empty( $_GET['onboarding'] ) && $onboarding !== '1' ) {
			update_option( 'nitropack-onboardingPassed', 0 );
		} else if ( ! empty( $_GET['page'] ) && $_GET['page'] === 'nitropack' ) {
			update_option( 'nitropack-onboardingPassed', 1 );
		}
	}
	/**
	 * Enable caching for logged-in users in preview mode
	 * @param boolean $passes
	 * @return boolean
	 */
	public function enable_cache_for_logged_in_preview_users( $passes ): bool {
		$isUserLoggedIn = nitropack_is_logged_in();
		if ( $isUserLoggedIn && ! empty( $_GET['previewmode'] ) ) {
			return true;
		}
		return $passes;
	}

	/**
	 * Get homepage preview URL with selected optimization level.
	 * Sort $_GET alphabetically to ensure consistent URL for caching (first previewmode, then testnitro)
	 * @return string
	 */
	public function get_homepage_preview_url( $mode_name = null ): string {
		$siteConfig = nitropack_get_site_config();
		
		if ( ! $mode_name ) {
			$optimization_level_class = new OptimizationLevel();
			$mode_name = $optimization_level_class->fetch_optimization_name();
		}
		$home_url_preview = $siteConfig["home_url"] . "/?previewmode=" . $mode_name . "&testnitro=1";
		return $home_url_preview;
	}

	/**
	 * Check if preview cache is ready (called repeatedly up to 60 sec in preview-site.php)
	 * @return void AJAX response
	 */
	public function nitropack_is_homepage_preview_cached(): void {
		nitropack_verify_ajax_nonce( $_REQUEST );

		$mode_name = isset( $_POST['mode_name'] ) ? sanitize_text_field( $_POST['mode_name'] ) : NULL;
		$home_url_preview = $this->get_homepage_preview_url( $mode_name );

		$siteConfig = nitropack_get_site_config();

		if ( null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"], $home_url_preview ) ) {
			/* IMPORTANT: Remove the AJAX check which is used in isAJAXRequest() (nitropack/nitropack-sdk/NitroPack/SDK/NitroPack.php). 
			 * If not done, it will return 403 to our API servers and cache will not be fetched.
			 */
			if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' ) {
				unset( $_SERVER['HTTP_X_REQUESTED_WITH'] );
			}
			$hasLocalCache = $nitro->hasLocalCache( false );

			/* If local cache doesn't exist, then check remote cache and force to generate one. */
			if ( ! $hasLocalCache ) {
				try {
					$nitro->hasRemoteCache( 'default', false );
				} catch (\Exception $e) {
					nitropack_json_and_exit( [ "preview" => 0 ] );
				}
			}

			nitropack_json_and_exit( [ "preview" => $hasLocalCache ? 1 : 0 ] );

		} else {
			nitropack_json_and_exit( [ "preview" => 0 ] );
		}
	}

	/**
	 * Finalize onboarding by updating the option and disabling safemode
	 * @return void
	 */
	public function nitropack_passed_onboarding(): void {
		nitropack_verify_ajax_nonce( $_REQUEST );

		update_option( 'nitropack-onboardingPassed', 1 );

		if ( null !== $nitro = get_nitropack_sdk() ) {
			$nitro->disableSafeMode();
		}
		$nitro_url = admin_url( "admin.php?page=nitropack" );

		nitropack_json_and_exit( [ "status" => 1, "redirect" => $nitro_url ] );
	}
}