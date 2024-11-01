<?php
/**
 * Plugin Name: WooCurrency
 * Description: Allow products selling in multiple currencies.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 1.0.2
 * License: Copyright LongWatchStudio 2018
 * Text Domain: woocurrency
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.4.2
 *
 * Copyright (c) 2018 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 *
 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once dirname(__FILE__) . '/assets/tgm-with-merge.php';


final class WooCurrency
{

	public static function init()
	{
		static $instance = false;
		if( !$instance )
		{
			$instance = new self();
			$instance->defineConstants();
			$instance->load_plugin_textdomain();

			add_action( 'tgmpa_register', array($instance, 'registerRequiredPlugins'), PHP_INT_MAX );
			add_action( 'lws_adminpanel_register', array($instance, 'admin') );
			add_action( 'lws_adminpanel_plugins', array($instance, 'plugin') );
			add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), array($instance, 'extensionListActions'), 10, 2 );
			add_filter( 'plugin_row_meta', array($instance, 'addLicenceLink'), 10, 4 );
			add_filter( 'lws_adminpanel_purchase_url_woocurrency', array($instance, 'addPurchaseUrl'), 10, 1 );
			add_filter( 'lws_adminpanel_plugin_version_woocurrency', array($instance, 'addPluginVersion'), 10, 1 );
			add_filter( 'lws_adminpanel_documentation_url_woocurrency', array($instance, 'addDocUrl'), 10, 1 );

			$instance->install();
		}
		return $instance;
	}

	public function v()
	{
		static $version = '';
		if( empty($version) ){
			if( !function_exists('get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data(__FILE__, false);
			$version = (isset($data['Version']) ? $data['Version'] : '0');
		}
		return $version;
	}

	/** Load translation file
	 * If called via a hook like this
	 * @code
	 * add_action( 'plugins_loaded', array($instance,'load_plugin_textdomain'), 1 );
	 * @endcode
	 * Take care no text is translated before. */
	function load_plugin_textdomain() {
		load_plugin_textdomain( LWS_WOOCURRENCY_DOMAIN, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	public function plugin()
	{
		lws_register_update(__FILE__, null, md5(\get_class() . __FUNCTION__));
		$activated = lws_require_activation(__FILE__, null, null, md5(\get_class() . __FUNCTION__));
		lws_extension_showcase(__FILE__);
		define( 'LWS_WOOCURRENCY_ACTIVATED', $activated );
	}

	public function extensionListActions($links, $file)
	{
		$label = __('Settings'); // use standart wp sentence, no text domain
		$url = add_query_arg(array('page'=>LWS_WOOCURRENCY_DOMAIN), admin_url('admin.php'));
		array_unshift($links, "<a href='$url'>$label</a>");
		$label = __('Help'); // use standart wp sentence, no text domain
		$url = esc_attr($this->addDocUrl(''));
		$links[] = "<a href='$url'>$label</a>";
		return $links;
	}

	public function addLicenceLink($links, $file, $data, $status)
	{
		if( (!defined('LWS_WOOCURRENCY_ACTIVATED') || !LWS_WOOCURRENCY_ACTIVATED) && plugin_basename(__FILE__)==$file)
		{
			$label = __('Add Licence Key', LWS_WOOCURRENCY_DOMAIN);
			$url = add_query_arg(array('page'=>LWS_WOOCURRENCY_DOMAIN, 'tab'=>'license'), admin_url('admin.php'));
			$links[] = "<a href='$url'>$label</a>";
		}
		return $links;
	}

	public function addDocUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/en/documentation-en/woocurrency/", LWS_WOOCURRENCY_DOMAIN);
	}

	public function addPurchaseUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/en/product/woocurrency-en/", LWS_WOOCURRENCY_DOMAIN);
	}

	public function addPluginVersion($url)
	{
		return $this->v();
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define( 'LWS_WOOCURRENCY_VERSION', $this->v() );
		define( 'LWS_WOOCURRENCY_FILE', __FILE__ );
		define( 'LWS_WOOCURRENCY_DOMAIN', 'woocurrency' );

		define( 'LWS_WOOCURRENCY_PATH', dirname( LWS_WOOCURRENCY_FILE ) );
		define( 'LWS_WOOCURRENCY_INCLUDES', LWS_WOOCURRENCY_PATH . '/include' );
		define( 'LWS_WOOCURRENCY_ASSETS', LWS_WOOCURRENCY_PATH . '/assets' );

		define( 'LWS_WOOCURRENCY_URL', plugins_url( '', LWS_WOOCURRENCY_FILE ) );
		define( 'LWS_WOOCURRENCY_JS', plugins_url( '/js', LWS_WOOCURRENCY_FILE ) );
		define( 'LWS_WOOCURRENCY_CSS', plugins_url( '/css', LWS_WOOCURRENCY_FILE ) );
	}

	function admin()
	{
		if( function_exists('get_woocommerce_currency_symbol') ) // WooCommerce must be activated first
		{
			require_once LWS_WOOCURRENCY_INCLUDES . '/admin.php';
			new \LWS\WOOCURRENCY\Admin();
		}
	}

	private function install()
	{
		require_once LWS_WOOCURRENCY_INCLUDES . '/priceoverride.php';
		new \LWS\WOOCURRENCY\PriceOverride();

		require_once LWS_WOOCURRENCY_INCLUDES . '/geoloc.php';
		\LWS\WOOCURRENCY\Geoloc::instance()->install();
	}

	/** @see http://tgmpluginactivation.com/configuration/ */
	function registerRequiredPlugins()
	{
		$plugins = array(
			array(
				'name'		=> 'WooCommerce',
				'slug'		=> 'woocommerce',
				'required'=> true,
				'version'	=> '3.0.0',
				'force_activation'	=> true,
			)
		);

		$config = array(
			'id'           => LWS_WOOCURRENCY_DOMAIN,
			'default_path' => '',
			'parent_slug'  => 'plugins.php',
			'capability'   => 'activate_plugins',
			'has_notices'  => true,
			'dismissable'  => false,
			'dismiss_msg'  => tgm_hack_the7_script(),
			'is_automatic' => true,                   // Automatically activate plugins after installation or not.
			'strings'      => array(
				'page_title'                      => __( 'Install Required Plugins Dependencies', LWS_WOOCURRENCY_DOMAIN ),
				'menu_title'                      => __( 'Install Dependencies', LWS_WOOCURRENCY_DOMAIN ),
				'notice_can_install_required'     => _n_noop(
					'This plugin requires the following plugin: %1$s.',
					'This plugin requires the following plugins: %1$s.',
					LWS_WOOCURRENCY_DOMAIN
				),
				'notice_can_install_recommended'  => _n_noop(
					'This plugin recommends the following plugin: %1$s.',
					'This plugin recommends the following plugins: %1$s.',
					LWS_WOOCURRENCY_DOMAIN
				)
			)
		);
		if( function_exists('tgmpa') )
			tgmpa( $plugins, $config );
	}
}

@include_once dirname(__FILE__) . '/assets/lws-adminpanel/lws-adminpanel.php';
@include_once dirname(__FILE__) . '/modules/woocurrency-pro/woocurrency-pro.php';

WooCurrency::init();

?>
