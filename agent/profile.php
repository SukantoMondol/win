<?php
session_start();
if (!isset($_SESSION['agent_id'])) { header("Location: login.php"); exit(); }
require 'db.php';

$agent_id = $_SESSION['agent_id'];
$msg = "";

if (isset($_POST['change_pass'])) {
    $new_pass = $_POST['new_pass'];
    $conn->query("UPDATE agents SET password='$new_pass' WHERE id=$agent_id");
    $msg = "<p class='bg-green-600/20 text-green-500 p-2 rounded mb-4'>পাসওয়ার্ড আপডেট সফল!</p>";
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-[#001515] text-white flex h-screen overflow-hidden font-sans">
    <?php include '../includes/sidebar_agent.php'; ?>
    
    <div class="flex-1 flex flex-col h-full relative w-full">
        <header class="md:hidden bg-[#002b2b] border-b border-gray-700 p-4 flex justify-between items-center z-30">
            <h1 class="text-lg font-bold text-yellow-500">সেটিংস</h1>
            <button onclick="toggleSidebar()"><i class="fas fa-bars text-2xl"></i></button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 flex items-center justify-center">
            <div class="bg-[#002b2b] p-6 md:p-8 rounded-xl border border-gray-700 w-full max-w-md shadow-2xl">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-user-shield text-3xl text-yellow-500"></i>
                    </div>
                    <h2 class="text-xl font-bold">একাউন্ট সেটিংস</h2>
                </div>
                
                <?php echo $msg; ?>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-1">নতুন পাসওয়ার্ড</label>
                        <input type="password" name="new_pass" class="w-full p-3 bg-gray-800 rounded border border-gray-600 focus:border-yellow-500 focus:outline-none" placeholder="******" required>
                    </div>
                    <button type="submit" name="change_pass" class="w-full bg-yellow-600 hover:bg-yellow-700 text-black font-bold py-3 rounded transition">পাসওয়ার্ড পরিবর্তন করুন</button>
                </form>
            </div>
        </main>
    </div>
    <script>function toggleSidebar() { document.getElementById('agentSidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebarOverlay').classList.toggle('hidden'); }</script>
</body>
</html>