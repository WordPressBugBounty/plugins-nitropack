<?php

namespace NitroPack\Integration\Theme;

use NitroPack\WordPress\Invalidations\InvalidationState;

/**
 * Divi 5 compatibility.
 *
 * 1. Purge downgrade. Every Divi CSS cache clear ends in et_core_clear_wp_cache(), which calls
 *    nitropack_purge( null, null, 'Divi CSS cache cleared' ) when the clear is for "all" posts.
 *    Divi 5's Loop module clears "all" on routine content events (post_updated, wp_insert_post incl.
 *    media uploads, status transitions, term/user changes), so every editor action becomes a full
 *    NitroPack purge. NitroPack's own post invalidation already covers the pages those events affect,
 *    so the full purge Divi queues during such an event is dropped (or downgraded to a full
 *    invalidation). Design-level clears (Theme Builder, presets, global data, Theme Options,
 *    Customizer, plugin/theme updates) are left untouched.
 *
 * 2. CSS to head. Divi only knows a page's module CSS after rendering the body. When its static CSS
 *    file is missing or stale (every clear above marks them all stale), it prints that CSS inline
 *    before </body>, including the Theme Builder header CSS. Divi 5 also always prints the Theme
 *    Options body font size/line height and the global variables in wp_footer, and a post's Google
 *    Fonts there on its first render. Both visitors and the NitroPack optimizer then get HTML whose
 *    first paint is unstyled, which causes large layout shifts. The page buffer moves those blocks
 *    into <head>.
 */
class Divi {
    const STAGE = 'late';

    const DIVI_REASON = 'Divi CSS cache cleared';

    // Divi 5 LoopHooks that trigger a full "all" clear for content-level changes.
    // Divi registers its callbacks at the default priority (10).
    const CONTENT_HOOKS = [
        'wp_insert_post',
        'post_updated',
        'transition_post_status',
        'delete_post',
        'wp_trash_post',
        'trashed_post',
        'wp_untrash_post',
        'added_post_meta',
        'updated_post_meta',
        'deleted_post_meta',
        'created_term',
        'delete_term',
        'user_register',
        'delete_user',
    ];

    // Set to the first Divi version that stops full purges on content events, once it ships.
    // Not fixed as of 5.17.0 (2026-10-22).
    const FIXED_IN_DIVI_VERSION = null;

    /**
     * Whether a full purge was already queued before each content hook ran.
     *
     * @var bool[]
     */
    private $snapshots = [];

    /**
     * Whether Divi 5 is active.
     *
     * @return bool
     */
    public static function is_active() {
        return defined( 'ET_BUILDER_5_DIR' ) && null !== self::get_divi_version();
    }

    /**
     * Returns the Divi version, or null if Divi isn't loaded.
     *
     * @return string|null
     */
    public static function get_divi_version() {
        if ( defined( 'ET_BUILDER_VERSION' ) ) {
            return ET_BUILDER_VERSION;
        }
        return defined( 'ET_CORE_VERSION' ) ? ET_CORE_VERSION : null;
    }

    /**
     * Module entry point.
     *
     * @param string $stage Module load stage.
     * @return void
     */
    public function init( $stage ) {
        // The Divi theme loads after plugins_loaded, so detection has to wait for the theme.
        add_action( 'after_setup_theme', [ $this, 'setup' ], 20 );
    }

    /**
     * Registers the hooks once Divi 5 is detected.
     *
     * @return void
     */
    public function setup() {
        if ( ! self::is_active() ) {
            return;
        }

        if ( $this->is_purge_downgrade_enabled() ) {
            foreach ( self::CONTENT_HOOKS as $hook ) {
                add_action( $hook, [ $this, 'snapshot_full_purge' ], 9, 0 );
                add_action( $hook, [ $this, 'downgrade_divi_full_purge' ], 11, 0 );
            }
        }

        if ( 1 === (int) get_option( \NitroPack\WordPress\Settings\DiviCssToHead::OPTION_NAME, 1 ) ) {
            add_action( 'template_redirect', [ $this, 'start_css_to_head_buffer' ], PHP_INT_MAX );
        }
    }

    /**
     * Whether Divi's full purges on content events should be downgraded.
     *
     * @return bool
     */
    private function is_purge_downgrade_enabled() {
        if ( 'purge' === $this->get_purge_mode() ) {
            return false;
        }

        $version = self::get_divi_version();
        $fixed_in = apply_filters( 'nitropack_divi_purge_fixed_in_version', self::FIXED_IN_DIVI_VERSION );
        if ( $fixed_in && $version && version_compare( $version, $fixed_in, '>=' ) ) {
            return false;
        }

        return true;
    }

