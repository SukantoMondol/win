<?php
session_start();
require '../includes/auth_session.php';
require '../includes/db.php';
require_once '../includes/withdrawal_system_helper.php';
wcb_withdraw_ensure_schema($conn);

if (empty($_SESSION['withdraw_methods_csrf'])) {
    try { $_SESSION['withdraw_methods_csrf'] = bin2hex(random_bytes(24)); }
    catch (Exception $e) { $_SESSION['withdraw_methods_csrf'] = sha1(session_id() . microtime(true)); }
}
$csrf = $_SESSION['withdraw_methods_csrf'];
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $message = 'Security validation failed.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $code = strtolower(trim((string)($_POST['code'] ?? '')));
            $code = preg_replace('/[^a-z0-9_-]/', '', $code);
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($name === '' || $code === '') {
                $message = 'Method name and code are required.';
                $messageType = 'error';
            } elseif ($id > 0) {
                $stmt = $conn->prepare('UPDATE withdrawal_methods SET name=?, code=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?');
                $stmt->bind_param('ssiii', $name, $code, $isActive, $sortOrder, $id);
                $ok = @$stmt->execute();
                $stmt->close();
                $message = $ok ? 'Withdrawal method updated successfully.' : 'Method code must be unique.';
                $messageType = $ok ? 'success' : 'error';
            } else {
                $stmt = $conn->prepare('INSERT INTO withdrawal_methods (name, code, is_active, sort_order) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('ssii', $name, $code, $isActive, $sortOrder);
                $ok = @$stmt->execute();
                $stmt->close();
                $message = $ok ? 'Withdrawal method added successfully.' : 'Method code must be unique.';
                $messageType = $ok ? 'success' : 'error';
            }
        }

        if ($action === 'toggle') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $conn->prepare('UPDATE withdrawal_methods SET is_active=IF(is_active=1,0,1), updated_at=NOW() WHERE id=?');
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            $message = $ok ? 'Method status updated.' : 'Unable to update method status.';
            $messageType = $ok ? 'success' : 'error';
        }

        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM withdrawal_methods WHERE id=?');
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            $message = $ok ? 'Withdrawal method deleted.' : 'Unable to delete withdrawal method.';
            $messageType = $ok ? 'success' : 'error';
        }
    }
}

$edit = array();
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare('SELECT * FROM withdrawal_methods WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editResult = $stmt->get_result();
    if ($editResult && $editResult->num_rows > 0) { $edit = $editResult->fetch_assoc(); }
    $stmt->close();
}
$methods = wcb_withdraw_methods($conn, false);
$stats = array('total' => 0, 'active' => 0, 'accounts' => 0);
$statsQ = @$conn->query("SELECT COUNT(*) AS total, SUM(is_active=1) AS active FROM withdrawal_methods");
if ($statsQ && $row = $statsQ->fetch_assoc()) {
    $stats['total'] = intval($row['total']);
    $stats['active'] = intval($row['active']);
}
$accountsQ = @$conn->query('SELECT COUNT(*) AS c FROM player_wallets');
if ($accountsQ && $row = $accountsQ->fetch_assoc()) { $stats['accounts'] = intval($row['c']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Methods</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-slate-50 text-slate-800">
<?php include '../includes/sidebar_admin.php'; ?>
<main class="lg:ml-64 min-h-screen p-4 pt-20 md:p-6 md:pt-6">
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Withdrawal Methods</h1>
            <p class="text-sm text-slate-500 mt-1">Manage the payment methods available when users add withdrawal accounts.</p>
        </div>
        <a href="finance.php?type=withdraw&status=pending&period=lifetime" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"><i class="fas fa-money-check-alt"></i>Pending Withdrawals</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="mb-5 rounded-xl border p-4 text-sm font-semibold <?php echo $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl border bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Total Methods</div><div class="text-3xl font-bold mt-2 text-slate-800"><?php echo number_format($stats['total']); ?></div></div>
        <div class="rounded-xl border bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Active Methods</div><div class="text-3xl font-bold mt-2 text-emerald-600"><?php echo number_format($stats['active']); ?></div></div>
        <div class="rounded-xl border bg-white p-5 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Saved Accounts</div><div class="text-3xl font-bold mt-2 text-blue-600"><?php echo number_format($stats['accounts']); ?></div></div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <form method="post" class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo intval($edit['id'] ?? 0); ?>">
            <div class="border-b pb-4">
                <h2 class="text-lg font-bold text-slate-900"><?php echo !empty($edit) ? 'Edit Method' : 'Add Method'; ?></h2>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Method Name</label>
                <input type="text" name="name" maxlength="80" required value="<?php echo htmlspecialchars($edit['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="bKash" class="w-full rounded-lg border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Method Code</label>
                <input type="text" name="code" maxlength="50" required value="<?php echo htmlspecialchars($edit['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="bkash" class="w-full rounded-lg border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Sort Order</label>
                <input type="number" name="sort_order" value="<?php echo htmlspecialchars((string)($edit['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            </div>
            <label class="flex items-center gap-3 rounded-lg border bg-slate-50 px-3.5 py-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_active" class="h-4 w-4 rounded border-slate-300 text-emerald-600" <?php echo !isset($edit['is_active']) || intval($edit['is_active']) === 1 ? 'checked' : ''; ?>>Enable this method</label>
            <button class="w-full rounded-lg bg-slate-900 py-3 text-sm font-semibold text-white hover:bg-black" type="submit"><?php echo !empty($edit) ? 'Update Method' : 'Add Method'; ?></button>
            <?php if (!empty($edit)): ?><a href="withdrawal_methods.php" class="block text-center text-sm font-semibold text-slate-500">Cancel Edit</a><?php endif; ?>
        </form>

        <div class="xl:col-span-2 rounded-xl border bg-white shadow-sm overflow-hidden">
            <div class="border-b px-5 py-4"><h2 class="text-lg font-bold text-slate-900">Available Methods</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 text-left">Method</th><th class="px-4 py-3 text-left">Code</th><th class="px-4 py-3 text-center">Order</th><th class="px-4 py-3 text-center">Status</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y">
                    <?php if (!empty($methods)): ?>
                        <?php foreach ($methods as $method): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-4 font-semibold text-slate-800"><?php echo htmlspecialchars($method['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="px-4 py-4 font-mono text-xs text-slate-500"><?php echo htmlspecialchars($method['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="px-4 py-4 text-center"><?php echo intval($method['sort_order']); ?></td>
                                <td class="px-4 py-4 text-center"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?php echo intval($method['is_active']) === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?>"><?php echo intval($method['is_active']) === 1 ? 'Enabled' : 'Disabled'; ?></span></td>
                                <td class="px-4 py-4 text-right whitespace-nowrap">
                                    <a href="?edit=<?php echo intval($method['id']); ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100"><i class="fas fa-pen text-xs"></i></a>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo intval($method['id']); ?>">
                                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100" type="submit"><i class="fas fa-power-off text-xs"></i></button>
                                    </form>
                                    <form method="post" class="inline" onsubmit="return confirm('Delete this withdrawal method?')">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo intval($method['id']); ?>">
                                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100" type="submit"><i class="fas fa-trash text-xs"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No withdrawal method found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>
