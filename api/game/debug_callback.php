<?php
/**
 * TEMPORARY DEBUG FILE - Delete after fixing
 * URL: https://bajixwin.com/api/game/debug_callback.php
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/game_api_helper.php';
require_once __DIR__ . '/../../includes/gamblly_api_helper.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }
if (!is_array($data)) { $data = []; }

$config = gamblly_api_get_config($conn);

$dbSettings = [];
$r = $conn->query("SELECT setting_key, setting_value FROM game_api_settings WHERE setting_key IN ('api_token','secret_key','agent_code','gamblly_api_key','api_endpoint') LIMIT 10");
if ($r) { while ($row = $r->fetch_assoc()) { $dbSettings[$row['setting_key']] = $row['setting_value']; } }

$playerRaw = gamblly_api_pick($data, array('player_uid', 'member_account', 'member', 'username', 'user_id', 'uid', 'player_id'), '');
$userId = gamblly_api_extract_player_id($playerRaw, $config);

$userFound = false;
$userBalance = null;
if ($userId > 0) {
    $stmt = $conn->prepare("SELECT id, username, balance FROM users WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $u = $res->fetch_assoc();
            $userFound = true;
            $userBalance = $u['balance'];
        }
        $stmt->close();
    }
}

$receivedApiKey = gamblly_api_pick($data, array('api_key', 'agency_uid', 'APIKey', 'key', 'token'), '');
$expectedApiKey = $config['api_key'] ?? '';
$keyMatch = ($receivedApiKey !== '' && $expectedApiKey !== '') ? hash_equals((string)$expectedApiKey, (string)$receivedApiKey) : 'skipped';

$out = [
    'received_payload'  => $data,
    'raw_body'          => $raw,
    'player_uid_raw'    => $playerRaw,
    'extracted_user_id' => $userId,
    'user_found_in_db'  => $userFound,
    'user_balance'      => $userBalance,
    'received_api_key'  => $receivedApiKey,
    'expected_api_key'  => $expectedApiKey,
    'key_match'         => $keyMatch,
    'db_settings'       => $dbSettings,
    'config_launch_url' => $config['launch_url'] ?? '',
];

$logLine = '[' . date('Y-m-d H:i:s') . '] DEBUG_CALLBACK ' . json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
@file_put_contents(__DIR__ . '/../game_api_debug.log', $logLine, FILE_APPEND);

echo json_encode(['debug' => 'logged', 'summary' => [
    'user_found'   => $userFound,
    'user_balance' => $userBalance,
    'key_match'    => $keyMatch,
    'received_key' => $receivedApiKey,
    'expected_key' => $expectedApiKey,
]], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
