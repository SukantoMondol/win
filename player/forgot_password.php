<?php
session_start();

// ডাটাবেস কানেকশন ইনক্লুড করা হলো
require_once '../includes/db.php'; 

$site_name = 'Bet365 Clone'; // সাইট নেম বা ডাইনামিক করতে পারেন
$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = mysqli_real_escape_string($conn, trim($_POST['user_input']));
    $found = false;
    $contact_method = "";

    // ১. চেক করা ইনপুটটি কি (Phone, Email নাকি Username)
    if (is_numeric($input)) {
        // Phone Number Logic
        if (strlen($input) != 11) {
            $error = "মোবাইল নম্বর অবশ্যই ১১ সংখ্যার হতে হবে।";
        } else {
            $sql = "SELECT * FROM users WHERE phone = '$input' LIMIT 1";
            $contact_method = "মোবাইল নম্বরে";
        }
    } elseif (filter_var($input, FILTER_VALIDATE_EMAIL)) {
        // Email Logic
        $sql = "SELECT * FROM users WHERE email = '$input' LIMIT 1";
        $contact_method = "ইমেইলে";
    } else {
        // Username Logic
        $sql = "SELECT * FROM users WHERE username = '$input' LIMIT 1";
        $contact_method = "রেজিস্টার্ড কন্টাক্ট নাম্বারে"; 
    }

    // ২. ডাটাবেস কুয়েরি (যদি এরর না থাকে)
    if (empty($error)) {
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // ইউজারনেম দিয়ে সার্চ করলে স্পেসিফিক মেসেজ সেট করা
            if(!is_numeric($input) && !filter_var($input, FILTER_VALIDATE_EMAIL)){
                if(!empty($user['phone'])) {
                    $contact_method = "মোবাইল নম্বরে (" . substr($user['phone'], 0, 3) . "***" . substr($user['phone'], -3) . ")";
                } elseif(!empty($user['email'])) {
                    $contact_method = "ইমেইলে";
                }
            }

            // সফল মেসেজ
            $msg = "আপনার $contact_method একটি ভেরিফিকেশন কোড পাঠানো হয়েছে।";
            
        } else {
            $error = "দুঃখিত, এই তথ্যের সাথে কোনো অ্যাকাউন্ট খুঁজে পাওয়া যায়নি।";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lost Login - bet365</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Hind+Siliguri:wght@400;500;600&display=swap');

        body { 
            background-color: #133729; /* bet365 Main Green */
            color: #dbece3;
            font-family: 'Roboto', 'Hind Siliguri', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header Style */
        .header {
            background-color: #133729;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #275645;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        /* Logo Font */
        .brand-logo {
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
            font-size: 24px;
            letter-spacing: -0.5px;
        }
        .text-bet { color: #ffffff; }
        .text-365 { color: #ffdf1b; } /* bet365 Yellow */

        /* Form Container */
        .login-container {
            background-color: #1c4e3f; /* Slightly lighter green card */
            border-radius: 8px;
            padding: 30px 20px;
            width: 100%;
            max-width: 400px;
            margin: auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        /* Inputs */
        .b365-input {
            width: 100%;
            background-color: #ffffff;
            border: 1px solid #ccc;
            color: #333;
            padding: 12px 15px;
            border-radius: 4px;
            font-size: 15px;
            transition: border-color 0.2s;
        }
        .b365-input:focus {
            outline: none;
            border-color: #133729;
            box-shadow: 0 0 0 2px rgba(19, 55, 41, 0.2);
        }
        .input-label {
            color: #a7c7b8;
            font-size: 13px;
            margin-bottom: 6px;
            display: block;
        }

        /* Buttons */
        .btn-action {
            width: 100%;
            background-color: #ffdf1b; /* The Yellow */
            color: #133729; /* Dark text on yellow */
            font-weight: 700;
            padding: 12px;
            border-radius: 4px;
            border: none;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-action:hover {
            background-color: #eacc19;
        }

        .back-link {
            color: #a7c7b8;
            font-size: 14px;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; color: #fff; }

        /* Messages */
        .msg-box {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .msg-success { background-color: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .msg-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }

    </style>
</head>
<body>

    <div class="header justify-between">
        <div class="brand-logo">
            <span class="text-bet">bet</span><span class="text-365">365</span>
        </div>
        <a href="login.php" class="text-sm text-[#a7c7b8] hover:text-white font-medium">লগ ইন</a>
    </div>

    <div class="flex-1 flex flex-col items-center justify-center p-4">
        
        <div class="login-container">
            <div class="text-center mb-6">
                <h2 class="text-white text-xl font-bold mb-2">লগইন করতে সমস্যা?</h2>
                <p class="text-[#a7c7b8] text-sm">আপনার অ্যাকাউন্ট পুনরুদ্ধার করতে নিচের তথ্য দিন।</p>
            </div>

            <?php if($msg): ?>
                <div class="msg-box msg-success">
                    <i class="fas fa-check-circle mr-1"></i> <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="msg-box msg-error">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-6">
                    <label class="input-label">ইউজারনেম, ইমেইল অথবা ফোন নম্বর</label>
                    <input type="text" name="user_input" required class="b365-input" placeholder="আপনার তথ্য লিখুন...">
                </div>

                <button type="submit" class="btn-action">
                    কোড পাঠান
                </button>
            </form>

            <div class="mt-6 text-center border-t border-[#2b6b55] pt-4">
                <p class="text-[#a7c7b8] text-xs mb-2">সাহায্য প্রয়োজন?</p>
                <a href="support_chat.php" class="text-[#ffdf1b] text-sm hover:underline">কাস্টমার সাপোর্টে যোগাযোগ করুন</a>
            </div>
        </div>

        <div class="mt-8 text-center">
             <a href="login.php" class="back-link"><i class="fas fa-arrow-left mr-1"></i> ফিরে যান</a>
        </div>

    </div>

    <div class="py-4 text-center text-[#55826f] text-xs">
        &copy; 2001-<?php echo date('Y'); ?> bet365. All rights reserved.
    </div>

</body>
</html>