<?php
if (!defined('AKPAY_HELPER_LOADED')) {
    define('AKPAY_HELPER_LOADED', true);
}

if (!function_exists('akpay_ensure_base_helpers')) {
    function akpay_ensure_base_helpers() {
        if (!function_exists('propay_ensure_schema') && file_exists(__DIR__ . '/propay_gateway_helper.php')) {
            require_once __DIR__ . '/propay_gateway_helper.php';
        }
        if (!function_exists('lgpay_ensure_schema') && file_exists(__DIR__ . '/lgpay_gateway_helper.php')) {
            require_once __DIR__ . '/lgpay_gateway_helper.php';
        }
    }
}

function akpay_ensure_schema($conn) {
    if (function_exists('wcb_force_lgpay_only')) { @wcb_force_lgpay_only($conn); }

    akpay_ensure_base_helpers();
    if (!$conn || !empty($conn->connect_error)) { return false; }
    if (function_exists('propay_ensure_schema')) { propay_ensure_schema($conn); }
    if (function_exists('propay_table_exists') && function_exists('propay_add_column_if_missing')) {
        if (propay_table_exists($conn, 'payment_gateway_settings')) {
            propay_add_column_if_missing($conn, 'payment_gateway_settings', 'api_base_url', "VARCHAR(255) DEFAULT NULL AFTER `secret_code`");
            @$conn->query("INSERT IGNORE INTO `payment_gateway_settings` (`provider`, `merchant_code`, `secret_code`, `api_base_url`, `is_enabled`) VALUES ('akpay', '', '', '', 0)");
        }
        if (propay_table_exists($conn, 'payment_gateway_orders')) {
            propay_add_column_if_missing($conn, 'payment_gateway_orders', 'gateway', "VARCHAR(40) NOT NULL DEFAULT 'propay' AFTER `user_id`");
        }
    }
    return true;
}

function akpay_get_settings($conn) {
    akpay_ensure_schema($conn);
    $settings = array('merchant_code' => '', 'secret_code' => '', 'api_base_url' => '', 'is_enabled' => 0, 'failover_priority' => 2, 'last_health_status' => '', 'last_health_checked_at' => '', 'last_error' => '');
    $res = @$conn->query("SELECT merchant_code, secret_code, api_base_url, is_enabled, failover_priority, last_health_status, last_health_checked_at, last_error FROM payment_gateway_settings WHERE provider='akpay' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $settings['merchant_code'] = trim((string)($row['merchant_code'] ?? ''));
        $settings['secret_code'] = trim((string)($row['secret_code'] ?? ''));
        $settings['api_base_url'] = trim((string)($row['api_base_url'] ?? ''));
        $settings['is_enabled'] = intval($row['is_enabled'] ?? 1);
        $settings['failover_priority'] = max(1, intval($row['failover_priority'] ?? 2));
        $settings['last_health_status'] = trim((string)($row['last_health_status'] ?? ''));
        $settings['last_health_checked_at'] = trim((string)($row['last_health_checked_at'] ?? ''));
        $settings['last_error'] = trim((string)($row['last_error'] ?? ''));
    }
    return $settings;
}

function akpay_save_settings($conn, $merchant_code, $secret_code, $api_base_url, $is_enabled) {
    $is_enabled = 0; // AKPay is disabled; LG Pay is the only active gateway.

    akpay_ensure_schema($conn);
    $provider = 'akpay';
    $merchant_code = trim((string)$merchant_code);
    $secret_code = trim((string)$secret_code);
    $api_base_url = trim((string)$api_base_url);
    $api_base_url = rtrim($api_base_url, '/');
    $is_enabled = $is_enabled ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO payment_gateway_settings (provider, merchant_code, secret_code, api_base_url, is_enabled)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE merchant_code=VALUES(merchant_code), secret_code=VALUES(secret_code), api_base_url=VALUES(api_base_url), is_enabled=VALUES(is_enabled), updated_at=NOW()");
    if (!$stmt) { return false; }
    $stmt->bind_param('ssssi', $provider, $merchant_code, $secret_code, $api_base_url, $is_enabled);
    return $stmt->execute();
}

function akpay_api_url($conn, $path) {
    $settings = akpay_get_settings($conn);
    $base = rtrim(trim((string)($settings['api_base_url'] ?? '')), '/');
    if ($base === '') { return ''; }
    return $base . '/' . ltrim($path, '/');
}

function akpay_json($data) {
    if (function_exists('propay_json')) { return propay_json($data); }
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function akpay_format_amount($amount) {
    if (function_exists('propay_format_amount')) { return propay_format_amount($amount); }
    return number_format((float)$amount, 2, '.', '');
}

function akpay_url($path) {
    if (function_exists('propay_url')) { return propay_url($path); }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9\-\.:]/', '', $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function akpay_order_no($prefix, $uid) {
    if (function_exists('propay_make_order_no')) { return propay_make_order_no($prefix, $uid); }
    return $prefix . date('ymdHis') . intval($uid) . mt_rand(100000, 999999);
}

