<?php
// includes/propay_gateway_helper.php
// ProPay Payment Gateway helper. Keeps credentials dynamic from DB and makes the patch work without manual SQL import.

if (!defined('PROPAY_HELPER_LOADED')) {
    define('PROPAY_HELPER_LOADED', true);
}
if (file_exists(__DIR__ . '/referral_system_helper.php')) { require_once __DIR__ . '/referral_system_helper.php'; }
if (file_exists(__DIR__ . '/withdrawal_system_helper.php')) { require_once __DIR__ . '/withdrawal_system_helper.php'; }

function propay_table_exists($conn, $table) {
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $res = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safe) . "'");
    return $res && $res->num_rows > 0;
}

function propay_column_exists($conn, $table, $column) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $res = @$conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '" . $conn->real_escape_string($safeColumn) . "'");
    return $res && $res->num_rows > 0;
}

function propay_add_column_if_missing($conn, $table, $column, $definition) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if (!propay_column_exists($conn, $safeTable, $safeColumn)) {
        @$conn->query("ALTER TABLE `$safeTable` ADD COLUMN `$safeColumn` $definition");
    }
}

function propay_ensure_schema($conn) {
    // ProPay is no longer an active gateway; schema helpers remain for compatibility.

    if (!$conn || $conn->connect_error) { return false; }

    @$conn->query("CREATE TABLE IF NOT EXISTS `payment_gateway_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `provider` VARCHAR(40) NOT NULL DEFAULT 'propay',
        `merchant_code` VARCHAR(120) DEFAULT NULL,
        `secret_code` VARCHAR(255) DEFAULT NULL,
        `api_base_url` VARCHAR(255) DEFAULT NULL,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_provider` (`provider`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    @$conn->query("INSERT IGNORE INTO `payment_gateway_settings` (`provider`, `merchant_code`, `secret_code`, `is_enabled`) VALUES ('propay', '', '', 0)");

    @$conn->query("CREATE TABLE IF NOT EXISTS `payment_gateway_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `transaction_id` INT(11) DEFAULT NULL,
        `order_no` VARCHAR(120) NOT NULL,
        `gateway_order_no` VARCHAR(120) DEFAULT NULL,
        `user_id` INT(11) NOT NULL,
        `gateway` VARCHAR(40) NOT NULL DEFAULT 'propay',
        `type` ENUM('deposit','withdraw') NOT NULL,
        `method` VARCHAR(40) NOT NULL,
        `account_number` VARCHAR(30) DEFAULT NULL,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `status` ENUM('pending','processing','success','failed') NOT NULL DEFAULT 'pending',
        `gateway_status` VARCHAR(60) DEFAULT NULL,
        `raw_request` MEDIUMTEXT DEFAULT NULL,
        `raw_response` MEDIUMTEXT DEFAULT NULL,
        `callback_payload` MEDIUMTEXT DEFAULT NULL,
        `credited_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_order_no` (`order_no`),
        KEY `idx_gateway_order_no` (`gateway_order_no`),
        KEY `idx_gateway` (`gateway`),
        KEY `idx_user_type_status` (`user_id`, `type`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (propay_table_exists($conn, 'payment_gateway_settings')) {
        $hadFailoverPriority = propay_column_exists($conn, 'payment_gateway_settings', 'failover_priority');
        propay_add_column_if_missing($conn, 'payment_gateway_settings', 'api_base_url', "VARCHAR(255) DEFAULT NULL AFTER `secret_code`");
        propay_add_column_if_missing($conn, 'payment_gateway_settings', 'failover_priority', "TINYINT(3) NOT NULL DEFAULT 1 AFTER `is_enabled`");
        propay_add_column_if_missing($conn, 'payment_gateway_settings', 'last_health_status', "VARCHAR(20) DEFAULT NULL AFTER `failover_priority`");
        propay_add_column_if_missing($conn, 'payment_gateway_settings', 'last_health_checked_at', "DATETIME DEFAULT NULL AFTER `last_health_status`");
        propay_add_column_if_missing($conn, 'payment_gateway_settings', 'last_error', "TEXT DEFAULT NULL AFTER `last_health_checked_at`");
        @$conn->query("INSERT IGNORE INTO `payment_gateway_settings` (`provider`, `merchant_code`, `secret_code`, `api_base_url`, `is_enabled`, `failover_priority`) VALUES ('akpay', '', '', '', 0, 99)");
        if (!$hadFailoverPriority) {
            @$conn->query("UPDATE payment_gateway_settings SET failover_priority=CASE WHEN provider='lgpay' THEN 1 WHEN provider IN ('propay','akpay','cowpay') THEN 99 ELSE failover_priority END");
        }
    }

    if (propay_table_exists($conn, 'payment_gateway_orders')) {
        propay_add_column_if_missing($conn, 'payment_gateway_orders', 'gateway', "VARCHAR(40) NOT NULL DEFAULT 'propay' AFTER `user_id`");
    }

    // Dynamic admin controlled deposit bonus, channel and transaction limit schema.
    if (propay_table_exists($conn, 'settings')) {
        $hadBkashBonus = propay_column_exists($conn, 'settings', 'deposit_bonus_bkash');
        $hadNagadBonus = propay_column_exists($conn, 'settings', 'deposit_bonus_nagad');
        propay_add_column_if_missing($conn, 'settings', 'deposit_bonus_bkash', "DECIMAL(8,2) NOT NULL DEFAULT 0.00");
        propay_add_column_if_missing($conn, 'settings', 'deposit_bonus_nagad', "DECIMAL(8,2) NOT NULL DEFAULT 0.00");
        propay_add_column_if_missing($conn, 'settings', 'min_deposit_amount', "DECIMAL(15,2) NOT NULL DEFAULT 100.00");
        propay_add_column_if_missing($conn, 'settings', 'min_withdraw_amount', "DECIMAL(15,2) NOT NULL DEFAULT 100.00");
        propay_add_column_if_missing($conn, 'settings', 'deposit_notice', "TEXT DEFAULT NULL");
        if (!$hadBkashBonus || !$hadNagadBonus) {
            @$conn->query("UPDATE settings SET deposit_bonus_bkash = COALESCE(NULLIF(deposit_bonus_bkash, 0), deposit_bonus_percent, 0), deposit_bonus_nagad = COALESCE(NULLIF(deposit_bonus_nagad, 0), deposit_bonus_percent, 0) WHERE id=1");
        }
    }

    if (propay_table_exists($conn, 'payment_gateway_orders')) {
        propay_add_column_if_missing($conn, 'payment_gateway_orders', 'channel', "VARCHAR(60) DEFAULT NULL AFTER `method`");
    }

    if (propay_table_exists($conn, 'users')) {
        $hadTurnoverCompleted = propay_column_exists($conn, 'users', 'turnover_completed');
        propay_add_column_if_missing($conn, 'users', 'turnover_completed', "DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `turnover_target`");
        if (!$hadTurnoverCompleted && propay_table_exists($conn, 'game_bet_history')) {
            if (propay_column_exists($conn, 'game_bet_history', 'amount') && propay_column_exists($conn, 'game_bet_history', 'type')) {
                @$conn->query("UPDATE users u LEFT JOIN (SELECT user_id, SUM(amount) AS total_bet FROM game_bet_history WHERE type='bet' GROUP BY user_id) b ON b.user_id=u.id SET u.turnover_completed = COALESCE(b.total_bet, 0)");
            } elseif (propay_column_exists($conn, 'game_bet_history', 'bet_amount')) {
                @$conn->query("UPDATE users u LEFT JOIN (SELECT user_id, SUM(bet_amount) AS total_bet FROM game_bet_history GROUP BY user_id) b ON b.user_id=u.id SET u.turnover_completed = COALESCE(b.total_bet, 0)");
            }
        }
    }

    // Keep compatibility with older live databases.
    if (propay_table_exists($conn, 'transactions_fake')) {
        propay_add_column_if_missing($conn, 'transactions_fake', 'promo_id', "INT(11) DEFAULT 0");
        propay_add_column_if_missing($conn, 'transactions_fake', 'wallet_number', "VARCHAR(20) DEFAULT NULL COMMENT 'User Phone/Wallet'");
        propay_add_column_if_missing($conn, 'transactions_fake', 'transaction_id', "VARCHAR(100) DEFAULT NULL COMMENT 'Gateway/Trx ID'");
        propay_add_column_if_missing($conn, 'transactions_fake', 'order_sn', "VARCHAR(120) DEFAULT NULL");
        propay_add_column_if_missing($conn, 'transactions_fake', 'lg_order_sn', "VARCHAR(120) DEFAULT NULL");
        propay_add_column_if_missing($conn, 'transactions_fake', 'admin_note', "TEXT DEFAULT NULL");
        propay_add_column_if_missing($conn, 'transactions_fake', 'is_notified', "TINYINT(1) NOT NULL DEFAULT 0");
        $statusColumn = @$conn->query("SHOW COLUMNS FROM transactions_fake LIKE 'status'");
        if ($statusColumn && ($statusRow = $statusColumn->fetch_assoc()) && strpos((string)$statusRow['Type'], "'processing'") === false) {
            @$conn->query("ALTER TABLE transactions_fake MODIFY status ENUM('pending','processing','approved','rejected') DEFAULT 'approved'");
        }
    }

    if (propay_table_exists($conn, 'payment_gateway_settings')) {
        @$conn->query("UPDATE payment_gateway_settings SET is_enabled=0, failover_priority=99, last_health_status='disabled', last_error='Disabled because LG Pay is the only active/default gateway.' WHERE provider IN ('propay','akpay','cowpay')");
        @$conn->query("INSERT INTO payment_gateway_settings (provider, merchant_code, secret_code, api_base_url, is_enabled, failover_priority) VALUES ('lgpay', '', '', 'https://www.lg-pay.com/api', 1, 1) ON DUPLICATE KEY UPDATE is_enabled=1, failover_priority=1, api_base_url=COALESCE(NULLIF(api_base_url, ''), VALUES(api_base_url)), updated_at=NOW()");
    }
    return true;
}

function propay_get_settings($conn) {
    propay_ensure_schema($conn);
    $settings = array('merchant_code' => '', 'secret_code' => '', 'api_base_url' => '', 'is_enabled' => 0, 'failover_priority' => 99, 'last_health_status' => '', 'last_health_checked_at' => '', 'last_error' => '');
    $res = @$conn->query("SELECT merchant_code, secret_code, api_base_url, is_enabled, failover_priority, last_health_status, last_health_checked_at, last_error FROM payment_gateway_settings WHERE provider='propay' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $settings['merchant_code'] = trim($row['merchant_code'] ?? '');
        $settings['secret_code'] = trim($row['secret_code'] ?? '');
        $settings['api_base_url'] = trim($row['api_base_url'] ?? '');
        $settings['is_enabled'] = intval($row['is_enabled'] ?? 1);
        $settings['failover_priority'] = max(1, intval($row['failover_priority'] ?? 1));
        $settings['last_health_status'] = trim((string)($row['last_health_status'] ?? ''));
        $settings['last_health_checked_at'] = trim((string)($row['last_health_checked_at'] ?? ''));
        $settings['last_error'] = trim((string)($row['last_error'] ?? ''));
    }
    return $settings;
}

function propay_save_settings($conn, $merchant_code, $secret_code, $is_enabled, $api_base_url = '') {
    $is_enabled = 0; // ProPay is permanently disabled; LG Pay is the only active gateway.

    propay_ensure_schema($conn);
    $provider = 'propay';
    $merchant_code = trim((string)$merchant_code);
    $secret_code = trim((string)$secret_code);
    $api_base_url = trim((string)$api_base_url);
    $is_enabled = $is_enabled ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO payment_gateway_settings (provider, merchant_code, secret_code, api_base_url, is_enabled)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE merchant_code=VALUES(merchant_code), secret_code=VALUES(secret_code), api_base_url=VALUES(api_base_url), is_enabled=VALUES(is_enabled), updated_at=NOW()");
    if (!$stmt) { return false; }
    $stmt->bind_param('ssssi', $provider, $merchant_code, $secret_code, $api_base_url, $is_enabled);
    return $stmt->execute();
}

function propay_base_url() {
    $https = false;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') { $https = true; }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') { $https = true; }
    if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false) { $https = true; }
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = preg_replace('/[^a-zA-Z0-9\-\.:]/', '', $host);
    return $scheme . '://' . $host;
}

