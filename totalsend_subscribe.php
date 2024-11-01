<?php
/**
 * @version 3accddf64b1dd03abeb9b0b3e5a7ba44 / 1.0.1
 * @author TotalSend <support@totalsend.com>
 * @see http://www.totalsend.com
 * @see Help: http://www.totalsend.com/totalsend/help/integration/wordpress/
 * @package TotalSend WordPress Integration
 */

/*
 * Plugin Name: TotalSend Integration
 * Plugin URI: https://www.totalsend.com
 * Description: With this plugin, blog owner will be able to "link" his/her TotalSend account with WordPress and start accepting email list subscriptions from his/her blog.
 * Version: 1.0.2
 * Author: TotalSend
 * Author URI: https://www.totalsend.com
 * Help URI: https://www.totalsend.com
 * License: MIT License
 */

define('TOTALSEND_SUBSCRIBE', '1.0.1');
define('TOTALSEND_PLUGIN_NAME', 'TotalSend Integration');
define('TOTALSEND_SUBSCRIBE_ACTION', WP_PLUGIN_URL . '/totalsend/dispatch.php');
define('TOTALSEND_PLUGIN_FILE', WP_PLUGIN_URL . '/totalsend/totalsend_subscribe.php');
define('TOTALSEND_ACCOUNT_URL', 'https://app.totalsend.com');

// Listen for the activate event
register_activation_hook(__FILE__, array('TotalSendSubscribeAdmin', 'activate'));

// Listen for the deactivate event
register_deactivation_hook(__FILE__, array('TotalSendSubscribeAdmin', 'deactivate'));

add_action('admin_init', array('TotalSendSubscribeAdmin', 'admin_init'));
add_action('admin_menu', array('TotalSendSubscribeAdmin', 'add_page'));

add_action('init', array('TotalSendSubscribeFront', 'init'));
add_action('init', array('TotalSendSubscribeFront', 'init_session'), 1);
add_action('init', array('TotalSendSubscribeWidget', 'init'), 1);

function ts_load_widget() {
    register_widget( 'TotalSendSubscribeWidget' );
}
add_action( 'widgets_init', 'ts_load_widget' );

add_action('wp_print_scripts', array('TotalSendSubscribeFront', 'enqueue_scripts'));

add_action('plugin_action_links_' . plugin_basename( __FILE__ ), array('TotalSendSubscribeAdmin', 'add_action_links'));
add_action('wp_ajax_ts_test_connection', array('TotalSendSubscribeAdmin', 'testConnection'));
add_action('wp_ajax_ts_get_subscriber_lists', array('TotalSendSubscribeAdmin', 'getLists'));
add_action('wp_ajax_ts_get_subscriber_list_fields', array('TotalSendSubscribeAdmin', 'getListCustomFields'));
add_action('wp_ajax_nopriv_ts_frontend_cb', array('TotalSendSubscribeFront', 'ts_frontend_cb'));
add_action('wp_ajax_ts_frontend_cb', array('TotalSendSubscribeFront', 'ts_frontend_cb'));

$TotalSendPluginOptions = array(
	'ts_login' => __('Integration Username'),
	'ts_password' => __('Integration Password'),
	'ts_account_url' => __('Integration URL'),
	'ts_subscription_success' => __('Subscribe success message'),
	'ts_subscription_success_pending' => __('Subscribe confirmation pending message'),
	'ts_unsubscription_success' => __('Unsubscribe success message'),

	'ts_subscription_error_2' => __('"Email address is missing" error message'),
	'ts_subscription_error_5' => __('"Invalid email address format" error message'),
	'ts_subscription_error_9' => __('"Email address already exists in the target list" error message'),

	'ts_unsubscription_error_3' => __('"Email address must be provided" error message'),
	'ts_unsubscription_error_6' => __('"Invalid email address format" error message'),
	'ts_unsubscription_error_7' => __('"Email address doesn\'t exist in the list" error message'),
	'ts_unsubscription_error_9' => __('"Email address already unsubscribed" error message'),
);

// ============================================================================
    class TotalSendSubscribeWidget extends WP_Widget
{

	public function __construct()
	{
		$widget_options = array(
			'classname' => 'ts_subscribe_widget',
			'description' => __('Attach and configure to allow users to subscribe to your TotalSend newsletters.'),
		);
		//$this->WP_Widget('ts_subscribe', TOTALSEND_PLUGIN_NAME, $widget_options);
        // Instantiate the parent object
        parent::__construct('ts_subscribe', TOTALSEND_PLUGIN_NAME, $widget_options);
    }

	public static function init()
	{
		//register_widget('TotalSendSubscribeWidget');
		self::register_shortcodes();
	}

	/**
	 * Implements Wordpress shortcode for TotalSend wordpress widget.
	 *
	 * @return string HTML Form for shortcode or corresponding error message if widget hasn't been setup.
	 */
	public function shortcode()
	{
		$widget_front = new TotalSendSubscribeFront();
		$instance = get_option('widget_ts_subscribe');

		if($instance !== false && is_array($instance)) {
			$instance = reset($instance);
			$return_v = $widget_front->draw($instance);
			return $return_v;
		}

		return 'The widget has not been setup!';
	}