function akpay_normalize_method($method) {
    $m = strtolower(trim((string)$method));
    $m = preg_replace('/[^a-z0-9]+/', '', $m);
    if ($m === 'bkash' || $m === 'bk') { return 'bkash'; }
    if ($m === 'nagad') { return 'nagad'; }
    if ($m === 'rocket') { return 'rocket'; }
    if ($m === 'upi') { return 'upi'; }
    return '';
}

function akpay_method_label($method) {
    $m = akpay_normalize_method($method);
    if ($m === 'bkash') { return 'bKash'; }
    if ($m === 'nagad') { return 'Nagad'; }
    if ($m === 'rocket') { return 'Rocket'; }
    if ($m === 'upi') { return 'UPI'; }
    return '';
}

function akpay_payout_method($method) {
    $m = akpay_normalize_method($method);
    if ($m === '') { return ''; }
    return strtoupper($m);
}

function akpay_generate_sign($params, $secretKey) {
    unset($params['sign']);
    ksort($params);
    $str = http_build_query($params) . '&key=' . $secretKey;
    return strtolower(md5($str));
}

function akpay_verify_sign($params, $secretKey) {
    $received = strtolower(trim((string)($params['sign'] ?? '')));
    if ($received === '' || trim((string)$secretKey) === '') { return false; }
    $expected = akpay_generate_sign($params, $secretKey);
    if (function_exists('hash_equals')) { return hash_equals($expected, $received); }
    return $expected === $received;
}

function akpay_http_post_form($url, $fields, $timeoutSeconds = 35) {
    if (!function_exists('curl_init')) {
        return array('success' => false, 'http_code' => 0, 'error' => 'cURL extension is not enabled.', 'body' => '');
    }

    // AKPay's live PHP endpoints read request values from form POST data.
    // Sending a JSON body makes every field appear missing even when the
    // payload is correct. Use application/x-www-form-urlencoded for API calls;
    // callbacks from AKPay are still handled as JSON by the callback scripts.
    $payload = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => max(10, intval($timeoutSeconds)),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($payload)
        )
    ));
    $body = curl_exec($curl);
    $err = curl_error($curl);
    $http = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    curl_close($curl);
    return array('success' => ($err === ''), 'http_code' => $http, 'error' => $err, 'body' => (string)$body);
}

// Backward-compatible wrapper for existing calls in older files.
function akpay_http_post_json($url, $fields, $timeoutSeconds = 35) {
    return akpay_http_post_form($url, $fields, $timeoutSeconds);
}

function wcb_gateway_url_reachable($url, $timeoutSeconds = 4) {
    $url = trim((string)$url);
    if ($url === '' || !function_exists('curl_init')) { return true; }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_CONNECTTIMEOUT => max(2, intval($timeoutSeconds)),
        CURLOPT_TIMEOUT => max(3, intval($timeoutSeconds) + 1),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_HTTPHEADER => array('User-Agent: Mozilla/5.0')
    ));
    curl_exec($curl);
    $err = curl_error($curl);
    $http = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    curl_close($curl);
    if ($err !== '') { return false; }
    if ($http === 0 || $http >= 500) { return false; }
    return true;
}

function wcb_propay_is_available($conn, $checkRemote = true) {
    return false;
}


function akpay_is_available($conn, $checkRemote = true) {
    return false;
}


function wcb_active_gateway($conn, $checkRemote = true) {
    if (function_exists('lgpay_ensure_schema')) { @lgpay_ensure_schema($conn); }
    if (function_exists('wcb_force_lgpay_only')) { @wcb_force_lgpay_only($conn); }
    if (function_exists('lgpay_is_available') && lgpay_is_available($conn, $checkRemote)) { return 'lgpay'; }
    if ($checkRemote && function_exists('lgpay_is_available') && lgpay_is_available($conn, false)) { return 'lgpay'; }
    return '';
}


function wcb_create_deposit_order_auto($conn, $uid, $amount, $method, $promo_id, $channel = '') {
    if (function_exists('lgpay_ensure_schema')) { @lgpay_ensure_schema($conn); }
    if (function_exists('wcb_force_lgpay_only')) { @wcb_force_lgpay_only($conn); }
    if (function_exists('lgpay_create_deposit_order')) {
        return lgpay_create_deposit_order($conn, $uid, $amount, $method, $promo_id, 'LG Pay');
    }
    return array('success' => false, 'message' => 'LG Pay gateway service is unavailable.');
}


