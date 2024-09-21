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
                <h1><?php esc_html_e('Welcome to NitroPack OneClick for WordPress', 'nitropack'); ?></h1>
                <p><?php esc_html_e( 'Your license is managed by your hosting provider.', 'nitropack'); ?></p>                
            </div>
            <div class="cta-container">
                <form class="w-full" action="options.php" method="post" id="api-details-form">
                    <?php settings_fields(NITROPACK_OPTION_GROUP);
                    do_settings_sections(NITROPACK_OPTION_GROUP); ?>
                    <?php if ($oneClickConnectUrl) : ?>
                    <a class="btn btn-primary btn-xl w-100" href="<?php esc_attr_e($oneClickConnectUrl); ?>">
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'images/loading.svg'; ?>" alt="loading" class="icon-left hidden">
                        <?php esc_html_e('Connect to NitroPack OneClick', 'nitropack'); ?>
                    </a>
                    <?php endif; ?>
                    <p class="text-center mt-2"><?php esc_html_e('Visit your hosting provider page to connect NitroPack with your WordPress site.', 'nitropack'); ?></p>
                </form>
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