<?php
require '../includes/auth_session.php'; // Login Check
require '../includes/db.php';
require '../includes/functions.php';
require 'includes/notification_script.php';

// Fast dashboard loader: combine repeated counters into grouped queries so the admin home opens smoothly.
$today_start = date('Y-m-d 00:00:00');
$today_end   = date('Y-m-d 23:59:59');
$chart_start = date('Y-m-d 00:00:00', strtotime('-6 days'));

function wcb_admin_fetch_assoc_safe($result) {
    return ($result && $result instanceof mysqli_result) ? ($result->fetch_assoc() ?: array()) : array();
}

$userStats = wcb_admin_fetch_assoc_safe($conn->query("SELECT
    SUM(CASE WHEN role='player' THEN 1 ELSE 0 END) AS total_players,
    SUM(CASE WHEN role='agent' THEN 1 ELSE 0 END) AS total_agents,
    SUM(CASE WHEN role='player' AND created_at BETWEEN '$today_start' AND '$today_end' THEN 1 ELSE 0 END) AS today_players,
    SUM(CASE WHEN role='player' AND referrer_id > 0 AND agent_id = 0 AND created_at BETWEEN '$today_start' AND '$today_end' THEN 1 ELSE 0 END) AS today_ref_players,
    SUM(CASE WHEN role='player' AND agent_id > 0 AND created_at BETWEEN '$today_start' AND '$today_end' THEN 1 ELSE 0 END) AS today_agent_players
    FROM users"));
$total_players = (int)($userStats['total_players'] ?? 0);
$total_agents = (int)($userStats['total_agents'] ?? 0);
$today_players = (int)($userStats['today_players'] ?? 0);
$today_ref_players = (int)($userStats['today_ref_players'] ?? 0);
$today_agent_players = (int)($userStats['today_agent_players'] ?? 0);

$trxStats = wcb_admin_fetch_assoc_safe($conn->query("SELECT
    SUM(CASE WHEN type='deposit' AND status='approved' THEN amount ELSE 0 END) AS total_deposits,
    SUM(CASE WHEN type='deposit' AND status='approved' AND agent_id > 0 AND created_at BETWEEN '$today_start' AND '$today_end' THEN amount ELSE 0 END) AS today_agent_deposit,
    SUM(CASE WHEN type='withdraw' AND status='approved' THEN amount ELSE 0 END) AS total_withdraw,
    SUM(CASE WHEN type='withdraw' AND status='approved' AND created_at BETWEEN '$today_start' AND '$today_end' THEN amount ELSE 0 END) AS today_withdraw,
    COUNT(DISTINCT CASE WHEN type='withdraw' AND status='approved' AND created_at BETWEEN '$today_start' AND '$today_end' THEN user_id END) AS today_withdraw_players
    FROM transactions_fake"));
$total_deposits = (float)($trxStats['total_deposits'] ?? 0);
$today_agent_deposit = (float)($trxStats['today_agent_deposit'] ?? 0);
$total_withdraw = (float)($trxStats['total_withdraw'] ?? 0);
$today_withdraw = (float)($trxStats['today_withdraw'] ?? 0);
$today_withdraw_players = (int)($trxStats['today_withdraw_players'] ?? 0);

$risk_alerts = 0;
$profileTable = $conn->query("SHOW TABLES LIKE 'player_profiles'");
if ($profileTable && $profileTable->num_rows > 0) {
    $riskRow = wcb_admin_fetch_assoc_safe($conn->query("SELECT COUNT(*) AS total FROM player_profiles WHERE risk_score > 50"));
    $risk_alerts = (int)($riskRow['total'] ?? 0);
}

$activeRow = wcb_admin_fetch_assoc_safe($conn->query("SELECT COUNT(*) AS total FROM game_providers WHERE status='active'"));
$active_games = (int)($activeRow['total'] ?? 0);
$betRow = wcb_admin_fetch_assoc_safe($conn->query("SELECT COUNT(*) AS total FROM game_bet_history WHERE created_at BETWEEN '$today_start' AND '$today_end'"));
$todays_bets = (int)($betRow['total'] ?? 0);
$online_users = rand(45, 120);

$chart_labels = array();
$chart_deposits = array();
$chart_withdraws = array();
$chartMap = array();
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime($date));
    $chartMap[$date] = array('deposit' => 0, 'withdraw' => 0);
}
$chartRes = $conn->query("SELECT DATE(created_at) AS trx_date,
    SUM(CASE WHEN type='deposit' AND status='approved' THEN amount ELSE 0 END) AS deposit_total,
    SUM(CASE WHEN type='withdraw' AND status='approved' THEN amount ELSE 0 END) AS withdraw_total
    FROM transactions_fake
    WHERE created_at >= '$chart_start' AND created_at <= '$today_end'
    GROUP BY DATE(created_at)");
if ($chartRes) {
    while ($row = $chartRes->fetch_assoc()) {
        $d = (string)($row['trx_date'] ?? '');
        if (isset($chartMap[$d])) {
            $chartMap[$d]['deposit'] = (float)($row['deposit_total'] ?? 0);
            $chartMap[$d]['withdraw'] = (float)($row['withdraw_total'] ?? 0);
        }
    }
}
foreach ($chartMap as $row) {
    $chart_deposits[] = $row['deposit'];
    $chart_withdraws[] = $row['withdraw'];
}

$recent_trx = $conn->query("SELECT t.*, u.username FROM transactions_fake t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - BetPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { background-color: #f3f4f6; }</style>
</head>
<body class="bg-gray-50 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="md:ml-64 p-4 md:p-8 min-h-screen pt-20 md:pt-24 transition-all duration-300">
        
        <?php include '../includes/header.php'; ?>

        <div class="max-w-7xl mx-auto">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between h-32 hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Players</p>
                            <h3 class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($total_players); ?></h3>
                        </div>
                        <div class="p-2 bg-blue-50 text-blue-600 rounded-lg"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-xs text-blue-500 font-bold bg-blue-50 px-1.5 py-0.5 rounded">Lifetime</span>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between h-32 hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Deposits</p>
                            <h3 class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatMoney($total_deposits); ?></h3>
                        </div>
                        <div class="p-2 bg-green-50 text-green-600 rounded-lg"><i class="fas fa-wallet"></i></div>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-xs text-green-500 font-bold bg-green-50 px-1.5 py-0.5 rounded">Lifetime</span>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between h-32 hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Withdraw</p>
                            <h3 class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatMoney($total_withdraw); ?></h3>
                        </div>
                        <div class="p-2 bg-red-50 text-red-600 rounded-lg"><i class="fas fa-money-bill-wave"></i></div>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-xs text-red-500 font-bold bg-red-50 px-1.5 py-0.5 rounded">Lifetime</span>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-sm border border-red-100 relative overflow-hidden flex flex-col justify-between h-32 hover:shadow-md transition">
                    <div class="absolute right-0 top-0 w-16 h-16 bg-red-500/10 rounded-bl-full"></div>
                    <div class="relative z-10">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-bold text-red-500 uppercase tracking-wide">Risk Alerts</p>
                                <h3 class="text-2xl font-bold text-red-600 mt-1"><?php echo number_format($risk_alerts); ?></h3>
                            </div>
                            <div class="p-2 bg-red-50 text-red-600 rounded-lg"><i class="fas fa-exclamation-triangle"></i></div>
                        </div>
                        <p class="text-[10px] text-red-400 mt-3 font-bold flex items-center gap-1">
                            <i class="fas fa-bell animate-pulse"></i> Action Required
                        </p>
                    </div>
                </div>

            </div>

            <h3 class="text-lg font-bold text-gray-700 mb-4 border-l-4 border-indigo-500 pl-3">Today's Highlights</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase">Today Players</p>
                    <h4 class="text-xl font-black text-indigo-600 mt-1"><?php echo number_format($today_players); ?></h4>
                </div>
                
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase">Today Ref Player</p>
                    <h4 class="text-xl font-black text-indigo-600 mt-1"><?php echo number_format($today_ref_players); ?></h4>
                </div>

                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase">Today Agent Player</p>
                    <h4 class="text-xl font-black text-indigo-600 mt-1"><?php echo number_format($today_agent_players); ?></h4>
                </div>

                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase">Agent Deposit</p>
                    <h4 class="text-xl font-black text-green-600 mt-1">৳<?php echo number_format($today_agent_deposit); ?></h4>
                </div>

                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase">Today Withdraw</p>
                    <h4 class="text-xl font-black text-red-600 mt-1">৳<?php echo number_format($today_withdraw); ?></h4>
                </div>

                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase">Withdraw Players</p>
                    <h4 class="text-xl font-black text-red-600 mt-1"><?php echo number_format($today_withdraw_players); ?></h4>
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-sm font-bold text-gray-700">Financial Flow (Last 7 Days)</h4>
                        <div class="flex gap-2">
                            <span class="text-[10px] bg-green-100 text-green-600 px-2 py-1 rounded-full font-bold animate-pulse">
                                ● <?php echo $online_users; ?> Users Online
                            </span>
                        </div>
                    </div>
                    <div class="h-64 w-full">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <div class="space-y-6">
                    
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h4 class="text-sm font-bold text-gray-700 mb-4">Player Risk Levels</h4>
                        <div class="h-40 flex justify-center relative">
                            <canvas id="riskChart"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-2xl font-bold text-gray-800"><?php echo $total_players; ?></span>
                                <span class="text-[10px] text-gray-400 uppercase">Total</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-indigo-900 text-white p-6 rounded-xl shadow-lg relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/10 rounded-full"></div>
                        <h4 class="text-sm font-bold text-indigo-200 uppercase mb-2">Today's Activity</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="block text-2xl font-bold"><?php echo number_format($todays_bets); ?></span>
                                <span class="text-[10px] text-indigo-300">Bets Placed (Today)</span>
                            </div>
                            <div>
                                <span class="block text-2xl font-bold"><?php echo number_format($active_games); ?></span>
                                <span class="text-[10px] text-indigo-300">Active Providers</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h4 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Live Feed
                    </h4>
                    <button class="text-xs bg-white border border-gray-200 text-gray-600 px-3 py-1 rounded hover:bg-gray-50 transition">View All</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-bold tracking-wider border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3">Type</th>
                                <th class="px-6 py-3">Amount</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php while($row = $recent_trx->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-3 font-medium text-gray-700"><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="px-6 py-3">
                                    <?php 
                                        $typeColors = ['deposit'=>'text-green-600','withdraw'=>'text-red-600','bet'=>'text-gray-500','win'=>'text-blue-600'];
                                        $color = $typeColors[$row['type']] ?? 'text-gray-500';
                                        echo "<span class='$color font-bold uppercase text-xs'>" . ucfirst($row['type']) . "</span>";
                                    ?>
                                </td>
                                <td class="px-6 py-3 font-bold font-mono text-gray-800"><?php echo formatMoney($row['amount']); ?></td>
                                <td class="px-6 py-3">
                                    <?php 
                                    $statusClass = [
                                        'approved' => 'bg-green-100 text-green-700 border-green-200',
                                        'rejected' => 'bg-red-100 text-red-700 border-red-200',
                                        'pending'  => 'bg-yellow-100 text-yellow-700 border-yellow-200'
                                    ];
                                    $cls = $statusClass[$row['status']] ?? 'bg-gray-100 text-gray-600 border-gray-200';
                                    echo "<span class='$cls px-2 py-0.5 rounded text-[10px] font-bold border uppercase'>{$row['status']}</span>";
                                    ?>
                                </td>
                                <td class="px-6 py-3 text-gray-400 text-xs">
                                    <?php echo date('H:i:s', strtotime($row['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        // 1. Real Database Chart (Deposit vs Withdraw - Last 7 Days)
        const ctxRev = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctxRev, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [
                    {
                        label: 'Deposits (৳)',
                        data: <?php echo json_encode($chart_deposits); ?>, 
                        borderColor: '#10B981', // Green
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#10B981'
                    },
                    {
                        label: 'Withdraws (৳)',
                        data: <?php echo json_encode($chart_withdraws); ?>, 
                        borderColor: '#EF4444', // Red
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.4,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#EF4444'
                    }
                ]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, usePointStyle: true } } 
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        grid: { borderDash: [4, 4], color: '#f3f4f6' }, 
                        ticks: { font: { size: 10 } } 
                    },
                    x: { 
                        grid: { display: false }, 
                        ticks: { font: { size: 10 } } 
                    }
                }
            }
        });

        // 2. Risk Chart (Keep simulated or adjust logic if needed)
        const ctxRisk = document.getElementById('riskChart').getContext('2d');
        new Chart(ctxRisk, {
            type: 'doughnut',
            data: {
                labels: ['Safe', 'Medium', 'Critical'],
                datasets: [{
                    data: [85, 10, 5], 
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: { 
                cutout: '75%', 
                maintainAspectRatio: false, 
                responsive: true,
                plugins: { legend: { display: false } }
            }
        });
    </script>

</body>
</html>