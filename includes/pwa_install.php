<?php if (!defined('WCB_PWA_INSTALL_INCLUDED')): define('WCB_PWA_INSTALL_INCLUDED', true);
$pwa = array(
    'app_name' => 'RedJili Web App',
    'short_name' => 'RedJili',
    'theme_color' => '#052e23',
    'icon_192' => '/assets/icons/icon-192.png',
    'version' => 1,
);
$helper = __DIR__ . '/pwa_helper.php';
if (file_exists($helper)) {
    require_once $helper;
    if (function_exists('wcb_pwa_get_settings')) {
        $pwa = wcb_pwa_get_settings($conn ?? null);
    }
}
$pwa_version = intval($pwa['version'] ?? 1);
$pwa_app_name = htmlspecialchars($pwa['app_name'] ?? 'Web App', ENT_QUOTES, 'UTF-8');
$pwa_short_name = htmlspecialchars($pwa['short_name'] ?? 'Web App', ENT_QUOTES, 'UTF-8');
$pwa_theme = htmlspecialchars($pwa['theme_color'] ?? '#052e23', ENT_QUOTES, 'UTF-8');
$pwa_icon = htmlspecialchars($pwa['icon_192'] ?? '/assets/icons/icon-192.png', ENT_QUOTES, 'UTF-8');
?>
<?php
$pwa_base = (basename(dirname($_SERVER['PHP_SELF'] ?? '')) === 'player' || basename(dirname($_SERVER['PHP_SELF'] ?? '')) === 'webcornerbd') ? '../' : '';
?>
<link rel="manifest" href="<?php echo $pwa_base; ?>manifest.php?v=<?php echo $pwa_version; ?>">
<meta name="theme-color" content="<?php echo $pwa_theme; ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?php echo $pwa_short_name; ?>">
<meta name="application-name" content="<?php echo $pwa_app_name; ?>">
<meta name="msapplication-TileColor" content="<?php echo $pwa_theme; ?>">
<meta name="msapplication-TileImage" content="<?php echo $pwa_icon; ?>?v=<?php echo $pwa_version; ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo $pwa_icon; ?>?v=<?php echo $pwa_version; ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo $pwa_icon; ?>?v=<?php echo $pwa_version; ?>">
<link rel="stylesheet" href="<?php echo $pwa_base; ?>assets/css/mobile-scroll-fix.css?v=2">
<script src="<?php echo $pwa_base; ?>pwa-install.js?v=6" defer></script>
<?php endif; ?>
