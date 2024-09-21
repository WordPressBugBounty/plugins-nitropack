<script>
    let nitroNonce = '<?php echo wp_create_nonce(NITROPACK_NONCE); ?>';
</script>
<div id="nitropack-container">

    <main id="main">
        <div class="container">
            <h1 class="mb-4"><?php esc_html_e('NitroPack OneClick™', 'nitropack'); ?></h1>
            <?php if (count(get_nitropack()->Notifications->get('system')) > 0) { ?>
                <div class="notification notification-danger" id="notifications">
                    <div class="text-box">
                        <div class="title-wrapper">
                            <img src="<?php echo plugin_dir_url(__FILE__) . 'images/alert-triangle.svg'; ?>" alt="alert" class="icon" />
                            <h5 class="title"><?php esc_html_e('Notifications', 'nitropack'); ?></h5>
                        </div>
                        <ul>
                            <?php foreach (get_nitropack()->Notifications->get('system') as $notification) : ?>
                                <li class="grid grid-cols-2 justify-between items-center">
                                    <div class="col-span-8">
                                        <?php echo $notification['message']; ?>
                                    </div>
                                    <div class="col-span-4 ml-auto">
                                        <a class="btn btn-danger rml_btn" data-notification_end="<?php echo $notification['end_date']; ?>" data-notification_id="<?php echo $notification['id']; ?>">Remind me later</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>
            <?php if (!isset($_GET['subpage'])) : ?>
                <?php require_once NITROPACK_PLUGIN_DIR . "view/dashboard-oneclick.php";
                ?>
            <?php endif; ?>
        </div>
    </main>
    <?php require_once NITROPACK_PLUGIN_DIR . 'view/templates/template-toast.php'; ?>
</div>

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