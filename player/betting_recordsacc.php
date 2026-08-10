<?php
session_start();

// ১. ডাটাবেজ কানেকশন (ম্যানুয়াল কানেকশন রিমুভড)
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';

if (file_exists($db_path)) {
    require $db_path;
} else {
    die("Database connection file not found.");
}

// লগিন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$tab = (isset($_GET['tab']) && $_GET['tab'] === 'unsettled') ? 'unsettled' : 'settled';

require_once __DIR__ . '/bet_record_helpers.php';

// ২. ডাটা ফেচিং লজিক
// আগের কোড game_bet_history টেবিলে status/bet_amount/win_amount কলাম ধরে query করছিল,
// কিন্তু এই প্রজেক্টের SQL-এ game_bet_history raw API log এবং bet_amount/status game_logs টেবিলে আছে।
// তাই schema অনুযায়ী safe loader ব্যবহার করা হয়েছে যেন page HTTP 500 না দেয়।
$records = player_bet_records($conn, $user_id, ($tab === 'unsettled' ? 'active' : 'completed'), 50);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Betting Records | </title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        /* Web App Fixes */
        html, body { max-width: 100vw; overflow-x: hidden; touch-action: pan-y pinch-zoom; }
        
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

        /* Tabs Container */
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

        /* Filter Bar */
        .filter-bar { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 12px 16px; background-color: #f8fafc; 
            border-bottom: 1px solid var(--border-color); position: relative; z-index: 30; 
        }
        .date-btn { 
            background-color: var(--card-bg); color: var(--header-color); 
            font-size: 12px; font-weight: 700; padding: 8px 14px; 
            border-radius: 6px; border: 1px solid #bae6fd; 
            display: flex; align-items: center; gap: 6px; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .filter-icon { 
            color: var(--header-color); font-size: 15px; cursor: pointer; 
            padding: 8px; background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        
        /* Dropdown */
        .dropdown-menu { 
            display: none; position: absolute; top: 55px; left: 16px; 
            background-color: var(--card-bg); border: 1px solid var(--border-color); 
            border-radius: 8px; width: 150px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            z-index: 50; overflow: hidden;
        }
        .dropdown-menu.show { display: block; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        .dropdown-item { 
            display: block; padding: 12px 16px; color: var(--text-main); 
            font-size: 12.5px; font-weight: 600; border-bottom: 1px solid var(--border-color); cursor: pointer; 
        }
        .dropdown-item:last-child { border-bottom: none; }
        .dropdown-item:hover { background-color: var(--blue-light); color: var(--header-color); }

        /* Table Design */
        .table-header { 
            display: grid; grid-template-columns: 1fr 1.2fr 0.8fr 1fr; 
            background-color: #e0f2fe; padding: 12px 6px; 
            font-size: 11px; font-weight: 800; color: var(--header-color); 
            text-align: center; border-bottom: 2px solid #bae6fd; 
            text-transform: uppercase;
        }
        .col-item { border-right: 1px solid #bae6fd; }
        .col-item:last-child { border-right: none; }
        
        .data-row { 
            display: grid; grid-template-columns: 1fr 1.2fr 0.8fr 1fr; 
            padding: 14px 6px; font-size: 12px; text-align: center; 
            border-bottom: 1px solid var(--border-color); align-items: center; 
            background-color: var(--card-bg);
            transition: background 0.2s;
        }
        .data-row:active { background-color: #f8fafc; }
        
        /* Profit Colors */
        .profit-win { color: #166534; background: #dcfce7; padding: 4px 6px; border-radius: 4px; border: 1px solid #bbf7d0; display: inline-block; width: 100%;}
        .profit-loss { color: #991b1b; background: #fee2e2; padding: 4px 6px; border-radius: 4px; border: 1px solid #fecaca; display: inline-block; width: 100%;}
        
        .no-data { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 40vh; opacity: 0.8; }
    </style>
</head>
<body>

    <div class="header-bg">
        <a href="account.php" class="text-white text-xl p-1"><i class="fas fa-chevron-left"></i></a>
        <h1 class="text-white font-bold text-[15px] uppercase tracking-wider">Betting Records</h1>
        <button onclick="window.history.back()" class="text-white text-xl p-1"><i class="fas fa-times"></i></button>
    </div>

    <div class="tabs-container">
        <a href="?tab=settled" class="tab-btn <?php echo $tab == 'settled' ? 'active' : ''; ?>">Settled</a>
        <a href="?tab=unsettled" class="tab-btn <?php echo $tab == 'unsettled' ? 'active' : ''; ?>">Unsettled</a>
    </div>

    <div class="filter-bar">
        <button class="date-btn" onclick="toggleDropdown()">
            <i class="far fa-calendar-alt text-[#154b77]"></i>
            <span id="selectedDate">Last 7 days</span> 
            <i class="fas fa-caret-down ml-1 text-[10px]"></i>
        </button>
        
        <div id="dateDropdown" class="dropdown-menu">
            <div class="dropdown-item" onclick="selectDate('Last 7 days')">Last 7 days</div>
            <div class="dropdown-item" onclick="selectDate('Last 1 month')">Last 1 month</div>
            <div class="dropdown-item" onclick="selectDate('All time')">All time</div>
        </div>

        <button class="filter-icon" onclick="openApiModal()">
            <i class="fas fa-filter"></i>
        </button>
    </div>

    <div class="table-header">
        <div class="col-item">Platform</div>
        <div class="col-item">Game Type</div>
        <div class="col-item"><?php echo $tab == 'settled' ? 'Turnover' : 'Bet Amt'; ?></div>
        <div class="col-item"><?php echo $tab == 'settled' ? 'Profit/Loss' : 'Status'; ?></div>
    </div>

    <div class="min-h-[50vh] bg-white">
        <?php if (!empty($records)): ?>
            <?php foreach($records as $rec): 
                // Calculation Logic
                $bet = floatval($rec['bet_amount']);
                $win = floatval($rec['win_amount'] ?? 0);
                
                // Profit Calculation
                $profit = $win - $bet;
                
                // Color Logic
                $p_class = $profit >= 0 ? 'profit-win' : 'profit-loss';
                $p_sign = $profit >= 0 ? '+' : '';
                
                // Details
                $provider = !empty($rec['provider_name']) ? $rec['provider_name'] : 'Unknown'; 
                $game_name = !empty($rec['game_name']) ? $rec['game_name'] : '-';
            ?>
            <div class="data-row">
                <div class="text-[#154b77] font-black text-[11px] truncate px-1 uppercase"><?php echo htmlspecialchars($provider); ?></div>
                <div class="text-gray-500 font-bold text-[10px] truncate px-1 leading-tight" title="<?php echo htmlspecialchars($game_name); ?>"><?php echo htmlspecialchars($game_name); ?></div>
                
                <?php if($tab == 'settled'): ?>
                    <div class="text-gray-700 font-bold font-mono text-[11px]"><?php echo number_format($bet, 2); ?></div>
                    <div class="px-1">
                        <div class="<?php echo $p_class; ?> font-mono font-black text-[12px] shadow-sm">
                            <?php echo $p_sign . number_format($profit, 2); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-[#154b77] font-bold font-mono text-[12px]"><?php echo number_format($bet, 2); ?></div>
                    <div class="text-yellow-600 font-bold text-[10px] uppercase bg-yellow-50 px-2 py-1 rounded border border-yellow-200 inline-block">Pending</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        
        <?php else: ?>
            <div class="no-data">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3 border border-gray-200">
                    <i class="fas fa-file-invoice text-3xl text-gray-300"></i>
                </div>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-wide">No Records Found</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="apiModal" class="fixed inset-0 z-[100] hidden bg-gray-900/60 flex flex-col items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl p-6 text-center w-full max-w-sm shadow-2xl border border-gray-200">
            <div class="mb-5 flex items-center justify-center relative">
                <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center border border-red-100">
                    <i class="fas fa-filter text-red-300 text-4xl"></i>
                    <i class="fas fa-times-circle text-red-500 absolute bottom-0 right-1/4 bg-white rounded-full p-0.5 text-xl"></i>
                </div>
            </div>
            <h3 class="text-[#154b77] text-lg font-black mt-2 leading-relaxed tracking-wide uppercase">
                Filter Not Available
            </h3>
            <p class="text-xs text-gray-500 mt-2 font-medium">Advanced filtering options are currently disabled.</p>
            <button onclick="document.getElementById('apiModal').classList.add('hidden')" class="mt-6 w-full bg-[#154b77] text-white font-bold px-8 py-3 rounded-lg text-sm hover:bg-[#0d2a45] transition uppercase tracking-wide shadow-md">
                Close
            </button>
        </div>
    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('dateDropdown');
            dropdown.classList.toggle('show');
        }

        function selectDate(text) {
            document.getElementById('selectedDate').innerText = text;
            document.getElementById('dateDropdown').classList.remove('show');
        }

        window.onclick = function(event) {
            if (!event.target.closest('.date-btn')) {
                var dropdowns = document.getElementsByClassName("dropdown-menu");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        function openApiModal() {
            document.getElementById('apiModal').classList.remove('hidden');
        }
    </script>
</body>
</html>