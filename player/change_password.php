<?php
session_start();
require '../includes/db.php';

// ১. লগইন চেক
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../login.php"); 
    exit(); 
}

$uid = $_SESSION['user_id'];
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$primary = $settings['theme_primary'] ?? '#034C44'; // ডাটাবেজ থেকে কালার

$msg = "";

// ২. পাসওয়ার্ড পরিবর্তন লজিক
if (isset($_POST['change_pass'])) {
    $old_pass = $_POST['old_pass'];
    $new_pass = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    // বর্তমান পাসওয়ার্ড আনা
    $user = $conn->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc();

    // পুরাতন পাসওয়ার্ড ভেরিফাই
    if (password_verify($old_pass, $user['password'])) {
        
        // নতুন পাসওয়ার্ড ম্যাচ চেক
        if ($new_pass === $confirm_pass) {
            
            if (strlen($new_pass) >= 6) {
                // নতুন পাসওয়ার্ড হ্যাশ করা
                $new_hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                
                // আপডেট কুয়েরি
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param("si", $new_hashed_pass, $uid);
                
                if ($stmt->execute()) {
                    $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4 text-sm font-bold text-center'>পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে!</div>";
                } else {
                    $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 text-sm font-bold text-center'>কোথাও সমস্যা হয়েছে!</div>";
                }
            } else {
                $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 text-sm font-bold text-center'>নতুন পাসওয়ার্ড অন্তত ৬ অক্ষরের হতে হবে!</div>";
            }

        } else {
            $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 text-sm font-bold text-center'>নতুন পাসওয়ার্ড দুটি মিলছে না!</div>";
        }

    } else {
        $msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 text-sm font-bold text-center'>পুরাতন পাসওয়ার্ড ভুল!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>পাসওয়ার্ড পরিবর্তন</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body class="bg-gray-100">

    <div class="p-4 flex items-center text-white sticky top-0 z-50 shadow-md" style="background-color: <?php echo $primary; ?>;">
        <a href="security.php" class="mr-4 text-xl"><i class="fas fa-chevron-left"></i></a>
        <h1 class="text-lg font-bold">পাসওয়ার্ড পরিবর্তন</h1>
    </div>

    <div class="p-6 max-w-md mx-auto mt-4">
        
        <div class="bg-white p-6 rounded-xl shadow-sm">
            <h2 class="text-center text-gray-700 font-bold mb-6 text-lg">নিরাপত্তা সেটিংস</h2>
            
            <?php echo $msg; ?>

            <form method="POST" class="space-y-5">
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">পুরাতন পাসওয়ার্ড</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="old_pass" id="old_pass" required placeholder="বর্তমান পাসওয়ার্ড দিন" 
                               class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-200 focus:outline-none focus:border-<?php echo $primary; ?> focus:ring-1 focus:ring-<?php echo $primary; ?> transition text-sm">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 cursor-pointer" onclick="togglePass('old_pass')">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">নতুন পাসওয়ার্ড</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-key"></i>
                        </span>
                        <input type="password" name="new_pass" id="new_pass" required placeholder="নতুন পাসওয়ার্ড (মিনিমাম ৬ অক্ষর)" 
                               class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-200 focus:outline-none focus:border-<?php echo $primary; ?> focus:ring-1 focus:ring-<?php echo $primary; ?> transition text-sm">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 cursor-pointer" onclick="togglePass('new_pass')">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">পাসওয়ার্ড নিশ্চিত করুন</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-check-circle"></i>
                        </span>
                        <input type="password" name="confirm_pass" id="confirm_pass" required placeholder="নতুন পাসওয়ার্ড পুনরায় লিখুন" 
                               class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-200 focus:outline-none focus:border-<?php echo $primary; ?> focus:ring-1 focus:ring-<?php echo $primary; ?> transition text-sm">
                    </div>
                </div>

                <button type="submit" name="change_pass" 
                        class="w-full text-white font-bold py-3.5 rounded-lg shadow-lg hover:opacity-90 transition active:scale-95"
                        style="background-color: <?php echo $primary; ?>;">
                    নিশ্চিত করুন
                </button>

            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6 leading-relaxed">
            আপনার অ্যাকাউন্টের সুরক্ষার জন্য শক্তিশালী পাসওয়ার্ড ব্যবহার করুন।<br>
            (অক্ষর, সংখ্যা এবং চিহ্নের মিশ্রণ প্রস্তাবিত)
        </p>

    </div>

    <script>
        function togglePass(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>

</body>
</html>