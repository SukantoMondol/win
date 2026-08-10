<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require_once '../includes/game_api_helper.php';
game_api_start_error_logging();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Access Denied');
}

$logPath = game_api_debug_log_path();
if (!file_exists($logPath)) { @file_put_contents($logPath, ''); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log'])) {
    @file_put_contents($logPath, '');
    header('Location: game_api_debug_log.php?cleared=1');
    exit;
}

if (isset($_GET['download'])) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="game_api_debug.log"');
    readfile($logPath);
    exit;
}

$lines = array();
if (file_exists($logPath)) {
    $all = @file($logPath, FILE_IGNORE_NEW_LINES);
    if (is_array($all)) { $lines = array_slice($all, -500); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game API Debug Log</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans text-slate-800">
    <?php include '../includes/sidebar_admin.php'; ?>
    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Game API Debug Log</h1>
                <p class="text-sm text-gray-500">Launch, callback, provider response এবং PHP error এখানে save হয়।</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="game_api_key.php" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg text-sm font-bold">Back</a>
                <a href="game_api_debug_log.php?download=1" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold">Download Log</a>
                <form method="POST" onsubmit="return confirm('Clear game API debug log?')">
                    <button name="clear_log" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-bold">Clear Log</button>
                </form>
            </div>
        </div>

        <?php if(isset($_GET['cleared'])): ?>
            <div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg font-semibold">Debug log cleared.</div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-bold mb-2">File Location</h2>
            <div class="bg-gray-100 rounded p-3 text-xs font-mono break-all">api/game_api_debug.log</div>
            <p class="text-xs text-gray-500 mt-2">cPanel File Manager থেকেও এই file download করা যাবে। Token/Secret automatically masked থাকবে।</p>
        </div>

        <section class="bg-black text-green-300 rounded-2xl shadow-sm border border-gray-800 p-4 overflow-x-auto">
            <pre class="text-xs whitespace-pre-wrap break-words"><?php
                if (empty($lines)) {
                    echo htmlspecialchars('No debug log yet. Try opening a game once, then refresh this page.', ENT_QUOTES, 'UTF-8');
                } else {
                    echo htmlspecialchars(implode(PHP_EOL, $lines), ENT_QUOTES, 'UTF-8');
                }
            ?></pre>
        </section>
    </main>
</body>
</html>