	/**
	 * Registers the TotalSend Wordpress shortcode.
	 */
	public static function register_shortcodes()
	{
		add_shortcode('totalsend-subscribe-form', array(
			'TotalSendSubscribeWidget',
			'shortcode',
		));
	}

	public function widget( $args, $instance ) // Widget Output
	{
		extract($args);
		$data['title'] = apply_filters(
			'widget_title',
			empty($instance['title']) ? '&nbsp;' : $instance['title'],
			$instance,
			$this->id_base
		);
		$data['target_list'] = apply_filters(
			'widget_target_list',
			intval($instance['target_list']),
			$instance,
			$this->id_base
		);
		$data['allow_unsubscribe'] = apply_filters(
			'widget_allow_unsubscribe',
			empty($instance['allow_unsubscribe']) ? 0 : $instance['allow_unsubscribe'],
			$instance,
			$this->id_base
		);
		$data['custom_fields'] = $instance['custom_fields'];
		echo $args['before_widget'];
		if ($data['title'])
			echo $args['before_title'] . $data['title'] . $args['after_title'];
		echo '<div class="ts_subscribe_widget_wrap">';
		echo TotalSendSubscribeFront::draw($data);
		echo '</div>';
		echo $args['after_widget'];
	}

	public function update( $new_instance, $old_instance ) // Save widget options
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['allow_unsubscribe'] = $new_instance['allow_unsubscribe'] ? 1 : 0;
		$instance['target_list'] = (int)$new_instance['target_list'];
		$instance['custom_fields'] = $new_instance['custom_fields'];
		return $instance;
	}

	public function form( $instance ) // Output admin widget options form
	{
		$instance = wp_parse_args((array)$instance, array(
			'title' => '',
			'allow_unsubscribe' => 1
		));

		$title = isset($instance['title']) ? $instance['title'] : '';
		$allowUnsubscribe = isset($instance['allow_unsubscribe']) ? (int)$instance['allow_unsubscribe'] : 0;
		$targetList = isset($instance['target_list']) ? (int)$instance['target_list'] : 0;

		$customFields = array();
		if(isset($instance['custom_fields']))
			$customFields = (!empty($instance['custom_fields']) and is_array($instance['custom_fields'])) ? $instance['custom_fields'] : array();

		$title_field_id = self::get_field_id('title');
		$title_field_name = self::get_field_name('title');

		$targetList_field_id = self::get_field_id('target_list');
		$targetList_field_name = self::get_field_name('target_list');

		printf(
			'<p>
			<label for="%s>">%s</label>
			<input class="widefat" id="%s" name="%s" type="text" value="%s" />
			</p>',
			$title_field_id,
			__('Title:'),
			$title_field_id,
			$title_field_name,
			esc_attr($title)
		);
		printf(
		    '<p>
	        <label for="%s>">%s</label><br />
	        <select class="select widefat" id="%s" name="%s"></select>
		    </p>',
			$this->get_field_id('target_list'),
			__('Target subscribers list:'),
			$targetList_field_id,
			$targetList_field_name
		);

		printf(
			"<script type=\"text/javascript\">

				jQuery(document).ready(function() {
					// Resolves a Wordpress bug related to drag and dropping widgets: http://goo.gl/4m3Xfw
					jQuery(document).ajaxComplete(function(event, XMLHttpRequest, ajaxOptions) {

						// determine which ajax request is this (we're after \"save-widget\")
						var request = {}, pairs = ajaxOptions.data.split('&'), i, split, widget;

						for(counter in pairs) {
							split = pairs[counter].split('=');
							request[decodeURIComponent(split[0])] = decodeURIComponent(split[1]);
						}

						// only proceed if this was a widget-save request
						if(request.action && (request.action === 'save-widget')) {

							// locate the widget block
							widget = jQuery('input.widget-id[value=\"' + request['widget-id'] + '\"]').parents('.widget');

							// trigger manual save, if this was the save request
							// and if we didn't get the form html response (the wp bug)
							if(!XMLHttpRequest.responseText)
							  wpWidgets.save(widget, 0, 1, 0);

							// we got an response, this could be either our request above,
							// or a correct widget-save call, so fire an event on which we can hook our js
							else
								jQuery(document).trigger('saved_widget', widget);
						}
					}); // END - AjaxComplete
				}); // END - ready(...)


				getCustomFieldsByList = function (listId) {
					jQuery.post(
						ajaxurl,
						{
							action: 'ts_get_subscriber_list_fields',
							cookie: encodeURIComponent(document.cookie),
							data: {
								listId: listId
							}
						},
						function(result) {
							var instanceNumber = '%s';
							if (true == result.success && 0 < result.fields.length) {

								var html = '';
								var optionName = 'widget-" . $this->id_base . "';
								var selectedFieldsJson = " . json_encode($customFields) . ";

								jQuery.each(result.fields, function() {
									var id = optionName+'-'+instanceNumber+'-'+this.FieldName.toLowerCase().replace(' ', '_');
									var fieldId = this.CustomFieldID;
									var isSelected = false;
									jQuery.each(selectedFieldsJson, function (k, v) {
										if (fieldId == k) {
											if (v.enabled) {
												isSelected = true;
											}
										}
									});

									html += '<input id=\"'+id+'\" type=\"checkbox\" name=\"'+optionName+'['+instanceNumber+'][custom_fields]['+fieldId+'][enabled]\" value=\"true\"'+((true == isSelected) ? 'checked=\"checked\"': '')+' />';
									html += '<label for=\"'+id+'\">'+this.FieldName+'</label><br />';

									jQuery.each(this, function(k, v) {
										html += '<input type=\"hidden\" name=\"'+optionName+'['+instanceNumber+'][custom_fields]['+fieldId+']['+k+']\" value=\"'+v+'\" />';
									});
								});

								jQuery('#customFieldsContainer'+instanceNumber).html('' + html);

							} else {
								jQuery('#customFieldsContainer'+instanceNumber).html('<p>" . __('No custom fields available.') . "</p>');
							}
						},
						'json'
					);
				}


				jQuery.post(
					ajaxurl,
					{
						action: 'ts_get_subscriber_lists',
						cookie: encodeURIComponent(document.cookie),
						data: {}
					},
					function(result) {
						if (result.success) {
							var html = '<option value=\"\">Please select a list</option>';
							var selected = '" . $targetList . "';
							for(var i = 0, n = result.lists.length; i < n; i++) {
								var list = result.lists[i];
								html += '<option value=\"'+list.ListID+'\"'+((list.ListID == selected) ? 'selected=\"selected\"': '')+'>'+list.Name+'</option>';
							}
							selectElId = '%s';

							// bind change event to fetch custom fields
							jQuery(\"[id$='-target_list']\")
								.html(html)
								.on(
									'change',
									function() {
										getCustomFieldsByList(jQuery(this).val());
									}
								);

							// fetch custom fields for preloaded list
							getCustomFieldsByList(jQuery('#'+selectElId).val());

						} else {
							var msg = '';
							if (result.msg) {
								msg = result.msg;
							} else {
								msg = '" . __('There are no any subscriber lists available. Please create one at your TotalSend account') . "';
							}
							alert(msg);
							return false;
						}

					},
					'json'
				);
				</script>",
			$this->number,
			$targetList_field_id
		);

		printf(
			'<p>
				<input class="checkbox" type="checkbox" id="%s" name="%s" ' . checked($allowUnsubscribe, true, false) . ' />
				<label for="%s>">%s</label>
			</p>',
			$this->get_field_id('allow_unsubscribe'),
			$this->get_field_name('allow_unsubscribe'),
			$this->get_field_id('allow_unsubscribe'),
			__('Allow unsubscribe')
		);

		printf('<h4>%s</h4>', __('Custom fields:'));
		printf('<p>%s</p>', __('Select custom fields which will be shown on frontend.'));
		printf('<div id="customFieldsContainer%s"></div>', $this->number);
	}
}

