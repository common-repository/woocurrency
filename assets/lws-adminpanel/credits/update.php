<?php
namespace LWS\Adminpanel;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Auto-update like Wordpress.
 * @ee https://code.tutsplus.com/tutorials/a-guide-to-the-wordpress-http-api-automatic-plugin-updates--wp-25181 */
class Update
{
	private $data = null;

	static public function install($main_file, $remote_home_url='', $nic='', $forceSpecificAPI=false)
	{
		return new Update($main_file, $remote_home_url, $nic, $forceSpecificAPI);
	}

	protected function __construct($main_file, $remote_home_url='', $nic='', $forceSpecificAPI=false)
	{
		$this->file = $main_file;
		$this->plugin_slug = plugin_basename($this->file);
		$this->slug = strtolower(basename($this->plugin_slug, '.php'));
		$this->nic = $nic;

		if( !empty($remote_home_url) )
			$this->remote_path = $remote_home_url;
		else
		{
			$args = array('action'=>'lwswoosoftware');
			$server = 'https://plugins.longwatchstudio.com/wp-admin/admin-ajax.php';
			if( defined('LWS_DEV') && !empty(LWS_DEV) )
			{
				if( LWS_DEV === true )
					$server = admin_url('admin-ajax.php');
				else if( is_string(LWS_DEV) )
					$server = LWS_DEV;
			}
			$this->remote_path = add_query_arg($args, $server);
		}

    if( $this->isActive() || $forceSpecificAPI )
    {
			// define the alternative API for updating checking
			add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
			// Define the alternative response for information checking
			add_filter('plugins_api_result', array($this, 'check_info'), 10, 3);
			// bypass standart dl
			add_filter('upgrader_pre_download', array($this, 'upgraderPreDownload'), 10, 3);
		}

		$this->local_force = isset($_REQUEST['lwsupforce']) ? boolval($_REQUEST['lwsupforce']) : false;
		if( $this->local_force )
		{
			if( !has_action('admin_head', '\LWS\Adminpanel\Update::clearTransient') )
				add_action('admin_head', '\LWS\Adminpanel\Update::clearTransient', 10);
			add_action('admin_head', array($this, 'forceTransient'), 20);
		}
	}

	static function clearTransient()
	{
		// Force refresh of plugin update information
		\delete_site_transient('update_plugins');
		\wp_cache_delete( 'plugins', 'plugins' );
		setcookie('lwsupforce', '1', time() + (60*5)); // ...
	}

	function forceTransient()
	{
		if ( !function_exists( 'plugins_api' ) )
			include( ABSPATH . '/wp-admin/includes/plugin-install.php' );
		$plugin_api = \plugins_api( 'plugin_information', array( 'slug' => $this->slug, 'fields' => array( 'sections' => false, 'compatibility' => false, 'tags' => false ) ) );
		if( \is_wp_error($plugin_api) )
			error_log("Error on {$this->slug} licensed version info request: " . $plugin_api->get_error_message());
		else if( (isset($plugin_api->new_version) || isset($plugin_api->version)) && isset($plugin_api->download_link) )
		{
			$obj = new \stdClass();
			foreach( get_object_vars($plugin_api) as $k => $v )
				$obj->$k = $v;
			$obj->slug = $this->slug;
			$obj->url = $this->remote_path;
			$obj->new_version = isset($plugin_api->new_version) ? $plugin_api->new_version : $plugin_api->version;
			$obj->package = $plugin_api->download_link;
			$plugin_transient->response[plugin_basename($this->file)] = $obj;
			\set_site_transient( 'update_plugins', $plugin_transient );
		}
		else
			error_log("Missing data returned by {$this->slug} licensed version info request.");
	}

	/** Add our self-hosted autoupdate plugin to the filter transient
	 * @param $transient
	 * @return object $ transient */
	public function check_update($transient)
	{
		if( !empty($transient) )
		{
			// Get the remote version
			$remote_info = $this->getRemoteInfo();
			if( $remote_info !== false && isset($remote_info->new_version) )
			{
				// If a newer version is available, add the update
				if( $this->local_force
				|| version_compare($this->pluginData('Version'), $remote_info->new_version, '<')
				|| (isset($remote_info->force) && $remote_info->force && version_compare($this->pluginData('Version'), $remote_info->new_version, '==')) )
				{
					$obj = new \stdClass();
					$obj->slug = $this->slug;
					$obj->url = $this->remote_path;
					foreach( get_object_vars($remote_info) as $k => $v )
					{
						$obj->$k = is_object($v) ? get_object_vars($v) : $v;
					}
					$obj->new_version = $remote_info->new_version;
					$obj->package = $remote_info->download_link;
					$transient->response[$this->plugin_slug] = $obj;
				}
			}
		}
		return $transient;
	}

