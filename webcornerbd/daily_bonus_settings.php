<?php
session_start();
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/bonus_system_helper.php';
wcb_bonus_ensure_schema($conn);

$message = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $amount = max(0, round((float)($_POST['bonus_amount'] ?? 0), 2));

    $stmt = $conn->prepare("UPDATE daily_bonus_settings SET is_enabled=?, bonus_amount=?, updated_at=NOW() WHERE id=1");
    if ($stmt) {
        $stmt->bind_param('id', $is_enabled, $amount);
        if ($stmt->execute()) {
            $message = 'Daily bonus settings updated successfully.';
        } else {
            error_log('Daily bonus update failed: ' . $conn->error);
            $message = 'Unable to update daily bonus settings right now.';
            $msg_type = 'error';
        }
    } else {
        error_log('Daily bonus prepare failed: ' . $conn->error);
        $message = 'Unable to update daily bonus settings right now.';
        $msg_type = 'error';
    }
}

$settings = wcb_bonus_settings($conn);

$stats = array(
    'claim_count' => 0,
    'total_amount' => 0,
    'today_count' => 0,
    'today_amount' => 0
);

$statRes = @$conn->query("SELECT COUNT(*) AS claim_count, COALESCE(SUM(amount),0) AS total_amount, COALESCE(SUM(CASE WHEN DATE(claimed_at)=CURDATE() THEN 1 ELSE 0 END),0) AS today_count, COALESCE(SUM(CASE WHEN DATE(claimed_at)=CURDATE() THEN amount ELSE 0 END),0) AS today_amount FROM daily_bonus_claims");
if ($statRes && $statRes->num_rows > 0) {
    $stats = array_merge($stats, $statRes->fetch_assoc());
}

$recent = @$conn->query("SELECT c.id, c.user_id, c.amount, c.claimed_at, u.username, u.phone FROM daily_bonus_claims c LEFT JOIN users u ON c.user_id=u.id ORDER BY c.claimed_at DESC, c.id DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Bonus Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-slate-800">
<?php include '../includes/sidebar_admin.php'; ?>
<main class="lg:ml-64 p-6 min-h-screen">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900 flex items-center gap-2"><i class="fas fa-gift text-orange-500"></i> Daily Bonus Settings</h1>
        </div>
    </div>

    <?php if($message): ?>
    <div class="mb-5 rounded-xl border p-4 <?php echo $msg_type==='success'?'bg-green-50 border-green-200 text-green-700':'bg-red-50 border-red-200 text-red-700'; ?> font-bold text-sm">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border p-5 shadow-sm">
            <p class="text-xs text-gray-400 font-bold uppercase">Total Claims</p>
            <h3 class="text-2xl font-black text-gray-900"><?php echo number_format((float)$stats['claim_count']); ?></h3>
        </div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm">
            <p class="text-xs text-gray-400 font-bold uppercase">Total Paid</p>
            <h3 class="text-2xl font-black text-green-600">৳<?php echo number_format((float)$stats['total_amount'], 2); ?></h3>
        </div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm">
            <p class="text-xs text-gray-400 font-bold uppercase">Today Claims</p>
            <h3 class="text-2xl font-black text-gray-900"><?php echo number_format((float)$stats['today_count']); ?></h3>
        </div>
        <div class="bg-white rounded-2xl border p-5 shadow-sm">
            <p class="text-xs text-gray-400 font-bold uppercase">Today Paid</p>
            <h3 class="text-2xl font-black text-orange-600">৳<?php echo number_format((float)$stats['today_amount'], 2); ?></h3>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <form method="POST" class="bg-white rounded-2xl border shadow-sm p-6 space-y-5">
            <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 border">
                <div>
                    <p class="font-black text-gray-800">Popup ON/OFF</p>
                </div>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_enabled" class="sr-only peer" <?php echo !empty($settings['is_enabled'])?'checked':''; ?>>
                    <div class="w-12 h-7 bg-gray-300 rounded-full peer peer-checked:bg-green-500 relative after:content-[''] after:absolute after:top-1 after:left-1 after:bg-white after:w-5 after:h-5 after:rounded-full after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
            </div>
            <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Daily Bonus Amount</label>
                <input type="number" step="0.01" min="0" name="bonus_amount" value="<?php echo htmlspecialchars((string)$settings['bonus_amount'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded-xl px-4 py-3 font-bold focus:ring-2 focus:ring-orange-300 outline-none">
            </div>
            <button class="w-full bg-orange-600 hover:bg-orange-700 text-white rounded-xl py-3 font-black shadow">Save Settings</button>
        </form>

        <div class="xl:col-span-2 bg-white rounded-2xl border shadow-sm overflow-hidden">
            <div class="p-5 border-b flex items-center justify-between gap-3">
                <h2 class="font-black text-lg">Claim History</h2>
                <span class="text-xs font-bold text-gray-400">Latest 100</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3 text-left">User</th>
                            <th class="p-3 text-left">Phone</th>
                            <th class="p-3 text-right">Amount</th>
                            <th class="p-3 text-left">Claimed At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                    <?php if($recent && $recent->num_rows > 0): while($row = $recent->fetch_assoc()): ?>
                        <tr>
                            <td class="p-3 font-bold"><?php echo htmlspecialchars($row['username'] ?: ('User #'.$row['user_id']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="p-3 text-gray-500"><?php echo htmlspecialchars($row['phone'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="p-3 text-right font-black text-green-600">৳<?php echo number_format((float)$row['amount'], 2); ?></td>
                            <td class="p-3 text-gray-500 whitespace-nowrap"><?php echo htmlspecialchars($row['claimed_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="4" class="p-8 text-center text-gray-400 font-bold">No claim found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>