function propay_url($path) {
    $path = '/' . ltrim($path, '/');
    return propay_base_url() . $path;
}

function propay_normalize_method($method) {
    $m = strtolower(trim((string)$method));
    if ($m === 'bkash' || $m === 'b-kash' || $m === 'bKash') { return 'bkash'; }
    if ($m === 'nagad') { return 'nagad'; }
    return '';
}

function propay_bank_code($method) {
    $m = propay_normalize_method($method);
    return $m === 'nagad' ? 'Nagad' : 'Bkash';
}

function propay_method_label($method) {
    $m = propay_normalize_method($method);
    return $m === 'nagad' ? 'Nagad' : 'bKash';
}

function propay_deposit_endpoint($method) {
    $m = propay_normalize_method($method);
    if ($m === 'bkash') { return 'https://checkout.propay.cyou/pay/Bkash.php'; }
    if ($m === 'nagad') { return 'https://checkout.propay.cyou/pay/Nagad.php'; }
    return '';
}

function propay_format_amount($amount) {
    return number_format((float)$amount, 2, '.', '');
}

function propay_make_order_no($prefix, $uid) {
    try { $rand = strtoupper(bin2hex(random_bytes(3))); }
    catch (Exception $e) { $rand = mt_rand(100000, 999999); }
    return $prefix . date('ymdHis') . intval($uid) . $rand;
}

