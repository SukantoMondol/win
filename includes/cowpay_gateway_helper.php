<?php
if (!defined('COWPAY_HELPER_LOADED')) {
    define('COWPAY_HELPER_LOADED', true);
}
if (file_exists(__DIR__ . '/propay_gateway_helper.php')) { require_once __DIR__ . '/propay_gateway_helper.php'; }


function cowpay_safe_query($conn, $sql) {
    if (!$conn) { return false; }
    try { return @$conn->query($sql); }
    catch (Throwable $e) { error_log('Cowpay query failed: ' . $e->getMessage()); return false; }
}

function cowpay_safe_prepare($conn, $sql) {
    if (!$conn) { return false; }
    try { return @$conn->prepare($sql); }
    catch (Throwable $e) { error_log('Cowpay prepare failed: ' . $e->getMessage()); return false; }
}

function cowpay_column_exists($conn, $table, $column) {
    if (function_exists('propay_column_exists')) {
        try { return propay_column_exists($conn, $table, $column); }
        catch (Throwable $e) { error_log('Cowpay column check failed: ' . $e->getMessage()); }
    }
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
    $res = cowpay_safe_query($conn, "SHOW COLUMNS FROM `$safeTable` LIKE '" . $conn->real_escape_string($safeColumn) . "'");
    return $res && $res->num_rows > 0;
}

function cowpay_table_exists($conn, $table) {
    if (function_exists('propay_table_exists')) {
        try { return propay_table_exists($conn, $table); }
        catch (Throwable $e) { error_log('Cowpay table check failed: ' . $e->getMessage()); }
    }
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    $res = cowpay_safe_query($conn, "SHOW TABLES LIKE '" . $conn->real_escape_string($safe) . "'");
    return $res && $res->num_rows > 0;
}

function cowpay_add_column_if_missing($conn, $table, $column, $definition) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
    if (!cowpay_column_exists($conn, $safeTable, $safeColumn)) {
        return cowpay_safe_query($conn, "ALTER TABLE `$safeTable` ADD COLUMN `$safeColumn` $definition");
    }
    return true;
}

function cowpay_ensure_schema($conn) {
    if (!$conn || !empty($conn->connect_error)) { return false; }
    if (function_exists('propay_ensure_schema')) {
        try { propay_ensure_schema($conn); }
        catch (Throwable $e) { error_log('Cowpay schema base failed: ' . $e->getMessage()); }
    }
    if (cowpay_table_exists($conn, 'payment_gateway_settings')) {
        cowpay_add_column_if_missing($conn, 'payment_gateway_settings', 'api_base_url', "VARCHAR(255) DEFAULT NULL AFTER `secret_code`");
        $hasApi = cowpay_column_exists($conn, 'payment_gateway_settings', 'api_base_url');
        $stmt = cowpay_safe_prepare($conn, "SELECT id FROM payment_gateway_settings WHERE provider='cowpay' LIMIT 1");
        $exists = false;
        if ($stmt) {
            try { $stmt->execute(); $res = $stmt->get_result(); $exists = ($res && $res->num_rows > 0); }
            catch (Throwable $e) { error_log('Cowpay provider check failed: ' . $e->getMessage()); }
            $stmt->close();
        }
        if (!$exists) {
            if ($hasApi) {
                cowpay_safe_query($conn, "INSERT INTO `payment_gateway_settings` (`provider`, `merchant_code`, `secret_code`, `api_base_url`, `is_enabled`) VALUES ('cowpay', '', '', 'https://api.cowpay.co', 0)");
            } else {
                cowpay_safe_query($conn, "INSERT INTO `payment_gateway_settings` (`provider`, `merchant_code`, `secret_code`, `is_enabled`) VALUES ('cowpay', '', '', 0)");
            }
        }
    }
    if (cowpay_table_exists($conn, 'payment_gateway_orders')) {
        cowpay_add_column_if_missing($conn, 'payment_gateway_orders', 'gateway', "VARCHAR(40) NOT NULL DEFAULT 'propay' AFTER `user_id`");
        cowpay_add_column_if_missing($conn, 'payment_gateway_orders', 'channel', "VARCHAR(60) DEFAULT NULL AFTER `method`");
        cowpay_safe_query($conn, "UPDATE payment_gateway_orders SET gateway='propay' WHERE gateway IS NULL OR gateway=''");
    }
    return true;
}

