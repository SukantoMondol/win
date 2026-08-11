<?php
// includes/nekpay_gateway_helper.php
// NEKpay Payment Gateway Integration Helper for Deposit & Payout

if (!defined('NEKPAY_HELPER_LOADED')) {
    define('NEKPAY_HELPER_LOADED', true);
}

function nekpay_default_api_base_url() {
    return 'https://api.nekpayment.com';
}

function nekpay_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9\-\.:]/', '', $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    return $scheme . '://' . $host;
}

function nekpay_url($path) {
    return rtrim(nekpay_base_url(), '/') . '/' . ltrim((string)$path, '/');
}

function nekpay_ensure_schema($conn) {
    if (!$conn || !empty($conn->connect_error)) { return false; }

    @$conn->query("CREATE TABLE IF NOT EXISTS `payment_gateway_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `provider` VARCHAR(40) NOT NULL DEFAULT 'nekpay',
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

    $defaultBase = $conn->real_escape_string(nekpay_default_api_base_url());
    $defaultKey = '7c1adc2ec9f04bc0a00a1c0fd88eee00';
    @$conn->query("INSERT IGNORE INTO `payment_gateway_settings` (`provider`, `merchant_code`, `secret_code`, `api_base_url`, `is_enabled`, `failover_priority`) VALUES ('nekpay', '', '$defaultKey', '$defaultBase', 1, 1)");

    return true;
}

function nekpay_get_settings($conn) {
    nekpay_ensure_schema($conn);
    $settings = array(
        'merchant_code' => '',
        'secret_code' => '7c1adc2ec9f04bc0a00a1c0fd88eee00',
        'api_base_url' => nekpay_default_api_base_url(),
        'is_enabled' => 1,
        'last_health_status' => '',
        'last_health_checked_at' => '',
        'last_error' => ''
    );
    $res = @$conn->query("SELECT merchant_code, secret_code, api_base_url, is_enabled, last_health_status, last_health_checked_at, last_error FROM payment_gateway_settings WHERE provider='nekpay' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $settings['merchant_code'] = trim((string)($row['merchant_code'] ?? ''));
        $settings['secret_code'] = trim((string)($row['secret_code'] ?? '')) ?: '7c1adc2ec9f04bc0a00a1c0fd88eee00';
        $settings['api_base_url'] = trim((string)($row['api_base_url'] ?? '')) ?: nekpay_default_api_base_url();
        $settings['is_enabled'] = intval($row['is_enabled'] ?? 1);
        $settings['last_health_status'] = trim((string)($row['last_health_status'] ?? ''));
        $settings['last_health_checked_at'] = trim((string)($row['last_health_checked_at'] ?? ''));
        $settings['last_error'] = trim((string)($row['last_error'] ?? ''));
    }
    return $settings;
}

function nekpay_save_settings($conn, $merchant_code, $secret_code, $api_base_url, $is_enabled) {
    nekpay_ensure_schema($conn);
    $provider = 'nekpay';
    $merchant_code = trim((string)$merchant_code);
    $secret_code = trim((string)$secret_code);
    $api_base_url = trim((string)$api_base_url) ?: nekpay_default_api_base_url();
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

/**
 * Generate MD5 Signature for NEKpay Deposit
 * Sort all parameters alphabetically by key (excluding sign & sign_type and empty values)
 * Build unencoded string: k1=v1&k2=v2...&key=SECRET
 */
function nekpay_generate_deposit_sign($params, $key) {
    if (isset($params['sign'])) { unset($params['sign']); }
    if (isset($params['sign_type'])) { unset($params['sign_type']); }

    $filtered = array();
    foreach ($params as $k => $v) {
        if ($v !== null && $v !== '') {
            $filtered[$k] = (string)$v;
        }
    }
    ksort($filtered);

    $unencodedStr = '';
    foreach ($filtered as $k => $v) {
        $unencodedStr .= $k . '=' . $v . '&';
    }
    $unencodedStr .= 'key=' . $key;

    return md5($unencodedStr);
}

/**
 * Generate MD5 Signature for NEKpay IPN Callback Verification
 */
function nekpay_generate_ipn_sign($params, $key) {
    if (isset($params['sign'])) { unset($params['sign']); }
    if (isset($params['signType'])) { unset($params['signType']); }
    if (isset($params['sign_type'])) { unset($params['sign_type']); }

    $filtered = array();
    foreach ($params as $k => $v) {
        if ($v !== null && $v !== '') {
            $filtered[$k] = (string)$v;
        }
    }
    ksort($filtered);

    $signStr = '';
    foreach ($filtered as $k => $v) {
        $signStr .= $k . '=' . $v . '&';
    }
    $signStr .= 'key=' . $key;

    return md5($signStr);
}

/**
 * Generate MD5 Signature for NEKpay Payout / Transfer
 */
function nekpay_generate_payout_sign($params, $key) {
    if (isset($params['sign'])) { unset($params['sign']); }
    if (isset($params['sign_type'])) { unset($params['sign_type']); }

    $filtered = array();
    foreach ($params as $k => $v) {
        if ($v !== null && $v !== '') {
            $filtered[$k] = (string)$v;
        }
    }
    ksort($filtered);

    $signStr = '';
    foreach ($filtered as $k => $v) {
        $signStr .= $k . '=' . $v . '&';
    }
    $signStr .= 'key=' . $key;

    return md5($signStr);
}

/**
 * Initiate NEKpay Deposit Order
 */
function nekpay_create_deposit_order($conn, $userId, $amount, $methodName = 'bKash', $promoId = 0, $payType = '2222') {
    $config = nekpay_get_settings($conn);
    if (empty($config['is_enabled'])) {
        return array('success' => false, 'message' => 'NEKpay gateway is currently disabled.');
    }

    $merchantId = $config['merchant_code'];
    $paymentKey = $config['secret_code'];
    $apiBase = rtrim($config['api_base_url'], '/');

    if (empty($merchantId) || empty($paymentKey)) {
        return array('success' => false, 'message' => 'NEKpay Merchant ID or Payment Key is not configured in Admin Panel.');
    }

    // Determine pay_type based on method if not explicitly passed
    $methodLower = strtolower($methodName);
    if (strpos($methodLower, 'nagad') !== false) {
        $payType = '2221';
    } elseif (strpos($methodLower, 'bkash') !== false) {
        $payType = '2222';
    } elseif (strpos($methodLower, 'bank') !== false) {
        $payType = '2220';
    }

    $mchOrderNo = 'NEK' . date('YmdHis') . rand(1000, 9999);
    $notifyUrl = nekpay_url('/api/nekpay_callback.php');
    $pageUrl = nekpay_url('/player/dashboard.php');

    // Get username
    $username = 'Customer User';
    $uRes = $conn->query("SELECT username FROM users WHERE id=" . intval($userId) . " LIMIT 1");
    if ($uRes && $uRes->num_rows > 0) {
        $row = $uRes->fetch_assoc();
        $payerName = preg_replace('/[^a-zA-Z ]/', '', $row['username'] ?? '');
        if (strlen(trim($payerName)) >= 3) {
            $username = trim($payerName);
        }
    }

    $tradeAmount = number_format((float)$amount, 2, '.', '');

    $params = array(
        'version'        => '1.0',
        'mch_id'         => $merchantId,
        'notify_url'     => $notifyUrl,
        'page_url'       => $pageUrl,
        'mch_order_no'   => $mchOrderNo,
        'pay_type'       => (string)$payType,
        'trade_amount'   => $tradeAmount,
        'order_date'     => date('Y-m-d H:i:s'),
        'goods_name'     => 'Deposit',
        'mch_return_msg' => 'Deposit',
        'payer_name'     => $username,
        'sign_type'      => 'MD5',
    );

    $params['sign'] = nekpay_generate_deposit_sign($params, $paymentKey);

    // Endpoint URL
    $endpoint = $apiBase . '/pay/web';

    // Execute Curl POST form-urlencoded
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("NEKpay Deposit Curl Error: $curlErr");
        return array('success' => false, 'message' => 'Connection Error: ' . $curlErr);
    }

    $json = json_decode($response, true);
    if (!$json) {
        error_log("NEKpay Deposit Invalid Response: $response");
        return array('success' => false, 'message' => 'Invalid response from gateway: ' . substr($response, 0, 100));
    }

    $respCode = $json['respCode'] ?? null;
    $payInfo  = $json['payInfo'] ?? null;

    if ($respCode !== 'SUCCESS' && (strpos(($json['tradeMsg'] ?? ''), 'MERCHANT_HAS_NO_GATEWAY') !== false || strpos(($json['errorMsg'] ?? ''), 'MERCHANT_HAS_NO_GATEWAY') !== false) && $params['pay_type'] !== '2220') {
        // Retry with 2220 (BANK2 default channel bound in NEKpay merchant backstage)
        $params['pay_type'] = '2220';
        $params['sign'] = nekpay_generate_deposit_sign($params, $paymentKey);

        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $endpoint);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        $response2 = curl_exec($ch2);
        curl_close($ch2);

        $json2 = json_decode($response2, true);
        if ($json2 && ($json2['respCode'] ?? '') === 'SUCCESS' && !empty($json2['payInfo'])) {
            $json = $json2;
            $respCode = $json2['respCode'];
            $payInfo = $json2['payInfo'];
        }
    }

    if ($respCode === 'SUCCESS' && !empty($payInfo)) {
        // Record pending deposit order in DB
        $stmt = $conn->prepare("INSERT INTO transactions_fake (user_id, amount, status, type, wallet_number, promo_id) VALUES (?, ?, 'pending', 'deposit', ?, ?)");
        if ($stmt) {
            $stmt->bind_param("idsi", $userId, $amount, $mchOrderNo, $promoId);
            $stmt->execute();
            $stmt->close();
        }

        return array(
            'success' => true,
            'pay_url' => $payInfo,
            'order_id' => $mchOrderNo
        );
    } else {
        $errorMsg = $json['tradeMsg'] ?? $json['errorMsg'] ?? json_encode($json);
        error_log("NEKpay Deposit Failed: $errorMsg");
        return array('success' => false, 'message' => 'NEKpay Error: ' . $errorMsg);
    }
}