	static function fingerprint($slug)
	{
		$tokens = array($slug, 'LWS\Adminpanel\Query', 'fingerprint', get_site_option('siteurl'),
			DB_HOST, DB_NAME, (is_multisite()?'M':'S'), get_site_option('initial_db_version'));
		return sanitize_key(wp_hash(implode('.', $tokens)));
	}

	/**Add our self-hosted description to the filter
	 * @param boolean $res result as wordpress got.
	 * @param array $action
	 * @param object $arg
	 * @return bool|object */
	public function check_info($res, $action, $arg)
	{
		if( isset($arg->slug) && ($arg->slug === $this->slug) )
		{
			$remote_info = $this->getRemoteInfo();
			if( $remote_info !== false )
			{
				if( isset($remote_info->sections) && !is_array($remote_info->sections) )
					$remote_info->sections = get_object_vars($remote_info->sections);
				$remote_info->name = $remote_info->plugin_name;

				if( $res === false || is_wp_error($res) )
					$res = $remote_info;
				else // merge
				{
					foreach( get_object_vars($remote_info) as $k => $v )
					{
						if( is_object($v) ) $v = get_object_vars($v);
						if( isset($res->$k) && is_array($v) && !isset($v[0]) ) // merge associative array
						{
							foreach( $v as $subk => $subv )
								$res->$k[$subk] = $subv;
						}
						else
							$res->$k = $v;
					}
				}
			}
		}
    return $res;
	}

	private function getRemoteInfo()
	{
		if( !isset($this->info) )
		{
			$args = array(
				'info'=>'',
				'product' => $this->slug,
				'lang' => get_locale()
			);
			$url = add_query_arg( $args, $this->remote_path );
			$args = array(
				'body' => array(
					'key' => get_site_option('lws-license-key-' . $this->slug, ''),
					'rc' => apply_filters('lws-ap-release-' . $this->slug, '')
				)
			);
			$request = wp_safe_remote_post( $url, $args );
			if( \is_wp_error($request) )
				error_log("[{$this->slug}] Remote response error: " . print_r($request, true));
			else if( !in_array(wp_remote_retrieve_response_code($request), array(200, 301)) )
				error_log("[{$this->slug}] Remote response code is " . wp_remote_retrieve_response_code($request));
			else
				$this->info = $this->filterInfo(json_decode(wp_remote_retrieve_body($request)));
		}
		return (isset($this->info) ? $this->info : false);
	}

	private function filterInfo($info)
	{
		if( isset($info->revoke) && $info->revoke )
			update_site_option('lws-license-xpr', array_merge(get_site_option('lws-license-xpr',array(), false),array($this->slug=>'-'.\date('Y-m-d'))));
		return $info;
	}

	function upgraderPreDownload($reply, $package, $upgrader)
	{
		if( false !== $reply ) return $reply;
		if( false === ($info = $this->getRemoteInfo()) ) return $reply;

		if( $package == $info->download_link )
		{
			if ( ! preg_match('!^(http|https|ftp)://!i', $package) && file_exists($package) ) //Local file or remote?
				return $package; //must be a local file..

			if ( empty($package) )
				return new \WP_Error('no_package', $upgrader->strings['no_package']);

			$upgrader->skin->feedback('downloading_package', $package);

			$download_file = $this->download_url($package);

			if ( \is_wp_error($download_file) )
				return new \WP_Error('download_failed', $upgrader->strings['download_failed'], $download_file->get_error_message());
			return $download_file;
		}
		return $reply;
	}

