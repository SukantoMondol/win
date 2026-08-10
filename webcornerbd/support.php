<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/admin_auth_helper.php';
wcb_admin_ensure_schema($conn);

// 1. ACCESS CONTROL (Admin & Support Roles Only)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'support'])) {
    header("Location: ../login.php");
    exit();
}

$is_admin_actor = (($_SESSION['role'] ?? '') === 'admin');
$current_admin_id = $is_admin_actor ? (int)($_SESSION['admin_id'] ?? 0) : (int)($_SESSION['user_id'] ?? 0);
$active_ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;

// --- FIX: FETCH LOGGED-IN AGENT NAME ---
$agent_name = "Support Agent";
if ($is_admin_actor) {
    $admin_row = wcb_admin_get_by_id($conn, $current_admin_id);
    if ($admin_row && !empty($admin_row['name'])) {
        $agent_name = $admin_row['name'];
    }
} elseif (isset($_SESSION['username'])) {
    $agent_name = $_SESSION['username'];
} else {
    $agent_query = $conn->query("SELECT username FROM users WHERE id = $current_admin_id AND role='support' LIMIT 1");
    if ($agent_query && $agent_query->num_rows > 0) {
        $agent_name = $agent_query->fetch_assoc()['username'];
    }
}

// ---------------------------------------------------------
// 2. HANDLE ACTIONS (Send Message, Close Ticket)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // SEND MESSAGE
    if (isset($_POST['send_message']) && $active_ticket_id > 0) {
        $msg = $conn->real_escape_string($_POST['message']);
        if (!empty($msg)) {
            if ($is_admin_actor) {
                $stmtMsg = $conn->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_type, sender_admin_id, message) VALUES (?, ?, 'admin', ?, ?)");
                if ($stmtMsg) {
                    $stmtMsg->bind_param('iiis', $active_ticket_id, $current_admin_id, $current_admin_id, $msg);
                    $stmtMsg->execute();
                    $stmtMsg->close();
                }
            } else {
                $stmtMsg = $conn->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_type, sender_admin_id, message) VALUES (?, ?, 'user', NULL, ?)");
                if ($stmtMsg) {
                    $stmtMsg->bind_param('iis', $active_ticket_id, $current_admin_id, $msg);
                    $stmtMsg->execute();
                    $stmtMsg->close();
                }
            }
            $conn->query("UPDATE support_tickets SET status='answered', last_reply_at=NOW(), assigned_admin_id=$current_admin_id WHERE id=$active_ticket_id");
        }
        header("Location: support.php?ticket_id=$active_ticket_id");
        exit();
    }

    // CLOSE TICKET
    if (isset($_POST['close_ticket']) && $active_ticket_id > 0) {
        $conn->query("UPDATE support_tickets SET status='closed' WHERE id=$active_ticket_id");
        header("Location: support.php");
        exit();
    }
}

// ---------------------------------------------------------
// 3. FETCH DATA
// ---------------------------------------------------------

// FETCH TICKET QUEUE (Open & Active)
$queue_sql = "SELECT t.*, u.username, u.role as user_role, 
              (SELECT message FROM support_messages WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) as last_msg
              FROM support_tickets t 
              JOIN users u ON t.user_id = u.id 
              WHERE t.status != 'closed' 
              ORDER BY t.last_reply_at DESC";
$queue = $conn->query($queue_sql);

// FETCH ACTIVE CONVERSATION & USER DETAILS
$chat_msgs = [];
$player_data = null;
$player_kyc = null;
$player_trans = null;

