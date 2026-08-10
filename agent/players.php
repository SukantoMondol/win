<?php
session_start();
// ১. লগইন চেক
if (!isset($_SESSION['agent_id'])) { header("Location: login.php"); exit(); }

require 'db.php';

$logged_in_user_id = $_SESSION['agent_id']; // এটি users টেবিলের ID

// ২. আসল এজেন্ট ID বের করা (Agents টেবিল থেকে)
$agent_info = $conn->query("SELECT id FROM agents WHERE user_id = $logged_in_user_id");

if($agent_info->num_rows == 0) {
    die("Error: This user is not registered in the agents table.");
}

$agent_data = $agent_info->fetch_assoc();
$real_agent_id = $agent_data['id']; // এটি সেই ID যা প্লেয়ার টেবিলে আছে

// ৩. প্লেয়ার ডিলিট/ব্যান লজিক
if(isset($_GET['ban_id'])) {
    $uid = intval($_GET['ban_id']);
    
    // সিকিউরিটি চেক: agent_id চেক করা হচ্ছে real_agent_id দিয়ে
    $check = $conn->query("SELECT id FROM users WHERE id=$uid AND agent_id=$real_agent_id");
    
    if($check->num_rows > 0){
        // ডিলিট করা হচ্ছে
        $conn->query("DELETE FROM users WHERE id=$uid");
        header("Location: players.php?msg=deleted");
        exit();
    }
}

// ৪. প্লেয়ার লিস্ট ফেচ করা (সার্চ অপশন সহ)
$search = "";
// লজিক: agent_id হতে হবে real_agent_id এবং role হতে হবে player
$sql = "SELECT * FROM users WHERE agent_id=$real_agent_id AND role='player'";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " AND (username LIKE '%$search%' OR phone LIKE '%$search%')";
}

$sql .= " ORDER BY id DESC";
$players = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Players List</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-[#001515] text-white flex h-screen overflow-hidden font-sans">
    
    <?php include '../includes/sidebar_agent.php'; ?>
    
    <div class="flex-1 flex flex-col h-full relative w-full">
        <header class="md:hidden bg-[#002b2b] border-b border-gray-700 p-4 flex justify-between items-center z-30">
            <h1 class="text-lg font-bold text-yellow-500">প্লেয়ার লিস্ট</h1>
            <button onclick="toggleSidebar()"><i class="fas fa-bars text-2xl"></i></button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-6xl mx-auto">
                
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
                    <h2 class="text-2xl font-bold text-gray-300">আমার প্লেয়ার (<?php echo $players->num_rows; ?>)</h2>
                    
                    <div class="flex gap-2 w-full md:w-auto">
                        <form class="flex w-full md:w-64">
                            <input type="text" name="search" value="<?php echo $search; ?>" placeholder="User/Phone..." class="w-full bg-gray-800 border border-gray-600 text-white px-3 py-2 rounded-l focus:outline-none focus:border-yellow-500">
                            <button type="submit" class="bg-yellow-600 text-black px-4 py-2 rounded-r font-bold hover:bg-yellow-500"><i class="fas fa-search"></i></button>
                        </form>
                        
                        <a href="add_player.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-bold whitespace-nowrap flex items-center">
                            <i class="fas fa-user-plus mr-2"></i> নতুন
                        </a>
                    </div>
                </div>

                <div class="bg-[#002b2b] rounded-xl shadow-lg border border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-gray-900 text-gray-400 text-sm uppercase">
                                <tr>
                                    <th class="p-4">ID</th>
                                    <th class="p-4">ইউজারনেম</th>
                                    <th class="p-4">মোবাইল</th>
                                    <th class="p-4">ব্যালেন্স</th>
                                    <th class="p-4 text-center">অ্যাকশন</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php if($players->num_rows > 0): ?>
                                    <?php while($row = $players->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-700/50 transition">
                                        <td class="p-4 text-gray-500">#<?php echo $row['id']; ?></td>
                                        
                                        <td class="p-4 font-bold text-white flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-yellow-500">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <?php echo $row['username']; ?>
                                        </td>
                                        
                                        <td class="p-4 text-gray-300"><?php echo $row['phone']; ?></td> 
                                        <td class="p-4 text-green-400 font-bold">৳ <?php echo number_format($row['balance'], 2); ?></td>
                                        
                                        <td class="p-4 text-center">
                                            <div class="flex justify-center gap-2">
                                                <a href="transfer.php?uid=<?php echo $row['id']; ?>" class="bg-blue-600/20 text-blue-400 w-8 h-8 flex items-center justify-center rounded border border-blue-600/50 hover:bg-blue-600 hover:text-white transition" title="Deposit/Withdraw">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </a>
                                                
                                                <a href="edit_player.php?id=<?php echo $row['id']; ?>" class="bg-yellow-600/20 text-yellow-400 w-8 h-8 flex items-center justify-center rounded border border-yellow-600/50 hover:bg-yellow-600 hover:text-black transition" title="Edit">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                
                                                <a href="?ban_id=<?php echo $row['id']; ?>" onclick="return confirm('আপনি কি নিশ্চিত যে এই প্লেয়ারকে ডিলিট করবেন?')" class="bg-red-600/20 text-red-400 w-8 h-8 flex items-center justify-center rounded border border-red-600/50 hover:bg-red-600 hover:text-white transition" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-gray-500">
                                            <i class="fas fa-users-slash text-4xl mb-3"></i>
                                            <p>কোনো প্লেয়ার পাওয়া যায়নি</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
    
    <script>
        function toggleSidebar() { 
            const sidebar = document.getElementById('agentSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if(sidebar) sidebar.classList.toggle('-translate-x-full'); 
            if(overlay) overlay.classList.toggle('hidden'); 
        }
    </script>
</body>
</html>