function akpay_create_deposit_order($conn, $uid, $amount, $method, $promo_id) {
    return array('success' => false, 'message' => 'AKPay is disabled. LG Pay is the only active payment gateway.');

    akpay_ensure_schema($conn);
    $settings = akpay_get_settings($conn);
    $merchant = trim((string)$settings['merchant_code']);
    $secret = trim((string)$settings['secret_code']);
    if (intval($settings['is_enabled'] ?? 0) !== 1 || $merchant === '' || $secret === '' || trim((string)$settings['api_base_url']) === '') {
        return array('success' => false, 'message' => 'Payment gateway is currently unavailable.');
    }

    $txnSettings = function_exists('propay_get_site_transaction_settings') ? propay_get_site_transaction_settings($conn) : array('min_deposit_amount' => 100);
    $minDeposit = (float)($txnSettings['min_deposit_amount'] ?? 100);
    $amount = round((float)$amount, 2);
    if ($amount < $minDeposit) {
        return array('success' => false, 'message' => 'Minimum deposit amount is ৳' . akpay_format_amount($minDeposit) . '.');
    }

    $methodKey = akpay_normalize_method($method);
    if ($methodKey === '') { return array('success' => false, 'message' => 'Invalid payment method.'); }

    $orderNo = akpay_order_no('AD', $uid);
    $methodLabel = akpay_method_label($methodKey);
    $historyMethod = 'AKPay ' . $methodLabel;
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

    $payload = array(
        'mchId' => $merchant,
        'out_trade_no' => $orderNo,
        'money' => akpay_format_amount($amount),
        'pay_type' => $methodKey,
        'currency' => 'BDT',
        'notify_url' => akpay_url('/api/akpay_callback.php'),
        'returnUrl' => akpay_url('/player/propay_deposit_return.php?order_no=' . urlencode($orderNo))
    );
    $payload['sign'] = akpay_generate_sign($payload, $secret);
    $logPayload = $payload;
    $logPayload['sign'] = '***hidden***';
    $rawRequest = akpay_json($logPayload);
    $txId = 0;

    $conn->begin_transaction();
    try {
        $transactionIdForUser = $orderNo;
        $note = 'AKPay deposit started';
        $stmt = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, promo_id, status, wallet_number, transaction_id, order_sn, agent_id, admin_note, created_at)
            VALUES (?, 'deposit', ?, ?, ?, 'pending', '', ?, ?, ?, ?, NOW())");
        $stmt->bind_param('idsissis', $uid, $amount, $historyMethod, $promo_id, $transactionIdForUser, $orderNo, $agentId, $note);
        $stmt->execute();
        $txId = intval($stmt->insert_id);

        $stmt2 = $conn->prepare("INSERT INTO payment_gateway_orders (transaction_id, order_no, user_id, gateway, type, method, amount, status, raw_request, created_at)
            VALUES (?, ?, ?, 'akpay', 'deposit', ?, ?, 'pending', ?, NOW())");
        $stmt2->bind_param('isisds', $txId, $orderNo, $uid, $methodKey, $amount, $rawRequest);
        $stmt2->execute();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Unable to start payment.');
    }

    $http = akpay_http_post_form(akpay_api_url($conn, '/v1/collect'), $payload, 35);
    $decoded = json_decode($http['body'], true);
    $rawResponse = $http['body'];
    if (!$http['success'] || !is_array($decoded)) {
        $message = $http['error'] !== '' ? $http['error'] : 'Invalid payment gateway response.';
        akpay_mark_deposit_failed($conn, $orderNo, $rawResponse, $message);
        return array('success' => false, 'message' => $message);
    }

    $code = intval($decoded['code'] ?? -1);
    $url = trim((string)($decoded['data']['url'] ?? ''));
    $gatewayTxn = trim((string)($decoded['data']['transaction_Id'] ?? ($decoded['data']['transaction_id'] ?? '')));
    if ($code !== 0 || $url === '') {
        $message = trim((string)($decoded['msg'] ?? $decoded['message'] ?? 'Payment gateway rejected the request.'));
        akpay_mark_deposit_failed($conn, $orderNo, $rawResponse, $message);
        return array('success' => false, 'message' => $message, 'gateway_response' => $decoded);
    }

    $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_order_no=?, gateway_status='created', raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='akpay' AND type='deposit'");
    if ($stmtUp) {
        $stmtUp->bind_param('sss', $gatewayTxn, $rawResponse, $orderNo);
        $stmtUp->execute();
        $stmtUp->close();
    }
    return array('success' => true, 'order_no' => $orderNo, 'redirect_url' => $url, 'gateway_response' => $decoded);
}

function akpay_mark_deposit_failed($conn, $orderNo, $rawResponse, $reason) {
    $payload = akpay_json(array('message' => (string)$reason, 'raw' => (string)$rawResponse));
    $stmt = $conn->prepare("UPDATE payment_gateway_orders SET status='failed', gateway_status='failed', raw_response=?, callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='akpay' AND type='deposit'");
    if ($stmt) { $stmt->bind_param('sss', $rawResponse, $payload, $orderNo); $stmt->execute(); $stmt->close(); }
    $stmtTx = $conn->prepare("UPDATE transactions_fake SET status='rejected', admin_note=? WHERE order_sn=? AND status='pending'");
    if ($stmtTx) { $stmtTx->bind_param('ss', $reason, $orderNo); $stmtTx->execute(); $stmtTx->close(); }
}

