<?php
// includes/bonus_system_helper.php
// WEB CORNER BD TEAM
// Daily free bonus + Deposit amount based claim bonus helper.

if (!function_exists('wcb_table_exists')) {
    function wcb_table_exists($conn, $table) {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $res = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safe) . "'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('wcb_column_exists')) {
    function wcb_column_exists($conn, $table, $column) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        $res = @$conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '" . $conn->real_escape_string($safeColumn) . "'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('wcb_index_exists')) {
    function wcb_index_exists($conn, $table, $indexName) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$indexName);
        $res = @$conn->query("SHOW INDEX FROM `$safeTable` WHERE Key_name='" . $conn->real_escape_string($safeIndex) . "'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('wcb_add_column_if_missing')) {
    function wcb_add_column_if_missing($conn, $table, $column, $definition) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        if (!wcb_column_exists($conn, $safeTable, $safeColumn)) {
            @$conn->query("ALTER TABLE `$safeTable` ADD COLUMN `$safeColumn` $definition");
        }
    }
}

if (!function_exists('wcb_money')) {
    function wcb_money($amount) {
        return number_format((float)$amount, 2, '.', '');
    }
}

function wcb_bonus_ensure_schema($conn) {
    static $schema_ready = false;
    if ($schema_ready) { return true; }
    if (!$conn || !empty($conn->connect_error)) { return false; }

    @$conn->query("CREATE TABLE IF NOT EXISTS `daily_bonus_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `bonus_amount` DECIMAL(15,2) NOT NULL DEFAULT 50.00,
        `bonus_title` VARCHAR(255) NOT NULL DEFAULT 'Daily Free Bonus',
        `bonus_text` TEXT DEFAULT NULL,
        `claim_button_text` VARCHAR(80) NOT NULL DEFAULT 'Claim Now',
        `cycle_hours` INT(11) NOT NULL DEFAULT 24,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    @$conn->query("INSERT IGNORE INTO `daily_bonus_settings` (`id`, `is_enabled`, `bonus_amount`, `bonus_title`, `bonus_text`, `claim_button_text`, `cycle_hours`)
        VALUES (1, 1, 50.00, 'Daily Free Bonus', 'Claim your daily free reward once every 24 hours.', 'Claim Now', 24)");

    @$conn->query("CREATE TABLE IF NOT EXISTS `daily_bonus_claims` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `claimed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_claimed_at` (`user_id`, `claimed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    @$conn->query("CREATE TABLE IF NOT EXISTS `deposit_bonus_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `section_title` VARCHAR(255) NOT NULL DEFAULT 'নতুন সদস্য বৃদ্ধির পরিকল্পনা',
        `section_subtitle` VARCHAR(255) NOT NULL DEFAULT 'Deposit Successful হলে Bonus Available হবে',
        `status_text` VARCHAR(255) NOT NULL DEFAULT 'Not checked in today ?',
        `claim_button_text` VARCHAR(80) NOT NULL DEFAULT 'Sign In',
        `locked_button_text` VARCHAR(80) NOT NULL DEFAULT 'Sign In',
        `claimed_button_text` VARCHAR(80) NOT NULL DEFAULT 'Claimed',
        `claim_rules_text` TEXT DEFAULT NULL,
        `daily_claim_limit` INT(11) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    @$conn->query("INSERT IGNORE INTO `deposit_bonus_settings`
        (`id`, `is_enabled`, `section_title`, `section_subtitle`, `status_text`, `claim_button_text`, `locked_button_text`, `claimed_button_text`, `claim_rules_text`, `daily_claim_limit`)
        VALUES
        (1, 1, 'নতুন সদস্য বৃদ্ধির পরিকল্পনা', 'Deposit Successful হলে Bonus Available হবে', 'Not checked in today ?', 'Sign In', 'Sign In', 'Claimed', '১) Bonus claim করার জন্য আগে successful deposit থাকতে হবে।\n২) এক deposit cycle/day-এ শুধু ১ বার bonus claim করা যাবে।\n৩) Bonus wallet-এ add হবে এবং turnover complete হওয়ার আগে withdraw করা যাবে না।', 1)");

    @$conn->query("CREATE TABLE IF NOT EXISTS `deposit_bonus_rules` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `min_deposit_amount` DECIMAL(15,2) NOT NULL DEFAULT 1000.00,
        `bonus_type` ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
        `bonus_value` DECIMAL(15,2) NOT NULL DEFAULT 100.00,
        `turnover_multiplier` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        `rules_text` TEXT DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_active_sort` (`is_active`, `sort_order`),
        KEY `idx_min_deposit` (`min_deposit_amount`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    wcb_add_column_if_missing($conn, 'deposit_bonus_rules', 'turnover_multiplier', "DECIMAL(10,2) NOT NULL DEFAULT 1.00 AFTER `bonus_value`");

    // If the admin has not created any rule yet, add safe example rules matching the requested 500/1000 flow.
    $ruleCountRes = @$conn->query("SELECT COUNT(*) AS c FROM deposit_bonus_rules");
    $ruleCount = ($ruleCountRes && $ruleCountRes->num_rows > 0) ? (int)$ruleCountRes->fetch_assoc()['c'] : 0;
    if ($ruleCount === 0) {
        @$conn->query("INSERT INTO deposit_bonus_rules (title, min_deposit_amount, bonus_type, bonus_value, turnover_multiplier, rules_text, is_active, sort_order, created_at) VALUES
            ('500 Deposit Bonus', 500.00, 'fixed', 500.00, 1.00, '500 টাকা approved deposit হলে 500 টাকা bonus claim করা যাবে।', 1, 1, NOW()),
            ('1000 Deposit Bonus', 1000.00, 'fixed', 1000.00, 1.00, '1000 টাকা approved deposit হলে 1000 টাকা bonus claim করা যাবে।', 1, 2, NOW())");
    }

    @$conn->query("CREATE TABLE IF NOT EXISTS `deposit_bonus_claims` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `rule_id` INT(11) NOT NULL,
        `deposit_txn_id` INT(11) DEFAULT NULL,
        `deposit_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `deposit_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `bonus_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `turnover_required` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `claim_date` DATE NOT NULL,
        `claim_status` VARCHAR(30) NOT NULL DEFAULT 'claimed',
        `claim_note` TEXT DEFAULT NULL,
        `claimed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_rule_date` (`user_id`, `rule_id`, `claim_date`),
        KEY `idx_user_claim_date` (`user_id`, `claim_date`),
        KEY `idx_deposit_txn` (`deposit_txn_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    wcb_add_column_if_missing($conn, 'deposit_bonus_claims', 'deposit_txn_id', "INT(11) DEFAULT NULL AFTER `rule_id`");
    wcb_add_column_if_missing($conn, 'deposit_bonus_claims', 'deposit_amount', "DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `deposit_txn_id`");
    wcb_add_column_if_missing($conn, 'deposit_bonus_claims', 'deposit_total', "DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `deposit_amount`");
    wcb_add_column_if_missing($conn, 'deposit_bonus_claims', 'turnover_required', "DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `bonus_amount`");
    wcb_add_column_if_missing($conn, 'deposit_bonus_claims', 'claim_status', "VARCHAR(30) NOT NULL DEFAULT 'claimed' AFTER `claim_date`");
    wcb_add_column_if_missing($conn, 'deposit_bonus_claims', 'claim_note', "TEXT DEFAULT NULL AFTER `claim_status`");
    if (wcb_table_exists($conn, 'deposit_bonus_claims') && !wcb_index_exists($conn, 'deposit_bonus_claims', 'uniq_user_claim_date')) {
        // Prevent multiple deposit-bonus claims in the same daily cycle. If older duplicate data exists, this may fail safely.
        @$conn->query("ALTER TABLE `deposit_bonus_claims` ADD UNIQUE KEY `uniq_user_claim_date` (`user_id`, `claim_date`)");
    }

    if (wcb_table_exists($conn, 'users')) {
        wcb_add_column_if_missing($conn, 'users', 'bonus_balance', "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `demo_balance`");
        wcb_add_column_if_missing($conn, 'users', 'turnover_target', "DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `email_verified`");
        wcb_add_column_if_missing($conn, 'users', 'turnover_completed', "DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `turnover_target`");
    }

    if (wcb_table_exists($conn, 'transactions_fake')) {
        wcb_add_column_if_missing($conn, 'transactions_fake', 'admin_note', 'TEXT DEFAULT NULL');
        wcb_add_column_if_missing($conn, 'transactions_fake', 'is_notified', 'TINYINT(1) NOT NULL DEFAULT 0');
    }
    $schema_ready = true;
    return true;
}

function wcb_bonus_settings($conn) {
    wcb_bonus_ensure_schema($conn);
    $defaults = array('is_enabled' => 1, 'bonus_amount' => 50.00, 'bonus_title' => 'Daily Free Bonus', 'bonus_text' => 'Claim your daily free reward once every 24 hours.', 'claim_button_text' => 'Claim Now', 'cycle_hours' => 24);
    $res = @$conn->query("SELECT * FROM daily_bonus_settings WHERE id=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        foreach ($defaults as $k => $v) { if (array_key_exists($k, $row)) { $defaults[$k] = $row[$k]; } }
    }
    $defaults['is_enabled'] = intval($defaults['is_enabled']);
    $defaults['bonus_amount'] = round(max(0, (float)$defaults['bonus_amount']), 2);
    $defaults['cycle_hours'] = max(1, intval($defaults['cycle_hours']));
    return $defaults;
}

function wcb_deposit_bonus_settings($conn) {
    wcb_bonus_ensure_schema($conn);
    $defaults = array(
        'is_enabled' => 1,
        'section_title' => 'নতুন সদস্য বৃদ্ধির পরিকল্পনা',
        'section_subtitle' => 'Deposit Successful হলে Bonus Available হবে',
        'status_text' => 'Not checked in today ?',
        'claim_button_text' => 'Sign In',
        'locked_button_text' => 'Sign In',
        'claimed_button_text' => 'Claimed',
        'claim_rules_text' => "১) Bonus claim করার জন্য আগে successful deposit থাকতে হবে।\n২) এক deposit cycle/day-এ শুধু ১ বার bonus claim করা যাবে।\n৩) Bonus wallet-এ add হবে এবং turnover complete হওয়ার আগে withdraw করা যাবে না।",
        'daily_claim_limit' => 1
    );
    $res = @$conn->query("SELECT * FROM deposit_bonus_settings WHERE id=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        foreach ($defaults as $k => $v) { if (array_key_exists($k, $row)) { $defaults[$k] = $row[$k]; } }
    }
    $defaults['is_enabled'] = intval($defaults['is_enabled']);
    $defaults['daily_claim_limit'] = max(1, intval($defaults['daily_claim_limit']));
    return $defaults;
}

function wcb_daily_bonus_last_claim($conn, $userId) {
    wcb_bonus_ensure_schema($conn);
    $userId = intval($userId);
    $stmt = $conn->prepare("SELECT claimed_at FROM daily_bonus_claims WHERE user_id=? ORDER BY claimed_at DESC LIMIT 1");
    if (!$stmt) { return null; }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) { return $res->fetch_assoc()['claimed_at']; }
    return null;
}

function wcb_user_has_approved_deposit($conn, $userId) {
    wcb_bonus_ensure_schema($conn);
    $userId = intval($userId);
    if ($userId <= 0 || !wcb_table_exists($conn, 'transactions_fake')) { return false; }

    // Only a real, successful positive deposit unlocks the daily claim bonus.
    // Admin balance adjustments are intentionally excluded from deposit eligibility.
    $stmt = $conn->prepare("SELECT 1 FROM transactions_fake WHERE user_id=? AND type='deposit' AND status='approved' AND amount>0 AND (method IS NULL OR method NOT LIKE 'Manual Adjustment%') LIMIT 1");
    if (!$stmt) { return false; }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $hasDeposit = ($res && $res->num_rows > 0);
    $stmt->close();
    return $hasDeposit;
}

function wcb_daily_bonus_can_claim($conn, $userId) {
    $settings = wcb_bonus_settings($conn);
    if (!$settings['is_enabled'] || $settings['bonus_amount'] <= 0) {
        return array('can_claim' => false, 'settings' => $settings, 'last_claim' => null, 'next_claim_at' => null, 'seconds_remaining' => 0);
    }
    $last = wcb_daily_bonus_last_claim($conn, $userId);
    if (!$last) {
        return array('can_claim' => true, 'settings' => $settings, 'last_claim' => null, 'next_claim_at' => null, 'seconds_remaining' => 0);
    }
    $lastTs = strtotime($last);
    $nextTs = $lastTs + ($settings['cycle_hours'] * 3600);
    $remaining = $nextTs - time();
    return array(
        'can_claim' => ($remaining <= 0),
        'settings' => $settings,
        'last_claim' => $last,
        'next_claim_at' => date('Y-m-d H:i:s', $nextTs),
        'seconds_remaining' => max(0, $remaining)
    );
}

function wcb_daily_bonus_claim($conn, $userId) {
    wcb_bonus_ensure_schema($conn);
    $userId = intval($userId);
    if ($userId <= 0) { return array('success' => false, 'message' => 'Invalid user.'); }

    // Server-side permission check: direct API requests must not bypass the
    // deposit requirement. The popup silently follows this redirect.
    if (!wcb_user_has_approved_deposit($conn, $userId)) {
        return array('success' => false, 'redirect' => '/player/deposit.php');
    }

    $conn->begin_transaction();
    try {
        $check = wcb_daily_bonus_can_claim($conn, $userId);
        $settings = $check['settings'];
        if (!$check['can_claim']) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Daily bonus already claimed. Please wait for next cycle.', 'next_claim_at' => $check['next_claim_at']);
        }
        $amount = round((float)$settings['bonus_amount'], 2);
        if ($amount <= 0) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Daily bonus amount is not configured.');
        }

        $stmtUser = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id=?");
        $stmtUser->bind_param('di', $amount, $userId);
        $stmtUser->execute();
        if ($stmtUser->affected_rows < 1) {
            $conn->rollback();
            return array('success' => false, 'message' => 'User not found.');
        }

        $stmtClaim = $conn->prepare("INSERT INTO daily_bonus_claims (user_id, amount, claimed_at) VALUES (?, ?, NOW())");
        $stmtClaim->bind_param('id', $userId, $amount);
        $stmtClaim->execute();

        if (wcb_table_exists($conn, 'transactions_fake')) {
            $method = 'Daily Free Bonus';
            $note = 'Daily free bonus claimed';
            $stmtTx = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, status, admin_note, created_at) VALUES (?, 'bonus', ?, ?, 'approved', ?, NOW())");
            if ($stmtTx) {
                $stmtTx->bind_param('idss', $userId, $amount, $method, $note);
                $stmtTx->execute();
            }
        }

        $conn->commit();
        return array('success' => true, 'message' => 'Daily bonus claimed successfully.', 'amount' => $amount);
    } catch (Exception $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Server error: ' . $e->getMessage());
    }
}

function wcb_today_approved_deposits($conn, $userId) {
    wcb_bonus_ensure_schema($conn);
    $userId = intval($userId);
    $rows = array();
    if (!wcb_table_exists($conn, 'transactions_fake')) { return $rows; }
    $stmt = $conn->prepare("SELECT id, amount, method, transaction_id, order_sn, created_at FROM transactions_fake WHERE user_id=? AND type='deposit' AND status='approved' AND DATE(created_at)=CURDATE() ORDER BY amount DESC, created_at DESC, id DESC");
    if (!$stmt) { return $rows; }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $row['amount'] = round((float)$row['amount'], 2);
        $rows[] = $row;
    }
    return $rows;
}

function wcb_today_approved_deposit_total($conn, $userId) {
    $total = 0.00;
    foreach (wcb_today_approved_deposits($conn, $userId) as $dep) { $total += (float)$dep['amount']; }
    return round($total, 2);
}

function wcb_deposit_bonus_rules($conn, $activeOnly = true) {
    wcb_bonus_ensure_schema($conn);
    $where = $activeOnly ? "WHERE is_active=1" : "";
    $rows = array();
    $res = @$conn->query("SELECT * FROM deposit_bonus_rules $where ORDER BY sort_order ASC, min_deposit_amount ASC, id ASC");
    while ($res && ($row = $res->fetch_assoc())) {
        if (!isset($row['turnover_multiplier'])) { $row['turnover_multiplier'] = 1.00; }
        $rows[] = $row;
    }
    return $rows;
}

function wcb_deposit_bonus_claim_today($conn, $userId) {
    wcb_bonus_ensure_schema($conn);
    $userId = intval($userId);
    $stmt = $conn->prepare("SELECT * FROM deposit_bonus_claims WHERE user_id=? AND claim_date=CURDATE() ORDER BY claimed_at DESC, id DESC LIMIT 1");
    if (!$stmt) { return null; }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

function wcb_deposit_bonus_already_claimed_today($conn, $userId, $ruleId = 0) {
    wcb_bonus_ensure_schema($conn);
    $userId = intval($userId);
    $ruleId = intval($ruleId);
    if ($ruleId > 0) {
        $stmt = $conn->prepare("SELECT id FROM deposit_bonus_claims WHERE user_id=? AND rule_id=? AND claim_date=CURDATE() LIMIT 1");
        if (!$stmt) { return true; }
        $stmt->bind_param('ii', $userId, $ruleId);
    } else {
        $stmt = $conn->prepare("SELECT id FROM deposit_bonus_claims WHERE user_id=? AND claim_date=CURDATE() LIMIT 1");
        if (!$stmt) { return true; }
        $stmt->bind_param('i', $userId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    return $res && $res->num_rows > 0;
}

function wcb_deposit_bonus_amount($rule, $depositAmount) {
    $depositAmount = round((float)$depositAmount, 2);
    $type = strtolower((string)($rule['bonus_type'] ?? 'fixed'));
    $value = round((float)($rule['bonus_value'] ?? 0), 2);
    if ($type === 'percent') { return round(($depositAmount * $value) / 100, 2); }
    return max(0, $value);
}

function wcb_deposit_bonus_best_eligible($conn, $userId) {
    $settings = wcb_deposit_bonus_settings($conn);
    $rules = wcb_deposit_bonus_rules($conn, true);
    $deposits = wcb_today_approved_deposits($conn, $userId);
    $claimToday = wcb_deposit_bonus_claim_today($conn, $userId);
    $best = null;

    if (!$settings['is_enabled'] || empty($rules) || empty($deposits) || $claimToday) {
        return array('rule' => null, 'deposit' => null, 'bonus_amount' => 0.00, 'turnover_required' => 0.00);
    }

    foreach ($deposits as $deposit) {
        foreach ($rules as $rule) {
            $min = round((float)($rule['min_deposit_amount'] ?? 0), 2);
            if ((float)$deposit['amount'] < $min) { continue; }
            $candidate = array('rule' => $rule, 'deposit' => $deposit);
            if ($best === null) { $best = $candidate; continue; }

            $bestMin = round((float)($best['rule']['min_deposit_amount'] ?? 0), 2);
            $bestDeposit = round((float)($best['deposit']['amount'] ?? 0), 2);
            // Prefer the highest deposit tier. This keeps a 1000 deposit from also unlocking the 500 bonus.
            if ($min > $bestMin || ($min == $bestMin && (float)$deposit['amount'] > $bestDeposit)) {
                $best = $candidate;
            }
        }
    }

    if (!$best) { return array('rule' => null, 'deposit' => null, 'bonus_amount' => 0.00, 'turnover_required' => 0.00); }
    $bonus = wcb_deposit_bonus_amount($best['rule'], (float)$best['deposit']['amount']);
    $multiplier = max(0, round((float)($best['rule']['turnover_multiplier'] ?? 1), 2));
    $best['bonus_amount'] = round($bonus, 2);
    $best['turnover_required'] = round($bonus * $multiplier, 2);
    return $best;
}

function wcb_deposit_bonus_page_state($conn, $userId) {
    $settings = wcb_deposit_bonus_settings($conn);
    $rules = wcb_deposit_bonus_rules($conn, true);
    $deposits = wcb_today_approved_deposits($conn, $userId);
    $claimToday = wcb_deposit_bonus_claim_today($conn, $userId);
    $best = wcb_deposit_bonus_best_eligible($conn, $userId);
    $todayTotal = 0.00;
    $maxDeposit = 0.00;
    foreach ($deposits as $dep) {
        $todayTotal += (float)$dep['amount'];
        if ((float)$dep['amount'] > $maxDeposit) { $maxDeposit = (float)$dep['amount']; }
    }
    $minRequired = 0.00;
    foreach ($rules as $r) {
        $min = (float)($r['min_deposit_amount'] ?? 0);
        if ($minRequired <= 0 || ($min > 0 && $min < $minRequired)) { $minRequired = $min; }
    }
    return array(
        'settings' => $settings,
        'rules' => $rules,
        'deposits' => $deposits,
        'today_total' => round($todayTotal, 2),
        'max_deposit' => round($maxDeposit, 2),
        'min_required' => round($minRequired, 2),
        'claim_today' => $claimToday,
        'best' => $best
    );
}

function wcb_deposit_bonus_claim($conn, $userId, $ruleId) {
    wcb_bonus_ensure_schema($conn);
    $userId = intval($userId);
    $ruleId = intval($ruleId);
    if ($userId <= 0 || $ruleId <= 0) { return array('success' => false, 'message' => 'Invalid request.'); }

    $conn->begin_transaction();
    try {
        $lock = $conn->prepare("SELECT id FROM users WHERE id=? LIMIT 1 FOR UPDATE");
        if (!$lock) { throw new Exception('Unable to lock user wallet.'); }
        $lock->bind_param('i', $userId);
        $lock->execute();
        $lockRes = $lock->get_result();
        if (!$lockRes || $lockRes->num_rows < 1) {
            $conn->rollback();
            return array('success' => false, 'message' => 'User not found.');
        }

        $settings = wcb_deposit_bonus_settings($conn);
        if (!$settings['is_enabled']) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Deposit bonus is currently disabled.');
        }

        if (wcb_deposit_bonus_already_claimed_today($conn, $userId, 0)) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Today deposit bonus already claimed. Next deposit cycle এ আবার claim করা যাবে।');
        }

        $eligible = wcb_deposit_bonus_best_eligible($conn, $userId);
        if (empty($eligible['rule']) || empty($eligible['deposit'])) {
            $conn->rollback();
            return array('success' => false, 'message' => 'No eligible approved deposit found for today.');
        }
        $rule = $eligible['rule'];
        $deposit = $eligible['deposit'];
        if (intval($rule['id']) !== $ruleId) {
            $conn->rollback();
            return array('success' => false, 'message' => 'This bonus is not available for your current deposit cycle.');
        }

        $depositTxnId = intval($deposit['id'] ?? 0);
        $depositAmount = round((float)($deposit['amount'] ?? 0), 2);
        $depositTotal = wcb_today_approved_deposit_total($conn, $userId);
        $bonusAmount = round((float)$eligible['bonus_amount'], 2);
        $turnoverRequired = round((float)$eligible['turnover_required'], 2);
        if ($bonusAmount <= 0) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Bonus amount is not configured.');
        }

        $stmtUser = $conn->prepare("UPDATE users SET balance = balance + ?, bonus_balance = COALESCE(bonus_balance,0) + ?, turnover_target = GREATEST(COALESCE(turnover_target,0), COALESCE(turnover_completed,0)) + ? WHERE id=?");
        $stmtUser->bind_param('dddi', $bonusAmount, $bonusAmount, $turnoverRequired, $userId);
        $stmtUser->execute();
        if ($stmtUser->affected_rows < 1) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Wallet update failed.');
        }

        $note = 'Deposit bonus claimed. Deposit transaction #' . $depositTxnId . ', deposit amount: ' . wcb_money($depositAmount) . ', turnover required: ' . wcb_money($turnoverRequired);
        $stmtClaim = $conn->prepare("INSERT INTO deposit_bonus_claims (user_id, rule_id, deposit_txn_id, deposit_amount, deposit_total, bonus_amount, turnover_required, claim_date, claim_status, claim_note, claimed_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'claimed', ?, NOW())");
        if (!$stmtClaim) { throw new Exception('Claim insert failed: ' . $conn->error); }
        $stmtClaim->bind_param('iiidddds', $userId, $ruleId, $depositTxnId, $depositAmount, $depositTotal, $bonusAmount, $turnoverRequired, $note);
        $stmtClaim->execute();

        if (wcb_table_exists($conn, 'transactions_fake')) {
            $method = 'Deposit Bonus Claim';
            $stmtTx = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, status, admin_note, created_at) VALUES (?, 'bonus', ?, ?, 'approved', ?, NOW())");
            if ($stmtTx) {
                $stmtTx->bind_param('idss', $userId, $bonusAmount, $method, $note);
                $stmtTx->execute();
            }
        }

        $conn->commit();
        return array(
            'success' => true,
            'message' => 'Deposit bonus claimed successfully.',
            'amount' => $bonusAmount,
            'deposit_amount' => $depositAmount,
            'turnover_required' => $turnoverRequired,
            'rule_id' => $ruleId,
            'deposit_txn_id' => $depositTxnId
        );
    } catch (Exception $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Server error: ' . $e->getMessage());
    }
}

