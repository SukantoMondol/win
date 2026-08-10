<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// Player accounts only. Administrator credentials are stored exclusively in `admin`.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $role = 'player';
    $username = trim((string)($_POST['username'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $plainPassword = (string)($_POST['password'] ?? '');

    if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($plainPassword) < 6) {
        $error = 'Please provide valid player account information.';
    } else {
        $checkSql = "SELECT id FROM users WHERE email=? OR username=?" . ($phone !== '' ? " OR phone=?" : '') . " LIMIT 1";
        $check = $conn->prepare($checkSql);
        if ($phone !== '') {
            $check->bind_param('sss', $email, $username, $phone);
        } else {
            $check->bind_param('ss', $email, $username);
        }
        $check->execute();
        $duplicate = $check->get_result();
        $check->close();

        if ($duplicate && $duplicate->num_rows > 0) {
            $error = 'Username, email or phone already exists.';
        } else {
            $password = password_hash($plainPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (role, username, email, phone, password, status, balance) VALUES ('player', ?, ?, ?, ?, 'active', 0.00)");
            if ($stmt) {
                $stmt->bind_param('ssss', $username, $email, $phone, $password);
                if ($stmt->execute()) {
                    $newId = (int)$stmt->insert_id;
                    @$conn->query("INSERT INTO player_profiles (user_id, country) VALUES ($newId, 'Unknown')");
                    $stmt->close();
                    header('Location: users_all.php?msg=created');
                    exit();
                }
                error_log('Player creation failed: ' . $conn->error);
                $stmt->close();
            }
            $error = 'Unable to create the player account right now.';
        }
    }
}

$search_q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$where = "u.role='player'";
if ($search_q !== '') {
    $safe_q = $conn->real_escape_string($search_q);
    $where .= " AND (u.username LIKE '%$safe_q%' OR u.email LIKE '%$safe_q%' OR u.phone LIKE '%$safe_q%')";
}

$count_res = $conn->query("SELECT COUNT(*) AS total FROM users u WHERE $where");
$count_row = ($count_res && $count_res instanceof mysqli_result) ? $count_res->fetch_assoc() : array('total' => 0);
$total_users = (int)($count_row['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_users / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

$sql = "SELECT u.id, u.username, u.email, u.phone, u.balance, u.status, u.created_at, u.agent_id,
        p.country, p.risk_score, agent_user.username AS agent_name
        FROM users u
        LEFT JOIN player_profiles p ON u.id = p.user_id
        LEFT JOIN agents ag ON ag.id = u.agent_id
        LEFT JOIN users agent_user ON agent_user.id = ag.user_id
        WHERE $where
        ORDER BY u.created_at DESC
        LIMIT $per_page OFFSET $offset";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Management | BetPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        
        <div class="max-w-7xl mx-auto">

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Player Management</h1>
                    <p class="text-sm text-gray-500">Manage player accounts (Agents are managed separately)</p>
                </div>
                <button onclick="document.getElementById('addUserModal').classList.remove('hidden')" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg font-bold shadow-md transition flex items-center gap-2 text-sm w-full sm:w-auto justify-center">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </div>

            <?php if(isset($error)): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border-l-4 border-red-500 flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['msg'])): ?>
                <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border-l-4 border-green-500 flex items-center gap-3">
                    <i class="fas fa-check-circle"></i> Action completed successfully!
                </div>
            <?php endif; ?>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                
                <div class="text-sm font-bold text-gray-600 px-2">Players <span class="text-gray-400 font-medium">(<?php echo number_format($total_users); ?> total)</span></div>

                <form class="relative w-full sm:w-64" method="GET" action="">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Search phone, user or email..." 
                           class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-indigo-500 text-sm bg-gray-50 focus:bg-white transition">
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-bold tracking-wider border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4">User Identity</th>
                                <th class="px-6 py-4">Role</th>
                                <th class="px-6 py-4">Balance</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Risk Level</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-xs uppercase border border-indigo-200">
                                                <?php echo substr($row['username'], 0, 2); ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-800 flex items-center gap-2">
                                                    <?php echo htmlspecialchars($row['username']); ?>
                                                    
                                                    <?php if(!empty($row['agent_id']) && !empty($row['agent_name'])): ?>
                                                        <span class="text-[10px] bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded border border-orange-200 font-normal" title="Agent">
                                                            @<?php echo htmlspecialchars($row['agent_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="text-sm font-bold text-slate-600 mt-0.5 tracking-wide font-mono">
                                                    <?php echo !empty($row['phone']) ? htmlspecialchars($row['phone']) : '<span class="text-xs text-gray-400 font-normal">No Mobile</span>'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700 border border-blue-200">Player</span>
                                    </td>

                                    <td class="px-6 py-4 font-mono font-medium text-gray-600">
                                        <?php 
                                            // Balance from users table
                                            echo '৳'.number_format($row['balance'], 2); 
                                        ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <a href="logic_crud.php?toggle_status=1&table=users&column=status&id=<?php echo $row['id']; ?>&redirect=users_all.php" class="group flex items-center gap-1.5 cursor-pointer">
                                            <?php if($row['status'] == 'active'): ?>
                                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                                <span class="text-green-700 text-xs font-bold uppercase group-hover:underline">Active</span>
                                            <?php else: ?>
                                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                                <span class="text-red-700 text-xs font-bold uppercase group-hover:underline"><?php echo $row['status']; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php echo getRiskBadge($row['risk_score']); ?>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="user_profile.php?id=<?php echo $row['id']; ?>" 
                                               class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 p-2 rounded-lg transition" title="View Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <a href="logic_crud.php?delete=1&table=users&id=<?php echo $row['id']; ?>&redirect=users_all.php" 
                                               onclick="return confirm('Permanently delete this user?')"
                                               class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition" title="Delete User">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                        <div class="flex flex-col items-center">
                                            <div class="bg-gray-50 p-4 rounded-full mb-3"><i class="fas fa-users text-3xl opacity-20"></i></div>
                                            <p class="text-sm font-medium">No users found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="mt-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm">
                <div class="text-gray-500">
                    Showing <?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $per_page, $total_users)); ?> of <?php echo number_format($total_users); ?> players
                </div>
                <div class="flex items-center gap-2">
                    <?php $prevUrl = '?' . http_build_query(array('q' => $search_q, 'page' => max(1, $page - 1))); ?>
                    <?php $nextUrl = '?' . http_build_query(array('q' => $search_q, 'page' => min($total_pages, $page + 1))); ?>
                    <a href="<?php echo $prevUrl; ?>" class="px-3 py-2 rounded-lg border border-gray-200 bg-white <?php echo $page <= 1 ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50'; ?>">Previous</a>
                    <span class="px-3 py-2 text-gray-600">Page <?php echo number_format($page); ?> / <?php echo number_format($total_pages); ?></span>
                    <a href="<?php echo $nextUrl; ?>" class="px-3 py-2 rounded-lg border border-gray-200 bg-white <?php echo $page >= $total_pages ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50'; ?>">Next</a>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <div id="addUserModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-bold text-lg text-gray-800">Add User</h3>
                    <button onclick="document.getElementById('addUserModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition"><i class="fas fa-times"></i></button>
                </div>
                
                <form method="POST" class="p-6">
                    <input type="hidden" name="create_user" value="1">
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Username</label>
                        <input type="text" name="username" required class="w-full border border-gray-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Mobile Number</label>
                        <input type="text" name="phone" placeholder="017XXXXXXXX" class="w-full border border-gray-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Email</label>
                        <input type="email" name="email" required class="w-full border border-gray-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                    </div>

                    <div class="mb-6">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Password</label>
                        <input type="password" name="password" required class="w-full border border-gray-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:shadow-xl transition transform active:scale-95">
                        Create Account
                    </button>
                </form>
            </div>
        </div>

    </main>
</body>
</html>