function akpay_finalize_deposit($conn, $orderNo, $amount, $gatewayStatus, $payloadJson, $gatewayTxn = '') {
    akpay_ensure_schema($conn);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='akpay' AND type='deposit' LIMIT 1 FOR UPDATE");
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $orderRes = $stmt->get_result();
        if (!$orderRes || $orderRes->num_rows === 0) { $conn->rollback(); return array('success' => false, 'http_code' => 404, 'message' => 'Order not found.'); }
        $order = $orderRes->fetch_assoc();
        if ($order['status'] === 'success') { $conn->commit(); return array('success' => true, 'http_code' => 200, 'message' => 'ok'); }
        if ($order['status'] === 'failed') { $conn->rollback(); return array('success' => false, 'http_code' => 400, 'message' => 'Order failed.'); }
        $expectedAmount = (float)$order['amount'];
        $amount = (float)$amount;
        if ($amount <= 0) { $amount = $expectedAmount; }
        if (abs($expectedAmount - $amount) > 0.01) {
            $stmtMismatch = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status='amount_mismatch', callback_payload=?, updated_at=NOW() WHERE id=?");
            $oid = intval($order['id']);
            $stmtMismatch->bind_param('si', $payloadJson, $oid);
            $stmtMismatch->execute();
            $conn->commit();
            return array('success' => false, 'http_code' => 400, 'message' => 'Amount mismatch.');
        }

        $txId = intval($order['transaction_id']);
        $uid = intval($order['user_id']);
        $promoId = 0;
        $txStatus = 'pending';
        if ($txId > 0) {
            $stmtTx = $conn->prepare("SELECT id, status, promo_id FROM transactions_fake WHERE id=? LIMIT 1 FOR UPDATE");
            $stmtTx->bind_param('i', $txId);
            $stmtTx->execute();
            $txRes = $stmtTx->get_result();
            if ($txRes && $txRes->num_rows > 0) {
                $tx = $txRes->fetch_assoc();
                $txStatus = $tx['status'];
                $promoId = intval($tx['promo_id'] ?? 0);
            }
        }

        if ($txStatus === 'pending') {
            $credit = function_exists('propay_calculate_deposit_credit') ? propay_calculate_deposit_credit($conn, $uid, $expectedAmount, $promoId, $order['method'] ?? '') : array('total_money' => $expectedAmount, 'target_add' => $expectedAmount, 'bonus_amount' => 0);
            $totalMoney = (float)$credit['total_money'];
            $targetAdd = (float)$credit['target_add'];
            $bonusAmount = (float)$credit['bonus_amount'];
            $note = 'AKPay auto verified. Bonus: ' . akpay_format_amount($bonusAmount) . ', Wager add: ' . akpay_format_amount($targetAdd);
            $stmtUser = $conn->prepare("UPDATE users SET balance = balance + ?, turnover_target = GREATEST(COALESCE(turnover_target,0), COALESCE(turnover_completed,0)) + ? WHERE id=?");
            $stmtUser->bind_param('ddi', $totalMoney, $targetAdd, $uid);
            $stmtUser->execute();
            if ($txId > 0) {
                $approved = 'approved';
                $trx = $gatewayTxn !== '' ? $gatewayTxn : $orderNo;
                $stmtUpdateTx = $conn->prepare("UPDATE transactions_fake SET status=?, transaction_id=?, admin_note=?, is_notified=0 WHERE id=? AND status='pending'");
                $stmtUpdateTx->bind_param('sssi', $approved, $trx, $note, $txId);
                $stmtUpdateTx->execute();
                if (function_exists('wcb_referral_award_for_deposit')) { wcb_referral_award_for_deposit($conn, $uid, $txId, $expectedAmount); }
            }
        }

        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='success', gateway_status=?, gateway_order_no=COALESCE(NULLIF(?, ''), gateway_order_no), callback_payload=?, credited_at=NOW(), updated_at=NOW() WHERE order_no=? AND gateway='akpay'");
        $stmtOrder->bind_param('ssss', $gatewayStatus, $gatewayTxn, $payloadJson, $orderNo);
        $stmtOrder->execute();
        $conn->commit();
        return array('success' => true, 'http_code' => 200, 'message' => 'ok');
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'http_code' => 500, 'message' => 'Server error.');
    }
}

