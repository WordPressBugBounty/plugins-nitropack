<div id="nitropack-connect">
    <header class="header">
        <nav>
            <ol>
                <li class="step passed"><?php esc_html_e('Plugin activation', 'nitropack'); ?></li>
                <li class="step current"><?php esc_html_e('Connect to NitroPack account', 'nitropack'); ?></li>
            </ol>
        </nav>
    </header>
    <main id="main">
        <div class="logos">
            <img src="<?php echo plugin_dir_url(__FILE__) . 'images/nitropack_logo.svg'; ?>" class="" width="116" height="44" alt="NitroPack" />
            <img src="<?php echo plugin_dir_url(__FILE__) . 'images/plus.svg'; ?>" class="" width="32" height="32" alt="+" />
            <img src="<?php echo plugin_dir_url(__FILE__) . 'images/wp_logo.svg'; ?>" class="" width="52" height="52" alt="WordPress" />
        </div>
        <div class="connect">
            <div class="headline-container">
                <h1><?php esc_html_e('Welcome to NitroPack for WordPress', 'nitropack'); ?></h1>
                <p><?php esc_html_e('Let\'s boost your website\'s page load speed and improve your Core Web Vitals.', 'nitropack'); ?></p>
            </div>
            <div class="cta-container">
                <form class="w-full" action="options.php" method="post" id="api-details-form">
                    <?php settings_fields(NITROPACK_OPTION_GROUP);
                    do_settings_sections(NITROPACK_OPTION_GROUP); ?>
                    <div id="manual-connect-fields" style="display: none">
                        <div class="form-row">
                            <label><span><?php esc_html_e('API Key', 'nitropack'); ?></span>
                                <div class="tooltip"><span class="tooltip-icon" data-tooltip-target="tooltip-api-key">
                                        <img src="<?php echo plugin_dir_url(__FILE__) . 'images/info.svg'; ?>">
                                    </span>
                                    <div id="tooltip-api-key" role="tooltip" class="tooltip-container hidden">
                                        <?php esc_html_e('API Key is a unique alphanumeric identifier assigned to each website using NitroPack.', 'nitropack');
                                        ?>
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                    </div>
                                </div>
                                <input id="nitropack-siteid-input" name="nitropack-siteId" type="text" class="form-control" placeholder="<?php esc_html_e('API Key ', 'nitropack'); ?>">
                            </label>
                        </div>
                        <div class="form-row">
                            <label><span><?php esc_html_e('API Secret Key', 'nitropack'); ?></span>
                                <div class="tooltip"><span class="tooltip-icon" data-tooltip-target="tooltip-secret-key">
                                        <img src="<?php echo plugin_dir_url(__FILE__) . 'images/info.svg'; ?>">
                                    </span>
                                    <div id="tooltip-secret-key" role="tooltip" class="tooltip-container hidden">
                                        <?php esc_html_e('Site secret is a confidential alphanumeric key associated with your website, designed to ensure secure communication between NitroPack and your site.', 'nitropack');
                                        ?>
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                    </div>
                                </div>
                                <input id="nitropack-sitesecret-input" name="nitropack-siteSecret" type="text" class="form-control" placeholder="<?php esc_html_e('API Secret Key', 'nitropack'); ?>">
                                <p class="text-smaller"><?php esc_html_e('Learn where to find your site\'s API details', 'nitropack'); ?> <a href="https://nitropack.io/blog/post/how-to-get-your-api-keys" target="_blank"><?php esc_html_e('here', 'nitropack'); ?></a></a>
                            </label>
                        </div>
                    </div>

                    <a href="#" class="btn btn-primary btn-xl w-100" id="connect-nitropack">
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'images/loading.svg'; ?>" alt="loading" class="icon-left hidden">
                        <?php esc_html_e('Connect', 'nitropack'); ?>
                    </a>
                </form>
                <div class="help main"><?php _e('Having trouble connecting? Explore our <a href="#" class="btn-manual-connect">manual connect</a> option, browse our <a href="https://support.nitropack.io/en/collections/6175768-nitropack-for-wordpress-and-woocommerce" target="_blank">FAQ section</a>, or reach out to our <a href="https://support.nitropack.io/en/" target="_blank">support team</a>.', 'nitropack'); ?></div>
                <div class="help manual" style="display: none"><?php esc_html_e('or', 'nitropack'); ?> <a href="#" class="btn-automatic-connect"><?php esc_html_e('connect automatically', 'nitropack'); ?></a></div>

            </div>
        </div>
        <div class="success-container hidden">
            <span class="bg-purple-100 btn-icon rounded-full mb-4" style="height: 36px;"><svg width="13" height="9" viewBox="0 0 13 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11.8334 1L4.50008 8.33333L1.16675 5" stroke="#1B004E" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <h1><?php esc_html_e('All set!', 'nitropack'); ?></h1>
            <p><?php esc_html_e('Your setup is complete – you\'re all set to experience a seamless journey ahead.', 'nitropack'); ?></p>
        </div>
    </main>
