<?php
session_start();
require '../includes/auth_session.php';
require '../includes/db.php';
require_once '../includes/referral_system_helper.php';
wcb_referral_ensure_schema($conn);

$message = '';
$msg_type = 'success';

if (isset($_GET['delete_level'])) {
    $id = intval($_GET['delete_level']);
    $stmt = $conn->prepare("DELETE FROM referral_level_rules WHERE id=?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
    header('Location: referral_management.php?msg=deleted');
    exit;
}

if (isset($_GET['toggle_level'])) {
    $id = intval($_GET['toggle_level']);
    $stmt = $conn->prepare("UPDATE referral_level_rules SET is_active=IF(is_active=1,0,1), updated_at=NOW() WHERE id=?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
    header('Location: referral_management.php?msg=updated');
    exit;
}

if (isset($_GET['run_pending'])) {
    $awarded = wcb_referral_run_pending_awards($conn, 500);
    $message = 'Referral check completed. Milestone rows: ' . intval($awarded);
    $msg_type = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_settings') {
        $enabled = isset($_POST['is_enabled']) ? 1 : 0;
        $min_deposit = max(0, round((float)($_POST['min_deposit_amount'] ?? 100), 2));
        $claim_mode = ($_POST['claim_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
        $stmt = $conn->prepare("UPDATE referral_settings SET is_enabled=?, min_deposit_amount=?, claim_mode=?, updated_at=NOW() WHERE id=1");
        if ($stmt) {
            $stmt->bind_param('ids', $enabled, $min_deposit, $claim_mode);
            $ok = $stmt->execute();
            $message = $ok ? 'Referral settings updated.' : 'Settings update failed.';
            $msg_type = $ok ? 'success' : 'error';
        }
    }
    if ($action === 'save_level') {
        $id = intval($_POST['id'] ?? 0);
        $level_no = max(1, intval($_POST['level_no'] ?? 1));
        $referral_limit = max(1, intval($_POST['referral_limit'] ?? 1));
        $bonus_amount = max(0, round((float)($_POST['bonus_amount'] ?? 0), 2));
        $sort_order = intval($_POST['sort_order'] ?? $level_no);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE referral_level_rules SET level_no=?, referral_limit=?, bonus_amount=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('iidiii', $level_no, $referral_limit, $bonus_amount, $active, $sort_order, $id);
                $ok = $stmt->execute();
                $message = $ok ? 'Referral level updated.' : 'Level update failed. Level number may already exist.';
                $msg_type = $ok ? 'success' : 'error';
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO referral_level_rules (level_no, referral_limit, bonus_amount, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE referral_limit=VALUES(referral_limit), bonus_amount=VALUES(bonus_amount), is_active=VALUES(is_active), sort_order=VALUES(sort_order), updated_at=NOW()");
            if ($stmt) {
                $stmt->bind_param('iidii', $level_no, $referral_limit, $bonus_amount, $active, $sort_order);
                $ok = $stmt->execute();
                $message = $ok ? 'Referral level saved.' : 'Level save failed.';
                $msg_type = $ok ? 'success' : 'error';
            }
        }
    }
}

if (isset($_GET['msg']) && !$message) { $message = 'Action completed successfully.'; }

$edit = null;
if (isset($_GET['edit_level'])) {
    $id = intval($_GET['edit_level']);
    $stmt = $conn->prepare("SELECT * FROM referral_level_rules WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) { $edit = $res->fetch_assoc(); }
    }
}

$settings = wcb_referral_settings($conn);
$levels = wcb_referral_levels($conn, false);
$next_level = 1;
foreach ($levels as $lv) { $next_level = max($next_level, intval($lv['level_no']) + 1); }
$stats = array('total_referrals'=>0,'qualified'=>0,'total_paid'=>0,'today_paid'=>0,'today_count'=>0);
$res = @$conn->query("SELECT COUNT(*) AS total FROM users WHERE COALESCE(referrer_id,0)>0");
if ($res && $res->num_rows > 0) { $stats['total_referrals'] = intval($res->fetch_assoc()['total']); }
$res = @$conn->query("SELECT SUM(CASE WHEN status='credited' THEN 1 ELSE 0 END) AS qualified, COALESCE(SUM(CASE WHEN status='credited' THEN bonus_amount ELSE 0 END),0) AS total_paid, COALESCE(SUM(CASE WHEN status='credited' AND DATE(COALESCE(claimed_at,created_at))=CURDATE() THEN bonus_amount ELSE 0 END),0) AS today_paid, SUM(CASE WHEN status='credited' AND DATE(COALESCE(claimed_at,created_at))=CURDATE() THEN 1 ELSE 0 END) AS today_count FROM referral_bonus_history WHERE source_user_id=0");
if ($res && $res->num_rows > 0) { $stats = array_merge($stats, $res->fetch_assoc()); }
$history = @$conn->query("SELECT h.*, inviter.username AS inviter_username, inviter.phone AS inviter_phone FROM referral_bonus_history h LEFT JOIN users inviter ON inviter.id=h.inviter_id WHERE h.source_user_id=0 ORDER BY h.created_at DESC LIMIT 150");
$recent_referrals = @$conn->query("SELECT u.id, u.username, u.phone, u.referrer_id, u.created_at, r.username AS ref_username, r.phone AS ref_phone, COALESCE(SUM(CASE WHEN t.type='deposit' AND t.status='approved' THEN t.amount ELSE 0 END),0) AS deposit_total FROM users u LEFT JOIN users r ON r.id=u.referrer_id LEFT JOIN transactions_fake t ON t.user_id=u.id WHERE COALESCE(u.referrer_id,0)>0 GROUP BY u.id ORDER BY u.id DESC LIMIT 80");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-slate-800">
<?php include '../includes/sidebar_admin.php'; ?>
<main class="lg:ml-64 p-5 lg:p-7 min-h-screen">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900 flex items-center gap-2"><i class="fas fa-user-plus text-green-600"></i> Referral Management</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="?run_pending=1" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-bold"><i class="fas fa-rotate mr-2"></i> Run Check</a>
            <a href="../player/referral.php" target="_blank" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold"><i class="fas fa-eye mr-2"></i> User Page</a>
        </div>
    </div>

    <?php if($message): ?>
        <div class="mb-5 rounded-xl border p-4 <?php echo $msg_type==='success'?'bg-green-50 border-green-200 text-green-700':'bg-red-50 border-red-200 text-red-700'; ?> font-bold text-sm"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Status</p><h3 class="text-2xl font-black <?php echo intval($settings['is_enabled'])===1?'text-green-600':'text-red-600'; ?>"><?php echo intval($settings['is_enabled'])===1?'ON':'OFF'; ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Total Referrals</p><h3 class="text-2xl font-black"><?php echo number_format((float)$stats['total_referrals']); ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Completed Levels</p><h3 class="text-2xl font-black text-blue-600"><?php echo number_format((float)$stats['qualified']); ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Total Paid</p><h3 class="text-2xl font-black text-green-600">৳<?php echo number_format((float)$stats['total_paid'],2); ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Today Paid</p><h3 class="text-2xl font-black text-amber-600">৳<?php echo number_format((float)$stats['today_paid'],2); ?></h3><p class="text-xs text-gray-400 mt-1"><?php echo number_format((float)$stats['today_count']); ?> claims</p></div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <div class="space-y-6">
            <form method="POST" class="bg-white rounded-2xl border shadow-sm p-6">
                <input type="hidden" name="action" value="save_settings">
                <div class="flex items-center justify-between border-b pb-4 mb-5">
                    <h2 class="font-black text-lg">Global Settings</h2>
                    <label class="flex items-center gap-2 text-sm font-black"><input type="checkbox" name="is_enabled" <?php echo intval($settings['is_enabled'])===1?'checked':''; ?>> ON</label>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">Minimum First Deposit</label>
                        <input type="number" step="0.01" min="0" name="min_deposit_amount" value="<?php echo htmlspecialchars((string)$settings['min_deposit_amount'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-green-300">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">Claim System</label>
                        <select name="claim_mode" class="w-full border rounded-xl px-4 py-3 font-bold bg-white outline-none focus:ring-2 focus:ring-green-300">
                            <option value="auto" <?php echo ($settings['claim_mode'] ?? 'auto')==='auto'?'selected':''; ?>>Auto Add</option>
                            <option value="manual" <?php echo ($settings['claim_mode'] ?? 'auto')==='manual'?'selected':''; ?>>User Claim Button</option>
                        </select>
                    </div>
                    <button class="w-full bg-green-600 hover:bg-green-700 text-white rounded-xl py-3 font-black shadow">Save Settings</button>
                </div>
            </form>

            <form method="POST" class="bg-white rounded-2xl border shadow-sm p-6">
                <input type="hidden" name="action" value="save_level">
                <input type="hidden" name="id" value="<?php echo intval($edit['id'] ?? 0); ?>">
                <h2 class="font-black text-lg border-b pb-3 mb-4"><?php echo $edit ? 'Edit Referral Level' : 'Create Referral Level'; ?></h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Level Number</label>
                            <input type="number" min="1" name="level_no" required value="<?php echo htmlspecialchars((string)($edit['level_no'] ?? $next_level), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-green-300">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Required Referral</label>
                            <input type="number" min="1" name="referral_limit" required value="<?php echo htmlspecialchars((string)($edit['referral_limit'] ?? 3), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-green-300">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">Bonus Amount</label>
                        <input type="number" step="0.01" min="0" name="bonus_amount" required value="<?php echo htmlspecialchars((string)($edit['bonus_amount'] ?? 100), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-green-300">
                    </div>
                    <div class="grid grid-cols-2 gap-3 items-center">
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Sort Order</label>
                            <input type="number" name="sort_order" value="<?php echo htmlspecialchars((string)($edit['sort_order'] ?? $next_level), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-green-300">
                        </div>
                        <label class="flex items-center gap-2 mt-6 font-bold text-sm"><input type="checkbox" name="is_active" <?php echo (!isset($edit['is_active']) || intval($edit['is_active'])===1)?'checked':''; ?>> Active</label>
                    </div>
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-3 font-black shadow"><?php echo $edit ? 'Update Level' : 'Add Level'; ?></button>
                    <?php if($edit): ?><a href="referral_management.php" class="block text-center text-sm font-bold text-gray-500">Cancel Edit</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl border shadow-sm overflow-hidden xl:col-span-2">
            <div class="p-5 border-b"><h2 class="font-black text-lg">Referral Milestone Levels
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="p-3 text-left">Level</th><th class="p-3 text-right">Required Referral</th><th class="p-3 text-right">Bonus</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y">
                    <?php if(count($levels)>0): foreach($levels as $lv): ?>
                        <tr>
                            <td class="p-3 font-black">Level <?php echo intval($lv['level_no']); ?><div class="text-xs text-gray-400">Sort: <?php echo intval($lv['sort_order']); ?></div></td>
                            <td class="p-3 text-right font-bold"><?php echo intval($lv['referral_limit']); ?> users</td>
                            <td class="p-3 text-right font-black text-green-600">৳<?php echo number_format((float)$lv['bonus_amount'],2); ?></td>
                            <td class="p-3 text-center"><?php echo intval($lv['is_active'])===1 ? '<span class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs font-black">Active</span>' : '<span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-xs font-black">Disabled</span>'; ?></td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a href="?edit_level=<?php echo intval($lv['id']); ?>" class="text-blue-600 font-bold mr-3">Edit</a>
                                <a href="?toggle_level=<?php echo intval($lv['id']); ?>" class="text-amber-600 font-bold mr-3">Toggle</a>
                                <a href="?delete_level=<?php echo intval($lv['id']); ?>" onclick="return confirm('Delete this level?')" class="text-red-600 font-bold">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="p-8 text-center text-gray-400 font-bold">No referral level found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden mb-6">
        <div class="p-5 border-b"><h2 class="font-black text-lg">Referral Bonus History</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="p-3 text-left">Receiver</th><th class="p-3 text-center">Level</th><th class="p-3 text-right">Qualified</th><th class="p-3 text-right">Required</th><th class="p-3 text-right">Bonus</th><th class="p-3 text-center">Status</th><th class="p-3 text-left">Date</th></tr></thead>
                <tbody class="divide-y">
                <?php if($history && $history->num_rows>0): while($h=$history->fetch_assoc()): ?>
                    <tr>
                        <td class="p-3 font-bold"><?php echo htmlspecialchars($h['inviter_phone'] ?: ($h['inviter_username'] ?: ('User #'.$h['inviter_id'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="p-3 text-center"><span class="px-2 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-black">L<?php echo intval($h['level']); ?></span></td>
                        <td class="p-3 text-right font-bold"><?php echo intval($h['qualified_count']); ?></td>
                        <td class="p-3 text-right font-bold"><?php echo intval($h['referral_limit']); ?></td>
                        <td class="p-3 text-right font-black text-green-600">৳<?php echo number_format((float)$h['bonus_amount'],2); ?></td>
                        <td class="p-3 text-center"><?php echo $h['status']==='credited' ? '<span class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs font-black">Credited</span>' : '<span class="px-2 py-1 rounded bg-amber-100 text-amber-700 text-xs font-black">Pending</span>'; ?></td>
                        <td class="p-3 text-gray-500 whitespace-nowrap"><?php echo htmlspecialchars($h['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="7" class="p-8 text-center text-gray-400 font-bold">No referral bonus history found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
        <div class="p-5 border-b"><h2 class="font-black text-lg">Recent Referral Registrations</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="p-3 text-left">New User</th><th class="p-3 text-left">Invited By</th><th class="p-3 text-right">Approved Deposit</th><th class="p-3 text-left">Registered</th></tr></thead>
                <tbody class="divide-y">
                <?php if($recent_referrals && $recent_referrals->num_rows>0): while($r=$recent_referrals->fetch_assoc()): ?>
                    <tr>
                        <td class="p-3 font-bold"><?php echo htmlspecialchars($r['phone'] ?: ($r['username'] ?: ('User #'.$r['id'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($r['ref_phone'] ?: ($r['ref_username'] ?: ('User #'.$r['referrer_id'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="p-3 text-right font-black text-green-600">৳<?php echo number_format((float)$r['deposit_total'],2); ?></td>
                        <td class="p-3 text-gray-500 whitespace-nowrap"><?php echo htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="4" class="p-8 text-center text-gray-400 font-bold">No referral registration found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
