<?php

namespace NitroPack\WordPress\Invalidations;

/**
 * Post invalidations on specific events
 */
class InvalidationService {
    /**
     * instance of the class
     * @var InvalidationService|null
     */
    private static $instance = null;

    public static function getInstance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Main logic of invalidating the cache for a post and its related taxonomies.
     * Checks if the post type is cacheable, and if so, invalidates the cache for the post and its related taxonomies.
     * @param \WP_Post $post
     * @param array | null $taxonomies
     * @param bool | null $important_changes
     * @param string | null $reason
     * @param bool $usePurge
     * @return void
     */
    public function clean_post_cache( ?\WP_Post $post, $taxonomies = null, $important_changes = null, $reason = null, $usePurge = false ) {
        if ( ! $post ) {
            return;
        }

        $post_type = isset( $post->post_type ) ? $post->post_type : "post";
        $nicePostTypeLabel = $this->readable_post_type_name( $post_type );
        $reason = $reason ? $reason : sprintf( "Updated %s '%s'", $nicePostTypeLabel, $post->post_title );
        $cpt_optimization = \NitroPack\WordPress\Settings\CPTOptimization::getInstance();
        $cacheble_post_types = $cpt_optimization->nitropack_get_cacheable_object_types();

        if ( ! in_array( $post_type, $cacheble_post_types ) ) {
            $this->invalidate_non_cacheable_post( $post, $reason );
            return;
        }

        // Detect important changes only when the caller hasn't asserted them (null = unknown).
        if ( $important_changes === null ) {
            $important_changes = (bool) $this->has_post_important_change( $post );
        }

        if ( $taxonomies === null ) {
            if ( $important_changes ) { // This change should be reflected in all taxonomy pages
                $taxonomies = array( 'related' => $this->get_taxonomies( $post ) );
            } else { // No important change, so only update taxonomy pages which have been added or removed from the post
                $taxonomies = $this->get_taxonomies_for_update( $post );
            }
        }

        // Always sent: while NitroPack is unreachable the SDK keeps the request in the backlog and replays it later,
        // so the optimized copy on NitroPack's side gets refreshed too.
        $this->invalidate_cacheable_post( $post, $taxonomies, $important_changes, $reason, $usePurge );

        if ( $this->should_serve_cache_when_api_down() ) {
            // If NitroPack turns out to be unreachable, the remote invalidation above can't tell which local files
            // to clear, so these URLs are purged locally on shutdown (see InvalidationSubscriber::execute_local_purges()).
            // Health can only be known after the remote calls are made, so the decision is made there.
            $this->queue_local_purges( $post, $taxonomies, $important_changes );
        }
    }

    /**
     * Invalidate (or purge) a cacheable post and its taxonomy pages through NitroPack.
     * @param \WP_Post $post
     * @param array $taxonomies Term taxonomy IDs under the keys 'added', 'deleted' and 'related'.
     * @param bool $important_changes
     * @param string $reason
     * @param bool $usePurge
     * @return void
     */
    private function invalidate_cacheable_post( \WP_Post $post, array $taxonomies, bool $important_changes, string $reason, bool $usePurge ) {
        $postID = $post->ID;

        if ( $important_changes && $post->post_type === 'post' ) {
            nitropack_invalidate( null, "pageType:blogindex", 'Invalidation of blog page due to changing related post status' );
        }

        if ( $usePurge ) {
            // Purge so the page stops being served right away; listings still need to refresh when the change is important.
            nitropack_purge( null, "post:$postID", $reason, $important_changes );
        } elseif ( $postID == get_option( 'page_on_front' ) ) {
            nitropack_invalidate( null, "pageType:home", 'Updated Homepage' );
        } else {
            nitropack_invalidate( null, "post:$postID", $reason, $important_changes );
        }

        // 'added' pages need a full refresh; 'deleted' and 'related' use taxpost: so paginated pages are covered too.
        foreach ( $taxonomies['added'] ?? [] as $term_taxonomy_id ) {
            nitropack_invalidate( null, "tax:$term_taxonomy_id", $reason, false );
        }
        foreach ( array_merge( $taxonomies['deleted'] ?? [], $taxonomies['related'] ?? [] ) as $term_taxonomy_id ) {
            nitropack_invalidate( null, "taxpost:$term_taxonomy_id:$postID", $reason, false );
        }
    }

