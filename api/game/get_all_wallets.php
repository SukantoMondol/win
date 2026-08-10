<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/game_api_helper.php';
require_once __DIR__ . '/../../includes/gamblly_api_helper.php';

if (!isset($_SESSION['user_id'])) {
    gamblly_api_response(array('success' => false, 'code' => 401, 'message' => 'Unauthorized'), 401);
}

$userId = (int)$_SESSION['user_id'];
$localBalance = 0.0;
$stmt = @$conn->prepare("SELECT balance FROM users WHERE id=? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) { $localBalance = (float)$res->fetch_assoc()['balance']; }
    $stmt->close();
}

$result = gamblly_api_wallet_request($conn, $userId, 'balance_url');
$gameBalance = isset($result['amount']) ? (float)$result['amount'] : 0.0;

gamblly_api_response(array(
    'success' => !empty($result['success']),
    'code' => !empty($result['success']) ? 0 : 500,
    'message' => !empty($result['message']) ? $result['message'] : (!empty($result['success']) ? 'Success' : 'Balance request failed'),
    'local_balance' => number_format($localBalance, 2, '.', ''),
    'game_balance' => number_format($gameBalance, 2, '.', ''),
    'data' => array(
        'thidGameBalanceList' => array(
            array('platform' => 'local_wallet', 'balance' => number_format($localBalance, 2, '.', '')),
            array('platform' => 'gamblly', 'balance' => number_format($gameBalance, 2, '.', ''))
        )
    )
), !empty($result['success']) ? 200 : 500);
?>