// ============================================================================
class TotalSendSubscribeAdmin
{
	private static $option_name = 'totalsend-subs';
	private static $data = array(
		'ts_login' => '',
		'ts_password' => '',
		'ts_account_url' => TOTALSEND_ACCOUNT_URL,
		'ts_subscription_success' => '',
		'ts_subscription_success_pending' => '',
		'ts_unsubscription_success' => '',

		'ts_subscription_error_2' => '',
		'ts_subscription_error_5' => '',
		'ts_subscription_error_9' => '',

		'ts_unsubscription_error_3' => '',
		'ts_unsubscription_error_6' => '',
		'ts_unsubscription_error_7' => '',
		'ts_unsubscription_error_9' => '',
	);

	public static function activate()
	{
		update_option(self::$option_name, self::$data);
        // [[OK]]
	}

	public static function deactivate()
	{
		delete_option(self::$option_name);
	}

	public static function add_action_links($links)
	{
	    // @todo Check
		return array_merge(
			array('settings' => '<a href="'.get_bloginfo('wpurl').'/wp-admin/options-general.php?page=totalsend-subscribe">Settings</a>'),
			$links
		);
		// [[OK]]
	}

	public static function admin_init()
	{
		register_setting('totalsend-subscribe', self::$option_name, array('TotalSendSubscribeAdmin', 'validate'));
	}

	public static function add_page()
	{
		add_options_page(
			'TotalSend Integration Options',
			TOTALSEND_PLUGIN_NAME,
			'manage_options',
			'totalsend-subscribe',
			array('TotalSendSubscribeAdmin', 'options_do_page')
		);
	}

	public static function options_do_page()
	{
		global $TotalSendPluginOptions;
		$options = get_option(self::$option_name);
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}

