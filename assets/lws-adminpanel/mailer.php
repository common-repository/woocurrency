<?php
namespace LWS\Adminpanel;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_ADMIN_PANEL_ASSETS . '/Parsedown.php';

/** Manage mail formating and sending.
 *
 *	To send a mail, use the action 'lws_mail_send' with parameters email, template_name, data.
 *
 *	You must add a filter to set mail settings with hook 'lws_mail_settings_' . $template_name.
 *	@see defaultSettings about values to return.
 *
 *	You must add a filter to define mail body with hook 'lws_mail_body_' . $template_name.
 *	Second argument is data given to 'lws_mail_send'.
 *	If a WP_Error is given instead, assume it is a demo (usually for stygen).
 *
 *	To get a mail settings value, use the filter 'lws_mail_snippet'.
 *
 *	@note
 *	During dev, notes that mailbox should prevent your image display if their url contains 127.0.0.1
 *
 *	This class use singleton. */
class Mailer
{

	/** $coupon_id (array|id) an array of coupon post id */
	function sendMail($email, $template, $data=null)
	{
		$settings = $this->getSettings($template, true);
		$settings['user_email'] = $email;
		$settings = apply_filters('lws_mail_arguments_' . $template, $settings, $data);

		wp_mail(
			$email,
			$settings['subject'],
			$this->getContent($template, $settings, $data),
			array('Content-Type: text/html; charset=UTF-8')
		);
	}

	/**	@return an array to set in admin page registration as 'groups', each item representing a group array.
	 *	@param $templates array of template names. */
	function settingsGroup($templates)
	{
		$mails = array();

		if( !is_array($templates) )
		{
			if( is_string($templates) )
				$templates = array($templates);
			else
				return $mails;
		}

		foreach( $this->groupsByDomain($templates) as $domain => $settings )
		{
			$mails['D_'.$domain] = $this->buildDomainSettingsGroup($domain, $settings['name']);

			foreach( $settings['settings'] as $template => $args )
				$mails[$template] = $this->buildTemplateSettingsGroup($template, $args);
		}
		return $mails;
	}

	/** @return a mail settings property.
	 * @param $value (string) default value.
	 * @param $template (string) the mail template name we are looking for.
	 * @param $key (string) the property name @see defaultSettings */
	function settingsData($value, $template, $key)
	{
		$settings = $this->getSettings($template, true);
		if( isset($settings[$key]) && !empty($settings[$key]) )
			$value = $settings[$key];
		return $value;
	}

	protected static function defaultSettings()
	{
		return array(
			'domain' => '', // groups several mail template with few commun settings.
			'settings_domain_name' => '', // name display in admin settings screen.
			'settings_name' => '', // name display in admin settings screen.
			'about' => '', // describe the purpose of this mail to the admin settings screen.
			'subject' => '', // subject of the mail.
			'title' => '', // set at top of mail body.
			'header' => '', // presentation text in the body.
			'demo_file_path' => false, // path to a php/html file with a fake content for styling purpose.
			'css_file_url' => false, // url to a css file.
			'fields' => array(), // (array of field array) as for lws_register_pages, add extra fields in mail settings.
			'footer' => '', // set at end of mail body.
			'headerpic' => false, // media ID of a picture set at the very top of the mail.
			'logo_url' => '' // <img> html code build from 'headerpic'
		);
	}

	function parsedown($txt)
	{
		return $this->Parsedown->text($txt);
	}

	static function instance()
	{
		static $_instance = null;
		if( $_instance == null )
			$_instance = new self();
		return $_instance;
	}

	protected function __construct()
	{
		$this->Parsedown = new \LWS\Adminpanel\Parsedown();
		$this->Parsedown->setBreaksEnabled(true);
		$this->settings = array();

		/** Send a mail
		 * @param user mail,
		 * @param mail_template (string),
		 * @param data (whatever is needed by your template) pass to hook 'lws_woorewards_mail_body_' . $template */
		add_action('lws_mail_send', array($this, 'sendMail'), 10, 3);
		/** return the settings piece of data.
		 * @param (not used)
		 * @param template_name
		 * @param settings key (as title, header...) @see defaultSettings */
		add_filter('lws_mail_snippet', array($this, 'settingsData'), 10, 3);

		add_filter('lws_markdown_parse', array($this, 'parsedown'));
	}

