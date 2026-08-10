<?php
/**
 * Gamblly API integration helper.
 *
 * This file is intentionally self-contained and only uses the existing users,
 * games and game_api_* tables already present in the site.
 */

if (!function_exists('gamblly_api_default_config')) {
    function gamblly_api_default_config() {
        return array(
            'launch_url' => 'https://game.gambllyapi.com/production/v1/gameLaunch.php',
            'withdraw_url' => 'https://game.gambllyapi.com/production/v1/getWithdraw.php',
            'balance_url' => 'https://game.gambllyapi.com/production/v1/getBalance.php',
            'callback_pull_url' => 'https://game.gambllyapi.com/production/v1/callback.php',
            'callback_url' => 'https://jb66.net/api/game/callback',
            'api_key' => '07d92b12ebaCodeHub944d2237b6af09',
            'member_prefix' => '',
            'member_suffix' => '',
            'currency' => 'BDT',
            'language' => 'bn',
            'platform' => '2',
            'zero_local_on_launch' => '0'
        );
    }
}

if (!function_exists('gamblly_api_get_setting_value')) {
    function gamblly_api_get_setting_value($settings, $keys, $defaultValue) {
        if (!is_array($keys)) { $keys = array($keys); }
        foreach ($keys as $key) {
            if (isset($settings[$key]) && trim((string)$settings[$key]) !== '') {
                return trim((string)$settings[$key]);
            }
        }
        return $defaultValue;
    }
}

if (!function_exists('gamblly_api_get_config')) {
    function gamblly_api_get_config($conn, $settings = null) {
        if ($settings === null) {
            $settings = function_exists('game_api_get_settings') ? game_api_get_settings($conn) : array();
        }
        if (!is_array($settings)) { $settings = array(); }
        $defaults = gamblly_api_default_config();

        $endpoint = gamblly_api_get_setting_value($settings, array('gamblly_launch_url', 'api_endpoint'), $defaults['launch_url']);
        if ($endpoint === '' || (stripos($endpoint, 'gambllyapi.com') === false && stripos($endpoint, 'gamblly-api.com') === false)) {
            $endpoint = $defaults['launch_url'];
        }

        $apiKey = gamblly_api_get_setting_value($settings, array('gamblly_api_key', 'api_token'), $defaults['api_key']);
        $prefix = gamblly_api_get_setting_value($settings, array('gamblly_member_prefix', 'api_prefix', 'agent_code'), $defaults['member_prefix']);
        $suffix = gamblly_api_get_setting_value($settings, array('gamblly_member_suffix', 'api_suffix', 'secret_key'), $defaults['member_suffix']);

        $prefix = preg_replace('/[^A-Za-z0-9_\-]/', '', $prefix);
        $suffix = preg_replace('/[^A-Za-z0-9_\-]/', '', $suffix);

        return array(
            'launch_url' => $endpoint,
            'withdraw_url' => gamblly_api_get_setting_value($settings, 'gamblly_withdraw_url', $defaults['withdraw_url']),
            'balance_url' => gamblly_api_get_setting_value($settings, 'gamblly_balance_url', $defaults['balance_url']),
            'callback_pull_url' => gamblly_api_get_setting_value($settings, 'gamblly_callback_api_url', $defaults['callback_pull_url']),
            'callback_url' => gamblly_api_get_setting_value($settings, 'gamblly_callback_url', $defaults['callback_url']),
            'api_key' => $apiKey,
            'member_prefix' => $prefix,
            'member_suffix' => $suffix,
            'currency' => strtoupper(gamblly_api_get_setting_value($settings, 'currency_code', $defaults['currency'])),
            'language' => strtolower(gamblly_api_get_setting_value($settings, 'gamblly_language', $defaults['language'])),
            'platform' => gamblly_api_get_setting_value($settings, 'gamblly_platform', $defaults['platform']),
            'zero_local_on_launch' => gamblly_api_get_setting_value($settings, 'gamblly_zero_local_on_launch', $defaults['zero_local_on_launch'])
        );
    }
}

