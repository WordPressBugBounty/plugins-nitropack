<script>
  let nitroNonce = '<?php echo wp_create_nonce(NITROPACK_NONCE); ?>';
</script>
<div id="nitropack-container">
  <nav class="nitro-navigation">
    <div class="nitro-navigation-inner">
      <img src="<?php echo plugin_dir_url(__FILE__) . 'images/nitropack_logo.svg'; ?>" height="25" alt="NitroPack" />
    </div>
  </nav>

  <main id="main">

    <div class="container">
      <?php if (count(get_nitropack()->Notifications->get('system')) > 0) { ?>
        <ul class="notifications-list" id="app-notifications">
          <?php foreach (get_nitropack()->Notifications->get('system') as $notification) : ?>
            <li class="nitro-notification notification-info app-type-<?php echo $notification['type']; ?>">
              <div class="notification-inner">
                <div class="title-msg">
                  <div class="title-wrapper">
                    <img src="<?php echo plugin_dir_url(__FILE__) . 'images/bell.svg'; ?>" alt="alert" class="icon">
                    <h5 class="title">Info</h5>
                  </div>
                  <div class="msg">
                    <?php echo $notification['message']; ?>
                  </div>
                </div>
                <div class="col-span-4 ml-auto actions">
                  <a class="btn btn-secondary btn-dismiss rml_btn" data-notification_end="<?php echo $notification['end_date']; ?>" data-notification_id="<?php echo $notification['id']; ?>"><?php _e('Dismiss', 'nitropack'); ?></a>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php } ?>
      <?php if (!isset($_GET['subpage'])) : ?>
        <?php require_once NITROPACK_PLUGIN_DIR . "view/dashboard.php";
        ?>
      <?php endif; ?>


      <?php if (isset($_GET['subpage']) && $_GET['subpage'] == 'system-report') : ?>
        <?php require_once NITROPACK_PLUGIN_DIR . "view/system-report.php";
        ?>
      <?php endif; ?>
    </div>
  </main>
  <?php require_once NITROPACK_PLUGIN_DIR . 'view/templates/template-toast.php'; ?>
</div>

<?php if (NITROPACK_SUPPORT_BUBBLE_VISIBLE) { ?>
  <div class="support-widget">
    <!-- support widget -->
    <script>
      window.intercomSettings = {
        api_base: "https://api-iam.intercom.io",
        app_id: "d5v9p9vg"
      };

      (function() {
        var w = window;
        var ic = w.Intercom;
        if (typeof ic === "function") {
          ic('reattach_activator');
          ic('update', w.intercomSettings);
        } else {
          var d = document;
          var i = function() {
            i.c(arguments);
          };
          i.q = [];
          i.c = function(args) {
            i.q.push(args);
          };
          w.Intercom = i;
          var l = function() {
            var s = d.createElement('script');
            s.type = 'text/javascript';
            s.async = true;
            s.src = 'https://widget.intercom.io/widget/d5v9p9vg';
            var x = d.getElementsByTagName('script')[0];
            x.parentNode.insertBefore(s, x);
          };
          if (document.readyState === 'complete') {
            l();
          } else if (w.attachEvent) {
            w.attachEvent('onload', l);
          } else {
            w.addEventListener('load', l, false);
          }
        }
      })();
    </script>
    <!-- end support widget -->
  </div>
<?php } ?>
<script>
  (function($) {
    window.Notification = (_ => {
      var timeout;

      var display = (msg, type) => {
        clearTimeout(timeout);
        $('#nitropack-notification').remove();

        $('[name="form"]').prepend('<div id="nitropack-notification" class="notice notice-' + type + '" is-dismissible"><p>' + msg + '</p></div>');

        timeout = setTimeout(_ => {
          $('#nitropack-notification').remove();
        }, 10000);
        loadDismissibleNotices();
      }

      return {
        success: msg => {
          display(msg, 'success');
        },
        error: msg => {
          display(msg, 'error');
        },
        info: msg => {
          display(msg, 'info');
        },
        warning: msg => {
          display(msg, 'warning');
        }
      }
    })();

    const clearCacheHandler = clearCacheAction => {
      return function(success, error) {
        $.ajax({
          url: ajaxurl,
          type: 'GET',
          data: {
            action: "nitropack_" + clearCacheAction + "_cache",
            nonce: nitroNonce
          },
          dataType: 'json',
          beforeSend: function() {
            $('#optimizations-purge-cache').attr('disabled', true);
          },
          success: function(data) {
            if (data.type === 'success') {
              NitropackUI.triggerToast('success', data.message);
              cacheEvent = new Event("cache." + clearCacheAction + ".success");
            } else {
              NitropackUI.triggerToast('error', data.message);
              cacheEvent = new Event("cache." + clearCacheAction + ".error");
            }
            window.dispatchEvent(cacheEvent);
          },
          error: function(data) {

            NitropackUI.triggerToast('error', data.message);
            cacheEvent = new Event("cache." + clearCacheAction + ".error");
            window.dispatchEvent(cacheEvent);
          },
          complete: function() {
            setTimeout(function() {
              $('#optimizations-purge-cache').attr('disabled', false);
            }, 3000);
          }
        });
      };
    }

    window.addEventListener("cache.purge.success", function() {
      setTimeout(function() {
        document.cookie = "nitropack_apwarning=1; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=<?php echo nitropack_cookiepath(); ?>";
        window.location.reload()
      }, 1500)
    });

    $(window).on("load", _ => {
      //Remove styles from jobcareer and jobhunt plugins since they break our layout. They should not be loaded on our options page anyway.
      $('link[href*="jobcareer"').remove();
      $('link[href*="jobhunt"').remove();

      $("#dashboard").addClass("show active");
      window.addEventListener('cache.invalidate.request', clearCacheHandler("invalidate"));
      window.addEventListener('cache.purge.request', clearCacheHandler("purge"));

    });
    const loading_icon = '<img src="<?php echo plugin_dir_url(__FILE__); ?>/images/loading.svg" width="14" class="icon loading"/>',
      success_icon = '<img src="<?php echo plugin_dir_url(__FILE__); ?>/images/check.svg" width="16" class="icon success"/>';

    $("#nitro-restore-connection-btn").on("click", function() {
      $.ajax({
        url: ajaxurl,
        type: 'GET',
        data: {
          action: "nitropack_reconfigure_webhooks",
          nonce: nitroNonce
        },
        dataType: 'json',
        beforeSend: function() {
          $("#nitro-restore-connection-btn").attr("disabled", true).html(loading_icon);
        },
        success: function(data) {
          if (!data.status || data.status != "success") {
            if (data.message) {
              alert("<?php esc_html_e('Error:', 'nitropack'); ?> " + data.message);
            } else {
              alert("<?php esc_html_e('Error: We were unable to restore the connection. Please contact our support team to get this resolved.', 'nitropack'); ?>");
            }
          } else {
            $("#nitro-restore-connection-btn").attr("disabled", true).html(success_icon);
            NitropackUI.triggerToast('success', data.message);
          }
        },
        complete: function() {
          location.reload();
        }
      });
    });
  })(jQuery);
</script>