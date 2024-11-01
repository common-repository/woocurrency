<?php
/**
 * Extends TGM_Plugin_Activation to merge $plugin and $config
 * with the most constraining values in case of conflict.
 *
 * Alone TGM_Plugin_Activation use only last value passed to config
 * (so all plugin endly hook with priority PHP_INT_MAX and problems persist).
 *
 * Alone TGM_Plugin_Activation ignore a plugin dependency if slug already in its list,
 * even if a greater version is required
 * (in this case, plugin must be the first to call - with priority PHP_INT_MIN).
 *
 * In addition, since id are overwritten, a notice already dissmissed will never
 * pop again even for a new plugin with missing dependency,
 * then we provide a function to reset dissmiss state to call in register_activation_hook.
 * 
 * 
 * It could be boring, when  all requirements are fulfilled
 * when one forbids dismiss
 * that notice from other plugin with dismissable to true cannot be hidden.
 * they is no easy way to avoid that except hope TGM dev team implement
 * independent notification config.
 */

if( !class_exists('TGM_With_Merge') )
{
	require_once 'tgm/class-tgm-plugin-activation.php';
	class TGM_With_Merge extends TGM_Plugin_Activation
	{
		public $lws_version = '0.0.0';
	}
}

$GLOBALS['TGM_With_Merge_Version'] = '1.0.1';
$TGM_With_Merge_Classname = 'TGM_With_Merge_v101';

if( !class_exists($TGM_With_Merge_Classname) )
{
	class TGM_With_Merge_v101 extends TGM_With_Merge
	{
		public function __construct($previous = null)
		{
			$this->lws_version = $GLOBALS['TGM_With_Merge_Version'];
			parent::$instance = $this;
			if( $previous != null )
			{
				$this->mergePreviousInstance($previous);
				
				remove_action( 'init', array( $previous, 'load_textdomain' ), 5 );
				remove_action( 'init', array( $previous, 'init' ) );
				remove_filter( 'load_textdomain_mofile', array( $previous, 'overload_textdomain_mofile' ), 10 );
			}
			parent::__construct();
		}

		// don't lose what already be done.
		private function mergePreviousInstance($previous)
		{
			$keys = array(
				'id',
				'default_path',
				'has_notices',
				'dismissable',
				'dismiss_msg',
				'menu',
				'parent_slug',
				'capability',
				'is_automatic',
				'message',
				'strings',
				'force_activation',
				'force_deactivation',
				'sort_order',
				'plugins',
				'page_hook'
			);
			foreach($keys as $k)
			{
				if( isset($previous->$k) )
					$this->$k = $previous->$k;
			}
		}

		/** overload to keep worst of any config key. */
		public function config( $config )
		{
			$best = array(
				'has_notices' => true,
				'dismissable' => false,
				'dismiss_msg' => '',
				'is_automatic' => true
			);
			
			if( !$this->pendingRequirement() )
			{
				$config['dismissable'] = true;
				unset($best['dismissable']);
			}
			else
			{
				$config['dismissable'] = false;
				static $dissmised_cleaned = false;
				if( !$dissmised_cleaned )
				{
					tgm_reset_dismissed_notices();
					$dissmised_cleaned = true;
				}
			}

			foreach( $best as $k => $v )
			{
				if( array_key_exists($k, $config) && isset($this->$k) )
				{
					if( is_bool($v) && $this->$k == $v )
						$config[$k] = $this->$k;
					else if( is_string($v)&& !empty($this->$k) )
						$config[$k] = $this->$k . (!empty($config[$k]) ? '<br/>' . $config[$k] : '');
				}
			}

			parent::config( $config );
		}
		
		/// @return true if a dependency is still not activated.
		private function pendingRequirement()
		{
			foreach($this->plugins as $slug => $plugin)
			{
				if( TGMPA_Utils::validate_bool($plugin['required']) )
				{
					if( !$this->is_plugin_installed($plugin['slug']) && !$this->is_plugin_active($plugin['slug']) )
						return true;
				}
			}
			return false;
		}

		/** overload to keep plugin description of the greatest version number. */
		public function register( $plugin )
		{
			if ( empty( $plugin['slug'] ) || empty( $plugin['name'] ) )
			{
				return;
			}

			if ( empty( $plugin['slug'] ) || ! is_string( $plugin['slug'] ) || isset( $this->plugins[ $plugin['slug'] ] ) )
			{
				// then should merge required, force_activation, force_deactivation
				$prefer = array('required', 'force_activation', 'force_deactivation');
				foreach($prefer as $k)
				{
					$this->bool_merge($plugin, $this->plugins[ $plugin['slug'] ], $k, true);
				}
				
				// test if required version is equal or greater
				if( version_compare($plugin['version'], $this->plugins[ $plugin['slug'] ]['version']) >= 0 )
				{
					// remove it to let standart behavior add the new one.
					unset($this->plugins[ $plugin['slug'] ]);
				}
			}
			
			parent::register( $plugin );
		}
	
		private function bool_merge(&$a_new, &$a_old, $k, $prefer = true)
		{
			if( array_key_exists($k, $a_old) )
			{
				if( !array_key_exists($k, $a_new) || $a_new[$k] != $prefer )
					$a_new[$k] = $a_old[$k];
			}
		}
	}
}

if( !function_exists('tgm_reset_dismissed_notices') )
{
	/** forget any tgmpa dismiss.
	 * We advise calling it in your function you gave to register_activation_hook(). */
	function tgm_reset_dismissed_notices()
	{
		global $wpdb; // force any notice to be displayed again
		$wpdb->query("DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` LIKE 'tgmpa_dismissed_notice_%'");
	}
}

if( !function_exists('tgm_hack_the7_script') )
{
	/** Theme the7 add a script to force page reloading at dismiss.
	 * This look for a href somewhere which is not present if notice is not dismissable.
	 * So, adding a href will avoid a 404. (search in the7, the function print_inline_js_action)
	 * @return a message to add in config['dismiss_msg'] if you set config['dismissable'] to false */
	function tgm_hack_the7_script()
	{
		return "<a style='display=none' class='dismiss-notice' href=''></a>";
	}
}

if( is_admin() && !defined('DOING_AJAX') )
{
	if( !isset($GLOBALS['tgmpa']) )
		$GLOBALS['tgmpa'] = new $TGM_With_Merge_Classname(null);
	else if( !is_a($GLOBALS['tgmpa'], 'TGM_With_Merge') )
		$GLOBALS['tgmpa'] = new $TGM_With_Merge_Classname($GLOBALS['tgmpa']);
	else if( version_compare($GLOBALS['tgmpa']->lws_version, $GLOBALS['TGM_With_Merge_Version']) < 0 )
		$GLOBALS['tgmpa'] = new $TGM_With_Merge_Classname($GLOBALS['tgmpa']);
}

?>
