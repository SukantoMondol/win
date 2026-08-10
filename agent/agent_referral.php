<?php
session_start();
require '../includes/db.php'; 

// ১. সিকিউরিটি চেক (শুধু এজেন্ট ঢুকতে পারবে)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 

// ২. এজেন্ট টেবিল থেকে ডাটা এবং রেফার কোড আনা
$agent_sql = $conn->query("SELECT referral_code FROM agents WHERE user_id = '$user_id' LIMIT 1");

if ($agent_sql->num_rows > 0) {
    $agent_data = $agent_sql->fetch_assoc();
    $my_code = $agent_data['referral_code'];
} else {
    $my_code = 'NOT_ASSIGNED'; // যদি কোড না থাকে
}

// ৩. রেফারেল লিংক তৈরি (আপনার পাথ অনুযায়ী ফিক্স করা হয়েছে)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];

// [FIXED LINK] -> pointing to player/signup.php
$referral_link = "$protocol://$domain/player/signup.php?ref=$my_code"; 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Agent Referral</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #133729; color: #dbece3; font-family: sans-serif; }
        .card { background-color: #1e4234; border: 1px solid #305f4c; }
        ::selection { background: #ffdf1b; color: #000; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="card w-full max-w-lg rounded-xl shadow-2xl p-8 relative overflow-hidden">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-[#305f4c] rounded-full flex items-center justify-center mx-auto mb-4 border border-[#ffdf1b]">
                <i class="fas fa-link text-[#ffdf1b] text-3xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-white uppercase tracking-wider">Marketing Tool</h2>
            <p class="text-[#7db39b] text-sm mt-1">Share your unique link to add players under you.</p>
        </div>

        <div class="space-y-6">
            
            <div>
                <label class="text-xs font-bold text-[#7db39b] uppercase mb-1 block">Your Referral Code</label>
                <div class="bg-[#133729] border border-[#305f4c] rounded-lg p-4 text-center relative group">
                    <span class="text-3xl font-mono font-bold text-[#ffdf1b] tracking-[0.2em]">
                        <?php echo htmlspecialchars($my_code); ?>
                    </span>
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-[#7db39b] uppercase mb-1 block">Registration Link</label>
                <div class="flex gap-2 relative">
                    <input type="text" id="refLink" value="<?php echo $referral_link; ?>" readonly 
                           class="w-full bg-[#133729] border border-[#305f4c] rounded-lg px-4 py-3 text-sm text-gray-300 outline-none focus:border-[#ffdf1b] transition select-all">
                    
                    <button onclick="copyLink()" class="bg-[#305f4c] hover:bg-[#ffdf1b] hover:text-[#133729] text-white px-5 rounded-lg transition font-bold border border-[#305f4c] shrink-0" title="Copy Link">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>

            <div class="text-center">
                <div class="inline-block p-2 bg-white rounded-lg">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($referral_link); ?>" class="w-32 h-32">
                </div>
                <p class="text-xs text-gray-400 mt-2">Scan to Register</p>
            </div>

        </div>

        <div class="mt-8 text-center border-t border-[#305f4c] pt-4">
            <a href="dashboard.php" class="text-[#7db39b] hover:text-white text-sm font-bold flex items-center justify-center gap-2 transition">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

    </div>

    <script>
        function copyLink() {
            var copyText = document.getElementById("refLink");
            copyText.select();
            copyText.setSelectionRange(0, 99999); 

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(copyText.value).then(() => {
                    alert("Link Copied Successfully!");
                });
            } else {
                document.execCommand('copy');
                alert("Link Copied!");
            }
        }
    </script>

</body>
</html>