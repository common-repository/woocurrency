<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

/** Expect a 'callback' entry in extra which refers to a callable.
 * The callback get two arguments: button_id, array with all inputs of the group (id => value).
 * This callback should return false for failure.
 * On success, a string can be returned which will be displayed on html after the button. */
class Button extends \LWS\Adminpanel\Pages\Field
{
	public function label()
	{
		if( isset($this->extra['text']) )
			return parent::label();
		else
			return ""; /// title will be used as button text
	}

	public function title()
	{
		if( isset($this->extra['text']) )
			return parent::title();
		else
			return ""; /// title will be used as button text
	}

	public function input()
	{
		$triggable = (isset($this->extra['callback']) && is_callable($this->extra['callback']));
		$class = (isset($this->extra['class']) && is_string($this->extra['class']) ? " {$this->extra['class']}" : '');
		$class .= ($triggable ? ' lws-adm-btn-trigger' : '');
		$text = esc_attr($this->getExtraValue('text', $this->m_Title));

		echo "<input class='lws-adm-btn$class' id='{$this->m_Id}' type='button' value='$text' />";
		if( $triggable ) // answer zone
			echo "<div class='lws-adm-btn-trigger-response'></div>";
	}
}

?>
