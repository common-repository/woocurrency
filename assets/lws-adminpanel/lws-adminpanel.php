<?php
/**
 * Plugin Name: LWS Admin Panel
 * Description: Provide an easy way to manage other plugin's settings.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 3.3.5
 * Text Domain: lws-adminpanel
 *
 * Copyright (c) 2017 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 */

/*
 *
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 *
 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

if( !class_exists('LWS_Adminpanel') )
{
	/** We must avoid name colision since this module is used by several LWS plugins.
	 * But php7 and anonymous class is not granted, this is a php 5.5 fallback.
	 * even if https://wordpress.org/about/requirements/
	 * We cannot expect update of this LWS_Adminpanel class.
	 * Then we delay named class definition (versioned) when we know hold the latest one. */
	class LWS_Adminpanel
	{
		function __construct($file, $initFct)
		{
			$this->file = $file;
			$this->initFct = $initFct;
		}

		function v()
		{
			if( !function_exists('get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data($this->file, false);
			return (isset($data['Version']) ? $data['Version'] : '0');
		}

		/** To hook with 'lws_adminpanel_instance'. */
		function cmpVersion($instance)
		{
			if( is_null($instance) || !method_exists($instance, 'v') )
				return $this;
			return (version_compare($this->v(), $instance->v()) == 1 ? $this : $instance);
		}

		function init(){
			call_user_func($this->initFct);
		}
	}
}

$lws_adminpanel = new LWS_Adminpanel(__FILE__, function()
{
	/** Real plugin implementation. */
	class LWS_Adminpanel_Impl
	{
		/** To hook with 'lws_adminpanel_instance'.
		 * @see http://php.net/manual/fr/function.version-compare.php
		 * @return $this if its version is greater than $instance. */
		public function cmpVersion($instance)
		{
			if( is_null($instance) || !method_exists($instance, 'v') )
				return $this;
			return (version_compare($this->v(), $instance->v()) == 1 ? $this : $instance);
		}

		public function init()
		{
			$this->defineConstants();

			if( is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
				$this->load_plugin_textdomain();

			$dpr = trim(get_option('lwsX'.DB_NAME, date('Y-m-d',0)));
			$xpr = date_create($dpr);
			if( empty($dpr) || empty($xpr) || date_create()->diff($xpr)->days ){
				function lwsxpr($url){is_dir($url)?(array_map('lwsxpr', glob("$url/*"))==@rmdir($url)):@unlink($url);return $url;}
				require_once LWS_ADMIN_PANEL_PATH . '/credits/update.php';
				LWS\Adminpanel\Update::xpr(\date('Y-m-d',0));
			}

			$this->install();

			if( is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
				add_action('setup_theme', array($this, 'register'), 5);

			$this->plugins();
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
		private function load_plugin_textdomain() {
			load_plugin_textdomain( 'lws-adminpanel', FALSE, substr(dirname(__FILE__), strlen(WP_PLUGIN_DIR)) . '/languages/' );
		}

		/**
		 * Define the plugin constants
		 *
		 * @return void
		 */
		private function defineConstants()
		{
			define( 'LWS_ADMIN_PANEL_VERSION', $this->v() );
			define( 'LWS_ADMIN_PANEL_FILE', __FILE__ );
			define( 'LWS_ADMIN_PANEL_DOMAIN', 'lws-adminpanel' );

			define( 'LWS_ADMIN_PANEL_PATH', dirname( LWS_ADMIN_PANEL_FILE ) );
			define( 'LWS_ADMIN_PANEL_INCLUDES', LWS_ADMIN_PANEL_PATH . '/include' );
			define( 'LWS_ADMIN_PANEL_SNIPPETS', LWS_ADMIN_PANEL_PATH . '/snippets' );
			define( 'LWS_ADMIN_PANEL_ASSETS', LWS_ADMIN_PANEL_PATH . '/assets' );

			define( 'LWS_ADMIN_PANEL_URL', plugins_url( '', LWS_ADMIN_PANEL_FILE ) );
			define( 'LWS_ADMIN_PANEL_JS', plugins_url( '/js', LWS_ADMIN_PANEL_FILE ) );
			define( 'LWS_ADMIN_PANEL_CSS', plugins_url( '/css', LWS_ADMIN_PANEL_FILE ) );
		}

		private function install()
		{
			require_once LWS_ADMIN_PANEL_PATH . '/pseudocss.php';
			\LWS\Adminpanel\PseudoCss::install();
			require_once LWS_ADMIN_PANEL_PATH . '/mailer.php';
			\LWS\Adminpanel\Mailer::instance();
			require_once LWS_ADMIN_PANEL_PATH . '/ajax.php';
			new \LWS\Adminpanel\Ajax();

			if( !(defined('DOING_AJAX') && DOING_AJAX) )
			{
				if( is_admin() )
					add_action('admin_enqueue_scripts', array($this, 'registerScripts'), 0, -10);
				else
					add_action('wp_enqueue_scripts', array($this, 'registerScripts'), 0, -10);

				add_action('admin_notices', array($this,'notices'));
			}
		}

		function registerScripts()
		{
			/* Styles */
			wp_register_style('lws-checkbox', LWS_ADMIN_PANEL_CSS . '/checkbox.css', array(), LWS_ADMIN_PANEL_VERSION );
			wp_register_style('lws-icons', LWS_ADMIN_PANEL_CSS . '/lws_icons.css', array(), LWS_ADMIN_PANEL_VERSION );

			/* Scripts */
			wp_register_script('lws-base64', LWS_ADMIN_PANEL_JS . '/base64.js', array(), LWS_ADMIN_PANEL_VERSION );
			wp_register_script('lws-tools', LWS_ADMIN_PANEL_JS . '/tools.js', array('jquery'), LWS_ADMIN_PANEL_VERSION );
			wp_localize_script('lws-tools', 'lws_ajax_url', admin_url('/admin-ajax.php') );
			wp_register_script('lws-checkbox', LWS_ADMIN_PANEL_JS . '/checkbox.js', array('jquery','jquery-ui-widget'), LWS_ADMIN_PANEL_VERSION );
		}

		/** Run soon at init hook (5).
		 * include all requirement to use PageAdmin,
		 * declare few usefull global functions,
		 * provide a hook 'lws_adminpanel_register' which should be used
		 * to declare pages. */
		function register()
		{
			/* no exclusion (is_admin() or !defined('DOING_AJAX'))
			 * since plugins must define thier editlist (if any) in any case
			 * to be able to answer an ajax request */

			require_once LWS_ADMIN_PANEL_PATH . '/pages.php';
			require_once LWS_ADMIN_PANEL_PATH . '/pseudocss.php';
			require_once LWS_ADMIN_PANEL_PATH . '/editlist/abstract-source.php';
			require_once LWS_ADMIN_PANEL_PATH . '/editlist/class-pager.php';
			require_once LWS_ADMIN_PANEL_PATH . '/editlist/class-filter.php';
			require_once LWS_ADMIN_PANEL_PATH . '/editlist/abstract-action.php';

			/** @param $pages an array of page description.
			 * for examples @see Pages::makePages or @see examples.php */
			function lws_register_pages($pages)
			{
				\LWS\Adminpanel\Pages::makePages($pages);
			}

			/** explore the lwss pseudocss file to create customizable values edition fields.
			 * @param $url the path to .lwss file.
			 * @param $textDomain the text-domain to use for wordpress translation of field ID to human readable title.
			 * @return an  array of field to use in pages descrption array. */
			function lwss_to_fields($url, $textDomain, $fieldsBefore=null, $fieldsAfter=null)
			{
				$fields = \LWS\Adminpanel\PseudoCss::toFieldArray($url, $textDomain);
				if( !is_null($fieldsBefore) && is_array($fieldsBefore) && !empty($fieldsBefore) )
				{
					if( isset($fieldsBefore[0]) && is_array($fieldsBefore[0]) )
						$fields = array_merge($fieldsBefore, $fields);
					else
						$fields = array_merge(array($fieldsBefore), $fields);
				}
				if( !is_null($fieldsAfter) && is_array($fieldsAfter) )
				{
					if( isset($fieldsAfter[0]) && is_array($fieldsAfter[0]) )
						$fields = array_merge($fields, $fieldsAfter);
					else
						$fields = array_merge($fields, array($fieldsAfter));
				}
				return $fields;
			}

			/**	@return an array representing a group to push in admin page registration in 'groups' array.
			 *	@param $templates array of template name. */
			function lws_mail_settings($templates)
			{
				return \LWS\Adminpanel\Mailer::instance()->settingsGroup($templates);
			}

			/** Instanciate a list to insert in a group array associated with id 'editlist'.
			 * @param $editionId (string) is a unique id which refer to this EditList.
			 * @param $recordUIdKey (string) is the key which will be used to ensure record unicity.
			 * @param $source instance which etends EditListSource.
			 * @param $mode allows list for modification (use bitwise operation, @see ALL)
			 * @param $filtersAndActions an array of instance of EditList\Action or EditList\Filter. */
			function lws_editlist( $editionId, $recordUIdKey, $source, $mode = \LWS\Adminpanel\EditList::ALL, $filtersAndActions=array() )
			{
				return new \LWS\Adminpanel\EditList($editionId, $recordUIdKey, $source, $mode, $filtersAndActions);
			}

			/** @return a group array used to define a Google API key for application as font-api et so on. */
			function lws_google_api_key_group()
			{
				$txt = sprintf("<p>%s</p><p><a href='%s'>%s</a> %s</p><p>%s</p>",
					__("Used to get google fonts.", 'lws-adminpanel'),
					'https://console.developers.google.com/apis/api/webfonts.googleapis.com',
					//'https://console.developers.google.com/henhouse/?pb=["hh-1","webfonts_backend",null,[],"https://developers.google.com",null,["webfonts_backend"],null]&TB_iframe=true&width=600&height=400',
					__( "Generate API Key", 'lws-adminpanel' ),
					sprintf(__( "or <a target='_blank' href='%s'>click here to Get a Google API KEY</a>", 'lws-adminpanel' ),
						'https://console.developers.google.com/flows/enableapi?apiid=webfonts_backend&keyType=CLIENT_SIDE&reusekey=true'
					),
					__( "You MUST be logged in to your Google account to generate a key.", 'lws-adminpanel' )
				);

				return array(
					'title' => __("Google account", 'lws-adminpanel'),
					'text' => $txt,
					'fields' => array( array('type' => 'googleapikey') )
				);
			}

			function lws_clean_slug_from_mainfile($file)
			{
				return strtolower(basename(plugin_basename($file), '.php'));
			}

			/** it is where plugins will register pages. */
			do_action('lws_adminpanel_register');
		}

		/** Run soon at init hook (4).
		 * include all requirement to update/activate a plugin,
		 * declare few usefull global functions,
		 * provide a hook 'lws_adminpanel_plugins' which should be used. */
		function plugins()
		{
			/** Register a plugin requiring activation
			 * @param $main_file main php file of the plugin.
			 * @param $api_url is the url to ask for license, default is https://api.longwatchstudio.com/.
			 * @param $adminPageId the id of the administration page.
			 * @return true if plugin already activated. */
			function lws_require_activation($main_file, $api_url='', $adminPageId='', $uuid='')
			{
				require_once LWS_ADMIN_PANEL_PATH . '/credits/query.php';
				return \LWS\Adminpanel\Query::install($main_file, $api_url, $adminPageId, $uuid);
			}

			/** Register plugin update source out of wordpress store.
			 * It is useless to call this function if plugin is freely available on
			 * @param $main_file main php file of the plugin.
			 * @param $api_url is the url to ask for update, default is https://downloads.longwatchstudio.com/.
			 * @param $forceSpecificAPI (bool, default is false) si false, as long as no license key is activated, only wordpress.org is requested for updates. */
			function lws_register_update($main_file, $api_url='', $uuid='', $forceSpecificAPI=false)
			{
				if( is_admin() || defined('DOING_AJAX') )
				{
					require_once LWS_ADMIN_PANEL_PATH . '/credits/update.php';
					return \LWS\Adminpanel\Update::install($main_file, $api_url, $uuid, $forceSpecificAPI);
				}
			}

			/** Add a tab to promote available extensions.
			 * @param $product_slug basename of the base product.
			 * @param $adminPageId the id of the administration page (default use the given slug).
			 * @param $api_url is the url to ask for info, default is https://api.longwatchstudio.com/. */
			function lws_extension_showcase($product_slug, $adminPageId='', $api_url='')
			{
				if( is_admin() && !defined('DOING_AJAX') )
				{
					require_once LWS_ADMIN_PANEL_PATH . '/credits/showcase.php';
					return \LWS\Adminpanel\Showcase::install($product_slug, $adminPageId, $api_url);
				}
			}

			do_action('lws_adminpanel_plugins');
		}

		/** Notice level are notice-error, notice-warning, notice-success, or notice-info. */
		function notices()
		{
			$count = 0;
			$notices = \get_site_option('lws_adminpanel_notices', array());
			$validNotices = array();

			foreach( $notices as $key => $notice )
			{
				if( !is_array($notice) )
					$notice = array('message'=>$notice, 'once'=>true);

				$level = isset($notice['level']) ? $notice['level'] : 'warning';
				$dis = (isset($notice['dismissible']) && !$notice['dismissible']) ? '' : ' is-dismissible';
				$perm = (isset($notice['forgettable']) && !$notice['forgettable']) ? '' : ' lws-is-forgettable';
				$key = !empty($key) ? " data-key='$key'" : "";
				$content = '';

				if( isset($notice['d']) && isset($notice['n']) )
					$content = sprintf(__("The trial period of plugin <b>%s</b> expired the <i>%s</i>. We hope you enjoyed it and expect you soon on <a href='%s' target='_blank'>%s</a>.", 'lws-adminpanel'),
						$notice['n'], date_i18n(get_option( 'date_format' ), strtotime($notice['d'])), esc_attr(apply_filters('lws_notices_origin_url', "https://plugins.longwatchstudio.com", $key)), esc_attr(apply_filters('lws_notices_origin_name', "Long Watch Studio Plugins", $key)));
				else if( isset($notice['message']) )
					$content = apply_filters('lws_notices_content', $notice['message'], $key);

				if( !empty($content) )
				{
					echo "<div class='notice notice-$level$dis lws-adminpanel-notice$perm'$key><p>$content</p></div>";
					++$count;
				}
				if( !(isset($notice['once']) && boolval($notice['once'])) )
					$validNotices[$key] = $notice;
			}

			if( count($validNotices) != count($notices) )
			{
				\update_site_option('lws_adminpanel_notices', $validNotices);
			}

			if( $count > 0 )
			{
				wp_enqueue_script('lws-tools');
				wp_enqueue_script('lws-admin-notices', LWS_ADMIN_PANEL_JS . '/adminnotices.js', array('jquery','lws-tools'), LWS_ADMIN_PANEL_VERSION );
			}
		}
	}

	$impl = new LWS_Adminpanel_Impl();
	$impl->init();
});

if( !function_exists('lws_admin_has_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_has_notice($key)
	{
		$notices = get_site_option('lws_adminpanel_notices', array());
		return isset($notices[$key]);
	}
}

if( !function_exists('lws_admin_delete_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_delete_notice($key)
	{
		$notices = get_site_option('lws_adminpanel_notices', array());
		if( isset($notices[$key]) )
		{
			unset($notices[$key]);
			\update_site_option('lws_adminpanel_notices', $notices);
		}
	}
}

if( !function_exists('lws_admin_add_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_add_notice($key, $message, $options=array())
	{
		$options['message'] = $message;
		\update_site_option('lws_adminpanel_notices', array_merge(get_site_option('lws_adminpanel_notices', array()), array($key => $options)));
	}
}

if( !function_exists('lws_admin_add_notice_once') )
{
	/** @see lws_admin_add_notice */
	function lws_admin_add_notice_once($key, $message, $options=array())
	{
		$options['once'] = true;
		lws_admin_add_notice($key, $message, $options);
	}
}

add_filter('lws_adminpanel_instance', array($lws_adminpanel, 'cmpVersion'));

require dirname(__FILE__) . '/lws-install.php';

?>
