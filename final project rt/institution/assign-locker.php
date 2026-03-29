<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'student';

if (!$user_id) { session_destroy(); redirect('login.php'); }
$chkUser = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
$chkUser->execute([$user_id]);
if (!$chkUser->fetch()) { session_destroy(); redirect('login.php?err=session_invalid'); }

$message  = '';
$msg_type = '';

// ── HANDLE REMOVE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_access') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT ula.locker_id FROM user_locker_assignments ula WHERE ula.id=? AND ula.user_id=?");
        $stmt->execute([$assignment_id, $user_id]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("UPDATE user_locker_assignments SET is_active=0 WHERE id=?")->execute([$assignment_id]);
            $pdo->prepare("UPDATE lockers SET status='available' WHERE id=?")->execute([$row['locker_id']]);
            $message = 'Locker access removed successfully.'; $msg_type = 'success';
        }
    } catch (Exception $e) { $message = 'Error: ' . $e->getMessage(); $msg_type = 'error'; }
}

// ── MY LOCKERS ──
try {
    $stmt = $pdo->prepare("
        SELECT ula.id as assignment_id, l.id as locker_id,
               COALESCE(ula.custom_name, l.unique_code) AS name,
               COALESCE(ula.custom_location, '') AS location,
               l.unique_code, l.device_id, l.status, ula.assigned_at, ula.key_value
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.user_id=? AND ula.is_active=1
        ORDER BY ula.assigned_at DESC
    ");
    $stmt->execute([$user_id]);
    $my_lockers = $stmt->fetchAll();
} catch (Exception $e) { $my_lockers = []; }

// Count available lockers
$avail_count = $pdo->query("SELECT COUNT(*) FROM lockers WHERE status='available'")->fetchColumn();

// Semak NFC card registered
$nfc_registered = false;
try {
    $chkNfc = $pdo->prepare("SELECT nfc_uid FROM users WHERE id = ? AND nfc_uid IS NOT NULL LIMIT 1");
    $chkNfc->execute([$user_id]);
    $nfc_registered = (bool)$chkNfc->fetch();
} catch(Exception $e) {}
?><!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assign Locker — Smart Locker Institution</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--teal:#0d7377;--teal-d:#085c60;--teal-l:#e8f6f7;--dark:#0f1f20;--mid:#3d5c5e;--light:#f4f9f9;--border:#d0e8e9;--sw:240px;--gold:#f0a500;}
body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--light);color:var(--dark);display:flex;min-height:100vh;}
/* SIDEBAR */
.sidebar{width:var(--sw);background:#0f1f20;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50;}
.sb-brand{padding:22px 20px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:10px;}
.sb-icon{width:36px;height:36px;background:var(--teal);border-radius:9px;display:grid;place-items:center;font-size:16px;}
.sb-brand strong{display:block;color:#fff;font-size:13px;font-weight:700;}
.sb-brand span{font-size:10px;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase;}
.sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:10px;}
.sb-av{width:38px;height:38px;border-radius:50%;background:var(--teal);display:grid;place-items:center;color:#fff;font-size:15px;font-weight:700;flex-shrink:0;}
.sb-user strong{color:#fff;font-size:13px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;}
.badge{display:inline-block;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:2px 8px;border-radius:20px;margin-top:3px;}
.b-st{background:rgba(13,115,119,.3);color:#6de8ec;}.b-sf{background:rgba(20,169,138,.3);color:#6de8c0;}
.nav-sec{padding:16px 12px 8px;font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.25);}
.nav-a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:9px;margin:2px 8px;color:rgba(255,255,255,.55);font-size:13px;font-weight:500;text-decoration:none;transition:all .2s;}
.nav-a i{width:18px;text-align:center;font-size:13px;}
.nav-a:hover{background:rgba(255,255,255,.07);color:#fff;}.nav-a.on{background:var(--teal);color:#fff;}
.sb-foot{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,.07);}
.logout{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;color:rgba(255,255,255,.45);font-size:13px;text-decoration:none;transition:all .2s;}
.logout:hover{background:rgba(220,50,50,.15);color:#ff7b7b;}
/* MAIN */
.main{margin-left:var(--sw);flex:1;display:flex;flex-direction:column;}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:16px 32px;position:sticky;top:0;z-index:40;}
.topbar h1{font-size:18px;font-weight:800;}.topbar p{font-size:12px;color:var(--mid);margin-top:2px;}
.content{padding:28px 32px;flex:1;}
.grid2{display:grid;grid-template-columns:1fr 360px;gap:22px;align-items:start;}
.card{background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:18px;}
.ch{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
.ch h2{font-size:15px;font-weight:800;flex:1;}
.ch-ic{width:32px;height:32px;border-radius:8px;background:var(--teal-l);display:grid;place-items:center;color:var(--teal);font-size:13px;}
.cb{padding:20px;}
/* QR SCAN BOX */
.qr-box{background:#0f1f20;border-radius:14px;overflow:hidden;position:relative;height:240px;margin-bottom:14px;cursor:pointer;}
.qr-box video{width:100%;height:100%;object-fit:cover;display:block;}
.qr-ph{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:rgba(255,255,255,.4);gap:10px;transition:all .2s;}
.qr-ph:hover .qr-ph-ic{transform:scale(1.1);}
.qr-ph-ic{font-size:44px;transition:transform .2s;}
.qr-ph p{font-size:13px;font-weight:700;color:rgba(255,255,255,.6);}
.qr-ph small{font-size:11px;color:rgba(255,255,255,.3);}
.qr-frame{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:160px;height:160px;border:2.5px solid var(--teal);border-radius:10px;box-shadow:0 0 0 9999px rgba(0,0,0,.55);}
.qr-corner{position:absolute;width:20px;height:20px;border-color:white;border-style:solid;}
.qr-corner.tl{top:-1px;left:-1px;border-width:3px 0 0 3px;border-radius:4px 0 0 0;}
.qr-corner.tr{top:-1px;right:-1px;border-width:3px 3px 0 0;border-radius:0 4px 0 0;}
.qr-corner.bl{bottom:-1px;left:-1px;border-width:0 0 3px 3px;border-radius:0 0 0 4px;}
.qr-corner.br{bottom:-1px;right:-1px;border-width:0 3px 3px 0;border-radius:0 0 4px 0;}
.scan-line{position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--teal),transparent);animation:scanl 1.8s linear infinite;}
@keyframes scanl{0%{top:5%}100%{top:95%}}
.scan-st{position:absolute;bottom:8px;left:0;right:0;text-align:center;font-size:11px;font-weight:700;color:white;text-shadow:0 1px 4px rgba(0,0,0,.8);}
/* DETECTED LOCKER BANNER */
.detected-banner{background:linear-gradient(135deg,#e8f7f3,#d0f0e8);border:2px solid #b6e8da;border-radius:12px;padding:14px 16px;margin-bottom:16px;display:none;}
.detected-banner.show{display:flex;align-items:center;gap:12px;}
.det-ic{width:42px;height:42px;background:var(--teal);border-radius:10px;display:grid;place-items:center;color:white;font-size:18px;flex-shrink:0;}
.det-code{font-size:16px;font-weight:800;color:var(--teal);}
.det-sub{font-size:12px;color:#3d7a60;margin-top:2px;}
.det-change{font-size:11px;color:var(--teal);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-top:5px;cursor:pointer;background:none;border:none;font-family:inherit;padding:0;}
.det-change:hover{text-decoration:underline;}
/* KEY SECTION */
.key-section{background:#f8fffe;border:2px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px;display:none;}
.key-section.show{display:block;}
.key-title{font-size:12px;font-weight:800;color:var(--teal);display:flex;align-items:center;gap:7px;margin-bottom:12px;}
.kinpw{position:relative;}
.kinpw input{width:100%;padding:12px 44px 12px 14px;border:2px solid var(--border);border-radius:9px;font-family:monospace;font-size:15px;font-weight:800;letter-spacing:.12em;color:var(--dark);background:#fff;outline:none;transition:all .2s;}
.kinpw input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,115,119,.08);}
.kinpw input.ok{border-color:#0a7c63;background:#e8f7f3;}
.kic{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:16px;color:#aac5c6;transition:color .2s;}
.key-hint{font-size:11px;color:#888;margin-top:6px;display:flex;align-items:center;gap:5px;}
/* EXTRA FIELDS */
.flbl{font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--mid);display:block;margin-bottom:7px;}
.finp{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;background:var(--light);outline:none;transition:border-color .2s;margin-bottom:14px;}
.finp:focus{border-color:var(--teal);background:#fff;}
/* BTNS */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 18px;border-radius:9px;font-family:inherit;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all .2s;width:100%;}
.btn-p{background:var(--teal);color:#fff;}.btn-p:hover{background:var(--teal-d);}.btn-p:disabled{background:#aac5c6;cursor:not-allowed;}
.btn-o{background:#fff;color:var(--mid);border:2px solid var(--border);}.btn-o:hover{border-color:var(--teal);color:var(--teal);}
.btn-d{background:#e74c3c;color:#fff;}.btn-d:hover{background:#c0392b;}
.btn-scan{width:100%;padding:11px;background:var(--teal-l);border:2px solid var(--border);border-radius:10px;color:var(--teal);font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:10px;transition:all .2s;}
.btn-scan:hover{background:var(--teal);color:white;border-color:var(--teal);}
.btn-stop{background:#fdf0ef;border-color:#f5c6c2;color:#c0392b;}
.btn-stop:hover{background:#c0392b;color:white;border-color:#c0392b;}
.brow{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
/* STEP INDICATOR */
.steps{display:flex;align-items:center;gap:4px;margin-bottom:18px;}
.step-item{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:#aac5c6;}
.step-num{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-size:10px;font-weight:800;background:var(--teal-l);color:#aac5c6;flex-shrink:0;}
.step-item.act .step-num{background:var(--teal);color:#fff;}
.step-item.act{color:var(--teal);}
.step-item.done .step-num{background:#0a7c63;color:#fff;}
.step-item.done{color:#0a7c63;}
.step-div{flex:1;height:2px;background:var(--border);max-width:30px;}
.step-div.done{background:var(--teal);}
/* MY LOCKERS */
.mlcard{border:2px solid var(--border);border-radius:13px;padding:16px;margin-bottom:12px;cursor:pointer;transition:all .2s;position:relative;display:flex;align-items:center;gap:14px;}
.mlcard:hover{border-color:#e74c3c;box-shadow:0 4px 16px rgba(231,76,60,.1);}
.mlcard-ic{width:44px;height:44px;background:var(--teal-l);border-radius:11px;display:grid;place-items:center;color:var(--teal);font-size:20px;flex-shrink:0;}
.mlcard-name{font-size:14px;font-weight:800;}
.mlcard-sub{font-size:11px;color:var(--mid);margin-top:2px;}
.mlcard-arrow{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#e74c3c;font-size:14px;}
.access-hint{font-size:11px;color:var(--mid);text-align:center;padding:8px;background:var(--teal-l);border-radius:8px;margin-top:4px;}
/* OR DIVIDER */
.or-div{display:flex;align-items:center;gap:10px;color:#aac5c6;font-size:11px;font-weight:700;margin:12px 0;}
.or-div::before,.or-div::after{content:'';flex:1;height:1px;background:var(--border);}
/* MSG */
.msg{padding:12px 16px;border-radius:10px;font-size:13px;display:flex;align-items:flex-start;gap:9px;margin-bottom:18px;line-height:1.5;}
.msg.success{background:#e8f7f3;border:1.5px solid #b6e8da;color:#0a7c63;}
.msg.error{background:#fdf0ef;border:1.5px solid #f5c6c2;color:#c0392b;}
.empty{text-align:center;padding:32px 16px;color:var(--mid);}
.empty i{font-size:36px;opacity:.15;display:block;margin-bottom:10px;}
.avbadge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;background:#e8f7f3;color:#0a7c63;padding:3px 10px;border-radius:20px;}
/* MODAL */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:center;justify-content:center;padding:16px;}
.modal-bg.show{display:flex;}
.modal{background:#fff;border-radius:20px;width:100%;max-width:440px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,.25);animation:mIn .25s ease;}
@keyframes mIn{from{opacity:0;transform:scale(.95) translateY(20px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-head{background:linear-gradient(135deg,#c0392b,#e74c3c);padding:20px 24px;color:#fff;border-radius:20px 20px 0 0;position:relative;}
.modal-head h3{font-size:16px;font-weight:800;margin-bottom:3px;}
.modal-head p{font-size:12px;opacity:.8;}
.modal-close{position:absolute;top:14px;right:16px;background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:16px;display:grid;place-items:center;}
.modal-close:hover{background:rgba(255,255,255,.3);}
.modal-body{padding:24px;}
footer{padding:16px 32px;border-top:1px solid var(--border);font-size:11px;color:#aaa;display:flex;justify-content:space-between;}
@media(max-width:1000px){.sidebar{display:none;}.main{margin-left:0;}.content{padding:20px 16px;}.grid2{grid-template-columns:1fr;}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-brand"><div class="sb-icon">🔒</div><div><strong>Smart Locker</strong><span>Institution</span></div></div>
  <div class="sb-user">
    <div class="sb-av"><?php echo strtoupper(substr($user_name,0,1)); ?></div>
    <div><strong><?php echo htmlspecialchars($user_name); ?></strong>
    <span class="badge <?php echo $user_type==='staff'?'b-sf':'b-st'; ?>"><?php echo ucfirst($user_type); ?></span></div>
  </div>
  <div class="nav-sec">Menu</div>
  <a href="dashboard.php" class="nav-a"><i class="fas fa-th-large"></i> Dashboard</a>
  <a href="my-locker.php" class="nav-a"><i class="fas fa-box"></i> My Lockers</a>
  <a href="assign-locker.php" class="nav-a on"><i class="fas fa-qrcode"></i> Assign Locker</a>
  <a href="scan-access.php" class="nav-a"><i class="fas fa-sign-in-alt"></i> Scan Access</a>
  <a href="activity-logs.php" class="nav-a"><i class="fas fa-history"></i> Activity Logs</a>
  <div class="nav-sec">Account</div>
  <a href="profile.php" class="nav-a"><i class="fas fa-user-circle"></i> My Profile</a>
  <a href="settings.php" class="nav-a"><i class="fas fa-cog"></i> Settings</a>
  <div class="sb-foot"><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <h1><i class="fas fa-wifi" style="color:var(--teal);margin-right:8px"></i>My Lockers</h1>
      <p>Assign a new locker via NFC tap — <?php echo $avail_count; ?> available lockers</p>
    </div>
  </div>

  <div class="content">
    <?php if($message): ?>
    <div class="msg <?php echo $msg_type; ?>">
      <i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-circle'; ?>" style="flex-shrink:0;margin-top:1px"></i>
      <span><?php echo $message; ?></span>
    </div>
    <?php endif; ?>

    <div class="grid2">

      <!-- LEFT: NFC ASSIGN INSTRUCTION -->
      <div>

        <?php if (!$nfc_registered): ?>
        <div class="msg error" style="margin-bottom:16px;">
          <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:1px;font-size:18px;"></i>
          <div>
            <strong>NFC Card not registered yet!</strong><br>
            <span style="font-size:12px;">Please register your NFC card in <a href="profile.php" style="color:#c0392b;font-weight:700;">Profile</a> before you can assign a locker.</span>
          </div>
        </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:18px;">
          <div class="ch">
            <div class="ch-ic" style="background:#e8f7f3;color:#0a7c63;"><i class="fas fa-wifi"></i></div>
            <h2>Assign Locker via NFC Tap</h2>
            <span class="avbadge"><i class="fas fa-circle" style="font-size:7px"></i><?php echo $avail_count; ?> available</span>
          </div>
          <div class="cb">
            <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:20px;">
              <div style="display:flex;align-items:flex-start;gap:14px;">
                <div style="width:36px;height:36px;background:var(--teal);border-radius:50%;display:grid;place-items:center;color:#fff;font-size:14px;font-weight:800;flex-shrink:0;">1</div>
                <div>
                  <div style="font-size:13px;font-weight:700;margin-bottom:2px;">Go to Scan Access</div>
                  <div style="font-size:12px;color:var(--mid);">Click the button below to go to Scan Access</div>
                </div>
              </div>
              <div style="display:flex;align-items:flex-start;gap:14px;">
                <div style="width:36px;height:36px;background:var(--teal);border-radius:50%;display:grid;place-items:center;color:#fff;font-size:14px;font-weight:800;flex-shrink:0;">2</div>
                <div>
                  <div style="font-size:13px;font-weight:700;margin-bottom:2px;">Select the locker to assign</div>
                  <div style="font-size:12px;color:var(--mid);">Choose from the list of available lockers</div>
                </div>
              </div>
              <div style="display:flex;align-items:flex-start;gap:14px;">
                <div style="width:36px;height:36px;background:var(--teal);border-radius:50%;display:grid;place-items:center;color:#fff;font-size:14px;font-weight:800;flex-shrink:0;">3</div>
                <div>
                  <div style="font-size:13px;font-weight:700;margin-bottom:2px;">Go to the locker & tap your NFC card</div>
                  <div style="font-size:12px;color:var(--mid);">Hold the NFC card near the PN532 reader on the locker</div>
                </div>
              </div>
              <div style="display:flex;align-items:flex-start;gap:14px;">
                <div style="width:36px;height:36px;background:#0a7c63;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:14px;flex-shrink:0;"><i class="fas fa-check"></i></div>
                <div>
                  <div style="font-size:13px;font-weight:700;margin-bottom:2px;color:#0a7c63;">Locker auto-assigned & unlocked!</div>
                  <div style="font-size:12px;color:var(--mid);">The system will assign and unlock the locker immediately</div>
                </div>
              </div>
            </div>
            <a href="scan-access.php" class="btn btn-p" style="display:flex;text-decoration:none;">
              <i class="fas fa-wifi"></i> Go to Scan Access
            </a>
          </div>
        </div>

        <div class="card">
          <div class="ch">
            <div class="ch-ic" style="background:#fff8e1;color:var(--gold)"><i class="fas fa-info-circle"></i></div>
            <h2>Important</h2>
          </div>
          <div class="cb" style="font-size:12px;color:var(--mid);line-height:1.9;">
            <p><i class="fas fa-check-circle" style="color:#0a7c63;margin-right:6px;"></i>Only <strong>available</strong> lockers can be claimed</p>
            <p><i class="fas fa-check-circle" style="color:#0a7c63;margin-right:6px;"></i>You can assign <strong>multiple lockers</strong> with the same NFC card</p>
            <p><i class="fas fa-check-circle" style="color:#0a7c63;margin-right:6px;"></i>First tap → locker assigned & unlocked automatically</p>
            <p><i class="fas fa-check-circle" style="color:#0a7c63;margin-right:6px;"></i>Subsequent taps → locker unlocks directly</p>
            <div style="background:#fdf0ef;border-radius:8px;padding:10px;margin-top:10px;border:1.5px solid #f5c6c2;">
              <p style="color:#c0392b;font-weight:700;margin-bottom:3px;"><i class="fas fa-exclamation-triangle" style="margin-right:5px;"></i>NFC Card Required</p>
              <p style="color:#8e1a0e;">Make sure your NFC card is registered in <a href="profile.php" style="color:#c0392b;font-weight:700;">Profile</a> before tapping.</p>
            </div>
          </div>
        </div>

      </div>

      <!-- RIGHT -->
      <div>
        <!-- MY LOCKERS -->
        <div class="card">
          <div class="ch">
            <div class="ch-ic"><i class="fas fa-list"></i></div>
            <h2>My Lockers</h2>
            <span style="background:var(--teal);color:#fff;font-size:11px;font-weight:800;padding:3px 10px;border-radius:20px"><?php echo count($my_lockers); ?></span>
          </div>
          <div class="cb">
            <?php if(empty($my_lockers)): ?>
            <div class="empty">
              <i class="fas fa-inbox"></i>
              <p style="font-size:13px;font-weight:600;">No lockers assigned yet</p>
              <p style="font-size:12px;margin-top:6px;">Go to Scan Access and tap your NFC card on the locker you want to assign</p>
            </div>
            <?php else: ?>
            <?php foreach($my_lockers as $ml): ?>
            <div class="mlcard" onclick="openRemove(<?php echo htmlspecialchars(json_encode(['aid'=>$ml['assignment_id'],'name'=>$ml['name'],'code'=>$ml['unique_code']])); ?>)">
              <div class="mlcard-ic"><i class="fas fa-box"></i></div>
              <div style="flex:1;min-width:0">
                <div class="mlcard-name"><?php echo htmlspecialchars($ml['name']); ?></div>
                <div class="mlcard-sub"><?php echo htmlspecialchars($ml['location']?:'No location'); ?></div>
                <div style="margin-top:4px;">
                  <span style="font-family:monospace;font-size:11px;background:var(--teal-l);color:var(--teal);padding:2px 8px;border-radius:5px;font-weight:700;">
                    <?php echo htmlspecialchars($ml['key_value']); ?>
                  </span>
                </div>
                <span style="font-size:10px;color:#aac5c6;font-style:italic;display:block;margin-top:3px;">
                  <i class="fas fa-lock" style="font-size:9px"></i> Since <?php echo date('d M Y', strtotime($ml['assigned_at'])); ?>
                </span>
              </div>
              <i class="fas fa-trash-alt mlcard-arrow"></i>
            </div>
            <?php endforeach; ?>
            <div class="access-hint"><i class="fas fa-info-circle" style="margin-right:5px;color:var(--teal)"></i>Click on a locker to remove access</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
  <footer>
    <span>© 2026 Smart Locker System — Institution Version</span>
    <span><?php echo htmlspecialchars($user_name)." · ".date("d M Y, H:i"); ?></span>
  </footer>
</div>

<!-- MODAL REMOVE -->
<div class="modal-bg" id="mRemove">
  <div class="modal">
    <div class="modal-head">
      <h3>Remove Locker Access</h3>
      <p id="mRemoveSub">Confirm your action</p>
      <button class="modal-close" onclick="closeRemove()">✕</button>
    </div>
    <div class="modal-body">
      <div style="text-align:center;padding:10px 0 20px">
        <div style="font-size:48px;margin-bottom:12px">🗑️</div>
        <p style="font-size:14px;font-weight:700;margin-bottom:6px">Are you sure you want to remove access to this locker?</p>
        <p style="font-size:12px;color:var(--mid)">Locker <strong id="mRemoveName"></strong> will be released and can be used by others.</p>
      </div>
      <div class="brow">
        <button class="btn btn-o" onclick="closeRemove()"><i class="fas fa-times"></i> No, Cancel</button>
        <button class="btn btn-d" onclick="submitRemove()"><i class="fas fa-trash-alt"></i> Yes, Remove</button>
      </div>
    </div>
  </div>
</div>
<form id="removeForm" method="POST" style="display:none">
  <input type="hidden" name="action" value="remove_access">
  <input type="hidden" name="assignment_id" id="removeAid">
</form>

<script>
// ── REMOVE MODAL ──
let curRemove = null;
function openRemove(data) {
    curRemove = data;
    document.getElementById('mRemoveName').textContent = data.name || data.code;
    document.getElementById('mRemoveSub').textContent  = data.code;
    document.getElementById('mRemove').classList.add('show');
}
function closeRemove() { document.getElementById('mRemove').classList.remove('show'); }
function submitRemove() {
    if (!curRemove) return;
    document.getElementById('removeAid').value = curRemove.aid;
    document.getElementById('removeForm').submit();
}
document.getElementById('mRemove').addEventListener('click', e => {
    if (e.target === document.getElementById('mRemove')) closeRemove();
});
</script>
</body>
</html>