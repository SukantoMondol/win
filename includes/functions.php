<?php
// includes/functions.php

// 1. Sanitize Input (Prevent SQL Injection basics)
function sanitize($conn, $input) {
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($input))));
}

// 2. Format Money (e.g. 1500 -> ৳ 1,500.00)
function formatMoney($amount) {
    return '৳ ' . number_format($amount, 2);
}

// 3. Risk Badge Helper (For the UI)
function getRiskBadge($score) {
    if ($score >= 80) return '<span class="bg-red-100 text-red-800 text-xs font-bold px-2 py-1 rounded">CRITICAL</span>';
    if ($score >= 50) return '<span class="bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-1 rounded">HIGH</span>';
    return '<span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded">SAFE</span>';
}

// includes/functions.php (Add to bottom)

function calculateRiskScore($conn, $user_id) {
    $score = 0;
    
    // 1. FETCH USER DATA
    $sql = "SELECT u.*, p.* FROM users u 
            JOIN player_profiles p ON u.id = p.user_id 
            WHERE u.id = $user_id";
    $user = $conn->query($sql)->fetch_assoc();
    
    // 2. FETCH FINANCIALS
    $dep_q = $conn->query("SELECT SUM(amount) FROM transactions_fake WHERE user_id=$user_id AND type='deposit' AND status='approved'");
    $deposit = $dep_q->fetch_row()[0] ?? 0;
    
    $with_q = $conn->query("SELECT SUM(amount) FROM transactions_fake WHERE user_id=$user_id AND type='withdraw' AND status='approved'");
    $withdraw = $with_q->fetch_row()[0] ?? 0;
    
    // --- RULE 1: VPN DETECTION (+50 Risk) ---
    // In a real app, you'd check $_SERVER['REMOTE_ADDR'] against a blacklist
    if ($user['is_vpn_detected'] == 1) {
        $score += 50; 
    }

    // --- RULE 2: SUSPICIOUS WIN RATIO (+30 Risk) ---
    // If they withdraw 3x more than they deposited (and deposited at least 1000)
    if ($deposit > 1000 && $withdraw > ($deposit * 3)) {
        $score += 30;
    }

    // --- RULE 3: INCOMPLETE PROFILE (+10 Risk) ---
    if ($user['kyc_status'] == 'pending') {
        $score += 10;
    }

    // --- RULE 4: NEW DEVICE (+10 Risk) ---
    // Simulating a mismatch (hardcoded for demo)
    if (rand(0, 10) > 8) { 
        $score += 10; 
    }

    // Cap Score at 100
    if ($score > 100) $score = 100;
    
    // UPDATE DATABASE
    $conn->query("UPDATE player_profiles SET risk_score = $score WHERE user_id = $user_id");
    
    return $score;
}


// Add this to includes/functions.php

function check_pending_deposits($conn, $uid) {
    // 1. Find deposits that are 'approved' in transactions but user balance might not reflect it
    // Note: This relies on a trigger or manual check. 
    // A better way is to have a flag 'is_credited' in transactions table.
    // Since we don't have that in your schema, we rely on the trigger I provided earlier.
    
    // If you CANNOT use triggers, use this PHP fallback:
    /*
    $pending = $conn->query("SELECT id, amount FROM transactions_fake WHERE user_id=$uid AND type='deposit' AND status='approved' AND is_credited=0");
    while($row = $pending->fetch_assoc()) {
        $amount = $row['amount'];
        $tid = $row['id'];
        $conn->query("UPDATE users SET balance = balance + $amount WHERE id=$uid");
        $conn->query("UPDATE transactions_fake SET is_credited=1 WHERE id=$tid");
    }
    */
    
    // Since we are relying on the TRIGGER method provided in the previous solution, 
    // simply fetching the user balance again is enough.
    
    $user = $conn->query("SELECT balance FROM users WHERE id=$uid")->fetch_assoc();
    return $user['balance'];
}

// Front category display helper: show "Popular" instead of old "Hot / Hot Game" label.
if (!function_exists('wcb_front_category_label')) {
    function wcb_front_category_label($name) {
        $raw = trim((string)$name);
        $norm = strtolower(preg_replace('/\s+/', ' ', $raw));
        if (in_array($norm, array('hot', 'hot game', 'hot games'), true)) {
            return 'Popular';
        }
        return $raw;
    }
}

if (!function_exists('wcb_apply_category_name_patch')) {
    function wcb_apply_category_name_patch($conn) {
        if (!$conn || $conn->connect_error) return;
        @mysqli_query($conn, "UPDATE front_categories SET name='Popular' WHERE LOWER(TRIM(name)) IN ('hot','hot game','hot games')");
    }
}



// Hide generated placeholder emails from frontend/admin display while keeping real user-added emails visible.
if (!function_exists('wcb_is_default_player_email')) {
    function wcb_is_default_player_email($email) {
        $email = strtolower(trim((string)$email));
        if ($email === '') return true;
        return (bool)preg_match('/^user_[0-9]+@lucky365\.com$/i', $email);
    }
}

if (!function_exists('wcb_public_email')) {
    function wcb_public_email($email) {
        $email = trim((string)$email);
        if (wcb_is_default_player_email($email)) return '';
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('wcb_public_email_html')) {
    function wcb_public_email_html($email) {
        return htmlspecialchars(wcb_public_email($email), ENT_QUOTES, 'UTF-8');
    }
}

?>