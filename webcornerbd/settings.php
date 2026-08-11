<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// Ensure support/social link columns exist so the patch works on the live DB without manual SQL import.
function ensure_settings_column($conn, $column, $definition) {
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $check = $conn->query("SHOW COLUMNS FROM settings LIKE '$safeColumn'");
    if ($check && $check->num_rows == 0) {
        @$conn->query("ALTER TABLE settings ADD COLUMN `$safeColumn` $definition");
    }
}

if (isset($conn) && !$conn->connect_error) {
    ensure_settings_column($conn, 'admin_panel_name', "varchar(120) DEFAULT NULL");
    ensure_settings_column($conn, 'app_logo', "varchar(255) DEFAULT NULL");
    ensure_settings_column($conn, 'telegram_link', "varchar(255) DEFAULT '#'");
    ensure_settings_column($conn, 'facebook_link', "varchar(255) DEFAULT '#'");
    ensure_settings_column($conn, 'instagram_link', "varchar(255) DEFAULT '#'");
    ensure_settings_column($conn, 'whatsapp_link', "varchar(255) DEFAULT '#'");
    ensure_settings_column($conn, 'popup_button_text', "varchar(100) DEFAULT NULL");
    ensure_settings_column($conn, 'popup_button_link', "varchar(255) DEFAULT NULL");
    ensure_settings_column($conn, 'theme_bg', "varchar(255) DEFAULT 'linear-gradient(135deg, #071f18 0%, #0a2e22 50%, #071f18 100%)'");
    ensure_settings_column($conn, 'theme_primary', "varchar(50) DEFAULT '#0d7a55'");
    ensure_settings_column($conn, 'theme_accent', "varchar(50) DEFAULT '#f0c030'");
}

$msg = "";
$msg_type = "";

