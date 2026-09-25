<?php
/**
 * GravityForms Class
 *
 * @package nitropack
 */

namespace NitroPack\Integration\Plugin;

use NitroPack\WordPress\NitroPack;

/**
 * GravityForms Class
 */
class GravityForms {
	const STAGE = 'late';
	const AJAX_ACTION = 'nitropack_gravity_form_ajax';
	const SHORTCODE_AJAX_NONCE_ACTION = 'nitropack_gravity_form_shortcode_output';
	const HONEYPOT_FORMS_OPTION = 'nitropack-gf_honeypot_forms';
	const CACHE_TAG = 'gravityforms';

	private $original_gf_shortcode = null;

	/**
	 * Check if plugin "Gravity Forms" is active
	 *
	 * @return bool
	 */
	public static function isActive() {     //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return class_exists( '\GFForms' );
	}

	/**
	 * Initialize the integration
	 * Works when nitropack-gravity-forms-honeypot is 1 (enabled) and honeypot is also enabled per form.
	 * Skips AJAX forms.
	 * @param string $stage Stage.
	 *
	 * @return void
	 */
	public function init( string $stage ) {  //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( $this->isActive() ) {

			add_filter( 'gform_state_lifespan', [ $this, 'extend_state_lifespan' ] );

			// We need that for already cached pages, that have the NP GF compatibility
			add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'gravity_form_output_ajax' ] );
			add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ $this, 'gravity_form_output_ajax' ] );

			if ( version_compare( \GFForms::$version, '3.1.1', '>=' ) ) {
				return;
			}

			$option = get_option( "nitropack-gravity-forms-honeypot" );

			if ( ! $option ) {
				return;
			}
			//update nitropack-gf_honeypot_forms option if honeypot is enabled/disabled for a form and invalidate the page
			foreach ( [ 'gform_after_save_form', 'gform_post_update_form_meta', 'gform_post_form_duplicated', 'gform_post_form_trashed', 'gform_post_form_restored', 'gform_post_form_deleted' ] as $gf_form_change ) {
				add_action( $gf_form_change, [ $this, 'refresh_honeypot_forms' ] );
			}

			//No Honeypot forms at all - bail early.
			if ( ! $this->get_honeypot_form_ids() ) {
				return;
			}

			add_filter( 'gform_pre_render', function ( $form ) {
				$this->tag_current_page();
				return $form;
			} );

			if ( ! wp_doing_ajax() ) {
				add_action( 'init', [ $this, 'override_gravityform_shortcode' ], 20 );
			}
		}
	}

	/**
	 * Calculate the correct lifespan for Gravity Forms state
	 *
	 * @param int $lifespan The current state lifespan in seconds.
	 *
	 * @return int
	 */
	public function extend_state_lifespan( $lifespan ) {
		$lifespan = (int) $lifespan;

		try {
			$sdk = NitroPack::getInstance()->getSdk();

			if ( ! $sdk ) {
				return $lifespan;
			}

			$config = $sdk->getConfig();
			$cacheLifespan = (int) $config->PageCache->ExpireTime + (int) $config->PageCache->StaleExpireTime;
		} catch (\Throwable $e) {
			return $lifespan;
		}

		return $cacheLifespan > $lifespan ? $cacheLifespan : $lifespan;
	}

	/**
	 * Override their shortcode, so it runs async (AJAX) and loads freshly Honeypot-protected forms.
	 */
	public function override_gravityform_shortcode() {
		global $shortcode_tags;
		$this->original_gf_shortcode = isset( $shortcode_tags['gravityform'] ) ? $shortcode_tags['gravityform'] : null;
		add_shortcode( 'gravityform', [ $this, 'modify_gf_shortcode' ] );
		add_shortcode( 'gravityforms', [ $this, 'modify_gf_shortcode' ] );
	}
	/**
	 * Register, localize, and enqueue GF + NitroPack scripts on demand when a honeypot form is on the page.
	 */
	private function enqueue_gf_assets( int $form_id ) {

		//IMPORTANT: We must enqueue Gravity Forms scripts for all forms, otherwise forms which are added somewhere in the very end of the page are not found in the DOM initially and GF scripts are not loaded.
		if ( function_exists( 'gravity_form_enqueue_scripts' ) && ! wp_script_is( 'gform_gravityforms', 'enqueued' ) ) {
			gravity_form_enqueue_scripts( $form_id, true );
		}

		wp_register_script( 'nitropack-gf-ajax-script', NITROPACK_PLUGIN_DIR_URL . 'assets/js/gravity_forms.min.js', array( 'jquery' ), NITROPACK_VERSION, true );
		wp_localize_script( 'nitropack-gf-ajax-script', 'nitropack_gf_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'action' => self::AJAX_ACTION,
		) );

		wp_enqueue_script( 'nitropack-gf-ajax-script' );
	}

	/**
	 * Get the IDs of the forms which have Honeypot anti-spam enabled.
	 * Uses cache for the results.
	 *
	 * @return array
	 */
	private function get_honeypot_form_ids() {
		$cached = get_option( self::HONEYPOT_FORMS_OPTION, null );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$form_ids = [];

		foreach ( (array) \GFAPI::get_forms() as $form ) {
			if ( ! empty( $form['enableHoneypot'] ) ) {
				$form_ids[] = (int) $form['id'];
			}
		}

		update_option( self::HONEYPOT_FORMS_OPTION, $form_ids, true );

		return $form_ids;
	}

	/**
	 * Recalculate the cached Honeypot form IDs after a form has changed.
	 *
	 * @return void
	 */
	public function refresh_honeypot_forms() {
		$previous = get_option( self::HONEYPOT_FORMS_OPTION, null );

		if ( ! is_array( $previous ) ) {
			return;
		}

		$form_ids = [];
		foreach ( (array) \GFAPI::get_forms() as $form ) {
			if ( ! empty( $form['enableHoneypot'] ) ) {
				$form_ids[] = (int) $form['id'];
			}
		}

		$updated = update_option( self::HONEYPOT_FORMS_OPTION, $form_ids, true );

		if ( $updated ) {
			nitropack_invalidate( NULL, self::CACHE_TAG, 'Change in Gravity Form Honeypot anti-spam settings.' );
		}
	}

	/**
	 * Tag the page being built as one which holds a Gravity Form.
	 *
	 * @return void
	 */
	private function tag_current_page() {
		if ( isset( $GLOBALS['NitroPack.tags'] ) && is_array( $GLOBALS['NitroPack.tags'] ) ) {
			$GLOBALS['NitroPack.tags'][ self::CACHE_TAG ] = 1;
		}
	}

	/**
	 * Override gravity forms shortcode render callback
	 * Skip AJAX forms.
	 * @param array $atts Attributes for shortcode.
	 * @param string $content Content of shortcode.
	 *
	 * @return string
	 */
	public function modify_gf_shortcode( array $atts, $content = null ) {
		$form_id = isset( $atts['id'] ) ? (int) $atts['id'] : 0;

		if ( ! in_array( $form_id, $this->get_honeypot_form_ids(), true ) ) {
			if ( $this->original_gf_shortcode ) {
				return call_user_func( $this->original_gf_shortcode, $atts, $content );
			}
			return '';
		}

		//Skip AJAX forms
		if ( isset( $atts['ajax'] ) && $atts['ajax'] === 'true' ) {
			return call_user_func( $this->original_gf_shortcode, $atts, $content );

		}

		$this->tag_current_page();
		$this->enqueue_gf_assets( $form_id );

		$shortcode_attributes = wp_json_encode( $atts );

		if ( false === $shortcode_attributes ) {
			$shortcode_attributes = '{}';
		}

		$shortcode_nonce = wp_create_nonce( $this->get_shortcode_nonce_action( $shortcode_attributes ) );

		return '<div class="nitropack-gravityforms-shortcode" data-shortcode-attributes="' . esc_attr( $shortcode_attributes ) . '" data-shortcode-nonce="' . esc_attr( $shortcode_nonce ) . '"><img src="' . esc_url( NITROPACK_PLUGIN_DIR_URL . 'assets/img/loading.svg' ) . '" alt="loading" /></div>';
	}

	/**
	 * Build nonce action for Gravity Forms shortcode output.
	 *
	 * @param string $shortcode_attributes The shortcode attributes as JSON.
	 *
	 * @return string
	 */
	private function get_shortcode_nonce_action( string $shortcode_attributes ) {
		return self::SHORTCODE_AJAX_NONCE_ACTION . '|' . wp_hash( $shortcode_attributes, 'nonce' );
	}

	/**
	 * AJAX entry point: validate request, then render the form.
	 *
	 * @return void
	 */
	public function gravity_form_output_ajax() {
		//nonce security
		$shortcode_attributes = $this->verify_ajax_request();
		//ouput html
		$this->render_shortcode_ajax( $shortcode_attributes );
	}

	/**
	 * Extract and verify the shortcode AJAX payload. Dies on failure.
	 *
	 * @return array Validated shortcode attributes.
	 */
	private function verify_ajax_request() {
		if ( isset( $_REQUEST['shortcode_attributes'] ) && is_string( $_REQUEST['shortcode_attributes'] ) ) {
			$raw_attributes = wp_unslash( $_REQUEST['shortcode_attributes'] );
		} elseif ( isset( $_REQUEST['shortcode-attributes'] ) && is_string( $_REQUEST['shortcode-attributes'] ) ) {
			$raw_attributes = wp_unslash( $_REQUEST['shortcode-attributes'] );
		} else {
			wp_die();
		}

		$nonce = isset( $_REQUEST['shortcode_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['shortcode_nonce'] ) ) : '';

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $this->get_shortcode_nonce_action( $raw_attributes ) ) ) {
			wp_die();
		}

		$attributes = json_decode( $raw_attributes, true );

		if ( empty( $attributes ) || ! is_array( $attributes ) ) {
			wp_die();
		}

		return $attributes;
	}

	/**
	 * Render the Gravity Forms shortcode and output the result.
	 *
	 * @param array $attributes Verified shortcode attributes.
	 * @return void
	 */
	private function render_shortcode_ajax( $attributes ) {
		$attributes['ajax'] = 'true';

		$attribute_string = implode( ' ', array_map( static function ( $k, $v ) {
			return "$k=\"$v\"";
		}, array_keys( $attributes ), $attributes ) );

		echo do_shortcode( '[gravityform ' . $attribute_string . ']' );

		wp_die();
	}
}