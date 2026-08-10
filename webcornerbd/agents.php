<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// 1. Validate Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 2. Handle Search
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where_clause = "u.role = 'agent'";

if ($search) {
    $where_clause .= " AND (a.name LIKE '%$search%' OR u.email LIKE '%$search%' OR a.referral_code LIKE '%$search%')";
}

// 3. Fetch Agents
$sql = "SELECT u.username, u.email, u.status as user_status, 
               a.id as agent_id, a.name, a.balance, a.total_commission, 
               a.status as agent_status, a.referral_code, a.type, a.wallet_number,
               (SELECT COUNT(*) FROM users WHERE agent_id = a.id) as total_players
        FROM users u 
        LEFT JOIN agents a ON u.id = a.user_id 
        WHERE $where_clause
        ORDER BY u.created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Network | BetPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Agent Network</h1>
                    <p class="text-sm text-gray-500">Manage your local and e-wallet agents</p>
                </div>
                
                <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                    <form method="GET" class="relative w-full md:w-auto">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search name, email or code..." 
                               class="w-full md:w-64 pl-10 pr-4 py-2 border rounded-lg text-sm focus:outline-none focus:border-indigo-500 shadow-sm">
                        <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                    </form>
                    
                    <a href="create_agent.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md flex items-center justify-center gap-2 transition whitespace-nowrap">
                        <i class="fas fa-user-plus"></i> Add New Agent
                    </a>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-bold tracking-wider border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4">Agent Profile</th>
                                <th class="px-6 py-4">Type</th>
                                <th class="px-6 py-4">Network Stats</th>
                                <th class="px-6 py-4">Wallet</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): 
                                    $agent_id = $row['agent_id'] ?? 0;
                                    $name = $row['name'] ?? $row['username'];
                                    $type = $row['type'] ?? 'local';
                                ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm text-white shadow-sm 
                                                <?php echo $type == 'ewallet' ? 'bg-gradient-to-r from-pink-500 to-rose-500' : 'bg-gradient-to-r from-indigo-500 to-blue-500'; ?>">
                                                <?php echo strtoupper(substr($row['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($name); ?></p>
                                                <p class="text-xs text-gray-400"><?php echo wcb_public_email_html($row['email'] ?? ''); ?></p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php if($type == 'ewallet'): ?>
                                            <span class="inline-flex items-center gap-1 bg-rose-50 text-rose-700 text-[10px] font-bold px-2 py-1 rounded border border-rose-100 uppercase">
                                                <i class="fas fa-wallet"></i> E-Wallet
                                            </span>
                                            <div class="text-[10px] text-gray-400 mt-1 font-mono"><?php echo $row['wallet_number']; ?></div>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 text-[10px] font-bold px-2 py-1 rounded border border-indigo-100 uppercase">
                                                <i class="fas fa-user-friends"></i> Local
                                            </span>
                                            <div class="text-[10px] text-gray-400 mt-1 font-mono"><?php echo $row['referral_code']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <div class="text-gray-700 font-medium">
                                            <i class="fas fa-users text-gray-300 mr-1"></i> 
                                            <?php echo $row['total_players']; ?> Players
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <p class="font-mono font-bold text-gray-700">৳<?php echo number_format($row['balance'], 2); ?></p>
                                        <p class="text-[10px] text-gray-400">Total: ৳<?php echo number_format($row['total_commission'], 2); ?></p>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php if($row['agent_status'] == 'active'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Banned
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <?php if($agent_id > 0): ?>
                                            <a href="agent_profile.php?id=<?php echo $agent_id; ?>" 
                                               class="inline-flex items-center justify-center px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-slate-900 transition shadow-sm gap-2">
                                                Manage <i class="fas fa-arrow-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-red-400 italic">Profile Error</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                        <div class="flex flex-col items-center">
                                            <div class="bg-gray-50 p-4 rounded-full mb-3">
                                                <i class="fas fa-search text-3xl opacity-20"></i>
                                            </div>
                                            <p class="text-sm font-medium">No agents found matching your search.</p>
                                            <a href="agents.php" class="text-xs text-indigo-500 mt-2 hover:underline">Clear Search</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center text-xs text-gray-500">
                    <span>Showing results</span>
                    <div class="flex gap-2">
                        <button disabled class="px-3 py-1 bg-white border rounded opacity-50 cursor-not-allowed">Previous</button>
                        <button class="px-3 py-1 bg-white border rounded hover:bg-gray-100 transition">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>