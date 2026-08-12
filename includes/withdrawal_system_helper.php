<?php
if (!function_exists('wcb_withdraw_table_exists')) {
    function wcb_withdraw_table_exists($conn, $table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $q = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        return $q && $q->num_rows > 0;
    }
}

if (!function_exists('wcb_withdraw_column_exists')) {
    function wcb_withdraw_column_exists($conn, $table, $column) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        $q = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
        return $q && $q->num_rows > 0;
    }
}

if (!function_exists('wcb_withdraw_add_column')) {
    function wcb_withdraw_add_column($conn, $table, $column, $definition) {
        if (!wcb_withdraw_column_exists($conn, $table, $column)) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
            $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
            @$conn->query("ALTER TABLE `$safeTable` ADD COLUMN `$safeColumn` $definition");
        }
    }
}

if (!function_exists('wcb_withdraw_ensure_schema')) {
    function wcb_withdraw_ensure_schema($conn) {
        if (!$conn || !empty($conn->connect_error)) { return false; }
        @$conn->query("CREATE TABLE IF NOT EXISTS withdrawal_methods (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            code VARCHAR(50) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_withdrawal_method_code (code),
            KEY idx_withdrawal_method_active (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $count = 0;
        $countQ = @$conn->query("SELECT COUNT(*) AS c FROM withdrawal_methods");
        if ($countQ && $row = $countQ->fetch_assoc()) { $count = intval($row['c']); }
        if ($count === 0) {
            @$conn->query("INSERT INTO withdrawal_methods (name, code, is_active, sort_order) VALUES
                ('bKash', 'bkash', 1, 1),
                ('Nagad', 'nagad', 1, 2),
                ('USDT', 'usdt', 0, 3),
                ('TRC20', 'trc20', 0, 4)");
        }
        if (wcb_withdraw_table_exists($conn, 'player_wallets')) {
            @ $conn->query("ALTER TABLE player_wallets MODIFY method VARCHAR(50) NOT NULL");
            @ $conn->query("ALTER TABLE player_wallets MODIFY wallet_number VARCHAR(120) NOT NULL");
            wcb_withdraw_add_column($conn, 'player_wallets', 'method_id', "INT NULL DEFAULT NULL AFTER user_id");
            wcb_withdraw_add_column($conn, 'player_wallets', 'withdraw_pin_hash', "VARCHAR(255) NULL DEFAULT NULL AFTER wallet_number");
            wcb_withdraw_add_column($conn, 'player_wallets', 'updated_at', "TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
            @$conn->query("UPDATE player_wallets pw JOIN withdrawal_methods wm ON (LOWER(pw.method) COLLATE utf8mb4_general_ci)=(LOWER(wm.code) COLLATE utf8mb4_general_ci) SET pw.method_id=wm.id WHERE pw.method_id IS NULL");
        }
        if (wcb_withdraw_table_exists($conn, 'transactions_fake')) {
            $statusColumn = @$conn->query("SHOW COLUMNS FROM transactions_fake LIKE 'status'");
            if ($statusColumn && ($statusRow = $statusColumn->fetch_assoc()) && strpos((string)$statusRow['Type'], "'processing'") === false) {
                @$conn->query("ALTER TABLE transactions_fake MODIFY status ENUM('pending','processing','approved','rejected') DEFAULT 'approved'");
            }
            wcb_withdraw_add_column($conn, 'transactions_fake', 'withdraw_wallet_id', "INT NULL DEFAULT NULL AFTER wallet_number");
            wcb_withdraw_add_column($conn, 'transactions_fake', 'withdraw_method_code', "VARCHAR(50) NULL DEFAULT NULL AFTER withdraw_wallet_id");
            wcb_withdraw_add_column($conn, 'transactions_fake', 'withdraw_pin_verified', "TINYINT(1) NOT NULL DEFAULT 0 AFTER withdraw_method_code");
        }
        return true;
    }
}

if (!function_exists('wcb_withdraw_methods')) {
    function wcb_withdraw_methods($conn, $activeOnly = true) {
        wcb_withdraw_ensure_schema($conn);
        $where = $activeOnly ? 'WHERE is_active=1' : '';
        $rows = array();
        $q = @$conn->query("SELECT * FROM withdrawal_methods $where ORDER BY sort_order ASC, id ASC");
        if ($q) { while ($row = $q->fetch_assoc()) { $rows[] = $row; } }
        return $rows;
    }
}

if (!function_exists('wcb_withdraw_method')) {
    function wcb_withdraw_method($conn, $id, $activeOnly = true) {
        wcb_withdraw_ensure_schema($conn);
        $id = intval($id);
        $sql = "SELECT * FROM withdrawal_methods WHERE id=?" . ($activeOnly ? " AND is_active=1" : '') . " LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { return array(); }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : array();
        $stmt->close();
        return $row;
    }
}

if (!function_exists('wcb_withdraw_wallets')) {
    function wcb_withdraw_wallets($conn, $userId) {
        wcb_withdraw_ensure_schema($conn);
        $userId = intval($userId);
        $rows = array();
        $stmt = $conn->prepare("SELECT pw.*, COALESCE(wm.name, pw.method) AS method_name, COALESCE(wm.code, pw.method) AS method_code, CASE WHEN pw.method_id IS NULL THEN 1 ELSE COALESCE(wm.is_active,0) END AS method_active FROM player_wallets pw LEFT JOIN withdrawal_methods wm ON wm.id=pw.method_id WHERE pw.user_id=? ORDER BY pw.id DESC");
        if (!$stmt) { return $rows; }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; } }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('wcb_withdraw_eligibility')) {
    function wcb_withdraw_eligibility($conn, $userId) {
        wcb_withdraw_ensure_schema($conn);
        $userId = intval($userId);
        $state = array(
            'balance' => 0.00,
            'bonus_balance' => 0.00,
            'turnover_target' => 0.00,
            'turnover_completed' => 0.00,
            'turnover_remaining' => 0.00,
            'withdrawable_balance' => 0.00,
            'allowed' => false,
            'balance_type' => 'locked'
        );
        $stmt = $conn->prepare("SELECT COALESCE(balance,0) AS balance, COALESCE(bonus_balance,0) AS bonus_balance, COALESCE(turnover_target,0) AS turnover_target, COALESCE(turnover_completed,0) AS turnover_completed FROM users WHERE id=? LIMIT 1");
        if (!$stmt) { return $state; }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $state['balance'] = round((float)$row['balance'], 2);
            $state['bonus_balance'] = round((float)$row['bonus_balance'], 2);
            $state['turnover_target'] = round((float)$row['turnover_target'], 2);
            $state['turnover_completed'] = round((float)$row['turnover_completed'], 2);
            $state['turnover_remaining'] = round(max(0, $state['turnover_target'] - $state['turnover_completed']), 2);
            $state['allowed'] = $state['turnover_remaining'] <= 0.01 && $state['balance'] > 0;
            $state['withdrawable_balance'] = $state['allowed'] ? $state['balance'] : 0.00;
            if ($state['turnover_remaining'] > 0.01) { $state['balance_type'] = 'turnover_locked'; }
            elseif ($state['balance'] <= 0) { $state['balance_type'] = 'no_balance'; }
            else { $state['balance_type'] = 'available'; }
        }
        $stmt->close();
        return $state;
    }
}


if (!function_exists('wcb_create_pending_withdraw_request')) {
    function wcb_create_pending_withdraw_request($conn, $userId, $amount, $wallet) {
        wcb_withdraw_ensure_schema($conn);
        $userId = intval($userId);
        $amount = round((float)$amount, 2);
        $walletId = intval($wallet['id'] ?? 0);
        $accountNo = trim((string)($wallet['wallet_number'] ?? ''));
        $methodCode = trim((string)($wallet['method_code'] ?? ($wallet['method'] ?? '')));
        $methodName = trim((string)($wallet['method_name'] ?? $methodCode));
        if ($userId <= 0 || $amount <= 0 || $walletId <= 0 || $accountNo === '') {
            return array('success' => false, 'message' => 'Invalid withdrawal request.');
        }
        $orderNo = function_exists('propay_make_order_no') ? propay_make_order_no('WRQ', $userId) : ('WRQ' . date('ymdHis') . $userId . mt_rand(1000,9999));
        $historyMethod = 'Pending ' . $methodName . ' (' . $accountNo . ')';
        $note = 'Waiting for admin approval.';

        $conn->begin_transaction();
        try {
            $stmtUser = $conn->prepare("SELECT balance, COALESCE(turnover_target,0) AS turnover_target, COALESCE(turnover_completed,0) AS turnover_completed FROM users WHERE id=? LIMIT 1 FOR UPDATE");
            if (!$stmtUser) { throw new RuntimeException('Unable to read user balance.'); }
            $stmtUser->bind_param('i', $userId);
            $stmtUser->execute();
            $userRes = $stmtUser->get_result();
            if (!$userRes || $userRes->num_rows === 0) { $conn->rollback(); return array('success' => false, 'message' => 'User account was not found.'); }
            $user = $userRes->fetch_assoc();
            $stmtUser->close();
            $remaining = max(0, (float)$user['turnover_target'] - (float)$user['turnover_completed']);
            if ($remaining > 0.01) { $conn->rollback(); return array('success' => false, 'message' => 'Required turnover is not complete. Remaining Turnover: ৳' . number_format($remaining, 2)); }
            if ((float)$user['balance'] + 0.0001 < $amount) { $conn->rollback(); return array('success' => false, 'message' => 'Insufficient withdrawable balance.'); }

            $stmtDeduct = $conn->prepare("UPDATE users SET balance=balance-? WHERE id=? AND balance>=?");
            if (!$stmtDeduct) { throw new RuntimeException('Unable to reserve balance.'); }
            $stmtDeduct->bind_param('did', $amount, $userId, $amount);
            $stmtDeduct->execute();
            if ($stmtDeduct->affected_rows < 1) { $stmtDeduct->close(); $conn->rollback(); return array('success' => false, 'message' => 'Insufficient withdrawable balance.'); }
            $stmtDeduct->close();

            $stmtTx = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, status, wallet_number, withdraw_wallet_id, withdraw_method_code, withdraw_pin_verified, transaction_id, order_sn, agent_id, admin_note, created_at) VALUES (?, 'withdraw', ?, ?, 'pending', ?, ?, ?, 1, ?, ?, 0, ?, NOW())");
            if (!$stmtTx) { throw new RuntimeException('Unable to create withdrawal request.'); }
            $stmtTx->bind_param('idssissss', $userId, $amount, $historyMethod, $accountNo, $walletId, $methodCode, $orderNo, $orderNo, $note);
            if (!$stmtTx->execute()) { throw new RuntimeException('Unable to create withdrawal request.'); }
            $txId = intval($stmtTx->insert_id);
            $stmtTx->close();
            $conn->commit();
            return array('success' => true, 'message' => 'Withdrawal request submitted successfully and is waiting for admin approval.', 'transaction_id' => $txId);
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Create pending withdrawal failed: ' . $e->getMessage());
            return array('success' => false, 'message' => $e->getMessage() ?: 'Unable to submit withdrawal request.');
        }
    }
}

if (!function_exists('wcb_process_pending_withdrawal')) {
    function wcb_process_pending_withdrawal($conn, $transactionId, $adminId = 0) {
        $transactionId = intval($transactionId);
        $adminId = intval($adminId);
        if ($transactionId <= 0) { return array('success' => false, 'message' => 'Invalid withdrawal request.'); }

        // Fetch transaction details
        $stmt = $conn->prepare("SELECT * FROM transactions_fake WHERE id=? AND type='withdraw' LIMIT 1");
        if (!$stmt) { return array('success' => false, 'message' => 'Database query failed.'); }
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) {
            $stmt->close();
            return array('success' => false, 'message' => 'Withdrawal request not found.');
        }
        $tx = $res->fetch_assoc();
        $stmt->close();

        if ($tx['status'] !== 'pending') {
            return array('success' => false, 'message' => 'Withdrawal request has already been processed.');
        }

        // 1. NEKpay Payout Support
        if (function_exists('nekpay_get_settings') && function_exists('nekpay_create_payout')) {
            $nekConfig = nekpay_get_settings($conn);
            if (!empty($nekConfig['is_enabled']) && !empty($nekConfig['merchant_code']) && !empty($nekConfig['secret_code'])) {
                $methodLower = strtolower(trim((string)($tx['method'] ?? '')));
                $bankCode = 'baksh';
                if (strpos($methodLower, 'nagad') !== false) { $bankCode = 'ngand'; }
                elseif (strpos($methodLower, 'rocket') !== false) { $bankCode = 'roket'; }

                $accountNumber = trim((string)($tx['wallet_number'] ?? ''));
                $amount = (float)($tx['amount'] ?? 0);
                $merchantTxId = 'PO' . date('YmdHis') . $transactionId;

                $payoutRes = nekpay_create_payout($conn, $merchantTxId, $bankCode, $accountNumber, $accountNumber, $amount);
                if (!empty($payoutRes['success'])) {
                    $note = 'Approved via NEKpay Payout (Tx: ' . $merchantTxId . ') by admin #' . $adminId;
                    $stmtUp = $conn->prepare("UPDATE transactions_fake SET status='approved', agent_id=?, admin_note=?, transaction_id=? WHERE id=? AND status='pending'");
                    if ($stmtUp) {
                        $stmtUp->bind_param('issi', $adminId, $note, $merchantTxId, $transactionId);
                        $stmtUp->execute();
                        $stmtUp->close();
                    }
                    return array('success' => true, 'message' => 'Withdrawal payout sent via NEKpay successfully!');
                } else {
                    $errMsg = !empty($payoutRes['message']) ? $payoutRes['message'] : 'NEKpay payout failed.';
                    return array('success' => false, 'message' => 'NEKpay Payout Error: ' . $errMsg);
                }
            }
        }

        // 2. LG Pay Payout Support
        if (function_exists('lgpay_ensure_schema')) { @lgpay_ensure_schema($conn); }
        if (function_exists('lgpay_is_available') && lgpay_is_available($conn, false) && function_exists('lgpay_submit_withdrawal_from_transaction')) {
            return lgpay_submit_withdrawal_from_transaction($conn, $transactionId, $adminId);
        }

        // 3. Manual Approval Fallback (If no auto-payout gateway is active)
        $note = 'Approved manually by admin #' . $adminId;
        $stmtUp = $conn->prepare("UPDATE transactions_fake SET status='approved', agent_id=?, admin_note=? WHERE id=? AND status='pending'");
        if ($stmtUp) {
            $stmtUp->bind_param('isi', $adminId, $note, $transactionId);
            $stmtUp->execute();
            $stmtUp->close();
            return array('success' => true, 'message' => 'Withdrawal request approved manually.');
        }

        return array('success' => false, 'message' => 'Unable to update withdrawal status in database.');
    }
}

