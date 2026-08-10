<?php
session_start();
require '../includes/db.php';
require_once '../includes/functions.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$uid = $_SESSION['user_id'];
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$primary = $settings['theme_primary'] ?? '#034C44';

// ইউজার ডাটা
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$display_email = wcb_public_email($user['email'] ?? '');

if(isset($_POST['update'])) {
    // এখানে আপডেট লজিক বসাবেন (যদি প্রয়োজন হয়)
    echo "<script>alert('তথ্য আপডেট করার জন্য সাপোর্টে যোগাযোগ করুন');</script>";
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>আমার অ্যাকাউন্ট</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">

    <div class="p-4 flex items-center text-white sticky top-0 z-50" style="background-color: <?php echo $primary; ?>;">
        <a href="account.php" class="mr-4"><i class="fas fa-chevron-left text-xl"></i></a>
        <h1 class="text-lg font-bold">আমার অ্যাকাউন্ট</h1>
    </div>

    <div class="p-6">
        <h2 class="text-center text-lg font-bold text-gray-700 mb-6">ব্যবহারকারীর নাম: <?php echo $user['username']; ?></h2>

        <form method="POST" class="space-y-4">
            <div class="relative">
                <i class="fas fa-user absolute left-4 top-3.5 text-gray-400"></i>
                <input type="text" value="<?php echo $user['username']; ?>" class="w-full pl-10 p-3 rounded bg-gray-200 border border-gray-300 text-gray-500" readonly>
            </div>

            <div class="relative">
                <i class="fas fa-envelope absolute left-4 top-3.5 text-gray-400"></i>
                <input type="email" value="<?php echo htmlspecialchars($display_email, ENT_QUOTES, 'UTF-8'); ?>" class="w-full pl-10 p-3 rounded bg-white border border-gray-300 focus:border-teal-500 outline-none" placeholder="ইমেইল প্রদান করুন">
            </div>

            <div class="relative">
                <i class="fas fa-phone absolute left-4 top-3.5 text-gray-400"></i>
                <input type="text" value="<?php echo substr($user['phone'], 0, 5).'****'.substr($user['phone'], -2); ?>" class="w-full pl-10 p-3 rounded bg-gray-200 border border-gray-300 text-gray-500" readonly>
            </div>

            <button type="submit" name="update" class="w-full bg-red-500 text-white font-bold py-3 rounded shadow hover:bg-red-600 transition">
                জমা দিন
            </button>
        </form>
        
        <p class="text-xs text-gray-400 mt-4 text-center">আমরা আপনার গোপনীয়তা সম্পর্কে চিন্তা করি। সমস্ত তথ্য এনক্রিপ্ট করা হয়।</p>
    </div>

</body>
</html>