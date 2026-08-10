<?php
session_start();

$db_path = file_exists('../includes/db.php') ? '../includes/db.php' : 'includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
} else {
    die("Database connection error");
}
require_once '../includes/game_api_helper.php';
require_once '../includes/gamblly_api_helper.php';
require_once '../includes/game_api_evolution_patch.php';
game_api_start_error_logging();
game_api_ensure_schema($conn, false);
if (function_exists('game_api_seed_jili_mappings')) { game_api_seed_jili_mappings($conn); }
if (function_exists('game_api_seed_sbo_mappings')) { game_api_seed_sbo_mappings($conn); }
if (function_exists('game_api_seed_dpsports_mappings')) { game_api_seed_dpsports_mappings($conn); }
if (function_exists('game_api_evolution_ensure_patch')) { game_api_evolution_ensure_patch($conn); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$local_game_uid = isset($_GET['game_id']) ? trim($_GET['game_id']) : '';
$local_game_uid = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $local_game_uid);
$user_id_db = (int)$_SESSION['user_id'];

// The launch request can wait on an external API. Release the session file lock
// immediately so the user's other tabs/AJAX requests are not blocked meanwhile.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

function show_launch_error($title, $message) {
    echo "<div style='background:#1a1a1a; color:#ff4d4d; padding:50px; text-align:center; font-family:sans-serif; min-height:100vh;'>";
    echo "<h2>❌ " . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h2>";
    echo "<p style='max-width:680px;margin:20px auto;color:#ddd;'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<a href='dashboard.php' style='color:#00f3ff; text-decoration:none; font-weight:bold;'>Return to Lobby</a>";
    echo "</div>";
    exit;
}

function oxen_send_launch_request($endpoint, $payload, $apiToken = '', $secretKey = '') {
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonPayload === false) {
        return array(
            'mode' => 'json_oxen_working_flow',
            'response' => '',
            'decoded' => null,
            'json_error' => json_last_error_msg(),
            'curl_error' => 'JSON encode failed',
            'http_code' => 0,
            'content_type' => '',
            'game_url' => ''
        );
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    if (defined('CURLOPT_TCP_NODELAY')) { curl_setopt($ch, CURLOPT_TCP_NODELAY, true); }
    if (defined('CURLOPT_DNS_CACHE_TIMEOUT')) { curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300); }
    if (defined('CURLOPT_FRESH_CONNECT')) { curl_setopt($ch, CURLOPT_FRESH_CONNECT, false); }
    if (defined('CURLOPT_FORBID_REUSE')) { curl_setopt($ch, CURLOPT_FORBID_REUSE, false); }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'OXEN-Game-Launcher/3.1-speed');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Token: ' . $apiToken,
        'X-Secret-Key: ' . $secretKey,
        'Content-Length: ' . strlen($jsonPayload)
    ));

    $startedAt = microtime(true);
    $response = curl_exec($ch);
    $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $decoded = json_decode((string)$response, true);
    return array(
        'mode' => 'json_oxen_working_flow',
        'response' => $response,
        'decoded' => $decoded,
        'json_error' => json_last_error_msg(),
        'curl_error' => $curl_error,
        'http_code' => $http_code,
        'content_type' => $content_type,
        'duration_ms' => $durationMs,
        'game_url' => is_array($decoded) ? game_api_extract_game_url($decoded) : ''
    );
}


function oxen_endpoint_with_query($endpoint, $params) {
    $clean = array();
    foreach ((array)$params as $key => $value) {
        if ($value === null) { continue; }
        $value = (string)$value;
        if ($value === '') { continue; }
        $clean[$key] = $value;
    }
    if (empty($clean)) { return $endpoint; }
    return $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . http_build_query($clean);
}


function oxen_endpoint_replace_path($endpoint, $path) {
    $parts = parse_url($endpoint);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) { return $endpoint; }
    $url = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port']) && $parts['port'] !== '') { $url .= ':' . $parts['port']; }
    if ($path === '' || $path[0] !== '/') { $path = '/' . $path; }
    return $url . $path;
}

function oxen_send_launch_form_request($endpoint, $payload, $apiToken = '', $secretKey = '') {
    $formPayload = http_build_query($payload);
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $formPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    if (defined('CURLOPT_TCP_NODELAY')) { curl_setopt($ch, CURLOPT_TCP_NODELAY, true); }
    if (defined('CURLOPT_DNS_CACHE_TIMEOUT')) { curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300); }
    if (defined('CURLOPT_FRESH_CONNECT')) { curl_setopt($ch, CURLOPT_FRESH_CONNECT, false); }
    if (defined('CURLOPT_FORBID_REUSE')) { curl_setopt($ch, CURLOPT_FORBID_REUSE, false); }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'OXEN-Game-Launcher/3.1-speed');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'X-API-Token: ' . $apiToken,
        'X-Secret-Key: ' . $secretKey,
        'Content-Length: ' . strlen($formPayload)
    ));

    $startedAt = microtime(true);
    $response = curl_exec($ch);
    $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $decoded = json_decode((string)$response, true);
    return array(
        'mode' => 'form_oxen_working_flow',
        'response' => $response,
        'decoded' => $decoded,
        'json_error' => json_last_error_msg(),
        'curl_error' => $curl_error,
        'http_code' => $http_code,
        'content_type' => $content_type,
        'duration_ms' => $durationMs,
        'game_url' => is_array($decoded) ? game_api_extract_game_url($decoded) : ''
    );
}


function oxen_is_game_unavailable_response($decoded) {
    if (!is_array($decoded)) { return false; }
    $msg = '';
    if (isset($decoded['msg'])) { $msg .= ' ' . (string)$decoded['msg']; }
    if (isset($decoded['message'])) { $msg .= ' ' . (string)$decoded['message']; }
    if (isset($decoded['error_code']) && (string)$decoded['error_code'] === '10017') { return true; }
    if (isset($decoded['code']) && (string)$decoded['code'] === '10017') { return true; }
    return (stripos($msg, 'Game is not available') !== false || stripos($msg, 'game unavailable') !== false);
}

function oxen_is_non_retryable_launch_error($decoded, $message = '', $httpCode = 0) {
    $text = strtolower(trim((string)$message));
    if (is_array($decoded)) {
        foreach (array('msg','message','error','error_message','Message') as $key) {
            if (isset($decoded[$key]) && $decoded[$key] !== '') {
                $text .= ' ' . strtolower((string)$decoded[$key]);
            }
        }
    }

    // Changing game-code/vendor aliases cannot fix wallet/auth/account errors.
    // Stop immediately instead of waiting through every provider fallback.
    $nonRetryableNeedles = array(
        'insufficient user balance',
        'insufficient balance',
        'invalid api token',
        'invalid token',
        'token expired',
        'unauthorized',
        'forbidden',
        'invalid secret',
        'secret key',
        'invalid player',
        'player not found',
        'account suspended',
        'account blocked',
        'merchant disabled'
    );
    foreach ($nonRetryableNeedles as $needle) {
        if (strpos($text, $needle) !== false) { return true; }
    }

    return ((int)$httpCode === 401 || (int)$httpCode === 403);
}

function oxen_payload_with_game_value($basePayload, $gameValue, $numericGameCode, $gameUid) {
    $payload = $basePayload;
    $payload['GameId'] = (string)$gameValue;
    $payload['gameCode'] = (string)$gameValue;
    $payload['game_code'] = (string)$gameValue;
    $payload['game_id'] = (string)$gameValue;
    $payload['gameId'] = (string)$gameValue;
    $payload['gameUID'] = (string)$gameUid;
    $payload['providerGameCode'] = (string)$numericGameCode;
    $payload['originalGameCode'] = (string)$numericGameCode;
    $payload['sourceGameCode'] = (string)$numericGameCode;
    $payload['jiliCode'] = (string)$numericGameCode;
    $payload['pgsoftCode'] = (string)$numericGameCode;
    return $payload;
}

if ($local_game_uid === '') {
    game_api_debug_log('launch_failed_missing_game_id', array('user_id' => $user_id_db));
    show_launch_error('Game Launch Failed', 'Game ID is missing.');
}

$u_stmt = $conn->prepare("SELECT id, username, balance FROM users WHERE id=? LIMIT 1");
$u_stmt->bind_param('i', $user_id_db);
$u_stmt->execute();
$u_res = $u_stmt->get_result();
if (!$u_res || $u_res->num_rows === 0) {
    game_api_debug_log('launch_failed_user_not_found', array('user_id' => $user_id_db, 'local_game_uid' => $local_game_uid));
    show_launch_error('Game Launch Failed', 'User account was not found.');
}
$u_data = $u_res->fetch_assoc();
$u_stmt->close();

$game = game_api_find_game($conn, $local_game_uid);
if (!$game) {
    game_api_debug_log('launch_failed_game_not_found', array('user_id' => $u_data['id'], 'local_game_uid' => $local_game_uid));
    show_launch_error('Game Launch Failed', 'This game was not found in the local database.');
}

// JILI duplicate/wrong-launch guard.
// Some mixed category/popular cards can point to a non-JILI provider row that shares the same
// JILI public Code/name (example: Super Ace code 49). In that case the image/title looks JILI
// but the final game opens another provider and may show MSG 104 region unavailable. Route only
// this JILI alias collision to the active provider_id=49 JILI row; other providers remain unchanged.
if (function_exists('game_api_jili_canonical_launch_uid')) {
    $jiliAliasUid = game_api_jili_canonical_launch_uid($conn, $game);
    if ($jiliAliasUid !== '' && $jiliAliasUid !== $local_game_uid) {
        $aliasGame = game_api_find_game($conn, $jiliAliasUid);
        if ($aliasGame && (!isset($aliasGame['status']) || $aliasGame['status'] === 'active')) {
            game_api_debug_log('jili_alias_launch_redirect', array(
                'user_id' => $u_data['id'],
                'from_local_game_uid' => $local_game_uid,
                'from_provider_id' => isset($game['provider_id']) ? $game['provider_id'] : '',
                'from_vendor_code' => isset($game['api_vendor_code']) ? $game['api_vendor_code'] : '',
                'from_game_name' => isset($game['name']) ? $game['name'] : '',
                'to_local_game_uid' => $jiliAliasUid,
                'to_provider_id' => isset($aliasGame['provider_id']) ? $aliasGame['provider_id'] : '',
                'to_vendor_code' => isset($aliasGame['api_vendor_code']) ? $aliasGame['api_vendor_code'] : '',
                'to_game_name' => isset($aliasGame['name']) ? $aliasGame['name'] : ''
            ));
            $game = $aliasGame;
            $local_game_uid = $jiliAliasUid;
        }
    }
}

if (isset($game['status']) && $game['status'] !== 'active') {
    game_api_debug_log('launch_failed_game_inactive', array('user_id' => $u_data['id'], 'local_game_uid' => $local_game_uid, 'status' => $game['status']));
    show_launch_error('Game Temporarily Unavailable', 'This game is not active right now.');
}
if (isset($game['provider_status']) && $game['provider_status'] !== null && $game['provider_status'] !== 'active') {
    game_api_debug_log('launch_failed_provider_inactive', array('user_id' => $u_data['id'], 'local_game_uid' => $local_game_uid, 'provider_id' => $game['provider_id'], 'provider_status' => $game['provider_status']));
    show_launch_error('Provider Temporarily Unavailable', 'This provider is currently under maintenance.');
}

$__gambllyLaunchSettings = game_api_get_settings($conn);
$isGambllyProvider = function_exists('gamblly_api_is_enabled') && gamblly_api_is_enabled($conn, $__gambllyLaunchSettings);

if (!$isGambllyProvider && !game_api_is_supported_launch_provider($game)) {
    game_api_debug_log('launch_skipped_provider_phase', array(
        'user_id' => $u_data['id'],
        'local_game_uid' => $local_game_uid,
        'game_name' => isset($game['name']) ? $game['name'] : '',
        'provider_id' => isset($game['provider_id']) ? $game['provider_id'] : '',
        'provider_name' => isset($game['provider_name']) ? $game['provider_name'] : '',
        'api_provider_name' => isset($game['api_provider_name']) ? $game['api_provider_name'] : '',
        'active_phase' => 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING,MINI,NETENT,ONGAMING,PRAGMATIC,SAGAMING,SBO,DPSPORTS,SEXY,T1,SPRIBE,TF,TFG,YEEBET'
    ));
    show_launch_error('Provider Not Configured Yet', 'এখন provider-by-provider phase এ JILI, PGSoft, SABA Sports, United Gaming, JDB, TADAGaming, CQ9, 100HP, 2J, BNG3Oaks ROW, 5G Gaming, 9Wicket, Amigo, AOG, Astar এবং BGaming, BigTimeGaming, BTGaming এবং Evolution Live, FaChaiGaming, FastSpin, GameArt, IDEAL, INOUT, LuckSportGaming এবং Microgaming, Pragmatic এবং SBO/SBO VirtualSports/Sportsbook এবং DP Sports/DP Esports, Sexy/Sexy Video/T1/Spribe/TF/TFG/YEEBET, T1, Spribe, TF/TFG এবং YEEBET setup/test চলছে। দয়া করে এই providerগুলোর game দিয়ে test করুন।');
}

$settings = (isset($__gambllyLaunchSettings) && is_array($__gambllyLaunchSettings)) ? $__gambllyLaunchSettings : game_api_get_settings($conn);
if (!isset($isGambllyProvider)) { $isGambllyProvider = function_exists('gamblly_api_is_enabled') && gamblly_api_is_enabled($conn, $settings); }
$apiEndpoint = trim(isset($settings['api_endpoint']) ? $settings['api_endpoint'] : 'https://oxentech.asia/api/devtools.php');
$apiToken = trim(isset($settings['api_token']) ? $settings['api_token'] : '');
$secretKey = trim(isset($settings['secret_key']) ? $settings['secret_key'] : '');
$currency = strtoupper(trim(isset($settings['currency_code']) ? $settings['currency_code'] : 'BDT'));
$agentCode = game_api_clean_no_space(isset($settings['agent_code']) ? $settings['agent_code'] : '');
if ($agentCode !== (isset($settings['agent_code']) ? trim((string)$settings['agent_code']) : '')) { game_api_set_setting($conn, 'agent_code', $agentCode); }
if ($apiEndpoint === '') { $apiEndpoint = 'https://oxentech.asia/api/devtools.php'; }
if ($currency === '') { $currency = 'BDT'; }
$siteCurrency = $currency;

