<?php
session_start();
require '../includes/auth_session.php';
require '../includes/db.php';
require_once '../includes/vip_system_helper.php';
wcb_vip_ensure_schema($conn);

$message = '';
$msg_type = 'success';

if (isset($_GET['delete_level'])) {
    $id = intval($_GET['delete_level']);
    $stmt = $conn->prepare("DELETE FROM vip_levels WHERE id=? AND required_xp>0");
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    header('Location: vip_settings.php?msg=deleted');
    exit;
}

if (isset($_GET['toggle_level'])) {
    $id = intval($_GET['toggle_level']);
    $stmt = $conn->prepare("UPDATE vip_levels SET is_active=IF(is_active=1,0,1), updated_at=NOW() WHERE id=?");
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    header('Location: vip_settings.php?msg=updated');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $enabled = isset($_POST['is_enabled']) ? 1 : 0;
        $xp_source = $_POST['xp_source'] ?? 'turnover';
        if (!in_array($xp_source, array('turnover','deposit','both'), true)) { $xp_source = 'turnover'; }
        $xp_per_amount = max(0, round((float)($_POST['xp_per_amount'] ?? 1), 4));
        $vp_per_amount = max(0, round((float)($_POST['vp_per_amount'] ?? 1), 4));
        $conversion_ratio = max(1, round((float)($_POST['conversion_ratio'] ?? 60), 2));
        $min_convert_points = max(1, intval($_POST['min_convert_points'] ?? 10));
        $stmt = $conn->prepare("UPDATE vip_settings SET is_enabled=?, xp_source=?, xp_per_amount=?, vp_per_amount=?, conversion_ratio=?, min_convert_points=?, updated_at=NOW() WHERE id=1");
        if ($stmt) {
            $stmt->bind_param('isdddi', $enabled, $xp_source, $xp_per_amount, $vp_per_amount, $conversion_ratio, $min_convert_points);
            $ok = $stmt->execute();
            $stmt->close();
            $message = $ok ? 'VIP settings updated.' : 'Settings update failed.';
            $msg_type = $ok ? 'success' : 'error';
        } else {
            $message = 'Settings update failed.';
            $msg_type = 'error';
        }
    }

    if ($action === 'save_level') {
        $id = intval($_POST['id'] ?? 0);
        $level_name = trim($_POST['level_name'] ?? 'VIP');
        $required_xp = max(0, round((float)($_POST['required_xp'] ?? 0), 2));
        $reward_amount = 0.00;
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if ($level_name === '') { $level_name = 'VIP'; }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE vip_levels SET level_name=?, required_xp=?, reward_amount=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('sddiii', $level_name, $required_xp, $reward_amount, $sort_order, $is_active, $id);
                $ok = $stmt->execute();
                $stmt->close();
                $message = $ok ? 'VIP level updated.' : 'Level update failed.';
                $msg_type = $ok ? 'success' : 'error';
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO vip_levels (level_name, required_xp, reward_amount, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param('sddii', $level_name, $required_xp, $reward_amount, $is_active, $sort_order);
                $ok = $stmt->execute();
                $stmt->close();
                $message = $ok ? 'VIP level added.' : 'Level add failed.';
                $msg_type = $ok ? 'success' : 'error';
            }
        }
    }

    if ($action === 'add_points') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $points = intval($_POST['points'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Admin adjustment');
        $admin_id = intval($_SESSION['admin_id'] ?? 0);
        if ($user_id > 0 && $points != 0) {
            $check = $conn->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
            if ($check) {
                $check->bind_param('i', $user_id);
                $check->execute();
                $res = $check->get_result();
                $check->close();
                if ($res && $res->num_rows > 0) {
                    $stmt = $conn->prepare("INSERT INTO vip_point_adjustments (user_id, points, admin_id, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                    if ($stmt) {
                        $stmt->bind_param('iiis', $user_id, $points, $admin_id, $reason);
                        $ok = $stmt->execute();
                        $stmt->close();
                        $message = $ok ? 'VIP points updated.' : 'Point update failed.';
                        $msg_type = $ok ? 'success' : 'error';
                    }
                } else {
                    $message = 'User not found.';
                    $msg_type = 'error';
                }
            }
        } else {
            $message = 'Invalid point adjustment.';
            $msg_type = 'error';
        }
    }
}

