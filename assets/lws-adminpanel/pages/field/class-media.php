<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

/** extra entries could be:
 * urlInputName : add a hidden input to get the image url.
 * size: define the wordpress size name of the image to use.
 * classSize: the css size style.
 * value: force a value instead of reading options.
 * type: only 'image' is supported actually.
 * */
class Media extends \LWS\Adminpanel\Pages\Field
{
	protected function dft(){ return array('type'=>'image'); }

	public static function compose($id, $extra=null)
	{
		$me = new self($id, '', $extra);
		return $me->html();
	}

	public function input()
	{
		echo $this->html();
	}

	private function html()
	{
		$img = '';
		$hide = '';
		$btn = esc_attr(__("Pick a media", 'lws-adminpanel'));
		$del = esc_attr(__("Remove", 'lws-adminpanel'));
		$title = esc_attr(__("Select media", 'lws-adminpanel'));
		$pick = esc_attr(__("Use the selected item", 'lws-adminpanel'));

		$value = esc_attr($this->hasExtra('value') ? $this->getExtraValue('value') : get_option($this->m_Id));
		$size = $this->getExtraValue('size', 'small');
		$classSize = $this->getExtraValue('classSize', 'medium');
		$url = $this->getExtraValue('urlInputName', '');

		if( $this->extra['type'] == 'image' )
		{
			if( !empty($value) && is_numeric($value) )
				$img = wp_get_attachment_image($value, $size);
		}
		else
			error_log("No other media than image is managed yet. Sorry.");
		if( empty($img) )
			$hide = "style='display: none;'";
		$str = "<div class='lws_media_master'><div class='lws-adm-media'>$img</div>";
		$str .= "<input type='button' class='lws-adm-btn lws_adminpanel_btn_add_media' value='$btn' data-type='{$this->extra['type']}' data-title='$title' data-pick='$pick' data-image-size='$size' data-class-size='$classSize'>";
		$str .= "<input type='button' class='lws-adm-btn lws_adminpanel_btn_del_media' value='$del' $hide>";
		$str .= "<input type='hidden' class='lws_adminpanel_input_media_id' name='{$this->m_Id}' value='$value' />";
		if( !empty($url) )
			$str .= "<input type='hidden' class='lws_adminpanel_input_media_url' name='$url'/>";
		$str .= "</div>";

		$script = LWS_ADMIN_PANEL_JS . '/media.js';
		wp_enqueue_script( 'lws-adm-media', $script, array('jquery'), LWS_ADMIN_PANEL_VERSION, true );
		return $str;
	}
}

?>
