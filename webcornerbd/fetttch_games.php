<?php
require '../includes/auth_session.php';

// Database connection setup
$db_path = file_exists('../includes/db.php') ? '../includes/db.php' : 'includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
} else {
    $conn = new mysqli('localhost', 'root', '', 'bating');
}

// Fetch API Settings from database
$settings_query = $conn->query("SELECT setting_key, setting_value FROM game_settings");
$api_settings = [];
while ($row = $settings_query->fetch_assoc()) {
    $api_settings[$row['setting_key']] = $row['setting_value'];
}

// Ensure base URL has no trailing slash and API Key is present
$api_key = $api_settings['api_token'] ?? '';
$api_base = "https://infinityapi.site/api/v1";

// Helper function for cURL requests with User-Agent
function fetchFromApi($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // Adding User-Agent to prevent server blocking
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        return json_decode($response, true);
    }
    
    return ['error' => true, 'http_code' => $http_code, 'curl_error' => $curl_error, 'raw' => $response];
}

// ==========================================
// API ENDPOINTS HANDLING (AJAX CALLS)
// ==========================================

// 1. Fetch Providers
if (isset($_GET['action']) && $_GET['action'] == 'get_providers') {
    header('Content-Type: application/json');
    
    $url = $api_base . "/providers?api_key=" . $api_key;
    $response = fetchFromApi($url);
    
    if (isset($response['providers']) && is_array($response['providers'])) {
        $providers = $response['providers'];
        $inserted = 0;
        
        foreach ($providers as $prov) {
            // JSON অনুযায়ী brand_id এবং logo ফেচ করা হচ্ছে
            $provider_id = $conn->real_escape_string($prov['brand_id'] ?? '');
            $name = $conn->real_escape_string($prov['name'] ?? '');
            $image = $conn->real_escape_string($prov['logo'] ?? ''); // logo key from JSON
            $type = 'slots';
            $slug = strtolower(str_replace(' ', '-', $name));
            
            if(!empty($provider_id)) {
                $check = $conn->query("SELECT id FROM game_providers WHERE provider_id = '$provider_id'");
                if ($check->num_rows == 0) {
                    $conn->query("INSERT INTO game_providers (provider_id, name, image, slug, type, status) VALUES ('$provider_id', '$name', '$image', '$slug', '$type', 'active')");
                    $inserted++;
                } else {
                    // Update if already exists
                    $conn->query("UPDATE game_providers SET name='$name', image='$image' WHERE provider_id='$provider_id'");
                }
            }
        }
        echo json_encode(['status' => 'success', 'message' => "Found " . count($providers) . " providers. New added/updated: $inserted", 'providers' => $providers]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch providers.', 'debug' => $response]);
    }
    exit;
}

// 2. Fetch Games for a specific provider
if (isset($_GET['action']) && $_GET['action'] == 'get_games' && isset($_GET['provider_id'])) {
    header('Content-Type: application/json');
    $provider_id = $_GET['provider_id'];
    
    $url = $api_base . "/games?api_key=" . $api_key . "&provider=" . urlencode($provider_id);
    $response = fetchFromApi($url);
    
    if (isset($response['games']) && is_array($response['games'])) {
        $games = $response['games'];
        $inserted = 0;
        
        foreach ($games as $game) {
            // JSON অনুযায়ী game_uid এবং img ফেচ করা হচ্ছে
            $game_uid = $conn->real_escape_string($game['game_uid'] ?? '');
            $name = $conn->real_escape_string($game['name'] ?? '');
            
            // গেমের ইমেজের লিংক যদি অসম্পূর্ণ থাকে, তবে ডোমেইন যোগ করে দেওয়া
            $raw_img = $game['img'] ?? '';
            if (!empty($raw_img) && !preg_match('/^http/', $raw_img)) {
                $raw_img = "https://softapi2.shop/" . ltrim($raw_img, '/');
            }
            $image = $conn->real_escape_string($raw_img);
            
            $category = 'Slots'; // Default
            
            if(!empty($game_uid)) {
                $check = $conn->query("SELECT id FROM games WHERE game_uid = '$game_uid'");
                if ($check->num_rows == 0) {
                    $conn->query("INSERT INTO games (game_uid, provider_id, name, image, category, status) VALUES ('$game_uid', '$provider_id', '$name', '$image', '$category', 'active')");
                    $inserted++;
                } else {
                    // Update if already exists
                    $conn->query("UPDATE games SET name='$name', image='$image' WHERE game_uid='$game_uid'");
                }
            }
        }
        echo json_encode(['status' => 'success', 'message' => "Processed " . count($games) . " games. New added/updated: $inserted"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch games or no games found.']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Fetcher UI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; padding: 20px; color: #1f2937; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; font-size: 24px; color: #111827; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; }
        
        .status-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .progress-bar-container { width: 100%; background-color: #e2e8f0; border-radius: 999px; height: 12px; margin-top: 15px; overflow: hidden; display: none; }
        .progress-bar { height: 100%; background-color: #3b82f6; width: 0%; transition: width 0.3s; }
        
        .btn { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #2563eb; }
        .btn:disabled { background: #9ca3af; cursor: not-allowed; }
        
        .log-container { background: #1e293b; color: #a5b4fc; padding: 15px; border-radius: 8px; height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.5; margin-top: 20px; }
        .log-success { color: #34d399; }
        .log-error { color: #f87171; }
        .log-info { color: #60a5fa; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 InfinityAPI Game Synchronizer</h1>
    
    <div class="status-box">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Status:</strong> <span id="currentStatus">Ready to fetch</span>
            </div>
            <button id="startBtn" class="btn" onclick="startProcess()">Start Fetching</button>
        </div>
        
        <div class="progress-bar-container" id="progressContainer">
            <div class="progress-bar" id="progressBar"></div>
        </div>
        <div id="progressText" style="text-align: right; font-size: 12px; margin-top: 5px; color: #6b7280; display: none;">0%</div>
    </div>

    <div class="log-container" id="logBox">
        <div>> Initialization complete. API Endpoint configured to /api/v1/. Waiting for action...</div>
    </div>
</div>

<script>
    let providersList = [];
    let currentProviderIndex = 0;

    function log(message, type = 'info') {
        const logBox = document.getElementById('logBox');
        const time = new Date().toLocaleTimeString();
        let colorClass = '';
        if(type === 'success') colorClass = 'log-success';
        if(type === 'error') colorClass = 'log-error';
        if(type === 'info') colorClass = 'log-info';
        
        logBox.innerHTML += `<div class="${colorClass}">[${time}] ${message}</div>`;
        logBox.scrollTop = logBox.scrollHeight;
    }

    async function startProcess() {
        document.getElementById('startBtn').disabled = true;
        document.getElementById('progressContainer').style.display = 'block';
        document.getElementById('progressText').style.display = 'block';
        
        log('Fetching providers list from API...', 'info');
        document.getElementById('currentStatus').innerText = "Fetching Providers...";

        try {
            let response = await fetch('?action=get_providers');
            let data = await response.json();
            
            if (data.status === 'success' && data.providers && data.providers.length > 0) {
                log(data.message, 'success');
                providersList = data.providers;
                currentProviderIndex = 0;
                fetchNextProviderGames();
            } else {
                log('Failed to fetch providers. Server responded with error.', 'error');
                if(data.debug) console.log("Debug Info:", data.debug);
                document.getElementById('startBtn').disabled = false;
            }
        } catch (error) {
            log('Connection error while fetching providers. Check Network tab.', 'error');
            document.getElementById('startBtn').disabled = false;
        }
    }

    async function fetchNextProviderGames() {
        if (currentProviderIndex >= providersList.length) {
            log('🎉 All tasks completed successfully!', 'success');
            document.getElementById('currentStatus').innerText = "Completed!";
            document.getElementById('startBtn').innerText = "Run Again";
            document.getElementById('startBtn').disabled = false;
            return;
        }

        let provider = providersList[currentProviderIndex];
        // JSON অনুযায়ী brand_id ফেচ করা হচ্ছে
        let providerId = provider.brand_id;
        let providerName = provider.name || providerId;

        if (!providerId) {
            log(`=> Skipping ${providerName}: No brand_id found.`, 'error');
            currentProviderIndex++;
            setTimeout(fetchNextProviderGames, 500);
            return;
        }

        log(`Fetching games for: ${providerName} (${currentProviderIndex + 1}/${providersList.length})...`, 'info');
        document.getElementById('currentStatus').innerText = `Syncing: ${providerName}`;

        try {
            let response = await fetch(`?action=get_games&provider_id=${encodeURIComponent(providerId)}`);
            let data = await response.json();

            if (data.status === 'success') {
                log(`=> ${providerName}: ${data.message}`, 'success');
            } else {
                log(`=> ${providerName}: ${data.message}`, 'error');
            }
        } catch (error) {
            log(`=> ${providerName}: Request failed/timeout. Moving to next.`, 'error');
        }

        // Update Progress Bar
        currentProviderIndex++;
        let progressPercent = Math.round((currentProviderIndex / providersList.length) * 100);
        document.getElementById('progressBar').style.width = `${progressPercent}%`;
        document.getElementById('progressText').innerText = `${progressPercent}% (${currentProviderIndex}/${providersList.length})`;

        // Add a 1 second delay
        setTimeout(fetchNextProviderGames, 1000); 
    }
</script>

</body>
</html>