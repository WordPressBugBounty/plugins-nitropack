/**
 * Simple Modal — drop-in replacement for Flowbite Modal.
 * Supports: new Modal(el, options), .show(), .hide(), .toggle(),
 * data-modal-hide, data-modal-show, data-modal-toggle attributes,
 * ESC to close, .close-modal buttons, backdrop click to close.
 */
(function () {
    'use strict';

    var instances = {};

    function Modal(el, options) {
        if (typeof el === 'string') el = document.getElementById(el);
        if (!el) return;

        this._el = el;
        this._options = Object.assign({ backdrop: 'dynamic' }, options);
        this._backdrop = null;
        this._isVisible = false;
        this._onKeydown = this._handleKeydown.bind(this);

        if (el.id) instances[el.id] = this;
    }

    Modal.prototype.show = function () {
        if (this._isVisible) return;
        this._isVisible = true;
        this._createBackdrop();
        this._el.classList.remove('hidden');
        this._el.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', this._onKeydown);
    };

    Modal.prototype.hide = function () {
        if (!this._isVisible) return;
        this._isVisible = false;
        this._el.classList.add('hidden');
        this._el.setAttribute('aria-hidden', 'true');
        this._removeBackdrop();
        document.body.style.overflow = '';
        document.removeEventListener('keydown', this._onKeydown);
    };

    Modal.prototype.toggle = function () {
        this._isVisible ? this.hide() : this.show();
    };

    Modal.prototype.isVisible = function () {
        return this._isVisible;
    };

    Modal.prototype._handleKeydown = function (e) {
        if (e.key === 'Escape' && this._options.backdrop !== 'static') {
            this.hide();
        }
    };

    Modal.prototype._createBackdrop = function () {
        if (this._options.backdrop === false) return;
        this._backdrop = document.createElement('div');
        this._backdrop.setAttribute('modal-backdrop', '');
        document.body.appendChild(this._backdrop);

        if (this._options.backdrop !== 'static') {
            var self = this;
            this._el.addEventListener('click', this._onWrapperClick = function (e) {
                if (e.target === self._el) self.hide();
            });
        }
    };

    Modal.prototype._removeBackdrop = function () {
        if (this._backdrop && this._backdrop.parentNode) {
            this._backdrop.parentNode.removeChild(this._backdrop);
            this._backdrop = null;
        }
        if (this._onWrapperClick) {
            this._el.removeEventListener('click', this._onWrapperClick);
            this._onWrapperClick = null;
        }
    };

    Modal.getInstance = function (id) {
        return instances[id] || null;
    };

    // Auto-init modals from DOM & bind declarative attributes
    function initModals() {
        // Create instances for elements with data-modal-backdrop
        document.querySelectorAll('.modal-wrapper').forEach(function (el) {
            if (!el.id || instances[el.id]) return;
            console.log(el.id);
            var backdrop = el.getAttribute('data-modal-backdrop') || 'dynamic';
            new Modal(el, { backdrop: backdrop });
        });

        // data-modal-show
        document.querySelectorAll('[data-modal-show]').forEach(function (trigger) {
            var id = trigger.getAttribute('data-modal-show');
            trigger.addEventListener('click', function () {
                var inst = instances[id];
                if (inst) inst.show();
            });
        });

        // data-modal-hide
        document.querySelectorAll('[data-modal-hide]').forEach(function (trigger) {
            var id = trigger.getAttribute('data-modal-hide');
            trigger.addEventListener('click', function () {
                var inst = instances[id];
                if (inst) inst.hide();
            });
        });

        // data-modal-toggle
        document.querySelectorAll('[data-modal-toggle]').forEach(function (trigger) {
            var id = trigger.getAttribute('data-modal-toggle');
        
            trigger.addEventListener('click', function () {
                var inst = instances[id];
                    console.log(instances);
                if (inst) inst.toggle();
            });
        });

        // .close-modal buttons — close the closest .modal-wrapper
        document.querySelectorAll('.close-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var wrapper = btn.closest('.modal-wrapper');
                if (wrapper && wrapper.id && instances[wrapper.id]) {
                    instances[wrapper.id].hide();
                }
            });
        });
    }

    window.Modal = Modal;
    window.initModals = initModals;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModals);
    } else {
        initModals();
    }
})();

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