function cowpay_get_settings($conn) {
    cowpay_ensure_schema($conn);
    $settings = array('merchant_code' => '', 'secret_code' => '', 'api_base_url' => 'https://api.cowpay.co', 'is_enabled' => 0, 'country_code' => 'BD');
    if (!cowpay_table_exists($conn, 'payment_gateway_settings')) { return $settings; }
    $hasApi = cowpay_column_exists($conn, 'payment_gateway_settings', 'api_base_url');
    $sql = $hasApi
        ? "SELECT merchant_code, secret_code, api_base_url, is_enabled FROM payment_gateway_settings WHERE provider='cowpay' LIMIT 1"
        : "SELECT merchant_code, secret_code, is_enabled FROM payment_gateway_settings WHERE provider='cowpay' LIMIT 1";
    $res = cowpay_safe_query($conn, $sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $settings['merchant_code'] = trim((string)($row['merchant_code'] ?? ''));
        $settings['secret_code'] = trim((string)($row['secret_code'] ?? ''));
        if ($hasApi) { $settings['api_base_url'] = trim((string)($row['api_base_url'] ?? '')) ?: 'https://api.cowpay.co'; }
        $settings['is_enabled'] = intval($row['is_enabled'] ?? 0);
    }
    return $settings;
}

function cowpay_save_settings($conn, $merchant_code, $secret_code, $api_base_url, $is_enabled) {
    $is_enabled = 0; // CowPay is disabled; LG Pay is the only active gateway.

    cowpay_ensure_schema($conn);
    if (!cowpay_table_exists($conn, 'payment_gateway_settings')) { return false; }
    $provider = 'cowpay';
    $merchant_code = trim((string)$merchant_code);
    $secret_code = trim((string)$secret_code);
    $api_base_url = trim((string)$api_base_url);
    if ($api_base_url === '') { $api_base_url = 'https://api.cowpay.co'; }
    $is_enabled = $is_enabled ? 1 : 0;
    $hasApi = cowpay_column_exists($conn, 'payment_gateway_settings', 'api_base_url');

    $id = 0;
    $stmt = cowpay_safe_prepare($conn, "SELECT id FROM payment_gateway_settings WHERE provider=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $provider);
        try { $stmt->execute(); $res = $stmt->get_result(); if ($res && ($row = $res->fetch_assoc())) { $id = intval($row['id']); } }
        catch (Throwable $e) { error_log('Cowpay settings lookup failed: ' . $e->getMessage()); }
        $stmt->close();
    }

    if ($id > 0) {
        if ($hasApi) {
            $stmt = cowpay_safe_prepare($conn, "UPDATE payment_gateway_settings SET merchant_code=?, secret_code=?, api_base_url=?, is_enabled=?, updated_at=NOW() WHERE id=?");
            if (!$stmt) { return false; }
            $stmt->bind_param('sssii', $merchant_code, $secret_code, $api_base_url, $is_enabled, $id);
        } else {
            $stmt = cowpay_safe_prepare($conn, "UPDATE payment_gateway_settings SET merchant_code=?, secret_code=?, is_enabled=?, updated_at=NOW() WHERE id=?");
            if (!$stmt) { return false; }
            $stmt->bind_param('ssii', $merchant_code, $secret_code, $is_enabled, $id);
        }
    } else {
        if ($hasApi) {
            $stmt = cowpay_safe_prepare($conn, "INSERT INTO payment_gateway_settings (provider, merchant_code, secret_code, api_base_url, is_enabled) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) { return false; }
            $stmt->bind_param('ssssi', $provider, $merchant_code, $secret_code, $api_base_url, $is_enabled);
        } else {
            $stmt = cowpay_safe_prepare($conn, "INSERT INTO payment_gateway_settings (provider, merchant_code, secret_code, is_enabled) VALUES (?, ?, ?, ?)");
            if (!$stmt) { return false; }
            $stmt->bind_param('sssi', $provider, $merchant_code, $secret_code, $is_enabled);
        }
    }
    try { $ok = $stmt->execute(); }
    catch (Throwable $e) { error_log('Cowpay settings save failed: ' . $e->getMessage()); $ok = false; }
    $stmt->close();
    return $ok;
}

function cowpay_base_url() {
    if (function_exists('propay_base_url')) { return propay_base_url(); }
    $https = false;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') { $https = true; }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') { $https = true; }
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = preg_replace('/[^a-zA-Z0-9\-\.:]/', '', $host);
    return $scheme . '://' . $host;
}

function cowpay_url($path) {
    return cowpay_base_url() . '/' . ltrim((string)$path, '/');
}

function cowpay_api_url($conn, $path) {
    $settings = cowpay_get_settings($conn);
    return rtrim((string)$settings['api_base_url'], '/') . '/' . ltrim((string)$path, '/');
}

function cowpay_json($data) {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $json === false ? '' : $json;
}

function cowpay_format_amount($amount) {
    return number_format((float)$amount, 2, '.', '');
}

function cowpay_normalize_method($method) {
    $m = strtolower(trim((string)$method));
    if (in_array($m, array('bkash','b-kash','bKash'), true)) { return 'bkash'; }
    if ($m === 'nagad') { return 'nagad'; }
    if ($m === 'bkash-native' || $m === 'bkash_native') { return 'bkash-native'; }
    return '';
}

