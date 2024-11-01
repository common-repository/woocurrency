<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Hidden extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id='', $title='', $extra=null)
	{
		if( is_array($extra) )
			$extra['hidden'] = true;
		else
			$extra = array('hidden' => true);
		parent::__construct($id, $title, $extra);
	}

	public function input()
	{
		$name = $this->m_Id;
		if( isset($this->extra['value']) )
			$value = esc_attr($this->extra['value']);
		else
			$value = esc_attr(get_option($this->m_Id));
		echo "<input class='{$this->style} lws-input-hidden' type='hidden' name='$name' value='$value' />";
	}
}

?>