function propay_json($data) {
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function propay_has_column_cached($conn, $table, $column) {
    static $cache = array();
    $key = $table . '.' . $column;
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = propay_column_exists($conn, $table, $column);
    }
    return $cache[$key];
}

function propay_get_site_transaction_settings($conn) {
    propay_ensure_schema($conn);
    $defaults = array(
        'deposit_bonus_bkash' => 0.00,
        'deposit_bonus_nagad' => 0.00,
        'min_deposit_amount' => 100.00,
        'min_withdraw_amount' => 100.00,
        'normal_wager_ratio' => 1.00
    );
    $res = @$conn->query("SELECT deposit_bonus_bkash, deposit_bonus_nagad, min_deposit_amount, min_withdraw_amount, normal_wager_ratio, deposit_bonus_percent FROM settings WHERE id=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $defaults['deposit_bonus_bkash'] = isset($row['deposit_bonus_bkash']) ? (float)$row['deposit_bonus_bkash'] : (float)($row['deposit_bonus_percent'] ?? 0);
        $defaults['deposit_bonus_nagad'] = isset($row['deposit_bonus_nagad']) ? (float)$row['deposit_bonus_nagad'] : (float)($row['deposit_bonus_percent'] ?? 0);
        $defaults['min_deposit_amount'] = max(1, (float)($row['min_deposit_amount'] ?? 100));
        $defaults['min_withdraw_amount'] = max(1, (float)($row['min_withdraw_amount'] ?? 100));
        $defaults['normal_wager_ratio'] = max(0, (float)($row['normal_wager_ratio'] ?? 1));
    }
    return $defaults;
}

function propay_save_deposit_bonus_settings($conn, $bkashBonus, $nagadBonus) {
    propay_ensure_schema($conn);
    $bkashBonus = max(0, min(1000, round((float)$bkashBonus, 2)));
    $nagadBonus = max(0, min(1000, round((float)$nagadBonus, 2)));
    $stmt = $conn->prepare("UPDATE settings SET deposit_bonus_bkash=?, deposit_bonus_nagad=? WHERE id=1");
    if (!$stmt) { return false; }
    $stmt->bind_param('dd', $bkashBonus, $nagadBonus);
    return $stmt->execute();
}

function propay_save_transaction_limits($conn, $minDeposit, $minWithdraw) {
    propay_ensure_schema($conn);
    $minDeposit = max(1, round((float)$minDeposit, 2));
    $minWithdraw = max(1, round((float)$minWithdraw, 2));
    $stmt = $conn->prepare("UPDATE settings SET min_deposit_amount=?, min_withdraw_amount=? WHERE id=1");
    if (!$stmt) { return false; }
    $stmt->bind_param('dd', $minDeposit, $minWithdraw);
    return $stmt->execute();
}

function propay_method_bonus_percent($conn, $method) {
    $settings = propay_get_site_transaction_settings($conn);
    $m = propay_normalize_method($method);
    if ($m === 'nagad') { return (float)$settings['deposit_bonus_nagad']; }
    return (float)$settings['deposit_bonus_bkash'];
}

function propay_allowed_channels() {
    return array('gopay' => 'GoPay', 'lgpay' => 'LGPay', 'cowpay' => 'CowPay', 'shurjopay' => 'ShurjoPay');
}

function propay_normalize_channel($channel) {
    $raw = trim((string)$channel);
    if ($raw === '') { return ''; }
    $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', $raw));
    $allowed = propay_allowed_channels();
    if (isset($allowed[$key])) { return $allowed[$key]; }
    foreach ($allowed as $label) {
        if (strtolower($label) === strtolower($raw)) { return $label; }
    }
    return '';
}

function propay_user_turnover_summary($conn, $uid) {
    propay_ensure_schema($conn);
    $summary = array('target' => 0.00, 'completed' => 0.00, 'remaining' => 0.00);
    $uid = intval($uid);
    if ($uid <= 0) { return $summary; }
    $stmt = $conn->prepare("SELECT COALESCE(turnover_target,0) AS turnover_target, COALESCE(turnover_completed,0) AS turnover_completed FROM users WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $summary['target'] = round((float)$row['turnover_target'], 2);
            $summary['completed'] = round((float)$row['turnover_completed'], 2);
            $summary['remaining'] = round(max(0, $summary['target'] - $summary['completed']), 2);
        }
    }
    return $summary;
}

