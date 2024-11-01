<?php
namespace LWS\WOOCURRENCY;
if( !defined( 'ABSPATH' ) ) exit();


class CurrencyList extends \LWS\Adminpanel\EditList\Source
{
	public function __construct()
	{
		add_filter('lws_woocurrency_currencylist_input', array($this, 'inputMain'), 10);
		add_filter('lws_woocurrency_currencylist_input', array($this, 'inputRounding'), 100);
	}

	function input()
	{
		return implode("\n", apply_filters('lws_woocurrency_currencylist_input', array()));
	}

	private function field($label, $input, $class='')
	{
		$field = "<label><span class='lws-editlist-opt-title$class'>{$label}</span>";
		$field .= "<span class='lws-editlist-opt-input$class'>{$input}</span></label>";
		return $field;
	}

	function inputMain($table)
	{
		if( !isset($table['main']) )
		{
			$currencyTitle = __("Currency", LWS_WOOCURRENCY_DOMAIN);
			$countriesTitle = __("Countries", LWS_WOOCURRENCY_DOMAIN);

			$str = "<fieldset class='lws-editlist-fieldset col50 pdr5'>";
			$str .= "<input type='hidden' name='saved'>";
			$str .= "<div class='lws-editlist-title'>$currencyTitle</div>";
			//currency autocomplete
			$str .= $this->field(
				$currencyTitle,
				\LWS\Adminpanel\Pages\Field\Autocomplete::compose('key', array('source'=>$this->getCurrencyLabels(), 'name'=>'currency'))
			);
			$str .= $this->field(
				$countriesTitle,
				\LWS\Adminpanel\Pages\Field\LacChecklist::compose('country', array('source'=>$this->getCountryLabels(), 'placeholder' => __("Select countries...", LWS_WOOCURRENCY_DOMAIN)))
			);
			$str .= "</fieldset>";

			$table['main'] = $str;
		}
		return $table;
	}

	function inputRounding($table)
	{
		if( !isset($table['rounding']) )
		{
			$roundTitle = __("Price rounding", LWS_WOOCURRENCY_DOMAIN);
			$decimal = __("Decimal count", LWS_WOOCURRENCY_DOMAIN);

			$str = "<fieldset class='lws-editlist-fieldset lws-woocurrency-currencylist-rounding'>";
			$str .= "<div class='lws-editlist-title'>$roundTitle</div>";
			$str .= $this->field($decimal, "<input type='text' name='rounding' pattern='\\d+'>");
			$str .= "</fieldset>";

			$table['rounding'] = $str;
		}
		return $table;
	}

	/**	@param $code (false|string) if a code string is set, return its label if any.
	 *	@return array(country_code => label)|string */
	private function getCountryLabels($code=false)
	{
		if( !isset($this->countryLabels) )
		{
			$geo = new \WC_Geo_IP();
			$codes = $geo->GEOIP_COUNTRY_CODES;
			$labels = $geo->GEOIP_COUNTRY_NAMES;
			require_once LWS_WOOCURRENCY_INCLUDES . '/countrycurrencylist.php';
			$cc = CountryCurrencyList::get();

			$this->countryLabels = array();
			for( $i=0 ; $i<count($codes) && $i<count($labels) ; ++$i )
			{
				if( !(empty($codes[$i]) || empty($labels[$i])) && isset($cc[$codes[$i]]) )
				{
					$this->countryLabels[$codes[$i]] = array(
						'value' => $codes[$i],
						'label' => __($labels[$i], 'woocommerce') // expect WooCommerce translate its country names.
					);
				}
			}
		}
		if( $code !== false )
		{
			return isset($this->countryLabels[$code]) ? $this->countryLabels[$code]['label'] : $code;
		}
		return $this->countryLabels;
	}

	/** Return only currency we can convert.
	 *	@return array(currency => label) */
	private function getCurrencyLabels()
	{
		if( !isset($this->currencyLabels) )
		{
			$rates = \apply_filters('lws_woocurrency_conversion_rates', array());
			$labels = \get_woocommerce_currencies();

			$this->currencyLabels = array();
			foreach( array_keys($rates) as $currency )
			{
				$this->currencyLabels[$currency] = array(
					'value' => $currency,
					'label' => (isset($labels[$currency]) ? $labels[$currency] : $currency)
				);
			}
		}
		return $this->currencyLabels;
	}

	function labels()
	{
		return apply_filters('lws_woocurrency_currencylist_labels', array(
			"key"           => array(__("Symbol", LWS_WOOCURRENCY_DOMAIN), "10%"),
			"currency"           => array(__("Currency", LWS_WOOCURRENCY_DOMAIN), "20%"),
			"countries"            => __("Associated countries", LWS_WOOCURRENCY_DOMAIN)
		));
	}

