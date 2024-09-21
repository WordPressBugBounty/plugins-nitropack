jQuery(document).ready(function ($) {
    class nitropackUI {
        constructor() {
            this.closeToast();
            this.highlight_columns();
            this.cosmetics();

            //toasts
            this.elapsedToastTime = 0;
        }
        triggerToast(status, msg) {
            if (!status) return;
            const nitroSelf = this,
                toast_wrapper = $('.toast-wrapper').eq(0),
                toast_text = toast_wrapper.find('.msg-box .text'),
                toast_icon = toast_wrapper.find('.icon img');
            var css_status = 'toast-' + status;

            if (toast_wrapper.hasClass('shown')) {
                //clone and hide prev toast
                const clone = toast_wrapper.clone();
                this.duplicateToast(clone, status, msg);
                nitroSelf.hideToast(toast_wrapper);
            } else {
                //adjust text and show
                toast_text.html(msg);
                this.replaceIcon(status, toast_icon);
                toast_wrapper.addClass('shown ' + css_status);
            }
            this.pauseAndHide(toast_wrapper);
        }
        pauseAndHide(toast_wrapper) {
            const nitroSelf = this,
                remainingTime = 1500;
            var showedTime = Date.now(),
                timeoutId,
                currentRemaining = 1500;

            timeoutId = setTimeout(function () {
                nitroSelf.hideToast(toast_wrapper);
            }, remainingTime);
            //Pause timeout on mouse hover
            toast_wrapper.on('mouseenter', function () {
                nitroSelf.elapsedToastTime = Date.now() - showedTime;
                clearTimeout(timeoutId);
            });
            //Resume timeout on mouse leave
            toast_wrapper.on('mouseleave', function () {
                showedTime = Date.now() - nitroSelf.elapsedToastTime; //track on multiple hover the correct showed time and apply to elapsedToastTime

                currentRemaining = Math.max(remainingTime - nitroSelf.elapsedToastTime, 0); // Calculate remaining time

                timeoutId = setTimeout(function () {
                    nitroSelf.hideToast(toast_wrapper);
                }, currentRemaining);
            });
        }
        duplicateToast(clone, status, msg) {
            const toast_wrapper = $('.toast-wrapper');
            var visible_toasts = $('.toast-wrapper.shown').length,
                css_status = 'toast-' + status,
                bottom = 8 + (5 * (visible_toasts - 1)), //multiply by 5, first is 8
                toast_icon = clone.find('.icon img');

            clone.find('.msg-box .text').html(msg);
            this.replaceIcon(status, toast_icon);
            this.hideToast(clone);

            clone.insertBefore(toast_wrapper.eq(0));

            setTimeout(function () {
                clone.addClass(css_status + ' shown');

            }, 250);
            this.pauseAndHide(clone);
        }
        toastIcon(status) {
            const icon = {
                'error': 'alert-triangle',
                'success': 'check-circle-green',
                'info': 'info-circle-blue'
            };
            return icon[status];
        }
        replaceIcon(status, toast_icon) {
            var new_icon = this.toastIcon(status),
                icon_url = this.replaceIconNameInUrl(toast_icon.attr('src'), new_icon);
            toast_icon.attr('src', icon_url);
        }
        replaceIconNameInUrl(url, newIconName) {
            // Find the index of the last "/"
            const lastSlashIndex = url.lastIndexOf('/');
            // Find the index of the ".svg" extension
            const svgIndex = url.indexOf('.svg', lastSlashIndex);
            // Extract the icon name between the last "/" and ".svg"
            const currentIconName = url.substring(lastSlashIndex + 1, svgIndex);
            // Construct the new URL with the replaced icon name
            const newUrl = url.replace(currentIconName + '.svg', newIconName + '.svg');

            return newUrl;
        }
        closeToast() {
            const nitroSelf = this;
            $(document).on('click', '.toast-close', function () {
                const el = $(this).closest('.toast-wrapper');
                nitroSelf.hideToast(el)
            });
        }
        hideToast(el) {
            el.removeClass('shown toast-success toast-error toast-info');
        }
        //end of toasts
        tabs() {
            $('.tabs .tab-link').click(function () {
                let tab = $(this).data('tab'),
                    tab_content_wrapper = $(this).closest('.tabs-wrapper').find('.tab-content-wrapper');
                tab_content_wrapper.find('.tab-content').addClass('hidden');
                tab_content_wrapper.find('.tab-content[data-tab="' + tab + '-tab"].hidden').removeClass('hidden');
            });
        }
        highlight_columns() {
            $('.modes .mode').on("mouseenter", function () {
                var columnIndex = $(this).index(); // Get the index of the cell within its parent container
                $(this).addClass('current-highlight')
                $('.modes .mode:nth-child(' + (columnIndex + 1) + ')').addClass("highlight-column");
                // Add the background class to all cells in the same column
            }).on("mouseleave", function () {
                $('.modes .mode').removeClass("highlight-column current-highlight");
                // Remove the background class from all cells
            });
        }
        toggle_submenu() {
            $('.toggle-dropdown').click(function () {
                let parent_li = $(this).closest('.list-item'),
                    child_ul = parent_li.find('ul'),
                    toggle_btn = $(this);
                child_ul.toggleClass('opened');
                toggle_btn.toggleClass('rotate-180');
            });
        }
        posttype_taxonomy_counter() {
            $('#modal-posttypes .taxonomies label input').click(function () {
                var parent_li = $(this).closest('.sub-menu').parent('.list-item'),
                    counter_div = parent_li.find('.count'),
                    counter = counter_div.text() * 1;
                if ($(this).is(':checked')) {
                    counter++
                } else {
                    counter--
                }
                counter_div.text(counter);
            });
        }
        cosmetics() {
            $('.tooltip-container').removeClass('hidden');
        }

    }
    const NitropackUI = new nitropackUI();
    window.NitropackUI = NitropackUI;
});