if ($active_ticket_id > 0) {
    // 1. Messages
    $msg_res = $conn->query("SELECT m.*, 
        CASE WHEN m.sender_type='admin' THEN COALESCE(a.name, 'Administrator') ELSE COALESCE(u.username, 'User') END AS username,
        CASE WHEN m.sender_type='admin' THEN 'admin' ELSE COALESCE(u.role, 'player') END AS role
        FROM support_messages m
        LEFT JOIN users u ON m.sender_type<>'admin' AND m.sender_id=u.id
        LEFT JOIN `admin` a ON m.sender_type='admin' AND m.sender_admin_id=a.id
        WHERE m.ticket_id=$active_ticket_id ORDER BY m.created_at ASC");
    
    // 2. Player Info
    $t_info = $conn->query("SELECT user_id FROM support_tickets WHERE id = $active_ticket_id")->fetch_assoc();
    if ($t_info) {
        $p_id = $t_info['user_id'];
        
        // Profile & Wallet
        $player_data = $conn->query("SELECT u.*, p.* FROM users u LEFT JOIN player_profiles p ON u.id = p.user_id WHERE u.id = $p_id")->fetch_assoc();
        
        // KYC
        $player_kyc = $conn->query("SELECT * FROM kyc_documents WHERE user_id = $p_id ORDER BY submitted_at DESC");
        
        // Recent Transactions
        $player_trans = $conn->query("SELECT * FROM transactions_fake WHERE user_id = $p_id ORDER BY created_at DESC LIMIT 10");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Live Support | BetPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Custom Scrollbar for Chat */
        .chat-scroll::-webkit-scrollbar { width: 6px; }
        .chat-scroll::-webkit-scrollbar-thumb { background-color: #CBD5E1; border-radius: 4px; }
        .chat-scroll::-webkit-scrollbar-track { background-color: #F1F5F9; }
    </style>
    <script>
        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            var chatBox = document.getElementById("chatBox");
            if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
        }
        
        function openKycModal(imgSrc) {
            document.getElementById('kycModalImg').src = imgSrc;
            document.getElementById('kycModal').classList.remove('hidden');
        }
        function closeKycModal() {
            document.getElementById('kycModal').classList.add('hidden');
        }
    </script>
</head>
<body class="bg-gray-100 font-sans text-slate-800 h-screen overflow-hidden" onload="scrollToBottom()">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 h-screen flex flex-col transition-all duration-300">
        
        <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-6 shrink-0 z-20">
            <div class="flex items-center gap-3">
                <?php if($active_ticket_id > 0): ?>
                <a href="support.php" class="lg:hidden text-gray-500 hover:text-indigo-600 mr-2">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <?php endif; ?>
                <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-headset text-indigo-600"></i> Support Desk
                </h1>
                <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-1 rounded-full hidden sm:inline-block">
                    <?php echo $queue->num_rows; ?> Waiting
                </span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500 hidden sm:block">Logged as: <strong><?php echo htmlspecialchars($agent_name); ?></strong></span>
                <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden relative">

            <div class="w-full lg:w-80 bg-white border-r border-gray-200 flex flex-col <?php echo ($active_ticket_id > 0) ? 'hidden lg:flex' : 'flex'; ?>">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <input type="text" placeholder="Search tickets..." class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-500">
                </div>
                
                <div class="flex-1 overflow-y-auto">
                    <?php if ($queue->num_rows > 0): ?>
                        <?php while($t = $queue->fetch_assoc()): ?>
                        <a href="?ticket_id=<?php echo $t['id']; ?>" class="block p-4 border-b border-gray-100 hover:bg-indigo-50 transition <?php echo ($t['id'] == $active_ticket_id) ? 'bg-indigo-50 border-l-4 border-l-indigo-600' : ''; ?>">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-bold text-sm text-gray-800"><?php echo htmlspecialchars($t['username']); ?></span>
                                <span class="text-[10px] text-gray-400"><?php echo date('H:i', strtotime($t['last_reply_at'])); ?></span>
                            </div>
                            <p class="text-xs font-bold text-gray-600 truncate mb-1"><?php echo htmlspecialchars($t['subject']); ?></p>
                            <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($t['last_msg'] ?? 'New Ticket'); ?></p>
                            <?php if($t['status'] == 'open'): ?>
                                <span class="inline-block mt-2 text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded font-bold">New</span>
                            <?php endif; ?>
                        </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-2 opacity-30"></i>
                            <p class="text-sm">Queue is empty!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($active_ticket_id > 0 && $player_data): ?>
            <div class="flex-1 flex flex-col bg-gray-50 min-w-0 relative">
                
                <div class="h-14 bg-white border-b border-gray-200 flex justify-between items-center px-6 shrink-0">
                    <div>
                        <h2 class="font-bold text-gray-800"><?php echo htmlspecialchars($player_data['username']); ?></h2>
                        <span class="text-xs text-gray-500">ID: <?php echo $player_data['id']; ?> | <?php echo ucfirst($player_data['country'] ?? 'Unknown'); ?></span>
                    </div>
                    <form method="POST" onsubmit="return confirm('Close this ticket?');">
                        <input type="hidden" name="close_ticket" value="1">
                        <button class="text-xs font-bold text-red-500 hover:text-red-700 bg-red-50 px-3 py-1.5 rounded-lg transition">
                            <i class="fas fa-check-circle mr-1"></i> Close Ticket
                        </button>
                    </form>
                </div>

                <div id="chatBox" class="flex-1 overflow-y-auto p-6 space-y-4 chat-scroll">
                    <?php while($msg = $msg_res->fetch_assoc()): 
                        $is_me = ($msg['role'] == 'admin' || $msg['role'] == 'support');
                    ?>
                    <div class="flex <?php echo $is_me ? 'justify-end' : 'justify-start'; ?>">
                        <div class="max-w-[75%] <?php echo $is_me ? 'bg-indigo-600 text-white rounded-l-2xl rounded-tr-2xl' : 'bg-white text-gray-800 border border-gray-200 rounded-r-2xl rounded-tl-2xl'; ?> p-3 shadow-sm relative group">
                            
                            <?php if(!empty($msg['attachment'])): 
                                $ext = pathinfo($msg['attachment'], PATHINFO_EXTENSION);
                                if(in_array(strtolower($ext), ['jpg','jpeg','png','gif'])): ?>
                                    <a href="../<?php echo $msg['attachment']; ?>" target="_blank" class="block mb-2">
                                        <img src="../<?php echo $msg['attachment']; ?>" class="rounded-lg max-h-48 object-cover border border-white/20">
                                    </a>
                                <?php elseif(in_array(strtolower($ext), ['mp4','webm','mov'])): ?>
                                    <video src="../<?php echo $msg['attachment']; ?>" controls class="rounded-lg mb-2 max-h-48 w-full border border-white/20"></video>
                                <?php endif; ?>
                            <?php endif; ?>
                            <p class="text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                            <span class="text-[10px] <?php echo $is_me ? 'text-indigo-200' : 'text-gray-400'; ?> absolute bottom-1 right-2 opacity-0 group-hover:opacity-100 transition">
                                <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <div class="p-4 bg-white border-t border-gray-200">
                    <form method="POST" class="flex gap-2">
                        <input type="text" name="message" class="flex-1 bg-gray-100 border-0 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" placeholder="Type your reply..." autocomplete="off" required>
                        <button type="submit" name="send_message" class="bg-indigo-600 text-white w-12 h-12 rounded-xl flex items-center justify-center hover:bg-indigo-700 shadow-lg transition transform active:scale-95">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="hidden xl:flex w-80 bg-white border-l border-gray-200 flex-col overflow-y-auto">
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-gray-200 rounded-full mx-auto flex items-center justify-center text-xl font-bold text-gray-500 mb-2">
                            <?php echo substr($player_data['username'], 0, 2); ?>
                        </div>
                        <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($player_data['username']); ?></h3>
                        <p class="text-xs text-gray-500 mb-2"><?php echo wcb_public_email_html($player_data['email'] ?? ''); ?></p>
                        
                        <div class="flex justify-center gap-2">
                            <span class="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded font-bold">
                                ৳<?php echo number_format($player_data['balance'] ?? 0, 2); ?>
                            </span>
                            <span class="text-xs px-2 py-1 <?php echo ($player_data['risk_score'] ?? 0) > 50 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?> rounded font-bold">
                                Risk: <?php echo $player_data['risk_score'] ?? 0; ?>%
                            </span>
                        </div>
                    </div>

                    <hr class="border-gray-100 mb-6">

                    <div class="mb-6">
                        <h4 class="text-xs font-bold text-gray-400 uppercase mb-3">KYC Documents</h4>
                        <?php if($player_kyc && $player_kyc->num_rows > 0): ?>
                            <div class="grid grid-cols-2 gap-2">
                                <?php while($doc = $player_kyc->fetch_assoc()): ?>
                                <div class="cursor-pointer border rounded hover:opacity-80" onclick="openKycModal('../<?php echo $doc['file_path']; ?>')">
                                    <img src="../<?php echo $doc['file_path']; ?>" class="h-16 w-full object-cover rounded">
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-xs text-gray-400 italic">No documents uploaded.</p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-gray-400 uppercase mb-3">Last Transactions</h4>
                        <div class="space-y-3">
                            <?php if($player_trans && $player_trans->num_rows > 0): ?>
                                <?php while($tr = $player_trans->fetch_assoc()): ?>
                                <div class="flex justify-between items-center text-xs">
                                    <div>
                                        <span class="block font-bold <?php echo $tr['type']=='deposit'?'text-green-600':'text-red-600'; ?>">
                                            <?php echo ucfirst($tr['type']); ?>
                                        </span>
                                        <span class="text-[10px] text-gray-400"><?php echo date('d/m H:i', strtotime($tr['created_at'])); ?></span>
                                    </div>
                                    <span class="font-mono font-bold">৳<?php echo number_format($tr['amount']); ?></span>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 italic">No transactions found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="flex-1 flex flex-col items-center justify-center bg-gray-50 text-gray-400 hidden lg:flex">
                    <i class="fas fa-comments text-6xl mb-4 opacity-20"></i>
                    <p class="text-sm font-medium">Select a ticket to start chatting</p>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <div id="kycModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-95 flex items-center justify-center p-4 backdrop-blur-sm" onclick="closeKycModal()">
        <button class="absolute top-4 right-4 text-white text-4xl hover:text-gray-300">&times;</button>
        <img id="kycModalImg" src="" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl">
    </div>

</body>
</html>