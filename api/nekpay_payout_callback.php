<?php
// api/nekpay_payout_callback.php
// NEKpay Asynchronous Payout / Withdrawal Callback (IPN)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/nekpay_gateway_helper.php';

$rawBody = file_get_contents('php://input');
$payload = $_POST;

if (empty($payload) && !empty($rawBody)) {
    parse_str($rawBody, $parsed);
    if (!empty($parsed)) {
        $payload = $parsed;
    } else {
        $json = json_decode($rawBody, true);
        if (is_array($json)) {
            $payload = $json;
        }
    }
}

error_log("NEKpay Payout IPN Received: " . json_encode($payload));

http_response_code(200);
echo "success";
exit();
?>
