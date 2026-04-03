<?php
require_once 'config.php';
if (isLoggedIn()) redirect('dashboard.php');

// Pastikan session started
if (session_status() === PHP_SESSION_NONE) session_start();

$error   = '';
$success = '';
$step    = isset($_POST['step']) ? (int)$_POST['step'] : 1;

// Kalau ada session verified, terus ke step 2
if (!empty($_SESSION['signup_matric']) && $step !== 2) {
    $step = 2;
}

$v_matric = $_SESSION['signup_matric'] ?? '';
$v_name   = $_SESSION['signup_name']   ?? '';
$v_type   = $_SESSION['signup_type']   ?? 'student';
$v_inst   = $_SESSION['signup_inst']   ?? '';

// ── STEP 1: VERIFY MATRIC ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    $id_number = strtoupper(trim($_POST['id_number'] ?? ''));

    if (empty($id_number)) {
        $error = 'Sila masukkan nombor matrik / ID anda.';
    } else {
        try {
            $chk = $pdo->prepare("SELECT * FROM registered_matrics WHERE id_number = ? LIMIT 1");
            $chk->execute([$id_number]);
            $matric = $chk->fetch();

            if (!$matric) {
                $error = 'Nombor matrik / ID ini tidak dijumpai dalam sistem. Sila hubungi admin institusi anda.';
            } elseif ($matric['is_used']) {
                $error = 'Nombor matrik / ID ini telah pun didaftarkan. Sila login atau hubungi admin.';
            } else {
                // Simpan dalam session
                $_SESSION['signup_matric'] = $matric['id_number'];
                $_SESSION['signup_name']   = $matric['full_name'] ?? '';
                $_SESSION['signup_type']   = $matric['user_type'] ?? 'student';
                $_SESSION['signup_inst']   = $matric['institution'] ?? '';
                $v_matric = $_SESSION['signup_matric'];
                $v_name   = $_SESSION['signup_name'];
                $v_type   = $_SESSION['signup_type'];
                $v_inst   = $_SESSION['signup_inst'];
                $step     = 2;
            }
        } catch (Exception $e) {
            $error = 'Ralat sistem: ' . $e->getMessage();
        }
    }
}