</div>
<script>
    (function($) {

        let connectPopup = null;
        const homePageUrl = "<?php echo get_home_url(); ?>";
        const nitroNonce = '<?php echo wp_create_nonce(NITROPACK_NONCE); ?>';

        $(document).ready(function() {
            function automaticConnect() {
                if (!connectPopup || !connectPopup.window) {
                    let screenWidth = window.screen.availWidth;
                    let screenHeight = window.screen.availHeight;
                    let windowWidth = 800;
                    let windowHeight = 700;
                    let leftPos = window.top.outerWidth / 2 + window.top.screenX - (windowWidth / 2);
                    let topPos = window.top.outerHeight / 2 + window.top.screenY - (windowHeight / 2);

                    connectPopup = window.open("https://<?php echo NITROPACKIO_HOST; ?>/auth?website=" + homePageUrl, "QuickConnect", "width=" + windowWidth + ",height=" + windowHeight + ",left=" + leftPos + ",top=" + topPos);
                } else if (connectPopup && connectPopup.window) {
                    connectPopup.focus();
                }
            }
            $('.btn-manual-connect').click(function(e) {
                $('#manual-connect-fields, .help').toggle();
            });
            $('.btn-automatic-connect').click(function() {
                automaticConnect();
            });
            $("#connect-nitropack").on("click", function(e) {
                e.preventDefault();
                let siteId = $("#nitropack-siteid-input").val();
                let siteSecret = $("#nitropack-sitesecret-input").val();
                let loading_icon = $(this).find('.icon-left');
                let isManualConnect = $("#manual-connect-fields").is(":visible");

                loading_icon.removeClass('hidden');

                if (isManualConnect || (siteId && siteSecret)) {
                    $.post(ajaxurl, {
                            action: 'nitropack_verify_connect',
                            siteId: siteId,
                            siteSecret: siteSecret,
                            nonce: nitroNonce
                        })
                        .done(function(response) {
                            let resp = JSON.parse(response);
                            if (resp.status == "success") {
                                $(".success-container").removeClass('hidden');
                                $(".header, .connect").addClass('hidden');
                                location.reload();
                            } else {
                                $("#nitropack-siteid-input, #nitropack-sitesecret-input").val("");
                                $("#main .notification").remove();
                                let errorMessage = resp.message ? resp.message : "<?php esc_html_e('Api details verification failed! Please check whether you entered correct details.', 'nitropack'); ?>";

                                if ($('#manual-connect-fields .nitro-notification').length) {
                                    $('#manual-connect-fields .nitro-notification .notification-inner p').text(errorMessage);
                                } else {
                                    $('#manual-connect-fields').prepend('<div class="nitro-notification notification-danger max-w-sm mb-8 ml-auto mr-auto"><div class="text-box text-center w-full"><div class="notification-inner"><p>' + errorMessage + '</p></div></div></div>');
                                }                              
                            }
                        })
                        .fail(function() {
                            console.error("An error occurred during the AJAX request.");
                        })
                        .always(function() {
                            loading_icon.addClass('hidden');
                        });
                } else if (!isManualConnect) {
                    automaticConnect();

                }
                loading_icon.removeClass('hidden');
            });
        });

        window.addEventListener("message", function(e) {
            if (e.data.messageType == "nitropack-connect") {
                $("#nitropack-siteid-input").val(e.data.api.key);
                $("#nitropack-sitesecret-input").val(e.data.api.secret);
                $("#connect-nitropack").click();
                if (connectPopup && !connectPopup.closed) {
                    connectPopup.close();
                    connectPopup = null;
                }
            }
        });

    })(jQuery);
</script>