function cowpay_method_label($method) {
    $m = cowpay_normalize_method($method);
    if ($m === 'bkash') { return 'bKash'; }
    if ($m === 'nagad') { return 'Nagad'; }
    if ($m === 'bkash-native') { return 'bKash Native'; }
    return strtoupper((string)$method);
}

function cowpay_sign_transdata($transdata, $secretKey) {
    if (!is_array($transdata)) { $transdata = array(); }
    ksort($transdata, SORT_STRING);
    $pairs = array();
    foreach ($transdata as $key => $value) {
        if ($key === 'sign') { continue; }
        if ($value === null) { continue; }
        if (is_bool($value)) { $value = $value ? 'true' : 'false'; }
        else { $value = (string)$value; }
        if (trim($value) === '') { continue; }
        $pairs[] = $key . '=' . $value;
    }
    $raw = implode('&', $pairs) . '&key=' . $secretKey;
    return strtoupper(md5($raw));
}

function cowpay_payload($transdata, $secretKey) {
    return array('sign' => cowpay_sign_transdata($transdata, $secretKey), 'signtype' => 'MD5', 'transdata' => $transdata);
}

function cowpay_verify_wrapper($wrapper, $secretKey) {
    if (!is_array($wrapper) || !isset($wrapper['transdata']) || !is_array($wrapper['transdata'])) { return false; }
    $given = strtoupper(trim((string)($wrapper['sign'] ?? '')));
    if ($given === '') { return false; }
    $expected = cowpay_sign_transdata($wrapper['transdata'], $secretKey);
    return hash_equals($expected, $given);
}

function cowpay_http_post($url, $transdata, $secretKey, $timeoutSeconds = 35) {
    $payload = cowpay_payload($transdata, $secretKey);
    $body = cowpay_json($payload);
    if (!function_exists('curl_init')) { return array('success' => false, 'http_code' => 0, 'error' => 'cURL unavailable', 'body' => ''); }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => max(15, intval($timeoutSeconds)),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/json', 'Content-Length: ' . strlen($body))
    ));
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $http = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    curl_close($curl);
    return array('success' => ($error === ''), 'http_code' => $http, 'error' => $error, 'body' => (string)$response);
}

function cowpay_is_available($conn, $checkRemote = true) {
    return false;
}


function cowpay_order_no($prefix, $uid) {
    return strtoupper($prefix) . date('ymdHis') . intval($uid) . mt_rand(100, 999);
}