$apiGameId = trim(isset($game['api_game_id']) ? $game['api_game_id'] : '');
$apiGameCode = trim(isset($game['api_game_code']) ? $game['api_game_code'] : '');
$isPgsoft = game_api_is_provider_pgsoft($game);
$isJili = game_api_is_first_provider_jili($game);
$isSaba = game_api_is_provider_saba($game);
$isUnitedGaming = game_api_is_provider_unitedgaming($game);
$isJdb = game_api_is_provider_jdb($game);
$isTadaGaming = game_api_is_provider_tadagaming($game);
$is2j = game_api_is_provider_2j($game);
$isCq9 = game_api_is_provider_cq9($game);
$is100hp = game_api_is_provider_100hp($game);
$isBng3oksRow = game_api_is_provider_bng3oks_row($game);
$is5gGaming = game_api_is_provider_5ggaming($game);
$is9Wicket = game_api_is_provider_9wicket($game);
$isAmigo = game_api_is_provider_amigo($game);
$isAog = game_api_is_provider_aog($game);
$isAstar = game_api_is_provider_astar($game);
$isBgaming = game_api_is_provider_bgaming($game);
$isBigTimeGaming = game_api_is_provider_bigtimegaming($game);
$isBtGaming = game_api_is_provider_btgaming($game);
$isEvolutionLive = game_api_is_provider_evolutionlive($game);
$isFachaiGaming = game_api_is_provider_fachaigaming($game);
$isFastSpin = game_api_is_provider_fastspin($game);
$isGameArt = game_api_is_provider_gameart($game);
$isIdeal = game_api_is_provider_ideal($game);
$isInOut = game_api_is_provider_inout($game);
$isLuckSportGaming = game_api_is_provider_lucksportgaming($game);
$isMicrogaming = game_api_is_provider_microgaming($game);
$isMini = game_api_is_provider_mini($game);
$isNetEnt = game_api_is_provider_netent($game);
$isOnGaming = game_api_is_provider_ongaming($game);
$isSaGaming = game_api_is_provider_sagaming($game);
$isSbo = game_api_is_provider_sbo($game);
$isDpSports = game_api_is_provider_dpsports($game);
$isSexy = game_api_is_provider_sexy($game);
$isPragmatic = game_api_is_provider_pragmatic($game);
$isT1 = game_api_is_provider_t1($game);
$isSpribe = game_api_is_provider_spribe($game);
$isTf = game_api_is_provider_tf($game);
$isTfg = game_api_is_provider_tfg($game);
$isYeebet = game_api_is_provider_yeebet($game);
$vendorCode = trim(isset($game['api_vendor_code']) && $game['api_vendor_code'] !== '' ? $game['api_vendor_code'] : game_api_provider_vendor_code($game));
if ($vendorCode === '') {
    if ($isSexy) { $vendorCode = game_api_provider_vendor_code($game); }
    elseif ($isSbo) { $vendorCode = game_api_provider_vendor_code($game); }
    elseif ($isSaGaming) { $vendorCode = 'sagaming'; }
    elseif ($isPragmatic) { $vendorCode = 'pragmatic'; }
    elseif ($isOnGaming) { $vendorCode = 'ongaming'; }
    elseif ($isNetEnt) { $vendorCode = 'netent'; }
    elseif ($isPragmatic) { $vendorCode = 'pragmatic'; }
elseif ($isSaGaming) { $vendorCode = 'sagaming'; }
elseif ($isSbo) { $vendorCode = game_api_provider_vendor_code($game); }
elseif ($isSexy) { $vendorCode = game_api_provider_vendor_code($game); }
else
if ($isYeebet) { $vendorCode = 'yeebet'; }
elseif ($isTfg) { $vendorCode = 'tfg'; }
elseif ($isTf) { $vendorCode = 'tf'; }
elseif ($isSpribe) { $vendorCode = 'spribe'; }
elseif ($isT1) { $vendorCode = 't1'; }
if ($isMini) { $vendorCode = 'mini'; }
    elseif ($isOnGaming) { $vendorCode = 'ongaming'; }
elseif ($isNetEnt) { $vendorCode = 'netent'; }
elseif ($isMini) { $vendorCode = 'mini'; }
elseif ($isMicrogaming) { $vendorCode = 'microgaming'; }
    elseif ($isLuckSportGaming) { $vendorCode = 'lucksportgaming'; }
    elseif ($isInOut) { $vendorCode = 'inout'; }
    elseif ($isIdeal) { $vendorCode = 'ideal'; }
    elseif ($isGameArt) { $vendorCode = 'gameart'; }
    elseif ($isFastSpin) { $vendorCode = 'fastspin'; }
    elseif ($isFachaiGaming) { $vendorCode = 'fachaigaming'; }
    elseif ($isEvolutionLive) { $vendorCode = 'evolutionlive'; }
    elseif ($isBtGaming) { $vendorCode = 'btgaming'; }
    elseif ($isBigTimeGaming) { $vendorCode = 'bigtimegaming'; }
    elseif ($isBgaming) { $vendorCode = 'bgaming'; }
    elseif ($isAstar) { $vendorCode = 'astar'; }
    elseif ($isAog) { $vendorCode = 'aog'; }
    elseif ($isAmigo) { $vendorCode = 'amigo'; }
    elseif ($is9Wicket) { $vendorCode = '9wicket'; }
    elseif ($is5gGaming) { $vendorCode = '5ggaming'; }
    elseif ($isBng3oksRow) { $vendorCode = 'bng3oks-row'; }
    elseif ($is2j) { $vendorCode = '2j'; }
    elseif ($is100hp) { $vendorCode = '100hp'; }
    elseif ($isCq9) { $vendorCode = 'cq9'; }
    elseif ($isTadaGaming) { $vendorCode = 'tadagaming'; }
    elseif ($isJdb) { $vendorCode = 'JDB'; }
    elseif ($isUnitedGaming) { $vendorCode = 'unitedgaming'; }
    elseif ($isSaba) { $vendorCode = 'saba'; }
    elseif ($isPgsoft) { $vendorCode = 'pgsoft'; }
    else { $vendorCode = 'JILI'; }
}
if ($isMini) { $vendorCode = 'mini'; }
elseif ($isMicrogaming) { $vendorCode = 'microgaming'; }
elseif ($isLuckSportGaming) { $vendorCode = 'lucksportgaming'; }
elseif ($isInOut) { $vendorCode = 'inout'; }
elseif ($isIdeal) { $vendorCode = 'ideal'; }
elseif ($isGameArt) { $vendorCode = 'gameart'; }
elseif ($isFastSpin) { $vendorCode = 'fastspin'; }
elseif ($isFachaiGaming) { $vendorCode = 'fachaigaming'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('evolutionlive','evolutionliveasia'), true) || in_array(strtolower($vendorCode), array('evolutionlive','evolution live','evolution-live','evolutionliveasia','evolution-live-asia','evolution live asia'), true)) { $vendorCode = 'evolutionlive'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('btgaming','betgaming','btgame'), true) || in_array(strtolower($vendorCode), array('btgaming','bt gaming','bt-gaming','betgaming','btgame'), true)) { $vendorCode = 'btgaming'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('bigtimegaming','btg','bigtime'), true) || in_array(strtolower($vendorCode), array('bigtimegaming','big time gaming','big-time-gaming','btg','bigtime'), true)) { $vendorCode = 'bigtimegaming'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('bgaming','bgaminggames'), true) || in_array(strtolower($vendorCode), array('bgaming','b gaming','b-gaming','bgaming games'), true)) { $vendorCode = 'bgaming'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('astar','astargaming','astargames'), true) || in_array(strtolower($vendorCode), array('astar','astar gaming','astar-games','astar games'), true)) { $vendorCode = 'astar'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('aog','aogaming','aoggames'), true) || in_array(strtolower($vendorCode), array('aog','aog gaming','aog-games','aog games'), true)) { $vendorCode = 'aog'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('amigo','amigogaming','amigogames'), true) || in_array(strtolower($vendorCode), array('amigo','amigo gaming','amigo-games','amigo games'), true)) { $vendorCode = 'amigo'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('sexy','sexyvideo','sexygaming'), true) || in_array(strtolower($vendorCode), array('sexy','sexy_video','sexy video','sexy-video','sexygaming','sexy gaming','sexy-gaming'), true)) { $vendorCode = $isSexy ? game_api_provider_vendor_code($game) : 'sexy'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('sbo','sbosportsbook','sbovirtualsport','sbovirtualsports','sportsbook'), true) || in_array(strtolower($vendorCode), array('sbo','sbo sportsbook','sbo-sportsbook','sbovirtualsport','sbovirtualsports','sbo virtualsport','sbo virtualsports','sbo-virtualsport','sbo-virtualsports','sportsbook'), true)) { $vendorCode = $isSbo ? game_api_provider_vendor_code($game) : 'sbo'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('sagaming','sagame','sacasino','salive'), true) || in_array(strtolower($vendorCode), array('sagaming','sa gaming','sa-gaming','sa casino','sa live'), true)) { $vendorCode = 'sagaming'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('pragmatic','pragmaticplay','pragmaticlive','pragmaticplaylive','pp','pplive'), true) || in_array(strtolower($vendorCode), array('pragmatic','pragmatic play','pragmatic-play','pragmaticplay','pragmatic live','pragmatic-live','pragmaticplaylive'), true)) { $vendorCode = 'pragmatic'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('ongaming','ongamingcasino'), true) || in_array(strtolower($vendorCode), array('ongaming','on gaming','on-gaming','ongaming casino','on gaming casino'), true)) { $vendorCode = 'ongaming'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('netent','netentgaming','evolutionnetent','evolutioinnetent'), true) || in_array(strtolower($vendorCode), array('netent','net ent','net-ent','netent gaming','evolution-netent','evolution netent','evolutioin-netent','evolutioin netent'), true)) { $vendorCode = 'netent'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('mini','minigames','minigaming'), true) || in_array(strtolower($vendorCode), array('mini','mini games','mini-games','minigames','mini gaming','mini-gaming'), true)) { $vendorCode = 'mini'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('microgaming','mg','mgplus','mglive','mglivegrand'), true) || in_array(strtolower($vendorCode), array('microgaming','micro gaming','micro-gaming','mg','mgplus','mglive','mglivegrand'), true)) { $vendorCode = 'microgaming'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('lucksportgaming','luckysportgaming','luckysport','lucksport'), true) || in_array(strtolower($vendorCode), array('lucksportgaming','luck sport gaming','luck-sport-gaming','lucky sport','lucky-sport','luckysport','luck sport'), true)) { $vendorCode = 'lucksportgaming'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('inout','inoutgaming','inoutgames'), true) || in_array(strtolower($vendorCode), array('inout','in out','in-out','inout gaming','inout-games','inout games'), true)) { $vendorCode = 'inout'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('ideal','idealgaming','idealslot'), true) || in_array(strtolower($vendorCode), array('ideal','ideal gaming','ideal-gaming','ideal slot'), true)) { $vendorCode = 'ideal'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('yeebet','yeebetgaming','yeegaming'), true) || in_array(strtolower($vendorCode), array('yeebet','yee bet','yee-bet'), true)) { $vendorCode = 'yeebet'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('tfg','tfgaming'), true) || in_array(strtolower($vendorCode), array('tfg','tf-gaming','tf gaming','tfgaming'), true)) { $vendorCode = $isTfg ? 'tfg' : ($isTf ? 'tf' : 'tfg'); }
elseif (strtolower($vendorCode) === 'tf') { $vendorCode = 'tf'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('spribe','spribegaming'), true) || in_array(strtolower($vendorCode), array('spribe','spribe gaming'), true)) { $vendorCode = 'spribe'; }
elseif (strtolower($vendorCode) === 't1' || strtolower($vendorCode) === 't1gaming' || strtolower($vendorCode) === 't1 gaming') { $vendorCode = 't1'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('9w','9wicket','9wickets','ninewicket','ninewickets'), true) || in_array(strtolower($vendorCode), array('9w','9wicket','9wickets'), true)) { $vendorCode = '9wicket'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('5ggaming','5g','kys','s5g','kysh5','s5gh5'), true) || in_array(strtolower($vendorCode), array('5g gaming','kys-h5','s5g-h5'), true)) { $vendorCode = '5ggaming'; }
elseif (in_array(preg_replace('/[^a-z0-9]+/', '', strtolower($vendorCode)), array('bng3oksrow','bng3oks','bng','3oaks','3oaksbng','boongo'), true) || in_array(strtolower($vendorCode), array('bng3oks-row','bng3oks row','3oaks-bng','3oaks (bng)'), true)) { $vendorCode = 'bng3oks-row'; }
elseif (strtolower($vendorCode) === '2j' || strtolower($vendorCode) === '2 j' || strtolower($vendorCode) === 'twoj' || strtolower($vendorCode) === 'two j') { $vendorCode = '2j'; }
elseif (strtolower($vendorCode) === '100hp' || strtolower($vendorCode) === '100 hp' || strtolower($vendorCode) === 'hundredhp') { $vendorCode = '100hp'; }
elseif (strtolower($vendorCode) === 'cq9' || strtolower($vendorCode) === 'cq9gaming' || strtolower($vendorCode) === 'cq9 gaming') { $vendorCode = 'cq9'; }
elseif (strtolower($vendorCode) === 'tadagaming' || strtolower($vendorCode) === 'tada gaming' || strtolower($vendorCode) === 'tada') { $vendorCode = 'tadagaming'; }
elseif (strtoupper($vendorCode) === 'JDB' || strtolower($vendorCode) === 'jdbgaming' || strtolower($vendorCode) === 'jdb gaming') { $vendorCode = 'JDB'; }
elseif (strtolower($vendorCode) === 'unitedgaming' || strtolower($vendorCode) === 'united gaming' || strtolower($vendorCode) === 'united_gaming' || strtolower($vendorCode) === 'united') { $vendorCode = 'unitedgaming'; }
elseif (strtolower($vendorCode) === 'saba' || strtolower($vendorCode) === 'sabasports' || strtolower($vendorCode) === 'saba sports') { $vendorCode = 'saba'; }
elseif (strtolower($vendorCode) === 'pgsoft' || strtolower($vendorCode) === 'pg') { $vendorCode = 'pgsoft'; }
elseif (strtoupper($vendorCode) === 'JILI') { $vendorCode = 'JILI'; }
if ($isDpSports) { $vendorCode = game_api_dpsports_vendor_for_game($game); }

$pragmaticProviderGameCode = '';
if ($isPragmatic) {
    $pragmaticProviderGameCode = trim(isset($game['api_game_code']) ? (string)$game['api_game_code'] : '');
    if ($pragmaticProviderGameCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $pragmaticProviderGameCode)) { $pragmaticProviderGameCode = ''; }
}


$newProviderGameCode = '';
if ($isT1 || $isSpribe || $isTf || $isTfg || $isYeebet) {
    $newProviderGameCode = trim(isset($game['api_game_code']) ? (string)$game['api_game_code'] : '');
    if ($newProviderGameCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $newProviderGameCode)) { $newProviderGameCode = ''; }
}

$microgamingProviderGameCode = '';
if ($isMicrogaming) {
    $microgamingProviderGameCode = trim(isset($game['api_game_code']) ? (string)$game['api_game_code'] : '');
    if ($microgamingProviderGameCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $microgamingProviderGameCode)) { $microgamingProviderGameCode = ''; }
}
if ($apiGameCode === '' && $apiGameId !== '') {
    // Backward compatibility with older mapping. Working flow requires gameCode; if only one value exists, use it.
    $apiGameCode = $apiGameId;
}
if ($isSaba && $apiGameId !== '') {
    // SABA Sports has no separate numeric Code in the docs; the launch key is the Game UID.
    $apiGameCode = $apiGameId;
}
if ($isBigTimeGaming && $apiGameId !== '') {
    // BigTimeGaming list provides 32-character Game UID; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}
if ($isBtGaming && $apiGameId !== '') {
    // BTGaming list provides 32-character Game UID; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}
if ($isEvolutionLive && $apiGameId !== '') {
    // Evolution Live documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isFachaiGaming && $apiGameId !== '') {
    // FaChaiGaming documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isFastSpin && $apiGameId !== '') {
    // FastSpin documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isGameArt && $apiGameId !== '') {
    // GameArt documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isIdeal && $apiGameId !== '') {
    // IDEAL documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isInOut && $apiGameId !== '') {
    // INOUT documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isLuckSportGaming && $apiGameId !== '') {
    // LuckSportGaming documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isMicrogaming && $apiGameId !== '') {
    // Microgaming documentation/list provides a 32-character Game UID; use it as the primary launch key.
    $apiGameCode = $apiGameId;
}
if ($isMini && $apiGameId !== '') {
    // Mini documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isNetEnt && $apiGameId !== '') {
    // NetEnt documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isOnGaming && $apiGameId !== '') {
    // OnGaming documentation/list uses the 32-character Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isPragmatic && $apiGameId !== '') {
    // Pragmatic documentation/list uses the 32-character Game UID as primary launch key.
    $apiGameCode = $apiGameId;
}
if ($isUnitedGaming && $apiGameId !== '') {
    // United Gaming docs provide only Game UID; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}
if ($isJdb && $apiGameId !== '') {
    // JDB documentation provides Game UID as launch key; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}
if ($isCq9 && $apiGameId !== '') {
    // CQ9 documentation/game list provides a 32-character Game UID; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}
if ($is100hp && $apiGameId !== '') {
    // 100HP documentation/game list provides a 32-character Game UID; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}
if ($is2j && $apiGameId !== '') {
    // 2J documentation/game list provides a 32-character Game UID; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}
if ($isBng3oksRow && $apiGameId !== '') {
    // BNG3Oaks ROW documentation/game list provides a 32-character Game UID; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}
if ($is9Wicket && $apiGameId !== '') {
    // 9Wicket documentation provides Game UID only and supports /game/v2 transfer-wallet launch.
    $apiGameCode = $apiGameId;
}
if ($isAmigo && $apiGameId !== '') {
    // Amigo documentation provides Game UID as the launch key.
    $apiGameCode = $apiGameId;
}
if ($isAog && $apiGameId !== '') {
    // AOG documentation provides Game UID as the launch key.
    $apiGameCode = $apiGameId;
}

if (($isT1 || $isSpribe || $isTf || $isTfg || $isYeebet) && $apiGameId !== '') {
    // New provider documentation uses the 32-character Game UID as primary launch key.
    $apiGameCode = $apiGameId;
}
if (!$isGambllyProvider && $apiGameCode === '') {
    game_api_log($conn, array(
        'user_id' => $u_data['id'],
        'local_game_uid' => $local_game_uid,
        'status' => 'mapping_missing',
        'message' => 'No provider gameCode mapped for this local game.'
    ));
    game_api_debug_log('launch_failed_mapping_missing', array(
        'user_id' => $u_data['id'],
        'local_game_uid' => $local_game_uid,
        'game_name' => isset($game['name']) ? $game['name'] : '',
        'provider_id' => isset($game['provider_id']) ? $game['provider_id'] : '',
        'category' => isset($game['category']) ? $game['category'] : ''
    ));
    show_launch_error('Game Mapping Missing', 'This game has no provider gameCode mapping yet. CQ9/100HP/2J/BNG3Oaks ROW/5G Gaming/9Wicket/Amigo/AOG/Astar/BGaming/BigTimeGaming/BTGaming/Evolution Live/FaChaiGaming/FastSpin/GameArt/IDEAL/INOUT/LuckSportGaming/Microgaming mapping is auto-applied from code level. Please check api_game_id/api_game_code and send api/game_api_debug.log if it still fails.');
}

if ($apiToken === '') {
    game_api_debug_log('launch_failed_api_token_missing', array('user_id' => $u_data['id'], 'local_game_uid' => $local_game_uid, 'api_game_code' => $apiGameCode));
    show_launch_error('API Key Missing', 'Please set the API Key from Admin Panel > Game API Key before launching games.');
}
if (!$isGambllyProvider && $secretKey === '') {
    game_api_debug_log('launch_failed_secret_key_missing', array('user_id' => $u_data['id'], 'local_game_uid' => $local_game_uid, 'api_game_code' => $apiGameCode));
    show_launch_error('Secret Key Missing', 'Please set the Secret Key from Admin Panel > Game API Key before launching games.');
}

$returnUrl = game_api_site_url('/player/dashboard.php');
$callbackUrl = game_api_site_url('/api/callback.php');
$homeUrl = game_api_site_url('/');

// Keep the real DB wallet balance as the source of truth.
// Earlier EvolutionLive patch sent a fake 0.01 preview/demo balance when the user had 0.00.
// OXEN may still return a wrapper URL, but EvolutionLive can reject the final session as GAME NOT FOUND
// because the wrapper/demo value and callback wallet value do not match.
$actualBalanceValue = round((float)$u_data['balance'], 2);
$walletBalance = number_format($actualBalanceValue, 2, '.', '');
$isPreviewLaunch = ($actualBalanceValue <= 0.0);
$balance = $isPreviewLaunch ? '0.01' : $walletBalance;
$playerName = !empty($u_data['username']) ? (string)$u_data['username'] : ('user_' . $u_data['id']);

if ($isGambllyProvider) {
    $gambllyGameUid = $apiGameId !== '' ? $apiGameId : ($apiGameCode !== '' ? $apiGameCode : $local_game_uid);
    $gambllyLaunch = gamblly_api_launch_game($conn, $u_data, $gambllyGameUid, $walletBalance, $currency, $local_game_uid, $game);
    game_api_log($conn, array(
        'user_id' => $u_data['id'],
        'local_game_uid' => $local_game_uid,
        'api_game_id' => $gambllyGameUid,
        'endpoint' => isset($gambllyLaunch['payload']) ? (isset($settings['api_endpoint']) ? $settings['api_endpoint'] : '') : '',
        'request_data' => isset($gambllyLaunch['payload']) ? $gambllyLaunch['payload'] : array(),
        'response_data' => isset($gambllyLaunch['response']) ? $gambllyLaunch['response'] : '',
        'status' => !empty($gambllyLaunch['success']) ? 'success' : 'failed',
        'message' => isset($gambllyLaunch['message']) ? $gambllyLaunch['message'] : ''
    ));
    game_api_debug_log('gamblly_launch_attempt', array(
        'user_id' => $u_data['id'],
        'local_game_uid' => $local_game_uid,
        'gamblly_game_uid' => $gambllyGameUid,
        'http_code' => isset($gambllyLaunch['http_code']) ? $gambllyLaunch['http_code'] : 0,
        'success' => !empty($gambllyLaunch['success']),
        'message' => isset($gambllyLaunch['message']) ? $gambllyLaunch['message'] : '',
        'curl_error' => isset($gambllyLaunch['curl_error']) ? $gambllyLaunch['curl_error'] : ''
    ));
    if (empty($gambllyLaunch['success']) || empty($gambllyLaunch['url'])) {
        $launchMessage = isset($gambllyLaunch['message']) && $gambllyLaunch['message'] !== '' ? $gambllyLaunch['message'] : 'Gamblly game launch URL was not returned.';
        show_launch_error('Game Launch Failed', $launchMessage);
    }
    $launchUrl = $gambllyLaunch['url'];
    echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Launching Game</title></head><body style='margin:0;background:#111;color:#fff;font-family:sans-serif;text-align:center;padding-top:40px;'><p>Launching game...</p><script>window.top.location.href = " . json_encode($launchUrl, JSON_UNESCAPED_SLASHES) . ";</script><a style='color:#00f3ff' href='" . htmlspecialchars($launchUrl, ENT_QUOTES, 'UTF-8') . "'>Open Game</a></body></html>";
    exit;
}

$sabaWidgetId = game_api_clean_no_space(game_api_get_setting($conn, 'saba_widget_id', 'sZ0P8C87'));
$sabaLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'saba_language', 'en')));
$sabaSportId = game_api_clean_no_space(game_api_get_setting($conn, 'saba_sport_id', ''));
if ($sabaLanguage === '') { $sabaLanguage = 'en'; }
$sabaExtrasParts = array();
if ($sabaWidgetId !== '') { $sabaExtrasParts[] = 'widgetId=' . $sabaWidgetId; }
if ($sabaSportId !== '') { $sabaExtrasParts[] = 'sportid=' . $sabaSportId; }
$sabaExtras = implode('&', $sabaExtrasParts);

$unitedGamingTheme = game_api_clean_no_space(game_api_get_setting($conn, 'unitedgaming_theme', 'style2'));
$unitedGamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'unitedgaming_language', 'en')));
if ($unitedGamingTheme === '') { $unitedGamingTheme = 'style2'; }
if ($unitedGamingLanguage === '') { $unitedGamingLanguage = 'en'; }
$unitedGamingExtras = 'theme=' . $unitedGamingTheme;

$cq9Language = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'cq9_language', 'en')));
if ($cq9Language === '') { $cq9Language = 'en'; }
$cq9MemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$cq9MemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $cq9MemberAccount);
if ($cq9MemberAccount === '') { $cq9MemberAccount = (string)$u_data['id']; }