		echo '<div class="wrap">';
		echo '<div id="icon-options-general" class="icon32"><br></div><h2><img src="https://77fd076ee376409e7cdf45b674315ad16b51a2f9.googledrive.com/host/0B3ve0VXYPb3DX0FGTXhuTVBueTQ/ts_sig_logo.png"> '. __('WordPress Integration') .'</h2>';
		printf('<div id="resultsContainer"><p>%s</p></div>', __('After saving of these options go to theme widgets to enable and configure your subscription form.'));
		echo '<form method="post" action="options.php" id="tsSettingsForm">';
		wp_nonce_field('update-options');
		settings_fields('totalsend-subscribe');
		echo '<input type="hidden" name="action" value="update" />';
		echo '<table class="form-table"><tbody>';
		$elCounter = 0;
		$advancedOptionsRefShown = false;
		foreach ($TotalSendPluginOptions as $optionCode => $title) {
			$input_type_str = 'text';
			if($optionCode == 'ts_password') {
				$input_type_str = 'password';
			} elseif($optionCode == 'ts_account_url') {
				$input_type_str = 'hidden';
			}

			if($elCounter > 2) {
				if($advancedOptionsRefShown === false) {
					echo '<tr valign="top">
								<th><a href="#" id="advancedOptionsRef" class="advancedOptionsRef">Show Advanced Options</a></th>
								<td></td>
						  </tr>';

					$advancedOptionsRefShown = true;
				}
				echo '<tr valign="top" class="advancedOptionsElement">';
			} else {
				if($input_type_str != 'hidden')
					echo '<tr valign="top">';
				else
					echo '<tr style="display:none;">';
			}

			printf(
				'<th scope="row">%s</th>
				<td><input type="%s" name="%s" id="%s" value="%s" class="regular-text code" /></td>
				 </tr>', $title, $input_type_str, self::$option_name.'['.$optionCode.']', $optionCode, $options[$optionCode]
			);

			$elCounter++;
		}
		echo '</tbody></table>';
		printf('<input type="hidden" name="page_options" value="%s" />', implode(',', array_keys($TotalSendPluginOptions)));
		printf('<div id="submitContainer"><p class="submit"><input type="button" class="button-primary" value="%s" id="submitBtn" /></p></div>', __('Save changes'));
		echo '</form>';
		echo '</div>';
		echo
			"<script type=\"text/javascript\">
				jQuery('#submitBtn').click(function() {
					jQuery.post(
						ajaxurl
						,{
							action: 'ts_test_connection',
							cookie: encodeURIComponent(document.cookie),
							data: {
								ts_account_url: jQuery('#ts_account_url').val(),
								ts_login: jQuery('#ts_login').val(),
								ts_password: jQuery('#ts_password').val()
							}
						}
						,function(result) {
							if (result.success) {
								jQuery('#resultsContainer').html('<div class=\"message updated\"><p>" . __('Congratulations! Supplied connection credentials are valid.') . "</p></div>');
								jQuery('#tsSettingsForm').submit()
							} else {
								jQuery('#resultsContainer').html('<div class=\"error\"><p>" . __('Supplied connection credentials are not valid. Please review and correct.') . "</p></div>');
								return false;
							}
						}
						,'json'
					)
				});

				var advancedOptionsShowing = false;
				var showHideAdvancedFn = function() {
					jQuery('.advancedOptionsElement').hide();
					jQuery('.advancedOptionsRef').click(function() {
						jQuery('.advancedOptionsElement').toggle(100, function(){});

						if(window.advancedOptionsShowing === false) {
							jQuery('.advancedOptionsRef').text('Hide Advanced Options');
							window.advancedOptionsShowing = true;
						} else {
							jQuery('.advancedOptionsRef').text('Show Advanced Options');
							window.advancedOptionsShowing = false;
						}

						return false;
					});
				};

				jQuery(document).ready(showHideAdvancedFn);
		    </script>";
	}

	public static function validate($input)
	{
		global $TotalSendPluginOptions;
		$options = get_option(self::$option_name);
		$valid = array();
		$ar_keys = array_keys($TotalSendPluginOptions);

		foreach($ar_keys as $keyItem)
		{
			$valid[$keyItem] = sanitize_text_field($input[$keyItem]);
		}

		if (strlen($valid['ts_login']) == 0) {
			add_settings_error(
				'ts_login',                     // Setting title
				'tslogin_texterror',            // Error ID
				'Please enter a valid Login or Username',     // Error message
				'error'                         // Type of message
			);

			// Set it to the default value
			$valid['ts_login'] = $options['ts_login'];
		}

		if (strlen($valid['ts_password']) == 0) {
			add_settings_error(
				'ts_password',                     // Setting title
				'tslogin_texterror',            // Error ID
				'Please enter a valid password',     // Error message
				'error'                         // Type of message
			);

			$valid['ts_password'] = $options['ts_password'];
		}

		if (strlen($valid['ts_account_url']) == 0) {
			add_settings_error(
				'ts_login',                     // Setting title
				'tslogin_texterror',            // Error ID
				'Please enter a valid account URL',     // Error message
				'error'                         // Type of message
			);

			// Set it to the default value
			$valid['ts_account_url'] = $options['ts_account_url'];
		}

		return $valid;
		// [[OK]]
	}


	public static function getLists()
	{
		$inputData = isset($_POST['data']) ? $_POST['data'] : array();
		$result = array(
			'success' => false,
			'lists' => null,
		);
		$TSSubscribeDispatcher = new TotalSendSubscribeDispatcher();
		$TSSubscribeDispatcher->init($inputData);
		$lists = $TSSubscribeDispatcher->getSubscriberLists();
		if (count($lists['Lists'])) {
			$result = array(
				'success' => true,
				'lists' => $lists['Lists'],
			);
		}
		die(json_encode($result));
	}

	public static function getListCustomFields()
	{
		$result = array(
			'success' => false,
			'fields' => null,
		);
		if (!$listId = (int)$_POST['data']['listId']) die(json_encode($result));
		$TSSubscribeDispatcher = new TotalSendSubscribeDispatcher();
		$TSSubscribeDispatcher->init($_POST['data']);
		$fields = $TSSubscribeDispatcher->getSubscriberListFields($listId);

		if (is_array($fields) && count($fields)) {
			$result = array(
				'success' => true,
				'fields' => $fields,
			);
		}
		die(json_encode($result));
	}

	public static function testConnection()
	{
		$result = array(
			'success' => false,
		);
		$TSSubscribeDispatcher = new TotalSendSubscribeDispatcher();
		$TSSubscribeDispatcher->init($_POST['data']);
		if ($TSSubscribeDispatcher->getConnection())
			$result['success'] = true;

		die(json_encode($result));
	}
}