function propay_calculate_deposit_credit($conn, $user_id, $amount, $promo_id, $method = '') {
    $amount = (float)$amount;
    $bonus_amount = 0.00;
    $wager_ratio = 1.00;

    $custom_wager_ratio = 0.00;
    $stmtUser = $conn->prepare("SELECT custom_wager_ratio FROM users WHERE id=? LIMIT 1");
    if ($stmtUser) {
        $stmtUser->bind_param('i', $user_id);
        $stmtUser->execute();
        $userRes = $stmtUser->get_result();
        if ($userRes && $userRes->num_rows > 0) {
            $u = $userRes->fetch_assoc();
            $custom_wager_ratio = (float)($u['custom_wager_ratio'] ?? 0);
        }
    }

    $settings = propay_get_site_transaction_settings($conn);
    if ($custom_wager_ratio > 0) { $wager_ratio = $custom_wager_ratio; }
    else { $wager_ratio = (float)($settings['normal_wager_ratio'] ?? 1); }

    $promo_id = intval($promo_id);
    if ($promo_id > 0) {
        $stmtPromo = $conn->prepare("SELECT bonus_percent, wager_multiplier, bonus_amount FROM promotions WHERE id=? LIMIT 1");
        if ($stmtPromo) {
            $stmtPromo->bind_param('i', $promo_id);
            $stmtPromo->execute();
            $promoRes = $stmtPromo->get_result();
            if ($promoRes && $promoRes->num_rows > 0) {
                $promo = $promoRes->fetch_assoc();
                $percent = 0.00;
                if (isset($promo['bonus_percent']) && (float)$promo['bonus_percent'] > 0) {
                    $percent = (float)$promo['bonus_percent'];
                } elseif (!empty($promo['bonus_amount'])) {
                    $percent = (float)preg_replace('/[^0-9.]/', '', $promo['bonus_amount']);
                }
                if ($percent > 0) { $bonus_amount = ($amount * $percent) / 100; }
                if (!empty($promo['wager_multiplier'])) { $wager_ratio = (float)$promo['wager_multiplier']; }
            }
        }
    } else {
        $methodBonus = propay_method_bonus_percent($conn, $method);
        if ($methodBonus > 0) { $bonus_amount = ($amount * $methodBonus) / 100; }
    }

    if ($wager_ratio < 0) { $wager_ratio = 0; }
    $total_money = $amount + $bonus_amount;
    // User requirement: deposit 100 should add 100 turnover requirement. Bonus is credited to wallet but does not increase the base turnover unless admin changes wager ratio.
    $target_add = $amount * $wager_ratio;

    return array(
        'deposit_amount' => round($amount, 2),
        'bonus_amount' => round($bonus_amount, 2),
        'total_money' => round($total_money, 2),
        'wager_ratio' => round($wager_ratio, 2),
        'target_add' => round($target_add, 2)
    );
}

function propay_create_deposit_order($conn, $uid, $amount, $method, $promo_id, $channel = '') {
    return array('success' => false, 'message' => 'ProPay is disabled. LG Pay is the only active payment gateway.');

    propay_ensure_schema($conn);
    $settings = propay_get_settings($conn);
    $secret = trim($settings['secret_code']);
    if (!$settings['is_enabled']) {
        return array('success' => false, 'message' => 'Payment gateway is currently disabled.');
    }
    if ($secret === '') {
        return array('success' => false, 'message' => 'ProPay Secret Code/API Key is not configured in Admin Panel.');
    }

    $txnSettings = propay_get_site_transaction_settings($conn);
    $minDeposit = (float)($txnSettings['min_deposit_amount'] ?? 100);
    $amount = round((float)$amount, 2);
    if ($amount < $minDeposit) {
        return array('success' => false, 'message' => 'Minimum deposit amount is ৳' . propay_format_amount($minDeposit) . '.');
    }

    $methodKey = propay_normalize_method($method);
    $endpoint = propay_deposit_endpoint($methodKey);
    if ($methodKey === '' || $endpoint === '') {
        return array('success' => false, 'message' => 'Invalid payment method.');
    }

    $orderNo = propay_make_order_no('D', $uid);
    $methodLabel = propay_method_label($methodKey);
    $channelLabel = propay_normalize_channel($channel);
    if ($channelLabel === '') {
        return array('success' => false, 'message' => 'Please select a payment channel.');
    }
    $historyMethod = 'ProPay ' . $methodLabel . ' / ' . $channelLabel;
    $agentId = 0;
    $promo_id = intval($promo_id);

    $userStmt = $conn->prepare("SELECT agent_id FROM users WHERE id=? LIMIT 1");
    $userStmt->bind_param('i', $uid);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    if ($userRes && $userRes->num_rows > 0) {
        $u = $userRes->fetch_assoc();
        $agentId = intval($u['agent_id'] ?? 0);
    }

    $conn->begin_transaction();
    try {
        $transactionIdForUser = $orderNo;
        $stmt = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, promo_id, status, wallet_number, transaction_id, order_sn, agent_id, admin_note, created_at)
            VALUES (?, 'deposit', ?, ?, ?, 'pending', '', ?, ?, ?, 'ProPay deposit started', NOW())");
        $stmt->bind_param('idsissi', $uid, $amount, $historyMethod, $promo_id, $transactionIdForUser, $orderNo, $agentId);
        $stmt->execute();
        $txId = $stmt->insert_id;

        $rawRequest = array(
            'api_key' => '***hidden***',
            'uid' => (string)$uid,
            'amount' => propay_format_amount($amount),
            'channel' => $channelLabel,
            'order_no' => $orderNo,
            'return_url' => propay_url('/player/propay_deposit_return.php?order_no=' . urlencode($orderNo)),
            'pass_through_callback_url' => propay_url('/api/propay_callback.php')
        );
        $rawJson = propay_json($rawRequest);

        $stmt2 = $conn->prepare("INSERT INTO payment_gateway_orders (transaction_id, order_no, user_id, gateway, type, method, channel, amount, status, raw_request, created_at)
            VALUES (?, ?, ?, 'propay', 'deposit', ?, ?, ?, 'pending', ?, NOW())");
        $stmt2->bind_param('isissds', $txId, $orderNo, $uid, $methodKey, $channelLabel, $amount, $rawJson);
        $stmt2->execute();
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Failed to create deposit order: ' . $e->getMessage());
    }

    $params = array(
        'api_key' => $secret,
        'uid' => (string)$uid,
        'amount' => propay_format_amount($amount),
        'order_no' => $orderNo,
        'return_url' => propay_url('/player/propay_deposit_return.php?order_no=' . urlencode($orderNo)),
        'pass_through_key' => $secret,
        'pass_through_callback_url' => propay_url('/api/propay_callback.php')
    );

    return array(
        'success' => true,
        'order_no' => $orderNo,
        'redirect_url' => $endpoint . '?' . http_build_query($params)
    );
}

