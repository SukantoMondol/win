<?php
session_start();
$dbPath = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (!file_exists($dbPath)) { http_response_code(500); exit('Database configuration missing.'); }
require $dbPath;
require_once '../includes/vip_system_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
wcb_vip_ensure_schema($conn);

$profileCheck = $conn->prepare('SELECT user_id FROM player_profiles WHERE user_id=? LIMIT 1');
$profileCheck->bind_param('i', $userId);
$profileCheck->execute();
$profileResult = $profileCheck->get_result();
if (!$profileResult || $profileResult->num_rows === 0) {
    $profileInsert = $conn->prepare('INSERT INTO player_profiles (user_id) VALUES (?)');
    $profileInsert->bind_param('i', $userId);
    $profileInsert->execute();
    $profileInsert->close();
}
$profileCheck->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'full_name') {
        $fullName = trim(preg_replace('/\s+/', ' ', (string)($_POST['full_name'] ?? '')));
        if (strlen($fullName) < 2 || strlen($fullName) > 100) {
            $_SESSION['profile_flash'] = array('type' => 'error', 'message' => 'Enter a valid full name.');
        } else {
            $stmt = $conn->prepare('UPDATE player_profiles SET kyc_real_name=? WHERE user_id=?');
            $stmt->bind_param('si', $fullName, $userId);
            $ok = $stmt->execute();
            $stmt->close();
            $_SESSION['profile_flash'] = array('type' => $ok ? 'success' : 'error', 'message' => $ok ? 'Full name updated successfully.' : 'Unable to update full name.');
        }
        header('Location: personal_info.php');
        exit;
    }

    if ($action === 'email') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $_SESSION['profile_flash'] = array('type' => 'error', 'message' => 'Enter a valid email address.');
        } else {
            $dup = $conn->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
            $dup->bind_param('si', $email, $userId);
            $dup->execute();
            $dupResult = $dup->get_result();
            if ($dupResult && $dupResult->num_rows > 0) {
                $_SESSION['profile_flash'] = array('type' => 'error', 'message' => 'This email address is already in use.');
            } else {
                $stmt = $conn->prepare('UPDATE users SET email=?, email_verified=1 WHERE id=?');
                $stmt->bind_param('si', $email, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                $_SESSION['profile_flash'] = array('type' => $ok ? 'success' : 'error', 'message' => $ok ? 'Email address saved and verified.' : 'Unable to save email address.');
            }
            $dup->close();
        }
        header('Location: personal_info.php');
        exit;
    }

    if ($action === 'avatar') {
        $file = $_FILES['avatar'] ?? null;
        $ok = false;
        if ($file && intval($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && intval($file['size'] ?? 0) > 0 && intval($file['size']) <= 5242880) {
            $mime = '';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime = (string)finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                }
            }
            $types = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
            if (isset($types[$mime]) && @getimagesize($file['tmp_name'])) {
                $directory = __DIR__ . '/assets/uploads/avatars';
                if (!is_dir($directory)) { @mkdir($directory, 0755, true); }
                foreach (array('jpg', 'jpeg', 'png', 'webp') as $extension) {
                    $old = $directory . '/avatar_' . $userId . '.' . $extension;
                    if (is_file($old)) { @unlink($old); }
                }
                $target = $directory . '/avatar_' . $userId . '.' . $types[$mime];
                $ok = move_uploaded_file($file['tmp_name'], $target);
            }
        }
        $_SESSION['profile_flash'] = array('type' => $ok ? 'success' : 'error', 'message' => $ok ? 'Profile photo updated successfully.' : 'Upload a JPG, PNG or WEBP image under 5 MB.');
        header('Location: personal_info.php');
        exit;
    }
}

