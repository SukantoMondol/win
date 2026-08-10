<?php
session_start();
header('Content-Type: application/json');
$db_path = file_exists('../includes/db.php') ? '../includes/db.php' : 'includes/db.php';
if (file_exists($db_path)) { require $db_path; }
require_once __DIR__ . '/../includes/referral_system_helper.php';
wcb_referral_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Please login first.'));
    exit;
}

$user_id = intval($_SESSION['user_id']);
$level = intval($_POST['level'] ?? 0);
if ($level <= 0) {
    echo json_encode(array('success' => false, 'message' => 'Invalid level.'));
    exit;
}

$result = wcb_referral_claim_level($conn, $user_id, $level);
echo json_encode($result);
exit;
?>