function propay_verify_signature($order_no, $amountRaw, $received_signature, $secret) {
    $expected = hash_hmac('sha256', (string)$order_no . (string)$amountRaw, (string)$secret);
    if (function_exists('hash_equals')) {
        return hash_equals($expected, (string)$received_signature);
    }
    return $expected === (string)$received_signature;
}

function propay_apply_deposit_callback($conn, $payload) {
    propay_ensure_schema($conn);
    $settings = propay_get_settings($conn);
    $secret = trim($settings['secret_code']);

    $orderNo = trim($payload['order_no'] ?? '');
    $amountRaw = trim((string)($payload['amount'] ?? ''));
    $amount = (float)$amountRaw;
    $status = strtolower(trim($payload['status'] ?? ''));
    $signature = trim($payload['signature'] ?? '');
    $payloadJson = propay_json($payload);

    if ($orderNo === '' || $amount <= 0 || $signature === '' || $secret === '') {
        return array('success' => false, 'http_code' => 400, 'message' => 'Missing required callback data.');
    }

    if (!propay_verify_signature($orderNo, $amountRaw, $signature, $secret)) {
        return array('success' => false, 'http_code' => 403, 'message' => 'Invalid Signature');
    }

    if ($status !== 'success') {
        $stmtFail = $conn->prepare("UPDATE payment_gateway_orders SET status='failed', gateway_status=?, callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='propay' AND type='deposit'");
        $stmtFail->bind_param('sss', $status, $payloadJson, $orderNo);
        $stmtFail->execute();
        return array('success' => false, 'http_code' => 400, 'message' => 'Payment status is not success.');
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='propay' AND type='deposit' LIMIT 1 FOR UPDATE");
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $orderRes = $stmt->get_result();
        if (!$orderRes || $orderRes->num_rows === 0) {
            $conn->rollback();
            return array('success' => false, 'http_code' => 404, 'message' => 'Order not found.');
        }
        $order = $orderRes->fetch_assoc();

        if ($order['status'] === 'success') {
            $conn->commit();
            return array('success' => true, 'http_code' => 200, 'message' => 'Success');
        }

        $expectedAmount = (float)$order['amount'];
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
            $credit = propay_calculate_deposit_credit($conn, $uid, $amount, $promoId, $order['method'] ?? '');
            $totalMoney = (float)$credit['total_money'];
            $targetAdd = (float)$credit['target_add'];
            $bonusAmount = (float)$credit['bonus_amount'];
            $note = 'ProPay auto verified. Bonus: ' . propay_format_amount($bonusAmount) . ', Wager add: ' . propay_format_amount($targetAdd);

            $stmtUser = $conn->prepare("UPDATE users SET balance = balance + ?, turnover_target = GREATEST(COALESCE(turnover_target,0), COALESCE(turnover_completed,0)) + ? WHERE id=?");
            $stmtUser->bind_param('ddi', $totalMoney, $targetAdd, $uid);
            $stmtUser->execute();

            if ($txId > 0) {
                $approved = 'approved';
                $stmtUpdateTx = $conn->prepare("UPDATE transactions_fake SET status=?, transaction_id=?, admin_note=?, is_notified=0 WHERE id=? AND status='pending'");
                $stmtUpdateTx->bind_param('sssi', $approved, $orderNo, $note, $txId);
                $stmtUpdateTx->execute();
                if (function_exists('wcb_referral_award_for_deposit')) { wcb_referral_award_for_deposit($conn, $uid, $txId, $amount); }
            }
        }

        $success = 'success';
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='success', gateway_status=?, callback_payload=?, credited_at=NOW(), updated_at=NOW() WHERE order_no=?");
        $stmtOrder->bind_param('sss', $success, $payloadJson, $orderNo);
        $stmtOrder->execute();

        $conn->commit();
        return array('success' => true, 'http_code' => 200, 'message' => 'Success');
    } catch (Exception $e) {
        $conn->rollback();
        return array('success' => false, 'http_code' => 500, 'message' => 'Server error: ' . $e->getMessage());
    }
}

function propay_http_post($url, $fields, $timeoutSeconds) {
    if (!function_exists('curl_init')) {
        return array('success' => false, 'http_code' => 0, 'error' => 'cURL extension is not enabled.', 'body' => '');
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => max(10, intval($timeoutSeconds)),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array('Accept: application/json')
    ));
    $body = curl_exec($curl);
    $err = curl_error($curl);
    $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return array('success' => ($err === ''), 'http_code' => $http, 'error' => $err, 'body' => (string)$body);
}

function propay_withdraw_endpoint() {
    return 'https://checkout.propay.cyou/pay/api-withdraw.php';
}

function propay_withdraw_status_endpoint() {
    return 'https://checkout.propay.cyou/pay/api-status-check.php';
}

function propay_http_post_json($url, $fields, $timeoutSeconds) {
    if (!function_exists('curl_init')) {
        return array('success' => false, 'http_code' => 0, 'error' => 'cURL extension is not enabled.', 'body' => '');
    }
    $payload = propay_json($fields);
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
        CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/json', 'Content-Length: ' . strlen($payload))
    ));
    $body = curl_exec($curl);
    $err = curl_error($curl);
    $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return array('success' => ($err === ''), 'http_code' => $http, 'error' => $err, 'body' => (string)$body);
}

function propay_status_is_success($status) {
    $s = strtolower(trim((string)$status));
    return in_array($s, array('success','successful','completed','complete','paid','approved','done'), true);
}

