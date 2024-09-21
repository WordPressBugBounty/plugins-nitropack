<?php $processing = 0;
$usage = '0 MB';
$max_usage = '1 GB';
$page_views = '0';
$max_page_views = '10000'; ?>
<?php nitropack_display_admin_notices(); ?>
<div class="grid grid-cols-2 gap-6 grid-col-1-tablet items-start">
  <div class="col-span-1">
    <!-- Optimized Pages Card -->
    <div class="card card-optimized-pages">
      <div class="card-header">
        <h3><?php esc_html_e('Optimized pages', 'nitropack'); ?></h3>
        <div class="flex flex-row items-center" style="display: none;" id="pending-optimizations-section">
          <img src="<?php echo plugin_dir_url(__FILE__) . 'images/loading.svg'; ?>" alt="loading" class="w-4 h-4">
          <span class="ml-2 mr-1 text-primary"> <?php esc_html_e('Processing', 'nitropack'); ?>
            <span id="pending-optimizations-count">X</span> <?php esc_html_e('page(s) in the background', 'nitropack'); ?></span>
        </div>
      </div>
      <div class="card-body">
        <div class="card-body-inner">
          <div class="optimized-pages"><span data-optimized-pages-total>0</span></div>
          <div class="text-box">
            <div class="time-ago"><?php esc_html_e('Last cache purge', 'nitropack'); ?>: <span data-last-cache-purge><?php esc_html_e('Never', 'nitropack'); ?></span></div>
            <div class="reason"><?php esc_html_e('Reason', 'nitropack'); ?>: <span data-purge-reason><?php esc_html_e('Unknown', 'nitropack'); ?></span></div>
          </div>
          <button id="optimizations-purge-cache" type="button" class="btn btn-secondary" data-modal-target="modal-purge-cache" data-modal-toggle="modal-purge-cache"><?php esc_html_e('Purge cache', 'nitropack'); ?></button>
        </div>
      </div>
      <?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-purge-cache.php'; ?>
    </div>
    <!-- Optimized Pages Card End -->
    <!-- Optimization Mode Card -->
    <div class="card card-optimization-mode">
      <div class="card-header no-border mb-0">
        <div class="flex items-center">
          <h3 class="mb-0"><?php esc_html_e('Optimization mode', 'nitropack'); ?></h3>
          <span class="tooltip-icon" data-tooltip-target="tooltip-optimization">
            <img src="<?php echo plugin_dir_url(__FILE__) . 'images/info.svg'; ?>">
          </span>
          <div id="tooltip-optimization" role="tooltip" class="tooltip-container hidden">
            <?php esc_html_e('Select from our range of predefined optimization modes to boost your site\'s performance.', 'nitropack');
            ?>
            <div class="tooltip-arrow" data-popper-arrow></div>
          </div>
        </div>
      </div>
      <?php $modes = array('standard' => esc_html__('Standard', 'nitropack'), 'medium' =>  esc_html__('Medium', 'nitropack'), 'strong' =>  esc_html__('Strong', 'nitropack'), 'ludicrous' =>  esc_html__('Ludicrous', 'nitropack'), 'custom' =>  esc_html__('Custom', 'nitropack')); ?>
      <div class="tabs-wrapper">
        <div class="tabs" id="optimization-modes">
          <?php foreach ($modes as $mode_id => $mode) : ?>
            <a class="btn tab-link btn-link" data-mode="<?php echo $mode_id; ?>" data-modal-target="modal-optimization-mode" data-modal-toggle="modal-optimization-mode"><?php echo $mode; ?></a>
          <?php endforeach; ?>
        </div>
        <p><?php esc_html_e('Active Mode', 'nitropack'); ?>: <span class="active-mode"></span></p>
        <div class="tab-content-wrapper">
          <div class="hidden tab-content" role="tabpanel" data-tab="standard-tab">
            <p class="text-secondary mt-2"> <?php esc_html_e('Standard optimization features enabled for your site. Ideal choice for maximum stability.', 'nitropack'); ?></p>
          </div>
          <div class="hidden tab-content" role="tabpanel" data-tab="medium-tab">
            <p class="text-secondary mt-2"> <?php esc_html_e('Adds image lazy loading to standard optimizations. Uses built-in browser techniques for loading resources.', 'nitropack'); ?></p>
          </div>
          <div class="hidden tab-content" role="tabpanel" data-tab="strong-tab">
            <p class="text-secondary mt-2"> <?php esc_html_e('Includes smart resource loading on top of Medium optimizations. Balances speed boost with stability.', 'nitropack'); ?></p>
          </div>
          <div class="hidden tab-content" role="tabpanel" data-tab="ludicrous-tab">
            <p class="text-secondary mt-2"> <?php esc_html_e('Applies deferred JS and advanced resource loading for optimal performance and Core Web Vitals.', 'nitropack'); ?></p>
          </div>
          <div class="hidden tab-content" role="tabpanel" data-tab="custom-tab">
            <p class="text-secondary mt-2"> <?php esc_html_e('Activated when manual setups are made. Ideal for advanced NitroPack optimizations.', 'nitropack'); ?></p>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <div class="flex flex-row">
          <p class=""><?php esc_html_e('Which optimization mode to choose?', 'nitropack'); ?></p>
          <a class="text-primary btn-link ml-auto see-modes" data-modal-target="modes-modal" data-modal-toggle="modes-modal"><?php esc_html_e('See modes comparison', 'nitropack'); ?></a>
          <?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-modes.php'; ?>
        </div>
      </div>
      <?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-optimization-mode.php'; ?>
    </div>
    <!-- Optimization Mode Card End -->
    <!-- Automated Behavior Card -->
    <div class="card card-automated-behavior">
      <div class="card-header">
        <h3><?php esc_html_e('Automated Behavior', 'nitropack'); ?></h3>
      </div>
      <div class="card-body">
        <div class="options-container">
          <div class="nitro-option" id="purge-cache-widget">
            <div class="nitro-option-main">
              <div class="text-box">
                <h6><?php esc_html_e('Purge cache', 'nitropack'); ?></h6>
                <p><?php esc_html_e('Purge affected cache when content is updated or published', 'nitropack'); ?></p>
              </div>
              <label class="inline-flex items-center cursor-pointer ml-auto">
                <input type="checkbox" value="" class="sr-only peer" name="purge_cache" id="auto-purge-status" <?php if ($autoCachePurge) echo "checked"; ?>>
                <div class="toggle"></div>
              </label>
            </div>
          </div>
          <div class="nitro-option" id="page-optimization-widget">
            <div class="nitro-option-main">
              <div class="text-box">
                <h6><?php esc_html_e('Page optimization', 'nitropack'); ?></h6>
                <p><?php esc_html_e('Select what post/page types get optimized', 'nitropack'); ?></p>
              </div>
              <a data-modal-target="modal-posttypes" data-modal-toggle="modal-posttypes" class="btn btn-secondary btn-icon">
                <img src="<?php echo plugin_dir_url(__FILE__); ?>images/setting-icon.svg">
              </a>
            </div>
            <?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-posttypes.php'; ?>
          </div>
        </div>
      </div>
    </div>
    <!-- Automated Behavior Card End -->
    <!-- Go to app Card -->
    <div class="card exclusion-card">
      <div class="card-header">
        <h3><?php esc_html_e('Exclusions', 'nitropack'); ?></h3>
      </div>
      <div class="card-body">
        <div class="options-container">
          <div class="nitro-option" id="ajax-shortcodes-widget">
            <div class="nitro-option-main">
              <div class="text-box">
                <h6><?php esc_html_e('Shortcodes exclusions', 'nitropack'); ?></h6>
                <p><?php esc_html_e('Load widgets, feeds, and any shortcode with AJAX to bypass the cache and always show the latest content.', 'nitropack'); ?></p>
              </div>
              <?php
              global $shortcode_tags;
              $nitropack = get_nitropack();
              $siteConfig = $nitropack->Config->get();
              $configKey = \NitroPack\WordPress\NitroPack::getConfigKey();

              $ajax_shortcodes = $siteConfig[$configKey]['options_cache']['ajaxShortcodes'];
              $ajax_shortcodes_enabled = $ajax_shortcodes['enabled'];
              $shortcode_container_shown = $ajax_shortcodes_enabled ? '' : 'hidden';

              ?>
              <label class="inline-flex items-center cursor-pointer ml-auto">
                <input type="checkbox" value="" class="sr-only peer" name="ajax_shortcodes" id="ajax-shortcodes" <?php if ($ajax_shortcodes_enabled) echo "checked"; ?>>
                <div class="toggle"></div>
              </label>
            </div>
            <div class="ajax-shortcodes <?php echo $shortcode_container_shown; ?>">
              <div class="select-wrapper">
                <select class="" name="nitropack-ajaxShortcodes" id="ajax-shortcodes-dropdown" multiple>
                  <?php
                  if (isset($ajax_shortcodes['shortcodes'])) {
                    $ajax_shortcodes_list = $ajax_shortcodes['shortcodes'];
                    $freely_added_shortcodes = array_diff($ajax_shortcodes_list, array_keys($shortcode_tags));
                  }
                  foreach ($shortcode_tags as $shortcode => $function) {
                    $disable = '';
                    if ($ajax_shortcodes_list && in_array($shortcode, $ajax_shortcodes_list)) $disable = 'selected="selected"';
                    echo '<option value="' . $shortcode . '" ' . $disable . '>' . $shortcode . '</option>';
                  }
                  if ($freely_added_shortcodes) {
                    foreach ($freely_added_shortcodes as $shortcode) {
                      echo '<option value="' . $shortcode . '" selected="selected">' . $shortcode . '</option>';
                    }
                  }
                  ?>
                </select>
                <button class="btn btn-primary" id="save-shortcodes"><?php esc_html_e('Save', 'nitropack'); ?></button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Go to app card End -->


  </div>
  <div class="col-span-1">
    <!-- Subscription Card -->
    <div class="card card-subscription">
      <div class="card-header">
        <h3><?php esc_html_e('Subscription', 'nitropack'); ?></h3>
      </div>
      <div class="card-body">
        <div class="flex flex-row items-center">
          <div class="plan-name"><?php esc_html_e('Free', 'nitropack'); ?></div>
          <a type="button" target="_blank" href="https://app.nitropack.io/account/billing" class="btn btn-secondary ml-auto" id="btn-manage-subscription"><?php esc_html_e('Manage subscription', 'nitropack'); ?></a>
        </div>
        <div class="table-wrapper">
          <table class="w-full">
            <tbody>
              <tr>
                <td class="key"><?php esc_html_e('Next reset', 'nitropack'); ?></td>
                <td class="value" data-next-reset><?php esc_html_e('No ETA', 'nitropack'); ?></td>
              </tr>
              <tr>
                <td class="key"><?php esc_html_e('Next billing', 'nitropack'); ?></td>
                <td class="value" data-next-billing><?php esc_html_e('No ETA', 'nitropack'); ?></td>
              </tr>
              <tr>
                <td class="key"><?php esc_html_e('Page views', 'nitropack'); ?></td>
                <td class="value" data-page-views><?php printf(esc_html__('%1$s out of %2$s', 'nitropack'), $page_views, $max_page_views); ?></td>
              </tr>
              <tr>
                <td class="key"><?php esc_html_e('CDN bandwidth', 'nitropack'); ?></td>
                <td class="value" data-cdn-bandwidth><?php printf(esc_html__('%1$s out of %2$s', 'nitropack'), $usage, $max_usage); ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer">
        <p class="text-secondary text-smaller"><?php esc_html_e('You will be notified by email when your website reaches the subscription resource limits.', 'nitropack'); ?></p>
      </div>
    </div>
    <!-- Subscription Card End -->
    <!-- Basic Settings Card -->
    <div class="card card-basic-settings">
      <div class="card-header">
        <h3><?php esc_html_e('Basic Settings', 'nitropack'); ?></h3>
      </div>
      <div class="card-body">
        <div class="options-container">
          <div class="nitro-option" id="cache-warmup-widget">
            <div class="nitro-option-main">
              <div class="text-box" id="warmup-status-slider">

                <?php $sitemap = get_option('np_warmup_sitemap', false);
                $toolTipDisplayState = $sitemap ? '' : 'hidden'; ?>

                <h6><?php esc_html_e('Cache warmup', 'nitropack'); ?> <span class="badge badge-primary ml-2"><?php esc_html_e('Recommended', 'nitropack'); ?></span> <span class="tooltip-icon <?php echo $toolTipDisplayState; ?>" data-tooltip-target="tooltip-sitemap">
                    <img src="<?php echo plugin_dir_url(__FILE__) . 'images/info.svg'; ?>">
                  </span></h6>
                <div id="tooltip-sitemap" role="tooltip" class="tooltip-container hidden">
                  <?php echo $sitemap; ?>
                  <div class="tooltip-arrow" data-popper-arrow></div>
                </div>
                <p><?php esc_html_e('Automatically pre-caches your website\'s page content', 'nitropack'); ?>. <a href="https://support.nitropack.io/en/articles/8390320-cache-warmup" class="text-blue" target="_blank"><?php esc_html_e('Learn more', 'nitropack'); ?></a></p>
              </div>
              <label class="inline-flex items-center cursor-pointer ml-auto">
                <input id="warmup-status" type="checkbox" class="sr-only peer">
                <div class="toggle"></div>
              </label>
            </div>
            <div class="msg-container" id="loading-warmup-status">
              <img src="<?php echo plugin_dir_url(__FILE__) . 'images/loading.svg'; ?>" alt="loading" class="icon"> <span class="msg"><?php esc_html_e('Loading cache warmup status', 'nitropack'); ?></span>
            </div>
          </div>
          <div class="nitro-option" id="test-mode-widget">
            <div class="nitro-option-main">
              <div class="text-box" id="safemode-status-slider">
                <h6><?php esc_html_e('Test Mode', 'nitropack'); ?></h6>
                <p><?php esc_html_e('Test NitroPack\'s features without affecting your visitors\' experience', 'nitropack'); ?>. <a href="https://support.nitropack.io/en/articles/8390292-test-mode" class="text-blue" target="_blank"><?php esc_html_e('Learn more', 'nitropack'); ?></a></p>
              </div>

              <label class="inline-flex items-center cursor-pointer ml-auto">
                <input type="checkbox" class="sr-only peer" id="safemode-status">

                <div class="toggle"></div>
              </label>
            </div>
            <div class="msg-container" id="loading-safemode-status">
              <img src="<?php echo plugin_dir_url(__FILE__) . 'images/loading.svg'; ?>" alt="loading" class="icon"> <?php esc_html_e('Loading test mode status', 'nitropack'); ?>
            </div>
            <?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-test-mode.php'; ?>
          </div>
          <div class="nitro-option" id="compression-widget">
            <div class="nitro-option-main">
              <div class="text-box">
                <h6><span id="detected-compression"><?php esc_html_e('HTML Compression', 'nitropack'); ?> </span></h6>
                <p><?php esc_html_e('Compressing the structure of your HTML, ensures faster page rendering and an optimized browsing experience for your users.', 'nitropack'); ?> <a href="https://support.nitropack.io/en/articles/8390333-nitropack-plugin-settings-in-wordpress#h_29b7ab4836" class="text-blue" target="_blank"><?php esc_html_e('Learn more', 'nitropack'); ?></a></p>
              </div>
              <label class="inline-flex items-center cursor-pointer ml-auto">
                <input type="checkbox" id="compression-status" class="sr-only peer" <?php echo (int)$enableCompression === 1 ? "checked" : ""; ?>>
                <div class="toggle"></div>
              </label>
            </div>
            <div class="mt-4 text-primary">
              <a href="javascript:void(0);" id="compression-test-btn" class="text-primary"><?php esc_html_e('Run compression test', 'nitropack'); ?></a>
              <div class="flex items-start msg-container hidden">
                <span class="msg"></span>
              </div>
            </div>
          </div>
          <?php if (\NitroPack\Integration\Plugin\BeaverBuilder::isActive()) { ?>
            <div class="nitro-option" id="beaver-builder-widget">
              <div class="nitro-option-main">
                <div class="text-box">
                  <h6><span id="detected-compression"><?php esc_html_e('Sync NitroPack Purge with Beaver Builder', 'nitropack'); ?> </span></h6>
                  <p><?php esc_html_e('When Beaver Builder cache is purged, NitroPack will perform a full cache purge keeping your site\'s content up-to-date.', 'nitropack'); ?></p>
                </div>
                <label class="inline-flex items-center cursor-pointer ml-auto">
                  <input type="checkbox" class="sr-only peer" id="bb-purge-status" <?php if ($bbCacheSyncPurge) echo "checked"; ?>>
                  <div class="toggle"></div>
                </label>
              </div>
            </div>
          <?php } ?>

          <?php if (nitropack_render_woocommerce_cart_cache_option()) { ?>
            <div class="nitro-option" id="cart-cache-widget">
              <div class="nitro-option-main">
                <div class="text-box">
                  <h6><?php esc_html_e('Cart cache', 'nitropack'); ?> <span class="badge badge-success ml-2">New</span></h6>
                  <p><?php esc_html_e('Your visitors will enjoy full site speed while browsing with items in cart. Fully optimized page cache will be served.', 'nitropack'); ?></p>

                </div>
                <label class="inline-flex items-center cursor-pointer ml-auto">
                  <input type="checkbox" id="cart-cache-status" class="sr-only peer" <?php if (nitropack_is_cart_cache_active()) echo "checked"; ?> <?php if (!nitropack_is_cart_cache_available()) echo "disabled"; ?>>
                  <div class="toggle"></div>
                </label>
              </div>
              <?php if (!nitropack_is_cart_cache_available()) : ?>
                <div class="msg-container bg-success paid-msg">
                  <p><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-success">
                      <g clip-path="url(#clip0_1244_36215)">
                        <path d="M10.0001 18.3333C14.6025 18.3333 18.3334 14.6023 18.3334 9.99996C18.3334 5.39759 14.6025 1.66663 10.0001 1.66663C5.39771 1.66663 1.66675 5.39759 1.66675 9.99996C1.66675 14.6023 5.39771 18.3333 10.0001 18.3333Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M13.3334 9.99996L10.0001 6.66663L6.66675 9.99996" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M10 13.3333V6.66663" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                      </g>
                      <defs>
                        <clipPath id="clip0_1244_36215">
                          <rect width="20" height="20" fill="white"></rect>
                        </clipPath>
                      </defs>
                    </svg> <?php esc_html_e('This feature is available on paid subscription.', 'nitropack'); ?> <a href="https://app.nitropack.io/subscription/buy" class="text-primary" target="_blank"><b><?php esc_html_e('Upgrade here', 'nitropack'); ?></b></a>
                  </p>
                </div>
              <?php endif; ?>
            </div>
            <div class="nitro-option" id="real-time-stock-refresh-widget">
              <div class="nitro-option-main">
                <div class="text-box">
                  <h6><?php esc_html_e('Real-time Stock Refresh', 'nitropack'); ?></h6>
                  <p><?php esc_html_e('Keep accurate product availability on your WooCommerce site. Turn on this feature if you display stock quantities, and enjoy automatic cache clearance when stock decreases.', 'nitropack'); ?></p>

                </div>
                <label class="inline-flex items-center cursor-pointer ml-auto">
                  <input type="checkbox" id="woo-stock-reduce-status" class="sr-only peer" <?php echo (int)$stockReduceStatus === 1 ? "checked" : ""; ?>>
                  <div class="toggle"></div>
                </label>
              </div>
            </div>
          <?php } ?>
        </div>
      </div>
      <div class="card-footer disconnect-container">
        <a class="text-primary btn-link" id="disconnect-btn"><?php esc_html_e('Disconnect NitroPack plugin', 'nitropack'); ?></a>
        <?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-disconnect.php'; ?>
      </div>
    </div>
    <!-- Basic Settings Card End -->
    <!-- Go to app Card -->
    <div class="card app-card">
      <div class="card-body">
        <div class="flex items-center justify-between">
          <p><?php esc_html_e('You can further configure how NitroPack\'s optimization behaves through your account', 'nitropack'); ?>.</p>
          <?php
          function getNitropackDashboardUrl() {
            $siteId = nitropack_get_current_site_id();
            $dashboardUrl = 'https://app.nitropack.io/dashboard';

            if ($siteId !== null) {
              $dashboardUrl .= '?update_session_website_id=' . urlencode($siteId);
            }

            return $dashboardUrl;
          }
          ?>
          <a href="<?php echo esc_url(getNitropackDashboardUrl()); ?>" target="_blank" class="btn btn-primary ml-2 flex-shrink-0"><?php esc_html_e('Go to app', 'nitropack'); ?></a>
        </div>
      </div>
    </div>
    <!-- Go to app card End -->
  </div>
  <?php $notOptimizedCPTs = nitropack_filter_non_optimized();
  if (!get_option('nitropack-noticeOptimizeCPT') && !empty($notOptimizedCPTs))  require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-not-optimized-CPT.php'; ?>