function cowpay_create_deposit_order($conn, $uid, $amount, $method, $promo_id) {
    return array('success' => false, 'message' => 'CowPay is disabled. LG Pay is the only active payment gateway.');

    cowpay_ensure_schema($conn);
    $settings = cowpay_get_settings($conn);
    $merchant = trim((string)$settings['merchant_code']);
    $secret = trim((string)$settings['secret_code']);
    if (intval($settings['is_enabled'] ?? 0) !== 1 || $merchant === '' || $secret === '') { return array('success' => false, 'message' => 'Payment gateway is currently unavailable.'); }
    $txnSettings = function_exists('propay_get_site_transaction_settings') ? propay_get_site_transaction_settings($conn) : array('min_deposit_amount' => 100);
    $minDeposit = (float)($txnSettings['min_deposit_amount'] ?? 100);
    $amount = round((float)$amount, 2);
    if ($amount < $minDeposit) { return array('success' => false, 'message' => 'Minimum deposit amount is ৳' . cowpay_format_amount($minDeposit) . '.'); }
    $methodKey = cowpay_normalize_method($method);
    if ($methodKey === '') { return array('success' => false, 'message' => 'Invalid payment method.'); }
    $uid = intval($uid);
    $orderNo = cowpay_order_no('CD', $uid);
    $promo_id = intval($promo_id);
    $agentId = 0;
    $userStmt = $conn->prepare("SELECT agent_id FROM users WHERE id=? LIMIT 1");
    if ($userStmt) {
        $userStmt->bind_param('i', $uid);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        if ($userRes && $userRes->num_rows > 0) { $agentId = intval(($userRes->fetch_assoc())['agent_id'] ?? 0); }
        $userStmt->close();
    }
    $transdata = array(
        'merchant_code' => $merchant,
        'country_code' => 'BD',
        'order_no' => $orderNo,
        'order_amount' => cowpay_format_amount($amount),
        'pay_type' => $methodKey,
        'notify_url' => cowpay_url('/api/cowpay_callback.php'),
        'return_url' => cowpay_url('/player/propay_deposit_return.php?order_no=' . urlencode($orderNo))
    );
    $logPayload = cowpay_payload($transdata, '***hidden***');
    $logPayload['sign'] = '***hidden***';
    $rawRequest = cowpay_json($logPayload);
    $historyMethod = 'Cowpay ' . cowpay_method_label($methodKey);

    $conn->begin_transaction();
    try {
        $note = 'Cowpay deposit started';
        $stmt = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, promo_id, status, wallet_number, transaction_id, order_sn, agent_id, admin_note, created_at)
            VALUES (?, 'deposit', ?, ?, ?, 'pending', '', ?, ?, ?, ?, NOW())");
        $stmt->bind_param('idsissis', $uid, $amount, $historyMethod, $promo_id, $orderNo, $orderNo, $agentId, $note);
        $stmt->execute();
        $txId = intval($stmt->insert_id);
        $stmt2 = $conn->prepare("INSERT INTO payment_gateway_orders (transaction_id, order_no, user_id, gateway, type, method, amount, status, raw_request, created_at)
            VALUES (?, ?, ?, 'cowpay', 'deposit', ?, ?, 'pending', ?, NOW())");
        $stmt2->bind_param('isisds', $txId, $orderNo, $uid, $methodKey, $amount, $rawRequest);
        $stmt2->execute();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Unable to start payment.');
    }

    $http = cowpay_http_post(cowpay_api_url($conn, '/pay'), $transdata, $secret, 35);
    $decoded = json_decode($http['body'], true);
    $rawResponse = $http['body'];
    if (!$http['success'] || !is_array($decoded)) {
        $message = $http['error'] !== '' ? $http['error'] : 'Invalid payment gateway response.';
        cowpay_mark_deposit_failed($conn, $orderNo, $rawResponse, $message);
        return array('success' => false, 'message' => $message);
    }
    $code = (string)($decoded['code'] ?? '');
    $ok = ($code === '0' || intval($decoded['code'] ?? -1) === 0) && (bool)($decoded['status'] ?? false);
    $payUrl = trim((string)($decoded['pay_url'] ?? ($decoded['data']['pay_url'] ?? '')));
    $platformOrder = trim((string)($decoded['plat_order_no'] ?? ($decoded['data']['plat_order_no'] ?? '')));
    if (!$ok || $payUrl === '') {
        $message = trim((string)($decoded['message'] ?? 'Payment gateway rejected the request.'));
        cowpay_mark_deposit_failed($conn, $orderNo, $rawResponse, $message);
        return array('success' => false, 'message' => $message, 'gateway_response' => $decoded);
    }
    $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_order_no=?, gateway_status='paying', raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='cowpay' AND type='deposit'");
    if ($stmtUp) { $stmtUp->bind_param('sss', $platformOrder, $rawResponse, $orderNo); $stmtUp->execute(); $stmtUp->close(); }
    return array('success' => true, 'order_no' => $orderNo, 'redirect_url' => $payUrl, 'gateway_response' => $decoded);
}

function cowpay_mark_deposit_failed($conn, $orderNo, $rawResponse, $reason) {
    $payload = cowpay_json(array('message' => (string)$reason, 'raw' => (string)$rawResponse));
    $stmt = $conn->prepare("UPDATE payment_gateway_orders SET status='failed', gateway_status='failed', raw_response=?, callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='cowpay' AND type='deposit'");
    if ($stmt) { $stmt->bind_param('sss', $rawResponse, $payload, $orderNo); $stmt->execute(); $stmt->close(); }
    $stmtTx = $conn->prepare("UPDATE transactions_fake SET status='rejected', admin_note=? WHERE order_sn=? AND status='pending'");
    if ($stmtTx) { $stmtTx->bind_param('ss', $reason, $orderNo); $stmtTx->execute(); $stmtTx->close(); }
}