// 1. HANDLE GENERAL SETTINGS UPDATE
// Global helper for robust file upload with detailed status
if (!function_exists('wcb_save_uploaded_file')) {
    function wcb_save_uploaded_file($file, $target_dir, $prefix = 'img_') {
        if (!isset($file) || !is_array($file)) {
            return array('ok' => false, 'error' => 'No file payload received.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $err_map = array(
                1 => 'File exceeds maximum upload size (upload_max_filesize).',
                2 => 'File exceeds MAX_FILE_SIZE directive.',
                3 => 'File was only partially uploaded.',
                4 => 'No file was selected.',
                6 => 'Missing temporary folder on server.',
                7 => 'Failed to write file to server disk.',
                8 => 'A PHP extension stopped the file upload.'
            );
            $err_msg = $err_map[$file['error']] ?? ('PHP upload error code: ' . $file['error']);
            return array('ok' => false, 'error' => $err_msg);
        }

        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return array('ok' => false, 'error' => 'Uploaded temporary file missing on server.');
        }

        $clean_rel = ltrim(str_replace('../', '', $target_dir), '/');
        $abs_dir = dirname(__DIR__) . '/' . $clean_rel;

        if (!is_dir($abs_dir)) {
            if (!@mkdir($abs_dir, 0777, true)) {
                return array('ok' => false, 'error' => 'Failed to create target directory: ' . $clean_rel);
            }
        }
        @chmod($abs_dir, 0777);

        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $allowed = array("jpg", "png", "jpeg", "gif", "webp", "svg");
        if (!in_array($ext, $allowed, true)) {
            return array('ok' => false, 'error' => 'Invalid file extension (.' . $ext . '). Allowed: JPG, PNG, WEBP, GIF, SVG');
        }

        $new_name = $prefix . time() . "_" . rand(100, 999) . "." . $ext;
        $target_file = rtrim($abs_dir, '/') . '/' . $new_name;

        $tmp = $file["tmp_name"];
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
            return array('ok' => true, 'filename' => $new_name);
        }
        return array('ok' => false, 'error' => 'Unable to write file to target directory.');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_general'])) {
    $site_name = sanitize($conn, $_POST['site_name'] ?? '');
    $admin_panel_name = sanitize($conn, $_POST['admin_panel_name'] ?? '');
    if ($admin_panel_name === '') { $admin_panel_name = $site_name; }

    $lock_percent = intval($_POST['risk_auto_lock_percent'] ?? 80);
    $marquee_text = sanitize($conn, $_POST['marquee_text'] ?? '');
    
    // Social Links
    $tg_link = sanitize($conn, $_POST['telegram_link'] ?? '#');
    $fb_link = sanitize($conn, $_POST['facebook_link'] ?? '#');
    $ig_link = sanitize($conn, $_POST['instagram_link'] ?? '#');
    $wa_link = sanitize($conn, $_POST['whatsapp_link'] ?? '#');
    
    // Popup Settings
    $popup_enabled = isset($_POST['popup_enabled']) ? 1 : 0;
    $popup_title = sanitize($conn, $_POST['popup_title'] ?? '');
    $popup_desc = sanitize($conn, $_POST['popup_desc'] ?? '');
    $popup_button_text = sanitize($conn, $_POST['popup_button_text'] ?? '');
    $popup_button_link = sanitize($conn, $_POST['popup_button_link'] ?? '');
    
    // Fetch existing images first
    $popup_image_path = '';
    $app_logo_path = '';
    $current_q = $conn->query("SELECT popup_image, app_logo FROM settings WHERE id=1");
    if ($current_q && $current_q->num_rows > 0) {
        $existing_row = $current_q->fetch_assoc();
        $popup_image_path = $existing_row['popup_image'] ?? '';
        $app_logo_path = $existing_row['app_logo'] ?? '';
    }

    // Handle global Website/Admin Logo Upload
    if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] == 0) {
        $logo_res = wcb_save_uploaded_file($_FILES['app_logo'], "assets/img/logo/", "site_logo_");
        if (!empty($logo_res['ok']) && !empty($logo_res['filename'])) {
            $app_logo_path = "assets/img/logo/" . $logo_res['filename'];
        }
    }

    // Handle Popup Image Upload
    if (isset($_FILES['popup_image']) && $_FILES['popup_image']['error'] == 0) {
        $popup_res = wcb_save_uploaded_file($_FILES['popup_image'], "assets/img/banners/", "popup_");
        if (!empty($popup_res['ok']) && !empty($popup_res['filename'])) {
            $popup_image_path = "assets/img/banners/" . $popup_res['filename'];
        }
    }

    // Theme Customizer Inputs
    $theme_bg = trim($_POST['theme_bg'] ?? '');
    $theme_primary = trim($_POST['theme_primary'] ?? '');
    $theme_accent = trim($_POST['theme_accent'] ?? '');

    // Update Settings in DB
    $stmt = $conn->prepare("UPDATE settings SET site_name=?, admin_panel_name=?, app_logo=?, risk_auto_lock_percent=?, marquee_text=?, telegram_link=?, facebook_link=?, instagram_link=?, whatsapp_link=?, popup_enabled=?, popup_title=?, popup_desc=?, popup_image=?, popup_button_text=?, popup_button_link=?, theme_bg=?, theme_primary=?, theme_accent=? WHERE id=1");
    $stmt->bind_param("sssisssssissssssss", $site_name, $admin_panel_name, $app_logo_path, $lock_percent, $marquee_text, $tg_link, $fb_link, $ig_link, $wa_link, $popup_enabled, $popup_title, $popup_desc, $popup_image_path, $popup_button_text, $popup_button_link, $theme_bg, $theme_primary, $theme_accent);
    
    if ($stmt->execute()) {
        $msg = "System configuration updated successfully.";
        $msg_type = "success";
    } else {
        error_log('Settings update failed: ' . $conn->error);
        $msg = "Unable to update settings right now.";
        $msg_type = "error";
    }
}

