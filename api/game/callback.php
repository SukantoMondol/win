<?php
define('GAME_API_SKIP_MAINTENANCE', true);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/game_api_helper.php';
require_once __DIR__ . '/../../includes/gamblly_api_helper.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Key, X-Agency-Uid');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// LOG every incoming request so we can see what Gamblly sends
$_rawBody   = file_get_contents('php://input');
$_logEntry  = '[' . date('Y-m-d H:i:s') . '] GAMBLLY_CALLBACK_RECEIVED '
    . 'method=' . $_SERVER['REQUEST_METHOD'] . ' '
    . 'ip=' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' '
    . 'raw=' . $_rawBody . ' '
    . 'post=' . json_encode($_POST)
    . PHP_EOL;
@file_put_contents(__DIR__ . '/../game_api_debug.log', $_logEntry, FILE_APPEND);

if (function_exists('game_api_start_error_logging')) { game_api_start_error_logging(); }
if (function_exists('game_api_ensure_schema')) { game_api_ensure_schema($conn, false); }

gamblly_api_handle_callback($conn);

