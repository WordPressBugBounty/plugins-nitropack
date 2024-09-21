<div id="modal-test-mode" data-modal-backdrop="static" tabindex="-1" aria-hidden="true" class="hidden modal-wrapper popup-modal">
    <!-- Modal container -->
    <div class="modal-container">
        <!-- Modal inner -->
        <div class="modal-inner">
            <div class="popup-header">
                <button type="button" class="close-modal">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.293031 1.29308C0.480558 1.10561 0.734866 1.00029 1.00003 1.00029C1.26519 1.00029 1.5195 1.10561 1.70703 1.29308L6.00003 5.58608L10.293 1.29308C10.3853 1.19757 10.4956 1.12139 10.6176 1.06898C10.7396 1.01657 10.8709 0.988985 11.0036 0.987831C11.1364 0.986677 11.2681 1.01198 11.391 1.06226C11.5139 1.11254 11.6255 1.18679 11.7194 1.28069C11.8133 1.37458 11.8876 1.48623 11.9379 1.60913C11.9881 1.73202 12.0134 1.8637 12.0123 1.99648C12.0111 2.12926 11.9835 2.26048 11.9311 2.38249C11.8787 2.50449 11.8025 2.61483 11.707 2.70708L7.41403 7.00008L11.707 11.2931C11.8892 11.4817 11.99 11.7343 11.9877 11.9965C11.9854 12.2587 11.8803 12.5095 11.6948 12.6949C11.5094 12.8803 11.2586 12.9855 10.9964 12.9878C10.7342 12.99 10.4816 12.8892 10.293 12.7071L6.00003 8.41408L1.70703 12.7071C1.51843 12.8892 1.26583 12.99 1.00363 12.9878C0.741432 12.9855 0.49062 12.8803 0.305212 12.6949C0.119804 12.5095 0.0146347 12.2587 0.0123563 11.9965C0.0100779 11.7343 0.110873 11.4817 0.293031 11.2931L4.58603 7.00008L0.293031 2.70708C0.10556 2.51955 0.000244141 2.26525 0.000244141 2.00008C0.000244141 1.73492 0.10556 1.48061 0.293031 1.29308Z" fill="#1B004E" />
                    </svg>
                    <span class="sr-only"><?php esc_html_e('Close modal', 'nitropack'); ?></span>
                </button>
                <img src="<?php echo plugin_dir_url(__FILE__) . '../images/info.svg'; ?>" width="46" height="46" class="icon rotate-180">
                <h3><?php esc_html_e('Enable Test Mode?', 'nitropack'); ?></h3>
            </div>
            <!-- Modal body -->
            <div class="popup-body"></div>
            <div class="popup-footer" style="margin-bottom: 0;">
                <button type="button" class="btn btn-secondary modal-close"><?php esc_html_e('Cancel', 'nitropack'); ?></button>
                <button type="button" class="btn btn-primary modal-action"><?php esc_html_e('Enable', 'nitropack'); ?></button>
            </div>
        </div>
    </div>