// 2. HANDLE NEW SLIDER UPLOAD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_slider'])) {
    if (isset($_FILES['slider_image'])) {
        $res = wcb_save_uploaded_file($_FILES['slider_image'], "assets/img/banners/", "banner_");
        if (!empty($res['ok']) && !empty($res['filename'])) {
            $saved_slider = $res['filename'];
            $db_path = "assets/img/banners/" . $saved_slider;
            $conn->query("INSERT INTO sliders (image_path, status, sort_order) VALUES ('$db_path', 'active', 0)");
            $msg = "New Banner Uploaded Successfully!";
            $msg_type = "success";
        } else {
            $msg = "Upload Error: " . ($res['error'] ?? 'Failed to save file. Check directory permissions.');
            $msg_type = "error";
        }
    } else {
        $msg = "Please select a valid image file to upload.";
        $msg_type = "error";
    }
}

// 3. HANDLE SLIDER DELETE
if (isset($_GET['delete_slider'])) {
    $slide_id = intval($_GET['delete_slider']);
    $conn->query("DELETE FROM sliders WHERE id=$slide_id");
    header("Location: settings.php");
    exit();
}

// 4. FETCH DATA
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$sliders = $conn->query("SELECT * FROM sliders ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #CBD5E1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background-color: #F1F5F9; }
    </style>
</head>
<body class="bg-gray-100 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 pt-20 lg:pt-20 min-h-screen transition-all duration-300">
        
        <?php include '../includes/header.php'; ?>

        <div class="max-w-5xl mx-auto">

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-6 gap-2">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-cogs text-gray-600"></i> System Configuration
                    </h1>
                    <p class="text-sm text-gray-500">Global platform settings and administrative tools.</p>
                </div>
            </div>

            <?php if($msg): ?>
                <div class="<?php echo $msg_type=='success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'; ?> border-l-4 p-4 rounded-lg mb-6 flex items-center shadow-sm text-sm font-medium">
                    <i class="fas <?php echo $msg_type=='success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2 text-lg"></i> <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                <div class="space-y-6">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_general" value="1">

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">General Settings</h3>
                            </div>
                            <div class="p-6 space-y-4">
                                
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Website Name / Frontend Name</label>
                                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" 
                                        class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-indigo-500 text-gray-800 font-bold transition shadow-sm">
                                    <p class="text-[10px] text-gray-400 mt-1">Frontend homepage, player pages and website title will use this name.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Admin Panel Name</label>
                                    <input type="text" name="admin_panel_name" value="<?php echo htmlspecialchars($settings['admin_panel_name'] ?? ($settings['site_name'] ?? '')); ?>" 
                                        class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-indigo-500 text-gray-800 font-bold transition shadow-sm">
                                    <p class="text-[10px] text-gray-400 mt-1">Admin sidebar/header branding will use this name.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Website / Admin Logo</label>
                                    <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg p-3">
                                        <?php if(!empty($settings['app_logo'])): ?>
                                            <img src="../<?php echo htmlspecialchars($settings['app_logo']); ?>?v=<?php echo time(); ?>" class="h-12 w-12 object-contain rounded bg-white border" alt="Logo">
                                        <?php else: ?>
                                            <div class="h-12 w-12 rounded bg-indigo-100 flex items-center justify-center text-indigo-600"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                        <input type="file" name="app_logo" accept="image/*,.svg" class="text-xs w-full border border-gray-300 rounded p-2 bg-white">
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-1">Upload once and it will show in Admin branding and frontend app/logo areas.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Scrolling Marquee Text</label>
                                    <textarea name="marquee_text" rows="3" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-indigo-500 text-gray-700 transition shadow-sm"><?php echo htmlspecialchars($settings['marquee_text']); ?></textarea>
                                </div>

                                <!-- WEBSITE THEME & BACKGROUND CUSTOMIZER -->
                                <div class="pt-4 border-t border-gray-200 bg-emerald-50/50 p-4 rounded-xl border border-emerald-100 mt-4 space-y-4">
                                    <h4 class="text-xs font-bold text-emerald-800 uppercase tracking-wider flex items-center gap-2">
                                        <i class="fas fa-palette text-emerald-600"></i> Website Theme & Background Customizer
                                    </h4>

                                    <!-- Quick Presets -->
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 uppercase mb-2">Quick Theme Presets (১ ক্লিকে থিম পরিবর্তন):</label>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                            <button type="button" onclick="setPreset('#000000', '#0a2e22', '#f0c030')" class="px-3 py-2 text-xs font-bold text-white bg-black rounded-lg border border-gray-700 shadow-sm hover:scale-95 transition flex items-center gap-2">
                                                <span class="w-3 h-3 rounded-full bg-black border border-white"></span> Pure Black
                                            </button>
                                            <button type="button" onclick="setPreset('linear-gradient(135deg, #071f18 0%, #0a2e22 50%, #071f18 100%)', '#0d7a55', '#f0c030')" class="px-3 py-2 text-xs font-bold text-white bg-emerald-900 rounded-lg border border-emerald-500 shadow-sm hover:scale-95 transition flex items-center gap-2">
                                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span> Emerald Casino
                                            </button>
                                            <button type="button" onclick="setPreset('linear-gradient(135deg, #0a1128 0%, #1c2541 50%, #0a1128 100%)', '#1c2541', '#38bdf8')" class="px-3 py-2 text-xs font-bold text-white bg-slate-900 rounded-lg border border-blue-500 shadow-sm hover:scale-95 transition flex items-center gap-2">
                                                <span class="w-3 h-3 rounded-full bg-blue-500"></span> Midnight Blue
                                            </button>
                                            <button type="button" onclick="setPreset('linear-gradient(135deg, #140d02 0%, #291a05 50%, #140d02 100%)', '#291a05', '#fbbf24')" class="px-3 py-2 text-xs font-bold text-amber-100 bg-amber-950 rounded-lg border border-amber-500 shadow-sm hover:scale-95 transition flex items-center gap-2">
                                                <span class="w-3 h-3 rounded-full bg-amber-400"></span> Dark Gold
                                            </button>
                                            <button type="button" onclick="setPreset('linear-gradient(135deg, #130326 0%, #28064d 50%, #130326 100%)', '#28064d', '#c084fc')" class="px-3 py-2 text-xs font-bold text-purple-100 bg-purple-950 rounded-lg border border-purple-500 shadow-sm hover:scale-95 transition flex items-center gap-2">
                                                <span class="w-3 h-3 rounded-full bg-purple-400"></span> Deep Purple
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Background Color or Gradient CSS</label>
                                        <div class="flex gap-2">
                                            <input type="text" id="theme_bg_input" name="theme_bg" value="<?php echo htmlspecialchars($settings['theme_bg'] ?? 'linear-gradient(135deg, #071f18 0%, #0a2e22 50%, #071f18 100%)'); ?>" class="w-full border border-gray-300 rounded p-2 text-xs font-mono bg-white shadow-sm focus:ring-2 focus:ring-emerald-400">
                                            <input type="color" id="bg_picker" onchange="document.getElementById('theme_bg_input').value=this.value" value="#000000" class="h-9 w-12 rounded cursor-pointer border p-0 bg-white">
                                        </div>
                                        <p class="text-[10px] text-gray-500 mt-1">Accepts solid colors (e.g. #000000) or CSS Gradients (e.g. linear-gradient(...)).</p>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Primary Theme Color</label>
                                            <div class="flex items-center gap-2 bg-white border border-gray-300 rounded p-1">
                                                <input type="color" id="primary_picker" name="theme_primary" onchange="document.getElementById('primary_code').innerText=this.value" value="<?php echo htmlspecialchars($settings['theme_primary'] ?? '#0d7a55'); ?>" class="h-8 w-10 rounded cursor-pointer border p-0">
                                                <span class="text-xs font-mono text-gray-700 font-bold" id="primary_code"><?php echo htmlspecialchars($settings['theme_primary'] ?? '#0d7a55'); ?></span>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Accent / Button Color</label>
                                            <div class="flex items-center gap-2 bg-white border border-gray-300 rounded p-1">
                                                <input type="color" id="accent_picker" name="theme_accent" onchange="document.getElementById('accent_code').innerText=this.value" value="<?php echo htmlspecialchars($settings['theme_accent'] ?? '#f0c030'); ?>" class="h-8 w-10 rounded cursor-pointer border p-0">
                                                <span class="text-xs font-mono text-gray-700 font-bold" id="accent_code"><?php echo htmlspecialchars($settings['theme_accent'] ?? '#f0c030'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <script>
                                function setPreset(bg, primary, accent) {
                                    document.getElementById('theme_bg_input').value = bg;
                                    document.getElementById('primary_picker').value = primary;
                                    document.getElementById('accent_picker').value = accent;
                                    document.getElementById('primary_code').innerText = primary;
                                    document.getElementById('accent_code').innerText = accent;
                                }
                                </script>

                                <div class="pt-4 border-t border-gray-100 bg-yellow-50/50 p-4 rounded-lg border border-yellow-100 mt-2">
                                    <h4 class="text-xs font-bold text-yellow-700 uppercase mb-3 flex items-center gap-2">
                                        <i class="fas fa-bullhorn"></i> Popup Announcement
                                    </h4>
                                    
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between bg-white p-2 rounded border border-gray-200">
                                            <span class="text-sm font-medium text-gray-700">Enable Popup</span>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="popup_enabled" value="1" class="sr-only peer" <?php echo $settings['popup_enabled'] == 1 ? 'checked' : ''; ?>>
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-yellow-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-500"></div>
                                            </label>
                                        </div>

                                        <input type="text" name="popup_title" value="<?php echo htmlspecialchars($settings['popup_title'] ?? ''); ?>" placeholder="Popup Title" class="w-full border border-gray-300 rounded p-2 text-sm">
                                        <textarea name="popup_desc" rows="2" placeholder="Popup Description" class="w-full border border-gray-300 rounded p-2 text-sm"><?php echo htmlspecialchars($settings['popup_desc'] ?? ''); ?></textarea>
                                        <input type="text" name="popup_button_text" value="<?php echo htmlspecialchars($settings['popup_button_text'] ?? ''); ?>" placeholder="Button Text (Optional, e.g. Join Now)" class="w-full border border-gray-300 rounded p-2 text-sm">
                                        <input type="text" name="popup_button_link" value="<?php echo htmlspecialchars($settings['popup_button_link'] ?? ''); ?>" placeholder="Button Link (Optional, e.g. deposit.php)" class="w-full border border-gray-300 rounded p-2 text-sm">
                                        
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Popup Image</label>
                                            <div class="flex items-center gap-3">
                                                <input type="file" name="popup_image" accept="image/*" class="text-xs w-full border border-gray-300 rounded p-1">
                                                <?php if(!empty($settings['popup_image'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($settings['popup_image']); ?>?v=<?php echo time(); ?>" class="h-8 w-8 object-cover rounded border">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-gray-100">
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-3">Social Media Links</label>
                                    
                                    <div class="space-y-3">
                                        <div class="relative">
                                            <i class="fab fa-telegram text-blue-500 absolute left-3 top-3.5 text-lg"></i>
                                            <input type="text" name="telegram_link" value="<?php echo htmlspecialchars($settings['telegram_link'] ?? ''); ?>" placeholder="Telegram Link"
                                                class="w-full pl-10 border border-gray-300 rounded-lg p-3 text-sm focus:outline-none focus:border-indigo-500">
                                        </div>
                                        
                                        <div class="relative">
                                            <i class="fab fa-facebook text-blue-700 absolute left-3 top-3.5 text-lg"></i>
                                            <input type="text" name="facebook_link" value="<?php echo htmlspecialchars($settings['facebook_link'] ?? ''); ?>" placeholder="Facebook Link"
                                                class="w-full pl-10 border border-gray-300 rounded-lg p-3 text-sm focus:outline-none focus:border-indigo-500">
                                        </div>

                                        <div class="relative">
                                            <i class="fab fa-whatsapp text-green-600 absolute left-3 top-3.5 text-lg"></i>
                                            <input type="text" name="whatsapp_link" value="<?php echo htmlspecialchars($settings['whatsapp_link'] ?? ''); ?>" placeholder="WhatsApp Link or Number (e.g. https://wa.me/8801...)"
                                                class="w-full pl-10 border border-gray-300 rounded-lg p-3 text-sm focus:outline-none focus:border-indigo-500">
                                            <p class="text-[10px] text-gray-400 mt-1 ml-1">Dashboard floating WhatsApp support icon link. You can use full link or phone number.</p>
                                        </div>

                                        <div class="relative">
                                            <i class="fab fa-instagram text-pink-600 absolute left-3 top-3.5 text-lg"></i>
                                            <input type="text" name="instagram_link" value="<?php echo htmlspecialchars($settings['instagram_link'] ?? ''); ?>" placeholder="Instagram Link"
                                                class="w-full pl-10 border border-gray-300 rounded-lg p-3 text-sm focus:outline-none focus:border-indigo-500">
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-gray-100">
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-4">
                                        Auto-Lock Withdrawals Threshold
                                    </label>
                                    <div class="flex items-center gap-4">
                                        <div class="w-full relative">
                                            <input type="range" name="risk_auto_lock_percent" min="10" max="100" 
                                                value="<?php echo $settings['risk_auto_lock_percent']; ?>" 
                                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                                                oninput="document.getElementById('riskVal').innerText = this.value + '%'">
                                        </div>
                                        <div class="bg-indigo-50 border border-indigo-100 rounded-lg w-20 h-12 flex items-center justify-center shrink-0">
                                            <span id="riskVal" class="text-xl font-bold text-indigo-700">
                                                <?php echo $settings['risk_auto_lock_percent']; ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right pt-2">
                                    <button type="submit" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-xl font-bold shadow-lg transition transform active:scale-95 flex items-center justify-center gap-2">
                                        <i class="fas fa-save"></i> Save Configuration
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Banner Sliders</h3>
                            <span class="bg-blue-100 text-blue-700 text-[10px] px-2 py-1 rounded-full font-bold uppercase">App & Web</span>
                        </div>
                        
                        <div class="p-6">
                            <div class="space-y-4 mb-6 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                                <?php if($sliders && $sliders->num_rows > 0): ?>
                                    <?php while($row = $sliders->fetch_assoc()): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100 group hover:border-indigo-200 transition">
                                        <div class="flex items-center gap-3">
                                            <img src="../<?php echo $row['image_path']; ?>" class="w-24 h-12 object-cover rounded border border-gray-200 shadow-sm" alt="Banner">
                                            <div class="flex-1 overflow-hidden">
                                                <p class="text-xs text-gray-500 truncate w-32"><?php echo basename($row['image_path']); ?></p>
                                                <span class="text-[10px] bg-green-100 text-green-600 px-1.5 rounded font-bold">Active</span>
                                            </div>
                                        </div>
                                        <a href="?delete_slider=<?php echo $row['id']; ?>" onclick="return confirm('Delete this banner?')" class="text-red-400 hover:text-red-600 p-2 bg-white rounded-full shadow-sm border border-gray-100 hover:bg-red-50 transition">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-8 border-2 border-dashed border-gray-200 rounded-lg">
                                        <i class="far fa-images text-gray-300 text-3xl mb-2"></i>
                                        <p class="text-gray-400 text-xs">No banners uploaded yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="bg-blue-50 p-5 rounded-xl border border-blue-100">
                                <h4 class="text-xs font-bold text-blue-800 uppercase mb-3">Upload New Banner</h4>
                                <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-3">
                                    <input type="hidden" name="upload_slider" value="1">
                                    <div class="relative group">
                                        <input type="file" name="slider_image" required accept="image/*" class="w-full text-sm text-gray-500 file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-white file:text-blue-700 hover:file:bg-blue-100 transition cursor-pointer border border-blue-200 rounded-lg bg-white p-1 shadow-sm">
                                    </div>
                                    <div class="flex justify-between items-center mt-1">
                                        <p class="text-[10px] text-gray-500">Size: 1200x400px (JPG, PNG)</p>
                                        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-xs font-bold hover:bg-blue-700 transition shadow-md flex items-center gap-2">
                                            <i class="fas fa-cloud-upload-alt"></i> Upload
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </main>

</body>
</html>