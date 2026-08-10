<?php
session_start();
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
} else {
    $conn = new mysqli('localhost', 'root', '', 'bating');
}

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Fetch User
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if ($user) {
        if (!password_verify($current_pass, $user['password'])) {
            $msg = "বর্তমান পাসওয়ার্ড ভুল!";
            $msg_type = "error";
        } elseif ($new_pass !== $confirm_pass) {
            $msg = "নতুন পাসওয়ার্ড মিলছে না!";
            $msg_type = "error";
        } elseif (strlen($new_pass) < 6 || strlen($new_pass) > 20) {
            $msg = "পাসওয়ার্ড ৬-২০ অক্ষরের মধ্যে হতে হবে।";
            $msg_type = "error";
        } else {
            // Update Password
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $new_hash, $user_id);
            
            if ($update->execute()) {
                $msg = "পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে!";
                $msg_type = "success";
            } else {
                $msg = "ত্রুটি হয়েছে, আবার চেষ্টা করুন।";
                $msg_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Change Password | SHA75</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        :root {
            /* 1xbet Color Palette */
            --bg-color: #f0f2f5;
            --header-color: #154b77;
            --accent-color: #43a047;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --blue-light: #e0f2fe;
        }

        /* Prevent zooming & horizontal scroll */
        html, body {
            max-width: 100vw;
            overflow-x: hidden;
            touch-action: pan-y pinch-zoom;
        }

        body { 
            background-color: var(--bg-color); 
            color: var(--text-main); 
            font-family: 'Roboto', sans-serif; 
            padding-bottom: 90px; 
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }
        input { user-select: auto; }

        /* --- Header --- */
        .header-bg { 
            background: linear-gradient(135deg, #0f395c 0%, #1a5c92 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* --- Form Elements --- */
        .form-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 20px 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
        }

        .form-label { 
            display: block; 
            color: var(--header-color); 
            font-size: 11.5px; 
            font-weight: 800;
            margin-bottom: 6px; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            background-color: #f8fafc;
            border-radius: 8px;
            transition: all 0.3s ease;
            overflow: hidden;
            border: 1.5px solid var(--border-color);
            margin-bottom: 16px;
        }
        .input-wrapper:focus-within {
            background-color: #ffffff;
            border-color: var(--header-color);
            box-shadow: 0 0 0 3px rgba(21, 75, 119, 0.1);
        }

        .custom-input {
            width: 100%;
            background: transparent;
            border: none;
            padding: 14px 45px 14px 45px; /* Space for both left and right icons */
            color: var(--header-color);
            font-size: 14px;
            font-weight: 600;
            outline: none;
            letter-spacing: 1px;
        }
        .custom-input::placeholder { color: #94a3b8; font-weight: 500; letter-spacing: normal; }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            transition: color 0.3s;
        }
        .input-wrapper:focus-within .input-icon { color: var(--header-color); }

        .eye-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            font-size: 15px;
            transition: color 0.3s;
            padding: 5px;
        }
        .eye-icon:hover { color: var(--header-color); }

        /* --- Validation List --- */
        .validation-list {
            background-color: var(--blue-light);
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 20px;
        }
        .valid-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: var(--text-muted);
            margin-bottom: 8px;
            transition: color 0.3s;
            font-weight: 600;
        }
        .valid-item:last-child { margin-bottom: 0; }
        .valid-item i { font-size: 14px; transition: color 0.3s; }
        
        .valid-item.success { color: #154b77; font-weight: 700; }
        .valid-item.success i { color: var(--accent-color); } /* Green check */

        /* --- Confirm Button --- */
        .btn-confirm {
            background: linear-gradient(to bottom, #4caf50 0%, #388e3c 100%);
            color: #ffffff;
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 14px;
            border: none;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 6px rgba(67, 160, 71, 0.2);
            transition: all 0.2s;
        }
        .btn-confirm:active {
            transform: translateY(2px);
            box-shadow: 0 2px 4px rgba(67, 160, 71, 0.2);
        }

        /* Bottom Nav */
        .bottom-nav { background: #ffffff; border-top: 1px solid var(--border-color); box-shadow: 0 -4px 15px rgba(0,0,0,0.03); }
        .nav-item { color: #94a3b8; transition: all 0.2s; } 
        .nav-item.active { color: var(--header-color); }
    </style>
</head>
<body>

    <div class="header-bg p-4 flex justify-between items-center sticky top-0 z-50">
        <a href="index.php" class="text-white text-xl p-1"><i class="fas fa-home"></i></a>
        <h1 class="text-white font-bold text-[15px] uppercase tracking-wider">Change Password</h1>
        <button onclick="window.history.back()" class="text-white text-xl p-1"><i class="fas fa-times"></i></button>
    </div>

    <div class="px-4 mt-5">
        
        <div class="flex flex-col items-center mb-6">
            <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-3 border border-blue-100 shadow-sm">
                <i class="fas fa-shield-alt text-3xl text-[#154b77]"></i>
            </div>
            <h2 class="text-[#154b77] text-lg font-black uppercase tracking-wide">Security Setup</h2>
            <p class="text-xs text-gray-500 font-medium mt-1">Keep your account safe with a strong password</p>
        </div>

        <?php if($msg): ?>
            <div class="<?php echo $msg_type=='success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'; ?> border-l-4 p-3 rounded shadow-sm mb-5 flex items-start gap-2 text-[13px] font-bold">
                <i class="fas <?php echo $msg_type=='success' ? 'fa-check-circle text-green-500 mt-0.5' : 'fa-exclamation-circle text-red-500 mt-0.5'; ?>"></i>
                <span><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST">
                
                <label class="form-label">Current password</label>
                <div class="input-wrapper">
                    <i class="fas fa-unlock-alt input-icon"></i>
                    <input type="password" name="current_password" id="currPass" class="custom-input" placeholder="Enter current password" required>
                    <i class="fas fa-eye-slash eye-icon" onclick="togglePass('currPass', this)"></i>
                </div>

                <label class="form-label mt-2">New password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="new_password" id="newPass" class="custom-input" placeholder="Enter new password" required oninput="validatePassword(this.value)">
                    <i class="fas fa-eye-slash eye-icon" onclick="togglePass('newPass', this)"></i>
                </div>

                <div class="validation-list">
                    <div class="valid-item" id="rule-length">
                        <i class="fas fa-check-circle"></i> Between 6~20 characters.
                    </div>
                    <div class="valid-item" id="rule-alpha">
                        <i class="fas fa-check-circle"></i> At least one alphabet.
                    </div>
                    <div class="valid-item" id="rule-num">
                        <i class="fas fa-check-circle"></i> At least one number. (Symbols allowed)
                    </div>
                </div>

                <label class="form-label">Confirm new password</label>
                <div class="input-wrapper">
                    <i class="fas fa-key input-icon"></i>
                    <input type="password" name="confirm_password" id="confPass" class="custom-input" placeholder="Confirm new password" required>
                    <i class="fas fa-eye-slash eye-icon" onclick="togglePass('confPass', this)"></i>
                </div>

                <button type="submit" class="btn-confirm">Update Password</button>

            </form>
        </div>

    </div>

    <div class="fixed bottom-0 left-0 w-full h-[65px] bottom-nav z-50 flex justify-between items-center px-4">
        <a href="index.php" class="flex flex-col items-center justify-center nav-item">
            <i class="fas fa-home text-[22px] mb-1"></i>
            <span class="text-[10px] font-bold tracking-wide">Home</span>
        </a>
        <a href="promotions.php" class="flex flex-col items-center justify-center nav-item">
            <i class="fas fa-gift text-[22px] mb-1"></i>
            <span class="text-[10px] font-bold tracking-wide">Promos</span>
        </a>
        <a href="deposit.php" class="flex flex-col items-center justify-center nav-item">
            <i class="fas fa-wallet text-[22px] mb-1"></i>
            <span class="text-[10px] font-bold tracking-wide">Deposit</span>
        </a>
        <a href="account.php" class="flex flex-col items-center justify-center nav-item active">
            <i class="fas fa-user-circle text-[22px] mb-1"></i>
            <span class="text-[10px] font-bold tracking-wide">Account</span>
        </a>
    </div>

    <script>
        // Toggle Password Visibility
        function togglePass(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                icon.style.color = '#154b77'; // Blue when visible
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                icon.style.color = '#94a3b8'; // Gray when hidden
            }
        }

        // Real-time Validation
        function validatePassword(val) {
            // Rule 1: Length 6-20
            const ruleLength = document.getElementById('rule-length');
            if (val.length >= 6 && val.length <= 20) {
                ruleLength.classList.add('success');
            } else {
                ruleLength.classList.remove('success');
            }

            // Rule 2: At least one alphabet
            const ruleAlpha = document.getElementById('rule-alpha');
            if (/[a-zA-Z]/.test(val)) {
                ruleAlpha.classList.add('success');
            } else {
                ruleAlpha.classList.remove('success');
            }

            // Rule 3: At least one number
            const ruleNum = document.getElementById('rule-num');
            if (/\d/.test(val)) {
                ruleNum.classList.add('success');
            } else {
                ruleNum.classList.remove('success');
            }
        }
    </script>

</body>
</html>