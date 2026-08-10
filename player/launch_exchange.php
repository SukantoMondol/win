<?php
session_start();
require '../includes/db.php';
require '../includes/betapi_helper.php';

// ১. লগইন চেক
if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = $_SESSION['user_id'];
$username = "USER_" . $user_id; // ইউনিক ইউজারনেম জেনারেট করা হলো

// ২. ইউজারকে এক্সচেঞ্জ সার্ভারে রেজিস্টার করার চেষ্টা করা
// (API যদি ডুপ্লিকেট এরর দেয়, তার মানে ইউজার অলরেডি আছে, আমরা ইগনোর করব)
registerExchangeUser($username);

// ৩. গেম ইউআরএল জেনারেট করা
$gameData = getExchangeUrl($username);

if (!isset($gameData['game_url'])) {
    die("Error loading game. Please try again.");
}

$gameUrl = $gameData['game_url'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sports Exchange</title>
    <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #000; }
        iframe { width: 100%; height: 100vh; border: none; }
        .back-btn {
            position: absolute; top: 10px; left: 10px; z-index: 1000;
            background: #facc15; color: #000; padding: 5px 15px;
            text-decoration: none; font-weight: bold; border-radius: 5px;
            font-family: sans-serif;
        }
    </style>
</head>
<body>

    <a href="dashboard.php" class="back-btn">← Back</a>
    
    <iframe src="<?php echo $gameUrl; ?>" allowfullscreen></iframe>

</body>
</html>