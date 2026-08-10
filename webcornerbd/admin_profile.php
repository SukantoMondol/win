<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require_once '../includes/admin_auth_helper.php';

if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

if (!wcb_admin_ensure_schema($conn)) {
    http_response_code(503);
    die('Secure administrator profile is temporarily unavailable.');
}

$adminId = (int)$_SESSION['admin_id'];
$admin = wcb_admin_get_by_id($conn, $adminId);
if (!$admin || $admin['status'] !== 'active') {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';
$csrfToken = wcb_admin_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_credentials'])) {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $newEmail = strtolower(trim((string)($_POST['email'] ?? '')));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $passwordRequested = ($newPassword !== '' || $confirmPassword !== '');
    $emailChanged = ($newEmail !== strtolower((string)$admin['email']));

    if (!wcb_admin_verify_csrf($postedToken)) {
        $message = 'Security validation failed. Please refresh and try again.';
        $messageType = 'error';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid admin email address.';
        $messageType = 'error';
    } elseif ($currentPassword === '' || !password_verify($currentPassword, $admin['password'])) {
        $message = 'Current password is incorrect.';
        $messageType = 'error';
    } elseif ($passwordRequested && strlen($newPassword) < 8) {
        $message = 'New password must be at least 8 characters.';
        $messageType = 'error';
    } elseif ($passwordRequested && $newPassword !== $confirmPassword) {
        $message = 'New password and confirm password do not match.';
        $messageType = 'error';
    } elseif ($passwordRequested && password_verify($newPassword, $admin['password'])) {
        $message = 'New password must be different from the current password.';
        $messageType = 'error';
    } elseif (!$emailChanged && !$passwordRequested) {
        $message = 'No changes were provided.';
        $messageType = 'error';
    } else {
        $check = $conn->prepare("SELECT id FROM `admin` WHERE email=? AND id<>? LIMIT 1");
        if (!$check) {
            wcb_admin_log_server_error('Admin credential duplicate-check prepare failed: ' . $conn->error);
            $message = 'Unable to update the administrator profile right now.';
            $messageType = 'error';
        } else {
            $check->bind_param('si', $newEmail, $adminId);
            $check->execute();
            $exists = $check->get_result();
            $check->close();

            if ($exists && $exists->num_rows > 0) {
                $message = 'This email address is already in use.';
                $messageType = 'error';
            } else {
                $conn->begin_transaction();
                $saved = false;

                try {
                    if ($passwordRequested) {
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        if ($newHash === false) {
                            throw new RuntimeException('Unable to create a secure password hash.');
                        }

                        $stmt = $conn->prepare("UPDATE `admin` SET email=?, password=?, last_password_change_at=NOW() WHERE id=?");
                        if (!$stmt) {
                            throw new RuntimeException('Unable to prepare administrator credential update.');
                        }
                        $stmt->bind_param('ssi', $newEmail, $newHash, $adminId);
                    } else {
                        $stmt = $conn->prepare("UPDATE `admin` SET email=? WHERE id=?");
                        if (!$stmt) {
                            throw new RuntimeException('Unable to prepare administrator email update.');
                        }
                        $stmt->bind_param('si', $newEmail, $adminId);
                    }

                    if (!$stmt->execute()) {
                        $stmt->close();
                        throw new RuntimeException('Unable to save administrator credentials.');
                    }
                    $stmt->close();

                    $conn->commit();
                    $saved = true;
                } catch (Throwable $e) {
                    $conn->rollback();
                    wcb_admin_log_server_error('Admin credential update failed: ' . $e->getMessage());
                }

                if ($saved) {
                    session_regenerate_id(true);
                    unset($_SESSION['admin_csrf_token']);
                    $csrfToken = wcb_admin_csrf_token();

                    if ($emailChanged && $passwordRequested) {
                        $message = 'Admin email and password updated successfully.';
                    } elseif ($passwordRequested) {
                        $message = 'Admin password updated successfully.';
                    } else {
                        $message = 'Admin email updated successfully.';
                    }
                    $messageType = 'success';

                    if ($emailChanged) {
                        wcb_admin_write_audit_log($conn, $adminId, 'Administrator email was changed.');
                    }
                    if ($passwordRequested) {
                        wcb_admin_write_audit_log($conn, $adminId, 'Administrator password was changed.', 'warning');
                    }
                } else {
                    $message = 'Unable to update the administrator profile right now.';
                    $messageType = 'error';
                }
            }
        }
    }

    $admin = wcb_admin_get_by_id($conn, $adminId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | Secure Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans text-slate-800">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300 pt-20 lg:pt-8">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-user-shield text-indigo-600"></i> Admin Profile
                    </h1>
                    <p class="text-sm text-gray-500">Update the administrator email and secure login password.</p>
                </div>
                <a href="dashboard.php" class="px-4 py-2 rounded-lg bg-white border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition shrink-0">
                    <i class="fas fa-arrow-left mr-1"></i> Dashboard
                </a>
            </div>

            <?php if ($message !== ''): ?>
                <div class="mb-6 px-4 py-3 rounded-lg border-l-4 <?php echo $messageType === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'; ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <section id="credentials" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden scroll-mt-6">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50">
                    <h2 class="font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-key text-indigo-600"></i> Change Password &amp; Login Email
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">Current password is required before any change can be saved.</p>
                </div>

                <form method="POST" class="p-6 space-y-5" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="update_admin_credentials" value="1">

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Admin Email</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars((string)$admin['email'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Current Password</label>
                        <input type="password" name="current_password" required autocomplete="current-password" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" placeholder="Enter current password">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">New Password</label>
                            <input type="password" name="new_password" minlength="8" autocomplete="new-password" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" placeholder="Leave blank to keep current">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" placeholder="Repeat new password">
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                        Use at least 8 characters for a new password. Leave both new-password fields blank when changing only the email.
                    </div>

                    <button type="submit" class="w-full bg-gray-900 hover:bg-black text-white font-bold py-3 rounded-lg transition shadow-sm">
                        <i class="fas fa-save mr-2"></i> Save Admin Login Information
                    </button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
