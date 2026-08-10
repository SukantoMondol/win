<?php
/**
 * Bulk Evolution Live Sync and Database Populate Script.
 * Can be run from the command-line or via a web request.
 * 
 * It attempts to fetch the latest Evolution Live games from providers.gambllyapi.com,
 * inserts new games, updates existing database image fields, associates them with the
 * Live Casino category, and flushes cache.
 * 
 * If curl is blocked by Cloudflare (403), it will look for a backup JSON file at
 * c:\Users\mdasi\Desktop\own\scratch\evolutionlive_api_response.json
 */

define('GAME_API_SKIP_MAINTENANCE', true);

$base_dir = dirname(__DIR__);
$db_file = $base_dir . '/includes/db.php';
$mapping_file = $base_dir . '/includes/game_api_evolutionlive_mapping_seed.php';
$backup_json_file = $base_dir . '/scratch/evolutionlive_api_response.json';

if (!file_exists($db_file)) {
    die("Error: db.php not found.\n");
}
require_once $db_file;

if (!file_exists($mapping_file)) {
    die("Error: Evolution Live mapping seed file not found.\n");
}
$mappings = require $mapping_file;
if (!is_array($mappings)) {
    die("Error: Evolution Live mappings format invalid.\n");
}

// Find Category IDs from database
$live_casino_cat_id = 2; // Default fallback
$hot_game_cat_id = 1;     // Default fallback

if (isset($conn) && !$conn->connect_error) {
    $res = $conn->query("SELECT id, name FROM front_categories WHERE LOWER(name) LIKE '%live casino%' OR LOWER(name) LIKE '%live%' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $live_casino_cat_id = (int)$res->fetch_assoc()['id'];
    }
    
    $res2 = $conn->query("SELECT id, name FROM front_categories WHERE LOWER(name) LIKE '%hot game%' OR LOWER(name) LIKE '%hot%' LIMIT 1");
    if ($res2 && $res2->num_rows > 0) {
        $hot_game_cat_id = (int)$res2->fetch_assoc()['id'];
    }
}

echo "Live Casino Category ID: $live_casino_cat_id\n";
echo "Hot Game Category ID: $hot_game_cat_id\n";

echo "Attempting to retrieve Evolution Live games list from API...\n";
$url = 'https://providers.gambllyapi.com/?category=EVOLIVEROW';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json_data = null;
if ($http_code === 200 && $res) {
    echo "Successfully fetched Evolution Live list from API.\n";
    $json_data = json_decode($res, true);
} else {
    echo "API fetch failed (HTTP $http_code). Checking for local backup JSON file...\n";
    if (file_exists($backup_json_file)) {
        echo "Found local backup JSON file: $backup_json_file\n";
        $file_content = file_get_contents($backup_json_file);
        $json_data = json_decode($file_content, true);
    } else {
        echo "Error: Local backup JSON file not found at $backup_json_file.\n";
        echo "Please open the URL in your browser, save the JSON to that path, and re-run this script.\n";
    }
}

if (!$json_data) {
    die("No JSON data to process.\n");
}

$raw_games = array();
if (isset($json_data['data']) && is_array($json_data['data'])) {
    $raw_games = $json_data['data'];
} else if (is_array($json_data)) {
    $raw_games = $json_data;
} else {
    die("Error: Invalid JSON structure.\n");
}

echo "Parsed " . count($raw_games) . " games from JSON.\n";

if (!isset($conn) || $conn->connect_error) {
    die("Database connection is not available to run inserts/updates.\n");
}

// Map mapping seeds by name and ID
$map_by_name = array();
$map_by_id = array();
foreach ($mappings as $m) {
    if (!is_array($m)) continue;
    $n = isset($m['game_name']) ? trim((string)$m['game_name']) : '';
    $id = isset($m['api_game_id']) ? strtolower(trim((string)$m['api_game_id'])) : '';
    if ($n !== '') { $map_by_name[strtolower(game_api_normalize_name($n))] = $m; }
    if ($id !== '') { $map_by_id[$id] = $m; }
}

$db_inserts = 0;
$db_updates = 0;
$cat_mappings = 0;

$stmt_insert = $conn->prepare("INSERT INTO games (game_uid, api_game_id, api_game_code, api_vendor_code, provider_id, api_provider_name, name, image, category, api_game_type, api_mapping_status, status) VALUES (?, ?, ?, 'evolutionlive', ?, ?, ?, ?, ?, ?, 'mapped', 'active')");
$stmt_update = $conn->prepare("UPDATE games SET image = ? WHERE game_uid = ? LIMIT 1");
$stmt_check_cat = $conn->prepare("SELECT id FROM front_category_games WHERE category_id=? AND game_uid=? LIMIT 1");
$stmt_insert_cat = $conn->prepare("INSERT INTO front_category_games (category_id, game_uid, sort_order) VALUES (?, ?, ?)");

