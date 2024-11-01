<?php
namespace LWS\WOOCURRENCY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

use \LWS\Adminpanel as AP;


class Admin
{
	public function __construct()
	{
		lws_register_pages($this->pages());
	}

	protected function pages()
	{
		$pa = array(
			array(
				'id' => 'woocommerce',
				'prebuild' => '1'
			),
			array(
				'id' => LWS_WOOCURRENCY_DOMAIN, // id of the page
				'title' => __("WooCurrency", LWS_WOOCURRENCY_DOMAIN),
				'rights' => 'manage_options', // acces restriction to visit the page
				'tabs' => array(
					'settings' => array(
						'title' => __("General Settings", LWS_WOOCURRENCY_DOMAIN),
						'id' => 'settings',
						'groups' => array(
							'currencies' => $this->currenciesSettings(),
							'ipinfos' => $this->ipInfos()
						),
						'nosave' => true
					)
				)
			)
		);
		return $pa;
	}

	/** This group display current user IP information. */
	protected function ipInfos()
	{
		$allInfos = Geoloc::instance()->get();
		return array(
			'title' => __("IP Test", LWS_WOOCURRENCY_DOMAIN),
			'delayedFunction' => array($this, 'ipTestGroup'),
			'fields' => array(
				array(
					'id' => 'lws_woocurrency_test_ip',
					'title' => __("Tested IP", LWS_WOOCURRENCY_DOMAIN),
					'type' => 'text',
					'extra' => array(
						'gizmo' => true,
						'class' => 'lws-ignore-confirm',
						'help' => sprintf(__("Invalid value fallbacks to current IP detection. Your IP is %s", LWS_WOOCURRENCY_DOMAIN), "<span id='lws_woocurrency_user_ip' class='lws_user_ip'></span>")
					)
				),
				array(
					'id' => 'lws_woocurrency_test_button',
					'title' => __("Test IP", LWS_WOOCURRENCY_DOMAIN),
					'type' => 'button',
					'extra' => array(
						'callback' => array($this, 'testIp')
					)
				)
			)
		);
	}

	/** Placed in a function to avoid ip test each time the site is loaded.
	 * Then only executed if group really displayed. */
	function ipTestGroup()
	{
		require_once LWS_WOOCURRENCY_INCLUDES . '/geoloc.php';
		$details = Geoloc::instance()->get();
		$ipAdress = \esc_attr($details['ipAdress']);
		$text = addslashes($this->formatIpDetails($details));

		echo "<script type='text/javascript'>
			document.getElementById('lws_woocurrency_user_ip').innerHTML='$ipAdress';
			document.getElementsByName('lws_woocurrency_test_ip')[0].placeholder='$ipAdress';
			var lws_woocurrency_user_response = document.createElement('div');
			lws_woocurrency_user_response.className='lws-adm-btn-trigger-response';
			lws_woocurrency_user_response.innerHTML='$text';
			var lws_woocurrency_test_button = document.getElementById('lws_woocurrency_test_button');
			lws_woocurrency_test_button.parentNode.insertBefore(lws_woocurrency_user_response, lws_woocurrency_test_button.nextSibling);
		</script>";
	}

	/** test IP button callback */
	function testIp($btnId, $data=array())
	{
		if( $btnId != 'lws_woocurrency_test_button' || !isset($data['lws_woocurrency_test_ip']) ) return false;

		require_once LWS_WOOCURRENCY_INCLUDES . '/geoloc.php';
		return $this->formatIpDetails(Geoloc::instance()->get($data['lws_woocurrency_test_ip']));
	}

	/** @param $ipInfos @see Geoloc::get
	 * @return html code */
	protected function formatIpDetails($ipInfos)
	{
		$str = "<div class='lws-field-expl'>%s <span class='lws_woocurrency_test_result'>%s</span></div><br/>";
		$text  = sprintf($str, __("Tested IP is", LWS_WOOCURRENCY_DOMAIN), $ipInfos['ipAdress']);
		$text .= sprintf($str, __("Country should be:", LWS_WOOCURRENCY_DOMAIN), $ipInfos['country']);
		$text .= sprintf($str, __("Country currency should be:", LWS_WOOCURRENCY_DOMAIN), $ipInfos['countryCurrency']);
		$text .= sprintf($str, __("Selling currency will be:", LWS_WOOCURRENCY_DOMAIN), $ipInfos['sellingCurrency']);
		return $text;
	}

	/** Editlist to set currencies. */
	protected function currenciesSettings()
	{
		require_once LWS_WOOCURRENCY_INCLUDES . '/currencylist.php';
		return array(
			'title' => __("Currency List"),
			'text' => __("Assign currencies to one or multiple countries.", LWS_WOOCURRENCY_DOMAIN),
			'editlist' => lws_editlist(
				'lws_woocurrency_c_settings',
				'key',
				new CurrencyList(),
				AP\EditList::ALL,
				array(
					new \LWS\Adminpanel\EditList\FilterSimpleField('currencyListSearch', __('Search...', LWS_WOOCURRENCY_DOMAIN))
				)
			)
		);
	}

}

?>
