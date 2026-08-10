<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/maintenance_guard.php';

maintenance_ensure_settings_columns($conn);

$msg = "";
$msg_type = "success";

function admin_validate_color($color) {
    $color = trim((string)$color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#ffcc00';
}

function admin_upload_maintenance_image($current_path) {
    if (!isset($_FILES['maintenance_image']) || $_FILES['maintenance_image']['error'] !== UPLOAD_ERR_OK) {
        return $current_path;
    }

    $target_dir = "../assets/img/maintenance/";
    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0777, true);
    }

    $original = $_FILES['maintenance_image']['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        return $current_path;
    }

    $new_name = 'maintenance_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $target_file = $target_dir . $new_name;
    if (move_uploaded_file($_FILES['maintenance_image']['tmp_name'], $target_file)) {
        return 'assets/img/maintenance/' . $new_name;
    }
    return $current_path;
}

// 1. HANDLE SETTINGS UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $maintenance = isset($_POST['maintenance_mode']) ? 1 : 0;
    $vpn_block = isset($_POST['vpn_block']) ? 1 : 0;
    $countries = sanitize($conn, $_POST['blocked_countries'] ?? '');
    $auto_lock = intval($_POST['auto_lock_percent'] ?? 80);
    if ($auto_lock < 0) $auto_lock = 0;
    if ($auto_lock > 100) $auto_lock = 100;

    $maintenance_message = trim($_POST['maintenance_message'] ?? '');
    if ($maintenance_message === '') {
        $maintenance_message = 'আমাদের ওয়েবসাইট বর্তমানে Maintenance কাজের জন্য সাময়িক বন্ধ আছে। কিছু সময় পর আবার চেষ্টা করুন।';
    }
    $maintenance_warning_text = trim($_POST['maintenance_warning_text'] ?? '');
    if ($maintenance_warning_text === '') {
        $maintenance_warning_text = 'Website Under Maintenance';
    }
    $maintenance_text_color = admin_validate_color($_POST['maintenance_text_color'] ?? '#ffcc00');

    $current = maintenance_fetch_settings($conn);
    $maintenance_image = admin_upload_maintenance_image($current['maintenance_image'] ?? '');

    // Update Settings (ID 1)
    $stmt = $conn->prepare("UPDATE settings SET maintenance_mode=?, maintenance_message=?, maintenance_warning_text=?, maintenance_text_color=?, maintenance_image=?, vpn_block_enabled=?, blocked_countries=?, risk_auto_lock_percent=? WHERE id=1");
    if ($stmt) {
        $stmt->bind_param("issssisi", $maintenance, $maintenance_message, $maintenance_warning_text, $maintenance_text_color, $maintenance_image, $vpn_block, $countries, $auto_lock);
        if ($stmt->execute()) {
            $msg = "Security and Maintenance settings updated successfully.";
            $msg_type = "success";
        } else {
            $msg = "Error saving settings. Please try again.";
            $msg_type = "error";
        }
    } else {
        $msg = "Database prepare failed. Please check settings table columns.";
        $msg_type = "error";
    }
}

// 2. HANDLE MANUAL BAN
if (isset($_GET['ban_user'])) {
    $uid = intval($_GET['ban_user']);
    $conn->query("UPDATE users SET status='banned' WHERE id=$uid");
    header("Location: risk_control.php?msg=banned"); exit();
}

