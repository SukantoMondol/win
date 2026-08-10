<?php
session_start();
// Database Connection
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
} else {
    die("Database file not found at: $db_path");
}

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Fetch System Settings & User Registration Date
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$user_data = $conn->query("SELECT created_at FROM users WHERE id=$user_id")->fetch_assoc();

$reg_date = date('Y-m-d', strtotime($user_data['created_at'])); // Min Date
$today = date('Y-m-d'); // Max Date

$primary = $settings['theme_primary'] ?? '#003333';
$secondary = $settings['theme_secondary'] ?? '#034C44';

// 2. Filter Logic
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'today';
$date_condition = "";
$display_date = date('m/d') . " - " . date('m/d'); // Default display

if ($filter == 'today') {
    $date_condition = "AND DATE(created_at) = CURDATE()";
    $display_date = date('m/d') . " - " . date('m/d');
} elseif ($filter == 'yesterday') {
    $date_condition = "AND DATE(created_at) = SUBDATE(CURDATE(), 1)";
    $display_date = date('m/d', strtotime('-1 day')) . " - " . date('m/d', strtotime('-1 day'));
} elseif ($filter == 'week') {
    $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $display_date = date('m/d', strtotime('-7 days')) . " - " . date('m/d');
} elseif ($filter == 'custom' && isset($_GET['from']) && isset($_GET['to'])) {
    $from = $conn->real_escape_string($_GET['from']);
    $to = $conn->real_escape_string($_GET['to']);
    // Validate range
    if ($from < $reg_date) $from = $reg_date; 
    if ($to > $today) $to = $today;
    
    $date_condition = "AND DATE(created_at) BETWEEN '$from' AND '$to'";
    $display_date = date('m/d', strtotime($from)) . " - " . date('m/d', strtotime($to));
}