    /**
     * Invalidate a post type which NitroPack doesn't cache: the post itself (if public) and its ancestors.
     * @param \WP_Post $post
     * @param string $reason
     * @return void
     */
    private function invalidate_non_cacheable_post( \WP_Post $post, string $reason ) {
        $post_type_object = get_post_type_object( $post->post_type );
        if ( $post_type_object && ! empty( $post_type_object->public ) ) {
            nitropack_invalidate( null, "post:{$post->ID}", $reason );
        }

        foreach ( get_post_ancestors( $post->ID ) as $parentID ) {
            $this->clean_post_cache( get_post( $parentID ), null, false, $reason );
        }
    }

    /**
     * The backlog keeps the local and remote caches in sync, so serving cache while the API is down requires it enabled.
     * @return bool
     */
    private function should_serve_cache_when_api_down() {
        return defined( 'NITROPACK_SERVE_CACHE_WHEN_API_DOWN' ) && ( ! defined( 'NITROPACK_DISABLE_BACKLOG' ) || ! NITROPACK_DISABLE_BACKLOG );
    }

    /**
     * Queue every affected URL to have its local cache purged on shutdown, in case NitroPack is unreachable
     * (see InvalidationSubscriber::execute_local_purges()).
     * @param \WP_Post $post
     * @param array $taxonomies Term taxonomy IDs under the keys 'added', 'deleted' and 'related'.
     * @param bool $important_changes
     * @return void
     */
    private function queue_local_purges( \WP_Post $post, array $taxonomies, bool $important_changes ) {
        $urls = [ get_permalink( $post ) ];
        // A post that stopped being published (trash, draft, private) has lost its public URL: get_permalink() now
        // gives "?p=ID" or the "__trashed" slug. Its cached page sits under the URL it had before the update, as does
        // the page of a post whose slug changed.
        $post_before = InvalidationState::get_post_pre_update( $post );
        if ( $post_before && 'publish' === $post_before->post_status ) {
            $published = clone $post_before;
            // Trashing renames the slug before the pre-update snapshot is taken; WordPress keeps the original one.
            $desired_slug = 'trash' === $post->post_status ? get_post_meta( $post->ID, '_wp_desired_post_slug', true ) : '';
            if ( $desired_slug ) {
                $published->post_name = $desired_slug;
            }
            $urls[] = get_permalink( $published );
            $urls = array_merge( $urls, $this->get_descendant_urls_before_update( $post, $published ) );
        }
        if ( $important_changes ) {
            $siteConfig = nitropack_get_site_config();
            $urls[] = $siteConfig && ! empty( $siteConfig["home_url"] ) ? $siteConfig["home_url"] : get_home_url();

            $blogPageId = (int) get_option( 'page_for_posts' );
            if ( $post->post_type === 'post' && $blogPageId ) {
                $urls[] = get_permalink( $blogPageId );
            }
        }

        $termTaxonomyIds = array_merge( $taxonomies['added'] ?? [], $taxonomies['deleted'] ?? [], $taxonomies['related'] ?? [] );
        foreach ( array_unique( $termTaxonomyIds ) as $termTaxonomyId ) {
            $term = get_term_by( 'term_taxonomy_id', $termTaxonomyId );
            $urls[] = $term ? get_term_link( $term ) : null;
        }

        foreach ( $urls as $url ) {
            if ( is_string( $url ) && $url !== '' ) { // get_permalink() can return false and get_term_link() a WP_Error
                InvalidationState::enqueue_local_purge( $url );
            }
        }
    }
    /**
     * URLs the published descendants of a hierarchical post (e.g. child pages) had before the update.
     * Their URLs contain the post's path, so when that path changes (slug change, trash, new parent) their cached
     * pages sit under the old URLs. Unpublishing to draft keeps the path, so it returns nothing.
     * @param \WP_Post $post The post after the update.
     * @param \WP_Post $published The post as it was published before the update.
     * @return string[]
     */
    private function get_descendant_urls_before_update( \WP_Post $post, \WP_Post $published ) {
        if ( ! is_post_type_hierarchical( $post->post_type ) || ! get_option( 'permalink_structure' ) ) {
            return [];
        }

        $old_path = '/' . get_page_uri( $published ) . '/';
        $new_path = '/' . get_page_uri( $post ) . '/';
        if ( $old_path === $new_path ) {
            return [];
        }

        $descendants = get_pages( [
            'child_of' => $post->ID,
            'post_type' => $post->post_type,
            'post_status' => 'publish',
        ] );

        $urls = [];
        foreach ( $descendants ?: [] as $descendant ) {
            $url = get_permalink( $descendant );
            $pos = is_string( $url ) ? strpos( $url, $new_path ) : false;
            if ( false !== $pos ) {
                $urls[] = substr_replace( $url, $old_path, $pos, strlen( $new_path ) );
            }
        }
        return $urls;
    }

