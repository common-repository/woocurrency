<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class WPEditor extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->m_Id;
		$value = get_option($this->m_Id);
		wp_editor($value, $name, $this->extra);
	}
}

?>