function propay_status_is_failed($status) {
    $s = strtolower(trim((string)$status));
    return in_array($s, array('failed','fail','rejected','reject','cancelled','canceled','declined','error'), true);
}

function propay_extract_withdraw_status($decoded) {
    if (!is_array($decoded)) { return ''; }
    foreach (array('transaction_status','payout_status','withdraw_status','pay_status','state') as $key) {
        if (isset($decoded[$key]) && trim((string)$decoded[$key]) !== '') {
            return strtolower(trim((string)$decoded[$key]));
        }
    }
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        foreach (array('transaction_status','payout_status','withdraw_status','pay_status','state') as $key) {
            if (isset($decoded['data'][$key]) && trim((string)$decoded['data'][$key]) !== '') {
                return strtolower(trim((string)$decoded['data'][$key]));
            }
        }
    }
    return '';
}

function propay_extract_gateway_order_no($decoded, $fallback) {
    if (!is_array($decoded)) { return $fallback; }
    foreach (array('gateway_order_no','payout_id','payout_no','transaction_id','trx_id','order_no','order_id') as $key) {
        if (!empty($decoded[$key])) { return trim((string)$decoded[$key]); }
    }
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        foreach (array('gateway_order_no','payout_id','payout_no','transaction_id','trx_id','order_no','order_id') as $key) {
            if (!empty($decoded['data'][$key])) { return trim((string)$decoded['data'][$key]); }
        }
    }
    return $fallback;
}

function propay_withdraw_callback_url() {
    return propay_url('/api/propay_withdraw_callback.php');
}

function propay_submit_withdrawal($conn, $uid, $amount, $wallet) {
    return array('success' => false, 'message' => 'ProPay payout is disabled. LG Pay is the only active payout gateway.');

    propay_ensure_schema($conn);
    if (function_exists('wcb_withdraw_ensure_schema')) { wcb_withdraw_ensure_schema($conn); }
    $settings = propay_get_settings($conn);
    $secret = trim((string)($settings['secret_code'] ?? ''));
    if (intval($settings['is_enabled'] ?? 0) !== 1) {
        return array('success' => false, 'message' => 'Payment gateway is currently disabled.');
    }
    if ($secret === '') {
        return array('success' => false, 'message' => 'ProPay API Key is not configured in Admin Panel.');
    }

    $txnSettings = propay_get_site_transaction_settings($conn);
    $minWithdraw = (float)($txnSettings['min_withdraw_amount'] ?? 100);
    $amount = round((float)$amount, 2);
    if ($amount < $minWithdraw) {
        return array('success' => false, 'message' => 'Minimum withdrawal amount is ৳' . propay_format_amount($minWithdraw) . '.');
    }

    $methodKey = propay_normalize_method($wallet['method_code'] ?? ($wallet['method'] ?? ''));
    if ($methodKey === '') {
        return array('success' => false, 'message' => 'Automatic withdrawal is available only for bKash and Nagad.');
    }
    $accountNo = preg_replace('/\D+/', '', (string)($wallet['wallet_number'] ?? ''));
    if (!preg_match('/^01\d{9}$/', $accountNo)) {
        return array('success' => false, 'message' => 'Enter a valid 11 digit bKash or Nagad number.');
    }

    $orderNo = propay_make_order_no('W', $uid);
    $bankCode = propay_bank_code($methodKey);
    $historyMethod = 'ProPay ' . propay_method_label($methodKey) . ' (' . $accountNo . ')';
    $apiFields = array(
        'api_key' => $secret,
        'amount' => propay_format_amount($amount),
        'bank_code' => $bankCode,
        'account_number' => $accountNo
    );
    $requestForLog = $apiFields;
    $requestForLog['api_key'] = '***hidden***';
    $rawRequest = propay_json($requestForLog);
    $txId = 0;

    $conn->begin_transaction();
    try {
        $stmtUser = $conn->prepare("SELECT balance, COALESCE(turnover_target,0) AS turnover_target, COALESCE(turnover_completed,0) AS turnover_completed FROM users WHERE id=? LIMIT 1 FOR UPDATE");
        $stmtUser->bind_param('i', $uid);
        $stmtUser->execute();
        $userRes = $stmtUser->get_result();
        if (!$userRes || $userRes->num_rows === 0) {
            $conn->rollback();
            return array('success' => false, 'message' => 'User account was not found.');
        }
        $user = $userRes->fetch_assoc();
        $remaining = max(0, (float)$user['turnover_target'] - (float)$user['turnover_completed']);
        if ($remaining > 0.01) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Required turnover is not complete. Remaining Turnover: ৳' . number_format($remaining, 2));
        }
        if ((float)$user['balance'] < $amount) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Insufficient withdrawable balance.');
        }

        $stmtDeduct = $conn->prepare("UPDATE users SET balance=balance-? WHERE id=? AND balance>=?");
        $stmtDeduct->bind_param('did', $amount, $uid, $amount);
        $stmtDeduct->execute();
        if ($stmtDeduct->affected_rows < 1) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Insufficient withdrawable balance.');
        }

        $walletId = intval($wallet['id'] ?? 0);
        $methodCode = $methodKey;
        $note = 'ProPay automatic payout processing.';
        $stmtTx = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, status, wallet_number, withdraw_wallet_id, withdraw_method_code, withdraw_pin_verified, transaction_id, order_sn, agent_id, admin_note, created_at) VALUES (?, 'withdraw', ?, ?, 'processing', ?, ?, ?, 1, ?, ?, 0, ?, NOW())");
        $stmtTx->bind_param('idssissss', $uid, $amount, $historyMethod, $accountNo, $walletId, $methodCode, $orderNo, $orderNo, $note);
        $stmtTx->execute();
        $txId = intval($stmtTx->insert_id);

        $stmtOrder = $conn->prepare("INSERT INTO payment_gateway_orders (transaction_id, order_no, user_id, gateway, type, method, account_number, amount, status, gateway_status, raw_request, created_at) VALUES (?, ?, ?, 'propay', 'withdraw', ?, ?, ?, 'processing', 'submitting', ?, NOW())");
        $stmtOrder->bind_param('isissds', $txId, $orderNo, $uid, $methodKey, $accountNo, $amount, $rawRequest);
        $stmtOrder->execute();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Unable to start automatic withdrawal.');
    }

    $http = propay_http_post(propay_withdraw_endpoint(), $apiFields, 35);
    $decoded = json_decode($http['body'], true);
    $rawResponse = $http['body'];
    if (!$http['success'] || !is_array($decoded)) {
        $message = $http['error'] !== '' ? $http['error'] : 'Invalid response from payment gateway.';
        propay_fail_withdraw_order($conn, $orderNo, $rawResponse, $message, true);
        return array('success' => false, 'message' => $message);
    }

    $topStatus = strtolower(trim((string)($decoded['status'] ?? '')));
    $gatewayOrderNo = trim((string)($decoded['order_no'] ?? ''));
    if ($topStatus !== 'success' || $gatewayOrderNo === '') {
        $message = trim((string)($decoded['message'] ?? ($decoded['error'] ?? 'Payment gateway rejected the withdrawal.')));
        if ($message === '') { $message = 'Payment gateway rejected the withdrawal.'; }
        propay_fail_withdraw_order($conn, $orderNo, $rawResponse, $message, true);
        return array('success' => false, 'message' => $message, 'gateway_response' => $decoded);
    }

    $gatewayStatus = propay_extract_withdraw_status($decoded);
    if ($gatewayStatus === '') { $gatewayStatus = 'payouting'; }
    $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_order_no=?, gateway_status=?, raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='propay' AND type='withdraw'");
    $stmtUp->bind_param('ssss', $gatewayOrderNo, $gatewayStatus, $rawResponse, $orderNo);
    $stmtUp->execute();
    $note = 'ProPay automatic payout submitted. Gateway order: ' . $gatewayOrderNo;
    $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET lg_order_sn=?, admin_note=? WHERE id=? AND status='processing'");
    $stmtTxUp->bind_param('ssi', $gatewayOrderNo, $note, $txId);
    $stmtTxUp->execute();

    if (propay_status_is_success($gatewayStatus)) {
        propay_mark_withdraw_success($conn, $orderNo, $gatewayStatus, $rawResponse);
    } elseif (propay_status_is_failed($gatewayStatus)) {
        $message = trim((string)($decoded['message'] ?? 'Transaction failed.'));
        propay_fail_withdraw_order($conn, $orderNo, $rawResponse, $message, true);
        return array('success' => false, 'message' => $message, 'gateway_response' => $decoded);
    }

    return array(
        'success' => true,
        'message' => 'Withdrawal sent to ProPay successfully. Order: ' . $gatewayOrderNo,
        'order_no' => $gatewayOrderNo,
        'gateway_status' => $gatewayStatus,
        'gateway_response' => $decoded
    );
}

