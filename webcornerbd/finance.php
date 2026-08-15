<?php
// ১. কনফিগারেশন এবং সেশন স্টার্ট
mysqli_report(MYSQLI_REPORT_OFF); 
session_start();

require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/propay_gateway_helper.php';
require_once '../includes/akpay_gateway_helper.php';
require_once '../includes/lgpay_gateway_helper.php';
require_once '../includes/nekpay_gateway_helper.php';
require_once '../includes/referral_system_helper.php';
require_once '../includes/withdrawal_system_helper.php';
propay_ensure_schema($conn);
akpay_ensure_schema($conn);
lgpay_ensure_schema($conn);
wcb_referral_ensure_schema($conn);
wcb_withdraw_ensure_schema($conn);
@wcb_sync_pending_withdrawals($conn, 0, 25);

// =========================================================
// 2. DATE RANGE & FILTERS (Default: This Month)
// =========================================================
$filter_period = $_GET['period'] ?? 'month'; // ডিফল্ট 'month'
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');

// পিরিয়ড অনুযায়ী ডেট সেট করা
if ($filter_period == 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
} elseif ($filter_period == 'yesterday') {
    $start_date = date('Y-m-d', strtotime('-1 days'));
    $end_date = date('Y-m-d', strtotime('-1 days'));
} elseif ($filter_period == 'week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filter_period == 'month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}
// 'lifetime' হলে আমরা কোনো ডেট রেঞ্জ ধরব না (নিচে কুয়েরিতে হ্যান্ডেল হবে)

// =========================================================
// 3. ACTION HANDLER (ATOMIC UPDATE - 100% DOUBLE DEPOSIT FIX)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['trx_id'])) {
    $tid = intval($_POST['trx_id']);
    $action = (string)$_POST['action'];
    $admin_id = intval($_SESSION['admin_id'] ?? 0);
    $t_query = $conn->query("SELECT * FROM transactions_fake WHERE id=$tid LIMIT 1");
    $t = $t_query ? $t_query->fetch_assoc() : null;

    if (!$t) {
        header('Location: finance.php?msg=already_processed');
        exit();
    }
    if ($t['type'] === 'withdraw') {
        if ($action === 'approve') {
            $result = wcb_process_pending_withdrawal($conn, $tid, $admin_id);
            $_SESSION['finance_flash'] = array('type' => !empty($result['success']) ? 'success' : 'error', 'message' => $result['message'] ?? 'Unable to process withdrawal.');
        } elseif ($action === 'reject') {
            $result = wcb_reject_pending_withdrawal($conn, $tid, $admin_id);
            $_SESSION['finance_flash'] = array('type' => !empty($result['success']) ? 'success' : 'error', 'message' => $result['message'] ?? 'Unable to reject withdrawal.');
        }
        header('Location: finance.php?type=withdraw&status=pending&period=lifetime');
        exit();
    }
    if ($t['status'] !== 'pending') {
        header('Location: finance.php?msg=already_processed');
        exit();
    }

    if ($action === 'approve' && $t['type'] === 'deposit') {
        $conn->query("UPDATE transactions_fake SET status='approved', agent_id=$admin_id WHERE id=$tid AND status='pending'");
        if ($conn->affected_rows > 0) {
            $user_id = intval($t['user_id']);
            $amount = (float)$t['amount'];
            $promo_id = isset($t['promo_id']) ? intval($t['promo_id']) : 0;
            $credit = propay_calculate_deposit_credit($conn, $user_id, $amount, $promo_id, $t['method'] ?? '');
            $total_money = (float)$credit['total_money'];
            $target_add = (float)$credit['target_add'];
            $stmtCredit = $conn->prepare("UPDATE users SET balance=balance+?, turnover_target=GREATEST(COALESCE(turnover_target,0),COALESCE(turnover_completed,0))+? WHERE id=?");
            $stmtCredit->bind_param('ddi', $total_money, $target_add, $user_id);
            $stmtCredit->execute();
            $stmtCredit->close();
            wcb_referral_award_for_deposit($conn, $user_id, $tid, $amount);
            header('Location: finance.php?msg=success');
            exit();
        }
    }

    if ($action === 'reject' && $t['type'] === 'deposit') {
        $conn->query("UPDATE transactions_fake SET status='rejected', agent_id=$admin_id WHERE id=$tid AND status='pending'");
        if ($conn->affected_rows > 0) {
            header('Location: finance.php?msg=rejected');
            exit();
        }
    }

    header('Location: finance.php?msg=already_processed');
    exit();
}

// =========================================================
// 4. BUILD QUERY & FILTERS
// =========================================================
$search_query = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$agent_filter = $_GET['agent'] ?? 'all';