// ============================================================================
class TotalSendSubscribeFront
{

	public static function init()
	{
		wp_enqueue_script('jquery');
	}

	public static function init_session()
	{
		if(session_id() != '')
			session_start();
	}

	public static function enqueue_scripts()
	{
		$TotalSendWidgetSessionKey = wp_create_nonce('totalsend-widget-nonce');
		$_SESSION['TotalSendWidgetSessionKey'] = $TotalSendWidgetSessionKey;
		wp_enqueue_script('ts_handle', WP_PLUGIN_URL .'/totalsend-integration/ts_script.js', array('jquery'));
		wp_localize_script('ts_handle', 'the_ajax_script', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'TotalSendWidgetSessionKey' => $TotalSendWidgetSessionKey,
		));
	}

	public static function draw($data)
	{
		$widgetSessionKey = isset($_SESSION['TotalSendWidgetSessionKey']) ? $_SESSION['TotalSendWidgetSessionKey'] : wp_create_nonce('totalsend-widget-nonce');

		$html = '
      <form role="opSubscribeForm" method="post" id="opSubscribeForm' . $widgetSessionKey . '">
        <input type="hidden" name="target_list" value="' . intval($data['target_list']) . '" />
        <div id="opResultContainer"></div>
    	  <div>
  	      <p>
	          <label for="opSubscriptionField' . $widgetSessionKey . '">Email:</label><br />
	          <input type="text" value="" name="email" id="opSubscriptionField' . $widgetSessionKey . '">
  	      </p>';
		if (isset($data['custom_fields']) and count($data['custom_fields'])) {
			foreach ($data['custom_fields'] as $custom_field) {
				if (isset($custom_field['enabled']) and true == $custom_field['enabled']) {
					$id = 'opCustomField' . $custom_field['CustomFieldID'] . $widgetSessionKey;
					$name = 'custom_fields[CustomField' . $custom_field['CustomFieldID'] . ']';

					if ('Hidden field' != $custom_field['FieldType'])
						$html .= '<p><label for="' . $id . '">' . $custom_field['FieldName'] . ':</label>';

					switch ($custom_field['FieldType']) {
						case 'Single line':
							$html .= '<br /><input type="text" value="' . htmlentities($custom_field['FieldDefaultValue']) . '" name="' . $name . '" id="' . $id . '" />';
							break;
						case 'Paragraph text':
							$html .= '<br /><textarea name="' . $name . '" id="' . $id . '">' . htmlentities($custom_field['FieldDefaultValue']) . '</textarea>';
							break;
						case 'Multiple choice':
							if ($options = explode(',,,', $custom_field['FieldOptions'])) {
								$html .= '<ul>';
								foreach ($options as $option) {
									$checked = false;
									$option = str_replace(array('[', ']'), '', $option);
									list($k, $v) = explode('||', $option);
									if(false !== strpos($v, '*')) {
										$checked = true;
										$v = str_replace('*', '', $v);
									}
									$html .= '<li><input type="radio" name="' . $name . '" value="' . $v . '"' . (($checked) ? ' checked="checked"' : '') . ' />&nbsp;<label>' . $k . '</label></li>';
								}
								$html .= '</ul>';
							}
							break;

						case 'Drop down':
							if ($options = explode(',,,', $custom_field['FieldOptions'])) {
								$html .= '<br /><select name="' . $name . '" id="' . $id . '">';
								foreach ($options as $option) {
									$checked = false;
									$option = str_replace(array('[', ']'), '', $option);
									list($k, $v) = explode('||', $option);
									if(false !== strpos($v, '*')) {
										$checked = true;
										$v = str_replace('*', '', $v);
									}
									$html .= '<option value="' . $v . '"' . (($checked) ? ' selected="selected"' : '') . '>' . $k . '</option>';
								}
								$html .= '</select>';
							}
							break;

						case 'Checkboxes':
							if ($options = explode(',,,', $custom_field['FieldOptions'])) {
								$html .= '<ul>';
								foreach ($options as $option) {
									$checked = false;
									$option = str_replace(array('[', ']'), '', $option);
									list($k, $v) = explode('||', $option);
									if(false !== strpos($v, '*')) {
										$checked = true;
										$v = str_replace('*', '', $v);
									}
									$html .= '<li><input type="checkbox" name="' . $name . '[]" value="' . $v . '"' . (($checked) ? ' checked="checked"' : '') . ' /><label>' . $k . '</label></li>';
								}
								$html .= '</ul>';
							}
							break;

						case 'Hidden field':
							$html .= '<input type="hidden" value="' . htmlentities($custom_field['FieldDefaultValue']) . '" name="' . $name . '" id="' . $id . '" />';
							break;

						case 'Date field':
							$html .= '<br />';

							// Days - Start {
							$day_str = '<select name="'.$name.'[]" id="'.$id.'">';
							for($counter = 0; $counter < 32; $counter++)
							{
								if($counter === 0) {
									$day_str .= '<option value="">'.__('Day').'</option>';
									continue;
								}

								$day_str .= '<option value="'.sprintf('%02d', $counter).'">'.sprintf('%02d', $counter).'</option>';
							}
							$day_str .= '</select>';
							$html .= $day_str;
							// Days - End }

							// Months - Start {
							$month_str = '&nbsp;<select name="'.$name.'[]" id="'.$id.'">';
							for($counter = 0; $counter < 13; $counter++)
							{
								if($counter === 0) {
									$month_str .= '<option value="">'.__('Month').'</option>';
									continue;
								}

								$month_str .= '<option value="'.sprintf('%02d', $counter).'">'.sprintf('%02d', $counter).'</option>';
							}
							$month_str .= '</select>';
							$html .= $month_str;
							// Months - End }

							// Years - Start {
							$startYear = (int) $custom_field['DateFieldYearsStart'];
							$endYear = (int) $custom_field['DateFieldYearsEnd'];
							$year_str = '&nbsp;<select name="'.$name.'[]" id="'.$id.'">';
							for($counter = ($startYear-1); $counter <= $endYear; $counter++)
							{
								if($counter == ($startYear-1)) {
									$year_str .= '<option value="">'.__('Year').'</option>';
									continue;
								}

								$year_str .= '<option value="'.$counter.'">'.$counter.'</option>';
							}
							$year_str .= '</select>';
							$html .= $year_str;
							// Years - End }
							break;

						case 'Time field':
							$html .= '<br />';

							// Hours - Start {
							$hour_str = '<select name="'.$name.'[]" id="'.$id.'">';
							for($counter = -1; $counter < 24; $counter++)
							{
								if($counter === -1) {
									$hour_str .= '<option value="">'.__('Hour').'</option>';
									continue;
								}

								$hour_str .= '<option value="'.sprintf('%02d', $counter).'">'.sprintf('%02d', $counter).'</option>';
							}
							$hour_str .= '</select>';
							$html .= $hour_str;
							// Hours - End }

							// Minutes - Start {
							$min_str = '&nbsp;<select name="'.$name.'[]" id="'.$id.'">';
							for($counter = -1; $counter < 60; $counter++)
							{
								if($counter === -1) {
									$min_str .= '<option value="">'.__('Minutes').'</option>';
									continue;
								}

								$min_str .= '<option value="'.sprintf('%02d', $counter).'">'.sprintf('%02d', $counter).'</option>';
							}
							$min_str .= '</select>';
							$html .= $min_str;
							// Minutes - End }
							break;
					}
					$html .= '</p>';
				}
			}
		}

		if ($data['allow_unsubscribe']) {
			$html .= '
				<p>
					<input type="radio" id="opActionFieldSubscribe' . $widgetSessionKey . '" name="subscribe_action" value="subscribe" checked="checked" />
					<label for="opActionFieldSubscribe' . $widgetSessionKey . '">' . __('Subscribe') . '</label>
					<br />
					<input type="radio" id="opActionFieldUnsubscribe' . $widgetSessionKey . '" name="subscribe_action" value="unsubscribe" />
					<label for="opActionFieldUnsubscribe' . $widgetSessionKey . '">' . __('Unsubscribe') . '</label>
				</p>
				';
		}

		$html .= '<div class="customFieldsContainer">';
		$html .= '</div>';
		$html .= '
					<p>
						<input type="submit" id="opSubmitSubscription' . $widgetSessionKey . '" value="' . __('Submit') . '">
					</p>
				</div>
			</form>
			';

		return sprintf("%s\n", $html);
	}


	public static function ts_frontend_cb()
	{
		if (!defined('TOTALSEND_SUBSCRIBE') || !is_array($_POST) || !isset($_POST['email']) || !isset($_POST['target_list']))
		{
			die('Bypass Attempt');
		}
		else
		{
			$TSSubscribeDispatcher = new TotalSendSubscribeDispatcher();
			$TSSubscribeDispatcher->setEmail($_POST['email']);
			$TSSubscribeDispatcher->setTargetList($_POST['target_list']);

			if (isset($_POST['custom_fields'])) {
				$TSSubscribeDispatcher->setCustomFields($_POST['custom_fields']);
            }

			if (isset($_POST['subscribe_action']) && ('unsubscribe' == $_POST['subscribe_action'])) {
				$TSSubscribeDispatcher->unsubscribe();
            }
			else {
				$TSSubscribeDispatcher->subscribe();
            }
		}
	}
}

