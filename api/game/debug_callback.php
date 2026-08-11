<?php
/**
 * TEMPORARY DEBUG + AUTO-FIX FILE
 * URL: https://bajixwin.com/api/game/debug_callback.php
 * This reads from correct 'game_settings' table and auto-fixes API key if needed
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/game_api_helper.php';
require_once __DIR__ . '/../../includes/gamblly_api_helper.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// MUST run schema first to create tables
if (function_exists('game_api_ensure_schema')) { game_api_ensure_schema($conn, false); }
gamblly_api_ensure_transactions_table($conn);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }
if (!is_array($data)) { $data = []; }

// Check correct table: game_settings (NOT game_api_settings)
$tableExists = false;
$tr = @$conn->query("SHOW TABLES LIKE 'game_settings'");
if ($tr && $tr->num_rows > 0) { $tableExists = true; }

// Read DB settings from correct table
$dbSettings = [];
if ($tableExists) {
    $r = @$conn->query("SELECT setting_key, setting_value FROM game_settings WHERE setting_key IN ('api_token','secret_key','agent_code','gamblly_api_key','api_endpoint','game_api_provider','gamblly_launch_url') LIMIT 20");
    if ($r) { while ($row = $r->fetch_assoc()) { $dbSettings[$row['setting_key']] = $row['setting_value']; } }
}

// AUTO-FIX: Set correct API key if empty
$needsFix = false;
$fixLog = [];

if ($tableExists) {
    $currentApiToken = $dbSettings['api_token'] ?? '';
    $currentEndpoint = $dbSettings['api_endpoint'] ?? '';

    if ($currentApiToken === '') {
        game_api_set_setting($conn, 'api_token', 'ee761aea015CodeHub94fbe22b19aa61');
        game_api_set_setting($conn, 'secret_key', 'b2cb3');
        game_api_set_setting($conn, 'agent_code', 'b2cb3');
        game_api_set_setting($conn, 'game_api_provider', 'GAMBLLY');
        $needsFix = true;
        $fixLog[] = 'api_token was empty - set to ee761aea015CodeHub94fbe22b19aa61';
        $fixLog[] = 'secret_key set to b2cb3';
        $fixLog[] = 'agent_code set to b2cb3';
    }

    if ($currentEndpoint === '' || strpos($currentEndpoint, 'gambllyapi.com') === false) {
        game_api_set_setting($conn, 'api_endpoint', 'https://game.gambllyapi.com/production/v1/gameLaunch.php');
        game_api_set_setting($conn, 'gamblly_launch_url', 'https://game.gambllyapi.com/production/v1/gameLaunch.php');
        $needsFix = true;
        $fixLog[] = 'api_endpoint fixed to https://game.gambllyapi.com/production/v1/gameLaunch.php';
    }

    // Re-read after fix
    $r2 = @$conn->query("SELECT setting_key, setting_value FROM game_settings WHERE setting_key IN ('api_token','secret_key','agent_code','gamblly_api_key','api_endpoint','game_api_provider') LIMIT 20");
    $dbSettings = [];
    if ($r2) { while ($row = $r2->fetch_assoc()) { $dbSettings[$row['setting_key']] = $row['setting_value']; } }
}

$config = gamblly_api_get_config($conn);

// Player lookup
$playerRaw = gamblly_api_pick($data, array('player_uid', 'member_account', 'member', 'username', 'user_id', 'uid', 'player_id'), '');
$userId = gamblly_api_extract_player_id($playerRaw, $config);

$userFound = false; $userBalance = null;
if ($userId > 0) {
    $stmt = @$conn->prepare("SELECT id, username, balance FROM users WHERE id=? LIMIT 1");
    if ($stmt) { $stmt->bind_param('i', $userId); $stmt->execute(); $res = $stmt->get_result(); if ($res && $res->num_rows > 0) { $u = $res->fetch_assoc(); $userFound = true; $userBalance = $u['balance']; } $stmt->close(); }
}

$receivedApiKey = gamblly_api_pick($data, array('api_key', 'agency_uid', 'APIKey', 'key', 'token'), '');
$expectedApiKey = $config['api_key'] ?? '';
$keyMatch = ($receivedApiKey !== '' && $expectedApiKey !== '') ? (hash_equals((string)$expectedApiKey, (string)$receivedApiKey) ? 'MATCH' : 'MISMATCH') : 'no_key_received';

$out = [
    'game_settings_table_exists' => $tableExists,
    'auto_fixed'        => $needsFix,
    'fix_log'           => $fixLog,
    'db_settings_after' => $dbSettings,
    'config_api_key'    => $expectedApiKey,
    'config_launch_url' => $config['launch_url'] ?? '',
    'received_api_key'  => $receivedApiKey,
    'key_match'         => $keyMatch,
    'player_uid_raw'    => $playerRaw,
    'extracted_user_id' => $userId,
    'user_found_in_db'  => $userFound,
    'user_balance'      => $userBalance,
    'received_payload'  => $data,
];

$logLine = '[' . date('Y-m-d H:i:s') . '] DEBUG_CALLBACK ' . json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
@file_put_contents(__DIR__ . '/../game_api_debug.log', $logLine, FILE_APPEND);

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
