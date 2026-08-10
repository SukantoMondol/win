<?php
// includes/maintenance_guard.php
// Central Maintenance Mode guard for all public/user-facing pages.
// Admin folder is always allowed so admins can turn Maintenance Mode OFF again.
require_once __DIR__ . '/admin_path_helper.php';

if (!function_exists('maintenance_column_exists')) {
    function maintenance_column_exists($conn, $column) {
        if (!$conn || !method_exists($conn, 'query')) return false;
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $result = @$conn->query("SHOW COLUMNS FROM settings LIKE '" . $conn->real_escape_string($safeColumn) . "'");
        return ($result && $result->num_rows > 0);
    }
}

if (!function_exists('maintenance_ensure_column')) {
    function maintenance_ensure_column($conn, $column, $definition) {
        if (!$conn || !method_exists($conn, 'query')) return;
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($safeColumn === '') return;
        if (!maintenance_column_exists($conn, $safeColumn)) {
            @ $conn->query("ALTER TABLE settings ADD COLUMN `$safeColumn` $definition");
        }
    }
}

if (!function_exists('maintenance_ensure_settings_columns')) {
    function maintenance_ensure_settings_columns($conn) {
        if (!$conn || (property_exists($conn, 'connect_error') && $conn->connect_error)) return;
        maintenance_ensure_column($conn, 'maintenance_message', "TEXT NULL");
        maintenance_ensure_column($conn, 'maintenance_warning_text', "VARCHAR(255) DEFAULT 'Website Under Maintenance'");
        maintenance_ensure_column($conn, 'maintenance_text_color', "VARCHAR(20) DEFAULT '#ffcc00'");
        maintenance_ensure_column($conn, 'maintenance_image', "VARCHAR(255) DEFAULT NULL");
    }
}

if (!function_exists('maintenance_request_path')) {
    function maintenance_request_path() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH);
        return strtolower($path ?: '');
    }
}

if (!function_exists('maintenance_is_admin_request')) {
    function maintenance_is_admin_request() {
        $path = maintenance_request_path();
        return admin_panel_is_request($path);
    }
}

if (!function_exists('maintenance_is_maintenance_page')) {
    function maintenance_is_maintenance_page() {
        $path = maintenance_request_path();
        return (basename($path) === 'maintenance.php');
    }
}

if (!function_exists('maintenance_is_admin_session')) {
    function maintenance_is_admin_session() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    }
}

if (!function_exists('maintenance_asset_url')) {
    function maintenance_asset_url($path) {
        $path = trim((string)$path);
        if ($path === '') return '';
        if (preg_match('#^https?://#i', $path)) return $path;
        $path = ltrim($path, '/');
        return '/' . $path;
    }
}

if (!function_exists('maintenance_fetch_settings')) {
    function maintenance_fetch_settings($conn) {
        $defaults = [
            'site_name' => 'Website',
            'maintenance_mode' => 0,
            'maintenance_message' => 'আমাদের ওয়েবসাইট বর্তমানে Maintenance কাজের জন্য সাময়িক বন্ধ আছে। কিছু সময় পর আবার চেষ্টা করুন।',
            'maintenance_warning_text' => 'Website Under Maintenance',
            'maintenance_text_color' => '#ffcc00',
            'maintenance_image' => ''
        ];
        if (!$conn || (property_exists($conn, 'connect_error') && $conn->connect_error)) return $defaults;
        maintenance_ensure_settings_columns($conn);
        $res = @$conn->query("SELECT * FROM settings WHERE id=1 LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            return array_merge($defaults, array_filter($row, function($v) { return $v !== null; }));
        }
        return $defaults;
    }
}

if (!function_exists('maintenance_render_page')) {
    function maintenance_render_page($settings = []) {
        $siteName = htmlspecialchars($settings['site_name'] ?? 'Website', ENT_QUOTES, 'UTF-8');
        $warning = trim((string)($settings['maintenance_warning_text'] ?? 'Website Under Maintenance'));
        if ($warning === '') $warning = 'Website Under Maintenance';
        $message = trim((string)($settings['maintenance_message'] ?? 'আমাদের ওয়েবসাইট বর্তমানে Maintenance কাজের জন্য সাময়িক বন্ধ আছে। কিছু সময় পর আবার চেষ্টা করুন।'));
        if ($message === '') $message = 'আমাদের ওয়েবসাইট বর্তমানে Maintenance কাজের জন্য সাময়িক বন্ধ আছে। কিছু সময় পর আবার চেষ্টা করুন।';
        $textColor = trim((string)($settings['maintenance_text_color'] ?? '#ffcc00'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $textColor)) $textColor = '#ffcc00';
        $image = maintenance_asset_url($settings['maintenance_image'] ?? '');

        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3600');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Content-Type: text/html; charset=utf-8');
        }
        ?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $siteName; ?> | Maintenance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, 'Noto Sans Bengali', sans-serif;
            background: radial-gradient(circle at top, #104d3b 0%, #05251d 52%, #020f0c 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
        }
        .maintenance-card {
            width: 100%;
            max-width: 560px;
            text-align: center;
            padding: 34px 24px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 26px;
            background: rgba(4, 34, 27, .78);
            box-shadow: 0 24px 80px rgba(0,0,0,.35);
            backdrop-filter: blur(12px);
        }
        .status-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 204, 0, .12);
            color: <?php echo htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8'); ?>;
            font-size: 34px;
            border: 1px solid rgba(255,204,0,.25);
        }
        .maintenance-img {
            max-width: 260px;
            width: 70%;
            max-height: 220px;
            object-fit: contain;
            margin: 0 auto 18px;
            display: block;
            border-radius: 18px;
        }
        h1 {
            margin: 0 0 12px;
            color: <?php echo htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8'); ?>;
            font-size: clamp(26px, 7vw, 42px);
            line-height: 1.15;
            font-weight: 900;
        }
        .message {
            margin: 0 auto;
            color: #e8fff6;
            font-size: 17px;
            line-height: 1.75;
            max-width: 470px;
            white-space: pre-line;
        }
        .footer-note {
            margin-top: 24px;
            color: rgba(255,255,255,.55);
            font-size: 12px;
        }
    </style>
</head>
<body>
    <main class="maintenance-card">
        <?php if ($image !== ''): ?>
            <img class="maintenance-img" src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="Maintenance">
        <?php else: ?>
            <div class="status-icon"><i class="fas fa-tools"></i></div>
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="message"><?php echo nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?></div>
        <div class="footer-note">Please try again later.</div>
    </main>
</body>
</html>
        <?php
        exit();
    }
}

if (!function_exists('maintenance_enforce')) {
    function maintenance_enforce($conn = null) {
        if (PHP_SAPI === 'cli') return;
        if (maintenance_is_admin_request() || maintenance_is_maintenance_page()) return;
        if (maintenance_is_admin_session()) return;
        if (!$conn || (property_exists($conn, 'connect_error') && $conn->connect_error)) return;

        $settings = maintenance_fetch_settings($conn);
        if (!empty($settings['maintenance_mode']) && intval($settings['maintenance_mode']) === 1) {
            maintenance_render_page($settings);
        }
    }
}
?>