function akpay_apply_deposit_callback($conn, $payload) {
    akpay_ensure_schema($conn);
    $settings = akpay_get_settings($conn);
    $secret = trim((string)$settings['secret_code']);
    if (!akpay_verify_sign($payload, $secret)) { return array('success' => false, 'http_code' => 403, 'message' => 'fail'); }
    $orderNo = trim((string)($payload['out_trade_no'] ?? ''));
    $status = strtolower(trim((string)($payload['status'] ?? '')));
    $amount = (float)($payload['pay_money'] ?? $payload['amount'] ?? 0);
    $gatewayTxn = trim((string)($payload['transaction_id'] ?? ''));
    $payloadJson = akpay_json($payload);
    if ($orderNo === '') { return array('success' => false, 'http_code' => 400, 'message' => 'fail'); }
    if ($status !== 'success') {
        $message = trim((string)($payload['message'] ?? 'Payment failed'));
        akpay_mark_deposit_failed($conn, $orderNo, $payloadJson, $message);
        return array('success' => false, 'http_code' => 200, 'message' => 'ok');
    }
    return akpay_finalize_deposit($conn, $orderNo, $amount, 'success', $payloadJson, $gatewayTxn);
}

function akpay_sync_deposit_order($conn, $orderNo) {
    akpay_ensure_schema($conn);
    $orderNo = trim((string)$orderNo);
    if ($orderNo === '') { return false; }
    $settings = akpay_get_settings($conn);
    $payload = array('mchId' => $settings['merchant_code'], 'out_trade_no' => $orderNo);
    $payload['sign'] = akpay_generate_sign($payload, $settings['secret_code']);
    $http = akpay_http_post_form(akpay_api_url($conn, '/v1/order'), $payload, 20);
    $decoded = json_decode($http['body'], true);
    if (!$http['success'] || !is_array($decoded)) { return false; }
    $status = strtolower(trim((string)($decoded['data']['status'] ?? $decoded['status'] ?? '')));
    if (in_array($status, array('1','success','paid','completed','complete','approved'), true)) {
        $amount = (float)($decoded['data']['amount'] ?? 0);
        $gatewayTxn = trim((string)($decoded['data']['transaction_id'] ?? ''));
        akpay_finalize_deposit($conn, $orderNo, $amount, $status, akpay_json($decoded), $gatewayTxn);
        return true;
    }
    if (in_array($status, array('failed','fail','0','rejected','cancelled','canceled'), true)) {
        akpay_mark_deposit_failed($conn, $orderNo, akpay_json($decoded), 'Payment failed');
        return true;
    }
    return false;
}

function wcb_active_withdraw_gateway($conn, $checkRemote = true) {
    if (function_exists('lgpay_ensure_schema')) { @lgpay_ensure_schema($conn); }
    if (function_exists('wcb_force_lgpay_only')) { @wcb_force_lgpay_only($conn); }
    if (function_exists('lgpay_is_available') && lgpay_is_available($conn, $checkRemote)) { return 'lgpay'; }
    if ($checkRemote && function_exists('lgpay_is_available') && lgpay_is_available($conn, false)) { return 'lgpay'; }
    return '';
}


function wcb_submit_withdrawal_auto($conn, $uid, $amount, $wallet) {
    $routerFile = __DIR__ . '/withdrawal_gateway_router.php';
    if (file_exists($routerFile)) { require_once $routerFile; }
    if (function_exists('wcb_route_withdrawal')) {
        return wcb_route_withdrawal($conn, $uid, $amount, $wallet);
    }
    return array('success' => false, 'message' => 'Withdrawal routing service is unavailable.');
}

