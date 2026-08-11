<?php
// player/nekpay_deposit_start.php
// Initiate NEKpay Deposit Order and redirect player to checkout URL

session_start();
require_once __DIR__ . '/../includes/auth_session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/nekpay_gateway_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];
$amount = floatval($_POST['amount'] ?? 0);
$method = sanitize($conn, $_POST['payment_method'] ?? 'bKash');
$promo_id = intval($_POST['promo_id'] ?? 0);

if ($amount < 100) {
    die("Minimum deposit amount is 100 BDT.");
}

nekpay_ensure_schema($conn);

$result = nekpay_create_deposit_order($conn, $uid, $amount, $method, $promo_id);

if (!empty($result['success']) && !empty($result['pay_url'])) {
    header("Location: " . $result['pay_url']);
    exit();
} else {
    $err = $result['message'] ?? 'Failed to initiate NEKpay deposit.';
    die("<h3>NEKpay Error: " . htmlspecialchars($err) . "</h3><a href='deposit.php'>Go Back</a>");
}
?>
