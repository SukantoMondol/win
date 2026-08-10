<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/admin_auth_helper.php';
wcb_admin_ensure_schema($conn);

// 1. FILTERS & SEARCH LOGIC
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
$admin_filter = isset($_GET['admin']) ? intval($_GET['admin']) : 0;
$severity_filter = isset($_GET['severity']) ? sanitize($conn, $_GET['severity']) : 'all';
$date_filter = isset($_GET['date']) ? sanitize($conn, $_GET['date']) : 'all';

$where = "WHERE 1=1";

if ($search) {
    $where .= " AND l.action LIKE '%$search%'";
}
if ($admin_filter > 0) {
    $where .= " AND l.admin_id = $admin_filter AND l.actor_type='admin'";
}
if ($severity_filter != 'all') {
    $where .= " AND l.severity = '$severity_filter'";
}
if ($date_filter == 'today') {
    $where .= " AND DATE(l.created_at) = CURDATE()";
} elseif ($date_filter == 'week') {
    $where .= " AND l.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
}

// 2. PAGINATION
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Count Total Rows
$total_res = $conn->query("SELECT COUNT(*) as count FROM admin_logs l $where");
$total_rows = $total_res->fetch_assoc()['count'];
$total_pages = ceil($total_rows / $limit);

// Fetch Logs
$sql = "SELECT l.*,
        CASE WHEN l.actor_type='admin' THEN COALESCE(a.name, 'Administrator') ELSE COALESCE(u.username, 'Support') END AS actor_name
        FROM admin_logs l
        LEFT JOIN `admin` a ON l.actor_type='admin' AND l.admin_id=a.id
        LEFT JOIN users u ON l.actor_type='support' AND l.admin_id=u.id AND u.role='support'
        $where
        ORDER BY l.created_at DESC
        LIMIT $limit OFFSET $offset";
$logs = $conn->query($sql);

// Fetch Stats
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN severity='warning' THEN 1 ELSE 0 END) as warnings,
    SUM(CASE WHEN severity='danger' THEN 1 ELSE 0 END) as errors
    FROM admin_logs WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc();

// Fetch Admins for Filter
$admins = $conn->query("SELECT id, name AS username FROM `admin` WHERE status='active' ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Logs | BetPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-history text-indigo-600"></i> Audit Logs
                </h1>
                <p class="text-sm text-gray-500">Track all administrative actions and security events.</p>
            </div>
            <button onclick="window.print()" class="bg-white border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm">
                <i class="fas fa-download mr-1"></i> Export Report
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-indigo-500">
                <p class="text-xs font-bold text-gray-400 uppercase">30-Day Activity</p>
                <h2 class="text-xl font-bold text-gray-800"><?php echo number_format($stats['total']); ?> <span class="text-xs font-normal text-gray-400">Actions</span></h2>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-orange-400">
                <p class="text-xs font-bold text-gray-400 uppercase">Warnings</p>
                <h2 class="text-xl font-bold text-orange-600"><?php echo number_format($stats['warnings']); ?> <span class="text-xs font-normal text-gray-400">Events</span></h2>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-red-500">
                <p class="text-xs font-bold text-gray-400 uppercase">Critical Errors</p>
                <h2 class="text-xl font-bold text-red-600"><?php echo number_format($stats['errors']); ?> <span class="text-xs font-normal text-gray-400">Alerts</span></h2>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                
                <div class="lg:col-span-2 relative">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search action..." 
                           class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500">
                </div>

                <select name="admin" class="w-full border border-gray-200 rounded-lg p-2 text-sm bg-gray-50 focus:bg-white focus:outline-none">
                    <option value="0">All Admins</option>
                    <?php while($adm = $admins->fetch_assoc()): ?>
                        <option value="<?php echo $adm['id']; ?>" <?php if($admin_filter==$adm['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($adm['username']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="severity" class="w-full border border-gray-200 rounded-lg p-2 text-sm bg-gray-50 focus:bg-white focus:outline-none">
                    <option value="all">All Levels</option>
                    <option value="info" <?php if($severity_filter=='info') echo 'selected'; ?>>ℹ️ Info</option>
                    <option value="warning" <?php if($severity_filter=='warning') echo 'selected'; ?>>⚠️ Warning</option>
                    <option value="danger" <?php if($severity_filter=='danger') echo 'selected'; ?>>🚨 Danger</option>
                </select>

                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition">
                    Filter Logs
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-bold tracking-wider border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">Admin / User</th>
                            <th class="px-6 py-4">Action Details</th>
                            <th class="px-6 py-4 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while($row = $logs->fetch_assoc()): 
                                // Color Logic
                                $badge_class = match($row['severity']) {
                                    'danger' => 'bg-red-50 text-red-700 border-red-100',
                                    'warning' => 'bg-orange-50 text-orange-700 border-orange-100',
                                    default => 'bg-blue-50 text-blue-700 border-blue-100'
                                };
                                $icon = match($row['severity']) {
                                    'danger' => 'fa-times-circle',
                                    'warning' => 'fa-exclamation-triangle',
                                    default => 'fa-info-circle'
                                };
                            ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600">
                                            <?php echo strtoupper(substr($row['actor_name'] ?? 'Sys', 0, 1)); ?>
                                        </div>
                                        <span class="font-bold text-gray-700"><?php echo htmlspecialchars($row['actor_name'] ?? 'System'); ?></span>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded border text-[10px] uppercase font-bold <?php echo $badge_class; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i> <?php echo $row['severity']; ?>
                                        </span>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($row['action']); ?></span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-right text-xs text-gray-400">
                                    <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-gray-400">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-search text-3xl mb-3 opacity-20"></i>
                                        <p class="text-sm">No logs found matching your criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs">
                <span class="text-gray-500">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                <div class="flex gap-2">
                    <?php if($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>" class="px-3 py-1.5 bg-white border rounded hover:bg-gray-100 transition">Previous</a>
                    <?php endif; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>" class="px-3 py-1.5 bg-white border rounded hover:bg-gray-100 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>