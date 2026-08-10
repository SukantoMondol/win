<?php
session_start();
// ১. ডাটাবেজ কানেকশন (সরাসরি ইনক্লুড)
require '../includes/db.php';
require_once '../includes/propay_gateway_helper.php';
propay_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../login.php"); 
    exit(); 
}

$uid = intval($_SESSION['user_id']);
@propay_sync_pending_withdrawals($conn, $uid, 20);
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$primary = $settings['theme_primary'] ?? '#154b77'; 

// ২. ফিল্টার লজিক
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$query = "SELECT * FROM transactions_fake WHERE user_id=$uid AND type='withdraw'";

if ($status_filter == 'pending') {
    $query .= " AND status='pending'";
} elseif ($status_filter == 'approved') {
    $query .= " AND status='approved'";
}

$query .= " ORDER BY created_at DESC LIMIT 50";
$withdraws = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Withdrawal Records</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f3f4f6; padding-bottom: 90px; }
        .tab-active { border-bottom: 3px solid <?php echo $primary; ?>; color: #000; font-weight: 800; }
    </style>
</head>
<body>

    <div class="p-4 flex items-center text-white sticky top-0 z-50 shadow-md" style="background-color: <?php echo $primary; ?>;">
        <a href="account.php" class="mr-4 text-xl p-1 active:scale-90 transition"><i class="fas fa-chevron-left"></i></a>
        <h1 class="text-lg font-bold uppercase tracking-tight">Withdrawal Records</h1>
    </div>

    <div class="flex justify-around bg-white p-1 shadow-sm mb-2 text-[13px] text-gray-500 font-bold sticky top-[60px] z-40">
        <a href="?status=all" class="flex-1 text-center py-3 <?php echo $status_filter == 'all' ? 'tab-active' : ''; ?>">ALL</a>
        <a href="?status=processing" class="flex-1 text-center py-3 <?php echo $status_filter == 'processing' ? 'tab-active' : ''; ?>">PROCESSING</a>
        <a href="?status=approved" class="flex-1 text-center py-3 <?php echo $status_filter == 'approved' ? 'tab-active' : ''; ?>">SUCCESS</a>
    </div>

    <div class="bg-white divide-y divide-gray-100">
        <?php if($withdraws->num_rows > 0): ?>
            <?php while($row = $withdraws->fetch_assoc()): ?>
            <div class="p-4 hover:bg-gray-50 transition active:bg-gray-100">
                
                <div class="flex justify-between items-start mb-2">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg border border-emerald-100">
                            <i class="fas fa-money-bill-transfer"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm uppercase tracking-tighter"><?php echo htmlspecialchars($row['method']); ?></h3>
                            <p class="text-[10px] text-gray-400 font-bold">
                                <?php echo !empty($row['wallet_number']) ? 'AC: '.$row['wallet_number'] : 'Withdrawal'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <span class="block font-black text-lg text-gray-800">৳ <?php echo number_format($row['amount'], 2); ?></span>
                        
                        <?php if($row['status'] == 'approved'): ?>
                            <span class="text-[9px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded font-black uppercase tracking-widest border border-emerald-200">Successful</span>
                        <?php elseif($row['status'] == 'rejected'): ?>
                            <span class="text-[9px] bg-rose-100 text-rose-700 px-2 py-0.5 rounded font-black uppercase tracking-widest border border-rose-200">Rejected</span>
                        <?php else: ?>
                            <span class="text-[9px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded font-black uppercase tracking-widest border border-amber-200">Processing</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex justify-between items-center text-[10px] text-gray-400 mt-2 border-t border-dashed border-gray-100 pt-2 font-bold">
                    <span><i class="far fa-clock mr-1"></i> <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></span>
                    
                    <?php if(!empty($row['transaction_id'])): ?>
                    <span class="flex items-center gap-1 bg-gray-50 px-2 py-1 rounded border border-gray-200 text-gray-500">
                        ID: <?php echo $row['transaction_id']; ?>
                        <i class="far fa-copy cursor-pointer active:text-emerald-500" onclick="copyTRX('<?php echo $row['transaction_id']; ?>')"></i>
                    </span>
                    <?php endif; ?>
                </div>

            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center py-24 text-gray-300">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-4 border border-gray-100">
                    <i class="fas fa-file-invoice-dollar text-4xl text-gray-200"></i>
                </div>
                <p class="font-bold text-xs uppercase tracking-widest">No withdrawal records found</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        function copyTRX(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Transaction ID copied!');
            });
        }
    </script>

</body>
</html>