$stmt = $conn->prepare('SELECT u.username, u.phone, u.email, u.email_verified, u.created_at, p.kyc_real_name FROM users u LEFT JOIN player_profiles p ON p.user_id=u.id WHERE u.id=? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$userResult = $stmt->get_result();
$user = $userResult && $userResult->num_rows > 0 ? $userResult->fetch_assoc() : array();
$stmt->close();

$email = trim((string)($user['email'] ?? ''));
$isPlaceholderEmail = $email === '' || preg_match('/^user_[0-9]+@lucky365\.com$/i', $email);
if ($isPlaceholderEmail) { $email = ''; }
$emailVerified = $email !== '' && intval($user['email_verified'] ?? 0) === 1;
$vipState = wcb_vip_state($conn, $userId);
$vipPoints = intval($vipState['available_vp'] ?? 0);
$vipLevel = (string)($vipState['current_level']['level_name'] ?? 'NORMAL');
$avatarUrl = '';
foreach (array('jpg', 'jpeg', 'png', 'webp') as $extension) {
    $path = __DIR__ . '/assets/uploads/avatars/avatar_' . $userId . '.' . $extension;
    if (is_file($path)) {
        $avatarUrl = 'assets/uploads/avatars/avatar_' . $userId . '.' . $extension . '?v=' . filemtime($path);
        break;
    }
}
$flash = $_SESSION['profile_flash'] ?? null;
unset($_SESSION['profile_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Personal Information</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}html,body{margin:0;max-width:100%;overflow-x:hidden}body{font-family:'Roboto',sans-serif;background:#f3f5f8;color:#17324d;padding-bottom:96px;-webkit-tap-highlight-color:transparent}.page{width:100%;max-width:720px;margin:0 auto;min-height:100vh}.header{height:58px;background:linear-gradient(135deg,#103d60,#1b5f91);color:#fff;display:flex;align-items:center;justify-content:center;position:sticky;top:0;z-index:40;box-shadow:0 3px 12px rgba(15,57,92,.18)}.header a,.header button{position:absolute;top:0;width:54px;height:58px;display:flex;align-items:center;justify-content:center;color:#fff;border:0;background:transparent}.header a{left:0}.header button{right:0}.profile-card,.info-card{background:#fff;border:1px solid #e0e7ef;border-radius:16px;box-shadow:0 5px 18px rgba(23,50,77,.05)}.profile-cover{height:86px;border-radius:15px 15px 0 0;background:linear-gradient(135deg,#154b77,#0d2a45);border-bottom:3px solid #42b86b}.avatar-wrap{width:88px;height:88px;margin:-44px auto 10px;position:relative}.avatar{width:88px;height:88px;border:4px solid #fff;border-radius:50%;overflow:hidden;background:#edf2f7;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 15px rgba(0,0,0,.17)}.avatar img{width:100%;height:100%;object-fit:cover}.avatar-edit{position:absolute;right:0;bottom:1px;width:28px;height:28px;border-radius:50%;border:2px solid #fff;background:#15527f;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;cursor:pointer}.profile-meta{display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #edf1f5;margin-top:16px}.meta-box{padding:14px;text-align:center}.meta-box:first-child{border-right:1px solid #edf1f5}.meta-label{font-size:10px;color:#8795a5;text-transform:uppercase;letter-spacing:.6px}.meta-value{margin-top:5px;font-size:14px;font-weight:600;color:#154b77}.vip-strip{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-top:1px solid #edf1f5;background:#f8fbfe;border-radius:0 0 15px 15px}.vip-value{font-size:18px;font-weight:700;color:#2f9e5b}.vip-link{display:inline-flex;align-items:center;gap:7px;padding:9px 13px;border-radius:9px;background:#eaf5ff;border:1px solid #cfe8fa;color:#15527f;font-size:12px;font-weight:600}.section-title{font-size:13px;font-weight:700;color:#154b77;text-transform:uppercase;letter-spacing:.6px;margin:0 0 12px}.info-card{padding:0 16px}.info-row{display:flex;align-items:center;gap:12px;padding:17px 0;border-bottom:1px solid #e8edf2}.info-row:last-child{border-bottom:0}.row-icon{width:38px;height:38px;border-radius:10px;background:#f5f8fb;border:1px solid #dde6ee;display:flex;align-items:center;justify-content:center;color:#15527f;flex:0 0 38px}.row-content{flex:1;min-width:0}.row-label{font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#154b77}.row-value{font-size:13px;color:#607387;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.status{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:7px;font-size:9px;font-weight:700;text-transform:uppercase;white-space:nowrap}.status-ok{background:#e7f8ed;color:#1e9b50;border:1px solid #c8efd5}.status-wait{background:#fff1f1;color:#e24b4b;border:1px solid #ffd2d2}.edit-btn{width:31px;height:31px;border:1px solid #dce5ed;border-radius:8px;background:#f8fafc;color:#15527f;display:flex;align-items:center;justify-content:center}.flash{padding:12px 14px;border-radius:10px;font-size:12px;font-weight:600;margin-bottom:14px}.flash-success{background:#eaf8ef;color:#21864a;border:1px solid #ccebd7}.flash-error{background:#fff0f0;color:#c93c3c;border:1px solid #ffd1d1}.modal{position:fixed;inset:0;z-index:100;background:rgba(14,29,43,.6);display:none;align-items:center;justify-content:center;padding:18px;backdrop-filter:blur(2px)}.modal.show{display:flex}.modal-card{width:100%;max-width:380px;background:#fff;border-radius:16px;padding:20px;box-shadow:0 18px 55px rgba(0,0,0,.22)}.modal-input{width:100%;height:48px;border:1px solid #dce5ed;border-radius:10px;padding:0 13px;font-size:14px;color:#17324d;outline:none}.modal-input:focus{border-color:#2883ba;box-shadow:0 0 0 3px rgba(40,131,186,.12)}.save-btn{width:100%;height:46px;border:0;border-radius:10px;background:linear-gradient(135deg,#1c8f54,#37b56f);color:#fff;font-size:13px;font-weight:700;margin-top:14px}.close-btn{width:34px;height:34px;border:0;border-radius:50%;background:#f1f4f7;color:#667789}.upload-note{font-size:10px;color:#8c99a6;margin-top:7px}@media(max-width:480px){.page{max-width:100%}.content{padding:14px!important}.profile-meta{grid-template-columns:1fr 1fr}.vip-strip{padding:13px}.info-card{padding:0 14px}.info-row{gap:10px}.status{padding:5px 7px}}
    </style>
</head>
<body>
<div class="page">
    <header class="header">
        <a href="account.php"><i class="fas fa-chevron-left"></i></a>
        <h1 class="text-[15px] font-semibold">Personal Information</h1>
        <button type="button" onclick="window.location.href='account.php'"><i class="fas fa-times"></i></button>
    </header>

    <main class="content p-4">
        <?php if ($flash): ?>
            <div class="flash <?php echo $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>"><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="profile-card overflow-hidden">
            <div class="profile-cover"></div>
            <form id="avatarForm" method="post" enctype="multipart/form-data" class="hidden">
                <input type="hidden" name="action" value="avatar">
                <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()">
            </form>
            <div class="avatar-wrap" onclick="document.getElementById('avatarInput').click()">
                <div class="avatar">
                    <?php if ($avatarUrl !== ''): ?>
                        <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile photo">
                    <?php else: ?>
                        <i class="fas fa-user text-3xl text-slate-300"></i>
                    <?php endif; ?>
                </div>
                <div class="avatar-edit"><i class="fas fa-camera"></i></div>
            </div>
            <div class="text-center px-4">
                <div class="text-[18px] font-semibold text-[#154b77]"><?php echo htmlspecialchars($user['kyc_real_name'] ?: $user['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="text-[11px] text-slate-400 mt-1">Tap the photo to update</div>
            </div>
            <div class="profile-meta">
                <div class="meta-box">
                    <div class="meta-label">Phone Number</div>
                    <div class="meta-value"><?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="meta-box">
                    <div class="meta-label">Joining Date</div>
                    <div class="meta-value"><?php echo !empty($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : '-'; ?></div>
                </div>
            </div>
            <div class="vip-strip">
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-slate-400">VIP Point</div>
                    <div class="vip-value"><?php echo number_format($vipPoints); ?> <span class="text-[10px] text-slate-400 font-medium">VP</span></div>
                </div>
                <a href="vip.php" class="vip-link"><i class="fas fa-crown text-amber-500"></i><?php echo htmlspecialchars($vipLevel, ENT_QUOTES, 'UTF-8'); ?><span>My VIP</span><i class="fas fa-chevron-right text-[9px]"></i></a>
            </div>
        </section>

        <section class="mt-4">
            <h2 class="section-title">Personal Information</h2>
            <div class="info-card">
                <div class="info-row">
                    <div class="row-icon"><i class="far fa-id-card"></i></div>
                    <div class="row-content">
                        <div class="row-label">Full Name</div>
                        <div class="row-value"><?php echo !empty($user['kyc_real_name']) ? htmlspecialchars($user['kyc_real_name'], ENT_QUOTES, 'UTF-8') : 'Not set'; ?></div>
                    </div>
                    <button type="button" class="edit-btn" onclick="openModal('nameModal')"><i class="fas fa-pen"></i></button>
                </div>
                <div class="info-row">
                    <div class="row-icon"><i class="fas fa-mobile-alt"></i></div>
                    <div class="row-content">
                        <div class="row-label">Phone Number</div>
                        <div class="row-value"><?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <span class="status status-ok"><i class="fas fa-check-circle"></i>Verified</span>
                </div>
                <div class="info-row">
                    <div class="row-icon"><i class="far fa-envelope"></i></div>
                    <div class="row-content">
                        <div class="row-label">Email Address</div>
                        <div class="row-value"><?php echo $email !== '' ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : 'Not added'; ?></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="status <?php echo $emailVerified ? 'status-ok' : 'status-wait'; ?>"><i class="fas <?php echo $emailVerified ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i><?php echo $emailVerified ? 'Verified' : 'Unverified'; ?></span>
                        <button type="button" class="edit-btn" onclick="openModal('emailModal')"><i class="fas fa-pen"></i></button>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<div id="nameModal" class="modal">
    <div class="modal-card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[16px] font-semibold text-[#154b77]">Update Full Name</h3>
            <button type="button" class="close-btn" onclick="closeModal('nameModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="full_name">
            <input class="modal-input" type="text" name="full_name" maxlength="100" required value="<?php echo htmlspecialchars($user['kyc_real_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter full name">
            <button class="save-btn" type="submit">Save Full Name</button>
        </form>
    </div>
</div>

<div id="emailModal" class="modal">
    <div class="modal-card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[16px] font-semibold text-[#154b77]">Add Email Address</h3>
            <button type="button" class="close-btn" onclick="closeModal('emailModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="email">
            <input class="modal-input" type="email" name="email" maxlength="100" required value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="name@example.com">
            <button class="save-btn" type="submit">Save Email Address</button>
        </form>
    </div>
</div>

<?php include 'bottom_nav.php'; ?>
<script>
function openModal(id){document.getElementById(id).classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show')}
document.querySelectorAll('.modal').forEach(function(modal){modal.addEventListener('click',function(event){if(event.target===modal){modal.classList.remove('show')}})})
</script>
</body>
</html>
