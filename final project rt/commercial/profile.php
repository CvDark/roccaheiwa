<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get NFC device_id dari locker user
$nfc_device_id = '';
try {
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
} catch(Exception $e) {}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $full_name      = trim($_POST['full_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $user_id_number = trim($_POST['user_id_number'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');

        if (empty($full_name) || empty($email) || empty($user_id_number)) {
            $message = ['type'=>'danger', 'text'=>'Name, Email, and ID Number are required.'];
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $user_id]);
            if ($chk->fetch()) {
                $message = ['type'=>'danger', 'text'=>'Email already taken by another user.'];
            } else {
                $pdo->prepare("UPDATE users SET full_name=?, email=?, user_id_number=?, phone=? WHERE id=?")
                    ->execute([$full_name, $email, $user_id_number, $phone, $user_id]);
                $_SESSION['user_name']      = $full_name;
                $_SESSION['user_id_number'] = $user_id_number;
                $_SESSION['user_email']     = $email;
                $user['full_name']      = $full_name;
                $user['email']          = $email;
                $user['user_id_number'] = $user_id_number;
                $user['phone']          = $phone;
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
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$new_hash, $user_id]);
            $message = ['type'=>'success', 'text'=>'Password updated successfully!'];
        }
    }
}

// Avatar initials
$name_parts = explode(' ', trim($user['full_name'] ?? 'U'));
$initials   = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Smart Locker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary:#667eea; --primary-d:#5a6fd6; --purple:#764ba2;
            --success:#22c55e; --danger:#ef4444; --warning:#f59e0b;
            --bg:#f4f6fb; --card:#fff; --border:#e8ecf4;
            --text:#1e2535; --muted:#7b8599; --radius:16px;
        }
        *{box-sizing:border-box;margin:0;padding:0;}
        body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;min-height:100vh;}

        /* Navbar */
        .topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 1px 8px rgba(0,0,0,.06);}
        .topbar-brand{font-weight:700;font-size:1.1rem;color:var(--text);text-decoration:none;display:flex;align-items:center;gap:10px;}
        .topbar-brand i{color:var(--primary);}
        .btn-back{display:flex;align-items:center;gap:8px;padding:8px 18px;border-radius:10px;background:var(--bg);border:1px solid var(--border);color:var(--text);font-size:.875rem;font-weight:600;text-decoration:none;transition:all .2s;}
        .btn-back:hover{background:var(--primary);color:#fff;border-color:var(--primary);}

        /* Hero */
        .profile-hero{background:linear-gradient(135deg,var(--primary) 0%,var(--purple) 100%);padding:40px 0 36px;color:#fff;}
        .avatar{width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:#fff;flex-shrink:0;}
        .hero-name{font-size:1.5rem;font-weight:800;margin-bottom:4px;}
        .hero-meta{opacity:.8;font-size:.9rem;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
        .hero-badge{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:3px 12px;font-size:.8rem;font-weight:600;}

        /* Cards */
        .pcard{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:hidden;}
        .pcard-header{padding:18px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
        .pcard-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
        .icon-blue{background:#eff2ff;color:var(--primary);}
        .icon-purple{background:#f5f0ff;color:var(--purple);}
        .icon-amber{background:#fffbeb;color:var(--warning);}
        .icon-green{background:#f0fdf4;color:var(--success);}
        .pcard-title{font-weight:700;font-size:.95rem;color:var(--text);}
        .pcard-subtitle{font-size:.8rem;color:var(--muted);}
        .pcard-body{padding:24px;}

        /* Form */
        .form-label{font-weight:600;font-size:.82rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block;}
        .form-control{border:1.5px solid var(--border);border-radius:10px;padding:11px 14px;font-size:.95rem;color:var(--text);background:#fafbff;transition:all .2s;width:100%;}
        .form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(102,126,234,.12);background:#fff;outline:none;}
        .input-group{display:flex;}
        .input-group-text{background:#f4f6fb;border:1.5px solid var(--border);border-right:none;border-radius:10px 0 0 10px;padding:11px 14px;color:var(--muted);}
        .input-group .form-control{border-radius:0 10px 10px 0;border-left:none;}
        .input-group .form-control:focus{border-left:1.5px solid var(--primary);}

        /* Buttons */
        .btn-save{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:#fff;border:none;border-radius:10px;padding:11px 24px;font-weight:700;font-size:.9rem;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;}
        .btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(102,126,234,.4);}
        .btn-pw{background:linear-gradient(135deg,var(--warning),#d97706);color:#fff;border:none;border-radius:10px;padding:11px 24px;font-weight:700;font-size:.9rem;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;width:100%;}
        .btn-pw:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(245,158,11,.35);}
        .btn-nfc-reg{background:linear-gradient(135deg,var(--primary),var(--purple));color:#fff;border:none;border-radius:10px;padding:11px 24px;font-weight:700;font-size:.9rem;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;}
        .btn-nfc-reg:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(102,126,234,.4);}
        .btn-replace{background:transparent;color:var(--danger);border:1.5px solid var(--danger);border-radius:10px;padding:9px 20px;font-weight:600;font-size:.875rem;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;}
        .btn-replace:hover{background:var(--danger);color:#fff;}
        .btn-cancel{background:var(--bg);color:var(--muted);border:1.5px solid var(--border);border-radius:8px;padding:8px 18px;font-weight:600;font-size:.85rem;cursor:pointer;transition:all .2s;}
        .btn-cancel:hover{border-color:var(--danger);color:var(--danger);}
        .btn-success{background:linear-gradient(135deg,var(--success),#16a34a);color:#fff;border:none;border-radius:10px;padding:9px 20px;font-weight:600;font-size:.875rem;cursor:pointer;display:inline-flex;align-items:center;gap:8px;}

        /* Password toggle */
        .pw-wrap{position:relative;}
        .pw-wrap .form-control{padding-right:44px;}
        .pw-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:15px;}
        .pw-eye:hover{color:var(--primary);}

        /* Info rows */
        .info-item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border);font-size:.9rem;}
        .info-item:last-child{border-bottom:none;padding-bottom:0;}
        .info-label{color:var(--muted);font-size:.85rem;}
        .info-value{font-weight:600;}

        /* NFC */
        .nfc-registered-box{background:linear-gradient(135deg,#eff2ff,#f5f0ff);border:1.5px solid #d4d9f8;border-radius:12px;padding:20px;display:flex;align-items:center;gap:16px;margin-bottom:16px;flex-wrap:wrap;}
        .nfc-card-icon{width:52px;height:52px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--purple));display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;}
        .nfc-uid{font-family:'Courier New',monospace;font-size:1rem;font-weight:700;letter-spacing:3px;color:var(--text);background:rgba(255,255,255,.7);padding:6px 14px;border-radius:8px;display:inline-block;margin-top:4px;}
        .badge-active{background:#f0fdf4;color:var(--success);border:1px solid #bbf7d0;border-radius:20px;padding:4px 14px;font-size:.8rem;font-weight:700;white-space:nowrap;}
        .nfc-empty-box{background:#fafbff;border:2px dashed var(--border);border-radius:12px;padding:32px 20px;text-align:center;margin-bottom:20px;}

        /* NFC pulse */
        .nfc-pulse-wrap{position:relative;width:90px;height:90px;margin:0 auto 20px;}
        .nfc-ring{position:absolute;border-radius:50%;border:2px solid var(--primary);top:50%;left:50%;transform:translate(-50%,-50%) scale(0);opacity:0;animation:nfcRing 2s ease-out infinite;}
        .nfc-ring:nth-child(1){width:28px;height:28px;animation-delay:0s}
        .nfc-ring:nth-child(2){width:56px;height:56px;animation-delay:.5s}
        .nfc-ring:nth-child(3){width:84px;height:84px;animation-delay:1s}
        @keyframes nfcRing{0%{transform:translate(-50%,-50%) scale(0);opacity:.9}100%{transform:translate(-50%,-50%) scale(1);opacity:0}}
        .nfc-center-icon{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:28px;color:var(--primary);animation:nfcGlow 1.5s ease-in-out infinite;}
        @keyframes nfcGlow{0%,100%{filter:drop-shadow(0 0 5px var(--primary))}50%{filter:drop-shadow(0 0 16px var(--primary))}}

        /* Timer */
        .timer-wrap{max-width:240px;margin:0 auto 20px;}
        .timer-label{display:flex;justify-content:space-between;font-size:.8rem;color:var(--muted);margin-bottom:6px;}
        .timer-bg{height:5px;background:var(--border);border-radius:3px;overflow:hidden;}
        .timer-fill{height:100%;background:linear-gradient(90deg,var(--primary),var(--purple));border-radius:3px;width:100%;transition:width 1s linear;}

        /* Alert */
        .my-alert{border-radius:12px;padding:14px 18px;margin-bottom:24px;display:flex;align-items:center;gap:12px;font-weight:500;font-size:.9rem;}
        .alert-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;}
        .alert-err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
        .alert-warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="topbar">
    <a href="dashboard.php" class="topbar-brand"><i class="fas fa-lock"></i> Smart Locker</a>
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
</nav>

<!-- Hero -->
<div class="profile-hero">
    <div class="container">
        <div class="d-flex align-items-center gap-4">
            <div class="avatar"><?php echo $initials; ?></div>
            <div>
                <div class="hero-name"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></div>
                <div class="hero-meta">
                    <span><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                    <span class="hero-badge"><?php echo ucfirst($user['user_type'] ?? 'user'); ?></span>
                    <?php if (!empty($user['nfc_uid'])): ?>
                    <span class="hero-badge"><i class="fas fa-wifi me-1"></i>NFC Active</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-4 pb-5" style="max-width:1100px;">

    <!-- Alert -->
    <?php if ($message): ?>
    <div class="my-alert <?php echo $message['type']==='success' ? 'alert-ok' : 'alert-err'; ?>">
        <i class="fas fa-<?php echo $message['type']==='success' ? 'check-circle' : 'exclamation-circle'; ?> fa-lg"></i>
        <?php echo htmlspecialchars($message['text']); ?>
    </div>
    <?php endif; ?>

    <!-- Row 1 -->
    <div class="row g-4">

        <!-- Profile Info -->
        <div class="col-lg-7">
            <div class="pcard h-100">
                <div class="pcard-header">
                    <div class="pcard-icon icon-blue"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="pcard-title">Profile Information</div>
                        <div class="pcard-subtitle">Update your personal details</div>
                    </div>
                </div>
                <div class="pcard-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required
                                    placeholder="Your full name"
                                    value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ID Number *</label>
                                <input type="text" name="user_id_number" class="form-control" required
                                    placeholder="e.g. MC2516203265"
                                    value="<?php echo htmlspecialchars($user['user_id_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" name="phone" class="form-control"
                                        placeholder="0123456789"
                                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required
                                        placeholder="your@email.com"
                                        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-12 pt-1">
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="col-lg-5 d-flex flex-column gap-4">

            <!-- Account Info -->
            <div class="pcard">
                <div class="pcard-header">
                    <div class="pcard-icon icon-purple"><i class="fas fa-info-circle"></i></div>
                    <div>
                        <div class="pcard-title">Account Info</div>
                        <div class="pcard-subtitle">Your account details</div>
                    </div>
                </div>
                <div class="pcard-body">
                    <div class="info-item">
                        <span class="info-label">User ID</span>
                        <span class="info-value">#<?php echo $user_id; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Account Type</span>
                        <span style="background:#eff2ff;color:var(--primary);border-radius:20px;padding:3px 14px;font-size:.82rem;font-weight:700;">
                            <?php echo ucfirst($user['user_type'] ?? 'user'); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Institution</span>
                        <span class="info-value"><?php echo htmlspecialchars(!empty($user['institution']) ? $user['institution'] : '—'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars(!empty($user['phone']) ? $user['phone'] : '—'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Member Since</span>
                        <span class="info-value"><?php echo isset($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : 'N/A'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="pcard">
                <div class="pcard-header">
                    <div class="pcard-icon icon-amber"><i class="fas fa-lock"></i></div>
                    <div>
                        <div class="pcard-title">Change Password</div>
                        <div class="pcard-subtitle">Keep your account secure</div>
                    </div>
                </div>
                <div class="pcard-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_password">
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <label class="form-label">Current Password</label>
                                <div class="pw-wrap">
                                    <input type="password" name="current_password" id="pw1" class="form-control" placeholder="Enter current password">
                                    <button type="button" class="pw-eye" onclick="togglePw('pw1',this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">New Password</label>
                                <div class="pw-wrap">
                                    <input type="password" name="new_password" id="pw2" class="form-control" placeholder="Min. 6 characters">
                                    <button type="button" class="pw-eye" onclick="togglePw('pw2',this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Confirm New Password</label>
                                <div class="pw-wrap">
                                    <input type="password" name="confirm_password" id="pw3" class="form-control" placeholder="Repeat new password">
                                    <button type="button" class="pw-eye" onclick="togglePw('pw3',this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <button type="submit" class="btn-pw">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: NFC Card full width -->
    <div class="mt-4">
        <div class="pcard">
            <div class="pcard-header">
                <div class="pcard-icon icon-green"><i class="fas fa-wifi"></i></div>
                <div>
                    <div class="pcard-title">NFC Card / Key Fob</div>
                    <div class="pcard-subtitle">Tap to unlock your lockers instantly</div>
                </div>
            </div>
            <div class="pcard-body">

                <?php if (!empty($user['nfc_uid'])): ?>
                <div class="nfc-registered-box">
                    <div class="nfc-card-icon"><i class="fas fa-id-card"></i></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.8rem;color:var(--muted);margin-bottom:4px;">Registered Card UID</div>
                        <div class="nfc-uid"><?php echo htmlspecialchars($user['nfc_uid']); ?></div>
                        <div style="font-size:.78rem;color:var(--muted);margin-top:6px;">
                            <i class="fas fa-clock me-1"></i>
                            Registered <?php echo isset($user['nfc_registered_at']) ? date('d M Y, H:i', strtotime($user['nfc_registered_at'])) : 'N/A'; ?>
                        </div>
                    </div>
                    <span class="badge-active"><i class="fas fa-check-circle me-1"></i>Active</span>
                </div>
                <?php if (!empty($nfc_device_id)): ?>
                <button class="btn-replace" onclick="startNFCRegister()">
                    <i class="fas fa-sync"></i> Replace Card
                </button>
                <?php endif; ?>

                <?php else: ?>
                <div class="nfc-empty-box">
                    <div style="width:60px;height:60px;background:#f0f2fa;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                        <i class="fas fa-wifi" style="font-size:26px;color:var(--muted);"></i>
                    </div>
                    <div style="font-weight:700;margin-bottom:6px;font-size:.95rem;">No NFC Card Registered</div>
                    <div style="font-size:.875rem;color:var(--muted);">Register your NFC card or key fob to unlock lockers with a single tap</div>
                </div>
                <?php if (!empty($nfc_device_id)): ?>
                <button class="btn-nfc-reg" onclick="startNFCRegister()">
                    <i class="fas fa-plus"></i> Register NFC Card
                </button>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (empty($nfc_device_id)): ?>
                <div class="my-alert alert-warn mt-3" style="margin-bottom:0;">
                    <i class="fas fa-exclamation-triangle fa-lg"></i>
                    No locker assigned yet. Get a locker assignment first before registering your NFC card.
                </div>
                <?php endif; ?>

                <!-- Register Panel -->
                <div id="nfcPanel" style="display:none;" class="mt-4">
                    <hr style="border-color:var(--border);margin-bottom:24px;">

                    <!-- Waiting -->
                    <div id="nfcWaiting" class="text-center">
                        <div class="nfc-pulse-wrap">
                            <div class="nfc-ring"></div>
                            <div class="nfc-ring"></div>
                            <div class="nfc-ring"></div>
                            <i class="fas fa-wifi nfc-center-icon"></i>
                        </div>
                        <div style="font-weight:700;font-size:1rem;margin-bottom:6px;">Tap your NFC card or key fob</div>
                        <div style="font-size:.875rem;color:var(--muted);margin-bottom:20px;">Hold your card near the NFC reader at any of your lockers</div>
                        <div class="timer-wrap">
                            <div class="timer-label">
                                <span>Waiting for tap...</span>
                                <span id="nfcCountdown">60</span>s
                            </div>
                            <div class="timer-bg"><div class="timer-fill" id="nfcTimerBar"></div></div>
                        </div>
                        <button class="btn-cancel" onclick="cancelNFC()"><i class="fas fa-times me-1"></i>Cancel</button>
                    </div>

                    <!-- Success -->
                    <div id="nfcDone" style="display:none;" class="text-center">
                        <div style="width:68px;height:68px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                            <i class="fas fa-check-circle fa-2x" style="color:var(--success);"></i>
                        </div>
                        <div style="font-weight:700;font-size:1rem;margin-bottom:6px;">NFC Card Registered!</div>
                        <div style="font-size:.875rem;color:var(--muted);margin-bottom:18px;">
                            UID: <span id="nfcNewUID" style="font-family:monospace;font-weight:700;color:var(--text);letter-spacing:2px;"></span>
                        </div>
                        <button class="btn-success" onclick="location.reload()">
                            <i class="fas fa-refresh"></i> Refresh Page
                        </button>
                    </div>

                    <!-- Failed -->
                    <div id="nfcFailed" style="display:none;" class="text-center">
                        <div style="width:68px;height:68px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                            <i class="fas fa-times-circle fa-2x" style="color:var(--danger);"></i>
                        </div>
                        <div style="font-weight:700;font-size:1rem;margin-bottom:6px;color:var(--danger);" id="nfcFailMsg">Registration failed.</div>
                        <div style="font-size:.875rem;color:var(--muted);margin-bottom:18px;">Please try again.</div>
                        <button class="btn-nfc-reg" onclick="startNFCRegister()">
                            <i class="fas fa-redo"></i> Try Again
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- Footer -->
<footer style="background:#1e2535;color:rgba(255,255,255,.55);padding:20px 0;margin-top:8px;">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:.85rem;">
        <span><i class="fas fa-lock me-2" style="color:var(--primary);"></i>Smart Locker System</span>
        <span><?php echo htmlspecialchars($user_name); ?> &nbsp;·&nbsp; <?php echo date('d M Y, H:i'); ?></span>
    </div>
</footer>

<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.classList.toggle('fa-eye');
    ico.classList.toggle('fa-eye-slash');
}

const NFC_DEVICE_ID = '<?php echo addslashes($nfc_device_id); ?>';
const NFC_TIMEOUT   = 60;
let nfcInt = null, nfcTimer = null, nfcLeft = NFC_TIMEOUT;

function startNFCRegister() {
    if (!NFC_DEVICE_ID) { alert('No locker device available.'); return; }
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
        if (nfcLeft <= 0) { stopNFC(); showFail('Timed out. Please try again.'); }
    }, 1000);

    nfcInt = setInterval(() => {
        fetch('api/nfc_register_poll.php?device_id=' + encodeURIComponent(NFC_DEVICE_ID))
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
}

function stopNFC() {
    if (nfcInt)   { clearInterval(nfcInt);   nfcInt   = null; }
    if (nfcTimer) { clearInterval(nfcTimer); nfcTimer = null; }
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