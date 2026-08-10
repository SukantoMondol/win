<?php
session_start();
header('Content-Type: application/json');
$db_path = file_exists('../includes/db.php') ? '../includes/db.php' : 'includes/db.php';
if (file_exists($db_path)) { require $db_path; } else { echo json_encode(array('success' => false, 'message' => 'Database connection not found.')); exit; }
require_once __DIR__ . '/../includes/vip_system_helper.php';
wcb_vip_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Login required.'));
    exit;
}

$user_id = intval($_SESSION['user_id']);
$points = intval($_POST['points'] ?? 0);
$state = wcb_vip_state($conn, $user_id);
$settings = $state['settings'];

if (intval($settings['is_enabled']) !== 1) {
    echo json_encode(array('success' => false, 'message' => 'VIP system is disabled.'));
    exit;
}

$min_points = max(1, intval($settings['min_convert_points'] ?? 10));
$available = intval($state['available_vp'] ?? 0);
$ratio = max(1, (float)($settings['conversion_ratio'] ?? 60));

if ($points < $min_points) {
    echo json_encode(array('success' => false, 'message' => 'Minimum VP required: ' . $min_points));
    exit;
}

if ($points > $available) {
    echo json_encode(array('success' => false, 'message' => 'Not enough VP.'));
    exit;
}

$real_amount = floor(($points / $ratio) * 100) / 100;
if ($real_amount <= 0) {
    echo json_encode(array('success' => false, 'message' => 'Invalid conversion amount.'));
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id=? FOR UPDATE");
    if (!$stmt) { throw new Exception('User lock failed.'); }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows <= 0) { throw new Exception('User not found.'); }
    $row = $res->fetch_assoc();
    $before = (float)($row['balance'] ?? 0);
    $after = $before + $real_amount;
    $stmt->close();

    $up = $conn->prepare("UPDATE users SET balance=? WHERE id=?");
    if (!$up) { throw new Exception('Balance update failed.'); }
    $up->bind_param('di', $after, $user_id);
    if (!$up->execute()) { throw new Exception('Balance update failed.'); }
    $up->close();

    $ins = $conn->prepare("INSERT INTO vip_conversions (user_id, points, real_amount, ratio, status, balance_before, balance_after, created_at) VALUES (?, ?, ?, ?, 'completed', ?, ?, NOW())");
    if (!$ins) { throw new Exception('Conversion save failed.'); }
    $ins->bind_param('iidddd', $user_id, $points, $real_amount, $ratio, $before, $after);
    if (!$ins->execute()) { throw new Exception('Conversion save failed.'); }
    $conversion_id = $conn->insert_id;
    $ins->close();

    if (wcb_vip_table_exists($conn, 'transactions_fake')) {
        $method = 'VIP Point Conversion';
        $type = 'bonus';
        $status = 'approved';
        $note = 'VIP conversion #' . $conversion_id;
        $tx = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, status, admin_note, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($tx) {
            $tx->bind_param('isdsss', $user_id, $type, $real_amount, $method, $status, $note);
            $tx->execute();
            $tx->close();
        }
    }

    $conn->commit();
    echo json_encode(array('success' => true, 'points' => $points, 'real_amount' => $real_amount, 'balance' => $after));
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
}
?>
