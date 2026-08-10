<?php
// PWA install page. This replaces old APK download with Chrome Web App install support.
define('GAME_API_SKIP_MAINTENANCE', true);
$__maintenance_db = __DIR__ . '/includes/db.php';
if (file_exists($__maintenance_db)) { require_once $__maintenance_db; }
$settings = [];
if (isset($conn) && !$conn->connect_error) {
    $set_q = @$conn->query("SELECT * FROM settings WHERE id=1");
    if ($set_q && $set_q->num_rows > 0) { $settings = $set_q->fetch_assoc(); }
}
require_once __DIR__ . '/includes/pwa_helper.php';
$pwa_settings = wcb_pwa_get_settings($conn ?? null);
$site_name = !empty($settings['site_name']) ? $settings['site_name'] : 'RedJili';
$app_name = !empty($pwa_settings['app_name']) ? $pwa_settings['app_name'] : ($site_name . ' Web App');
$app_logo_src = !empty($pwa_settings['icon_original']) ? $pwa_settings['icon_original'] : '/assets/icons/icon-192.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Install <?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include __DIR__ . '/includes/pwa_install.php'; ?>
    <style>
        body{margin:0;min-height:100vh;background:linear-gradient(180deg,#073f32,#021e18);font-family:Arial,sans-serif;color:#fff;display:flex;align-items:center;justify-content:center;padding:20px;box-sizing:border-box}.card{width:100%;max-width:430px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);box-shadow:0 20px 80px rgba(0,0,0,.36);border-radius:28px;padding:28px;text-align:center}.logo{width:86px;height:86px;border-radius:24px;background:#fff;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;overflow:hidden}.logo img{max-width:80%;max-height:80%;object-fit:contain}.title{font-size:27px;font-weight:900;margin:0 0 8px}.sub{font-size:14px;line-height:1.6;color:#d5fff2;margin:0 0 24px}.btn{border:0;border-radius:999px;background:linear-gradient(180deg,#1de9b6,#00897b);color:#fff;width:100%;padding:16px 20px;font-weight:900;font-size:16px;cursor:pointer;box-shadow:0 12px 25px rgba(0,0,0,.25)}.back{display:inline-block;margin-top:18px;color:#ffdf1b;font-weight:800;text-decoration:none}.hint{font-size:12px;color:#b8d8cf;margin-top:16px;line-height:1.5}.badge{display:inline-block;background:#ffdf1b;color:#052e23;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:900;margin-bottom:18px}
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">Chrome Web App Install</div>
        <div class="logo"><img src="<?php echo htmlspecialchars($app_logo_src, ENT_QUOTES, 'UTF-8'); ?>" onerror="this.src='/assets/icons/icon-192.png'" alt="App Logo"></div>
        <h1 class="title"><?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="sub">এটি আলাদা APK নয়। Chrome Browser-এর Install Prompt দিয়ে website app হিসেবে install হবে।</p>
        <button class="btn js-pwa-install" data-pwa-install="1">Install App</button>
        <div class="hint">Prompt না এলে Chrome menu থেকে <b>Install app</b> / <b>Add to Home screen</b> নির্বাচন করুন।</div>
        <a class="back" href="index.php">Back to Website</a>
    </div>
</body>
</html>
