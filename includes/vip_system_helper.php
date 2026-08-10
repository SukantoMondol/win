<?php
if (!function_exists('wcb_vip_table_exists')) {
    function wcb_vip_table_exists($conn, $table) {
        $table = $conn->real_escape_string($table);
        $q = @$conn->query("SHOW TABLES LIKE '$table'");
        return $q && $q->num_rows > 0;
    }
}

if (!function_exists('wcb_vip_column_exists')) {
    function wcb_vip_column_exists($conn, $table, $column) {
        $table = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);
        $q = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $q && $q->num_rows > 0;
    }
}

if (!function_exists('wcb_vip_ensure_schema')) {
    function wcb_vip_ensure_schema($conn) {
        if (!$conn || !empty($conn->connect_error)) { return false; }

        @$conn->query("CREATE TABLE IF NOT EXISTS vip_settings (
            id INT NOT NULL PRIMARY KEY,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            xp_source ENUM('turnover','deposit','both') NOT NULL DEFAULT 'turnover',
            xp_per_amount DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
            vp_per_amount DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
            conversion_ratio DECIMAL(12,2) NOT NULL DEFAULT 60.00,
            min_convert_points INT NOT NULL DEFAULT 10,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        @$conn->query("INSERT IGNORE INTO vip_settings (id, is_enabled, xp_source, xp_per_amount, vp_per_amount, conversion_ratio, min_convert_points) VALUES (1, 1, 'turnover', 1.0000, 1.0000, 60.00, 10)");

        @$conn->query("CREATE TABLE IF NOT EXISTS vip_levels (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            level_name VARCHAR(80) NOT NULL,
            required_xp DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            reward_amount DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_active_required (is_active, required_xp),
            KEY idx_sort_order (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $level_count = 0;
        $lc = @$conn->query("SELECT COUNT(*) AS c FROM vip_levels");
        if ($lc && $row = $lc->fetch_assoc()) { $level_count = intval($row['c']); }
        if ($level_count <= 0) {
            $defaults = array(
                array('NORMAL', 0, 0, 1),
                array('ACE', 10000, 0, 2),
                array('BRONZE', 50000, 0, 3),
                array('SILVER', 100000, 0, 4),
                array('GOLD', 250000, 0, 5),
                array('DIAMOND', 500000, 0, 6)
            );
            $stmt = $conn->prepare("INSERT INTO vip_levels (level_name, required_xp, reward_amount, is_active, sort_order) VALUES (?, ?, ?, 1, ?)");
            if ($stmt) {
                foreach ($defaults as $d) {
                    $name = $d[0]; $xp = (float)$d[1]; $reward = (float)$d[2]; $sort = (int)$d[3];
                    $stmt->bind_param('sddi', $name, $xp, $reward, $sort);
                    $stmt->execute();
                }
                $stmt->close();
            }
        }

        @$conn->query("CREATE TABLE IF NOT EXISTS vip_conversions (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            points INT NOT NULL DEFAULT 0,
            real_amount DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            ratio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            status ENUM('completed','rejected') NOT NULL DEFAULT 'completed',
            balance_before DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            balance_after DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        @$conn->query("CREATE TABLE IF NOT EXISTS vip_point_adjustments (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            points INT NOT NULL DEFAULT 0,
            admin_id INT NULL DEFAULT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        return true;
    }
}

if (!function_exists('wcb_vip_settings')) {
    function wcb_vip_settings($conn) {
        wcb_vip_ensure_schema($conn);
        $defaults = array(
            'id' => 1,
            'is_enabled' => 1,
            'xp_source' => 'turnover',
            'xp_per_amount' => 1.0000,
            'vp_per_amount' => 1.0000,
            'conversion_ratio' => 60.00,
            'min_convert_points' => 10
        );
        $q = @$conn->query("SELECT * FROM vip_settings WHERE id=1 LIMIT 1");
        if ($q && $q->num_rows > 0) { return array_merge($defaults, $q->fetch_assoc()); }
        return $defaults;
    }
}

if (!function_exists('wcb_vip_levels')) {
    function wcb_vip_levels($conn, $active_only = true) {
        wcb_vip_ensure_schema($conn);
        $where = $active_only ? "WHERE is_active=1" : "";
        $q = @$conn->query("SELECT * FROM vip_levels $where ORDER BY required_xp ASC, sort_order ASC, id ASC");
        $rows = array();
        if ($q) { while ($r = $q->fetch_assoc()) { $rows[] = $r; } }
        return $rows;
    }
}

if (!function_exists('wcb_vip_user')) {
    function wcb_vip_user($conn, $user_id) {
        $user_id = intval($user_id);
        $q = @$conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1");
        return ($q && $q->num_rows > 0) ? $q->fetch_assoc() : array();
    }
}

if (!function_exists('wcb_vip_sum_value')) {
    function wcb_vip_sum_value($conn, $sql, $types = '', $params = array()) {
        if (!$conn || !empty($conn->connect_error)) { return 0.00; }
        $stmt = $conn->prepare($sql);
        if (!$stmt) { return 0.00; }
        if ($types !== '' && !empty($params)) {
            $bind = array($types);
            foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
            call_user_func_array(array($stmt, 'bind_param'), $bind);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $value = 0.00;
        if ($res && $row = $res->fetch_assoc()) { $value = (float)($row['total'] ?? 0); }
        $stmt->close();
        return $value;
    }
}

if (!function_exists('wcb_vip_user_totals')) {
    function wcb_vip_user_totals($conn, $user_id) {
        $user_id = intval($user_id);
        $deposit_total = 0.00;
        $bet_total = 0.00;
        $turnover_completed = 0.00;
        $converted_points = 0;
        $adjusted_points = 0;

        if (wcb_vip_table_exists($conn, 'transactions_fake')) {
            $deposit_total = wcb_vip_sum_value($conn, "SELECT COALESCE(SUM(amount),0) AS total FROM transactions_fake WHERE user_id=? AND type='deposit' AND status='approved'", 'i', array($user_id));
        }
        if (wcb_vip_table_exists($conn, 'game_bet_history')) {
            $bet_total = wcb_vip_sum_value($conn, "SELECT COALESCE(SUM(amount),0) AS total FROM game_bet_history WHERE user_id=? AND type='bet'", 'i', array($user_id));
        }
        if (wcb_vip_column_exists($conn, 'users', 'turnover_completed')) {
            $turnover_completed = wcb_vip_sum_value($conn, "SELECT COALESCE(turnover_completed,0) AS total FROM users WHERE id=?", 'i', array($user_id));
        }
        if (wcb_vip_table_exists($conn, 'vip_conversions')) {
            $converted_points = (int)wcb_vip_sum_value($conn, "SELECT COALESCE(SUM(points),0) AS total FROM vip_conversions WHERE user_id=? AND status='completed'", 'i', array($user_id));
        }
        if (wcb_vip_table_exists($conn, 'vip_point_adjustments')) {
            $adjusted_points = (int)wcb_vip_sum_value($conn, "SELECT COALESCE(SUM(points),0) AS total FROM vip_point_adjustments WHERE user_id=?", 'i', array($user_id));
        }

        return array(
            'deposit_total' => $deposit_total,
            'bet_total' => $bet_total,
            'turnover_completed' => $turnover_completed,
            'converted_points' => $converted_points,
            'adjusted_points' => $adjusted_points
        );
    }
}

if (!function_exists('wcb_vip_state')) {
    function wcb_vip_state($conn, $user_id) {
        $settings = wcb_vip_settings($conn);
        $levels = wcb_vip_levels($conn, true);
        $user = wcb_vip_user($conn, $user_id);
        $totals = wcb_vip_user_totals($conn, $user_id);

        $turnover_source = max((float)$totals['bet_total'], (float)$totals['turnover_completed']);
        $source = $settings['xp_source'] ?? 'turnover';
        if ($source === 'deposit') { $base = (float)$totals['deposit_total']; }
        elseif ($source === 'both') { $base = (float)$totals['deposit_total'] + $turnover_source; }
        else { $base = $turnover_source; }

        $xp = max(0, floor($base * (float)($settings['xp_per_amount'] ?? 1)));
        $earned_vp = max(0, floor($base * (float)($settings['vp_per_amount'] ?? 1)));
        $available_vp = max(0, $earned_vp + (int)$totals['adjusted_points'] - (int)$totals['converted_points']);

        $current = array('level_name' => 'NORMAL', 'required_xp' => 0, 'reward_amount' => 0);
        $next = null;
        foreach ($levels as $level) {
            if ($xp >= (float)$level['required_xp']) { $current = $level; }
            elseif ($next === null) { $next = $level; break; }
        }

        $target = $next ? (float)$next['required_xp'] : max((float)$current['required_xp'], $xp);
        $current_req = (float)($current['required_xp'] ?? 0);
        $range = max(1, $target - $current_req);
        $progress = $next ? (($xp - $current_req) / $range) * 100 : 100;
        $progress = max(0, min(100, $progress));
        $need = $next ? max(0, $target - $xp) : 0;
        $ratio = max(1, (float)($settings['conversion_ratio'] ?? 60));

        return array(
            'settings' => $settings,
            'levels' => $levels,
            'user' => $user,
            'totals' => $totals,
            'xp' => $xp,
            'earned_vp' => $earned_vp,
            'available_vp' => $available_vp,
            'converted_points' => (int)$totals['converted_points'],
            'current_level' => $current,
            'next_level' => $next,
            'progress_percent' => $progress,
            'target_xp' => $target,
            'need_xp' => $need,
            'convert_ratio' => $ratio,
            'min_convert_points' => max(1, (int)($settings['min_convert_points'] ?? 10)),
            'real_money_available' => floor(($available_vp / $ratio) * 100) / 100
        );
    }
}

if (!function_exists('wcb_vip_recent_conversions')) {
    function wcb_vip_recent_conversions($conn, $user_id = 0, $limit = 20) {
        wcb_vip_ensure_schema($conn);
        $limit = max(1, min(100, intval($limit)));
        $rows = array();
        if ($user_id > 0) {
            $stmt = $conn->prepare("SELECT vc.*, u.username, u.phone FROM vip_conversions vc LEFT JOIN users u ON u.id=vc.user_id WHERE vc.user_id=? ORDER BY vc.id DESC LIMIT $limit");
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
                $stmt->close();
            }
        } else {
            $q = @$conn->query("SELECT vc.*, u.username, u.phone FROM vip_conversions vc LEFT JOIN users u ON u.id=vc.user_id ORDER BY vc.id DESC LIMIT $limit");
            if ($q) { while ($r = $q->fetch_assoc()) { $rows[] = $r; } }
        }
        return $rows;
    }
}

if (!function_exists('wcb_vip_stats')) {
    function wcb_vip_stats($conn) {
        wcb_vip_ensure_schema($conn);
        $stats = array('total_conversions' => 0, 'total_points' => 0, 'total_paid' => 0.00, 'today_conversions' => 0, 'today_paid' => 0.00, 'active_levels' => 0);
        $q = @$conn->query("SELECT COUNT(*) AS total_conversions, COALESCE(SUM(points),0) AS total_points, COALESCE(SUM(real_amount),0) AS total_paid, COALESCE(SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END),0) AS today_conversions, COALESCE(SUM(CASE WHEN DATE(created_at)=CURDATE() THEN real_amount ELSE 0 END),0) AS today_paid FROM vip_conversions WHERE status='completed'");
        if ($q && $r = $q->fetch_assoc()) { $stats = array_merge($stats, $r); }
        $l = @$conn->query("SELECT COUNT(*) AS c FROM vip_levels WHERE is_active=1");
        if ($l && $lr = $l->fetch_assoc()) { $stats['active_levels'] = intval($lr['c']); }
        return $stats;
    }
}
?>