	protected function getSettings($template, $loadValues=false, $reset=false)
	{
		if( !isset($this->settings[$template]) || $reset )
			$this->settings[$template] = apply_filters('lws_mail_settings_' . $template, self::defaultSettings());

		if( $loadValues && (!isset($this->settings[$template]['loaded']) || !$this->settings[$template]['loaded']) )
		{
			$value = trim(\get_option('lws_mail_subject_'.$template));
			if( !empty($value) ) $this->settings[$template]['subject'] = $value;
			$value = trim(\get_option('lws_mail_title_'.$template));
			if( !empty($value) ) $this->settings[$template]['title'] = $value;
			$value = trim(\get_option('lws_mail_header_'.$template));
			if( !empty($value) ) $this->settings[$template]['header'] = $value;

			$domain = !empty($this->settings[$template]['domain']) ? $this->settings[$template]['domain'] : $template;
			$value = trim(\get_option("lws_mail_{$domain}_attribute_footer"));
			if( !empty($value) ) $this->settings[$template]['footer'] = $value;

			$value = intval(\get_option("lws_mail_{$domain}_attribute_headerpic"));
			if( !empty($value) ) $this->settings[$template]['headerpic'] = $value;
			if( !empty($this->settings[$template]['headerpic']) )
			{
				$value = \wp_get_attachment_image($this->settings[$template]['headerpic'], 'small');
				if( !empty($value) ) $this->settings[$template]['logo_url'] = $value;
			}

			$this->settings[$template]['title']   = $this->Parsedown->text($this->settings[$template]['title']);

			$this->settings[$template]['loaded'] = true;
		}

		return $this->settings[$template];
	}

	protected function getContent($template, &$settings, &$data)
	{
		$style = '';
		if( !empty($settings['css_file_url']) )
			$style = \apply_filters('stygen_inline_style', '', $settings['css_file_url'], 'lws_mail_template_'.$template);

		return $this->content($template, $settings, $data, $style);
	}

	protected function content($template, &$settings, &$data, $style='')
	{
		$html = "<!DOCTYPE html><html xmlns='http://www.w3.org/1999/xhtml'>";
		$html .= "<head><meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />";
		if( !empty($style) )
			$html .= "<style>$style</style>";
		$html .= "</head><body leftmargin='0' marginwidth='0' topmargin='0' marginheight='0' offset='0'>";
		$html .= $this->banner($template, $settings);
		$html .= \apply_filters('lws_mail_body_' . $template, '', $data, $settings);
		$html .= $this->footer($template, $settings);
		$html .= "</body></html>";
		return $html;
	}

	/** Ask for a mail content with placeholder data.
	 * Do not embed any style.
	 * provided for class-stygen.php */
	function getDemo($template)
	{
		$settings = $this->getSettings($template, true);
		$data = new \WP_Error('gizmo', __("This is a test."));
		return $this->content($template, $settings, $data);
	}

	protected function banner($template, $settings)
	{
		$html = <<<EOT
	<center>
		<center>{$settings['logo_url']}</center>
		<table class='lwss_selectable lws-main-conteneur' data-type='Main Border'>
			<thead>
				<tr>
					<td class='lwss_selectable lws-top-cell' data-type='Title'>{$settings['title']}</td>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td class='lwss_selectable lws-middle-cell' data-type='Explanation'>{$settings['header']}</td>
				</tr>
EOT;
		return apply_filters('lws_mail_head_' . $template, $html, $settings);
	}

	protected function footer($template, $settings)
	{
		$html = <<<EOT
			</tbody>
			<tfoot>
				<tr>
					<td class='lwss_selectable lws-bottom-cell' data-type='Footer'>{$settings['footer']}</td>
				</tr>
				</tfoot>
		</table>
	</center>
EOT;
		return apply_filters('lws_mail_foot_' . $template, $html, $settings);
	}

	protected function groupsByDomain($templates)
	{
		$domains = array();
		foreach($templates as $template)
		{
			$settings = $this->getSettings($template, false, true);
			if( empty($settings['domain']) )
			{
				if( !isset($domains[$template]) || empty($domains[$template]['name']) )
					$domains[$template]['name'] = !empty($settings['settings_domain_name']) ? $settings['settings_domain_name'] : '';
				$domains[$template]['settings'][$template] = $settings;
			}
			else
			{
				$domain = $settings['domain'];
				if( !isset($domains[$domain]) || empty($domains[$domain]['name']) )
					$domains[$domain]['name'] = !empty($settings['settings_domain_name']) ? $settings['settings_domain_name'] : '';
				$domains[$domain]['settings'][$template] = $settings;
			}
		}
		return $domains;
	}

