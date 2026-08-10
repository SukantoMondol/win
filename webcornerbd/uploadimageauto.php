<?php
require '../includes/auth_session.php';
require_once '../includes/db.php';

// AJAX রিকোয়েস্ট হ্যান্ডেলিং
if (isset($_POST['action']) && $_POST['action'] == 'update_db') {
    header('Content-Type: application/json');
    $brand_id = (int)$_POST['brand_id'];
    $games = json_decode($_POST['games'], true);
    $updated_count = 0;
    $already_exists = 0;

    if (!empty($games)) {
        // প্রথমে চেক করবে ইমেজ লিঙ্ক আলাদা কি না, আলাদা হলে তবেই আপডেট করবে
        $stmt = $conn->prepare("UPDATE `games` SET `image` = ? WHERE `game_uid` = ? AND `provider_id` = ? AND (`image` IS NULL OR `image` != ?)");
        foreach ($games as $game) {
            $game_uid = $game['game_code'] ?? $game['gameID'] ?? null;
            $image_url = $game['game_img'] ?? $game['img'] ?? null;

            if ($game_uid && $image_url) {
                $stmt->bind_param("ssss", $image_url, $game_uid, $brand_id, $image_url);
                $stmt->execute();
                if ($stmt->affected_rows > 0) { 
                    $updated_count++; 
                } else {
                    $already_exists++;
                }
            }
        }
        $stmt->close();
    }
    echo json_encode(['success' => true, 'updated' => $updated_count, 'skipped' => $already_exists]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Winbuz88 - Browser-Powered Sync PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0b0b0b; color: white; font-family: 'Segoe UI', sans-serif; }
        .card { background-color: #161616; border: 1px solid #333; }
        input { background-color: #222 !important; color: white !important; border: 1px solid #444 !important; }
        .log-entry { border-bottom: 1px solid #222; padding: 12px; font-family: monospace; font-size: 13px; transition: all 0.3s; }
        .log-entry:hover { background: #1a1a1a; }
        .status-badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body class="p-6">
    <div class="max-w-4xl mx-auto">
        <div class="card p-8 rounded-2xl shadow-2xl mb-8">
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-3 text-yellow-500">
                <i class="fas fa-bolt"></i> Image Auto-Sync <span class="text-xs bg-yellow-500/10 text-yellow-500 px-2 py-1 rounded">v2.0</span>
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-gray-400 text-xs font-bold mb-2 uppercase">Start Brand ID</label>
                    <input type="number" id="start_id" value="74" class="w-full p-3 rounded-lg outline-none focus:border-yellow-500">
                </div>
                <div>
                    <label class="block text-gray-400 text-xs font-bold mb-2 uppercase">End Brand ID</label>
                    <input type="number" id="end_id" value="85" class="w-full p-3 rounded-lg outline-none focus:border-yellow-500">
                </div>
                <button onclick="startSync()" id="syncBtn" class="bg-yellow-500 hover:bg-yellow-400 text-black font-black py-3 rounded-lg transition-all uppercase text-sm shadow-[0_4px_15px_rgba(234,179,8,0.3)]">
                    <i class="fas fa-play mr-2"></i> Start Syncing
                </button>
            </div>
        </div>

        <div class="card p-6 rounded-2xl hidden shadow-2xl" id="logContainer">
            <div class="flex justify-between items-center mb-6 border-b border-gray-800 pb-4">
                <h3 class="text-lg font-bold">Process Monitor</h3>
                <div id="overallStatus" class="flex items-center gap-2 text-yellow-500 font-bold text-sm">
                    <i class="fas fa-circle-notch fa-spin"></i> <span>Ready to start</span>
                </div>
            </div>
            <div id="logContent" class="space-y-1 max-h-[500px] overflow-y-auto">
                </div>
        </div>
    </div>

    <script>
    let isRunning = false;

    async function startSync() {
        if(isRunning) return;
        
        const startId = parseInt($('#start_id').val());
        const endId = parseInt($('#end_id').val());
        
        if(isNaN(startId) || isNaN(endId) || startId > endId) {
            alert('Please enter a valid Range!');
            return;
        }

        isRunning = true;
        $('#syncBtn').prop('disabled', true).addClass('opacity-50');
        $('#logContainer').removeClass('hidden');
        $('#logContent').empty();
        $('#overallStatus').html('<i class="fas fa-circle-notch fa-spin"></i> <span>Processing...</span>').addClass('animate-pulse');

        for (let i = startId; i <= endId; i++) {
            addLog(i, 'Connecting to API...', 'text-blue-400');
            
            try {
                const response = await fetch(`https://igamingapis.com/provider/brands.php?brand_id=${i}`);
                
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                
                const data = await response.json();

                if (data.status && data.games) {
                    addLog(i, `Found ${data.total_games} games. Syncing with Database...`, 'text-yellow-400');
                    
                    const updateRes = await $.post('', {
                        action: 'update_db',
                        brand_id: i,
                        games: JSON.stringify(data.games)
                    });

                    if(updateRes.success) {
                        const msg = `Done! <span class="text-green-400">${updateRes.updated} New</span> / <span class="text-gray-400">${updateRes.skipped} Skipped</span>`;
                        addLog(i, msg, 'text-white font-bold');
                    }
                } else {
                    addLog(i, 'Skipped: Provider has no games or invalid ID.', 'text-gray-600');
                }
            } catch (error) {
                addLog(i, `Error: ${error.message}`, 'text-red-500');
                // লুপ থামবে না, পরের আইডিতে চলে যাবে
            }
            
            // ছোট একটি ডিলে যাতে সার্ভারে প্রেসার না পড়ে
            await new Promise(resolve => setTimeout(resolve, 500));
        }

        isRunning = false;
        $('#overallStatus').html('<i class="fas fa-check-circle"></i> <span>All Tasks Completed!</span>').removeClass('animate-pulse text-yellow-500').addClass('text-green-500');
        $('#syncBtn').prop('disabled', false).removeClass('opacity-50');
    }

    function addLog(id, msg, colorClass) {
        const timestamp = new Date().toLocaleTimeString();
        const html = `
            <div class="log-entry ${colorClass}">
                <span class="text-gray-600 text-[10px] mr-2">${timestamp}</span>
                <span class="status-badge bg-gray-800 text-yellow-500 mr-3">ID ${id}</span>
                <span>${msg}</span>
            </div>
        `;
        $('#logContent').prepend(html);
    }
    </script>
</body>
</html>