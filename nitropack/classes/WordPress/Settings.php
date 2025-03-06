<?php

namespace NitroPack\WordPress;

use NitroPack\WordPress\Settings\TestMode;
use NitroPack\WordPress\Settings\Shortcodes;
use NitroPack\WordPress\Settings\Logger;

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
	 * - 'nitropack-safeModeStatus': (int) Status of safe mode, default is 0.
	 * - 'nitropack-distribution': (string) Distribution type, default is 'regular'.
	 */
	private $settings;
	/**
	 * Grabs TestMode class
	 * @var TestMode
	 */
	public $test_mode;
	/**
	 * Grabs Shortcodes class
	 * @var Shortcodes
	 */
	public $shortcodes;
    public $logger;
	/**
	 * Settings constructor.
	 *
	 * Initializes the default required settings for the NitroPack plugin.
	 */
	function __construct($config = null) {
		$this->default_required_settings();
		//initialize each setting
		$this->test_mode = new TestMode();
        $this->shortcodes = new Shortcodes();
        $this->logger = new Logger($config);
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
			'nitropack-safeModeStatus' => 0,
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
        
        if ($token !== null) {
            $this->settings['nitropack-webhookToken'] = $token;
        } else {
            // Generate a new webhook token if it is not passed
            $this->generate_webhook_token();
        }

        foreach ($this->settings as $option => $value) {
            if (get_option($option) === false && $value !== null) {
                if ($option === 'nitropack-cacheableObjectTypes') {
                    $value = nitropack_get_default_cacheable_object_types();
                }
                update_option($option, $value);
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
        if (isset($siteConfig['webhookToken'])) {
            $this->settings['nitropack-webhookToken'] = $siteConfig['webhookToken'];
        } elseif (isset($siteConfig['siteId'])) {
            //generate from existing siteId
            $siteId = $siteConfig['siteId'];
            $this->settings['nitropack-webhookToken'] = nitropack_generate_webhook_token($siteId);
        } elseif (!empty($_POST["siteId"])) {
            //try to generate from POST
            $siteId = $_POST["siteId"];
            $this->settings['nitropack-webhookToken'] = nitropack_generate_webhook_token($siteId);
        } else {
            $this->settings['nitropack-webhookToken'] = null;
        }
    }
    /**
     * Get NitroPack configuration for ajaxShortcodes
     *
     * @return array|null
     */
    private function get_nitropack_config_for_ajaxShortcodes() {
        try {
            $nitropack = get_nitropack();
            if (!$nitropack) {
                throw new \Exception('NitroPack instance not found');
            }

            $siteConfig = $nitropack->Config->get();
            $configKey = \NitroPack\WordPress\NitroPack::getConfigKey();

            return isset($siteConfig[$configKey]['options_cache']['ajaxShortcodes']) ? $siteConfig[$configKey]['options_cache']['ajaxShortcodes'] : null;
        } catch (\Exception $e) {
            error_log('NitroPack Config Error: ' . $e->getMessage());
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
    private function generate_shortcode_options($shortcode_tags, $ajax_shortcodes_list) {
        $restricted_shortcodes = $this->get_restricted_shortcodes();
        $html = '';

        foreach ($shortcode_tags as $shortcode => $_) {
            if (in_array($shortcode, $restricted_shortcodes)) {
                continue;
            }

            $selected = in_array($shortcode, $ajax_shortcodes_list) ? 'selected="selected"' : '';
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($shortcode),
                $selected,
                esc_html($shortcode)
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
    private function generate_manual_shortcode_options($freely_added_shortcodes) {
        return implode('', array_map(function ($shortcode) {
            return sprintf(
                '<option value="%s" selected="selected">%s</option>',
                esc_attr($shortcode),
                esc_html($shortcode)
            );
        }, $freely_added_shortcodes));
    }

    /**
     * List all available AJAX shortcodes
     *
     * @return string
     */
    private function list_ajax_shortcodes() {
        global $shortcode_tags;

        $config = $this->get_nitropack_config_for_ajaxShortcodes();
        if (!$config) {
            return '<option value="" disabled>Configuration not available</option>';
        }

        $ajax_shortcodes_list = isset($config['shortcodes']) ? $config['shortcodes'] : [];
        $freely_added_shortcodes = array_diff($ajax_shortcodes_list, array_keys($shortcode_tags));

        $html = $this->generate_shortcode_options($shortcode_tags, $ajax_shortcodes_list);

        if (!empty($freely_added_shortcodes)) {
            $html .= $this->generate_manual_shortcode_options($freely_added_shortcodes);
        }

        return $html;
    }

    /**
     * Render AJAX shortcodes settings in the admin panel (dashboard.php and dashboard-oneclick.php)
     */
    public function render_ajax_shortcodes_setting() {
        $config = $this->get_nitropack_config_for_ajaxShortcodes();
        if (!$config) {
            echo '<div class="error">Unable to load NitroPack Ajax Shortcodes configuration</div>';
            return;
        }

        $ajax_shortcodes_enabled = isset($config['enabled']) ? $config['enabled'] : false;
        $shortcode_container_shown = $ajax_shortcodes_enabled ? '' : 'hidden';
?>
        <div class="nitro-option-main">
            <div class="text-box">
                <h6><?php esc_html_e('Shortcodes exclusions', 'nitropack'); ?></h6>
                <p><?php esc_html_e('Load widgets, feeds, and any shortcode with AJAX to bypass the cache and always show the latest content.', 'nitropack'); ?></p>
            </div>
            <label class="inline-flex items-center cursor-pointer ml-auto">
                <input type="checkbox"
                    value=""
                    class="sr-only peer"
                    name="ajax_shortcodes"
                    id="ajax-shortcodes"
                    <?php echo $ajax_shortcodes_enabled ? 'checked' : ''; ?>>
                <div class="toggle"></div>
            </label>
        </div>
        <div class="ajax-shortcodes <?php echo esc_attr($shortcode_container_shown); ?>">
            <div class="select-wrapper">
                <select class="shortcode-select"
                    name="nitropack-ajaxShortcodes"
                    id="ajax-shortcodes-dropdown"
                    multiple>
                    <?php echo $this->list_ajax_shortcodes(); ?>
                </select>
                <button class="btn btn-primary" id="save-shortcodes">
                    <?php esc_html_e('Save', 'nitropack'); ?>
                </button>
            </div>
        </div>

    <?php
    }
}
