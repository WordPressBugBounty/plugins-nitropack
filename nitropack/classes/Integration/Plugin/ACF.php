<?php

namespace NitroPack\Integration\Plugin;

class ACF {
    const STAGE = "late";

    public static function isActive() {
        return class_exists(\ACF::class);
    }

    public function init($stage) {
        /* By using 5 priority the function is called before the ACF save_post action, 10 is after */
        add_action('acf/save_post', [$this, 'invalidate_post_due_to_acf_change'], 5);
    }

    public function invalidate_post_due_to_acf_change($post_id) {
        if (!get_option("nitropack-autoCachePurge", 1)) return;

        $allowed_cpts = get_option('nitropack-cacheableObjectTypes');
        if (!in_array(get_post_type($post_id), $allowed_cpts)) return;

        //acf update check
        if (!isset($_POST['acf'])) return;

        //get old meta
        $metaBefore = [];
        $field_objects = get_field_objects($post_id, false);
        foreach ($field_objects as $field_object) {
            $metaBefore[$field_object['key']] = $field_object['value'];
        }
        // Get submitted new values.
        $metaAfter = $_POST['acf'];

        //comapre meta before and after and update
        $metaIsEqual = nitropack_compare_posts($metaAfter, $metaBefore);
        if (!$metaIsEqual) {
            $post = get_post($post_id);
            if ($post->post_status === 'publish' && !defined('NITROPACK_PURGE_CACHE') ) {
                nitropack_clean_post_cache($post, null, true, sprintf("Invalidate related pages due to modifying ACF fields in %s '%s'", $post->post_type, $post->post_title));
                define('NITROPACK_PURGE_CACHE', true);
            }
        }
    }
}