</div>
<script>
    jQuery(document).ready(function($) {
        const $targetEl = document.getElementById('modal-test-mode'),
            modal_options = {
                backdrop: 'static',
                backdropClasses: 'nitro-backdrop',
                closable: false,
            },
            instanceOptions = {
                id: 'modal-test-mode',
                override: true
            },
            modal = new Modal($targetEl, modal_options, instanceOptions),
            modal_wrapper = $('#modal-test-mode'),
            modal_icon = modal_wrapper.find('.icon'),
            modal_text = modal_wrapper.find('.popup-body'),
            modal_title = modal_wrapper.find('.popup-header h3'),
            x_button = modal_wrapper.find('.close-modal'),
            modal_footer = modal_wrapper.find('.popup-footer'),
            close_btn = modal_footer.find('.modal-close'),
            action_btn = modal_footer.find('.modal-action'),
            setting_id = '#safemode-status';


        $(setting_id).change(function() {
            close_btn.off("click");
            action_btn.off("click");
            modal_icon.attr('src', '<?php echo plugin_dir_url(__FILE__) . '../images/info.svg'; ?>');
            modal_footer.removeClass('hidden');
            if (this.checked) {
                modal_title.text('<?php esc_html_e('Enable Test Mode?', 'nitropack'); ?>');
                modal_text.html('<p><?php esc_html_e('When you enable Test Mode, we disable all NitroPack’s optimizations and your site visitors are accessing your regular, unoptimized URLs.', 'nitropack'); ?></p>');
                modal_text.append("<p class='msg-container'><?php _e('To view how a NitroPack optimised page will load and behave simply append <b>?testnitro=1</b> to any URL (e.g. https://yourwebsite.com/?testnitro=1)', 'nitropack'); ?></p>");
                modal_text.append("<p><?php esc_html_e('This allows you to assess and fine-tune NitroPack’s performance before implementing optimizations site-wide.', 'nitropack'); ?></p>");
                close_btn.text('<?php esc_html_e('Cancel', 'nitropack'); ?>');
                action_btn.text('<?php esc_html_e('Enable', 'nitropack'); ?>');
                close_btn.click(function() {
                    $(setting_id).prop('checked', false);
                    modal.hide();
                });
                x_button.click(function() {
                    $(setting_id).prop('checked', false);
                });

                action_btn.click(function() {
                    enableSafemode();
                });

            } else {

                modal_title.text('<?php esc_html_e('Purge cache after disabling Test Mode?', 'nitropack'); ?>');
                modal_text.html('<p><?php esc_html_e('If you have made changes to NitroPack configuration or your website while you were using test mode, we recommend you to purge your cache. In this way we will update NitroPack cache with your recent changes.', 'nitropack'); ?></p>');
                close_btn.text('<?php esc_html_e('I will do it later', 'nitropack'); ?>');
                action_btn.text('<?php esc_html_e('Purge cache now', 'nitropack'); ?>');

                action_btn.click(function() {
                    let purgeEvent = new Event("cache.purge.request");
                    window.dispatchEvent(purgeEvent);
                    modal.hide();
                });
                disableSafemode();

                $(setting_id).prop('checked', false);
            }
            close_btn.click(function() {
                modal.hide();
            });
            modal.show();
        });
        close_btn.click(function() {
            modal.hide();
        });
        x_button.click(function() {
            modal.hide();
        });
        var enableSafemode = () => {
            modal_icon.attr('src', '<?php echo plugin_dir_url(__FILE__) . '../images/loading.svg'; ?>');
            $.post(ajaxurl, {
                action: 'nitropack_enable_safemode',
                nonce: nitroNonce
            }, function(response) {
                var resp = JSON.parse(response);
                modal_footer.addClass('hidden');
                if (resp.type == "success") {
                    $(setting_id).prop("checked", true);
                    $("#nitropack-smenabled-notice").closest('.nitro-notification').show();
                    modal_title.text(resp.message);
                    modal_text.text('');
                } else {
                    modal_title.text('<?php esc_html_e('Error!', 'nitropack'); ?>');
                    modal_text.text('');
                    $(setting_id).prop('checked', false);

                }
                NitropackUI.triggerToast(resp.type, resp.message);
                modal.hide();
            });
        }

        var disableSafemode = () => {
            $.post(ajaxurl, {
                action: 'nitropack_disable_safemode',
                nonce: nitroNonce
            }, function(response) {
                var resp = JSON.parse(response);
                if (resp.type == "success") $("#nitropack-smenabled-notice").closest('.nitro-notification').hide();

                NitropackUI.triggerToast(resp.type, resp.message);
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
                        $("#safemode-status").attr("checked", !!resp.isEnabled);
                        $("#nitropack-smenabled-notice").length && !!resp.isEnabled ? $("#nitropack-smenabled-notice").parent().show() : $("#nitropack-smenabled-notice").parent().hide();
                        if (resp.isEnabled && !$("#nitropack-smenabled-notice").length) {
                            $('#main > .container').prepend('<div class="nitro-notification notification-warning"><div class="notification-inner">' +
                                +'<div class="title-msg"><div class="title-wrapper">' +
                                '<img src="/view/images/info.svg" alt="alert" class="icon">' +
                                '<h5 class="title">Warning</h5>' +
                                '</div><div class="msg"><div id="nitropack-smenabled-notice">Test Mode is enabled for your site and visitors are accessing your unoptimized pages. Make sure to disable it once you are done testing.</div></div>' +
                                '</div></div>' +
                                '</div>')
                        }
                        $("#loading-safemode-status").hide();
                    } else {
                        setTimeout(loadSafemodeStatus, 500);
                    }
                }
            });
        }

        loadSafemodeStatus();
    });
</script>