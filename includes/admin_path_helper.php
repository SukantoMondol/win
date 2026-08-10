<?php
/**
 * Dynamic Admin Panel path helper.
 *
 * Purpose:
 * - Admin URL must follow the admin folder name automatically.
 * - If the admin folder is renamed, links/redirects/security checks should not keep forcing /admin/.
 *
 * Usage:
 * - Keep this file in /includes/.
 * - Keep admin_panel_marker.php inside the admin panel folder.
 */

if (!function_exists('admin_panel_project_root')) {
    function admin_panel_project_root() {
        return realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    }
}

if (!function_exists('admin_panel_normalize_path')) {
    function admin_panel_normalize_path($path) {
        return str_replace('\\', '/', (string)$path);
    }
}

if (!function_exists('admin_panel_has_marker')) {
    function admin_panel_has_marker($dir) {
        return is_dir($dir) && (
            file_exists($dir . DIRECTORY_SEPARATOR . 'admin_panel_marker.php') ||
            file_exists($dir . DIRECTORY_SEPARATOR . '.admin_panel_marker')
        );
    }
}

if (!function_exists('admin_panel_directory_score')) {
    function admin_panel_directory_score($dir) {
        if (!is_dir($dir)) return 0;

        $score = 0;
        $requiredLikeFiles = array(
            'login.php',
            'dashboard.php',
            'support.php',
            'users_all.php',
            'payment_gateway_settings.php'
        );

        foreach ($requiredLikeFiles as $file) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . $file)) {
                $score++;
            }
        }

        if (admin_panel_has_marker($dir)) {
            $score += 50;
        }

        return $score;
    }
}

if (!function_exists('admin_panel_is_directory')) {
    function admin_panel_is_directory($dir) {
        if (!is_dir($dir)) return false;
        if (admin_panel_has_marker($dir)) return true;
        return admin_panel_directory_score($dir) >= 4;
    }
}

if (!function_exists('admin_panel_folder_name')) {
    function admin_panel_folder_name() {
        static $cachedFolder = null;
        if ($cachedFolder !== null) {
            return $cachedFolder;
        }

        if (defined('ADMIN_PANEL_FOLDER') && ADMIN_PANEL_FOLDER) {
            $cachedFolder = trim((string)ADMIN_PANEL_FOLDER, "/ \\t\n\r\0\x0B");
            return $cachedFolder !== '' ? $cachedFolder : 'admin';
        }

        $root = admin_panel_project_root();
        $rootReal = realpath($root) ?: $root;

        // 1) If the current executing PHP file is inside the admin folder, detect from that folder.
        $scriptFilename = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
        if ($scriptFilename) {
            $scriptDir = dirname($scriptFilename);
            if (admin_panel_is_directory($scriptDir)) {
                $cachedFolder = basename($scriptDir);
                return $cachedFolder;
            }
        }

        // 2) Prefer the marker file. This is the safest when root files need to redirect to admin.
        $items = @scandir($rootReal);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $dir = $rootReal . DIRECTORY_SEPARATOR . $item;
                if (admin_panel_has_marker($dir)) {
                    $cachedFolder = basename($dir);
                    return $cachedFolder;
                }
            }
        }

        // 3) Fallback: scan for the admin folder by unique admin panel files.
        if (is_array($items)) {
            $bestFolder = '';
            $bestScore = 0;
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                if (in_array(strtolower($item), array('agent', 'player', 'api', 'includes', 'assets', 'public_html'), true)) continue;
                $dir = $rootReal . DIRECTORY_SEPARATOR . $item;
                $score = admin_panel_directory_score($dir);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestFolder = basename($dir);
                }
            }
            if ($bestScore >= 4 && $bestFolder !== '') {
                $cachedFolder = $bestFolder;
                return $cachedFolder;
            }
        }

        // 4) Last fallback for this source package.
        if (is_dir($rootReal . DIRECTORY_SEPARATOR . 'webcornerbd')) {
            $cachedFolder = 'webcornerbd';
            return $cachedFolder;
        }

        $cachedFolder = 'admin';
        return $cachedFolder;
    }
}

if (!function_exists('admin_panel_base_url_path')) {
    function admin_panel_base_url_path() {
        $root = realpath(admin_panel_project_root());
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

        if ($root && $docRoot && strpos(admin_panel_normalize_path($root), admin_panel_normalize_path($docRoot)) === 0) {
            $sub = substr(admin_panel_normalize_path($root), strlen(admin_panel_normalize_path($docRoot)));
            $sub = '/' . trim($sub, '/');
            return $sub === '/' ? '' : $sub;
        }

        $scriptName = admin_panel_normalize_path($_SERVER['SCRIPT_NAME'] ?? '');
        $folder = admin_panel_folder_name();
        $needle = '/' . $folder . '/';
        $pos = strpos($scriptName, $needle);
        if ($pos !== false) {
            return rtrim(substr($scriptName, 0, $pos), '/');
        }

        return '';
    }
}

if (!function_exists('admin_panel_url')) {
    function admin_panel_url($path = '') {
        $base = rtrim(admin_panel_base_url_path(), '/');
        $folder = trim(admin_panel_folder_name(), '/');
        $path = ltrim((string)$path, '/');

        $url = $base . '/' . rawurlencode($folder);
        if ($path !== '') {
            $parts = array_map('rawurlencode', explode('/', $path));
            $url .= '/' . implode('/', $parts);
        } else {
            $url .= '/';
        }
        return $url;
    }
}

if (!function_exists('admin_panel_is_request')) {
    function admin_panel_is_request($path = null) {
        $requestPath = $path;
        if ($requestPath === null) {
            $requestPath = $_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? '');
        }
        $requestPath = parse_url($requestPath, PHP_URL_PATH);
        $requestPath = '/' . trim(rawurldecode((string)$requestPath), '/');
        if ($requestPath === '/') $requestPath = '';

        $adminBase = rtrim(admin_panel_base_url_path(), '/') . '/' . trim(admin_panel_folder_name(), '/');
        $adminBase = '/' . trim(rawurldecode($adminBase), '/');

        return ($requestPath === $adminBase || strpos($requestPath, $adminBase . '/') === 0);
    }
}

if (!function_exists('admin_panel_redirect')) {
    function admin_panel_redirect($path = '') {
        header('Location: ' . admin_panel_url($path));
        exit();
    }
}
?>
