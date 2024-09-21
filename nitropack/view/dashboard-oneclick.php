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
        <div class="flex flex-row items-center">
          <div class="optimized-pages"><span data-optimized-pages-total>0</span></div>
          <div class="text-box mr-2">
            <p class="text-md"><?php esc_html_e('Last cache purge', 'nitropack'); ?>: <span data-last-cache-purge><?php esc_html_e('Never', 'nitropack'); ?></span> </br>
              <?php esc_html_e('Reason', 'nitropack'); ?>: <span data-purge-reason><?php esc_html_e('Unknown', 'nitropack'); ?></span></p>
          </div>
          <button id="optimizations-purge-cache" type="button" class="ml-auto btn btn-secondary flex-shrink-0" data-modal-target="modal-purge-cache" data-modal-toggle="modal-purge-cache">
            <?php esc_html_e('Purge Cache', 'nitropack'); ?></button>
        </div>
      </div>
      <?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-purge-cache.php'; ?>
    </div>
    <!-- Optimized Pages Card End -->

  </div>
  <div class="col-span-1">
    <!-- WP Engine Content Card -->
    <div class="card card-vendor">
      <?php if (empty($oneClickVendorWidget)) { ?>
        <div class="card-header">
          <h3><?php esc_html_e('What is NitroPack OneClick?', 'nitropack'); ?></h3>
          <img src="<?php echo plugin_dir_url(__FILE__) . 'images/info.svg'; ?>">
        </div>
        <div class="card-body">
          <p><?php esc_html_e('NitroPack OneClick is technically a one-click version of NitroPack preconfigured with essential features for immediate use. Activate is effortlessly and enjoy an instant boost in page speed.', 'nitropack'); ?> <a href="https://wpengine.com/page-speed-boost" target="_blank" class="link mt-5"><?php esc_html_e('Learn More', 'nitropack'); ?></a></p>
          <a href="https://my.wpengine.com/products/page_speed_boost" target="_blank" class="btn btn-primary btn-manage mt-5"><?php esc_html_e('Manage', 'nitropack'); ?></a>
        </div>
      <?php } else { ?>
        <?php echo $oneClickVendorWidget; ?>
      <?php } ?>
    </div>
    <!-- WP Engine Content Card End -->
  </div>
</div>

<script>
  ($ => {
    var getOptimizationsTimeout = null;
    let isClearing = false;

    $(window).on("load", function() {
      getOptimizations();
    });
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

    var loadSafemodeStatus = function() {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "nitropack_safemode_status",
          nonce: nitroNonce
        },
        dataType: "json",
        success: function(resp) {
          if (resp.type == "success") {
            $("#nitropack-smenabled-notice").length && !!resp.isEnabled ? $("#nitropack-smenabled-notice").parent().show() : $("#nitropack-smenabled-notice").parent().hide();
          } else {
            setTimeout(loadSafemodeStatus, 500);
          }
        }
      });
    }
    loadSafemodeStatus();

    window.addEventListener("cache.invalidate.success", getOptimizations);
    if ($('#np-onstate-cache-purge').length) {
      window.addEventListener("cache.purge.success", function() {
        setTimeout(function() {
          document.cookie = "nitropack_apwarning=1; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=<?php echo nitropack_cookiepath(); ?>";
          window.location.reload()
        }, 1500)
      });
    } else {
      window.addEventListener("cache.purge.success", getOptimizations);
    }

    window.performCachePurge = () => {
      purgeCache();
    }
  })(jQuery);
</script>