	/** Downloads a URL to a local temporary file using the WordPress HTTP Class.
	* Please note, That the calling function must unlink() the file. */
	private function download_url( $url, $timeout = 1000 ) {
		//WARNING: The file is not automatically deleted, The script must unlink() the file.
		if ( ! $url )
			return new \WP_Error('http_no_url', __('Invalid URL Provided.'));

		$url_filename = basename( parse_url( $url, PHP_URL_PATH ) );

		$tmpfname = wp_tempnam( $url_filename );
		if ( ! $tmpfname )
			return new \WP_Error('http_no_file', __('Could not create Temporary file.'));

		$args = array(
			'timeout' => $timeout, 'stream' => true, 'filename' => $tmpfname,
			'body' => $this->fileRequestBody()
		);
		$response = wp_safe_remote_post( $url, $args );

		if ( \is_wp_error( $response ) ) {
			unlink( $tmpfname );
			return $response;
		}

		if ( 200 != wp_remote_retrieve_response_code( $response ) ){
			error_log(wp_remote_retrieve_response_code( $response ));
			unlink( $tmpfname );
			return new \WP_Error( 'http_404', trim( wp_remote_retrieve_response_message( $response ) ) );
		}

		$content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );
		if ( $content_md5 ) {
			$md5_check = verify_file_md5( $tmpfname, $content_md5 );
			if ( \is_wp_error( $md5_check ) ) {
				unlink( $tmpfname );
				return $md5_check;
			}
		}

		return $tmpfname;
	}

	private function fileRequestBody()
	{
		$info = $this->getRemoteInfo();
		$body = array(
			'product' => $this->slug,
			'version' => $info->new_version,
			'lang' => get_locale()
		);
		if( !empty($key = get_site_option('lws-license-key-' . $this->slug, '')) )
		{
			$body['key'] = $key;
			$body['fingerprint'] = self::fingerprint($this->slug);
			$nonce = isset($info->nonce) ? $info->nonce : '';
			$body['nonce'] = $nonce;
			$body['hash'] = hash( 'sha256', implode('/', array($this->slug, $info->new_version, self::fingerprint($this->slug), $nonce, $this->nic)) );
		}
		else if( isset($info->nonce) )
		{
			$body['nonce'] = $info->nonce;
			$body['hash'] = hash( 'sha256', implode('/', array($this->slug, $info->new_version, self::fingerprint($this->slug), $nonce, $this->nic)) );
		}
		return $body;
	}

	private function pluginData($key='')
	{
		if( is_null($this->data) )
		{
			if( !function_exists('get_plugin_data') )
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$this->data = \get_plugin_data($this->file, false);
			if( !isset($this->data['Name']) || empty($this->data['Name']) )
				$this->data['Name'] = $this->slug;
			if( !isset($this->data['Version']) )
				$this->data['Version'] = '';
			if( !isset($this->data['Author']) || empty($this->data['Author']) )
				$this->data['Author'] = __("Long Watch Studio", 'lws-adminpanel');
			if( !isset($this->data['AuthorURI']) || empty($this->data['AuthorURI']) )
				$this->data['AuthorURI'] = __("https://plugins.longwatchstudio.com", 'lws-adminpanel');
			if( !isset($this->data['PluginURI']) || empty($this->data['PluginURI']) )
				$this->data['PluginURI'] = __("https://plugins.longwatchstudio.com", 'lws-adminpanel');
		}
		return empty($key) ? $this->data : $this->data[$key];
	}

	private function isActive()
	{
		return !empty(get_site_option('lws-ap-activation-' . $this->slug, ''));
	}

	static function xpr($zero)
	{
		$opt = array('lws-license-xpr','lws_adminpanel_notices');

		foreach(\get_site_option($opt[0],array()) as $sl=>$xpr)
		{
			$p = strpos($xpr,'-'); $d = substr($xpr,$p+1);
			if( (!empty($d) && \date_create($d)<=\date_create()) || \wp_hash($sl.\get_site_option('lws-license-key-'.$sl,'').self::fingerprint($sl).(empty($d)?$zero:$d))!=substr($xpr,0,$p) )
			{
				if( !function_exists('get_plugin_data') )
					require_once(ABSPATH . 'wp-admin/includes/plugin.php');
				$data = \get_plugins('/'.$sl);
				lwsxpr(WP_PLUGIN_DIR."/$sl/modules");
				\delete_site_option("lws-ap-activation-$sl");\delete_site_option("lws-license-key-$sl");\delete_site_option("lws-ap-release-$sl");\delete_site_option("lws-ap-release-$sl-pro");
				\update_site_option($opt[0], array_filter(\get_site_option($opt[0]),function($k)use($sl){return $k!=$sl;},ARRAY_FILTER_USE_KEY));
				\do_action($opt[1].'_'.$sl, 42);
				\update_site_option($opt[1], array_merge(\get_site_option($opt[1],array(), false),array($sl.$d=>array('d'=>$d,'n'=>isset($data['Name'])?$data['Name']:$sl))));
			}
		}
		update_option('lwsX'.DB_NAME, date('Y-m-d'));
	}

}

?>
