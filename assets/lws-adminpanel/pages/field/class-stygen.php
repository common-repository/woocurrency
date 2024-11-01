<?php
namespace LWS\Adminpanel\Pages\Field;

/** Provide a CSS graphic editor.
 * User is shepherded by a html demo with selectable and editable elements.
 * Use an extra (array) argument with:
 * @param extra['html'] a path (local file path) to a html or php local file used for demo;
 * 	we require a local path since it is include as any html/php page.
 * @param extra['css'] a url (@see plugins_dir()) to the css;
 *  we require an url as wp_enqueue_style requires it.
 * @param extra['template'] name of the template, is set to $template before calling the demo.
 *  In your demo snippet, you can use filter 'lws_mail_snippet' to get a settings value. */
class StyGen extends \LWS\Adminpanel\Pages\Field
{

	public function __construct($id, $title, $extra=null)
	{
		parent::__construct($id, $title, $extra);
		add_action('admin_enqueue_scripts', array($this, 'script'));
		add_action("update_option_{$id}", array( $this, 'revokeCache'), 10, 3);
		add_action('admin_head', array($this, 'reset'));
	}

	public function input()
	{
		if($this->is_valid())
		{
			$balises = $this->getBalises($this->extra['css']);
			$cssvalues = base64_encode(json_encode(array_values($balises)));
			$labels = array(
				__("Available Elements", 'lws-adminpanel'),
				__("Select an element to start modifying its style", 'lws-adminpanel'),
				esc_attr(_x("Reset Style", "Stygen button", 'lws-adminpanel'))
			);

			$lwsseditor = null;
			$lwsseditor = "<div class='lwss_editor lwss-editor' data-cssvalues='{$cssvalues}'>";
			$lwsseditor .= "<input name='{$this->m_Id}' type='hidden' value='' class='lwss_editor_chain'/>";
			$lwsseditor .= "<div class='lwss_sidemenu lwss-sidemenu'><div class='lwss-sidemenu-title'>{$labels[0]}</div></div>";
			$lwsseditor .= "<div class='lwss_centraldiv'>";
			$lwsseditor .= "<div class='lwss_info'>{$labels[1]}</div>";
			$lwsseditor .= "<div class='lwss-main-conteneur'><div class='lwss-mc-row'></div><div class='lwss-mc-row lwss-mc-mastercol'>";
			$lwsseditor .= "<div class='lwss-mc-col'></div><div class='lwss_canvas lwss-canvas'>";
			$lwsseditor .= $this->getBody($this->extra['html']);
			$lwsseditor .= "</div><div class='lwss-mc-col'></div></div><div class='lwss-mc-row'></div>";
			$lwsseditor .= "</div></div></div>";

			$lwsseditor .= "<div class='lws-stygen-row-reset'><input class='lws-stygen-reset lws-adm-btn' data-id='{$this->m_Id}' type='button' value='{$labels[2]}' /></div>";
			echo $lwsseditor;
		}
	}

	public function script()
	{
		wp_enqueue_script( 'js-cookie', LWS_ADMIN_PANEL_JS . '/jquery-plugins/js.cookie.js', array('jquery'), LWS_ADMIN_PANEL_VERSION, true );
		wp_enqueue_script( 'lws-stygen-font', LWS_ADMIN_PANEL_JS . '/stygenfont.js', array('jquery','js-cookie'), LWS_ADMIN_PANEL_VERSION, true );
		wp_enqueue_script( 'lws-stygen-fields', LWS_ADMIN_PANEL_JS . '/stygenfields.js', array('jquery'), LWS_ADMIN_PANEL_VERSION, true );
		wp_enqueue_script( 'lws-stygen-panel', LWS_ADMIN_PANEL_JS . '/stygenpanel.js', array('lws-stygen-fields','lws-stygen-font'), LWS_ADMIN_PANEL_VERSION, true );
		wp_enqueue_script( 'lws-stygen', LWS_ADMIN_PANEL_JS . '/stygen.js', array('lws-base64','lws-stygen-panel'), LWS_ADMIN_PANEL_VERSION, true );
		wp_enqueue_style( $this->id(), \add_query_arg('stygen', $this->id(), $this->extra['css']), array(), strval(time()) ); // set version to timestamp to force no buffering
	}

	// function to get the html file's body only
	protected function getBody($url)
	{
		$template = $this->getExtraValue('template');
		$page = '';
		if( $url !== false )
		{
			ob_start();
			require($url);
			$page = ob_get_contents();
			ob_end_clean();
		}
		else if( $this->getExtraValue('purpose') == 'mail' )
		{
			require_once LWS_ADMIN_PANEL_PATH . '/mailer.php';
			$page = \LWS\Adminpanel\Mailer::instance()->getDemo($template);
		}
		else
			return __("Snippet unknown", LWS_ADMIN_PANEL_DOMAIN);

		$d = new \DOMDocument;
		$d->loadHTML($page);
		$body = $d->getElementsByTagName('body')->item(0);
		$innerHTML = "";
		$children  = $body->childNodes;
		foreach ($children as $child)
		{
			$innerHTML .= $body->ownerDocument->saveHTML($child);
		}
		return $innerHTML;
	}

	protected function is_valid()
	{
		if(!isset($this->extra['html']))
		{
			error_log("No html file provided");
			return false;
		}
		if(!isset($this->extra['css']))
		{
			error_log("No lwss file provided");
			return false;
		}
		return true;
	}

	protected function getBalises($cssFile)
	{
		require_once LWS_ADMIN_PANEL_PATH . '/pseudocss.php';
		$pseudocss = new \LWS\Adminpanel\PseudoCss();
		$pseudocss->extract($pseudocss->relevantUrlPart($cssFile), false, false);

		$values = get_option($this->m_Id, '');
		if( !empty($values) )
		{
			$css = base64_decode($values);
			if( !empty($css) )
			{
				$loading = new \LWS\Adminpanel\PseudoCss();
				$loading->fromString($css);
				$pseudocss->merge($loading, false);
			}
		}
		return $pseudocss->getBalises();
	}

	/** from do_action( 'updated_option', $option, $old_value, $value );
	 *	or do_action( "update_option_{$option}", $old_value, $value, $option );
	 *	@brief delete any cache. */
	public function revokeCache($old_value, $value, $option)
	{
		require_once LWS_ADMIN_PANEL_PATH . '/cache.php';
		$filename = sanitize_key($this->m_Id) . '-cached.css';
		$cached = new \LWS\Adminpanel\Cache($filename);
		$cached->del();
	}

	/**	Erase cache and database value. Require a $_POST["lws-stygen-reset-{$this->m_Id}"] = true */
	public function reset($force=false)
	{
		$resetId = "lws-stygen-reset-{$this->m_Id}";
		if( $force || (isset($_POST[$resetId]) && boolval($_POST[$resetId])) )
		{
			delete_option($this->m_Id);
			$this->revokeCache('', '', $this->m_Id);
		}
	}

}

?>
