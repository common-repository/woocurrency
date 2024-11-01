<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

/** Expect a 'mode' in extra, default is 'ace/mode/css'.
 * @see https://ace.c9.io/ for other available mode. */
class AceEditor extends \LWS\Adminpanel\Pages\Field
{
	protected function dft(){ return array('mode' => 'ace/mode/css'); }

	public function input()
	{
		$name = $this->m_Id;
		$value = esc_attr(get_option($this->m_Id));
		$mode = $this->extra['mode'];
		$style = '';
		if( isset($this->extra['rows']) && is_numeric($this->extra['rows']) && $this->extra['rows'] > 1 )
			$style = " style='height:{$this->extra['rows']}em'";
		echo "<div id='$name' class='lws-aceEditor' data-mode='$mode'$style></div>";
		echo "<input type='hidden' name='$name' value='$value' />";
	}
}

?>
