<?php
namespace LWS\WOOCURRENCY;

if( !defined( 'ABSPATH' ) ) exit();

/** This class allow overriding currency by user country.
 * Re-compute product prices accordingly.
 * Read euro conversion from BCE. */
class PriceOverride
{
	/**
		All must be started somewhere
	*/
	public function __construct()
	{
		// big priority to be one of the last called
		add_filter('woocommerce_product_get_price', array($this, 'getAnyPrice'), 9999, 2);
		add_filter('woocommerce_product_get_sale_price', array($this, 'getSalePrice'), 9999, 2);
		add_filter('woocommerce_product_get_regular_price',array($this, 'getRegularPrice'), 9999, 2);
		add_filter('woocommerce_coupon_get_amount',array($this, 'getCouponPrice'), 9999, 2);

		add_filter('lws_woocurrency_conversion_rates', array($this, 'getConversionRates'));
		add_filter('lws_woocurrency_price_convert', array($this, 'convert'), 10, 4);
	}

	/**
		@see getProductPrice
	*/
	function getCouponPrice($originPrice, $coupon)
	{
		$couponTypes = \apply_filters('lws_woocurrency_price_convert_coupon_types', array('fixed_cart', 'fixed_product'));
		if( in_array($coupon->get_discount_type(), $couponTypes) )
			$originPrice = $this->getProductPrice($originPrice, $coupon, true);
		return $originPrice;
	}

	/** Must look if product is on sale.
	 * @see getProductPrice */
	function getAnyPrice($originPrice, $product)
	{
		if( isset($this->lock) && $this->lock )
			return $originPrice;
		$this->lock = true;

		$regular = empty($product) ? true : !$product->is_on_sale();

		$this->lock = false;
		return $this->getProductPrice($originPrice, $product, $regular);
	}

	/** @see getProductPrice */
	function getSalePrice($originPrice, $product)
	{
		return $this->getProductPrice($originPrice, $product, false);
	}

	/** @see getProductPrice */
	function getRegularPrice($originPrice, $product)
	{
		return $this->getProductPrice($originPrice, $product, true);
	}

	/** Do nothing in admin screen (test is_admin, but anyway used hooks shouldn't be called on 'edit' context).
	 * Convert product price to currency set for customer country (find out by IP).
		@param $originPrice sell price from woocommerce
		@param $product the product the price come from
		@param $regular (null|bool) true: regular, false: on-sale, null: unknow.
		@return price according to currency found
	*/
	function getProductPrice($originPrice, $product, $regular=null)
	{
		if( is_admin() && !defined('DOING_AJAX') )
			return $originPrice;
		if( !is_numeric($originPrice) )
			return $originPrice;
		if( empty($originPrice) )
			return $originPrice;

		if( isset($this->lock) && $this->lock )
			return $originPrice;
		$this->lock = true;

		if( !isset($this->details) )
		{
			require_once LWS_WOOCURRENCY_INCLUDES . '/geoloc.php';
			$this->details = Geoloc::instance()->get();
		}
		$price = $this->convert($originPrice, $this->details['currentCurrency'], $this->details['sellingCurrency']);
		$price = apply_filters('lws_woocurrency_product_get_price', $price, $originPrice, $product, $this->details, $regular);

		$this->lock = false;
		return $price;
	}

	/**	@brief converts origin price to new price using currency sets.
	 * @param $rounding (false|int) if not false override the currency rounding setting.
	 *
	 *	Since settings should not allow currency out of conversion table,
	 *	We should still be able to find a conversion. */
	public function convert($originalPrice, $originalCurrency, $newCurrency, $rounding = false)
	{
		if( $originalCurrency == $newCurrency )
			$newPrice = $originalPrice;
		else
		{
			$rates = $this->getConversionRates();
			if( !isset($rates[$originalCurrency]) || !isset($rates[$newCurrency]) )
			{
				$message = "Attemps to convert a price to or from unknown currency ($originalCurrency ==> $newCurrency). Return a deterrent price to mark it.";
				error_log($message);
				\wc_get_logger()->error($message, array('source' => LWS_WOOCURRENCY_DOMAIN));

				$newPrice = PHP_INT_MAX;
				\wc_add_notice(__("An error occured with the requested currency. Please contact an administrator.", LWS_WOOCURRENCY_DOMAIN), 'error');
			}
			else
			{
				$newPrice = $rates[$newCurrency] * $originalPrice / $rates[$originalCurrency];
			}
		}

		return round($newPrice, ($rounding !== false) ? $rounding : $this->getRounding($newCurrency));
	}

	/** @return number of decimal set to round a price for the given currency. */
	private function getRounding($currency)
	{
		$currencyRounding = $wcRounding = \get_option('woocommerce_price_num_decimals', 2);

		if( !isset($this->settings) )
			$this->settings = \get_option('lws_woocurrency_currency_array', array());

		if( is_array($this->settings) && isset($this->settings[$currency]) && isset($this->settings[$currency]['rounding']) )
			$currencyRounding = $this->settings[$currency]['rounding'];

		return is_numeric($currencyRounding) ? $currencyRounding: (is_numeric($wcRounding) ? $wcRounding : 2);
	}

	/** @param not used, provided to make this function callable as filter.
	 *	@brief Get currencies conversion rates.
	 *
	 *	Rates are read from BCE, daily updated.
	 *	Rates should be based on Euro, but we ensure a EUR=1 at worst in the returned list.
	 *
	 *	Settings should not allow to use a currency that does not exist in this conversion table.
	 *	@return array(currency_code => rate) */
	public function getConversionRates($rates=null)
	{
		if( !isset($this->conversionRates) )
		{
			$this->conversionRates = \get_option('lws_woocurrency_rates', '');
			if( empty($this->conversionRates) || $this->isRatesExpired() )
			{
				$this->conversionRates = array();
				$file = \apply_filters('lws_woocurrency_euro_rates_url', "http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml");
				$XML = simplexml_load_file($file);

				if( $XML == false )
					error_log("Cannot read euro conversion rates from url: '$file'");
				else if( !isset($XML->Cube) || !isset($XML->Cube->Cube) || !isset($XML->Cube->Cube->Cube) )
					error_log("Unexpected format in euro conversion rates from url: '$file'");
				else foreach($XML->Cube->Cube->Cube as $rate)
				{
					$currency = strtoupper(sanitize_key(strval($rate["currency"])));
					$current_rate = strval($rate["rate"]);
					$this->conversionRates[$currency] = $current_rate;
				}

				if( !isset($this->conversionRates['EUR']) )
					$this->conversionRates['EUR'] = 1;

				\update_option('lws_woocurrency_rates', $this->conversionRates);
			}
		}
		return $this->conversionRates;
	}

	/** Are the saved rates too old. */
	private function isRatesExpired()
	{
		$check = false;
		$today = new \DateTime();

		$ratesCheckup = \get_option('lws_woocurrency_rates_checkup', '');
		if( empty($ratesCheckup) )
			$check = true;
		else
		{

			$delay = intval(apply_filters('lws_woocurrency_rates_checkup_period', '1'));
			if($today->diff($ratesCheckup)->days >= $delay)
				$check = true;
		}

		\update_option('lws_woocurrency_rates_checkup', $today);
		return $check;
	}
}

?>