$sort_order = 10;
foreach ($raw_games as $rg) {
    if (!is_array($rg)) continue;
    
    $rg_code = isset($rg['game_code']) ? trim((string)$rg['game_code']) : (isset($rg['code']) ? trim((string)$rg['code']) : '');
    $rg_uid = isset($rg['game_uid']) ? trim((string)$rg['game_uid']) : (isset($rg['game_id']) ? trim((string)$rg['game_id']) : (isset($rg['id']) ? trim((string)$rg['id']) : ''));
    $rg_name = isset($rg['game_name']) ? trim((string)$rg['game_name']) : (isset($rg['name']) ? trim((string)$rg['name']) : '');
    $rg_img = isset($rg['image']) ? trim((string)$rg['image']) : (isset($rg['image_url']) ? trim((string)$rg['image_url']) : (isset($rg['banner']) ? trim((string)$rg['banner']) : (isset($rg['thumbnail']) ? trim((string)$rg['thumbnail']) : (isset($rg['img']) ? trim((string)$rg['img']) : (isset($rg['imageUrl']) ? trim((string)$rg['imageUrl']) : '')))));
    
    if ($rg_name === '' || $rg_uid === '') continue;
    if ($rg_code === '') { $rg_code = $rg_uid; }
    
    // Check if game exists in database
    $game_uid = '';
    $exist_q = $conn->query("SELECT game_uid, image FROM games WHERE api_game_id='" . $conn->real_escape_string($rg_uid) . "' OR api_game_code='" . $conn->real_escape_string($rg_code) . "' OR game_uid='evolutionlive_" . $conn->real_escape_string($rg_code) . "' LIMIT 1");
    if ($exist_q && $exist_q->num_rows > 0) {
        $row = $exist_q->fetch_assoc();
        $game_uid = $row['game_uid'];
        $curr_img = $row['image'];
        
        // Update image if it's missing or default placeholder/brand logo
        if ($rg_img !== '' && ($curr_img === '' || stripos($curr_img, 'placehold.co') !== false || stripos($curr_img, 'default_game') !== false || stripos($curr_img, 'brand_58') !== false || stripos($curr_img, 'brand_59') !== false)) {
            if ($stmt_update) {
                $stmt_update->bind_param('ss', $rg_img, $game_uid);
                if ($stmt_update->execute()) {
                    $db_updates++;
                }
            }
        }
    } else {
        // Find suggested game_uid or generate a new one
        $suggested = '';
        $l_uid = strtolower($rg_uid);
        $norm_name = strtolower(game_api_normalize_name($rg_name));
        if (isset($map_by_id[$l_uid])) {
            $suggested = isset($map_by_id[$l_uid]['local_game_uid']) ? $map_by_id[$l_uid]['local_game_uid'] : '';
        } else if (isset($map_by_name[$norm_name])) {
            $suggested = isset($map_by_name[$norm_name]['local_game_uid']) ? $map_by_name[$norm_name]['local_game_uid'] : '';
        }
        
        if ($suggested !== '') {
            $game_uid = $suggested;
        } else {
            $game_uid = 'evolutionlive_' . preg_replace('/[^0-9A-Za-z_\-]/', '', $rg_code);
            // Verify uniqueness
            $chk = $conn->query("SELECT id FROM games WHERE game_uid='" . $conn->real_escape_string($game_uid) . "' LIMIT 1");
            if ($chk && $chk->num_rows > 0) {
                $game_uid = 'evolutionlive_' . substr(md5($rg_uid . $rg_code), 0, 12);
            }
        }
        
        $provider_id = '58';
        $provider_name = 'Evolution Live';
        $category = 'CasinoLive';
        $game_type = 'CasinoLive';
        
        if ($stmt_insert) {
            $stmt_insert->bind_param('sssssssss', $game_uid, $rg_uid, $rg_code, $provider_id, $provider_name, $rg_name, $rg_img, $category, $game_type);
            if ($stmt_insert->execute()) {
                $db_inserts++;
            }
        }
    }
    
    // Ensure the game is linked in Category 2 (Live Casino) and Category 1 (Hot Games)
    if ($game_uid !== '') {
        foreach (array($live_casino_cat_id, $hot_game_cat_id) as $cat_id) {
            if ($stmt_check_cat && $stmt_insert_cat) {
                $stmt_check_cat->bind_param('is', $cat_id, $game_uid);
                $stmt_check_cat->execute();
                $res_chk = $stmt_check_cat->get_result();
                if ($res_chk && $res_chk->num_rows === 0) {
                    $stmt_insert_cat->bind_param('isi', $cat_id, $game_uid, $sort_order);
                    if ($stmt_insert_cat->execute()) {
                        $cat_mappings++;
                    }
                }
            }
        }
        $sort_order += 5;
    }
}

if ($stmt_insert) $stmt_insert->close();
if ($stmt_update) $stmt_update->close();
if ($stmt_check_cat) $stmt_check_cat->close();
if ($stmt_insert_cat) $stmt_insert_cat->close();

echo "Database new games inserted: $db_inserts\n";
echo "Database existing game images updated: $db_updates\n";
echo "Category mappings (Live Casino / Hot Games) added: $cat_mappings\n";

if (function_exists('game_api_jili_clear_category_cache')) {
    game_api_jili_clear_category_cache();
    echo "Cleared JILI/Live Casino category cache.\n";
}
