<?php
// Backward-compatible LG Pay config wrapper.
// New integration stores LG Pay credentials in Admin Panel -> Payment Gateway Settings.
if (file_exists(__DIR__ . '/lgpay_gateway_helper.php')) {
    require_once __DIR__ . '/lgpay_gateway_helper.php';
}

$__lg_settings = (isset($conn) && function_exists('lgpay_get_settings')) ? lgpay_get_settings($conn) : array();
if (!defined('LG_APP_ID')) { define('LG_APP_ID', trim((string)($__lg_settings['merchant_code'] ?? ''))); }
if (!defined('LG_KEY')) { define('LG_KEY', trim((string)($__lg_settings['secret_code'] ?? ''))); }
if (!defined('LG_GATEWAY_URL')) { define('LG_GATEWAY_URL', (function_exists('lgpay_endpoint') && isset($conn)) ? lgpay_endpoint($conn, 'order/create') : 'https://www.lg-pay.com/api/order/create'); }
if (!defined('LG_NOTIFY_URL')) { define('LG_NOTIFY_URL', function_exists('lgpay_url') ? lgpay_url('/api/lgpay_callback.php') : '/api/lgpay_callback.php'); }
if (!defined('LG_RETURN_URL')) { define('LG_RETURN_URL', function_exists('lgpay_url') ? lgpay_url('/player/lgpay_deposit_return.php') : '/player/lgpay_deposit_return.php'); }

if (!function_exists('generate_lg_sign')) {
    function generate_lg_sign($data, $key) {
        if (function_exists('lgpay_md5_sign')) { return lgpay_md5_sign($data, $key); }
        if (isset($data['sign'])) { unset($data['sign']); }
        foreach ($data as $k => $v) { if ($v === null || $v === '') { unset($data[$k]); } }
        ksort($data);
        $string = urldecode(http_build_query($data));
        $string = trim($string) . "&key=" . $key;
        return strtoupper(md5($string));
    }
}
?>