/**
 * Handle NEKpay Deposit Asynchronous Callback (IPN)
 */
function nekpay_apply_deposit_success($conn, $data, $source = 'ipn') {
    $tradeResult  = $data['tradeResult'] ?? $data['trade_result'] ?? null;
    $mchOrderNo   = $data['mchOrderNo'] ?? $data['mch_order_no'] ?? $data['orderNo'] ?? null;
    $receivedSign = $data['sign'] ?? null;

    if (!$mchOrderNo || !$receivedSign) {
        error_log('NEKpay IPN Missing parameters: ' . json_encode($data));
        return false;
    }

    $config = nekpay_get_settings($conn);
    $paymentKey = $config['secret_code'];

    // Verify signature
    $expectedSign = nekpay_generate_ipn_sign($data, $paymentKey);
    if (strtolower($receivedSign) !== strtolower($expectedSign)) {
        error_log("NEKpay IPN Signature Mismatch! Received: $receivedSign, Expected: $expectedSign");
        return false;
    }

    // Check if payment was successful (tradeResult == "1" or 1)
    if ($tradeResult === '1' || $tradeResult === 1) {
        // Find pending transaction
        $stmt = $conn->prepare("SELECT id, user_id, amount, status, promo_id FROM transactions_fake WHERE wallet_number=? AND status='pending' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $mchOrderNo);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $trx = $res->fetch_assoc();
                $trxId = $trx['id'];
                $userId = $trx['user_id'];
                $amount = (float)$trx['amount'];

                // Update transaction status to approved
                $conn->query("UPDATE transactions_fake SET status='approved' WHERE id=$trxId");

                // Credit user balance
                $conn->query("UPDATE users SET balance = balance + $amount WHERE id=$userId");

                error_log("NEKpay IPN: Deposit $mchOrderNo (Amount: $amount BDT) approved & credited to User ID $userId successfully!");
                return true;
            }
            $stmt->close();
        }
    }

    return true;
}

