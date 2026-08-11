<?php
/**
 * Log viewer for callback debugging
 * URL: https://bajixwin.com/api/game/view_log.php
 */
header('Content-Type: text/plain; charset=utf-8');
$logFile = __DIR__ . '/../game_api_debug.log';
if (file_exists($logFile)) {
    echo file_get_contents($logFile);
} else {
    echo "Log file does not exist yet.";
}
