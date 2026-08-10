<?php
require 'includes/db.php';
require_once 'includes/admin_path_helper.php';
session_start();

$error = "";

// Redirect if already logged in as support
if (isset($_SESSION['role']) && $_SESSION['role'] === 'support') {
    admin_panel_redirect('support.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];

    // Fetch user
    $sql = "SELECT id, username, email, password, role, status FROM users WHERE email = '$email' LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // 1. Verify Password
        if (password_verify($password, $user['password'])) {
            
            // 2. STRICT ROLE CHECK: Only 'support' allowed
            if ($user['role'] !== 'support') {
                $error = "Access Denied. This portal is for Support Staff only.";
            } 
            // 3. Check Status
            elseif ($user['status'] !== 'active') {
                $error = "Your account has been suspended. Contact Administration.";
            } 
            else {
                // 4. Set Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;

                // 5. Redirect to Support Dashboard
                admin_panel_redirect('support.php');
                exit();
            }
        } else {
            $error = "Incorrect credentials.";
        }
    } else {
        $error = "No account found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Portal | BetPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-slate-900 to-slate-800 h-screen flex items-center justify-center p-4 font-sans">

    <div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-sm border-t-4 border-indigo-500">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 text-indigo-600 text-2xl shadow-sm">
                <i class="fas fa-headset"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 tracking-wide">Support Portal</h1>
            <p class="text-sm text-gray-500">Authorized Personnel Only</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-6 flex items-center gap-2 border border-red-100 animate-pulse">
                <i class="fas fa-shield-alt"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Staff Email</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" required placeholder="support@betpro.com" 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition bg-gray-50 focus:bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Password</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition bg-gray-50 focus:bg-white">
                </div>
            </div>

            <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg shadow-lg hover:bg-indigo-700 hover:shadow-xl transition transform active:scale-95 flex items-center justify-center gap-2">
                Sign In to Console <i class="fas fa-arrow-right text-xs"></i>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-100 text-center">
            <a href="login.php" class="text-xs text-gray-400 hover:text-indigo-500 transition flex items-center justify-center gap-1">
                <i class="fas fa-home"></i> Back to Main Site
            </a>
        </div>
    </div>

</body>
</html>