	protected function buildDomainSettingsGroup($domain, $title)
	{
		$prefix = "lws_mail_{$domain}_attribute_";

		return array(
			'title' => empty($title) ? __("Email Settings", LWS_ADMIN_PANEL_DOMAIN) : sprintf(__("%s Email Settings", LWS_ADMIN_PANEL_DOMAIN), $title),
			'fields' => array(
				array( 'type' => 'media' , 'title' => __("Header picture", LWS_ADMIN_PANEL_DOMAIN), 'id' => $prefix.'headerpic' ),
				array( 'type' => 'wpeditor' , 'title' => __("Footer text", LWS_ADMIN_PANEL_DOMAIN), 'id' => $prefix.'footer', 'extra' => array('editor_height' => 30) )
			)
		);
	}

	protected function buildTemplateSettingsGroup($template, $settings)
	{
		$mail = array(
			'title' => !empty($settings['settings_name']) ? $settings['settings_name'] : __("Email details", LWS_ADMIN_PANEL_DOMAIN),
			'text' => !empty($settings['about']) ? $settings['about'] : '',
			'fields' => array(
				array(
					'id' => 'lws_mail_subject_'.$template,
					'title' => __("Subject", LWS_ADMIN_PANEL_DOMAIN),
					'type' => 'text',
					'extra' => array( 'maxlength' => 350 )
				),
				array(
					'id' => 'lws_mail_title_'.$template,
					'title' => __("Title", LWS_ADMIN_PANEL_DOMAIN),
					'type' => 'text',
					'extra' => array( 'maxlength' => 120 )
				),
				array(
					'id' => 'lws_mail_header_'.$template,
					'title' => __("Header", LWS_ADMIN_PANEL_DOMAIN),
					'type' => 'wpeditor',
					'extra' => array(
						'editor_height' => 60,
						'help' => __("A short text describing the mail purpose.", LWS_ADMIN_PANEL_DOMAIN)
					)
				),
				array(
					'id' => 'lws_adminpanel_email_help',
					'type' => 'help',
					'extra' => array(
						'help'=>__("
							Once you've finished the email settings, <b>save your changes</b><br/>
							You will then see the result in the style editor below<br/>
							Select the elements you wish to change and have fun!
						", LWS_ADMIN_PANEL_DOMAIN)
					)
				)
			)
		);

		if( isset($settings['fields']) && is_array($settings['fields']) && !empty($settings['fields']) )
			$mail['fields'] = array_merge($mail['fields'], $settings['fields']);

		if( !empty($settings['css_file_url']) )
		{
			$mail['fields'][] = array(
				'id' => 'lws_mail_template_'.$template,
				'type' => 'stygen',
				'extra' => array(
					'template' => $template,
					'html' => !empty($settings['demo_file_path']) ? $settings['demo_file_path'] : false,
					'css' => $settings['css_file_url'],
					'purpose' => 'mail'
				)
			);
		}

		$mail['fields'][] = array(
			'id' => 'lws_adminpanel_mail_tester_'.$template,
			'title' => __("Receiver Email", LWS_ADMIN_PANEL_DOMAIN),
			'type' => 'text',
			'extra' => array(
				'help' => __("Test your email to see how it looks", LWS_ADMIN_PANEL_DOMAIN),
				'class' => 'lws-ignore-confirm'
			)
		);
		$mail['fields'][] = array(
			'id' => 'lws_adminpanel_mail_tester_btn_'.$template,
			'title' => __("Send test email", LWS_ADMIN_PANEL_DOMAIN),
			'type' => 'button',
			'extra' => array('callback' => array($this, 'test'))
		);

		return $mail;
	}

	function test($id, $data)
	{
		$base = 'lws_adminpanel_mail_tester_btn_';
		$len = strlen($base);
		if( substr($id, 0, $len) == $base && !empty($template=substr($id,$len)) && isset($data['lws_adminpanel_mail_tester_'.$template]) )
		{
			$email = sanitize_email($data['lws_adminpanel_mail_tester_'.$template]);
			if( \is_email($email) )
			{
				do_action('lws_mail_send', $email, $template, new \WP_Error());
				return __("Test email sent.", LWS_ADMIN_PANEL_DOMAIN);
			}
			else
				return __("Test email is not valid.", LWS_ADMIN_PANEL_DOMAIN);
		}
		return false;
	}

}
?>
