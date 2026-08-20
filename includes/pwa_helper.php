<?php
/**
 * Dynamic PWA helper.
 * Keeps the install prompt/app name/icon controlled from Admin without manual SQL.
 */
if (!function_exists('wcb_db_column_exists')) {
    function wcb_db_column_exists($conn, $table, $column) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        if ($table === '' || $column === '' || !$conn) return false;
        $res = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('wcb_db_add_column_if_missing')) {
    function wcb_db_add_column_if_missing($conn, $table, $column, $definition) {
        if (!$conn || $conn->connect_error) return false;
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        if ($table === '' || $column === '') return false;
        if (!wcb_db_column_exists($conn, $table, $column)) {
            return @$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
        return true;
    }
}

if (!function_exists('wcb_pwa_ensure_schema')) {
    function wcb_pwa_ensure_schema($conn) {
        if (!$conn || $conn->connect_error) return false;
        wcb_db_add_column_if_missing($conn, 'settings', 'pwa_app_name', "varchar(120) DEFAULT NULL");
        wcb_db_add_column_if_missing($conn, 'settings', 'pwa_app_short_name', "varchar(50) DEFAULT NULL");
        wcb_db_add_column_if_missing($conn, 'settings', 'pwa_app_icon', "varchar(255) DEFAULT NULL");
        wcb_db_add_column_if_missing($conn, 'settings', 'pwa_app_icon_192', "varchar(255) DEFAULT NULL");
        wcb_db_add_column_if_missing($conn, 'settings', 'pwa_app_icon_512', "varchar(255) DEFAULT NULL");
        wcb_db_add_column_if_missing($conn, 'settings', 'pwa_app_maskable_192', "varchar(255) DEFAULT NULL");
        wcb_db_add_column_if_missing($conn, 'settings', 'pwa_app_maskable_512', "varchar(255) DEFAULT NULL");
        wcb_db_add_column_if_missing($conn, 'settings', 'pwa_version', "int(11) NOT NULL DEFAULT 1");
        return true;
    }
}

if (!function_exists('wcb_pwa_normalize_path')) {
    function wcb_pwa_normalize_path($path, $fallback = '/assets/icons/icon-192.png') {
        $path = trim((string)$path);
        if ($path === '') return $fallback;
        if (strpos($path, 'data:') === 0 || preg_match('~^https?://~i', $path)) return $path;
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('wcb_pwa_get_settings')) {
    function wcb_pwa_get_settings($conn = null) {
        $defaults = array(
            'site_name' => 'RedJili',
            'app_name' => 'RedJili Web App',
            'short_name' => 'RedJili',
            'description' => 'Website app',
            'theme_color' => '#052e23',
            'background_color' => '#052e23',
            'icon_original' => '/assets/icons/icon-192.png',
            'icon_192' => '/assets/icons/icon-192.png',
            'icon_512' => '/assets/icons/icon-512.png',
            'maskable_192' => '/assets/icons/maskable-192.png',
            'maskable_512' => '/assets/icons/maskable-512.png',
            'version' => 1,
        );
        if (!$conn || $conn->connect_error) return $defaults;
        wcb_pwa_ensure_schema($conn);
        $q = @$conn->query("SELECT * FROM settings WHERE id=1 LIMIT 1");
        if (!$q || $q->num_rows < 1) return $defaults;
        $row = $q->fetch_assoc();
        $site = trim((string)($row['site_name'] ?? $defaults['site_name']));
        if ($site === '') $site = $defaults['site_name'];
        $appName = trim((string)($row['pwa_app_name'] ?? ''));
        if ($appName === '') $appName = $site . ' Web App';
        $shortName = trim((string)($row['pwa_app_short_name'] ?? ''));
        if ($shortName === '') $shortName = $site;
        if (function_exists('mb_substr')) {
            $shortName = mb_substr($shortName, 0, 48, 'UTF-8');
        } else {
            $shortName = substr($shortName, 0, 48);
        }
        $iconOriginal = !empty($row['pwa_app_icon']) ? $row['pwa_app_icon'] : ($row['app_logo'] ?? '');
        $icon192 = !empty($row['pwa_app_icon_192']) ? $row['pwa_app_icon_192'] : (!empty($iconOriginal) ? $iconOriginal : 'assets/icons/icon-192.png');
        $icon512 = !empty($row['pwa_app_icon_512']) ? $row['pwa_app_icon_512'] : (!empty($iconOriginal) ? $iconOriginal : 'assets/icons/icon-512.png');
        $mask192 = !empty($row['pwa_app_maskable_192']) ? $row['pwa_app_maskable_192'] : $icon192;
        $mask512 = !empty($row['pwa_app_maskable_512']) ? $row['pwa_app_maskable_512'] : $icon512;
        return array(
            'site_name' => $site,
            'app_name' => $appName,
            'short_name' => $shortName,
            'description' => $appName . ' website app',
            'theme_color' => !empty($row['theme_primary']) ? $row['theme_primary'] : $defaults['theme_color'],
            'background_color' => !empty($row['theme_primary']) ? $row['theme_primary'] : $defaults['background_color'],
            'icon_original' => wcb_pwa_normalize_path($iconOriginal, '/assets/icons/icon-192.png'),
            'icon_192' => wcb_pwa_normalize_path($icon192, '/assets/icons/icon-192.png'),
            'icon_512' => wcb_pwa_normalize_path($icon512, '/assets/icons/icon-512.png'),
            'maskable_192' => wcb_pwa_normalize_path($mask192, '/assets/icons/maskable-192.png'),
            'maskable_512' => wcb_pwa_normalize_path($mask512, '/assets/icons/maskable-512.png'),
            'version' => max(1, (int)($row['pwa_version'] ?? 1)),
        );
    }
}

if (!function_exists('wcb_pwa_increment_version')) {
    function wcb_pwa_increment_version($conn) {
        if (!$conn || $conn->connect_error) return;
        wcb_pwa_ensure_schema($conn);
        @$conn->query("UPDATE settings SET pwa_version = COALESCE(pwa_version, 1) + 1 WHERE id=1");
    }
}

if (!function_exists('wcb_pwa_resize_icon')) {
    function wcb_pwa_resize_icon($src, $dest, $size, $maskable = false) {
        if (!extension_loaded('gd') || !is_file($src)) return false;
        $info = @getimagesize($src);
        if (!$info) return false;
        $mime = $info['mime'] ?? '';
        $source = null;
        if ($mime === 'image/png') $source = @imagecreatefrompng($src);
        elseif ($mime === 'image/jpeg') $source = @imagecreatefromjpeg($src);
        elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $source = @imagecreatefromwebp($src);
        elseif ($mime === 'image/gif') $source = @imagecreatefromgif($src);
        if (!$source) return false;

        $sw = imagesx($source);
        $sh = imagesy($source);
        if ($sw < 1 || $sh < 1) { imagedestroy($source); return false; }

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);

        $safeArea = $maskable ? (int)round($size * 0.78) : $size;
        $scale = min($safeArea / $sw, $safeArea / $sh);
        $nw = max(1, (int)round($sw * $scale));
        $nh = max(1, (int)round($sh * $scale));
        $dx = (int)floor(($size - $nw) / 2);
        $dy = (int)floor(($size - $nh) / 2);
        imagecopyresampled($canvas, $source, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
        $ok = @imagepng($canvas, $dest, 6);
        imagedestroy($canvas);
        imagedestroy($source);
        return $ok;
    }
}

if (!function_exists('wcb_pwa_handle_icon_upload')) {
    function wcb_pwa_handle_icon_upload($fileField = 'pwa_app_icon') {
        if (!isset($_FILES[$fileField]) || ($_FILES[$fileField]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'error' => 'No icon uploaded.');
        }
        $file = $_FILES[$fileField];
        if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
            return array('ok' => false, 'error' => 'Icon file is too large. Maximum 8MB allowed.');
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowed = array('jpg', 'jpeg', 'png', 'webp', 'gif', 'svg');
        if (!in_array($ext, $allowed, true)) {
            return array('ok' => false, 'error' => 'Invalid icon type. Use PNG, JPG, WEBP, GIF or SVG.');
        }

        $stamp = time() . '_' . bin2hex(random_bytes(3));
        $originalName = 'pwa_app_original_' . $stamp . '.' . $ext;

        $possible_roots = array(
            dirname(__DIR__),
            $_SERVER['DOCUMENT_ROOT'] ?? '',
            '/app',
            '/var/www/html',
            '/Applications/XAMPP/xamppfiles/htdocs/jb66.net'
        );

        $saved = false;
        $originalPath = '';
        $saved_rel_path = '';

        foreach ($possible_roots as $root) {
            if (empty($root)) continue;
            $abs_dir = rtrim($root, '/') . '/assets/icons/';
            if (!is_dir($abs_dir)) {
                @mkdir($abs_dir, 0777, true);
            }
            @chmod($abs_dir, 0777);

            $target_file = $abs_dir . $originalName;
            $tmp = $file['tmp_name'];
            $saved = @move_uploaded_file($tmp, $target_file);
            if (!$saved) {
                $saved = @copy($tmp, $target_file);
            }
            if (!$saved && file_exists($tmp)) {
                $content = @file_get_contents($tmp);
                if ($content !== false && strlen($content) > 0) {
                    $saved = (@file_put_contents($target_file, $content) !== false);
                }
            }
            if ($saved) {
                @chmod($target_file, 0666);
                $originalPath = $target_file;
                $saved_rel_path = 'assets/icons/' . $originalName;
                break;
            }
        }

        if (!$saved) {
            // Ultimate Fallback: Base64 Data URI
            $tmpContent = @file_get_contents($file['tmp_name']);
            if ($tmpContent !== false && strlen($tmpContent) > 0) {
                $mime = 'image/' . ($ext === 'svg' ? 'svg+xml' : ($ext === 'jpg' ? 'jpeg' : $ext));
                $dataUri = 'data:' . $mime . ';base64,' . base64_encode($tmpContent);
                $paths = array(
                    'original' => $dataUri,
                    'icon_192' => $dataUri,
                    'icon_512' => $dataUri,
                    'maskable_192' => $dataUri,
                    'maskable_512' => $dataUri,
                );
                return array('ok' => true, 'paths' => $paths);
            }
            return array('ok' => false, 'error' => 'Unable to save uploaded icon. Check server folder permissions.');
        }

        $paths = array(
            'original' => $saved_rel_path,
            'icon_192' => $saved_rel_path,
            'icon_512' => $saved_rel_path,
            'maskable_192' => $saved_rel_path,
            'maskable_512' => $saved_rel_path,
        );

        $targetDir = dirname($originalPath) . '/';
        $icon192 = $targetDir . 'pwa_app_192_' . $stamp . '.png';
        $icon512 = $targetDir . 'pwa_app_512_' . $stamp . '.png';
        $mask192 = $targetDir . 'pwa_app_maskable_192_' . $stamp . '.png';
        $mask512 = $targetDir . 'pwa_app_maskable_512_' . $stamp . '.png';

        if (wcb_pwa_resize_icon($originalPath, $icon192, 192, false)) $paths['icon_192'] = 'assets/icons/' . basename($icon192);
        if (wcb_pwa_resize_icon($originalPath, $icon512, 512, false)) $paths['icon_512'] = 'assets/icons/' . basename($icon512);
        if (wcb_pwa_resize_icon($originalPath, $mask192, 192, true)) $paths['maskable_192'] = 'assets/icons/' . basename($mask192);
        if (wcb_pwa_resize_icon($originalPath, $mask512, 512, true)) $paths['maskable_512'] = 'assets/icons/' . basename($mask512);

        return array('ok' => true, 'paths' => $paths);
    }
}
?>
