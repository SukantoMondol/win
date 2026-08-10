<?php
session_start();
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/bonus_system_helper.php';
wcb_bonus_ensure_schema($conn);

$message = '';
$msg_type = 'success';

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM deposit_bonus_rules WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: deposit_bonus_settings.php?msg=deleted');
    exit;
}
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $conn->prepare("UPDATE deposit_bonus_rules SET is_active = IF(is_active=1,0,1), updated_at=NOW() WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: deposit_bonus_settings.php?msg=updated');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_rule';

    if ($action === 'save_settings') {
        $enabled = isset($_POST['is_enabled']) ? 1 : 0;
        $section_title = trim($_POST['section_title'] ?? 'নতুন সদস্য বৃদ্ধির পরিকল্পনা');
        $status_text = trim($_POST['status_text'] ?? 'Not checked in today ?');
        $claim_button_text = trim($_POST['claim_button_text'] ?? 'Sign In');
        $locked_button_text = trim($_POST['locked_button_text'] ?? 'Sign In');
        $claimed_button_text = trim($_POST['claimed_button_text'] ?? 'Claimed');
        $daily_claim_limit = 1;

        $stmt = $conn->prepare("UPDATE deposit_bonus_settings SET is_enabled=?, section_title=?, status_text=?, claim_button_text=?, locked_button_text=?, claimed_button_text=?, daily_claim_limit=?, updated_at=NOW() WHERE id=1");
        $stmt->bind_param('isssssi', $enabled, $section_title, $status_text, $claim_button_text, $locked_button_text, $claimed_button_text, $daily_claim_limit);
        $ok = $stmt->execute();
        if ($ok) { $message = 'Deposit bonus global settings updated.'; } else { error_log('Deposit bonus settings update failed: ' . $conn->error); $message = 'Unable to update settings right now.'; }
        $msg_type = $ok ? 'success' : 'error';
    } else {
        $id = intval($_POST['id'] ?? 0);
        $min = max(1, round((float)($_POST['min_deposit_amount'] ?? 0), 2));
        $type = 'fixed';
        $value = max(0, round((float)($_POST['bonus_value'] ?? 0), 2));
        $turnover = max(0, round((float)($_POST['turnover_multiplier'] ?? 1), 2));
        $title = 'Deposit ' . rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.') . ' Bonus';
        $rules = '';
        $active = isset($_POST['is_active']) ? 1 : 0;
        $sort = intval(round($min));

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE deposit_bonus_rules SET title=?, min_deposit_amount=?, bonus_type=?, bonus_value=?, turnover_multiplier=?, rules_text=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param('sdsddsiii', $title, $min, $type, $value, $turnover, $rules, $active, $sort, $id);
            $ok = $stmt->execute();
            if ($ok) { $message = 'Deposit bonus rule updated.'; } else { error_log('Deposit bonus rule update failed: ' . $conn->error); $message = 'Unable to update the rule right now.'; }
            $msg_type = $ok ? 'success' : 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO deposit_bonus_rules (title, min_deposit_amount, bonus_type, bonus_value, turnover_multiplier, rules_text, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('sdsddsii', $title, $min, $type, $value, $turnover, $rules, $active, $sort);
            $ok = $stmt->execute();
            if ($ok) { $message = 'Deposit bonus rule added.'; } else { error_log('Deposit bonus rule insert failed: ' . $conn->error); $message = 'Unable to add the rule right now.'; }
            $msg_type = $ok ? 'success' : 'error';
        }
    }
}