// ============================================================================
class TotalSendSubscribeDispatcher
{
	private static $option_name = 'totalsend-subs';
	private $_response;
	private $_email;
	private $_options;
	private $_SessionID;
	private $_IpAddress;
	private $_successMessages;
	private $_subscriptionErrors;
	private $_unsubscriptionErrors;
	private $_targetListID;
	private $_customFields;

	/**
	 * Populate and map saved options. Including overwrite for standard error message
	 * according to plugin settings.
	 *
	 * @param array $testData
	 * @return mixed
	 */
	public function init($testData = array())
	{
		global $TotalSendPluginOptions;
		$options = array_keys($TotalSendPluginOptions);
		$storedOptions = get_option(self::$option_name);

		if (!count($testData)) {;
			foreach ($options as $optionCode) {
				if (false !== strpos($optionCode, 'unsubscription_error')) {
					if( $val = trim($storedOptions[$optionCode]) ) {
						$nmbr = (int)substr($optionCode, -1, 1);
						$this->_unsubscriptionErrors[$nmbr] = $val;
					}
				} elseif (false !== strpos($optionCode, 'subscription_error')) {
					if( $val = trim($storedOptions[$optionCode]) ) {
						$nmbr = (int)substr($optionCode, -1, 1);
						$this->_subscriptionErrors[$nmbr] = $val;
					}
				} elseif (false !== strpos($optionCode, 'success')) {
					if( $val = trim($storedOptions[$optionCode]) ) {
						$key = substr($optionCode, 3);
						$this->_successMessages[$key] = $val;
					}
				} else {
					$key = substr($optionCode, 3);
					$this->_options[$key] = $storedOptions[$optionCode];
				}
			}
		} else {
			foreach ($testData as $optionCode => $testVal) {
				if (false !== strpos($optionCode, 'unsubscription_error')) {
					if ($val = trim($testVal)) {
						$nmbr = (int)substr($optionCode, -1, 1);
						$this->_unsubscriptionErrors[$nmbr] = $val;
					}
				} elseif (false !== strpos($optionCode, 'subscription_error')) {
					if ($val = trim($testVal)) {
						$nmbr = (int)substr($optionCode, -1, 1);
						$this->_subscriptionErrors[$nmbr] = $val;
					}
				} elseif (false !== strpos($optionCode, 'success')) {
					if ($val = trim($testVal)) {
						$key = substr($optionCode, 3);
						$this->_successMessages[$key] = $val;
					}
				} elseif ($optionCode == 'listId') {
					$key = $optionCode;
					$this->_options[$key] = $testVal;
				} else {
					$key = substr($optionCode, 3);
					$this->_options[$key] = $testVal;
				}
			}
		}

		$this->_options['api_url'] = rtrim($storedOptions['ts_account_url'], '/') . '/api.php?';
		return $this->_options;
	}

