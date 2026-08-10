<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// 1. Validate Admin & Get Inputs
// Ensure only admins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all'; // 'all', 'deposit', 'withdraw'

// 2. Build Query
$where_clauses = [];
if ($user_id > 0) {
    $where_clauses[] = "t.user_id = $user_id";
}
if ($filter_type != 'all') {
    $safe_type = $conn->real_escape_string($filter_type);
    $where_clauses[] = "t.type = '$safe_type'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
}

// Fetch Transactions with Usernames
$sql = "SELECT t.*, u.username, u.email 
        FROM transactions_fake t 
        JOIN users u ON t.user_id = u.id 
        $where_sql 
        ORDER BY t.created_at DESC";

$result = $conn->query($sql);

// 3. Fetch User Details for Header (if specific user selected)
$current_user_name = "All Users";
if ($user_id > 0) {
    $u_res = $conn->query("SELECT username FROM users WHERE id = $user_id");
    if ($u_res->num_rows > 0) {
        $current_user_name = $u_res->fetch_assoc()['username'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions: <?php echo htmlspecialchars($current_user_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="ml-64 p-8 min-h-screen">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <a href="user_profile.php?id=<?php echo $user_id; ?>" class="text-gray-400 hover:text-gray-600 text-sm mb-1 inline-flex items-center gap-1">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
                <h1 class="text-2xl font-bold text-gray-800">
                    Transaction History <span class="text-gray-400 font-normal">/ <?php echo htmlspecialchars($current_user_name); ?></span>
                </h1>
            </div>
            
            <div class="flex bg-white rounded-lg shadow-sm border border-gray-200 p-1">
                <a href="?user_id=<?php echo $user_id; ?>&type=all" 
                   class="px-4 py-2 rounded-md text-sm font-medium transition <?php echo $filter_type == 'all' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-500 hover:bg-gray-50'; ?>">
                   All
                </a>
                <a href="?user_id=<?php echo $user_id; ?>&type=deposit" 
                   class="px-4 py-2 rounded-md text-sm font-medium transition <?php echo $filter_type == 'deposit' ? 'bg-green-50 text-green-600' : 'text-gray-500 hover:bg-gray-50'; ?>">
                   Deposits
                </a>
                <a href="?user_id=<?php echo $user_id; ?>&type=withdraw" 
                   class="px-4 py-2 rounded-md text-sm font-medium transition <?php echo $filter_type == 'withdraw' ? 'bg-red-50 text-red-600' : 'text-gray-500 hover:bg-gray-50'; ?>">
                   Withdrawals
                </a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-semibold tracking-wider border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">ID</th>
                            <?php if ($user_id == 0): ?><th class="px-6 py-4">User</th><?php endif; ?>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4">Method / TRX ID</th>
                            <th class="px-6 py-4 text-right">Amount</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 text-gray-400 font-mono">#<?php echo $row['id']; ?></td>
                                    
                                    <?php if ($user_id == 0): ?>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-700"><?php echo htmlspecialchars($row['username']); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo wcb_public_email_html($row['email'] ?? ''); ?></div>
                                    </td>
                                    <?php endif; ?>

                                    <td class="px-6 py-4">
                                        <?php if ($row['type'] == 'deposit'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-green-50 text-green-700 text-xs font-bold border border-green-100">
                                                <i class="fas fa-arrow-down"></i> Deposit
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-50 text-red-700 text-xs font-bold border border-red-100">
                                                <i class="fas fa-arrow-up"></i> Withdraw
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-gray-700 font-medium"><?php echo htmlspecialchars($row['method'] ?? 'N/A'); ?></div>
                                        <div class="text-xs text-gray-400 font-mono mt-0.5"><?php echo htmlspecialchars($row['transaction_id'] ?? '-'); ?></div>
                                    </td>

                                    <td class="px-6 py-4 text-right font-mono font-bold text-gray-700">
                                        <?php echo ($row['type'] == 'withdraw' ? '-' : '+') . ' ৳ ' . number_format($row['amount'], 2); ?>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <?php 
                                            $status_class = '';
                                            $status_icon = '';
                                            switch($row['status']) {
                                                case 'approved': 
                                                    $status_class = 'bg-emerald-100 text-emerald-700'; 
                                                    $status_icon = 'fa-check';
                                                    break;
                                                case 'rejected': 
                                                    $status_class = 'bg-rose-100 text-rose-700'; 
                                                    $status_icon = 'fa-times';
                                                    break;
                                                default: 
                                                    $status_class = 'bg-amber-100 text-amber-700'; 
                                                    $status_icon = 'fa-clock';
                                            }
                                        ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full <?php echo $status_class; ?>" title="<?php echo ucfirst($row['status']); ?>">
                                            <i class="fas <?php echo $status_icon; ?>"></i>
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-right text-gray-500">
                                        <?php echo date('M d, Y', strtotime($row['created_at'])); ?><br>
                                        <span class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <i class="fas fa-receipt text-4xl mb-3 opacity-20"></i>
                                        <p class="text-sm">No transactions found matching your filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
                <span class="text-xs text-gray-500">Showing recent transactions</span>
                <div class="flex gap-2">
                    <button class="px-3 py-1 bg-white border border-gray-300 rounded text-xs font-medium text-gray-500 disabled:opacity-50" disabled>Previous</button>
                    <button class="px-3 py-1 bg-white border border-gray-300 rounded text-xs font-medium text-gray-500 hover:bg-gray-50">Next</button>
                </div>
            </div>
        </div>

    </main>
</body>
</html>