// 3. Fetch Transactions
$sql = "SELECT * FROM transactions_fake WHERE user_id = $user_id AND type='deposit' $date_condition ORDER BY created_at DESC";
$result = $conn->query($sql);
$total_amount = 0;
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>জমা রেকর্ড - <?php echo $settings['site_name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f3f4f6; padding-bottom: 80px; }
        .header-bg { background-color: <?php echo $primary; ?>; color: white; }
        .tab-active { border-bottom: 2px solid <?php echo $secondary; ?>; color: <?php echo $secondary; ?>; font-weight: bold; }
        .tab-inactive { color: #666; }
        
        .status-approved { color: #22c55e; } 
        .status-pending { color: #f59e0b; }  
        .status-rejected { color: #ef4444; }
        
        /* Modal Animation */
        .modal-enter { animation: slideUp 0.3s ease-out forwards; }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-100">

    <header class="header-bg flex justify-between items-center px-4 py-3 shadow-md sticky top-0 z-50">
        <button onclick="window.location.href='dashboard.php'" class="text-white text-xl">
            <i class="fas fa-chevron-left"></i>
        </button>
        <h1 class="text-lg font-bold">জমা রেকর্ড</h1>
        <div class="text-white text-xl">
            <i class="fas fa-file-download"></i>
        </div>
    </header>

    <div class="bg-white shadow-sm mb-2 sticky top-14 z-40">
        <div class="flex text-center border-b border-gray-200">
            <a href="?filter=today" class="flex-1 py-3 text-sm <?php echo $filter=='today' ? 'tab-active' : 'tab-inactive'; ?>">আজ</a>
            <a href="?filter=yesterday" class="flex-1 py-3 text-sm <?php echo $filter=='yesterday' ? 'tab-active' : 'tab-inactive'; ?>">গতকাল</a>
            <a href="?filter=week" class="flex-1 py-3 text-sm <?php echo $filter=='week' ? 'tab-active' : 'tab-inactive'; ?>">৭ দিন</a>
        </div>
        
        <div class="flex justify-between p-3 gap-2 bg-gray-50 border-b border-gray-200">
            <button class="border border-blue-400 text-blue-500 px-4 py-1 rounded text-xs">সব</button>
            <button class="border border-blue-400 text-blue-500 px-4 py-1 rounded text-xs">প্রকার</button>
            
            <button onclick="openDateModal()" class="border border-blue-400 text-blue-500 px-4 py-1 rounded text-xs flex items-center gap-1 font-bold">
                <i class="far fa-calendar-alt"></i> <?php echo $display_date; ?>
            </button>
        </div>
    </div>

    <div class="p-3 space-y-3">
        
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): 
                $total_amount += $row['amount'];
                $status_text = ($row['status'] == 'approved') ? 'অনুমোদিত' : (($row['status'] == 'rejected') ? 'বাতিল' : 'অপেক্ষমান');
                $status_class = ($row['status'] == 'approved') ? 'status-approved' : (($row['status'] == 'rejected') ? 'status-rejected' : 'status-pending');
            ?>
            
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <span class="font-bold text-gray-700 capitalize"><?php echo $row['method']; ?></span>
                    <span class="text-xs text-gray-500"><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></span>
                </div>
                
                <div class="bg-gray-50 p-3 rounded text-xs text-gray-600 space-y-1.5">
                    <div class="flex justify-between">
                        <span>জমা রেফ#: <?php echo $row['transaction_id'] ?? 'N/A'; ?></span>
                        <i class="far fa-copy text-gray-400 cursor-pointer" onclick="navigator.clipboard.writeText('<?php echo $row['transaction_id']; ?>')"></i>
                    </div>
                    <p>পোস্টস্ক্রিপ্ট:</p>
                    <p>প্রাপ্ত সময়: <?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></p>
                    <p>হ্যান্ডলিং ফি: 0.00</p>
                    <p>কার্যক্রম: -</p>
                    <p>মন্তব্য: -</p>
                </div>

                <div class="flex justify-between items-end mt-3 border-t border-gray-100 pt-2">
                    <div class="text-center">
                        <p class="text-xs text-gray-500">অনুরোধ</p>
                        <p class="text-red-500 font-bold"><?php echo number_format($row['amount'], 2); ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-500">প্রাপ্ত পরিমাণ</p>
                        <p class="text-gray-700 font-bold"><?php echo number_format($row['amount'], 2); ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-500">স্থিতি</p>
                        <p class="<?php echo $status_class; ?> font-bold text-sm"><?php echo $status_text; ?></p>
                    </div>
                </div>
            </div>

            <?php endwhile; ?>
            
            <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 p-3 font-bold text-sm text-gray-800 z-30">
                মোট পরিমাণ: <?php echo number_format($total_amount, 2); ?>
            </div>

        <?php else: ?>
            
            <div class="flex flex-col items-center justify-center mt-20">
                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" class="w-32 h-32 opacity-50 mb-4">
                <p class="text-blue-500 text-lg">কোন ডেটা নেই</p>
            </div>

        <?php endif; ?>

    </div>

    <div id="dateModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-end justify-center transition-opacity">
        <div class="bg-white w-full rounded-t-2xl p-6 modal-enter">
            <div class="flex justify-between items-center mb-4 border-b pb-2">
                <h3 class="font-bold text-gray-700">তারিখ নির্বাচন করুন</h3>
                <button onclick="closeDateModal()" class="text-gray-500 text-xl">&times;</button>
            </div>
            
            <form method="GET" action="transaction_history.php">
                <input type="hidden" name="filter" value="custom">
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="text-xs font-bold text-gray-500 block mb-1">শুরুর তারিখ (From)</label>
                        <input type="date" name="from" required 
                               min="<?php echo $reg_date; ?>" 
                               max="<?php echo $today; ?>"
                               class="w-full border border-gray-300 rounded p-2 text-sm focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 block mb-1">শেষ তারিখ (To)</label>
                        <input type="date" name="to" required 
                               min="<?php echo $reg_date; ?>" 
                               max="<?php echo $today; ?>"
                               class="w-full border border-gray-300 rounded p-2 text-sm focus:border-blue-500 outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg shadow-lg active:scale-95 transition">
                    নিশ্চিত করুন
                </button>
            </form>
        </div>
    </div>

    <script>
        function openDateModal() {
            document.getElementById('dateModal').classList.remove('hidden');
        }

        function closeDateModal() {
            document.getElementById('dateModal').classList.add('hidden');
        }

        // Close on outside click
        document.getElementById('dateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDateModal();
            }
        });
    </script>

</body>
</html>