function cowpay_finalize_deposit($conn, $orderNo, $amount, $gatewayStatus, $payloadJson, $platformOrder = '') {
    cowpay_ensure_schema($conn);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='cowpay' AND type='deposit' LIMIT 1 FOR UPDATE");
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $conn->rollback(); return array('success' => false, 'http_code' => 404, 'message' => 'fail'); }
        $order = $res->fetch_assoc();
        if ($order['status'] === 'success') { $conn->commit(); return array('success' => true, 'http_code' => 200, 'message' => 'success'); }
        if ($order['status'] === 'failed') { $conn->rollback(); return array('success' => false, 'http_code' => 400, 'message' => 'fail'); }
        $actualAmount = round((float)$amount, 2);
        if ($actualAmount <= 0) { $actualAmount = round((float)$order['amount'], 2); }
        $txId = intval($order['transaction_id']);
        $uid = intval($order['user_id']);
        $promoId = 0;
        $txStatus = 'pending';
        if ($txId > 0) {
            $stmtTx = $conn->prepare("SELECT id, status, promo_id FROM transactions_fake WHERE id=? LIMIT 1 FOR UPDATE");
            $stmtTx->bind_param('i', $txId);
            $stmtTx->execute();
            $txRes = $stmtTx->get_result();
            if ($txRes && $txRes->num_rows > 0) { $tx = $txRes->fetch_assoc(); $txStatus = $tx['status']; $promoId = intval($tx['promo_id'] ?? 0); }
        }
        if ($txStatus === 'pending') {
            $credit = function_exists('propay_calculate_deposit_credit') ? propay_calculate_deposit_credit($conn, $uid, $actualAmount, $promoId, $order['method'] ?? '') : array('total_money' => $actualAmount, 'target_add' => $actualAmount, 'bonus_amount' => 0);
            $totalMoney = (float)$credit['total_money'];
            $targetAdd = (float)$credit['target_add'];
            $bonusAmount = (float)$credit['bonus_amount'];
            $note = 'Cowpay auto verified. Bonus: ' . cowpay_format_amount($bonusAmount) . ', Wager add: ' . cowpay_format_amount($targetAdd);
            $stmtUser = $conn->prepare("UPDATE users SET balance = balance + ?, turnover_target = turnover_target + ? WHERE id=?");
            $stmtUser->bind_param('ddi', $totalMoney, $targetAdd, $uid);
            $stmtUser->execute();
            $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET amount=?, status='approved', transaction_id=?, admin_note=? WHERE id=? AND status='pending'");
            $stmtTxId = $platformOrder !== '' ? $platformOrder : $orderNo;
            $stmtTxUp->bind_param('dssi', $actualAmount, $stmtTxId, $note, $txId);
            $stmtTxUp->execute();
        }
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET amount=?, status='success', gateway_status=?, gateway_order_no=COALESCE(NULLIF(?, ''), gateway_order_no), callback_payload=?, credited_at=NOW(), updated_at=NOW() WHERE order_no=? AND gateway='cowpay'");
        $stmtOrder->bind_param('dssss', $actualAmount, $gatewayStatus, $platformOrder, $payloadJson, $orderNo);
        $stmtOrder->execute();
        $conn->commit();
        return array('success' => true, 'http_code' => 200, 'message' => 'success');
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'http_code' => 500, 'message' => 'fail');
    }
}

function cowpay_sync_deposit_order($conn, $orderNo) {
    cowpay_ensure_schema($conn);
    $settings = cowpay_get_settings($conn);
    $merchant = trim((string)$settings['merchant_code']);
    $secret = trim((string)$settings['secret_code']);
    if ($merchant === '' || $secret === '') { return false; }
    $transdata = array('merchant_code' => $merchant, 'country_code' => 'BD', 'order_no' => trim((string)$orderNo));
    if ($transdata['order_no'] === '') { return false; }
    $http = cowpay_http_post(cowpay_api_url($conn, '/queryPayOrder'), $transdata, $secret, 20);
    $decoded = json_decode($http['body'], true);
    if (!$http['success'] || !is_array($decoded)) { return false; }
    $status = strtolower(trim((string)($decoded['order_status'] ?? ($decoded['transdata']['order_status'] ?? ''))));
    $amount = (float)($decoded['order_amount'] ?? ($decoded['transdata']['order_amount'] ?? 0));
    $platformOrder = trim((string)($decoded['plat_order_no'] ?? ($decoded['transdata']['plat_order_no'] ?? '')));
    if ($status === 'success') { cowpay_finalize_deposit($conn, $transdata['order_no'], $amount, $status, cowpay_json($decoded), $platformOrder); return true; }
    if ($status === 'failed') { cowpay_mark_deposit_failed($conn, $transdata['order_no'], cowpay_json($decoded), 'Payment failed'); return true; }
    if ($status !== '') {
        $raw = cowpay_json($decoded);
        $stmt = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status=?, raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='cowpay' AND type='deposit'");
        if ($stmt) { $stmt->bind_param('sss', $status, $raw, $transdata['order_no']); $stmt->execute(); $stmt->close(); }
    }
    return false;
}

function cowpay_apply_deposit_callback($conn, $wrapper) {
    cowpay_ensure_schema($conn);
    $settings = cowpay_get_settings($conn);
    if (!cowpay_verify_wrapper($wrapper, trim((string)$settings['secret_code']))) { return array('success' => false, 'http_code' => 403, 'message' => 'fail'); }
    $data = $wrapper['transdata'];
    $orderNo = trim((string)($data['order_no'] ?? ''));
    if ($orderNo === '') { return array('success' => false, 'http_code' => 400, 'message' => 'fail'); }
    $status = strtolower(trim((string)($data['order_status'] ?? '')));
    $amount = (float)($data['order_amount'] ?? 0);
    $platformOrder = trim((string)($data['plat_order_no'] ?? ''));
    $payloadJson = cowpay_json($wrapper);
    if ($status === 'success') { return cowpay_finalize_deposit($conn, $orderNo, $amount, 'success', $payloadJson, $platformOrder); }
    if ($status === 'failed') { cowpay_mark_deposit_failed($conn, $orderNo, $payloadJson, trim((string)($data['message'] ?? 'Payment failed'))); return array('success' => true, 'http_code' => 200, 'message' => 'success'); }
    $stmt = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status=?, callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='cowpay' AND type='deposit'");
    if ($stmt) { $stmt->bind_param('sss', $status, $payloadJson, $orderNo); $stmt->execute(); $stmt->close(); }
    return array('success' => true, 'http_code' => 200, 'message' => 'success');
}

