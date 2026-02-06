/**
 * NitroPack Integration for Elementor Tools Clear Cache
 *
 * Attaches to Elementor's "Clear Files & Data" button on the Tools page
 * and triggers NitroPack cache clearing silently in the background.
 */
jQuery(document).ready(function($) {
    // Find Elementor's Clear Cache button
    const $clearCacheButton = $('#elementor_clear_cache');

    // Exit if button not found (not on Elementor Tools page)
    if (!$clearCacheButton.length) {
        return;
    }

    // Attach click handler
    $clearCacheButton.on('click', function() {
        // Trigger NitroPack cache clearing via AJAX
        $.ajax({
            url: nitropack_elementor.ajax_url,
            type: 'POST',
            data: {
                action: 'nitropack_elementor_clear_cache',
                nonce: nitropack_elementor.nonce
            },
            dataType: 'json',
            success: function(response) {
                // Silent success - only log to console
                if (response.success) {
                    console.log('[NitroPack] Cache cleared successfully from Elementor Tools');
                } else {
                    console.error('[NitroPack] Cache clearing failed:', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                // Silent error - only log to console
                console.error('[NitroPack] AJAX error clearing cache:', error);
            }
        });
    });
});
