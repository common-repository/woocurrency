<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Help extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id='', $title='', $extra=null)
	{
		parent::__construct($id, $title, $extra);
		$this->help = $this->getExtraValue('help');
		if( isset($this->extra['help']) )
			unset($this->extra['help']);
		$this->gizmo = true;
	}

	public function input()
	{
		echo "<div class='lws-field-expl'>{$this->help}</div>";
	}
}

?>
