jQuery(function($){

	$('.nitropack-widget-ajax').each(function(){
		let $widget = $(this);
		let widgetId = $widget.attr('data-widget-id') || '';
		let sidebarId = $widget.attr('data-sidebar-id') || '';
		let widgetNonce = $widget.attr('data-widget-nonce') || '';
		let queryString = 'action=nitropack_widget_output_ajax'
			+ '&widget_id=' + encodeURIComponent(widgetId)
			+ '&sidebar_id=' + encodeURIComponent(sidebarId)
			+ '&widget_nonce=' + encodeURIComponent(widgetNonce);

		$widget.load(nitropack_widget_ajax.ajax_url + '?' + queryString);
	});
});