if (!function_exists('gamblly_api_is_enabled')) {
    function gamblly_api_is_enabled($conn, $settings = null) {
        if ($settings === null) {
            $settings = function_exists('game_api_get_settings') ? game_api_get_settings($conn) : array();
        }
        if (!is_array($settings)) { $settings = array(); }
        $provider = strtoupper(trim(isset($settings['game_api_provider']) ? (string)$settings['game_api_provider'] : ''));
        $endpoint = trim(isset($settings['api_endpoint']) ? (string)$settings['api_endpoint'] : '');
        if (strpos($provider, 'GAMBLLY') !== false || strpos($provider, 'GAMBLING_API') !== false) { return true; }
        if ($endpoint !== '' && (stripos($endpoint, 'gambllyapi.com') !== false || stripos($endpoint, 'gamblly-api.com') !== false)) { return true; }
        return false;
    }
}

if (!function_exists('gamblly_api_member_account')) {
    function gamblly_api_member_account($userId, $config) {
        return (string)((int)$userId);
    }
}

if (!function_exists('gamblly_api_extract_player_id')) {
    function gamblly_api_extract_player_id($rawValue, $config) {
        $raw = trim((string)$rawValue);
        if ($raw === '') { return 0; }
        if (preg_match('/([0-9]+)/', $raw, $m)) { return (int)$m[1]; }
        return (int)$raw;
    }
}

if (!function_exists('gamblly_api_json_encode')) {
    function gamblly_api_json_encode($payload) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '' : $json;
    }
}

if (!function_exists('gamblly_api_send_json')) {
    function gamblly_api_send_json($url, $payload, $timeout = 20) {
        $json = gamblly_api_json_encode($payload);
        if ($json === '') {
            return array('success' => false, 'response' => '', 'decoded' => null, 'http_code' => 0, 'curl_error' => 'JSON encode failed');
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Gamblly-API-Module/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($json)
        ));
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string)$response, true);
        return array(
            'success' => ($curlError === '' && $httpCode >= 200 && $httpCode < 300),
            'response' => (string)$response,
            'decoded' => is_array($decoded) ? $decoded : null,
            'http_code' => $httpCode,
            'curl_error' => $curlError
        );
    }
}

if (!function_exists('gamblly_api_send_form')) {
    function gamblly_api_send_form($url, $payload, $timeout = 20) {
        $post_fields = http_build_query($payload);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Gamblly-API-Module/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded'
        ));
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string)$response, true);
        return array(
            'success' => ($curlError === '' && $httpCode >= 200 && $httpCode < 300),
            'response' => (string)$response,
            'decoded' => is_array($decoded) ? $decoded : null,
            'http_code' => $httpCode,
            'curl_error' => $curlError
        );
    }
}

if (!function_exists('gamblly_api_pick')) {
    function gamblly_api_pick($data, $keys, $defaultValue = '') {
        if (!is_array($data)) { return $defaultValue; }
        if (!is_array($keys)) { $keys = array($keys); }
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') { return $data[$key]; }
        }
        return $defaultValue;
    }
}

if (!function_exists('gamblly_api_pick_nested')) {
    function gamblly_api_pick_nested($data, $paths, $defaultValue = '') {
        if (!is_array($paths)) { $paths = array($paths); }
        foreach ($paths as $path) {
            $parts = explode('.', $path);
            $node = $data;
            $found = true;
            foreach ($parts as $part) {
                if (!is_array($node) || !array_key_exists($part, $node)) { $found = false; break; }
                $node = $node[$part];
            }
            if ($found && $node !== null && $node !== '') { return $node; }
        }
        return $defaultValue;
    }
}

if (!function_exists('gamblly_api_number')) {
    function gamblly_api_number($value) {
        if (is_array($value) || is_object($value)) { return 0.0; }
        $value = preg_replace('/[^0-9\.\-]/', '', (string)$value);
        if ($value === '' || $value === '-' || $value === '.') { return 0.0; }
        return round((float)$value, 6);
    }
}

if (!function_exists('gamblly_api_extract_launch_url')) {
    function gamblly_api_extract_launch_url($decoded) {
        if (!is_array($decoded)) { return ''; }
        $url = gamblly_api_pick_nested($decoded, array(
            'payload.game_launch_url',
            'payload.game_url',
            'payload.launch_url',
            'payload.url',
            'data.game_launch_url',
            'data.game_url',
            'data.launch_url',
            'data.url',
            'game_launch_url',
            'game_url',
            'launch_url',
            'url'
        ), '');
        $url = trim((string)$url);
        return (preg_match('#^https?://#i', $url)) ? $url : '';
    }
}

