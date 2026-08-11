<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/game_api_helper.php';

// This page is intentionally lightweight. It must not run provider/game seed patches on every admin load.
// Only the credential rows that the admin changes are updated; all other API settings stay in the database.
game_api_ensure_schema($conn, false);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Access Denied');
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_game_api_key'])) {
    $token = trim((string)($_POST['api_token'] ?? ''));
    $secret = trim((string)($_POST['secret_key'] ?? ''));
    $agent = game_api_clean_no_space($_POST['agent_code'] ?? '');
    $endpoint = trim((string)($_POST['api_endpoint'] ?? ''));

    if ($agent === '') {
        $error = 'Agent Code is required.';
    } else {
        game_api_set_setting($conn, 'api_token', $token);
        game_api_set_setting($conn, 'secret_key', $secret);
        game_api_set_setting($conn, 'agent_code', $agent);
        if ($endpoint !== '') {
            game_api_set_setting($conn, 'api_endpoint', $endpoint);
            game_api_set_setting($conn, 'gamblly_launch_url', $endpoint);
        }

        // Save active provider as GAMBLLY
        game_api_set_setting($conn, 'game_api_provider', 'GAMBLLY');
        $msg = 'API credentials updated successfully. Game provider configured to GAMBLLY.';
    }
}

$config = game_api_get_settings($conn, true);
$apiToken = (string)($config['api_token'] ?? '');
$secretKey = (string)($config['secret_key'] ?? '');
$agentCode = (string)($config['agent_code'] ?? '');
$apiEndpoint = (string)($config['api_endpoint'] ?? 'https://game.gambllyapi.com/production/v1/gameLaunch.php');
if ($apiEndpoint === '') { $apiEndpoint = 'https://game.gambllyapi.com/production/v1/gameLaunch.php'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game API Key | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-50 font-sans text-slate-800">
    <?php include '../includes/sidebar_admin.php'; ?>
    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        <div class="max-w-3xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2"><i class="fas fa-key text-yellow-500"></i> Game API Key</h1>
            </div>

            <?php if($msg): ?><div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg font-semibold"><?php echo game_api_h($msg); ?></div><?php endif; ?>
            <?php if($error): ?><div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg font-semibold"><?php echo game_api_h($error); ?></div><?php endif; ?>

            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-bold mb-2">Provider Credentials</h2>

                <form method="POST" class="space-y-5" autocomplete="off">
                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-1">API Key / Validation Token</label>
                        <input type="text" name="api_token" value="<?php echo game_api_h($apiToken); ?>" class="w-full border border-gray-300 rounded-lg p-3 font-mono text-sm focus:ring-2 focus:ring-yellow-400 outline-none" placeholder="Enter API Key">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-1">Secret Key</label>
                        <input type="text" name="secret_key" value="<?php echo game_api_h($secretKey); ?>" class="w-full border border-gray-300 rounded-lg p-3 font-mono text-sm focus:ring-2 focus:ring-yellow-400 outline-none" placeholder="Enter Secret Key">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-1">API Prefix / Agent Code</label>
                        <input type="text" name="agent_code" value="<?php echo game_api_h($agentCode); ?>" class="w-full border border-gray-300 rounded-lg p-3 font-mono text-sm focus:ring-2 focus:ring-yellow-400 outline-none" placeholder="MR_n93AA" required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-1">API Endpoint URL <span class="text-red-500">★ Important</span></label>
                        <input type="url" name="api_endpoint" value="<?php echo game_api_h($apiEndpoint); ?>" class="w-full border border-gray-300 rounded-lg p-3 font-mono text-sm focus:ring-2 focus:ring-yellow-400 outline-none" placeholder="https://game.gambllyapi.com/production/v1/gameLaunch.php">
                        <p class="text-xs text-red-600 mt-1 font-semibold">⚠️ Make sure URL has NO hyphen: <code>gambllyapi.com</code> (NOT <code>gamblly-api.com</code>)</p>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-800 space-y-2">
                        <p class="font-bold"><i class="fas fa-info-circle"></i> Gamblly API Settings Guide:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li><strong>API Key / Validation Token:</strong> Your Gamblly API Key (e.g. <code>ee761aea015CodeHub94fbe22b19aa61</code>)</li>
                            <li><strong>Secret Key (Suffix):</strong> Your Gamblly API Suffix (e.g. <code>b2cb3</code>)</li>
                            <li><strong>API Prefix / Agent Code:</strong> Your Gamblly API Prefix/Agent code (e.g. <code>b2cb3</code>)</li>
                            <li><strong>API Endpoint URL:</strong> <code>https://game.gambllyapi.com/production/v1/gameLaunch.php</code></li>
                            <li><strong>Callback URL:</strong> <code>https://bajixwin.com/api/game/callback.php</code> (Make sure to include <code>.php</code> at the end in Gamblly panel)</li>
                        </ul>
                    </div>

                    <button type="submit" name="save_game_api_key" class="w-full bg-yellow-500 hover:bg-yellow-400 text-black font-black py-3 rounded-lg shadow transition">
                        <i class="fas fa-save"></i> SAVE API CREDENTIALS
                    </button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
