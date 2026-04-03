<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');
$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'student';

// Fetch user's lockers
$user_lockers = [];
try {
    $stmt = $pdo->prepare("
        SELECT l.id,
               COALESCE(ula.custom_name, l.unique_code) AS name,
               COALESCE(ula.custom_location, '')         AS location,
               ula.key_value,
               l.unique_code,
               l.device_id,
               l.status
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.user_id  = ?
          AND ula.is_active = 1
          AND l.status     != 'maintenance'
        ORDER BY name ASC
    ");
    $stmt->execute([$user_id]);
    $user_lockers = $stmt->fetchAll();
} catch (Exception $e) { $user_lockers = []; }

// Fetch available lockers (belum assigned ke sesiapa)
$available_lockers = [];
try {
    $stmt_av = $pdo->prepare("
        SELECT l.id, l.unique_code, l.device_id,
               COALESCE(l.unique_code, '') AS name,
               '' AS location, '' AS key_value
        FROM lockers l
        WHERE l.status = 'available'
          AND l.device_id IS NOT NULL
          AND l.device_id != ''
          AND l.id NOT IN (
              SELECT locker_id FROM user_locker_assignments WHERE is_active = 1
          )
        ORDER BY l.unique_code ASC
    ");
    $stmt_av->execute();
    $available_lockers = $stmt_av->fetchAll();
} catch (Exception $e) { $available_lockers = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scan Access — Smart Locker Institution</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--teal:#0d7377;--teal-d:#085c60;--teal-l:#e8f6f7;--dark:#0f1f20;--mid:#3d5c5e;--light:#f4f9f9;--border:#d0e8e9;--sw:240px;--gold:#f0a500;}
body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--light);color:var(--dark);display:flex;min-height:100vh;}
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
.nav-a:hover{background:rgba(255,255,255,.07);color:#fff;}
.nav-a.on{background:var(--teal);color:#fff;}
.sb-foot{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,.07);}
.logout{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;color:rgba(255,255,255,.45);font-size:13px;text-decoration:none;transition:all .2s;}
.logout:hover{background:rgba(220,50,50,.15);color:#ff7b7b;}
.main{margin-left:var(--sw);flex:1;display:flex;flex-direction:column;}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:16px 32px;position:sticky;top:0;z-index:40;}
.topbar h1{font-size:18px;font-weight:800;}.topbar p{font-size:12px;color:var(--mid);margin-top:2px;}
.content{padding:28px 32px;flex:1;}

/* STEPPER */
.stepper{display:flex;align-items:center;background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:16px 22px;margin-bottom:24px;gap:4px;}
.si{display:flex;align-items:center;gap:9px;flex:1;}
.sn{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:800;flex-shrink:0;transition:all .3s;}
.sn.done{background:var(--teal);color:#fff;}
.sn.act{background:var(--teal);color:#fff;box-shadow:0 0 0 4px rgba(13,115,119,.18);}
.sn.pend{background:var(--teal-l);color:#aac5c6;}
.sl{font-size:12px;font-weight:700;color:var(--mid);}.sl.act{color:var(--teal);}
.sdv{flex:0 0 28px;height:2px;background:var(--border);transition:background .3s;}.sdv.done{background:var(--teal);}

/* PANEL */
.panel{background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;max-width:760px;margin:0 auto;}
.ph{background:linear-gradient(135deg,var(--teal),var(--teal-d));padding:20px 26px;color:#fff;position:relative;overflow:hidden;}
.ph::after{content:"";position:absolute;right:-20px;top:-20px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.05);}
.ph h2{font-size:16px;font-weight:800;margin-bottom:3px;}
.ph p{font-size:12px;opacity:.8;}
.pb{padding:26px;}
.sc{display:none;animation:fu .3s ease both;}.sc.on{display:block;}
@keyframes fu{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* LOCKER GRID */
.lgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px;}
.lcard{border:2px solid var(--border);border-radius:13px;padding:16px;cursor:pointer;transition:all .2s;background:var(--light);position:relative;}
.lcard:hover{border-color:var(--teal);background:var(--teal-l);transform:translateY(-2px);}
.lcard.sel{border-color:var(--teal);background:var(--teal-l);box-shadow:0 0 0 3px rgba(13,115,119,.12);}
.lcard.sel::after{content:"✓";position:absolute;top:8px;right:10px;font-size:13px;font-weight:800;color:var(--teal);}
.lc-icon{font-size:26px;margin-bottom:8px;display:block;}
.lc-name{font-size:13px;font-weight:800;margin-bottom:3px;}
.lc-loc{font-size:11px;color:var(--mid);margin-bottom:6px;}
code{background:var(--teal-l);color:var(--teal);padding:2px 7px;border-radius:5px;font-size:11px;font-family:monospace;}

/* SEL BAR */
.sbar{background:var(--teal-l);border:1.5px solid var(--border);border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;}
.sbar-name{font-size:14px;font-weight:700;}.sbar-loc{font-size:12px;color:var(--mid);}
.chg{font-size:12px;font-weight:600;color:var(--teal);background:none;border:1.5px solid var(--teal);border-radius:7px;padding:5px 12px;cursor:pointer;transition:all .2s;}
.chg:hover{background:var(--teal);color:#fff;}

/* KEY INPUT */
.ktabs{display:flex;gap:8px;margin-bottom:14px;}
.ktab{flex:1;padding:10px;border:2px solid var(--border);border-radius:10px;background:var(--light);font-family:inherit;font-size:12px;font-weight:700;color:var(--mid);cursor:pointer;text-align:center;transition:all .2s;}
.ktab.on{border-color:var(--teal);background:var(--teal-l);color:var(--teal);}
.kpanel{display:none;}.kpanel.on{display:block;}
.flbl{font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--mid);display:block;margin-bottom:8px;}
.kinp-wrap{position:relative;}
.kinp-wrap input{width:100%;padding:13px 44px 13px 16px;border:2px solid var(--border);border-radius:10px;font-family:monospace;font-size:16px;font-weight:800;letter-spacing:.15em;color:var(--dark);background:var(--light);outline:none;transition:all .2s;}
.kinp-wrap input:focus{border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px rgba(13,115,119,.08);}
.kinp-wrap input.ok{border-color:#0a7c63;background:#e8f7f3;}
.kinp-wrap input.err{border-color:#e74c3c;background:#fdf0ef;}
.kic{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:16px;color:#aac5c6;transition:color .2s;}
.khint{font-size:11px;color:#aaa;margin-top:6px;}

/* MINI CAM (key scan) */
.minicam{position:relative;background:#0f1f20;border-radius:10px;overflow:hidden;height:190px;margin-bottom:10px;}
#kv{width:100%;height:100%;object-fit:cover;display:block;}
.kframe{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:130px;height:130px;border:2px solid var(--teal);border-radius:8px;box-shadow:0 0 0 9999px rgba(0,0,0,.55);}
.kscan{position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--teal),transparent);animation:scanl 1.8s linear infinite;}
@keyframes scanl{0%{top:5%}100%{top:95%}}
.kph{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:rgba(255,255,255,.3);gap:8px;}
.kst{position:absolute;bottom:6px;left:0;right:0;text-align:center;font-size:11px;font-weight:600;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.6);}

/* USER CARD CAM */
.camw{position:relative;background:#0f1f20;border-radius:13px;overflow:hidden;height:280px;margin-bottom:14px;}
#uv{width:100%;height:100%;object-fit:cover;display:block;}
.uframe{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:200px;height:200px;border:3px solid var(--teal);border-radius:14px;box-shadow:0 0 0 9999px rgba(0,0,0,.5);}
.uscan{position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--teal),transparent);animation:scanl 2s linear infinite;}
.uph{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:rgba(255,255,255,.3);gap:10px;}
.ust{position:absolute;bottom:8px;left:0;right:0;text-align:center;font-size:12px;font-weight:600;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.6);}

/* RESULT */
.rgrant{background:linear-gradient(135deg,#0a7c63,#0d7377);border-radius:14px;padding:22px;color:#fff;margin-bottom:16px;}
.rdeny{background:linear-gradient(135deg,#c0392b,#e74c3c);border-radius:14px;padding:22px;color:#fff;margin-bottom:16px;}
.rt{font-size:18px;font-weight:800;margin-bottom:14px;display:flex;align-items:center;gap:9px;}
.rg{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.ri .lbl{font-size:10px;opacity:.7;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;}
.ri .val{font-size:14px;font-weight:700;}

/* CONTROL BUTTONS */
.ctrl-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;}
.ctrl-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:18px 12px;border-radius:13px;border:none;cursor:pointer;font-family:inherit;font-weight:800;font-size:14px;transition:all .2s;}
.ctrl-btn i{font-size:24px;}
.ctrl-open{background:#0a7c63;color:#fff;}.ctrl-open:hover{background:#086b54;transform:translateY(-2px);}
.ctrl-close{background:#2c3e50;color:#fff;}.ctrl-close:hover{background:#1a252f;transform:translateY(-2px);}

/* REMINDER BOX */
.reminder{background:linear-gradient(135deg,#fffbef,#fff8dc);border:2px solid var(--gold);border-radius:13px;padding:18px;margin-bottom:16px;}
.reminder-title{font-size:14px;font-weight:800;color:#c87f00;display:flex;align-items:center;gap:8px;margin-bottom:10px;}
.reminder p{font-size:12px;color:#7a5c00;line-height:1.6;margin-bottom:10px;}
.reminder-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:var(--gold);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;}
.reminder-btn:hover{background:#c87f00;}

/* GENERAL */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 18px;border-radius:10px;font-family:inherit;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all .2s;width:100%;}
.btn-p{background:var(--teal);color:#fff;}.btn-p:hover{background:var(--teal-d);}
.btn-p:disabled{background:#aac5c6;cursor:not-allowed;}
.btn-o{background:#fff;color:var(--mid);border:2px solid var(--border);}.btn-o:hover{border-color:var(--teal);color:var(--teal);}
.btn-d{background:#e74c3c;color:#fff;}.btn-d:hover{background:#c0392b;}
.brow{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.al{padding:12px 15px;border-radius:10px;font-size:13px;display:flex;align-items:center;gap:9px;margin-bottom:12px;}
.al-d{background:#fdf0ef;border:1.5px solid #f5c6c2;color:#c0392b;}
.al-i{background:var(--teal-l);border:1.5px solid var(--border);color:var(--teal);}
.al-s{background:#e8f7f3;border:1.5px solid #b6e8da;color:#0a7c63;}
.empty{text-align:center;padding:40px 20px;color:var(--mid);}
.empty i{font-size:40px;opacity:.15;display:block;margin-bottom:12px;}
footer{padding:16px 32px;border-top:1px solid var(--border);font-size:11px;color:#aaa;display:flex;justify-content:space-between;}
@keyframes pulse{0%,100%{opacity:.3;transform:scale(.8)}50%{opacity:1;transform:scale(1.2)}}
@keyframes nfcPulse{0%{transform:translate(-50%,-50%) scale(0);opacity:.9}100%{transform:translate(-50%,-50%) scale(1);opacity:0}}
@keyframes nfcGlow{0%,100%{filter:drop-shadow(0 0 5px #0d7377)}50%{filter:drop-shadow(0 0 16px #0d7377)}}
@media(max-width:900px){.sidebar{display:none;}.main{margin-left:0;}.content{padding:20px 16px;}.rg{grid-template-columns:1fr;}.ctrl-row{grid-template-columns:1fr 1fr;}}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sb-brand"><div class="sb-icon">🔒</div><div><strong>Smart Locker</strong><span>Institution</span></div></div>
  <div class="sb-user">
    <div class="sb-av"><?php echo strtoupper(substr($user_name,0,1)); ?></div>
    <div>
      <strong><?php echo htmlspecialchars($user_name); ?></strong>
      <span class="badge <?php echo $user_type==='staff'?'b-sf':'b-st'; ?>"><?php echo ucfirst($user_type); ?></span>
    </div>
  </div>
  <div class="nav-sec">Menu</div>
  <a href="dashboard.php" class="nav-a"><i class="fas fa-th-large"></i> Dashboard</a>
  <a href="my-locker.php" class="nav-a"><i class="fas fa-box"></i> My Lockers</a>
  <a href="assign-locker.php" class="nav-a"><i class="fas fa-plus-circle"></i> Assign Locker</a>
  <a href="scan-access.php" class="nav-a on"><i class="fas fa-qrcode"></i> Scan Access</a>
  <a href="access-logs.php" class="nav-a"><i class="fas fa-history"></i> Activity Logs</a>
  <div class="nav-sec">Account</div>
  <a href="profile.php" class="nav-a"><i class="fas fa-user-circle"></i> My Profile</a>
  <a href="settings.php" class="nav-a"><i class="fas fa-cog"></i> Settings</a>
  <div class="sb-foot"><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</aside>

<div class="main">
  <div class="topbar"><h1>Scan Access</h1><p>Choose your locker or claim a new one — tap NFC to access</p></div>
  <div class="content">

    <!-- STEPPER -->
    <div class="stepper">
      <div class="si"><div class="sn act" id="sn1">1</div><div class="sl act" id="sl1">Choose Locker</div></div>
      <div class="sdv" id="sd1"></div>
      <div class="si"><div class="sn pend" id="sn2">2</div><div class="sl" id="sl2">Tap NFC Card</div></div>
      <div class="sdv" id="sd2"></div>
      <div class="si"><div class="sn pend" id="sn3">3</div><div class="sl" id="sl3">Access Locker</div></div>
    </div>

    <div class="panel">

      <!-- ══ STEP 1: PILIH LOCKER ══ -->
      <div id="sc1" class="sc on">
        <div class="ph"><h2><i class="fas fa-box" style="margin-right:8px"></i>Step 1 — Choose Locker</h2><p>Choose your existing locker or claim a new available one</p></div>
        <div class="pb">

          <?php if(empty($user_lockers) && empty($available_lockers)): ?>
          <div class="empty">
            <i class="fas fa-box-open"></i>
            <p style="font-size:13px;font-weight:600">No lockers available</p>
            <p style="font-size:12px;margin-top:6px">No lockers are available right now. Please contact admin.</p>
          </div>

          <?php else: ?>

          <?php if(!empty($user_lockers)): ?>
          <!-- My Lockers -->
          <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--mid);margin-bottom:10px;display:flex;align-items:center;gap:8px;">
            <span style="background:var(--teal);color:#fff;padding:2px 8px;border-radius:20px;">MY LOCKERS</span>
            <span>Tap NFC to open</span>
          </div>
          <div class="lgrid" style="margin-bottom:20px;">
            <?php foreach($user_lockers as $lk): ?>
            <div class="lcard" onclick="selLock(this)"
                 data-id="<?php echo (int)$lk['id']; ?>"
                 data-name="<?php echo htmlspecialchars($lk['name']); ?>"
                 data-loc="<?php echo htmlspecialchars($lk['location']); ?>"
                 data-key="<?php echo htmlspecialchars($lk['key_value']); ?>"
                 data-code="<?php echo htmlspecialchars($lk['unique_code']); ?>"
                 data-type="mine">
              <span class="lc-icon">🔒</span>
              <div class="lc-name"><?php echo htmlspecialchars($lk['name']); ?></div>
              <div class="lc-loc"><?php echo htmlspecialchars($lk['location'] ?: $lk['unique_code']); ?></div>
              <code><?php echo htmlspecialchars($lk['unique_code']); ?></code>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if(!empty($available_lockers)): ?>
          <!-- Available Lockers -->
          <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--mid);margin-bottom:10px;display:flex;align-items:center;gap:8px;">
            <span style="background:#0a7c63;color:#fff;padding:2px 8px;border-radius:20px;">AVAILABLE</span>
            <span>Tap NFC to claim & open</span>
          </div>
          <div class="lgrid" style="margin-bottom:20px;">
            <?php foreach($available_lockers as $lk): ?>
            <div class="lcard" onclick="selLock(this)"
                 style="border-color:#b6e8da;background:#f0fdf9;"
                 data-id="<?php echo (int)$lk['id']; ?>"
                 data-name="<?php echo htmlspecialchars($lk['unique_code']); ?>"
                 data-loc=""
                 data-key=""
                 data-code="<?php echo htmlspecialchars($lk['unique_code']); ?>"
                 data-type="available">
              <span class="lc-icon">🔓</span>
              <div class="lc-name"><?php echo htmlspecialchars($lk['unique_code']); ?></div>
              <div class="lc-loc" style="color:#0a7c63;">Available to claim</div>
              <code style="background:#d0f0e8;color:#0a7c63;"><?php echo htmlspecialchars($lk['unique_code']); ?></code>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <button class="btn btn-p" id="btn1n" onclick="gS(2)" disabled><i class="fas fa-arrow-right"></i> Next</button>
          <?php endif; ?>
        </div>
      </div>

      <!-- ══ STEP 2: TAP NFC CARD ══ -->
      <div id="sc2" class="sc">
        <div class="ph"><h2><i class="fas fa-wifi" style="margin-right:8px"></i>Step 2 — Tap NFC Card</h2><p>Tap your NFC sticker/card on the reader at the locker</p></div>
        <div class="pb">
          <div class="sbar"><div><div class="sbar-name" id="s2n">—</div><div class="sbar-loc" id="s2l">—</div></div><button class="chg" onclick="gS(1)"><i class="fas fa-exchange-alt"></i> Change</button></div>

          <!-- NFC WAITING UI -->
          <div style="background:linear-gradient(135deg,#0f1f20,#1a3535);border-radius:14px;padding:32px 28px;text-align:center;margin-bottom:16px;">
            <!-- NFC Pulse Animation -->
            <div style="position:relative;width:90px;height:90px;margin:0 auto 18px;">
              <div class="nfc-pulse-ring" style="position:absolute;border-radius:50%;border:2px solid #0d7377;top:50%;left:50%;width:28px;height:28px;transform:translate(-50%,-50%) scale(0);opacity:0;animation:nfcPulse 2s ease-out infinite 0s;"></div>
              <div class="nfc-pulse-ring" style="position:absolute;border-radius:50%;border:2px solid #0d7377;top:50%;left:50%;width:56px;height:56px;transform:translate(-50%,-50%) scale(0);opacity:0;animation:nfcPulse 2s ease-out infinite .5s;"></div>
              <div class="nfc-pulse-ring" style="position:absolute;border-radius:50%;border:2px solid #0d7377;top:50%;left:50%;width:84px;height:84px;transform:translate(-50%,-50%) scale(0);opacity:0;animation:nfcPulse 2s ease-out infinite 1s;"></div>
              <div id="scanIcon" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:30px;color:#0d7377;animation:nfcGlow 1.5s ease-in-out infinite;">📶</div>
            </div>
            <p style="color:rgba(255,255,255,.9);font-size:14px;font-weight:800;margin-bottom:4px" id="scanTitle">Ready to Tap</p>
            <p style="color:rgba(255,255,255,.5);font-size:12px;margin-bottom:0;" id="scanSub">Hold your NFC sticker near the PN532 reader on your locker</p>
            <div id="pulseWrap" style="margin-top:14px;display:flex;justify-content:center;align-items:center;gap:6px">
              <div style="width:8px;height:8px;border-radius:50%;background:#0d7377;animation:pulse 1.4s infinite"></div>
              <div style="width:8px;height:8px;border-radius:50%;background:#0d7377;animation:pulse 1.4s infinite .2s"></div>
              <div style="width:8px;height:8px;border-radius:50%;background:#0d7377;animation:pulse 1.4s infinite .4s"></div>
            </div>
          </div>

          <div id="scanAl" style="margin-bottom:12px"></div>
          <button class="btn btn-o" onclick="gS(1)"><i class="fas fa-arrow-left"></i> Back</button>
        </div>
      </div>

      <!-- ══ STEP 3: AKSES LOCKER ══ -->
      <div id="sc3" class="sc">
        <div class="ph"><h2><i class="fas fa-lock-open" style="margin-right:8px"></i>Step 3 — Locker Access</h2><p>Identity verified — open or lock your locker</p></div>
        <div class="pb">

          <!-- GRANTED — ACCESS (locker buka biasa) -->
          <div id="resG" style="display:none">
            <div class="rgrant">
              <div class="rt"><i class="fas fa-check-circle"></i> ACCESS GRANTED</div>
              <div class="rg">
                <div class="ri"><div class="lbl">Name</div><div class="val" id="rNm">—</div></div>
                <div class="ri"><div class="lbl">Locker</div><div class="val" id="rLk">—</div></div>
                <div class="ri"><div class="lbl">ID / Matric</div><div class="val" id="rId">—</div></div>
                <div class="ri"><div class="lbl">Location</div><div class="val" id="rLo">—</div></div>
                <div class="ri"><div class="lbl">Type</div><div class="val" id="rTy">—</div></div>
                <div class="ri"><div class="lbl">Institution</div><div class="val" id="rIn">—</div></div>
              </div>
            </div>
            <div class="ctrl-row">
              <button class="ctrl-btn ctrl-open" onclick="ctrlL('open')">
                <i class="fas fa-lock-open"></i> Open Locker
              </button>
              <button class="ctrl-btn ctrl-close" onclick="ctrlL('close')">
                <i class="fas fa-lock"></i> Lock Locker
              </button>
            </div>
            <div class="reminder">
              <div class="reminder-title"><i class="fas fa-bell"></i> Important Reminder</div>
              <p>If you are <strong>no longer using this locker</strong>, please remove your access so the locker can be used by others.</p>
              <a href="my-locker.php" class="reminder-btn"><i class="fas fa-trash-alt"></i> Go to My Lockers to remove access</a>
            </div>
          </div>

          <!-- ASSIGNED — locker baru di-claim & terus dibuka -->
          <div id="resA" style="display:none">
            <div class="rgrant" style="background:linear-gradient(135deg,#0d7377,#0a5c60);">
              <div class="rt"><i class="fas fa-star"></i> LOCKER SUCCESSFULLY ASSIGNED!</div>
              <div class="rg">
                <div class="ri"><div class="lbl">Name</div><div class="val" id="aNm">—</div></div>
                <div class="ri"><div class="lbl">Locker</div><div class="val" id="aLk">—</div></div>
                <div class="ri"><div class="lbl">ID / Matric</div><div class="val" id="aId">—</div></div>
                <div class="ri"><div class="lbl">Type</div><div class="val" id="aTy">—</div></div>
              </div>
            </div>
            <!-- Access Key display -->
            <div style="background:#e8f7f3;border:1.5px solid #b6e8da;border-radius:12px;padding:16px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
              <i class="fas fa-key" style="font-size:22px;color:#0a7c63;flex-shrink:0;"></i>
              <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#0a7c63;margin-bottom:4px;">Your Access Key</div>
                <div style="font-family:monospace;font-size:18px;font-weight:800;letter-spacing:3px;color:#0f1f20;" id="aKey">—</div>
                <div style="font-size:11px;color:#3d5c5e;margin-top:3px;">Save this access key for your reference.</div>
              </div>
            </div>
            <div class="ctrl-row">
              <button class="ctrl-btn ctrl-open" onclick="ctrlL('open')">
                <i class="fas fa-lock-open"></i> Open Locker
              </button>
              <button class="ctrl-btn ctrl-close" onclick="ctrlL('close')">
                <i class="fas fa-lock"></i> Lock Locker
              </button>
            </div>
          </div>

          <!-- DENIED -->
          <div id="resD" style="display:none">
            <div class="rdeny">
              <div class="rt"><i class="fas fa-times-circle"></i> ACCESS DENIED</div>
              <p id="rEr" style="font-size:13px;opacity:.9;line-height:1.5"></p>
            </div>
          </div>

          <button class="btn btn-o" onclick="resetAll()"><i class="fas fa-redo"></i> Start Over</button>
        </div>
      </div>

    </div><!-- /panel -->
  </div>
  <footer>
    <span>© 2026 Smart Locker System — Institution Version</span>
    <span><?php echo htmlspecialchars($user_name)." · ".date("d M Y, H:i"); ?></span>
  </footer>
</div>

<script>
let S = null, vk = "", vl = "";
let pollInt = null;

/* ── STEPPER ── */
function gS(n) {
  if (n !== 2) stopPolling();
  for (let i = 1; i <= 3; i++) {
    document.getElementById("sc" + i).classList.remove("on");
    const sn = document.getElementById("sn" + i), sl = document.getElementById("sl" + i);
    if (i < n)        { sn.className = "sn done"; sn.innerHTML = '<i class="fas fa-check" style="font-size:10px"></i>'; sl.className = "sl"; }
    else if (i === n) { sn.className = "sn act"; sn.textContent = i; sl.className = "sl act"; }
    else              { sn.className = "sn pend"; sn.textContent = i; sl.className = "sl"; }
    if (i < 3) document.getElementById("sd" + i).className = "sdv" + (i < n ? " done" : "");
  }
  document.getElementById("sc" + n).classList.add("on");

  if (n === 2 && S) {
    document.getElementById("s2n").textContent = S.name;
    document.getElementById("s2l").textContent = S.loc || S.code;
    document.getElementById("scanAl").innerHTML = "";

    // Tunjuk hint berbeza untuk available vs my locker
    const sub = document.getElementById("sc2").querySelector(".ph p");
    if (S.type === 'available') {
      sub.innerHTML = '🟢 <strong>New locker</strong> — tap NFC to claim & unlock automatically';
    } else {
      sub.textContent = 'Tap your NFC sticker/card on the reader at the locker';
    }

    setScanUI("waiting");
    startPolling();
  }
}

/* ── STEP 1 ── */
function selLock(el) {
  document.querySelectorAll(".lcard").forEach(c => c.classList.remove("sel"));
  el.classList.add("sel");
  S = { id: el.dataset.id, name: el.dataset.name, loc: el.dataset.loc, key: el.dataset.key, code: el.dataset.code, type: el.dataset.type };
  document.getElementById("btn1n").disabled = false;
}

/* ── STEP 2: POLLING NFC ── */
function startPolling() {
  if (pollInt) return;
  pollInt = setInterval(doPoll, 1500);
}

function stopPolling() {
  if (pollInt) { clearInterval(pollInt); pollInt = null; }
}

async function doPoll() {
  if (!S) return;
  try {
    const res = await fetch("api/nfc_poll.php?locker_id=" + encodeURIComponent(S.id));
    const d = await res.json();

    if (d.scanned && d.success) {
      stopPolling();
      setScanUI("success");
      setTimeout(() => {
        vk = d.data.access_key || S.key;
        vl = S.id;
        gS(3);

        if (d.action === 'assigned') {
          // ── LOCKER BARU DI-ASSIGN ──
          document.getElementById("resG").style.display = "none";
          document.getElementById("resA").style.display = "block";
          document.getElementById("resD").style.display = "none";
          document.getElementById("aNm").textContent = d.data.full_name || "—";
          document.getElementById("aLk").textContent = d.data.locker_name || S.name;
          document.getElementById("aId").textContent = d.data.user_id_number || "—";
          document.getElementById("aTy").textContent = d.data.user_type ? d.data.user_type.charAt(0).toUpperCase() + d.data.user_type.slice(1) : "—";
          document.getElementById("aKey").textContent = d.data.access_key || "—";
        } else {
          // ── ACCESS BIASA ──
          document.getElementById("resG").style.display = "block";
          document.getElementById("resA").style.display = "none";
          document.getElementById("resD").style.display = "none";
          document.getElementById("rNm").textContent = d.data.full_name || "—";
          document.getElementById("rLk").textContent = d.data.locker_name || S.name;
          document.getElementById("rId").textContent = d.data.user_id_number || "—";
          document.getElementById("rLo").textContent = d.data.location || S.loc || "—";
          document.getElementById("rTy").textContent = d.data.user_type ? d.data.user_type.charAt(0).toUpperCase() + d.data.user_type.slice(1) : "—";
          document.getElementById("rIn").textContent = d.data.institution || "—";
        }
      }, 800);

    } else if (d.scanned && !d.success) {
      stopPolling();
      setScanUI("denied", d.message);
      setTimeout(() => {
        setScanUI("waiting");
        document.getElementById("scanAl").innerHTML = "";
        startPolling();
      }, 3000);
    }
    // !d.scanned = belum ada tap, teruskan poll
  } catch (e) {
    console.error("NFC poll error:", e);
  }
}

function setScanUI(state, msg) {
  const icon  = document.getElementById("scanIcon");
  const title = document.getElementById("scanTitle");
  const sub   = document.getElementById("scanSub");
  const pulse = document.getElementById("pulseWrap");
  const al    = document.getElementById("scanAl");

  if (state === "waiting") {
    icon.textContent  = "📶";
    title.textContent = "Ready to Tap";
    sub.textContent   = "Hold your NFC sticker near the PN532 reader on your locker";
    pulse.style.display = "flex";
    al.innerHTML = "";
  } else if (state === "success") {
    icon.textContent  = "✅";
    title.textContent = "NFC Verified!";
    sub.textContent   = "Redirecting to locker control...";
    pulse.style.display = "none";
  } else if (state === "denied") {
    icon.textContent  = "❌";
    title.textContent = "Access Denied";
    sub.textContent   = msg || "NFC card is invalid or has no access to this locker.";
    pulse.style.display = "none";
    al.innerHTML = '<div class="al al-d"><i class="fas fa-times-circle"></i> ' + (msg || "Please try tapping again.") + '</div>';
  }
}

/* ── STEP 3: CONTROL ── */
async function ctrlL(action) {
  if (!vk || !vl) { alert("No locker data found."); return; }
  try {
    const r = await fetch("api/" + (action === "open" ? "open_locker" : "close_locker") + ".php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ access_key: vk, locker_id: vl })
    });
    const d = await r.json();
    alert(d.success ? (action === "open" ? "🔓 Locker opened successfully!" : "🔒 Locker locked successfully!") : "❌ " + (d.message || "Failed."));
  } catch (e) { alert("Network error: " + e.message); }
}

/* ── RESET ── */
function resetAll() {
  stopPolling();
  S = null; vk = ""; vl = "";
  document.querySelectorAll(".lcard").forEach(c => c.classList.remove("sel"));
  const b = document.getElementById("btn1n"); if (b) b.disabled = true;
  document.getElementById("resG").style.display = "none";
  document.getElementById("resA").style.display = "none";
  document.getElementById("resD").style.display = "none";
  gS(1);
}
</script>
</body>
</html>