<?php
// Backward-compatible LG Pay callback path. New integration uses lgpay_callback.php.
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/lgpay_gateway_helper.php';

$payload = $_POST;
$result = lgpay_apply_deposit_success($conn, $payload, 'callback');

http_response_code(!empty($result['success']) ? 200 : intval($result['http_code'] ?? 400));
echo !empty($result['success']) ? 'ok' : 'fail';
