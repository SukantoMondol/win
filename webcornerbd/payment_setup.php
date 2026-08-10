<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// 1. Handle Add Number (System Admin Numbers)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $method = $conn->real_escape_string($_POST['method']);
    $type = $conn->real_escape_string($_POST['type']);
    $number = $conn->real_escape_string($_POST['number']);
    $limit = floatval($_POST['limit_daily']);
    
    // agent_id = 0 means System/Admin Number
    $sql = "INSERT INTO payment_accounts (agent_id, method, type, number, limit_daily, is_active) 
            VALUES (0, '$method', '$type', '$number', $limit, 1)";
            
    if($conn->query($sql)) {
        $msg = "System payment number added.";
        $msg_type = "success";
    } else {
        error_log('Payment account save failed: ' . $conn->error);
        $msg = "Unable to save the payment account right now.";
        $msg_type = "error";
    }
}

// 2. Fetch All Numbers (Joined with Agent Info)
$sql = "SELECT pa.*, a.name as agent_name, a.type as agent_role 
        FROM payment_accounts pa 
        LEFT JOIN agents a ON pa.agent_id = a.id 
        ORDER BY pa.is_active DESC, pa.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFS Setup | BetPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Payment Gateway Setup</h1>
                <p class="text-sm text-gray-500">Manage deposit numbers from System & Agents</p>
            </div>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                    class="w-full sm:w-auto bg-indigo-600 text-white px-5 py-2.5 rounded-lg font-bold shadow-md hover:bg-indigo-700 flex items-center justify-center gap-2 transition">
                <i class="fas fa-plus"></i> Add System Number
            </button>
        </div>

        <?php if(isset($msg)): ?>
            <div class="mb-6 p-4 rounded-lg flex items-center gap-3 shadow-sm bg-white border-l-4 <?php echo $msg_type == 'success' ? 'border-green-500 text-green-700' : 'border-red-500 text-red-700'; ?>">
                <i class="fas <?php echo $msg_type == 'success' ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i>
                <span class="text-sm font-medium"><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-bold tracking-wider border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">Method & Type</th>
                            <th class="px-6 py-4">Number / Owner</th>
                            <th class="px-6 py-4">Daily Limit Status</th>
                            <th class="px-6 py-4">Live Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                // Limit Calculations
                                $limit = $row['limit_daily'];
                                $used = $row['current_balance']; // Assuming this tracks today's inflow
                                $percent = ($limit > 0) ? ($used / $limit) * 100 : 0;
                                $percent = min(100, $percent);
                                $left = $limit - $used;
                                
                                // Color logic for method
                                $method_color = match($row['method']) {
                                    'bkash' => 'text-pink-600 bg-pink-50 border-pink-100',
                                    'nagad' => 'text-orange-600 bg-orange-50 border-orange-100',
                                    'rocket' => 'text-purple-600 bg-purple-50 border-purple-100',
                                    default => 'text-gray-600 bg-gray-50 border-gray-100'
                                };
                            ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="px-3 py-1 rounded border text-xs font-bold uppercase <?php echo $method_color; ?>">
                                            <?php echo $row['method']; ?>
                                        </div>
                                        <span class="text-xs text-gray-500 font-medium bg-gray-100 px-2 py-0.5 rounded capitalize">
                                            <?php echo $row['type']; ?>
                                        </span>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="font-mono text-base font-bold text-gray-800 tracking-wide">
                                        <?php echo $row['number']; ?>
                                    </div>
                                    <div class="text-xs mt-1">
                                        <?php if($row['agent_id'] > 0): ?>
                                            <span class="text-indigo-600 font-bold flex items-center gap-1">
                                                <i class="fas fa-user-tie"></i> <?php echo $row['agent_name']; ?>
                                                <span class="text-[10px] text-gray-400 font-normal ml-1">(<?php echo ucfirst($row['agent_role']); ?>)</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 font-bold flex items-center gap-1">
                                                <i class="fas fa-shield-alt"></i> System Admin
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4 w-64">
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-gray-500">Used: ৳<?php echo number_format($used); ?></span>
                                        <span class="font-bold <?php echo $left < 5000 ? 'text-red-500' : 'text-green-600'; ?>">
                                            Left: ৳<?php echo number_format($left); ?>
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-500" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-1 text-right">Max: ৳<?php echo number_format($limit); ?></div>
                                </td>

                                <td class="px-6 py-4">
                                    <a href="logic_crud.php?toggle_status=1&table=payment_accounts&column=is_active&id=<?php echo $row['id']; ?>&redirect=payment_setup.php" 
                                       class="cursor-pointer group flex items-center gap-2">
                                        <?php if($row['is_active']): ?>
                                            <div class="relative w-10 h-5 bg-green-500 rounded-full transition group-hover:bg-green-600">
                                                <div class="absolute right-1 top-1 w-3 h-3 bg-white rounded-full shadow-sm"></div>
                                            </div>
                                            <span class="text-xs font-bold text-green-700">Live</span>
                                        <?php else: ?>
                                            <div class="relative w-10 h-5 bg-gray-300 rounded-full transition group-hover:bg-gray-400">
                                                <div class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full shadow-sm"></div>
                                            </div>
                                            <span class="text-xs font-bold text-gray-500">Hidden</span>
                                        <?php endif; ?>
                                    </a>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <a href="logic_crud.php?delete=1&table=payment_accounts&id=<?php echo $row['id']; ?>&redirect=payment_setup.php" 
                                       onclick="return confirm('Delete this number? It will stop appearing on deposit page immediately.')"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-700 transition" title="Delete Number">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-wallet text-3xl mb-2 opacity-20"></i>
                                        <p class="text-sm">No payment numbers configured yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all scale-100">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-bold text-lg text-gray-800">Add System Number</h3>
                    <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition"><i class="fas fa-times"></i></button>
                </div>
                
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="add_payment" value="1">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Method</label>
                        <select name="method" class="w-full border border-gray-200 p-2.5 rounded-lg text-sm bg-white focus:outline-none focus:border-indigo-500">
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="upay">Upay</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Type</label>
                            <select name="type" class="w-full border border-gray-200 p-2.5 rounded-lg text-sm bg-white focus:outline-none focus:border-indigo-500">
                                <option value="personal">Personal</option>
                                <option value="agent">Agent</option>
                                <option value="merchant">Merchant</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Daily Limit</label>
                            <input type="number" name="limit_daily" value="25000" class="w-full border border-gray-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Phone Number</label>
                        <input type="text" name="number" required placeholder="017xxxxxxxx" class="w-full border border-gray-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-indigo-500 font-mono tracking-wide">
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold shadow-lg hover:bg-indigo-700 transition">Save Number</button>
                    </div>
                </form>
            </div>
        </div>

    </main>
</body>
</html>