function propay_process_pending_withdrawal($conn, $transactionId, $adminId = 0) {
    return array('success' => false, 'message' => 'Automatic withdrawals are submitted directly by the user and do not require admin approval.');
}

function propay_fail_withdraw_order($conn, $orderNo, $rawResponse, $reason, $refund) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='propay' AND type='withdraw' LIMIT 1 FOR UPDATE");
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
            $note = 'ProPay withdrawal failed and balance refunded: ' . $reason;
            $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET status='rejected', admin_note=? WHERE id=? AND status IN ('pending','processing')");
            $stmtTxUp->bind_param('si', $note, $txId);
            $stmtTxUp->execute();
        }

        $failedStatus = propay_extract_withdraw_status(json_decode((string)$rawResponse, true));
        if ($failedStatus === '') { $failedStatus = 'failed'; }
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='failed', gateway_status=?, raw_response=?, updated_at=NOW() WHERE order_no=?");
        $stmtOrder->bind_param('sss', $failedStatus, $rawResponse, $orderNo);
        $stmtOrder->execute();
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function propay_mark_withdraw_success($conn, $orderNo, $gatewayStatus, $rawResponse) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_gateway_orders WHERE order_no=? AND gateway='propay' AND type='withdraw' LIMIT 1 FOR UPDATE");
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $conn->rollback(); return false; }
        $order = $res->fetch_assoc();
        if ($order['status'] === 'success') { $conn->commit(); return true; }
        if ($order['status'] === 'failed') { $conn->rollback(); return false; }
        $txId = intval($order['transaction_id']);
        if ($txId > 0) {
            $note = 'ProPay automatic withdrawal completed successfully.';
            $stmtTxUp = $conn->prepare("UPDATE transactions_fake SET status='approved', admin_note=? WHERE id=? AND status IN ('pending','processing')");
            $stmtTxUp->bind_param('si', $note, $txId);
            $stmtTxUp->execute();
        }
        $stmtOrder = $conn->prepare("UPDATE payment_gateway_orders SET status='success', gateway_status=?, raw_response=?, credited_at=NOW(), updated_at=NOW() WHERE order_no=?");
        $stmtOrder->bind_param('sss', $gatewayStatus, $rawResponse, $orderNo);
        $stmtOrder->execute();
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function propay_sync_pending_withdrawals($conn, $user_id, $limit) {
    propay_ensure_schema($conn);
    $settings = propay_get_settings($conn);
    $secret = trim((string)($settings['secret_code'] ?? ''));
    if ($secret === '') { return array('checked' => 0, 'updated' => 0); }
    $limit = max(1, min(50, intval($limit)));
    $checked = 0;
    $updated = 0;

    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT order_no, gateway_order_no FROM payment_gateway_orders WHERE gateway='propay' AND type='withdraw' AND status IN ('pending','processing') AND user_id=? ORDER BY id ASC LIMIT ?");
        $stmt->bind_param('ii', $user_id, $limit);
    } else {
        $stmt = $conn->prepare("SELECT order_no, gateway_order_no FROM payment_gateway_orders WHERE gateway='propay' AND type='withdraw' AND status IN ('pending','processing') ORDER BY id ASC LIMIT ?");
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $gatewayOrderNo = trim((string)($row['gateway_order_no'] ?? ''));
        if ($gatewayOrderNo === '') { continue; }
        $checked++;
        $statusPayload = array('api_key' => $secret, 'order_no' => $gatewayOrderNo);
        $http = propay_http_post_json(propay_withdraw_status_endpoint(), $statusPayload, 20);
        $decoded = json_decode($http['body'], true);
        if (!$http['success'] || !is_array($decoded)) {
            $http = propay_http_post(propay_withdraw_status_endpoint(), $statusPayload, 20);
            $decoded = json_decode($http['body'], true);
        }
        if (!$http['success'] || !is_array($decoded)) { continue; }
        $transactionStatus = propay_extract_withdraw_status($decoded);
        $rawResponse = $http['body'];
        if (propay_status_is_success($transactionStatus)) {
            if (propay_mark_withdraw_success($conn, $row['order_no'], $transactionStatus, $rawResponse)) { $updated++; }
        } elseif (propay_status_is_failed($transactionStatus)) {
            $reason = trim((string)($decoded['message'] ?? ('Gateway status: ' . $transactionStatus)));
            if (propay_fail_withdraw_order($conn, $row['order_no'], $rawResponse, $reason, true)) { $updated++; }
        } elseif ($transactionStatus !== '') {
            $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status=?, raw_response=?, updated_at=NOW() WHERE order_no=? AND gateway='propay' AND type='withdraw'");
            $stmtUp->bind_param('sss', $transactionStatus, $rawResponse, $row['order_no']);
            $stmtUp->execute();
        }
    }
    return array('checked' => $checked, 'updated' => $updated);
}