function akpay_submit_withdrawal($conn, $uid, $amount, $wallet) {
    return array('success' => false, 'message' => 'AKPay payout is disabled. LG Pay is the only active payout gateway.');

    akpay_ensure_schema($conn);
    if (function_exists('wcb_withdraw_ensure_schema')) { wcb_withdraw_ensure_schema($conn); }
    $settings = akpay_get_settings($conn);
    $merchant = trim((string)$settings['merchant_code']);
    $secret = trim((string)$settings['secret_code']);
    if (intval($settings['is_enabled'] ?? 0) !== 1 || $merchant === '' || $secret === '' || trim((string)$settings['api_base_url']) === '') {
        return array('success' => false, 'message' => 'Payment gateway is currently unavailable.');
    }

    $txnSettings = function_exists('propay_get_site_transaction_settings') ? propay_get_site_transaction_settings($conn) : array('min_withdraw_amount' => 100);
    $minWithdraw = (float)($txnSettings['min_withdraw_amount'] ?? 100);
    $amount = round((float)$amount, 2);
    if ($amount < $minWithdraw) { return array('success' => false, 'message' => 'Minimum withdrawal amount is ৳' . akpay_format_amount($minWithdraw) . '.'); }
    $methodKey = akpay_normalize_method($wallet['method_code'] ?? ($wallet['method'] ?? ''));
    if (!in_array($methodKey, array('bkash','nagad','rocket','upi'), true)) { return array('success' => false, 'message' => 'Selected withdrawal method is unavailable.'); }
    $accountNo = trim((string)($wallet['wallet_number'] ?? ''));
    if (in_array($methodKey, array('bkash','nagad','rocket'), true)) {
        $accountNo = preg_replace('/\D+/', '', $accountNo);
        if (!preg_match('/^01\d{9}$/', $accountNo)) { return array('success' => false, 'message' => 'Enter a valid 11 digit wallet number.'); }
    }

    $username = 'User';
    $stmtName = $conn->prepare("SELECT username, phone FROM users WHERE id=? LIMIT 1");
    if ($stmtName) {
        $stmtName->bind_param('i', $uid);
        $stmtName->execute();
        $nameRes = $stmtName->get_result();
        if ($nameRes && $nameRes->num_rows > 0) {
            $u = $nameRes->fetch_assoc();
            $username = trim((string)($u['username'] ?? '')) ?: trim((string)($u['phone'] ?? 'User'));
        }
        $stmtName->close();
    }

    $orderNo = akpay_order_no('AW', $uid);
    $historyMethod = 'AKPay ' . akpay_method_label($methodKey) . ' (' . $accountNo . ')';
    $payload = array(
        'mchId' => $merchant,
        'out_trade_no' => $orderNo,
        'money' => akpay_format_amount($amount),
        'pay_type' => akpay_payout_method($methodKey),
        'currency' => 'BDT',
        'account' => $accountNo,
        'userName' => $username,
        'notify_url' => akpay_url('/api/akpay_payout_callback.php')
    );

    // Generate the MD5 signature according to the supplied documentation.
    // The live payout endpoint additionally requires secret_key as a POST
    // field, so add it only after signing to avoid changing the documented
    // signature source string.
    $payload['sign'] = akpay_generate_sign($payload, $secret);
    $payload['secret_key'] = $secret;

    $logPayload = $payload;
    $logPayload['sign'] = '***hidden***';
    $logPayload['secret_key'] = '***hidden***';
    $rawRequest = akpay_json($logPayload);
    $txId = 0;

    $conn->begin_transaction();
    try {
        $stmtUser = $conn->prepare("SELECT balance, COALESCE(turnover_target,0) AS turnover_target, COALESCE(turnover_completed,0) AS turnover_completed FROM users WHERE id=? LIMIT 1 FOR UPDATE");
        $stmtUser->bind_param('i', $uid);
        $stmtUser->execute();
        $userRes = $stmtUser->get_result();
        if (!$userRes || $userRes->num_rows === 0) { $conn->rollback(); return array('success' => false, 'message' => 'User account was not found.'); }
        $user = $userRes->fetch_assoc();
        $remaining = max(0, (float)$user['turnover_target'] - (float)$user['turnover_completed']);
        if ($remaining > 0.01) { $conn->rollback(); return array('success' => false, 'message' => 'Required turnover is not complete. Remaining Turnover: ৳' . number_format($remaining, 2)); }
        if ((float)$user['balance'] < $amount) { $conn->rollback(); return array('success' => false, 'message' => 'Insufficient withdrawable balance.'); }
        $stmtDeduct = $conn->prepare("UPDATE users SET balance=balance-? WHERE id=? AND balance>=?");
        $stmtDeduct->bind_param('did', $amount, $uid, $amount);
        $stmtDeduct->execute();
        if ($stmtDeduct->affected_rows < 1) { $conn->rollback(); return array('success' => false, 'message' => 'Insufficient withdrawable balance.'); }

        $walletId = intval($wallet['id'] ?? 0);
        $methodCode = $methodKey;
        $note = 'AKPay automatic payout processing.';
        $stmtTx = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, status, wallet_number, withdraw_wallet_id, withdraw_method_code, withdraw_pin_verified, transaction_id, order_sn, agent_id, admin_note, created_at) VALUES (?, 'withdraw', ?, ?, 'processing', ?, ?, ?, 1, ?, ?, 0, ?, NOW())");
        $stmtTx->bind_param('idssissss', $uid, $amount, $historyMethod, $accountNo, $walletId, $methodCode, $orderNo, $orderNo, $note);
        $stmtTx->execute();
        $txId = intval($stmtTx->insert_id);

        $stmtOrder = $conn->prepare("INSERT INTO payment_gateway_orders (transaction_id, order_no, user_id, gateway, type, method, account_number, amount, status, gateway_status, raw_request, created_at) VALUES (?, ?, ?, 'akpay', 'withdraw', ?, ?, ?, 'processing', 'submitting', ?, NOW())");
        $stmtOrder->bind_param('isissds', $txId, $orderNo, $uid, $methodKey, $accountNo, $amount, $rawRequest);
        $stmtOrder->execute();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Unable to start automatic withdrawal.');
    }

    $http = akpay_http_post_form(akpay_api_url($conn, '/v1/payout'), $payload, 35);
    $decoded = json_decode($http['body'], true);
    $rawResponse = $http['body'];
    if (!$http['success'] || !is_array($decoded)) {
        $message = $http['error'] !== '' ? $http['error'] : 'Invalid payment gateway response.';
        akpay_fail_withdraw_order($conn, $orderNo, $rawResponse, $message, true);
        return array('success' => false, 'message' => $message);
    }

    $code = intval($decoded['code'] ?? -1);
    $okMessage = strtolower(trim((string)($decoded['message'] ?? $decoded['msg'] ?? '')));
    $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : array();
    $gatewayTxn = trim((string)($data['transaction_id'] ?? ''));
    $gatewayStatus = strtolower(trim((string)($data['status'] ?? 'processing')));
    if (!in_array($code, array(0, 200), true) || ($okMessage !== '' && $okMessage !== 'success')) {
        $message = trim((string)($decoded['message'] ?? $decoded['msg'] ?? 'Payment gateway rejected the withdrawal.'));
        akpay_fail_withdraw_order($conn, $orderNo, $rawResponse, $message, true);
        return array('success' => false, 'message' => $message, 'gateway_response' => $decoded);
    }

    $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_order_no=?, gateway_status=?, raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='akpay' AND type='withdraw'");
    if ($stmtUp) { $stmtUp->bind_param('ssss', $gatewayTxn, $gatewayStatus, $rawResponse, $orderNo); $stmtUp->execute(); $stmtUp->close(); }
    $note = 'AKPay automatic payout submitted.';
    $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET lg_order_sn=?, admin_note=? WHERE id=? AND status='processing'");
    if ($stmtTxUp) { $stmtTxUp->bind_param('ssi', $gatewayTxn, $note, $txId); $stmtTxUp->execute(); $stmtTxUp->close(); }

    if (function_exists('propay_status_is_success') && propay_status_is_success($gatewayStatus)) {
        akpay_mark_withdraw_success($conn, $orderNo, $gatewayStatus, $rawResponse);
    } elseif (function_exists('propay_status_is_failed') && propay_status_is_failed($gatewayStatus)) {
        $message = trim((string)($decoded['message'] ?? 'Transaction failed.'));
        akpay_fail_withdraw_order($conn, $orderNo, $rawResponse, $message, true);
        return array('success' => false, 'message' => $message, 'gateway_response' => $decoded);
    }

    return array('success' => true, 'message' => 'Withdrawal submitted successfully.', 'order_no' => $orderNo, 'gateway_status' => $gatewayStatus, 'gateway_response' => $decoded);
}