if (isset($_GET['msg']) && !$message) { $message = 'Action completed successfully.'; }
$settings = wcb_vip_settings($conn);
$levels = wcb_vip_levels($conn, false);
$stats = wcb_vip_stats($conn);
$conversions = wcb_vip_recent_conversions($conn, 0, 25);
$edit = null;
if (isset($_GET['edit_level'])) {
    $id = intval($_GET['edit_level']);
    $stmt = $conn->prepare("SELECT * FROM vip_levels WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) { $edit = $res->fetch_assoc(); }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIP Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-slate-800">
<?php include '../includes/sidebar_admin.php'; ?>
<main class="lg:ml-64 p-4 md:p-6 min-h-screen">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900 flex items-center gap-2"><i class="fas fa-crown text-yellow-500"></i> VIP Settings</h1>
            <p class="text-sm text-gray-500 mt-1">Control VIP levels, VP conversion and user VIP points.</p>
        </div>
        <a href="../player/vip.php" target="_blank" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-yellow-500 text-white text-sm font-black shadow hover:bg-yellow-600"><i class="fas fa-eye mr-2"></i> Preview User Page</a>
    </div>

    <?php if($message): ?>
    <div class="mb-5 rounded-xl border p-4 <?php echo $msg_type==='success'?'bg-green-50 border-green-200 text-green-700':'bg-red-50 border-red-200 text-red-700'; ?> font-bold text-sm">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Status</p><h3 class="text-2xl font-black <?php echo intval($settings['is_enabled'])===1?'text-green-600':'text-red-600'; ?>"><?php echo intval($settings['is_enabled'])===1?'ON':'OFF'; ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Active Levels</p><h3 class="text-2xl font-black text-yellow-600"><?php echo number_format((float)$stats['active_levels']); ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Conversions</p><h3 class="text-2xl font-black"><?php echo number_format((float)$stats['total_conversions']); ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">VP Converted</p><h3 class="text-2xl font-black text-blue-600"><?php echo number_format((float)$stats['total_points']); ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Paid</p><h3 class="text-2xl font-black text-green-600">৳<?php echo number_format((float)$stats['total_paid'], 2); ?></h3></div>
    </div>

    <form method="POST" class="bg-white rounded-2xl border shadow-sm p-6 mb-6">
        <input type="hidden" name="action" value="save_settings">
        <div class="flex items-center justify-between gap-4 border-b pb-4 mb-5">
            <h2 class="font-black text-lg">Global Control</h2>
            <label class="flex items-center gap-2 font-black text-sm"><input type="checkbox" name="is_enabled" <?php echo intval($settings['is_enabled'])===1?'checked':''; ?>> VIP ON</label>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">XP Source</label>
                <select name="xp_source" class="w-full border rounded-xl px-4 py-3 font-bold bg-white outline-none focus:ring-2 focus:ring-yellow-300">
                    <?php $src = $settings['xp_source'] ?? 'turnover'; ?>
                    <option value="turnover" <?php echo $src==='turnover'?'selected':''; ?>>Turnover</option>
                    <option value="deposit" <?php echo $src==='deposit'?'selected':''; ?>>Deposit</option>
                    <option value="both" <?php echo $src==='both'?'selected':''; ?>>Both</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">XP Per 1 Tk</label>
                <input type="number" step="0.0001" min="0" name="xp_per_amount" value="<?php echo htmlspecialchars((string)$settings['xp_per_amount'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">VP Per 1 Tk</label>
                <input type="number" step="0.0001" min="0" name="vp_per_amount" value="<?php echo htmlspecialchars((string)$settings['vp_per_amount'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">VP For ৳1</label>
                <input type="number" step="0.01" min="1" name="conversion_ratio" value="<?php echo htmlspecialchars((string)$settings['conversion_ratio'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Minimum Convert VP</label>
                <input type="number" min="1" name="min_convert_points" value="<?php echo htmlspecialchars((string)$settings['min_convert_points'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
            </div>
        </div>
        <button class="mt-5 bg-slate-900 hover:bg-black text-white rounded-xl px-5 py-3 font-black shadow">Save Settings</button>
    </form>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <form method="POST" class="bg-white rounded-2xl border shadow-sm p-6 space-y-4">
            <input type="hidden" name="action" value="save_level">
            <input type="hidden" name="id" value="<?php echo intval($edit['id'] ?? 0); ?>">
            <h2 class="font-black text-lg border-b pb-3"><?php echo $edit ? 'Edit VIP Level' : 'Add VIP Level'; ?></h2>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Level Name</label>
                <input type="text" name="level_name" required value="<?php echo htmlspecialchars($edit['level_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="GOLD" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Required XP</label>
                <input type="number" step="0.01" min="0" name="required_xp" required value="<?php echo htmlspecialchars((string)($edit['required_xp'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
            </div>
            <div class="grid grid-cols-2 gap-3 items-center">
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="<?php echo htmlspecialchars((string)($edit['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
                </div>
                <label class="flex items-center gap-2 mt-6 font-bold text-sm"><input type="checkbox" name="is_active" <?php echo (!isset($edit['is_active']) || intval($edit['is_active'])===1)?'checked':''; ?>> Active</label>
            </div>
            <button class="w-full bg-yellow-500 hover:bg-yellow-600 text-white rounded-xl py-3 font-black shadow"><?php echo $edit ? 'Update Level' : 'Add Level'; ?></button>
            <?php if($edit): ?><a href="vip_settings.php" class="block text-center text-sm font-bold text-gray-500">Cancel Edit</a><?php endif; ?>
        </form>

        <div class="xl:col-span-2 bg-white rounded-2xl border shadow-sm overflow-hidden">
            <div class="p-5 border-b flex items-center justify-between"><h2 class="font-black text-lg">VIP Levels</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="p-3 text-left">Level</th><th class="p-3 text-right">Required XP</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y">
                    <?php if(count($levels)>0): foreach($levels as $r): ?>
                        <tr>
                            <td class="p-3"><div class="font-black"><?php echo htmlspecialchars($r['level_name'], ENT_QUOTES, 'UTF-8'); ?></div><div class="text-xs text-gray-400">Sort: <?php echo intval($r['sort_order']); ?></div></td>
                            <td class="p-3 text-right font-bold"><?php echo number_format((float)$r['required_xp'], 2); ?></td>
                            <td class="p-3 text-center"><?php echo intval($r['is_active'])===1 ? '<span class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs font-black">Active</span>' : '<span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-xs font-black">Disabled</span>'; ?></td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a href="?edit_level=<?php echo intval($r['id']); ?>" class="text-blue-600 font-bold mr-3">Edit</a>
                                <a href="?toggle_level=<?php echo intval($r['id']); ?>" class="text-amber-600 font-bold mr-3">Toggle</a>
                                <a href="?delete_level=<?php echo intval($r['id']); ?>" onclick="return confirm('Delete this level?')" class="text-red-600 font-bold">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="p-8 text-center text-gray-400 font-bold">No level found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <form method="POST" class="bg-white rounded-2xl border shadow-sm p-6 space-y-4">
            <input type="hidden" name="action" value="add_points">
            <h2 class="font-black text-lg border-b pb-3">Manual VP Adjustment</h2>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">User ID</label>
                <input type="number" min="1" name="user_id" required class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Points</label>
                <input type="number" name="points" required placeholder="100 or -100" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Reason</label>
                <input type="text" name="reason" value="Admin adjustment" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-yellow-300">
            </div>
            <button class="w-full bg-slate-900 hover:bg-black text-white rounded-xl py-3 font-black shadow">Update VP</button>
        </form>

        <div class="xl:col-span-2 bg-white rounded-2xl border shadow-sm overflow-hidden">
            <div class="p-5 border-b flex items-center justify-between"><h2 class="font-black text-lg">Recent Conversions</h2><span class="text-xs font-bold text-gray-400">Today Paid: ৳<?php echo number_format((float)$stats['today_paid'], 2); ?></span></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="p-3 text-left">User</th><th class="p-3 text-right">Points</th><th class="p-3 text-right">Money</th><th class="p-3 text-right">Balance After</th><th class="p-3 text-left">Date</th></tr></thead>
                    <tbody class="divide-y">
                    <?php if(!empty($conversions)): foreach($conversions as $c): ?>
                        <tr>
                            <td class="p-3 font-bold"><?php echo htmlspecialchars($c['username'] ?: ($c['phone'] ?: ('User #'.$c['user_id'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="p-3 text-right font-black text-blue-600"><?php echo number_format((int)$c['points']); ?></td>
                            <td class="p-3 text-right font-black text-green-600">৳<?php echo number_format((float)$c['real_amount'], 2); ?></td>
                            <td class="p-3 text-right font-bold">৳<?php echo number_format((float)$c['balance_after'], 2); ?></td>
                            <td class="p-3 text-gray-500"><?php echo htmlspecialchars($c['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="p-8 text-center text-gray-400 font-bold">No conversion found yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>
