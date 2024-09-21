<?php

namespace NitroPack\Feature;

class AjaxShortcodes {
    const STAGE = "very_early";

    private $shortcodes = array();
    private $scriptHandle = 'nitropack-ajax-shortcodes';
    private $processedShortcodes = [];
    private $emptyScript = "let nitroAjaxShortcode = false";

    private function generateNitroScLoader() {
        if (empty($this->processedShortcodes)) return $this->emptyScript;

        $adminUrl = admin_url('admin-ajax.php');
        $js = <<<SCRIPT
    (async () => {
        let domIsReady = new Promise((resolve) => {
            if (document.readyState !== 'complete') {
                document.addEventListener('DOMContentLoaded', resolve);
            } else {
                resolve();
            }
        });

        let formData = new URLSearchParams();
        formData.append("action", "nitro_shortcode_ajax");  
        formData.append("nonce", "NONCE_VALUE");
        formData.append("tags", "TAGS_JSON_DATA");

        fetch('$adminUrl', {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: formData.toString(),
        })
        .then(response => response.json())
        .then(async (data) => {
            let scData = data.data;
            await domIsReady;
            for (let key in scData) {
                document.querySelectorAll(`.nitro-sc-load[data-sc-meta-id='\${key}']`).forEach(el => {
                    el.outerHTML = scData[key];
                });            
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    })()

SCRIPT;

        return str_replace(
            [
                "NONCE_VALUE",
                "TAGS_JSON_DATA"
            ],
            [
                $this->generateNonce($this->processedShortcodes),
                base64_encode(json_encode($this->processedShortcodes))
            ],
            $js
        );
    }

    private function generateNonce($tags) {
        return wp_create_nonce($this->getNonceAction($tags));
    }

    private function getNonceAction($tags) {
        $jsons = array_map("json_encode", $tags);
        sort($jsons);
        return base64_encode(json_encode($jsons));
    }
    /* Merge shortcodes from wp-config and site config */
    private function mergeShortcodes() {

        $siteConfig = get_nitropack()->getSiteConfig();        
        $options = $siteConfig['options_cache'];

        $ajax_shortcodes = isset($options['ajaxShortcodes']) ? $options['ajaxShortcodes'] : null; //initially ajaxShortcodes is empty

        if (self::isEnabled() && (!empty($ajax_shortcodes['shortcodes']) && is_array($ajax_shortcodes['shortcodes']))) {
            $this->shortcodes = $ajax_shortcodes['shortcodes'];
        }
        if (defined("NITROPACK_AJAX_SHORTCODES") && strpos(NITROPACK_AJAX_SHORTCODES, ',') !== false) {
            $wp_config_shortcodes = explode(',', NITROPACK_AJAX_SHORTCODES);
            $this->shortcodes = array_unique(array_merge($this->shortcodes, $wp_config_shortcodes));
        }
    }
    public function init($stage) {
        if (!defined("NITROPACK_AJAX_SHORTCODES") && !self::isEnabled()) {
            // This init method can be run at any stage. This gives the opportunity to define the constant at a later point
            // For example in a MU plugin
            return true;
        }
        $this->mergeShortcodes();
        //stop execution if no shortcodes are found
        if (!$this->shortcodes) return;

        add_filter('pre_do_shortcode_tag', function ($out, $tag, $attr) {

            if (defined("NITRO_DOING_AJAX_SHORTCODES") && NITRO_DOING_AJAX_SHORTCODES) return $out;

            if (in_array($tag, $this->shortcodes)) {
                $entry = ["tag" => $tag, "attr" => $attr];
                if (!in_array($entry, $this->processedShortcodes)) {
                    $this->processedShortcodes[] = $entry;
                }
                $np_out = '<span class="nitro-sc-load" data-sc-meta-id="' . base64_encode(json_encode($entry)) . '"></span>';

                return $np_out;
            }

            return $out;
        }, 10, 3);


        add_action('wp_ajax_nitro_shortcode_ajax', array($this, 'shortcodeAjax'));
        add_action('wp_ajax_nopriv_nitro_shortcode_ajax', array($this, 'shortcodeAjax'));

        add_action('wp_enqueue_scripts', [$this, 'add_scripts']);
        $this->startBuffering();
    }

    private function startBuffering() {
        ob_start(function($buffer) {
            return str_replace($this->emptyScript, $this->generateNitroScLoader(), $buffer);
        }, 0, PHP_OUTPUT_HANDLER_FLUSHABLE | PHP_OUTPUT_HANDLER_REMOVABLE);
    }

    public function add_scripts() {
        wp_register_script($this->scriptHandle, '', [], '1.0.0', false);
        wp_add_inline_script($this->scriptHandle, $this->emptyScript);
        wp_enqueue_script($this->scriptHandle);
        
        add_filter('wp_inline_script_attributes', function ($attr, $js) {

            if(isset($attr['id']) && strpos($attr['id'], $this->scriptHandle) === 0) {
                $attr = array_merge(array( 'nitro-exclude' => true ), $attr);
            } 

            return $attr;

        }, 10, 2);
    }

    public function shortcodeAjax() {
        if (!defined("NITRO_DOING_AJAX_SHORTCODES")) define("NITRO_DOING_AJAX_SHORTCODES", true);
        $this->runShortcodes();

        // In case a later hook is needed, we can and an option for it and use something like this
        //if (did_action('plugins_loaded')) {
        //    $this->runShortcodes();
        //} else {
        //    add_action('plugins_loaded', [$this, 'runShortcodes']);
        //}
    }

    private function validateNonce($tags) {
        if (empty($_POST['nonce']) || empty($_POST['tags']) || !is_string($_POST['tags'])) {
            die(__('Security check', 'nitropack'));
        }

        $action = $this->getNonceAction($tags);
        if (!wp_verify_nonce($_POST['nonce'], $action)) die(__('Security check', 'nitropack'));
    }

    public function runShortcodes() {
        $tags = !empty($_POST["tags"]) && is_string($_POST["tags"]) ? json_decode(base64_decode($_POST['tags']), true) : [];
        if (empty($tags)) wp_send_json_error(["message" => "Invalid shortcode input"], 400);
        $this->validateNonce($tags);

        $resp = [];
        foreach ($tags as $sc) {
            $attrFlat = is_array($sc["attr"]) ? array_map(function ($k, $v) {
                return "$k=$v";
            }, array_keys($sc["attr"]), array_values($sc["attr"])) : [];
            $arrKey = base64_encode(json_encode($sc));
            $resp[$arrKey] = do_shortcode("[{$sc['tag']} " . implode(" ", $attrFlat) . "]");
        }

        wp_send_json_success($resp);
    }
    public function isEnabled() {
        $siteConfig = get_nitropack()->getSiteConfig();
        return !empty($siteConfig['options_cache']['ajaxShortcodes']['enabled'])
            && $siteConfig['options_cache']['ajaxShortcodes']['enabled'] == 1;
    }
}