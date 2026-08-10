<?php
// Backward-compatible LG Pay checkout endpoint.
// The active user deposit flow uses LG Pay only.
ob_start();
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/propay_gateway_helper.php';
require_once __DIR__ . '/../includes/lgpay_gateway_helper.php';

if (function_exists('propay_ensure_schema')) { @propay_ensure_schema($conn); }
lgpay_ensure_schema($conn);
if (function_exists('wcb_force_lgpay_only')) { @wcb_force_lgpay_only($conn); }

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(array('status' => 0, 'msg' => 'Unauthorized access'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header('Location: ../player/deposit.php');
    exit;
}

$uid = intval($_SESSION['user_id']);
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$method = $_POST['method'] ?? '';
$promoId = isset($_POST['promo_id']) ? intval($_POST['promo_id']) : 0;
$channel = $_POST['channel'] ?? 'LGPay';

$result = lgpay_create_deposit_order($conn, $uid, $amount, $method, $promoId, $channel);
if (!empty($result['success']) && !empty($result['redirect_url'])) {
    ob_end_clean();
    header('Location: ' . $result['redirect_url']);
    exit;
}

ob_end_clean();
http_response_code(400);
echo '<h3>Gateway Error</h3><p>' . htmlspecialchars($result['message'] ?? 'Unable to start LG Pay deposit.', ENT_QUOTES, 'UTF-8') . '</p><p><a href="../player/deposit.php">Back to Deposit</a></p>';
