<?php
// api/nekpay_callback.php
// NEKpay Asynchronous Deposit Callback (IPN)

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

if (!empty($_GET)) {
    $payload = array_merge($_GET, $payload);
}

error_log("NEKpay IPN Received: " . json_encode($payload));

if (isset($conn) && !$conn->connect_error) {
    nekpay_ensure_schema($conn);
    $ok = nekpay_apply_deposit_success($conn, $payload, 'callback');
    if ($ok) {
        http_response_code(200);
        echo "success";
        exit();
    }
}

http_response_code(400);
echo "fail";
exit();
?>
