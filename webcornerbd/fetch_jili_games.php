<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Access Denied');
}

$msg = '';
$results = [];

if (isset($_POST['run_sync'])) {
    $url = 'https://providers.gambllyapi.com/?category=JL';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 35);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        $msg = "Error: Failed to fetch data from API (HTTP $http_code). " . $curl_error;
    } else {
        $data = json_decode($response, true);
        $raw_games = [];
        if (is_array($data)) {
            $raw_games = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;
        }

        if (empty($raw_games) || !is_array($raw_games)) {
            $msg = "Error: No games found in the API response or invalid JSON.";
        } else {
            $updated = 0;
            $inserted = 0;
            
            // Match JILI provider details
            $conn->query("UPDATE game_providers SET name='JILI', slug='jili', status='active' WHERE provider_id='49' LIMIT 1");

            foreach ($raw_games as $rg) {
                if (!is_array($rg)) continue;

                $name = trim((string)($rg['name'] ?? $rg['game_name'] ?? ''));
                $uid = trim((string)($rg['game_uid'] ?? $rg['game_id'] ?? $rg['uid'] ?? ''));
                $code = trim((string)($rg['game_code'] ?? $rg['code'] ?? ''));
                $image = trim((string)($rg['image'] ?? $rg['image_url'] ?? $rg['img'] ?? ''));
                $type = trim((string)($rg['category'] ?? $rg['type'] ?? 'Slot'));

                if ($name === '' || $uid === '') continue;

                // Simple name normalize for match: lowercase, remove non-alphanumeric
                $normName = preg_replace('/[^a-z0-9]/', '', strtolower($name));

                // Find game in DB
                $find = $conn->prepare("SELECT id, name, game_uid, api_game_id, api_game_code FROM games WHERE provider_id='49' AND (REPLACE(LOWER(name), ' ', '') = ? OR api_game_id = ? OR game_uid = ? OR (api_game_code = ? AND api_game_code != '')) LIMIT 1");
                $find->bind_param('ssss', $normName, $uid, $uid, $code);
                $find->execute();
                $res = $find->get_result();
                
                if ($res && $res->num_rows > 0) {
                    $existing = $res->fetch_assoc();
                    $gameId = $existing['id'];

                    // Update game entry
                    $update = $conn->prepare("UPDATE games SET api_game_id = ?, api_game_code = ?, api_vendor_code = 'JILI', image = ?, api_game_type = ? WHERE id = ? LIMIT 1");
                    $update->bind_param('ssssi', $uid, $code, $image, $type, $gameId);
                    $update->execute();
                    $update->close();

                    $results[] = [
                        'name' => $name,
                        'uid' => $uid,
                        'code' => $code,
                        'status' => 'Updated',
                        'existing_name' => $existing['name']
                    ];
                    $updated++;
                } else {
                    // Insert new game entry
                    $gameUid = 'jili_' . ($code !== '' ? $code : substr($uid, 0, 8));
                    $insert = $conn->prepare("INSERT INTO games (game_uid, provider_id, name, image, category, api_game_id, api_game_code, api_vendor_code, api_game_type, status) VALUES (?, '49', ?, ?, 'Slots', ?, ?, 'JILI', ?, 'active')");
                    $insert->bind_param('sssssss', $gameUid, $name, $image, $uid, $code, $type);
                    $insert->execute();
                    $insert->close();

                    $results[] = [
                        'name' => $name,
                        'uid' => $uid,
                        'code' => $code,
                        'status' => 'Inserted',
                        'existing_name' => '-'
                    ];
                    $inserted++;
                }
                $find->close();
            }

            // Clear cache for games to update immediately
            $cache_dir = __DIR__ . '/../cache/category_games';
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '/*.cache.php');
                foreach ($files as $f) { @unlink($f); }
            }

            $msg = "Successfully processed " . count($raw_games) . " games. (Updated: $updated, Inserted: $inserted)";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JILI Games Synchronizer | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-50 font-sans text-slate-800">
    <?php include '../includes/sidebar_admin.php'; ?>
    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        <div class="max-w-5xl mx-auto">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-sync text-indigo-600"></i> JILI Games Synchronizer
                    </h1>
                    <p class="text-sm text-gray-500">Sync all JILI game UIDs and codes directly from Gamblly API.</p>
                </div>
                <form method="POST">
                    <button type="submit" name="run_sync" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-bold shadow-md transition flex items-center gap-2">
                        <i class="fas fa-cloud-download-alt"></i> Sync JILI Games Now
                    </button>
                </form>
            </div>

            <?php if($msg): ?>
                <div class="mb-6 p-4 rounded-lg font-semibold text-sm border <?php echo strpos($msg, 'Error') === 0 ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700'; ?>">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($results)): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Sync Activity Log</h3>
                    </div>
                    <div class="overflow-x-auto max-h-[500px]">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 uppercase font-black tracking-wider border-b border-slate-200">
                                    <th class="p-3.5">Game Name</th>
                                    <th class="p-3.5">Code</th>
                                    <th class="p-3.5">Game UID</th>
                                    <th class="p-3.5">Matched/Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($results as $r): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-3 font-bold text-slate-800"><?php echo htmlspecialchars($r['name']); ?></td>
                                        <td class="p-3 font-mono"><?php echo htmlspecialchars($r['code']); ?></td>
                                        <td class="p-3 font-mono text-slate-500"><?php echo htmlspecialchars($r['uid']); ?></td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $r['status'] == 'Updated' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'; ?>">
                                                <?php echo $r['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