	public function __construct()
	{
		$this->_response = array(
			'msg' => '',
			'success' => false
		);
		$this->_SessionID = null;
		$this->_IpAddress = $this->_getIpAddress();
		$this->_successMessages = array(
			'subscription_success' => __('Successfully subscribed'),
			'subscription_success_pending' => __('Please check your inbox to confirm subscription'),
			'unsubscription_success' => __('Successfully unsubscribed')
		);
		$this->_subscriptionErrors = array(
			'2' => __('Email address is missing'),
			'5' => __('Invalid email address format'),
			'9' => __('Email address already exists in the target list'),
		);
		$this->_unsubscriptionErrors = array(
			'3' => __('Email address must be provided'),
			'6' => __('Invalid email address format'),
			'7' => __('Email address doesn\'t exist in the list'),
			'9' => __('Email address already unsubscribed'),
		);

		$this->_customFields = array();
	}

	private function _getIpAddress()
	{
		if (isset($_SERVER)) {
			if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
				$ip_addr = $_SERVER["HTTP_X_FORWARDED_FOR"];
			} elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
				$ip_addr = $_SERVER["HTTP_CLIENT_IP"];
			} else {
				$ip_addr = $_SERVER["REMOTE_ADDR"];
			}
		} else {
			if (getenv('HTTP_X_FORWARDED_FOR')) {
				$ip_addr = getenv('HTTP_X_FORWARDED_FOR');
			} elseif (getenv('HTTP_CLIENT_IP')) {
				$ip_addr = getenv('HTTP_CLIENT_IP');
			} else {
				$ip_addr = getenv('REMOTE_ADDR');
			}
		}
		return $ip_addr;
	}

	public function setEmail($email)
	{
		if (!$this->_email = $this->_validateEmail($email)) {
			$this->_setResponse($this->_subscriptionErrors[5]);
			$this->_sendResponse();
		}
		return $this->_email;
	}

	public function setTargetList($listId)
	{
		return $this->_targetListID = $listId;
	}

	public function setCustomFields($customFields = array())
	{
		return $this->_customFields = $customFields;
	}

	public function subscribe()
	{
		if (!$this->_email) {
			$this->_setResponse($this->_subscriptionErrors[7]);
			$this->_sendResponse();
		}

		$this->init();
		try {
			$params = array(
				'ListID' => $this->_targetListID,
				'EmailAddress' => $this->_email,
				'IPAddress' => $this->_IpAddress
			);
			if (count($this->_customFields)) {
				foreach ($this->_customFields as $key => $val) {
					$params[$key] = $val;
				}
			}

			$response = $this->_getResponse($this->_getCommandUrl('Subscriber.Subscribe', $params));

			if ($response['Success']) {
				if ('Subscribed' == $response['Subscriber']['SubscriptionStatus'])
					$this->_setResponse($this->_successMessages['subscription_success'], true);
				elseif ('Confirmation Pending' == $response['Subscriber']['SubscriptionStatus'])
					$this->_setResponse($this->_successMessages['subscription_success_pending'], true);
			} else {
				if(is_array($response['ErrorCode']) && count($response['ErrorCode'])) {
					// we need to show up only first problem which we know
					foreach ($response['ErrorCode'] as $errorCode) {
						if (isset($this->_subscriptionErrors[$errorCode]))
							$this->_setResponse($this->_subscriptionErrors[$errorCode]);
					}
				} else {
					$this->_setResponse($this->_subscriptionErrors[$response['ErrorCode']]);
				}
			}
		} catch (Exception $e) {
			$this->_setResponse($e);
		}
		$this->_sendResponse();
	}

	public function unsubscribe()
	{
		if (!$this->_email) {
			$this->_setResponse($this->_subscriptionErrors[7]);
			$this->_sendResponse();
		}

		$this->init();
		try {
			$response = $this->_getResponse($this->_getCommandUrl('Subscriber.Unsubscribe',
				array(
					'ListID' => $this->_targetListID,
					'EmailAddress' => $this->_email,
					'IPAddress' => $this->_IpAddress
				)
			));
			if ($response['Success']) {
				$this->_setResponse($this->_successMessages['unsubscription_success'], true);
			} else {
				if(is_array($response['ErrorCode']) && count($response['ErrorCode'])) {
					// we need to show up only first problem which we know
					foreach ($response['ErrorCode'] as $errorCode) {
						if (isset($this->_subscriptionErrors[$errorCode]))
							$this->_setResponse($this->_unsubscriptionErrors[$errorCode]);
					}
				} else {
					$this->_setResponse($this->_subscriptionErrors[$response['ErrorCode']]);
				}
			}
		} catch (Exception $e) {
			$this->_setResponse($e);
		}
		$this->_sendResponse();
	}


	private function _getResponse($url)
	{
		$results = null;
		$parts = parse_url($url);
		parse_str($parts['query'], $fields);

		if(isset($fields['Command']) && $fields['Command'] != 'User.Login')
		{
			$results = file_get_contents($url);
		}
		else
		{
			$url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

			// Get cURL resource
			$curl = curl_init();

			// Set some options
			curl_setopt_array($curl, array(
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_URL => $url,
				CURLOPT_POST => count($fields),
				CURLOPT_POSTFIELDS => http_build_query($fields),
				CURLOPT_SSL_VERIFYPEER => false,
			));

			// Send the request & save response to $resp
			$results = curl_exec($curl);

			if(!$results){
				die('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
			}

			// Close request to clear up some resources
			curl_close($curl);
		}

		$json_results = json_decode($results, true);
		return $json_results;
	}

	private function _getCommandUrl($command, $params = array())
	{

		if ($command == 'Subscriber.Subscribe' || $command == 'Subscriber.Unsubscribe') {

			$url = $this->_options['api_url'] . sprintf(
					'Command=%s&ResponseFormat=JSON',
					$command
				);

		} else {

			if (!$this->_SessionID) {
				$url = $this->_options['api_url'] . sprintf(
						'Command=User.Login&Username=%s&Password=%s&ResponseFormat=JSON',
						$this->_options['login'],
						$this->_options['password']
					);
				$response = $this->_getResponse($url);
				if (true == $response['Success'])
					$this->_SessionID = $response['SessionID'];
				else {
					$this->_setResponse(__(serialize($url) . 'TotalSend credentials are incorrect'));
					$this->_sendResponse();
				}
			}

			$url = $this->_options['api_url'] . sprintf(
					'Command=%s&SessionID=%s&ResponseFormat=JSON',
					$command,
					$this->_SessionID
				);

		}

		if (count($params)) {
			foreach ($params as $paramKey => $val) {
				if (!empty($val))
					if (!is_array($val)) {
						$url .= sprintf('&%s=%s', $paramKey, htmlentities(urlencode($val)));
					} else {
						foreach ($val as $valEl) {
							$url .= sprintf('&%s=%s', $paramKey . '[]', htmlentities(urlencode($valEl)));
						}

					}
			}
		}
		return $url;
	}

	private function _validateEmail($email)
	{
		return is_email(trim($email));
	}

	private function _setResponse($msg, $success = false)
	{
		$this->_response['msg'] = $msg;
		$this->_response['success'] = $success;
	}

	private function _sendResponse()
	{
		die(json_encode($this->_response));
	}


	public function getSubscriberLists()
	{
		return $this->_getResponse($this->_getCommandUrl('Lists.Get', array('OrderField' => 'Name', 'OrderType' => 'ASC')));
	}

	public function getConnection()
	{
		$url = $this->_options['api_url'] . sprintf(
				'Command=User.Login&Username=%s&Password=%s&ResponseFormat=JSON',
				$this->_options['login'],
				$this->_options['password']
			);

		$response = $this->_getResponse($url);
		return (true === $response['Success']) ? true : false;
	}

	public function getSubscriberListFields($listId)
	{
		$result = array();

		$this->init();
		$this->setTargetList($listId);
		try {
			$params = array(
				'SubscriberListID' => $this->_targetListID,
				'OrderField' => 'CustomFieldID',
				'OrderType' => 'ASC',
			);
			$response = $this->_getResponse($this->_getCommandUrl('CustomFields.Get', $params));
			if ($response['Success']) {
				$result = $response['CustomFields'];
			}
		} catch (Exception $e) {
			// Nothing to do here.
		}
		return $result;
	}
}
// ============================================================================