function cowpay_check_merchant_balance($conn) {
    cowpay_ensure_schema($conn);
    $settings = cowpay_get_settings($conn);
    $merchant = trim((string)$settings['merchant_code']);
    $secret = trim((string)$settings['secret_code']);
    if (intval($settings['is_enabled'] ?? 0) !== 1 || $merchant === '' || $secret === '') { return array('success' => false, 'balance' => 0, 'message' => 'Cowpay is not configured.'); }
    $transdata = array('merchant_code' => $merchant, 'country_code' => 'BD');
    $http = cowpay_http_post(cowpay_api_url($conn, '/v2/queryBalance'), $transdata, $secret, 20);
    $decoded = json_decode($http['body'], true);
    if (!$http['success'] || !is_array($decoded)) { return array('success' => false, 'balance' => 0, 'message' => $http['error'] ?: 'Cowpay balance response unavailable.'); }
    $bd = is_array($decoded['BD'] ?? null) ? $decoded['BD'] : (is_array($decoded['data']['BD'] ?? null) ? $decoded['data']['BD'] : array());
    $balance = null;
    if (isset($bd['canPayoutBalance'])) { $balance = (float)$bd['canPayoutBalance']; }
    elseif (isset($bd['balance'])) { $balance = (float)$bd['balance']; }
    if ($balance === null) { return array('success' => false, 'balance' => 0, 'message' => 'Cowpay balance field not found.', 'raw' => $decoded); }
    return array('success' => true, 'balance' => $balance, 'raw' => $decoded);
}

function cowpay_submit_pending_withdraw($conn, $tx, $orderNo, $methodKey, $accountNo, $adminId) {
    return array('success' => false, 'message' => 'CowPay payout is disabled. LG Pay is the only active payout gateway.');

    cowpay_ensure_schema($conn);
    $settings = cowpay_get_settings($conn);
    $merchant = trim((string)$settings['merchant_code']);
    $secret = trim((string)$settings['secret_code']);
    if (intval($settings['is_enabled'] ?? 0) !== 1 || $merchant === '' || $secret === '') { return array('success' => false, 'message' => 'Cowpay is not configured.'); }
    $methodKey = cowpay_normalize_method($methodKey);
    if (!in_array($methodKey, array('bkash','nagad'), true)) { return array('success' => false, 'message' => 'Selected method is not supported by Cowpay.'); }
    $amount = round((float)$tx['amount'], 2);
    $account = preg_replace('/\D+/', '', (string)$accountNo);
    $username = trim((string)($tx['username'] ?? '')) ?: trim((string)($tx['phone'] ?? 'User'));
    $transdata = array(
        'merchant_code' => $merchant,
        'country_code' => 'BD',
        'order_no' => $orderNo,
        'order_amount' => cowpay_format_amount($amount),
        'pay_type' => 'bengalCommon',
        'bank_code' => $methodKey,
        'bank_card_no' => $account,
        'notify_url' => cowpay_url('/api/cowpay_payout_callback.php'),
        'bene_name' => $username
    );
    $logPayload = cowpay_payload($transdata, '***hidden***');
    $logPayload['sign'] = '***hidden***';
    $rawRequest = cowpay_json($logPayload);
    $txId = intval($tx['id']);

    $conn->begin_transaction();
    try {
        $stmtTx = $conn->prepare("UPDATE transactions_fake SET status='processing', agent_id=?, admin_note='Approved. Processing by Cowpay.', order_sn=? WHERE id=? AND status='pending'");
        $stmtTx->bind_param('isi', $adminId, $orderNo, $txId);
        $stmtTx->execute();
        if ($stmtTx->affected_rows < 1) { $conn->rollback(); return array('success' => false, 'message' => 'Withdraw request is already processed.'); }
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET gateway='cowpay', status='processing', gateway_status='submitting', raw_request=?, updated_at=NOW() WHERE transaction_id=? AND type='withdraw'");
        if ($stmtOrder) { $stmtOrder->bind_param('si', $rawRequest, $txId); $stmtOrder->execute(); }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Unable to process withdraw request.');
    }

    $http = cowpay_http_post(cowpay_api_url($conn, '/v2/withdraw'), $transdata, $secret, 35);
    $decoded = json_decode($http['body'], true);
    $rawResponse = $http['body'];
    if (!$http['success'] || !is_array($decoded)) {
        if (function_exists('wcb_keep_withdraw_pending')) { wcb_keep_withdraw_pending($conn, $txId, $orderNo, 'cowpay', 'Gateway response unavailable.', $rawResponse); }
        return array('success' => false, 'message' => 'Gateway response unavailable.');
    }
    $codeOk = ((string)($decoded['code'] ?? '') === '0' || intval($decoded['code'] ?? -1) === 0);
    $statusOk = (bool)($decoded['status'] ?? false);
    $platformOrder = trim((string)($decoded['plat_order_no'] ?? ''));
    if (!$codeOk || !$statusOk) {
        $message = trim((string)($decoded['message'] ?? 'Gateway rejected the withdrawal.'));
        if (function_exists('wcb_keep_withdraw_pending')) { wcb_keep_withdraw_pending($conn, $txId, $orderNo, 'cowpay', $message, $rawResponse); }
        return array('success' => false, 'message' => $message);
    }
    $gatewayStatus = strtolower(trim((string)($decoded['order_status'] ?? 'payouting')));
    if ($gatewayStatus === '') { $gatewayStatus = 'payouting'; }
    $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_order_no=?, gateway_status=?, raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='cowpay' AND type='withdraw'");
    if ($stmtUp) { $stmtUp->bind_param('ssss', $platformOrder, $gatewayStatus, $rawResponse, $orderNo); $stmtUp->execute(); $stmtUp->close(); }
    $note = 'Approved. Cowpay payout submitted.';
    $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET lg_order_sn=?, admin_note=? WHERE id=? AND status='processing'");
    if ($stmtTxUp) { $stmtTxUp->bind_param('ssi', $platformOrder, $note, $txId); $stmtTxUp->execute(); $stmtTxUp->close(); }
    if (cowpay_status_is_success($gatewayStatus)) { cowpay_mark_withdraw_success($conn, $orderNo, $gatewayStatus, $rawResponse); }
    if (cowpay_status_is_failed($gatewayStatus)) { cowpay_fail_withdraw_order($conn, $orderNo, $rawResponse, 'Gateway failed.', true); }
    return array('success' => true, 'message' => 'Withdraw approved and sent to Cowpay.');
}