function akpay_fail_withdraw_order($conn, $orderNo, $rawResponse, $reason, $refund) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='akpay' AND type='withdraw' LIMIT 1 FOR UPDATE");
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
            $note = 'AKPay withdrawal failed and balance refunded: ' . $reason;
            $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET status='rejected', admin_note=? WHERE id=? AND status IN ('pending','processing')");
            $stmtTxUp->bind_param('si', $note, $txId);
            $stmtTxUp->execute();
        }
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='failed', gateway_status='failed', raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='akpay'");
        $stmtOrder->bind_param('ss', $rawResponse, $orderNo);
        $stmtOrder->execute();
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function akpay_mark_withdraw_success($conn, $orderNo, $gatewayStatus, $rawResponse) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='akpay' AND type='withdraw' LIMIT 1 FOR UPDATE");
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $conn->rollback(); return false; }
        $order = $res->fetch_assoc();
        if ($order['status'] === 'success') { $conn->commit(); return true; }
        if ($order['status'] === 'failed') { $conn->rollback(); return false; }
        $txId = intval($order['transaction_id']);
        if ($txId > 0) {
            $note = 'AKPay automatic withdrawal completed successfully.';
            $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET status='approved', admin_note=? WHERE id=? AND status IN ('pending','processing')");
            $stmtTxUp->bind_param('si', $note, $txId);
            $stmtTxUp->execute();
        }
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='success', gateway_status=?, raw_response=?, credited_at=NOW(), updated_at=NOW() WHERE order_no=? AND gateway='akpay'");
        $stmtOrder->bind_param('sss', $gatewayStatus, $rawResponse, $orderNo);
        $stmtOrder->execute();
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function akpay_apply_payout_callback($conn, $payload) {
    akpay_ensure_schema($conn);
    $settings = akpay_get_settings($conn);
    if (!akpay_verify_sign($payload, trim((string)$settings['secret_code']))) { return array('success' => false, 'http_code' => 403, 'message' => 'fail'); }
    $orderNo = trim((string)($payload['out_trade_no'] ?? ''));
    if ($orderNo === '') { return array('success' => false, 'http_code' => 400, 'message' => 'fail'); }
    $status = strtolower(trim((string)($payload['status'] ?? '')));
    $payloadJson = akpay_json($payload);
    $gatewayTxn = trim((string)($payload['transaction_id'] ?? ''));
    if ($gatewayTxn !== '') {
        $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_order_no=COALESCE(NULLIF(gateway_order_no, ''), ?), callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='akpay' AND type='withdraw'");
        if ($stmtUp) { $stmtUp->bind_param('sss', $gatewayTxn, $payloadJson, $orderNo); $stmtUp->execute(); $stmtUp->close(); }
    }
    if ($status === 'success') {
        $ok = akpay_mark_withdraw_success($conn, $orderNo, 'success', $payloadJson);
        return array('success' => $ok, 'http_code' => $ok ? 200 : 500, 'message' => $ok ? 'ok' : 'fail');
    }
    if ($status === 'failed') {
        $reason = trim((string)($payload['message'] ?? 'Payout failed'));
        $ok = akpay_fail_withdraw_order($conn, $orderNo, $payloadJson, $reason, true);
        return array('success' => $ok, 'http_code' => $ok ? 200 : 500, 'message' => $ok ? 'ok' : 'fail');
    }
    $stmt = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status=?, callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='akpay' AND type='withdraw'");
    if ($stmt) { $stmt->bind_param('sss', $status, $payloadJson, $orderNo); $stmt->execute(); $stmt->close(); }
    return array('success' => true, 'http_code' => 200, 'message' => 'ok');
}

