<?php
session_start();

// ১. ডাটাবেজ কানেকশন (ম্যানুয়াল কানেকশন রিমুভড)
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';

if (file_exists($db_path)) {
    require $db_path;
} else {
    // ডাটাবেস ফাইল না পেলে স্ক্রিপ্ট বন্ধ হয়ে যাবে (নিরাপত্তার জন্য)
    die("Database connection file not found.");
}

// লগিন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// --- FILTER LOGIC ---
$where_clauses = ["user_id = $uid"];

// 1. Date Filter
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'today';
if ($date_filter == 'today') {
    $where_clauses[] = "DATE(created_at) = CURDATE()";
} elseif ($date_filter == 'yesterday') {
    $where_clauses[] = "DATE(created_at) = CURDATE() - INTERVAL 1 DAY";
} elseif ($date_filter == 'week') {
    $where_clauses[] = "created_at >= CURDATE() - INTERVAL 7 DAY";
}

// 2. Status Filter
if (isset($_GET['status']) && $_GET['status'] != '') {
    $status = $conn->real_escape_string($_GET['status']); // pending/approved/rejected
    $where_clauses[] = "status = '$status'";
}

// 3. Type Filter
if (isset($_GET['type']) && $_GET['type'] != '') {
    $type = $conn->real_escape_string($_GET['type']); // deposit/withdraw/adjustment
    $where_clauses[] = "type = '$type'";
}

