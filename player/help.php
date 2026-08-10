<?php
session_start();
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) require $db_path;
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$site_name = 'RedJili';
if (isset($conn) && !$conn->connect_error) {
    $q = $conn->query("SELECT site_name FROM settings WHERE id=1");
    if ($q && $q->num_rows > 0) $site_name = $q->fetch_assoc()['site_name'] ?: $site_name;
}
$faqs = [
    ['Deposit কতক্ষণে add হবে?', 'Manual deposit হলে admin approve করার পর balance add হবে। Auto gateway active থাকলে payment verify হওয়ার সাথে সাথে balance update হবে।'],
    ['Withdraw request কোথায় দেখবো?', 'Account থেকে Withdrawal Records পেজে গিয়ে withdraw status দেখা যাবে।'],
    ['Game open না হলে কী করবো?', 'Internet connection, VPN, browser cache এবং account status check করুন। সমস্যা থাকলে Customer Service-এ ticket করুন।'],
    ['Referral / Invite link কোথায় পাবো?', 'Invite অথবা Affiliate menu থেকে আপনার referral link ও invite code পাওয়া যাবে।'],
    ['Password change করবো কীভাবে?', 'Account → Security Center থেকে password পরিবর্তন করা যাবে।'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Help Center - <?php echo htmlspecialchars($site_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin:0; background:#f0f2f5; color:#263238; font-family:'Segoe UI', Roboto, Arial, sans-serif; padding-bottom:90px; }
        .header { background:linear-gradient(135deg,#0f395c,#1a5c92); color:#fff; height:58px; display:flex; align-items:center; justify-content:space-between; padding:0 16px; position:sticky; top:0; z-index:50; }
        .hero { margin:16px; background:#fff; border-radius:18px; padding:22px 18px; text-align:center; box-shadow:0 8px 24px rgba(15,57,92,.10); border:1px solid #e6eef5; }
        .faq { margin:0 16px 12px; background:#fff; border-radius:14px; border:1px solid #e6eef5; overflow:hidden; box-shadow:0 4px 12px rgba(15,57,92,.05); }
        .faq summary { list-style:none; cursor:pointer; padding:15px; font-size:13px; font-weight:900; color:#154b77; display:flex; justify-content:space-between; gap:10px; }
        .faq summary::-webkit-details-marker { display:none; }
        .faq summary:after { content:'+'; font-size:18px; color:#43a047; }
        .faq[open] summary:after { content:'−'; }
        .faq p { padding:0 15px 15px; margin:0; color:#607d8b; font-size:12px; line-height:1.6; }
        .support { display:flex; gap:10px; margin:16px; }
        .support a { flex:1; text-decoration:none; border-radius:14px; padding:14px 10px; text-align:center; font-size:12px; font-weight:900; text-transform:uppercase; }
        .primary { background:#154b77; color:#fff; }
        .secondary { background:#fff; color:#154b77; border:1px solid #dbe7f1; }
    </style>
</head>
<body>
    <header class="header">
        <a href="account.php" class="text-white text-xl"><i class="fas fa-chevron-left"></i></a>
        <div class="font-black text-sm uppercase tracking-wide"><i class="far fa-question-circle"></i> Help Center</div>
        <a href="support_chat.php" class="text-white text-lg"><i class="fas fa-headset"></i></a>
    </header>

    <section class="hero">
        <div class="w-16 h-16 mx-auto rounded-full bg-blue-50 text-[#154b77] flex items-center justify-center text-2xl mb-3 border border-blue-100"><i class="fas fa-life-ring"></i></div>
        <h1 class="text-lg font-black text-[#154b77] uppercase">How can we help?</h1>
        <p class="text-xs text-gray-500 mt-1">Common questions and quick support links.</p>
    </section>

    <?php foreach ($faqs as $item): ?>
        <details class="faq">
            <summary><?php echo htmlspecialchars($item[0]); ?></summary>
            <p><?php echo htmlspecialchars($item[1]); ?></p>
        </details>
    <?php endforeach; ?>

    <div class="support">
        <a href="support_chat.php" class="primary"><i class="fas fa-headset mr-1"></i> Customer Service</a>
        <a href="feedback.php" class="secondary"><i class="far fa-comment-dots mr-1"></i> Advice</a>
    </div>

    <?php include 'bottom_nav.php'; ?>
</body>
</html>