function wcb_daily_bonus_popup_html($conn, $userId) {
    static $printed = false;
    if ($printed) { return ''; }
    $printed = true;

    $state = wcb_daily_bonus_can_claim($conn, $userId);
    $settings = $state['settings'];
    if (empty($settings['is_enabled']) || (float)$settings['bonus_amount'] <= 0) { return ''; }

    $title = htmlspecialchars($settings['bonus_title'], ENT_QUOTES, 'UTF-8');
    $text = htmlspecialchars($settings['bonus_text'], ENT_QUOTES, 'UTF-8');
    $button = trim((string)($settings['claim_button_text'] ?? 'Claim'));
    if ($button === '') { $button = 'Claim'; }
    $button = htmlspecialchars($button, ENT_QUOTES, 'UTF-8');
    $amount = number_format((float)$settings['bonus_amount'], 2);
    $apiUrl = '/api/daily_bonus_claim.php';
    $depositUrl = '/player/deposit.php';
    $hasApprovedDeposit = wcb_user_has_approved_deposit($conn, $userId) ? 1 : 0;

    // Non-depositors see the exact same ready-to-claim popup. Their click is
    // redirected silently to the existing deposit page instead of claiming.
    $canClaim = $hasApprovedDeposit ? (!empty($state['can_claim']) ? 1 : 0) : 1;
    $secondsRemaining = $hasApprovedDeposit ? max(0, intval($state['seconds_remaining'] ?? 0)) : 0;
    $cycleSeconds = max(60, intval($settings['cycle_hours']) * 3600);

    return <<<HTML
<style id="wcbDailyBonusFloatingStyle">
#wcbDailyBonusWidget{position:fixed;right:22px;bottom:230px;width:76px;z-index:2147483000;font-family:Arial,'Hind Siliguri',sans-serif;text-align:center;pointer-events:auto;animation:wcbBonusFloat 2.8s ease-in-out infinite}#wcbDailyBonusWidget *{box-sizing:border-box}#wcbDailyBonusWidget.wcb-hide{display:none}.wcb-daily-close{position:absolute;top:-25px;left:-6px;width:24px;height:24px;border:0;border-radius:50%;background:rgba(80,84,92,.88);color:#fff;font-size:22px;line-height:20px;font-weight:300;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 10px rgba(0,0,0,.16);z-index:4;padding:0}.wcb-daily-close:hover{background:rgba(58,63,70,.96)}.wcb-daily-mini-card{position:relative;border-radius:16px;padding:0;background:transparent;filter:drop-shadow(0 7px 11px rgba(255,112,37,.18))}.wcb-daily-gift-wrap{position:relative;width:68px;height:76px;margin:0 auto}.wcb-daily-halo{position:absolute;left:4px;right:4px;top:2px;height:60px;border-radius:50%;background:radial-gradient(circle at 50% 35%,#ffd86c 0%,#ff8b3e 52%,#c1343d 100%);box-shadow:inset 0 0 0 2px rgba(255,255,255,.28),0 5px 12px rgba(255,102,45,.22)}.wcb-daily-gift{position:absolute;left:10px;right:10px;top:8px;height:40px;display:flex;align-items:center;justify-content:center;font-size:35px;line-height:1;text-shadow:0 3px 5px rgba(0,0,0,.13);transform:translateZ(0)}.wcb-daily-claim{position:absolute;left:4px;right:4px;bottom:12px;height:23px;border:0;border-radius:7px;background:linear-gradient(180deg,#ffb044 0%,#ff7043 44%,#ff3f6a 100%);color:#fff;font-size:11px;font-weight:500;letter-spacing:.05px;cursor:pointer;box-shadow:0 4px 8px rgba(239,68,68,.18);text-shadow:0 1px 1px rgba(0,0,0,.08);touch-action:manipulation;padding:0}.wcb-daily-claim:disabled{opacity:.72;cursor:not-allowed;background:linear-gradient(180deg,#ffbd70,#f08672)}.wcb-daily-amount{position:absolute;right:-4px;top:0;background:linear-gradient(180deg,#fff8b5,#ffd83f);color:#c2410c;border-radius:999px;padding:2px 5px;font-size:9px;font-weight:600;line-height:1;box-shadow:0 3px 7px rgba(234,88,12,.14);max-width:54px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.wcb-daily-timer{margin-top:1px;color:#d78be8;font-size:13px;font-weight:600;line-height:1.05;text-shadow:0 1px 0 rgba(255,255,255,.55);letter-spacing:.2px}.wcb-daily-status{display:none}.wcb-daily-pop-title{display:none}.wcb-fireworks-layer{position:fixed;inset:0;z-index:2147483001;pointer-events:none;overflow:hidden}.wcb-fire-particle{position:absolute;width:8px;height:8px;border-radius:50%;background:var(--c);left:var(--l);top:var(--t);box-shadow:0 0 10px var(--c);animation:wcbFire 1.15s cubic-bezier(.16,.75,.4,1) forwards}.wcb-success-ring{position:fixed;left:50%;top:50%;width:12px;height:12px;border-radius:50%;border:3px solid rgba(255,193,7,.82);z-index:2147483001;pointer-events:none;transform:translate(-50%,-50%);animation:wcbRing 1.1s ease-out forwards}@keyframes wcbBonusFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}@keyframes wcbFire{0%{opacity:1;transform:translate(0,0) scale(1)}100%{opacity:0;transform:translate(var(--x),var(--y)) scale(.15)}}@keyframes wcbRing{0%{opacity:.95;width:12px;height:12px}100%{opacity:0;width:380px;height:380px}}@media(max-width:480px){#wcbDailyBonusWidget{right:24px;bottom:255px;width:74px}.wcb-daily-close{top:-24px;left:-4px;width:23px;height:23px;font-size:21px}.wcb-daily-gift-wrap{width:66px;height:74px}.wcb-daily-halo{left:4px;right:4px;height:58px}.wcb-daily-gift{font-size:34px;height:39px;top:8px}.wcb-daily-claim{height:22px;font-size:10.5px;bottom:12px}.wcb-daily-timer{font-size:12.5px}.wcb-daily-amount{font-size:8.5px;max-width:52px}}@media(max-width:360px){#wcbDailyBonusWidget{right:18px;bottom:235px;width:70px}.wcb-daily-gift-wrap{width:62px;height:70px}.wcb-daily-halo{height:54px}.wcb-daily-gift{font-size:31px;height:37px}.wcb-daily-claim{height:21px;font-size:10px}.wcb-daily-timer{font-size:12px}.wcb-daily-amount{font-size:8px;max-width:48px}}
</style>
<div id="wcbDailyBonusWidget" data-can-claim="{$canClaim}" data-seconds="{$secondsRemaining}" data-cycle="{$cycleSeconds}" data-deposit-qualified="{$hasApprovedDeposit}" data-title="{$title}" data-text="{$text}">
  <button type="button" class="wcb-daily-close" id="wcbDailyBonusClose" aria-label="Close">×</button>
  <div class="wcb-daily-mini-card">
    <div class="wcb-daily-gift-wrap">
      <div class="wcb-daily-halo"></div>
      <div class="wcb-daily-gift">🎁</div>
      <div class="wcb-daily-amount">৳ {$amount}</div>
      <button type="button" id="wcbDailyBonusBtn" class="wcb-daily-claim">{$button}</button>
    </div>
    <div id="wcbDailyBonusTimer" class="wcb-daily-timer">00:00:00</div>
    <div id="wcbDailyBonusStatus" class="wcb-daily-status"></div>
  </div>
</div>
<script>
(function(){
  if(window.wcbDailyBonusFloatingBound) return;
  window.wcbDailyBonusFloatingBound = true;
  var widget = document.getElementById('wcbDailyBonusWidget');
  if(!widget) return;
  try{
    if(sessionStorage.getItem('wcbDailyBonusClosed') === '1'){
      widget.classList.add('wcb-hide');
      return;
    }
  }catch(e){}
  var btn = document.getElementById('wcbDailyBonusBtn');
  var closeBtn = document.getElementById('wcbDailyBonusClose');
  var timer = document.getElementById('wcbDailyBonusTimer');
  var status = document.getElementById('wcbDailyBonusStatus');
  var canClaim = widget.getAttribute('data-can-claim') === '1';
  var depositQualified = widget.getAttribute('data-deposit-qualified') === '1';
  var remaining = parseInt(widget.getAttribute('data-seconds') || '0', 10);
  var cycle = parseInt(widget.getAttribute('data-cycle') || '86400', 10);
  var defaultText = btn ? btn.textContent : 'Claim';
  var intervalId = null;
  function pad(n){return n < 10 ? '0' + n : '' + n;}
  function format(sec){
    sec = Math.max(0, parseInt(sec || 0, 10));
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    return pad(h) + ':' + pad(m) + ':' + pad(s);
  }
  function render(){
    if(!timer || !btn) return;
    if(canClaim){
      timer.textContent = '00:00:00';
      btn.disabled = false;
      btn.textContent = defaultText;
      if(status) status.textContent = 'Ready to claim';
    }else{
      timer.textContent = format(remaining);
      btn.disabled = true;
      btn.textContent = 'Claim';
      if(status) status.textContent = 'Next bonus available';
    }
  }
  function startCountdown(){
    if(intervalId) clearInterval(intervalId);
    intervalId = setInterval(function(){
      if(!canClaim && remaining > 0){
        remaining--;
        render();
      }
      if(!canClaim && remaining <= 0){
        canClaim = true;
        render();
        clearInterval(intervalId);
      }
    }, 1000);
  }
  function celebrate(){
    var layer = document.createElement('div');
    layer.className = 'wcb-fireworks-layer';
    document.body.appendChild(layer);
    var colors = ['#ff3157','#ffc107','#20c997','#38bdf8','#a855f7','#fb923c','#ffffff'];
    for(var i=0;i<86;i++){
      var p = document.createElement('span');
      p.className = 'wcb-fire-particle';
      var left = 12 + Math.random() * 76;
      var top = 12 + Math.random() * 66;
      var angle = Math.random() * Math.PI * 2;
      var distance = 70 + Math.random() * 150;
      p.style.setProperty('--l', left + 'vw');
      p.style.setProperty('--t', top + 'vh');
      p.style.setProperty('--x', Math.cos(angle) * distance + 'px');
      p.style.setProperty('--y', Math.sin(angle) * distance + 'px');
      p.style.setProperty('--c', colors[Math.floor(Math.random() * colors.length)]);
      p.style.animationDelay = (Math.random() * .28) + 's';
      layer.appendChild(p);
    }
    var ring = document.createElement('div');
    ring.className = 'wcb-success-ring';
    document.body.appendChild(ring);
    setTimeout(function(){ if(layer && layer.parentNode) layer.parentNode.removeChild(layer); if(ring && ring.parentNode) ring.parentNode.removeChild(ring); }, 1600);
  }
  if(closeBtn){
    closeBtn.addEventListener('click', function(){
      try{sessionStorage.setItem('wcbDailyBonusClosed','1');}catch(e){}
      widget.classList.add('wcb-hide');
    });
  }
  if(btn){
    btn.addEventListener('click', function(){
      if(!depositQualified){
        window.location.href = '{$depositUrl}';
        return;
      }
      if(!canClaim || btn.disabled) return;
      btn.disabled = true;
      btn.textContent = '...';
      if(status) status.textContent = 'Claiming bonus';
      fetch('{$apiUrl}', {method:'POST', credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json();})
        .then(function(data){
          if(data && data.success){
            celebrate();
            canClaim = false;
            remaining = cycle;
            btn.textContent = 'Claimed';
            if(status) status.textContent = 'Bonus claimed successfully';
            render();
            startCountdown();
          }else if(data && data.redirect){
            window.location.href = data.redirect;
          }else{
            if(status) status.textContent = (data && data.message) ? data.message : 'Claim failed';
            btn.disabled = false;
            btn.textContent = defaultText;
          }
        })
        .catch(function(){
          if(status) status.textContent = 'Network error';
          btn.disabled = false;
          btn.textContent = defaultText;
        });
    });
  }
  render();
  if(!canClaim && remaining > 0) startCountdown();
})();
</script>
HTML;
}
?>
