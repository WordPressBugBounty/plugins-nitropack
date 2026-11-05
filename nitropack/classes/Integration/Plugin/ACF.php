<?php

namespace NitroPack\Integration\Plugin;
use NitroPack\WordPress\Settings\CPTOptimization;
class ACF {
    const STAGE = "late";

    /**
     * ACF values of the posts being saved, captured before ACF writes them, keyed by post ID.
     * @var array<int,array>
     */
    private $values_before = [];

    public static function isActive() {
        return class_exists(\ACF::class);
    }

    public function init($stage) {
        if (!self::isActive()) return;

        // ACF writes the submitted values on acf/save_post at priority 10: capture the stored values before it and
        // compare them with the stored values after it, so both sides come from the database in the same format.
        add_action('acf/save_post', [$this, 'capture_values_before_save'], 5);
        add_action('acf/save_post', [$this, 'invalidate_post_due_to_acf_change'], 20);
    }

    /**
     * @param int|string $post_id
     * @return void
     */
    public function capture_values_before_save($post_id) {
        if (!$this->is_cacheable_post($post_id)) return;

        $this->values_before[(int) $post_id] = $this->get_values($post_id);
    }

    /**
     * @param int|string $post_id
     * @return void
     */
    public function invalidate_post_due_to_acf_change($post_id) {
        if (!$this->is_cacheable_post($post_id) || !array_key_exists((int) $post_id, $this->values_before)) return;

        $metaBefore = $this->values_before[(int) $post_id];
        unset($this->values_before[(int) $post_id]);
        $metaAfter = $this->get_values($post_id);

        $metaIsEqual = \NitroPack\WordPress\Invalidations\InvalidationService::getInstance()->compare_posts($metaAfter, $metaBefore);
        if ($metaIsEqual) return;

        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish' && !defined('NITROPACK_PURGE_CACHE')) {
            \NitroPack\WordPress\Invalidations\InvalidationService::getInstance()->clean_post_cache($post, null, true, sprintf("Invalidate related pages due to modifying ACF fields in %s '%s'", $post->post_type, $post->post_title));
            define('NITROPACK_PURGE_CACHE', true);
        }
    }

    /**
     * Whether $post_id is a post of a type NitroPack caches. ACF also saves options pages, users and terms
     * ("options", "user_1", "term_5"), which are skipped.
     * @param int|string $post_id
     * @return bool
     */
    private function is_cacheable_post($post_id) {
        if (!get_option("nitropack-autoCachePurge", 1) || !is_numeric($post_id)) return false;

        $allowed_cpts = get_option('nitropack-cacheableObjectTypes');
        //refresh option if not set when activating already connected NitroPack
        if (!$allowed_cpts || !is_array($allowed_cpts)) {
            $allowed_cpts = CPTOptimization::getInstance()->nitropack_get_default_cacheable_object_types();
            update_option("nitropack-cacheableObjectTypes", $allowed_cpts);
        }

        return in_array(get_post_type((int) $post_id), $allowed_cpts, true);
    }

    /**
     * Stored (unformatted) ACF values of a post, keyed by field key. Empty values are left out, so a field that was
     * never saved and one saved empty compare equal.
     * @param int|string $post_id
     * @return array
     */
    private function get_values($post_id) {
        $values = [];
        foreach (get_field_objects($post_id, false) ?: [] as $field) {
            if (null === $field['value'] || '' === $field['value'] || [] === $field['value'] || false === $field['value']) continue;
            $values[$field['key']] = $field['value'];
        }
        return $values;
    }
}