$hp100Language = strtolower(game_api_clean_no_space(game_api_get_setting($conn, '100hp_language', 'bn')));
if ($hp100Language === '') { $hp100Language = 'bn'; }
$hp100MemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$hp100MemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $hp100MemberAccount);
if ($hp100MemberAccount === '') { $hp100MemberAccount = (string)$u_data['id']; }


if ($isAstar && $apiGameId !== '') {
    // Astar documentation/game list provides the 32-character Game UID; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}
if ($isBgaming && $apiGameId !== '') {
    // BGaming documentation/game list provides the 32-character Game UID; use it as gameCode/GameId.
    $apiGameCode = $apiGameId;
}

$twoJLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, '2j_language', 'bn')));
if ($twoJLanguage === '') { $twoJLanguage = 'bn'; }
$twoJMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$twoJMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $twoJMemberAccount);
if ($twoJMemberAccount === '') { $twoJMemberAccount = (string)$u_data['id']; }

$bng3oksRowLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'bng3oks_row_language', 'en')));
if ($bng3oksRowLanguage === '') { $bng3oksRowLanguage = 'en'; }
$bng3oksRowMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$bng3oksRowMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $bng3oksRowMemberAccount);
if ($bng3oksRowMemberAccount === '') { $bng3oksRowMemberAccount = (string)$u_data['id']; }

$fiveGGamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, '5ggaming_language', 'bn')));
if ($fiveGGamingLanguage === '') { $fiveGGamingLanguage = 'bn'; }
$fiveGGamingMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$fiveGGamingMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $fiveGGamingMemberAccount);
if ($fiveGGamingMemberAccount === '') { $fiveGGamingMemberAccount = (string)$u_data['id']; }

$nineWicketLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, '9wicket_language', 'en')));
if ($nineWicketLanguage === '') { $nineWicketLanguage = 'en'; }
$nineWicketMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$nineWicketMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nineWicketMemberAccount);
if ($nineWicketMemberAccount === '') { $nineWicketMemberAccount = (string)$u_data['id']; }
$nineWicketSupportedCurrencies = array('INR','LKR','NPR','PKR','USDT');
$nineWicketCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, '9wicket_currency', '')));
$nineWicketCurrencyWasAdjusted = false;
$nineWicketTransferId = '';
if ($is9Wicket) {
    // 9Wicket /game/v2 transfer-wallet requires a non-empty transfer_id for the credit/launch handoff.
    // Keep it numeric but below signed 64-bit range; some gateway wrappers treat very long numbers as empty/invalid.
    $nineWicketUserDigits = str_pad((string)(((int)$u_data['id']) % 1000), 3, '0', STR_PAD_LEFT);
    $nineWicketRandomDigits = (string)mt_rand(100, 999);
    $nineWicketTransferId = date('ymdHis') . $nineWicketUserDigits . $nineWicketRandomDigits;
    if ($nineWicketTransferId === '') { $nineWicketTransferId = (string)time() . $nineWicketUserDigits; }
}

if ($is9Wicket) {
    if ($nineWicketCurrencyOverride !== '' && in_array($nineWicketCurrencyOverride, $nineWicketSupportedCurrencies, true)) {
        $currency = $nineWicketCurrencyOverride;
    } elseif (!in_array($currency, $nineWicketSupportedCurrencies, true)) {
        // 9Wicket docs do not list BDT; use a supported provider currency unless admin overrides game_settings.9wicket_currency.
        $currency = 'INR';
        $nineWicketCurrencyWasAdjusted = true;
    }
}

$amigoLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'amigo_language', 'en')));
if ($amigoLanguage === '') { $amigoLanguage = 'en'; }
$amigoMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$amigoMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $amigoMemberAccount);
if ($amigoMemberAccount === '') { $amigoMemberAccount = (string)$u_data['id']; }
$amigoSupportedCurrencies = array('BDT','IDR','INR','JPY','KHR','KRW','LAK','MMK','MNT','MYR','NPR','PHP','PKR','PYG','SGD','THB','USD','UZS','VND','BRL','RUB','KZT');

$aogLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'aog_language', 'en')));
if ($aogLanguage === '') { $aogLanguage = 'en'; }
$aogMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$aogMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $aogMemberAccount);
if ($aogMemberAccount === '') { $aogMemberAccount = (string)$u_data['id']; }
$aogSupportedCurrencies = array('MYR','JPY','KRW','THB','USD','IDR','INR','BRL','EUR','KHR','PKR','VND','MMK');
$aogCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'aog_currency', '')));
$aogCurrencyWasAdjusted = false;
if ($isAog) {
    if ($aogCurrencyOverride !== '' && in_array($aogCurrencyOverride, $aogSupportedCurrencies, true)) {
        $currency = $aogCurrencyOverride;
    } elseif (!in_array($currency, $aogSupportedCurrencies, true)) {
        // AOG docs do not list BDT; use a supported provider currency unless admin overrides game_settings.aog_currency.
        $currency = 'INR';
        $aogCurrencyWasAdjusted = true;
    }
}


$astarLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'astar_language', 'en')));
if ($astarLanguage === '') { $astarLanguage = 'en'; }
$astarMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$astarMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $astarMemberAccount);
if ($astarMemberAccount === '') { $astarMemberAccount = (string)$u_data['id']; }
$astarSupportedCurrencies = array('USD','JPY','KRW','AUD','THB','VND','IDR','MYR','INR','MMK','KES','USDT','BDT','PHP','GBP','AED','PKR','MXN','BRL','SGD','PEN','VES','HNL','CRC','GTQ');

$bgamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'bgaming_language', 'en')));
if ($bgamingLanguage === '') { $bgamingLanguage = 'en'; }
$bgamingMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$bgamingMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $bgamingMemberAccount);
if ($bgamingMemberAccount === '') { $bgamingMemberAccount = (string)$u_data['id']; }
$bgamingSupportedCurrencies = array('BDT','BRL','COP','EGP','IDR','INR','MYR','PEN','PHP','PKR','THB','TZS','USD','VND','XAF','ZAR','EUR','USDT','RUB','MMK','TRY','AED');

$bigtimegamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'bigtimegaming_language', 'en')));
if ($bigtimegamingLanguage === '') { $bigtimegamingLanguage = 'en'; }
$bigtimegamingMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$bigtimegamingMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $bigtimegamingMemberAccount);
if ($bigtimegamingMemberAccount === '') { $bigtimegamingMemberAccount = (string)$u_data['id']; }
$bigtimegamingSupportedCurrencies = array('BRL','USDT','RUB','USD','NGN','PEN','BWP','GHS','KES','MXN','ZAR','CLP','ARS');
$bigtimegamingCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'bigtimegaming_currency', '')));
$bigtimegamingCurrencyWasAdjusted = false;
if ($isBigTimeGaming) {
    if ($bigtimegamingCurrencyOverride !== '' && in_array($bigtimegamingCurrencyOverride, $bigtimegamingSupportedCurrencies, true)) {
        $currency = $bigtimegamingCurrencyOverride;
    } elseif (!in_array($currency, $bigtimegamingSupportedCurrencies, true)) {
        // BigTimeGaming docs do not list BDT; use USD unless admin overrides game_settings.bigtimegaming_currency.
        $currency = 'USD';
        $bigtimegamingCurrencyWasAdjusted = true;
    }
    $payload['Currency'] = $currency;
    $payload['currency'] = $currency;
}


$btgamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'btgaming_language', 'en')));
if ($btgamingLanguage === '') { $btgamingLanguage = 'en'; }
$btgamingMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$btgamingMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $btgamingMemberAccount);
if ($btgamingMemberAccount === '') { $btgamingMemberAccount = (string)$u_data['id']; }
$btgamingSupportedCurrencies = array('MYR','THB','JPY','USDT','SGD','PHP','KHR','MMK','VND','IDR','AUD','USD','INR','KRW','BRL','BDT','NPR','PKR','ARS','BOB','CAD','CLP','COP','EGP','ETB','EUR','HNL','MXN','NGN','PEN','PYG','TRY','TZS','ZAR','IRR','BND','KES','KZT','KGS','TJS','TMT','UZS','LAK','XOF','LKR','PLN','RUB','PGK','MNT');
$btgamingCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'btgaming_currency', '')));
$btgamingCurrencyWasAdjusted = false;
if ($isBtGaming) {
    if ($btgamingCurrencyOverride !== '' && in_array($btgamingCurrencyOverride, $btgamingSupportedCurrencies, true)) {
        $currency = $btgamingCurrencyOverride;
    } elseif (!in_array($currency, $btgamingSupportedCurrencies, true)) {
        // BTGaming supports BDT; if admin site currency is something else unsupported, use BDT.
        $currency = 'BDT';
        $btgamingCurrencyWasAdjusted = true;
    }
}
$btgamingProviderGameCode = $isBtGaming ? game_api_btgaming_provider_game_code($game) : '';


$evolutionLiveLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'evolutionlive_language', 'en')));
if ($evolutionLiveLanguage === '') { $evolutionLiveLanguage = 'en'; }
$evolutionLiveMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$evolutionLiveMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $evolutionLiveMemberAccount);
if ($evolutionLiveMemberAccount === '') { $evolutionLiveMemberAccount = (string)$u_data['id']; }
$evolutionLiveSupportedCurrencies = array('BDT','INR','BRL','USDT','RUB','USD','NGN','PEN','BWP','GHS','KES','MXN','ZAR','CLP','ARS','KWD','SAR','XOF','AED');
$evolutionLiveCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'evolutionlive_currency', '')));
$evolutionLiveCurrencyWasAdjusted = false;
if ($isEvolutionLive) {
    // IMPORTANT: the live wrapper response is returning currency=BDT for this merchant.
    // Do not force INR for production launches; keep IP/currency consistent with site/admin config.
    if ($evolutionLiveCurrencyOverride !== '') {
        $currency = $evolutionLiveCurrencyOverride;
    } elseif ($siteCurrency !== '') {
        $currency = $siteCurrency;
    } else {
        $currency = 'BDT';
        $evolutionLiveCurrencyWasAdjusted = true;
    }
}


$fachaiGamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'fachaigaming_language', 'en')));
if ($fachaiGamingLanguage === '') { $fachaiGamingLanguage = 'en'; }
$fachaiGamingMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$fachaiGamingMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $fachaiGamingMemberAccount);
if ($fachaiGamingMemberAccount === '') { $fachaiGamingMemberAccount = (string)$u_data['id']; }
$fachaiGamingSupportedCurrencies = array('THB','USD','IDR','VND','INR','MMK','MYR','CAD','SGD','HKD','JPY','KRW','AED','AUD','NZD','TRY','BRL','IRR','EUR','USDT','BDT','PHP','RUB','NPR','LKR','MXN','GHS','NGN','PKR','BND','ZAR','ARS','KHR','AMD','CLP','KES','CHF','GBP','PEN','TND','NOK','SEK','ZMW','UAH','KZT','COP','MNT','TZS','LAK','ETB','EGP','UGX','PYG','BOB','LBP','UZS','MAD','XAF','XOF','AZN','KGS','DOP','SSP','HTG','IQD','PLN','GEL','TMT','BYN','CDF','VES','UYU','CZK','MDL','NAD','CUP','HUF','CRC','BGN','DKK','GTQ','HNL','JOD','NIO','RON','ALL','AOA','BAM','BHD','BZD','ILS','ISK','KWD','MKD','MZN','OMR','QAR','RSD','SAR','SDG','PGK','LYD','MVR','BMD','GNF','MGA','MRU','SOS','BWP','SCR','LSL','ZWG','BIF');
$fachaiGamingCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'fachaigaming_currency', '')));
$fachaiGamingCurrencyWasAdjusted = false;
if ($isFachaiGaming) {
    if ($fachaiGamingCurrencyOverride !== '' && in_array($fachaiGamingCurrencyOverride, $fachaiGamingSupportedCurrencies, true)) {
        $currency = $fachaiGamingCurrencyOverride;
    } elseif (!in_array($currency, $fachaiGamingSupportedCurrencies, true)) {
        // FaChaiGaming supports BDT; if admin site currency is unsupported, use BDT.
        $currency = 'BDT';
        $fachaiGamingCurrencyWasAdjusted = true;
    }
}
$fachaiGamingProviderGameCode = $isFachaiGaming ? game_api_fachaigaming_provider_game_code($game) : '';


$fastSpinLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'fastspin_language', 'en')));
if ($fastSpinLanguage === '') { $fastSpinLanguage = 'en'; }
$fastSpinMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$fastSpinMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $fastSpinMemberAccount);
if ($fastSpinMemberAccount === '') { $fastSpinMemberAccount = (string)$u_data['id']; }
$fastSpinSupportedCurrencies = array('AED','ALL','AMD','ARS','AUD','AZN','BAM','BDT','BIF','BGN','BHD','BND','BRL','BYN','CAD','CDF','CHF','CLP','COP','CRC','CZK','DKK','DZD','EGP','ETB','EUR','GBP','GEL','GHS','GTQ','HNL','HUF','HTG','IDR','ILS','INR','IQD','IRR','JPY','KES','KGS','KHR','KRW','KWD','KZT','LAK','LBP','LKR','MAD','MDL','MMK','MNT','MXN','MYR','MZN','NGN','NOK','NPR','NZD','PEN','PGK','PHP','PKR','PLN','PYG','QAR','RON','RSD','RUB','SAR','SEK','SGD','THB','TJS','TMT','TND','TRY','TZS','UAH','UGX','USD','UYU','UZS','VND','XAF','XOF','ZAR','ZMW');
$fastSpinCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'fastspin_currency', '')));
$fastSpinCurrencyWasAdjusted = false;
if ($isFastSpin) {
    if ($fastSpinCurrencyOverride !== '' && in_array($fastSpinCurrencyOverride, $fastSpinSupportedCurrencies, true)) {
        $currency = $fastSpinCurrencyOverride;
    } elseif (!in_array($currency, $fastSpinSupportedCurrencies, true)) {
        // FastSpin supports BDT; if admin site currency is unsupported, use BDT.
        $currency = 'BDT';
        $fastSpinCurrencyWasAdjusted = true;
    }
}


$gameArtLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'gameart_language', 'en')));
if ($gameArtLanguage === '') { $gameArtLanguage = 'en'; }
$gameArtMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$gameArtMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $gameArtMemberAccount);
if ($gameArtMemberAccount === '') { $gameArtMemberAccount = (string)$u_data['id']; }
$gameArtSupportedCurrencies = array('JPY','NGN','PHP','BRL','USD','USDT','KRW','VND','THB','INR','IDR','MYR','MXN','MMK','EUR','SGD');
$gameArtCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'gameart_currency', '')));
$gameArtCurrencyWasAdjusted = false;
if ($isGameArt) {
    if ($gameArtCurrencyOverride !== '' && in_array($gameArtCurrencyOverride, $gameArtSupportedCurrencies, true)) {
        $currency = $gameArtCurrencyOverride;
    } elseif (!in_array($currency, $gameArtSupportedCurrencies, true)) {
        // GameArt docs do not list BDT; if admin site currency is unsupported, use USD unless overridden.
        $currency = 'USD';
        $gameArtCurrencyWasAdjusted = true;
    }
}


$idealLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'ideal_language', 'en')));
if ($idealLanguage === '') { $idealLanguage = 'en'; }
$idealMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$idealMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $idealMemberAccount);
if ($idealMemberAccount === '') { $idealMemberAccount = (string)$u_data['id']; }
$idealSupportedCurrencies = array('USD','KRW','EUR','AUD','BND','CAD','CHF','GBP','MMK','NOK','NZD','PHP','SGD','SEK','ZAR','BRL','USDT','VND','INR','NGN','COP','EGP','PKR','BDT','JPY');
$idealCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'ideal_currency', '')));
$idealCurrencyWasAdjusted = false;
if ($isIdeal) {
    if ($idealCurrencyOverride !== '' && in_array($idealCurrencyOverride, $idealSupportedCurrencies, true)) {
        $currency = $idealCurrencyOverride;
    } elseif (!in_array($currency, $idealSupportedCurrencies, true)) {
        // IDEAL supports BDT; if admin site currency is unsupported, use BDT.
        $currency = 'BDT';
        $idealCurrencyWasAdjusted = true;
    }
}


$inOutLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'inout_language', 'en')));
if ($inOutLanguage === '') { $inOutLanguage = 'en'; }
$inOutMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$inOutMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $inOutMemberAccount);
if ($inOutMemberAccount === '') { $inOutMemberAccount = (string)$u_data['id']; }
$inOutSupportedCurrencies = array('BDT','AED','AFN','ALL','AMD','ANG','AOA','ARS','AUD','AWG','AZN','BAM','BBD','BGN','BHD','BIF','BMD','BND','BOB','BRL','BSD','BTN','BWP','BYN','BZD','CAD','CDF','CHF','CLF','CLP','COP','CRC','CUP','CVE','CZK','DJF','DKK','DOP','DZD','EGP','ERN','ETB','EUR','FJD','FKP','GBP','GEL','GHS','GIP','GMD','GNF','GTQ','GYD','HNL','HTG','HUF','IDR','ILS','INR','IQD','IRR','ISK','JMD','JOD','JPY','KES','KGS','KHR','KMF','KPW','KRW','KWD','KYD','KZT','LAK','LBP','LKR','LRD','LSL','LYD','MAD','MDL','MGA','MKD','MMK','MNT','MRU','MUR','MVR','MWK','MXN','MYR','MZN','NAD','NGN','NIO','NOK','NPR','NZD','OMR','PAB','PEN','PGK','PHP','PKR','PLN','PYG','QAR','RON','RSD','RUB','RWF','SAR','SBD','SCR','SDG','SEK','SGD','SHP','SOS','SRD','SSP','SYP','SZL','THB','TRY','TZS','UAH','UGX','USD','USDT','UYU','UZS','VES','VND','XOF','ZAR','ZMW');
$inOutCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'inout_currency', '')));
$inOutCurrencyWasAdjusted = false;
if ($isInOut) {
    if ($inOutCurrencyOverride !== '' && in_array($inOutCurrencyOverride, $inOutSupportedCurrencies, true)) {
        $currency = $inOutCurrencyOverride;
    } elseif (!in_array($currency, $inOutSupportedCurrencies, true)) {
        // INOUT supports BDT; if admin site currency is unsupported, use BDT.
        $currency = 'BDT';
        $inOutCurrencyWasAdjusted = true;
    }
}


$luckSportGamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'lucksportgaming_language', 'en')));
if ($luckSportGamingLanguage === '') { $luckSportGamingLanguage = 'en'; }
$luckSportGamingMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$luckSportGamingMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $luckSportGamingMemberAccount);
if ($luckSportGamingMemberAccount === '') { $luckSportGamingMemberAccount = (string)$u_data['id']; }
$luckSportGamingSupportedCurrencies = array('BDT','BRL','COP','EGP','EUR','IDR','INR','MYR','PEN','PHP','PKR','THB','TZS','USD','USDT','VND','XAF','ZAR');
$luckSportGamingCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'lucksportgaming_currency', '')));
$luckSportGamingCurrencyWasAdjusted = false;
if ($isLuckSportGaming) {
    if ($luckSportGamingCurrencyOverride !== '' && in_array($luckSportGamingCurrencyOverride, $luckSportGamingSupportedCurrencies, true)) {
        $currency = $luckSportGamingCurrencyOverride;
    } elseif (!in_array($currency, $luckSportGamingSupportedCurrencies, true)) {
        // LuckSportGaming supports BDT; if admin site currency is unsupported, use BDT.
        $currency = 'BDT';
        $luckSportGamingCurrencyWasAdjusted = true;
    }
}
$luckSportGamingAllowedSports = array('soccer','basketball','tennis','volleyball','baseball','cricket','handball','badminton','table tennis','darts','squash');
$luckSportGamingSport = strtolower(trim((string)game_api_get_setting($conn, 'lucksportgaming_sport', 'soccer')));
$luckSportGamingSport = preg_replace('/[^a-z ]+/', '', $luckSportGamingSport);
if ($luckSportGamingSport === '' || !in_array($luckSportGamingSport, $luckSportGamingAllowedSports, true)) { $luckSportGamingSport = 'soccer'; }
$luckSportGamingExtras = 'sport=' . rawurlencode($luckSportGamingSport);


$microgamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'microgaming_language', 'en')));
$microgamingAllowedLanguages = array('en','id','ja','ko','th','vi','hi','te','ru','tr','pt');
if ($microgamingLanguage === '' || !in_array($microgamingLanguage, $microgamingAllowedLanguages, true)) { $microgamingLanguage = 'en'; }
$microgamingMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$microgamingMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $microgamingMemberAccount);
if ($microgamingMemberAccount === '') { $microgamingMemberAccount = (string)$u_data['id']; }
$microgamingSupportedCurrencies = array('BDT','BND','BRL','BYN','CLP','COP','EGP','EUR','IDR','INR','JPY','KES','KGS','KRW','KZT','LAK','MMK','MXN','MYR','NGN','PEN','PGK','PHP','PKR','RUB','THB','TZS','UAH','USD','USDT','UZS','VND','XAF','ZAR');
$microgamingCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'microgaming_currency', '')));
$microgamingCurrencyWasAdjusted = false;
if ($isMicrogaming) {
    if ($microgamingCurrencyOverride !== '' && in_array($microgamingCurrencyOverride, $microgamingSupportedCurrencies, true)) {
        $currency = $microgamingCurrencyOverride;
    } elseif (!in_array($currency, $microgamingSupportedCurrencies, true)) {
        // Microgaming docs include BDT; if admin site currency is unsupported, use BDT unless overridden.
        $currency = 'BDT';
        $microgamingCurrencyWasAdjusted = true;
    }
}


$ongamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'ongaming_language', 'en')));
$ongamingAllowedLanguages = array('en','vi','ko','th','id','ja','ph','hi','pt','zh');
if ($ongamingLanguage === '' || !in_array($ongamingLanguage, $ongamingAllowedLanguages, true)) { $ongamingLanguage = 'en'; }
$ongamingMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$ongamingMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ongamingMemberAccount);
if ($ongamingMemberAccount === '') { $ongamingMemberAccount = (string)$u_data['id']; }
$ongamingSupportedCurrencies = array('MYR','INR','IDR','BDT','SGD','AUD','BRL','RUB','VND','PHP','KRW','MMK','THB','JPY','USD','PKR');
$ongamingCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'ongaming_currency', '')));
$ongamingCurrencyWasAdjusted = false;
if ($isOnGaming) {
    if ($ongamingCurrencyOverride !== '' && in_array($ongamingCurrencyOverride, $ongamingSupportedCurrencies, true)) {
        $currency = $ongamingCurrencyOverride;
    } elseif (!in_array($currency, $ongamingSupportedCurrencies, true)) {
        // OnGaming docs include BDT; if admin site currency is unsupported, use BDT unless overridden.
        $currency = 'BDT';
        $ongamingCurrencyWasAdjusted = true;
    }
}


$netentLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'netent_language', 'en')));
$netentAllowedLanguages = array('en','sq','ar','bn','bg','ca','cs','de','da','el','es','et','fi','fr','he','hi','hr','hu','hy','id','it','ja','ka','ko','lt','lv','mk','mn','ms','nl','no','pl','pt','ro','ru','sk','sl','sr','sv','te','th','tr','uk','vi');
if ($netentLanguage === '' || !in_array($netentLanguage, $netentAllowedLanguages, true)) { $netentLanguage = 'en'; }
$netentMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$netentMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $netentMemberAccount);
if ($netentMemberAccount === '') { $netentMemberAccount = (string)$u_data['id']; }
$netentSupportedCurrencies = array('BRL','USDT','RUB','USD','NGN','PEN','BWP','GHS','KES','MXN','ZAR','CLP','ARS','AED');
$netentCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'netent_currency', '')));
$netentCurrencyWasAdjusted = false;
if ($isNetEnt) {
    if ($netentCurrencyOverride !== '' && in_array($netentCurrencyOverride, $netentSupportedCurrencies, true)) {
        $currency = $netentCurrencyOverride;
    } elseif (!in_array($currency, $netentSupportedCurrencies, true)) {
        // NetEnt docs supplied here do not list BDT; use USD unless admin overrides netent_currency.
        $currency = 'USD';
        $netentCurrencyWasAdjusted = true;
    }
}


$miniLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'mini_language', 'en')));
$miniAllowedLanguages = array('en','es','ja','pt','ru','fr','de','hi','id','ko','pl','tr','vi','fi','ar','it','sw','bn','sr','ms','nl','el','sv','fa','da','my','nb','ro','uk','th','tw');
if ($miniLanguage === '' || !in_array($miniLanguage, $miniAllowedLanguages, true)) { $miniLanguage = 'en'; }
$miniMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$miniMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $miniMemberAccount);
if ($miniMemberAccount === '') { $miniMemberAccount = (string)$u_data['id']; }
$miniSupportedCurrencies = array('BDT','AED','ARS','BRL','CAD','CLP','DKK','EUR','IDR','INR','JPY','KRW','MXN','PEN','PHP','PLN','RUB','TRY','USD','USDT','VND','AUD','AZN','ANG','BGN','BHD','BOB','BWP','ERN','COP','PKR','CRC','CZK','DOP','EGP','GBP','GTQ','HNL','JOD','LBP','MDL','MMK','MNT','MYR','NOK','NIO','NGN','PYG','RON','SEK','SGD','THB','UAH','UYU','VES','NPR','BND','NZD','ZAR','LAK','ZMW','KHR','AOA','CDF','DZD','ETB','GHS','KES','MAD','TND','TZS','UGX','UZS','LKR','AMD','PGK','KZT');
$miniCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'mini_currency', '')));
$miniCurrencyWasAdjusted = false;
if ($isMini) {
    if ($miniCurrencyOverride !== '' && in_array($miniCurrencyOverride, $miniSupportedCurrencies, true)) {
        $currency = $miniCurrencyOverride;
    } elseif (!in_array($currency, $miniSupportedCurrencies, true)) {
        // Mini docs include BDT; if admin site currency is unsupported, use BDT unless overridden.
        $currency = 'BDT';
        $miniCurrencyWasAdjusted = true;
    }
}




$sexyLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'sexy_language', 'en')));
$sexyAllowedLanguages = array('en','ja','th','vi','ko');
if ($sexyLanguage === '' || !in_array($sexyLanguage, $sexyAllowedLanguages, true)) { $sexyLanguage = 'en'; }
// Sexy docs require member_account to use digits and letters only.
$sexyMemberAccount = (($agentCode !== '') ? $agentCode : '') . (string)$u_data['id'];
$sexyMemberAccount = preg_replace('/[^A-Za-z0-9]/', '', $sexyMemberAccount);
if ($sexyMemberAccount === '') { $sexyMemberAccount = (string)$u_data['id']; }
$sexySupportedCurrencies = array('AED','ARS','AUD','BDT','BND','BRL','CAD','CHF','COP','EGP','ETB','EUR','GBP','GHS','IDR','INR','JPY','KES','KHR','KRW','KZT','LAK','LKR','MMK','MNT','MYR','NGN','NOK','NPR','NZD','PEN','PHP','PKR','RUB','SEK','SGD','THB','TND','TRY','TZS','UAH','UGX','USD','VND','ZAR','ZMW');
$sexyCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'sexy_currency', '')));
$sexyCurrencyWasAdjusted = false;
if ($isSexy) {
    if ($sexyCurrencyOverride !== '' && in_array($sexyCurrencyOverride, $sexySupportedCurrencies, true)) {
        $currency = $sexyCurrencyOverride;
    } elseif (!in_array($currency, $sexySupportedCurrencies, true)) {
        $currency = 'BDT';
        $sexyCurrencyWasAdjusted = true;
    }
}

$sboLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'sbo_language', 'en')));
$sboAllowedLanguages = array('en','th','id','ja','ko','vi','de','es','fr','ru','pt','my','km');
if ($sboLanguage === '' || !in_array($sboLanguage, $sboAllowedLanguages, true)) { $sboLanguage = 'en'; }
$sboMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$sboMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $sboMemberAccount);
if ($sboMemberAccount === '') { $sboMemberAccount = (string)$u_data['id']; }
$sboSupportedCurrencies = array('BDT','USDT','AED','AFN','ALL','AMD','ANG','AOA','ARS','AUD','AWG','AZN','BAM','BBD','BGN','BHD','BIF','BMD','BND','BOB','BRL','BSD','BTN','BWP','BYN','BZD','CAD','CDF','CHF','CLP','COP','CRC','CUP','CVE','CZK','DJF','DKK','DOP','DZD','EGP','ERN','ETB','EUR','FJD','FKP','GBP','GEL','GHS','GIP','GMD','GNF','GTQ','GYD','HNL','HTG','HUF','IDR','ILS','INR','IQD','IRR','ISK','JMD','JOD','JPY','KES','KGS','KHR','KMF','KPW','KRW','KWD','KYD','KZT','LAK','LBP','LKR','LRD','LSL','LYD','MAD','MDL','MGA','MKD','MMK','MNT','MRU','MUR','MVR','MWK','MXN','MYR','MZN','NAD','NGN','NIO','NOK','NPR','NZD','OMR','PAB','PEN','PGK','PHP','PKR','PLN','PYG','QAR','RON','RSD','RUB','RWF','SAR','SBD','SCR','SDG','SEK','SLL','SOS','SRD','SSP','STN','SYP','SZL','THB','TJS','TMT','TND','TOP','TRY','TTD','TZS','UAH','UGX','USD','UYU','UZS','VES','VND','VUV','WST','XAF','XCD','XOF','XPF','YER','ZAR','ZMW');
$sboCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'sbo_currency', '')));
$sbo568Currency = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'sbo_568win_currency', 'PHP')));
$sboCurrencyWasAdjusted = false;
if ($isSbo) {
    $sboGameNameNorm = preg_replace('/[^a-z0-9]+/', '', strtolower(isset($game['name']) ? (string)$game['name'] : ''));
    if ($sboCurrencyOverride !== '' && in_array($sboCurrencyOverride, $sboSupportedCurrencies, true)) {
        $currency = $sboCurrencyOverride;
    } elseif (strpos($sboGameNameNorm, '568win') !== false) {
        // Documentation says 568win Sportsbook only supports PHP.
        $currency = ($sbo568Currency !== '' ? $sbo568Currency : 'PHP');
        $sboCurrencyWasAdjusted = true;
    } elseif (!in_array($currency, $sboSupportedCurrencies, true)) {
        $currency = 'BDT';
        $sboCurrencyWasAdjusted = true;
    }
}


$dpsportsLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'dpsports_language', 'en')));
$dpsportsAllowedLanguages = array('en','th','id','ja','ko','vi','de','es','fr','ru','pt','my','km','zh');
if ($dpsportsLanguage === '' || !in_array($dpsportsLanguage, $dpsportsAllowedLanguages, true)) { $dpsportsLanguage = 'en'; }
$dpsportsMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$dpsportsMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $dpsportsMemberAccount);
if ($dpsportsMemberAccount === '') { $dpsportsMemberAccount = (string)$u_data['id']; }
$dpsportsSupportedCurrencies = $sboSupportedCurrencies;
$dpsportsCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'dpsports_currency', '')));
$dpsportsCurrencyWasAdjusted = false;
if ($isDpSports) {
    if ($dpsportsCurrencyOverride !== '' && in_array($dpsportsCurrencyOverride, $dpsportsSupportedCurrencies, true)) {
        $currency = $dpsportsCurrencyOverride;
    } elseif (!in_array($currency, $dpsportsSupportedCurrencies, true)) {
        $currency = 'BDT';
        $dpsportsCurrencyWasAdjusted = true;
    }
}


$sagamingLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'sagaming_language', 'en')));
$sagamingAllowedLanguages = array('ar','bn','en','es','fa','hi','id','ja','ko','ms','my','pt','te','th','vi');
if ($sagamingLanguage === '' || !in_array($sagamingLanguage, $sagamingAllowedLanguages, true)) { $sagamingLanguage = 'en'; }
$sagamingMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$sagamingMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $sagamingMemberAccount);
if ($sagamingMemberAccount === '') { $sagamingMemberAccount = (string)$u_data['id']; }
$sagamingSupportedCurrencies = array('AED','AMD','ARS','AUD','AZN','BDT','BND','BRL','BYN','CAD','CHF','CLP','CZK','DKK','EGP','EUR','GBP','GEL','GHS','HTG','HUF','ILS','INR','IQD','JPY','KES','KGS','KRW','KZT','LKR','MDL','MXN','MYR','NAD','NGN','NOK','NPR','NZD','PEN','PHP','PKR','PLN','RUB','SEK','SGD','SZL','THB','TMT','TND','TRY','UAH','USD','USDT','VES','XAF','XOF','ZAR','ZMW','CDF','COP','IDR','IRR','KHR','LAK','MMK','MNT','PYG','TZS','UGX','UZS','VND');
$sagamingCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'sagaming_currency', '')));
$sagamingCurrencyWasAdjusted = false;
if ($isSaGaming) {
    if ($sagamingCurrencyOverride !== '' && in_array($sagamingCurrencyOverride, $sagamingSupportedCurrencies, true)) {
        $currency = $sagamingCurrencyOverride;
    } elseif (!in_array($currency, $sagamingSupportedCurrencies, true)) {
        // SaGaming docs include BDT and USDT; if admin site currency is unsupported, use BDT unless overridden.
        $currency = 'BDT';
        $sagamingCurrencyWasAdjusted = true;
    }
}

$pragmaticLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'pragmatic_language', 'en')));
$pragmaticAllowedLanguages = array('en','ar','bg','ca','cs','da','de','el','es','et','fa','fi','fr','hi','hr','hu','hy','id','it','ja','ka','ko','lt','lv','ms','nl','no','pl','pt','ro','ru','sk','sr','sv','th','tr','uk','vi');
if ($pragmaticLanguage === '' || !in_array($pragmaticLanguage, $pragmaticAllowedLanguages, true)) { $pragmaticLanguage = 'en'; }
$pragmaticMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$pragmaticMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $pragmaticMemberAccount);
if ($pragmaticMemberAccount === '') { $pragmaticMemberAccount = (string)$u_data['id']; }
$pragmaticSupportedCurrencies = array('BDT','AFN','ALL','AMD','ANG','AOA','ARS','AWG','AZN','BAM','BBD','BGN','BHD','BIF','BMD','BND','BOB','BRL','BSD','BTN','BWP','BYN','BZD','CAD','CDF','CHF','CLP','COP','CRC','CUP','CVE','CZK','DJF','DKK','DOP','DZD','EGP','ERN','ETB','EUR','FJD','FKP','GBP','GEL','GHS','GIP','GMD','GNF','GTQ','GYD','HNL','HTG','HUF','IDR','ILS','INR','IQD','IRR','ISK','JMD','JOD','JPY','KES','KGS','KHR','KMF','KPW','KRW','KWD','KYD','KZT','LAK','LBP','LKR','LRD','LSL','LYD','MAD','MDL','MGA','MKD','MMK','MNT','MUR','MVR','MWK','MXN','MZN','NAD','NGN','NIO','NOK','NPR','NZD','OMR','PAB','PEN','PGK','PHP','PKR','PLN','PYG','QAR','RON','RSD','RUB','RWF','SAR','SBD','SCR','SDG','SEK','SHP','SOS','SRD','SYP','SZL','TJS','TMT','TND','TRY','TZS','UAH','UGX','USD','USDT','UYU','UZS','VND','XAF','XOF','ZAR','ZMW');
$pragmaticCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'pragmatic_currency', '')));
$pragmaticCurrencyWasAdjusted = false;
if ($isPragmatic) {
    if ($pragmaticCurrencyOverride !== '' && in_array($pragmaticCurrencyOverride, $pragmaticSupportedCurrencies, true)) {
        $currency = $pragmaticCurrencyOverride;
    } elseif (!in_array($currency, $pragmaticSupportedCurrencies, true)) {
        // Pragmatic docs include BDT; if admin site currency is unsupported, use BDT unless overridden.
        $currency = 'BDT';
        $pragmaticCurrencyWasAdjusted = true;
    }
}



