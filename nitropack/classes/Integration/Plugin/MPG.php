<?php

namespace NitroPack\Integration\Plugin;

/**
 * Multi Pages Generator plugin integration class
 *
 * @package NitroPack\Integration\Plugin
 * @since [1.18.2]
 */
class MPG {
	const STAGE = "early";

	/* Check for free or premium version of MPG */
	public static function isActive() {
		return is_plugin_active( 'multiple-pages-generator-by-porthas/porthas-multi-pages-generator.php' ) || is_plugin_active( 'multi-pages-plugin-premium/porthas-multi-pages-generator.php' );
	}

	public function init( $stage ) {
		if ( ! self::isActive() )
			return;

		add_action( 'wp', [ $this, 'is_mpg_404' ] );
	}
	/**
	 * Checks if the page is a 404 and calls the MPG function which handles the 404 error to prevent
	 * our nitropack_passes_page_requirements() for positive check for is_404() and optimize the page.
	 *
	 * @return void
	 * @since [1.18.2]
	 */
	public function is_mpg_404() {
		if ( class_exists( 'MPG_CoreController' ) ) {
			global $wp_query;
			if ( $wp_query->is_404 === true || ( ! empty( $wp_query->query['error'] ) && $wp_query->query['error'] == '404' ) ) {
				\MPG_CoreController::mpg_view_multipages_standard();
			}
		}
	}
}