    /**
     * Queue invalidation of the home and archive listing pages.
     * Separated so callers only pay the cost of purging every archive URL when a
     * listing-visible field (title/slug/excerpt) actually changed.
     * @param string|null $reason
     * @return void
     */
    public function invalidate_listings( ?string $reason = null ) {
        $siteConfig = nitropack_get_site_config();
        $home_url = $siteConfig && ! empty( $siteConfig["home_url"] ) ? $siteConfig["home_url"] : get_home_url();
        $this->log_invalidate( $this->should_serve_cache_when_api_down() ? null : $home_url, "pageType:home", $reason );
        $this->log_invalidate( null, "pageType:archive", $reason );
    }
    /**
     * Log NitroPack invalidations in the InvalidationState invalidation queue
     * @param string $url
     * @param string|null $tag
     * @param string|null $reason
     * @return void
     */
    public function log_invalidate( ?string $url = null, ?string $tag = null, ?string $reason = null ) {
        if ( $tag && is_array( $tag ) ) {
            foreach ( $tag as $tagSingle ) {
                $this->log_invalidate( $url, $tagSingle, $reason );
            }
            return;
        }

        $keyBase = "";
        if ( $url ) {
            $keyBase .= $url;
        }

        if ( $tag ) {
            $tag = nitropack_filter_tag( $tag );
            $keyBase .= $tag;
        }

        $invalidateRequestKey = md5( $keyBase );
        InvalidationState::enqueue_invalidation( $invalidateRequestKey, $url, $tag, $reason );
    }
    /**
     * Logs NitroPack purges in the InvalidationState purge queue
     * @param string $url
     * @param string|null $tag
     * @param string|null $reason
     * @return void
     */
    public function log_purge( $url = null, $tag = null, $reason = null ) {
        if ( $tag && is_array( $tag ) ) {
            foreach ( $tag as $tagSingle ) {
                $this->log_purge( $url, $tagSingle, $reason );
            }
            return;
        }

        $keyBase = "";
        if ( $url ) {
            $keyBase .= $url;
        }

        if ( $tag ) {
            $tag = nitropack_filter_tag( $tag );
            $keyBase .= $tag;
        }

        $purgeRequestKey = md5( $keyBase );
        InvalidationState::enqueue_purge( $purgeRequestKey, $url, $tag, $reason );
    }
    /**
     * Detects if a post has important changes (title, name, or excerpt)
     * @param \WP_Post $post
     * @return bool
     **/
    private function has_post_important_change( $post ) {
        $prevPost = InvalidationState::get_post_pre_update( $post );
        return $prevPost && ( $prevPost->post_title != $post->post_title || $prevPost->post_name != $post->post_name || $prevPost->post_excerpt != $post->post_excerpt );
    }
    /**
     * Formats the post type into a nice label
     * @param string $postType
     */
    public function readable_post_type_name( string $postType ) {
        $postTypes = get_post_types( array(
            "name" => $postType
        ), "objects" );

        return ! empty( $postTypes[ $postType ] ) && ! empty( $postTypes[ $postType ]->labels ) ? $postTypes[ $postType ]->labels->singular_name : $postType;
    }

