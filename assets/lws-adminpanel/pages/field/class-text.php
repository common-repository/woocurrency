<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

class Text extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$value = '';
		$dft = '';
		$prop = '';
		$source = (isset($this->extra['source']) ? " data-source='{$this->extra['source']}'" : "");
		if( isset($this->extra['values']) )
		{
			foreach($this->extra['values'] as $k => $v)
			{
				$prop = esc_attr($k);
				$value = esc_attr($v);
				break;
			}
		}
		else if( isset($this->extra['value']) )
			$value = esc_attr($this->extra['value']);
		else
			$value = esc_attr(get_option($this->m_Id, ''));

		if( isset($this->extra['defaults']) )
		{
			if( is_array($this->extra['defaults']) )
			{
				if( !empty($prop) )
				{
					if( isset($this->extra['defaults'][$prop]) )
						$dft = esc_attr($v);
				}
				else foreach($this->extra['defaults'] as $k => $v)
				{
					$prop = esc_attr($k);
					$dft = esc_attr($v);
					break;
				}
			}
			else if( is_string($this->extra['defaults']) )
				$prop = $this->extra['defaults'];
		}
		$mix = (!empty($value) ? $value : $dft);

		$maxlen = '';
		if( isset($this->extra['maxlength']) && is_numeric($this->extra['maxlength']) && $this->extra['maxlength'] > 0 )
			$maxlen = " maxlength='{$this->extra['maxlength']}'";
		$pattern = '';
		if( isset($this->extra['pattern']) && is_string($this->extra['pattern']) && !empty($this->extra['pattern']) )
			$pattern = " pattern='{$this->extra['pattern']}'";
		$placeholder = '';
		if( isset($this->extra['placeholder']) && is_string($this->extra['placeholder']) && !empty($this->extra['placeholder']) )
			$placeholder = " placeholder='{$this->extra['placeholder']}'";

		$class = '';
		if( isset($this->extra['class']) && is_string($this->extra['class']) && !empty($this->extra['class']) )
			$class = (empty($this->style) ? '' : ' ') . $this->extra['class'];

		if( empty($prop) )
			echo "<input class='{$this->style}$class' type='text' name='{$this->m_Id}' value='$mix'$maxlen$pattern$placeholder />";
		else
		{
			echo "<div class='lwss-css-inputs'>";
			echo "<input class='{$this->style}$class' type='text' data-css='$prop' data-lwss='$dft'$source value='$mix'$maxlen$pattern$placeholder />";
			echo "<input class='{$this->style} lwss-merge-css' type='hidden' name='{$this->m_Id}' value='$prop:$value' />";
			echo "</div>";
		}
	}
}

?>
