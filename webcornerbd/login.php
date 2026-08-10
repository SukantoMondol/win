<?php
ob_start();
session_start();

// Do not cache the administrator login state or protected-page history.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

require_once '../includes/db.php';
require_once '../includes/admin_auth_helper.php';

if (($_SESSION['role'] ?? '') === 'admin' && !empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$now = time();
if (!isset($_SESSION['admin_login_window']) || ($now - (int)$_SESSION['admin_login_window']) > 900) {
    $_SESSION['admin_login_window'] = $now;
    $_SESSION['admin_login_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((int)($_SESSION['admin_login_attempts'] ?? 0) >= 8) {
        $error = 'Access temporarily limited. Please try again later.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Access Denied: Invalid Credentials.';
        } elseif (!wcb_admin_ensure_schema($conn)) {
            $error = 'Secure administrator access is temporarily unavailable.';
        } else {
            $admin = wcb_admin_find_by_email($conn, $email);
            $valid = $admin && $admin['status'] === 'active' && password_verify($password, $admin['password']);

            if ($valid) {
                if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $rehash = $conn->prepare("UPDATE `admin` SET password=? WHERE id=?");
                    if ($rehash) {
                        $adminIdForHash = (int)$admin['id'];
                        $rehash->bind_param('si', $newHash, $adminIdForHash);
                        $rehash->execute();
                        $rehash->close();
                    }
                }

                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                // Kept only for compatibility with existing authorization checks; no users-table lookup is used.
                $_SESSION['user_id'] = (int)$admin['id'];
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_name'] = (string)$admin['name'];
                $_SESSION['last_login_time'] = $now;
                $_SESSION['admin_login_attempts'] = 0;
                unset($_SESSION['admin_csrf_token']);

                wcb_admin_record_login($conn, (int)$admin['id']);
                wcb_admin_write_audit_log($conn, (int)$admin['id'], 'Administrator signed in successfully.');

                header('Location: dashboard.php');
                exit();
            }

            $_SESSION['admin_login_attempts'] = (int)($_SESSION['admin_login_attempts'] ?? 0) + 1;
            usleep(350000);
            $error = 'Access Denied: Invalid Credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control | BetPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes pulse-red {
            0%, 100% { background-color: rgba(185, 28, 28, 1); }
            50% { background-color: rgba(127, 29, 29, 1); }
        }
        .animate-pulse-red {
            animation: pulse-red 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-gray-900 min-h-screen flex flex-col items-center justify-center p-4">

    <div class="w-full max-w-sm">
        <div class="bg-gray-800 rounded-xl shadow-2xl border border-gray-700 p-6 md:p-8">
            <div class="text-center mb-8">
                <div class="inline-block bg-red-600 text-white text-[10px] px-2 py-0.5 rounded mb-2 font-bold uppercase">Secure Access</div>
                <h1 class="text-2xl font-bold text-white tracking-widest uppercase">System <span class="text-red-500">Admin</span></h1>
                <p class="text-xs text-gray-500 mt-1 italic">Authorized Personnel Only</p>
            </div>

            <?php if($error): ?>
                <div class="bg-red-900/30 border border-red-600 text-red-400 text-xs p-3 rounded-md mb-6 text-center font-medium">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1.5 ml-1">Admin ID</label>
                    <input type="email" name="email" required class="w-full bg-gray-900 border border-gray-600 rounded-lg p-3 text-white focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all" placeholder="admin@demo.com">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1.5 ml-1">Security Key</label>
                    <input type="password" name="password" required class="w-full bg-gray-900 border border-gray-600 rounded-lg p-3 text-white focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all" placeholder="••••••••">
                </div>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg shadow-lg hover:shadow-red-900/20 transition-all uppercase tracking-widest text-sm mt-2">
                    Access Control Panel
                </button>
            </form>
        </div>
        
    </div>

</body>
</html>