// FETCH DATA
$settings = maintenance_fetch_settings($conn);
$high_risk_users = $conn->query("SELECT u.id, u.username, u.email, p.risk_score, p.last_ip, p.country 
                                 FROM users u JOIN player_profiles p ON u.id = p.user_id 
                                 WHERE p.risk_score >= 50 AND u.status = 'active' 
                                 ORDER BY p.risk_score DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk & Security | BetPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-shield-alt text-red-600"></i> Security Center
                </h1>
                <p class="text-sm text-gray-500">Manage global access rules, maintenance mode and fraud prevention.</p>
            </div>
            <a href="../maintenance.php" target="_blank" class="bg-yellow-100 text-yellow-800 border border-yellow-200 px-4 py-2 rounded-lg text-xs font-bold hover:bg-yellow-200 transition">
                <i class="fas fa-eye mr-1"></i> Preview Maintenance Page
            </a>
        </div>

        <?php if(!empty($msg)): ?>
            <div class="<?php echo $msg_type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'; ?> border-l-4 p-4 rounded-lg mb-6 flex items-center shadow-sm">
                <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
            <input type="hidden" name="update_settings" value="1">

            <div class="bg-white rounded-xl shadow-sm border border-red-100 overflow-hidden">
                <div class="bg-red-50 px-6 py-4 border-b border-red-100 flex justify-between items-center">
                    <h3 class="font-bold text-red-800 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i> EMERGENCY LOCKDOWN
                    </h3>
                    <span class="bg-red-200 text-red-800 text-[10px] px-2 py-1 rounded font-bold uppercase">Critical</span>
                </div>
                <div class="p-6 space-y-5">
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Enabling <b>Maintenance Mode</b> will hide all public/player/agent pages and show only the maintenance screen. Admin panel will remain accessible.
                    </p>
                    <label class="flex items-center cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" name="maintenance_mode" class="sr-only" <?php echo !empty($settings['maintenance_mode']) ? 'checked' : ''; ?>>
                            <div class="block bg-gray-200 w-14 h-8 rounded-full transition-colors toggle-bg group-hover:bg-gray-300"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition transform shadow-sm"></div>
                        </div>
                        <div class="ml-3 text-gray-700 font-bold group-hover:text-red-600 transition">Enable Maintenance Mode</div>
                    </label>

                    <div class="grid grid-cols-1 gap-4 pt-4 border-t border-red-100">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Maintenance Warning Text</label>
                            <input type="text" name="maintenance_warning_text" value="<?php echo htmlspecialchars($settings['maintenance_warning_text'] ?? 'Website Under Maintenance'); ?>"
                                   class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-red-500 text-sm font-semibold"
                                   placeholder="Website Under Maintenance">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Maintenance Message</label>
                            <textarea name="maintenance_message" rows="4" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-red-500 text-sm leading-relaxed" placeholder="Write maintenance reason/message here..."><?php echo htmlspecialchars($settings['maintenance_message'] ?? 'আমাদের ওয়েবসাইট বর্তমানে Maintenance কাজের জন্য সাময়িক বন্ধ আছে। কিছু সময় পর আবার চেষ্টা করুন।'); ?></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Text Color</label>
                                <div class="flex gap-2">
                                    <input type="color" name="maintenance_text_color" value="<?php echo htmlspecialchars($settings['maintenance_text_color'] ?? '#ffcc00'); ?>" class="h-12 w-16 border border-gray-300 rounded-lg bg-white p-1">
                                    <input type="text" value="<?php echo htmlspecialchars($settings['maintenance_text_color'] ?? '#ffcc00'); ?>" readonly class="flex-1 border border-gray-300 rounded-lg p-3 text-sm font-mono bg-gray-50">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Maintenance Image</label>
                                <input type="file" name="maintenance_image" accept="image/*" class="w-full border border-gray-300 rounded-lg p-2 text-xs bg-white">
                                <p class="text-[10px] text-gray-400 mt-1">JPG, PNG, GIF, WEBP supported.</p>
                            </div>
                        </div>

                        <?php if(!empty($settings['maintenance_image'])): ?>
                            <div class="bg-gray-50 border border-gray-100 rounded-lg p-3 flex items-center gap-3">
                                <img src="../<?php echo htmlspecialchars($settings['maintenance_image']); ?>" class="h-16 w-20 object-contain rounded border bg-white" alt="Maintenance Image">
                                <div>
                                    <div class="text-xs font-bold text-gray-700">Current Image</div>
                                    <div class="text-[10px] text-gray-400 break-all"><?php echo htmlspecialchars($settings['maintenance_image']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-blue-100 overflow-hidden">
                <div class="bg-blue-50 px-6 py-4 border-b border-blue-100 flex justify-between items-center">
                    <h3 class="font-bold text-blue-800 flex items-center gap-2">
                        <i class="fas fa-globe-americas"></i> VPN Detection
                    </h3>
                    <span class="bg-blue-200 text-blue-800 text-[10px] px-2 py-1 rounded font-bold uppercase">AI Engine</span>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-4 leading-relaxed">
                        Automatically flag or block users connecting via Tor nodes, Data Centers, or known VPN IP addresses.
                    </p>
                    <label class="flex items-center cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" name="vpn_block" class="sr-only" <?php echo !empty($settings['vpn_block_enabled']) ? 'checked' : ''; ?>>
                            <div class="block bg-gray-200 w-14 h-8 rounded-full transition-colors toggle-bg-blue group-hover:bg-gray-300"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition transform shadow-sm"></div>
                        </div>
                        <div class="ml-3 text-gray-700 font-bold group-hover:text-blue-600 transition">Block VPN Connections</div>
                    </label>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 xl:col-span-2 p-6">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-map-marked-alt text-gray-400"></i> Geo-IP Rules
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Blocked Countries (ISO Codes)</label>
                        <input type="text" name="blocked_countries" value="<?php echo htmlspecialchars($settings['blocked_countries'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-red-500 uppercase font-mono text-sm"
                               placeholder="US, IL, NK">
                        <p class="text-[10px] text-gray-400 mt-2">Comma separated (e.g. US, UK, IN). Users from these regions will be blocked.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Auto-Lock Risk Threshold</label>
                        <div class="flex items-center gap-2">
                            <input type="number" min="0" max="100" name="auto_lock_percent" value="<?php echo intval($settings['risk_auto_lock_percent'] ?? 80); ?>" 
                                   class="w-24 border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-red-500 font-bold text-center">
                            <span class="text-gray-600 font-bold">%</span>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2">Withdrawals are auto-locked if user risk score exceeds this %.</p>
                    </div>
                </div>
                <div class="mt-6 text-right">
                    <button type="submit" class="bg-slate-900 hover:bg-black text-white px-6 py-2.5 rounded-lg font-bold shadow-lg transition transform active:scale-95 text-sm">
                        Save Security & Maintenance Settings
                    </button>
                </div>
            </div>
        </form>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h3 class="font-bold text-gray-800 text-sm uppercase flex items-center gap-2">
                    <i class="fas fa-user-secret text-red-500"></i> High Risk Detected Users
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse whitespace-nowrap">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-bold">
                        <tr>
                            <th class="px-6 py-3">User</th>
                            <th class="px-6 py-3">Risk Score</th>
                            <th class="px-6 py-3">Location (IP)</th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if($high_risk_users && $high_risk_users->num_rows > 0): ?>
                            <?php while($u = $high_risk_users->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-bold text-gray-700">
                                    <?php echo htmlspecialchars($u['username']); ?>
                                    <div class="text-[10px] text-gray-400 font-normal"><?php echo wcb_public_email_html($u['email'] ?? ''); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="bg-red-100 text-red-700 px-2 py-1 rounded font-bold text-xs">
                                        <?php echo intval($u['risk_score']); ?>%
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    <?php echo htmlspecialchars($u['country'] ?? ''); ?> <span class="font-mono text-[10px]">(<?php echo htmlspecialchars($u['last_ip'] ?? ''); ?>)</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="?ban_user=<?php echo intval($u['id']); ?>" onclick="return confirm('Ban this high-risk user?')" 
                                       class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-bold transition">
                                        BAN USER
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="p-6 text-center text-gray-400 text-xs">No high-risk users detected.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <style>
        /* Custom Toggles */
        input:checked ~ .toggle-bg { background-color: #ef4444; }
        input:checked ~ .toggle-bg-blue { background-color: #3b82f6; }
        input:checked ~ .dot { transform: translateX(100%); }
    </style>
</body>
</html>
