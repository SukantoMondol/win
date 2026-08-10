<?php
session_start();
// ১. ডাটাবেজ কানেকশন (ম্যানুয়াল কানেকশন রিমুভড)
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
}

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// --- Create Notifications Table (If not exists) ---
$conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// --- Insert Welcome Msg if empty (For Demo) ---
$check = $conn->query("SELECT id FROM notifications WHERE user_id=$uid");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES ($uid, 'Sign up success.', 'Congratulations sign up success.', 'system', NOW())");
}

// --- Fetch Notifications ---
$sql = "SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC";
$result = $conn->query($sql);
$current_date = date('d M, Y'); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Inbox</title> <script src="https://cdn.tailwindcss.com"></script>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .toolbar {
            background-color: var(--card-bg);
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            position: sticky;
            top: 52px; 
            z-index: 40;
        }
        .gmt-badge {
            border: 1px solid #bae6fd;
            background-color: var(--blue-light);
            color: var(--header-color);
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 800;
            margin-right: 12px;
            text-transform: uppercase;
        }

        .msg-list-container {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .msg-item {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 16px;
            border: 1px solid var(--border-color);
            display: flex;
            gap: 14px;
            align-items: flex-start;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .msg-item:active {
            transform: scale(0.98);
            border-color: #bae6fd;
            box-shadow: 0 4px 10px rgba(21, 75, 119, 0.08);
        }

        .msg-icon {
            width: 42px; height: 42px;
            background-color: var(--blue-light);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--header-color); font-size: 18px;
            flex-shrink: 0;
            border: 1px solid #bae6fd;
        }

        .msg-content { flex: 1; min-width: 0; }
        .msg-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;}
        
        .msg-title { 
            color: var(--header-color); 
            font-size: 13.5px; 
            font-weight: 800; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis;
            padding-right: 8px;
        }
        
        .msg-desc { 
            color: var(--text-muted); 
            font-size: 12.5px; 
            line-height: 1.5;
            font-weight: 500;
        }
        
        .msg-time { 
            color: #94a3b8; 
            font-size: 10.5px; 
            font-weight: 700;
            white-space: nowrap;
        }

        .end-text { 
            text-align: center; 
            color: #94a3b8; 
            font-size: 11px; 
            font-weight: 600;
            margin-top: 10px; 
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .no-data { 
            display: flex; flex-direction: column; align-items: center; justify-content: center; 
            height: 50vh; 
        }
    </style>
</head>
<body>

    <div class="header-bg p-4 flex justify-between items-center sticky top-0 z-50">
        <a href="account.php" class="text-white text-xl p-1"><i class="fas fa-chevron-left"></i></a>
        <h1 class="text-white font-bold text-[15px] uppercase tracking-wider">Inbox</h1>
        <button onclick="window.history.back()" class="text-white text-xl p-1"><i class="fas fa-times"></i></button>
    </div>

    <div class="toolbar">
        <div class="flex items-center gap-2 text-[12px] font-bold text-[#154b77]">
            <i class="far fa-calendar-alt text-lg"></i>
            <span><?php echo $current_date; ?></span>
        </div>
        <div class="flex items-center">
            <span class="gmt-badge">GMT+6</span>
            <i class="fas fa-ellipsis-h text-lg text-gray-300"></i>
        </div>
    </div>

    <div class="msg-list-container">
        <?php if($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="msg-item">
                    <div class="msg-icon">
                        <i class="fas fa-bullhorn transform -rotate-12"></i>
                    </div>
                    <div class="msg-content">
                        <div class="msg-header">
                            <h3 class="msg-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                            <span class="msg-time"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
                        </div>
                        <p class="msg-desc"><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
            
            <div class="end-text">- end of page -</div>

        <?php else: ?>
            <div class="no-data">
                <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mb-4 border border-blue-100">
                    <i class="far fa-envelope-open text-4xl text-[#154b77]"></i>
                </div>
                <p class="text-gray-500 font-bold text-sm uppercase tracking-wide">No messages yet</p>
                <p class="text-gray-400 text-xs mt-1 font-medium">You have no notifications in your inbox.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'bottom_nav.php'; ?>

</body>
</html>