    /**
     * How Divi's full purge on content events is handled:
     * skip       - drop Divi's full purge; NitroPack's own post invalidation handles the content change.
     * invalidate - replace it with a full invalidation (stale pages keep being served while they refresh). Default.
     * purge      - leave Divi's behaviour unchanged.
     *
     * @return string
     */
    private function get_purge_mode() {
        $mode = apply_filters( 'nitropack_divi_content_purge_mode', 'invalidate' );
        return in_array( $mode, [ 'skip', 'invalidate', 'purge' ], true ) ? $mode : 'skip';
    }

    /**
     * Same key InvalidationService::log_purge() uses for a purge with no URL and no tag.
     *
     * @return string
     */
    private static function full_purge_key() {
        return md5( '' );
    }

    /**
     * Records whether a full purge is already queued before Divi's callback runs.
     *
     * @return void
     */
    public function snapshot_full_purge() {
        $purges            = InvalidationState::get_purges();
        $this->snapshots[] = isset( $purges[ self::full_purge_key() ] );
    }

    /**
     * Drops (or downgrades to an invalidation) the full purge Divi queued during a content event.
     *
     * @return void
     */
    public function downgrade_divi_full_purge() {
        $already_queued = array_pop( $this->snapshots );
        if ( $already_queued ) {
            // A full purge queued before this hook (e.g. a design change) must stay.
            return;
        }

        $key    = self::full_purge_key();
        $purges = InvalidationState::get_purges();
        if ( empty( $purges[ $key ] ) ) {
            return;
        }

        // The queue keeps only the last caller's reason. If another plugin requested a full
        // purge during this hook after Divi did, its reason wins and the purge is kept. Divi itself can
        // request it more than once (nested saves), so the request count isn't used.
        if ( self::DIVI_REASON !== $purges[ $key ]['reason'] ) {
            return;
        }

        InvalidationState::remove_purge( $key );

        // The home and archive invalidations nitropack_purge() queued alongside are kept.
        if ( 'invalidate' === $this->get_purge_mode() ) {
            nitropack_invalidate( null, null, sprintf( '%s (%s, downgraded to invalidation)', self::DIVI_REASON, current_action() ) );
        }
    }

    /**
     * Starts the output buffer that moves Divi's inline CSS to the head on front-end page requests.
     *
     * @return void
     */
    public function start_css_to_head_buffer() {
        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

        if (
            is_admin()
            || wp_doing_ajax()
            || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || is_feed()
            || is_embed()
            || is_customize_preview()
            || ( ( isset( $_GET['et_fb'] ) || isset( $_GET['et_tb'] ) ) && current_user_can( 'edit_posts' ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Divi Visual/Theme Builder.
            || 'GET' !== $request_method
        ) {
            return;
        }

        // Started after NitroPack's own buffer, so this callback runs first and NitroPack
        // (cache storage and optimizer requests alike) receives the fixed HTML.
        ob_start( [ __CLASS__, 'move_inline_css_to_head' ] );
    }

    /**
     * Move Divi's late CSS from <body> to just before </head>.
     *
     * - "et-builder-module-design-*-cached-inline-styles" when inline output is forced, and
     *   "et-core-unified-*-cached-inline-styles[-N]" when static CSS is on but the file doesn't exist yet
     *   (the -N block carries the Theme Builder header/footer CSS). The <link> variant shares the id
     *   suffix but isn't a <style>, so it never matches.
     * - "et-vb-global-data" blocks: Theme Options body font size/line height and the global variables,
     *   printed in wp_footer on every page.
     * - Google Fonts printed in the footer on a post's first render.
     * Only matches after </head>, so blocks already in the head stay. HTML without such blocks is returned untouched.
     *
     * @param string $html Page HTML.
     * @return string
     */
    public static function move_inline_css_to_head( $html ) {
        if ( ! is_string( $html ) || '' === $html ) {
            return $html;
        }

        $head_end = stripos( $html, '</head>' );
        if ( false === $head_end ) {
            return $html;
        }

        $pattern = '#<style id="(?:et-builder-module-design|et-core-unified)[^"]*-cached-inline-styles(?:-\d+)?">.*?</style>'
            . '|<style class="et-vb-global-data[^"]*">.*?</style>'
            . '|<style id="et-builder-googlefonts-inline">.*?</style>'
            . "|<link rel='stylesheet' id='et-builder-googlefonts-css'[^>]*>#s";

        if ( ! preg_match_all( $pattern, $html, $matches, PREG_OFFSET_CAPTURE, $head_end ) ) {
            return $html;
        }

        $moved = '';

        // Remove from the end backwards so earlier offsets stay valid.
        foreach ( array_reverse( $matches[0] ) as $match ) {
            list( $tag, $offset ) = $match;
            $html = substr_replace( $html, '', $offset, strlen( $tag ) );
            $moved = $tag . "\n" . $moved;
        }

        return substr_replace( $html, $moved, $head_end, 0 );
    }
}