$t1Language = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 't1_language', 'en')));
if ($t1Language === '') { $t1Language = 'en'; }
$t1MemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$t1MemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $t1MemberAccount);
if ($t1MemberAccount === '') { $t1MemberAccount = (string)$u_data['id']; }
$t1SupportedCurrencies = array('USD','EGP','IDR','INR','PHP','THB','VND','NGN','PKR');
$t1CurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 't1_currency', '')));
$t1CurrencyWasAdjusted = false;
if ($isT1) {
    if ($t1CurrencyOverride !== '' && in_array($t1CurrencyOverride, $t1SupportedCurrencies, true)) {
        $currency = $t1CurrencyOverride;
    } elseif (!in_array($currency, $t1SupportedCurrencies, true)) {
        $currency = 'USD';
        $t1CurrencyWasAdjusted = true;
    }
}

$spribeLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'spribe_language', 'en')));
if ($spribeLanguage === '') { $spribeLanguage = 'en'; }
$spribeMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$spribeMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $spribeMemberAccount);
if ($spribeMemberAccount === '') { $spribeMemberAccount = (string)$u_data['id']; }
$spribeSupportedCurrencies = array('BDT','USD','USDT','EUR','INR','PHP','PKR','IDR','VND','THB','MYR','BRL','NGN','JPY','KRW','AUD','CAD','MXN','ZAR','TRY');
$spribeCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'spribe_currency', '')));
$spribeCurrencyWasAdjusted = false;
if ($isSpribe) {
    if ($spribeCurrencyOverride !== '' && in_array($spribeCurrencyOverride, $spribeSupportedCurrencies, true)) { $currency = $spribeCurrencyOverride; }
    elseif (!in_array($currency, $spribeSupportedCurrencies, true)) { $currency = 'BDT'; $spribeCurrencyWasAdjusted = true; }
}

$tfLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'tf_language', 'en')));
if ($tfLanguage === '') { $tfLanguage = 'en'; }
$tfMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$tfMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $tfMemberAccount);
if ($tfMemberAccount === '') { $tfMemberAccount = (string)$u_data['id']; }
$tfSupportedCurrencies = array('BDT','USD','USDT','EUR','INR','PHP','PKR','IDR','VND','THB','MYR','BRL','NGN','JPY','KRW','AUD','CAD','ARS','BND','COP','CLP','DKK','DZD','EGP','GBP','KES','KHR','LAK','MAD','MMK','MNT','MXN','NOK','NPR','PYG','RUB','SGD','TND','TRY','ZAR','ZMW','XAF');
$tfCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'tf_currency', '')));
$tfCurrencyWasAdjusted = false;
if ($isTf) {
    if ($tfCurrencyOverride !== '' && in_array($tfCurrencyOverride, $tfSupportedCurrencies, true)) { $currency = $tfCurrencyOverride; }
    elseif (!in_array($currency, $tfSupportedCurrencies, true)) { $currency = 'BDT'; $tfCurrencyWasAdjusted = true; }
}

$tfgLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'tfg_language', 'en')));
if ($tfgLanguage === '') { $tfgLanguage = 'en'; }
$tfgMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$tfgMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $tfgMemberAccount);
if ($tfgMemberAccount === '') { $tfgMemberAccount = (string)$u_data['id']; }
$tfgSupportedCurrencies = $tfSupportedCurrencies;
$tfgCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'tfg_currency', '')));
$tfgCurrencyWasAdjusted = false;
if ($isTfg) {
    if ($tfgCurrencyOverride !== '' && in_array($tfgCurrencyOverride, $tfgSupportedCurrencies, true)) { $currency = $tfgCurrencyOverride; }
    elseif (!in_array($currency, $tfgSupportedCurrencies, true)) { $currency = 'BDT'; $tfgCurrencyWasAdjusted = true; }
}

$yeebetLanguage = strtolower(game_api_clean_no_space(game_api_get_setting($conn, 'yeebet_language', 'en')));
if ($yeebetLanguage === '') { $yeebetLanguage = 'en'; }
$yeebetMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
$yeebetMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $yeebetMemberAccount);
if ($yeebetMemberAccount === '') { $yeebetMemberAccount = (string)$u_data['id']; }
$yeebetSupportedCurrencies = array('BDT','USD','EUR','GBP','INR','IDR','MYR','THB','VND','PHP','PKR','BRL','JPY','KRW','AUD','CAD','SGD','USDT');
$yeebetCurrencyOverride = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'yeebet_currency', '')));
$yeebetCurrencyWasAdjusted = false;
if ($isYeebet) {
    if ($yeebetCurrencyOverride !== '' && in_array($yeebetCurrencyOverride, $yeebetSupportedCurrencies, true)) { $currency = $yeebetCurrencyOverride; }
    elseif (!in_array($currency, $yeebetSupportedCurrencies, true)) { $currency = 'BDT'; $yeebetCurrencyWasAdjusted = true; }
}

// OXEN working flow sends vendorCode, gameCode, userId and UserBalance together.
// Keep legacy aliases too, because the provider documentation/sample differs from the live endpoint.
$payload = array(
    'ValidationToken' => $apiToken,
    'GameId' => $apiGameCode,
    'PlayerId' => (string)$u_data['id'],
    'PlayerName' => $playerName,
    'Currency' => $currency,
    'ReturnUrl' => $returnUrl,
    'UserBalance' => $balance,

    'userId' => (string)$u_data['id'],
    'userName' => $playerName,
    'userBalance' => $balance,
    'balance' => $balance,
    'creditAmount' => $balance,
    'gameCode' => $apiGameCode,
    'vendorCode' => $vendorCode,
    'playerId' => (string)$u_data['id'],
    'playerName' => $playerName,
    'currency' => $currency,
    'returnUrl' => $returnUrl,
    'home_url' => rtrim($homeUrl, '/'),
    'callback_url' => $callbackUrl,
    'callbackUrl' => $callbackUrl,
    'CallbackUrl' => $callbackUrl,

    // Preview hints are ignored by providers that do not support them. The local
    // callback remains authoritative and prevents betting without real balance.
    'previewMode' => $isPreviewLaunch ? 1 : 0,
    'isDemo' => $isPreviewLaunch ? 1 : 0,
    'demo' => $isPreviewLaunch ? 1 : 0,
    'playMode' => $isPreviewLaunch ? 'demo' : 'real',
    'bettingEnabled' => $isPreviewLaunch ? false : true,

    // Optional metadata for debugging/mapping. Provider can ignore unknown fields.
    'gameId' => $apiGameId !== '' ? $apiGameId : $apiGameCode,
    'gameUID' => $apiGameId,
    'localGameUid' => $local_game_uid,
    'agentCode' => $agentCode,
    'merchantCode' => $agentCode
);

if ($isJili) {
    // JILI documentation uses the public numeric Code for launch, while some
    // reseller wrappers still need the 32-character Game UID. Send Code first
    // and keep UID aliases so the existing fallback flow remains safe.
    $jiliGameCode = $apiGameCode;
    $jiliGameUid = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $jiliMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
    $jiliMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $jiliMemberAccount);
    if ($jiliMemberAccount === '') { $jiliMemberAccount = (string)$u_data['id']; }

    $payload['GameId'] = $jiliGameCode;
    $payload['gameCode'] = $jiliGameCode;
    $payload['game_code'] = $jiliGameCode;
    $payload['game_id'] = $jiliGameCode;
    $payload['gameId'] = $jiliGameCode;
    $payload['gameUID'] = $jiliGameUid;
    $payload['jiliGameUID'] = $jiliGameUid;
    $payload['providerGameCode'] = $jiliGameCode;
    $payload['originalGameCode'] = $jiliGameCode;
    $payload['sourceGameCode'] = $jiliGameCode;
    $payload['jiliCode'] = $jiliGameCode;
    $payload['uidGameCode'] = $jiliGameUid;
    $payload['vendorCode'] = 'JILI';
    $payload['VendorCode'] = 'JILI';
    $payload['vendor_code'] = 'JILI';
    $payload['apiVendorCode'] = 'JILI';
    $payload['providerCode'] = 'JILI';
    $payload['ProviderCode'] = 'JILI';
    $payload['provider_code'] = 'JILI';
    $payload['providerName'] = 'JILI';
    $payload['member_account'] = $jiliMemberAccount;
    $payload['memberAccount'] = $jiliMemberAccount;
    $payload['member'] = $jiliMemberAccount;
    $payload['account'] = $jiliMemberAccount;
    $payload['login'] = $jiliMemberAccount;
    $payload['full_user_id'] = $jiliMemberAccount;
    $payload['language'] = 'en';
    $payload['lang'] = 'en';
    $payload['Language'] = 'en';
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
}

if ($isCq9) {
    // CQ9 launch uses vendorCode=cq9 and the 32-character Game UID as gameCode/GameId.
    // Keep PlayerId numeric for local wallet callbacks, and send member_account/account aliases for CQ9 compatibility.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $cq9MemberAccount;
    $payload['memberAccount'] = $cq9MemberAccount;
    $payload['account'] = $cq9MemberAccount;
    $payload['full_user_id'] = $cq9MemberAccount;
    $payload['language'] = $cq9Language;
    $payload['lang'] = $cq9Language;
    $payload['Language'] = $cq9Language;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : 'Slot';
}

if ($is100hp) {
    // 100HP launch uses vendorCode=100hp and the 32-character Game UID as gameCode/GameId.
    // Keep PlayerId numeric for local wallet callbacks, and send member/account aliases for wrapper compatibility.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $hp100MemberAccount;
    $payload['memberAccount'] = $hp100MemberAccount;
    $payload['account'] = $hp100MemberAccount;
    $payload['full_user_id'] = $hp100MemberAccount;
    $payload['language'] = $hp100Language;
    $payload['lang'] = $hp100Language;
    $payload['Language'] = $hp100Language;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : 'Instant';
}

if ($is2j) {
    // 2J launch uses vendorCode=2j and the 32-character Game UID as gameCode/GameId.
    // Keep PlayerId numeric for local wallet callbacks, and send member/account aliases for wrapper compatibility.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $twoJMemberAccount;
    $payload['memberAccount'] = $twoJMemberAccount;
    $payload['member'] = $twoJMemberAccount;
    $payload['account'] = $twoJMemberAccount;
    $payload['full_user_id'] = $twoJMemberAccount;
    $payload['language'] = $twoJLanguage;
    $payload['lang'] = $twoJLanguage;
    $payload['Language'] = $twoJLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
}


if ($isBng3oksRow) {
    // BNG3Oaks ROW launch uses vendorCode=bng3oks-row and the 32-character Game UID as gameCode/GameId.
    // Keep PlayerId numeric for local wallet callbacks, and send member/account aliases for wrapper compatibility.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $bng3oksRowMemberAccount;
    $payload['memberAccount'] = $bng3oksRowMemberAccount;
    $payload['member'] = $bng3oksRowMemberAccount;
    $payload['account'] = $bng3oksRowMemberAccount;
    $payload['login'] = $bng3oksRowMemberAccount;
    $payload['full_user_id'] = $bng3oksRowMemberAccount;
    $payload['language'] = $bng3oksRowLanguage;
    $payload['lang'] = $bng3oksRowLanguage;
    $payload['Language'] = $bng3oksRowLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
    $payload['providerName'] = 'BNG3Oaks ROW';
}


