<?php
/**
 * Log viewer for callback and payout debugging
 * URL: https://bajixwin.com/api/game/view_log.php
 */
header('Content-Type: text/plain; charset=utf-8');

$possibleLogs = array(
    __DIR__ . '/../game_api_debug.log',
    __DIR__ . '/../../game_api_debug.log',
    __DIR__ . '/game_api_debug.log',
    dirname(__DIR__, 2) . '/game_api_debug.log',
);

$content = '';
$foundPath = '';

foreach ($possibleLogs as $path) {
    if (file_exists($path) && filesize($path) > 0) {
        $content = file_get_contents($path);
        $foundPath = $path;
        break;
    }
}

if ($content !== '') {
    echo "=== LOG FOUND AT: " . basename($foundPath) . " ===" . PHP_EOL . PHP_EOL;
    echo $content;
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Log viewer active. No debug events recorded yet." . PHP_EOL;
    echo "To test NEKpay payout: Go to Admin Finance Controller and click 'Approve' (Blue button) on a pending withdrawal." . PHP_EOL;
}

