<?php
// Dynamic PWA manifest generated from Admin > PWA App Settings.
define('GAME_API_SKIP_MAINTENANCE', true);
$__db = __DIR__ . '/includes/db.php';
if (file_exists($__db)) { require_once $__db; }
$__helper = __DIR__ . '/includes/pwa_helper.php';
if (file_exists($__helper)) { require_once $__helper; }
$pwa = function_exists('wcb_pwa_get_settings') ? wcb_pwa_get_settings($conn ?? null) : array();
$manifest = array(
    'id' => '/?source=pwa',
    'name' => $pwa['app_name'] ?? 'RedJili Web App',
    'short_name' => $pwa['short_name'] ?? 'RedJili',
    'description' => $pwa['description'] ?? 'Website app',
    'start_url' => '/?source=pwa',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => $pwa['background_color'] ?? '#052e23',
    'theme_color' => $pwa['theme_color'] ?? '#052e23',
    'icons' => array(
        array('src' => $pwa['icon_192'] ?? '/assets/icons/icon-192.png', 'sizes' => '192x192', 'purpose' => 'any'),
        array('src' => $pwa['icon_512'] ?? '/assets/icons/icon-512.png', 'sizes' => '512x512', 'purpose' => 'any'),
        array('src' => $pwa['maskable_192'] ?? '/assets/icons/maskable-192.png', 'sizes' => '192x192', 'purpose' => 'maskable'),
        array('src' => $pwa['maskable_512'] ?? '/assets/icons/maskable-512.png', 'sizes' => '512x512', 'purpose' => 'maskable')
    )
);
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