if (!function_exists('wcb_reject_pending_withdrawal')) {
    function wcb_reject_pending_withdrawal($conn, $transactionId, $adminId = 0) {
        wcb_withdraw_ensure_schema($conn);
        $transactionId = intval($transactionId);
        $adminId = intval($adminId);
        if ($transactionId <= 0) { return array('success' => false, 'message' => 'Invalid withdrawal request.'); }
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, user_id, amount, status FROM transactions_fake WHERE id=? AND type='withdraw' LIMIT 1 FOR UPDATE");
            if (!$stmt) { throw new RuntimeException('Unable to read withdrawal request.'); }
            $stmt->bind_param('i', $transactionId);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$res || $res->num_rows === 0) { $conn->rollback(); return array('success' => false, 'message' => 'Withdrawal request was not found.'); }
            $tx = $res->fetch_assoc();
            $stmt->close();
            $status = (string)$tx['status'];
            if ($status === 'approved' || $status === 'rejected') { $conn->rollback(); return array('success' => false, 'message' => 'Withdrawal request was already processed.'); }
            if ($status === 'processing') { $conn->rollback(); return array('success' => false, 'message' => 'This withdrawal has already been sent to the gateway. Wait for callback/query final status.'); }
            $uid = intval($tx['user_id']);
            $amount = (float)$tx['amount'];
            $stmtRefund = $conn->prepare("UPDATE users SET balance=balance+? WHERE id=?");
            if (!$stmtRefund) { throw new RuntimeException('Unable to refund user balance.'); }
            $stmtRefund->bind_param('di', $amount, $uid);
            $stmtRefund->execute();
            $stmtRefund->close();
            $note = 'Withdrawal rejected by admin #' . $adminId . ' and reserved balance refunded.';
            $stmtUp = $conn->prepare("UPDATE transactions_fake SET status='rejected', agent_id=?, admin_note=? WHERE id=? AND status='pending'");
            if (!$stmtUp) { throw new RuntimeException('Unable to reject withdrawal.'); }
            $stmtUp->bind_param('isi', $adminId, $note, $transactionId);
            $stmtUp->execute();
            if ($stmtUp->affected_rows < 1) { throw new RuntimeException('Withdrawal request could not be rejected.'); }
            $stmtUp->close();
            $conn->commit();
            return array('success' => true, 'message' => 'Withdrawal rejected and balance refunded.');
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Reject withdrawal failed: ' . $e->getMessage());
            return array('success' => false, 'message' => $e->getMessage() ?: 'Unable to reject withdrawal.');
        }
    }
}