function akpay_sync_pending_withdrawals($conn, $user_id = 0, $limit = 20) {
    akpay_ensure_schema($conn);
    $settings = akpay_get_settings($conn);
    $merchant = trim((string)($settings['merchant_code'] ?? ''));
    $secret = trim((string)($settings['secret_code'] ?? ''));
    if ($merchant === '' || $secret === '' || trim((string)($settings['api_base_url'] ?? '')) === '') {
        return array('checked' => 0, 'updated' => 0);
    }
    $limit = max(1, min(50, intval($limit)));
    $checked = 0;
    $updated = 0;

    if (intval($user_id) > 0) {
        $stmt = $conn->prepare("SELECT order_no FROM payment_gateway_orders WHERE gateway='akpay' AND type='withdraw' AND status IN ('pending','processing') AND user_id=? ORDER BY id ASC LIMIT ?");
        if (!$stmt) { return array('checked' => 0, 'updated' => 0); }
        $uid = intval($user_id);
        $stmt->bind_param('ii', $uid, $limit);
    } else {
        $stmt = $conn->prepare("SELECT order_no FROM payment_gateway_orders WHERE gateway='akpay' AND type='withdraw' AND status IN ('pending','processing') ORDER BY id ASC LIMIT ?");
        if (!$stmt) { return array('checked' => 0, 'updated' => 0); }
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $orderNo = trim((string)($row['order_no'] ?? ''));
        if ($orderNo === '') { continue; }
        $checked++;
        $payload = array('mchId' => $merchant, 'out_trade_no' => $orderNo);
        $payload['sign'] = akpay_generate_sign($payload, $secret);
        $http = akpay_http_post_form(akpay_api_url($conn, '/v1/order'), $payload, 20);
        $decoded = json_decode($http['body'], true);
        if (!$http['success'] || !is_array($decoded)) { continue; }
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
        $status = strtolower(trim((string)($data['status'] ?? $decoded['status'] ?? '')));
        $raw = akpay_json($decoded);
        if (in_array($status, array('1','success','paid','completed','complete','approved'), true)) {
            if (akpay_mark_withdraw_success($conn, $orderNo, $status, $raw)) { $updated++; }
        } elseif (in_array($status, array('0','failed','fail','rejected','cancelled','canceled'), true)) {
            $reason = trim((string)($decoded['message'] ?? $decoded['msg'] ?? 'Payout failed'));
            if (akpay_fail_withdraw_order($conn, $orderNo, $raw, $reason, true)) { $updated++; }
        } elseif ($status !== '') {
            $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status=?, raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='akpay' AND type='withdraw'");
            if ($stmtUp) { $stmtUp->bind_param('sss', $status, $raw, $orderNo); $stmtUp->execute(); $stmtUp->close(); }
        }
    }
    $stmt->close();
    return array('checked' => $checked, 'updated' => $updated);
}

function wcb_sync_pending_withdrawals($conn, $user_id = 0, $limit = 20) {
    $result = array('checked' => 0, 'updated' => 0);
    if (function_exists('lgpay_sync_pending_withdrawals')) {
        $l = lgpay_sync_pending_withdrawals($conn, $user_id, $limit);
        $result['checked'] += intval($l['checked'] ?? 0);
        $result['updated'] += intval($l['updated'] ?? 0);
    }
    if (function_exists('propay_sync_pending_withdrawals')) {
        $a = propay_sync_pending_withdrawals($conn, $user_id, $limit);
        $result['checked'] += intval($a['checked'] ?? 0);
        $result['updated'] += intval($a['updated'] ?? 0);
    }
    $b = akpay_sync_pending_withdrawals($conn, $user_id, $limit);
    $result['checked'] += intval($b['checked'] ?? 0);
    $result['updated'] += intval($b['updated'] ?? 0);
    return $result;
}
?>
