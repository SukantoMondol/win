<?php
ob_start();
session_start();
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
} else {
    die("Database file not found.");
}

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? '';
if ($username === '' && $user_id > 0 && isset($conn)) {
    $uStmt = $conn->prepare("SELECT username FROM users WHERE id=? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param('i', $user_id);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        if ($uRes && $uRes->num_rows > 0) {
            $username = $uRes->fetch_assoc()['username'];
            $_SESSION['username'] = $username;
        }
        $uStmt->close();
    }
}
if ($username === '') { $username = 'User #' . $user_id; }

// 1. Fetch Settings
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$primary = $settings['theme_primary'] ?? '#154b77'; // Fallback to 1xbet Blue

// --- HANDLE FORM SUBMISSIONS ---

// A. Create New Ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_ticket'])) {
    $subject = $conn->real_escape_string($_POST['subject']);
    $message = $conn->real_escape_string($_POST['message']);
    
    $sql_ticket = "INSERT INTO support_tickets (user_id, subject, status, last_reply_at) VALUES ($user_id, '$subject', 'open', NOW())";
    if ($conn->query($sql_ticket)) {
        $ticket_id = $conn->insert_id;
        $sql_msg = "INSERT INTO support_messages (ticket_id, sender_id, message, created_at) VALUES ($ticket_id, $user_id, '$message', NOW())";
        $conn->query($sql_msg);
        header("Location: support_chat.php?ticket_id=$ticket_id");
        exit();
    }
}

// B. Send Reply & Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reply'])) {
    $t_id = intval($_POST['ticket_id']);
    $msg = $conn->real_escape_string($_POST['message']);
    $attachment = NULL;

    // Handle File Upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $file_name = $_FILES['attachment']['name'];
        $file_size = $_FILES['attachment']['size'];
        $file_tmp = $_FILES['attachment']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'webm'];
        
        // Max 30MB (30 * 1024 * 1024)
        if ($file_size <= 31457280 && in_array($file_ext, $allowed)) {
            $upload_dir = '../assets/uploads/support/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $new_name = uniqid() . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $attachment = 'assets/uploads/support/' . $new_name;
            }
        }
    }
    
    // Check if ticket belongs to user
    $check = $conn->query("SELECT id FROM support_tickets WHERE id=$t_id AND user_id=$user_id");
    if ($check->num_rows > 0 && (!empty($msg) || $attachment)) {
        $attach_sql = $attachment ? "'$attachment'" : "NULL";
        $conn->query("INSERT INTO support_messages (ticket_id, sender_id, message, attachment, created_at) VALUES ($t_id, $user_id, '$msg', $attach_sql, NOW())");
        $conn->query("UPDATE support_tickets SET last_reply_at=NOW(), status='open' WHERE id=$t_id");
        header("Location: support_chat.php?ticket_id=$t_id");
        exit();
    }
}

