jQuery(document).ready(function ($) {
  class nitropackSettings {
    constructor() {
      this.initial_settings = {
        ajaxShortcodes: {
          enabled: 0,
          shortcodes: [],
        },
        cacheWarmUp: {
          enabled: 0,
        },
      };
      //run settings
      this.cacheWarmUp();
      this.ajaxShortcodes = this.ajaxShortcodes();
      this.restoreConnection();
      this.windowNotification();
      this.nitropackAddEventListeners();
      this.clearResidualCache();
      //logger
      this.loggerToggle();
      this.setLoggerLevel();
      this.archiveLogs();
      //unsaved changes
      this.onPageLeave();
      //must be last so we get updated copy
      this.unsavedChangesModal = false;
      this.modified_settings = JSON.parse(
        JSON.stringify(this.initial_settings)
      );
    }
    cacheWarmUp() {
      const setting_id = "#warmup-status",
        msg_wrapper = $("#loading-warmup-status"),
        msg_icon = msg_wrapper.find(".icon"),
        msg_text = msg_wrapper.find(".msg"),
        nitroSelf = this;

      $(setting_id).change(function () {
        if ($(this).is(":checked")) {
          estimateWarmup();
        } else {
          disableWarmup();
        }
      });
      var disableWarmup = () => {
        $.post(
          ajaxurl,
          {
            action: "nitropack_disable_warmup",
            nonce: np_settings.nitroNonce,
          },
          function (response) {
            var resp = JSON.parse(response);
            if (resp.type == "success") {
              nitroSelf.modified_settings.cacheWarmUp.enabled = 0;
              NitropackUI.triggerToast("success", np_settings.success_msg);
            } else {
              NitropackUI.triggerToast("error", np_settings.error_msg);
            }
          }
        );
      };

      var estimateWarmup = (id, retry) => {
        id = id || null;
        retry = retry || 0;
        msg_wrapper.removeClass("hidden");
        if (!id) {
          msg_text.text(np_settings.est_cachewarmup_msg);
          $.post(
            ajaxurl,
            {
              action: "nitropack_estimate_warmup",
              nonce: np_settings.nitroNonce,
            },
            function (response) {
              var resp = JSON.parse(response);
              if (resp.type == "success") {
                setTimeout(
                  (function (id) {
                    estimateWarmup(id);
                  })(resp.res),
                  1000
                );
              } else {
                $(setting_id).prop("checked", true);
                msg_text.text(resp.message);

                msg_icon.attr(
                  "src",
                  np_settings.nitro_plugin_url + "/view/images/info.svg"
                );
                setTimeout(function () {
                  msg_wrapper.addClass("hidden");
                }, 3000);
              }
            }
          );
        } else {
          $.post(
            ajaxurl,
            {
              action: "nitropack_estimate_warmup",
              estId: id,
              nonce: np_settings.nitroNonce,
            },
            function (response) {
              var resp = JSON.parse(response);
              if (resp.type == "success") {
                if (isNaN(resp.res) || resp.res == -1) {
                  // Still calculating
                  if (retry >= 10) {
                    $(setting_id).prop("checked", false);
                    msg_icon.attr(
                      "src",
                      np_settings.nitro_plugin_url + "/view/images/info.svg"
                    );
                    msg_text.text(resp.message);

                    setTimeout(function () {
                      msg_wrapper.addClass("hidden");
                    }, 3000);
                  } else {
                    setTimeout(
                      (function (id, retry) {
                        estimateWarmup(id, retry);
                      })(id, retry + 1),
                      1000
                    );
                  }
                } else {
                  if (resp.res == 0) {
                    $(setting_id).prop("checked", false);
                    msg_icon.attr(
                      "src",
                      np_settings.nitro_plugin_url + "/view/images/info.svg"
                    );
                    msg_text.text(resp.message);
                    setTimeout(function () {
                      msg_wrapper.addClass("hidden");
                    }, 3000);
                  } else {
                    enableWarmup();
                  }
                }
              } else {
                msg_text.text(resp.message);
                setTimeout(function () {
                  msg_wrapper.addClass("hidden");
                }, 3000);
              }
            }
          );
        }
      };
      var enableWarmup = () => {
        $.post(
          ajaxurl,
          {
            action: "nitropack_enable_warmup",
            nonce: np_settings.nitroNonce,
          },
          function (response) {
            var resp = JSON.parse(response);
            if (resp.type == "success") {
              nitroSelf.modified_settings.cacheWarmUp.enabled = 1;
              $(setting_id).prop("checked", true);
              msg_wrapper.addClass("hidden");
              NitropackUI.triggerToast("success", np_settings.success_msg);
            } else {
              setTimeout(enableWarmup, 1000);
            }
          }
        );
      };

      var loadWarmupStatus = function () {
        $.ajax({
          url: ajaxurl,
          type: "POST",
          data: {
            action: "nitropack_warmup_stats",
            nonce: np_settings.nitroNonce,
          },
          dataType: "json",
          success: function (resp) {
            if (resp.type == "success") {
              nitroSelf.initial_settings.cacheWarmUp.enabled = 1;
              nitroSelf.modified_settings.cacheWarmUp.enabled = 1;
              $(setting_id).prop("checked", !!resp.stats.status);
              msg_wrapper.addClass("hidden");
            } else {
              setTimeout(loadWarmupStatus, 500);
            }
          },
        });
      };

      if ($(setting_id).length) loadWarmupStatus();
    }
    ajaxShortcodes() {
      //main setting
      const setting_id = "#ajax-shortcodes",
        nitroSelf = this;
      if ($(setting_id).is(":checked")) {
        nitroSelf.initial_settings.ajaxShortcodes.enabled = 1;
      }

      $(setting_id).change(function () {
        if ($(this).is(":checked")) {
          ajaxShortcodeRequest(null, 1);
        } else {
          ajaxShortcodeRequest(null, 0);
        }
      });
      //template for selected shortcodes tags
      let select2 = $("#ajax-shortcodes-dropdown").select2({
          selectOnClose: false,
          tags: true,
          multiple: true,
          width: "100%",
          placeholder: "Enter a shortcode",
          templateSelection: shortcodeTagTemplate,
        }),
        shortcodes_val = select2.val();
      if (shortcodes_val.length > 0) {
        nitroSelf.initial_settings.ajaxShortcodes.shortcodes = select2.val();
      } else {
        nitroSelf.initial_settings.ajaxShortcodes.shortcodes = [];
      }

      select2.on("change", (event) => {
        const selectedValues = $(event.target).val(); // Get selected values
        this.modified_settings.ajaxShortcodes.shortcodes = selectedValues;
        if (selectedValues.length === 0) {
          $(
            ".select2-search.select2-search--inline .select2-search__field"
          ).addClass("w-full");
        } else {
          $(
            ".select2-search.select2-search--inline .select2-search__field"
          ).removeClass("w-full");
        }
      });
      $(
        ".select2-search.select2-search--inline .select2-search__field"
      ).addClass("w-full");
      //select2
      function shortcodeTagTemplate(item) {
        if (!item.id) {
          return item.text;
        }
        var $item = $(
          '<span class="select2-selection__choice-inner">' +
            item.text +
            '<span class="np-select2-remove"></span>' +
            "</span>"
        );
        return $item;
      }
      //remove single shortcode
      $(".ajax-shortcodes").on("click", ".np-select2-remove", function () {
        let valueToRemove = $(this)
            .closest("li.select2-selection__choice")
            .attr("title"),
          newVals = select2.val().filter(function (item) {
            return item !== valueToRemove;
          });
        select2.val(newVals).trigger("change");
      });
      //btn save click
      $(".ajax-shortcodes #save-shortcodes").click(function () {
        let shortcodes = $("#ajax-shortcodes-dropdown").val();
        ajaxShortcodeRequest(shortcodes, null);
      });

      /* shortcodes - array of shortcodes or null
            enabled - 1 or 0
            */
      const ajaxShortcodeRequest = function (shortcodes, enabled) {
        let data_obj = {
          action: "nitropack_set_ajax_shortcodes_ajax",
          nonce: np_settings.nitroNonce,
          shortcodes:
            Array.isArray(shortcodes) && shortcodes.length
              ? shortcodes
              : [JSON.stringify([])], // Ensure it's always an array
        };

        if (enabled !== null) data_obj.enabled = enabled;

        const response = $.ajax({
          url: ajaxurl,
          type: "POST",
          data: data_obj,
          dataType: "json",
          success: function (resp) {
            if (resp.type == "success") {
              if (enabled == 1) {
                $(".ajax-shortcodes").removeClass("hidden");
                nitroSelf.modified_settings.ajaxShortcodes.enabled = 1;
              }
              if (enabled == 0) {
                $(".ajax-shortcodes").addClass("hidden");
                nitroSelf.modified_settings.ajaxShortcodes.enabled = 0;
              }
              // Ensure we're setting an array in settings
              if (Array.isArray(shortcodes) && shortcodes.length) {
                nitroSelf.initial_settings.ajaxShortcodes.shortcodes =
                  shortcodes;
              } else {
                nitroSelf.initial_settings.ajaxShortcodes.shortcodes = [];
              }
              NitropackUI.triggerToast("success", np_settings.success_msg);
            } else {
              NitropackUI.triggerToast("error", np_settings.error_msg);
            }
          },
        });
        return response;
      };
      return {
        ajaxShortcodeRequest: ajaxShortcodeRequest,
      };
    }
    // Function to omit 'enabled' property
    omitEnabledProperty(obj) {
      return Object.keys(obj).reduce((acc, key) => {
        if (typeof obj[key] === "object" && obj[key] !== null) {
          acc[key] = this.omitEnabledProperty(obj[key]);
        } else if (key !== "enabled") {
          acc[key] = obj[key];
        }
        return acc;
      }, {});
    }

    // Function to check for unsaved changes, ignoring 'enabled' property
    hasUnsavedChanges() {
      const initialWithoutEnabled = this.omitEnabledProperty(
        this.initial_settings
      );
      const modifiedWithoutEnabled = this.omitEnabledProperty(
        this.modified_settings
      );
      return (
        JSON.stringify(initialWithoutEnabled) !==
        JSON.stringify(modifiedWithoutEnabled)
      );
    }

    // Function to handle page leave
    onPageLeave() {
      const nitroSelf = this;
      window.onbeforeunload = function (event) {
        if (
          nitroSelf.hasUnsavedChanges() &&
          !nitroSelf.unsavedChangesModal &&
          nitroSelf.modified_settings.ajaxShortcodes.enabled === 1
        ) {
          event.preventDefault(); // show prompt
        }
      };
      //a links - display modal
      $(document).on(
        "click",
        'a[href]:not([target="_blank"])',
        function (event) {
          if (
            nitroSelf.hasUnsavedChanges() &&
            nitroSelf.modified_settings.ajaxShortcodes.enabled === 1
          ) {
            event.preventDefault();
            const leaveUrl = this.href;
            nitroSelf.showUnsavedChangesModal(() => {
              window.location.href = leaveUrl;
            });
          }
        }
      );
    }
    // Show unsaved changes modal
    showUnsavedChangesModal(onConfirm) {
      const nitroSelf = this;
      //vanilla js
      const modalID = "modal-unsavedChanges",
        $modal_target = document.getElementById(modalID),
        modal_options = {
          backdrop: "static",
          backdropClasses: "nitro-backdrop",
          closable: true,
          onHide: () => {
            this.unsavedChangesModal = false;
          },
          onShow: () => {
            this.unsavedChangesModal = true;
          },
        },
        instanceOptions = {
          id: modalID,
        },
        modal = new Modal($modal_target, modal_options, instanceOptions);
      //jquery
      const modal_wrapper = $("#" + modalID),
        x_button = modal_wrapper.find(".close-modal"),
        modal_footer = modal_wrapper.find(".popup-footer"),
        secondary_btn = modal_footer.find(".popup-close"),
        action_btn = modal_footer.find(".btn-primary");
      modal.show();

      //no action
      $(x_button).one("click", function () {
        modal.hide();
      });
      //redirect without saving
      $(secondary_btn).one("click", function () {
        onConfirm();
        modal.hide();
      });
      //save and redirect
      $(action_btn).one("click", function () {
        const ajaxRequest = nitroSelf.ajaxShortcodes.ajaxShortcodeRequest(
          nitroSelf.modified_settings.ajaxShortcodes.shortcodes,
          null
        );
        ajaxRequest.done(function (response) {
          if (response.type === "success") onConfirm();
        });
        ajaxRequest.fail(function () {
          console.error("AJAX request failed.");
          NitropackUI.triggerToast("error", "Error saving shortcodes.");
          onConfirm();
        });
        modal.hide();
      });
    }
    removeElement(array, value) {
      const index = array.indexOf(value);
      if (index !== -1) {
        array.splice(index, 1);
      }
    }
    loggerToggle() {
      const radio = $("#minimum-log-level-status"),
        widget = $("#minimum-log-level-widget"),
        fancy_radios = widget.find(".fancy-radio"),
        fancy_radios_container = widget.find(".fancy-radio-container");
      let minimum_log_level;

      radio.on("change", function () {
        const self = $(this);
        if (self.is(":checked")) {
          minimum_log_level = 3;
        } else {
          minimum_log_level = null;
        }
        $.post(
          ajaxurl,
          {
            action: "nitropack_set_log_level_ajax",
            minimum_log_level: minimum_log_level,
            nonce: np_settings.nitroNonce,
          },
          function (response) {
            var resp = JSON.parse(response);
            if (resp.type == "success") {
              if (self.is(":checked")) {
                $(".logging").removeClass("hidden");
                fancy_radios_container.removeClass("selected");
                fancy_radios.removeClass("selected");
                widget
                  .find(
                    '.fancy-radio-container[data-value="' +
                      minimum_log_level +
                      '"'
                  )
                  .addClass("selected");
                widget
                  .find(
                    '.fancy-radio-container[data-value="' +
                      minimum_log_level +
                      '"'
                  )
                  .find(".fancy-radio")
                  .addClass("selected");
              } else {
                fancy_radios.removeClass("selected");
                $(".logging").addClass("hidden");
              }

              NitropackUI.triggerToast("success", np_settings.success_msg);
            } else {
              NitropackUI.triggerToast("error", np_settings.success_msg);
              $(this).prop("checked", false);
            }
          }
        );
      });
    }
    /* Set logger level */
    setLoggerLevel() {
      const widget = $("#minimum-log-level-widget"),
        fancy_radios_container = widget.find(".fancy-radio-container"),
        fancy_radios = widget.find(".fancy-radio");
      let initial_minimum_log_level = widget
        .find(".fancy-radio-container.selected")
        .data("value");

      fancy_radios_container.click(function () {
        let fancy_radio_container = $(this),
          fancy_radio = $(this).find(".fancy-radio"),
          minimum_log_level = fancy_radio_container.data("value");
        if (minimum_log_level === initial_minimum_log_level) return;

        $.post(
          ajaxurl,
          {
            action: "nitropack_set_log_level_ajax",
            minimum_log_level: minimum_log_level,
            nonce: np_settings.nitroNonce,
          },
          function (response) {
            var resp = JSON.parse(response);
            if (resp.type == "success") {
              //container
              fancy_radios_container.removeClass("selected");
              fancy_radio_container.addClass("selected");
              //custom radios
              fancy_radios.removeClass("selected");
              fancy_radio.addClass("selected");
              initial_minimum_log_level = minimum_log_level;
              NitropackUI.triggerToast("success", np_settings.success_msg);
            } else {
              NitropackUI.triggerToast("error", np_settings.success_msg);
              $(this).prop("checked", false);
            }
          }
        );
      });
    }
    /* Zips all logs and downloads them */
    archiveLogs() {
      $(".archive-logs").click(function (e) {
        e.preventDefault();
        $.post(
          ajaxurl,
          {
            action: "nitropack_archive_logs_ajax",
            nonce: np_settings.nitroNonce,
          },
          function (response) {
            var resp = JSON.parse(response);
            if (resp.type == "success") {
              window.location.href = resp.url;
              NitropackUI.triggerToast("success", np_settings.success_msg);
            } else {
              NitropackUI.triggerToast("error", np_settings.success_msg);
            }
          }
        );
      });
    }
    restoreConnection() {
      const loading_icon =
          '<img src="' +
          np_settings.nitro_plugin_url +
          '/view/images/loading.svg" width="14" class="icon loading"/>',
        success_icon =
          '<img src="' +
          np_settings.nitro_plugin_url +
          '/view/images/check.svg" width="16" class="icon success"/>';

      $("#nitro-restore-connection-btn").on("click", function () {
        $.ajax({
          url: ajaxurl,
          type: "GET",
          data: {
            action: "nitropack_reconfigure_webhooks",
            nonce: nitroNonce,
          },
          dataType: "json",
          beforeSend: function () {
            $("#nitro-restore-connection-btn")
              .attr("disabled", true)
              .html(loading_icon);
          },
          success: function (data) {
            if (!data.status || data.status != "success") {
              if (data.message) {
                alert(
                  "<?php esc_html_e('Error:', 'nitropack'); ?> " + data.message
                );
              } else {
                alert(
                  "<?php esc_html_e('Error: We were unable to restore the connection. Please contact our support team to get this resolved.', 'nitropack'); ?>"
                );
              }
            } else {
              $("#nitro-restore-connection-btn")
                .attr("disabled", true)
                .html(success_icon);
              NitropackUI.triggerToast("success", data.message);
            }
          },
          complete: function () {
            location.reload();
          },
        });
      });
    }
    /* Was used in dashboard.php and oneclick.php */
    loadDismissibleNotices() {
      var $ = jQuery;

      $(".nitro-notification.is-dismissible").each(function () {
        var b = $(this),
          c = $(
            '<button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button>'
          );
        c.on("click.wp-dismiss-notice", function ($) {
          $.preventDefault(),
            b.fadeTo(100, 0, function () {
              b.slideUp(100, function () {
                b.remove();
              });
            });
        }),
          b.append(c);
      });
    }

    clearCacheHandler = (clearCacheAction) => {
      return function (success, error) {
        $.ajax({
          url: ajaxurl,
          type: "GET",
          data: {
            action: "nitropack_" + clearCacheAction + "_cache",
            nonce: nitroNonce,
          },
          dataType: "json",
          beforeSend: function () {
            $("#optimizations-purge-cache").attr("disabled", true);
          },
          success: function (data) {
            if (data.type === "success") {
              NitropackUI.triggerToast("success", data.message);
              window.dispatchEvent(
                new Event("cache." + clearCacheAction + ".success")
              );
            } else {
              NitropackUI.triggerToast("error", data.message);
              window.dispatchEvent(
                new Event("cache." + clearCacheAction + ".error")
              );
            }
          },
          error: function (data) {
            NitropackUI.triggerToast("error", data.message);
            window.dispatchEvent(
              new Event("cache." + clearCacheAction + ".error")
            );
          },
          complete: function () {
            setTimeout(function () {
              $("#optimizations-purge-cache").attr("disabled", false);
            }, 3000);
          },
        });
      };
    };
    windowNotification() {
      const nitroSelf = this;
      window.Notification = ((_) => {
        var timeout;
        var display = (msg, type) => {
          clearTimeout(timeout);
          $(".nitro-notification").remove();
          //tbd
          $('[name="form"]').prepend(
            '<div class="nitro-notification notification-' +
              type +
              '" is-dismissible"><p>' +
              msg +
              "</p></div>"
          );

          timeout = setTimeout((_) => {
            $(".nitro-notification").remove();
          }, 10000);

          nitroSelf.loadDismissibleNotices();
        };

        return {
          success: (msg) => {
            display(msg, "success");
          },
          error: (msg) => {
            display(msg, "error");
          },
          info: (msg) => {
            display(msg, "info");
          },
          warning: (msg) => {
            display(msg, "warning");
          },
        };
      })();
    }
    nitropackAddEventListeners() {
      const nitroSelf = this;
      $(window).on("load", (_) => {
        window.addEventListener(
          "cache.invalidate.request",
          nitroSelf.clearCacheHandler("invalidate")
        );
        window.addEventListener(
          "cache.purge.request",
          nitroSelf.clearCacheHandler("purge")
        );
        if ($("#np-onstate-cache-purge").length) {
          window.addEventListener("cache.purge.success", function () {
            setTimeout(function () {
              document.cookie =
                "nitropack_apwarning=1; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/";
              window.location.reload();
            }, 1500);
          });
        }
      });
    }
    clearResidualCache() {
      let isClearing = false;
      $(document).on("click", ".btn[nitropack-rc-data]", function (e) {
        e.preventDefault();
        if (isClearing) return;
        let currentButton = $(this);
        $.ajax({
          url: ajaxurl,
          type: "POST",
          dataType: "text",
          data: {
            action: "nitropack_clear_residual_cache",
            gde: currentButton.attr("nitropack-rc-data"),
            nonce: nitroNonce,
          },
          beforeSend: function () {
            isClearing = true;
          },
          success: function (resp) {
            NitropackUI.triggerToast("success", np_settings.success_msg);
          },
          error: function (resp) {
            NitropackUI.triggerToast("error", np_settings.success_msg);
          },
          complete: function () {
            isClearing = false;
            setTimeout(function () {
              location.reload();
            }, 3000);
          },
        });
      });
    }
  }
  const NitroPackSettings = new nitropackSettings();
});
