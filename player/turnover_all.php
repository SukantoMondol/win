<?php
session_start();
// ১. ডাটাবেজ কানেকশন (ম্যানুয়াল কানেকশন রিমুভড)
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';

if (file_exists($db_path)) {
    require $db_path;
} else {
    $conn = new mysqli('localhost', 'root', '', 'bating');
}

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = (int)$_SESSION['user_id'];

require_once __DIR__ . '/bet_record_helpers.php';

// --- Fetch Real Data ---
// game_bet_history টেবিলে status/bet_amount কলাম নেই; এই কারণে page 500 হচ্ছিল।
// Shared safe loader game_logs ব্যবহার করে এবং প্রয়োজনে raw API history aggregate করে।
$active_records = player_bet_records($conn, $uid, 'active', 50);
$completed_records = player_bet_records($conn, $uid, 'completed', 50);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Turnover</title> <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #f0f2f5;
            --header-color: #154b77;
            --accent-color: #43a047;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --blue-light: #e0f2fe;
        }

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

        .header-bg { 
            background: linear-gradient(135deg, #0f395c 0%, #1a5c92 100%);
            padding: 16px; 
            display: flex; justify-content: space-between; align-items: center; 
            position: sticky; top: 0; z-index: 50; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tabs-container { 
            display: flex; 
            background-color: var(--card-bg); 
            border-bottom: 1px solid var(--border-color); 
            position: sticky; top: 56px; z-index: 40;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .tab-btn { 
            flex: 1; text-align: center; padding: 14px 0; 
            font-size: 13px; font-weight: 700; color: var(--text-muted); 
            cursor: pointer; position: relative; transition: 0.2s;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .tab-btn.active { color: var(--header-color); font-weight: 900; }
        .tab-btn.active::after {
            content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px;
            background-color: var(--accent-color);
        }

        .tab-content { display: none; animation: fadeIn 0.3s ease; padding-top: 15px; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .data-card { 
            background-color: var(--card-bg); 
            padding: 16px; margin: 0 15px 12px 15px;
            border-radius: 12px; border: 1px solid var(--border-color); 
            display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.02); transition: all 0.2s;
        }
        .data-card:active { transform: scale(0.98); border-color: #bae6fd; box-shadow: 0 4px 10px rgba(21, 75, 119, 0.08); }

        .card-icon {
            width: 40px; height: 40px; border-radius: 8px;
            background: #f8fafc; border: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: center;
            color: var(--header-color); font-size: 18px; margin-right: 12px;
            flex-shrink: 0;
        }

        .data-val { font-size: 14px; color: var(--header-color); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;}
        .data-label { font-size: 11.5px; color: var(--text-muted); font-weight: 500;}

        .badge-running { color: #92400e; background: #fef3c7; padding: 4px 8px; border-radius: 4px; border: 1px solid #fde68a; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block;}
        .badge-win { color: #166534; background: #dcfce7; padding: 4px 8px; border-radius: 4px; border: 1px solid #bbf7d0; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block;}
        .badge-loss { color: #991b1b; background: #fee2e2; padding: 4px 8px; border-radius: 4px; border: 1px solid #fecaca; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block;}

        .no-data { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 50vh; opacity: 0.8; }
    </style>
</head>
<body>

    <div class="header-bg">
        <a href="account.php" class="text-white text-xl p-1"><i class="fas fa-chevron-left"></i></a>
        <h1 class="text-white font-bold text-[15px] uppercase tracking-wider">Turnover</h1>
        <button onclick="window.history.back()" class="text-white text-xl p-1"><i class="fas fa-times"></i></button>
    </div>

    <div class="tabs-container">
        <div id="tab-active-btn" class="tab-btn active" onclick="switchTab('active', this)">Active Bets</div>
        <div id="tab-completed-btn" class="tab-btn" onclick="switchTab('completed', this)">Completed</div>
    </div>

    <div id="active" class="tab-content active">
        <?php if(!empty($active_records)): ?>
            <?php foreach($active_records as $row): 
                $gameName = !empty($row['game_name']) ? $row['game_name'] : 'Casino Game';
            ?>
                <div class="data-card">
                    <div class="flex items-center">
                        <div class="card-icon"><i class="fas fa-gamepad"></i></div>
                        <div>
                            <p class="data-val truncate max-w-[150px]"><?php echo htmlspecialchars($gameName); ?></p>
                            <p class="data-label"><?php echo date('d M, Y - h:i A', strtotime($row['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[#154b77] font-mono font-black text-[15px] mb-1">৳ <?php echo number_format($row['bet_amount'], 2); ?></p>
                        <span class="badge-running">Running</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4 border border-gray-200">
                    <i class="fas fa-spinner fa-spin text-3xl text-gray-300"></i>
                </div>
                <p class="font-bold text-sm text-gray-500 uppercase tracking-wide">No Active Bets</p>
                <p class="text-xs mt-1 text-gray-400 font-medium">You have no running games at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="completed" class="tab-content">
        <?php if(!empty($completed_records)): ?>
            <?php foreach($completed_records as $row): 
                $bet = floatval($row['bet_amount']);
                $win = floatval($row['win_amount'] ?? 0);
                $profit = $win - $bet;
                $isWin = ($profit >= 0);
                $badgeClass = $isWin ? 'badge-win' : 'badge-loss';
                $sign = $isWin ? '+' : '';
                $statusTxt = ucfirst($row['status']);
                $gameName = !empty($row['game_name']) ? $row['game_name'] : 'Casino Game';
            ?>
                <div class="data-card">
                    <div class="flex items-center">
                        <div class="card-icon"><i class="fas fa-dice"></i></div>
                        <div>
                            <p class="data-val truncate max-w-[140px]"><?php echo htmlspecialchars($gameName); ?></p>
                            <p class="data-label">Turnover: <span class="font-bold text-[#154b77]">৳ <?php echo number_format($bet, 2); ?></span></p>
                            <p class="text-[10px] text-gray-400 mt-0.5 font-medium"><?php echo date('d M, h:i A', strtotime($row['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-mono font-black text-[15px] mb-1 <?php echo $isWin ? 'text-[#166534]' : 'text-[#991b1b]'; ?>">
                            <?php echo $sign . ' ৳ ' . number_format($profit, 2); ?>
                        </p>
                        <span class="<?php echo $badgeClass; ?>"><?php echo $statusTxt; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4 border border-gray-200">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300"></i>
                </div>
                <p class="font-bold text-sm text-gray-500 uppercase tracking-wide">No History Found</p>
                <p class="text-xs mt-1 text-gray-400 font-medium">Your completed bets will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }
    </script>

</body>
</html>