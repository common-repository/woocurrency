<?php
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();
@include_once dirname(__FILE__) . '/modules/woocurrency-pro/uninstall.php';

\delete_option('lws_woocurrency_currency_array');
\delete_option('lws_woocurrency_rates');
\delete_option('lws_woocurrency_rates_checkup');

?>
