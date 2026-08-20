<?php
// includes/lgpay_gateway_helper.php
// LG Pay gateway integration for auto deposit and admin-approved payout.

if (!defined('LGPAY_HELPER_LOADED')) {
    define('LGPAY_HELPER_LOADED', true);
}
if (file_exists(__DIR__ . '/propay_gateway_helper.php')) { require_once __DIR__ . '/propay_gateway_helper.php'; }
if (file_exists(__DIR__ . '/referral_system_helper.php')) { require_once __DIR__ . '/referral_system_helper.php'; }

function lgpay_table_exists($conn, $table) {
    if (function_exists('propay_table_exists')) { return propay_table_exists($conn, $table); }
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    $res = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safe) . "'");
    return $res && $res->num_rows > 0;
}

function lgpay_column_exists($conn, $table, $column) {
    if (function_exists('propay_column_exists')) { return propay_column_exists($conn, $table, $column); }
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
    $res = @$conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '" . $conn->real_escape_string($safeColumn) . "'");
    return $res && $res->num_rows > 0;
}

function lgpay_add_column_if_missing($conn, $table, $column, $definition) {
    if (function_exists('propay_add_column_if_missing')) { return propay_add_column_if_missing($conn, $table, $column, $definition); }
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
    if (!lgpay_column_exists($conn, $safeTable, $safeColumn)) {
        return @$conn->query("ALTER TABLE `$safeTable` ADD COLUMN `$safeColumn` $definition");
    }
    return true;
}

function lgpay_default_api_base_url() {
    return 'https://www.lg-pay.com/api';
}


function wcb_force_lgpay_only($conn) {
    if (!$conn || !empty($conn->connect_error) || !lgpay_table_exists($conn, 'payment_gateway_settings')) { return false; }
    $defaultBase = $conn->real_escape_string(lgpay_default_api_base_url());
    @$conn->query("INSERT INTO `payment_gateway_settings` (`provider`, `merchant_code`, `secret_code`, `api_base_url`, `is_enabled`, `failover_priority`) VALUES ('lgpay', '', '', '$defaultBase', 1, 1) ON DUPLICATE KEY UPDATE `api_base_url`=COALESCE(NULLIF(`api_base_url`, ''), VALUES(`api_base_url`)), `is_enabled`=1, `failover_priority`=1, `updated_at`=NOW()");
    @$conn->query("INSERT INTO `payment_gateway_settings` (`provider`, `merchant_code`, `secret_code`, `api_base_url`, `is_enabled`, `failover_priority`) VALUES ('propay', '', '', '', 0, 99), ('akpay', '', '', '', 0, 99), ('cowpay', '', '', '', 0, 99) ON DUPLICATE KEY UPDATE `is_enabled`=0, `failover_priority`=99, `last_health_status`='disabled', `last_error`='Disabled because LG Pay is the only active/default gateway.', `updated_at`=NOW()");
    return true;
}

