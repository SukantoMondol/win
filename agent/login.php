<?php
session_start();
require 'db.php';

// যদি অলরেডি লগিন থাকে, ড্যাশবোর্ডে পাঠাও
if (isset($_SESSION['agent_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // users টেবিল চেক করা হচ্ছে (role='agent' সহ)
    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND role='agent'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // পাসওয়ার্ড ভেরিফিকেশন (যেহেতু আপনার DB তে হ্যাশ পাসওয়ার্ড আছে)
        // যদি সাধারণ টেক্সট পাসওয়ার্ড ব্যবহার করেন তবে: if ($password == $row['password']) {
        if (password_verify($password, $row['password'])) { 
            
            // --- [FIXED SECTION START] ---
            // আমরা সব ধরনের ভেরিয়েবল সেট করছি যাতে সব ফাইলে কাজ করে
            $_SESSION['agent_id'] = $row['id'];   // পুরনো ফাইলের জন্য
            $_SESSION['user_id'] = $row['id'];    // নতুন ফাইলের জন্য
            $_SESSION['role'] = 'agent';          // রোল চেকের জন্য
            $_SESSION['agent_name'] = $row['username'];
            // --- [FIXED SECTION END] ---
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "ভুল পাসওয়ার্ড!";
        }
    } else {
        $error = "এজেন্ট খুঁজে পাওয়া যায়নি!";
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-[#001212] flex items-center justify-center h-screen">

    <div class="w-full max-w-md bg-[#001a1a] p-8 rounded-xl shadow-2xl border border-gray-800">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-yellow-500 mb-2">এজেন্ট লগিন</h2>
            <p class="text-gray-400 text-sm">আপনার ড্যাশবোর্ডে প্রবেশ করুন</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-900/50 border border-red-600 text-red-300 px-4 py-3 rounded mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-gray-300 text-sm font-bold mb-2">ইউজারনেম</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#002b2b] border border-gray-600 text-white focus:outline-none focus:border-yellow-500 transition" placeholder="Username" required>
                </div>
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-bold mb-2">পাসওয়ার্ড</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#002b2b] border border-gray-600 text-white focus:outline-none focus:border-yellow-500 transition" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" name="login" class="w-full bg-gradient-to-r from-yellow-600 to-yellow-500 hover:from-yellow-500 hover:to-yellow-400 text-black font-bold py-3 rounded-lg shadow-lg transform transition hover:scale-105">
                লগিন করুন <i class="fas fa-arrow-right ml-2"></i>
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="#" class="text-sm text-gray-500 hover:text-yellow-500">পাসওয়ার্ড ভুলে গেছেন?</a>
        </div>
    </div>

</body>
</html>