function propay_apply_withdraw_callback($conn, $payload) {
    propay_ensure_schema($conn);
    $payloadJson = propay_json($payload);
    $orderNo = '';
    foreach (array('order_id','order_no','merchant_order_id','local_order_no') as $key) {
        if (!empty($payload[$key])) { $orderNo = trim((string)$payload[$key]); break; }
    }
    $gatewayOrderNo = '';
    foreach (array('gateway_order_no','payout_id','payout_no','transaction_id','trx_id') as $key) {
        if (!empty($payload[$key])) { $gatewayOrderNo = trim((string)$payload[$key]); break; }
    }
    if ($orderNo === '' && $gatewayOrderNo === '') {
        return array('success' => false, 'http_code' => 400, 'message' => 'Missing order id.');
    }

    if ($orderNo !== '') {
        $stmt = $conn->prepare("UPDATE payment_gateway_orders SET callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='propay' AND type='withdraw'");
        $stmt->bind_param('ss', $payloadJson, $orderNo);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("UPDATE payment_gateway_orders SET callback_payload=?, updated_at=NOW() WHERE gateway_order_no=? AND gateway='propay' AND type='withdraw'");
        $stmt->bind_param('ss', $payloadJson, $gatewayOrderNo);
        $stmt->execute();
        $lookup = $conn->prepare("SELECT order_no FROM payment_gateway_orders WHERE gateway_order_no=? AND gateway='propay' AND type='withdraw' LIMIT 1");
        $lookup->bind_param('s', $gatewayOrderNo);
        $lookup->execute();
        $res = $lookup->get_result();
        if ($res && $res->num_rows > 0) { $orderNo = $res->fetch_assoc()['order_no']; }
    }

    if ($orderNo === '') {
        return array('success' => false, 'http_code' => 404, 'message' => 'Withdraw order not found.');
    }

    $status = propay_extract_withdraw_status($payload);
    if ($status === '' && isset($payload['status'])) {
        $candidateStatus = strtolower(trim((string)$payload['status']));
        if (propay_status_is_success($candidateStatus) || propay_status_is_failed($candidateStatus)) { $status = $candidateStatus; }
    }
    if (propay_status_is_success($status)) {
        $ok = propay_mark_withdraw_success($conn, $orderNo, $status, $payloadJson);
        return array('success' => $ok, 'http_code' => $ok ? 200 : 500, 'message' => $ok ? 'Success' : 'Update failed');
    }
    if (propay_status_is_failed($status)) {
        $reason = isset($payload['message']) ? (string)$payload['message'] : ('Gateway callback status: ' . $status);
        $ok = propay_fail_withdraw_order($conn, $orderNo, $payloadJson, $reason, true);
        return array('success' => $ok, 'http_code' => $ok ? 200 : 500, 'message' => $ok ? 'Failed status updated' : 'Update failed');
    }

    if ($status !== '' && $orderNo !== '') {
        $stmtUp = $conn->prepare("UPDATE payment_gateway_orders SET gateway_status=?, callback_payload=?, updated_at=NOW() WHERE order_no=? AND gateway='propay' AND type='withdraw'");
        $stmtUp->bind_param('sss', $status, $payloadJson, $orderNo);
        $stmtUp->execute();
    }
    return array('success' => true, 'http_code' => 200, 'message' => 'Processing');
}

function propay_check_merchant_balance($conn) {
    return array('success' => false, 'message' => 'ProPay is disabled. LG Pay is the only active gateway.');

    $settings = propay_get_settings($conn);
    $merchant = trim($settings['merchant_code']);
    $secret = trim($settings['secret_code']);
    if ($merchant === '' || $secret === '') {
        return array('success' => false, 'message' => 'Merchant Code and Secret Code/API Key are required.');
    }
    $http = propay_http_post('https://checkout.propay.cyou/pay/balance-check.php', array('merchant_id' => $merchant, 'api_key' => $secret), 20);
    $decoded = json_decode($http['body'], true);
    if (!is_array($decoded)) {
        return array('success' => false, 'message' => $http['error'] ?: 'Invalid gateway response.', 'raw' => $http['body']);
    }
    $status = $decoded['status'] ?? false;
    $statusText = strtolower(trim((string)$status));
    $success = $status === true || $status === 1 || $status === '1' || in_array($statusText, array('success','successful','ok'), true);
    $message = trim((string)($decoded['message'] ?? $decoded['error'] ?? ''));
    if (!$success && $message === '') { $message = 'Merchant balance check was rejected.'; }
    return array('success' => $success, 'message' => $message, 'data' => $decoded, 'raw' => $http['body']);
}
?>
