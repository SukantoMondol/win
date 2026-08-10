<?php
ob_start();
session_start();
// ডাটাবেস কানেকশন
$db_path = file_exists('../includes/db.php') ? '../includes/db.php' : 'includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
} else {
    $conn = new mysqli('localhost', 'root', '', 'bating');
}
require_once __DIR__ . '/../includes/referral_system_helper.php';
wcb_referral_ensure_schema($conn);

$incoming_ref = trim($_GET['ref'] ?? ($_GET['invite'] ?? ($_GET['code'] ?? ($_POST['ref'] ?? ($_COOKIE['wcb_ref_code'] ?? '')))));
if ($incoming_ref !== '') {
    setcookie('wcb_ref_code', $incoming_ref, time() + (86400 * 30), '/');
}

// সেটিংস
$settings = [];
if (isset($conn) && !$conn->connect_error) {
    $set_q = @$conn->query("SELECT * FROM settings WHERE id=1");
    if ($set_q && $set_q->num_rows > 0) { $settings = $set_q->fetch_assoc(); }
}
$site_name = !empty($settings['site_name']) ? $settings['site_name'] : 'Lucky365';
$app_logo_src = !empty($settings['app_logo']) ? '../' . ltrim($settings['app_logo'], '/') : '';
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ইনপুট স্যানিটাইজেশন
    $mobile = mysqli_real_escape_string($conn, trim($_POST['mobile']));
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];
    
    // Username as mobile to bypass backend requirement silently
    $username = $mobile; 
    
    // ১. ভ্যালিডেশন
    if ($pass !== $confirm_pass) {
        $error = "পাসওয়ার্ড দুটি মিলছে না!";
    } elseif (!is_numeric($mobile)) {
        $error = "মোবাইল নম্বর শুধুমাত্র সংখ্যা হতে হবে।";
    } elseif (strlen($mobile) != 11) {
        $error = "মোবাইল নম্বর অবশ্যই ১১ সংখ্যার হতে হবে।";
    } elseif (strlen($pass) < 6) {
        $error = "পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।";
    } else {
        // ২. ডুপ্লিকেট চেক
        $check = $conn->query("SELECT id FROM users WHERE phone='$mobile' OR username='$username'");
        
        if ($check && $check->num_rows > 0) {
            $error = "এই মোবাইল নম্বরটি ইতিমধ্যে নিবন্ধিত।";
        } else {
            // ৩. ইউজার তৈরি
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $my_new_ref_code = 'REF' . rand(10000, 99999) . time(); 
            $final_email = "user_" . time() . rand(100,999) . "@lucky365.com";

            $referrer_id = wcb_referral_resolve_code($conn, $incoming_ref);
            $safe_ref_code = mysqli_real_escape_string($conn, $my_new_ref_code);
            $sql = "INSERT INTO users (username, phone, email, password, role, status, created_at, balance, ref_code, referrer_id) 
                    VALUES ('$username', '$mobile', '$final_email', '$hashed_pass', 'player', 'active', NOW(), 0.00, '$safe_ref_code', $referrer_id)";
            
            if ($conn->query($sql)) {
                $new_uid = $conn->insert_id;
                $conn->query("INSERT INTO player_profiles (user_id) VALUES ($new_uid)");
                
                $_SESSION['user_id'] = $new_uid;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'player';
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "নিবন্ধন ব্যর্থ হয়েছে।";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register | <?php echo $site_name; ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        /* Base reset and dark background */
        body, html {
            margin: 0;
            padding: 0;
            background-color: #042e23;
            background-image: linear-gradient(180deg, #073f32 0%, #032119 100%);
            font-family: 'Roboto', Arial, sans-serif;
            color: #ffffff;
            min-height: 100vh;
        }

        .mobile-container {
            max-width: 450px;
            margin: 0 auto;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* Back Button */
        .back-btn {
            color: #ffcc00;
            font-size: 20px;
            text-decoration: none;
            margin-top: 10px;
            display: inline-block;
            transition: opacity 0.2s;
        }
        .back-btn:active { opacity: 0.6; }

        /* Logo and Titles */
        .logo-text {
            text-align: center;
            font-size: 34px;
            font-weight: 900;
            font-style: italic;
            color: #ffcc00;
            font-family: "Arial Black", sans-serif;
            letter-spacing: 1.5px;
            margin-top: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.4);
        }
        .logo-text img { max-width: 150px; max-height: 70px; object-fit: contain; display: inline-block; }
        .page-title {
            text-align: center;
            color: #ffcc00;
            font-size: 24px;
            font-weight: bold;
            margin-top: 25px;
        }
        .login-link {
            text-align: center;
            font-size: 14px;
            margin-top: 8px;
            margin-bottom: 25px;
            color: #ffffff;
        }
        .login-link a {
            color: #1de9b6;
            text-decoration: underline;
            font-weight: 500;
        }

        /* Error Box */
        .error-box {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        /* Form Inputs */
        .input-group {
            position: relative;
            margin-bottom: 16px;
        }
        .input-group input {
            width: 100%;
            background-color: #021e18;
            border: 1px solid #094a3e;
            border-radius: 10px;
            padding: 16px 16px 16px 48px;
            color: #ffffff;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        .input-group input::placeholder {
            color: #1de9b6;
            font-weight: 500;
        }
        .input-group input:focus { border-color: #1de9b6; }
        
        .input-group i.left-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #1de9b6;
            font-size: 16px;
        }
        .input-group i.right-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #1de9b6;
            font-size: 16px;
            cursor: pointer;
        }

        /* Checkboxes */
        .checkbox-wrapper {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            padding: 0 4px;
        }
        .custom-checkbox {
            appearance: none;
            min-width: 22px;
            width: 22px;
            height: 22px;
            background-color: transparent;
            border: 1.5px solid #1de9b6;
            border-radius: 50%;
            position: relative;
            cursor: pointer;
            outline: none;
            margin-right: 12px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .custom-checkbox:checked {
            background-color: #ffcc00;
            border-color: #ffcc00;
        }
        .custom-checkbox:checked::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 12px;
            color: #000;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .checkbox-text {
            font-size: 11px;
            color: #ffffff;
            line-height: 1.4;
        }

        /* 3D Button */
        .btn-register {
            width: 100%;
            background: linear-gradient(180deg, #ffdf00 0%, #ff9d00 100%);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 8px;
            padding: 14px;
            font-size: 18px;
            font-weight: 900;
            color: #8c2800;
            cursor: pointer;
            box-shadow: 0 4px 0px #c57300, 0 6px 10px rgba(0,0,0,0.3);
            transition: transform 0.1s, box-shadow 0.1s;
            margin-top: 10px;
            margin-bottom: 25px;
        }
        .btn-register:active {
            transform: translateY(4px);
            box-shadow: 0 0px 0px #c57300, 0 2px 4px rgba(0,0,0,0.3);
        }

        /* Social Icons */
        .social-row {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        .social-btn {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s;
        }
        .social-btn:active { transform: scale(0.9); }
        .social-btn.fb { background: #3b5998; color: #ffffff; }
        .social-btn.google { background: #ffffff; border: 1px solid #ddd; }
        .google-icon { width: 26px; height: 26px; }
    </style>
</head>
<body>

    <div class="mobile-container">
        
        <a href="javascript:history.back()" class="back-btn">
            <i class="fa-solid fa-chevron-left"></i>
        </a>

        <div class="logo-text">
            <?php if(!empty($app_logo_src)): ?>
                <img src="<?php echo htmlspecialchars($app_logo_src); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($site_name); ?>">
            <?php else: ?>
                <?php echo htmlspecialchars($site_name); ?>
            <?php endif; ?>
        </div>

        <div class="page-title">Register</div>
        <div class="login-link">
            Already have an account? <a href="login.php">Login</a>
        </div>

        <?php if($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-triangle"></i> 
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" onsubmit="return validatePasswords()">
            <input type="hidden" name="ref" value="<?php echo htmlspecialchars($incoming_ref, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="input-group">
                <i class="fa-solid fa-phone left-icon"></i>
                <input type="tel" name="mobile" id="mobileInput" placeholder="Phone number" required maxlength="11" value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>">
            </div>

            <div class="input-group">
                <i class="fa-solid fa-lock left-icon"></i>
                <input type="password" name="password" id="password" placeholder="Password" required>
                <i class="fa-solid fa-eye-slash right-icon" id="togglePass1" onclick="togglePassword('password', 'togglePass1')"></i>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-lock left-icon"></i>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>
                <i class="fa-solid fa-eye-slash right-icon" id="togglePass2" onclick="togglePassword('confirm_password', 'togglePass2')"></i>
            </div>

            <div class="checkbox-wrapper">
                <input type="checkbox" name="terms" class="custom-checkbox" checked required>
                <div class="checkbox-text">
                    I am over 18 years of age and have read and accepted Terms & Conditions, Privacy Policy & Betting Rules as published on the site.
                </div>
            </div>

            <div class="checkbox-wrapper">
                <input type="checkbox" name="promos" class="custom-checkbox">
                <div class="checkbox-text">
                    I would like to receive details of special offers, free bets and other promotions.
                </div>
            </div>

            <button type="submit" class="btn-register">Register</button>

        </form>

        <div class="social-row">
            <a href="#" class="social-btn fb">
                <i class="fa-brands fa-facebook-f"></i>
            </a>
            <a href="#" class="social-btn google">
                <svg class="google-icon" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    <path d="M1 1h22v22H1z" fill="none"/>
                </svg>
            </a>
        </div>

    </div>

    <script>
        // Password Visibility Toggle
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const icon = document.getElementById(iconId);

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }

        // Input Format Handlers
        document.getElementById('mobileInput').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Frontend Password Match Validation
        function validatePasswords() {
            const pass = document.getElementById('password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (pass !== confirmPass) {
                alert("পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলছে না!");
                return false;
            }
            return true;
        }
    </script>

<?php include __DIR__ . '/../includes/modal_popup.php'; ?>
</body>
</html>