if ($is5gGaming) {
    // 5G Gaming list has both provider Code (KYS-H5/S5G-H5) and 32-character Game UID.
    // Primary attempt uses Code; existing launch retry will automatically fallback to UID if provider rejects Code.
    $payload['GameId'] = $apiGameCode;
    $payload['gameCode'] = $apiGameCode;
    $payload['game_code'] = $apiGameCode;
    $payload['game_id'] = $apiGameCode;
    $payload['gameId'] = $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['providerGameCode'] = $apiGameCode;
    $payload['originalGameCode'] = $apiGameCode;
    $payload['sourceGameCode'] = $apiGameCode;
    $payload['uidGameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $fiveGGamingMemberAccount;
    $payload['memberAccount'] = $fiveGGamingMemberAccount;
    $payload['member'] = $fiveGGamingMemberAccount;
    $payload['account'] = $fiveGGamingMemberAccount;
    $payload['login'] = $fiveGGamingMemberAccount;
    $payload['full_user_id'] = $fiveGGamingMemberAccount;
    $payload['language'] = $fiveGGamingLanguage;
    $payload['lang'] = $fiveGGamingLanguage;
    $payload['Language'] = $fiveGGamingLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
    $payload['providerName'] = '5G Gaming';
}


if ($is9Wicket) {
    // 9Wicket supports transfer wallet through /game/v2 and does not support iframe rendering.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $nineWicketMemberAccount;
    $payload['memberAccount'] = $nineWicketMemberAccount;
    $payload['member'] = $nineWicketMemberAccount;
    $payload['account'] = $nineWicketMemberAccount;
    $payload['login'] = $nineWicketMemberAccount;
    $payload['full_user_id'] = $nineWicketMemberAccount;
    $payload['language'] = $nineWicketLanguage;
    $payload['lang'] = $nineWicketLanguage;
    $payload['Language'] = $nineWicketLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : 'Sports';
    $payload['providerName'] = '9Wicket';
    $payload['apiVendorCode'] = '9wicket';
    $payload['providerCode'] = '9wicket';
    $payload['vendor'] = '9wicket';
    $payload['vendor_code'] = '9wicket';
    $payload['api_vendor_code'] = '9wicket';
    $payload['provider_code'] = '9wicket';
    $payload['game_uid'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_name'] = '9Wicket';
    $payload['walletMode'] = 'transfer';
    $payload['transferWallet'] = true;
    $payload['transfer_id'] = $nineWicketTransferId;
    $payload['transferId'] = $nineWicketTransferId;
    $payload['TransferId'] = $nineWicketTransferId;
    $payload['TransferID'] = $nineWicketTransferId;
    $payload['transaction_id'] = $nineWicketTransferId;
    $payload['transactionId'] = $nineWicketTransferId;
    $payload['order_id'] = $nineWicketTransferId;
    $payload['orderId'] = $nineWicketTransferId;
    $payload['ref_no'] = $nineWicketTransferId;
    $payload['transfer_no'] = $nineWicketTransferId;
    $payload['transferNo'] = $nineWicketTransferId;
    $payload['serial_no'] = $nineWicketTransferId;
    $payload['serialNo'] = $nineWicketTransferId;
    $payload['bill_no'] = $nineWicketTransferId;
    $payload['billNo'] = $nineWicketTransferId;
    $payload['txn_id'] = $nineWicketTransferId;
    $payload['txnId'] = $nineWicketTransferId;
    $payload['reference_id'] = $nineWicketTransferId;
    $payload['merchant_order_id'] = $nineWicketTransferId;
    $payload['merchantOrderId'] = $nineWicketTransferId;
    // Transfer-wallet amount must always be the user's real balance.
    $payload['amount'] = $walletBalance;
    $payload['Amount'] = $walletBalance;
    $payload['transferAmount'] = $walletBalance;
    $payload['transfer_amount'] = $walletBalance;
    $nineWicketExtras = array(
        'transfer_id' => $nineWicketTransferId,
        'transferId' => $nineWicketTransferId,
        'TransferId' => $nineWicketTransferId,
        'TransferID' => $nineWicketTransferId,
        'transaction_id' => $nineWicketTransferId,
        'order_id' => $nineWicketTransferId,
        'amount' => $walletBalance,
        'transfer_amount' => $walletBalance,
        'currency' => $currency,
        'member_account' => $nineWicketMemberAccount,
        'memberAccount' => $nineWicketMemberAccount,
        'vendor_code' => '9wicket',
        'vendorCode' => '9wicket',
        'game_code' => $apiGameId !== '' ? $apiGameId : $apiGameCode,
        'gameCode' => $apiGameId !== '' ? $apiGameId : $apiGameCode,
        'game_id' => $apiGameId !== '' ? $apiGameId : $apiGameCode,
        'gameId' => $apiGameId !== '' ? $apiGameId : $apiGameCode
    );
    // OxenTech devtools wrapper reads extras as a string parameter for provider-specific launch data.
    // Keeping top-level extras as an array makes the wrapper ignore transfer_id and 9Wicket returns 10022.
    $payload['extras'] = json_encode($nineWicketExtras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $payload['extra'] = $payload['extras'];
    $payload['Extra'] = $payload['extras'];
    $payload['apiPath'] = '/game/v2';
    $payload['gameVersion'] = 'v2';
    $payload['iframeSupported'] = false;
    $payload['supportedCurrencies'] = $nineWicketSupportedCurrencies;
    if ($nineWicketCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = strtoupper(trim(isset($settings['currency_code']) ? $settings['currency_code'] : 'BDT'));
    }
}


if ($isAmigo) {
    // Amigo launch uses vendorCode=amigo and the 32-character Game UID as gameCode/GameId.
    // Keep PlayerId numeric for local wallet callbacks, and send member/account aliases for wrapper compatibility.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $amigoMemberAccount;
    $payload['memberAccount'] = $amigoMemberAccount;
    $payload['member'] = $amigoMemberAccount;
    $payload['account'] = $amigoMemberAccount;
    $payload['login'] = $amigoMemberAccount;
    $payload['full_user_id'] = $amigoMemberAccount;
    $payload['language'] = $amigoLanguage;
    $payload['lang'] = $amigoLanguage;
    $payload['Language'] = $amigoLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
    $payload['providerName'] = 'Amigo';
    $payload['supportedCurrencies'] = $amigoSupportedCurrencies;
}


if ($isAog) {
    // AOG launch uses vendorCode=aog and the 32-character Game UID as gameCode/GameId.
    // Keep PlayerId numeric for local wallet callbacks, and send member/account aliases for wrapper compatibility.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $aogMemberAccount;
    $payload['memberAccount'] = $aogMemberAccount;
    $payload['member'] = $aogMemberAccount;
    $payload['account'] = $aogMemberAccount;
    $payload['login'] = $aogMemberAccount;
    $payload['full_user_id'] = $aogMemberAccount;
    $payload['language'] = $aogLanguage;
    $payload['lang'] = $aogLanguage;
    $payload['Language'] = $aogLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : 'Cockfight';
    $payload['providerName'] = 'AOG';
    $payload['decimalPlaces'] = 2;
    $payload['supportedCurrencies'] = $aogSupportedCurrencies;
    $payload['restrictedTerritories'] = array('TW');
    if ($aogCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = strtoupper(trim(isset($settings['currency_code']) ? $settings['currency_code'] : 'BDT'));
    }
}


if ($isAstar) {
    // Astar launch uses vendorCode=astar and the 32-character Game UID as gameCode/GameId.
    // BDT is supported by the provider list, so do not force currency conversion.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $astarMemberAccount;
    $payload['memberAccount'] = $astarMemberAccount;
    $payload['member'] = $astarMemberAccount;
    $payload['account'] = $astarMemberAccount;
    $payload['login'] = $astarMemberAccount;
    $payload['full_user_id'] = $astarMemberAccount;
    $payload['language'] = $astarLanguage;
    $payload['lang'] = $astarLanguage;
    $payload['Language'] = $astarLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : 'CasinoLive';
    $payload['providerName'] = 'Astar';
    $payload['supportedCurrencies'] = $astarSupportedCurrencies;
    $payload['restrictedTerritories'] = array();
}


if ($isBgaming) {
    // BGaming launch uses vendorCode=bgaming and the 32-character Game UID as gameCode/GameId.
    // BDT is supported by the provided list, so do not force currency conversion.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $bgamingMemberAccount;
    $payload['memberAccount'] = $bgamingMemberAccount;
    $payload['member'] = $bgamingMemberAccount;
    $payload['account'] = $bgamingMemberAccount;
    $payload['login'] = $bgamingMemberAccount;
    $payload['full_user_id'] = $bgamingMemberAccount;
    $payload['language'] = $bgamingLanguage;
    $payload['lang'] = $bgamingLanguage;
    $payload['Language'] = $bgamingLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Video Slot');
    $payload['providerName'] = 'BGaming';
    $payload['supportedCurrencies'] = $bgamingSupportedCurrencies;
}


if ($isBigTimeGaming) {
    // BigTimeGaming launch uses vendorCode=bigtimegaming and the 32-character Game UID as gameCode/GameId.
    // BDT is not listed in the provided currency list; default fallback is USD unless admin overrides bigtimegaming_currency.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $bigtimegamingMemberAccount;
    $payload['memberAccount'] = $bigtimegamingMemberAccount;
    $payload['member'] = $bigtimegamingMemberAccount;
    $payload['account'] = $bigtimegamingMemberAccount;
    $payload['login'] = $bigtimegamingMemberAccount;
    $payload['full_user_id'] = $bigtimegamingMemberAccount;
    $payload['language'] = $bigtimegamingLanguage;
    $payload['lang'] = $bigtimegamingLanguage;
    $payload['Language'] = $bigtimegamingLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
    $payload['providerName'] = 'BigTimeGaming';
    $payload['supportedCurrencies'] = $bigtimegamingSupportedCurrencies;
    $payload['blockedTerritories'] = array('Australia','Cuba','Iran','North Korea','South Sudan','Sudan','Syria','Taiwan','Crimea','Myanmar');
    $payload['restrictedTerritoriesNeedLicense'] = array('Algeria','Angola','Bolivia','Bulgaria','Burkina Faso','Cameroon','Cote D’Ivoire','Democratic Republic Congo','Haiti','Kenya','Laos','Lebanon','Monaco','Mozambique','Namibia','Nepal','Nigeria','South Africa','Venezuela','Vietnam','UK','Yemen','US');
    if ($bigtimegamingCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isBtGaming) {
    // BTGaming launch uses vendorCode=btgaming and the 32-character Game UID as gameCode/GameId.
    // The docs also include a short provider Code (AB/VG/VGF/etc.); keep it as metadata and one fallback attempt.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    if ($btgamingProviderGameCode !== '') {
        $payload['providerGameCode'] = $btgamingProviderGameCode;
        $payload['originalGameCode'] = $btgamingProviderGameCode;
        $payload['code'] = $btgamingProviderGameCode;
        $payload['Code'] = $btgamingProviderGameCode;
    }
    $payload['member_account'] = $btgamingMemberAccount;
    $payload['memberAccount'] = $btgamingMemberAccount;
    $payload['member'] = $btgamingMemberAccount;
    $payload['account'] = $btgamingMemberAccount;
    $payload['login'] = $btgamingMemberAccount;
    $payload['full_user_id'] = $btgamingMemberAccount;
    $payload['language'] = $btgamingLanguage;
    $payload['lang'] = $btgamingLanguage;
    $payload['Language'] = $btgamingLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
    $payload['providerName'] = 'BtGaming';
    $payload['supportedCurrencies'] = $btgamingSupportedCurrencies;
    $payload['restrictedTerritories'] = array();
    if ($btgamingCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isEvolutionLive) {
    // Evolution Live launch uses vendorCode=evolutionlive and the 32-character Game UID as gameCode/GameId.
    // Existing website games only are mapped; no new Evolution games are inserted.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $evolutionLiveMemberAccount;
    $payload['memberAccount'] = $evolutionLiveMemberAccount;
    $payload['member'] = $evolutionLiveMemberAccount;
    $payload['account'] = $evolutionLiveMemberAccount;
    $payload['login'] = $evolutionLiveMemberAccount;
    $payload['full_user_id'] = $evolutionLiveMemberAccount;
    $payload['language'] = $evolutionLiveLanguage;
    $payload['lang'] = $evolutionLiveLanguage;
    $payload['Language'] = $evolutionLiveLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'CasinoLive');
    $payload['providerName'] = ((isset($game['provider_id']) && (string)$game['provider_id'] === '59') ? 'Evolution Live (Asia)' : 'Evolution Live');
    $payload['testApiPreferredGame'] = 'Evolution Lobby';
    $payload['officialCurrencyNote'] = 'Official API requires IP address and currency to remain consistent.';
    $payload['supportedCurrenciesFromDoc'] = $evolutionLiveSupportedCurrencies;
    $payload['blockedTerritories'] = array('Australia','Cuba','Iran','North Korea','South Sudan','Sudan','Syria','Taiwan','Crimea','Myanmar');
    $payload['restrictedTerritoriesNeedLicense'] = array('Algeria','Angola','Bolivia','Bulgaria','Burkina Faso','Cameroon','Cote D’Ivoire','Democratic Republic Congo','Haiti','Kenya','Laos','Lebanon','Monaco','Mozambique','Namibia','Nepal','Nigeria','South Africa','Venezuela','Vietnam','UK','Yemen','US');
    if ($evolutionLiveCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isFastSpin) {
    // FastSpin launch uses vendorCode=fastspin and the 32-character Game UID as gameCode/GameId.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $fastSpinMemberAccount;
    $payload['memberAccount'] = $fastSpinMemberAccount;
    $payload['member'] = $fastSpinMemberAccount;
    $payload['account'] = $fastSpinMemberAccount;
    $payload['login'] = $fastSpinMemberAccount;
    $payload['full_user_id'] = $fastSpinMemberAccount;
    $payload['language'] = $fastSpinLanguage;
    $payload['lang'] = $fastSpinLanguage;
    $payload['Language'] = $fastSpinLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
    $payload['providerName'] = 'FastSpin';
    $payload['supportedCurrencies'] = $fastSpinSupportedCurrencies;
    $payload['restrictedTerritories'] = array('Taiwan');
    if ($fastSpinCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isFachaiGaming) {
    // FaChaiGaming launch uses vendorCode=fachaigaming and the 32-character Game UID as gameCode/GameId.
    // Docs also include a numeric provider Code; keep it as metadata and one fallback attempt.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    if ($fachaiGamingProviderGameCode !== '') {
        $payload['providerGameCode'] = $fachaiGamingProviderGameCode;
        $payload['originalGameCode'] = $fachaiGamingProviderGameCode;
        $payload['code'] = $fachaiGamingProviderGameCode;
        $payload['Code'] = $fachaiGamingProviderGameCode;
    }
    $payload['member_account'] = $fachaiGamingMemberAccount;
    $payload['memberAccount'] = $fachaiGamingMemberAccount;
    $payload['member'] = $fachaiGamingMemberAccount;
    $payload['account'] = $fachaiGamingMemberAccount;
    $payload['login'] = $fachaiGamingMemberAccount;
    $payload['full_user_id'] = $fachaiGamingMemberAccount;
    $payload['language'] = $fachaiGamingLanguage;
    $payload['lang'] = $fachaiGamingLanguage;
    $payload['Language'] = $fachaiGamingLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
    $payload['providerName'] = 'FaChaiGaming';
    $payload['supportedCurrencies'] = $fachaiGamingSupportedCurrencies;
    $payload['restrictedTerritories'] = array('Taiwan');
    if ($fachaiGamingCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}

if ($isSaba) {
    // SABA Sports docs require extras when getting gameurl, e.g. widgetId=<betting component ID>.
    // Use production classic1 widget by default; admin can override game_settings.saba_widget_id if needed.
    if ($sabaExtras !== '') {
        $payload['extras'] = $sabaExtras;
        $payload['Extras'] = $sabaExtras;
        $payload['extra'] = $sabaExtras;
    }
    $payload['language'] = $sabaLanguage;
    $payload['lang'] = $sabaLanguage;
    $payload['Language'] = $sabaLanguage;
    $payload['gameType'] = 'Sports Game';
    $payload['sportsType'] = 'sports';
    $payload['sabaWidgetId'] = $sabaWidgetId;
}

if ($isUnitedGaming) {
    // United Gaming docs require extras for the layout theme, e.g. extras="theme=style2".
    // Default is style2 from the provided documentation example; admin can override game_settings.unitedgaming_theme.
    if ($unitedGamingExtras !== '') {
        $payload['extras'] = $unitedGamingExtras;
        $payload['Extras'] = $unitedGamingExtras;
        $payload['extra'] = $unitedGamingExtras;
    }
    $payload['language'] = $unitedGamingLanguage;
    $payload['lang'] = $unitedGamingLanguage;
    $payload['Language'] = $unitedGamingLanguage;
    $payload['gameType'] = 'Sports Game';
    $payload['sportsType'] = 'sports';
    $payload['unitedGamingTheme'] = $unitedGamingTheme;
}

if ($isGameArt) {
    // GameArt launch uses vendorCode=gameart and the 32-character Game UID as gameCode/GameId.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $gameArtMemberAccount;
    $payload['memberAccount'] = $gameArtMemberAccount;
    $payload['member'] = $gameArtMemberAccount;
    $payload['account'] = $gameArtMemberAccount;
    $payload['login'] = $gameArtMemberAccount;
    $payload['full_user_id'] = $gameArtMemberAccount;
    $payload['language'] = $gameArtLanguage;
    $payload['lang'] = $gameArtLanguage;
    $payload['Language'] = $gameArtLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'slots');
    $payload['providerName'] = 'GameArt';
    $payload['supportedCurrencies'] = $gameArtSupportedCurrencies;
    if ($gameArtCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isIdeal) {
    // IDEAL launch uses vendorCode=ideal and the 32-character Game UID as gameCode/GameId.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $idealMemberAccount;
    $payload['memberAccount'] = $idealMemberAccount;
    $payload['member'] = $idealMemberAccount;
    $payload['account'] = $idealMemberAccount;
    $payload['login'] = $idealMemberAccount;
    $payload['full_user_id'] = $idealMemberAccount;
    $payload['language'] = $idealLanguage;
    $payload['lang'] = $idealLanguage;
    $payload['Language'] = $idealLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot Game');
    $payload['providerName'] = 'IDEAL';
    $payload['supportedCurrencies'] = $idealSupportedCurrencies;
    $payload['restrictedTerritories'] = array('China','Taiwan','Malaysia','Australia');
    if ($idealCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isInOut) {
    // INOUT launch uses vendorCode=inout and the 32-character Game UID as gameCode/GameId.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $inOutMemberAccount;
    $payload['memberAccount'] = $inOutMemberAccount;
    $payload['member'] = $inOutMemberAccount;
    $payload['account'] = $inOutMemberAccount;
    $payload['login'] = $inOutMemberAccount;
    $payload['full_user_id'] = $inOutMemberAccount;
    $payload['language'] = $inOutLanguage;
    $payload['lang'] = $inOutLanguage;
    $payload['Language'] = $inOutLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Instant');
    $payload['providerName'] = 'INOUT';
    $payload['supportedCurrencies'] = $inOutSupportedCurrencies;
    $payload['restrictedTerritories'] = array('USA','Curacao','Afghanistan','Antigua','Barbuda','Cuba','Iran','Iraq','Israel','Libya','Macau','Netherlands','Republic of Serbia','Sudan','Syria','UAE','Bonaire','Aruba','Sint Eustatius','St Maarten','Saba','France','Dubai');
    if ($inOutCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isLuckSportGaming) {
    // LuckSportGaming launch uses vendorCode=lucksportgaming and the 32-character Game UID as gameCode/GameId.
    // Documentation says to pass extras sport=soccer when getting launch link so the default page opens soccer.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $luckSportGamingMemberAccount;
    $payload['memberAccount'] = $luckSportGamingMemberAccount;
    $payload['member'] = $luckSportGamingMemberAccount;
    $payload['account'] = $luckSportGamingMemberAccount;
    $payload['login'] = $luckSportGamingMemberAccount;
    $payload['full_user_id'] = $luckSportGamingMemberAccount;
    $payload['language'] = $luckSportGamingLanguage;
    $payload['lang'] = $luckSportGamingLanguage;
    $payload['Language'] = $luckSportGamingLanguage;
    $payload['gameType'] = 'Sports Game';
    $payload['providerName'] = 'LuckySport';
    $payload['extras'] = $luckSportGamingExtras;
    $payload['Extras'] = $luckSportGamingExtras;
    $payload['extra'] = $luckSportGamingExtras;
    $payload['sport'] = $luckSportGamingSport;
    $payload['defaultSport'] = $luckSportGamingSport;
    $payload['supportedSports'] = $luckSportGamingAllowedSports;
    $payload['supportedCurrencies'] = $luckSportGamingSupportedCurrencies;
    $payload['restrictedTerritories'] = array('North America','United States','USA','Canada','Taiwan','Singapore','European Union','EU','United Kingdom','UK');
    if ($luckSportGamingCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isMicrogaming) {
    // Microgaming launch uses vendorCode=microgaming and the 32-character Game UID as primary GameId/gameCode.
    // The provider Code (SMG/P1/P2/WP/WD) is preserved and sent as metadata/fallback for engines that require it.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    if ($microgamingProviderGameCode !== '') {
        $payload['providerGameCode'] = $microgamingProviderGameCode;
        $payload['provider_game_code'] = $microgamingProviderGameCode;
        $payload['originalGameCode'] = $microgamingProviderGameCode;
        $payload['mgGameCode'] = $microgamingProviderGameCode;
    }
    $payload['member_account'] = $microgamingMemberAccount;
    $payload['memberAccount'] = $microgamingMemberAccount;
    $payload['member'] = $microgamingMemberAccount;
    $payload['account'] = $microgamingMemberAccount;
    $payload['login'] = $microgamingMemberAccount;
    $payload['full_user_id'] = $microgamingMemberAccount;
    $payload['language'] = $microgamingLanguage;
    $payload['lang'] = $microgamingLanguage;
    $payload['Language'] = $microgamingLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slots');
    $payload['providerName'] = 'Microgaming';
    $payload['supportedCurrencies'] = $microgamingSupportedCurrencies;
    $payload['restrictedTerritories'] = array('Australia','Singapore','Taiwan','South Africa','Belgium','Croatia','Denmark','Italy','Spain','United Kingdom','Sweden','Scandinavian','Czech Republic','United States');
    if ($microgamingCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isOnGaming) {
    // OnGaming launch uses vendorCode=ongaming and the 32-character Game UID as GameId/gameCode.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $ongamingMemberAccount;
    $payload['memberAccount'] = $ongamingMemberAccount;
    $payload['member'] = $ongamingMemberAccount;
    $payload['account'] = $ongamingMemberAccount;
    $payload['login'] = $ongamingMemberAccount;
    $payload['full_user_id'] = $ongamingMemberAccount;
    $payload['language'] = $ongamingLanguage;
    $payload['lang'] = $ongamingLanguage;
    $payload['Language'] = $ongamingLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'casino');
    $payload['providerName'] = 'OnGaming';
    $payload['supportedCurrencies'] = $ongamingSupportedCurrencies;
    $payload['restrictedTerritories'] = array('Taiwan');
    if ($ongamingCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isNetEnt) {
    // NetEnt launch uses vendorCode=netent and the 32-character Game UID as GameId/gameCode.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $netentMemberAccount;
    $payload['memberAccount'] = $netentMemberAccount;
    $payload['member'] = $netentMemberAccount;
    $payload['account'] = $netentMemberAccount;
    $payload['login'] = $netentMemberAccount;
    $payload['full_user_id'] = $netentMemberAccount;
    $payload['language'] = $netentLanguage;
    $payload['lang'] = $netentLanguage;
    $payload['Language'] = $netentLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
    $payload['providerName'] = 'NetEnt';
    $payload['supportedCurrencies'] = $netentSupportedCurrencies;
    $payload['restrictedTerritories'] = array('Australia','Cuba','Iran','North Korea','South Sudan','Sudan','Syria','Taiwan','Crimea','Myanmar','United States','United Kingdom','Vietnam','Venezuela','South Africa');
    if ($netentCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isMini) {
    // Mini launch uses vendorCode=mini and the 32-character Game UID as gameCode/GameId.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $miniMemberAccount;
    $payload['memberAccount'] = $miniMemberAccount;
    $payload['member'] = $miniMemberAccount;
    $payload['account'] = $miniMemberAccount;
    $payload['login'] = $miniMemberAccount;
    $payload['full_user_id'] = $miniMemberAccount;
    $payload['language'] = $miniLanguage;
    $payload['lang'] = $miniLanguage;
    $payload['Language'] = $miniLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'mini');
    $payload['providerName'] = 'Mini';
    $payload['supportedCurrencies'] = $miniSupportedCurrencies;
    $payload['restrictedTerritories'] = array();
    if ($miniCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}




if ($isSexy) {
    // Sexy/Sexy Video/T1/Spribe/TF/TFG/YEEBET launch uses the 32-character Game UID as GameId/gameCode.
    // member_account is alphanumeric-only per documentation.
    $sexyGameValue = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['GameId'] = $sexyGameValue;
    $payload['gameCode'] = $sexyGameValue;
    $payload['game_code'] = $sexyGameValue;
    $payload['game_id'] = $sexyGameValue;
    $payload['gameId'] = $sexyGameValue;
    $payload['gameUID'] = $sexyGameValue;
    $payload['providerGameCode'] = $sexyGameValue;
    $payload['member_account'] = $sexyMemberAccount;
    $payload['memberAccount'] = $sexyMemberAccount;
    $payload['member'] = $sexyMemberAccount;
    $payload['account'] = $sexyMemberAccount;
    $payload['login'] = $sexyMemberAccount;
    $payload['full_user_id'] = $sexyMemberAccount;
    $payload['language'] = $sexyLanguage;
    $payload['lang'] = $sexyLanguage;
    $payload['Language'] = $sexyLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'CasinoLive');
    $payload['providerName'] = isset($game['api_provider_name']) && $game['api_provider_name'] !== '' ? $game['api_provider_name'] : 'SexyGaming';
    $payload['supportedCurrencies'] = $sexySupportedCurrencies;
    $payload['restrictedTerritories'] = array('Taiwan');
    $payload['memberAccountRule'] = 'alphanumeric_only';
    if ($sexyCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isSbo) {
    // SBO launch supports three vendor codes from documentation: sbo, sbovirtualsport and sportsbook.
    // Use the 32-character Game UID as GameId/gameCode and preserve the selected vendor code per game.
    $sboGameValue = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['GameId'] = $sboGameValue;
    $payload['gameCode'] = $sboGameValue;
    $payload['game_code'] = $sboGameValue;
    $payload['game_id'] = $sboGameValue;
    $payload['gameId'] = $sboGameValue;
    $payload['gameUID'] = $sboGameValue;
    $payload['providerGameCode'] = $sboGameValue;
    $payload['member_account'] = $sboMemberAccount;
    $payload['memberAccount'] = $sboMemberAccount;
    $payload['member'] = $sboMemberAccount;
    $payload['account'] = $sboMemberAccount;
    $payload['login'] = $sboMemberAccount;
    $payload['full_user_id'] = $sboMemberAccount;
    $payload['language'] = $sboLanguage;
    $payload['lang'] = $sboLanguage;
    $payload['Language'] = $sboLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Sports Game');
    $payload['providerName'] = isset($game['api_provider_name']) && $game['api_provider_name'] !== '' ? $game['api_provider_name'] : 'SBO';
    $payload['supportedCurrencies'] = $sboSupportedCurrencies;
    $payload['restrictedTerritories'] = array('Singapore','Taiwan','Philippines');
    if ($vendorCode === 'sbovirtualsport') {
        $payload['sportsType'] = 'virtualsports';
        $payload['virtualSport'] = true;
    } else {
        $payload['sportsType'] = 'sportsbook';
    }
    if ($sboCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isDpSports) {
    // DP Sports / DP Esports launch uses the exact vendor codes from the provided vendor list:
    // dpesports and dpsports. Existing local game IDs are preserved as launch game codes unless
    // a separate API UID is configured later.
    $dpGameValue = $apiGameId !== '' ? $apiGameId : ($apiGameCode !== '' ? $apiGameCode : $local_game_uid);
    $payload['GameId'] = $dpGameValue;
    $payload['gameCode'] = $dpGameValue;
    $payload['game_code'] = $dpGameValue;
    $payload['game_id'] = $dpGameValue;
    $payload['gameId'] = $dpGameValue;
    $payload['gameUID'] = $dpGameValue;
    $payload['providerGameCode'] = $dpGameValue;
    $payload['originalGameCode'] = $dpGameValue;
    $payload['sourceGameCode'] = $dpGameValue;
    $payload['vendorCode'] = $vendorCode;
    $payload['VendorCode'] = $vendorCode;
    $payload['vendor_code'] = $vendorCode;
    $payload['apiVendorCode'] = $vendorCode;
    $payload['providerCode'] = $vendorCode;
    $payload['ProviderCode'] = $vendorCode;
    $payload['provider_code'] = $vendorCode;
    $payload['member_account'] = $dpsportsMemberAccount;
    $payload['memberAccount'] = $dpsportsMemberAccount;
    $payload['member'] = $dpsportsMemberAccount;
    $payload['account'] = $dpsportsMemberAccount;
    $payload['login'] = $dpsportsMemberAccount;
    $payload['full_user_id'] = $dpsportsMemberAccount;
    $payload['language'] = $dpsportsLanguage;
    $payload['lang'] = $dpsportsLanguage;
    $payload['Language'] = $dpsportsLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : ($vendorCode === 'dpesports' ? 'Esports' : 'Sports Game');
    $payload['providerName'] = isset($game['api_provider_name']) && $game['api_provider_name'] !== '' ? $game['api_provider_name'] : ($vendorCode === 'dpesports' ? 'DpEsports' : 'DpSports');
    $payload['sportsType'] = ($vendorCode === 'dpesports' ? 'esports' : 'sportsbook');
    $payload['supportedCurrencies'] = $dpsportsSupportedCurrencies;
    if ($dpsportsCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isSaGaming) {
    // SaGaming launch uses vendorCode=sagaming and the 32-character Game UID as GameId/gameCode.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $sagamingMemberAccount;
    $payload['memberAccount'] = $sagamingMemberAccount;
    $payload['member'] = $sagamingMemberAccount;
    $payload['account'] = $sagamingMemberAccount;
    $payload['login'] = $sagamingMemberAccount;
    $payload['full_user_id'] = $sagamingMemberAccount;
    $payload['language'] = $sagamingLanguage;
    $payload['lang'] = $sagamingLanguage;
    $payload['Language'] = $sagamingLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'CasinoLive');
    $payload['providerName'] = 'SaGaming';
    $payload['supportedCurrencies'] = $sagamingSupportedCurrencies;
    $payload['restrictedTerritories'] = array('China');
    $payload['supportsUSLogin'] = true;
    $payload['supportsUSDT'] = true;
    if ($sagamingCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isPragmatic) {
    // Pragmatic launch uses vendorCode=pragmatic and the 32-character Game UID as GameId/gameCode.
    // The provider code (vs*/numeric live table code) is sent as metadata/fallback.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    if ($pragmaticProviderGameCode !== '') {
        $payload['providerGameCode'] = $pragmaticProviderGameCode;
        $payload['provider_game_code'] = $pragmaticProviderGameCode;
        $payload['originalGameCode'] = $pragmaticProviderGameCode;
        $payload['pragmaticCode'] = $pragmaticProviderGameCode;
        $payload['tableCode'] = $pragmaticProviderGameCode;
    }
    $payload['member_account'] = $pragmaticMemberAccount;
    $payload['memberAccount'] = $pragmaticMemberAccount;
    $payload['member'] = $pragmaticMemberAccount;
    $payload['account'] = $pragmaticMemberAccount;
    $payload['login'] = $pragmaticMemberAccount;
    $payload['full_user_id'] = $pragmaticMemberAccount;
    $payload['language'] = $pragmaticLanguage;
    $payload['lang'] = $pragmaticLanguage;
    $payload['Language'] = $pragmaticLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Video Slots');
    $payload['providerName'] = isset($game['api_provider_name']) && $game['api_provider_name'] !== '' ? $game['api_provider_name'] : 'PragmaticPlay';
    $payload['supportedCurrencies'] = $pragmaticSupportedCurrencies;
    $payload['restrictedTerritories'] = array('US','FR','IL','TW','AU','KP','IN','SG','IR','AE','LB');
    if ($pragmaticCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


$payloadAttempts = array();

$vendorMode = preg_replace('/[^a-z0-9]+/', '_', strtolower($vendorCode));
if ($vendorMode === '') { $vendorMode = 'provider'; }

// First try the official numeric Code from the active game list.
$payloadAttempts[] = array(
    'mode' => 'json_' . $vendorMode . '_numeric_code',
    'payload' => $payload,
    'sent_game_code' => $apiGameCode
);

if ($isEvolutionLive) {
    // EvolutionLive final page was returning black/GAME NOT FOUND even when OXEN returned code=0.
    // The log showed every launch was sent as preview/demo with fake UserBalance=0.01 while
    // the local wallet callback would return the real DB balance 0.00. Evolution live tables must
    // be launched as a normal wallet session, not as local preview/demo.
    $evolutionGameUid = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $evolutionVendor = 'evolutionlive';
    $evolutionMemberAccount = (($agentCode !== '') ? $agentCode . '_' : '') . (string)$u_data['id'];
    $evolutionMemberAccount = preg_replace('/[^A-Za-z0-9_\-]/', '_', $evolutionMemberAccount);
    if ($evolutionMemberAccount === '') { $evolutionMemberAccount = (string)$u_data['id']; }

    $payload['GameId'] = $evolutionGameUid;
    $payload['gameCode'] = $evolutionGameUid;
    $payload['game_code'] = $evolutionGameUid;
    $payload['game_id'] = $evolutionGameUid;
    $payload['gameId'] = $evolutionGameUid;
    $payload['gameUID'] = $evolutionGameUid;
    $payload['vendorCode'] = $evolutionVendor;
    $payload['VendorCode'] = $evolutionVendor;
    $payload['vendor_code'] = $evolutionVendor;
    $payload['providerCode'] = $evolutionVendor;
    $payload['ProviderCode'] = $evolutionVendor;
    $payload['provider_code'] = $evolutionVendor;
    $payload['apiVendorCode'] = $evolutionVendor;
    $payload['providerName'] = 'Evolution Live';
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : 'CasinoLive';
    $payload['UserBalance'] = $walletBalance;
    $payload['userBalance'] = $walletBalance;
    $payload['balance'] = $walletBalance;
    $payload['creditAmount'] = $walletBalance;
    $payload['playMode'] = 'real';
    $payload['bettingEnabled'] = true;
    unset($payload['previewMode'], $payload['isDemo'], $payload['demo'], $payload['is_demo'], $payload['Demo'], $payload['IsDemo']);

    // First attempt: compact real wallet launch without preview/demo/balance hints.
    // This follows provider docs but adds the required live-wrapper userId/vendor fields.
    $evolutionRealCompactPayload = array(
        'ValidationToken' => $apiToken,
        'GameId' => $evolutionGameUid,
        'PlayerId' => (string)$u_data['id'],
        'PlayerName' => $playerName,
        'Currency' => $currency,
        'ReturnUrl' => $returnUrl,
        'userId' => (string)$u_data['id'],
        'userName' => $playerName,
        'UserBalance' => $walletBalance,
        'userBalance' => $walletBalance,
        'balance' => $walletBalance,
        'gameCode' => $evolutionGameUid,
        'game_id' => $evolutionGameUid,
        'gameId' => $evolutionGameUid,
        'gameUID' => $evolutionGameUid,
        'vendorCode' => $evolutionVendor,
        'VendorCode' => $evolutionVendor,
        'vendor_code' => $evolutionVendor,
        'providerCode' => $evolutionVendor,
        'ProviderCode' => $evolutionVendor,
        'provider_code' => $evolutionVendor,
        'callback_url' => $callbackUrl,
        'callbackUrl' => $callbackUrl,
        'CallbackUrl' => $callbackUrl,
        'language' => isset($payload['language']) ? $payload['language'] : 'en',
        'lang' => isset($payload['lang']) ? $payload['lang'] : 'en'
    );

    // Second attempt: same real wallet session, with real DB balance only.
    $evolutionRealWalletPayload = $evolutionRealCompactPayload + array(
        'UserBalance' => $walletBalance,
        'userBalance' => $walletBalance,
        'balance' => $walletBalance,
        'creditAmount' => $walletBalance,
        'playerId' => (string)$u_data['id'],
        'playerName' => $playerName,
        'currency' => $currency,
        'returnUrl' => $returnUrl,
        'agentCode' => $agentCode,
        'merchantCode' => $agentCode,
        'member_account' => $evolutionMemberAccount,
        'memberAccount' => $evolutionMemberAccount,
        'account' => $evolutionMemberAccount,
        'login' => $evolutionMemberAccount,
        'full_user_id' => $evolutionMemberAccount,
        'playMode' => 'real',
        'bettingEnabled' => true
    );

    $evolutionFullRealPayload = $payload;

    $evolutionMinimalJsonPlusUser = array(
        'ValidationToken' => $apiToken,
        'GameId' => $evolutionGameUid,
        'PlayerId' => (string)$u_data['id'],
        'PlayerName' => $playerName,
        'Currency' => $currency,
        'ReturnUrl' => $returnUrl,
        'userId' => (string)$u_data['id'],
        'gameCode' => $evolutionGameUid,
        'vendorCode' => $evolutionVendor,
        'VendorCode' => $evolutionVendor
    );

    $payloadAttempts = array(
        array(
            'mode' => 'json_evolutionlive_real_wallet_no_demo',
            'payload' => $evolutionRealWalletPayload,
            'sent_game_code' => $evolutionGameUid,
            'transport' => 'json'
        ),
        array(
            'mode' => 'json_evolutionlive_real_compact_no_demo',
            'payload' => $evolutionRealCompactPayload,
            'sent_game_code' => $evolutionGameUid,
            'transport' => 'json'
        ),
        array(
            'mode' => 'json_evolutionlive_full_real_no_demo',
            'payload' => $evolutionFullRealPayload,
            'sent_game_code' => $evolutionGameUid,
            'transport' => 'json'
        ),
        array(
            'mode' => 'json_evolutionlive_minimal_plus_userid',
            'payload' => $evolutionMinimalJsonPlusUser,
            'sent_game_code' => $evolutionGameUid,
            'transport' => 'json'
        )
    );
}

if ($is9Wicket) {
    // 9Wicket/OxenTech wrapper has been returning 10022 even when transfer_id is present in the flat JSON.
    // Send a small set of explicit JSON-only variants so the wrapper can read transfer_id from flat, query, extras, or nested payload/data.
    $nineWicketGameUid = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $nineWicketTransferCore = array(
        'transfer_id' => $nineWicketTransferId,
        'transferId' => $nineWicketTransferId,
        'TransferId' => $nineWicketTransferId,
        'TransferID' => $nineWicketTransferId,
        'transferID' => $nineWicketTransferId,
        'trans_id' => $nineWicketTransferId,
        'transId' => $nineWicketTransferId,
        'TransId' => $nineWicketTransferId,
        'transaction_id' => $nineWicketTransferId,
        'transactionId' => $nineWicketTransferId,
        'TransactionId' => $nineWicketTransferId,
        'order_id' => $nineWicketTransferId,
        'orderId' => $nineWicketTransferId,
        'OrderId' => $nineWicketTransferId,
        'merchant_order_id' => $nineWicketTransferId,
        'merchantOrderId' => $nineWicketTransferId,
        'reference_id' => $nineWicketTransferId,
        'ref_no' => $nineWicketTransferId,
        'amount' => $walletBalance,
        'Amount' => $walletBalance,
        'transfer_amount' => $walletBalance,
        'transferAmount' => $walletBalance,
        'currency' => $currency,
        'Currency' => $currency,
        'member_account' => $nineWicketMemberAccount,
        'memberAccount' => $nineWicketMemberAccount,
        'agentCode' => $agentCode,
        'merchantCode' => $agentCode,
        'vendor_code' => '9wicket',
        'vendorCode' => '9wicket',
        'provider_code' => '9wicket',
        'providerCode' => '9wicket',
        'game_code' => $nineWicketGameUid,
        'gameCode' => $nineWicketGameUid,
        'game_id' => $nineWicketGameUid,
        'gameId' => $nineWicketGameUid,
        'GameId' => $nineWicketGameUid,
        'game_uid' => $nineWicketGameUid,
        'gameUID' => $nineWicketGameUid,
        'apiPath' => '/game/v2',
        'gameVersion' => 'v2'
    );
    $nineWicketExtrasJson = json_encode($nineWicketTransferCore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($nineWicketExtrasJson === false) { $nineWicketExtrasJson = ''; }
    $nineWicketExtrasQuery = http_build_query($nineWicketTransferCore);
    $nineWicketEndpointParams = array_merge($nineWicketTransferCore, array(
        'extras' => $nineWicketExtrasJson,
        'extra' => $nineWicketExtrasJson,
        'api_path' => '/game/v2'
    ));

    $flatPayload = $payload;
    $flatPayload['extras'] = $nineWicketExtrasJson;
    $flatPayload['extra'] = $nineWicketExtrasJson;
    $flatPayload['Extra'] = $nineWicketExtrasJson;
    foreach ($nineWicketTransferCore as $k => $v) { $flatPayload[$k] = $v; }

    $payloadAttempts = array();
    $payloadAttempts[] = array(
        'mode' => 'json_9wicket_flat_transfer_id_default',
        'payload' => $flatPayload,
        'sent_game_code' => $nineWicketGameUid,
        'transport' => 'json'
    );

    $queryPayload = $flatPayload;
    $payloadAttempts[] = array(
        'mode' => 'json_9wicket_query_transfer_id',
        'payload' => $queryPayload,
        'sent_game_code' => $nineWicketGameUid,
        'endpoint' => oxen_endpoint_with_query($apiEndpoint, $nineWicketEndpointParams),
        'transport' => 'json'
    );

    $queryStringExtrasPayload = $flatPayload;
    $queryStringExtrasPayload['extras'] = $nineWicketExtrasQuery;
    $queryStringExtrasPayload['extra'] = $nineWicketExtrasQuery;
    $queryStringExtrasPayload['Extra'] = $nineWicketExtrasQuery;
    $payloadAttempts[] = array(
        'mode' => 'json_9wicket_extras_query_string',
        'payload' => $queryStringExtrasPayload,
        'sent_game_code' => $nineWicketGameUid,
        'endpoint' => oxen_endpoint_with_query($apiEndpoint, array('vendor_code'=>'9wicket','transfer_id'=>$nineWicketTransferId,'extras'=>$nineWicketExtrasQuery)),
        'transport' => 'json'
    );

    $wrappedPayload = array(
        'ValidationToken' => $apiToken,
        'vendorCode' => '9wicket',
        'vendor_code' => '9wicket',
        'providerCode' => '9wicket',
        'provider_code' => '9wicket',
        'GameId' => $nineWicketGameUid,
        'gameCode' => $nineWicketGameUid,
        'game_id' => $nineWicketGameUid,
        'PlayerId' => (string)$u_data['id'],
        'PlayerName' => (string)$u_data['username'],
        'Currency' => $currency,
        'amount' => $walletBalance,
        'UserBalance' => $balance,
        'ReturnUrl' => $returnUrl,
        'callback_url' => $callbackUrl,
        'callbackUrl' => $callbackUrl,
        'apiPath' => '/game/v2',
        'transfer_id' => $nineWicketTransferId,
        'transferId' => $nineWicketTransferId,
        'order_id' => $nineWicketTransferId,
        'extras' => $nineWicketExtrasJson,
        'extra' => $nineWicketExtrasJson,
        'payload' => $nineWicketTransferCore,
        'data' => $nineWicketTransferCore,
        'params' => $nineWicketTransferCore,
        'request' => $nineWicketTransferCore,
        'body' => $nineWicketTransferCore
    );
    $payloadAttempts[] = array(
        'mode' => 'json_9wicket_wrapped_payload_data',
        'payload' => $wrappedPayload,
        'sent_game_code' => $nineWicketGameUid,
        'transport' => 'json'
    );

    // Last resort: keep direct documented path attempts for providers that expose /api/game/v2 on the same host.
    $payloadAttempts[] = array(
        'mode' => 'json_9wicket_direct_api_game_v2_flat',
        'payload' => $flatPayload,
        'sent_game_code' => $nineWicketGameUid,
        'endpoint' => oxen_endpoint_replace_path($apiEndpoint, '/api/game/v2'),
        'transport' => 'json'
    );
}



if ($isT1 || $isSpribe || $isTf || $isTfg || $isYeebet) {
    $newProviderAltVendors = array();
    if ($isT1) { $newProviderAltVendors = array('t1gaming'); }
    elseif ($isSpribe) { $newProviderAltVendors = array('spribe-gaming'); }
    elseif ($isTf) { $newProviderAltVendors = array('tfgaming'); }
    elseif ($isTfg) { $newProviderAltVendors = array('tfgaming'); }
    elseif ($isYeebet) { $newProviderAltVendors = array('yee-bet','yeebetgaming'); }
    foreach ($newProviderAltVendors as $altVendorCode) {
        if ($altVendorCode === $vendorCode) { continue; }
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
    if ($newProviderGameCode !== '') {
        $codePayload = $payload;
        $codePayload['gameCode'] = $newProviderGameCode;
        $codePayload['game_code'] = $newProviderGameCode;
        $codePayload['game_id'] = $newProviderGameCode;
        $codePayload['gameId'] = $newProviderGameCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . $vendorMode . '_provider_code_fallback',
            'payload' => $codePayload,
            'sent_game_code' => $newProviderGameCode
        );
    }
}

// 9Wicket is configured on OxenTech as canonical vendor_code=9wicket.
// Do not send 9w/9wickets alias fallback for transfer wallet because fallback can cause duplicate transfer attempts
// and may overwrite the real provider message with an unrelated unknown-provider error.

if ($isAmigo) {
    foreach (array('amigogaming', 'amigo-gaming') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isAog) {
    foreach (array('aogaming', 'aog-gaming') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}



if ($isAstar) {
    foreach (array('astargaming', 'astar-gaming') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isBgaming) {
    foreach (array('b-gaming', 'b gaming', 'bgaminggames') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isBigTimeGaming) {
    foreach (array('btg', 'big-time-gaming', 'big time gaming', 'bigtime') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isBtGaming) {
    foreach (array('bt-gaming', 'bt gaming', 'betgaming', 'btgame') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
    if ($btgamingProviderGameCode !== '') {
        $codePayload = $payload;
        $codePayload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $codePayload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $codePayload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $codePayload['gameCode'] = $btgamingProviderGameCode;
        $codePayload['game_code'] = $btgamingProviderGameCode;
        $codePayload['providerGameCode'] = $btgamingProviderGameCode;
        $codePayload['originalGameCode'] = $btgamingProviderGameCode;
        $payloadAttempts[] = array(
            'mode' => 'json_btgaming_provider_code_fallback',
            'payload' => $codePayload,
            'sent_game_code' => $btgamingProviderGameCode
        );
    }
}


if ($isEvolutionLive) {
    foreach (array('evolution-live', 'evolutionliveasia', 'evolution-live-asia', 'evolution') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isFastSpin) {
    // FastSpin launch uses vendorCode=fastspin and the 32-character Game UID as gameCode/GameId.
    $payload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameCode'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_code'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['game_id'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
    $payload['member_account'] = $fastSpinMemberAccount;
    $payload['memberAccount'] = $fastSpinMemberAccount;
    $payload['member'] = $fastSpinMemberAccount;
    $payload['account'] = $fastSpinMemberAccount;
    $payload['login'] = $fastSpinMemberAccount;
    $payload['full_user_id'] = $fastSpinMemberAccount;
    $payload['language'] = $fastSpinLanguage;
    $payload['lang'] = $fastSpinLanguage;
    $payload['Language'] = $fastSpinLanguage;
    $payload['gameType'] = isset($game['api_game_type']) && $game['api_game_type'] !== '' ? $game['api_game_type'] : (isset($game['category']) ? $game['category'] : 'Slot');
    $payload['providerName'] = 'FastSpin';
    $payload['supportedCurrencies'] = $fastSpinSupportedCurrencies;
    $payload['restrictedTerritories'] = array('Taiwan');
    if ($fastSpinCurrencyWasAdjusted) {
        $payload['currencyAdjustedFrom'] = $siteCurrency;
    }
}


if ($isFastSpin) {
    foreach (array('fast-spin', 'fast spin', 'fastspingaming', 'fastspin-gaming') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isGameArt) {
    foreach (array('game-art', 'game art', 'gameartgaming', 'gameart-gaming') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isIdeal) {
    foreach (array('ideal-gaming', 'ideal gaming', 'idealgaming', 'ideal-slot', 'idealslot') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}




if ($isSexy) {
    $sexyAliasList = array('sexy', 'sexy_video', 'sexy-video', 'sexy video', 'sexygaming', 'sexy-gaming', 'sexy gaming');
    foreach ($sexyAliasList as $altVendorCode) {
        if ($altVendorCode === $vendorCode) { continue; }
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isSbo) {
    $sboAliasList = array('sbo', 'sbo-sportsbook', 'sbo sportsbook', 'sbovirtualsport', 'sbovirtualsports', 'sbo-virtualsport', 'sbo-virtualsports', 'sportsbook');
    foreach ($sboAliasList as $altVendorCode) {
        if ($altVendorCode === $vendorCode) { continue; }
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isSaGaming) {
    foreach (array('sa-gaming', 'sa gaming', 'sagame', 'sacasino') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isPragmatic) {
    foreach (array('pragmaticplay', 'pragmatic-play', 'pragmatic play', 'pp', 'pragmaticlive', 'pragmaticplaylive') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
    if ($pragmaticProviderGameCode !== '') {
        $codePayload = $payload;
        $codePayload['GameId'] = $pragmaticProviderGameCode;
        $codePayload['gameCode'] = $pragmaticProviderGameCode;
        $codePayload['game_code'] = $pragmaticProviderGameCode;
        $codePayload['game_id'] = $pragmaticProviderGameCode;
        $codePayload['gameId'] = $pragmaticProviderGameCode;
        $codePayload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $codePayload['providerGameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $payloadAttempts[] = array(
            'mode' => 'json_pragmatic_provider_code_fallback',
            'payload' => $codePayload,
            'sent_game_code' => $pragmaticProviderGameCode
        );
    }
}


if ($isOnGaming) {
    foreach (array('on-gaming', 'on gaming', 'ongamingcasino') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isNetEnt) {
    foreach (array('net-ent', 'net ent', 'netentgaming', 'evolution-netent', 'evolution netent') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isMini) {
    foreach (array('mini-games', 'mini games', 'minigames', 'mini-gaming', 'minigaming') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isMicrogaming) {
    foreach (array('micro-gaming', 'micro gaming', 'mg', 'mgplus', 'mglivegrand') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
    if ($microgamingProviderGameCode !== '') {
        $codePayload = $payload;
        $codePayload['GameId'] = $microgamingProviderGameCode;
        $codePayload['gameCode'] = $microgamingProviderGameCode;
        $codePayload['game_code'] = $microgamingProviderGameCode;
        $codePayload['game_id'] = $microgamingProviderGameCode;
        $codePayload['gameId'] = $microgamingProviderGameCode;
        $codePayload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $codePayload['providerGameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $payloadAttempts[] = array(
            'mode' => 'json_microgaming_provider_code_fallback',
            'payload' => $codePayload,
            'sent_game_code' => $microgamingProviderGameCode
        );
    }
}


if ($isLuckSportGaming) {
    foreach (array('luck-sport-gaming', 'luck sport gaming', 'luckysport', 'lucky-sport', 'luck-sport') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $altPayload['extras'] = $luckSportGamingExtras;
        $altPayload['sport'] = $luckSportGamingSport;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isInOut) {
    foreach (array('in-out', 'in out', 'inoutgaming', 'inout-gaming', 'inoutgames') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
}


if ($isFachaiGaming) {
    foreach (array('fachai', 'fa-chai', 'fa chai', 'facai', 'facai-gaming', 'fcgaming') as $altVendorCode) {
        $altPayload = $payload;
        $altPayload['vendorCode'] = $altVendorCode;
        $altPayload['apiVendorCode'] = $altVendorCode;
        $altPayload['providerCode'] = $altVendorCode;
        $payloadAttempts[] = array(
            'mode' => 'json_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($altVendorCode)) . '_vendor_alias',
            'payload' => $altPayload,
            'sent_game_code' => $apiGameCode
        );
    }
    if ($fachaiGamingProviderGameCode !== '') {
        $codePayload = $payload;
        $codePayload['GameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $codePayload['gameId'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $codePayload['gameUID'] = $apiGameId !== '' ? $apiGameId : $apiGameCode;
        $codePayload['gameCode'] = $fachaiGamingProviderGameCode;
        $codePayload['game_code'] = $fachaiGamingProviderGameCode;
        $codePayload['providerGameCode'] = $fachaiGamingProviderGameCode;
        $codePayload['originalGameCode'] = $fachaiGamingProviderGameCode;
        $payloadAttempts[] = array(
            'mode' => 'json_fachaigaming_provider_code_fallback',
            'payload' => $codePayload,
            'sent_game_code' => $fachaiGamingProviderGameCode
        );
    }
}

// OxenTech live wrapper can reject numeric code but accept the 32-char Game UID.
// Try UID as gameCode too, while keeping numeric code as providerGameCode/originalGameCode for compatibility.
if ($apiGameId !== '' && $apiGameId !== $apiGameCode) {
    $payloadAttempts[] = array(
        'mode' => 'json_' . $vendorMode . '_uid_fallback',
        'payload' => oxen_payload_with_game_value($payload, $apiGameId, $apiGameCode, $apiGameId),
        'sent_game_code' => $apiGameId
    );
}

// Speed optimization:
// Some live wrappers reject JILI numeric Code only after several internal retry/currency checks
// (4-7+ seconds), then accept the 32-character Game UID immediately. Use the known working
// UID attempt first for JILI, while keeping numeric Code as fallback/metadata so existing
// mapping and old working games remain safe. PGSoft/TADA also keep UID-first based on the
// existing production flow.
$preferredLaunchMode = '';
if ($isEvolutionLive) {
    $preferredLaunchMode = 'json_evolutionlive_real_wallet_no_demo';
} elseif (($isJili || $isPgsoft || $isTadaGaming) && $apiGameId !== '') {
    $preferredLaunchMode = 'json_' . $vendorMode . '_uid_fallback';
}
if ($preferredLaunchMode !== '' && count($payloadAttempts) > 1) {
    $preferredAttempts = array();
    $otherAttempts = array();
    foreach ($payloadAttempts as $attemptDef) {
        if (isset($attemptDef['mode']) && $attemptDef['mode'] === $preferredLaunchMode) {
            $preferredAttempts[] = $attemptDef;
        } else {
            $otherAttempts[] = $attemptDef;
        }
    }
    if (!empty($preferredAttempts)) {
        $payloadAttempts = array_merge($preferredAttempts, $otherAttempts);
    }
}

$attemptLog = array();
$chosen = null;
$result = null;
$game_url = '';
$status_ok = false;
$message = 'Provider did not return a game URL.';
$maskedPayload = game_api_mask_sensitive($payloadAttempts[0]['payload']);

foreach ($payloadAttempts as $idx => $attemptDef) {
    $attemptPayload = $attemptDef['payload'];
    if ($idx === 0) {
        game_api_debug_log('launch_request_prepare', array(
            'user_id' => $u_data['id'],
            'local_game_uid' => $local_game_uid,
            'game_name' => isset($game['name']) ? $game['name'] : '',
            'provider_id' => isset($game['provider_id']) ? $game['provider_id'] : '',
            'vendor_code' => $vendorCode,
            'api_game_code' => $apiGameCode,
            'api_game_id' => $apiGameId,
            'endpoint' => $apiEndpoint,
            'currency' => $currency,
            'actual_balance' => $walletBalance,
            'launch_balance' => $balance,
            'preview_mode' => $isPreviewLaunch,
            'agent_code' => $agentCode,
            'callback_url' => $callbackUrl,
            'auth_headers' => game_api_mask_sensitive(array('X-API-Token' => $apiToken, 'X-Secret-Key' => $secretKey)),
            'primary_payload' => game_api_mask_sensitive($attemptPayload),
            'fallback_uid_enabled' => (count($payloadAttempts) > 1)
        ));
    } else {
        game_api_debug_log('launch_uid_fallback_prepare', array(
            'user_id' => $u_data['id'],
            'local_game_uid' => $local_game_uid,
            'game_name' => isset($game['name']) ? $game['name'] : '',
            'vendor_code' => $vendorCode,
            'numeric_game_code' => $apiGameCode,
            'uid_game_code' => $apiGameId,
            'payload' => game_api_mask_sensitive($attemptPayload)
        ));
    }

    $attemptEndpoint = isset($attemptDef['endpoint']) && $attemptDef['endpoint'] !== '' ? $attemptDef['endpoint'] : $apiEndpoint;
    $attemptTransport = isset($attemptDef['transport']) ? (string)$attemptDef['transport'] : 'json';
    if ($attemptTransport === 'form') {
        $try = oxen_send_launch_form_request($attemptEndpoint, $attemptPayload, $apiToken, $secretKey);
    } else {
        $try = oxen_send_launch_request($attemptEndpoint, $attemptPayload, $apiToken, $secretKey);
    }
    $try['mode'] = $attemptDef['mode'];
    $tryResult = $try['decoded'];
    $tryMessage = is_array($tryResult)
        ? game_api_response_message($tryResult, 'Provider did not return a game URL.')
        : ($try['curl_error'] ? $try['curl_error'] : 'Provider response was not valid JSON. JSON error: ' . $try['json_error']);

    $attemptLog[] = array(
        'mode' => $try['mode'],
        'sent_game_code' => $attemptDef['sent_game_code'],
        'transport' => isset($attemptDef['transport']) ? $attemptDef['transport'] : 'json',
        'endpoint_mode' => isset($attemptDef['endpoint']) ? 'query' : 'default',
        'http_code' => $try['http_code'],
        'content_type' => $try['content_type'],
        'duration_ms' => isset($try['duration_ms']) ? $try['duration_ms'] : 0,
        'curl_error' => $try['curl_error'],
        'json_error' => $try['json_error'],
        'game_url_found' => $try['game_url'] !== '',
        'message' => $tryMessage,
        // Keep launch fast: do not write the full encrypted launch URL/HTML response to DB/logs.
        // It can be many KB and slows down the redirect after the provider already returned a URL.
        'response' => (is_string($try['response']) && strlen($try['response']) > 700) ? substr($try['response'], 0, 700) . '... [truncated]' : $try['response']
    );

    $chosen = $try;
    $result = $tryResult;
    $game_url = $try['game_url'];
    $status_ok = ($game_url !== '');
    $message = $status_ok ? 'Game URL received' : $tryMessage;
    $maskedPayload = game_api_mask_sensitive($attemptPayload);

    if ($status_ok) { break; }
    if (oxen_is_non_retryable_launch_error($tryResult, $tryMessage, $try['http_code'])) {
        break;
    }
}

if ($chosen === null) {
    $chosen = array('mode' => 'no_attempt', 'http_code' => 0, 'content_type' => '', 'duration_ms' => 0, 'curl_error' => '', 'json_error' => '', 'game_url' => '', 'response' => '');
}

game_api_log($conn, array(
    'user_id' => $u_data['id'],
    'local_game_uid' => $local_game_uid,
    'api_game_id' => $apiGameCode,
    'endpoint' => $apiEndpoint,
    'request_data' => $maskedPayload,
    'response_data' => $attemptLog,
    'status' => $status_ok ? 'success' : 'failed',
    'message' => $status_ok ? 'Game URL received via ' . $chosen['mode'] : $message . ' HTTP:' . $chosen['http_code'] . ' mode:' . $chosen['mode']
));

game_api_debug_log($status_ok ? 'launch_success' : 'launch_failed_provider_response', array(
    'user_id' => $u_data['id'],
    'local_game_uid' => $local_game_uid,
    'vendor_code' => $vendorCode,
    'api_game_code' => $apiGameCode,
    'api_game_id' => $apiGameId,
    'endpoint' => $apiEndpoint,
    'selected_mode' => $chosen['mode'],
    'message' => $message,
    'preview_mode' => $isPreviewLaunch,
    'actual_balance' => $walletBalance,
    'launch_balance' => $balance,
    'attempts' => $attemptLog
));

if (!$status_ok || $game_url === '') {
    show_launch_error('Game Launch Failed', $message);
}

if ($isJili || $is9Wicket || $isEvolutionLive) {
    // JILI and live-casino providers open fastest as a direct top-window redirect, not through the extra iframe page.
    $redirectUrl = str_replace(array("\r", "\n"), '', $game_url);
    game_api_debug_log('launch_redirect_top_window', array(
        'user_id' => $u_data['id'],
        'local_game_uid' => $local_game_uid,
        'vendor_code' => $vendorCode,
        'selected_mode' => $chosen['mode'],
        'redirect_url_host' => parse_url($redirectUrl, PHP_URL_HOST),
        'preview_mode' => $isPreviewLaunch,
        'actual_balance' => $walletBalance
    ));
    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Referrer-Policy: origin-when-cross-origin');
        header('Location: ' . $redirectUrl, true, 302);
    }
    $safeRedirectUrl = htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8');
    $jsonRedirectUrl = json_encode($redirectUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Opening Game</title>";
    echo "<meta http-equiv='refresh' content='0;url=" . $safeRedirectUrl . "'>";
    echo "<script>try{window.top.location.href=" . $jsonRedirectUrl . ";}catch(e){window.location.href=" . $jsonRedirectUrl . ";}</script>";
    echo "</head><body style='margin:0;background:#000;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh'>";
    echo "<div style='text-align:center'><h3>Opening game...</h3><p><a id='openGame' style='color:#00f3ff' target='_top' href='" . $safeRedirectUrl . "'>Click here if the game does not open automatically</a></p></div>";
    echo "<script>setTimeout(function(){document.getElementById('openGame').click();},300);</script>";
    echo "</body></html>";
    exit;
}

$safe_game_url = htmlspecialchars($game_url, ENT_QUOTES, 'UTF-8');
$safe_title = htmlspecialchars(isset($game['name']) ? $game['name'] : $local_game_uid, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Playing - <?php echo $safe_title; ?></title>
    <style>
        body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background-color: #000; }
        .game-wrapper { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; }
        iframe { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body>
    <div class="game-wrapper">
        <iframe src="<?php echo $safe_game_url; ?>" allowfullscreen></iframe>
    </div>
    <script>window.scrollTo(0, 1);</script>
</body>
</html>
