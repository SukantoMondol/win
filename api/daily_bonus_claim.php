<?php
define('GAME_API_SKIP_MAINTENANCE', true);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bonus_system_helper.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Please login first.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$result = wcb_daily_bonus_claim($conn, intval($_SESSION['user_id']));
http_response_code(!empty($result['success']) ? 200 : 400);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