</div>
<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-unsaved-changes.php'; ?>
<script>
  ($ => {
    var getOptimizationsTimeout = null;
    let isClearing = false;
    var paid_plan = false;
    $(window).on("load", function() {
      getOptimizations();
      getPlan();
      <?php if ($checkedCompression != 1) { ?>
        autoDetectCompression();
      <?php } ?>
    });

    /* Cache Purge begin */
    window.performCachePurge = () => {
      purgeCache();
    }

    let purgeCache = () => {
      let purgeEvent = new Event("cache.purge.request");
      window.dispatchEvent(purgeEvent);
    }

    var getOptimizations = _ => {
      var url = '<?php echo $optimizationDetailsUrl; ?>';
      ((s, e, f) => {
        if (window.fetch) {
          fetch(url)
            .then(resp => resp.json())
            .then(s)
            .catch(e)
            .finally(f);
        } else {
          $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: s,
            error: e,
            complete: f
          })
        }
      })(data => {
        $('[data-last-cache-purge]').text(data.last_cache_purge.timeAgo);
        if (data.last_cache_purge.reason) {
          $('[data-purge-reason]').text(data.last_cache_purge.reason);
          $('[data-purge-reason]').attr('title', data.last_cache_purge.reason);
          $('#last-cache-purge-reason').show();
        } else {
          $('#last-cache-purge-reason').hide();
        }
        if (data.pending_count) {
          $("#pending-optimizations-count").text(data.pending_count);
          $("#pending-optimizations-section").show();
        } else {
          $("#pending-optimizations-section").hide();
        }

        $('[data-optimized-pages-total]').text(data.optimized_pages.total);

      }, __ => {
        console.error("An error occurred while fetching data for optimized pages");
      }, __ => {
        if (!getOptimizationsTimeout) {
          getOptimizationsTimeout = setTimeout(function() {
            getOptimizationsTimeout = null;
            getOptimizations();
          }, 60000);
        }
      });
    }

    var getPlan = _ => {

      var url = '<?php echo $planDetailsUrl; ?>';
      ((s, e, f) => {
        if (window.fetch) {
          fetch(url)
            .then(resp => resp.json())
            .then(s)
            .catch(e)
            .finally(f);
        } else {
          $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: s,
            error: e,
            complete: f
          })
        }
      })(data => {

        $('.plan-name').text(data.plan_title);
        $('[data-next-billing]').text(data.next_billing ? data.next_billing : 'N/A');
        $('[data-next-reset]').text(data.next_reset ? data.next_reset : 'N/A');
        $('[data-page-views]').text(data.page_views ? data.page_views : 'N/A');
        $('[data-cdn-bandwidth]').text(data.cdn_bandwidth ? data.cdn_bandwidth + ' out of ' + data.max_cdn_bandwidth : 'N/A');

        for (prop in data) {
          if (prop.indexOf("show_") === 0) continue;
          if (prop.indexOf("label_") === 0) continue;
          if (prop.indexOf("max_") === 0) continue;
          if (
            typeof data["show_" + prop] != "undefined" &&
            data["show_" + prop] &&
            typeof data["label_" + prop] != "undefined" &&
            typeof data["max_" + prop] != "undefined"
          ) {
            let propertyLabel = data["label_" + prop];
            let propertyValue = data[prop];
            let propertyLimit = data["max_" + prop];
            $("#plan-quotas").append('<li class="list-group-item px-0 d-flex justify-content-between align-items-center">' + propertyLabel + ' <span><span data-optimizations>' + propertyValue + '</span> out of <span data-max-optimizations>' + propertyLimit + '</span></span></li>');
          }
        }

      }, __ => {
        NitropackUI.triggerToast('error', '<?php esc_html_e('Error while fetching plan data', 'nitropack'); ?>');
      }, __ => {});
    }


    $(document).on('click', "#compression-test-btn", e => {
      e.preventDefault();
      autoDetectCompression();
    });
    /* Compression end */

    /* HTML Compression begin */
    var autoDetectCompression = function() {
      let msg_container = $('#compression-widget .msg-container'),
        msg_icon = msg_container.find('.icon'),
        msg_box = msg_container.find('.msg'),
        compression_setting = $('#compression-status'),
        compression_btn = $('#compression-test-btn');
      //add spinner here
      msg_box.html('<img src="<?php echo plugin_dir_url(__FILE__) . 'images/loading.svg'; ?>" alt="loading" class="icon"> <?php esc_html_e('Testing current compression status', 'nitropack'); ?>');
      compression_btn.addClass('hidden');
      msg_container.removeClass('hidden');
      $.post(ajaxurl, {
        action: 'nitropack_test_compression_ajax',
        nonce: nitroNonce
      }, function(response) {
        var resp = JSON.parse(response);

        if (resp.status == "success") {
          if (resp.hasCompression) { // compression already enabled
            compression_setting.attr("checked", false);

            msg_box.text('<?php esc_html_e('Compression is already enabled on your server! There is no need to enable it in NitroPack.', 'nitropack'); ?>')
          } else {
            compression_setting.attr("checked", true);
            msg_box.text('<?php esc_html_e('No compression was detected! We will now enable it in NitroPack.', 'nitropack'); ?>');
          }
          NitropackUI.triggerToast(resp.type, resp.message);
        } else {
          msg_box.text('<?php esc_html_e('Could not determine compression status automatically. Please configure it manually.', 'nitropack'); ?>');
        }
        setTimeout(function() {
          msg_container.addClass('hidden');
          compression_btn.removeClass('hidden');
        }, 5000);
      });
    }


    $("#compression-status").on("click", function(e) {
      $.post(ajaxurl, {
        action: 'nitropack_set_compression_ajax',
        nonce: nitroNonce,
        data: {
          compressionStatus: $(this).is(":checked") ? 1 : 0
        }
      }, function(response) {
        var resp = JSON.parse(response);
        NitropackUI.triggerToast(resp.type, resp.message);
      });
    });

    $("#auto-purge-status").on("click", function(e) {
      $.post(ajaxurl, {
        action: 'nitropack_set_auto_cache_purge_ajax',
        nonce: nitroNonce,
        autoCachePurgeStatus: $(this).is(":checked") ? 1 : 0
      }, function(response) {
        var resp = JSON.parse(response);
        NitropackUI.triggerToast(resp.type, resp.message);
      });
    });

    $("#cart-cache-status").on("click", function(e) {
      $.post(ajaxurl, {
        action: 'nitropack_set_cart_cache_ajax',
        nonce: nitroNonce,
        cartCacheStatus: $(this).is(":checked") ? 1 : 0
      }, function(response) {
        var resp = JSON.parse(response);
        NitropackUI.triggerToast(resp.type, resp.message);
      });
    });


    $("#woo-stock-reduce-status").on("click", function(e) {
      $.post(ajaxurl, {
        action: 'nitropack_set_stock_reduce_status',
        nonce: nitroNonce,
        data: {
          stockReduceStatus: $(this).is(":checked") ? 1 : 0
        }
      }, function(response) {
        var resp = JSON.parse(response);
        NitropackUI.triggerToast(resp.type, resp.message);
      });
    });

    $("#legacy-purge-status").on("click", function(e) {
      $.post(ajaxurl, {
        action: 'nitropack_set_legacy_purge_ajax',
        nonce: nitroNonce,
        legacyPurgeStatus: $(this).is(":checked") ? 1 : 0
      }, function(response) {
        var resp = JSON.parse(response);
        NitropackUI.triggerToast(resp.type, resp.message);

      });
    });

    $("#bb-purge-status").on("click", function(e) {
      $.post(ajaxurl, {
        action: 'nitropack_set_bb_cache_purge_sync_ajax',
        nonce: nitroNonce,
        bbCachePurgeSyncStatus: $(this).is(":checked") ? 1 : 0
      }, function(response) {
        var resp = JSON.parse(response);
        NitropackUI.triggerToast(resp.type, resp.message);
      });
    });


  })(jQuery);
</script>