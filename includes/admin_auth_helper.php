<?php
/**
 * Admin authentication and one-time database migration helper.
 *
 * Admin credentials live only in the dedicated `admin` table. The migration
 * preserves the existing administrator password hash, moves legacy references,
 * and removes administrator rows from `users`.
 */

if (!function_exists('wcb_admin_log_server_error')) {
    function wcb_admin_log_server_error($message) {
        error_log('[Admin Security] ' . $message);
    }
}

if (!function_exists('wcb_admin_column_exists')) {
    function wcb_admin_column_exists($conn, $table, $column) {
        $tableSafe = $conn->real_escape_string($table);
        $columnSafe = $conn->real_escape_string($column);
        $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableSafe' AND COLUMN_NAME = '$columnSafe' LIMIT 1";
        $res = @$conn->query($sql);
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('wcb_admin_table_exists')) {
    function wcb_admin_table_exists($conn, $table) {
        $tableSafe = $conn->real_escape_string($table);
        $res = @$conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableSafe' LIMIT 1");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('wcb_admin_ensure_schema')) {
    function wcb_admin_ensure_schema($conn) {
        static $completed = false;
        if ($completed) {
            return true;
        }
        if (!($conn instanceof mysqli) || $conn->connect_error) {
            return false;
        }

        $createAdmin = "CREATE TABLE IF NOT EXISTS `admin` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL DEFAULT 'Administrator',
            `email` VARCHAR(190) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
            `last_login_at` DATETIME NULL DEFAULT NULL,
            `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
            `last_password_change_at` DATETIME NULL DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_admin_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!@$conn->query($createAdmin)) {
            wcb_admin_log_server_error('Unable to create admin table: ' . $conn->error);
            return false;
        }

        // Support messages need an explicit sender source once admins no longer live in users.
        if (wcb_admin_table_exists($conn, 'support_messages')) {
            if (!wcb_admin_column_exists($conn, 'support_messages', 'sender_type')) {
                if (!@$conn->query("ALTER TABLE `support_messages` ADD COLUMN `sender_type` ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER `sender_id`")) {
                    wcb_admin_log_server_error('Unable to add support sender_type: ' . $conn->error);
                    return false;
                }
            }
            if (!wcb_admin_column_exists($conn, 'support_messages', 'sender_admin_id')) {
                if (!@$conn->query("ALTER TABLE `support_messages` ADD COLUMN `sender_admin_id` INT(11) NULL DEFAULT NULL AFTER `sender_type`")) {
                    wcb_admin_log_server_error('Unable to add support sender_admin_id: ' . $conn->error);
                    return false;
                }
            }
        }

        if (wcb_admin_table_exists($conn, 'admin_logs') && !wcb_admin_column_exists($conn, 'admin_logs', 'actor_type')) {
            if (!@$conn->query("ALTER TABLE `admin_logs` ADD COLUMN `actor_type` ENUM('admin','support') NOT NULL DEFAULT 'admin' AFTER `admin_id`")) {
                wcb_admin_log_server_error('Unable to add admin log actor_type: ' . $conn->error);
                return false;
            }
        }

        // Migrate all legacy admin rows, preserving their ids and password hashes.
        if (wcb_admin_table_exists($conn, 'users')) {
            $legacy = @$conn->query("SELECT id, username, email, password, status, created_at FROM users WHERE role='admin' ORDER BY id ASC");
            if ($legacy) {
                while ($row = $legacy->fetch_assoc()) {
                    $legacyId = (int)$row['id'];
                    $name = trim((string)$row['username']) !== '' ? trim((string)$row['username']) : 'Administrator';
                    $email = trim((string)$row['email']);
                    $passwordHash = (string)$row['password'];
                    $status = ((string)$row['status'] === 'active') ? 'active' : 'disabled';
                    $createdAt = !empty($row['created_at']) ? (string)$row['created_at'] : date('Y-m-d H:i:s');

                    if ($email === '' || $passwordHash === '') {
                        wcb_admin_log_server_error('Skipped an incomplete legacy administrator record (id ' . $legacyId . ').');
                        continue;
                    }

                    $stmt = $conn->prepare("INSERT INTO `admin` (`id`,`name`,`email`,`password`,`status`,`created_at`) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `email`=VALUES(`email`), `password`=VALUES(`password`), `status`=VALUES(`status`)");
                    if (!$stmt) {
                        wcb_admin_log_server_error('Unable to prepare admin migration: ' . $conn->error);
                        return false;
                    }
                    $stmt->bind_param('isssss', $legacyId, $name, $email, $passwordHash, $status, $createdAt);
                    $ok = $stmt->execute();
                    $stmt->close();
                    if (!$ok) {
                        wcb_admin_log_server_error('Unable to migrate administrator id ' . $legacyId . ': ' . $conn->error);
                        return false;
                    }

                    if (wcb_admin_table_exists($conn, 'support_messages')) {
                        @$conn->query("UPDATE `support_messages` SET `sender_type`='admin', `sender_admin_id`=$legacyId WHERE `sender_id`=$legacyId AND (`sender_admin_id` IS NULL OR `sender_admin_id`=0)");
                    }
                    if (wcb_admin_table_exists($conn, 'admin_logs') && wcb_admin_column_exists($conn, 'admin_logs', 'actor_type')) {
                        @$conn->query("UPDATE `admin_logs` SET `actor_type`='admin' WHERE `admin_id`=$legacyId");
                    }

                    // Delete only after the dedicated administrator record exists.
                    $verify = @$conn->query("SELECT id FROM `admin` WHERE id=$legacyId LIMIT 1");
                    if ($verify && $verify->num_rows === 1) {
                        if (!@$conn->query("DELETE FROM `users` WHERE id=$legacyId AND role='admin'")) {
                            wcb_admin_log_server_error('Unable to remove legacy administrator id ' . $legacyId . ': ' . $conn->error);
                            return false;
                        }
                    }
                }
            }

            // Remove the admin option from the user role schema after all admin rows are migrated.
            $typeRes = @$conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role' LIMIT 1");
            if ($typeRes && ($typeRow = $typeRes->fetch_assoc()) && strpos((string)$typeRow['COLUMN_TYPE'], "'admin'") !== false) {
                if (!@$conn->query("ALTER TABLE `users` MODIFY `role` ENUM('agent','player','support') NOT NULL")) {
                    wcb_admin_log_server_error('Unable to separate admin role from users schema: ' . $conn->error);
                    return false;
                }
            }
        }

        $completed = true;
        return true;
    }
}

if (!function_exists('wcb_admin_find_by_email')) {
    function wcb_admin_find_by_email($conn, $email) {
        if (!wcb_admin_ensure_schema($conn)) {
            return null;
        }
        $stmt = $conn->prepare("SELECT id, name, email, password, status FROM `admin` WHERE email=? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $admin = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
        $stmt->close();
        return $admin;
    }
}

if (!function_exists('wcb_admin_get_by_id')) {
    function wcb_admin_get_by_id($conn, $adminId) {
        if (!wcb_admin_ensure_schema($conn)) {
            return null;
        }
        $adminId = (int)$adminId;
        $stmt = $conn->prepare("SELECT id, name, email, password, status, last_login_at, last_password_change_at, created_at, updated_at FROM `admin` WHERE id=? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $res = $stmt->get_result();
        $admin = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
        $stmt->close();
        return $admin;
    }
}

if (!function_exists('wcb_admin_record_login')) {
    function wcb_admin_record_login($conn, $adminId) {
        $adminId = (int)$adminId;
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $stmt = $conn->prepare("UPDATE `admin` SET last_login_at=NOW(), last_login_ip=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('si', $ip, $adminId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('wcb_admin_csrf_token')) {
    function wcb_admin_csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['admin_csrf_token'])) {
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['admin_csrf_token'];
    }
}

if (!function_exists('wcb_admin_verify_csrf')) {
    function wcb_admin_verify_csrf($token) {
        $stored = $_SESSION['admin_csrf_token'] ?? '';
        return is_string($token) && is_string($stored) && $stored !== '' && hash_equals($stored, $token);
    }
}

if (!function_exists('wcb_admin_write_audit_log')) {
    function wcb_admin_write_audit_log($conn, $adminId, $action, $severity = 'info') {
        if (!wcb_admin_table_exists($conn, 'admin_logs')) {
            return;
        }
        $adminId = (int)$adminId;
        $action = substr((string)$action, 0, 255);
        $severity = in_array($severity, array('info', 'warning', 'danger'), true) ? $severity : 'info';
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

        if (wcb_admin_column_exists($conn, 'admin_logs', 'actor_type')) {
            $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, actor_type, action, severity, ip_address) VALUES (?, 'admin', ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('isss', $adminId, $action, $severity, $ip);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, severity, ip_address) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('isss', $adminId, $action, $severity, $ip);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}
