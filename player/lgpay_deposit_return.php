<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/lgpay_gateway_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$uid = intval($_SESSION['user_id']);
$orderNo = trim((string)($_GET['order_no'] ?? ''));
$statusText = 'Payment is processing';
$message = 'If payment is completed, your balance will update automatically after gateway verification.';
$ok = false;

if ($orderNo !== '') {
    $stmt = $conn->prepare("SELECT status FROM payment_gateway_orders WHERE order_no=? AND user_id=? AND gateway='lgpay' AND type='deposit' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('si', $orderNo, $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            if ($row['status'] !== 'success') { @lgpay_sync_deposit_order($conn, $orderNo); }
            $stmt2 = $conn->prepare("SELECT status FROM payment_gateway_orders WHERE order_no=? AND user_id=? AND gateway='lgpay' AND type='deposit' LIMIT 1");
            if ($stmt2) { $stmt2->bind_param('si', $orderNo, $uid); $stmt2->execute(); $res2 = $stmt2->get_result(); if ($res2 && $row2 = $res2->fetch_assoc()) { $row = $row2; } $stmt2->close(); }
            if ($row['status'] === 'success') { $ok = true; $statusText = 'Payment Successful'; $message = 'Your deposit has been verified and added to your balance.'; }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-3xl p-7 shadow-xl text-center">
        <div class="w-16 h-16 <?php echo $ok ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600'; ?> rounded-full flex items-center justify-center mx-auto mb-5">
            <i class="fas <?php echo $ok ? 'fa-check' : 'fa-clock'; ?> text-3xl"></i>
        </div>
        <h1 class="text-xl font-black text-gray-800 mb-2"><?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="text-sm text-gray-600 mb-6"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="dashboard.php" class="block w-full bg-[#0d4a3a] text-white font-bold py-3 rounded-xl">Back to Dashboard</a>
        <a href="deposit.php" class="block w-full mt-3 bg-gray-100 text-gray-700 font-bold py-3 rounded-xl">Back to Deposit</a>
    </div>
</body>
</html>
