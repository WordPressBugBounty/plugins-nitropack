<?php

namespace NitroPack\Integration\Plugin;

class WPML {
    const STAGE = "early";

    public static function isActive() {
        if (class_exists('SitePress') || defined('ICL_SITEPRESS_VERSION')) return true;
        return false;
    }

    public function init($stage) {
        add_action('admin_init', [$this, 'nitropack_remove_wpml_home_url_filter']);
    }
    /**
     * Remove WPML home_url filter on NitroPack connect page and ajax calls. 
     * Located in WPML plugin: classes\url-handling\wpml-url-filters.class.php
     */
    public function nitropack_remove_wpml_home_url_filter() {      
        if (!class_exists('WPML_URL_Filters')) return;

        global $pagenow, $wpml_url_filters;
       
        //apply it only on nitropack connect screen
        if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] == 'nitropack' && !get_nitropack()->isConnected()) {          
            remove_filter('home_url', [$wpml_url_filters, 'home_url_filter'], -10);
        }
        //remove it only on ajax calls for nitropack connect and disconnect, for correct config fetch
        if (wp_doing_ajax() && isset($_REQUEST['action']) && ($_REQUEST['action'] === 'nitropack_disconnect' || $_REQUEST['action'] === 'nitropack_verify_connect')) {        
            remove_filter('home_url', [$wpml_url_filters, 'home_url_filter'], -10);
        }
    }
}
