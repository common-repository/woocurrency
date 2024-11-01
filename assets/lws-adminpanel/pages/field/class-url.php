<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class URL extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->m_Id;
		$value = get_option($this->m_Id);
		echo "<input class='{$this->style}' type='url' name='$name' value='$value' placeholder='URL' />";
	}
}

?>
