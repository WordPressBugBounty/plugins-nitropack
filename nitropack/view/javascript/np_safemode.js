jQuery(document).ready(function ($) {
    class nitropackDeactivate {
        constructor() {
            //vars
            this.deactivateLink = 'tr[data-slug="nitropack"] #deactivate-nitropack';
            this.modal = $('#deactivate-modal');
            this.modal_state = 'close';
            //func
            this.closeModalClick();
            this.deactivateClick();
            this.deactivation();
            this.enableTestMode();
        }
        deactivateClick() {
            const nitroSelf = this;
            $(this.deactivateLink).on('click', function (e) {
                e.preventDefault();
                nitroSelf.modalState('open');
            });
        }
        deactivation() {
            const nitroSelf = this,
                deactivateLinkUrl = $(this.deactivateLink).attr('href');
            $('#np-safemode-nogo').on('click', function (e) {
                nitroSelf.modalState('loading');
                e.preventDefault();
                e.stopPropagation();
                $(nitroSelf.deactivateLink).unbind('click'); //enable click                
                nitroSelf.modalState('close');
                if (deactivateLinkUrl !== '') { location.href = deactivateLinkUrl; }
            });
        }
        enableTestMode() {
            const nitroSelf = this;
            $('#np-safemode-go').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(nitroSelf.deactivateLink).unbind('click');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'nitropack_enable_safemode', nonce: frontendajax.nitroNonce },
                    beforeSend: function () {
                        nitroSelf.modalState('loading');
                    },
                    success: function (response) {
                        let responseResult = JSON.parse(response);
                        if (responseResult.type === 'success') {
                            nitroSelf.modalState(responseResult.type);
                        } else {
                            nitroSelf.modalState('error');
                        }
                    },
                    error: function () {
                        nitroSelf.modalState('error');
                    },
                    complete: function () {
                        setTimeout(function () {
                            nitroSelf.modalState('close');
                        }, 1500);
                    }
                });
            });
        }
        modalState(state) {
            const modal_wrapper = this.modal,
                modal_text = modal_wrapper.find('.popup-body p'),
                modal_title = modal_wrapper.find('.popup-header h3'),
                modal_icon = modal_wrapper.find('.icon'),
                modal_footer = modal_wrapper.find('.popup-footer');

            let icon_url;

            switch (state) {
                case 'open':
                    this.openModal();

                    icon_url = this.replaceIconNameInUrl(modal_icon.attr('src'), 'question-circle');
                    modal_icon.attr('src', icon_url).removeClass('rotate-180');
                    modal_title.text('Did you know?');
                    modal_text.text('It is not necessary to deactivate NitroPack for troubleshooting. You can use our Test Mode insted. Do you still want to deactivate?');
                    modal_footer.removeClass('hidden');
                    break;
                case 'close':
                    this.hideModal();
                    break;
                case 'loading':
                    icon_url = this.replaceIconNameInUrl(modal_icon.attr('src'), 'loading');
                    modal_icon.attr('src', icon_url).removeClass('rotate-180');
                    break;
                case 'error':
                    icon_url = this.replaceIconNameInUrl(modal_icon.attr('src'), 'x-circle');
                    modal_icon.attr('src', icon_url).removeClass('rotate-180');
                    modal_title.text('Something went wrong!');
                    modal_text.text('');
                    modal_footer.addClass('hidden');
                    break;
                case 'success':
                    icon_url = this.replaceIconNameInUrl(modal_icon.attr('src'), 'check-circle-green');
                    modal_icon.attr('src', icon_url).removeClass('rotate-180')
                    modal_title.text('Test Mode enabled.');
                    modal_text.text('');
                    modal_footer.addClass('hidden');
                    break;
            }
            this.modal_state = state;
        }
        closeModalClick() {
            const nitroSelf = this;
            //esc key
            $(document).keydown(function (event) {
                if (event.keyCode == 27) nitroSelf.modalState('close');
            });
            //close button
            this.modal.find('.close-modal').click(function () {
                nitroSelf.modalState('close');
            });
            $(document).on('click', 'div[modal-backdrop]',function (e) {
                nitroSelf.modalState('close');
            });
        }
        //cosmetics
        openModal() {
            $('<div modal-backdrop></div>').appendTo('body');
            this.modal.removeClass('hidden');
        }
        hideModal() {
            this.modal.addClass('hidden');
            $('div[modal-backdrop]').remove();
        }
        //helpers
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
    }
    const NitroPackDeactivate = new nitropackDeactivate();
    window.NitroPackDeactivate = NitroPackDeactivate;
});