jQuery(function($){

    $('.nitropack-gravityforms-block').each(function(){
        let $block = $(this);
        let blockName = $block.attr('data-block-name') || '';
        let blockAttributes = $block.attr('data-block-attributes') || '';
        let blockNonce = $block.attr('data-block-nonce') || '';
        let blockQuery = 'action=nitropack_gf_block_output_ajax'
            + '&block_name=' + encodeURIComponent(blockName)
            + '&block_attributes=' + encodeURIComponent(blockAttributes)
            + '&block_nonce=' + encodeURIComponent(blockNonce);

        $block.load(nitropack_gf_ajax.ajax_url + '?' + blockQuery);
    });

    $('.nitropack-gravityforms-shortcode').each(function(){
        let $shortcode = $(this);
        let shortcodeAttributes = $shortcode.attr('data-shortcode-attributes') || '';
        let shortcodeNonce = $shortcode.attr('data-shortcode-nonce') || '';
        let shortcodeQuery = 'action=nitropack_gf_shortcode_output_ajax'
            + '&shortcode-attributes=' + encodeURIComponent(shortcodeAttributes)
            + '&shortcode_nonce=' + encodeURIComponent(shortcodeNonce);

        $shortcode.load(nitropack_gf_ajax.ajax_url + '?' + shortcodeQuery);
    });
});
