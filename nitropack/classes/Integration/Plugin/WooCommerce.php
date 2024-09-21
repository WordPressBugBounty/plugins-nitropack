<?php

namespace NitroPack\Integration\Plugin;

class WooCommerce {
    const STAGE = "very_early";

    public static function isActive() {
        if (class_exists('WooCommerce')) return true;
        return false;
    }

    public function init($stage) {
        add_action('init', [$this, 'cache_sale_products']);
        add_action('updated_post_meta', [$this, 'update_cached_sale_products'], 10, 4);
        add_action('added_post_meta', [$this, 'update_cached_sale_products'], 10, 4);
        add_action('deleted_post_meta', [$this, 'update_cached_sale_products'], 10, 4);
        if (nitropack_is_optimizer_request()) {
            add_action('template_redirect', [$this, 'purge_site_cache_on_sale_start_and_end']);
        }
    }
    /**
     * Retrieves the sale dates for a given WooCommerce product.
     *
     * @param \WC_Product $product The WooCommerce product object.
     * @return array An associative array containing the start and end dates of the sale.
     */
    private function get_sale_dates($product) {
        $sale_start = $product->get_date_on_sale_from();
        $sale_end = $product->get_date_on_sale_to();
        $result = [];

        if ($sale_start) {
            $sale_start = strtotime(date('Y-m-d', $sale_start->getTimestamp()));
            $result['from'] = $sale_start;
        }
        if ($sale_end) {
            $sale_end = strtotime(date('Y-m-d', $sale_end->getTimestamp()));
            $result['to'] = $sale_end;
        }

        return $result;
    }
    /**
     * This method sets the expiration date for the cache.
     *
     * @param int $date The expiration date as a timestamp.
     * @return void
     */
    private function add_cache_expiration($date) {
        global $np_customExpirationTimes;

        $np_customExpirationTimes[] = $date;
        nitropack_set_custom_expiration();
    }
    /**
     * Update cached products when post meta is updated, deleted, or added.
     *
     * This function updates the cached sale product dates when the post meta
     * keys '_sale_price_dates_from' or '_sale_price_dates_to' are modified.
     * It ensures that the cached products are updated accordingly and removes
     * the product from the cache if both dates are empty.
     *
     * @param int $meta_id The ID of the meta entry being updated.
     * @param int $post_id The ID of the post being updated.
     * @param string $meta_key The meta key being updated.
     * @param string $meta_value The new value of the meta key.
     * @return void
     */
    public function update_cached_sale_products($meta_id, $post_id, $meta_key, $meta_value) {
        //bail if we dont update future sale dates
        if ($meta_key != '_sale_price_dates_from' && $meta_key != '_sale_price_dates_to') return;

        $cached_products = get_transient('nitropack_sale_product_dates');
        // If $cached_products is empty, initialize it as an array
        if (empty($cached_products)) {
            $cached_products = [];
        }

        // Ensure that the $post_id key exists in the $cached_products array
        if (!isset($cached_products[$post_id])) {
            $cached_products[$post_id] = [];
        }
        //update
        if ($meta_key === '_sale_price_dates_from') {
            $cached_products[$post_id]['from'] = $meta_value;
        }
        if ($meta_key === '_sale_price_dates_to') {
            $cached_products[$post_id]['to'] = $meta_value;
        }
        //delete product
        if (empty($cached_products[$post_id]['from']) && empty($cached_products[$post_id]['to'])) {
            unset($cached_products[$post_id]);
        }
        set_transient('nitropack_sale_product_dates', $cached_products);
    }
    /**
     * Cache all products with sale dates.
     *
     * This method identifies all products that have sale dates and caches them
     * to improve performance and reduce load times for users viewing sale items.
     *
     * @return void
     */
    public function cache_sale_products() {
        $cached_products = get_transient('nitropack_sale_product_dates');
        if ($cached_products !== false) return;

        $scheduled_sale_products =  $this->get_products_with_sale();
        $sale_dates = array();
        if (!empty($scheduled_sale_products)) {

            foreach ($scheduled_sale_products as $scheduled_sale_product_id) {
                $current_product_sale_dates = $this->get_sale_dates(wc_get_product($scheduled_sale_product_id));
                $sale_dates[$scheduled_sale_product_id] = $current_product_sale_dates;
            }
        }
        //mostly it will be empty array
        set_transient('nitropack_sale_product_dates', $sale_dates);
    }

    /**
     * Purges the site cache when a sale starts or ends.
     * 
     * This function sets the X-Nitro-Expire header to the earliest future date 
     * based on the sale start or end date. It ensures that the cache is 
     * appropriately purged to reflect the changes in sale status.
     */
    public function purge_site_cache_on_sale_start_and_end() {
        $sale_dates = get_transient('nitropack_sale_product_dates');
        if ($sale_dates === false) return;

        $current_time = time();
        $valid_timestamps = [];
        foreach ($sale_dates as $product_id => $dates) {
            $timestamps = [];

            if (isset($dates['from']) && $dates['from'] >= $current_time) {
                $timestamps[] = $dates['from'];
            }

            if (isset($dates['to']) && $dates['to'] >= $current_time) {
                $timestamps[] = $dates['to'];
            }

            if (!empty($timestamps)) {
                // Use the earliest timestamp that is greater than or equal to the current time
                $valid_timestamps[$product_id] = min($timestamps);
            }
        }

        // Find the earliest date from the valid timestamps
        if (!empty($valid_timestamps)) {
            $earliest_key = array_search(min($valid_timestamps), $valid_timestamps);
            $earliest_date = $valid_timestamps[$earliest_key];


            $this->add_cache_expiration($earliest_date);
        }
    }
    /**
     * Retrieves all products that have sale dates.
     *
     * @return array An array of products that are currently on sale.
     */
    public function get_products_with_sale() {

        $product_ids = [];
        $args = array(
            'post_type'      => array('product', 'product_variation'),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_sale_price_dates_from',
                    'value'   => time(),
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ),
                array(
                    'key'     => '_sale_price_dates_to',
                    'value'   => time(),
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ),
            ),
        );

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                global $post;
                $query->the_post();
                $product_ids[] = $post->ID;
            }
        }
        wp_reset_postdata();

        return $product_ids;
    }
}