/**
 * Execute NEKpay Payout / Transfer Request
 */
function nekpay_create_payout($conn, $merchantTransferId, $bankCode, $accountNumber, $receiverName, $amount) {
    $config = nekpay_get_settings($conn);
    if (empty($config['is_enabled'])) {
        return array('success' => false, 'message' => 'NEKpay gateway is currently disabled.');
    }

    $merchantId = $config['merchant_code'];
    $payoutKey  = $config['secret_code'];
    $apiBase    = rtrim($config['api_base_url'], '/');

    $holderName = preg_replace('/[^a-zA-Z]/', '', $receiverName ?? '');
    if (strlen($holderName) < 5) {
        $holderName = 'CustomerAccount';
    }

    $backUrl = nekpay_url('/api/nekpay_payout_callback.php');

    $params = array(
        'apply_date'      => date('Y-m-d H:i:s'),
        'back_url'        => $backUrl,
        'bank_code'       => (string)$bankCode, // 'baksh' for bKash, 'ngand' for Nagad
        'mch_id'          => $merchantId,
        'mch_transferId'  => (string)$merchantTransferId,
        'receive_account' => (string)$accountNumber,
        'receive_name'    => $holderName,
        'transfer_amount' => (string)(int)$amount,
        'sign_type'       => 'MD5',
    );

    $params['sign'] = nekpay_generate_payout_sign($params, $payoutKey);

    $endpoint = $apiBase . '/pay/transfer';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return array('success' => false, 'message' => 'Connection Error: ' . $curlErr);
    }

    $json = json_decode($response, true);
    if (!$json) {
        return array('success' => false, 'message' => 'Invalid response from NEKpay payout gateway');
    }

    $respCode    = $json['respCode'] ?? null;
    $tradeResult = $json['tradeResult'] ?? null;

    if ($respCode === 'SUCCESS' && ($tradeResult === '0' || $tradeResult === '1' || $tradeResult === 0 || $tradeResult === 1)) {
        return array(
            'success' => true,
            'trx_id'  => $json['tradeNo'] ?? $merchantTransferId,
            'message' => 'Payout initiated successfully via NEKpay'
        );
    } else {
        $errorMsg = $json['errorMsg'] ?? json_encode($json);
        return array('success' => false, 'message' => 'NEKpay Payout Error: ' . $errorMsg);
    }
}
?>
