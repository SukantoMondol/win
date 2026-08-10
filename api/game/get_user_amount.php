<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/game_api_helper.php';
require_once __DIR__ . '/../../includes/gamblly_api_helper.php';

if (!isset($_SESSION['user_id'])) {
    gamblly_api_response(array('success' => false, 'code' => 401, 'message' => 'Unauthorized'), 401);
}

$userId = (int)$_SESSION['user_id'];
$result = gamblly_api_wallet_request($conn, $userId, 'withdraw_url');
$amount = isset($result['amount']) ? (float)$result['amount'] : 0.0;

$localBalance = 0.0;
if (!empty($result['success']) && $amount > 0) {
    @$conn->begin_transaction();
    $stmt = @$conn->prepare("SELECT balance FROM users WHERE id=? LIMIT 1 FOR UPDATE");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $localBalance = round((float)$row['balance'] + $amount, 6);
            $up = @$conn->prepare("UPDATE users SET balance=? WHERE id=? LIMIT 1");
            if ($up) { $up->bind_param('di', $localBalance, $userId); $up->execute(); $up->close(); }
            @$conn->commit();
        } else {
            @$conn->rollback();
            gamblly_api_response(array('success' => false, 'code' => 404, 'message' => 'Player not found'), 404);
        }
        $stmt->close();
    } else {
        @$conn->rollback();
        gamblly_api_response(array('success' => false, 'code' => 500, 'message' => 'Database error'), 500);
    }
} else {
    $stmt = @$conn->prepare("SELECT balance FROM users WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) { $localBalance = (float)$res->fetch_assoc()['balance']; }
        $stmt->close();
    }
}

gamblly_api_response(array(
    'success' => !empty($result['success']),
    'code' => !empty($result['success']) ? 0 : 500,
    'message' => !empty($result['message']) ? $result['message'] : (!empty($result['success']) ? 'Success' : 'Withdraw request failed'),
    'withdraw_amount' => number_format($amount, 2, '.', ''),
    'local_balance' => number_format($localBalance, 2, '.', '')
), !empty($result['success']) ? 200 : 500);
?>