// --- VIEW LOGIC ---
$active_ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
$view_mode = $active_ticket_id > 0 ? 'chat' : 'list';
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Customer Service | SHA75</title>
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

        /* Prevent zooming & horizontal scroll */
        html, body {
            max-width: 100vw;
            overflow-x: hidden;
            touch-action: pan-y pinch-zoom;
        }

        body { 
            font-family: 'Roboto', sans-serif; 
            background-color: var(--bg-color); 
            min-height: 100vh;
            min-height: 100dvh;
            overflow-y: auto;
            display: flex; flex-direction: column; 
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }
        input, textarea { user-select: auto; }

        .header-bg { 
            background: linear-gradient(135deg, #0f395c 0%, #1a5c92 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Chat Bubbles */
        .msg-user { 
            background-color: #dcfce7; /* Light Green */
            border: 1px solid #bbf7d0;
            color: #166534;
            align-self: flex-end; 
            border-radius: 12px 12px 0 12px; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .msg-support { 
            background-color: #ffffff; 
            border: 1px solid #e2e8f0;
            color: #334155;
            align-self: flex-start; 
            border-radius: 12px 12px 12px 0; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        /* Modal Animation */
        .modal-enter { animation: fadeUp 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }

        /* Input Area */
        .chat-input-area {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--border-color);
        }
    </style>
</head>
<body>

    <header class="header-bg flex justify-between items-center px-4 py-3.5 shadow-md sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <button onclick="<?php echo $view_mode=='chat' ? "window.location.href='support_chat.php'" : "window.location.href='account.php'"; ?>" class="text-white text-xl p-1 hover:text-blue-200 transition">
                <i class="fas fa-chevron-left"></i>
            </button>
            <h1 class="text-[15px] font-bold text-white uppercase tracking-wider">
                <?php echo $view_mode == 'chat' ? 'Conversation' : 'Customer Service'; ?>
            </h1>
        </div>
        <?php if($view_mode == 'list'): ?>
        <button onclick="document.getElementById('newTicketModal').classList.remove('hidden')" class="bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded-md text-[11px] font-bold text-white uppercase tracking-wide transition shadow-sm flex items-center gap-1.5">
            <i class="fas fa-plus"></i> New
        </button>
        <?php endif; ?>
    </header>

    <div class="flex-1 overflow-y-auto relative bg-[#f8fafc]">

        <?php if ($view_mode == 'list'): ?>
            <div class="p-4 space-y-3 h-full">
                <?php 
                $tickets = $conn->query("SELECT * FROM support_tickets WHERE user_id=$user_id ORDER BY last_reply_at DESC");
                if ($tickets->num_rows > 0): 
                    while($t = $tickets->fetch_assoc()): 
                        $is_open = $t['status'] == 'open';
                        $status_color = $is_open ? 'text-green-600 bg-green-50 border-green-200' : 'text-gray-500 bg-gray-100 border-gray-200';
                        $icon = $is_open ? 'fa-envelope-open-text text-[#154b77]' : 'fa-check-circle text-gray-400';
                ?>
                <a href="?ticket_id=<?php echo $t['id']; ?>" class="flex gap-3 bg-white p-4 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition active:scale-[0.98]">
                    <div class="w-10 h-10 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center flex-shrink-0 mt-1">
                        <i class="fas <?php echo $icon; ?> text-lg"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start mb-1.5 gap-2">
                            <h3 class="font-extrabold text-[#154b77] text-[13px] truncate uppercase tracking-wide"><?php echo htmlspecialchars($t['subject']); ?></h3>
                            <span class="text-[9px] <?php echo $status_color; ?> px-2 py-0.5 rounded border font-bold uppercase tracking-wider flex-shrink-0"><?php echo $t['status']; ?></span>
                        </div>
                        <div class="flex justify-between items-center text-[11px] text-gray-500 font-medium">
                            <span>Ticket #<?php echo $t['id']; ?></span>
                            <span><?php echo date('d M, h:i A', strtotime($t['last_reply_at'])); ?></span>
                        </div>
                    </div>
                </a>
                <?php endwhile; 
                else: ?>
                    <div class="flex flex-col items-center justify-center h-full mt-24 text-gray-400">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4 border border-gray-200">
                            <i class="far fa-comments text-3xl text-gray-300"></i>
                        </div>
                        <p class="font-bold text-sm text-gray-500 uppercase tracking-wide">No Tickets Found</p>
                        <p class="text-xs mt-1 text-gray-400">Create a new ticket to get support.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($view_mode == 'chat'): 
            $msgs = $conn->query("SELECT * FROM support_messages WHERE ticket_id=$active_ticket_id ORDER BY created_at ASC");
            $ticket_info = $conn->query("SELECT subject, status FROM support_tickets WHERE id=$active_ticket_id")->fetch_assoc();
        ?>
            <div class="flex flex-col p-4 space-y-4 pb-24" id="chatContainer">
                <div class="text-center my-2 sticky top-2 z-10">
                    <span class="bg-white/90 border border-gray-200 text-[#154b77] font-bold text-[10px] px-3.5 py-1.5 rounded-full shadow-sm backdrop-blur uppercase tracking-wide">
                        <?php echo htmlspecialchars($ticket_info['subject']); ?>
                    </span>
                </div>

                <?php while($m = $msgs->fetch_assoc()): 
                    $is_me = (($m['sender_type'] ?? 'user') !== 'admin' && (int)$m['sender_id'] === (int)$user_id);
                ?>
                <div class="flex flex-col max-w-[85%] <?php echo $is_me ? 'msg-user' : 'msg-support'; ?> p-2.5 relative">
                    
                    <?php if(!empty($m['attachment'])): 
                        $ext = pathinfo($m['attachment'], PATHINFO_EXTENSION);
                        if(in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                            <a href="../<?php echo $m['attachment']; ?>" target="_blank">
                                <img src="../<?php echo $m['attachment']; ?>" class="rounded-lg mb-2 max-h-48 object-cover w-full border border-black/5">
                            </a>
                        <?php elseif(in_array($ext, ['mp4','webm'])): ?>
                            <video src="../<?php echo $m['attachment']; ?>" controls class="rounded-lg mb-2 max-h-48 w-full border border-black/5"></video>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if(!empty($m['message'])): ?>
                        <p class="text-[13px] font-medium leading-relaxed px-1 break-words"><?php echo nl2br(htmlspecialchars($m['message'])); ?></p>
                    <?php endif; ?>
                    
                    <span class="text-[9px] mt-1.5 block text-right px-1 opacity-70 font-bold uppercase tracking-wider">
                        <?php echo date('h:i A', strtotime($m['created_at'])); ?>
                        <?php if($is_me): ?> <i class="fas fa-check-double ml-1 text-[#43a047]"></i> <?php endif; ?>
                    </span>
                </div>
                <?php endwhile; ?>
            </div>

            <?php if($ticket_info['status'] != 'closed'): ?>
            <div class="fixed bottom-0 left-0 w-full chat-input-area px-3 py-3 z-50">
                
                <div id="filePreview" class="hidden absolute -top-14 left-4 bg-[#154b77] text-white text-[11px] font-bold px-3 py-2 rounded-lg shadow-lg flex items-center gap-3 border border-[#1a5c92]">
                    <i class="fas fa-file-image"></i> 
                    <span id="fileName" class="truncate max-w-[150px]">file.jpg</span>
                    <button onclick="clearFile()" class="text-blue-200 hover:text-white bg-[#0d2a45] w-5 h-5 rounded-full flex items-center justify-center transition ml-1"><i class="fas fa-times text-[10px]"></i></button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                    <input type="hidden" name="ticket_id" value="<?php echo $active_ticket_id; ?>">
                    <input type="hidden" name="send_reply" value="1">
                    
                    <input type="file" name="attachment" id="fileInput" class="hidden" accept="image/*,video/*" onchange="handleFileSelect(this)">
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="text-[#154b77] bg-[#e0f2fe] w-10 h-10 rounded-full flex items-center justify-center text-lg hover:bg-[#bae6fd] transition flex-shrink-0 border border-[#bae6fd]">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    
                    <input type="text" name="message" id="msgInput" placeholder="Type your message..." 
                           class="flex-1 bg-gray-100 border border-gray-200 rounded-full px-4 py-3 text-[13px] font-medium text-[#154b77] focus:ring-2 focus:ring-[#bae6fd] focus:border-[#154b77] outline-none transition placeholder-gray-400">
                    
                    <button type="submit" class="bg-gradient-to-b from-[#4caf50] to-[#388e3c] text-white w-11 h-11 rounded-full flex items-center justify-center shadow-md active:scale-95 transition flex-shrink-0 pl-1">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <script>
                // Auto Scroll to bottom
                const container = document.getElementById('chatContainer');
                container.scrollTop = container.scrollHeight;

                // File Handling
                function handleFileSelect(input) {
                    const file = input.files[0];
                    if (file) {
                        if (file.size > 30 * 1024 * 1024) { // 30MB
                            alert("File size cannot exceed 30MB.");
                            input.value = "";
                        } else {
                            document.getElementById('filePreview').classList.remove('hidden');
                            document.getElementById('fileName').innerText = file.name;
                        }
                    }
                }

                function clearFile() {
                    document.getElementById('fileInput').value = "";
                    document.getElementById('filePreview').classList.add('hidden');
                }
            </script>
        <?php endif; ?>

    </div>

    <div id="newTicketModal" class="fixed inset-0 bg-gray-900/60 z-[60] hidden flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white w-full max-w-sm rounded-xl p-6 modal-enter relative shadow-2xl border border-gray-200">
            <button onclick="document.getElementById('newTicketModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-100 w-8 h-8 rounded-full flex items-center justify-center transition"><i class="fas fa-times"></i></button>
            
            <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center mb-4 border border-blue-100">
                <i class="fas fa-headset text-xl text-[#154b77]"></i>
            </div>
            <h3 class="text-lg font-black text-[#154b77] mb-5 uppercase tracking-wide">Create New Ticket</h3>
            
            <form method="POST">
                <input type="hidden" name="create_ticket" value="1">
                <div class="mb-4">
                    <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-wide">Subject</label>
                    <select name="subject" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-[13px] font-medium text-[#154b77] outline-none focus:border-[#154b77] focus:ring-2 focus:ring-[#bae6fd] transition">
                        <option value="Deposit Issue">Deposit Issue</option>
                        <option value="Withdrawal Issue">Withdrawal Issue</option>
                        <option value="Game Issue">Game Issue</option>
                        <option value="Bonus/Promotions">Bonus/Promotions</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div class="mb-6">
                    <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-wide">Message</label>
                    <textarea name="message" rows="4" required class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-[13px] font-medium text-[#154b77] outline-none focus:border-[#154b77] focus:ring-2 focus:ring-[#bae6fd] transition resize-none" placeholder="Describe your issue in detail..."></textarea>
                </div>
                <button type="submit" class="w-full bg-gradient-to-b from-[#4caf50] to-[#388e3c] text-white font-extrabold text-[14px] uppercase tracking-wide py-3.5 rounded-lg shadow-md active:scale-[0.98] transition">Submit Ticket</button>
            </form>
        </div>
    </div>

</body>
</html>