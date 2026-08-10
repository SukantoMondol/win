<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require '../includes/propay_gateway_helper.php';

propay_ensure_schema($conn);
$msg = '';
$msg_type = 'success';

function dts_clean_text($value, $limit = 255) {
    $value = trim((string)$value);
    $value = strip_tags($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit, 'UTF-8');
    }
    return substr($value, 0, $limit);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_transaction_limits'])) {
        $min_deposit = floatval($_POST['min_deposit_amount'] ?? 100);
        $min_withdraw = floatval($_POST['min_withdraw_amount'] ?? 100);
        if (propay_save_transaction_limits($conn, $min_deposit, $min_withdraw)) {
            $msg = 'Transaction limits saved successfully.';
        } else {
            error_log('Transaction limit save failed: ' . $conn->error);
            $msg = 'Unable to save transaction limits right now.';
            $msg_type = 'error';
        }
    }

    if (isset($_POST['save_bonus_settings'])) {
        $bkash_bonus = floatval($_POST['deposit_bonus_bkash'] ?? 0);
        $nagad_bonus = floatval($_POST['deposit_bonus_nagad'] ?? 0);
        if (propay_save_deposit_bonus_settings($conn, $bkash_bonus, $nagad_bonus)) {
            $msg = 'Deposit bonus settings saved successfully.';
        } else {
            error_log('Bonus settings save failed: ' . $conn->error);
            $msg = 'Unable to save bonus settings right now.';
            $msg_type = 'error';
        }
    }

    if (isset($_POST['save_deposit_notice'])) {
        $notice_text = dts_clean_text($_POST['deposit_notice'] ?? '', 1000);
        $stmt = $conn->prepare("UPDATE settings SET deposit_notice=? WHERE id=1");
        if ($stmt) {
            $stmt->bind_param('s', $notice_text);
            if ($stmt->execute()) {
                $msg = 'Deposit page notice saved successfully.';
            } else {
                $msg = 'Unable to save deposit notice: ' . $stmt->error;
                $msg_type = 'error';
            }
        } else {
            error_log('Notice update prepare failed: ' . $conn->error);
            $msg = 'Unable to update the notice right now.';
            $msg_type = 'error';
        }
    }

    if (isset($_POST['add_promo_notice'])) {
        $title = dts_clean_text($_POST['title'] ?? '', 255);
        $description = dts_clean_text($_POST['description'] ?? '', 1500);
        $bonus_percent = max(0, min(1000, intval($_POST['bonus_percent'] ?? 0)));
        $wager_multiplier = max(0, min(1000, intval($_POST['wager_multiplier'] ?? 1)));
        $start_date = trim($_POST['start_date'] ?? '') ?: date('Y-m-d');
        $end_date = trim($_POST['end_date'] ?? '') ?: '2030-12-31';
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $bonus_amount = $bonus_percent . '% Bonus';

        if ($title === '') {
            $msg = 'Promotion title is required.';
            $msg_type = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO promotions (title, category, image_path, description, bonus_amount, start_date, end_date, is_new, status, wager_multiplier, target_group, bonus_percent) VALUES (?, 'deposit', '', ?, ?, ?, ?, 1, ?, ?, 'all', ?)");
            if ($stmt) {
                $stmt->bind_param('ssssssii', $title, $description, $bonus_amount, $start_date, $end_date, $status, $wager_multiplier, $bonus_percent);
                if ($stmt->execute()) {
                    $msg = 'Promotion notice added successfully.';
                } else {
                    $msg = 'Unable to add promotion notice: ' . $stmt->error;
                    $msg_type = 'error';
                }
            } else {
                error_log('Promotion insert prepare failed: ' . $conn->error);
                $msg = 'Unable to add the promotion right now.';
                $msg_type = 'error';
            }
        }
    }

    if (isset($_POST['update_promo_notice'])) {
        $id = intval($_POST['promo_id'] ?? 0);
        $title = dts_clean_text($_POST['title'] ?? '', 255);
        $description = dts_clean_text($_POST['description'] ?? '', 1500);
        $bonus_percent = max(0, min(1000, intval($_POST['bonus_percent'] ?? 0)));
        $wager_multiplier = max(0, min(1000, intval($_POST['wager_multiplier'] ?? 1)));
        $start_date = trim($_POST['start_date'] ?? '') ?: date('Y-m-d');
        $end_date = trim($_POST['end_date'] ?? '') ?: '2030-12-31';
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $bonus_amount = $bonus_percent . '% Bonus';

        if ($id <= 0 || $title === '') {
            $msg = 'Valid promotion notice and title are required.';
            $msg_type = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE promotions SET title=?, description=?, bonus_amount=?, start_date=?, end_date=?, status=?, wager_multiplier=?, bonus_percent=? WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('ssssssiii', $title, $description, $bonus_amount, $start_date, $end_date, $status, $wager_multiplier, $bonus_percent, $id);
                if ($stmt->execute()) {
                    $msg = 'Promotion notice updated successfully.';
                } else {
                    $msg = 'Unable to update promotion notice: ' . $stmt->error;
                    $msg_type = 'error';
                }
            } else {
                error_log('Promotion update prepare failed: ' . $conn->error);
                $msg = 'Unable to update the promotion right now.';
                $msg_type = 'error';
            }
        }
    }

    if (isset($_POST['delete_promo_notice'])) {
        $id = intval($_POST['promo_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM promotions WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $msg = 'Promotion notice removed successfully.';
                } else {
                    $msg = 'Unable to remove promotion notice: ' . $stmt->error;
                    $msg_type = 'error';
                }
            }
        }
    }
}

