<?php $diagnostic_settings = array(
  array(
    'name' => esc_html__('Include NitroPack info (version, methods, environment)', 'nitropack'),
    'desc' => '',
    'id'   => 'general-info-status',
    'class' => 'diagnostic-option',
    'setting' => 'include_info'
  ),
  array(
    'name' => esc_html__('Include active plugins list', 'nitropack'),
    'desc' => '',
    'id'   => 'active-plugins-status',
    'class' => 'diagnostic-option',
    'setting' => 'active_plugins'
  ),
  array(
    'name' => esc_html__('Include conflicting plugins list', 'nitropack'),
    'desc' => '',
    'id'   => 'conflicting-plugins-status',
    'class' => 'diagnostic-option',
    'setting' => 'conflicting_plugins'
  ),
  array(
    'name' => esc_html__('Include plugin config', 'nitropack'),
    'desc' => '',
    'id'   => 'user-config-status',
    'class' => 'diagnostic-option',
    'setting' => 'user_conflict'
  ),
  array(
    'name' => esc_html__('Include directory status', 'nitropack'),
    'desc' => '',
    'id'   => 'dir-info-status',
    'class' => 'diagnostic-option',
    'setting' => 'dir_info_status'
  ),
);
?>

<div class="grid grid-cols-1 gap-6">
  <div class="col-span-1">
    <div class="card">
      <div class="flex">
        <div class="" style="flex-basis: 66%;">
          <h3><?php esc_html_e('System Info Report', 'nitropack'); ?></h3>
          <p><?php esc_html_e('The system info report provides detailed insights into your NitroPack setup, including website configuration. Sharing this report with our support team enables them to quickly diagnose and resolve any issues you might face.', 'nitropack'); ?></p>
        </div>
        <div class="ml-auto">
          <a id="gen-report-btn" href="javascript:void(0);" class="btn btn-secondary"><img src="<?php echo plugin_dir_url(__FILE__) . 'images/download.svg'; ?> " class="icon-left" /> <span class="btn-text"><?php esc_html_e('Download', 'nitropack'); ?></span></a>
        </div>
      </div>
      <div class="card-body">
        <div>
          <div id="accordion-collapse" data-accordion="collapse" class="mt-4" data-active-classes="active" data-inactive-classes="not-active">
            <div id="accordion-collapse-heading-1" class="text-center">
              <a class="btn btn-link" data-accordion-target="#accordion-collapse-body-1" aria-expanded="false" aria-controls="accordion-collapse-body-1">
                <span><?php esc_html_e('Customize Report', 'nitropack'); ?></span>
                <svg width="9" height="6" data-accordion-icon class="w-3 h-3 rotate-180 shrink-0 icon-right" aria-hidden="false" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 10 6">
                  <path d="M8.5 5L4.5 1L0.5 5" stroke="#4600CC" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </a>
            </div>
            <div id="accordion-collapse-body-1" class="accordion-body hidden" aria-labelledby="accordion-collapse-heading-1">
              <div class="options-container">
                <?php foreach ($diagnostic_settings as $setting) : ?>
                  <div class="nitro-option">
                    <div class="nitro-option-main">
                      <h6><?php echo $setting['name']; ?></h6>
                      <label class="inline-flex items-center cursor-pointer ml-auto">
                        <input type="checkbox" value="" id="<?php echo $setting['id']; ?>" class="sr-only peer <?php echo $setting['class']; ?>" name="<?php echo $setting['setting']; ?>" checked>
                        <div class="toggle"></div>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<script>
  ($ => {
    let isReportGenerating = false;

    $("#gen-report-btn").on("click", function(e) {
      if (isReportGenerating) return;

      $.ajax({
        url: ajaxurl,
        type: "POST",
        dataType: "text",
        data: {
          action: 'nitropack_generate_report',
          nonce: nitroNonce,
          toggled: {
            "general-info-status": $("#general-info-status:checked").length,
            "active-plugins-status": $("#active-plugins-status:checked").length,
            "conflicting-plugins-status": $("#conflicting-plugins-status:checked").length,
            "user-config-status": $("#user-config-status:checked").length,
            "dir-info-status": $("#dir-info-status:checked").length
          }
        },
        beforeSend: function(xhr, sett) {
          if ($(".diagnostic-option:checked").length > 0) {
            $("#diagnostics-loader").show();
            isReportGenerating = true;
            return true;
          } else {
            alert("<?php esc_html_e('Please select at least one of the report options', 'nitropack'); ?>");
            return false;
          }
        },
        success: function(response, status, xhr) {
          if (response.length > 1) {
            var filename = "";
            var disposition = xhr.getResponseHeader('Content-Disposition');
            if (disposition && disposition.indexOf('attachment') !== -1) {
              var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
              var matches = filenameRegex.exec(disposition);
              if (matches != null && matches[1]) filename = matches[1].replace(/['"]/g, '');
            }

            var type = xhr.getResponseHeader('Content-Type');
            var blob = new Blob([response], {
              type: type
            });

            if (typeof window.navigator.msSaveBlob !== 'undefined') {
              // IE workaround for "HTML7007: One or more blob URLs were revoked by closing the blob for which they were created. These URLs will no longer resolve as the data backing the URL has been freed."
              window.navigator.msSaveBlob(blob, filename);
            } else {
              var URL = window.URL || window.webkitURL;
              var downloadUrl = URL.createObjectURL(blob);

              if (filename) {
                // use HTML5 a[download] attribute to specify filename
                var a = document.createElement("a");
                // safari doesn't support this yet
                if (typeof a.download === 'undefined') {
                  window.location.href = downloadUrl;
                } else {
                  a.href = downloadUrl;
                  a.download = filename;
                  document.body.appendChild(a);
                  a.click();
                }
              } else {
                window.location.href = downloadUrl;
              }

              setTimeout(function() {
                URL.revokeObjectURL(downloadUrl);
              }, 100);
            }
            NitropackUI.triggerToast('success', "<?php esc_html_e('Report generated successfully.', 'nitropack'); ?>");
          } else {
            NitropackUI.triggerToast('error', "<?php esc_html_e('Response is empty. Report generation failed.', 'nitropack'); ?>");
          }
        },
        error: function() {
          NitropackUI.triggerToast('error', "<?php esc_html_e('There was an error while generating the report.', 'nitropack'); ?>");
        },
        complete: function() {
          $("#diagnostics-loader").hide();
          isReportGenerating = false;
        }
      });
    });
  })(jQuery);
</script>