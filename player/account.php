<?php
session_start();
// ১. ডাটাবেজ কানেকশন (ম্যানুয়াল কানেকশন রিমুভড)
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);
$user_query = $conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1");
$user = $user_query ? $user_query->fetch_assoc() : null;

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$settings_query = $conn->query("SELECT * FROM settings WHERE id=1");
$settings = $settings_query ? $settings_query->fetch_assoc() : [];
$site_name = $settings['site_name'] ?? 'BAJIXWIN';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Account - <?php echo $site_name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #0b0b0b; 
            color: #ffffff;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0; padding-bottom: 80px; /* Adjusted for bottom nav */
        }

        .header-bg {
            background: linear-gradient(to bottom, #2c2c2c 0%, #0b0b0b 100%);
            padding: 20px 15px 70px 15px;
            text-align: center;
        }

        .user-card {
            background: linear-gradient(135deg, #fdfdfe 0%, #e8ebf2 100%);
            border-radius: 25px;
            margin: -50px 15px 20px 15px;
            padding: 20px;
            position: relative;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            color: #333;
        }

        .sign-in-tag {
            position: absolute;
            top: 0; right: 0;
            background: #d32f2f;
            color: white;
            padding: 5px 15px;
            border-top-right-radius: 20px;
            border-bottom-left-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: flex; align-items: center; gap: 5px;
        }

        .avatar-box {
            width: 85px; height: 85px;
            border-radius: 50%;
            border: 4px solid #fff;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .vip-tag {
            background: #62656a;
            color: #fff;
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 10px;
            text-transform: uppercase;
        }

        .action-btn {
            background: #f1f3f8;
            border-radius: 25px;
            padding: 10px;
            font-size: 13px;
            font-weight: bold;
            color: #444;
            flex: 1;
            text-align: center;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }

        .menu-container {
            background-color: #161616;
            border-radius: 30px 30px 0 0;
            padding-top: 20px;
            margin-top: 10px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px 5px;
            padding: 20px 10px;
        }

        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            position: relative;
        }

        .icon-box {
            width: 52px; height: 52px;
            background: #252525; 
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            color: #d1b894;
            font-size: 22px;
            border: 1px solid #333;
        }

        .menu-label {
            font-size: 10px;
            color: #a0a0a0;
            text-align: center;
            line-height: 1.2;
            font-weight: 500;
        }

        .badge-count {
            position: absolute;
            top: -5px; right: 8px;
            background: #ff3b30;
            color: white;
            font-size: 9px;
            min-width: 18px; height: 18px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            border: 1.5px solid #161616;
            font-weight: bold;
        }

        .section-title {
            color: #555;
            font-size: 12px;
            font-weight: bold;
            padding-left: 20px;
            margin-bottom: 10px;
            display: flex; align-items: center;
        }
        .section-title::after {
            content: ""; height: 1px; flex: 1; background: #333; margin-left: 10px;
        }
    </style>
</head>
<body>

    <div class="header-bg">
        <div class="flex justify-between items-center text-white mb-8">
            <a href="index.php"><i class="fas fa-chevron-left text-xl"></i></a>
            <span class="font-bold text-lg uppercase tracking-tight">My Account</span>
            <div class="w-6"></div>
        </div>
    </div>

    <div class="user-card">
        <div class="sign-in-tag">
            <i class="far fa-calendar-check"></i> Sign In <i class="fas fa-chevron-right text-[8px]"></i>
        </div>

        <div class="flex items-center gap-4">
            <div class="avatar-box">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['username'] ?? 'User'); ?>&background=ffd700&color=333&bold=true" class="w-full h-full object-cover">
            </div>
            <div class="flex-1">
                <div class="vip-tag flex items-center w-fit gap-1">
                    <i class="fas fa-medal text-[8px]"></i> VIP1
                </div>
                <div class="text-xl font-extrabold text-gray-800 mt-1 flex items-center gap-2">
                    <?php echo htmlspecialchars($user['username'] ?? 'User'); ?>
                    <i class="far fa-copy text-sm text-gray-400"></i>
                </div>
                <div class="text-[11px] text-gray-400 mt-1">Nickname: <?php echo htmlspecialchars($user['username'] ?? 'User'); ?> <i class="fas fa-pencil-alt text-[9px]"></i></div>
                <div class="text-[10px] text-gray-400">Joined since: <?php echo htmlspecialchars(substr($user['created_at'] ?? '2025-09-09', 0, 10)); ?></div>
            </div>
        </div>

        <div class="mt-6">
            <div class="flex items-center gap-2">
                <span class="text-3xl font-black text-gray-800">৳ <?php echo number_format(floatval($user['balance'] ?? 0), 2); ?></span>
                <i class="fas fa-sync-alt text-gray-400 text-sm cursor-pointer" onclick="location.reload()"></i>
            </div>
        </div>

        <div class="flex gap-2 mt-5">
            <a href="deposit.php" class="action-btn">Deposit</a>
            <a href="withdraw.php" class="action-btn">Withdraw</a>
            <a href="personal_info.php" class="action-btn">My Card</a>
        </div>
    </div>

    <div class="menu-container">
        <div class="section-title">Member Center</div>
        
        <div class="menu-grid">
            <a href="rewards.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-trophy"></i></div>
                <span class="badge-count">3</span>
                <span class="menu-label">Reward Center</span>
            </a>

            <a href="betting_recordsacc.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-star-half-alt"></i></div>
                <span class="menu-label">Betting Records</span>
            </a>

            <a href="turnover_all.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-file-invoice-dollar"></i></div>
                <span class="menu-label">Profit & Loss</span>
            </a>

            <a href="trans_rec_full.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-file-export"></i></div>
                <span class="menu-label">Deposit Records</span>
            </a>

            <a href="withdraw_rec.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-file-import"></i></div>
                <span class="menu-label">Withdrawal Records</span>
            </a>

            <a href="trans_rec_full.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-chart-line"></i></div>
                <span class="menu-label">Account Records</span>
            </a>

            <a href="personal_info.php" class="menu-item">
                <div class="icon-box"><i class="far fa-user"></i></div>
                <span class="menu-label">My Account</span>
            </a>

            <a href="security.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-shield-alt"></i></div>
                <span class="menu-label">Security Center</span>
            </a>

            <a href="share.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-user-friends"></i></div>
                <span class="menu-label">Invite Friends</span>
            </a>

            <a href="mission.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-gift"></i></div>
                <span class="badge-count">2</span>
                <span class="menu-label">Mission</span>
            </a>

            <a href="rebate.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-coins"></i></div>
                <span class="menu-label">Rebate</span>
            </a>

            <a href="inboxper.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-at"></i></div>
                <span class="badge-count">96</span>
                <span class="menu-label">Mail</span>
            </a>

            <a href="feedback.php" class="menu-item">
                <div class="icon-box"><i class="far fa-comment-dots"></i></div>
                <span class="menu-label">Advice</span>
            </a>

            <a href="support_chat.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-headphones-alt"></i></div>
                <span class="menu-label">Customer Service</span>
            </a>

            <a href="help.php" class="menu-item">
                <div class="icon-box"><i class="far fa-question-circle"></i></div>
                <span class="menu-label">Help Center</span>
            </a>

            <a href="../logout.php" class="menu-item">
                <div class="icon-box"><i class="fas fa-sign-out-alt"></i></div>
                <span class="menu-label">Log Out</span>
            </a>
        </div>
    </div>

    <?php include 'bottom_nav.php'; ?>

</body>
</html>