$txn_settings = propay_get_site_transaction_settings($conn);
$settings_res = $conn->query("SELECT deposit_notice FROM settings WHERE id=1 LIMIT 1");
$settings = ($settings_res && $settings_res->num_rows > 0) ? $settings_res->fetch_assoc() : array('deposit_notice' => '');
$promotions = $conn->query("SELECT * FROM promotions WHERE category IN ('all','deposit') OR category='' OR category IS NULL ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit & Transaction Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 text-slate-800">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-800">Deposit & Transaction Settings</h1>
        </div>

        <?php if($msg): ?>
            <div class="mb-6 p-4 rounded-xl border-l-4 bg-white shadow-sm <?php echo $msg_type === 'success' ? 'border-green-500 text-green-700' : 'border-red-500 text-red-700'; ?>">
                <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                <span class="font-bold text-sm"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h2 class="font-black text-lg text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-sliders-h text-orange-600"></i> Minimum Limits</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Minimum Deposit Amount</label>
                        <input type="number" step="0.01" min="1" name="min_deposit_amount" value="<?php echo htmlspecialchars((string)($txn_settings['min_deposit_amount'] ?? 100), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Minimum Withdraw Amount</label>
                        <input type="number" step="0.01" min="1" name="min_withdraw_amount" value="<?php echo htmlspecialchars((string)($txn_settings['min_withdraw_amount'] ?? 100), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" name="save_transaction_limits" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-3 rounded-xl font-black shadow-md"><i class="fas fa-save mr-2"></i> Save Limits</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h2 class="font-black text-lg text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-gift text-pink-600"></i> Deposit Bonus Settings</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">bKash Bonus Percentage</label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" max="1000" name="deposit_bonus_bkash" value="<?php echo htmlspecialchars((string)($txn_settings['deposit_bonus_bkash'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-10 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400">
                            <span class="absolute right-4 top-3.5 font-black text-gray-400">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Nagad Bonus Percentage</label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" max="1000" name="deposit_bonus_nagad" value="<?php echo htmlspecialchars((string)($txn_settings['deposit_bonus_nagad'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-10 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400">
                            <span class="absolute right-4 top-3.5 font-black text-gray-400">%</span>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" name="save_bonus_settings" class="bg-pink-600 hover:bg-pink-700 text-white px-6 py-3 rounded-xl font-black shadow-md"><i class="fas fa-save mr-2"></i> Save Bonus Settings</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h2 class="font-black text-lg text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-bullhorn text-emerald-600"></i> Deposit Page Notice</h2>
            <form method="POST" class="space-y-4">
                <textarea name="deposit_notice" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400" placeholder="Deposit page top notice text..."><?php echo htmlspecialchars($settings['deposit_notice'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <button type="submit" name="save_deposit_notice" class="bg-[#126E51] hover:bg-[#0d4a3a] text-white px-6 py-3 rounded-xl font-black shadow-md"><i class="fas fa-save mr-2"></i> Save Deposit Notice</button>
            </form>
        </div>

        <div class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h2 class="font-black text-lg text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-tags text-indigo-600"></i> Available Promotion Notices</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 mb-6 bg-gray-50 rounded-2xl p-4 border border-gray-200">
                <div class="xl:col-span-2">
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Title</label>
                    <input type="text" name="title" required class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold" placeholder="Regular Deposit / First Deposit Bonus">
                </div>
                <div class="xl:col-span-2">
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Description</label>
                    <input type="text" name="description" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold" placeholder="Notice description">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Bonus %</label>
                    <input type="number" name="bonus_percent" min="0" max="1000" value="0" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Wager x</label>
                    <input type="number" name="wager_multiplier" min="0" max="1000" value="1" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Start</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">End</label>
                    <input type="date" name="end_date" value="2030-12-31" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="md:col-span-2 xl:col-span-3 flex items-end">
                    <button type="submit" name="add_promo_notice" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-black shadow-md w-full md:w-auto"><i class="fas fa-plus mr-2"></i> Add Notice</button>
                </div>
            </form>

            <div class="overflow-x-auto border border-gray-200 rounded-2xl">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-black">
                        <tr>
                            <th class="text-left px-4 py-3">Title</th>
                            <th class="text-left px-4 py-3">Description</th>
                            <th class="text-center px-4 py-3">Bonus</th>
                            <th class="text-center px-4 py-3">Wager</th>
                            <th class="text-center px-4 py-3">Status</th>
                            <th class="text-right px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if($promotions && $promotions->num_rows > 0): ?>
                            <?php while($row = $promotions->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-bold text-gray-800"><?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-4 py-3 text-gray-500 max-w-sm truncate"><?php echo htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-4 py-3 text-center font-bold"><?php echo intval($row['bonus_percent'] ?? 0); ?>%</td>
                                    <td class="px-4 py-3 text-center font-bold"><?php echo intval($row['wager_multiplier'] ?? 0); ?>x</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 rounded-full text-xs font-black <?php echo ($row['status'] ?? '') === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo htmlspecialchars(ucfirst($row['status'] ?? 'inactive'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" onclick='openEditModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)' class="text-blue-600 hover:bg-blue-50 px-3 py-2 rounded-lg"><i class="fas fa-edit"></i></button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Remove this promotion notice?');">
                                            <input type="hidden" name="promo_id" value="<?php echo intval($row['id']); ?>">
                                            <button type="submit" name="delete_promo_notice" class="text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400 font-bold">No promotion notices found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                <h3 class="font-black text-lg">Update Promotion Notice</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="promo_id" id="edit_promo_id">
                <div class="md:col-span-2">
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Title</label>
                    <input type="text" name="title" id="edit_title" required class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Description</label>
                    <textarea name="description" id="edit_description" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Bonus %</label>
                    <input type="number" name="bonus_percent" id="edit_bonus_percent" min="0" max="1000" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Wager x</label>
                    <input type="number" name="wager_multiplier" id="edit_wager_multiplier" min="0" max="1000" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Start Date</label>
                    <input type="date" name="start_date" id="edit_start_date" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">End Date</label>
                    <input type="date" name="end_date" id="edit_end_date" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Status</label>
                    <select name="status" id="edit_status" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="md:col-span-2 flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-xl font-black">Cancel</button>
                    <button type="submit" name="update_promo_notice" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-black"><i class="fas fa-save mr-2"></i> Update Notice</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(row) {
        document.getElementById('edit_promo_id').value = row.id || '';
        document.getElementById('edit_title').value = row.title || '';
        document.getElementById('edit_description').value = row.description || '';
        document.getElementById('edit_bonus_percent').value = row.bonus_percent || 0;
        document.getElementById('edit_wager_multiplier').value = row.wager_multiplier || 0;
        document.getElementById('edit_start_date').value = row.start_date || '';
        document.getElementById('edit_end_date').value = row.end_date || '';
        document.getElementById('edit_status').value = row.status || 'active';
        const modal = document.getElementById('editModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeEditModal() {
        const modal = document.getElementById('editModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    </script>
</body>
</html>