// ── STEP 2: REGISTER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    // Ambil dari session — lebih selamat dari hidden field
    $id_number   = $_SESSION['signup_matric'] ?? '';
    $full_name   = sanitize($_POST['full_name'] ?? '');
    $email       = sanitize($_POST['email'] ?? '');
    $phone       = sanitize($_POST['phone'] ?? '');
    $user_type   = $_SESSION['signup_type'] ?? 'student';
    $institution = sanitize($_POST['institution'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm_pw  = $_POST['confirm_password'] ?? '';

    $v_matric = $id_number;
    $v_type   = $user_type;
    $v_inst   = $institution ?: $v_inst;

    if (empty($id_number)) {
        $error = 'Sesi tamat. Sila mula semula.';
        unset($_SESSION['signup_matric'], $_SESSION['signup_name'], $_SESSION['signup_type'], $_SESSION['signup_inst']);
        $step = 1;
    } elseif (empty($full_name) || empty($email) || empty($password) || empty($institution)) {
        $error = 'Sila lengkapkan semua medan yang diperlukan.';
    } elseif ($password !== $confirm_pw) {
        $error = 'Kata laluan tidak sepadan.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata laluan mestilah sekurang-kurangnya 6 aksara.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Alamat e-mel tidak sah.';
    } else {
        try {
            $chkM = $pdo->prepare("SELECT id FROM registered_matrics WHERE id_number = ? AND is_used = 0 LIMIT 1");
            $chkM->execute([$id_number]);
            if (!$chkM->fetch()) {
                $error = 'Matrik tidak sah atau sudah digunakan. Sila hubungi admin.';
                unset($_SESSION['signup_matric'], $_SESSION['signup_name'], $_SESSION['signup_type'], $_SESSION['signup_inst']);
                $step = 1;
            } else {
                $chkE = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $chkE->execute([$email]);
                if ($chkE->fetch()) {
                    $error = 'E-mel ini telah didaftarkan.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (email,password_hash,full_name,phone,user_id_number,user_type,institution,role,created_at,is_active) VALUES (?,?,?,?,?,?,?,'user',NOW(),1)")
                        ->execute([$email,$hash,$full_name,$phone,$id_number,$user_type,$institution]);
                    $pdo->prepare("UPDATE registered_matrics SET is_used=1 WHERE id_number=?")
                        ->execute([$id_number]);
                    // Clear session
                    unset($_SESSION['signup_matric'], $_SESSION['signup_name'], $_SESSION['signup_type'], $_SESSION['signup_inst']);
                    $success = 'Akaun berjaya didaftarkan! Mengalihkan ke halaman login...';
                    echo "<script>setTimeout(()=>{window.location.href='login.php';},2500);</script>";
                }
            }
        } catch (Exception $e) {
            $error = 'Pendaftaran gagal: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akaun — Smart Locker Institution</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--teal:#0d7377;--teal-d:#085c60;--teal-l:#e8f6f7;--dark:#0f1f20;--mid:#3d5c5e;--light:#f4f9f9;--border:#d0e8e9;--err:#c0392b;--suc:#0a7c63;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--light);min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:var(--dark);padding:12px 32px;display:flex;align-items:center;justify-content:space-between;}
.topbar-brand{display:flex;align-items:center;gap:10px;color:white;font-weight:700;font-size:15px;text-decoration:none;}
.topbar-brand .icon{width:30px;height:30px;background:var(--teal);border-radius:7px;display:grid;place-items:center;font-size:14px;}
.topbar-back{font-size:12px;color:rgba(255,255,255,.55);text-decoration:none;display:flex;align-items:center;gap:6px;transition:color .2s;}
.topbar-back:hover{color:white;}
.main{flex:1;display:flex;align-items:flex-start;justify-content:center;padding:40px 24px;}
.signup-card{background:white;border:1.5px solid var(--border);border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(13,115,119,.10);width:100%;max-width:580px;animation:fadeUp .5s ease both;}
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.card-header{background:linear-gradient(135deg,var(--teal),var(--teal-d));padding:28px 32px;position:relative;overflow:hidden;}
.card-header::after{content:'🎓';position:absolute;right:28px;top:50%;transform:translateY(-50%);font-size:52px;opacity:.13;}
.card-header h2{color:white;font-size:20px;font-weight:800;margin-bottom:4px;}
.card-header p{color:rgba(255,255,255,.75);font-size:13px;}
/* STEPS */
.steps{display:flex;align-items:center;padding:22px 32px 0;gap:6px;}
.step-item{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:700;color:#aac5c6;}
.step-num{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:11px;font-weight:800;background:var(--teal-l);color:#aac5c6;flex-shrink:0;transition:all .3s;}
.step-item.done .step-num{background:var(--suc);color:white;}
.step-item.done{color:var(--suc);}
.step-item.act .step-num{background:var(--teal);color:white;box-shadow:0 0 0 3px rgba(13,115,119,.2);}
.step-item.act{color:var(--teal);}
.step-div{flex:1;height:2px;background:var(--border);max-width:60px;}
.step-div.done{background:var(--teal);}
/* FORM */
.card-body{padding:28px 32px 32px;}
.section-label{font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--teal);background:var(--teal-l);border:1px solid var(--border);padding:4px 12px;border-radius:20px;display:inline-block;margin-bottom:16px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--mid);margin-bottom:7px;}
.field label .req{color:var(--err);margin-left:2px;}
.input-wrap{position:relative;}
.input-wrap i.icon-l{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#aac5c6;font-size:13px;pointer-events:none;}
.input-wrap input{width:100%;padding:11px 12px 11px 38px;border:2px solid var(--border);border-radius:10px;font-family:inherit;font-size:14px;color:var(--dark);background:var(--light);transition:all .2s;outline:none;}
.input-wrap input:focus{border-color:var(--teal);background:white;box-shadow:0 0 0 4px rgba(13,115,119,.08);}
.toggle-pw{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#aac5c6;font-size:13px;}
.toggle-pw:hover{color:var(--teal);}
.hint{font-size:11px;color:#9ab5b6;margin-top:5px;}
.row{display:grid;gap:16px;}
.row.cols2{grid-template-columns:1fr 1fr;}
.divider{height:1px;background:var(--border);margin:20px 0;}
.alert{padding:12px 16px;border-radius:10px;font-size:13px;display:flex;align-items:center;gap:10px;margin-bottom:20px;line-height:1.5;}
.alert-danger{background:#fdf0ef;border:1.5px solid #f5c6c2;color:var(--err);}
.alert-success{background:#e8f7f3;border:1.5px solid #b6e8da;color:var(--suc);}
/* VERIFIED BANNER */
.verified-banner{background:#e8f7f3;border:2px solid #b6e8da;border-radius:12px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:12px;}
.verified-banner i{font-size:22px;color:var(--suc);flex-shrink:0;}
.verified-banner strong{font-size:13px;color:var(--suc);display:block;}
.verified-banner span{font-size:12px;color:#3d7a60;}
/* SCAN BOX */
.scan-box{background:#0f1f20;border-radius:12px;overflow:hidden;position:relative;height:180px;margin-bottom:12px;}
.scan-box video{width:100%;height:100%;object-fit:cover;display:block;}
.scan-frame-box{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:80%;height:60px;border:2px solid var(--teal);border-radius:4px;box-shadow:0 0 0 9999px rgba(0,0,0,.5);}
.scan-line{position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--teal),transparent);animation:scanl 1.8s linear infinite;}
@keyframes scanl{0%{top:5%}100%{top:95%}}
.scan-ph{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:rgba(255,255,255,.3);gap:8px;}
.scan-st{position:absolute;bottom:6px;left:0;right:0;text-align:center;font-size:11px;font-weight:600;color:white;text-shadow:0 1px 3px rgba(0,0,0,.6);}
#interactive{width:100%;height:100%;}
/* TYPE TOGGLE */
.type-toggle{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;}
.type-option{display:none;}
.type-label{display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border:2px solid var(--border);border-radius:10px;cursor:default;font-size:14px;font-weight:600;color:var(--mid);background:var(--light);}
.type-option:checked + .type-label{border-color:var(--teal);background:var(--teal-l);color:var(--teal);}
/* BTNS */
.submit-btn{width:100%;padding:14px;background:var(--teal);border:none;border-radius:10px;color:white;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;}
.submit-btn:hover{background:var(--teal-d);}
.btn-scan{width:100%;padding:11px;background:var(--teal-l);border:2px solid var(--border);border-radius:10px;color:var(--teal);font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:8px;transition:all .2s;}
.btn-scan:hover{background:var(--teal);color:white;border-color:var(--teal);}
.btn-stop{background:#fdf0ef;border-color:#f5c6c2;color:var(--err);}
.btn-stop:hover{background:var(--err);color:white;border-color:var(--err);}
.or-div{display:flex;align-items:center;gap:12px;margin:14px 0;color:#aac5c6;font-size:12px;font-weight:600;}
.or-div::before,.or-div::after{content:'';flex:1;height:1px;background:var(--border);}
.login-link{text-align:center;margin-top:18px;font-size:13px;color:var(--mid);}
.login-link a{color:var(--teal);font-weight:600;text-decoration:none;}
footer{text-align:center;padding:18px;font-size:11px;color:#aaa;border-top:1px solid var(--border);}
@media(max-width:540px){.row.cols2{grid-template-columns:1fr;}.card-body{padding:24px;}}
</style>
</head>
<body>
<div class="topbar">
    <a class="topbar-brand" href="../index.php"><div class="icon">🔒</div> Smart Locker System</a>
    <a href="login.php" class="topbar-back"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
</div>

<div class="main">
<div class="signup-card">
    <div class="card-header">
        <h2>Daftar Akaun Institusi</h2>
        <p>Untuk Pelajar &amp; Staf sahaja — pengesahan matrik diperlukan</p>
    </div>

    <!-- STEP INDICATOR -->
    <div class="steps">
        <div class="step-item <?php echo $step >= 1 ? ($step > 1 ? 'done' : 'act') : ''; ?>">
            <div class="step-num"><?php echo $step > 1 ? '<i class="fas fa-check" style="font-size:9px"></i>' : '1'; ?></div>
            Sahkan Matrik
        </div>
        <div class="step-div <?php echo $step > 1 ? 'done' : ''; ?>"></div>
        <div class="step-item <?php echo $step >= 2 ? 'act' : ''; ?>">
            <div class="step-num">2</div>
            Daftar Akaun
        </div>
    </div>

    <div class="card-body">

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle" style="flex-shrink:0"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle" style="flex-shrink:0"></i><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- ══════════════ STEP 1: VERIFY MATRIC ══════════════ -->
    <div class="section-label"><i class="fas fa-id-card" style="margin-right:5px"></i>Langkah 1 — Sahkan Identiti</div>

    <form method="POST">
        <input type="hidden" name="step" value="1">
        <div class="field">
            <label>Nombor Matrik / ID Staf <span class="req">*</span></label>
            <div class="input-wrap">
                <i class="fas fa-id-badge icon-l"></i>
                <input type="text" name="id_number" id="matricInp"
                       placeholder="Contoh: MC2516203265"
                       value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>"
                       style="text-transform:uppercase" autocomplete="off" autofocus required>
            </div>
            <p class="hint"><i class="fas fa-info-circle" style="margin-right:4px"></i>Taip nombor matrik / ID staf yang tertera pada kad anda</p>
        </div>

        <!-- SCAN OPTION (collapsible) -->
        <div style="margin-bottom:16px">
            <button type="button" onclick="toggleScanPanel()" id="btnToggleScan"
                style="width:100%;padding:9px;background:var(--light);border:2px dashed var(--border);border-radius:9px;color:var(--mid);font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:all .2s">
                <i class="fas fa-camera"></i> Guna Kamera Scan Barcode (pilihan)
                <i class="fas fa-chevron-down" id="chevron" style="font-size:10px;transition:transform .2s"></i>
            </button>
            <div id="scanPanel" style="display:none;margin-top:10px">
                <div class="scan-box">
                    <div class="scan-ph" id="scanPh"><i class="fas fa-barcode" style="font-size:28px"></i><span style="font-size:12px;font-weight:600">Tekan butang di bawah untuk buka kamera</span></div>
                    <div id="interactive" style="display:none"></div>
                    <div id="scanFrame" style="display:none"><div class="scan-frame-box"><div class="scan-line"></div></div></div>
                    <div class="scan-st" id="scanSt"></div>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn-scan" id="btnStart" onclick="startScan()" style="flex:1"><i class="fas fa-camera"></i> Buka Kamera</button>
                    <button type="button" class="btn-scan btn-stop" id="btnStop" onclick="stopScan()" style="display:none;flex:1"><i class="fas fa-stop"></i> Stop</button>
                </div>
                <div id="scanResult" style="margin-top:6px"></div>
                <p style="font-size:11px;color:#aac5c6;text-align:center;margin-top:6px">
                    <i class="fas fa-exclamation-triangle" style="margin-right:4px;color:#c87f00"></i>
                    Kadang-kadang scan tidak tepat — semak nombor yang dikesan sebelum teruskan
                </p>
            </div>
        </div>

        <button type="submit" class="submit-btn"><i class="fas fa-search"></i> Semak & Teruskan</button>
    </form>

    <?php else: ?>
    <!-- ══════════════ STEP 2: REGISTER FORM ══════════════ -->
    <div class="verified-banner">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong>✓ Matrik / ID Disahkan</strong>
            <span><?php echo htmlspecialchars($v_matric); ?><?php echo $v_name ? ' — '.htmlspecialchars($v_name) : ''; ?></span>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="step" value="2">
        <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($v_type); ?>">

        <div class="section-label">Jenis Akaun</div>
        <div class="type-toggle">
            <input type="radio" id="typeStudent" class="type-option" <?php echo $v_type==='student'?'checked':''; ?> disabled>
            <label for="typeStudent" class="type-label"><i class="fas fa-user-graduate"></i> Pelajar</label>
            <input type="radio" id="typeStaff" class="type-option" <?php echo $v_type==='staff'?'checked':''; ?> disabled>
            <label for="typeStaff" class="type-label"><i class="fas fa-chalkboard-teacher"></i> Staf</label>
        </div>

        <div class="section-label">Maklumat Peribadi</div>
        <div class="row cols2">
            <div class="field">
                <label>Nama Penuh <span class="req">*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-user icon-l"></i>
                    <input type="text" name="full_name" required placeholder="Nama penuh anda"
                           value="<?php echo htmlspecialchars($v_name ?: ($_POST['full_name'] ?? '')); ?>">
                </div>
            </div>
            <div class="field">
                <label>No. Telefon</label>
                <div class="input-wrap">
                    <i class="fas fa-phone icon-l"></i>
                    <input type="tel" name="phone" placeholder="0123456789"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
            </div>
        </div>
        <div class="field">
            <label>Institusi <span class="req">*</span></label>
            <div class="input-wrap">
                <i class="fas fa-university icon-l"></i>
                <input type="text" name="institution" required placeholder="Contoh: Matrikulasi Kedah"
                       value="<?php echo htmlspecialchars($v_inst ?: ($_POST['institution'] ?? '')); ?>">
            </div>
        </div>

        <div class="divider"></div>
        <div class="section-label">Maklumat Akaun</div>
        <div class="field">
            <label>Alamat E-mel <span class="req">*</span></label>
            <div class="input-wrap">
                <i class="fas fa-envelope icon-l"></i>
                <input type="email" name="email" required placeholder="your@email.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
        </div>
        <div class="row cols2">
            <div class="field">
                <label>Kata Laluan <span class="req">*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-lock icon-l"></i>
                    <input type="password" name="password" id="pw1" required placeholder="Min. 6 aksara">
                    <button type="button" class="toggle-pw" onclick="togglePw('pw1','e1')"><i class="fas fa-eye" id="e1"></i></button>
                </div>
            </div>
            <div class="field">
                <label>Sahkan Kata Laluan <span class="req">*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-lock icon-l"></i>
                    <input type="password" name="confirm_password" id="pw2" required placeholder="Ulang kata laluan">
                    <button type="button" class="toggle-pw" onclick="togglePw('pw2','e2')"><i class="fas fa-eye" id="e2"></i></button>
                </div>
            </div>
        </div>

        <button type="submit" class="submit-btn"><i class="fas fa-user-plus"></i> Daftar Akaun</button>
    </form>
    <p style="text-align:center;margin-top:12px;font-size:12px;">
        <a href="signup.php" style="color:var(--mid);text-decoration:none"><i class="fas fa-arrow-left" style="margin-right:5px"></i>Guna matrik lain</a>
    </p>
    <?php endif; ?>

    <p class="login-link">Sudah ada akaun? <a href="login.php">Login di sini</a></p>
    </div>
</div>
</div>
<footer>© 2026 Smart Locker System — Institution Version</footer>

<script>
// ── TOGGLE SCAN PANEL ──
function toggleScanPanel(){
    const panel=document.getElementById('scanPanel');
    const chevron=document.getElementById('chevron');
    const btn=document.getElementById('btnToggleScan');
    if(panel.style.display==='none'){
        panel.style.display='block';
        chevron.style.transform='rotate(180deg)';
        btn.style.borderColor='var(--teal)';
        btn.style.color='var(--teal)';
    } else {
        panel.style.display='none';
        chevron.style.transform='';
        btn.style.borderColor='var(--border)';
        btn.style.color='var(--mid)';
        stopScan();
    }
}

// ── QUAGGA SCAN (debounce: sama 3x baru accept) ──
let lastCode='', sameCount=0;

function startScan(){
    document.getElementById('scanPh').style.display='none';
    document.getElementById('interactive').style.display='block';
    document.getElementById('scanFrame').style.display='block';
    document.getElementById('btnStart').style.display='none';
    document.getElementById('btnStop').style.display='flex';
    document.getElementById('scanSt').textContent='🔍 Scanning... arahkan ke barcode';
    lastCode=''; sameCount=0;

    Quagga.init({
        inputStream:{
            name:'Live', type:'LiveStream',
            target:document.getElementById('interactive'),
            constraints:{facingMode:'environment',width:640,height:480}
        },
        decoder:{readers:['code_128_reader','code_39_reader','ean_reader','ean_8_reader']},
        locate:true
    }, function(err){
        if(err){ document.getElementById('scanSt').textContent='❌ '+err.message; stopScan(); return; }
        Quagga.start();
    });

    Quagga.onDetected(function(result){
        const code=result.codeResult.code;
        if(!code) return;
        // Debounce — kena detect sama 3x berturut-turut
        if(code===lastCode){ sameCount++; }
        else{ lastCode=code; sameCount=1; }
        document.getElementById('scanSt').textContent='🔍 Mengesahkan... ('+sameCount+'/3) '+code;
        if(sameCount>=3){
            stopScan();
            const val=code.trim().toUpperCase();
            document.getElementById('matricInp').value=val;
            document.getElementById('scanResult').innerHTML=
                '<div style="background:#e8f7f3;border:1.5px solid #b6e8da;border-radius:9px;padding:10px 13px;font-size:12px;color:#0a7c63;display:flex;align-items:center;gap:8px">'+
                '<i class="fas fa-check-circle"></i> Dikesan: <strong>'+val+'</strong> — Semak dan tekan <strong>Semak & Teruskan</strong></div>';
        }
    });
}

function stopScan(){
    try{ Quagga.stop(); }catch(e){}
    const interactive=document.getElementById('interactive');
    if(interactive){ interactive.style.display='none'; interactive.innerHTML=''; }
    document.getElementById('scanPh').style.display='flex';
    document.getElementById('scanFrame').style.display='none';
    document.getElementById('btnStart').style.display='flex';
    document.getElementById('btnStop').style.display='none';
    document.getElementById('scanSt').textContent='';
    lastCode=''; sameCount=0;
}

function togglePw(id,eyeId){
    const i=document.getElementById(id),e=document.getElementById(eyeId);
    if(i.type==='password'){i.type='text';e.classList.replace('fa-eye','fa-eye-slash');}
    else{i.type='password';e.classList.replace('fa-eye-slash','fa-eye');}
}
<?php if($step===2): ?>
document.getElementById('pw2').addEventListener('input',function(){
    this.style.borderColor=document.getElementById('pw1').value===this.value?'#0d7377':'#c0392b';
});
<?php endif; ?>
</script>
</body>
</html>