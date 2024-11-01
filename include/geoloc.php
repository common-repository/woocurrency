<?php
namespace LWS\WOOCURRENCY;

if( !defined( 'ABSPATH' ) ) exit();

/** Find country from IP address.
 *	Then find/read associated currency. */
class Geoloc
{
	public static function instance()
	{
		static $instance = false;
		if( !$instance )
			$instance = new self();
		return $instance;
	}

	public function install()
	{
		add_filter('woocommerce_currency', array($this, 'overrideCurrency'), 9999);

		add_filter('lws_woocurrency_geoloc_get', array($this, 'get'));
		add_action('lws_woocurrency_geoloc_cache_delete', array($this, 'delCookie'));
	}

	/** forbide instanciation out of instance. */
	protected function __construct()
	{
		$this->originalCurrency = \get_option( 'woocommerce_currency' );
	}

	/**	Get details about an IP.
	 *	@param $ip if empty find out current user ip.
	 *	@return array {ipAdress, country, countryCurrency, currentCurrency, sellingCurrency} */
	public function get($ipAddress = false)
	{
		if( empty($ipAddress) )
		{
			if( !isset($this->currentUserIpDetails) )
			{
				if( is_admin() && !defined('DOING_AJAX') )
					$this->delCookie();
				else if( isset($_COOKIE['lws_woocurrency_j']) && isset($_COOKIE['lws_woocurrency_h']) && \wp_hash($_COOKIE['lws_woocurrency_j']) == $_COOKIE['lws_woocurrency_h'] )
				{
					$json = stripcslashes($_COOKIE['lws_woocurrency_j']);
					$tmp = json_decode($json, true);

					$keys = array('ipAdress','country','countryCurrency','currentCurrency','sellingCurrency');
					if( is_array($tmp) && empty(array_diff($keys, array_keys($tmp))) )
						$this->currentUserIpDetails = $tmp;
				}

				if( !isset($this->currentUserIpDetails) )
				{
					$ipAddress = \apply_filters('lws_woocurrency_geoloc_user_ip_address', \WC_Geolocation::get_ip_address());
					$this->currentUserIpDetails = $this->compute($ipAddress);
					// assume sometimes cookies will not be set because first geoloc use is too late.
					add_action('wp', array($this, "setCookie"), PHP_INT_MAX);
				}
			}
			return $this->currentUserIpDetails;
		}
		else
			return $this->compute($ipAddress);
	}

	/** Save IP details in a cookie to save processing time at each page.
	 * Cookies are part of the HTTP header, so setCookie() must be called before any output is sent to the browser.
	 * Hook 'wp' seems the latest action a http header can be set before the first output. */
	function setCookie()
	{
		if( isset($this->currentUserIpDetails) && !is_admin() )
		{
			$value = json_encode($this->currentUserIpDetails);
			setcookie('lws_woocurrency_j', $value, time()+(60*15), COOKIEPATH, COOKIE_DOMAIN);
			setcookie('lws_woocurrency_h', \wp_hash(addslashes($value)), time()+(60*15), COOKIEPATH, COOKIE_DOMAIN);
		}
	}

	function delCookie()
	{
		if( isset($_COOKIE['lws_woocurrency_j']) ) unset($_COOKIE['lws_woocurrency_j']);
		if( isset($_COOKIE['lws_woocurrency_h']) ) unset($_COOKIE['lws_woocurrency_h']);
		setcookie('lws_woocurrency_j', '', time()-3600, COOKIEPATH, COOKIE_DOMAIN);
		setcookie('lws_woocurrency_h', '', time()-3600, COOKIEPATH, COOKIE_DOMAIN);
	}

	/** Only frontend: override currency based on current IP.
	 *	@return new currency defined for the country of the current Ip.
	*/
	public function overrideCurrency($previousCurrency)
	{
		$this->originalCurrency = $previousCurrency;

		if( is_admin() && !defined('DOING_AJAX') )
			return $previousCurrency;

		// since get_woocommerce_currency is a fallback which trigger this function, we avoid infinit loop.
		if( isset($this->computing) && $this->computing )
			return $previousCurrency;
		$this->computing = true;

		if( !isset($this->sellingCurrency) )
		{
			$details = $this->get();
			$this->sellingCurrency = apply_filters('lws_woocurrency_get_selling_currency', $details['sellingCurrency'], $details);
		}
		$this->computing = false;
		return $this->sellingCurrency;
	}

	/**	@brief find out details about an ip.
	 *
	 *	Test samples:
	 *	*	69.58.178.56 => US
	 *	*	193.5.80.203 => suisse
	 *	*	2406:5a00:e001:3a00:98a6:bfaf:3817:a344  => (IPv6) New Zealand
	 *	*	74.125.206.99 => www.google.com
	 * @return array {ipAdress, country, countryCurrency, currentCurrency, sellingCurrency} */
	private function compute($ipAddress)
	{

		$localisation = \WC_Geolocation::geolocate_ip($ipAddress, true, true);
		$countryName = isset($localisation['country']) && !empty($localisation['country']) ? $localisation['country'] : 'undefined';

		require_once LWS_WOOCURRENCY_INCLUDES . '/countrycurrencylist.php';
		$countryCurrency = CountryCurrencyList::find($countryName);
		if( empty($countryCurrency) )
			$countryCurrency = $this->originalCurrency;

		$sellingCurrency = $this->getSellCurrency($countryName, $countryCurrency);

		return array(
			'ipAdress' => $ipAddress,
			'country' => $countryName,
			'countryCurrency' => $countryCurrency,
			'currentCurrency' => \get_option( 'woocommerce_currency' ),
			'sellingCurrency' => $sellingCurrency
		);
	}

	/**	 @return the selling currency.
	 *	Settings only display currencies coming from conversion table.
	 *	Then we should still be able to convert currency returned here.
	 *
	 *	1. If a country is associated to a curreny, return this currency.
	 *	2. Else return the default currency of woocommerce settings. */
	private function getSellCurrency($countryName, $countryCurrency)
	{
		$foundCurrency = $this->getCurrencyByCountry($countryName);
		if($foundCurrency !== false)
			return $foundCurrency;

//		if( $this->isCurrencySet($countryCurrency) )
//			return $countryCurrency;

		// we return default one;
		return $this->originalCurrency;
	}

	/**	Search the currency associated to the country.
			@return a currency code or false if not found. */
	private function getCurrencyByCountry($country)
	{
		foreach( $this->getCurrencyList() as $ckey=>$cvalue )
		{
			if( !empty($cvalue['country']) )
			{
				$countries = is_array($cvalue['country']) ? $cvalue['country'] : explode(";", $cvalue['country']);
				if( in_array($country, $countries) )
					return $ckey;
			}
		}
		return false;
	}

	/**
		checks directly if currency is set in the list
		@return currency if set, false if not
	*/
	private function isCurrencySet($currency)
	{
		$list = $this->getCurrencyList();
		return isset($list[$currency]);
	}

	/**	read currency/country settings.
	 *	@see CurrencyList
	 *	@return array currency => {country, rounding}. */
	private function getCurrencyList()
	{
		if( !isset($this->currencyList) )
			$this->currencyList = \get_option('lws_woocurrency_currency_array', array());
		return is_array($this->currencyList) ? $this->currencyList : array();
	}
}
?>
