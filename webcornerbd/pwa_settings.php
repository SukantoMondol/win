<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require '../includes/pwa_helper.php';

wcb_pwa_ensure_schema($conn);
$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pwa_settings'])) {
    $app_name = trim(strip_tags($_POST['pwa_app_name'] ?? ''));
    $short_name = trim(strip_tags($_POST['pwa_app_short_name'] ?? ''));
    if (function_exists('mb_substr')) {
        $app_name = mb_substr($app_name, 0, 120, 'UTF-8');
        $short_name = mb_substr($short_name, 0, 50, 'UTF-8');
    } else {
        $app_name = substr($app_name, 0, 120);
        $short_name = substr($short_name, 0, 50);
    }

    if ($app_name === '') {
        $msg = 'App Name is required.';
        $msg_type = 'error';
    } else {
        $current = $conn->query("SELECT pwa_app_icon, pwa_app_icon_192, pwa_app_icon_512, pwa_app_maskable_192, pwa_app_maskable_512 FROM settings WHERE id=1 LIMIT 1");
        $paths = array('original' => '', 'icon_192' => '', 'icon_512' => '', 'maskable_192' => '', 'maskable_512' => '');
        if ($current && $current->num_rows > 0) {
            $row = $current->fetch_assoc();
            $paths['original'] = $row['pwa_app_icon'] ?? '';
            $paths['icon_192'] = $row['pwa_app_icon_192'] ?? '';
            $paths['icon_512'] = $row['pwa_app_icon_512'] ?? '';
            $paths['maskable_192'] = $row['pwa_app_maskable_192'] ?? '';
            $paths['maskable_512'] = $row['pwa_app_maskable_512'] ?? '';
        }

        if (isset($_FILES['pwa_app_icon']) && ($_FILES['pwa_app_icon']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = wcb_pwa_handle_icon_upload('pwa_app_icon');
            if (!$upload['ok']) {
                $msg = $upload['error'];
                $msg_type = 'error';
            } else {
                $paths = $upload['paths'];
            }
        }

        if ($msg_type !== 'error') {
            $stmt = $conn->prepare("UPDATE settings SET pwa_app_name=?, pwa_app_short_name=?, pwa_app_icon=?, pwa_app_icon_192=?, pwa_app_icon_512=?, pwa_app_maskable_192=?, pwa_app_maskable_512=?, pwa_version=COALESCE(pwa_version,1)+1 WHERE id=1");
            if ($stmt) {
                $stmt->bind_param('sssssss', $app_name, $short_name, $paths['original'], $paths['icon_192'], $paths['icon_512'], $paths['maskable_192'], $paths['maskable_512']);
                if ($stmt->execute()) {
                    $msg = 'PWA App settings saved successfully. New installs will use the updated name and icon.';
                    $msg_type = 'success';
                } else {
                    $msg = 'Unable to save PWA settings: ' . $stmt->error;
                    $msg_type = 'error';
                }
            } else {
                $msg = 'Unable to prepare PWA settings update.';
                $msg_type = 'error';
            }
        }
    }
}

$settings_row = $conn->query("SELECT * FROM settings WHERE id=1 LIMIT 1");
$settings = ($settings_row && $settings_row->num_rows > 0) ? $settings_row->fetch_assoc() : [];
$pwa = wcb_pwa_get_settings($conn);
$app_name_value = $settings['pwa_app_name'] ?? $pwa['app_name'];
$short_name_value = $settings['pwa_app_short_name'] ?? $pwa['short_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWA App Settings | Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 text-slate-800">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen">
        <?php include '../includes/header.php'; ?>

        <div class="max-w-5xl mx-auto pt-16 lg:pt-20">
            <div class="mb-6">
                <h1 class="text-2xl font-black text-gray-800 flex items-center gap-2"><i class="fas fa-mobile-screen-button text-emerald-600"></i> PWA App Settings</h1>
                <p class="text-sm text-gray-500">Change the Web App install name, short name and app icon from Admin Panel.</p>
            </div>

            <?php if($msg): ?>
                <div class="mb-6 p-4 rounded-xl border-l-4 bg-white shadow-sm <?php echo $msg_type === 'success' ? 'border-green-500 text-green-700' : 'border-red-500 text-red-700'; ?>">
                    <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                    <span class="font-bold text-sm"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <form method="POST" enctype="multipart/form-data" class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <input type="hidden" name="save_pwa_settings" value="1">
                    <div class="px-6 py-4 border-b bg-gray-50">
                        <h2 class="font-black text-gray-800">App Install Branding</h2>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">App Name</label>
                            <input type="text" name="pwa_app_name" required value="<?php echo htmlspecialchars($app_name_value, ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400" placeholder="AJ WIN999 Web App">
                            <p class="text-[11px] text-gray-400 mt-1">This name appears in the browser install prompt and installed app details.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Short App Name</label>
                            <input type="text" name="pwa_app_short_name" value="<?php echo htmlspecialchars($short_name_value, ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400" placeholder="AJ WIN999">
                            <p class="text-[11px] text-gray-400 mt-1">This short name is used under the icon on mobile home screen.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">App Logo / Icon</label>
                            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center bg-gray-50 border border-gray-200 rounded-2xl p-4">
                                <img src="<?php echo htmlspecialchars($pwa['icon_original'], ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo intval($pwa['version']); ?>" onerror="this.src='../assets/icons/icon-192.png'" class="h-20 w-20 rounded-2xl bg-white object-contain border shadow-sm" alt="PWA Icon">
                                <div class="flex-1 w-full">
                                    <input type="file" name="pwa_app_icon" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full text-sm border border-gray-300 rounded-xl p-2 bg-white">
                                    <p class="text-[11px] text-gray-400 mt-2">Recommended: square PNG 512x512. The system will auto-generate 192/512 icons when PHP GD is available.</p>
                                </div>
                            </div>
                        </div>
                        <div class="pt-4 border-t flex justify-end">
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-7 py-3 rounded-xl font-black shadow-md"><i class="fas fa-save mr-2"></i> Save PWA Settings</button>
                        </div>
                    </div>
                </form>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b bg-gray-50">
                        <h2 class="font-black text-gray-800">Live Preview</h2>
                    </div>
                    <div class="p-6">
                        <div class="mx-auto max-w-[260px] rounded-[28px] border border-gray-200 bg-gray-50 p-5 text-center shadow-inner">
                            <img src="<?php echo htmlspecialchars($pwa['icon_192'], ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo intval($pwa['version']); ?>" onerror="this.src='../assets/icons/icon-192.png'" class="h-20 w-20 rounded-2xl bg-white object-contain mx-auto shadow" alt="App Icon">
                            <h3 class="mt-4 font-black text-gray-800"><?php echo htmlspecialchars($pwa['app_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'your-domain.com', ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="mt-4 bg-blue-600 text-white rounded-full py-2 text-sm font-black">Install</div>
                        </div>
                        <div class="mt-5 text-xs text-gray-500 leading-relaxed bg-blue-50 border border-blue-100 rounded-xl p-4">
                            <b>Note:</b> Already installed app icons may need uninstall/reinstall or browser cache clear to refresh immediately. New installs will read the latest manifest automatically.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
