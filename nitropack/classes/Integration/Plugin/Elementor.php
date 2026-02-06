<?php

namespace NitroPack\Integration\Plugin;

class Elementor {

	const STAGE = "late";

	public static function isActive() {
		$activePlugins = apply_filters('active_plugins', get_option('active_plugins'));
		if (defined('ELEMENTOR_PRO_VERSION') || in_array( 'elementor-pro/elementor-pro.php', $activePlugins )) {
			return true;
		}
		if (defined('ELEMENTOR_VERSION') || in_array( 'elementor/elementor.php', $activePlugins )) {
			return true;
		}
		return false;
	}

	public function init($stage) {
		if ( ! self::isActive() ) {
			return;
		}

		add_action( 'save_post', array($this, 'purge_cache_on_custom_code_snippet_update'), 10, 3 );
		add_action( 'elementor/document/after_save', array($this, 'purge_on_kit_update'), 10, 2 );

		// Add AJAX handler for Elementor Tools page cache clearing
		add_action( 'wp_ajax_nitropack_elementor_clear_cache', array($this, 'ajax_clear_cache') );
	}

	public function purge_cache_on_custom_code_snippet_update( $post_id, $post, $update ) {

		if ( 'elementor_snippet' !== $post->post_type || defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || 'auto-draft' === $post->post_status ) {
			return;
		}

		if( strpos( wp_get_raw_referer(), 'post-new' ) > 0 ) {

			if ( empty( $_POST['code'] ) ) {
				return;
			}

			/* If new snippet is added */
			nitropack_sdk_invalidate(NULL, NULL, 'Elementor Custom Code Snippet Added');
		} else {

			/* If old snippet is Updated */
			nitropack_sdk_invalidate(NULL, NULL, 'Elementor Custom Code Snippet Updated');
		}
	}

	/**
	 * Handle Global Settings Updates Only (Document of type Kit)
	 * Hook: 'elementor/document/after_save'
	 */
	public function purge_on_kit_update( $document, $data ) {
		// Check if the document being saved is a "Kit" (Global Settings)
		// We strictly check the class name or type to ensure it's not a normal page.
		$is_kit = (
			$document instanceof \Elementor\Core\Kits\Documents\Kit ||
			$document->get_type() === 'kit'
		);

		if (! $is_kit) {
			return;
		}

		$active_kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();

		if ( $document->get_id() != $active_kit_id ) {
			return;
		}

		if ( function_exists('nitropack_sdk_purge') && nitropack_sdk_purge( NULL, NULL, 'Light purge, because of Elementor Settings/CSS Update', \NitroPack\SDK\PurgeType::LIGHT_PURGE ) ) {
			\NitroPack\WordPress\NitroPack::getInstance()->getLogger()->notice('Light purge, because of Elementor Settings/CSS Update');
		}
	}

	/**
	 * AJAX handler for clearing NitroPack cache from Elementor Tools page
	 * Triggered when user clicks Clear Cache button on Elementor Tools admin page
	 * Hook: 'wp_ajax_nitropack_elementor_clear_cache'
	 */
	public function ajax_clear_cache() {
		// Verify nonce for security
		check_ajax_referer( 'nitropack_elementor_clear_cache', 'nonce' );

		// Check user permissions (same as Elementor's manage_options requirement)
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
			return;
		}

		try {
			// Execute NitroPack light purge
			if ( function_exists('nitropack_sdk_purge') ) {
				$result = nitropack_sdk_purge(
					NULL,
					NULL,
					'Light purge triggered from Elementor Tools Clear Cache',
					\NitroPack\SDK\PurgeType::LIGHT_PURGE
				);

				if ( $result ) {
					// Log success
					\NitroPack\WordPress\NitroPack::getInstance()
						->getLogger()
						->notice('Light purge triggered from Elementor Tools Clear Cache');

					wp_send_json_success( array( 'message' => 'NitroPack cache cleared successfully' ) );
				} else {
					wp_send_json_error( array( 'message' => 'NitroPack cache clearing failed' ) );
				}
			} else {
				wp_send_json_error( array( 'message' => 'NitroPack SDK not available' ) );
			}
		} catch ( \Exception $e ) {
			// Log error
			\NitroPack\WordPress\NitroPack::getInstance()
				->getLogger()
				->error('Error clearing NitroPack cache from Elementor Tools: ' . $e->getMessage());

			wp_send_json_error( array( 'message' => 'Error clearing cache: ' . $e->getMessage() ) );
		}
	}
}
