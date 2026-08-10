<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// ১. ইউজার লগইন চেক
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit();
}

// ২. সেশনে থাকা লেটেস্ট অর্ডার আইডিটি নিন
// এটি আমরা lg_checkout.php থেকে সেট করেছি
$order_sn = $_SESSION['last_order_sn'] ?? '';

if (empty($order_sn)) {
    echo json_encode(['status' => 'waiting', 'msg' => 'No active order found']);
    exit();
}

$uid = $_SESSION['user_id'];

// ৩. ডাটাবেজে স্ট্যাটাস চেক
// আমরা transactions_fake টেবিল থেকে ঐ নির্দিষ্ট অর্ডারের বর্তমান স্ট্যাটাস দেখবো
$sql = "SELECT status FROM transactions_fake WHERE order_sn = ? AND user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $order_sn, $uid);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $current_status = $row['status'];
    
    if ($current_status == 'success') {
        // পেমেন্ট সফল হলে সেশন থেকে আইডি মুছে দিন যাতে বারবার রিডাইরেক্ট না হয়
        unset($_SESSION['last_order_sn']);
        echo json_encode(['status' => 'success', 'redirect' => '../player/dashboard.php']);
    } else {
        // স্ট্যাটাস যদি এখনও pending থাকে
        echo json_encode(['status' => 'pending']);
    }
} else {
    echo json_encode(['status' => 'not_found']);
}

$stmt->close();
$conn->close();
?>