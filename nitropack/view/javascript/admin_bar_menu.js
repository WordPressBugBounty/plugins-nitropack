jQuery(window).on("load", _ => {
    const loading_icon = '<img src="' + frontendajax.nitro_plugin_url + '/view/images/loading.svg" width="14" class="icon loading"/>';

    function clearCacheSingleHandler(clearCacheAction, elem) {
        jQuery.ajax({
            url: frontendajax.ajaxurl,
            type: 'POST',
            data: {
                action: "nitropack_" + clearCacheAction + "_single_cache",
                postUrl: window.location.href.split('#')[0],
                postId: -1,
                nonce: frontendajax.nitroNonce
            },
            dataType: 'json',
            beforeSend: function () {
                if (!elem.find('.icon').length) {
                    elem.find('a').first().append(loading_icon);
                } else {
                    elem.find('.icon').attr('src', frontendajax.nitro_plugin_url + 'view/images/loading.svg')
                    elem.find('.icon').addClass('loading').removeClass('error success');
                }
            },
            success: function (data) {
                if (data.type == 'error') {
                    elem.find('.icon').attr('src', frontendajax.nitro_plugin_url + 'view/images/x-mark.svg');
                    elem.find('.icon').addClass('error');
                    alert(data.message);                   
                } else {
                    elem.find('.icon').attr('src', frontendajax.nitro_plugin_url + 'view/images/check.svg');
                }
                elem.find('.icon').removeClass('loading');
            }
        });
    }

    function clearCacheEntireHandler(clearCacheAction, elem) {
        jQuery.ajax({
            url: frontendajax.ajaxurl,
            type: 'POST',
            data: {
                action: "nitropack_" + clearCacheAction + "_entire_cache",
                nonce: frontendajax.nitroNonce
            },
            dataType: 'json',
            beforeSend: function () {
                if (!elem.find('.icon').length) {
                    elem.find('a').first().append(loading_icon);
                } else {
                    elem.find('.icon').attr('src', frontendajax.nitro_plugin_url + 'view/images/loading.svg')
                    elem.find('.icon').addClass('loading').removeClass('error success');
                }
            },
            success: function (data) {
                if (data.type == 'error') {
                    elem.find('.icon').attr('src', frontendajax.nitro_plugin_url + 'view/images/x-mark.svg');
                    elem.find('.icon').addClass('error');
                    alert(data.message);
                } else {
                    elem.find('.icon').attr('src', frontendajax.nitro_plugin_url + 'view/images/check.svg')
                    elem.find('.icon').addClass('success');
                }
                elem.find('.icon').removeClass('loading');
            }
        });
    }

    jQuery('.nitropack-invalidate-cache').click(function (e) {
        e.preventDefault();
        clearCacheSingleHandler("invalidate", jQuery(this))
        return false;
    });

    jQuery('.nitropack-purge-cache').click(function (e) {
        e.preventDefault();
        clearCacheSingleHandler("purge", jQuery(this))
        return false;
    });

    jQuery('.nitropack-purge-cache-entire-site').click(function (e) {
        e.preventDefault();
        clearCacheEntireHandler("purge", jQuery(this))
        return false;
    });

});

