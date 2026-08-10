<?php
// API Credentials & Setup
$apiKey = "BETAPI_TEST_12345";
$apiUrl = "https://exchange.betapidata.com/api.php";

session_start();
// ডায়নামিক ইউজারনেম
$user = isset($_SESSION['user_id']) ? "USER_" . $_SESSION['user_id'] : "test6656";

// 1. Get URL from API
$ch = curl_init($apiUrl . "?action=get_game_url");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-API-KEY: $apiKey"]);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["username" => $user]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);
$game_url = isset($res['game_url']) ? $res['game_url'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Live Game</title>
    
    <style>
        /* Reset & Basics */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body, html {
            width: 100%; 
            height: 100%; 
            background-color: #000;
            font-family: sans-serif;
            /* overflow: hidden;  <-- এই লাইনটি বাদ দিয়েছি যাতে স্ক্রল করা যায় */
            -webkit-overflow-scrolling: touch; /* iOS-এ স্মুথ স্ক্রলিংয়ের জন্য */
        }

        /* The Game Iframe */
        #gameFrame {
            width: 100%;
            height: 100%;
            min-height: 100vh; /* অন্তত পুরো স্ক্রিন জুড়ে থাকবে */
            border: none;
            display: block;
        }

        /* Loading Screen */
        #loader {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: #111;
            z-index: 20;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #facc15;
            transition: opacity 0.5s ease;
        }
        
        .spinner {
            width: 50px; height: 50px;
            border: 4px solid rgba(250, 204, 21, 0.3);
            border-top: 4px solid #facc15;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        .loading-text { font-size: 14px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; }

        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Floating Back Button */
        .back-btn {
            position: fixed;
            top: 15px; left: 15px;
            z-index: 30;
            width: 35px; height: 35px;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .back-btn svg { width: 18px; height: 18px; fill: white; }

        /* Error Message */
        .error-msg {
            color: #ef4444; text-align: center; padding: 20px;
            margin-top: 50px;
        }
    </style>
</head>
<body>

    <a href="dashboard.php" class="back-btn">
        <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>

    <?php if ($game_url): ?>
        <div id="loader">
            <div class="spinner"></div>
            <div class="loading-text">Loading...</div>
        </div>

        <iframe id="gameFrame" src="<?php echo $game_url; ?>" allowfullscreen scrolling="auto"></iframe>

        <script>
            const iframe = document.getElementById('gameFrame');
            const loader = document.getElementById('loader');

            iframe.onload = function() {
                setTimeout(() => {
                    loader.style.opacity = '0';
                    setTimeout(() => { loader.style.display = 'none'; }, 500);
                }, 1000);
            };
        </script>
    <?php else: ?>
        <div class="error-msg">
            <h2>Game Error</h2>
            <p>Could not connect to the game server.</p>
            <br>
            <a href="dashboard.php" style="color:#facc15;">Go Back</a>
        </div>
    <?php endif; ?>

</body>
</html>