// Build Query
$sql = "SELECT * FROM transactions_fake WHERE " . implode(' AND ', $where_clauses) . " ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Transaction Records |</title>
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

        /* Header */
        .header-bg { 
            background: linear-gradient(135deg, #0f395c 0%, #1a5c92 100%);
            padding: 16px; 
            display: flex; justify-content: space-between; align-items: center; 
            position: sticky; top: 0; z-index: 40; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Filter Bar */
        .filter-bar {
            background-color: var(--card-bg);
            padding: 12px 16px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            position: sticky; top: 56px; z-index: 30;
        }

        /* Table Header */
        .tbl-header {
            display: grid; grid-template-columns: 1fr 1fr 1fr 1fr;
            background-color: #e0f2fe; padding: 12px 5px; 
            font-size: 11px; font-weight: 800; color: var(--header-color);
            text-align: center; border-bottom: 2px solid #bae6fd;
            text-transform: uppercase;
        }
        .tbl-col { border-right: 1px solid #bae6fd; }
        .tbl-col:last-child { border-right: none; }

        /* Data Row */
        .data-row {
            display: grid; grid-template-columns: 1fr 1fr 1fr 1fr;
            padding: 14px 5px; font-size: 12px; text-align: center;
            border-bottom: 1px solid var(--border-color); align-items: center;
            background-color: var(--card-bg); transition: background 0.2s;
        }
        .data-row:active { background-color: #f8fafc; }
        
        /* Status Colors (Pills) */
        .st-approved { color: #166534; background: #dcfce7; padding: 4px 6px; border-radius: 4px; border: 1px solid #bbf7d0; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block; width: 100%;}
        .st-pending { color: #92400e; background: #fef3c7; padding: 4px 6px; border-radius: 4px; border: 1px solid #fde68a; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block; width: 100%;}
        .st-rejected { color: #991b1b; background: #fee2e2; padding: 4px 6px; border-radius: 4px; border: 1px solid #fecaca; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block; width: 100%;}

        /* --- FILTER MODAL STYLES --- */
        .filter-modal {
            position: fixed; inset: 0; z-index: 100;
            background-color: var(--bg-color);
            transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .filter-modal.open { transform: translateX(0); }
        
        .filter-sec-title { 
            font-size: 11.5px; font-weight: 800; color: var(--header-color); 
            margin-bottom: 10px; margin-top: 24px; text-transform: uppercase; 
            letter-spacing: 0.5px; border-left: 3px solid var(--accent-color); padding-left: 8px;
        }
        
        .filter-btn {
            background-color: var(--card-bg); color: var(--text-muted);
            font-size: 12px; font-weight: 600; padding: 12px 10px; border-radius: 6px;
            text-align: center; border: 1px solid var(--border-color); transition: 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02); cursor: pointer;
        }
        /* Active Filter Button */
        .filter-btn.active {
            background-color: var(--blue-light); color: var(--header-color); 
            font-weight: 800; border-color: #bae6fd; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <div class="header-bg">
        <a href="account.php" class="text-white text-xl p-1"><i class="fas fa-chevron-left"></i></a>
        <h1 class="text-white font-bold text-[15px] uppercase tracking-wider">Transaction Records</h1>
        <button onclick="openFilter()" class="text-white text-lg p-1"><i class="fas fa-filter"></i></button>
    </div>

    <div class="filter-bar">
        <div class="flex items-center gap-2">
            <i class="far fa-calendar-alt text-[#154b77]"></i>
            <span class="bg-[#e0f2fe] text-[#154b77] text-[11px] font-bold uppercase tracking-wide px-3 py-1 rounded border border-[#bae6fd]">
                <?php 
                    if($date_filter == 'today') echo 'Today';
                    elseif($date_filter == 'yesterday') echo 'Yesterday';
                    else echo 'Last 7 days';
                ?>
            </span>
        </div>
        <button onclick="openFilter()" class="text-[#154b77] text-[12px] font-black uppercase tracking-wide flex items-center gap-1.5 bg-blue-50 px-3 py-1.5 rounded-md border border-blue-100 active:scale-95 transition">
            Filter <i class="fas fa-sliders-h text-[#43a047]"></i>
        </button>
    </div>

    <div class="tbl-header">
        <div class="tbl-col">Type</div>
        <div class="tbl-col">Amount</div>
        <div class="tbl-col">Status</div>
        <div class="tbl-col">Txn Date</div>
    </div>

    <div class="min-h-[60vh] bg-white">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): 
                $status_class = 'st-pending';
                if($row['status'] == 'approved') $status_class = 'st-approved';
                if($row['status'] == 'rejected') $status_class = 'st-rejected';
                
                // Map DB enum to display text
                $status_text = $row['status'] == 'pending' ? 'Processing' : ucfirst($row['status']);
            ?>
            <div class="data-row">
                <div class="text-[#154b77] font-black text-[11px] uppercase tracking-wide truncate px-1"><?php echo $row['type']; ?></div>
                <div class="text-gray-700 font-bold font-mono text-[12px]">৳ <?php echo number_format($row['amount'], 2); ?></div>
                <div class="px-1">
                    <span class="<?php echo $status_class; ?> shadow-sm"><?php echo $status_text; ?></span>
                </div>
                <div class="text-gray-500 text-[10px] font-medium leading-tight">
                    <?php echo date('d M, Y', strtotime($row['created_at'])); ?><br>
                    <span class="font-bold text-gray-400"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center pt-32 opacity-80">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3 border border-gray-200">
                    <i class="fas fa-file-invoice-dollar text-3xl text-gray-300"></i>
                </div>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-wide">No Records Found</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="filterModal" class="filter-modal flex flex-col h-full">
        
        <div class="bg-[linear-gradient(135deg,#0f395c_0%,#1a5c92_100%)] p-4 flex justify-between items-center shadow-md">
            <h2 class="text-white text-[15px] font-bold uppercase tracking-wider">Transaction Filter</h2>
            <button onclick="closeFilter()" class="text-white text-xl p-1"><i class="fas fa-times"></i></button>
        </div>

        <div class="p-4 flex-1 overflow-y-auto bg-white">
            
            <p class="filter-sec-title">Status</p>
            <div class="grid grid-cols-3 gap-3">
                <div class="filter-btn f-status" data-val="pending" onclick="selectFilter(this, 'status')">Processing</div>
                <div class="filter-btn f-status" data-val="rejected" onclick="selectFilter(this, 'status')">Rejected</div>
                <div class="filter-btn f-status" data-val="approved" onclick="selectFilter(this, 'status')">Approved</div>
            </div>

            <p class="filter-sec-title">Payment Type</p>
            <div class="grid grid-cols-3 gap-3">
                <div class="filter-btn f-type" data-val="deposit" onclick="selectFilter(this, 'type')">Deposit</div>
                <div class="filter-btn f-type" data-val="withdraw" onclick="selectFilter(this, 'type')">Withdrawal</div>
                <div class="filter-btn f-type" data-val="adjustment" onclick="selectFilter(this, 'type')">Adjustment</div>
            </div>

            <p class="filter-sec-title">Date</p>
            <div class="grid grid-cols-3 gap-3">
                <div class="filter-btn f-date active" data-val="today" onclick="selectFilter(this, 'date')">Today</div>
                <div class="filter-btn f-date" data-val="yesterday" onclick="selectFilter(this, 'date')">Yesterday</div>
                <div class="filter-btn f-date" data-val="week" onclick="selectFilter(this, 'date')">Last 7 days</div>
            </div>

        </div>

        <div class="p-4 bg-white border-t border-gray-200 pb-8">
            <button onclick="applyFilters()" class="w-full bg-gradient-to-b from-[#4caf50] to-[#388e3c] text-white font-extrabold text-[15px] uppercase tracking-wide py-3.5 rounded-lg shadow-md active:scale-[0.98] transition">Apply Filter</button>
        </div>
    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        // State variables
        let selectedStatus = '';
        let selectedType = '';
        let selectedDate = 'today';

        // Initialize from URL params if present
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('status')) setActive('status', urlParams.get('status'));
        if(urlParams.get('type')) setActive('type', urlParams.get('type'));
        if(urlParams.get('date')) setActive('date', urlParams.get('date'));

        function openFilter() {
            document.getElementById('filterModal').classList.add('open');
        }

        function closeFilter() {
            document.getElementById('filterModal').classList.remove('open');
        }

        function selectFilter(btn, type) {
            // Remove active class from siblings
            const group = document.querySelectorAll(`.f-${type}`);
            group.forEach(el => el.classList.remove('active'));
            
            // Add active to clicked
            btn.classList.add('active');

            // Update state
            const val = btn.getAttribute('data-val');
            if (type === 'status') selectedStatus = val;
            if (type === 'type') selectedType = val;
            if (type === 'date') selectedDate = val;
        }

        function setActive(type, val) {
            const btn = document.querySelector(`.f-${type}[data-val="${val}"]`);
            if(btn) {
                // Trigger logic
                selectFilter(btn, type);
            }
        }

        function applyFilters() {
            let url = 'trans_rec_full.php?';
            if (selectedDate) url += `date=${selectedDate}&`;
            if (selectedStatus) url += `status=${selectedStatus}&`;
            if (selectedType) url += `type=${selectedType}&`;
            
            window.location.href = url;
        }
    </script>

</body>
</html>