	/** Get or Set currency data.
	 * @param $buffer if array: save data; else: read data.
	 * @return currency data */
	protected function data($buffer=false)
	{
		if( $buffer !== false && is_array($buffer) )
		{
			\update_option('lws_woocurrency_currency_array', $buffer);
			\do_action('lws_woocurrency_geoloc_cache_delete');
			$this->buffer = $buffer;
		}
		else
		{
			if( !isset($this->buffer) )
				$this->buffer = \get_option('lws_woocurrency_currency_array', array());
		}
		return $this->buffer;
	}

	protected function filteredData()
	{
		if( !isset($this->filtered) )
		{
			$this->filtered = array();
			$search = isset($_GET['currencyListSearch']) ? trim($_GET['currencyListSearch']) : '';
			$labels = \get_woocommerce_currencies();

			foreach( $this->data() as $currency => $data )
			{
				$key = $data['key'];
				$label = isset($labels[$key]) ? $labels[$key] : $key;
				$countries = $this->getCountryNames($data['country']);

				if( empty($search) || false !== stripos($key, $search) || false !== stripos($label, $search) || false !== stripos($countries, $search) )
					$this->filtered[] = array(
						'saved' => $key,
						'key' => $key,
						'currency' => $label,
						'country' => base64_encode(json_encode($data['country'])),
						'countries' => $countries,
						'rounding' => intval($data['rounding'])
					);
			}
		}
		return $this->filtered;
	}

	function read($limit)
	{
		$table = $this->filteredData();

		if( !empty($limit) )
			$table = array_slice($table, $limit->offset, $limit->count, true);

		return \apply_filters('lws_woocurrency_currencylist_read', $table, $this->data(), $limit);
	}

	function total()
	{
		return count($this->filteredData());
	}

	/** @return (string) the names of the given country codes as a string.
	 * @param $codes (array|string) an array of 2 character codes or a single one. */
	private function getCountryNames($codes)
	{
		if( !is_array($codes) )
			$codes = array($codes);

		$countries = array();
		foreach( $codes as $c )
			$countries[] = $this->getCountryLabels($c);

		sort($countries, SORT_STRING);
		return implode(', ', $countries);
	}

	function erase( $line )
	{
		if( !empty($line) && isset($line['saved']) )
		{
			$key = strtoupper(sanitize_key($line['saved']));
			$buffer = $this->data();

			if( isset($buffer[$key]) )
				unset($buffer[$key]);

			$this->data($buffer);
			return true;
		}
		return false;
	}

	function write($line)
	{
		$err = \apply_filters(
			'lws_woocurrency_currencylist_invalid_array',
			\LWS\Adminpanel\EditList\Source::invalidArray(
				$line, array(
					'saved' => 's0',
					'key' => 's+',
					'currency' => 's+',
					'country' => 'A+/s+',
					'rounding' => 'io'
				),
				false, true, array(
					'country' => __("Country", LWS_WOOCURRENCY_DOMAIN),
					'currency' => __("Currency", LWS_WOOCURRENCY_DOMAIN),
					'rounding' => __("Rounding", LWS_WOOCURRENCY_DOMAIN)
				)
			),
			$line
		);
		if( false !== $err )
			return \LWS\Adminpanel\EditList\UpdateResult::err($err);

		$buffer = $this->data();
		$key = strtoupper(sanitize_key($line['key']));
		$saved = isset($line['saved']) ? strtoupper(sanitize_key($line['saved'])) : false;

		if( $key != $saved )
		{
			if( isset($buffer[$key]) )
				return \LWS\Adminpanel\EditList\UpdateResult::err(sprintf(__("The currency [%s/%s] is already defined.", LWS_WOOCURRENCY_DOMAIN), $key, $line['currency']));

			if( isset($buffer[$saved]) )
				unset($buffer[$saved]);
		}

		foreach( $buffer as $other => $value )
		{
			if( $other != $key )
			{
				$inter = array_intersect($line['country'], $value['country']);
				if( !empty($inter) )
				{
					$txt = _n("The country %s is already associated to %s.", "The countries %s are already associated to %s.", count($inter), LWS_WOOCURRENCY_DOMAIN);
					return \LWS\Adminpanel\EditList\UpdateResult::err(sprintf($txt, $this->getCountryNames($inter), $value['key']));
				}
			}
		}

		$buffer[$key] = array(
			'key' => $line['key'],
			'country' => $line['country'],
			'rounding' => intval($line['rounding'])
		);
		ksort($buffer);
		$buffer = $this->data(\apply_filters('lws_woocurrency_currencylist_write', $buffer, $line));

		$buffer[$key]['currency'] = $line['currency'];
		$buffer[$key]['saved'] = $key;
		$buffer[$key]['countries'] = $this->getCountryNames($buffer[$key]['country']);
		$buffer[$key]['country'] = base64_encode(json_encode($buffer[$key]['country']));
		return \apply_filters('lws_woocurrency_currencylist_write_return', $buffer[$key], $line);
	}

}
?>