if (!function_exists('gamblly_api_ensure_transactions_table')) {
    function gamblly_api_ensure_transactions_table($conn) {
        if (function_exists('game_api_table_exists') && !game_api_table_exists($conn, 'transactions')) {
            @$conn->query("CREATE TABLE IF NOT EXISTS `transactions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT NOT NULL,
                `txn_id` VARCHAR(160) NOT NULL,
                `game_round` VARCHAR(160) DEFAULT NULL,
                `game_name` VARCHAR(255) DEFAULT NULL,
                `bet` DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
                `win` DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
                `result` VARCHAR(50) DEFAULT NULL,
                `api_key` VARCHAR(255) DEFAULT NULL,
                `response_data` LONGTEXT DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_txn_id` (`txn_id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_game_round` (`user_id`, `game_round`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        if (function_exists('game_api_column_exists') && function_exists('game_api_ensure_column') && game_api_table_exists($conn, 'transactions')) {
            game_api_ensure_column($conn, 'transactions', 'game_round', 'VARCHAR(160) DEFAULT NULL');
            game_api_ensure_column($conn, 'transactions', 'response_data', 'LONGTEXT DEFAULT NULL');
            if (function_exists('game_api_ensure_index')) { game_api_ensure_index($conn, 'transactions', 'idx_game_round', 'INDEX `idx_game_round` (`user_id`, `game_round`)'); }
        }
    }
}

if (!function_exists('gamblly_api_launch_game')) {
    function gamblly_api_launch_game($conn, $user, $gameUid, $walletBalance, $currency = '', $localGameUid = '', $game = array()) {
        $config = gamblly_api_get_config($conn);
        $userId = isset($user['id']) ? (int)$user['id'] : 0;
        $gameUid = trim((string)$gameUid);
        if ($userId <= 0) { return array('success' => false, 'message' => 'Invalid player account.'); }
        if ($gameUid === '') { return array('success' => false, 'message' => 'Gamblly game UID is missing.'); }
        if (trim((string)$config['api_key']) === '') { return array('success' => false, 'message' => 'Gamblly API Key is missing.'); }
        if ($currency === '') { $currency = $config['currency']; }

        $transferId = 'GBL' . date('YmdHis') . $userId . mt_rand(1000, 9999);
        
        // V1 Seamless Wallet payload (application/x-www-form-urlencoded format)
        $payload = array(
            'api_key' => $config['api_key'],
            'member_account' => gamblly_api_member_account($userId, $config),
            'game_uid' => $gameUid,
            'home_url' => function_exists('game_api_site_url') ? rtrim(game_api_site_url('/'), '/') : '',
            'credit_amount' => number_format((float)$walletBalance, 2, '.', ''),
            'currency' => strtoupper($currency),
            'currency_code' => strtoupper($currency),
            'language' => $config['language'],
            'platform' => (int)$config['platform'],
            'transfer_id' => $transferId
        );

        $result = gamblly_api_send_form($config['launch_url'], $payload, 25);
        $decoded = isset($result['decoded']) ? $result['decoded'] : null;
        $launchUrl = gamblly_api_extract_launch_url($decoded);
        $status = false;
        if (is_array($decoded)) {
            $statusValue = gamblly_api_pick($decoded, array('status', 'success'), null);
            $codeValue = gamblly_api_pick($decoded, array('code', 'errorCode', 'status_code'), null);
            $status = ($statusValue === true || $statusValue === 'true' || $statusValue === 1 || $statusValue === '1' || (string)$codeValue === '0');
        }
        $ok = ($launchUrl !== '' && ($status || (isset($result['success']) && $result['success'])));

        if ($ok && !empty($config['zero_local_on_launch']) && (string)$config['zero_local_on_launch'] === '1') {
            $stmt = @$conn->prepare("UPDATE users SET balance=0 WHERE id=? LIMIT 1");
            if ($stmt) { $stmt->bind_param('i', $userId); $stmt->execute(); $stmt->close(); }
        }

        $message = 'Game launch failed.';
        if (is_array($decoded)) {
            $message = trim((string)gamblly_api_pick($decoded, array('message', 'msg', 'error', 'errorMessage'), $message));
        }
        if (!$ok && isset($result['curl_error']) && $result['curl_error'] !== '') { $message = $result['curl_error']; }
        if (!$ok && isset($result['http_code']) && (int)$result['http_code'] > 0) { $message .= ' (HTTP ' . (int)$result['http_code'] . ')'; }

        return array(
            'success' => $ok,
            'url' => $launchUrl,
            'message' => $message,
            'payload' => $payload,
            'response' => isset($result['response']) ? $result['response'] : '',
            'decoded' => $decoded,
            'http_code' => isset($result['http_code']) ? $result['http_code'] : 0,
            'curl_error' => isset($result['curl_error']) ? $result['curl_error'] : '',
            'transfer_id' => $transferId
        );
    }
}

if (!function_exists('gamblly_api_wallet_request')) {
    function gamblly_api_wallet_request($conn, $userId, $endpointKey) {
        $config = gamblly_api_get_config($conn);
        $url = isset($config[$endpointKey]) ? $config[$endpointKey] : '';
        if ($url === '') { return array('success' => false, 'amount' => 0, 'message' => 'Endpoint missing.'); }
        $payload = array(
            'agency_uid' => $config['api_key'],
            'member_account' => gamblly_api_member_account((int)$userId, $config),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'credit_amount' => 0,
            'currency_code' => $config['currency'],
            'language' => $config['language'],
            'platform' => $config['platform'],
            'home_url' => function_exists('game_api_site_url') ? rtrim(game_api_site_url('/'), '/') : '',
            'transfer_id' => 'GBW' . date('YmdHis') . (int)$userId . mt_rand(1000, 9999)
        );
        $result = gamblly_api_send_json($url, $payload, 25);
        $decoded = isset($result['decoded']) ? $result['decoded'] : null;
        $amount = 0.0;
        if (is_array($decoded)) {
            $amount = gamblly_api_number(gamblly_api_pick_nested($decoded, array(
                'payload.amount', 'payload.balance', 'payload.after_amount', 'data.amount', 'data.balance', 'data.after_amount', 'amount', 'balance', 'after_amount'
            ), 0));
        }
        $success = isset($result['success']) ? (bool)$result['success'] : false;
        if (is_array($decoded)) {
            $statusValue = gamblly_api_pick($decoded, array('status', 'success'), null);
            $codeValue = gamblly_api_pick($decoded, array('code', 'errorCode', 'status_code'), null);
            if ($statusValue === true || $statusValue === 'true' || $statusValue === 1 || $statusValue === '1' || (string)$codeValue === '0') { $success = true; }
        }
        $message = is_array($decoded) ? (string)gamblly_api_pick($decoded, array('message','msg','error'), '') : '';
        if (!$success && isset($result['curl_error']) && $result['curl_error'] !== '') { $message = $result['curl_error']; }
        return array('success' => $success, 'amount' => $amount, 'message' => $message, 'payload' => $payload, 'response' => isset($result['response']) ? $result['response'] : '', 'decoded' => $decoded);
    }
}

if (!function_exists('gamblly_api_read_callback_payload')) {
    function gamblly_api_read_callback_payload() {
        $raw = file_get_contents('php://input');
        $data = array();
        if (trim((string)$raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) { $data = $decoded; }
        }
        if (empty($data) && !empty($_POST)) { $data = $_POST; }
        if (empty($data) && !empty($_GET)) { $data = $_GET; }
        return array($data, (string)$raw);
    }
}

if (!function_exists('gamblly_api_find_callback_transaction')) {
    function gamblly_api_find_callback_transaction($conn, $transactionId) {
        if (!function_exists('game_api_table_exists') || !game_api_table_exists($conn, 'game_api_callback_transactions')) { return false; }
        $column = '';
        if (function_exists('game_api_column_exists') && game_api_column_exists($conn, 'game_api_callback_transactions', 'transaction_id')) { $column = 'transaction_id'; }
        elseif (function_exists('game_api_column_exists') && game_api_column_exists($conn, 'game_api_callback_transactions', 'external_transaction_id')) { $column = 'external_transaction_id'; }
        if ($column === '') { return false; }
        $sql = "SELECT id FROM game_api_callback_transactions WHERE `$column`=? LIMIT 1";
        $stmt = @$conn->prepare($sql);
        if (!$stmt) { return false; }
        $stmt->bind_param('s', $transactionId);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = ($res && $res->num_rows > 0);
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('gamblly_api_insert_callback_transaction')) {
    function gamblly_api_insert_callback_transaction($conn, $payload) {
        if (!function_exists('game_api_table_exists') || !game_api_table_exists($conn, 'game_api_callback_transactions')) { return false; }
        $columns = array();
        $values = array();
        $types = '';

        $map = array(
            'user_id' => array('value_key' => 'user_id', 'type' => 'i'),
            'player_id' => array('value_key' => 'user_id', 'type' => 'i'),
            'game_uid' => array('value_key' => 'game_uid', 'type' => 's'),
            'local_game_uid' => array('value_key' => 'game_uid', 'type' => 's'),
            'api_game_id' => array('value_key' => 'game_uid', 'type' => 's'),
            'round_id' => array('value_key' => 'round_id', 'type' => 's'),
            'transaction_id' => array('value_key' => 'transaction_id', 'type' => 's'),
            'external_transaction_id' => array('value_key' => 'transaction_id', 'type' => 's'),
            'action_type' => array('value_key' => 'action_type', 'type' => 's'),
            'callback_type' => array('value_key' => 'action_type', 'type' => 's'),
            'amount' => array('value_key' => 'amount', 'type' => 'd'),
            'balance_before' => array('value_key' => 'balance_before', 'type' => 'd'),
            'balance_after' => array('value_key' => 'balance_after', 'type' => 'd'),
            'status' => array('value_key' => 'status', 'type' => 's'),
            'raw_payload' => array('value_key' => 'raw_payload', 'type' => 's'),
            'raw_data' => array('value_key' => 'raw_payload', 'type' => 's')
        );

        foreach ($map as $col => $info) {
            if (function_exists('game_api_column_exists') && game_api_column_exists($conn, 'game_api_callback_transactions', $col)) {
                $key = $info['value_key'];
                if (!array_key_exists($key, $payload)) { continue; }
                $columns[] = $col;
                $values[] = $payload[$key];
                $types .= $info['type'];
            }
        }
        if (empty($columns)) { return false; }
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO game_api_callback_transactions (`" . implode('`,`', $columns) . "`) VALUES ($placeholders)";
        $stmt = @$conn->prepare($sql);
        if (!$stmt) { return false; }
        $refs = array();
        $refs[] = $types;
        for ($i = 0; $i < count($values); $i++) { $refs[] = &$values[$i]; }
        call_user_func_array(array($stmt, 'bind_param'), $refs);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('gamblly_api_upsert_transaction_summary')) {
    function gamblly_api_upsert_transaction_summary($conn, $userId, $data, $bet, $win, $txnId, $roundId) {
        gamblly_api_ensure_transactions_table($conn);
        if (!function_exists('game_api_table_exists') || !game_api_table_exists($conn, 'transactions')) { return false; }
        $gameName = trim((string)gamblly_api_pick($data, array('game_name', 'gameName', 'game_uid', 'game_code'), ''));
        $apiKey = trim((string)gamblly_api_pick($data, array('api_key', 'agency_uid'), ''));
        $raw = gamblly_api_json_encode($data);
        $result = ($win > 0) ? 'win' : (($bet > 0) ? 'loss' : 'settled');
        $txnId = substr((string)$txnId, 0, 160);
        $roundId = substr((string)$roundId, 0, 160);

        $existingId = 0;
        $find = @$conn->prepare("SELECT id FROM transactions WHERE txn_id=? LIMIT 1");
        if ($find) {
            $find->bind_param('s', $txnId);
            $find->execute();
            $res = $find->get_result();
            if ($res && $res->num_rows > 0) { $existingId = (int)$res->fetch_assoc()['id']; }
            $find->close();
        }

        if ($existingId > 0) {
            $stmt = @$conn->prepare("UPDATE transactions SET user_id=?, game_round=?, game_name=?, bet=?, win=?, result=?, api_key=?, response_data=?, updated_at=CURRENT_TIMESTAMP WHERE id=? LIMIT 1");
            if (!$stmt) { return false; }
            $stmt->bind_param('issddsssi', $userId, $roundId, $gameName, $bet, $win, $result, $apiKey, $raw, $existingId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        }

        $stmt = @$conn->prepare("INSERT INTO transactions (user_id, txn_id, game_round, game_name, bet, win, result, api_key, response_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) { return false; }
        $stmt->bind_param('isssddsss', $userId, $txnId, $roundId, $gameName, $bet, $win, $result, $apiKey, $raw);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('gamblly_api_response')) {
    function gamblly_api_response($payload, $httpCode = 200) {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('gamblly_api_handle_callback')) {
    function gamblly_api_handle_callback($conn) {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { gamblly_api_response(array('success' => true)); }
        if (function_exists('game_api_ensure_schema')) { game_api_ensure_schema($conn, false); }
        gamblly_api_ensure_transactions_table($conn);

        list($data, $raw) = gamblly_api_read_callback_payload();
        $config = gamblly_api_get_config($conn);
        if (!is_array($data) || empty($data)) {
            gamblly_api_response(array('status' => true, 'balance' => 0.00, 'message' => 'Empty request'));
        }

        $receivedApiKey = trim((string)gamblly_api_pick($data, array('api_key', 'agency_uid', 'APIKey', 'key'), ''));
        if ($receivedApiKey === '' && isset($_SERVER['HTTP_X_API_KEY'])) { $receivedApiKey = trim((string)$_SERVER['HTTP_X_API_KEY']); }
        if ($receivedApiKey === '' && isset($_SERVER['HTTP_X_AGENCY_UID'])) { $receivedApiKey = trim((string)$_SERVER['HTTP_X_AGENCY_UID']); }
        if (trim((string)$config['api_key']) !== '' && !hash_equals((string)$config['api_key'], $receivedApiKey)) {
            gamblly_api_response(array('status' => false, 'message' => 'Invalid API key'), 403);
        }

        $playerRaw = gamblly_api_pick($data, array('player_uid', 'member_account', 'member', 'username', 'user_id', 'uid', 'player_id'), '');
        $userId = gamblly_api_extract_player_id($playerRaw, $config);
        if ($userId <= 0) { gamblly_api_response(array('status' => false, 'message' => 'Invalid player'), 400); }

        $action = strtolower(trim((string)gamblly_api_pick($data, array('action', 'type', 'method', 'transaction_type', 'bet_type'), '')));
        $roundId = trim((string)gamblly_api_pick($data, array('round_id', 'roundId', 'game_round', 'gameRound', 'game_round_id'), ''));
        $baseTxnId = trim((string)gamblly_api_pick($data, array('txn_id', 'transaction_id', 'serial_number', 'id', 'order_id', 'transfer_id'), ''));
        if ($baseTxnId === '') { $baseTxnId = sha1($raw !== '' ? $raw : gamblly_api_json_encode($data)); }
        if ($roundId === '') { $roundId = $baseTxnId; }

        $stmt = @$conn->prepare("SELECT id, username, balance FROM users WHERE id=? LIMIT 1 FOR UPDATE");
        if (!$stmt) { gamblly_api_response(array('status' => false, 'message' => 'Database error'), 500); }
        @$conn->begin_transaction();
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) {
            $stmt->close();
            @$conn->rollback();
            gamblly_api_response(array('status' => false, 'message' => 'Player not found'), 404);
        }
        $user = $res->fetch_assoc();
        $stmt->close();
        $balanceBefore = round((float)$user['balance'], 6);

        // Special Case: action=deposit_required
        if ($action === 'deposit_required') {
            @$conn->commit();
            gamblly_api_response(array('balance' => round((float)$balanceBefore, 2), 'status' => true, 'message' => 'Deposit required notice received'));
        }

        if (in_array($action, array('balance', 'getbalance', 'get_balance', 'checkbalance', 'check_balance'), true)) {
            @$conn->commit();
            gamblly_api_response(array('balance' => round((float)$balanceBefore, 2), 'status' => true));
        }

        $betAmount = 0.0;
        $winAmount = 0.0;
        $genericAmount = gamblly_api_number(gamblly_api_pick($data, array('amount', 'money'), 0));
        $explicitBet = gamblly_api_number(gamblly_api_pick($data, array('bet_amount', 'bet', 'stake', 'debit_amount'), 0));
        $explicitWin = gamblly_api_number(gamblly_api_pick($data, array('win_amount', 'win', 'payout', 'credit_amount'), 0));
        if ($explicitBet > 0) { $betAmount = $explicitBet; }
        if ($explicitWin > 0) { $winAmount = $explicitWin; }
        if ($genericAmount > 0 && $betAmount <= 0 && $winAmount <= 0) {
            if (in_array($action, array('win','credit','settle','settlement','payout','reward'), true)) { $winAmount = $genericAmount; }
            else { $betAmount = $genericAmount; }
        }

        $balanceAfter = $balanceBefore;
        $processed = array();
        $rawJson = $raw !== '' ? $raw : gamblly_api_json_encode($data);

        if ($betAmount > 0) {
            $txnId = 'gamblly_bet_' . $baseTxnId;
            if (!gamblly_api_find_callback_transaction($conn, $txnId)) {
                $balanceAfter = round($balanceAfter - $betAmount, 6);
                if ($balanceAfter < 0) { $balanceAfter = 0.0; }
                $processed[] = array('type' => 'bet', 'txn_id' => $txnId, 'amount' => $betAmount, 'before' => $balanceBefore, 'after' => $balanceAfter);
            }
        }
        if ($winAmount > 0) {
            $txnId = 'gamblly_win_' . $baseTxnId;
            if (!gamblly_api_find_callback_transaction($conn, $txnId)) {
                $beforeWin = $balanceAfter;
                $balanceAfter = round($balanceAfter + $winAmount, 6);
                $processed[] = array('type' => 'win', 'txn_id' => $txnId, 'amount' => $winAmount, 'before' => $beforeWin, 'after' => $balanceAfter);
            }
        }

        if (empty($processed)) {
            @$conn->commit();
            gamblly_api_response(array('balance' => round((float)$balanceAfter, 2), 'status' => true, 'message' => 'Duplicate or zero transaction'));
        }

        $stmt = @$conn->prepare("UPDATE users SET balance=? WHERE id=? LIMIT 1");
        if (!$stmt) { @$conn->rollback(); gamblly_api_response(array('status' => false, 'message' => 'Wallet update failed'), 500); }
        $stmt->bind_param('di', $balanceAfter, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) { @$conn->rollback(); gamblly_api_response(array('status' => false, 'message' => 'Wallet update failed'), 500); }

        foreach ($processed as $row) {
            gamblly_api_insert_callback_transaction($conn, array(
                'user_id' => $userId,
                'game_uid' => trim((string)gamblly_api_pick($data, array('game_uid','game_code','game_id'), '')),
                'round_id' => $roundId,
                'transaction_id' => $row['txn_id'],
                'action_type' => $row['type'],
                'amount' => $row['amount'],
                'balance_before' => $row['before'],
                'balance_after' => $row['after'],
                'status' => 'success',
                'raw_payload' => $rawJson
            ));
            if (function_exists('game_api_insert_bet_history')) {
                game_api_insert_bet_history($conn, array(
                    'user_id' => $userId,
                    'username' => isset($user['username']) ? $user['username'] : '',
                    'vendor_code' => 'gamblly',
                    'game_uid' => trim((string)gamblly_api_pick($data, array('game_uid','game_code','game_id'), '')),
                    'round_id' => $roundId,
                    'transaction_id' => $row['txn_id'],
                    'type' => ($row['type'] === 'win' ? 'win' : 'bet'),
                    'amount' => $row['amount'],
                    'balance_after' => $row['after'],
                    'currency' => $config['currency']
                ));
            }
        }
        gamblly_api_upsert_transaction_summary($conn, $userId, $data, $betAmount, $winAmount, 'gamblly_' . $baseTxnId, $roundId);
        @$conn->commit();

        gamblly_api_response(array('balance' => round((float)$balanceAfter, 2), 'status' => true));
    }
}
?>