function cowpay_status_is_success($status) {
    $s = strtolower(trim((string)$status));
    return in_array($s, array('success','paid','completed','complete','approved'), true);
}

function cowpay_status_is_failed($status) {
    $s = strtolower(trim((string)$status));
    return in_array($s, array('failed','fail','rejected','cancelled','canceled'), true);
}

function cowpay_fail_withdraw_order($conn, $orderNo, $rawResponse, $reason, $refund) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='cowpay' AND type='withdraw' LIMIT 1 FOR UPDATE");
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $conn->rollback(); return false; }
        $order = $res->fetch_assoc();
        if ($order['status'] === 'failed' || $order['status'] === 'success') { $conn->commit(); return true; }
        $txId = intval($order['transaction_id']);
        $uid = intval($order['user_id']);
        $amount = (float)$order['amount'];
        $txStatus = '';
        if ($txId > 0) {
            $stmtTx = $conn->prepare("SELECT status FROM transactions_fake WHERE id=? LIMIT 1 FOR UPDATE");
            $stmtTx->bind_param('i', $txId);
            $stmtTx->execute();
            $txRes = $stmtTx->get_result();
            if ($txRes && $txRes->num_rows > 0) { $txStatus = (string)$txRes->fetch_assoc()['status']; }
        }
        if ($refund && in_array($txStatus, array('pending','processing'), true)) {
            $stmtRefund = $conn->prepare("UPDATE users SET balance=balance+? WHERE id=?");
            $stmtRefund->bind_param('di', $amount, $uid);
            $stmtRefund->execute();
        }
        if ($txId > 0 && in_array($txStatus, array('pending','processing'), true)) {
            $note = 'Cowpay withdrawal failed and balance refunded: ' . $reason;
            $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET status='rejected', admin_note=? WHERE id=? AND status IN ('pending','processing')");
            $stmtTxUp->bind_param('si', $note, $txId);
            $stmtTxUp->execute();
        }
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='failed', gateway_status='failed', raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='cowpay'");
        $stmtOrder->bind_param('ss', $rawResponse, $orderNo);
        $stmtOrder->execute();
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function cowpay_mark_withdraw_success($conn, $orderNo, $gatewayStatus, $rawResponse) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='cowpay' AND type='withdraw' LIMIT 1 FOR UPDATE");
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $conn->rollback(); return false; }
        $order = $res->fetch_assoc();
        if ($order['status'] === 'success') { $conn->commit(); return true; }
        if ($order['status'] === 'failed') { $conn->rollback(); return false; }
        $txId = intval($order['transaction_id']);
        if ($txId > 0) {
            $note = 'Cowpay withdrawal completed successfully.';
            $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET status='approved', admin_note=? WHERE id=? AND status IN ('pending','processing')");
            $stmtTxUp->bind_param('si', $note, $txId);
            $stmtTxUp->execute();
        }
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='success', gateway_status=?, raw_response=?, credited_at=NOW(), updated_at=NOW() WHERE order_no=? AND gateway='cowpay'");
        $stmtOrder->bind_param('sss', $gatewayStatus, $rawResponse, $orderNo);
        $stmtOrder->execute();
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function cowpay_apply_payout_callback($conn, $wrapper) {
    cowpay_ensure_schema($conn);
    $settings = cowpay_get_settings($conn);
    if (!cowpay_verify_wrapper($wrapper, trim((string)$settings['secret_code']))) { return array('success' => false, 'http_code' => 403, 'message' => 'fail'); }
    $data = $wrapper['transdata'];
    $orderNo = trim((string)($data['order_no'] ?? ''));
    if ($orderNo === '') { return array('success' => false, 'http_code' => 400, 'message' => 'fail'); }
    $status = strtolower(trim((string)($data['order_status'] ?? '')));
    $payloadJson = cowpay_json($wrapper);
    $platformOrder = trim((string)($data['plat_order_no'] ?? ''));
    if ($platformOrder !== '') {
        $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_order_no=COALESCE(NULLIF(gateway_order_no, ''), ?), callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='cowpay' AND type='withdraw'");
        if ($stmtUp) { $stmtUp->bind_param('sss', $platformOrder, $payloadJson, $orderNo); $stmtUp->execute(); $stmtUp->close(); }
    }
    if (cowpay_status_is_success($status)) {
        $ok = cowpay_mark_withdraw_success($conn, $orderNo, 'success', $payloadJson);
        return array('success' => $ok, 'http_code' => $ok ? 200 : 500, 'message' => $ok ? 'success' : 'fail');
    }
    if (cowpay_status_is_failed($status)) {
        $reason = trim((string)($data['message'] ?? 'Payout failed'));
        $ok = cowpay_fail_withdraw_order($conn, $orderNo, $payloadJson, $reason, true);
        return array('success' => $ok, 'http_code' => $ok ? 200 : 500, 'message' => $ok ? 'success' : 'fail');
    }
    $stmt = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status=?, callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='cowpay' AND type='withdraw'");
    if ($stmt) { $stmt->bind_param('sss', $status, $payloadJson, $orderNo); $stmt->execute(); $stmt->close(); }
    return array('success' => true, 'http_code' => 200, 'message' => 'success');
}

