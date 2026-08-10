<?php
/**
 * Bulk JILI Image Sync and Seed Generator.
 * Can be run from the command-line or via a web request.
 * 
 * It attempts to fetch the latest JILI game images from providers.gambllyapi.com,
 * updates the local database games table, and updates includes/game_api_jili_image_seed.php.
 * 
 * If curl is blocked by Cloudflare (403), it will look for a backup JSON file at
 * c:\Users\mdasi\Desktop\own\scratch\jili_api_response.json
 */

define('GAME_API_SKIP_MAINTENANCE', true);

$base_dir = dirname(__DIR__);
$db_file = $base_dir . '/includes/db.php';
$mapping_file = $base_dir . '/includes/game_api_jili_mapping_seed.php';
$image_seed_file = $base_dir . '/includes/game_api_jili_image_seed.php';
$backup_json_file = $base_dir . '/scratch/jili_api_response.json';

if (!file_exists($db_file)) {
    die("Error: db.php not found.\n");
}
require_once $db_file;

if (!file_exists($mapping_file)) {
    die("Error: JILI mapping seed file not found.\n");
}
$mappings = require $mapping_file;
if (!is_array($mappings)) {
    die("Error: JILI mappings format invalid.\n");
}

// Load existing image seed if present
$existing_seed = array('by_code' => array(), 'by_uid' => array());
if (file_exists($image_seed_file)) {
    $loaded = @include $image_seed_file;
    if (is_array($loaded)) {
        if (isset($loaded['by_code']) && is_array($loaded['by_code'])) { $existing_seed['by_code'] = $loaded['by_code']; }
        if (isset($loaded['by_uid']) && is_array($loaded['by_uid'])) { $existing_seed['by_uid'] = $loaded['by_uid']; }
    }
}

echo "Attempting to retrieve JILI games list from API...\n";
$url = 'https://providers.gambllyapi.com/?category=JL';
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
    echo "Successfully fetched JILI list from API.\n";
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

// Map JILI codes/IDs to names from mapping seed for cleaner output comments
$code_to_name = array();
$uid_to_name = array();
foreach ($mappings as $m) {
    if (!is_array($m)) continue;
    $c = isset($m['api_game_code']) ? trim((string)$m['api_game_code']) : '';
    $u = isset($m['api_game_id']) ? strtolower(trim((string)$m['api_game_id'])) : '';
    $n = isset($m['game_name']) ? trim((string)$m['game_name']) : '';
    if ($c !== '') { $code_to_name[$c] = $n; }
    if ($u !== '') { $uid_to_name[$u] = $n; }
}

$updated_by_code = $existing_seed['by_code'];
$updated_by_uid = $existing_seed['by_uid'];

$db_updates = 0;
$seed_updates = 0;

foreach ($raw_games as $rg) {
    if (!is_array($rg)) continue;
    
    // Extract key details
    $rg_code = isset($rg['game_code']) ? trim((string)$rg['game_code']) : (isset($rg['code']) ? trim((string)$rg['code']) : '');
    $rg_uid = isset($rg['game_uid']) ? trim((string)$rg['game_uid']) : (isset($rg['game_id']) ? trim((string)$rg['game_id']) : (isset($rg['id']) ? trim((string)$rg['id']) : ''));
    $rg_img = isset($rg['image']) ? trim((string)$rg['image']) : (isset($rg['image_url']) ? trim((string)$rg['image_url']) : (isset($rg['banner']) ? trim((string)$rg['banner']) : (isset($rg['thumbnail']) ? trim((string)$rg['thumbnail']) : (isset($rg['img']) ? trim((string)$rg['img']) : (isset($rg['imageUrl']) ? trim((string)$rg['imageUrl']) : '')))));
    
    if ($rg_img === '') continue;
    
    // Check if we have mappings matching this game
    $game_name = '';
    if ($rg_code !== '' && isset($code_to_name[$rg_code])) {
        $game_name = $code_to_name[$rg_code];
    } else if ($rg_uid !== '' && isset($uid_to_name[strtolower($rg_uid)])) {
        $game_name = $uid_to_name[strtolower($rg_uid)];
    }
    
    // Update seed arrays
    if ($rg_code !== '') {
        if (!isset($updated_by_code[$rg_code]) || $updated_by_code[$rg_code] !== $rg_img) {
            $updated_by_code[$rg_code] = $rg_img;
            $seed_updates++;
        }
    }
    if ($rg_uid !== '') {
        $l_uid = strtolower($rg_uid);
        if (!isset($updated_by_uid[$l_uid]) || $updated_by_uid[$l_uid] !== $rg_img) {
            $updated_by_uid[$l_uid] = $rg_img;
            $seed_updates++;
        }
    }
    
    // Update database row directly if connection is active
    if (isset($conn) && !$conn->connect_error) {
        $queries = array();
        if ($rg_code !== '') {
            $queries[] = "api_game_code='" . $conn->real_escape_string($rg_code) . "'";
            $queries[] = "game_uid='jili_" . $conn->real_escape_string($rg_code) . "'";
        }
        if ($rg_uid !== '') {
            $queries[] = "LOWER(api_game_id)='" . $conn->real_escape_string(strtolower($rg_uid)) . "'";
        }
        
        if (!empty($queries)) {
            $where = "(" . implode(' OR ', $queries) . ") AND (provider_id='49' OR UPPER(COALESCE(api_vendor_code,''))='JILI')";
            $sql = "UPDATE games SET image='" . $conn->real_escape_string($rg_img) . "' WHERE " . $where;
            if ($conn->query($sql)) {
                $db_updates += max(0, (int)$conn->affected_rows);
            }
        }
    }
}

echo "Database updated rows: $db_updates\n";
echo "Seed mappings updated/added count: $seed_updates\n";

// Regenerate includes/game_api_jili_image_seed.php
$php_content = "<?php\n";
$php_content .= "/**\n";
$php_content .= " * JILI image seed. Generated from the existing database thumbnails; local files are\n";
$php_content .= " * only used where the supplied source had no individual thumbnail and showed generic JILI icon.\n";
$php_content .= " * Generated by sync_jili_images.php.\n";
$php_content .= " */\n";
$php_content .= "return array(\n";

$php_content .= "    'by_code' => array(\n";
ksort($updated_by_code, SORT_NATURAL);
foreach ($updated_by_code as $code => $img) {
    $name = isset($code_to_name[$code]) ? $code_to_name[$code] : '';
    $comment = $name !== '' ? " // " . $name : "";
    $php_content .= "        '" . addslashes($code) . "' => '" . addslashes($img) . "'," . $comment . "\n";
}
$php_content .= "    ),\n";

$php_content .= "    'by_uid' => array(\n";
ksort($updated_by_uid);
foreach ($updated_by_uid as $uid => $img) {
    $name = isset($uid_to_name[strtolower($uid)]) ? $uid_to_name[strtolower($uid)] : '';
    $comment = $name !== '' ? " // " . $name : "";
    $php_content .= "        '" . addslashes($uid) . "' => '" . addslashes($img) . "'," . $comment . "\n";
}
$php_content .= "    ),\n";

$php_content .= ");\n";
$php_content .= "?>\n";

if (file_put_contents($image_seed_file, $php_content)) {
    echo "Successfully updated seed file: $image_seed_file\n";
} else {
    echo "Error: Failed to write to seed file: $image_seed_file\n";
}

// Clear categories cache so games show up with new images immediately
if (function_exists('game_api_jili_clear_category_cache')) {
    game_api_jili_clear_category_cache();
    echo "Cleared JILI category cache.\n";
}
