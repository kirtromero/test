<?php
namespace Aelia\CurrencySwitcher;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \WP_Widget;
use \WC_Aelia_CurrencySwitcher;
/**
 * Displays the billing country selector widget.
 */
class Billing_Country_Selector_Widget extends WP_Widget {
	protected $text_domain;

	// Widget types
	const TYPE_DROPDOWN = 'dropdown';
	const TYPE_BUTTONS = 'buttons';

	protected function WC() {
		global $woocommerce;
		return $woocommerce;
	}

	/**
	 * Returns a list of the available widget types and their attributes.
	 *
	 * @return array
	 */
	protected function widget_types() {
		$widget_types = array(
			self::TYPE_DROPDOWN => array(
				'name' => __('Dropdown', $this->text_domain),
				'template' => 'billing-country-selector-widget-dropdown',
				'title' => __('Displays a dropdown with all enabled countries', $this->text_domain),
			),
		);

		$widget_types = apply_filters('wc_aelia_cs_billing_country_selector_widget_types', $widget_types);
		return $widget_types;
	}

	/**
	 * Retrieves the template that will be used to render the widget.
	 *
	 * @param string template_type The template type.
	 * @return string
	 */
	protected function get_widget_template($template_type) {
		$widget_types = $this->widget_types();
		$type_info = get_value($template_type, $widget_types);
		// If an invalid type is passed, default to a dropdown widget
		if(empty($type_info)) {
			$type_info = get_value(self::TYPE_DROPDOWN, $this->widget_types());
		}

		return $type_info['template'];
	}

	/**
	 *	Class constructor.
	 */
	public function __construct() {
		$this->text_domain = WC_Aelia_CurrencySwitcher::$text_domain;

		parent::__construct(
			'wc_aelia_cs_billing_country_selector_widget',
			'WooCommerce Currency Switcher - Billing Country Selector',
			array('description' => __('Allow to switch billing country on the fly and ' .
																'set the active currency accordingly.', $this->text_domain),)
		);
	}

	/**
	 * Loads the CSS files required by the Widget.
	 */
	private function load_css() {
	}

	/**
	 * Loads the JavaScript files required by the Widget.
	 */
	private function load_js() {
		wp_enqueue_script('wc-aelia-currency-switcher-widget');
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $widget_args Widget arguments.
	 * @param array $instance Saved values from database.
	 * @see WP_Widget::widget()
	 */
	public function widget($widget_args, $instance = array()) {
		$this->load_css();
		$this->load_js();

		if(!is_array($widget_args)) {
			$widget_args = array();
		}

		$widget_args = array_merge(
			$instance,
			$widget_args
		);

		$widget_type = get_value('widget_type', $widget_args, self::TYPE_DROPDOWN);
		$widget_template_name = $this->get_widget_template($widget_type);
		$widget_template_file = WC_Aelia_CurrencySwitcher::instance()->get_template_file($widget_template_name);
		// Debug
		//var_dump($widget_template_name, $widget_template_file);

		if(empty($widget_template_file)) {
			$this->display_invalid_widget_type_error($widget_type);
		}
		else {
			$widget_args['title'] = apply_filters('wc_aelia_cs_billing_country_selector_widget_title', get_value('title', $widget_args));
			$widget_args['countries'] = apply_filters('wc_aelia_cs_billing_country_selector_widget_countries',
																								$this->WC()->countries->get_allowed_countries());

			$selected_country = get_value('selected_country', $widget_args);
			if(empty($selected_country)) {
				$widget_args['selected_country'] = WC_Aelia_CurrencySwitcher::instance()->get_billing_country();
			}

			// Display the Widget
			include $widget_template_file;
		}
	}

	/**
	 * If an invalid widget type has been set, display an error so that the site
	 * owner is aware of it.
	 *
	 * @param string widget_type The invalid widget type.
	 */
	protected function display_invalid_widget_type_error($widget_type) {
		echo '<div class="error">';
		echo '<h5 class="title">' . __('Error', $this->text_domain) . '</h5>';
		echo sprintf(__('The billing country selector widget has not been configured properly. A template for ' .
										'wiget type "%s" could not be found. Please review the widget settings and ensure ' .
										'that a valid widget type has been selected. If the issue persists, please ' .
										'<a href="https://aelia.freshdesk.com/helpdesk/tickets/new" title="Contact support">' .
										'contact support</a>.', $this->text_domain),
								 $widget_type);
		echo '</div>';
	}

	/**
	 * Renders Widget's form in Admin section.
	 *
	 * @param array instance Widgets settings passed when submitting the form.
	 */
 	public function form($instance) {
		$title_field_id = $this->get_field_id('title');
		$title_field_name = $this->get_field_name('title');

		echo '<p>';
		echo '<label for="' . $title_field_id . '">' . _e('Title:', $this->text_domain) . '</label>';
		echo '<input type="text" class="widefat" id="' . esc_attr($title_field_id) . '" ' .
				 'name="' . $title_field_name . '" ' .
				 'value="' . esc_attr(get_value('title', $instance, '')) . '" />';
		echo '</p>';

		$widget_type_field_id = $this->get_field_id('widget_type');
		$widget_type_field_name = $this->get_field_name('widget_type');

		echo '<p>';
		echo '<label for="' . $widget_type_field_id . '">' . _e('Widget type:', $this->text_domain) . '</label>';
		echo '<select class="widefat" id="' . esc_attr($widget_type_field_id) . '" ' .
				 'name="' . $widget_type_field_name . '">';
		foreach($this->widget_types() as $type_id => $type_info) {
			$selected_attr = '';
			if(esc_attr(get_value('widget_type', $instance, '')) == $type_id) {
				$selected_attr = 'selected="selected"';
			}

			echo '<option value="' . $type_id . '" title="' . $type_info['title'] . '" ' . $selected_attr . '>';
			echo $type_info['name'];
			echo '</option>';
		}
		echo '</select>';
		echo '</p>';
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 * @return array Updated safe values to be saved.
	 * @see WP_Widget::update()
	 */
	public function update($new_instance, $old_instance) {
		$instance = array();
		$instance['title'] = strip_tags(stripslashes($new_instance['title']));
		$instance['widget_type'] = strip_tags(stripslashes($new_instance['widget_type']));

		return $instance;
	}

	/**
	 * Renders the billing country selector widget when invoked using a shortcode.
	 *
	 * @param array widget_args An array of arguments for the widget.
	 * @return string
	 */
	public static function render_billing_country_selector($widget_args) {
		ob_start();

		$class = get_called_class();
		$widget_instance = new $class();
		$widget_instance->widget($widget_args);

		$output = ob_get_contents();
		@ob_end_clean();

		return $output;
	}
}
