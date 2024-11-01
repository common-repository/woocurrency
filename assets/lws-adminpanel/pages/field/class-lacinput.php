<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_ADMIN_PANEL_PATH . '/pages/class-lac.php';


/** Expose an enhanced text input
 * Data can be preloaded or read from ajax call.
 * All extra are optionnal.
 * @param $extra['ajax'] the ajax action to grab data list.
 * @param $extra['source'] the preload data list as array of array('value'=>…, 'label'=>…, 'detail'=>…)
 * 	value is not used, label is the displayed and recorded value,
 * 	detail key is optionnal if you want to display complexe html as menu item.
 * @param $extra['class'] css class list transfered to autocomplete wrapper (span).
 * @param $extra['name'] input name set to autocomplete wrapper input (in case label is relevant too).
 * @param $extra['delay'] hit key delay before search trigger (let user finish its term before loading).
 * @param $extra['placeholder'] is a input placeholder.
 * @param $extra['spec'] any value transfered as json base64 encoded to ajax.
 * @param $extra['value'] if is set, use this as input value, else try a get_option($id).
*
 * @note soure is an array of object or array with value, label and optionnaly detail for complex html item in unfold list.
 * It is recommended to have at least the selected value described in source.
 */

class LacInput extends \LWS\Adminpanel\Pages\LAC
{
	public function __construct($id, $title, $extra=null)
	{
		parent::__construct($id, $title, $extra);
		add_action('admin_enqueue_scripts', array($this, 'script'), 9);
	}

	protected function html()
	{
		if( $this->isValid(false) )
		{
			$attrs = implode('', array(
				$this->getExtraAttr('sourceurl', 'data-sourceurl'),
				$this->getExtraAttr('ajax', 'data-ajax'),
				$this->getExtraAttr('placeholder', 'data-placeholder'),
				$this->getExtraAttr('class', 'data-class'),
				$this->getExtraAttr('name', 'data-name'),
				$this->getExtraAttr('minsearch', 'data-minsearch'),
				$this->getExtraAttr('minoption', 'data-minoption'),
				$this->getExtraAttr('delay', 'data-delay'),
				$this->getExtraAttr('minlength', 'data-minlength')
			));
			$value = esc_attr($this->hasExtra('value') ? $this->getExtraValue('value') : get_option($this->m_Id));
			$name = esc_attr($this->m_Id);
			$source = $this->data('source');
			$spec = $this->data('spec');
			if( !isset($this->scriptAdded) || !$this->scriptAdded )
			{
				$this->script();
			}
			return "<input class='lac_input' name='$name' value='$value'$attrs$source$spec>";
		}
	}

	public function script()
	{
		$this->scriptAdded = true;
		$dep = $this->modelScript();
		wp_enqueue_script( 'lac-input', LWS_ADMIN_PANEL_JS . '/lac/lacinput.js', $dep, LWS_ADMIN_PANEL_VERSION, true );
		$css = LWS_ADMIN_PANEL_CSS . '/lac/lacinput.css';
		wp_enqueue_style('lws-adminpanel-lacinputcss', $css, array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
	}

}

?>
