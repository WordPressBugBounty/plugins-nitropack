<?php
namespace NitroPack\WordPress\Settings;
use \NitroPack\SDK\Api\ResponseStatus;

/**
 * Class System Report used in NitroPack
 */
class SystemReport {
	private static $instance = null;
	/**
	 * Singleton instance. Avoids multiple instances.
	 * @return SystemReport
	 */
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'wp_ajax_nitropack_generate_report', [ $this, 'nitropack_generate_report' ] );
	}

	/**
	 * Generates a system report based on selected options and outputs it as a downloadable file.
	 */
	public function nitropack_generate_report() {
		nitropack_verify_ajax_nonce( $_REQUEST );

		$np_diag_functions = array(
			'general-info-status' => $this->get_general_info(),
			'active-plugins-status' => $this->get_active_plugins(),
			'conflicting-plugins-status' => $this->get_conflicting_plugins(),
			'user-config-status' => $this->get_user_config(),
			'dir-info-status' => $this->get_dir_info(),
			'getexternalcache' => $this->detect_third_party_cache()
		);
		try {
			$options = ! empty( $_POST["toggled"] ) ? $_POST["toggled"] : NULL;

			if ( $options !== NULL ) {
				$diag_data = array( 'report-time-stamp' => date( "Y-m-d H:i:s" ) );
				foreach ( $options as $func_name => $func_allowed ) {
					if ( (boolean) $func_allowed ) {
						$diag_data[ $func_name ] = $np_diag_functions[ $func_name ];
					}
				}
				$str = json_encode( $diag_data, JSON_PRETTY_PRINT );
				$filename = 'nitropack_diag_file.txt';
				nitropack_header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				nitropack_header( "Content-Type: text/plain" );
				nitropack_header( "Content-Length: " . strlen( $str ) );
				echo $str;
				wp_die();
			}
		} catch (\Exception $e) {
			wp_send_json_error( __( 'Error generating report: ', 'nitropack' ) . $e->getMessage() );
		}
	}

	private function helper_trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
	/**
	 * Compares the constructed webhook URL with the stored one in NitroPack
	 *
	 * @param \NitroPack\SDK\Nitropack $nitro_sdk The NitroPack SDK instance.
	 * @return string Result of the comparison.
	 */
	private function compare_webhooks( $nitro_sdk ) {
		try {
			$siteConfig = nitropack_get_site_config();
			if ( ! empty( $siteConfig['siteId'] ) ) {
				$WHToken = nitropack_generate_webhook_token( $siteConfig['siteId'] );
				$constructedWH = new \NitroPack\Url\Url( strtolower( get_home_url() ) ) . '?nitroWebhook=config&token=' . $WHToken;
				$storedWH = $nitro_sdk->getApi()->getWebhook( "config" );
				$matchResult = ( $constructedWH == $storedWH ) ? __( 'OK', 'nitropack' ) : __( 'Warning: Webhooks do not match this site', 'nitropack' );
			} else {
				$debugMsg = empty( $_SERVER["HTTP_HOST"] ) ? "HTTP_HOST is not defined. " : "";
				$debugMsg .= empty( $_SERVER["REQUEST_URI"] ) ? "REQUEST_URI is not defined. " : "";
				$debugMsg .= empty( $debugMsg ) ? 'URL used to match config was: ' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] : "";
				$matchResult = __( 'Site config cannot be found, because ', 'nitropack' ) . $debugMsg;
			}
			return $matchResult;
		} catch (\Exception $e) {
			return $e->getMessage();
		}
	}
	/**
	 * Polls the NitroPack API and returns a human-readable status message.
	 *
	 * @param \NitroPack\SDK\Nitropack $nitro_sdk The NitroPack SDK instance.
	 * @return string The status message based on the API response.
	 */
	private function poll_api( $nitro_sdk ) {
		$pollResult = array(
			ResponseStatus::OK => __( 'OK', 'nitropack' ),
			ResponseStatus::ACCEPTED => __( 'OK', 'nitropack' ),
			ResponseStatus::BAD_REQUEST => __( 'Bad request.', 'nitropack' ),
			ResponseStatus::PAYMENT_REQUIRED => __( 'Payment required. Please, contact NP support for details.', 'nitropack' ),
			ResponseStatus::FORBIDDEN => __( 'Site disabled. Please, contact NP support for details.', 'nitropack' ),
			ResponseStatus::NOT_FOUND => __( 'URL used for the API poll request returned 404. Please ignore this.', 'nitropack' ),
			ResponseStatus::CONFLICT => __( 'Conflict. There is another operation, which prevents accepting optimization requests at the moment. Please, contact NP support for details.', 'nitropack' ),
			ResponseStatus::RUNTIME_ERROR => __( 'Runtime error.', 'nitropack' ),
			ResponseStatus::SERVICE_UNAVAILABLE => __( 'Service unavailable.', 'nitropack' ),
			ResponseStatus::UNKNOWN => __( 'Unknown.', 'nitropack' )
		);

		try {
			$referer = isset( $_SERVER["HTTP_REFERER"] ) ? $_SERVER["HTTP_REFERER"] : '';
			$apiResponseCode = $nitro_sdk->getApi()->getCache( get_home_url(), __( 'NitroPack Diagnostic Agent', 'nitropack' ), array(), false, 'default', $referer )->getStatus();
			return $pollResult[ $apiResponseCode ];
		} catch (\Exception $e) {
			return 'Error: ' . $e->getMessage();
		}

	}

	private function backlog_status( $nitro_sdk ) {
		return $nitro_sdk->backlog->exists() ? 'Warning' : 'OK';
	}
	/**
	 * Gathers general information about the NitroPack installation and environment.
	 *
	 * @return array An associative array containing various pieces of information.
	 */
	private function get_general_info() {
		global $wp_version;
		if ( null !== $nitro = get_nitropack_sdk() ) {
			$probe_result = "OK";
			try {
				$nitro->fetchConfig();
			} catch (\Exception $e) {
				$probe_result = __( 'Error: ', 'nitropack' ) . $e->getMessage();
			}
		} else {
			$probe_result = __( 'Error: Cannot get an SDK instance', 'nitropack' );
		}

		$third_party_residual_cache = $this->detect_third_party_cache();

		$info = array(
			'Nitro_WP_version' => ! empty( $wp_version ) ? $wp_version : get_bloginfo( 'version' ),
			'Nitro_Version' => defined( 'NITROPACK_VERSION' ) ? NITROPACK_VERSION : __( 'Undefined', 'nitropack' ),
			'Nitro_SDK_Connection' => $probe_result,
			'Nitro_API_Polling' => $nitro ? $this->poll_api( $nitro ) : __( 'Error: Cannot get an SDK instance', 'nitropack' ),
			'Nitro_SDK_Version' => defined( 'NitroPack\SDK\Nitropack::VERSION' ) ? \NitroPack\SDK\Nitropack::VERSION : __( 'Undefined', 'nitropack' ),
			'Nitro_WP_Cache' => defined( 'WP_CACHE' ) ? ( WP_CACHE ? __( 'OK for drop-in', 'nitropack' ) : __( 'Turned off', 'nitropack' ) ) : __( 'Undefined', 'nitropack' ),
			'Advanced_Cache_Version' => defined( 'NITROPACK_ADVANCED_CACHE_VERSION' ) ? NITROPACK_ADVANCED_CACHE_VERSION : __( 'Undefined', 'nitropack' ),
			'Nitro_Absolute_Path' => defined( 'ABSPATH' ) ? ABSPATH : __( 'Undefined', 'nitropack' ),
			'Nitro_Plugin_Directory' => defined( 'NITROPACK_PLUGIN_DIR' ) ? NITROPACK_PLUGIN_DIR : dirname( __FILE__ ),
			'Nitro_Data_Directory' => defined( 'NITROPACK_DATA_DIR' ) ? NITROPACK_DATA_DIR : __( 'Undefined', 'nitropack' ),
			'Nitro_Plugin_Data_Directory' => defined( 'NITROPACK_PLUGIN_DATA_DIR' ) ? NITROPACK_PLUGIN_DATA_DIR : __( 'Undefined', 'nitropack' ),
			'Nitro_Config_File' => defined( 'NITROPACK_CONFIG_FILE' ) ? NITROPACK_CONFIG_FILE : __( 'Undefined', 'nitropack' ),
			'Nitro_Backlog_File_Status' => $nitro ? $this->backlog_status( $nitro ) : __( 'Error: Cannot get an SDK instance', 'nitropack' ),
			'Nitro_Webhooks' => $nitro ? $this->compare_webhooks( $nitro ) : __( 'Error: Cannot get an SDK instance', 'nitropack' ),
			'Nitro_Connectivity_Requirements' => nitropack_check_func_availability( 'stream_socket_client' ) ? __( 'OK', 'nitropack' ) : __( 'Warning: "stream_socket_client" function is disabled.', 'nitropack' ),
			'Residual_Cache_Found_For' => $third_party_residual_cache,
		);

		if ( defined( "NITROPACK_VERSION" ) && defined( "NITROPACK_ADVANCED_CACHE_VERSION" ) && NITROPACK_VERSION == NITROPACK_ADVANCED_CACHE_VERSION && nitropack_is_dropin_cache_allowed() ) {
			$info['Nitro_Cache_Method'] = 'drop-in';
		} elseif ( defined( 'EZOIC_INTEGRATION_VERSION' ) ) {
			$info['Nitro_Cache_Method'] = 'plugin-ezoic';
		} else {
			$info['Nitro_Cache_Method'] = 'plugin';
		}

		return $info;
	}

	/**
	 * Retrieves a list of active plugins and their versions.
	 *
	 * @return array An associative array where keys are plugin names and values are their versions.
	 */
	private function get_active_plugins() {

		$plugins = array();
		$raw_installed_list = get_plugins();
		$raw_active_list = get_option( 'active_plugins' );
		foreach ( $raw_installed_list as $pkey => $pval ) {
			if ( in_array( $pkey, $raw_active_list ) ) {
				$plugins[ $pval['Name'] ] = $pval['Version'];
			}
		}

		return $plugins;
	}

	/** Retrieves the contents of the NitroPack configuration file - config.json in wp-content/config-[hash]-nitropack/.
	 *
	 * @return mixed The contents of the configuration file or an error message.
	 */
	private function get_user_config() {
		if ( defined( 'NITROPACK_CONFIG_FILE' ) ) {
			if ( file_exists( NITROPACK_CONFIG_FILE ) ) {
				$info = json_decode( file_get_contents( NITROPACK_CONFIG_FILE ) );
				if ( ! $info ) {
					$info = __( 'Config found, but unable to get contents.', 'nitropack' );
				}
			} else {
				$info = __( 'Config file not found.', 'nitropack' );
			}
		} else {
			$info = __( 'Config file constant is not defined.', 'nitropack' );
		}

		return $info;
	}

	/**[p]
	 * Gathers information about specific directories related to NitroPack.
	 *
	 * @return array An associative array containing the status of various directories.
	 */
	private function get_dir_info() {
		$siteConfig = nitropack_get_site_config();
		$siteID = $siteConfig['siteId'];
		// DoI = Directories of Interest
		$DoI = array(
			'WP_Content_Dir_Writable' => defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ( defined( 'ABSPATH' ) ? ABSPATH . '/wp-content' : __( 'Undefined', 'nitropack' ) ),
			'Nitro_Data_Dir_Writable' => defined( 'NITROPACK_DATA_DIR' ) ? NITROPACK_DATA_DIR : $this->helper_trailingslashit( WP_CONTENT_DIR ) . 'nitropack',
			'Nitro_siteID_Dir_Writable' => defined( 'NITROPACK_DATA_DIR' ) ? NITROPACK_DATA_DIR . "/$siteID" : $this->helper_trailingslashit( WP_CONTENT_DIR ) . "nitropack/$siteID",
			'Nitro_Plugin_Dir_Writable' => defined( 'NITROPACK_PLUGIN_DIR' ) ? NITROPACK_PLUGIN_DIR : dirname( __FILE__ ),
			'Nitro_Plugin_Data_Dir_Writable' => defined( 'NITROPACK_PLUGIN_DATA_DIR' ) ? NITROPACK_PLUGIN_DATA_DIR : $this->helper_trailingslashit( WP_CONTENT_DIR ) . 'nitropack',
		);

		$info = array();
		foreach ( $DoI as $doi_dir => $dpath ) {
			if ( is_dir( $dpath ) ) {
				$info[ $doi_dir ] = is_writeable( $dpath ) ? true : false;
			} else if ( is_file( $dpath ) ) {
				$info[ $doi_dir ] = $dpath . __( ' is a file not a directory', 'nitropack' );
			} else {
				$info[ $doi_dir ] = __( 'Directory not found', 'nitropack' );
			}
		}
		return $info;
	}

	/**
	 * Retrieves a list of plugins that are known to conflict with NitroPack.
	 *
	 * @return array|string An array of conflicting plugins or a message indicating none were detected.
	 */
	private function get_conflicting_plugins()  {
		$conflictingPlugins = \NitroPack\WordPress\ConflictingPlugins::getInstance();
		$info = $conflictingPlugins->nitropack_get_conflicting_plugins();
		if ( ! empty( $info ) ) {
			return $info;
		} else {
			return $info = __( 'None detected', 'nitropack' );
		}
	}

	/** 
	 * Detects third-party caching plugins that might conflict with NitroPack.
	 *
	 * @return mixed Information about detected third-party caches or a message indicating none were found.
	 */
	private function detect_third_party_cache() {
		$info = \NitroPack\Integration\Plugin\RC::detectThirdPartyCaches();
		if ( ! empty( $info ) ) {
			return $info;
		} else {
			return $info = __( 'Not found', 'nitropack' );
		}
	}

	/**
	 * Summary of diagnostic_settings
	 * @return array{class: string, desc: string, id: string, name: mixed, setting: string[]}
	 */
	private function diagnostic_settings() {
		$diagnostic_settings = array(
			array(
				'name' => esc_html__( 'Include NitroPack info (version, methods, environment)', 'nitropack' ),
				'desc' => '',
				'id' => 'general-info-status',
				'class' => 'diagnostic-option',
				'setting' => 'include_info'
			),
			array(
				'name' => esc_html__( 'Include active plugins list', 'nitropack' ),
				'desc' => '',
				'id' => 'active-plugins-status',
				'class' => 'diagnostic-option',
				'setting' => 'active_plugins'
			),
			array(
				'name' => esc_html__( 'Include conflicting plugins list', 'nitropack' ),
				'desc' => '',
				'id' => 'conflicting-plugins-status',
				'class' => 'diagnostic-option',
				'setting' => 'conflicting_plugins'
			),
			array(
				'name' => esc_html__( 'Include plugin config', 'nitropack' ),
				'desc' => '',
				'id' => 'user-config-status',
				'class' => 'diagnostic-option',
				'setting' => 'user_conflict'
			),
			array(
				'name' => esc_html__( 'Include directory status', 'nitropack' ),
				'desc' => '',
				'id' => 'dir-info-status',
				'class' => 'diagnostic-option',
				'setting' => 'dir_info_status'
			),
		);
		return $diagnostic_settings;
	}

	/**
	 * Renders the System Report settings page.
	 */
	public function render() {
		?>
		<div class="flex">
			<div class="" style="flex-basis: 80%;">
				<h3><?php esc_html_e( 'System Info Report', 'nitropack' ); ?></h3>
				<p><?php esc_html_e( 'This report gives a clear picture of how NitroPack is set up on your site. It checks for anything that might cause problems, like plugins that don’t work well with NitroPack or server issues. If something isn’t working as expected, share this report with support to help them fix it quickly.', 'nitropack' ); ?>
				</p>
			</div>

			<div class="ml-auto">
				<?php $components = new Components();
				echo $components->render_button( [ 'text' => 'Download', 'type' => null, 'classes' => 'btn btn-secondary', 'icon' => 'download.svg', 'attributes' => [ 'id' => 'gen-report-btn' ] ] );
				?>

			</div>
		</div>
		<div class="card-body">
			<div>
				<div id="accordion-collapse" data-accordion="collapse" class="mt-4" data-active-classes="active"
					data-inactive-classes="not-active">
					<div id="accordion-collapse-heading-1" class="text-center">
						<a class="btn btn-link" data-accordion-target="#accordion-collapse-body-1" aria-expanded="false"
							aria-controls="accordion-collapse-body-1">
							<span><?php esc_html_e( 'Customize Report', 'nitropack' ); ?></span>
							<svg width="9" height="6" data-accordion-icon class="w-3 h-3 rotate-180 shrink-0 icon-right"
								aria-hidden="false" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none"
								viewBox="0 0 10 6">
								<path d="M8.5 5L4.5 1L0.5 5" stroke="#4600CC" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
						</a>
					</div>
					<div id="accordion-collapse-body-1" class="accordion-body hidden"
						aria-labelledby="accordion-collapse-heading-1">
						<div class="options-container">
							<?php foreach ( $this->diagnostic_settings() as $setting ) : ?>
								<div class="nitro-option">
									<div class="nitro-option-main">
										<h6><?php echo $setting['name']; ?></h6>
										<label class="inline-flex items-center cursor-pointer ml-auto">
											<input type="checkbox" value="" id="<?php echo $setting['id']; ?>"
												class="sr-only peer <?php echo $setting['class']; ?>"
												name="<?php echo $setting['setting']; ?>" checked>
											<div class="toggle"></div>
										</label>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}