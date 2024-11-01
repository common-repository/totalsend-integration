/**
 * @version 3accddf64b1dd03abeb9b0b3e5a7ba44 / 1.0.1
 * @author TotalSend <support@totalsend.com>
 * @see http://www.totalsend.com
 * @see Help: http://www.totalsend.com/totalsend/help/integration/wordpress/
 * @package TotalSend WordPress Integration
 */

jQuery(document).ready(function(){
	if (jQuery('#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey+' input[name=action]').length) {
		jQuery('#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey+' input[name=action]').change(function (radio) {
			action = jQuery('#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey+' input[name=action]:checked').val();
			if ('subscribe' == action) {
				jQuery('#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey+' .customFieldsContainer').show();
			} else {
				jQuery('#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey+' .customFieldsContainer').hide();
			}
		});
	}

	jQuery('#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey).submit(function(e) {
		var action = 'subscribe';
		if (jQuery('#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey+' input[name=action]').length) {
			action = jQuery('#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey+' input[name=action]:checked').val();
		}

		jQuery.ajax({
			url: the_ajax_script.ajaxurl,
			dataType: 'json',
			type: 'POST',
			data: jQuery('#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey).serialize()
				+ '&action=ts_frontend_cb&ts_wp_nonce='+the_ajax_script.TotalSendWidgetSessionKey,
			success: function (data, textStatus, XMLHttpRequest) {
				if (true == data.success) {
					var msg = data.msg;
					jQuery('#opResultContainer')
						.html('<div><p style=\"font-weight: bold; color: green;\">'+msg+'</p></div>');

					// reset form
					jQuery(':input','#opSubscribeForm'+the_ajax_script.TotalSendWidgetSessionKey)
						.not(':radio, :button, :submit, :reset, :hidden, :checkbox')
						.val('');
					return false;

				} else {
					var msg = data.msg; // we have different errors
					jQuery('#opResultContainer').html('<div><p style=\"font-weight: bold; color: red;\">'+msg+'</p></div>');
					return false;
				}
			},

			error: function (XMLHttpRequest, textStatus, errorThrown) {
				alert('Error:' + textStatus + ' ' + errorThrown);
			}
		});

		return false;
	});
});