$where_clauses = [];

// A. Global Search
if (!empty($search_query)) {
    $where_clauses[] = "(t.transaction_id LIKE '%$search_query%' OR u.username LIKE '%$search_query%' OR a.name LIKE '%$search_query%')";
} else {
    // B. Date Filter (শুধুমাত্র যদি Lifetime না হয়)
    if ($filter_period != 'lifetime') {
        $where_clauses[] = "DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'";
    }
}

// C. Dropdown Filters
if ($status_filter != 'all') $where_clauses[] = "t.status = '$status_filter'";
if ($type_filter != 'all') $where_clauses[] = "t.type = '$type_filter'";
if ($agent_filter != 'all') {
    if ($agent_filter == 'system') $where_clauses[] = "t.agent_id = 0";
    else $where_clauses[] = "t.agent_id = " . intval($agent_filter);
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

// [QUERY] LEFT JOIN ব্যবহার করা হয়েছে যাতে ডাটা মিস না হয়
$sql = "SELECT t.*, u.username, u.custom_wager_ratio, a.name as agent_name, 
        p.title as promo_title, p.bonus_percent as promo_percent, p.bonus_amount as promo_desc, p.wager_multiplier as promo_wager,
        pgo.status AS gateway_order_status, pgo.gateway_status, pgo.gateway_order_no, pgo.gateway
        FROM transactions_fake t 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN agents a ON t.agent_id = a.id 
        LEFT JOIN promotions p ON (t.promo_id IS NOT NULL AND t.promo_id = p.id)
        LEFT JOIN payment_gateway_orders pgo ON pgo.id=(SELECT MAX(pg2.id) FROM payment_gateway_orders pg2 WHERE pg2.transaction_id=t.id AND pg2.type='withdraw')
        $where_sql 
        ORDER BY FIELD(t.status, 'processing', 'pending', 'approved', 'rejected'), t.created_at DESC";

$result = $conn->query($sql);

// Stats Calculation
$stats_sql = "SELECT 
    SUM(CASE WHEN type='deposit' AND status='approved' THEN amount ELSE 0 END) as total_dep,
    SUM(CASE WHEN type='withdraw' AND status='approved' THEN amount ELSE 0 END) as total_wd,
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending_count
    FROM transactions_fake t
    LEFT JOIN users u ON t.user_id=u.id
    LEFT JOIN agents a ON t.agent_id=a.id
    $where_sql";
$statsResult = $conn->query($stats_sql);
$stats = $statsResult ? $statsResult->fetch_assoc() : array('total_dep'=>0,'total_wd'=>0,'pending_count'=>0);

// Risk Alerts (Duplicate Withdraw Numbers)
$risk_sql = "SELECT wallet_number, COUNT(DISTINCT user_id) as user_count, GROUP_CONCAT(DISTINCT u.username) as users
             FROM transactions_fake t
             LEFT JOIN users u ON t.user_id = u.id
             WHERE type='withdraw' AND wallet_number IS NOT NULL AND wallet_number != ''
             GROUP BY wallet_number
             HAVING user_count > 1";
$risk_alerts = $conn->query($risk_sql);

$agents_list = $conn->query("SELECT id, name FROM agents");
$global_settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$finance_flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Controller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-6 min-h-screen">
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Finance Controller</h1>
                <p class="text-sm text-gray-500">Manage deposits, withdrawals & risk.</p>
            </div>
            
            <form method="GET" class="relative w-full md:w-64">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search TrxID, User..." 
                       class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
            </form>
        </div>

        <?php if($finance_flash): ?>
            <div class="p-4 mb-4 rounded <?php echo $finance_flash['type']==='success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>"><?php echo htmlspecialchars($finance_flash['message'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if(isset($_GET['msg'])): ?>
            <div class="p-4 mb-4 rounded <?php echo ($_GET['msg']=='success') ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                <?php if($_GET['msg'] == 'success') echo "Action Completed Successfully!"; ?>
                <?php if($_GET['msg'] == 'rejected') echo "Transaction Rejected & Refunded (if withdraw)."; ?>
                <?php if($_GET['msg'] == 'already_processed') echo "Notice: Transaction was already processed."; ?>
            </div>
        <?php endif; ?>

        <?php if ($risk_alerts->num_rows > 0): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded shadow-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0"><i class="fas fa-exclamation-triangle text-red-500 text-xl"></i></div>
                <div class="ml-3 w-full">
                    <h3 class="text-sm font-bold text-red-800">Risk Alert: Duplicate Wallet Usage</h3>
                    <ul class="mt-2 text-sm text-red-700 list-disc pl-5">
                        <?php while($risk = $risk_alerts->fetch_assoc()): ?>
                            <li>
                                <strong><?php echo $risk['wallet_number']; ?></strong> used by <?php echo $risk['user_count']; ?> users 
                                <span class="text-xs bg-red-200 px-1 rounded">(<?php echo $risk['users']; ?>)</span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white p-5 rounded-xl border border-green-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase">Total Deposits</p>
                <h3 class="text-2xl font-bold text-green-600">৳<?php echo number_format($stats['total_dep']); ?></h3>
            </div>
            <div class="bg-white p-5 rounded-xl border border-red-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase">Total Withdraws</p>
                <h3 class="text-2xl font-bold text-red-600">৳<?php echo number_format($stats['total_wd']); ?></h3>
            </div>
            <div class="bg-indigo-900 p-5 rounded-xl shadow-lg text-white">
                <p class="text-xs font-bold text-indigo-300 uppercase">Pending</p>
                <h3 class="text-2xl font-bold"><?php echo $stats['pending_count']; ?></h3>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border mb-6 shadow-sm">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <?php if($search_query): ?><input type="hidden" name="q" value="<?php echo $search_query; ?>"><?php endif; ?>

                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Period</label>
                    <select name="period" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-gray-50 min-w-[140px] focus:ring-2 focus:ring-indigo-500">
                        <option value="lifetime" <?php if($filter_period=='lifetime') echo 'selected'; ?>>Lifetime (All)</option>
                        <option value="month" <?php if($filter_period=='month') echo 'selected'; ?>>This Month</option>
                        <option value="week" <?php if($filter_period=='week') echo 'selected'; ?>>This Week</option>
                        <option value="today" <?php if($filter_period=='today') echo 'selected'; ?>>Today</option>
                        <option value="yesterday" <?php if($filter_period=='yesterday') echo 'selected'; ?>>Yesterday</option>
                        <option value="custom" <?php if($filter_period=='custom') echo 'selected'; ?>>Custom Date</option>
                    </select>
                </div>

                <div class="<?php echo ($filter_period == 'lifetime' || $filter_period != 'custom') ? 'opacity-50 pointer-events-none' : ''; ?>">
                    <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Date Range</label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="start" value="<?php echo $start_date; ?>" class="border rounded px-2 py-2 text-sm">
                        <span>-</span>
                        <input type="date" name="end" value="<?php echo $end_date; ?>" class="border rounded px-2 py-2 text-sm">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Status</label>
                    <select name="status" class="border rounded px-3 py-2 text-sm min-w-[100px]">
                        <option value="all">All</option>
                        <option value="processing" <?php if($status_filter=='processing') echo 'selected'; ?>>Processing</option>
                        <option value="pending" <?php if($status_filter=='pending') echo 'selected'; ?>>Pending</option>
                        <option value="approved" <?php if($status_filter=='approved') echo 'selected'; ?>>Approved</option>
                        <option value="rejected" <?php if($status_filter=='rejected') echo 'selected'; ?>>Rejected</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Agent</label>
                    <select name="agent" class="border rounded px-3 py-2 text-sm min-w-[120px]">
                        <option value="all">All Agents</option>
                        <option value="system" <?php if($agent_filter=='system') echo 'selected'; ?>>System Admin</option>
                        <?php while($ag = $agents_list->fetch_assoc()): ?>
                            <option value="<?php echo $ag['id']; ?>" <?php if($agent_filter==$ag['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($ag['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" class="bg-gray-800 text-white px-5 py-2 rounded text-sm font-bold hover:bg-black transition">
                    Filter
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 border-b text-gray-500 font-bold uppercase text-xs">
                        <tr>
                            <th class="px-6 py-4">User</th>
                            <th class="px-6 py-4">Transaction Info</th>
                            <th class="px-6 py-4 bg-yellow-50 border-l border-yellow-100">Offer / Wager</th>
                            <th class="px-6 py-4">Status & Agent</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            
                            <?php 
                                // Display Logic
                                $display_wager = 1;
                                $display_bonus_text = "None";
                                $source_badge = "Global";

                                if (!empty($row['promo_title'])) {
                                    // যদি ডাটাবেজে bonus_percent কলাম থাকে সেটা দেখাব, না হলে টেক্সট
                                    $b_percent = isset($row['promo_percent']) && $row['promo_percent'] > 0 
                                                 ? $row['promo_percent'] . "%" 
                                                 : $row['promo_desc']; // fallback text
                                    
                                    $display_bonus_text = $row['promo_title'] . " (" . $b_percent . ")";
                                    $display_wager = $row['promo_wager'];
                                    $source_badge = "Promotion";
                                } else {
                                    if (!empty($row['custom_wager_ratio']) && $row['custom_wager_ratio'] > 0) {
                                        $display_wager = $row['custom_wager_ratio'];
                                        $source_badge = "User Custom";
                                    } else {
                                        $display_wager = $global_settings['normal_wager_ratio'];
                                        $display_bonus_text = "Default " . ($global_settings['deposit_bonus_percent'] ?? 0) . "%";
                                    }
                                }
                            ?>

                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-800"><?php echo htmlspecialchars($row['username']); ?></div>
                                    <div class="text-xs text-gray-500">ID: <?php echo $row['user_id']; ?></div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="uppercase text-xs font-bold px-2 py-0.5 rounded <?php echo $row['type']=='deposit'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'; ?>">
                                            <?php echo $row['type']; ?>
                                        </span>
                                        <span class="font-mono font-bold">৳<?php echo number_format($row['amount']); ?></span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php echo $row['method']; ?> <span class="text-gray-300">|</span> 
                                        <?php echo $row['wallet_number']; ?>
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-0.5"><?php echo $row['transaction_id']; ?></div>
                                    <div class="text-[10px] text-gray-400"><?php echo date('d M, h:i A', strtotime($row['created_at'])); ?></div>
                                </td>

                                <td class="px-6 py-4 bg-yellow-50 border-l border-yellow-100">
                                    <?php if($row['type'] == 'deposit'): ?>
                                        <div class="space-y-1">
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500">Bonus:</span>
                                                <span class="font-bold text-green-700"><?php echo $display_bonus_text; ?></span>
                                            </div>
                                            <div class="flex justify-between text-xs border-t border-yellow-200 pt-1">
                                                <span class="text-gray-500">Wager:</span>
                                                <span class="font-bold text-red-600"><?php echo $display_wager; ?>x</span>
                                            </div>
                                            <div class="text-[10px] text-right text-gray-400 italic">Src: <?php echo $source_badge; ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-center block">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-4">
                                    <?php 
                                        $statusClass = match($row['status']) {
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'processing' => 'bg-blue-100 text-blue-800 animate-pulse',
                                            'pending'  => 'bg-yellow-100 text-yellow-800 animate-pulse',
                                            default    => 'bg-gray-100 text-gray-800'
                                        };
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs font-bold uppercase <?php echo $statusClass; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                    <?php if($row['type']==='withdraw' && !empty($row['gateway_order_status'])): ?>
                                        <div class="text-[10px] mt-2 font-semibold text-blue-600">Gateway: <?php echo htmlspecialchars(strtoupper($row['gateway'] ?: 'auto') . ' / ' . ($row['gateway_status'] ?: $row['gateway_order_status']), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500 mt-2">
                                        By: <?php echo !empty($row['agent_name']) ? htmlspecialchars($row['agent_name']) : ($row['status']!='pending' ? 'System/Admin' : '-'); ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <?php if($row['type']==='withdraw'): ?>
                                        <?php if($row['status'] === 'pending'): ?>
                                            <form method="POST" class="inline-flex items-center justify-end gap-2">
                                                <input type="hidden" name="trx_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="action" value="approve" onclick="return confirm('Approve this withdrawal?')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-1.5 rounded-lg shadow-sm text-xs inline-flex items-center gap-1.5 transition active:scale-95" title="Approve Withdrawal">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject" onclick="return confirm('Reject this withdrawal?')" class="bg-red-500 hover:bg-red-600 text-white font-bold px-3.5 py-1.5 rounded-lg shadow-sm text-xs inline-flex items-center gap-1.5 transition active:scale-95" title="Reject Withdrawal">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        <?php elseif($row['status'] === 'processing'): ?>
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 border border-blue-200"><i class="fas fa-spinner fa-spin"></i> Processing</span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 font-bold italic flex items-center justify-end gap-1"><i class="fas fa-lock"></i> Processed</span>
                                        <?php endif; ?>
                                    <?php elseif($row['status'] === 'pending'): ?>
                                        <form method="POST" class="inline-flex items-center justify-end gap-2">
                                            <input type="hidden" name="trx_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="action" value="approve" onclick="return confirm('Confirm deposit approval?')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-1.5 rounded-lg shadow-sm text-xs inline-flex items-center gap-1.5 transition active:scale-95" title="Approve Deposit">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" onclick="return confirm('Reject Transaction?')" class="bg-red-500 hover:bg-red-600 text-white font-bold px-3.5 py-1.5 rounded-lg shadow-sm text-xs inline-flex items-center gap-1.5 transition active:scale-95" title="Reject Deposit">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 font-bold italic flex items-center justify-end gap-1">
                                            <i class="fas fa-lock"></i> Processed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="p-8 text-center text-gray-400">No transactions match your filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>
</html>