    /**
     * Grabs the taxonomies for a given post and returns an array of term_taxonomy_ids
     * @param WP_Post $post
     * @return array of term_taxonomy_ids
     */
    public function get_taxonomies( \WP_Post $post ) {
        $term_taxonomy_ids = array();
        $taxonomies = get_object_taxonomies( $post->post_type );
        foreach ( $taxonomies as $taxonomy ) {
            $terms = get_the_terms( $post->ID, $taxonomy );
            if ( ! empty( $terms ) ) {
                foreach ( $terms as $term ) {
                    $term_taxonomy_ids[] = $term->term_taxonomy_id;
                }
            }
        }
        return $term_taxonomy_ids;
    }

    /**
     * Grabs the taxonomies for a given post and returns an array of term_taxonomy_ids that have been added or deleted since the last update
     * @param \WP_Post $post
     * @return array{added: array, deleted: array}
     */
    public function get_taxonomies_for_update( \WP_Post $post ) {
        $prevTaxonomies = InvalidationState::get_taxonomies_pre_update( $post );
        $newTaxonomies = $this->get_taxonomies( $post );
        $intersection = array_intersect( $newTaxonomies, $prevTaxonomies );
        $prevTaxonomies = array_diff( $prevTaxonomies, $intersection );
        $newTaxonomies = array_diff( $newTaxonomies, $intersection );
        return array(
            "added" => array_diff( $newTaxonomies, $prevTaxonomies ),
            "deleted" => array_diff( $prevTaxonomies, $newTaxonomies )
        );
    }

    /**
     * Compare two posts or one post and its associated data - post_meta or taxonomies
     * Return true if they are equal, false otherwise.
     * @param array $p1
     * @param array $p2
     * @param array|null $ignored_keys
     * @return bool
     */
    public function compare_posts( array $p1, array $p2, ?array $ignored_keys = null ) {
        if ( count( $p1 ) !== count( $p2 ) ) {
            return false;
        }

        // Cheap list detection with early exit, replacing the old double full-iteration + array_diff.
        $p1IsList = true;
        $expectedKey = 0;
        foreach ( $p1 as $k => $_ ) {
            if ( $k !== $expectedKey++ ) {
                $p1IsList = false;
                break;
            }
        }
        $p2IsList = true;
        $expectedKey = 0;
        foreach ( $p2 as $k => $_ ) {
            if ( $k !== $expectedKey++ ) {
                $p2IsList = false;
                break;
            }
        }
        if ( $p1IsList !== $p2IsList ) {
            return false;
        }

        if ( $p1IsList ) {
            // Order-insensitive comparison for list arrays (e.g. taxonomy term_taxonomy_ids).
            sort( $p1 );
            sort( $p2 );
        }

        foreach ( $p1 as $poKey => $poVal ) {
            if ( $ignored_keys && in_array( $poKey, $ignored_keys, true ) ) {
                continue;
            }
            if ( ! array_key_exists( $poKey, $p2 ) ) {
                return false;
            }
            $otherVal = $p2[ $poKey ];
            $valIsArray = is_array( $poVal );
            $otherIsArray = is_array( $otherVal );
            if ( $valIsArray && $otherIsArray ) {
                if ( ! $this->compare_posts( $poVal, $otherVal, $ignored_keys ) ) {
                    return false;
                }
            } elseif ( $valIsArray || $otherIsArray ) {
                return false;
            } elseif ( (string) $poVal !== (string) $otherVal ) {
                return false;
            }
        }
        return true;
    }
}