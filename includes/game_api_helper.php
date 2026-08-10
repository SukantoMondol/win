<?php
/**
 * Dynamic OXEN_TECH Game API helper.
 * Keeps provider credentials and game mapping in database so the same source can be moved
 * to another domain by changing values from Admin Panel > Game API Key.
 */

if (!array_key_exists('GAME_API_SETTINGS_CACHE', $GLOBALS)) { $GLOBALS['GAME_API_SETTINGS_CACHE'] = null; }
if (!array_key_exists('GAME_API_SCHEMA_READY', $GLOBALS)) { $GLOBALS['GAME_API_SCHEMA_READY'] = false; }

if (!function_exists('game_api_table_exists')) {

    function game_api_debug_log_path() {
        $root = dirname(__DIR__);
        $apiDir = $root . '/api';
        if (!is_dir($apiDir)) { @mkdir($apiDir, 0755, true); }
        return $apiDir . '/game_api_debug.log';
    }

    function game_api_mask_sensitive($value) {
        if (is_array($value)) {
            $masked = array();
            foreach ($value as $k => $v) {
                $lk = strtolower((string)$k);
                if (strpos($lk, 'token') !== false || strpos($lk, 'secret') !== false || strpos($lk, 'password') !== false || strpos($lk, 'key') !== false || $lk === 'validationtoken') {
                    $masked[$k] = is_string($v) && $v !== '' ? substr($v, 0, 4) . '***hidden***' . substr($v, -4) : '***hidden***';
                } else {
                    $masked[$k] = game_api_mask_sensitive($v);
                }
            }
            return $masked;
        }
        if (is_string($value) && strlen($value) > 2500) {
            return substr($value, 0, 2500) . '... [truncated]';
        }
        return $value;
    }

    function game_api_debug_log($event, $context = array()) {
        $path = game_api_debug_log_path();
        $safeContext = game_api_mask_sensitive($context);
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $event;
        $meta = array(
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : ''
        );
        $payload = array('meta' => $meta, 'context' => $safeContext);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) { $json = 'json_encode_failed'; }
        @file_put_contents($path, $line . ' ' . $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        return $path;
    }

    function game_api_start_error_logging() {
        $path = game_api_debug_log_path();
        @ini_set('log_errors', '1');
        @ini_set('error_log', $path);
        @ini_set('display_errors', '0');
        error_reporting(E_ALL);
        return $path;
    }


    function game_api_clean_text($value) {
        return trim((string)$value);
    }

    function game_api_clean_no_space($value) {
        return preg_replace('/\s+/', '', trim((string)$value));
    }

    function game_api_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    function game_api_normalize_name($name) {
        $name = strtolower(trim((string)$name));
        $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        $name = str_replace(array('’','‘','`','´'), "'", $name);
        $name = preg_replace('/[^a-z0-9]+/i', '', $name);
        return $name;
    }

    function game_api_is_first_provider_jili($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtoupper(trim((string)$game['api_vendor_code'])) : '';
        if ($apiVendor === 'JILI') { return true; }
        if ($apiProvider === 'jili' || $apiProvider === 'jiligaming' || $apiProvider === 'jili gaming') { return true; }
        if ($providerId === '49') { return true; }
        if (strpos($providerName, 'jili') !== false) { return true; }
        if (strpos($providerSlug, 'jili') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_pgsoft($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        if ($apiVendor === 'pgsoft' || $apiVendor === 'pg') { return true; }
        if ($apiProvider === 'pgsoft' || $apiProvider === 'pg soft' || $apiProvider === 'pg') { return true; }
        if ($providerId === '45') { return true; }
        if (strpos($providerName, 'pgsoft') !== false || strpos($providerName, 'pg soft') !== false) { return true; }
        if (strpos($providerSlug, 'pgsoft') !== false || strpos($providerSlug, 'pg-soft') !== false || $providerSlug === 'pg') { return true; }
        return false;
    }

    // Backward-compatible alias, in case an older patch calls the old function name.
    function game_api_is_first_provider_pgsoft($game) {
        return game_api_is_provider_pgsoft($game);
    }

    function game_api_is_provider_saba($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        if ($apiVendor === 'saba') { return true; }
        if ($apiProvider === 'saba' || $apiProvider === 'saba sports' || $apiProvider === 'sabasports') { return true; }
        if ($providerId === '46') { return true; }
        if (strpos($providerName, 'saba') !== false) { return true; }
        if (strpos($providerSlug, 'saba') !== false) { return true; }
        if (strpos($gameName, 'saba') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_unitedgaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        if ($apiVendor === 'unitedgaming' || $apiVendor === 'united_gaming') { return true; }
        if ($apiProvider === 'unitedgaming' || $apiProvider === 'united gaming' || $apiProvider === 'united') { return true; }
        if ($providerId === '48') { return true; }
        if (strpos($providerName, 'united') !== false) { return true; }
        if (strpos($providerSlug, 'united') !== false) { return true; }
        if (strpos($gameName, 'united gaming') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_jdb($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtoupper(trim((string)$game['api_vendor_code'])) : '';
        if ($apiVendor === 'JDB') { return true; }
        if ($apiProvider === 'jdb' || $apiProvider === 'jdbgaming' || $apiProvider === 'jdb gaming') { return true; }
        if ($providerId === '50') { return true; }
        if (strpos($providerName, 'jdb') !== false) { return true; }
        if (strpos($providerSlug, 'jdb') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_tadagaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        if ($apiVendor === 'tadagaming' || $apiVendor === 'tada') { return true; }
        if ($apiProvider === 'tadagaming' || $apiProvider === 'tada gaming' || $apiProvider === 'tada') { return true; }
        if ($providerId === '51') { return true; }
        if (strpos($providerName, 'tada') !== false) { return true; }
        if (strpos($providerSlug, 'tada') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_2j($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        if ($apiVendor === '2j' || $apiVendor === '2 j' || $apiVendor === 'twoj' || $apiVendor === 'two j') { return true; }
        if ($apiProvider === '2j' || $apiProvider === '2 j' || $apiProvider === 'twoj' || $apiProvider === 'two j') { return true; }
        if ($providerId === '105') { return true; }
        if (strpos($providerName, '2j') !== false || strpos($providerName, '2 j') !== false) { return true; }
        if (strpos($providerSlug, '2j') !== false || strpos($providerSlug, '2-j') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_cq9($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        if ($apiVendor === 'cq9' || $apiVendor === 'cq9gaming' || $apiVendor === 'cq9 gaming') { return true; }
        if ($apiProvider === 'cq9' || $apiProvider === 'cq9gaming' || $apiProvider === 'cq9 gaming') { return true; }
        if ($providerId === '52') { return true; }
        if (strpos($providerName, 'cq9') !== false) { return true; }
        if (strpos($providerSlug, 'cq9') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_100hp($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        if ($apiVendor === '100hp' || $apiVendor === '100 hp' || $apiVendor === 'hundredhp') { return true; }
        if ($apiProvider === '100hp' || $apiProvider === '100 hp' || $apiProvider === 'hundredhp') { return true; }
        if ($providerId === '131') { return true; }
        if (strpos($providerName, '100hp') !== false || strpos($providerName, '100 hp') !== false) { return true; }
        if (strpos($providerSlug, '100hp') !== false || strpos($providerSlug, '100-hp') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_bng3oks_row($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('bng3oks-row','bng3oks row','bng3oks','bng','3oaks','3oaks-bng','3oaks (bng)','boongo'), true)) { return true; }
        if (in_array($vendorNorm, array('bng3oksrow','bng3oks','bng','3oaks','3oaksbng','boongo'), true)) { return true; }
        if (in_array($apiProvider, array('bng3oks-row','bng3oks row','bng3oks','bng','3oaks','3oaks-bng','3oaks (bng)','boongo'), true)) { return true; }
        if (in_array($providerNorm, array('bng3oksrow','bng3oks','bng','3oaks','3oaksbng','boongo'), true)) { return true; }
        if ($providerId === '96') { return true; }
        if (strpos($providerName, 'bng') !== false || strpos($providerName, '3oaks') !== false || strpos($providerName, '3oks') !== false || strpos($providerName, 'boongo') !== false) { return true; }
        if (strpos($providerSlug, 'bng') !== false || strpos($providerSlug, '3oaks') !== false || strpos($providerSlug, '3oks') !== false || strpos($providerSlug, 'boongo') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_5ggaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('5ggaming','5g gaming','5g','kys','s5g','kys-h5','s5g-h5'), true)) { return true; }
        if (in_array($vendorNorm, array('5ggaming','5g','kys','s5g','kysh5','s5gh5'), true)) { return true; }
        if (in_array($apiProvider, array('5ggaming','5g gaming','5g','kys','s5g','kys-h5','s5g-h5'), true)) { return true; }
        if (in_array($providerNorm, array('5ggaming','5g','kys','s5g','kysh5','s5gh5'), true)) { return true; }
        if ($providerId === '103') { return true; }
        if (strpos($providerName, '5ggaming') !== false || strpos($providerName, '5g gaming') !== false || strpos($providerName, '5g') !== false) { return true; }
        if (strpos($providerSlug, '5ggaming') !== false || strpos($providerSlug, '5g-gaming') !== false || strpos($providerSlug, '5g') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_9wicket($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('9w','9wicket','9wickets','ninewicket','ninewickets'), true)) { return true; }
        if (in_array($vendorNorm, array('9w','9wicket','9wickets','ninewicket','ninewickets'), true)) { return true; }
        if (in_array($apiProvider, array('9w','9wicket','9wickets','ninewicket','ninewickets'), true)) { return true; }
        if (in_array($providerNorm, array('9w','9wicket','9wickets','ninewicket','ninewickets'), true)) { return true; }
        if ($providerId === '141') { return true; }
        if (strpos($providerName, '9wicket') !== false || strpos($providerName, '9wickets') !== false || strpos($providerName, '9w') !== false) { return true; }
        if (strpos($providerSlug, '9wicket') !== false || strpos($providerSlug, '9wickets') !== false || strpos($providerSlug, '9w') !== false) { return true; }
        if (strpos($gameName, '9wicket') !== false || strpos($gameName, '9wickets') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_amigo($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('amigo','amigogaming','amigo gaming','amigo-games','amigo games'), true)) { return true; }
        if (in_array($vendorNorm, array('amigo','amigogaming','amigogames'), true)) { return true; }
        if (in_array($apiProvider, array('amigo','amigogaming','amigo gaming','amigo-games','amigo games'), true)) { return true; }
        if (in_array($providerNorm, array('amigo','amigogaming','amigogames'), true)) { return true; }
        if ($providerId === '137') { return true; }
        if (strpos($providerName, 'amigo') !== false) { return true; }
        if (strpos($providerSlug, 'amigo') !== false) { return true; }
        return false;
    }



    function game_api_is_provider_aog($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('aog','aogaming','aog gaming','aog-games','aog games'), true)) { return true; }
        if (in_array($vendorNorm, array('aog','aogaming','aoggames'), true)) { return true; }
        if (in_array($apiProvider, array('aog','aogaming','aog gaming','aog-games','aog games'), true)) { return true; }
        if (in_array($providerNorm, array('aog','aogaming','aoggames'), true)) { return true; }
        if ($providerId === '122') { return true; }
        if (strpos($providerName, 'aog') !== false) { return true; }
        if (strpos($providerSlug, 'aog') !== false) { return true; }
        if (in_array($gameName, array('wcc','wgc','wgb'), true)) { return true; }
        return false;
    }



    function game_api_is_provider_astar($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('astar','astargaming','astar gaming','astar-games','astar games'), true)) { return true; }
        if (in_array($vendorNorm, array('astar','astargaming','astargames'), true)) { return true; }
        if (in_array($apiProvider, array('astar','astargaming','astar gaming','astar-games','astar games'), true)) { return true; }
        if (in_array($providerNorm, array('astar','astargaming','astargames'), true)) { return true; }
        if ($providerId === '82') { return true; }
        if (strpos($providerName, 'astar') !== false) { return true; }
        if (strpos($providerSlug, 'astar') !== false) { return true; }
        if ($gameName === 'astargaming' || strpos($gameName, 'astar') !== false) { return true; }
        return false;
    }



    function game_api_is_provider_bgaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('bgaming','b gaming','b-gaming','bgaminggames','bgaming games'), true)) { return true; }
        if (in_array($vendorNorm, array('bgaming','bgaminggames'), true)) { return true; }
        if (in_array($apiProvider, array('bgaming','b gaming','b-gaming','bgaminggames','bgaming games'), true)) { return true; }
        if (in_array($providerNorm, array('bgaming','bgaminggames'), true)) { return true; }
        if ($providerId === '65') { return true; }
        if (strpos($providerName, 'bgaming') !== false || strpos($providerName, 'b gaming') !== false || strpos($providerName, 'b-gaming') !== false) { return true; }
        if (strpos($providerSlug, 'bgaming') !== false || strpos($providerSlug, 'b-gaming') !== false) { return true; }
        return false;
    }



    function game_api_is_provider_bigtimegaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('bigtimegaming','big time gaming','big-time-gaming','btg','bigtime'), true)) { return true; }
        if (in_array($vendorNorm, array('bigtimegaming','btg','bigtime'), true)) { return true; }
        if (in_array($apiProvider, array('bigtimegaming','big time gaming','big-time-gaming','btg','bigtime'), true)) { return true; }
        if (in_array($providerNorm, array('bigtimegaming','btg','bigtime'), true)) { return true; }
        if ($providerId === '62' || $providerId === '63') { return true; }
        if (strpos($providerName, 'bigtimegaming') !== false || strpos($providerName, 'big time gaming') !== false || strpos($providerName, 'big-time-gaming') !== false) { return true; }
        if (strpos($providerSlug, 'bigtimegaming') !== false || strpos($providerSlug, 'big-time-gaming') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_btgaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('btgaming','bt gaming','bt-gaming','betgaming','btgame'), true)) { return true; }
        if (in_array($vendorNorm, array('btgaming','betgaming','btgame'), true)) { return true; }
        if (in_array($apiProvider, array('btgaming','bt gaming','bt-gaming','betgaming','btgame'), true)) { return true; }
        if (in_array($providerNorm, array('btgaming','betgaming','btgame'), true)) { return true; }
        if ($providerId === '109') { return true; }
        if (strpos($providerName, 'btgaming') !== false || strpos($providerName, 'bt gaming') !== false || strpos($providerName, 'bt-gaming') !== false) { return true; }
        if (strpos($providerSlug, 'btgaming') !== false || strpos($providerSlug, 'bt-gaming') !== false) { return true; }
        return false;
    }



    function game_api_is_provider_evolutionlive($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('evolutionlive','evolution live','evolution-live','evolutionliveasia','evolution-live-asia','evolution live asia'), true)) { return true; }
        if (in_array($vendorNorm, array('evolutionlive','evolutionliveasia'), true)) { return true; }
        if (in_array($providerNorm, array('evolutionlive','evolutionliveasia'), true)) { return true; }
        if ($providerId === '58' || $providerId === '59') { return true; }
        if (strpos($providerName, 'evolution live') !== false || strpos($providerName, 'evolution-live') !== false) { return true; }
        if (strpos($providerSlug, 'evolution-live') !== false || strpos($providerSlug, 'evolutionlive') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_fachaigaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('fachaigaming','fa chai gaming','fa-chai-gaming','fachai','fa chai','fa-chai','facai','facai gaming','fcgaming'), true)) { return true; }
        if (in_array($vendorNorm, array('fachaigaming','fachai','facai','facaigaming','fcgaming'), true)) { return true; }
        if (in_array($apiProvider, array('fachaigaming','fa chai gaming','fa-chai-gaming','fachai','fa chai','fa-chai','facai','facai gaming','fcgaming'), true)) { return true; }
        if (in_array($providerNorm, array('fachaigaming','fachai','facai','facaigaming','fcgaming'), true)) { return true; }
        if ($providerId === '61') { return true; }
        if (strpos($providerName, 'fachaigaming') !== false || strpos($providerName, 'fa chai') !== false || strpos($providerName, 'fa-chai') !== false || strpos($providerName, 'fachai') !== false || strpos($providerName, 'facai') !== false) { return true; }
        if (strpos($providerSlug, 'fachaigaming') !== false || strpos($providerSlug, 'fa-chai') !== false || strpos($providerSlug, 'fachai') !== false || strpos($providerSlug, 'facai') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_fastspin($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('fastspin','fast spin','fast-spin','fastspingaming','fastspin gaming'), true)) { return true; }
        if (in_array($vendorNorm, array('fastspin','fastspingaming'), true)) { return true; }
        if (in_array($apiProvider, array('fastspin','fast spin','fast-spin','fastspingaming','fastspin gaming'), true)) { return true; }
        if (in_array($providerNorm, array('fastspin','fastspingaming'), true)) { return true; }
        if ($providerId === '125') { return true; }
        if (strpos($providerName, 'fastspin') !== false || strpos($providerName, 'fast spin') !== false || strpos($providerName, 'fast-spin') !== false) { return true; }
        if (strpos($providerSlug, 'fastspin') !== false || strpos($providerSlug, 'fast-spin') !== false || strpos($providerSlug, 'fast_spin') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_gameart($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('gameart','game art','game-art','gameartgaming','gameart gaming'), true)) { return true; }
        if (in_array($vendorNorm, array('gameart','gameartgaming'), true)) { return true; }
        if (in_array($apiProvider, array('gameart','game art','game-art','gameartgaming','gameart gaming'), true)) { return true; }
        if (in_array($providerNorm, array('gameart','gameartgaming'), true)) { return true; }
        if ($providerId === '64') { return true; }
        if (strpos($providerName, 'gameart') !== false || strpos($providerName, 'game art') !== false || strpos($providerName, 'game-art') !== false) { return true; }
        if (strpos($providerSlug, 'gameart') !== false || strpos($providerSlug, 'game-art') !== false || strpos($providerSlug, 'game_art') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_ideal($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('ideal','ideal gaming','ideal-gaming','idealgaming','ideal slot','idealslot'), true)) { return true; }
        if (in_array($vendorNorm, array('ideal','idealgaming','idealslot'), true)) { return true; }
        if (in_array($apiProvider, array('ideal','ideal gaming','ideal-gaming','idealgaming','ideal slot','idealslot'), true)) { return true; }
        if (in_array($providerNorm, array('ideal','idealgaming','idealslot'), true)) { return true; }
        if ($providerId === '79') { return true; }
        if (strpos($providerName, 'ideal') !== false) { return true; }
        if (strpos($providerSlug, 'ideal') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_inout($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        if (in_array($apiVendor, array('inout','in out','in-out','inoutgaming','inout gaming','inoutgames','inout games'), true)) { return true; }
        if (in_array($vendorNorm, array('inout','inoutgaming','inoutgames'), true)) { return true; }
        if (in_array($apiProvider, array('inout','in out','in-out','inoutgaming','inout gaming','inoutgames','inout games'), true)) { return true; }
        if (in_array($providerNorm, array('inout','inoutgaming','inoutgames'), true)) { return true; }
        if ($providerId === '112') { return true; }
        if (strpos($providerName, 'inout') !== false || strpos($providerName, 'in out') !== false || strpos($providerName, 'in-out') !== false) { return true; }
        if (strpos($providerSlug, 'inout') !== false || strpos($providerSlug, 'in-out') !== false || strpos($providerSlug, 'in_out') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_lucksportgaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $nameNorm = preg_replace('/[^a-z0-9]+/', '', $gameName);
        if (in_array($apiVendor, array('lucksportgaming','luck sport gaming','luck-sport-gaming','luckysport','lucky sport','lucky-sport','luck sport'), true)) { return true; }
        if (in_array($vendorNorm, array('lucksportgaming','luckysportgaming','luckysport','lucksport'), true)) { return true; }
        if (in_array($apiProvider, array('lucksportgaming','luck sport gaming','luck-sport-gaming','luckysport','lucky sport','lucky-sport','luck sport'), true)) { return true; }
        if (in_array($providerNorm, array('lucksportgaming','luckysportgaming','luckysport','lucksport'), true)) { return true; }
        if ($providerId === '83') { return true; }
        if (strpos($providerName, 'lucksport') !== false || strpos($providerName, 'luck sport') !== false || strpos($providerName, 'lucky sport') !== false || strpos($providerName, 'luckysport') !== false) { return true; }
        if (strpos($providerSlug, 'lucksport') !== false || strpos($providerSlug, 'luck-sport') !== false || strpos($providerSlug, 'lucky-sport') !== false || strpos($providerSlug, 'luckysport') !== false) { return true; }
        if (in_array($nameNorm, array('lucksportgaming','luckysportgaming','luckysport'), true)) { return true; }
        return false;
    }


    function game_api_is_provider_microgaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $apiGameCode = isset($game['api_game_code']) ? trim((string)$game['api_game_code']) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $codeNorm = preg_replace('/[^a-z0-9_]+/', '', strtolower($apiGameCode));
        if (in_array($apiVendor, array('microgaming','micro gaming','micro-gaming','mg','mgplus','mglive','mglivegrand'), true)) { return true; }
        if (in_array($vendorNorm, array('microgaming','mg','mgplus','mglive','mglivegrand'), true)) { return true; }
        if (in_array($apiProvider, array('microgaming','micro gaming','micro-gaming','mg','mglive','mglivegrand'), true)) { return true; }
        if (in_array($providerNorm, array('microgaming','mg','mglive','mglivegrand'), true)) { return true; }
        if ($providerId === '90') { return true; }
        if (strpos($providerName, 'microgaming') !== false || strpos($providerName, 'micro gaming') !== false || strpos($providerName, 'micro-gaming') !== false) { return true; }
        if (strpos($providerSlug, 'microgaming') !== false || strpos($providerSlug, 'micro-gaming') !== false || strpos($providerSlug, 'micro_gaming') !== false) { return true; }
        if (stripos($apiGameCode, 'SMG_') === 0 || stripos($apiGameCode, 'P1_') === 0 || stripos($apiGameCode, 'P2_') === 0 || stripos($apiGameCode, 'P5_') === 0 || stripos($apiGameCode, 'WP ') === 0 || stripos($apiGameCode, 'WD ') === 0) { return true; }
        if (strpos($codeNorm, 'smg_') === 0 || strpos($codeNorm, 'p1_') === 0 || strpos($codeNorm, 'p2_') === 0 || strpos($codeNorm, 'p5_') === 0) { return true; }
        return false;
    }


    function game_api_is_provider_ongaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $providerNameNorm = preg_replace('/[^a-z0-9]+/', '', $providerName);
        $providerSlugNorm = preg_replace('/[^a-z0-9]+/', '', $providerSlug);
        if (in_array($apiVendor, array('ongaming','on gaming','on-gaming','on gaming casino','ongaming casino'), true)) { return true; }
        if (in_array($vendorNorm, array('ongaming','ongamingcasino'), true)) { return true; }
        if (in_array($apiProvider, array('ongaming','on gaming','on-gaming','on gaming casino','ongaming casino'), true)) { return true; }
        if (in_array($providerNorm, array('ongaming','ongamingcasino'), true)) { return true; }
        if (in_array($providerId, array('121','102'), true)) { return true; }
        if ($providerNameNorm === 'ongaming' || $providerNameNorm === 'ongamingcasino') { return true; }
        if ($providerSlugNorm === 'ongaming' || $providerSlugNorm === 'ongamingcasino') { return true; }
        if (strpos($providerName, 'on gaming') !== false || strpos($providerName, 'on-gaming') !== false) { return true; }
        if (strpos($providerSlug, 'on-gaming') !== false || strpos($providerSlug, 'on_gaming') !== false) { return true; }
        return false;
    }


    function game_api_is_provider_netent($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $providerNameNorm = preg_replace('/[^a-z0-9]+/', '', $providerName);
        $providerSlugNorm = preg_replace('/[^a-z0-9]+/', '', $providerSlug);
        if (in_array($apiVendor, array('netent','net ent','net-ent','evolution-netent','evolution netent','evolutioin-netent','evolutioin netent'), true)) { return true; }
        if (in_array($vendorNorm, array('netent','evolutionnetent','evolutioinnetent'), true)) { return true; }
        if (in_array($apiProvider, array('netent','net ent','net-ent','evolution-netent','evolution netent','evolutioin-netent','evolutioin netent'), true)) { return true; }
        if (in_array($providerNorm, array('netent','evolutionnetent','evolutioinnetent'), true)) { return true; }
        if ($providerId === '68') { return true; }
        if (strpos($providerName, 'netent') !== false || strpos($providerName, 'net ent') !== false || strpos($providerName, 'evolution-netent') !== false || strpos($providerName, 'evolutioin-netent') !== false) { return true; }
        if (strpos($providerSlug, 'netent') !== false || strpos($providerSlug, 'net-ent') !== false || strpos($providerSlug, 'net_ent') !== false || strpos($providerSlug, 'evolution-netent') !== false) { return true; }
        if ($providerNameNorm === 'netent' || $providerNameNorm === 'evolutionnetent' || $providerNameNorm === 'evolutioinnetent') { return true; }
        if ($providerSlugNorm === 'netent' || $providerSlugNorm === 'evolutionnetent' || $providerSlugNorm === 'evolutioinnetent') { return true; }
        return false;
    }


    function game_api_is_provider_mini($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $providerNameNorm = preg_replace('/[^a-z0-9]+/', '', $providerName);
        $providerSlugNorm = preg_replace('/[^a-z0-9]+/', '', $providerSlug);
        if (in_array($apiVendor, array('mini','mini games','mini-games','minigames','mini gaming','mini-gaming'), true)) { return true; }
        if (in_array($vendorNorm, array('mini','minigames','minigaming'), true)) { return true; }
        if (in_array($apiProvider, array('mini','mini games','mini-games','minigames','mini gaming','mini-gaming'), true)) { return true; }
        if (in_array($providerNorm, array('mini','minigames','minigaming'), true)) { return true; }
        if ($providerId === '104') { return true; }
        if ($providerNameNorm === 'mini' || $providerNameNorm === 'minigames' || $providerNameNorm === 'minigaming') { return true; }
        if ($providerSlugNorm === 'mini' || $providerSlugNorm === 'minigames' || $providerSlugNorm === 'minigaming') { return true; }
        return false;
    }


    function game_api_is_provider_sexy($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $providerNameNorm = preg_replace('/[^a-z0-9]+/', '', $providerName);
        $providerSlugNorm = preg_replace('/[^a-z0-9]+/', '', $providerSlug);
        $nameNorm = preg_replace('/[^a-z0-9]+/', '', $gameName);
        if (in_array($apiVendor, array('sexy','sexy_video','sexy video','sexy-video','sexygaming','sexy gaming','sexy-gaming'), true)) { return true; }
        if (in_array($vendorNorm, array('sexy','sexyvideo','sexygaming'), true)) { return true; }
        if (in_array($apiProvider, array('sexy','sexy_video','sexy video','sexy-video','sexygaming','sexy gaming','sexy-gaming'), true)) { return true; }
        if (in_array($providerNorm, array('sexy','sexyvideo','sexygaming'), true)) { return true; }
        if ($providerId === '88') { return true; }
        if ($providerId === '140' && strpos($nameNorm, 'sexy') === 0) { return true; }
        if ($providerNameNorm === 'sexy' || $providerNameNorm === 'sexygaming' || $providerSlugNorm === 'sexy' || $providerSlugNorm === 'sexygaming') { return true; }
        if (strpos($providerNameNorm, 'sexy') !== false || strpos($providerSlugNorm, 'sexy') !== false) { return true; }
        if (strpos($nameNorm, 'sexy') === 0 && in_array($providerId, array('88','140'), true)) { return true; }
        return false;
    }

    function game_api_sexy_vendor_for_game($game) {
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        if ($vendorNorm === 'sexyvideo') { return 'sexy_video'; }
        if ($vendorNorm === 'sexy') { return 'sexy'; }
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $name = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $nameNorm = preg_replace('/[^a-z0-9]+/', '', $name);
        if ($providerId === '140' || strpos($nameNorm, 'sexy') === 0) { return 'sexy_video'; }
        return 'sexy';
    }


    function game_api_is_provider_sbo($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $providerNameNorm = preg_replace('/[^a-z0-9]+/', '', $providerName);
        $providerSlugNorm = preg_replace('/[^a-z0-9]+/', '', $providerSlug);
        $nameNorm = preg_replace('/[^a-z0-9]+/', '', $gameName);
        if (in_array($apiVendor, array('sbo','sbo sportsbook','sbo-sportsbook','sbovirtualsport','sbovirtualsports','sbo virtualsport','sbo virtualsports','sbo-virtualsport','sbo-virtualsports','sportsbook'), true)) { return true; }
        if (in_array($vendorNorm, array('sbo','sbosportsbook','sbovirtualsport','sbovirtualsports','sportsbook'), true)) { return true; }
        if (in_array($apiProvider, array('sbo','sbo sportsbook','sbo-sportsbook','sbovirtualsport','sbovirtualsports','sbo virtualsports','sportsbook'), true)) { return true; }
        if (in_array($providerNorm, array('sbo','sbosportsbook','sbovirtualsport','sbovirtualsports','sportsbook'), true)) { return true; }
        if ($providerId === '126') { return true; }
        if ($providerNameNorm === 'sbo' || $providerSlugNorm === 'sbo' || $providerNameNorm === 'sbosportsbook' || $providerSlugNorm === 'sbosportsbook') { return true; }
        if (strpos($providerNameNorm, 'sbovirtual') !== false || strpos($providerSlugNorm, 'sbovirtual') !== false || strpos($providerNameNorm, 'sportsbook') !== false || strpos($providerSlugNorm, 'sportsbook') !== false) { return true; }
        if (in_array($nameNorm, array('sbosportsbook','sbovirtualsportsvs','568winsportsbook'), true)) { return true; }
        return false;
    }

    function game_api_sbo_vendor_for_game($game) {
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        if (in_array($vendorNorm, array('sbovirtualsport','sbovirtualsports'), true)) { return 'sbovirtualsport'; }
        if ($vendorNorm === 'sportsbook') { return 'sportsbook'; }
        if ($vendorNorm === 'sbo') { return 'sbo'; }
        $name = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $provider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $nameNorm = preg_replace('/[^a-z0-9]+/', '', $name);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $provider);
        if (strpos($nameNorm, 'virtualsports') !== false || strpos($providerNorm, 'virtualsports') !== false || strpos($providerNorm, 'sbovirtual') !== false) { return 'sbovirtualsport'; }
        if (strpos($nameNorm, '568win') !== false || $providerNorm === 'sportsbook') { return 'sportsbook'; }
        return 'sbo';
    }



    function game_api_is_provider_dpsports($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $providerNameNorm = preg_replace('/[^a-z0-9]+/', '', $providerName);
        $providerSlugNorm = preg_replace('/[^a-z0-9]+/', '', $providerSlug);
        $nameNorm = preg_replace('/[^a-z0-9]+/', '', $gameName);
        if (in_array($providerId, array('94','95'), true)) { return true; }
        if (in_array($apiVendor, array('dpesports','dp esports','dp-esports','dpsports','dp sports','dp-sports'), true)) { return true; }
        if (in_array($vendorNorm, array('dpesports','dpsports'), true)) { return true; }
        if (in_array($apiProvider, array('dpesports','dp esports','dp-esports','dpsports','dp sports','dp-sports'), true)) { return true; }
        if (in_array($providerNorm, array('dpesports','dpsports'), true)) { return true; }
        if (in_array($providerNameNorm, array('dpesports','dpsports'), true) || in_array($providerSlugNorm, array('dpesports','dpsports'), true)) { return true; }
        if (in_array($nameNorm, array('dpesportsgaming','dpsportsgaming'), true)) { return true; }
        return false;
    }

    function game_api_dpsports_vendor_for_game($game) {
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        if ($vendorNorm === 'dpesports') { return 'dpesports'; }
        if ($vendorNorm === 'dpsports') { return 'dpsports'; }
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        if ($providerId === '94') { return 'dpesports'; }
        if ($providerId === '95') { return 'dpsports'; }
        $name = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $provider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $allNorm = preg_replace('/[^a-z0-9]+/', '', $name . ' ' . $provider . ' ' . $providerName . ' ' . $providerSlug);
        if (strpos($allNorm, 'dpesports') !== false || strpos($allNorm, 'esports') !== false) { return 'dpesports'; }
        return 'dpsports';
    }

    function game_api_is_provider_sagaming($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $gameName = isset($game['name']) ? strtolower(trim((string)$game['name'])) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $providerNameNorm = preg_replace('/[^a-z0-9]+/', '', $providerName);
        $providerSlugNorm = preg_replace('/[^a-z0-9]+/', '', $providerSlug);
        $nameNorm = preg_replace('/[^a-z0-9]+/', '', $gameName);
        if (in_array($apiVendor, array('sagaming','sa gaming','sa-gaming','sa casino','sa live'), true)) { return true; }
        if (in_array($vendorNorm, array('sagaming','sagame','sacasino','salive'), true)) { return true; }
        if (in_array($apiProvider, array('sagaming','sa gaming','sa-gaming','sa casino','sa live'), true)) { return true; }
        if (in_array($providerNorm, array('sagaming','sagame','sacasino','salive'), true)) { return true; }
        if ($providerId === '89') { return true; }
        if ($providerNameNorm === 'sagaming' || $providerSlugNorm === 'sagaming' || $providerNameNorm === 'sagame' || $providerSlugNorm === 'sagame') { return true; }
        if (strpos($providerName, 'sa gaming') !== false || strpos($providerName, 'sa-gaming') !== false || strpos($providerName, 'sagaming') !== false) { return true; }
        if (strpos($providerSlug, 'sa-gaming') !== false || strpos($providerSlug, 'sa_gaming') !== false || strpos($providerSlug, 'sagaming') !== false) { return true; }
        if ($nameNorm === 'sagaming') { return true; }
        return false;
    }


    function game_api_is_provider_pragmatic($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $apiGameCode = isset($game['api_game_code']) ? trim((string)$game['api_game_code']) : '';
        $vendorNorm = preg_replace('/[^a-z0-9]+/', '', $apiVendor);
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider);
        $providerNameNorm = preg_replace('/[^a-z0-9]+/', '', $providerName);
        $providerSlugNorm = preg_replace('/[^a-z0-9]+/', '', $providerSlug);
        if (in_array($apiVendor, array('pragmatic','pragmaticplay','pragmatic-play','pragmatic play','pragmaticlive','pragmatic live','pragmaticplaylive','pragmatic play live'), true)) { return true; }
        if (in_array($vendorNorm, array('pragmatic','pragmaticplay','pragmaticlive','pragmaticplaylive','pp','pplive'), true)) { return true; }
        if (in_array($apiProvider, array('pragmatic','pragmaticplay','pragmatic-play','pragmatic play','pragmaticlive','pragmatic live','pragmaticplaylive','pragmatic play live'), true)) { return true; }
        if (in_array($providerNorm, array('pragmatic','pragmaticplay','pragmaticlive','pragmaticplaylive'), true)) { return true; }
        if (in_array($providerId, array('53','54','55','56'), true)) { return true; }
        if (strpos($providerNameNorm, 'pragmatic') !== false || strpos($providerSlugNorm, 'pragmatic') !== false) { return true; }
        if (stripos($apiGameCode, 'vs') === 0 || stripos($apiGameCode, 'cs') === 0 || stripos($apiGameCode, 'bn') === 0) {
            if (strpos($providerNameNorm, 'pragmatic') !== false || strpos($providerSlugNorm, 'pragmatic') !== false || in_array($providerId, array('53','54','55','56'), true)) { return true; }
        }
        return false;
    }




    function game_api_is_provider_t1($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider . ' ' . $providerName . ' ' . $providerSlug . ' ' . $apiVendor);
        if ($apiVendor === 't1' || $apiProvider === 't1') { return true; }
        if ($providerId === '80') { return true; }
        if ($providerName === 't1' || $providerSlug === 't1') { return true; }
        if (strpos($providerNorm, 't1gaming') !== false || strpos($providerNorm, 't1games') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_spribe($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider . ' ' . $providerName . ' ' . $providerSlug . ' ' . $apiVendor);
        if ($apiVendor === 'spribe' || $apiProvider === 'spribe') { return true; }
        if ($providerId === '57') { return true; }
        if (strpos($providerNorm, 'spribe') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_tf($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider . ' ' . $providerName . ' ' . $providerSlug . ' ' . $apiVendor);
        if ($apiVendor === 'tf' || $apiProvider === 'tf') { return true; }
        if ($providerId === '85') { return true; }
        if ($providerName === 'tf' || $providerSlug === 'tf') { return true; }
        if ($providerId === '85' && strpos($providerNorm, 'tfgaming') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_tfg($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider . ' ' . $providerName . ' ' . $providerSlug . ' ' . $apiVendor);
        if ($apiVendor === 'tfg' || $apiProvider === 'tfg') { return true; }
        if ($providerId === '86') { return true; }
        if ($providerName === 'tfg' || $providerSlug === 'tfg') { return true; }
        if ($providerId === '86' && strpos($providerNorm, 'tfgaming') !== false) { return true; }
        return false;
    }

    function game_api_is_provider_yeebet($game) {
        if (!is_array($game)) { return false; }
        $apiProvider = isset($game['api_provider_name']) ? strtolower(trim((string)$game['api_provider_name'])) : '';
        $providerId = isset($game['provider_id']) ? trim((string)$game['provider_id']) : '';
        $providerName = isset($game['provider_name']) ? strtolower(trim((string)$game['provider_name'])) : '';
        $providerSlug = isset($game['provider_slug']) ? strtolower(trim((string)$game['provider_slug'])) : '';
        $apiVendor = isset($game['api_vendor_code']) ? strtolower(trim((string)$game['api_vendor_code'])) : '';
        $providerNorm = preg_replace('/[^a-z0-9]+/', '', $apiProvider . ' ' . $providerName . ' ' . $providerSlug . ' ' . $apiVendor);
        if ($apiVendor === 'yeebet' || $apiVendor === 'yee') { return true; }
        if ($apiProvider === 'yeebet' || $apiProvider === 'yee bet') { return true; }
        if ($providerId === '60') { return true; }
        if (strpos($providerNorm, 'yeebet') !== false || strpos($providerNorm, 'yeebe') !== false) { return true; }
        return false;
    }

    function game_api_is_supported_launch_provider($game) {
        return game_api_is_first_provider_jili($game) || game_api_is_provider_pgsoft($game) || game_api_is_provider_saba($game) || game_api_is_provider_unitedgaming($game) || game_api_is_provider_jdb($game) || game_api_is_provider_tadagaming($game) || game_api_is_provider_2j($game) || game_api_is_provider_cq9($game) || game_api_is_provider_100hp($game) || game_api_is_provider_bng3oks_row($game) || game_api_is_provider_5ggaming($game) || game_api_is_provider_9wicket($game) || game_api_is_provider_amigo($game) || game_api_is_provider_aog($game) || game_api_is_provider_astar($game) || game_api_is_provider_bgaming($game) || game_api_is_provider_bigtimegaming($game) || game_api_is_provider_btgaming($game) || game_api_is_provider_evolutionlive($game) || game_api_is_provider_fachaigaming($game) || game_api_is_provider_fastspin($game) || game_api_is_provider_gameart($game) || game_api_is_provider_ideal($game) || game_api_is_provider_inout($game) || game_api_is_provider_lucksportgaming($game) || game_api_is_provider_microgaming($game) || game_api_is_provider_mini($game) || game_api_is_provider_netent($game) || game_api_is_provider_ongaming($game) || game_api_is_provider_pragmatic($game) || game_api_is_provider_sagaming($game) || game_api_is_provider_sbo($game) || game_api_is_provider_dpsports($game) || game_api_is_provider_sexy($game) || game_api_is_provider_t1($game) || game_api_is_provider_spribe($game) || game_api_is_provider_tf($game) || game_api_is_provider_tfg($game) || game_api_is_provider_yeebet($game);
    }

    function game_api_provider_vendor_code($game) {
        if (game_api_is_provider_yeebet($game)) { return 'yeebet'; }
        if (game_api_is_provider_tfg($game)) { return 'tfg'; }
        if (game_api_is_provider_tf($game)) { return 'tf'; }
        if (game_api_is_provider_spribe($game)) { return 'spribe'; }
        if (game_api_is_provider_t1($game)) { return 't1'; }
        if (game_api_is_provider_sexy($game)) { return game_api_sexy_vendor_for_game($game); }
        if (game_api_is_provider_sbo($game)) { return game_api_sbo_vendor_for_game($game); }
        if (game_api_is_provider_dpsports($game)) { return game_api_dpsports_vendor_for_game($game); }
        if (game_api_is_provider_sagaming($game)) { return 'sagaming'; }
        if (game_api_is_provider_pragmatic($game)) { return 'pragmatic'; }
        if (game_api_is_provider_ongaming($game)) { return 'ongaming'; }
        if (game_api_is_provider_netent($game)) { return 'netent'; }
        if (game_api_is_provider_mini($game)) { return 'mini'; }
        if (game_api_is_provider_microgaming($game)) { return 'microgaming'; }
        if (game_api_is_provider_lucksportgaming($game)) { return 'lucksportgaming'; }
        if (game_api_is_provider_inout($game)) { return 'inout'; }
        if (game_api_is_provider_ideal($game)) { return 'ideal'; }
        if (game_api_is_provider_gameart($game)) { return 'gameart'; }
        if (game_api_is_provider_fastspin($game)) { return 'fastspin'; }
        if (game_api_is_provider_fachaigaming($game)) { return 'fachaigaming'; }
        if (game_api_is_provider_evolutionlive($game)) { return 'evolutionlive'; }
        if (game_api_is_provider_btgaming($game)) { return 'btgaming'; }
        if (game_api_is_provider_bigtimegaming($game)) { return 'bigtimegaming'; }
        if (game_api_is_provider_bgaming($game)) { return 'bgaming'; }
        if (game_api_is_provider_astar($game)) { return 'astar'; }
        if (game_api_is_provider_aog($game)) { return 'aog'; }
        if (game_api_is_provider_amigo($game)) { return 'amigo'; }
        if (game_api_is_provider_9wicket($game)) { return '9wicket'; }
        if (game_api_is_provider_5ggaming($game)) { return '5ggaming'; }
        if (game_api_is_provider_bng3oks_row($game)) { return 'bng3oks-row'; }
        if (game_api_is_provider_2j($game)) { return '2j'; }
        if (game_api_is_provider_100hp($game)) { return '100hp'; }
        if (game_api_is_provider_cq9($game)) { return 'cq9'; }
        if (game_api_is_provider_tadagaming($game)) { return 'tadagaming'; }
        if (game_api_is_provider_jdb($game)) { return 'JDB'; }
        if (game_api_is_provider_unitedgaming($game)) { return 'unitedgaming'; }
        if (game_api_is_provider_saba($game)) { return 'saba'; }
        if (game_api_is_provider_pgsoft($game)) { return 'pgsoft'; }
        if (game_api_is_first_provider_jili($game)) { return 'JILI'; }
        $vendor = isset($game['api_vendor_code']) ? trim((string)$game['api_vendor_code']) : '';
        return $vendor !== '' ? $vendor : '';
    }


    function game_api_normalize_player_id($conn, $rawPlayerId) {
        $raw = trim((string)$rawPlayerId);
        if ($raw === '') { return 0; }
        if (ctype_digit($raw)) { return (int)$raw; }

        $agent = '';
        if ($conn) { $agent = game_api_clean_no_space(game_api_get_setting($conn, 'agent_code', '')); }
        if ($agent !== '') {
            $patterns = array($agent . '_', $agent . '-', $agent);
            foreach ($patterns as $prefix) {
                if (stripos($raw, $prefix) === 0) {
                    $raw = substr($raw, strlen($prefix));
                    break;
                }
            }
        }

        if (preg_match('/(\d+)$/', $raw, $m)) { return (int)$m[1]; }
        if (preg_match('/(\d+)/', $raw, $m)) { return (int)$m[1]; }
        return 0;
    }

    function game_api_extract_game_url($result) {
        if (!is_array($result)) { return ''; }
        $keys = array('gameUrl','game_url','GameUrl','GameURL','url','URL','launch_url','launchUrl','gameLink','GameLink');
        foreach ($keys as $key) {
            if (!empty($result[$key]) && is_string($result[$key])) { return trim($result[$key]); }
        }
        if (isset($result['data'])) {
            if (is_string($result['data']) && preg_match('/^https?:\/\//i', $result['data'])) { return trim($result['data']); }
            if (is_array($result['data'])) {
                foreach ($keys as $key) {
                    if (!empty($result['data'][$key]) && is_string($result['data'][$key])) { return trim($result['data'][$key]); }
                }
            }
        }
        return '';
    }

    function game_api_response_message($result, $fallback = 'Provider did not return a game URL.') {
        if (is_array($result)) {
            foreach (array('msg','message','error','error_message','Message') as $key) {
                if (isset($result[$key]) && $result[$key] !== '') { return (string)$result[$key]; }
            }
            if (isset($result['data']) && is_array($result['data'])) {
                foreach (array('msg','message','error','error_message','Message') as $key) {
                    if (isset($result['data'][$key]) && $result['data'][$key] !== '') { return (string)$result['data'][$key]; }
                }
            }
        }
        return $fallback;
    }

    function game_api_table_exists($conn, $table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $res = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        return ($res && $res->num_rows > 0);
    }

    function game_api_column_exists($conn, $table, $column) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $columnEsc = $conn->real_escape_string($column);
        $res = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '$columnEsc'");
        return ($res && $res->num_rows > 0);
    }

    function game_api_ensure_column($conn, $table, $column, $definition) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if (!game_api_column_exists($conn, $table, $column)) {
            @$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    function game_api_ensure_index($conn, $table, $index, $definition) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $indexEsc = $conn->real_escape_string($index);
        $res = @$conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexEsc'");
        if (!$res || $res->num_rows === 0) {
            @$conn->query("ALTER TABLE `$table` ADD $definition");
        }
    }

    function game_api_ensure_setting($conn, $key, $defaultValue) {
        $keyEsc = $conn->real_escape_string($key);
        $res = @$conn->query("SELECT id FROM game_settings WHERE setting_key='$keyEsc' LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            $valEsc = $conn->real_escape_string($defaultValue);
            @$conn->query("INSERT INTO game_settings (setting_key, setting_value) VALUES ('$keyEsc', '$valEsc')");
        }
    }

    function game_api_set_setting($conn, $key, $value) {
        global $GAME_API_SETTINGS_CACHE;
        $keyEsc = $conn->real_escape_string($key);
        $valEsc = $conn->real_escape_string($value);
        $res = @$conn->query("SELECT id FROM game_settings WHERE setting_key='$keyEsc' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            @$conn->query("UPDATE game_settings SET setting_value='$valEsc' WHERE setting_key='$keyEsc'");
        } else {
            @$conn->query("INSERT INTO game_settings (setting_key, setting_value) VALUES ('$keyEsc', '$valEsc')");
        }
        $GAME_API_SETTINGS_CACHE = null;
    }

    function game_api_get_settings($conn, $forceRefresh = false) {
        global $GAME_API_SETTINGS_CACHE;
        if (!$forceRefresh && is_array($GAME_API_SETTINGS_CACHE)) {
            return $GAME_API_SETTINGS_CACHE;
        }
        game_api_ensure_schema($conn, false);
        $settings = array();
        $res = @$conn->query("SELECT setting_key, setting_value FROM game_settings");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        $GAME_API_SETTINGS_CACHE = $settings;
        return $settings;
    }

    function game_api_get_setting($conn, $key, $defaultValue = '') {
        $settings = game_api_get_settings($conn);
        return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $defaultValue;
    }

    function game_api_ensure_schema($conn, $seedMappings = false) {
        global $GAME_API_SCHEMA_READY;
        if (!empty($GAME_API_SCHEMA_READY)) {
            if ($seedMappings) { game_api_seed_mappings($conn); }
            return;
        }
        $GAME_API_SCHEMA_READY = true;

        // Runtime requests (especially game launch/callback) must not execute dozens of
        // SHOW/ALTER/SELECT migration queries every time. A persistent schema marker
        // keeps the original self-healing migration behavior, but makes normal requests
        // use a two-query fast path after the first successful migration.
        $schemaVersion = '2026_06_16_launch_perf_v1';

        @$conn->query("CREATE TABLE IF NOT EXISTS `game_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(80) NOT NULL,
            `setting_value` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $schemaReadyRes = @$conn->query("SELECT setting_value FROM game_settings WHERE setting_key='game_api_schema_version' ORDER BY id DESC LIMIT 1");
        if ($schemaReadyRes && $schemaReadyRes->num_rows > 0) {
            $schemaReadyRow = $schemaReadyRes->fetch_assoc();
            if (isset($schemaReadyRow['setting_value']) && (string)$schemaReadyRow['setting_value'] === $schemaVersion) {
                if ($seedMappings) { game_api_seed_mappings($conn); }
                return;
            }
        }

        if (game_api_table_exists($conn, 'users')) {
            game_api_ensure_column($conn, 'users', 'turnover_completed', "DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `turnover_target`");
        }

        if (game_api_table_exists($conn, 'games')) {
            game_api_ensure_column($conn, 'games', 'api_game_id', "VARCHAR(100) DEFAULT NULL AFTER `game_uid`");
            game_api_ensure_column($conn, 'games', 'api_game_code', "VARCHAR(100) DEFAULT NULL AFTER `api_game_id`");
            game_api_ensure_column($conn, 'games', 'api_vendor_code', "VARCHAR(60) DEFAULT NULL AFTER `api_game_code`");
            game_api_ensure_column($conn, 'games', 'api_provider_name', "VARCHAR(120) DEFAULT NULL AFTER `provider_id`");
            game_api_ensure_column($conn, 'games', 'api_game_type', "VARCHAR(80) DEFAULT NULL AFTER `category`");
            game_api_ensure_column($conn, 'games', 'api_mapping_status', "VARCHAR(30) NOT NULL DEFAULT 'pending' AFTER `api_game_type`");
            game_api_ensure_index($conn, 'games', 'idx_api_game_id', "INDEX `idx_api_game_id` (`api_game_id`)");
            game_api_ensure_index($conn, 'games', 'idx_api_game_code', "INDEX `idx_api_game_code` (`api_game_code`)");
            game_api_ensure_index($conn, 'games', 'idx_api_vendor_code', "INDEX `idx_api_vendor_code` (`api_vendor_code`)");
        }

        @$conn->query("CREATE TABLE IF NOT EXISTS `game_api_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `local_game_uid` varchar(100) DEFAULT NULL,
            `api_game_id` varchar(120) DEFAULT NULL,
            `endpoint` varchar(255) DEFAULT NULL,
            `request_data` longtext DEFAULT NULL,
            `response_data` longtext DEFAULT NULL,
            `status` varchar(50) DEFAULT NULL,
            `message` varchar(255) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_local_game_uid` (`local_game_uid`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @$conn->query("CREATE TABLE IF NOT EXISTS `game_api_callback_transactions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `external_transaction_id` varchar(160) NOT NULL,
            `player_id` int(11) NOT NULL,
            `callback_type` varchar(40) NOT NULL,
            `local_game_uid` varchar(100) DEFAULT NULL,
            `api_game_id` varchar(120) DEFAULT NULL,
            `round_id` varchar(120) DEFAULT NULL,
            `amount` decimal(20,2) NOT NULL DEFAULT 0.00,
            `balance_before` decimal(20,2) NOT NULL DEFAULT 0.00,
            `balance_after` decimal(20,2) NOT NULL DEFAULT 0.00,
            `raw_data` longtext DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_external_transaction_id` (`external_transaction_id`),
            KEY `idx_player_id` (`player_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        game_api_ensure_setting($conn, 'api_endpoint', 'https://game.gambllyapi.com/production/v1/gameLaunch.php');
        game_api_ensure_setting($conn, 'api_token', '');
        game_api_ensure_setting($conn, 'secret_key', '');
        game_api_ensure_setting($conn, 'agent_code', '');
        game_api_ensure_setting($conn, 'currency_code', 'BDT');
        game_api_ensure_setting($conn, 'game_api_provider', 'GAMBLLY');
        game_api_ensure_setting($conn, 'game_api_mapping_seed_version', '');
        game_api_ensure_setting($conn, 'game_api_mapping_seeded_count', '0');
        game_api_ensure_setting($conn, 'saba_widget_id', 'sZ0P8C87');
        game_api_ensure_setting($conn, 'saba_language', 'en');
        game_api_ensure_setting($conn, 'saba_sport_id', '');
        game_api_ensure_setting($conn, 'unitedgaming_theme', 'style2');
        game_api_ensure_setting($conn, 'unitedgaming_language', 'en');
        game_api_ensure_setting($conn, 'cq9_language', 'en');
        game_api_ensure_setting($conn, '100hp_language', 'bn');
        game_api_ensure_setting($conn, '2j_language', 'bn');
        game_api_ensure_setting($conn, 'bng3oks_row_language', 'en');
        game_api_ensure_setting($conn, '5ggaming_language', 'bn');
        game_api_ensure_setting($conn, '9wicket_language', 'en');
        game_api_ensure_setting($conn, '9wicket_currency', '');
        game_api_ensure_setting($conn, 'amigo_language', 'en');
        game_api_ensure_setting($conn, 'aog_language', 'en');
        game_api_ensure_setting($conn, 'aog_currency', '');
        game_api_ensure_setting($conn, 'astar_language', 'en');
        game_api_ensure_setting($conn, 'bgaming_language', 'en');
        game_api_ensure_setting($conn, 'bigtimegaming_language', 'en');
        game_api_ensure_setting($conn, 'bigtimegaming_currency', '');
        game_api_ensure_setting($conn, 'btgaming_language', 'en');
        game_api_ensure_setting($conn, 'btgaming_currency', '');
        game_api_ensure_setting($conn, 'evolutionlive_language', 'en');
        game_api_ensure_setting($conn, 'evolutionlive_currency', '');
        game_api_ensure_setting($conn, 'fachaigaming_language', 'en');
        game_api_ensure_setting($conn, 'fachaigaming_currency', '');
        game_api_ensure_setting($conn, 'fastspin_language', 'en');
        game_api_ensure_setting($conn, 'fastspin_currency', '');
        game_api_ensure_setting($conn, 'gameart_language', 'en');
        game_api_ensure_setting($conn, 'gameart_currency', '');
        game_api_ensure_setting($conn, 'ideal_language', 'en');
        game_api_ensure_setting($conn, 'ideal_currency', '');
        game_api_ensure_setting($conn, 'inout_language', 'en');
        game_api_ensure_setting($conn, 'inout_currency', '');
        game_api_ensure_setting($conn, 'lucksportgaming_language', 'en');
        game_api_ensure_setting($conn, 'lucksportgaming_currency', '');
        game_api_ensure_setting($conn, 'lucksportgaming_sport', 'soccer');
        game_api_ensure_setting($conn, 'microgaming_language', 'en');
        game_api_ensure_setting($conn, 'microgaming_currency', '');
        game_api_ensure_setting($conn, 'mini_language', 'en');
        game_api_ensure_setting($conn, 'mini_currency', '');
        game_api_ensure_setting($conn, 'netent_language', 'en');
        game_api_ensure_setting($conn, 'netent_currency', '');
        game_api_ensure_setting($conn, 'ongaming_language', 'en');
        game_api_ensure_setting($conn, 'ongaming_currency', '');
        game_api_ensure_setting($conn, 'pragmatic_language', 'en');
        game_api_ensure_setting($conn, 'pragmatic_currency', '');
        game_api_ensure_setting($conn, 'sagaming_language', 'en');
        game_api_ensure_setting($conn, 'sagaming_currency', '');
        game_api_ensure_setting($conn, 'sbo_language', 'en');
        game_api_ensure_setting($conn, 'sbo_currency', '');
        game_api_ensure_setting($conn, 'sbo_568win_currency', 'PHP');
        game_api_ensure_setting($conn, 'dpsports_language', 'en');
        game_api_ensure_setting($conn, 'dpsports_currency', '');
        game_api_ensure_setting($conn, 'sexy_language', 'en');
        game_api_ensure_setting($conn, 'sexy_currency', '');
        game_api_ensure_setting($conn, 't1_language', 'en');
        game_api_ensure_setting($conn, 't1_currency', '');
        game_api_ensure_setting($conn, 'spribe_language', 'en');
        game_api_ensure_setting($conn, 'spribe_currency', '');
        game_api_ensure_setting($conn, 'tf_language', 'en');
        game_api_ensure_setting($conn, 'tf_currency', '');
        game_api_ensure_setting($conn, 'tfg_language', 'en');
        game_api_ensure_setting($conn, 'tfg_currency', '');
        game_api_ensure_setting($conn, 'yeebet_language', 'en');
        game_api_ensure_setting($conn, 'yeebet_currency', '');

        // Mark this migration version only after all required tables, columns, indexes
        // and settings have been checked successfully.
        game_api_set_setting($conn, 'game_api_schema_version', $schemaVersion);

        if ($seedMappings) {
            game_api_seed_mappings($conn);
        }
    }

    function game_api_seed_mappings($conn) {
        // Run specific patches that check their own versions first
        @game_api_seed_jili_mappings($conn);
        @game_api_seed_evolutionlive_mappings($conn);

        // Provider mapping seed is expensive. Run it only when the bundled mapping version changes.
        $bundleVersion = 'oxen_all_providers_2026_06_19_sports_vendor_fix_v1';
        $currentVersion = game_api_get_setting($conn, 'game_api_mapping_seed_version', '');
        if ($currentVersion === $bundleVersion) {
            return (int)game_api_get_setting($conn, 'game_api_mapping_seeded_count', '0');
        }

        // Provider-by-provider phase: JILI, PGSoft, SABA, United Gaming, JDB, TADAGaming, CQ9, 100HP, 2J, BNG3Oaks ROW, 5G Gaming and 9Wicket.
        $total = 0;
        $total += (int)game_api_seed_jili_mappings($conn);
        $total += (int)game_api_seed_pgsoft_mappings($conn);
        $total += (int)game_api_seed_saba_mappings($conn);
        $total += (int)game_api_seed_unitedgaming_mappings($conn);
        $total += (int)game_api_seed_jdb_mappings($conn);
        $total += (int)game_api_seed_tadagaming_mappings($conn);
        $total += (int)game_api_seed_cq9_mappings($conn);
        $total += (int)game_api_seed_100hp_mappings($conn);
        $total += (int)game_api_seed_2j_mappings($conn);
        $total += (int)game_api_seed_bng3oks_row_mappings($conn);
        $total += (int)game_api_seed_5ggaming_mappings($conn);
        $total += (int)game_api_seed_9wicket_mappings($conn);
        $total += (int)game_api_seed_amigo_mappings($conn);
        $total += (int)game_api_seed_aog_mappings($conn);
        $total += (int)game_api_seed_astar_mappings($conn);
        $total += (int)game_api_seed_bgaming_mappings($conn);
        $total += (int)game_api_seed_bigtimegaming_mappings($conn);
        $total += (int)game_api_seed_btgaming_mappings($conn);
        $total += (int)game_api_seed_evolutionlive_mappings($conn);
        $total += (int)game_api_seed_evolutionlive_popular_games($conn);
        $total += (int)game_api_seed_fachaigaming_mappings($conn);
        $total += (int)game_api_seed_fastspin_mappings($conn);
        $total += (int)game_api_seed_gameart_mappings($conn);
        $total += (int)game_api_seed_ideal_mappings($conn);
        $total += (int)game_api_seed_inout_mappings($conn);
        $total += (int)game_api_seed_lucksportgaming_mappings($conn);
        $total += (int)game_api_seed_microgaming_mappings($conn);
        $total += (int)game_api_seed_mini_mappings($conn);
        $total += (int)game_api_seed_netent_mappings($conn);
        $total += (int)game_api_seed_ongaming_mappings($conn);
        $total += (int)game_api_seed_pragmatic_mappings($conn);
        $total += (int)game_api_seed_sagaming_mappings($conn);
        $total += (int)game_api_seed_sbo_mappings($conn);
        $total += (int)game_api_seed_dpsports_mappings($conn);
        $total += (int)game_api_seed_sexy_mappings($conn);
        $total += (int)game_api_seed_t1_mappings($conn);
        $total += (int)game_api_seed_spribe_mappings($conn);
        $total += (int)game_api_seed_tf_mappings($conn);
        $total += (int)game_api_seed_tfg_mappings($conn);
        $total += (int)game_api_seed_yeebet_mappings($conn);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING,MINI,NETENT,ONGAMING,PRAGMATIC,SAGAMING,SBO,DPSPORTS,SEXY,T1,SPRIBE,TF,TFG,YEEBET');
        game_api_set_setting($conn, 'game_api_mapping_seed_version', $bundleVersion);
        game_api_set_setting($conn, 'game_api_mapping_seeded_count', (string)$total);
        return $total;
    }



    function game_api_jili_image_seed() {
        static $seed = null;
        if ($seed !== null) { return $seed; }
        $seed = array('by_code' => array(), 'by_uid' => array());
        $file = __DIR__ . '/game_api_jili_image_seed.php';
        if (file_exists($file)) {
            $loaded = @include $file;
            if (is_array($loaded)) {
                if (isset($loaded['by_code']) && is_array($loaded['by_code'])) { $seed['by_code'] = $loaded['by_code']; }
                if (isset($loaded['by_uid']) && is_array($loaded['by_uid'])) { $seed['by_uid'] = $loaded['by_uid']; }
            }
        }
        return $seed;
    }

    function game_api_jili_is_missing_image($image) {
        $image = trim((string)$image);
        if ($image === '') { return true; }
        $lower = strtolower($image);
        $missingNeedles = array(
            'default_game',
            'placeholder',
            'placehold.co',
            '/assets/img/pro/jili',
            '/assets/img/providers/jili',
            'provider-awcmjili',
            'jili.jpg',
            'jili.png'
        );
        foreach ($missingNeedles as $needle) {
            if (strpos($lower, $needle) !== false) { return true; }
        }
        return false;
    }

    function game_api_jili_image_for_mapping($map, $currentImage = '') {
        if (!is_array($map)) { return ''; }
        $seed = game_api_jili_image_seed();
        $code = isset($map['api_game_code']) ? trim((string)$map['api_game_code']) : '';
        $uid = isset($map['api_game_id']) ? strtolower(trim((string)$map['api_game_id'])) : '';
        if ($code !== '' && isset($seed['by_code'][$code]) && trim((string)$seed['by_code'][$code]) !== '') {
            return trim((string)$seed['by_code'][$code]);
        }
        if ($uid !== '' && isset($seed['by_uid'][$uid]) && trim((string)$seed['by_uid'][$uid]) !== '') {
            return trim((string)$seed['by_uid'][$uid]);
        }
        return game_api_jili_is_missing_image($currentImage) ? '/assets/img/games/default_game.jpg' : (string)$currentImage;
    }

    function game_api_jili_clear_category_cache() {
        $cacheDir = dirname(__DIR__) . '/cache/category_games';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*.cache.php') as $cacheFile) { @unlink($cacheFile); }
        }
    }

    function game_api_jili_dedupe_mapped_rows($conn, $mappings) {
        if (!is_array($mappings) || empty($mappings)) { return 0; }
        $hidden = 0;
        foreach ($mappings as $map) {
            if (!is_array($map)) { continue; }
            $code = isset($map['api_game_code']) ? trim((string)$map['api_game_code']) : '';
            $uid = isset($map['api_game_id']) ? trim((string)$map['api_game_id']) : '';
            if ($code === '' && $uid === '') { continue; }

            $conditions = array();
            if ($code !== '') { $conditions[] = "api_game_code='" . $conn->real_escape_string($code) . "'"; }
            if ($uid !== '') { $conditions[] = "LOWER(api_game_id)='" . $conn->real_escape_string(strtolower($uid)) . "'"; }
            if ($code !== '') { $conditions[] = "game_uid='jili_" . $conn->real_escape_string($code) . "'"; }

            if (empty($conditions)) { continue; }
            $sql = "SELECT id, game_uid, api_game_code, api_game_id, name, image, status
                    FROM games
                    WHERE (" . implode(' OR ', $conditions) . ")
                      AND (provider_id='49' OR UPPER(COALESCE(api_vendor_code,''))='JILI')
                    ORDER BY id ASC";
            $res = @$conn->query($sql);
            if (!$res || $res->num_rows <= 1) {
                if ($res) { $res->free(); }
                continue;
            }

            $rows = array();
            while ($row = $res->fetch_assoc()) { $rows[] = $row; }
            $res->free();
            if (count($rows) <= 1) { continue; }

            $keepIndex = 0;
            $bestScore = -999999;
            foreach ($rows as $idx => $row) {
                $score = 0;
                if (isset($row['status']) && $row['status'] === 'active') { $score += 20; }
                if (!game_api_jili_is_missing_image(isset($row['image']) ? $row['image'] : '')) { $score += 15; }
                $localUid = isset($row['game_uid']) ? (string)$row['game_uid'] : '';
                if (strpos($localUid, 'jili_') !== 0) { $score += 10; }
                if ($code !== '' && isset($row['api_game_code']) && (string)$row['api_game_code'] === $code) { $score += 5; }
                if ($uid !== '' && isset($row['api_game_id']) && strtolower((string)$row['api_game_id']) === strtolower($uid)) { $score += 5; }
                $score -= (int)$row['id'] / 1000000;
                if ($score > $bestScore) { $bestScore = $score; $keepIndex = $idx; }
            }

            $keepId = (int)$rows[$keepIndex]['id'];
            foreach ($rows as $row) {
                $id = (int)$row['id'];
                if ($id === $keepId) { continue; }
                $image = isset($row['image']) ? (string)$row['image'] : '';
                $localUid = isset($row['game_uid']) ? (string)$row['game_uid'] : '';
                // Only hide the duplicate clone/generic rows; never touch a distinct real row with its own thumbnail.
                if (strpos($localUid, 'jili_') === 0 || game_api_jili_is_missing_image($image)) {
                    if (@$conn->query("UPDATE games SET status='inactive', api_mapping_status='duplicate' WHERE id=" . $id . " LIMIT 1")) {
                        $hidden += max(0, (int)$conn->affected_rows);
                    }
                }
            }
        }
        return $hidden;
    }


    function game_api_jili_canonical_active_rows($conn) {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $cache = array('by_code' => array(), 'by_uid' => array(), 'by_name' => array(), 'by_game_uid' => array());
        if (!game_api_table_exists($conn, 'games')) { return $cache; }

        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.api_game_id, g.api_game_code, g.api_vendor_code, g.api_provider_name, g.image, g.category, g.api_game_type, g.status,
                       gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE g.status='active'
                  AND (g.provider_id='49' OR UPPER(COALESCE(g.api_vendor_code,''))='JILI' OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%jili%')
                ORDER BY (g.provider_id='49') DESC, (UPPER(COALESCE(g.api_vendor_code,''))='JILI') DESC, g.id ASC";
        $res = @$conn->query($sql);
        if (!$res) { return $cache; }
        while ($row = $res->fetch_assoc()) {
            $uid = isset($row['game_uid']) ? trim((string)$row['game_uid']) : '';
            $code = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
            $gameUid = isset($row['api_game_id']) ? strtolower(trim((string)$row['api_game_id'])) : '';
            $nameKey = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
            if ($uid !== '' && !isset($cache['by_game_uid'][$uid])) { $cache['by_game_uid'][$uid] = $row; }
            if ($code !== '' && !isset($cache['by_code'][$code])) { $cache['by_code'][$code] = $row; }
            if ($gameUid !== '' && !isset($cache['by_uid'][$gameUid])) { $cache['by_uid'][$gameUid] = $row; }
            if ($nameKey !== '' && !isset($cache['by_name'][$nameKey])) { $cache['by_name'][$nameKey] = $row; }
        }
        $res->free();
        return $cache;
    }

    function game_api_jili_canonical_row_for_alias($conn, $row) {
        if (!is_array($row)) { return null; }
        $ownUid = isset($row['game_uid']) ? trim((string)$row['game_uid']) : '';
        if ($ownUid === '') { return null; }
        if (game_api_is_first_provider_jili($row)) { return $row; }

        $cache = game_api_jili_canonical_active_rows($conn);
        $code = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
        $uid = isset($row['api_game_id']) ? strtolower(trim((string)$row['api_game_id'])) : '';
        $nameKey = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
        $candidate = null;

        // Wrong-launch symptom: some non-JILI rows use the same public numeric Code/name as JILI
        // but open another provider URL. Prefer the active JILI row only for this JILI alias collision.
        if ($code !== '' && isset($cache['by_code'][$code])) { $candidate = $cache['by_code'][$code]; }
        if (!$candidate && $uid !== '' && isset($cache['by_uid'][$uid])) { $candidate = $cache['by_uid'][$uid]; }
        if (!$candidate && $nameKey !== '' && isset($cache['by_name'][$nameKey])) {
            // Name-only fallback is only used when the non-JILI row also carries a numeric code that exists
            // in the JILI list, which avoids hijacking unrelated providers with same marketing names.
            if ($code !== '' && isset($cache['by_code'][$code])) { $candidate = $cache['by_name'][$nameKey]; }
        }
        if (!$candidate || !is_array($candidate)) { return null; }
        $targetUid = isset($candidate['game_uid']) ? trim((string)$candidate['game_uid']) : '';
        if ($targetUid === '' || $targetUid === $ownUid) { return null; }
        return $candidate;
    }

    function game_api_jili_canonical_launch_uid($conn, $row) {
        if (!is_array($row)) { return ''; }
        $ownUid = isset($row['game_uid']) ? trim((string)$row['game_uid']) : '';
        $target = game_api_jili_canonical_row_for_alias($conn, $row);
        if ($target && isset($target['game_uid']) && trim((string)$target['game_uid']) !== '') {
            return trim((string)$target['game_uid']);
        }
        return $ownUid;
    }

    function game_api_jili_prepare_display_rows($conn, $rows, $limit = 0) {
        if (!is_array($rows) || empty($rows)) { return array(); }
        $limit = (int)$limit;
        $out = array();
        $seenNames = array();
        foreach ($rows as $row) {
            if (!is_array($row)) { continue; }
            $displayRow = $row;
            $target = game_api_jili_canonical_row_for_alias($conn, $row);
            if ($target && is_array($target)) {
                // Show the real JILI title/image and make the click launch the same JILI game.
                $displayRow = array_merge($row, $target);
                $displayRow['jili_launch_uid'] = isset($target['game_uid']) ? (string)$target['game_uid'] : (isset($row['game_uid']) ? (string)$row['game_uid'] : '');
                if (!isset($displayRow['prov_name']) && isset($target['provider_name'])) { $displayRow['prov_name'] = $target['provider_name']; }
            } else {
                $displayRow['jili_launch_uid'] = isset($row['game_uid']) ? (string)$row['game_uid'] : '';
            }

            $nameKey = game_api_normalize_name(isset($displayRow['name']) ? $displayRow['name'] : '');
            if ($nameKey !== '' && isset($seenNames[$nameKey])) {
                // Hide duplicate same-title cards in mixed category/popular lists when the JILI row already exists.
                continue;
            }
            if ($nameKey !== '') { $seenNames[$nameKey] = true; }
            $out[] = $displayRow;
            if ($limit > 0 && count($out) >= $limit) { break; }
        }
        return $out;
    }

    function game_api_seed_jili_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        // Keep this version JILI-specific so file-only cPanel updates can auto-apply the DB mapping patch
        // without forcing a full all-provider reseed on every request.
        $jiliVersion = 'oxen_jili_docs_code_uid_image_dedupe_speed_alias_fix_2026_07_03_v6';
        $currentJiliVersion = game_api_get_setting($conn, 'game_api_mapping_seed_version_jili', '');
        if ($currentJiliVersion === $jiliVersion) {
            return (int)game_api_get_setting($conn, 'game_api_mapping_seeded_count_jili', '0');
        }

        $seedFile = __DIR__ . '/game_api_jili_mapping_seed.php';
        if (!file_exists($seedFile)) { return 0; }
        $mappings = require $seedFile;
        if (!is_array($mappings)) { return 0; }

        $mapByName = array();
        $mapByCode = array();
        $mapByUid = array();
        foreach ($mappings as $map) {
            if (!is_array($map)) { continue; }
            $name = isset($map['game_name']) ? trim((string)$map['game_name']) : '';
            $apiGameId = isset($map['api_game_id']) ? trim((string)$map['api_game_id']) : '';
            $apiGameCode = isset($map['api_game_code']) ? trim((string)$map['api_game_code']) : '';
            if ($name === '' || $apiGameId === '' || $apiGameCode === '') { continue; }
            $mapByName[game_api_normalize_name($name)] = $map;
            $mapByCode[$apiGameCode] = $map;
            $mapByUid[strtolower($apiGameId)] = $map;
        }

        if (empty($mapByName) && empty($mapByCode) && empty($mapByUid)) { return 0; }

        // Make sure the JILI provider row is present and active. This only touches provider_id=49.
        if (game_api_table_exists($conn, 'game_providers')) {
            @$conn->query("UPDATE game_providers SET name='JILI', slug='jili', status='active' WHERE provider_id='49' LIMIT 1");
        }

        $updated = 0;
        $matched = 0;
        $inserted = 0;
        $hidden = 0;
        $existingByCode = array();
        $existingByUid = array();
        $existingLocalUid = array();

        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.api_game_id, g.api_game_code, g.image, g.category, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='49'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%jili%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%jili%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%jili%'
                        OR UPPER(COALESCE(g.api_vendor_code,'')) = 'JILI'
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games
                                    SET api_game_id=?, api_game_code=?, api_vendor_code='JILI', provider_id='49', api_provider_name='JILI', name=?, api_game_type=?, api_mapping_status='mapped', status='active'
                                    WHERE game_uid=? LIMIT 1");
            $imageStmt = $conn->prepare("UPDATE games SET image=? WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    if ($localUid !== '') { $existingLocalUid[$localUid] = true; }
                    $rowCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                    $rowUid = isset($row['api_game_id']) ? strtolower(trim((string)$row['api_game_id'])) : '';
                    if ($rowCode !== '' && strtoupper($rowCode) !== 'NULL') { $existingByCode[$rowCode] = true; }
                    if ($rowUid !== '' && strtoupper($rowUid) !== 'NULL') { $existingByUid[$rowUid] = true; }

                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $match = null;
                    if ($rowCode !== '' && isset($mapByCode[$rowCode])) { $match = $mapByCode[$rowCode]; }
                    elseif ($rowUid !== '' && isset($mapByUid[$rowUid])) { $match = $mapByUid[$rowUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $match = $mapByName[$norm]; }
                    if (!$match) { continue; }

                    $matched++;
                    $apiGameId = (string)$match['api_game_id'];       // 32-character JILI Game UID from documentation.
                    $apiGameCode = (string)$match['api_game_code'];   // Numeric JILI Code from documentation.
                    $gameName = (string)$match['game_name'];          // Canonical name from documentation.
                    $apiType = isset($match['api_game_type']) ? (string)$match['api_game_type'] : '';
                    $stmt->bind_param('sssss', $apiGameId, $apiGameCode, $gameName, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }

                    // Only fill missing/generic JILI thumbnails. Existing real thumbnails are not replaced.
                    $currentImage = isset($row['image']) ? (string)$row['image'] : '';
                    $mappedImage = game_api_jili_image_for_mapping($match, $currentImage);
                    if ($imageStmt && $mappedImage !== '' && game_api_jili_is_missing_image($currentImage)) {
                        $imageStmt->bind_param('ss', $mappedImage, $localUid);
                        if ($imageStmt->execute()) { $updated += max(0, (int)$imageStmt->affected_rows); }
                    }

                    $existingByCode[$apiGameCode] = true;
                    $existingByUid[strtolower($apiGameId)] = true;
                }
                $stmt->close();
            }
            if ($imageStmt) { $imageStmt->close(); }
            $res->free();
        }

        // Add valid JILI games from the provided official list when they are not present in the DB yet.
        // For missing-image rows, use a local title thumbnail instead of the repeated generic JILI icon.
        $insertSql = "INSERT INTO games (game_uid, api_game_id, api_game_code, api_vendor_code, provider_id, api_provider_name, name, image, category, api_game_type, api_mapping_status, status)
                      VALUES (?, ?, ?, 'JILI', '49', 'JILI', ?, ?, ?, ?, 'mapped', 'active')";
        $insertStmt = $conn->prepare($insertSql);
        if ($insertStmt) {
            foreach ($mappings as $map) {
                if (!is_array($map)) { continue; }
                $apiGameId = isset($map['api_game_id']) ? trim((string)$map['api_game_id']) : '';
                $apiGameCode = isset($map['api_game_code']) ? trim((string)$map['api_game_code']) : '';
                $gameName = isset($map['game_name']) ? trim((string)$map['game_name']) : '';
                $apiType = isset($map['api_game_type']) ? trim((string)$map['api_game_type']) : '';
                if ($apiGameId === '' || $apiGameCode === '' || $gameName === '') { continue; }
                if (isset($existingByCode[$apiGameCode]) || isset($existingByUid[strtolower($apiGameId)])) { continue; }

                $localUid = 'jili_' . preg_replace('/[^0-9A-Za-z_\-]/', '', $apiGameCode);
                if ($localUid === 'jili_' || isset($existingLocalUid[$localUid])) {
                    $localUid = 'jili_' . substr(md5($apiGameId . $apiGameCode), 0, 12);
                }
                $category = $apiType !== '' ? $apiType : 'Slot';
                if (strcasecmp($category, 'Fish') === 0) { $category = 'Fishing'; }
                elseif (strcasecmp($category, 'India Poker') === 0) { $category = 'Card'; }
                elseif (strcasecmp($category, 'slot/arcade') === 0) { $category = 'Slot'; }
                $image = game_api_jili_image_for_mapping($map, '');
                if ($image === '') { $image = '/assets/img/games/default_game.jpg'; }

                $insertStmt->bind_param('sssssss', $localUid, $apiGameId, $apiGameCode, $gameName, $image, $category, $apiType);
                if ($insertStmt->execute()) {
                    $inserted++;
                    $existingByCode[$apiGameCode] = true;
                    $existingByUid[strtolower($apiGameId)] = true;
                    $existingLocalUid[$localUid] = true;
                }
            }
            $insertStmt->close();
        }

        // Public JILI docs do not provide numeric codes for these legacy/non-public rows. Keep them from
        // appearing as clickable active cards, otherwise they can only fail with mapping missing/unavailable.
        $hideSql = "UPDATE games
                    SET status='inactive', api_mapping_status='pending'
                    WHERE provider_id='49'
                      AND (
                            api_game_code IS NULL OR api_game_code='' OR api_game_id IS NULL OR api_game_id=''
                            OR LOWER(name) IN ('gem party','monkey party','big small','7 up-down','fairness games(blocklobby)')
                          )";
        if (@$conn->query($hideSql)) { $hidden = max(0, (int)$conn->affected_rows); }

        // After filling images and inserting missing games, hide only duplicate JILI clone/generic rows.
        $hidden += (int)game_api_jili_dedupe_mapped_rows($conn, $mappings);

        // Always clear category cache when this JILI-only patch version runs, because display rows are now de-duplicated.
        game_api_jili_clear_category_cache();

        // Auto-map and sort active JILI games and Aviator to Hot Games category
        if (game_api_table_exists($conn, 'front_category_games')) {
            $hot_cat_id = 1;
            $cat_q = $conn->query("SELECT id FROM front_categories WHERE LOWER(name) LIKE '%hot%' OR LOWER(name) LIKE '%popular%' LIMIT 1");
            if ($cat_q && $cat_q->num_rows > 0) {
                $hot_cat_id = (int)$cat_q->fetch_assoc()['id'];
            }

            $aviator_uid = '';
            $av_res = $conn->query("SELECT game_uid FROM games WHERE LOWER(name) = 'aviator' AND status = 'active' LIMIT 1");
            if ($av_res && $av_res->num_rows > 0) {
                $aviator_uid = (string)$av_res->fetch_assoc()['game_uid'];
            }

            $jili_res = $conn->query("SELECT game_uid, name FROM games WHERE status='active' AND (provider_id='49' OR UPPER(COALESCE(api_vendor_code,''))='JILI')");
            if ($jili_res) {
                $top_6_order = array(
                    'aviator' => 1,
                    'bombing fishing' => 2,
                    'dinosaur tycoon' => 3,
                    'jackpot fishing' => 4,
                    'dragon fortune' => 5,
                    'mega fishing' => 6
                );
                
                $stmt_check = $conn->prepare("SELECT id, sort_order FROM front_category_games WHERE category_id=? AND game_uid=? LIMIT 1");
                $stmt_insert = $conn->prepare("INSERT INTO front_category_games (category_id, game_uid, sort_order) VALUES (?, ?, ?)");
                $stmt_update = $conn->prepare("UPDATE front_category_games SET sort_order=? WHERE id=? LIMIT 1");
                
                $normal_sort = 10;
                
                $all_game_uids = array();
                if ($aviator_uid !== '') {
                    $all_game_uids[$aviator_uid] = 'aviator';
                }
                while ($row = $jili_res->fetch_assoc()) {
                    $all_game_uids[$row['game_uid']] = $row['name'];
                }
                
                foreach ($all_game_uids as $uid => $name) {
                    $lower_name = strtolower(trim((string)$name));
                    $sort = isset($top_6_order[$lower_name]) ? $top_6_order[$lower_name] : null;
                    if ($sort === null) {
                        $sort = $normal_sort;
                        $normal_sort += 2;
                    }
                    
                    if ($stmt_check) {
                        $stmt_check->bind_param('is', $hot_cat_id, $uid);
                        $stmt_check->execute();
                        $res_check = $stmt_check->get_result();
                        if ($res_check && $res_check->num_rows > 0) {
                            $check_row = $res_check->fetch_assoc();
                            $existing_id = (int)$check_row['id'];
                            $existing_sort = (int)$check_row['sort_order'];
                            if ($existing_sort !== $sort && $stmt_update) {
                                $stmt_update->bind_param('ii', $sort, $existing_id);
                                $stmt_update->execute();
                            }
                        } else if ($stmt_insert) {
                            $stmt_insert->bind_param('isi', $hot_cat_id, $uid, $sort);
                            $stmt_insert->execute();
                        }
                    }
                }
                
                if ($stmt_check) $stmt_check->close();
                if ($stmt_insert) $stmt_insert->close();
                if ($stmt_update) $stmt_update->close();
            }
        }

        game_api_set_setting($conn, 'game_api_mapping_seed_version_jili', $jiliVersion);
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_jili', (string)($updated + $inserted));
        game_api_set_setting($conn, 'game_api_mapping_matched_count_jili', (string)$matched);
        game_api_set_setting($conn, 'game_api_mapping_inserted_count_jili', (string)$inserted);
        game_api_set_setting($conn, 'game_api_mapping_hidden_count_jili', (string)$hidden);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return ($updated + $inserted);
    }

    function game_api_seed_pgsoft_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        $seedFile = __DIR__ . '/game_api_pgsoft_mapping_seed.php';
        if (!file_exists($seedFile)) { return 0; }
        $mappings = require $seedFile;
        if (!is_array($mappings)) { return 0; }

        $mapByName = array();
        foreach ($mappings as $map) {
            $name = isset($map['game_name']) ? $map['game_name'] : '';
            $apiGameId = isset($map['api_game_id']) ? $map['api_game_id'] : '';
            $apiGameCode = isset($map['api_game_code']) ? $map['api_game_code'] : '';
            if ($name === '' || $apiGameId === '' || $apiGameCode === '') { continue; }
            $mapByName[game_api_normalize_name($name)] = $map;
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='45'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%pgsoft%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%pg soft%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%pgsoft%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%pg-soft%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%pgsoft%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%pg soft%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('pgsoft','pg')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='pgsoft', api_provider_name='PGSoft', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    if ($norm === '' || !isset($mapByName[$norm])) { continue; }
                    $matched++;
                    $apiGameId = (string)$mapByName[$norm]['api_game_id'];
                    $apiGameCode = (string)$mapByName[$norm]['api_game_code'];
                    $apiType = isset($mapByName[$norm]['api_game_type']) ? (string)$mapByName[$norm]['api_game_type'] : '';
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='45'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%pgsoft%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%pg soft%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%pgsoft%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%pg-soft%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%pgsoft%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%pg soft%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('pgsoft','pg')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_pgsoft', 'oxen_pgsoft_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_pgsoft', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_pgsoft', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }

    function game_api_seed_saba_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        $seedFile = __DIR__ . '/game_api_saba_mapping_seed.php';
        if (!file_exists($seedFile)) { return 0; }
        $mappings = require $seedFile;
        if (!is_array($mappings)) { return 0; }

        $mapByName = array();
        foreach ($mappings as $map) {
            $name = isset($map['game_name']) ? $map['game_name'] : '';
            $apiGameId = isset($map['api_game_id']) ? $map['api_game_id'] : '';
            $apiGameCode = isset($map['api_game_code']) ? $map['api_game_code'] : '';
            if ($name === '' || $apiGameId === '' || $apiGameCode === '') { continue; }
            $mapByName[game_api_normalize_name($name)] = $map;
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='46'
                        OR LOWER(COALESCE(g.name,'')) LIKE '%saba%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%saba%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%saba%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%saba%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) = 'saba'
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='saba', api_provider_name='SABA Sports', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif (strpos($norm, 'saba') !== false && isset($mapByName['sabasports'])) { $map = $mapByName['sabasports']; }
                    if (!$map) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = (string)$map['api_game_code'];
                    $apiType = isset($map['api_game_type']) ? (string)$map['api_game_type'] : 'Sports Game';
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='46'
                            OR LOWER(COALESCE(g.name,'')) LIKE '%saba%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%saba%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%saba%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%saba%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) = 'saba'
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_saba', 'oxen_saba_sports_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_saba', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_saba', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }

    function game_api_seed_unitedgaming_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        $seedFile = __DIR__ . '/game_api_unitedgaming_mapping_seed.php';
        if (!file_exists($seedFile)) { return 0; }
        $mappings = require $seedFile;
        if (!is_array($mappings)) { return 0; }

        $mapByName = array();
        foreach ($mappings as $map) {
            $name = isset($map['game_name']) ? $map['game_name'] : '';
            $apiGameId = isset($map['api_game_id']) ? $map['api_game_id'] : '';
            $apiGameCode = isset($map['api_game_code']) ? $map['api_game_code'] : '';
            if ($name === '' || $apiGameId === '' || $apiGameCode === '') { continue; }
            $mapByName[game_api_normalize_name($name)] = $map;
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='48'
                        OR LOWER(COALESCE(g.name,'')) LIKE '%united%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%united%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%united%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%united%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('unitedgaming','united_gaming')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='unitedgaming', api_provider_name='United Gaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif (strpos($norm, 'united') !== false && isset($mapByName['unitedgaming'])) { $map = $mapByName['unitedgaming']; }
                    if (!$map) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = (string)$map['api_game_code'];
                    $apiType = isset($map['api_game_type']) ? (string)$map['api_game_type'] : 'Sports Game';
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='48'
                            OR LOWER(COALESCE(g.name,'')) LIKE '%united%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%united%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%united%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%united%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('unitedgaming','united_gaming')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_unitedgaming', 'oxen_unitedgaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_unitedgaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_unitedgaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }

    function game_api_seed_jdb_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        $seedFile = __DIR__ . '/game_api_jdb_mapping_seed.php';
        if (!file_exists($seedFile)) { return 0; }
        $mappings = require $seedFile;
        if (!is_array($mappings)) { return 0; }

        $mapByName = array();
        $mapByApiId = array();
        foreach ($mappings as $map) {
            $name = isset($map['game_name']) ? trim((string)$map['game_name']) : '';
            $apiGameId = isset($map['api_game_id']) ? trim((string)$map['api_game_id']) : '';
            $apiGameCode = isset($map['api_game_code']) ? trim((string)$map['api_game_code']) : '';
            if ($name === '' || $apiGameId === '' || $apiGameCode === '') { continue; }
            $norm = game_api_normalize_name($name);
            if ($norm !== '') { $mapByName[$norm] = $map; }
            $mapByApiId[$apiGameId] = $map;
        }

        $updated = 0;
        $matched = 0;
        $inserted = 0;
        $existingByUid = array();
        $existingByApiId = array();
        $existingByName = array();

        $sql = "SELECT g.game_uid, g.api_game_id, g.name, g.provider_id, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='50'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%jdb%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%jdb%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%jdb%'
                        OR UPPER(COALESCE(g.api_vendor_code,'')) = 'JDB'
                  )";
        $res = @$conn->query($sql);
        $rows = array();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
                $uid = isset($row['game_uid']) ? (string)$row['game_uid'] : '';
                $apiId = isset($row['api_game_id']) ? (string)$row['api_game_id'] : '';
                $norm = game_api_normalize_name(isset($row['name']) ? (string)$row['name'] : '');
                if ($uid !== '') { $existingByUid[$uid] = true; }
                if ($apiId !== '') { $existingByApiId[$apiId] = $uid !== '' ? $uid : true; }
                if ($norm !== '') { $existingByName[$norm] = $uid !== '' ? $uid : true; }
            }
        }

        $updateStmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='JDB', api_provider_name='JDB', name=?, image=?, category=?, api_game_type=?, api_mapping_status='mapped', status='active' WHERE game_uid=? LIMIT 1");
        if ($updateStmt) {
            foreach ($rows as $row) {
                $uid = isset($row['game_uid']) ? (string)$row['game_uid'] : '';
                if ($uid === '') { continue; }
                $apiId = isset($row['api_game_id']) ? (string)$row['api_game_id'] : '';
                $norm = game_api_normalize_name(isset($row['name']) ? (string)$row['name'] : '');
                $map = null;
                if ($apiId !== '' && isset($mapByApiId[$apiId])) {
                    $map = $mapByApiId[$apiId];
                } elseif ($norm !== '' && isset($mapByName[$norm])) {
                    $map = $mapByName[$norm];
                }
                if (!$map) { continue; }
                $matched++;
                $apiGameId = (string)$map['api_game_id'];
                $apiGameCode = isset($map['api_game_code']) && (string)$map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                $name = isset($map['game_name']) ? (string)$map['game_name'] : (isset($row['name']) ? (string)$row['name'] : '');
                $image = isset($map['image_path']) ? (string)$map['image_path'] : '';
                $category = isset($map['category']) ? (string)$map['category'] : 'Slots';
                $apiType = isset($map['api_game_type']) ? (string)$map['api_game_type'] : 'Slot Game';
                $updateStmt->bind_param('sssssss', $apiGameId, $apiGameCode, $name, $image, $category, $apiType, $uid);
                if ($updateStmt->execute()) { $updated += max(0, (int)$updateStmt->affected_rows); }
                $existingByApiId[$apiGameId] = $uid;
                $existingByName[game_api_normalize_name($name)] = $uid;
                $existingByUid[$uid] = true;
            }
            $updateStmt->close();
        }

        $insertStmt = $conn->prepare("INSERT INTO games (game_uid, api_game_id, api_game_code, api_vendor_code, provider_id, api_provider_name, name, image, category, api_game_type, api_mapping_status, status) VALUES (?, ?, ?, 'JDB', '50', 'JDB', ?, ?, ?, ?, 'mapped', 'active')");
        if ($insertStmt) {
            foreach ($mappings as $map) {
                $name = isset($map['game_name']) ? trim((string)$map['game_name']) : '';
                $apiGameId = isset($map['api_game_id']) ? trim((string)$map['api_game_id']) : '';
                if ($name === '' || $apiGameId === '') { continue; }
                $norm = game_api_normalize_name($name);
                if (isset($existingByApiId[$apiGameId]) || ($norm !== '' && isset($existingByName[$norm]))) {
                    continue;
                }
                $uidBase = isset($map['local_game_uid']) ? trim((string)$map['local_game_uid']) : '';
                if ($uidBase === '') { $uidBase = 'jdb_' . substr($apiGameId, 0, 10); }
                $uid = $uidBase;
                $suffix = 1;
                while (isset($existingByUid[$uid])) {
                    $uid = 'jdb_' . $uidBase . '_' . $suffix;
                    $suffix++;
                }
                $apiGameCode = isset($map['api_game_code']) && (string)$map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                $image = isset($map['image_path']) ? (string)$map['image_path'] : '';
                $category = isset($map['category']) ? (string)$map['category'] : 'Slots';
                $apiType = isset($map['api_game_type']) ? (string)$map['api_game_type'] : 'Slot Game';
                $insertStmt->bind_param('sssssss', $uid, $apiGameId, $apiGameCode, $name, $image, $category, $apiType);
                if ($insertStmt->execute()) {
                    $inserted += max(0, (int)$insertStmt->affected_rows);
                    $existingByUid[$uid] = true;
                    $existingByApiId[$apiGameId] = $uid;
                    if ($norm !== '') { $existingByName[$norm] = $uid; }
                }
            }
            $insertStmt->close();
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='50'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%jdb%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%jdb%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%jdb%'
                            OR UPPER(COALESCE(g.api_vendor_code,'')) = 'JDB'
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_jdb', 'oxen_jdb_2026_06_19_full_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_jdb', (string)($updated + $inserted));
        game_api_set_setting($conn, 'game_api_mapping_matched_count_jdb', (string)$matched);
        game_api_set_setting($conn, 'game_api_inserted_count_jdb', (string)$inserted);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated + $inserted;
    }



    function game_api_seed_tadagaming_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        $seedFile = __DIR__ . '/game_api_tadagaming_mapping_seed.php';
        if (!file_exists($seedFile)) { return 0; }
        $mappings = require $seedFile;
        if (!is_array($mappings)) { return 0; }

        $mapByName = array();
        foreach ($mappings as $map) {
            $name = isset($map['game_name']) ? $map['game_name'] : '';
            $apiGameId = isset($map['api_game_id']) ? $map['api_game_id'] : '';
            $apiGameCode = isset($map['api_game_code']) ? $map['api_game_code'] : '';
            if ($name === '' || $apiGameId === '' || $apiGameCode === '') { continue; }
            $mapByName[game_api_normalize_name($name)] = $map;
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='51'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%tada%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%tada%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%tada%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) = '2j'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('tadagaming','tada','2j')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='tadagaming', api_provider_name='TADAGaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    if ($norm === '' || !isset($mapByName[$norm])) { continue; }
                    $matched++;
                    $apiGameId = (string)$mapByName[$norm]['api_game_id'];
                    $apiGameCode = (string)$mapByName[$norm]['api_game_code'];
                    $apiType = isset($mapByName[$norm]['api_game_type']) ? (string)$mapByName[$norm]['api_game_type'] : '';
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='51'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%tada%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%tada%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%tada%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) = '2j'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('tadagaming','tada','2j')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_tadagaming', 'oxen_tadagaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_tadagaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_tadagaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_cq9_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        $seedFile = __DIR__ . '/game_api_cq9_mapping_seed.php';
        $mappings = file_exists($seedFile) ? require $seedFile : array();
        if (!is_array($mappings)) { $mappings = array(); }

        $mapByName = array();
        foreach ($mappings as $map) {
            $name = isset($map['game_name']) ? $map['game_name'] : '';
            $apiGameId = isset($map['api_game_id']) ? $map['api_game_id'] : '';
            if ($name === '' || $apiGameId === '') { continue; }
            $mapByName[game_api_normalize_name($name)] = $map;
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='52'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%cq9%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%cq9%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%cq9%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('cq9','cq9gaming','cq9 gaming')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='cq9', api_provider_name='CQ9', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $apiGameId = '';
                    $apiType = '';

                    if ($norm !== '' && isset($mapByName[$norm])) {
                        $matched++;
                        $apiGameId = (string)$mapByName[$norm]['api_game_id'];
                        $apiType = isset($mapByName[$norm]['api_game_type']) ? (string)$mapByName[$norm]['api_game_type'] : '';
                    } else {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '') { $apiGameId = $existingId; }
                        elseif ($existingCode !== '') { $apiGameId = $existingCode; }
                    }

                    if ($apiGameId === '') { continue; }
                    if ($apiType === '') { $apiType = isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'); }
                    $apiGameCode = $apiGameId; // CQ9 docs/list uses the 32-character Game UID as launch key.
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='52'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%cq9%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%cq9%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%cq9%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('cq9','cq9gaming','cq9 gaming')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_cq9', 'oxen_cq9_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_cq9', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_cq9', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }

    function game_api_seed_100hp_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        $seedFile = __DIR__ . '/game_api_100hp_mapping_seed.php';
        $mappings = file_exists($seedFile) ? require $seedFile : array();
        if (!is_array($mappings)) { $mappings = array(); }

        $mapByName = array();
        foreach ($mappings as $map) {
            $name = isset($map['game_name']) ? $map['game_name'] : '';
            $apiGameId = isset($map['api_game_id']) ? $map['api_game_id'] : '';
            if ($name === '' || $apiGameId === '') { continue; }
            $mapByName[game_api_normalize_name($name)] = $map;
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='131'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%100hp%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%100 hp%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%100hp%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%100-hp%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%100hp%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%100 hp%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('100hp','100 hp','hundredhp')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='100hp', api_provider_name='100HP', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $apiGameId = '';
                    $apiType = '';

                    if ($norm !== '' && isset($mapByName[$norm])) {
                        $matched++;
                        $apiGameId = (string)$mapByName[$norm]['api_game_id'];
                        $apiType = isset($mapByName[$norm]['api_game_type']) ? (string)$mapByName[$norm]['api_game_type'] : '';
                    } else {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '') { $apiGameId = $existingId; }
                        elseif ($existingCode !== '') { $apiGameId = $existingCode; }
                    }

                    if ($apiGameId === '') { continue; }
                    if ($apiType === '') { $apiType = isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Instant'); }
                    $apiGameCode = $apiGameId; // 100HP list uses the 32-character Game UID as launch key.
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='131'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%100hp%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%100 hp%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%100hp%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%100-hp%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%100hp%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%100 hp%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('100hp','100 hp','hundredhp')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_100hp', 'oxen_100hp_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_100hp', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_100hp', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_2j_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        $seedFile = __DIR__ . '/game_api_2j_mapping_seed.php';
        $mappings = file_exists($seedFile) ? require $seedFile : array();
        if (!is_array($mappings)) { $mappings = array(); }

        $mapByName = array();
        foreach ($mappings as $map) {
            $name = isset($map['game_name']) ? $map['game_name'] : '';
            $apiGameId = isset($map['api_game_id']) ? $map['api_game_id'] : '';
            if ($name === '' || $apiGameId === '') { continue; }
            $norm = game_api_normalize_name($name);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $map; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='105'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%2j%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%2 j%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%2j%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%2-j%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%2j%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%2 j%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('2j','2 j','twoj','two j')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='2j', api_provider_name='2J', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $apiGameId = '';
                    $apiType = '';

                    // Uploaded SQL already stores correct 2J Game UID in api_game_id for provider_id=105.
                    // Prefer that value first to avoid duplicate-name ambiguity, then fall back to the seed list by name.
                    $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                    $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                    if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                        $apiGameId = $existingId;
                    } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                        $apiGameId = $existingCode;
                    } elseif ($norm !== '' && isset($mapByName[$norm])) {
                        $matched++;
                        $apiGameId = (string)$mapByName[$norm]['api_game_id'];
                        $apiType = isset($mapByName[$norm]['api_game_type']) ? (string)$mapByName[$norm]['api_game_type'] : '';
                    }

                    if ($apiGameId === '') { continue; }
                    if ($apiType === '') { $apiType = isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'); }
                    $apiGameCode = $apiGameId; // 2J list uses the 32-character Game UID as launch key.
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='105'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%2j%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%2 j%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%2j%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%2-j%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%2j%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%2 j%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('2j','2 j','twoj','two j')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_2j', 'oxen_2j_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_2j', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_2j', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_bng3oks_row_mappings($conn) {
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        game_api_ensure_schema($conn, false);
        if (!game_api_column_exists($conn, 'games', 'api_game_id') || !game_api_column_exists($conn, 'games', 'api_game_code')) {
            return 0;
        }

        $seedFile = __DIR__ . '/game_api_bng3oks_row_mapping_seed.php';
        $mappings = file_exists($seedFile) ? require $seedFile : array();
        if (!is_array($mappings)) { $mappings = array(); }

        $mapByName = array();
        foreach ($mappings as $map) {
            $name = isset($map['game_name']) ? $map['game_name'] : '';
            $apiGameId = isset($map['api_game_id']) ? $map['api_game_id'] : '';
            if ($name === '' || $apiGameId === '') { continue; }
            $norm = game_api_normalize_name($name);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $map; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='96'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%bng%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%3oaks%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%3oks%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%boongo%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%bng%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%3oaks%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%3oks%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%boongo%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%bng%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%3oaks%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%3oks%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%boongo%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('bng3oks-row','bng3oks row','bng3oks','bng','3oaks','3oaks-bng','boongo')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='bng3oks-row', api_provider_name='BNG3Oaks ROW', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $apiGameId = '';
                    $apiType = '';

                    // Uploaded SQL already stores correct BNG3Oaks Game UID in api_game_id for provider_id=96.
                    // Prefer that value first to keep existing game placement/image untouched, then fall back to seed list by name.
                    $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                    $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                    if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                        $apiGameId = $existingId;
                    } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                        $apiGameId = $existingCode;
                    } elseif ($norm !== '' && isset($mapByName[$norm])) {
                        $matched++;
                        $apiGameId = (string)$mapByName[$norm]['api_game_id'];
                        $apiType = isset($mapByName[$norm]['api_game_type']) ? (string)$mapByName[$norm]['api_game_type'] : '';
                    }

                    if ($apiGameId === '') { continue; }
                    if ($apiType === '') { $apiType = isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'); }
                    $apiGameCode = $apiGameId; // BNG3Oaks list uses the 32-character Game UID as launch key.
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='96'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%bng%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%3oaks%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%3oks%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%boongo%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%bng%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%3oaks%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%3oks%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%boongo%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%bng%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%3oaks%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%3oks%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%boongo%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('bng3oks-row','bng3oks row','bng3oks','bng','3oaks','3oaks-bng','boongo')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_bng3oks_row', 'oxen_bng3oks_row_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_bng3oks_row', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_bng3oks_row', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_5ggaming_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_5ggaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['name']);
            if ($norm !== '') { $mapByName[$norm] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='103'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%5ggaming%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%5g gaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%5ggaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%5g%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%5ggaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%5g gaming%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('5ggaming','5g gaming','5g','kys','s5g','kys-h5','s5g-h5')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='5ggaming', api_provider_name='5G Gaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    if ($norm === '' || !isset($mapByName[$norm])) { continue; }
                    $matched++;
                    $apiGameId = (string)$mapByName[$norm]['api_game_id'];
                    $apiGameCode = isset($mapByName[$norm]['api_game_code']) && $mapByName[$norm]['api_game_code'] !== '' ? (string)$mapByName[$norm]['api_game_code'] : $apiGameId;
                    $apiType = isset($mapByName[$norm]['api_game_type']) && $mapByName[$norm]['api_game_type'] !== '' ? (string)$mapByName[$norm]['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot');
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='103'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%5ggaming%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%5g gaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%5ggaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%5g%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%5ggaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%5g gaming%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('5ggaming','5g gaming','5g','kys','s5g','kys-h5','s5g-h5')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_5ggaming', 'oxen_5ggaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_5ggaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_5ggaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_9wicket_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_9wicket_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '') { $mapByName[$norm] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='141'
                        OR LOWER(COALESCE(g.name,'')) LIKE '%9wicket%'
                        OR LOWER(COALESCE(g.name,'')) LIKE '%9wickets%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%9wicket%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%9wickets%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%9wicket%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%9wickets%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) IN ('9w','9wicket','9wickets')
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('9w','9wicket','9wickets')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='9wicket', api_provider_name='9Wicket', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif ((strpos($norm, '9wicket') !== false || strpos($norm, '9wickets') !== false) && isset($mapByName['9wicket'])) { $map = $mapByName['9wicket']; }
                    if (!$map) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'Sports';
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='141'
                            OR LOWER(COALESCE(g.name,'')) LIKE '%9wicket%'
                            OR LOWER(COALESCE(g.name,'')) LIKE '%9wickets%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%9wicket%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%9wickets%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%9wicket%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%9wickets%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) IN ('9w','9wicket','9wickets')
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('9w','9wicket','9wickets')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_9wicket', 'oxen_9wicket_2026_06_12_v2');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_9wicket', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_9wicket', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_amigo_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_amigo_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '') { $mapByName[$norm] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='137'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%amigo%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%amigo%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%amigo%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('amigo','amigogaming','amigo gaming','amigo-games','amigo games')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='amigo', api_provider_name='Amigo', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $norm = game_api_normalize_name(isset($row['name']) ? html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8') : '');
                    $apiGameId = '';
                    $apiType = '';

                    if ($norm !== '' && isset($mapByName[$norm])) {
                        $matched++;
                        $apiGameId = (string)$mapByName[$norm]['api_game_id'];
                        $apiType = isset($mapByName[$norm]['api_game_type']) ? (string)$mapByName[$norm]['api_game_type'] : '';
                    } else {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $apiGameId = $existingId;
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $apiGameId = $existingCode;
                        }
                    }

                    if ($apiGameId === '') { continue; }
                    if ($apiType === '') { $apiType = isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'); }
                    $apiGameCode = $apiGameId; // Amigo list uses the 32-character Game UID as launch key.
                    $localUid = (string)$row['game_uid'];
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='137'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%amigo%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%amigo%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%amigo%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('amigo','amigogaming','amigo gaming','amigo-games','amigo games')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_amigo', 'oxen_amigo_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_amigo', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_amigo', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }



    function game_api_seed_aog_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_aog_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '') { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='122'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%aog%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%aog%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%aog%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('aog','aogaming','aog gaming','aog-games','aog games')
                        OR LOWER(COALESCE(g.name,'')) IN ('wcc','wgc','wgb')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='aog', api_provider_name='AOG', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Cockfight');
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Cockfight');
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'Cockfight';
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='122'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%aog%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%aog%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%aog%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('aog','aogaming','aog gaming','aog-games','aog games')
                            OR LOWER(COALESCE(g.name,'')) IN ('wcc','wgc','wgb')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_aog', 'oxen_aog_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_aog', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_aog', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_astar_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_astar_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '') { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='82'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%astar%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%astar%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%astar%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('astar','astargaming','astar gaming','astar-games','astar games')
                        OR LOWER(COALESCE(g.name,'')) LIKE '%astar%'
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='astar', api_provider_name='Astar', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'CasinoLive');
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'CasinoLive');
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'CasinoLive';
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='82'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%astar%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%astar%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%astar%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('astar','astargaming','astar gaming','astar-games','astar games')
                            OR LOWER(COALESCE(g.name,'')) LIKE '%astar%'
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_astar', 'oxen_astar_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_astar', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_astar', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_bgaming_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_bgaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '') { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='65'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%bgaming%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%b gaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%bgaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%b-gaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%bgaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%b gaming%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('bgaming','b gaming','b-gaming','bgaminggames','bgaming games')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='bgaming', api_provider_name='BGaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Video Slot'));
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Video Slot'));
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Video Slot');
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='65'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%bgaming%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%b gaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%bgaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%b-gaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%bgaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%b gaming%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('bgaming','b gaming','b-gaming','bgaminggames','bgaming games')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_bgaming', 'oxen_bgaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_bgaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_bgaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_bigtimegaming_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_bigtimegaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            // Keep first occurrence for duplicate names; local UID matching handles provider-specific duplicates.
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id IN ('62','63')
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%bigtimegaming%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%big time gaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%bigtimegaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%big-time-gaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%bigtimegaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%big time gaming%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('bigtimegaming','big time gaming','big-time-gaming','btg','bigtime')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='bigtimegaming', api_provider_name='BigTimeGaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'));
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'));
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot');
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id IN ('62','63')
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%bigtimegaming%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%big time gaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%bigtimegaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%big-time-gaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%bigtimegaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%big time gaming%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('bigtimegaming','big time gaming','big-time-gaming','btg','bigtime')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_bigtimegaming', 'oxen_bigtimegaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_bigtimegaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_bigtimegaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_btgaming_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_btgaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='109'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%btgaming%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%bt gaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%btgaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%bt-gaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%btgaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%bt gaming%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('btgaming','bt gaming','bt-gaming','betgaming','btgame')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='btgaming', api_provider_name='BtGaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'));
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'));
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot');
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='109'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%btgaming%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%bt gaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%btgaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%bt-gaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%btgaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%bt gaming%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('btgaming','bt gaming','bt-gaming','betgaming','btgame')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_btgaming', 'oxen_btgaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_btgaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_btgaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }

    function game_api_btgaming_provider_game_code($game) {
        if (!is_array($game)) { return ''; }
        $seedFile = __DIR__ . '/game_api_btgaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { return ''; }
        $localUid = isset($game['game_uid']) ? (string)$game['game_uid'] : '';
        $apiGameId = isset($game['api_game_id']) ? (string)$game['api_game_id'] : '';
        $apiGameCode = isset($game['api_game_code']) ? (string)$game['api_game_code'] : '';
        $nameNorm = game_api_normalize_name(isset($game['name']) ? $game['name'] : '');
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['provider_game_code'])) { continue; }
            if ($localUid !== '' && isset($row['local_game_uid']) && (string)$row['local_game_uid'] === $localUid) { return (string)$row['provider_game_code']; }
            if ($apiGameId !== '' && isset($row['api_game_id']) && (string)$row['api_game_id'] === $apiGameId) { return (string)$row['provider_game_code']; }
            if ($apiGameCode !== '' && isset($row['api_game_code']) && (string)$row['api_game_code'] === $apiGameCode) { return (string)$row['provider_game_code']; }
            if ($nameNorm !== '' && isset($row['game_name']) && game_api_normalize_name($row['game_name']) === $nameNorm) { return (string)$row['provider_game_code']; }
        }
        return '';
    }



    function game_api_seed_evolutionlive_mappings($conn) {
        game_api_ensure_schema($conn, false);
        
        $evoliveVersion = 'oxen_evolutionlive_2026_07_03_v2';
        $currentEvoliveVersion = game_api_get_setting($conn, 'game_api_mapping_seed_version_evolutionlive', '');
        if ($currentEvoliveVersion === $evoliveVersion) {
            return (int)game_api_get_setting($conn, 'game_api_mapping_seeded_count_evolutionlive', '0');
        }

        $seedFile = __DIR__ . '/game_api_evolutionlive_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByLocalUid = array();
        $mapByName = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['local_game_uid'])) { continue; }
            $mapByLocalUid[(string)$row['local_game_uid']] = $row;
            if (!empty($row['game_name'])) {
                $norm = game_api_normalize_name($row['game_name']);
                if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            }
        }

        $updated = 0;
        $matched = 0;
        $pending = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, g.api_provider_name, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id IN ('58','59')
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%evolution live%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%evolution-live%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) IN ('evolution live','evolution live (asia)','evolutionlive','evolutionliveasia')
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('evolutionlive','evolution live','evolution-live','evolutionliveasia','evolution-live-asia')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='evolutionlive', api_provider_name=?, api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    $apiGameId = '';
                    $apiType = isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'CasinoLive');
                    $providerName = ((string)$row['provider_id'] === '59') ? 'Evolution Live (Asia)' : 'Evolution Live';

                    if ($map && !empty($map['api_game_id'])) {
                        $apiGameId = (string)$map['api_game_id'];
                        $apiType = !empty($map['api_game_type']) ? (string)$map['api_game_type'] : $apiType;
                        $providerName = !empty($map['api_provider_name']) ? (string)$map['api_provider_name'] : $providerName;
                    } else {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) { $apiGameId = $existingId; }
                        elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) { $apiGameId = $existingCode; }
                    }

                    if ($apiGameId === '') { $pending++; continue; }
                    $matched++;
                    $stmt->bind_param('sssss', $apiGameId, $apiGameId, $providerName, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id IN ('58','59')
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%evolution live%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%evolution-live%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) IN ('evolution live','evolution live (asia)','evolutionlive','evolutionliveasia')
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('evolutionlive','evolution live','evolution-live','evolutionliveasia','evolution-live-asia')
                        )");

        // Auto-map and sort active Evolution Live games in front_category_games for Category 2 (Live Casino)
        if (game_api_table_exists($conn, 'front_category_games')) {
            $evolive_res = $conn->query("
                SELECT game_uid, name FROM games 
                WHERE status='active' 
                  AND (provider_id IN ('58','59') OR api_vendor_code='evolutionlive')
            ");
            if ($evolive_res) {
                $top_6_order = array(
                    'crazy time' => 1,
                    'crazy time a' => 2,
                    'monopoly live' => 3,
                    'mega ball' => 4,
                    'lightning roulette' => 5,
                    'dream catcher' => 6
                );
                
                $stmt_check = $conn->prepare("SELECT id, sort_order FROM front_category_games WHERE category_id=2 AND game_uid=? LIMIT 1");
                $stmt_insert = $conn->prepare("INSERT INTO front_category_games (category_id, game_uid, sort_order) VALUES (2, ?, ?)");
                $stmt_update = $conn->prepare("UPDATE front_category_games SET sort_order=? WHERE id=? LIMIT 1");
                
                $normal_sort = 10;
                while ($row = $evolive_res->fetch_assoc()) {
                    $uid = $row['game_uid'];
                    $name = $row['name'];
                    $lower_name = strtolower(trim((string)$name));
                    $sort = isset($top_6_order[$lower_name]) ? $top_6_order[$lower_name] : null;
                    if ($sort === null) {
                        $sort = $normal_sort;
                        $normal_sort += 2;
                    }
                    
                    if ($stmt_check) {
                        $stmt_check->bind_param('s', $uid);
                        $stmt_check->execute();
                        $res_check = $stmt_check->get_result();
                        if ($res_check && $res_check->num_rows > 0) {
                            $check_row = $res_check->fetch_assoc();
                            $existing_id = (int)$check_row['id'];
                            $existing_sort = (int)$check_row['sort_order'];
                            if ($existing_sort !== $sort && $stmt_update) {
                                $stmt_update->bind_param('ii', $sort, $existing_id);
                                $stmt_update->execute();
                            }
                        } else if ($stmt_insert) {
                            $stmt_insert->bind_param('si', $uid, $sort);
                            $stmt_insert->execute();
                        }
                    }
                }
                if ($stmt_check) $stmt_check->close();
                if ($stmt_insert) $stmt_insert->close();
                if ($stmt_update) $stmt_update->close();
            }
            game_api_jili_clear_category_cache();
        }

        game_api_set_setting($conn, 'game_api_mapping_seed_version_evolutionlive', $evoliveVersion);
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_evolutionlive', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_evolutionlive', (string)$matched);
        game_api_set_setting($conn, 'game_api_mapping_pending_count_evolutionlive', (string)$pending);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }



    function game_api_evolutionlive_find_existing_game_uid($conn, $gameName, $apiGameId) {
        game_api_ensure_schema($conn, false);
        $apiGameId = trim((string)$apiGameId);
        if ($apiGameId !== '') {
            $stmt = $conn->prepare("SELECT game_uid FROM games WHERE api_game_id=? OR api_game_code=? ORDER BY CASE WHEN provider_id='58' THEN 0 WHEN provider_id='59' THEN 1 ELSE 2 END, id ASC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $apiGameId, $apiGameId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) { $uid = (string)$res->fetch_assoc()['game_uid']; $stmt->close(); return $uid; }
                $stmt->close();
            }
        }

        $nameNorm = game_api_normalize_name($gameName);
        if ($nameNorm === '') { return ''; }
        $res = @$conn->query("SELECT game_uid, name FROM games WHERE provider_id IN ('58','59') OR LOWER(COALESCE(api_vendor_code,'')) IN ('evolutionlive','evolution live','evolution-live','evolutionliveasia','evolution-live-asia') ORDER BY CASE WHEN provider_id='58' THEN 0 WHEN provider_id='59' THEN 1 ELSE 2 END, id ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (game_api_normalize_name(isset($row['name']) ? $row['name'] : '') === $nameNorm) {
                    return (string)$row['game_uid'];
                }
            }
        }
        return '';
    }

    function game_api_evolutionlive_game_uid_exists($conn, $gameUid) {
        $gameUid = trim((string)$gameUid);
        if ($gameUid === '') { return true; }
        $stmt = $conn->prepare("SELECT id FROM games WHERE game_uid=? LIMIT 1");
        if (!$stmt) { return true; }
        $stmt->bind_param('s', $gameUid);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = ($res && $res->num_rows > 0);
        $stmt->close();
        return $exists;
    }

    function game_api_evolutionlive_next_game_uid($conn, $preferredUid) {
        $preferredUid = trim((string)$preferredUid);
        if ($preferredUid !== '' && !game_api_evolutionlive_game_uid_exists($conn, $preferredUid)) { return $preferredUid; }
        $base = 12001;
        $res = @$conn->query("SELECT MAX(CAST(game_uid AS UNSIGNED)) AS max_uid FROM games WHERE game_uid REGEXP '^[0-9]+$'");
        if ($res && $row = $res->fetch_assoc()) {
            $max = isset($row['max_uid']) ? (int)$row['max_uid'] : 0;
            if ($max >= $base) { $base = $max + 1; }
        }
        for ($i = 0; $i < 5000; $i++) {
            $candidate = (string)($base + $i);
            if (!game_api_evolutionlive_game_uid_exists($conn, $candidate)) { return $candidate; }
        }
        return 'evo_' . substr(md5($preferredUid . microtime(true)), 0, 12);
    }

    function game_api_evolutionlive_provider_image($conn) {
        $fallback = 'https://ik.imagekit.io/f4rqxekfu/brands/brand_58_1759739497_u136bxtGP.png';
        if (!game_api_table_exists($conn, 'game_providers')) { return $fallback; }
        $res = @$conn->query("SELECT image FROM game_providers WHERE provider_id='58' AND image IS NOT NULL AND image<>'' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!empty($row['image'])) { return (string)$row['image']; }
        }
        return $fallback;
    }

    function game_api_evolutionlive_add_front_category($conn, $gameUid, $categoryId, $sortOrder) {
        if (!game_api_table_exists($conn, 'front_category_games')) { return 0; }
        $gameUid = (string)$gameUid;
        $categoryId = (int)$categoryId;
        $sortOrder = (int)$sortOrder;
        $stmt = $conn->prepare("SELECT id FROM front_category_games WHERE category_id=? AND game_uid=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $categoryId, $gameUid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) { $stmt->close(); return 0; }
            $stmt->close();
        }
        $stmt = $conn->prepare("INSERT INTO front_category_games (category_id, game_uid, sort_order) VALUES (?, ?, ?)");
        if (!$stmt) { return 0; }
        $stmt->bind_param('isi', $categoryId, $gameUid, $sortOrder);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? 1 : 0;
    }

    function game_api_seed_evolutionlive_popular_games($conn) {
        game_api_ensure_schema($conn, false);
        if (!game_api_table_exists($conn, 'games')) { return 0; }
        $seedFile = __DIR__ . '/game_api_evolutionlive_popular_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed) || empty($seed)) { return 0; }

        $providerImage = game_api_evolutionlive_provider_image($conn);
        $changed = 0;
        $inserted = 0;
        $featured = 0;
        $mapped = 0;

        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $gameName = (string)$row['game_name'];
            $apiGameId = (string)$row['api_game_id'];
            $apiGameCode = !empty($row['api_game_code']) ? (string)$row['api_game_code'] : $apiGameId;
            $providerId = !empty($row['provider_id']) ? (string)$row['provider_id'] : '58';
            $providerName = !empty($row['api_provider_name']) ? (string)$row['api_provider_name'] : 'Evolution Live';
            $gameType = !empty($row['api_game_type']) ? (string)$row['api_game_type'] : 'CasinoLive';
            $category = !empty($row['category']) ? (string)$row['category'] : 'CasinoLive';
            $sortOrder = isset($row['sort_order']) ? (int)$row['sort_order'] : 999;
            $existingUid = game_api_evolutionlive_find_existing_game_uid($conn, $gameName, $apiGameId);

            if ($existingUid !== '') {
                $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='evolutionlive', api_provider_name=?, api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('sssss', $apiGameId, $apiGameCode, $providerName, $gameType, $existingUid);
                    if ($stmt->execute()) { $mapped += max(0, (int)$stmt->affected_rows); }
                    $stmt->close();
                }
                $gameUid = $existingUid;
            } else {
                $preferredUid = !empty($row['suggested_game_uid']) ? (string)$row['suggested_game_uid'] : '';
                $gameUid = game_api_evolutionlive_next_game_uid($conn, $preferredUid);
                $stmt = $conn->prepare("INSERT INTO games (game_uid, api_game_id, api_game_code, api_vendor_code, provider_id, api_provider_name, name, image, category, api_game_type, api_mapping_status, status) VALUES (?, ?, ?, 'evolutionlive', ?, ?, ?, ?, ?, ?, 'mapped', 'active')");
                if ($stmt) {
                    $stmt->bind_param('sssssssss', $gameUid, $apiGameId, $apiGameCode, $providerId, $providerName, $gameName, $providerImage, $category, $gameType);
                    if ($stmt->execute()) { $inserted++; $changed++; }
                    $stmt->close();
                }
            }

            // Feature selected games without changing the existing card design. Category 2 = Live Casino, Category 1 = Hot Game.
            $featured += game_api_evolutionlive_add_front_category($conn, $gameUid, 2, $sortOrder);
            $featured += game_api_evolutionlive_add_front_category($conn, $gameUid, 1, 100 + $sortOrder);
        }

        $changed += $mapped + $featured;
        game_api_set_setting($conn, 'game_api_evolutionlive_popular_seed_version', 'oxen_evolutionlive_popular_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_evolutionlive_popular_inserted_count', (string)$inserted);
        game_api_set_setting($conn, 'game_api_evolutionlive_popular_featured_count', (string)$featured);
        game_api_set_setting($conn, 'game_api_evolutionlive_popular_mapped_count', (string)$mapped);
        return $changed;
    }


    function game_api_seed_fachaigaming_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_fachaigaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='61'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%fachaigaming%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%fa chai%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%fachai%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%facai%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%fachaigaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%fa-chai%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%fachai%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%facai%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fachaigaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fa chai%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fachai%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('fachaigaming','fa chai gaming','fa-chai-gaming','fachai','fa chai','fa-chai','facai','facai gaming','fcgaming')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='fachaigaming', api_provider_name='FaChaiGaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'));
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'));
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot');
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='61'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%fachaigaming%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%fa chai%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%fachai%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%facai%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%fachaigaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%fa-chai%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%fachai%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%facai%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fachaigaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fa chai%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fachai%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('fachaigaming','fa chai gaming','fa-chai-gaming','fachai','fa chai','fa-chai','facai','facai gaming','fcgaming')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_fachaigaming', 'oxen_fachaigaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_fachaigaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_fachaigaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }

    function game_api_fachaigaming_provider_game_code($game) {
        if (!is_array($game)) { return ''; }
        $seedFile = __DIR__ . '/game_api_fachaigaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { return ''; }
        $localUid = isset($game['game_uid']) ? (string)$game['game_uid'] : '';
        $apiGameId = isset($game['api_game_id']) ? (string)$game['api_game_id'] : '';
        $apiGameCode = isset($game['api_game_code']) ? (string)$game['api_game_code'] : '';
        $nameNorm = game_api_normalize_name(isset($game['name']) ? $game['name'] : '');
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['provider_game_code'])) { continue; }
            if ($localUid !== '' && isset($row['local_game_uid']) && (string)$row['local_game_uid'] === $localUid) { return (string)$row['provider_game_code']; }
            if ($apiGameId !== '' && isset($row['api_game_id']) && (string)$row['api_game_id'] === $apiGameId) { return (string)$row['provider_game_code']; }
            if ($apiGameCode !== '' && isset($row['api_game_code']) && (string)$row['api_game_code'] === $apiGameCode) { return (string)$row['provider_game_code']; }
            if ($nameNorm !== '' && isset($row['game_name']) && game_api_normalize_name($row['game_name']) === $nameNorm) { return (string)$row['provider_game_code']; }
        }
        return '';
    }


    function game_api_seed_fastspin_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_fastspin_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='125'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%fastspin%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%fast spin%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%fast-spin%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%fastspin%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fastspin%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fast spin%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('fastspin','fast spin','fast-spin','fastspingaming','fastspin gaming')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='fastspin', api_provider_name='FastSpin', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'));
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot'));
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot');
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='125'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%fastspin%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%fast spin%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%fast-spin%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%fastspin%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fastspin%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%fast spin%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('fastspin','fast spin','fast-spin','fastspingaming','fastspin gaming')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_fastspin', 'oxen_fastspin_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_fastspin', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_fastspin', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_gameart_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_gameart_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='64'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%gameart%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%game art%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%game-art%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%gameart%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%gameart%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%game art%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('gameart','game art','game-art','gameartgaming','gameart gaming')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='gameart', api_provider_name='GameArt', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'slots'));
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'slots'));
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'slots');
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='64'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%gameart%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%game art%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%game-art%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%gameart%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%gameart%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%game art%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('gameart','game art','game-art','gameartgaming','gameart gaming')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_gameart', 'oxen_gameart_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_gameart', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_gameart', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_ideal_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_ideal_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='79'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%ideal%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%ideal%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%ideal%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('ideal','ideal gaming','ideal-gaming','idealgaming','ideal slot','idealslot')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='ideal', api_provider_name='IDEAL', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot Game'));
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot Game'));
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slot Game');
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='79'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%ideal%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%ideal%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%ideal%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('ideal','ideal gaming','ideal-gaming','idealgaming','ideal slot','idealslot')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_ideal', 'oxen_ideal_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_ideal', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_ideal', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_inout_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_inout_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='112'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%inout%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%in out%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%in-out%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%inout%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%in-out%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%in_out%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%inout%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%in out%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('inout','in out','in-out','inoutgaming','inout gaming','inoutgames','inout games')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='inout', api_provider_name='INOUT', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Instant'));
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Instant'));
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Instant');
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='112'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%inout%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%in out%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%in-out%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%inout%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%in-out%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%in_out%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%inout%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%in out%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('inout','in out','in-out','inoutgaming','inout gaming','inoutgames','inout games')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_inout', 'oxen_inout_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_inout', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_inout', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_lucksportgaming_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_lucksportgaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='83'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%lucksport%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%lucky sport%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%luck sport%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%lucksport%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%lucky-sport%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%luck-sport%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%lucksport%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%lucky sport%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('lucksportgaming','luck sport gaming','luck-sport-gaming','luckysport','lucky sport','lucky-sport','luck sport')
                        OR LOWER(COALESCE(g.name,'')) IN ('lucksportgaming','lucky sport gaming','luck sport gaming','luckysport')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='lucksportgaming', api_provider_name='LuckySport', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif (isset($mapByName['lucksportgaming'])) { $map = $mapByName['lucksportgaming']; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Sports Game');
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Sports Game');
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'Sports Game';
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='83'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%lucksport%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%lucky sport%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%luck sport%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%lucksport%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%lucky-sport%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%luck-sport%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%lucksport%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%lucky sport%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('lucksportgaming','luck sport gaming','luck-sport-gaming','luckysport','lucky sport','lucky-sport','luck sport')
                            OR LOWER(COALESCE(g.name,'')) IN ('lucksportgaming','lucky sport gaming','luck sport gaming','luckysport')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_lucksportgaming', 'oxen_lucksportgaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_lucksportgaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_lucksportgaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_microgaming_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_microgaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        $mapByProviderCode = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
            if (!empty($row['provider_game_code'])) { $mapByProviderCode[strtolower((string)$row['provider_game_code'])] = $row; }
            if (!empty($row['api_game_code']) && !preg_match('/^[a-f0-9]{32}$/i', (string)$row['api_game_code'])) { $mapByProviderCode[strtolower((string)$row['api_game_code'])] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='90'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%microgaming%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%micro gaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%microgaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%micro-gaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%microgaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%micro gaming%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('microgaming','micro gaming','micro-gaming','mg','mgplus','mglive','mglivegrand')
                        OR LOWER(COALESCE(g.api_game_code,'')) LIKE 'smg\\_%'
                        OR LOWER(COALESCE(g.api_game_code,'')) LIKE 'p1\\_%'
                        OR LOWER(COALESCE(g.api_game_code,'')) LIKE 'p2\\_%'
                        OR LOWER(COALESCE(g.api_game_code,'')) LIKE 'p5\\_%'
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='microgaming', api_provider_name='Microgaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($existingCode !== '' && isset($mapByProviderCode[strtolower($existingCode)])) { $map = $mapByProviderCode[strtolower($existingCode)]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingCode !== '' ? $existingCode : $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slots'));
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slots'));
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : (isset($row['category']) ? (string)$row['category'] : 'Slots');
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='90'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%microgaming%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%micro gaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%microgaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%micro-gaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%microgaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%micro gaming%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('microgaming','micro gaming','micro-gaming','mg','mgplus','mglive','mglivegrand')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_microgaming', 'oxen_microgaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_microgaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_microgaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING');
        return $updated;
    }


    function game_api_seed_mini_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_mini_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='104'
                        OR LOWER(COALESCE(gp.name,'')) = 'mini'
                        OR LOWER(COALESCE(gp.slug,'')) = 'mini'
                        OR LOWER(COALESCE(g.api_provider_name,'')) IN ('mini','mini games','mini-gaming','minigames')
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('mini','mini games','mini-games','minigames','mini gaming','mini-gaming')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='mini', api_provider_name='Mini', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'mini');
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'mini');
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'mini';
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='104'
                            OR LOWER(COALESCE(gp.name,'')) = 'mini'
                            OR LOWER(COALESCE(gp.slug,'')) = 'mini'
                            OR LOWER(COALESCE(g.api_provider_name,'')) IN ('mini','mini games','mini-gaming','minigames')
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('mini','mini games','mini-games','minigames','mini gaming','mini-gaming')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_mini', 'oxen_mini_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_mini', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_mini', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING,MINI,NETENT,ONGAMING,PRAGMATIC,SAGAMING,SBO,DPSPORTS,SEXY,T1,SPRIBE,TF,TFG,YEEBET');
        return $updated;
    }


    function game_api_seed_netent_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_netent_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='68'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%netent%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%netent%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%netent%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('netent','net-ent','net ent','evolution-netent','evolution netent','evolutioin-netent','evolutioin netent')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='netent', api_provider_name='NetEnt', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Slot');
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Slot');
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'Slot';
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='68'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%netent%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%netent%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%netent%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('netent','net-ent','net ent','evolution-netent','evolution netent','evolutioin-netent','evolutioin netent')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_netent', 'oxen_netent_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_netent', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_netent', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING,MINI,NETENT,ONGAMING,PRAGMATIC,SAGAMING,SBO,DPSPORTS,SEXY,T1,SPRIBE,TF,TFG,YEEBET');
        return $updated;
    }


    function game_api_seed_ongaming_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_ongaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id IN ('121','102')
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%ongaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%ongaming%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%on gaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%ongaming%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('ongaming','on-gaming','on gaming')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='ongaming', api_provider_name='OnGaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif ($norm === 'casino' && isset($mapByName['ongaming'])) { $map = $mapByName['ongaming']; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'casino');
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'casino');
                        } elseif (isset($mapByName['ongaming'])) {
                            $map = $mapByName['ongaming'];
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'casino';
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id IN ('121','102')
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%ongaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%ongaming%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%on gaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%ongaming%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('ongaming','on-gaming','on gaming')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_ongaming', 'oxen_ongaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_ongaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_ongaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING,MINI,NETENT,ONGAMING,PRAGMATIC,SAGAMING,SBO,DPSPORTS,SEXY,T1,SPRIBE,TF,TFG,YEEBET');
        return $updated;
    }


    function game_api_seed_sbo_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_sbo_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, g.api_vendor_code, g.api_provider_name, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='126'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%sbo%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%sbo%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%sportsbook%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%sportsbook%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%sbo%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%sportsbook%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('sbo','sbovirtualsport','sbovirtualsports','sportsbook')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code=?, api_provider_name=?, api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif (strpos($norm, 'virtual') !== false && isset($mapByName['sbovirtualsportsvs'])) { $map = $mapByName['sbovirtualsportsvs']; }
                    elseif (strpos($norm, '568win') !== false && isset($mapByName['568winsportsbook'])) { $map = $mapByName['568winsportsbook']; }
                    elseif (strpos($norm, 'sportsbook') !== false && strpos($norm, '568win') === false && isset($mapByName['sbosportsbook'])) { $map = $mapByName['sbosportsbook']; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_vendor_code' => game_api_sbo_vendor_for_game($row), 'api_provider_name' => 'SBO', 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Sports Game');
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_vendor_code' => game_api_sbo_vendor_for_game($row), 'api_provider_name' => 'SBO', 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Sports Game');
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $vendor = isset($map['api_vendor_code']) && $map['api_vendor_code'] !== '' ? (string)$map['api_vendor_code'] : game_api_sbo_vendor_for_game($row);
                    $providerName = isset($map['api_provider_name']) && $map['api_provider_name'] !== '' ? (string)$map['api_provider_name'] : 'SBO';
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'Sports Game';
                    $stmt->bind_param('ssssss', $apiGameId, $apiGameCode, $vendor, $providerName, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
            $res->free();
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='126'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%sbo%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%sbo%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%sportsbook%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%sportsbook%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%sbo%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%sportsbook%'
                        )");

        if ($matched > 0) { game_api_set_setting($conn, 'sbo_mapping_seeded_count', (string)$matched); }
        return $updated;
    }


    function game_api_seed_dpsports_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_dpsports_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, g.api_vendor_code, g.api_provider_name, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id IN ('94','95')
                        OR LOWER(COALESCE(gp.name,'')) IN ('dpesports','dpsports')
                        OR LOWER(COALESCE(gp.slug,'')) IN ('dpesports','dpsports')
                        OR LOWER(COALESCE(g.api_provider_name,'')) IN ('dpesports','dp esports','dp-esports','dpsports','dp sports','dp-sports')
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('dpesports','dp esports','dp-esports','dpsports','dp sports','dp-sports')
                        OR LOWER(REPLACE(COALESCE(g.name,''),' ','')) IN ('dpesportsgaming','dpsportsgaming')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code=?, api_provider_name=?, api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif (strpos($norm, 'esports') !== false && isset($mapByName['dpesportsgaming'])) { $map = $mapByName['dpesportsgaming']; }
                    elseif (strpos($norm, 'sports') !== false && isset($mapByName['dpsportsgaming'])) { $map = $mapByName['dpsportsgaming']; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '') {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => ($existingCode !== '' ? $existingCode : $existingId), 'api_vendor_code' => game_api_dpsports_vendor_for_game($row), 'api_provider_name' => (game_api_dpsports_vendor_for_game($row) === 'dpesports' ? 'DpEsports' : 'DpSports'), 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Sports Game');
                        } elseif ($existingCode !== '') {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_vendor_code' => game_api_dpsports_vendor_for_game($row), 'api_provider_name' => (game_api_dpsports_vendor_for_game($row) === 'dpesports' ? 'DpEsports' : 'DpSports'), 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Sports Game');
                        } elseif ($localUid !== '') {
                            $map = array('api_game_id' => $localUid, 'api_game_code' => $localUid, 'api_vendor_code' => game_api_dpsports_vendor_for_game($row), 'api_provider_name' => (game_api_dpsports_vendor_for_game($row) === 'dpesports' ? 'DpEsports' : 'DpSports'), 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'Sports Game');
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $vendor = isset($map['api_vendor_code']) && $map['api_vendor_code'] !== '' ? (string)$map['api_vendor_code'] : game_api_dpsports_vendor_for_game($row);
                    $providerName = isset($map['api_provider_name']) && $map['api_provider_name'] !== '' ? (string)$map['api_provider_name'] : ($vendor === 'dpesports' ? 'DpEsports' : 'DpSports');
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'Sports Game';
                    $stmt->bind_param('ssssss', $apiGameId, $apiGameCode, $vendor, $providerName, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
            $res->free();
        }

        if ($matched > 0) { game_api_set_setting($conn, 'dpsports_mapping_seeded_count', (string)$matched); }
        game_api_set_setting($conn, 'game_api_mapping_seed_version_dpsports', 'oxen_dpsports_2026_06_19_v1');
        return $updated;
    }


    function game_api_seed_sagaming_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_sagaming_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='89'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%sagaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%sagaming%'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%sa gaming%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%sa-gaming%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%sagaming%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('sagaming','sa-gaming','sa gaming')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='sagaming', api_provider_name='SaGaming', api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif (isset($mapByName['sagaming'])) { $map = $mapByName['sagaming']; }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'CasinoLive');
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'CasinoLive');
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'CasinoLive';
                    $stmt->bind_param('ssss', $apiGameId, $apiGameCode, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
            $res->free();
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='89'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%sagaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%sagaming%'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%sa gaming%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%sa-gaming%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%sagaming%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('sagaming','sa-gaming','sa gaming')
                        )");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_sagaming', 'oxen_sagaming_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_sagaming', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_sagaming', (string)$matched);
        game_api_set_setting($conn, 'game_api_active_provider_phase', 'JILI,PGSOFT,SABA,UNITEDGAMING,JDB,TADAGAMING,CQ9,100HP,2J,BNG3OKS_ROW,5GGAMING,9WICKET,AMIGO,AOG,ASTAR,BGAMING,BIGTIMEGAMING,BTGAMING,EVOLUTIONLIVE,FACHAIGAMING,FASTSPIN,GAMEART,IDEAL,INOUT,LUCKSPORTGAMING,MICROGAMING,MINI,NETENT,ONGAMING,PRAGMATIC,SAGAMING,SBO,DPSPORTS,SEXY,T1,SPRIBE,TF,TFG,YEEBET');
        return $updated;
    }


    function game_api_seed_sexy_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_sexy_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, g.api_vendor_code, g.api_provider_name, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id='88'
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%sexy%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%sexy%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%sexy%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('sexy','sexy_video','sexyvideo','sexy-gaming','sexy gaming')
                        OR (g.provider_id='140' AND LOWER(COALESCE(g.name,'')) LIKE 'sexy%')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code=?, api_provider_name=?, api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif (strpos($norm, 'sexy') === 0) {
                        $trimmed = substr($norm, 4);
                        if (isset($mapByName[$trimmed])) { $map = $mapByName[$trimmed]; }
                        elseif ($trimmed === 'dragontiger' && isset($mapByName['dragontiger'])) { $map = $mapByName['dragontiger']; }
                    }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => $existingId, 'api_vendor_code' => game_api_sexy_vendor_for_game($row), 'api_provider_name' => 'SexyGaming', 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'CasinoLive');
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_vendor_code' => game_api_sexy_vendor_for_game($row), 'api_provider_name' => 'SexyGaming', 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : 'CasinoLive');
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $vendor = isset($map['api_vendor_code']) && $map['api_vendor_code'] !== '' ? (string)$map['api_vendor_code'] : game_api_sexy_vendor_for_game($row);
                    $providerName = isset($map['api_provider_name']) && $map['api_provider_name'] !== '' ? (string)$map['api_provider_name'] : ($vendor === 'sexy_video' ? 'Sexy Video' : 'SexyGaming');
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : 'CasinoLive';
                    $stmt->bind_param('ssssss', $apiGameId, $apiGameCode, $vendor, $providerName, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
            $res->free();
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (
                            g.provider_id='88'
                            OR LOWER(COALESCE(gp.name,'')) LIKE '%sexy%'
                            OR LOWER(COALESCE(gp.slug,'')) LIKE '%sexy%'
                            OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%sexy%'
                            OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('sexy','sexy_video','sexyvideo','sexy-gaming','sexy gaming')
                            OR (g.provider_id='140' AND LOWER(COALESCE(g.name,'')) LIKE 'sexy%')
                        )");

        game_api_set_setting($conn, 'game_api_mapping_seed_version_sexy', 'oxen_sexy_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_sexy', (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_sexy', (string)$matched);
        return $updated;
    }




    function game_api_seed_generic_external_provider_mappings($conn, $providerKey, $seedFileName, $providerIds, $vendorCode, $providerName, $defaultType, $aliasTerms) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/' . $seedFileName;
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByName = array();
        $mapByLocalUid = array();
        $firstMap = null;
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            if ($firstMap === null) { $firstMap = $row; }
            $norm = game_api_normalize_name($row['game_name']);
            if ($norm !== '' && !isset($mapByName[$norm])) { $mapByName[$norm] = $row; }
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
        }

        $conditions = array();
        foreach ((array)$providerIds as $pid) {
            $pid = preg_replace('/[^0-9]/', '', (string)$pid);
            if ($pid !== '') { $conditions[] = "g.provider_id='" . $conn->real_escape_string($pid) . "'"; }
        }
        foreach ((array)$aliasTerms as $term) {
            $term = strtolower(trim((string)$term));
            if ($term === '') { continue; }
            $safe = $conn->real_escape_string($term);
            $conditions[] = "LOWER(COALESCE(gp.name,'')) LIKE '%" . $safe . "%'";
            $conditions[] = "LOWER(COALESCE(gp.slug,'')) LIKE '%" . $safe . "%'";
            $conditions[] = "LOWER(COALESCE(g.api_provider_name,'')) LIKE '%" . $safe . "%'";
            $conditions[] = "LOWER(COALESCE(g.api_vendor_code,''))='" . $safe . "'";
        }
        if (!$conditions) { return 0; }
        $where = implode(' OR ', $conditions);

        $updated = 0;
        $matched = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, g.api_vendor_code, g.api_provider_name, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (" . $where . ")";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code=?, api_provider_name=?, api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $map = null;
                    if (isset($mapByLocalUid[$localUid])) { $map = $mapByLocalUid[$localUid]; }
                    elseif ($norm !== '' && isset($mapByName[$norm])) { $map = $mapByName[$norm]; }
                    elseif ($norm !== '') {
                        foreach ($mapByName as $seedNorm => $seedRow) {
                            if ($seedNorm !== '' && ($norm === $seedNorm || strpos($norm, $seedNorm) !== false || strpos($seedNorm, $norm) !== false)) { $map = $seedRow; break; }
                        }
                    }

                    if (!$map) {
                        $existingId = isset($row['api_game_id']) ? trim((string)$row['api_game_id']) : '';
                        $existingCode = isset($row['api_game_code']) ? trim((string)$row['api_game_code']) : '';
                        if ($existingId !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingId)) {
                            $map = array('api_game_id' => $existingId, 'api_game_code' => ($existingCode !== '' ? $existingCode : $existingId), 'api_vendor_code' => $vendorCode, 'api_provider_name' => $providerName, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : $defaultType);
                        } elseif ($existingCode !== '' && preg_match('/^[a-f0-9]{32}$/i', $existingCode)) {
                            $map = array('api_game_id' => $existingCode, 'api_game_code' => $existingCode, 'api_vendor_code' => $vendorCode, 'api_provider_name' => $providerName, 'api_game_type' => isset($row['api_game_type']) && $row['api_game_type'] !== '' ? (string)$row['api_game_type'] : $defaultType);
                        } elseif (count($seed) === 1 && $firstMap !== null) {
                            $map = $firstMap;
                        }
                    }

                    if (!$map || empty($map['api_game_id'])) { continue; }
                    $matched++;
                    $apiGameId = (string)$map['api_game_id'];
                    $apiGameCode = isset($map['api_game_code']) && $map['api_game_code'] !== '' ? (string)$map['api_game_code'] : $apiGameId;
                    $vendor = isset($map['api_vendor_code']) && $map['api_vendor_code'] !== '' ? (string)$map['api_vendor_code'] : $vendorCode;
                    $pName = isset($map['api_provider_name']) && $map['api_provider_name'] !== '' ? (string)$map['api_provider_name'] : $providerName;
                    $apiType = isset($map['api_game_type']) && $map['api_game_type'] !== '' ? (string)$map['api_game_type'] : $defaultType;
                    $stmt->bind_param('ssssss', $apiGameId, $apiGameCode, $vendor, $pName, $apiType, $localUid);
                    if ($stmt->execute()) { $updated += max(0, (int)$stmt->affected_rows); }
                }
                $stmt->close();
            }
            $res->free();
        }

        @$conn->query("UPDATE games g LEFT JOIN game_providers gp ON gp.provider_id=g.provider_id
                      SET g.api_mapping_status='pending'
                      WHERE (g.api_game_code IS NULL OR g.api_game_code='' OR g.api_game_id IS NULL OR g.api_game_id='')
                        AND (" . $where . ")");
        game_api_set_setting($conn, 'game_api_mapping_seed_version_' . $providerKey, 'oxen_' . $providerKey . '_2026_06_11_v1');
        game_api_set_setting($conn, 'game_api_mapping_seeded_count_' . $providerKey, (string)$updated);
        game_api_set_setting($conn, 'game_api_mapping_matched_count_' . $providerKey, (string)$matched);
        return $updated;
    }

    function game_api_seed_t1_mappings($conn) {
        return game_api_seed_generic_external_provider_mappings($conn, 't1', 'game_api_t1_mapping_seed.php', array('80'), 't1', 'T1', 'Classic Games', array('t1', 't1 gaming', 't1games'));
    }

    function game_api_seed_spribe_mappings($conn) {
        return game_api_seed_generic_external_provider_mappings($conn, 'spribe', 'game_api_spribe_mapping_seed.php', array('57'), 'spribe', 'Spribe', 'Crash', array('spribe'));
    }

    function game_api_seed_tf_mappings($conn) {
        return game_api_seed_generic_external_provider_mappings($conn, 'tf', 'game_api_tf_mapping_seed.php', array('85'), 'tf', 'TFGaming', 'Esports', array('tf', 'tf gaming', 'tfgaming'));
    }

    function game_api_seed_tfg_mappings($conn) {
        return game_api_seed_generic_external_provider_mappings($conn, 'tfg', 'game_api_tfg_mapping_seed.php', array('86'), 'tfg', 'TFG', 'Esports', array('tfg', 'tfgaming', 'tf gaming'));
    }

    function game_api_seed_yeebet_mappings($conn) {
        return game_api_seed_generic_external_provider_mappings($conn, 'yeebet', 'game_api_yeebet_mapping_seed.php', array('60'), 'yeebet', 'YEEBET', 'CasinoLive', array('yeebet', 'yee bet'));
    }


    function game_api_site_url($path = '/') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        if ($path === '' || $path[0] !== '/') { $path = '/' . $path; }
        return $scheme . '://' . $host . $path;
    }

    function game_api_log($conn, $data) {
        game_api_ensure_schema($conn, false);
        $stmt = $conn->prepare("INSERT INTO game_api_logs (user_id, local_game_uid, api_game_id, endpoint, request_data, response_data, status, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) { return false; }
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $localGameUid = isset($data['local_game_uid']) ? (string)$data['local_game_uid'] : null;
        $apiGameId = isset($data['api_game_id']) ? (string)$data['api_game_id'] : null;
        $endpoint = isset($data['endpoint']) ? (string)$data['endpoint'] : null;
        $requestData = isset($data['request_data']) ? (is_string($data['request_data']) ? $data['request_data'] : json_encode($data['request_data'], JSON_UNESCAPED_UNICODE)) : null;
        $responseData = isset($data['response_data']) ? (is_string($data['response_data']) ? $data['response_data'] : json_encode($data['response_data'], JSON_UNESCAPED_UNICODE)) : null;
        $status = isset($data['status']) ? substr((string)$data['status'], 0, 50) : null;
        $message = isset($data['message']) ? substr((string)$data['message'], 0, 250) : null;
        if ($localGameUid !== null) { $localGameUid = substr($localGameUid, 0, 100); }
        if ($apiGameId !== null) { $apiGameId = substr($apiGameId, 0, 120); }
        if ($endpoint !== null) { $endpoint = substr($endpoint, 0, 255); }
        $stmt->bind_param('isssssss', $userId, $localGameUid, $apiGameId, $endpoint, $requestData, $responseData, $status, $message);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function game_api_find_game($conn, $localGameUid) {
        game_api_ensure_schema($conn, false);
        $stmt = $conn->prepare("SELECT g.*, gp.status AS provider_status, gp.name AS provider_name, gp.slug AS provider_slug FROM games g LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id WHERE g.game_uid=? LIMIT 1");
        if (!$stmt) { return null; }
        $stmt->bind_param('s', $localGameUid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }

    function game_api_local_uid_by_api_id($conn, $apiGameId) {
        game_api_ensure_schema($conn, false);
        if ($apiGameId === '') { return ''; }
        $stmt = $conn->prepare("SELECT game_uid FROM games WHERE api_game_id=? OR api_game_code=? LIMIT 1");
        if (!$stmt) { return ''; }
        $stmt->bind_param('ss', $apiGameId, $apiGameId);
        $stmt->execute();
        $res = $stmt->get_result();
        $uid = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['game_uid'] : '';
        $stmt->close();
        return $uid;
    }


    function game_api_seed_pragmatic_mappings($conn) {
        game_api_ensure_schema($conn, false);
        $seedFile = __DIR__ . '/game_api_pragmatic_mapping_seed.php';
        $seed = file_exists($seedFile) ? include $seedFile : array();
        if (!is_array($seed)) { $seed = array(); }

        $mapByProviderName = array();
        $mapByLocalUid = array();
        foreach ($seed as $row) {
            if (!is_array($row) || empty($row['game_name']) || empty($row['api_game_id'])) { continue; }
            $providerId = isset($row['provider_id']) ? (string)$row['provider_id'] : '';
            $norm = game_api_normalize_name($row['game_name']);
            if (!empty($row['local_game_uid'])) { $mapByLocalUid[(string)$row['local_game_uid']] = $row; }
            if ($providerId !== '' && $norm !== '') { $mapByProviderName[$providerId . '|' . $norm] = $row; }
        }

        $updated = 0;
        $sql = "SELECT g.game_uid, g.name, g.provider_id, g.category, g.api_game_id, g.api_game_code, g.api_game_type, gp.name AS provider_name, gp.slug AS provider_slug
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE (
                        g.provider_id IN ('53','54','55','56')
                        OR LOWER(COALESCE(gp.name,'')) LIKE '%pragmatic%'
                        OR LOWER(COALESCE(gp.slug,'')) LIKE '%pragmatic%'
                        OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%pragmatic%'
                        OR LOWER(COALESCE(g.api_vendor_code,'')) IN ('pragmatic','pragmaticplay','pragmatic-play','pragmatic play')
                  )";
        $res = @$conn->query($sql);
        if ($res) {
            $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code='pragmatic', api_provider_name=?, api_game_type=?, api_mapping_status='mapped' WHERE game_uid=? LIMIT 1");
            if ($stmt) {
                while ($row = $res->fetch_assoc()) {
                    $localUid = (string)$row['game_uid'];
                    $providerId = isset($row['provider_id']) ? (string)$row['provider_id'] : '';
                    $norm = game_api_normalize_name(isset($row['name']) ? $row['name'] : '');
                    $match = null;
                    if (isset($mapByLocalUid[$localUid])) { $match = $mapByLocalUid[$localUid]; }
                    elseif ($providerId !== '' && $norm !== '' && isset($mapByProviderName[$providerId . '|' . $norm])) { $match = $mapByProviderName[$providerId . '|' . $norm]; }
                    if (!$match) { continue; }
                    $apiId = (string)$match['api_game_id'];
                    $apiCode = !empty($match['provider_game_code']) ? (string)$match['provider_game_code'] : (!empty($match['api_game_code']) ? (string)$match['api_game_code'] : $apiId);
                    $providerName = !empty($match['api_provider_name']) ? (string)$match['api_provider_name'] : 'PragmaticPlay';
                    $gameType = !empty($match['api_game_type']) ? (string)$match['api_game_type'] : (!empty($row['api_game_type']) ? (string)$row['api_game_type'] : (!empty($row['category']) ? (string)$row['category'] : 'Video Slots'));
                    $stmt->bind_param('sssss', $apiId, $apiCode, $providerName, $gameType, $localUid);
                    if ($stmt->execute()) { $updated++; }
                }
                $stmt->close();
            }
            $res->free();
        }
        game_api_set_setting($conn, 'game_api_pragmatic_mapping_count', (string)$updated);
        return $updated;
    }

    function game_api_apply_turnover_progress($conn, $payload) {
        if (!is_array($payload) || !isset($payload['user_id'])) { return false; }
        $type = isset($payload['type']) ? strtolower((string)$payload['type']) : '';
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0;
        $userId = intval($payload['user_id']);
        if ($userId <= 0 || $amount <= 0 || $type !== 'bet') { return false; }
        if (!game_api_table_exists($conn, 'users')) { return false; }
        if (!game_api_column_exists($conn, 'users', 'turnover_completed')) {
            game_api_ensure_column($conn, 'users', 'turnover_completed', "DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `turnover_target`");
        }
        $stmt = $conn->prepare("UPDATE users SET turnover_completed = COALESCE(turnover_completed,0) + ? WHERE id=?");
        if (!$stmt) { return false; }
        $stmt->bind_param('di', $amount, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function game_api_insert_bet_history($conn, $payload) {
        if (!game_api_table_exists($conn, 'game_bet_history')) { return false; }
        $columns = array();
        $values = array();
        $types = '';

        $available = array('user_id','username','vendor_code','game_uid','round_id','transaction_id','type','amount','balance_after','currency');
        foreach ($available as $col) {
            if (game_api_column_exists($conn, 'game_bet_history', $col) && array_key_exists($col, $payload)) {
                $columns[] = $col;
                $values[] = $payload[$col];
                if (in_array($col, array('user_id'))) { $types .= 'i'; }
                elseif (in_array($col, array('amount','balance_after'))) { $types .= 'd'; }
                else { $types .= 's'; }
            }
        }
        if (empty($columns)) { return false; }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO game_bet_history (`" . implode('`,`', $columns) . "`) VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { return false; }

        $refs = array();
        $refs[] = $types;
        for ($i=0; $i<count($values); $i++) { $refs[] = &$values[$i]; }
        call_user_func_array(array($stmt, 'bind_param'), $refs);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) { game_api_apply_turnover_progress($conn, $payload); }
        return $ok;
    }

    function game_api_json_response($payload) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    function game_api_fetch_ezugi_image_from_api($game) {
        $game_uid = isset($game['game_uid']) ? $game['game_uid'] : '';
        $api_game_code = isset($game['api_game_code']) ? $game['api_game_code'] : '';
        $api_game_id = isset($game['api_game_id']) ? $game['api_game_id'] : '';
        
        $cache_dir = __DIR__ . '/../cache';
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0755, true);
        }
        $cache_file = $cache_dir . '/ezugi_images.json';
        $cache_time = 86400; // 24 hours
        
        $images = array();
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
            $images = json_decode(file_get_contents($cache_file), true);
        }
        
        if (!is_array($images) || empty($images)) {
            $images = array();
            $url = 'https://providers.gambllyapi.com/?category=EZUGI';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $res = curl_exec($ch);
            curl_close($ch);
            
            if ($res) {
                $data = json_decode($res, true);
                $raw_games = array();
                if (is_array($data)) {
                    if (isset($data['data']) && is_array($data['data'])) {
                        $raw_games = $data['data'];
                    } else {
                        $raw_games = $data;
                    }
                }
                
                foreach ($raw_games as $rg) {
                    if (!is_array($rg)) continue;
                    $rg_uid = isset($rg['game_uid']) ? $rg['game_uid'] : (isset($rg['game_id']) ? $rg['game_id'] : (isset($rg['game_code']) ? $rg['game_code'] : (isset($rg['code']) ? $rg['code'] : (isset($rg['id']) ? $rg['id'] : ''))));
                    $rg_img = isset($rg['image']) ? $rg['image'] : (isset($rg['image_url']) ? $rg['image_url'] : (isset($rg['banner']) ? $rg['banner'] : (isset($rg['thumbnail']) ? $rg['thumbnail'] : (isset($rg['img']) ? $rg['img'] : (isset($rg['imageUrl']) ? $rg['imageUrl'] : '')))));
                    if ($rg_uid !== '' && $rg_img !== '') {
                        $images[strtolower(trim((string)$rg_uid))] = trim((string)$rg_img);
                    }
                }
                
                if (!empty($images)) {
                    @file_put_contents($cache_file, json_encode($images));
                }
            }
        }
        
        $keys_to_check = array(
            strtolower(trim((string)$game_uid)),
            strtolower(trim((string)str_replace('ezugi_', '', $game_uid))),
            strtolower(trim((string)$api_game_code)),
            strtolower(trim((string)$api_game_id))
        );
        
        foreach ($keys_to_check as $k) {
            if ($k !== '' && isset($images[$k])) {
                return $images[$k];
            }
        }
        
        return '';
    }

    function game_api_fetch_jili_image_from_api($game) {
        $game_uid = isset($game['game_uid']) ? trim((string)$game['game_uid']) : '';
        $api_game_code = isset($game['api_game_code']) ? trim((string)$game['api_game_code']) : '';
        $api_game_id = isset($game['api_game_id']) ? trim((string)$game['api_game_id']) : '';
        
        $cache_dir = dirname(__DIR__) . '/cache';
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0777, true);
        }
        $cache_file = $cache_dir . '/jili_images.json';
        $cache_time = 86400; // 24 hours
        
        $images = array();
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
            $images = json_decode(file_get_contents($cache_file), true);
        }
        
        if (!is_array($images) || empty($images)) {
            $images = array();
            $url = 'https://providers.gambllyapi.com/?category=JL';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $res = curl_exec($ch);
            curl_close($ch);
            
            if ($res) {
                $data = json_decode($res, true);
                $raw_games = array();
                if (is_array($data)) {
                    if (isset($data['data']) && is_array($data['data'])) {
                        $raw_games = $data['data'];
                    } else {
                        $raw_games = $data;
                    }
                }
                
                foreach ($raw_games as $rg) {
                    if (!is_array($rg)) continue;
                    $rg_uid = isset($rg['game_uid']) ? $rg['game_uid'] : (isset($rg['game_id']) ? $rg['game_id'] : (isset($rg['game_code']) ? $rg['game_code'] : (isset($rg['code']) ? $rg['code'] : (isset($rg['id']) ? $rg['id'] : ''))));
                    $rg_img = isset($rg['image']) ? $rg['image'] : (isset($rg['image_url']) ? $rg['image_url'] : (isset($rg['banner']) ? $rg['banner'] : (isset($rg['thumbnail']) ? $rg['thumbnail'] : (isset($rg['img']) ? $rg['img'] : (isset($rg['imageUrl']) ? $rg['imageUrl'] : '')))));
                    if ($rg_uid !== '' && $rg_img !== '') {
                        $images[strtolower(trim((string)$rg_uid))] = trim((string)$rg_img);
                    }
                }
                
                if (!empty($images)) {
                    @file_put_contents($cache_file, json_encode($images));
                }
            }
        }
        
        $keys_to_check = array(
            strtolower(trim((string)$game_uid)),
            strtolower(trim((string)str_replace('jili_', '', $game_uid))),
            strtolower(trim((string)$api_game_code)),
            strtolower(trim((string)$api_game_id))
        );
        
        foreach ($keys_to_check as $k) {
            if ($k !== '' && isset($images[$k])) {
                $found_img = $images[$k];
                // Try updating database so we don't have to keep doing this
                global $conn;
                if (isset($conn) && !$conn->connect_error && $game_uid !== '') {
                    $update_sql = "UPDATE games SET image = ? WHERE game_uid = ? AND (image = '' OR image LIKE '%placehold.co%' OR image LIKE '%default_game%' OR image LIKE '%code_%') LIMIT 1";
                    $stmt = $conn->prepare($update_sql);
                    if ($stmt) {
                        $stmt->bind_param('ss', $found_img, $game_uid);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                return $found_img;
            }
        }
        
        return '';
    }

    function game_api_fetch_evolutionlive_image_from_api($game) {
        $game_uid = isset($game['game_uid']) ? trim((string)$game['game_uid']) : '';
        $api_game_code = isset($game['api_game_code']) ? trim((string)$game['api_game_code']) : '';
        $api_game_id = isset($game['api_game_id']) ? trim((string)$game['api_game_id']) : '';
        
        $cache_dir = dirname(__DIR__) . '/cache';
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0777, true);
        }
        $cache_file = $cache_dir . '/evolutionlive_images.json';
        $cache_time = 86400; // 24 hours
        
        $images = array();
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
            $images = json_decode(file_get_contents($cache_file), true);
        }
        
        if (!is_array($images) || empty($images)) {
            $images = array();
            $url = 'https://providers.gambllyapi.com/?category=EVOLIVEROW';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $res = curl_exec($ch);
            curl_close($ch);
            
            if ($res) {
                $data = json_decode($res, true);
                $raw_games = array();
                if (is_array($data)) {
                    if (isset($data['data']) && is_array($data['data'])) {
                        $raw_games = $data['data'];
                    } else {
                        $raw_games = $data;
                    }
                }
                
                foreach ($raw_games as $rg) {
                    if (!is_array($rg)) continue;
                    $rg_uid = isset($rg['game_uid']) ? $rg['game_uid'] : (isset($rg['game_id']) ? $rg['game_id'] : (isset($rg['game_code']) ? $rg['game_code'] : (isset($rg['code']) ? $rg['code'] : (isset($rg['id']) ? $rg['id'] : ''))));
                    $rg_img = isset($rg['image']) ? $rg['image'] : (isset($rg['image_url']) ? $rg['image_url'] : (isset($rg['banner']) ? $rg['banner'] : (isset($rg['thumbnail']) ? $rg['thumbnail'] : (isset($rg['img']) ? $rg['img'] : (isset($rg['imageUrl']) ? $rg['imageUrl'] : '')))));
                    if ($rg_uid !== '' && $rg_img !== '') {
                        $images[strtolower(trim((string)$rg_uid))] = trim((string)$rg_img);
                    }
                }
                
                if (!empty($images)) {
                    @file_put_contents($cache_file, json_encode($images));
                }
            }
        }
        
        $keys_to_check = array(
            strtolower(trim((string)$game_uid)),
            strtolower(trim((string)str_replace('evolutionlive_', '', $game_uid))),
            strtolower(trim((string)$api_game_code)),
            strtolower(trim((string)$api_game_id))
        );
        
        foreach ($keys_to_check as $k) {
            if ($k !== '' && isset($images[$k])) {
                $found_img = $images[$k];
                // Try updating database so we don't have to keep doing this
                global $conn;
                if (isset($conn) && !$conn->connect_error && $game_uid !== '') {
                    $update_sql = "UPDATE games SET image = ? WHERE game_uid = ? AND (image = '' OR image LIKE '%placehold.co%' OR image LIKE '%default_game%' OR image LIKE '%brand_58%' OR image LIKE '%brand_59%') LIMIT 1";
                    $stmt = $conn->prepare($update_sql);
                    if ($stmt) {
                        $stmt->bind_param('ss', $found_img, $game_uid);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                return $found_img;
            }
        }
        
        return '';
    }

    function game_api_prepare_game_image($game) {
        $img = isset($game['image']) ? trim($game['image']) : '';
        if ($img === '' || strpos($img, 'placehold.co') !== false || strpos($img, 'default_game') !== false || strpos($img, 'code_') !== false || strpos($img, 'brand_58') !== false || strpos($img, 'brand_59') !== false) {
            $is_ezugi = false;
            $is_jili = false;
            $is_evolutionlive = false;
            $provider_id = isset($game['provider_id']) ? (string)$game['provider_id'] : '';
            $provider_name = isset($game['provider_name']) ? strtolower($game['provider_name']) : '';
            $provider_slug = isset($game['provider_slug']) ? strtolower($game['provider_slug']) : '';
            $game_uid = isset($game['game_uid']) ? strtolower($game['game_uid']) : '';
            
            if ($provider_id === '78' || strpos($provider_name, 'ezugi') !== false || strpos($provider_slug, 'ezugi') !== false || strpos($game_uid, 'ezugi_') === 0) {
                $is_ezugi = true;
            }
            
            if ($provider_id === '49' || strpos($provider_name, 'jili') !== false || strpos($provider_slug, 'jili') !== false || strpos($game_uid, 'jili_') === 0) {
                $is_jili = true;
            }
            
            if ($provider_id === '58' || $provider_id === '59' || strpos($provider_name, 'evolution') !== false || strpos($provider_slug, 'evolution') !== false || strpos($game_uid, 'evolutionlive') !== false) {
                $is_evolutionlive = true;
            }
            
            if ($is_ezugi) {
                $ezugi_img = game_api_fetch_ezugi_image_from_api($game);
                if ($ezugi_img !== '') {
                    return $ezugi_img;
                }
            }
            
            if ($is_jili) {
                $jili_img = game_api_fetch_jili_image_from_api($game);
                if ($jili_img !== '') {
                    return $jili_img;
                }
            }
            
            if ($is_evolutionlive) {
                $evolive_img = game_api_fetch_evolutionlive_image_from_api($game);
                if ($evolive_img !== '') {
                    return $evolive_img;
                }
            }
        }
        return $img !== '' ? $img : 'https://placehold.co/120x120/0d3d2c/ffd84d?text=GAME';
    }
}
?>
