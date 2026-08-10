<?php
session_start();
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
} else {
    die('Database connection file not found.');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

function feedback_table_exists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? 'Feedback');
    $body = trim($_POST['message'] ?? '');

    if ($body === '') {
        $error = 'Please write your advice/message.';
    } elseif (!feedback_table_exists($conn, 'support_tickets') || !feedback_table_exists($conn, 'support_messages')) {
        $error = 'Feedback system database table is missing.';
    } else {
        $subject = $conn->real_escape_string(substr($subject ?: 'Feedback / Advice', 0, 100));
        $bodySql = $conn->real_escape_string($body);
        if ($conn->query("INSERT INTO support_tickets (user_id, subject, status, last_reply_at) VALUES ($user_id, '$subject', 'open', NOW())")) {
            $ticket_id = (int)$conn->insert_id;
            $conn->query("INSERT INTO support_messages (ticket_id, sender_id, message, created_at) VALUES ($ticket_id, $user_id, '$bodySql', NOW())");
            header("Location: support_chat.php?ticket_id=$ticket_id");
            exit();
        } else {
            $error = 'Could not submit feedback. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Advice / Feedback</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin:0; background:#f0f2f5; color:#263238; font-family:'Segoe UI', Roboto, Arial, sans-serif; padding-bottom:90px; }
        .header { background:linear-gradient(135deg,#0f395c,#1a5c92); color:#fff; height:58px; display:flex; align-items:center; justify-content:space-between; padding:0 16px; position:sticky; top:0; z-index:50; }
        .card { background:#fff; margin:16px; border-radius:18px; padding:18px; box-shadow:0 8px 24px rgba(15,57,92,.10); border:1px solid #e6eef5; }
        label { display:block; font-size:12px; font-weight:800; color:#154b77; margin-bottom:7px; text-transform:uppercase; }
        input, textarea { width:100%; border:1.5px solid #dbe7f1; border-radius:12px; padding:13px; outline:none; font-size:14px; background:#f8fafc; }
        input:focus, textarea:focus { border-color:#1a5c92; background:#fff; }
        textarea { min-height:160px; resize:vertical; }
        .btn { width:100%; background:#154b77; color:white; border:none; border-radius:12px; padding:14px; font-weight:900; text-transform:uppercase; letter-spacing:.5px; }
        .alert { margin-bottom:14px; border-radius:10px; padding:11px 12px; font-size:12px; font-weight:700; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    </style>
</head>
<body>
    <header class="header">
        <a href="account.php" class="text-white text-xl"><i class="fas fa-chevron-left"></i></a>
        <div class="font-black text-sm uppercase tracking-wide"><i class="far fa-comment-dots"></i> Advice</div>
        <a href="support_chat.php" class="text-white text-lg"><i class="fas fa-headset"></i></a>
    </header>

    <div class="card">
        <div class="text-center mb-5">
            <div class="w-16 h-16 mx-auto rounded-full bg-blue-50 text-[#154b77] flex items-center justify-center text-2xl mb-3 border border-blue-100"><i class="far fa-paper-plane"></i></div>
            <h1 class="text-lg font-black text-[#154b77] uppercase">Send Advice / Feedback</h1>
            <p class="text-xs text-gray-500 mt-1">আপনার সমস্যা, পরামর্শ বা feedback লিখে পাঠান। এটি support ticket হিসেবে submit হবে।</p>
        </div>

        <?php if($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="post">
            <div class="mb-4">
                <label>Subject</label>
                <input type="text" name="subject" value="Feedback / Advice" maxlength="100">
            </div>
            <div class="mb-5">
                <label>Message</label>
                <textarea name="message" placeholder="Write your message here..." required></textarea>
            </div>
            <button class="btn" type="submit"><i class="fas fa-paper-plane mr-1"></i> Submit</button>
        </form>
    </div>

    <?php include 'bottom_nav.php'; ?>
</body>
</html>
