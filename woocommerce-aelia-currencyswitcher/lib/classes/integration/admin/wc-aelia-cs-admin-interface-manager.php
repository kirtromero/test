<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Handles the rendering of the elements of the Admin interface.
 */
class WC_Aelia_CS_Admin_Interface_Manager {
	protected $admin_views_path;

	/**
	 * Returns the currency of the order currently being displayed.
	 *
	 * @return string
	 */
	protected function displayed_order_currency() {
		global $post;

		$post_id = get_value('ID', $post);
		$order = new Aelia_Order($post_id);

		return $order->get_order_currency();
	}

	/**
	 * Adds meta boxes to the admin interface.
	 *
	 * @see add_meta_boxes().
	 */
	public function add_meta_boxes() {
		add_meta_box('aelia_cs_order_currency_box',
								 __('Order currency', AELIA_CS_PLUGIN_TEXTDOMAIN),
								 array($this, 'render_currency_selector_widget'),
								 'shop_order',
								 'side',
								 'default');
	}

	/**
	 * Renders the currency selector widget in "new order" page.
	 */
	public function render_currency_selector_widget() {
		$order_currency = $this->displayed_order_currency();

		if(empty($order_currency)) {
			echo '<p>';
			echo __('Set currency for this new order. It is recommended to choose ' .
							'the order currency <b>before</b> adding the products, as changing ' .
							'it later will not update the product prices.',
							AELIA_CS_PLUGIN_TEXTDOMAIN);
			echo '</p>';
			echo '<p>';
			echo __('<b>NOTE</b>: you can only select the currency <b>once</b>. If ' .
							'you choose the wrong currency, please discard the order and ' .
							'create a new one.',
							AELIA_CS_PLUGIN_TEXTDOMAIN);
			echo '</p>';
			$currency_selector_options = array(
				'title' => '',
				'widget_type' => 'dropdown',
			);

			echo WC_Aelia_CurrencySwitcher_Widget::render_currency_selector($currency_selector_options);
		}
		else {
			// Prepare the text to use to display the order currency
			$order_currency_text = $order_currency;

			$currency_name = WC_Aelia_Currencies_Manager::get_currency_name($order_currency);
			// If a currency name is returned, append it to the code for displau.
			// If a currency name cannot be found, the method will return the currency
			// code itself. In such case, there would be no point in displaying the
			// code twice.
			if($currency_name != $order_currency) {
				$order_currency_text .= ' - ' . $currency_name;
			}

			echo '<h4 class="order-currency">';
			echo $order_currency_text;
			echo '</h4>';
		}
	}

	/**
	 * Displays additional data in the "orders list" page.
	 *
	 * @param string column The column being displayed.
	 */
	public function manage_shop_order_posts_custom_column($column) {
		global $post, $woocommerce;

		// Use the Aelia_Order class, which provides additional methods
		$order = new Aelia_Order($post->ID);

		switch($column) {
			case 'order_total':
			case 'total_cost':
				$base_currency = WC_Aelia_CurrencySwitcher::settings()->base_currency();

				/* If order is not in base currency, display order total in base currency
				 * before the one in order currency. It's not possible to display it after,
				 * because WooCommerce core simply outputs the information and it's not
				 * possible to modify it.
				 */
				if($order->get_order_currency() != $base_currency) {
					$order_total_base_currency =  WC_Aelia_CurrencySwitcher::instance()->format_price(
						$order->get_total_in_base_currency(),
						$base_currency
					);
					echo '<div class="order_total_base_currency" title="' .
							 __('Order total in base currency (estimated)', AELIA_CS_PLUGIN_TEXTDOMAIN) .
							 '">';
					echo '(' . esc_html(strip_tags($order_total_base_currency)) . ')';
					echo '</div>';
				}

			break;
		}
	}

	/**
	 * Overrides the active currency, depending on the Admin page being rendered.
	 *
	 * @param string currency The currency passed to the filter.
	 * @return string
	 */
	public function woocommerce_currency($currency) {
		if(is_admin() && !defined('DOING_AJAX') && function_exists('get_current_screen')) {
			$screen = get_current_screen();

			// WooCommerce 2.1
			// When viewing an existing order, override the currency and force it to the
			// currency in which the order was placed
			if($screen->base == 'post') {
				global $post;

				if(get_value('post_type', $post) == 'shop_order') {
					$order = new Aelia_Order($post->ID);
					$order_currency = $order->get_order_currency();

					// New orders don't have a currency associated to them until they are
					// saved. In such case, keep using the active one
					if(!empty($order_currency)) {
						$currency = $order_currency;
					}
				}
			}
		}
		return $currency;
	}

	/**
	 * Sets the hooks required by the class.
	 */
	protected function set_hooks() {
		global $post;

		add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
		add_action('manage_shop_order_posts_custom_column', array($this, 'manage_shop_order_posts_custom_column'), 1);
		add_filter('woocommerce_currency', array($this, 'woocommerce_currency'), 20, 1);
	}

	/**
	 * Loads (includes) a View file.
	 *
	 * @param string view_file_name The name of the view file to include.
	 */
	protected function load_view($view_file_name) {
		$file_to_load = $this->get_view($view_file_name);
		include($file_to_load);
	}

	/**
	 * Retrieves an admin view.
	 *
	 * @param string The view file name (without path).
	 * @return string
	 */
	protected function get_view($view_file_name) {
		return $this->admin_views_path . '/' . $view_file_name;
	}

	/* WC Reports assume Order Totals to be in base currency and simply sum them
	 * together. This is incorrect when Currency Switcher is installed, as each
	 * order total is saved in the currency in which the transaction was completed.
	 * It's therefore necessary, during reporting, to convert all order totals into
	 * the base currency.
	 */
	public function __construct() {
		global $wpdb;
		//$wpdb->show_errors();

		// TODO Determine Views Path dynamically, depending on WooCommerce version
		$this->admin_views_path = AELIA_CS_VIEWS_PATH . '/admin/wc20/reports';

		$this->set_hooks();
	}
}