if (isset($_GET['msg']) && !$message) { $message = 'Action completed successfully.'; }
$edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM deposit_bonus_rules WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) { $edit = $res->fetch_assoc(); }
}
$settings = wcb_deposit_bonus_settings($conn);
$rules = wcb_deposit_bonus_rules($conn, false);
$stats = array('claim_count' => 0, 'total_bonus' => 0, 'today_bonus' => 0, 'turnover_required' => 0);
$statRes = @$conn->query("SELECT COUNT(*) AS claim_count, COALESCE(SUM(bonus_amount),0) AS total_bonus, COALESCE(SUM(CASE WHEN claim_date=CURDATE() THEN bonus_amount ELSE 0 END),0) AS today_bonus, COALESCE(SUM(turnover_required),0) AS turnover_required FROM deposit_bonus_claims");
if ($statRes && $statRes->num_rows > 0) { $stats = array_merge($stats, $statRes->fetch_assoc()); }
$latestClaims = @$conn->query("SELECT c.*, u.username, u.phone, r.title AS rule_title FROM deposit_bonus_claims c LEFT JOIN users u ON u.id=c.user_id LEFT JOIN deposit_bonus_rules r ON r.id=c.rule_id ORDER BY c.claimed_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Bonus Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-slate-800">
<?php include '../includes/sidebar_admin.php'; ?>
<main class="lg:ml-64 p-6 min-h-screen">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900 flex items-center gap-2"><i class="fas fa-gift text-blue-600"></i> Deposit Bonus Settings</h1>
        </div>
        <a href="../player/rewardsignin.php" target="_blank" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-black shadow hover:bg-blue-700"><i class="fas fa-eye mr-2"></i> Preview User Page</a>
    </div>

    <?php if($message): ?>
    <div class="mb-5 rounded-xl border p-4 <?php echo $msg_type==='success'?'bg-green-50 border-green-200 text-green-700':'bg-red-50 border-red-200 text-red-700'; ?> font-bold text-sm">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">System Status</p><h3 class="text-2xl font-black <?php echo intval($settings['is_enabled'])===1?'text-green-600':'text-red-600'; ?>"><?php echo intval($settings['is_enabled'])===1?'ON':'OFF'; ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Total Claims</p><h3 class="text-2xl font-black"><?php echo number_format((float)$stats['claim_count']); ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Total Bonus Paid</p><h3 class="text-2xl font-black text-green-600">৳<?php echo number_format((float)$stats['total_bonus'],2); ?></h3></div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm"><p class="text-xs text-gray-400 font-bold uppercase">Turnover Generated</p><h3 class="text-2xl font-black text-blue-600">৳<?php echo number_format((float)$stats['turnover_required'],2); ?></h3></div>
    </div>

    <form method="POST" class="bg-white rounded-2xl border shadow-sm p-6 mb-6">
        <input type="hidden" name="action" value="save_settings">
        <div class="flex items-center justify-between gap-4 border-b pb-4 mb-5">
            <div>
                <h2 class="font-black text-lg">Global Control</h2>
            </div>
            <label class="flex items-center gap-2 font-black text-sm"><input type="checkbox" name="is_enabled" <?php echo intval($settings['is_enabled'])===1?'checked':''; ?>> Bonus ON</label>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Section Title</label>
                <input type="text" name="section_title" value="<?php echo htmlspecialchars($settings['section_title'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Status Text</label>
                <input type="text" name="status_text" value="<?php echo htmlspecialchars($settings['status_text'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Claim Button Text</label>
                <input type="text" name="claim_button_text" value="<?php echo htmlspecialchars($settings['claim_button_text'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Locked Button Text</label>
                <input type="text" name="locked_button_text" value="<?php echo htmlspecialchars($settings['locked_button_text'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-300">
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Claimed Button Text</label>
                <input type="text" name="claimed_button_text" value="<?php echo htmlspecialchars($settings['claimed_button_text'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-300">
            </div>
        </div>
        <button class="mt-5 bg-slate-900 hover:bg-black text-white rounded-xl px-5 py-3 font-black shadow">Save Global Settings</button>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form method="POST" class="bg-white rounded-2xl border shadow-sm p-6 space-y-4">
            <input type="hidden" name="action" value="save_rule">
            <input type="hidden" name="id" value="<?php echo intval($edit['id'] ?? 0); ?>">
            <h2 class="font-black text-lg border-b pb-3"><?php echo $edit ? 'Edit Bonus Rule' : 'Add New Bonus Rule'; ?></h2>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Minimum Deposit Amount</label>
                <input type="number" step="0.01" min="1" name="min_deposit_amount" required value="<?php echo htmlspecialchars((string)($edit['min_deposit_amount'] ?? 500), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-300">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase mb-1">Bonus Type</label>
                    <input type="hidden" name="bonus_type" value="fixed">
                    <input type="text" value="Fixed Amount" readonly class="w-full border rounded-xl px-4 py-3 font-bold bg-gray-100 text-gray-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase mb-1">Bonus Value</label>
                    <input type="number" step="0.01" min="0" name="bonus_value" required value="<?php echo htmlspecialchars((string)($edit['bonus_value'] ?? 500), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-300">
                </div>
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Turnover Multiplier</label>
                <input type="number" step="0.01" min="0" name="turnover_multiplier" required value="<?php echo htmlspecialchars((string)($edit['turnover_multiplier'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-300">
            </div>
            <label class="flex items-center gap-2 font-bold text-sm"><input type="checkbox" name="is_active" <?php echo (!isset($edit['is_active']) || intval($edit['is_active'])===1)?'checked':''; ?>> Active</label>
            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-3 font-black shadow"><?php echo $edit ? 'Update Rule' : 'Add Rule'; ?></button>
            <?php if($edit): ?><a href="deposit_bonus_settings.php" class="block text-center text-sm font-bold text-gray-500">Cancel Edit</a><?php endif; ?>
        </form>

        <div class="lg:col-span-2 bg-white rounded-2xl border shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="p-3 text-right">Min Deposit</th><th class="p-3 text-right">Bonus Type</th><th class="p-3 text-right">Bonus Value</th><th class="p-3 text-right">Turnover</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y">
                    <?php if(count($rules)>0): foreach($rules as $r): ?>
                        <tr>
                            <td class="p-3 text-right font-bold">৳<?php echo number_format((float)$r['min_deposit_amount'],2); ?></td>
                            <td class="p-3 text-right font-bold">Fixed Amount</td>
                            <td class="p-3 text-right font-black text-green-600">৳<?php echo number_format((float)$r['bonus_value'],2); ?></td>
                            <td class="p-3 text-right font-black text-blue-600">x<?php echo rtrim(rtrim(number_format((float)($r['turnover_multiplier'] ?? 1),2), '0'), '.'); ?></td>
                            <td class="p-3 text-center"><?php echo intval($r['is_active'])===1 ? '<span class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs font-black">Active</span>' : '<span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-xs font-black">Disabled</span>'; ?></td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a href="?edit=<?php echo intval($r['id']); ?>" class="text-blue-600 font-bold mr-3">Edit</a>
                                <a href="?toggle=<?php echo intval($r['id']); ?>" class="text-amber-600 font-bold mr-3">Toggle</a>
                                <a href="?delete=<?php echo intval($r['id']); ?>" onclick="return confirm('Delete this rule?')" class="text-red-600 font-bold">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="p-8 text-center text-gray-400 font-bold">No rule found. Add one rule first.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden mt-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="p-3 text-left">User</th><th class="p-3 text-left">Rule</th><th class="p-3 text-right">Deposit</th><th class="p-3 text-right">Bonus</th><th class="p-3 text-right">Turnover</th><th class="p-3 text-left">Claimed At</th></tr></thead>
                <tbody class="divide-y">
                <?php if($latestClaims && $latestClaims->num_rows > 0): while($c = $latestClaims->fetch_assoc()): ?>
                    <tr>
                        <td class="p-3 font-bold"><?php echo htmlspecialchars($c['username'] ?: ($c['phone'] ?: ('User #'.$c['user_id'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($c['rule_title'] ?: ('Rule #'.$c['rule_id']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="p-3 text-right font-bold">৳<?php echo number_format((float)($c['deposit_amount'] ?? $c['deposit_total']), 2); ?></td>
                        <td class="p-3 text-right font-black text-green-600">৳<?php echo number_format((float)$c['bonus_amount'], 2); ?></td>
                        <td class="p-3 text-right font-black text-blue-600">৳<?php echo number_format((float)($c['turnover_required'] ?? 0), 2); ?></td>
                        <td class="p-3 text-gray-500"><?php echo htmlspecialchars($c['claimed_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="6" class="p-8 text-center text-gray-400 font-bold">No claim found yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
