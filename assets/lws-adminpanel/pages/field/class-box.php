<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

class Checkbox extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->m_Id;
		$value = '';
		$option = get_option($this->m_Id, false);

		if( $option === false )
		{
			if( $this->hasExtra('checked') )
				$option = $this->extra['checked'] ? 'on' : '';
			else if( $this->hasExtra('default') )
				$option = $this->extra['default'] ? 'on' : '';
		}
		if( !empty($option) )
			$value = "checked='checked'";

		$class = '';
		if( isset($this->extra['class']) && is_string($this->extra['class']) && !empty($this->extra['class']) )
			$class = (empty($this->style) ? '' : ' ') . $this->extra['class'];

		echo "<input class='{$this->style}$class' type='checkbox' name='$name' $value />";
	}
}

?>