function lgpay_ensure_schema($conn) {
    if (!$conn || !empty($conn->connect_error)) { return false; }
    if (function_exists('propay_ensure_schema')) { @propay_ensure_schema($conn); }

    @$conn->query("CREATE TABLE IF NOT EXISTS `payment_gateway_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `provider` VARCHAR(40) NOT NULL DEFAULT 'lgpay',
        `merchant_code` VARCHAR(120) DEFAULT NULL,
        `secret_code` VARCHAR(255) DEFAULT NULL,
        `api_base_url` VARCHAR(255) DEFAULT NULL,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `failover_priority` TINYINT(3) NOT NULL DEFAULT 1,
        `last_health_status` VARCHAR(20) DEFAULT NULL,
        `last_health_checked_at` DATETIME DEFAULT NULL,
        `last_error` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_provider` (`provider`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (lgpay_table_exists($conn, 'payment_gateway_settings')) {
        lgpay_add_column_if_missing($conn, 'payment_gateway_settings', 'api_base_url', "VARCHAR(255) DEFAULT NULL AFTER `secret_code`");
        lgpay_add_column_if_missing($conn, 'payment_gateway_settings', 'failover_priority', "TINYINT(3) NOT NULL DEFAULT 1 AFTER `is_enabled`");
        lgpay_add_column_if_missing($conn, 'payment_gateway_settings', 'last_health_status', "VARCHAR(20) DEFAULT NULL AFTER `failover_priority`");
        lgpay_add_column_if_missing($conn, 'payment_gateway_settings', 'last_health_checked_at', "DATETIME DEFAULT NULL AFTER `last_health_status`");
        lgpay_add_column_if_missing($conn, 'payment_gateway_settings', 'last_error', "TEXT DEFAULT NULL AFTER `last_health_checked_at`");

        $defaultBase = $conn->real_escape_string(lgpay_default_api_base_url());
        @$conn->query("INSERT IGNORE INTO `payment_gateway_settings` (`provider`, `merchant_code`, `secret_code`, `api_base_url`, `is_enabled`, `failover_priority`) VALUES ('lgpay', '', '', '$defaultBase', 0, 1)");
    }

    if (lgpay_table_exists($conn, 'payment_gateway_orders')) {
        lgpay_add_column_if_missing($conn, 'payment_gateway_orders', 'gateway', "VARCHAR(40) NOT NULL DEFAULT 'propay' AFTER `user_id`");
        lgpay_add_column_if_missing($conn, 'payment_gateway_orders', 'channel', "VARCHAR(60) DEFAULT NULL AFTER `method`");
        lgpay_add_column_if_missing($conn, 'payment_gateway_orders', 'account_number', "VARCHAR(30) DEFAULT NULL AFTER `channel`");
    }

    if (lgpay_table_exists($conn, 'transactions_fake')) {
        lgpay_add_column_if_missing($conn, 'transactions_fake', 'promo_id', "INT(11) DEFAULT 0");
        lgpay_add_column_if_missing($conn, 'transactions_fake', 'wallet_number', "VARCHAR(120) DEFAULT NULL COMMENT 'User Phone/Wallet'");
        lgpay_add_column_if_missing($conn, 'transactions_fake', 'transaction_id', "VARCHAR(120) DEFAULT NULL COMMENT 'Gateway/Trx ID'");
        lgpay_add_column_if_missing($conn, 'transactions_fake', 'order_sn', "VARCHAR(120) DEFAULT NULL");
        lgpay_add_column_if_missing($conn, 'transactions_fake', 'lg_order_sn', "VARCHAR(120) DEFAULT NULL");
        lgpay_add_column_if_missing($conn, 'transactions_fake', 'admin_note', "TEXT DEFAULT NULL");
        lgpay_add_column_if_missing($conn, 'transactions_fake', 'withdraw_wallet_id', "INT NULL DEFAULT NULL AFTER wallet_number");
        lgpay_add_column_if_missing($conn, 'transactions_fake', 'withdraw_method_code', "VARCHAR(50) NULL DEFAULT NULL AFTER withdraw_wallet_id");
        lgpay_add_column_if_missing($conn, 'transactions_fake', 'withdraw_pin_verified', "TINYINT(1) NOT NULL DEFAULT 0 AFTER withdraw_method_code");
        $statusColumn = @$conn->query("SHOW COLUMNS FROM transactions_fake LIKE 'status'");
        if ($statusColumn && ($statusRow = $statusColumn->fetch_assoc()) && strpos((string)$statusRow['Type'], "'processing'") === false) {
            @$conn->query("ALTER TABLE transactions_fake MODIFY status ENUM('pending','processing','approved','rejected') DEFAULT 'approved'");
        }
    }
    return true;
}

function lgpay_get_settings($conn) {
    lgpay_ensure_schema($conn);
    $settings = array(
        'merchant_code' => '',
        'secret_code' => '',
        'api_base_url' => lgpay_default_api_base_url(),
        'is_enabled' => 1,
        'failover_priority' => 1,
        'last_health_status' => '',
        'last_health_checked_at' => '',
        'last_error' => ''
    );
    $res = @$conn->query("SELECT merchant_code, secret_code, api_base_url, is_enabled, failover_priority, last_health_status, last_health_checked_at, last_error FROM payment_gateway_settings WHERE provider='lgpay' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $settings['merchant_code'] = trim((string)($row['merchant_code'] ?? ''));
        $settings['secret_code'] = trim((string)($row['secret_code'] ?? ''));
        $settings['api_base_url'] = trim((string)($row['api_base_url'] ?? '')) ?: lgpay_default_api_base_url();
        $settings['is_enabled'] = intval($row['is_enabled'] ?? 1);
        $settings['failover_priority'] = max(1, intval($row['failover_priority'] ?? 1));
        $settings['last_health_status'] = trim((string)($row['last_health_status'] ?? ''));
        $settings['last_health_checked_at'] = trim((string)($row['last_health_checked_at'] ?? ''));
        $settings['last_error'] = trim((string)($row['last_error'] ?? ''));
    }
    return $settings;
}

function lgpay_save_settings($conn, $merchant_code, $secret_code, $api_base_url, $is_enabled) {
    $is_enabled = 1; // LG Pay is the only active/default gateway.

    lgpay_ensure_schema($conn);
    $provider = 'lgpay';
    $merchant_code = trim((string)$merchant_code);
    $secret_code = trim((string)$secret_code);
    $api_base_url = trim((string)$api_base_url) ?: lgpay_default_api_base_url();
    $is_enabled = $is_enabled ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO payment_gateway_settings (provider, merchant_code, secret_code, api_base_url, is_enabled)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE merchant_code=VALUES(merchant_code), secret_code=VALUES(secret_code), api_base_url=VALUES(api_base_url), is_enabled=VALUES(is_enabled), updated_at=NOW()");
    if (!$stmt) { return false; }
    $stmt->bind_param('ssssi', $provider, $merchant_code, $secret_code, $api_base_url, $is_enabled);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function lgpay_base_url() {
    if (function_exists('propay_base_url')) { return propay_base_url(); }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9\-\.:]/', '', $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    return $scheme . '://' . $host;
}

function lgpay_url($path) {
    return rtrim(lgpay_base_url(), '/') . '/' . ltrim((string)$path, '/');
}

function lgpay_json($data) {
    if (function_exists('propay_json')) { return propay_json($data); }
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function lgpay_format_amount($amount) {
    if (function_exists('propay_format_amount')) { return propay_format_amount($amount); }
    return number_format((float)$amount, 2, '.', '');
}

function lgpay_money_to_minor($amount) {
    return (int)floor(((float)$amount * 100) + 0.00001);
}

function lgpay_minor_to_money($minor) {
    return ((float)$minor) / 100;
}

function lgpay_md5_sign($data, $key) {
    if (isset($data['sign'])) { unset($data['sign']); }
    foreach ($data as $k => $v) {
        if ($v === null || $v === '') { unset($data[$k]); }
    }
    ksort($data);
    $string = http_build_query($data);
    $string = urldecode($string);
    $string = trim($string) . '&key=' . $key;
    return strtoupper(md5($string));
}

function lgpay_verify_sign($payload, $secret) {
    $remote = trim((string)($payload['sign'] ?? ''));
    if ($remote === '' || $secret === '') { return false; }
    $local = lgpay_md5_sign($payload, $secret);
    return function_exists('hash_equals') ? hash_equals($local, $remote) : ($local === $remote);
}

function lgpay_endpoint($conn, $path) {
    $settings = lgpay_get_settings($conn);
    $base = trim((string)($settings['api_base_url'] ?? '')) ?: lgpay_default_api_base_url();
    $base = rtrim($base, '/');
    return $base . '/' . ltrim($path, '/');
}

function lgpay_normalize_method($method) {
    $m = strtolower(trim((string)$method));
    if ($m === 'bkash' || $m === 'b-kash' || $m === 'b cash' || $m === 'bcash') { return 'bkash'; }
    if ($m === 'nagad') { return 'nagad'; }
    return '';
}

function lgpay_method_label($method) {
    $m = lgpay_normalize_method($method);
    return $m === 'nagad' ? 'Nagad' : 'bKash';
}

function lgpay_trade_type($method) {
    return lgpay_method_label($method) === 'Nagad' ? 'Nagad' : 'bKash';
}

function lgpay_make_order_no($prefix, $uid) {
    if (function_exists('propay_make_order_no')) { return propay_make_order_no($prefix, $uid); }
    try { $rand = strtoupper(bin2hex(random_bytes(3))); } catch (Exception $e) { $rand = mt_rand(100000, 999999); }
    return $prefix . date('ymdHis') . intval($uid) . $rand;
}

function lgpay_http_post($url, $fields, $timeoutSeconds = 35) {
    if (!function_exists('curl_init')) {
        return array('success' => false, 'http_code' => 0, 'error' => 'cURL extension is not enabled.', 'body' => '');
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => max(10, intval($timeoutSeconds)),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded')
    ));
    $body = curl_exec($curl);
    $err = curl_error($curl);
    $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return array('success' => ($err === ''), 'http_code' => $http, 'error' => $err, 'body' => (string)$body);
}

function lgpay_mask_request($fields) {
    $copy = $fields;
    if (isset($copy['sign'])) { $copy['sign'] = '***hidden***'; }
    return $copy;
}

function lgpay_update_health($conn, $status, $message = '') {
    if (!lgpay_table_exists($conn, 'payment_gateway_settings')) { return; }
    $stmt = $conn->prepare("UPDATE payment_gateway_settings SET last_health_status=?, last_health_checked_at=NOW(), last_error=? WHERE provider='lgpay'");
    if ($stmt) { $stmt->bind_param('ss', $status, $message); $stmt->execute(); $stmt->close(); }
}

function lgpay_is_available($conn, $checkRemote = true) {
    $settings = lgpay_get_settings($conn);
    if (intval($settings['is_enabled'] ?? 0) !== 1) { return false; }
    if (trim((string)$settings['merchant_code']) === '' || trim((string)$settings['secret_code']) === '') { return false; }
    if (!$checkRemote) { return true; }
    $base = rtrim(trim((string)$settings['api_base_url']) ?: lgpay_default_api_base_url(), '/') . '/';
    if (function_exists('wcb_gateway_url_reachable')) { return wcb_gateway_url_reachable($base); }
    return true;
}

function lgpay_create_deposit_order($conn, $uid, $amount, $method, $promo_id, $channel = '') {
    lgpay_ensure_schema($conn);
    $settings = lgpay_get_settings($conn);
    $appId = trim((string)$settings['merchant_code']);
    $secret = trim((string)$settings['secret_code']);
    if (intval($settings['is_enabled'] ?? 0) !== 1 || $appId === '' || $secret === '') {
        return array('success' => false, 'message' => 'LG Pay Merchant/App ID or Secret Key is not configured in Admin Panel.');
    }

    $txnSettings = function_exists('propay_get_site_transaction_settings') ? propay_get_site_transaction_settings($conn) : array('min_deposit_amount' => 100);
    $minDeposit = (float)($txnSettings['min_deposit_amount'] ?? 100);
    $amount = round((float)$amount, 2);
    if ($amount < $minDeposit) { return array('success' => false, 'message' => 'Minimum deposit amount is ৳' . lgpay_format_amount($minDeposit) . '.'); }
    if ($amount < 100 || $amount > 25000) { return array('success' => false, 'message' => 'LG Pay Bangladesh deposit amount must be between ৳100 and ৳25,000.'); }

    $methodKey = lgpay_normalize_method($method);
    if ($methodKey === '') { return array('success' => false, 'message' => 'LG Pay supports bKash and Nagad deposits.'); }

    $stmtUser = $conn->prepare("SELECT phone, agent_id FROM users WHERE id=? LIMIT 1");
    if (!$stmtUser) { return array('success' => false, 'message' => 'Unable to read user information.'); }
    $stmtUser->bind_param('i', $uid);
    $stmtUser->execute();
    $userRes = $stmtUser->get_result();
    if (!$userRes || $userRes->num_rows === 0) { $stmtUser->close(); return array('success' => false, 'message' => 'User account was not found.'); }
    $user = $userRes->fetch_assoc();
    $stmtUser->close();
    $payerPhone = preg_replace('/\D+/', '', (string)($user['phone'] ?? ''));
    if (!preg_match('/^01\d{9}$/', $payerPhone)) { return array('success' => false, 'message' => 'Your account phone number must be a valid 11 digit Bangladesh mobile number.'); }

    $orderNo = lgpay_make_order_no('LGD', $uid);
    $methodLabel = lgpay_method_label($methodKey);
    $historyMethod = 'LGPay ' . $methodLabel;
    $agentId = intval($user['agent_id'] ?? 0);
    $promo_id = intval($promo_id);
    $minorAmount = lgpay_money_to_minor($amount);

    $params = array(
        'app_id' => $appId,
        'trade_type' => lgpay_trade_type($methodKey),
        'order_sn' => $orderNo,
        'user_id' => $payerPhone,
        'money' => $minorAmount,
        'notify_url' => lgpay_url('/api/lgpay_callback.php'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'remark' => 'uid:' . intval($uid)
    );
    $params['sign'] = lgpay_md5_sign($params, $secret);
    $rawRequest = lgpay_json(lgpay_mask_request($params));

    $conn->begin_transaction();
    try {
        $stmtTx = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, promo_id, status, wallet_number, transaction_id, order_sn, agent_id, admin_note, created_at)
            VALUES (?, 'deposit', ?, ?, ?, 'pending', ?, ?, ?, ?, 'LGPay deposit started', NOW())");
        if (!$stmtTx) { throw new RuntimeException('Unable to create deposit transaction.'); }
        $stmtTx->bind_param('idsisssi', $uid, $amount, $historyMethod, $promo_id, $payerPhone, $orderNo, $orderNo, $agentId);
        if (!$stmtTx->execute()) { throw new RuntimeException('Unable to create deposit transaction.'); }
        $txId = intval($stmtTx->insert_id);
        $stmtTx->close();

        $channelLabel = $channel !== '' ? trim((string)$channel) : 'LGPay';
        $stmtOrder = $conn->prepare("INSERT INTO payment_gateway_orders (transaction_id, order_no, user_id, gateway, type, method, channel, account_number, amount, status, gateway_status, raw_request, created_at)
            VALUES (?, ?, ?, 'lgpay', 'deposit', ?, ?, ?, ?, 'pending', 'created', ?, NOW())");
        if (!$stmtOrder) { throw new RuntimeException('Unable to create gateway order.'); }
        $stmtOrder->bind_param('isisssds', $txId, $orderNo, $uid, $methodKey, $channelLabel, $payerPhone, $amount, $rawRequest);
        if (!$stmtOrder->execute()) { throw new RuntimeException('Unable to create gateway order.'); }
        $stmtOrder->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Failed to create LG Pay deposit order: ' . $e->getMessage());
    }

    $http = lgpay_http_post(lgpay_endpoint($conn, 'order/create'), $params, 35);
    $rawResponse = $http['body'];
    $decoded = json_decode($rawResponse, true);
    $stmtResp = $conn->prepare("UPDATE payment_gateway_orders SET raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='lgpay' AND type='deposit'");
    if ($stmtResp) { $stmtResp->bind_param('ss', $rawResponse, $orderNo); $stmtResp->execute(); $stmtResp->close(); }

    if (!$http['success'] || !is_array($decoded)) {
        $message = $http['error'] !== '' ? $http['error'] : 'Invalid response from LG Pay.';
        lgpay_update_health($conn, 'down', $message);
        return array('success' => false, 'message' => $message);
    }
    if (intval($decoded['status'] ?? 0) !== 1 || empty($decoded['data']['pay_url'])) {
        $message = trim((string)($decoded['msg'] ?? 'LG Pay rejected the deposit order.'));
        $stmtFail = $conn->prepare("UPDATE payment_gateway_orders SET status='failed', gateway_status='create_failed', raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='lgpay' AND type='deposit'");
        if ($stmtFail) { $stmtFail->bind_param('ss', $rawResponse, $orderNo); $stmtFail->execute(); $stmtFail->close(); }
        $stmtTxFail = $conn->prepare("UPDATE transactions_fake SET status='rejected', admin_note=? WHERE order_sn=? AND status='pending'");
        if ($stmtTxFail) { $stmtTxFail->bind_param('ss', $message, $orderNo); $stmtTxFail->execute(); $stmtTxFail->close(); }
        lgpay_update_health($conn, 'down', $message);
        return array('success' => false, 'message' => $message, 'gateway_response' => $decoded);
    }

    lgpay_update_health($conn, 'up', 'Deposit order created.');
    return array('success' => true, 'order_no' => $orderNo, 'redirect_url' => (string)$decoded['data']['pay_url'], 'gateway_response' => $decoded);
}

function lgpay_apply_deposit_success($conn, $payload, $source = 'callback') {
    lgpay_ensure_schema($conn);
    $settings = lgpay_get_settings($conn);
    $secret = trim((string)$settings['secret_code']);
    if (!lgpay_verify_sign($payload, $secret)) {
        return array('success' => false, 'http_code' => 403, 'message' => 'Invalid Signature');
    }
    $orderNo = trim((string)($payload['order_sn'] ?? ''));
    $status = intval($payload['status'] ?? 0);
    $minor = (int)($payload['money'] ?? 0);
    $amount = lgpay_minor_to_money($minor);
    $payloadJson = lgpay_json($payload);
    if ($orderNo === '' || $status !== 1 || $minor <= 0) {
        return array('success' => false, 'http_code' => 400, 'message' => 'Invalid callback payload.');
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='lgpay' AND type='deposit' LIMIT 1 FOR UPDATE");
        if (!$stmt) { throw new RuntimeException('Unable to read order.'); }
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $conn->rollback(); return array('success' => false, 'http_code' => 404, 'message' => 'Order not found.'); }
        $order = $res->fetch_assoc();
        $stmt->close();

        if ($order['status'] === 'success') {
            $conn->commit();
            return array('success' => true, 'http_code' => 200, 'message' => 'Success');
        }

        $expected = (float)$order['amount'];
        if (abs($expected - $amount) > 0.01) {
            $stmtMismatch = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status='amount_mismatch', callback_payload=?, updated_at=NOW() WHERE id=?");
            $oid = intval($order['id']);
            if ($stmtMismatch) { $stmtMismatch->bind_param('si', $payloadJson, $oid); $stmtMismatch->execute(); $stmtMismatch->close(); }
            $conn->commit();
            return array('success' => false, 'http_code' => 400, 'message' => 'Amount mismatch.');
        }

        $txId = intval($order['transaction_id']);
        $uid = intval($order['user_id']);
        $promoId = 0;
        $txStatus = 'pending';
        if ($txId > 0) {
            $stmtTx = $conn->prepare("SELECT id, status, promo_id FROM transactions_fake WHERE id=? LIMIT 1 FOR UPDATE");
            if ($stmtTx) {
                $stmtTx->bind_param('i', $txId);
                $stmtTx->execute();
                $txRes = $stmtTx->get_result();
                if ($txRes && $txRes->num_rows > 0) {
                    $tx = $txRes->fetch_assoc();
                    $txStatus = (string)$tx['status'];
                    $promoId = intval($tx['promo_id'] ?? 0);
                }
                $stmtTx->close();
            }
        }

        if ($txStatus === 'pending') {
            $credit = function_exists('propay_calculate_deposit_credit') ? propay_calculate_deposit_credit($conn, $uid, $amount, $promoId, $order['method'] ?? '') : array('total_money' => $amount, 'target_add' => $amount, 'bonus_amount' => 0);
            $totalMoney = (float)$credit['total_money'];
            $targetAdd = (float)$credit['target_add'];
            $bonusAmount = (float)$credit['bonus_amount'];
            $note = 'LGPay auto verified via ' . $source . '. Bonus: ' . lgpay_format_amount($bonusAmount) . ', Wager add: ' . lgpay_format_amount($targetAdd);

            $stmtUser = $conn->prepare("UPDATE users SET balance = balance + ?, turnover_target = GREATEST(COALESCE(turnover_target,0), COALESCE(turnover_completed,0)) + ? WHERE id=?");
            if (!$stmtUser) { throw new RuntimeException('Unable to credit user.'); }
            $stmtUser->bind_param('ddi', $totalMoney, $targetAdd, $uid);
            $stmtUser->execute();
            $stmtUser->close();

            if ($txId > 0) {
                $approved = 'approved';
                $gwMsg = trim((string)($payload['msg'] ?? 'LGPay Success'));
                $stmtUpdateTx = $conn->prepare("UPDATE transactions_fake SET status=?, transaction_id=?, lg_order_sn=?, admin_note=?, is_notified=0 WHERE id=? AND status='pending'");
                if ($stmtUpdateTx) { $stmtUpdateTx->bind_param('ssssi', $approved, $orderNo, $gwMsg, $note, $txId); $stmtUpdateTx->execute(); $stmtUpdateTx->close(); }
                if (function_exists('wcb_referral_award_for_deposit')) { wcb_referral_award_for_deposit($conn, $uid, $txId, $amount); }
            }
        }

        $success = 'success';
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='success', gateway_status=?, callback_payload=?, credited_at=NOW(), updated_at=NOW() WHERE order_no=? AND gateway='lgpay' AND type='deposit'");
        if ($stmtOrder) { $stmtOrder->bind_param('sss', $success, $payloadJson, $orderNo); $stmtOrder->execute(); $stmtOrder->close(); }
        $conn->commit();
        lgpay_update_health($conn, 'up', 'Deposit callback verified.');
        return array('success' => true, 'http_code' => 200, 'message' => 'Success');
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'http_code' => 500, 'message' => 'Server error: ' . $e->getMessage());
    }
}

function lgpay_query_deposit_order($conn, $orderNo) {
    lgpay_ensure_schema($conn);
    $settings = lgpay_get_settings($conn);
    $params = array('app_id' => trim((string)$settings['merchant_code']), 'order_sn' => trim((string)$orderNo));
    if ($params['app_id'] === '' || $params['order_sn'] === '' || trim((string)$settings['secret_code']) === '') {
        return array('success' => false, 'message' => 'LG Pay credentials or order number missing.');
    }
    $params['sign'] = lgpay_md5_sign($params, trim((string)$settings['secret_code']));
    $http = lgpay_http_post(lgpay_endpoint($conn, 'order/query'), $params, 25);
    $decoded = json_decode($http['body'], true);
    if (!$http['success'] || !is_array($decoded)) { return array('success' => false, 'message' => $http['error'] ?: 'Invalid LG Pay query response.', 'raw' => $http['body']); }
    return array('success' => true, 'data' => $decoded, 'raw' => $http['body']);
}

function lgpay_sync_deposit_order($conn, $orderNo) {
    $q = lgpay_query_deposit_order($conn, $orderNo);
    if (empty($q['success'])) { return $q; }
    $decoded = $q['data'];
    if (intval($decoded['status'] ?? 0) === 1) {
        $money = isset($decoded['data']['money']) ? intval($decoded['data']['money']) : 0;
        if ($money <= 0) {
            $stmt = $conn->prepare("SELECT amount FROM payment_gateway_orders WHERE order_no=? AND gateway='lgpay' AND type='deposit' LIMIT 1");
            if ($stmt) { $stmt->bind_param('s', $orderNo); $stmt->execute(); $res = $stmt->get_result(); if ($res && $row = $res->fetch_assoc()) { $money = lgpay_money_to_minor((float)$row['amount']); } $stmt->close(); }
        }
        $payload = array('order_sn' => $orderNo, 'money' => $money, 'status' => 1, 'pay_time' => date('Y-m-d H:i:s'), 'msg' => 'Verified by query');
        $settings = lgpay_get_settings($conn);
        $payload['sign'] = lgpay_md5_sign($payload, trim((string)$settings['secret_code']));
        return lgpay_apply_deposit_success($conn, $payload, 'query');
    }
    return array('success' => false, 'message' => 'Deposit is not paid yet.', 'gateway_response' => $decoded);
}

function lgpay_payout_callback_url() {
    return lgpay_url('/api/lgpay_payout_callback.php');
}

function lgpay_submit_withdrawal_from_transaction($conn, $transactionId, $adminId = 0) {
    lgpay_ensure_schema($conn);
    $settings = lgpay_get_settings($conn);
    $appId = trim((string)$settings['merchant_code']);
    $secret = trim((string)$settings['secret_code']);
    if (intval($settings['is_enabled'] ?? 0) !== 1 || $appId === '' || $secret === '') {
        return array('success' => false, 'message' => 'LG Pay Merchant/App ID or Secret Key is not configured in Admin Panel.');
    }

    $transactionId = intval($transactionId);
    $adminId = intval($adminId);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT t.*, u.username, u.phone FROM transactions_fake t JOIN users u ON u.id=t.user_id WHERE t.id=? AND t.type='withdraw' LIMIT 1 FOR UPDATE");
        if (!$stmt) { throw new RuntimeException('Unable to load withdrawal request.'); }
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $conn->rollback(); return array('success' => false, 'message' => 'Withdrawal request was not found.'); }
        $tx = $res->fetch_assoc();
        $stmt->close();
        if (!in_array((string)$tx['status'], array('pending','processing'), true)) { $conn->rollback(); return array('success' => false, 'message' => 'Withdrawal request was already processed.'); }

        $uid = intval($tx['user_id']);
        $amount = round((float)$tx['amount'], 2);
        $accountNo = preg_replace('/\D+/', '', (string)($tx['wallet_number'] ?? ''));
        $methodKey = lgpay_normalize_method($tx['withdraw_method_code'] ?? $tx['method'] ?? '');
        if ($methodKey === '') { $methodKey = 'bkash'; }
        if (!preg_match('/^01\d{9}$/', $accountNo)) { $conn->rollback(); return array('success' => false, 'message' => 'Withdraw account must be a valid 11 digit Bangladesh mobile number.'); }
        if ($amount <= 0) { $conn->rollback(); return array('success' => false, 'message' => 'Invalid withdrawal amount.'); }

        $orderNo = trim((string)($tx['order_sn'] ?? ''));
        if ($orderNo === '' || strpos($orderNo, 'LGW') !== 0) { $orderNo = lgpay_make_order_no('LGW', $uid); }
        $name = trim((string)($tx['username'] ?? 'User')) ?: 'User';
        $params = array(
            'app_id' => $appId,
            'order_sn' => $orderNo,
            'currency' => 'BDT',
            'money' => lgpay_money_to_minor($amount),
            'notify_url' => lgpay_payout_callback_url(),
            'name' => $name,
            'card_number' => $accountNo
        );
        $params['sign'] = lgpay_md5_sign($params, $secret);
        $rawRequest = lgpay_json(lgpay_mask_request($params));

        $stmtFind = $conn->prepare("SELECT id FROM payment_gateway_orders WHERE order_no=? AND gateway='lgpay' AND type='withdraw' LIMIT 1");
        $existingOrderId = 0;
        if ($stmtFind) { $stmtFind->bind_param('s', $orderNo); $stmtFind->execute(); $findRes = $stmtFind->get_result(); if ($findRes && $row = $findRes->fetch_assoc()) { $existingOrderId = intval($row['id']); } $stmtFind->close(); }
        if ($existingOrderId <= 0) {
            $stmtOrder = $conn->prepare("INSERT INTO payment_gateway_orders (transaction_id, order_no, user_id, gateway, type, method, account_number, amount, status, gateway_status, raw_request, created_at)
                VALUES (?, ?, ?, 'lgpay', 'withdraw', ?, ?, ?, 'processing', 'submitting', ?, NOW())");
            if (!$stmtOrder) { throw new RuntimeException('Unable to create payout order.'); }
            $stmtOrder->bind_param('isissds', $transactionId, $orderNo, $uid, $methodKey, $accountNo, $amount, $rawRequest);
            if (!$stmtOrder->execute()) { throw new RuntimeException('Unable to create payout order.'); }
            $stmtOrder->close();
        } else {
            $stmtOrderUp = $conn->prepare("UPDATE payment_gateway_orders SET raw_request=?, gateway_status='submitting', status='processing', updated_at=NOW() WHERE id=?");
            if ($stmtOrderUp) { $stmtOrderUp->bind_param('si', $rawRequest, $existingOrderId); $stmtOrderUp->execute(); $stmtOrderUp->close(); }
        }
        $note = 'LGPay payout approved by admin #' . $adminId . ' and submitted to gateway.';
        $methodText = 'LGPay ' . lgpay_method_label($methodKey) . ' (' . $accountNo . ')';
        $stmtTx = $conn->prepare("UPDATE transactions_fake SET status='processing', method=?, order_sn=?, transaction_id=?, lg_order_sn=?, agent_id=?, admin_note=? WHERE id=? AND status IN ('pending','processing')");
        if ($stmtTx) { $stmtTx->bind_param('ssssisi', $methodText, $orderNo, $orderNo, $orderNo, $adminId, $note, $transactionId); }
        // bind_param type string above is hard to read; if it failed, fallback below after rollback.
        if (!$stmtTx || !$stmtTx->execute()) { throw new RuntimeException('Unable to update withdrawal transaction.'); }
        $stmtTx->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'message' => $e->getMessage() ?: 'Unable to submit LG Pay payout.');
    }

    $http = lgpay_http_post(lgpay_endpoint($conn, 'deposit/create'), $params, 35);
    $rawResponse = $http['body'];
    $decoded = json_decode($rawResponse, true);
    $stmtResp = $conn->prepare("UPDATE payment_gateway_orders SET raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='lgpay' AND type='withdraw'");
    if ($stmtResp) { $stmtResp->bind_param('ss', $rawResponse, $orderNo); $stmtResp->execute(); $stmtResp->close(); }

    if (!$http['success'] || !is_array($decoded)) {
        $message = $http['error'] !== '' ? $http['error'] : 'Invalid response from LG Pay payout API.';
        lgpay_fail_withdraw_order($conn, $orderNo, $rawResponse, $message, true);
        lgpay_update_health($conn, 'down', $message);
        return array('success' => false, 'message' => $message);
    }

    $status = intval($decoded['status'] ?? 0);
    $message = trim((string)($decoded['msg'] ?? '')) ?: ($status === 1 ? 'ok' : 'LG Pay rejected the payout.');
    if ($status !== 1) {
        lgpay_fail_withdraw_order($conn, $orderNo, $rawResponse, $message, true);
        lgpay_update_health($conn, 'down', $message);
        return array('success' => false, 'message' => $message, 'gateway_response' => $decoded);
    }

    $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status='processing', raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='lgpay' AND type='withdraw'");
    if ($stmtUp) { $stmtUp->bind_param('ss', $rawResponse, $orderNo); $stmtUp->execute(); $stmtUp->close(); }
    lgpay_update_health($conn, 'up', 'Payout submitted.');
    return array('success' => true, 'message' => 'Withdrawal submitted to LG Pay successfully. Final status will update by callback/query.', 'order_no' => $orderNo, 'gateway_status' => 'processing', 'gateway_response' => $decoded);
}

function lgpay_fail_withdraw_order($conn, $orderNo, $rawResponse, $reason, $refund) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='lgpay' AND type='withdraw' LIMIT 1 FOR UPDATE");
        if (!$stmt) { throw new RuntimeException('Unable to read order.'); }
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $conn->rollback(); return false; }
        $order = $res->fetch_assoc();
        $stmt->close();
        if ($order['status'] === 'failed' || $order['status'] === 'success') { $conn->commit(); return true; }
        $txId = intval($order['transaction_id']);
        $uid = intval($order['user_id']);
        $amount = (float)$order['amount'];
        $txStatus = '';
        if ($txId > 0) {
            $stmtTx = $conn->prepare("SELECT status FROM transactions_fake WHERE id=? LIMIT 1 FOR UPDATE");
            if ($stmtTx) { $stmtTx->bind_param('i', $txId); $stmtTx->execute(); $txRes = $stmtTx->get_result(); if ($txRes && $row = $txRes->fetch_assoc()) { $txStatus = (string)$row['status']; } $stmtTx->close(); }
        }
        if ($refund && in_array($txStatus, array('pending','processing'), true)) {
            $stmtRefund = $conn->prepare("UPDATE users SET balance=balance+? WHERE id=?");
            if ($stmtRefund) { $stmtRefund->bind_param('di', $amount, $uid); $stmtRefund->execute(); $stmtRefund->close(); }
        }
        if ($txId > 0 && in_array($txStatus, array('pending','processing'), true)) {
            $note = 'LGPay withdrawal failed and balance refunded: ' . $reason;
            $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET status='rejected', admin_note=? WHERE id=? AND status IN ('pending','processing')");
            if ($stmtTxUp) { $stmtTxUp->bind_param('si', $note, $txId); $stmtTxUp->execute(); $stmtTxUp->close(); }
        }
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='failed', gateway_status='failed', raw_response=?, callback_payload=COALESCE(callback_payload, ?), updated_at=NOW() WHERE order_no=? AND gateway='lgpay' AND type='withdraw'");
        $reasonJson = lgpay_json(array('reason' => (string)$reason));
        if ($stmtOrder) { $stmtOrder->bind_param('sss', $rawResponse, $reasonJson, $orderNo); $stmtOrder->execute(); $stmtOrder->close(); }
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function lgpay_mark_withdraw_success($conn, $orderNo, $gatewayStatus, $rawResponse, $callbackPayload = '') {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='lgpay' AND type='withdraw' LIMIT 1 FOR UPDATE");
        if (!$stmt) { throw new RuntimeException('Unable to read order.'); }
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $conn->rollback(); return false; }
        $order = $res->fetch_assoc();
        $stmt->close();
        if ($order['status'] === 'success') { $conn->commit(); return true; }
        if ($order['status'] === 'failed') { $conn->rollback(); return false; }
        $txId = intval($order['transaction_id']);
        if ($txId > 0) {
            $note = 'LGPay withdrawal completed successfully.';
            $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET status='approved', admin_note=? WHERE id=? AND status IN ('pending','processing')");
            if ($stmtTxUp) { $stmtTxUp->bind_param('si', $note, $txId); $stmtTxUp->execute(); $stmtTxUp->close(); }
        }
        $payload = $callbackPayload !== '' ? $callbackPayload : $rawResponse;
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='success', gateway_status=?, raw_response=?, callback_payload=?, credited_at=NOW(), updated_at=NOW() WHERE order_no=? AND gateway='lgpay' AND type='withdraw'");
        if ($stmtOrder) { $stmtOrder->bind_param('ssss', $gatewayStatus, $rawResponse, $payload, $orderNo); $stmtOrder->execute(); $stmtOrder->close(); }
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function lgpay_apply_payout_callback($conn, $payload) {
    lgpay_ensure_schema($conn);
    $settings = lgpay_get_settings($conn);
    if (!lgpay_verify_sign($payload, trim((string)$settings['secret_code']))) {
        return array('success' => false, 'http_code' => 403, 'message' => 'Invalid Signature');
    }
    $orderNo = trim((string)($payload['order_sn'] ?? ''));
    $status = intval($payload['status'] ?? -1);
    $payloadJson = lgpay_json($payload);
    if ($orderNo === '' || !in_array($status, array(0,1), true)) {
        return array('success' => false, 'http_code' => 400, 'message' => 'Invalid callback payload.');
    }
    if ($status === 1) {
        $ok = lgpay_mark_withdraw_success($conn, $orderNo, 'paid', $payloadJson, $payloadJson);
        return array('success' => $ok, 'http_code' => $ok ? 200 : 500, 'message' => $ok ? 'Success' : 'Unable to update withdrawal.');
    }
    $reason = trim((string)($payload['msg'] ?? 'LG Pay payout failed.'));
    $ok = lgpay_fail_withdraw_order($conn, $orderNo, $payloadJson, $reason, true);
    return array('success' => $ok, 'http_code' => $ok ? 200 : 500, 'message' => $ok ? 'Failed callback processed.' : 'Unable to update failed withdrawal.');
}

function lgpay_query_withdraw_order($conn, $orderNo) {
    lgpay_ensure_schema($conn);
    $settings = lgpay_get_settings($conn);
    $params = array('app_id' => trim((string)$settings['merchant_code']), 'order_sn' => trim((string)$orderNo));
    if ($params['app_id'] === '' || $params['order_sn'] === '' || trim((string)$settings['secret_code']) === '') {
        return array('success' => false, 'message' => 'LG Pay credentials or order number missing.');
    }
    $params['sign'] = lgpay_md5_sign($params, trim((string)$settings['secret_code']));
    $http = lgpay_http_post(lgpay_endpoint($conn, 'deposit/query'), $params, 25);
    $decoded = json_decode($http['body'], true);
    if (!$http['success'] || !is_array($decoded)) { return array('success' => false, 'message' => $http['error'] ?: 'Invalid LG Pay payout query response.', 'raw' => $http['body']); }
    return array('success' => true, 'data' => $decoded, 'raw' => $http['body']);
}

function lgpay_sync_pending_withdrawals($conn, $user_id = 0, $limit = 20) {
    lgpay_ensure_schema($conn);
    $limit = max(1, min(50, intval($limit)));
    $checked = 0;
    $updated = 0;
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT order_no FROM payment_gateway_orders WHERE gateway='lgpay' AND type='withdraw' AND status IN ('pending','processing') AND user_id=? ORDER BY id ASC LIMIT ?");
        if (!$stmt) { return array('checked' => 0, 'updated' => 0); }
        $stmt->bind_param('ii', $user_id, $limit);
    } else {
        $stmt = $conn->prepare("SELECT order_no FROM payment_gateway_orders WHERE gateway='lgpay' AND type='withdraw' AND status IN ('pending','processing') ORDER BY id ASC LIMIT ?");
        if (!$stmt) { return array('checked' => 0, 'updated' => 0); }
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $orderNo = trim((string)$row['order_no']);
        if ($orderNo === '') { continue; }
        $checked++;
        $q = lgpay_query_withdraw_order($conn, $orderNo);
        if (empty($q['success'])) { continue; }
        $decoded = $q['data'];
        $raw = (string)$q['raw'];
        $status = intval($decoded['status'] ?? -1);
        if ($status === 1) {
            if (lgpay_mark_withdraw_success($conn, $orderNo, 'paid', $raw)) { $updated++; }
        } elseif ($status === 0) {
            $reason = trim((string)($decoded['msg'] ?? 'LG Pay payout failed.'));
            if (lgpay_fail_withdraw_order($conn, $orderNo, $raw, $reason, true)) { $updated++; }
        } elseif ($status === 5) {
            $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status='processing', raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='lgpay' AND type='withdraw'");
            if ($stmtUp) { $stmtUp->bind_param('ss', $raw, $orderNo); $stmtUp->execute(); $stmtUp->close(); }
        }
    }
    $stmt->close();
    return array('checked' => $checked, 'updated' => $updated);
}

function lgpay_query_balance($conn) {
    lgpay_ensure_schema($conn);
    $settings = lgpay_get_settings($conn);
    $params = array('app_id' => trim((string)$settings['merchant_code']), 'time' => time());
    if ($params['app_id'] === '' || trim((string)$settings['secret_code']) === '') { return array('success' => false, 'message' => 'LG Pay credentials missing.'); }
    $params['sign'] = lgpay_md5_sign($params, trim((string)$settings['secret_code']));
    $http = lgpay_http_post(lgpay_endpoint($conn, 'deposit/balance'), $params, 25);
    $decoded = json_decode($http['body'], true);
    if (!$http['success'] || !is_array($decoded)) { return array('success' => false, 'message' => $http['error'] ?: 'Invalid LG Pay balance response.', 'raw' => $http['body']); }
    if (intval($decoded['status'] ?? 0) === 1 && isset($decoded['data']['balance'])) {
        return array('success' => true, 'balance' => lgpay_minor_to_money($decoded['data']['balance']), 'gateway_response' => $decoded, 'raw' => $http['body']);
    }
    return array('success' => false, 'message' => trim((string)($decoded['msg'] ?? 'Unable to read LG Pay balance.')), 'gateway_response' => $decoded, 'raw' => $http['body']);
}
?>