function cowpay_sync_pending_withdrawals($conn, $user_id = 0, $limit = 20) {
    cowpay_ensure_schema($conn);
    $settings = cowpay_get_settings($conn);
    $merchant = trim((string)$settings['merchant_code']);
    $secret = trim((string)$settings['secret_code']);
    if ($merchant === '' || $secret === '') { return array('checked' => 0, 'updated' => 0); }
    $limit = max(1, min(50, intval($limit)));
    $checked = 0;
    $updated = 0;
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT order_no FROM payment_gateway_orders WHERE type='withdraw' AND gateway='cowpay' AND status IN ('processing') AND user_id=? ORDER BY id ASC LIMIT ?");
        $stmt->bind_param('ii', $user_id, $limit);
    } else {
        $stmt = $conn->prepare("SELECT order_no FROM payment_gateway_orders WHERE type='withdraw' AND gateway='cowpay' AND status IN ('processing') ORDER BY id ASC LIMIT ?");
        $stmt->bind_param('i', $limit);
    }
    if (!$stmt) { return array('checked' => 0, 'updated' => 0); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $orderNo = trim((string)($row['order_no'] ?? ''));
        if ($orderNo === '') { continue; }
        $checked++;
        $transdata = array('merchant_code' => $merchant, 'country_code' => 'BD', 'order_no' => $orderNo);
        $http = cowpay_http_post(cowpay_api_url($conn, '/v2/queryWithdrawOrder'), $transdata, $secret, 20);
        $decoded = json_decode($http['body'], true);
        if (!$http['success'] || !is_array($decoded)) { continue; }
        $status = strtolower(trim((string)($decoded['order_status'] ?? ($decoded['transdata']['order_status'] ?? ''))));
        $raw = cowpay_json($decoded);
        if (cowpay_status_is_success($status)) {
            if (cowpay_mark_withdraw_success($conn, $orderNo, $status, $raw)) { $updated++; }
        } elseif (cowpay_status_is_failed($status)) {
            $reason = trim((string)($decoded['message'] ?? 'Gateway failed.'));
            if (cowpay_fail_withdraw_order($conn, $orderNo, $raw, $reason, true)) { $updated++; }
        } elseif ($status !== '') {
            $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status=?, raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='cowpay' AND type='withdraw'");
            if ($stmtUp) { $stmtUp->bind_param('sss', $status, $raw, $orderNo); $stmtUp->execute(); $stmtUp->close(); }
        }
    }
    $stmt->close();
    return array('checked' => $checked, 'updated' => $updated);
}
?>
