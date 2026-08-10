<?php
session_start();
if (!isset($_SESSION['agent_id'])) { header("Location: login.php"); exit(); }
require 'db.php';

$agent_id = $_SESSION['agent_id'];
$id = intval($_GET['id']);
$msg = "";

// চেক করা এই প্লেয়ার এই এজেন্টের কিনা
$check = $conn->query("SELECT * FROM users WHERE id=$id AND agent_id=$agent_id");
if ($check->num_rows == 0) { die("Access Denied!"); }
$user = $check->fetch_assoc();

if (isset($_POST['update'])) {
    $mobile = $_POST['mobile'];
    $password = $_POST['password'];
    
    $conn->query("UPDATE users SET mobile='$mobile', password='$password' WHERE id=$id");
    $msg = "<p class='bg-green-600/20 text-green-500 p-2 rounded mb-4'>তথ্য আপডেট হয়েছে!</p>";
    // রিফ্রেশ ডাটা
    $user = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Player</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-[#001515] text-white flex h-screen overflow-hidden font-sans">
    <?php include '../includes/sidebar_agent.php'; ?>
    
    <div class="flex-1 flex flex-col h-full relative w-full">
        <header class="md:hidden bg-[#002b2b] border-b border-gray-700 p-4 flex justify-between items-center z-30">
            <h1 class="text-lg font-bold text-yellow-500">এডিট প্লেয়ার</h1>
            <button onclick="toggleSidebar()"><i class="fas fa-bars text-2xl"></i></button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 flex items-center justify-center">
            <div class="bg-[#002b2b] p-6 md:p-8 rounded-xl border border-gray-700 w-full max-w-md shadow-2xl">
                <div class="flex justify-between items-center mb-6 border-b border-gray-700 pb-2">
                    <h2 class="text-xl font-bold text-yellow-500">তথ্য পরিবর্তন</h2>
                    <span class="text-sm text-gray-400">ID: #<?php echo $user['id']; ?></span>
                </div>
                
                <?php echo $msg; ?>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-500 text-xs mb-1">ইউজারনেম (পরিবর্তনযোগ্য নয়)</label>
                        <input type="text" value="<?php echo $user['username']; ?>" class="w-full p-3 bg-gray-900/50 text-gray-400 rounded border border-gray-700 cursor-not-allowed" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">মোবাইল নম্বর</label>
                        <input type="text" name="mobile" value="<?php echo $user['mobile']; ?>" class="w-full p-3 bg-gray-800 rounded border border-gray-600 focus:border-yellow-500 focus:outline-none" required>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">পাসওয়ার্ড</label>
                        <input type="text" name="password" value="<?php echo $user['password']; ?>" class="w-full p-3 bg-gray-800 rounded border border-gray-600 focus:border-yellow-500 focus:outline-none" required>
                    </div>
                    <button type="submit" name="update" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded transition">সেভ করুন</button>
                    <a href="players.php" class="block text-center text-sm text-gray-400 mt-2">বাতিল করে ফিরে যান</a>
                </form>
            </div>
        </main>
    </div>
    <script>function toggleSidebar() { document.getElementById('agentSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }</script>
</body>
</html>