if (!function_exists('wcb_withdraw_add_wallet')) {
    function wcb_withdraw_add_wallet($conn, $userId, $methodId, $accountNumber, $pin) {
        wcb_withdraw_ensure_schema($conn);
        $userId = intval($userId);
        $methodId = intval($methodId);
        $accountNumber = trim((string)$accountNumber);
        $pin = trim((string)$pin);
        if (!preg_match('/^\d{4}$/', $pin)) { return array('success' => false, 'message' => 'Withdrawal PIN must be exactly 4 digits.'); }
        if (strlen($accountNumber) < 5 || strlen($accountNumber) > 120) { return array('success' => false, 'message' => 'Enter a valid account number or wallet address.'); }
        $method = wcb_withdraw_method($conn, $methodId, true);
        if (empty($method)) { return array('success' => false, 'message' => 'Selected withdrawal method is unavailable.'); }
        $stmtCount = $conn->prepare("SELECT COUNT(*) AS c FROM player_wallets WHERE user_id=?");
        $stmtCount->bind_param('i', $userId);
        $stmtCount->execute();
        $countRes = $stmtCount->get_result();
        $count = ($countRes && $row = $countRes->fetch_assoc()) ? intval($row['c']) : 0;
        $stmtCount->close();
        if ($count >= 5) { return array('success' => false, 'message' => 'You can add a maximum of 5 withdrawal accounts.'); }
        $stmtDup = $conn->prepare("SELECT id FROM player_wallets WHERE user_id=? AND method_id=? AND wallet_number=? LIMIT 1");
        $stmtDup->bind_param('iis', $userId, $methodId, $accountNumber);
        $stmtDup->execute();
        $dupRes = $stmtDup->get_result();
        if ($dupRes && $dupRes->num_rows > 0) { $stmtDup->close(); return array('success' => false, 'message' => 'This withdrawal account is already added.'); }
        $stmtDup->close();
        $hash = password_hash($pin, PASSWORD_DEFAULT);
        $code = (string)$method['code'];
        $stmt = $conn->prepare("INSERT INTO player_wallets (user_id, method_id, method, wallet_number, withdraw_pin_hash) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) { return array('success' => false, 'message' => 'Unable to save withdrawal account.'); }
        $stmt->bind_param('iisss', $userId, $methodId, $code, $accountNumber, $hash);
        $ok = $stmt->execute();
        $stmt->close();
        return array('success' => $ok, 'message' => $ok ? 'Withdrawal account saved successfully.' : 'Unable to save withdrawal account.');
    }
}

if (!function_exists('wcb_withdraw_create_request')) {
    function wcb_withdraw_create_request($conn, $userId, $amount, $walletId, $pin, $minAmount) {
        wcb_withdraw_ensure_schema($conn);
        $userId = intval($userId);
        $walletId = intval($walletId);
        $amount = round((float)$amount, 2);
        $minAmount = max(1, (float)$minAmount);
        $pin = trim((string)$pin);
        if (!preg_match('/^\d{4}$/', $pin)) { return array('success' => false, 'message' => 'Enter your 4 digit withdrawal PIN.'); }
        if ($amount < $minAmount) { return array('success' => false, 'message' => 'Minimum withdrawal amount is ৳' . number_format($minAmount, 2) . '.'); }

        $stmtWallet = $conn->prepare("SELECT * FROM player_wallets WHERE id=? AND user_id=? LIMIT 1");
        $stmtWallet->bind_param('ii', $walletId, $userId);
        $stmtWallet->execute();
        $walletRes = $stmtWallet->get_result();
        if (!$walletRes || $walletRes->num_rows === 0) { return array('success' => false, 'message' => 'Selected withdrawal account was not found.'); }
        $wallet = $walletRes->fetch_assoc();
        $stmtWallet->close();

        $method = array();
        if (intval($wallet['method_id'] ?? 0) > 0) {
            $method = wcb_withdraw_method($conn, intval($wallet['method_id']), true);
        } else {
            $methodCode = trim((string)($wallet['method'] ?? ''));
            $stmtMethod = $conn->prepare("SELECT * FROM withdrawal_methods WHERE code=? AND is_active=1 LIMIT 1");
            $stmtMethod->bind_param('s', $methodCode);
            $stmtMethod->execute();
            $methodRes = $stmtMethod->get_result();
            if ($methodRes && $methodRes->num_rows > 0) { $method = $methodRes->fetch_assoc(); }
            $stmtMethod->close();
        }
        if (empty($method)) { return array('success' => false, 'message' => 'Selected withdrawal method is disabled.'); }
        $wallet['method_name'] = $method['name'];
        $wallet['method_code'] = $method['code'];
        $pinHash = (string)($wallet['withdraw_pin_hash'] ?? '');
        if ($pinHash === '' || !password_verify($pin, $pinHash)) { return array('success' => false, 'message' => 'Incorrect withdrawal PIN.'); }
        if (function_exists('wcb_create_pending_withdraw_request')) {
            return wcb_create_pending_withdraw_request($conn, $userId, $amount, $wallet);
        }
        if (!function_exists('propay_submit_withdrawal')) { return array('success' => false, 'message' => 'Payment gateway service is unavailable.'); }
        return propay_submit_withdrawal($conn, $userId, $amount, $wallet);
    }
}
