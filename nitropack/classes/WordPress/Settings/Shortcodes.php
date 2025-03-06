<?php

namespace NitroPack\WordPress\Settings;

/**
 * Class Shortcodes
 *
 * The shortcode settings are stored in config.json!
 *
 * @package NitroPack\WordPress\Settings
 */
class Shortcodes {
	public function __construct() {
		add_action( 'wp_ajax_nitropack_set_ajax_shortcodes_ajax', [ $this, 'nitropack_set_ajax_shortcodes_ajax' ] );
	}
	/**
	 * Get NitroPack configuration for ajaxShortcodes
	 *
	 * @return array|null
	 */
	private function get_nitropack_config_for_ajaxShortcodes() {
		try {
			$nitropack = get_nitropack();
			if ( ! $nitropack ) {
				throw new \Exception( 'NitroPack instance not found' );
			}

			$siteConfig = $nitropack->Config->get();
			$configKey = \NitroPack\WordPress\NitroPack::getConfigKey();

			return isset( $siteConfig[ $configKey ]['options_cache']['ajaxShortcodes'] ) ? $siteConfig[ $configKey ]['options_cache']['ajaxShortcodes'] : null;
		} catch (\Exception $e) {
			error_log( 'NitroPack Config Error: ' . $e->getMessage() );
			return null;
		}
	}
	/**
	 * Predefined WooCommerce shortcodes to restrict
	 *
	 * @return array
	 */
	private function get_restricted_shortcodes() {
		return [ 
			'woocommerce_cart',
			'woocommerce_my_account',
			'woocommerce_order_tracking',
			'woocommerce_checkout',
			// Add more shortcodes to restrict as needed
		];
	}
	/**
	 * Generate shortcode options HTML
	 *
	 * @param array $shortcode_tags
	 * @param array $ajax_shortcodes_list
	 * @return string
	 */
	private function generate_shortcode_options( $shortcode_tags, $ajax_shortcodes_list ) {
		$restricted_shortcodes = $this->get_restricted_shortcodes();
		$html = '';

		foreach ( $shortcode_tags as $shortcode => $_ ) {
			if ( in_array( $shortcode, $restricted_shortcodes ) ) {
				continue;
			}

			$selected = in_array( $shortcode, $ajax_shortcodes_list ) ? 'selected="selected"' : '';
			$html .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $shortcode ),
				$selected,
				esc_html( $shortcode )
			);
		}

		return $html;
	}

	/**
	 * Generate options for manually added shortcodes
	 *
	 * @param array $freely_added_shortcodes
	 * @return string
	 */
	private function generate_manual_shortcode_options( $freely_added_shortcodes ) {
		return implode( '', array_map( function ($shortcode) {
			return sprintf(
				'<option value="%s" selected="selected">%s</option>',
				esc_attr( $shortcode ),
				esc_html( $shortcode )
			);
		}, $freely_added_shortcodes ) );
	}

	/**
	 * List all available AJAX shortcodes
	 *
	 * @return string
	 */
	private function list_ajax_shortcodes() {
		global $shortcode_tags;

		$config = $this->get_nitropack_config_for_ajaxShortcodes();
		if ( ! $config ) {
			return '<option value="" disabled>Configuration not available</option>';
		}

		$ajax_shortcodes_list = isset( $config['shortcodes'] ) ? $config['shortcodes'] : [];
		$freely_added_shortcodes = array_diff( $ajax_shortcodes_list, array_keys( $shortcode_tags ) );

		$html = $this->generate_shortcode_options( $shortcode_tags, $ajax_shortcodes_list );

		if ( ! empty( $freely_added_shortcodes ) ) {
			$html .= $this->generate_manual_shortcode_options( $freely_added_shortcodes );
		}

		return $html;
	}

	/**
	 * Render AJAX shortcodes settings in the admin panel (dashboard.php and dashboard-oneclick.php)
	 */
	public function render() {
		$config = $this->get_nitropack_config_for_ajaxShortcodes();
		if ( ! $config ) {
			echo '<div class="error">Unable to load NitroPack Ajax Shortcodes configuration</div>';
			return;
		}

		$ajax_shortcodes_enabled = isset( $config['enabled'] ) ? $config['enabled'] : false;
		$shortcode_container_shown = $ajax_shortcodes_enabled ? '' : 'hidden';
		?>
		<div class="nitro-option-main">
			<div class="text-box">
				<h6><?php esc_html_e( 'Shortcodes exclusions', 'nitropack' ); ?></h6>
				<p><?php esc_html_e( 'Load widgets, feeds, and any shortcode with AJAX to bypass the cache and always show the latest content.', 'nitropack' ); ?>
				</p>
			</div>
			<label class="inline-flex items-center cursor-pointer ml-auto">
				<input type="checkbox" value="" class="sr-only peer" name="ajax_shortcodes" id="ajax-shortcodes" <?php echo ( $ajax_shortcodes_enabled ? 'checked' : '' ) ?>>
				<div class="toggle"></div>
			</label>
		</div>
		<div class="ajax-shortcodes <?php echo esc_attr( $shortcode_container_shown ); ?>">
			<div class="select-wrapper">
				<select class="shortcode-select" name="nitropack-ajaxShortcodes" id="ajax-shortcodes-dropdown" multiple>
					<?php echo $this->list_ajax_shortcodes(); ?>
				</select>
				<button class="btn btn-primary" id="save-shortcodes">
					<?php esc_html_e( 'Save', 'nitropack' ); ?>
				</button>
			</div>
		</div>

		<?php
	}
	public function nitropack_set_ajax_shortcodes_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );

		$new_shortcodes = isset( $_POST['shortcodes'] ) ? $_POST['shortcodes'] : null;
		$enabled = isset( $_POST['enabled'] ) ? $_POST['enabled'] : null;

		if ( $new_shortcodes === null && $enabled === null ) {
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => nitropack_admin_toast_msgs( 'error' )
			) );
		}
		$nitropack = get_nitropack();
		$siteConfig = $nitropack->Config->get();
		$configKey = \NitroPack\WordPress\NitroPack::getConfigKey();

		if ( ! is_null( $enabled ) ) {
			$siteConfig[ $configKey ]['options_cache']['ajaxShortcodes']['enabled'] = $enabled === '1';
		}

		if ( ! is_null( $new_shortcodes ) ) {
			
			/* If the user has cleared the input field, we should set the shortcodes to an empty array */
			$existing_options = ( is_array( $new_shortcodes ) && $new_shortcodes[0] !== '[]' ) ? $new_shortcodes : [];
			
			/* update config.json */
			$siteConfig[ $configKey ]['options_cache']['ajaxShortcodes']['shortcodes'] = $existing_options;
		}
		$updated = $nitropack->Config->set( $siteConfig );

		if ( $updated ) {
			nitropack_json_and_exit( array( "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ) ) );
		} else {
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => nitropack_admin_toast_msgs( 'error' )
			) );
		}
	}
}
