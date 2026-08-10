<?php
if (!function_exists('wcb_referral_table_exists')) {
    function wcb_referral_table_exists($conn, $table) {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $res = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safe) . "'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('wcb_referral_column_exists')) {
    function wcb_referral_column_exists($conn, $table, $column) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $res = @$conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '" . $conn->real_escape_string($safeColumn) . "'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('wcb_referral_index_exists')) {
    function wcb_referral_index_exists($conn, $table, $index) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', $index);
        $res = @$conn->query("SHOW INDEX FROM `$safeTable` WHERE Key_name='" . $conn->real_escape_string($safeIndex) . "'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('wcb_referral_add_column_if_missing')) {
    function wcb_referral_add_column_if_missing($conn, $table, $column, $definition) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if (wcb_referral_table_exists($conn, $safeTable) && !wcb_referral_column_exists($conn, $safeTable, $safeColumn)) {
            @$conn->query("ALTER TABLE `$safeTable` ADD COLUMN `$safeColumn` $definition");
        }
    }
}

if (!function_exists('wcb_referral_ensure_schema')) {
    function wcb_referral_ensure_schema($conn) {
        if (!$conn || $conn->connect_error) { return false; }

        wcb_referral_add_column_if_missing($conn, 'users', 'referrer_id', "INT(11) NOT NULL DEFAULT 0");
        wcb_referral_add_column_if_missing($conn, 'users', 'ref_code', "VARCHAR(50) DEFAULT NULL");
        wcb_referral_add_column_if_missing($conn, 'users', 'bonus_balance', "DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        if (wcb_referral_table_exists($conn, 'users') && !wcb_referral_index_exists($conn, 'users', 'idx_users_referrer_id')) {
            @$conn->query("ALTER TABLE `users` ADD INDEX `idx_users_referrer_id` (`referrer_id`)");
        }
        if (wcb_referral_table_exists($conn, 'users') && !wcb_referral_index_exists($conn, 'users', 'idx_users_ref_code')) {
            @$conn->query("ALTER TABLE `users` ADD INDEX `idx_users_ref_code` (`ref_code`)");
        }

        @$conn->query("CREATE TABLE IF NOT EXISTS `referral_settings` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `min_deposit_amount` DECIMAL(15,2) NOT NULL DEFAULT 100.00,
            `claim_mode` VARCHAR(20) NOT NULL DEFAULT 'auto',
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        wcb_referral_add_column_if_missing($conn, 'referral_settings', 'is_enabled', "TINYINT(1) NOT NULL DEFAULT 1");
        wcb_referral_add_column_if_missing($conn, 'referral_settings', 'min_deposit_amount', "DECIMAL(15,2) NOT NULL DEFAULT 100.00");
        wcb_referral_add_column_if_missing($conn, 'referral_settings', 'claim_mode', "VARCHAR(20) NOT NULL DEFAULT 'auto'");
        wcb_referral_add_column_if_missing($conn, 'referral_settings', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        @$conn->query("INSERT IGNORE INTO `referral_settings` (`id`, `is_enabled`, `min_deposit_amount`, `claim_mode`) VALUES (1, 1, 100.00, 'auto')");

        @$conn->query("CREATE TABLE IF NOT EXISTS `referral_level_rules` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `level_no` INT(11) NOT NULL,
            `referral_limit` INT(11) NOT NULL DEFAULT 1,
            `bonus_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_referral_level_no` (`level_no`),
            KEY `idx_referral_level_active` (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @$conn->query("CREATE TABLE IF NOT EXISTS `referral_bonus_history` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `inviter_id` INT(11) NOT NULL,
            `source_user_id` INT(11) NOT NULL DEFAULT 0,
            `level` INT(11) NOT NULL DEFAULT 1,
            `rule_id` INT(11) NOT NULL DEFAULT 0,
            `referral_limit` INT(11) NOT NULL DEFAULT 0,
            `qualified_count` INT(11) NOT NULL DEFAULT 0,
            `bonus_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `trigger_deposit_id` INT(11) DEFAULT NULL,
            `trigger_deposit_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `status` VARCHAR(30) NOT NULL DEFAULT 'credited',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `claimed_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_referral_award` (`inviter_id`, `source_user_id`, `level`),
            KEY `idx_inviter_id` (`inviter_id`),
            KEY `idx_source_user_id` (`source_user_id`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        wcb_referral_add_column_if_missing($conn, 'referral_bonus_history', 'rule_id', "INT(11) NOT NULL DEFAULT 0 AFTER `level`");
        wcb_referral_add_column_if_missing($conn, 'referral_bonus_history', 'referral_limit', "INT(11) NOT NULL DEFAULT 0 AFTER `rule_id`");
        wcb_referral_add_column_if_missing($conn, 'referral_bonus_history', 'qualified_count', "INT(11) NOT NULL DEFAULT 0 AFTER `referral_limit`");
        wcb_referral_add_column_if_missing($conn, 'referral_bonus_history', 'claimed_at', "DATETIME DEFAULT NULL AFTER `created_at`");

        $levelCount = 0;
        $res = @$conn->query("SELECT COUNT(*) AS total FROM referral_level_rules");
        if ($res && $res->num_rows > 0) { $levelCount = intval($res->fetch_assoc()['total']); }
        if ($levelCount === 0) {
            $defaults = array(
                array(1, 3, 30.00, 1),
                array(2, 7, 40.00, 2),
                array(3, 12, 50.00, 3),
                array(4, 20, 100.00, 4),
                array(5, 50, 300.00, 5),
                array(6, 100, 500.00, 6)
            );
            $stmt = $conn->prepare("INSERT IGNORE INTO referral_level_rules (level_no, referral_limit, bonus_amount, is_active, sort_order, created_at) VALUES (?, ?, ?, 1, ?, NOW())");
            if ($stmt) {
                foreach ($defaults as $d) {
                    $stmt->bind_param('iidi', $d[0], $d[1], $d[2], $d[3]);
                    @$stmt->execute();
                }
            }
        }
        return true;
    }
}

if (!function_exists('wcb_referral_settings')) {
    function wcb_referral_settings($conn) {
        wcb_referral_ensure_schema($conn);
        $defaults = array('is_enabled' => 1, 'min_deposit_amount' => 100.00, 'claim_mode' => 'auto');
        $res = @$conn->query("SELECT * FROM referral_settings WHERE id=1 LIMIT 1");
        if ($res && $res->num_rows > 0) { return array_merge($defaults, $res->fetch_assoc()); }
        return $defaults;
    }
}

if (!function_exists('wcb_referral_levels')) {
    function wcb_referral_levels($conn, $activeOnly = true) {
        wcb_referral_ensure_schema($conn);
        $where = $activeOnly ? "WHERE is_active=1" : "";
        $rows = array();
        $res = @$conn->query("SELECT * FROM referral_level_rules $where ORDER BY sort_order ASC, level_no ASC, id ASC");
        if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; } }
        return $rows;
    }
}

if (!function_exists('wcb_referral_level_rule')) {
    function wcb_referral_level_rule($conn, $level) {
        wcb_referral_ensure_schema($conn);
        $level = intval($level);
        $stmt = $conn->prepare("SELECT * FROM referral_level_rules WHERE level_no=? AND is_active=1 LIMIT 1");
        if (!$stmt) { return null; }
        $stmt->bind_param('i', $level);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) { return $res->fetch_assoc(); }
        return null;
    }
}

if (!function_exists('wcb_referral_code')) {
    function wcb_referral_code($conn, $user_id) {
        wcb_referral_ensure_schema($conn);
        $user_id = intval($user_id);
        $res = @$conn->query("SELECT ref_code FROM users WHERE id=$user_id LIMIT 1");
        $code = '';
        if ($res && $res->num_rows > 0) { $code = trim((string)($res->fetch_assoc()['ref_code'] ?? '')); }
        if ($code === '') {
            do {
                $code = 'REF' . $user_id . strtoupper(substr(md5($user_id . microtime(true) . mt_rand()), 0, 10));
                $safe = $conn->real_escape_string($code);
                $check = @$conn->query("SELECT id FROM users WHERE ref_code='$safe' AND id<>$user_id LIMIT 1");
            } while ($check && $check->num_rows > 0);
            $safe = $conn->real_escape_string($code);
            @$conn->query("UPDATE users SET ref_code='$safe' WHERE id=$user_id");
        }
        return $code;
    }
}

if (!function_exists('wcb_referral_resolve_code')) {
    function wcb_referral_resolve_code($conn, $code) {
        wcb_referral_ensure_schema($conn);
        $code = trim((string)$code);
        if ($code === '') { return 0; }
        $stmt = $conn->prepare("SELECT id FROM users WHERE ref_code=? AND role='player' LIMIT 1");
        if (!$stmt) { return 0; }
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) { return intval($res->fetch_assoc()['id']); }
        return 0;
    }
}

if (!function_exists('wcb_referral_first_approved_deposit')) {
    function wcb_referral_first_approved_deposit($conn, $user_id) {
        if (!wcb_referral_table_exists($conn, 'transactions_fake')) { return null; }
        $user_id = intval($user_id);
        $res = @$conn->query("SELECT id, amount FROM transactions_fake WHERE user_id=$user_id AND type='deposit' AND status='approved' ORDER BY created_at ASC, id ASC LIMIT 1");
        if ($res && $res->num_rows > 0) { return $res->fetch_assoc(); }
        return null;
    }
}

if (!function_exists('wcb_referral_descendant_ids_at_level')) {
    function wcb_referral_descendant_ids_at_level($conn, $user_id, $level) {
        $user_id = intval($user_id);
        $level = max(1, intval($level));
        $current = array($user_id);
        for ($depth = 1; $depth <= $level; $depth++) {
            if (empty($current)) { return array(); }
            $next = array();
            foreach (array_chunk($current, 500) as $chunk) {
                $ids = implode(',', array_map('intval', $chunk));
                if ($ids === '') { continue; }
                $res = @$conn->query("SELECT id FROM users WHERE referrer_id IN ($ids)");
                if ($res) { while ($row = $res->fetch_assoc()) { $next[] = intval($row['id']); } }
            }
            $current = array_values(array_unique($next));
        }
        return $current;
    }
}

if (!function_exists('wcb_referral_qualified_count')) {
    function wcb_referral_qualified_count($conn, $user_id, $level, $min_deposit) {
        if (!wcb_referral_table_exists($conn, 'transactions_fake')) { return 0; }
        $ids = wcb_referral_descendant_ids_at_level($conn, $user_id, $level);
        if (empty($ids)) { return 0; }
        $min_deposit = round((float)$min_deposit, 2);
        $total = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $list = implode(',', array_map('intval', $chunk));
            if ($list === '') { continue; }
            $sql = "SELECT COUNT(*) AS total FROM users u INNER JOIN transactions_fake t ON t.id=(SELECT t2.id FROM transactions_fake t2 WHERE t2.user_id=u.id AND t2.type='deposit' AND t2.status='approved' ORDER BY t2.created_at ASC, t2.id ASC LIMIT 1) WHERE u.id IN ($list) AND t.amount >= $min_deposit";
            $res = @$conn->query($sql);
            if ($res && $res->num_rows > 0) { $total += intval($res->fetch_assoc()['total']); }
        }
        return $total;
    }
}

if (!function_exists('wcb_referral_credit_history')) {
    function wcb_referral_credit_history($conn, $history_id) {
        $history_id = intval($history_id);
        if ($history_id <= 0) { return array('success' => false, 'message' => 'Invalid request.'); }
        $stmt = $conn->prepare("SELECT * FROM referral_bonus_history WHERE id=? LIMIT 1");
        if (!$stmt) { return array('success' => false, 'message' => 'Request failed.'); }
        $stmt->bind_param('i', $history_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { return array('success' => false, 'message' => 'Bonus not found.'); }
        $row = $res->fetch_assoc();
        if ($row['status'] === 'credited') { return array('success' => true, 'message' => 'Already credited.', 'amount' => (float)$row['bonus_amount']); }
        if ($row['status'] !== 'pending') { return array('success' => false, 'message' => 'Bonus is not claimable.'); }
        $amount = round((float)$row['bonus_amount'], 2);
        $user_id = intval($row['inviter_id']);
        if ($amount <= 0 || $user_id <= 0) { return array('success' => false, 'message' => 'Invalid bonus amount.'); }
        $conn->begin_transaction();
        try {
            $stmtBal = $conn->prepare("UPDATE users SET balance=balance+? WHERE id=?");
            if (!$stmtBal) { throw new Exception('Balance update failed.'); }
            $stmtBal->bind_param('di', $amount, $user_id);
            if (!$stmtBal->execute()) { throw new Exception('Balance update failed.'); }
            $stmtHist = $conn->prepare("UPDATE referral_bonus_history SET status='credited', claimed_at=NOW() WHERE id=? AND status='pending'");
            if (!$stmtHist) { throw new Exception('Claim update failed.'); }
            $stmtHist->bind_param('i', $history_id);
            if (!$stmtHist->execute() || $stmtHist->affected_rows <= 0) { throw new Exception('Already processed.'); }
            if (wcb_referral_table_exists($conn, 'transactions_fake')) {
                $method = 'Referral Level ' . intval($row['level']) . ' Bonus';
                $note = 'Referral milestone bonus';
                $approved = 'approved';
                $stmtTx = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, status, admin_note, created_at) VALUES (?, 'bonus', ?, ?, ?, ?, NOW())");
                if ($stmtTx) {
                    $stmtTx->bind_param('idsss', $user_id, $amount, $method, $approved, $note);
                    @$stmtTx->execute();
                }
            }
            if (wcb_referral_table_exists($conn, 'notifications')) {
                $title = 'Referral Bonus';
                $msg = 'Referral Level ' . intval($row['level']) . ' milestone bonus credited: ৳' . number_format($amount, 2);
                $type = 'success';
                $stmtNt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmtNt) {
                    $stmtNt->bind_param('isss', $user_id, $title, $msg, $type);
                    @$stmtNt->execute();
                }
            }
            $conn->commit();
            return array('success' => true, 'message' => 'Bonus credited.', 'amount' => $amount);
        } catch (Exception $e) {
            $conn->rollback();
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
}

if (!function_exists('wcb_referral_sync_user_level')) {
    function wcb_referral_sync_user_level($conn, $user_id, $level_no) {
        wcb_referral_ensure_schema($conn);
        $settings = wcb_referral_settings($conn);
        if (intval($settings['is_enabled']) !== 1) { return null; }
        $rule = wcb_referral_level_rule($conn, $level_no);
        if (!$rule) { return null; }
        $user_id = intval($user_id);
        $level_no = intval($level_no);
        $required = max(1, intval($rule['referral_limit']));
        $qualified = wcb_referral_qualified_count($conn, $user_id, $level_no, (float)$settings['min_deposit_amount']);
        $existing = null;
        $stmtEx = $conn->prepare("SELECT * FROM referral_bonus_history WHERE inviter_id=? AND source_user_id=0 AND level=? LIMIT 1");
        if ($stmtEx) {
            $stmtEx->bind_param('ii', $user_id, $level_no);
            $stmtEx->execute();
            $exRes = $stmtEx->get_result();
            if ($exRes && $exRes->num_rows > 0) { $existing = $exRes->fetch_assoc(); }
        }
        if ($existing) {
            $rid = intval($existing['id']);
            @$conn->query("UPDATE referral_bonus_history SET qualified_count=" . intval($qualified) . ", referral_limit=" . intval($required) . ", rule_id=" . intval($rule['id']) . " WHERE id=$rid");
            if (($existing['status'] ?? '') === 'pending' && ($settings['claim_mode'] ?? 'auto') === 'auto') {
                wcb_referral_credit_history($conn, $rid);
                $refresh = @$conn->query("SELECT * FROM referral_bonus_history WHERE id=$rid LIMIT 1");
                if ($refresh && $refresh->num_rows > 0) { return $refresh->fetch_assoc(); }
            }
            $existing['qualified_count'] = $qualified;
            $existing['referral_limit'] = $required;
            $existing['rule_id'] = intval($rule['id']);
            return $existing;
        }
        if ($qualified < $required) { return null; }
        $bonus = round((float)$rule['bonus_amount'], 2);
        if ($bonus <= 0) { return null; }
        $status = 'pending';
        $claimed_at = null;
        $trigger_id = 0;
        $trigger_amount = 0.00;
        $stmtIn = $conn->prepare("INSERT IGNORE INTO referral_bonus_history (inviter_id, source_user_id, level, rule_id, referral_limit, qualified_count, bonus_amount, trigger_deposit_id, trigger_deposit_amount, status, created_at, claimed_at) VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        if (!$stmtIn) { return null; }
        $stmtIn->bind_param('iiiiididss', $user_id, $level_no, $rule['id'], $required, $qualified, $bonus, $trigger_id, $trigger_amount, $status, $claimed_at);
        $stmtIn->execute();
        if ($stmtIn->affected_rows <= 0) { return null; }
        $history_id = intval($conn->insert_id);
        if ($status === 'credited') { wcb_referral_credit_history($conn, $history_id); }
        $stmtNew = $conn->prepare("SELECT * FROM referral_bonus_history WHERE id=? LIMIT 1");
        if ($stmtNew) {
            $stmtNew->bind_param('i', $history_id);
            $stmtNew->execute();
            $newRes = $stmtNew->get_result();
            if ($newRes && $newRes->num_rows > 0) { return $newRes->fetch_assoc(); }
        }
        return null;
    }
}

if (!function_exists('wcb_referral_sync_user_milestones')) {
    function wcb_referral_sync_user_milestones($conn, $user_id) {
        $levels = wcb_referral_levels($conn, true);
        $rows = array();
        foreach ($levels as $lv) {
            $row = wcb_referral_sync_user_level($conn, $user_id, intval($lv['level_no']));
            if ($row) { $rows[] = $row; }
        }
        return $rows;
    }
}

if (!function_exists('wcb_referral_award_for_deposit')) {
    function wcb_referral_award_for_deposit($conn, $source_user_id, $deposit_txn_id, $deposit_amount) {
        wcb_referral_ensure_schema($conn);
        $settings = wcb_referral_settings($conn);
        if (intval($settings['is_enabled']) !== 1) { return array('success' => false, 'awarded' => 0); }
        $source_user_id = intval($source_user_id);
        $deposit_txn_id = intval($deposit_txn_id);
        $deposit_amount = round((float)$deposit_amount, 2);
        $min = round((float)$settings['min_deposit_amount'], 2);
        if ($source_user_id <= 0 || $deposit_amount < $min) { return array('success' => false, 'awarded' => 0); }
        $first = wcb_referral_first_approved_deposit($conn, $source_user_id);
        if ($first) {
            $firstId = intval($first['id']);
            $firstAmount = round((float)$first['amount'], 2);
            if ($deposit_txn_id > 0 && $firstId !== $deposit_txn_id) { return array('success' => true, 'awarded' => 0); }
            if ($firstAmount < $min) { return array('success' => true, 'awarded' => 0); }
        }
        $stmtSrc = $conn->prepare("SELECT id, referrer_id FROM users WHERE id=? LIMIT 1");
        if (!$stmtSrc) { return array('success' => false, 'awarded' => 0); }
        $stmtSrc->bind_param('i', $source_user_id);
        $stmtSrc->execute();
        $srcRes = $stmtSrc->get_result();
        if (!$srcRes || $srcRes->num_rows === 0) { return array('success' => false, 'awarded' => 0); }
        $upline_id = intval($srcRes->fetch_assoc()['referrer_id'] ?? 0);
        if ($upline_id <= 0 || $upline_id === $source_user_id) { return array('success' => true, 'awarded' => 0); }
        $activeLevels = wcb_referral_levels($conn, true);
        $levelMap = array();
        $maxDepth = 0;
        foreach ($activeLevels as $lv) { $levelMap[intval($lv['level_no'])] = true; $maxDepth = max($maxDepth, intval($lv['level_no'])); }
        $created = 0;
        for ($level = 1; $level <= $maxDepth && $upline_id > 0; $level++) {
            if (isset($levelMap[$level])) {
                $before = 0;
                $ex = @$conn->query("SELECT COUNT(*) AS total FROM referral_bonus_history WHERE inviter_id=$upline_id AND source_user_id=0 AND level=$level");
                if ($ex && $ex->num_rows > 0) { $before = intval($ex->fetch_assoc()['total']); }
                wcb_referral_sync_user_level($conn, $upline_id, $level);
                $after = 0;
                $ex2 = @$conn->query("SELECT COUNT(*) AS total FROM referral_bonus_history WHERE inviter_id=$upline_id AND source_user_id=0 AND level=$level");
                if ($ex2 && $ex2->num_rows > 0) { $after = intval($ex2->fetch_assoc()['total']); }
                if ($after > $before) { $created++; }
            }
            $next_id = 0;
            $stmtUp = $conn->prepare("SELECT referrer_id FROM users WHERE id=? LIMIT 1");
            if ($stmtUp) {
                $stmtUp->bind_param('i', $upline_id);
                $stmtUp->execute();
                $upRes = $stmtUp->get_result();
                if ($upRes && $upRes->num_rows > 0) { $next_id = intval($upRes->fetch_assoc()['referrer_id'] ?? 0); }
            }
            if ($next_id === $upline_id || $next_id === $source_user_id) { $next_id = 0; }
            $upline_id = $next_id;
        }
        return array('success' => true, 'awarded' => $created);
    }
}

if (!function_exists('wcb_referral_run_pending_awards')) {
    function wcb_referral_run_pending_awards($conn, $limit = 500) {
        wcb_referral_ensure_schema($conn);
        $settings = wcb_referral_settings($conn);
        if (intval($settings['is_enabled']) !== 1) { return 0; }
        $limit = max(1, min(5000, intval($limit)));
        $res = @$conn->query("SELECT id FROM users WHERE id IN (SELECT DISTINCT referrer_id FROM users WHERE COALESCE(referrer_id,0)>0) ORDER BY id ASC LIMIT $limit");
        $count = 0;
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $before = 0;
                $user_id = intval($row['id']);
                $b = @$conn->query("SELECT COUNT(*) AS total FROM referral_bonus_history WHERE inviter_id=$user_id AND source_user_id=0");
                if ($b && $b->num_rows > 0) { $before = intval($b->fetch_assoc()['total']); }
                wcb_referral_sync_user_milestones($conn, $user_id);
                $after = 0;
                $a = @$conn->query("SELECT COUNT(*) AS total FROM referral_bonus_history WHERE inviter_id=$user_id AND source_user_id=0");
                if ($a && $a->num_rows > 0) { $after = intval($a->fetch_assoc()['total']); }
                $count += max(0, $after - $before);
            }
        }
        return $count;
    }
}

if (!function_exists('wcb_referral_claim_level')) {
    function wcb_referral_claim_level($conn, $user_id, $level_no) {
        wcb_referral_ensure_schema($conn);
        $user_id = intval($user_id);
        $level_no = intval($level_no);
        if ($user_id <= 0 || $level_no <= 0) { return array('success' => false, 'message' => 'Invalid request.'); }
        wcb_referral_sync_user_level($conn, $user_id, $level_no);
        $stmt = $conn->prepare("SELECT id, status FROM referral_bonus_history WHERE inviter_id=? AND source_user_id=0 AND level=? LIMIT 1");
        if (!$stmt) { return array('success' => false, 'message' => 'Claim failed.'); }
        $stmt->bind_param('ii', $user_id, $level_no);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { return array('success' => false, 'message' => 'Required referral count is not completed.'); }
        $row = $res->fetch_assoc();
        if ($row['status'] === 'credited') { return array('success' => true, 'message' => 'Already credited.'); }
        return wcb_referral_credit_history($conn, intval($row['id']));
    }
}

if (!function_exists('wcb_referral_stats')) {
    function wcb_referral_stats($conn, $user_id) {
        wcb_referral_ensure_schema($conn);
        $user_id = intval($user_id);
        wcb_referral_sync_user_milestones($conn, $user_id);
        $settings = wcb_referral_settings($conn);
        $data = array('total_referrals' => 0, 'today_reward' => 0.00, 'yesterday_reward' => 0.00, 'total_earned' => 0.00, 'qualified_referrals' => 0);
        $res = @$conn->query("SELECT COUNT(*) AS total FROM users WHERE referrer_id=$user_id");
        if ($res && $res->num_rows > 0) { $data['total_referrals'] = intval($res->fetch_assoc()['total']); }
        $res = @$conn->query("SELECT COALESCE(SUM(bonus_amount),0) AS total FROM referral_bonus_history WHERE inviter_id=$user_id AND source_user_id=0 AND status='credited' AND DATE(COALESCE(claimed_at,created_at))=CURDATE()");
        if ($res && $res->num_rows > 0) { $data['today_reward'] = (float)$res->fetch_assoc()['total']; }
        $res = @$conn->query("SELECT COALESCE(SUM(bonus_amount),0) AS total FROM referral_bonus_history WHERE inviter_id=$user_id AND source_user_id=0 AND status='credited' AND DATE(COALESCE(claimed_at,created_at))=DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
        if ($res && $res->num_rows > 0) { $data['yesterday_reward'] = (float)$res->fetch_assoc()['total']; }
        $res = @$conn->query("SELECT COALESCE(SUM(bonus_amount),0) AS total FROM referral_bonus_history WHERE inviter_id=$user_id AND source_user_id=0 AND status='credited'");
        if ($res && $res->num_rows > 0) { $data['total_earned'] = (float)$res->fetch_assoc()['total']; }
        $data['qualified_referrals'] = wcb_referral_qualified_count($conn, $user_id, 1, (float)$settings['min_deposit_amount']);
        return $data;
    }
}

if (!function_exists('wcb_referral_level_progress')) {
    function wcb_referral_level_progress($conn, $user_id) {
        wcb_referral_ensure_schema($conn);
        $user_id = intval($user_id);
        wcb_referral_sync_user_milestones($conn, $user_id);
        $settings = wcb_referral_settings($conn);
        $levels = wcb_referral_levels($conn, true);
        $out = array();
        foreach ($levels as $level) {
            $levelNo = intval($level['level_no']);
            $required = max(1, intval($level['referral_limit']));
            $qualified = wcb_referral_qualified_count($conn, $user_id, $levelNo, (float)$settings['min_deposit_amount']);
            $record = null;
            $stmt = $conn->prepare("SELECT * FROM referral_bonus_history WHERE inviter_id=? AND source_user_id=0 AND level=? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ii', $user_id, $levelNo);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) { $record = $res->fetch_assoc(); }
            }
            $level['earned_count'] = min($required, $qualified);
            $level['qualified_count'] = $qualified;
            $level['remaining_count'] = max(0, $required - $qualified);
            $level['is_eligible'] = ($qualified >= $required) ? 1 : 0;
            $level['history_id'] = $record ? intval($record['id']) : 0;
            $level['status'] = $record ? (string)$record['status'] : 'locked';
            $level['is_completed'] = ($record && $record['status'] === 'credited') ? 1 : 0;
            $level['is_claimable'] = ($record && $record['status'] === 'pending') ? 1 : 0;
            $out[] = $level;
        }
        return $out;
    }
}

if (!function_exists('wcb_referral_history')) {
    function wcb_referral_history($conn, $user_id, $limit = 50) {
        wcb_referral_ensure_schema($conn);
        $user_id = intval($user_id);
        $limit = max(1, min(200, intval($limit)));
        $rows = array();
        $res = @$conn->query("SELECT h.*, u.username, u.phone FROM referral_bonus_history h LEFT JOIN users u ON u.id=h.source_user_id WHERE h.inviter_id=$user_id AND h.source_user_id=0 ORDER BY h.created_at DESC LIMIT $limit");
        if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
        return $rows;
    }
}

if (!function_exists('wcb_referral_direct_list')) {
    function wcb_referral_direct_list($conn, $user_id, $limit = 80) {
        wcb_referral_ensure_schema($conn);
        $settings = wcb_referral_settings($conn);
        $user_id = intval($user_id);
        $limit = max(1, min(200, intval($limit)));
        $rows = array();
        $min = round((float)$settings['min_deposit_amount'], 2);
        $sql = "SELECT u.id, u.username, u.phone, u.created_at, (SELECT COALESCE(SUM(t.amount),0) FROM transactions_fake t WHERE t.user_id=u.id AND t.type='deposit' AND t.status='approved') AS deposit_total, CASE WHEN (SELECT t2.amount FROM transactions_fake t2 WHERE t2.user_id=u.id AND t2.type='deposit' AND t2.status='approved' ORDER BY t2.created_at ASC, t2.id ASC LIMIT 1) >= $min THEN 1 ELSE 0 END AS qualified FROM users u WHERE u.referrer_id=$user_id ORDER BY u.id DESC LIMIT $limit";
        $res = @$conn->query($sql);
        if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
        return $rows;
    }
}
?>
