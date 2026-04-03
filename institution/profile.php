<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'student';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get NFC device_id — cari dari locker yang ada device_id
$nfc_device_id = '';
try {
    // Cuba dari locker user sendiri dulu
    $stmt_dev = $pdo->prepare("
        SELECT l.device_id FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.user_id = ? AND ula.is_active = 1
          AND l.device_id IS NOT NULL AND l.device_id != ''
          AND l.status != 'maintenance'
        LIMIT 1
    ");
    $stmt_dev->execute([$user_id]);
    $dev = $stmt_dev->fetch();
    $nfc_device_id = $dev['device_id'] ?? '';

    // Kalau user takde locker, guna device mana-mana locker dalam sistem
    if (empty($nfc_device_id)) {
        $stmt_any = $pdo->query("
            SELECT device_id FROM lockers
            WHERE device_id IS NOT NULL AND device_id != ''
              AND status != 'maintenance'
            LIMIT 1
        ");
        $any = $stmt_any->fetch();
        $nfc_device_id = $any['device_id'] ?? '';
    }
} catch(Exception $e) {}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $full_name      = trim($_POST['full_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $user_id_number = trim($_POST['user_id_number'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $institution    = trim($_POST['institution'] ?? '');

        if (empty($full_name) || empty($email) || empty($user_id_number)) {
            $message = ['type'=>'danger', 'text'=>'Name, Email, and ID Number are required.'];
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $user_id]);
            if ($chk->fetch()) {
                $message = ['type'=>'danger', 'text'=>'Email already taken by another user.'];
            } else {
                $pdo->prepare("UPDATE users SET full_name=?, email=?, user_id_number=?, phone=?, institution=? WHERE id=?")
                    ->execute([$full_name, $email, $user_id_number, $phone, $institution, $user_id]);
                $_SESSION['user_name']      = $full_name;
                $_SESSION['user_id_number'] = $user_id_number;
                $_SESSION['user_email']     = $email;
                $_SESSION['institution']    = $institution;
                $user['full_name']      = $full_name;
                $user['email']          = $email;
                $user['user_id_number'] = $user_id_number;
                $user['phone']          = $phone;
                $user['institution']    = $institution;
                $message = ['type'=>'success', 'text'=>'Profile updated successfully!'];
            }
        }

    } elseif ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = ['type'=>'danger', 'text'=>'All password fields are required.'];
        } elseif ($new_password !== $confirm_password) {
            $message = ['type'=>'danger', 'text'=>'New passwords do not match.'];
        } elseif (strlen($new_password) < 6) {
            $message = ['type'=>'danger', 'text'=>'New password must be at least 6 characters.'];
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $message = ['type'=>'danger', 'text'=>'Current password is incorrect.'];
        } else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([password_hash($new_password, PASSWORD_DEFAULT), $user_id]);
            $message = ['type'=>'success', 'text'=>'Password updated successfully!'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Smart Locker Institution</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{
            --teal:#0d7377;--teal-d:#085c60;--teal-l:#e8f6f7;
            --dark:#0f1f20;--mid:#3d5c5e;--light:#f4f9f9;
            --white:#fff;--border:#d0e8e9;--sidebar-w:240px;
            --err:#c0392b;--suc:#0a7c63;
        }

        /* ── NFC ── */
        .nfc-section{margin-top:20px;}
        .nfc-registered-box{background:linear-gradient(135deg,#e8f6f7,#d0f0f0);border:1.5px solid #a8dce0;border-radius:12px;padding:20px;display:flex;align-items:center;gap:16px;margin-bottom:16px;flex-wrap:wrap;}
        .nfc-card-icon{width:52px;height:52px;border-radius:12px;background:linear-gradient(135deg,var(--teal),var(--teal-d));display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;}
        .nfc-uid{font-family:'Courier New',monospace;font-size:.95rem;font-weight:700;letter-spacing:3px;color:var(--dark);background:rgba(255,255,255,.7);padding:6px 14px;border-radius:8px;display:inline-block;margin-top:4px;}
        .badge-nfc-active{background:#e8f7f3;color:#0a7c63;border:1px solid #b6e8da;border-radius:20px;padding:4px 14px;font-size:.75rem;font-weight:700;white-space:nowrap;}
        .nfc-empty-box{background:var(--light);border:2px dashed var(--border);border-radius:12px;padding:28px 20px;text-align:center;margin-bottom:20px;}

        /* NFC pulse animation */
        .nfc-pulse-wrap{position:relative;width:90px;height:90px;margin:0 auto 20px;}
        .nfc-ring{position:absolute;border-radius:50%;border:2px solid var(--teal);top:50%;left:50%;transform:translate(-50%,-50%) scale(0);opacity:0;animation:nfcRing 2s ease-out infinite;}
        .nfc-ring:nth-child(1){width:28px;height:28px;animation-delay:0s}
        .nfc-ring:nth-child(2){width:56px;height:56px;animation-delay:.5s}
        .nfc-ring:nth-child(3){width:84px;height:84px;animation-delay:1s}
        @keyframes nfcRing{0%{transform:translate(-50%,-50%) scale(0);opacity:.9}100%{transform:translate(-50%,-50%) scale(1);opacity:0}}
        .nfc-center-icon{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:28px;color:var(--teal);animation:nfcGlow 1.5s ease-in-out infinite;}
        @keyframes nfcGlow{0%,100%{filter:drop-shadow(0 0 5px var(--teal))}50%{filter:drop-shadow(0 0 16px var(--teal))}}

        /* NFC Timer */
        .timer-wrap{max-width:260px;margin:0 auto 20px;}
        .timer-label{display:flex;justify-content:space-between;font-size:.78rem;color:var(--mid);margin-bottom:6px;}
        .timer-bg{height:5px;background:var(--border);border-radius:3px;overflow:hidden;}
        .timer-fill{height:100%;background:linear-gradient(90deg,var(--teal),var(--teal-d));border-radius:3px;width:100%;transition:width 1s linear;}

        /* NFC Buttons */
        .btn-nfc-reg{background:var(--teal);color:white;border:none;border-radius:9px;padding:11px 22px;font-family:inherit;font-weight:700;font-size:13px;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;}
        .btn-nfc-reg:hover{background:var(--teal-d);transform:translateY(-1px);box-shadow:0 6px 20px rgba(13,115,119,.35);}
        .btn-nfc-replace{background:transparent;color:var(--err);border:1.5px solid var(--err);border-radius:9px;padding:9px 20px;font-family:inherit;font-weight:600;font-size:13px;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;}
        .btn-nfc-replace:hover{background:var(--err);color:white;}
        .btn-nfc-cancel{background:var(--light);color:var(--mid);border:1.5px solid var(--border);border-radius:8px;padding:8px 18px;font-family:inherit;font-weight:600;font-size:13px;cursor:pointer;transition:all .2s;}
        .btn-nfc-cancel:hover{border-color:var(--err);color:var(--err);}
        .btn-nfc-success{background:var(--suc);color:white;border:none;border-radius:9px;padding:9px 20px;font-family:inherit;font-weight:600;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;}
        .nfc-warn-box{background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:12px 16px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:10px;margin-top:12px;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--light);color:var(--dark);display:flex;min-height:100vh;}

        /* ── SIDEBAR ── */
        .sidebar{width:var(--sidebar-w);background:#0f1f20;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50;}
        .sidebar-brand{padding:22px 20px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:10px;}
        .brand-icon{width:36px;height:36px;background:var(--teal);border-radius:9px;display:grid;place-items:center;font-size:16px;flex-shrink:0;}
        .brand-text strong{display:block;color:white;font-size:13px;font-weight:700;}
        .brand-text span{font-size:10px;color:rgba(255,255,255,0.4);letter-spacing:0.08em;text-transform:uppercase;}
        .sidebar-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.07);}
        .user-info{display:flex;align-items:center;gap:10px;}
        .user-avatar{width:38px;height:38px;border-radius:50%;background:var(--teal);display:grid;place-items:center;color:white;font-size:15px;font-weight:700;flex-shrink:0;}
        .user-info strong{color:white;font-size:13px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;}
        .user-badge{display:inline-block;font-size:10px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:2px 8px;border-radius:20px;margin-top:3px;}
        .badge-student{background:rgba(13,115,119,0.3);color:#6de8ec;}
        .badge-staff{background:rgba(20,169,138,0.3);color:#6de8c0;}
        .nav-section{padding:16px 12px 8px;font-size:10px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.25);}
        .nav-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:9px;margin:2px 8px;color:rgba(255,255,255,0.55);font-size:13px;font-weight:500;text-decoration:none;transition:all 0.2s;}
        .nav-item i{width:18px;text-align:center;font-size:13px;}
        .nav-item:hover{background:rgba(255,255,255,0.07);color:white;}
        .nav-item.active{background:var(--teal);color:white;}
        .sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,0.07);}
        .logout-btn{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;color:rgba(255,255,255,0.45);font-size:13px;text-decoration:none;transition:all 0.2s;width:100%;}
        .logout-btn:hover{background:rgba(220,50,50,0.15);color:#ff7b7b;}

        /* ── MAIN ── */
        .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
        .topbar{background:white;border-bottom:1px solid var(--border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;}
        .topbar h1{font-size:18px;font-weight:800;color:var(--dark);}
        .topbar p{font-size:12px;color:var(--mid);margin-top:2px;}

        .content{padding:28px 32px;flex:1;}

        /* ── PROFILE HEADER ── */
        .profile-banner{
            background:linear-gradient(135deg,var(--teal),var(--teal-d));
            border-radius:16px;padding:24px 28px;
            display:flex;align-items:center;gap:20px;
            margin-bottom:24px;position:relative;overflow:hidden;
            animation:fadeUp 0.4s ease both;
        }
        .profile-banner::after{
            content:'👤';position:absolute;right:24px;top:50%;
            transform:translateY(-50%);font-size:64px;opacity:0.1;
        }
        .profile-av{
            width:64px;height:64px;border-radius:50%;
            background:rgba(255,255,255,0.2);border:3px solid rgba(255,255,255,0.4);
            display:grid;place-items:center;
            color:white;font-size:26px;font-weight:800;flex-shrink:0;
        }
        .profile-info strong{color:white;font-size:18px;font-weight:800;display:block;margin-bottom:4px;}
        .profile-info span{color:rgba(255,255,255,0.7);font-size:13px;}
        .profile-badge{
            display:inline-block;font-size:10px;font-weight:700;
            letter-spacing:0.08em;text-transform:uppercase;
            background:rgba(255,255,255,0.2);color:white;
            padding:3px 10px;border-radius:20px;margin-left:8px;
        }

        /* ── GRID ── */
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}

        /* ── PANEL ── */
        .panel{
            background:white;border:1.5px solid var(--border);
            border-radius:14px;overflow:hidden;
            animation:fadeUp 0.5s ease both;
        }
        .panel:nth-child(2){animation-delay:0.07s;}
        .panel:nth-child(3){animation-delay:0.14s;}
        @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

        .panel-header{
            padding:16px 20px;border-bottom:1px solid var(--border);
            display:flex;align-items:center;gap:10px;
        }
        .panel-header i{color:var(--teal);font-size:14px;}
        .panel-header h3{font-size:14px;font-weight:700;color:var(--dark);}
        .panel-body{padding:20px;}

        /* ── FORM ── */
        .form-grid{display:grid;gap:14px;}
        .form-grid.cols2{grid-template-columns:1fr 1fr;}
        .field label{display:block;font-size:11px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--mid);margin-bottom:7px;}
        .field label .req{color:var(--err);margin-left:2px;}
        .input-wrap{position:relative;}
        .input-wrap i.icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aac5c6;font-size:13px;pointer-events:none;}
        .input-wrap input{
            width:100%;padding:10px 12px 10px 36px;
            border:2px solid var(--border);border-radius:9px;
            font-family:inherit;font-size:13px;color:var(--dark);
            background:var(--light);outline:none;transition:border-color 0.2s;
        }
        .input-wrap input:focus{border-color:var(--teal);background:white;box-shadow:0 0 0 3px rgba(13,115,119,0.08);}
        .input-wrap input[readonly]{background:#f0f0f0;color:#999;cursor:not-allowed;}

        /* ── ALERT ── */
        .alert{padding:12px 16px;border-radius:10px;font-size:13px;display:flex;align-items:center;gap:9px;margin-bottom:20px;}
        .alert-success{background:#e8f7f3;border:1.5px solid #b6e8da;color:var(--suc);}
        .alert-danger {background:#fdf0ef;border:1.5px solid #f5c6c2;color:var(--err);}

        /* ── INFO ROWS ── */
        .info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px;}
        .info-row:last-child{border-bottom:none;}
        .info-row .lbl{color:var(--mid);font-size:12px;}
        .info-row .val{color:var(--dark);font-weight:600;font-size:13px;}
        .type-badge{display:inline-block;font-size:10px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:3px 10px;border-radius:20px;}
        .type-student{background:var(--teal-l);color:var(--teal);}
        .type-staff{background:rgba(52,152,219,0.1);color:#1a6fa8;}

        /* ── SAVE BTN ── */
        .save-btn{
            width:100%;padding:12px;background:var(--teal);border:none;
            border-radius:9px;color:white;font-family:inherit;
            font-size:14px;font-weight:700;cursor:pointer;
            display:flex;align-items:center;justify-content:center;gap:8px;
            transition:background 0.2s;margin-top:4px;
        }
        .save-btn:hover{background:var(--teal-d);}
        .save-btn.warn{background:#e67e22;}
        .save-btn.warn:hover{background:#d35400;}

        footer{padding:16px 32px;border-top:1px solid var(--border);font-size:11px;color:#aaa;display:flex;justify-content:space-between;}

        @media(max-width:900px){
            .sidebar{display:none;}.main{margin-left:0;}
            .grid2{grid-template-columns:1fr;}
            .form-grid.cols2{grid-template-columns:1fr;}
            .content{padding:20px 16px;}
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🔒</div>
        <div class="brand-text"><strong>Smart Locker</strong><span>Institution</span></div>
    </div>
    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name,0,1)); ?></div>
            <div>
                <strong><?php echo htmlspecialchars($user_name); ?></strong>
                <span class="user-badge <?php echo $user_type==='staff'?'badge-staff':'badge-student'; ?>"><?php echo ucfirst($user_type); ?></span>
            </div>
        </div>
    </div>
    <div class="nav-section">Menu</div>
    <a href="dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="my-locker.php" class="nav-item"><i class="fas fa-box"></i> My Lockers</a>
    <a href="scan-access.php" class="nav-item"><i class="fas fa-qrcode"></i> Scan Access</a>
    <a href="access-logs.php" class="nav-item"><i class="fas fa-history"></i> Activity Logs</a>
    <div class="nav-section">Account</div>
    <a href="profile.php" class="nav-item active"><i class="fas fa-user-circle"></i> My Profile</a>
    <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <div>
            <h1>My Profile</h1>
            <p>Manage your account information</p>
        </div>
        <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:white;border:2px solid var(--border);border-radius:9px;font-size:13px;font-weight:600;color:var(--mid);text-decoration:none;transition:all 0.2s;"
           onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border)'">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
    </div>

    <div class="content">

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message['type']; ?>">
            <i class="fas fa-<?php echo $message['type']==='success'?'check-circle':'exclamation-circle'; ?>"></i>
            <?php echo $message['text']; ?>
        </div>
        <?php endif; ?>

        <!-- Profile Banner -->
        <div class="profile-banner">
            <div class="profile-av"><?php echo strtoupper(substr($user['full_name']??'U',0,1)); ?></div>
            <div class="profile-info">
                <strong>
                    <?php echo htmlspecialchars($user['full_name']??'User'); ?>
                    <span class="profile-badge"><?php echo ucfirst($user['user_type']??'student'); ?></span>
                </strong>
                <span>
                    <i class="fas fa-envelope" style="margin-right:5px;opacity:0.7"></i><?php echo htmlspecialchars($user['email']??''); ?>
                    &nbsp;·&nbsp;
                    <i class="fas fa-university" style="margin-right:5px;opacity:0.7"></i><?php echo htmlspecialchars($user['institution']??'N/A'); ?>
                </span>
            </div>
        </div>

        <div class="grid2">

            <!-- LEFT: Edit Profile -->
            <div style="display:flex;flex-direction:column;gap:20px;">

                <!-- Profile Info Form -->
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-user-edit"></i>
                        <h3>Profile Information</h3>
                    </div>
                    <div class="panel-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-grid cols2">
                                <div class="field" style="grid-column:span 2">
                                    <label>Full Name <span class="req">*</span></label>
                                    <div class="input-wrap">
                                        <i class="fas fa-user icon"></i>
                                        <input type="text" name="full_name" required
                                               value="<?php echo htmlspecialchars($user['full_name']??''); ?>">
                                    </div>
                                </div>
                                <div class="field">
                                    <label>Matric / Staff ID <span class="req">*</span></label>
                                    <div class="input-wrap">
                                        <i class="fas fa-id-card icon"></i>
                                        <input type="text" name="user_id_number" required
                                               value="<?php echo htmlspecialchars($user['user_id_number']??''); ?>">
                                    </div>
                                </div>
                                <div class="field">
                                    <label>Phone Number</label>
                                    <div class="input-wrap">
                                        <i class="fas fa-phone icon"></i>
                                        <input type="tel" name="phone"
                                               placeholder="e.g. 0123456789"
                                               value="<?php echo htmlspecialchars($user['phone']??''); ?>">
                                    </div>
                                </div>
                                <div class="field" style="grid-column:span 2">
                                    <label>Email Address <span class="req">*</span></label>
                                    <div class="input-wrap">
                                        <i class="fas fa-envelope icon"></i>
                                        <input type="email" name="email" required
                                               value="<?php echo htmlspecialchars($user['email']??''); ?>">
                                    </div>
                                </div>
                                <div class="field" style="grid-column:span 2">
                                    <label>Institution</label>
                                    <div class="input-wrap">
                                        <i class="fas fa-university icon"></i>
                                        <input type="text" name="institution"
                                               placeholder="e.g. Matrikulasi Kedah"
                                               value="<?php echo htmlspecialchars($user['institution']??''); ?>">
                                    </div>
                                </div>
                                <div class="field" style="grid-column:span 2">
                                    <label>User Type</label>
                                    <div class="input-wrap">
                                        <i class="fas fa-tag icon"></i>
                                        <input type="text" readonly
                                               value="<?php echo ucfirst($user['user_type']??'student'); ?>">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="save-btn" style="margin-top:16px">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-lock"></i>
                        <h3>Change Password</h3>
                    </div>
                    <div class="panel-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_password">
                            <div class="form-grid">
                                <div class="field">
                                    <label>Current Password</label>
                                    <div class="input-wrap">
                                        <i class="fas fa-lock icon"></i>
                                        <input type="password" name="current_password" placeholder="Enter current password">
                                    </div>
                                </div>
                                <div class="field">
                                    <label>New Password</label>
                                    <div class="input-wrap">
                                        <i class="fas fa-key icon"></i>
                                        <input type="password" name="new_password" placeholder="Min. 6 characters">
                                    </div>
                                </div>
                                <div class="field">
                                    <label>Confirm New Password</label>
                                    <div class="input-wrap">
                                        <i class="fas fa-key icon"></i>
                                        <input type="password" name="confirm_password" id="confirmPw" placeholder="Repeat new password">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="save-btn warn" style="margin-top:16px">
                                <i class="fas fa-shield-alt"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Account Info -->
            <div style="display:flex;flex-direction:column;gap:20px;">
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Account Information</h3>
                    </div>
                    <div class="panel-body">
                        <div class="info-row">
                            <span class="lbl">User ID</span>
                            <span class="val">#<?php echo $user_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Matric / Staff ID</span>
                            <span class="val" style="font-family:monospace"><?php echo htmlspecialchars($user['user_id_number']??'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">User Type</span>
                            <span class="type-badge type-<?php echo $user['user_type']??'student'; ?>"><?php echo ucfirst($user['user_type']??'student'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Institution</span>
                            <span class="val"><?php echo htmlspecialchars($user['institution']??'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Phone</span>
                            <span class="val"><?php echo htmlspecialchars(!empty($user['phone'])?$user['phone']:'Not set'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Account Status</span>
                            <span style="font-size:11px;font-weight:700;background:rgba(10,124,99,0.1);color:#0a7c63;padding:3px 10px;border-radius:20px;">
                                <?php echo $user['is_active']?'Active':'Inactive'; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Joined</span>
                            <span class="val"><?php echo isset($user['created_at'])?date('d M Y',strtotime($user['created_at'])):'N/A'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Last Login</span>
                            <span class="val" style="font-size:12px"><?php echo !empty($user['last_login'])?date('d M Y, H:i',strtotime($user['last_login'])):'Never'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-bolt"></i>
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="panel-body" style="display:flex;flex-direction:column;gap:8px;">
                        <a href="my-locker.php" style="display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--teal-l);border:1.5px solid var(--border);border-radius:9px;color:var(--teal);font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s;"
                           onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border)'">
                            <i class="fas fa-box"></i> My Lockers
                        </a>
                        <a href="scan-access.php" style="display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--teal-l);border:1.5px solid var(--border);border-radius:9px;color:var(--teal);font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s;"
                           onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border)'">
                            <i class="fas fa-qrcode"></i> Scan Access
                        </a>
                        <a href="access-logs.php" style="display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--teal-l);border:1.5px solid var(--border);border-radius:9px;color:var(--teal);font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s;"
                           onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border)'">
                            <i class="fas fa-history"></i> Activity Logs
                        </a>
                        <a href="logout.php" style="display:flex;align-items:center;gap:10px;padding:11px 14px;background:rgba(231,76,60,0.06);border:1.5px solid rgba(231,76,60,0.15);border-radius:9px;color:#c0392b;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s;"
                           onmouseover="this.style.background='rgba(231,76,60,0.12)'" onmouseout="this.style.background='rgba(231,76,60,0.06)'">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- NFC Card Section -->
        <div class="nfc-section">
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-wifi"></i>
                    <h3>NFC Card / Key Fob</h3>
                </div>
                <div class="panel-body">

                    <?php if (!empty($user['nfc_uid'])): ?>
                    <div class="nfc-registered-box">
                        <div class="nfc-card-icon"><i class="fas fa-id-card"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:11px;color:var(--mid);margin-bottom:4px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Registered Card UID</div>
                            <div class="nfc-uid"><?php echo htmlspecialchars($user['nfc_uid']); ?></div>
                            <div style="font-size:12px;color:var(--mid);margin-top:6px;">
                                <i class="fas fa-clock" style="margin-right:4px;"></i>
                                Registered <?php echo isset($user['nfc_registered_at']) ? date('d M Y, H:i', strtotime($user['nfc_registered_at'])) : 'N/A'; ?>
                            </div>
                        </div>
                        <span class="badge-nfc-active"><i class="fas fa-check-circle" style="margin-right:4px;"></i>Active</span>
                    </div>
                    <?php if (!empty($nfc_device_id)): ?>
                    <button class="btn-nfc-replace" onclick="startNFCRegister()">
                        <i class="fas fa-sync"></i> Replace Card
                    </button>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="nfc-empty-box">
                        <div style="width:56px;height:56px;background:#e8f6f7;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                            <i class="fas fa-wifi" style="font-size:24px;color:var(--mid);"></i>
                        </div>
                        <div style="font-weight:700;margin-bottom:6px;font-size:14px;">No NFC Card Registered</div>
                        <div style="font-size:13px;color:var(--mid);">Register your NFC card or key fob to unlock lockers with a single tap</div>
                    </div>
                    <?php if (!empty($nfc_device_id)): ?>
                    <button class="btn-nfc-reg" onclick="startNFCRegister()">
                        <i class="fas fa-plus"></i> Register NFC Card
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (empty($nfc_device_id)): ?>
                    <div class="nfc-warn-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        No locker assigned yet. Get a locker assignment first before registering your NFC card.
                    </div>
                    <?php endif; ?>

                    <!-- Register Panel -->
                    <div id="nfcPanel" style="display:none;" class="mt-4" style="margin-top:24px;">
                        <hr style="border:none;border-top:1.5px solid var(--border);margin:20px 0 24px;">

                        <!-- Waiting -->
                        <div id="nfcWaiting" class="text-center" style="text-align:center;">
                            <div class="nfc-pulse-wrap">
                                <div class="nfc-ring"></div>
                                <div class="nfc-ring"></div>
                                <div class="nfc-ring"></div>
                                <i class="fas fa-wifi nfc-center-icon"></i>
                            </div>
                            <div style="font-weight:700;font-size:14px;margin-bottom:6px;">Tap your NFC card or key fob</div>
                            <div style="font-size:13px;color:var(--mid);margin-bottom:20px;">Hold your card near the NFC reader at any of your lockers</div>
                            <div class="timer-wrap">
                                <div class="timer-label">
                                    <span>Waiting for tap...</span>
                                    <span id="nfcCountdown">60</span>s
                                </div>
                                <div class="timer-bg"><div class="timer-fill" id="nfcTimerBar"></div></div>
                            </div>
                            <button class="btn-nfc-cancel" onclick="cancelNFC()"><i class="fas fa-times" style="margin-right:4px;"></i>Cancel</button>
                        </div>

                        <!-- Success -->
                        <div id="nfcDone" style="display:none;text-align:center;">
                            <div style="width:68px;height:68px;background:#e8f7f3;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                <i class="fas fa-check-circle" style="font-size:28px;color:#0a7c63;"></i>
                            </div>
                            <div style="font-weight:700;font-size:14px;margin-bottom:6px;">NFC Card Registered!</div>
                            <div style="font-size:13px;color:var(--mid);margin-bottom:18px;">
                                UID: <span id="nfcNewUID" style="font-family:monospace;font-weight:700;color:var(--dark);letter-spacing:2px;"></span>
                            </div>
                            <button class="btn-nfc-success" onclick="location.reload()">
                                <i class="fas fa-refresh"></i> Refresh Page
                            </button>
                        </div>

                        <!-- Failed -->
                        <div id="nfcFailed" style="display:none;text-align:center;">
                            <div style="width:68px;height:68px;background:#fdf0ef;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                <i class="fas fa-times-circle" style="font-size:28px;color:var(--err);"></i>
                            </div>
                            <div style="font-weight:700;font-size:14px;margin-bottom:6px;color:var(--err);" id="nfcFailMsg">Registration failed.</div>
                            <div style="font-size:13px;color:var(--mid);margin-bottom:18px;">Please try again.</div>
                            <button class="btn-nfc-reg" onclick="startNFCRegister()">
                                <i class="fas fa-redo"></i> Try Again
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <footer>
        <span>© 2026 Smart Locker System — Institution Version</span>
        <span><?php echo htmlspecialchars($user_name); ?> · <?php echo date('d M Y, H:i'); ?></span>
    </footer>
</div>


<script>
const NFC_DEVICE_ID = '<?php echo addslashes($nfc_device_id); ?>';
const NFC_TIMEOUT   = 60;
let nfcInt = null, nfcTimer = null, nfcLeft = NFC_TIMEOUT;

function startNFCRegister() {
    if (!NFC_DEVICE_ID) { alert('Tiada ESP32 device dijumpai. Pastikan locker dah dikonfigurasi.'); return; }

    fetch('api/nfc_set_mode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: 1, device_id: NFC_DEVICE_ID })
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { alert('Gagal set register mode: ' + d.message); return; }

        document.getElementById('nfcPanel').style.display   = 'block';
        document.getElementById('nfcWaiting').style.display = 'block';
        document.getElementById('nfcDone').style.display    = 'none';
        document.getElementById('nfcFailed').style.display  = 'none';
        nfcLeft = NFC_TIMEOUT;
        document.getElementById('nfcCountdown').textContent = nfcLeft;
        document.getElementById('nfcTimerBar').style.width  = '100%';
        document.getElementById('nfcPanel').scrollIntoView({ behavior: 'smooth' });

        nfcTimer = setInterval(() => {
            nfcLeft--;
            document.getElementById('nfcCountdown').textContent = nfcLeft;
            document.getElementById('nfcTimerBar').style.width  = (nfcLeft / NFC_TIMEOUT * 100) + '%';
            if (nfcLeft <= 0) {
                stopNFC();
                showFail('Timed out. Please try again.');
            }
        }, 1000);

        nfcInt = setInterval(() => {
            fetch('api/nfc_register_self_poll.php?device_id=' + encodeURIComponent(NFC_DEVICE_ID))
            .then(r => r.json())
            .then(d => {
                if (d.found) {
                    stopNFC();
                    if (d.success) {
                        document.getElementById('nfcWaiting').style.display = 'none';
                        document.getElementById('nfcDone').style.display    = 'block';
                        document.getElementById('nfcNewUID').textContent    = d.uid;
                    } else { showFail(d.message); }
                }
            })
            .catch(() => {});
        }, 2000);
    })
    .catch(() => alert('Network error. Cuba lagi.'));
}

function stopNFC() {
    if (nfcInt)   { clearInterval(nfcInt);   nfcInt   = null; }
    if (nfcTimer) { clearInterval(nfcTimer); nfcTimer = null; }

    fetch('api/nfc_set_mode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: 0, device_id: NFC_DEVICE_ID })
    }).catch(() => {});
}

function cancelNFC() {
    stopNFC();
    document.getElementById('nfcPanel').style.display = 'none';
}

function showFail(msg) {
    document.getElementById('nfcWaiting').style.display = 'none';
    document.getElementById('nfcFailed').style.display  = 'block';
    document.getElementById('nfcFailMsg').textContent   = msg;
}
</script>
</body>
</html>