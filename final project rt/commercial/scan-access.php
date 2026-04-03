<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_COMMERCIAL');
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'config.php';

$error   = '';
$success = '';
$user_info   = null;
$locker_info = null;

// Fetch semua locker user untuk NFC picker
$user_lockers = [];
try {
    $stmt_lockers = $pdo->prepare("
        SELECT 
            l.id,
            l.device_id,
            COALESCE(ula.custom_name, l.unique_code) AS name,
            COALESCE(ula.custom_location, '')          AS location,
            ula.key_value
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.user_id  = ?
          AND ula.is_active = 1
          AND l.status     != 'maintenance'
        ORDER BY name ASC
    ");
    $stmt_lockers->execute([$_SESSION['user_id']]);
    $user_lockers = $stmt_lockers->fetchAll();
} catch (Exception $e) {
    $user_lockers = [];
}

// Handle Camera Scan POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input  = sanitize($_POST['scan_data'] ?? '');
    $manual_key = sanitize($_POST['access_key'] ?? '');
    $manual_lid = sanitize($_POST['locker_id_input'] ?? '');

    $access_key      = '';
    $locker_id_input = '';

    $qr_decoded = json_decode($raw_input, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($qr_decoded['access_key'], $qr_decoded['locker_id'])) {
        $access_key      = $qr_decoded['access_key'];
        $locker_id_input = $qr_decoded['locker_id'];
    } elseif (!empty($manual_key) && !empty($manual_lid)) {
        $access_key      = $manual_key;
        $locker_id_input = $manual_lid;
    } else {
        $error = 'Sila scan QR Code atau masukkan Access Key dan Locker ID.';
    }

    if (empty($error) && !empty($access_key) && !empty($locker_id_input)) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    ula.id, ula.user_id, ula.locker_id, ula.key_value,
                    u.full_name, u.user_id_number, u.user_type, u.institution,
                    l.unique_code, l.device_id,
                    COALESCE(ula.custom_name, l.name, l.unique_code) AS locker_name,
                    COALESCE(ula.custom_location, l.location, '')    AS location,
                    l.status AS locker_status
                FROM user_locker_assignments ula
                JOIN users   u ON u.id  = ula.user_id
                JOIN lockers l ON l.id  = ula.locker_id
                WHERE ula.locker_id  = ?
                  AND ula.key_value  = ?
                  AND ula.user_id    = ?
                  AND ula.is_active  = 1
                  AND u.is_active    = 1
                  AND l.status      != 'maintenance'
                LIMIT 1
            ");
            $stmt->execute([$locker_id_input, $access_key, $_SESSION['user_id']]);
            $match = $stmt->fetch();

            if ($match) {
                $user_info   = $match;
                $locker_info = $match;
                $success     = 'Access granted to ' . htmlspecialchars($match['locker_name']);

                $stmt2 = $pdo->prepare("
                    INSERT INTO activity_logs 
                        (user_id, locker_id, device_id, access_method, key_used, success, timestamp)
                    VALUES (?, ?, ?, 'qr_code', ?, 1, NOW())
                ");
                $stmt2->execute([
                    $match['user_id'],
                    $match['locker_id'],
                    $match['device_id'] ?? 'scan_station',
                    $access_key
                ]);
            } else {
                $stmt3 = $pdo->prepare("
                    SELECT COUNT(*) FROM user_locker_assignments
                    WHERE user_id = ? AND locker_id = ? AND is_active = 1
                ");
                $stmt3->execute([$_SESSION['user_id'], $locker_id_input]);
                $has_locker = $stmt3->fetchColumn();
                $error = $has_locker
                    ? 'Access Key tidak sah untuk locker ini.'
                    : 'Locker tidak dijumpai atau anda tiada akses ke locker ini.';
            }
        } catch (Exception $e) {
            $error = 'Scan error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner - Smart Locker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background:#1a1a2e; color:white; min-height:100vh; }
        .scanner-container { max-width:800px; margin:0 auto; background:#16213e; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,.5); }
        .scanner-header { background:linear-gradient(135deg,#0f3460,#1a1a2e); border-radius:15px 15px 0 0; padding:25px; text-align:center; }
        .camera-view { width:100%; height:300px; background:#000; border-radius:10px; overflow:hidden; position:relative; margin:20px 0; }
        #video { width:100%; height:100%; object-fit:cover; }
        .scan-overlay { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:250px; height:250px; border:3px solid #00ff88; border-radius:15px; box-shadow:0 0 20px rgba(0,255,136,.3); }
        .result-card { background:#0f3460; border-radius:10px; padding:20px; margin:15px 0; border-left:4px solid #00ff88; }
        .btn-scan { background:linear-gradient(135deg,#00ff88,#00cc6a); color:#000; font-weight:bold; border:none; }
        .btn-scan:hover { background:linear-gradient(135deg,#00cc6a,#00aa55); }
        .tab-btn { padding:12px 30px; border:none; border-radius:8px; font-weight:bold; cursor:pointer; transition:all .3s; font-size:15px; }
        .tab-btn.active  { background:linear-gradient(135deg,#00ff88,#00cc6a); color:#000; }
        .tab-btn.inactive { background:rgba(255,255,255,.1); color:white; }

        /* NFC Styles */
        .nfc-locker-card { background:rgba(0,180,255,.05); border:1px solid rgba(0,180,255,.2); border-radius:10px; padding:15px; cursor:pointer; transition:all .3s; }
        .nfc-locker-card:hover { background:rgba(0,180,255,.12); border-color:#00b4ff; transform:translateX(4px); }
        .nfc-step { display:flex; align-items:center; gap:15px; background:rgba(0,180,255,.05); border:1px solid rgba(0,180,255,.2); border-radius:10px; padding:12px 16px; margin-bottom:10px; text-align:left; }
        .nfc-step-icon { font-size:24px; min-width:36px; text-align:center; color:#00b4ff; }
        .nfc-step p { margin:0; font-size:14px; color:#ccc; }
        .nfc-step strong { color:white; display:block; margin-bottom:2px; }

        /* NFC Pulse Animation */
        .nfc-wrapper { position:relative; width:140px; height:140px; margin:15px auto 20px; }
        .nfc-ring { position:absolute; border-radius:50%; border:2px solid #00b4ff; top:50%; left:50%; transform:translate(-50%,-50%) scale(0); opacity:0; animation:nfcPulse 2.5s ease-out infinite; }
        .nfc-ring:nth-child(1) { width:40px;  height:40px;  animation-delay:0s; }
        .nfc-ring:nth-child(2) { width:80px;  height:80px;  animation-delay:.6s; }
        .nfc-ring:nth-child(3) { width:120px; height:120px; animation-delay:1.2s; }
        .nfc-ring:nth-child(4) { width:140px; height:140px; animation-delay:1.8s; }
        @keyframes nfcPulse {
            0%   { transform:translate(-50%,-50%) scale(0); opacity:.8; }
            100% { transform:translate(-50%,-50%) scale(1); opacity:0; }
        }
        .nfc-icon-center { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:32px; color:#00b4ff; filter:drop-shadow(0 0 10px #00b4ff); animation:nfcGlow 1.5s ease-in-out infinite; }
        @keyframes nfcGlow {
            0%,100% { filter:drop-shadow(0 0 8px #00b4ff); }
            50%     { filter:drop-shadow(0 0 20px #00b4ff) drop-shadow(0 0 35px #00b4ff); }
        }
    </style>
</head>
<body>
<div class="container py-4">
<div class="scanner-container">

    <!-- Header -->
    <div class="scanner-header">
        <div class="text-end mb-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i>Dashboard
            </a>
        </div>
        <h1 class="mb-3"><i class="fas fa-qrcode"></i> SMART LOCKER SCANNER</h1>
        <div class="d-flex justify-content-center gap-3 mt-3">
            <button class="tab-btn active"   id="tabCamera" onclick="switchTab('camera')">
                <i class="fas fa-camera me-2"></i> Camera Scan
            </button>
            <button class="tab-btn inactive" id="tabNFC"    onclick="switchTab('nfc')">
                <i class="fas fa-wifi me-2"></i> NFC Card
            </button>
        </div>
        <p class="mb-0 mt-2">Scan QR Code or tap your NFC card to access locker</p>
        <small class="text-muted">Compatible with: personal use, institutional use and office locker</small>
    </div>

    <div class="p-4">
        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ══ CAMERA SECTION ══ -->
        <div id="sectionCamera">
            <div class="camera-view">
                <video id="video" autoplay playsinline></video>
                <div class="scan-overlay" id="scanOverlay" style="display:none;"></div>
                <div id="cameraStatus">📷 Memulakan kamera...</div>
            </div>

            <form method="POST" id="scanForm">
                <input type="hidden" name="scan_data" id="scanData">
                <div class="row mb-3 mt-3">
                    <div class="col-12 mb-2">
                        <label class="form-label fw-bold"><i class="fas fa-key me-1"></i> Access Key</label>
                        <input type="text" class="form-control form-control-lg" name="access_key" id="accessKeyInput"
                               placeholder="Masukkan Access Key (auto-isi bila scan QR)"
                               value="<?php echo isset($_POST['access_key']) ? htmlspecialchars($_POST['access_key']) : ''; ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-hashtag me-1"></i> Locker ID</label>
                        <input type="number" class="form-control form-control-lg" name="locker_id_input" id="lockerIdInput"
                               placeholder="Masukkan Locker ID (auto-isi bila scan QR)"
                               value="<?php echo isset($_POST['locker_id_input']) ? htmlspecialchars($_POST['locker_id_input']) : ''; ?>">
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-scan btn-lg">
                        <i class="fas fa-search me-2"></i> VERIFY & ACCESS
                    </button>
                    <button type="button" class="btn btn-outline-light btn-lg" onclick="startCamera()">
                        <i class="fas fa-camera me-2"></i> START CAMERA
                    </button>
                </div>
            </form>

            <!-- ACCESS GRANTED (Camera) -->
            <?php if ($user_info && $locker_info): ?>
            <div class="result-card mt-4">
                <h4 style="color:#00ff88;"><i class="fas fa-check-circle me-2"></i> ✅ ACCESS GRANTED</h4>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user_info['full_name']); ?></p>
                        <p><strong>ID Number:</strong> <?php echo htmlspecialchars($user_info['user_id_number']); ?></p>
                        <p><strong>Type:</strong> <?php echo ucfirst($user_info['user_type']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Locker:</strong> <?php echo htmlspecialchars($locker_info['locker_name']); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($locker_info['location']); ?></p>
                        <p><strong>Status:</strong> <span class="badge bg-success">ACCESS GRANTED</span></p>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary me-2" onclick="openLocker()"><i class="fas fa-unlock me-2"></i> OPEN LOCKER</button>
                    <button class="btn btn-danger"       onclick="closeLocker()"><i class="fas fa-lock me-2"></i> CLOSE LOCKER</button>
                </div>
            </div>
            <?php endif; ?>
        </div><!-- /sectionCamera -->

        <!-- ══ NFC SECTION ══ -->
        <div id="sectionNFC" style="display:none;">

            <!-- Step 1: Pilih Locker -->
            <div id="nfcPickLocker" class="p-2">
                <h5 class="mb-3" style="color:#00b4ff;">
                    <i class="fas fa-wifi me-2"></i> Choose Locker for NFC Card Access
                </h5>

                <?php if (empty($user_lockers)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> Tiada locker assigned kepada kamu.
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($user_lockers as $ul): ?>
                    <div class="col-12">
                        <div class="nfc-locker-card"
                             data-id="<?php echo (int)$ul['id']; ?>"
                             data-name="<?php echo htmlspecialchars($ul['name'] ?? '', ENT_QUOTES); ?>"
                             data-location="<?php echo htmlspecialchars($ul['location'] ?? '', ENT_QUOTES); ?>"
                             data-key="<?php echo htmlspecialchars($ul['key_value'], ENT_QUOTES); ?>"
                             data-device="<?php echo htmlspecialchars($ul['device_id'] ?? '', ENT_QUOTES); ?>"
                             onclick="selectLockerNFC(this)">
                            <div class="d-flex align-items-center gap-3">
                                <div style="font-size:28px; color:#00b4ff;"><i class="fas fa-box"></i></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($ul['name'] ?? ''); ?></strong>
                                    <p class="mb-0 text-muted" style="font-size:13px;">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($ul['location'] ?? ''); ?>
                                    </p>
                                </div>
                                <div class="ms-auto"><i class="fas fa-chevron-right" style="color:#00b4ff;"></i></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Step 2: Waiting for NFC tap -->
            <div id="nfcScanSection" style="display:none;">

                <div id="nfcWaitingSection">
                    <div style="text-align:center; padding:20px 0;">

                        <button onclick="backToNFCPick()"
                            style="background:rgba(255,255,255,.1);border:none;color:white;padding:6px 14px;border-radius:6px;margin-bottom:15px;cursor:pointer;">
                            <i class="fas fa-arrow-left me-2"></i> Change Locker
                        </button>

                        <!-- Locker info -->
                        <div style="background:rgba(0,180,255,.08);border:1px solid rgba(0,180,255,.2);border-radius:10px;padding:10px 16px;margin-bottom:20px;text-align:left;">
                            <strong id="nfcWaitingLockerName" style="color:#00b4ff;"></strong>
                            <p class="mb-0" style="font-size:13px;color:#aaa;">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <span id="nfcWaitingLockerLocation"></span>
                            </p>
                        </div>

                        <!-- NFC Animation -->
                        <div class="nfc-wrapper">
                            <div class="nfc-ring"></div>
                            <div class="nfc-ring"></div>
                            <div class="nfc-ring"></div>
                            <div class="nfc-ring"></div>
                            <i class="fas fa-wifi nfc-icon-center"></i>
                        </div>

                        <h5 class="mb-1">Tap NFC Card / Key Fob at Locker</h5>
                        <p id="nfcStatus" style="color:#00b4ff; margin-bottom:20px;">Waiting for card tap...</p>

                        <!-- Instructions -->
                        <div style="max-width:420px;margin:0 auto;">
                            <div class="nfc-step">
                                <div class="nfc-step-icon"><i class="fas fa-walking"></i></div>
                                <div><strong>Go to your locker</strong><p>Walk to the locker you selected</p></div>
                            </div>
                            <div class="nfc-step">
                                <div class="nfc-step-icon"><i class="fas fa-id-card"></i></div>
                                <div><strong>Tap your card</strong><p>Hold your NFC card or key fob near the reader</p></div>
                            </div>
                            <div class="nfc-step">
                                <div class="nfc-step-icon"><i class="fas fa-bolt"></i></div>
                                <div><strong>Auto unlock</strong><p>Locker will unlock automatically</p></div>
                            </div>
                        </div>
                    </div>

                    <!-- Error result -->
                    <div id="nfcErrorResult" style="display:none; text-align:center; padding:20px;">
                        <i class="fas fa-times-circle fa-4x mb-3" style="color:#ff6b6b;"></i>
                        <h4 id="nfcErrorMsg" style="color:#ff6b6b;"></h4>
                    </div>
                </div>

                <!-- Step 3: ACCESS GRANTED -->
                <div class="result-card mt-4" id="nfcAccessResult" style="border-left-color:#00b4ff; display:none;">
                    <h4 style="color:#00b4ff;"><i class="fas fa-check-circle me-2"></i> ✅ NFC ACCESS GRANTED</h4>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p><strong>Locker:</strong> <span id="nfcResultLockerName">-</span></p>
                            <p><strong>Location:</strong> <span id="nfcResultLocation">-</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <span class="badge bg-success">ACCESS GRANTED</span></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary me-2" onclick="openLockerNFC()"><i class="fas fa-unlock me-2"></i> OPEN LOCKER</button>
                        <button class="btn btn-danger"       onclick="closeLockerNFC()"><i class="fas fa-lock me-2"></i> CLOSE LOCKER</button>
                    </div>
                </div>
            </div>
        </div><!-- /sectionNFC -->

        <!-- Footer -->
        <div class="text-center mt-4 text-muted">
            <small>&copy; <?php echo date('Y'); ?> Smart Locker System - Commercial Edition v2.0</small>
        </div>
    </div>
</div>
</div>

<!-- jsQR + Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ══════════════════════════════════════════
// CAMERA SCAN
// ══════════════════════════════════════════
let videoStream = null, scanning = false, animFrameId = null;
let cooldown = false, lastScanned = '';
let passwordVerified = <?php echo ($user_info && $locker_info) ? 'true' : 'false'; ?>;
const video = document.getElementById('video');
const scanCanvas = document.createElement('canvas');
const scanCtx = scanCanvas.getContext('2d', { willReadFrequently: true });

function setCameraStatus(msg, color) {
    const el = document.getElementById('cameraStatus');
    if (el) { el.textContent = msg; el.style.color = color || '#00ff88'; }
}

window.addEventListener('DOMContentLoaded', () => { startCamera(); });

async function startCamera() {
    const btn = document.querySelector('button[onclick="startCamera()"]');
    if (btn) { btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Starting...'; btn.disabled = true; }
    setCameraStatus('📷 Meminta akses kamera...', '#aaa');
    try {
        if (videoStream) videoStream.getTracks().forEach(t => t.stop());
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
        });
        video.srcObject = stream;
        videoStream = stream;
        video.oncanplay = () => {
            video.play();
            scanning = true;
            document.getElementById('scanOverlay').style.display = 'block';
            setCameraStatus('🔍 Mengimbas... Tuju kamera ke QR Code', '#00ff88');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-stop me-2"></i> STOP CAMERA';
                btn.disabled = false;
                btn.setAttribute('onclick', 'stopCamera()');
                btn.classList.replace('btn-outline-light', 'btn-danger');
            }
            animFrameId = requestAnimationFrame(scanQRCode);
        };
    } catch (err) {
        let msg = '❌ ' + err.message;
        if (err.name === 'NotAllowedError') msg = '❌ Permission kamera ditolak.';
        else if (err.name === 'NotFoundError') msg = '❌ Tiada kamera dijumpai.';
        setCameraStatus(msg, '#ff6b6b');
        if (btn) { btn.innerHTML = '<i class="fas fa-camera me-2"></i> START CAMERA'; btn.disabled = false; }
    }
}

function stopCamera() {
    scanning = false;
    if (animFrameId) { cancelAnimationFrame(animFrameId); animFrameId = null; }
    if (videoStream) { videoStream.getTracks().forEach(t => t.stop()); videoStream = null; }
    video.srcObject = null;
    document.getElementById('scanOverlay').style.display = 'none';
    setCameraStatus('📷 Kamera dihentikan', '#aaa');
    const btn = document.querySelector('button[onclick="stopCamera()"]');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-camera me-2"></i> START CAMERA';
        btn.disabled = false;
        btn.setAttribute('onclick', 'startCamera()');
        btn.classList.replace('btn-danger', 'btn-outline-light');
    }
}

function scanQRCode() {
    if (!scanning) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
        scanCanvas.width = video.videoWidth;
        scanCanvas.height = video.videoHeight;
        scanCtx.drawImage(video, 0, 0, scanCanvas.width, scanCanvas.height);
        try {
            const imageData = scanCtx.getImageData(0, 0, scanCanvas.width, scanCanvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
            if (code && code.data && code.data !== lastScanned && !cooldown) {
                lastScanned = code.data;
                cooldown = true;
                scanning = false;
                processQRData(code.data);
                return;
            }
        } catch (e) {}
    }
    animFrameId = requestAnimationFrame(scanQRCode);
}

function processQRData(raw) {
    document.getElementById('scanData').value = raw;
    try {
        const qr = JSON.parse(raw);
        if (qr.access_key && qr.locker_id) {
            document.getElementById('accessKeyInput').value = qr.access_key;
            document.getElementById('lockerIdInput').value  = qr.locker_id;
            setCameraStatus('✅ QR Code berjaya dikesan! Tekan VERIFY & ACCESS.', '#00ff88');
            if (videoStream) videoStream.getTracks().forEach(t => t.stop());
            return;
        }
    } catch (e) {}
    setCameraStatus('⚠️ Format QR tidak dikenali. Sila masukkan manual.', '#ffaa00');
    document.getElementById('accessKeyInput').value = raw;
}

function openLocker() {
    const key = document.getElementById('accessKeyInput').value;
    const lid = document.getElementById('lockerIdInput').value;
    if (!key || !lid) { alert('Tiada data locker.'); return; }
    fetch('api/open_locker.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ access_key: key, locker_id: lid })
    }).then(r => r.json()).then(d => alert(d.success ? '✅ Locker berjaya dibuka!' : '❌ ' + d.message))
      .catch(e => alert('Network error: ' + e));
}

function closeLocker() {
    const key = document.getElementById('accessKeyInput').value;
    const lid = document.getElementById('lockerIdInput').value;
    if (!key || !lid) { alert('Tiada data locker.'); return; }
    fetch('api/close_locker.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ access_key: key, locker_id: lid })
    }).then(r => r.json()).then(d => alert(d.success ? '🔒 Locker berjaya dikunci!' : '❌ ' + d.message))
      .catch(e => alert('Network error: ' + e));
}

// ══════════════════════════════════════════
// TAB SWITCHER
// ══════════════════════════════════════════
function switchTab(tab) {
    if (tab === 'camera') {
        document.getElementById('sectionCamera').style.display = 'block';
        document.getElementById('sectionNFC').style.display    = 'none';
        document.getElementById('tabCamera').className = 'tab-btn active';
        document.getElementById('tabNFC').className    = 'tab-btn inactive';
        stopNFCPolling();
        startCamera();
    } else {
        document.getElementById('sectionCamera').style.display = 'none';
        document.getElementById('sectionNFC').style.display    = 'block';
        document.getElementById('tabCamera').className = 'tab-btn inactive';
        document.getElementById('tabNFC').className    = 'tab-btn active';
        stopCamera();
        // Show locker picker
        document.getElementById('nfcPickLocker').style.display  = 'block';
        document.getElementById('nfcScanSection').style.display = 'none';
        stopNFCPolling();
    }
}

// ══════════════════════════════════════════
// NFC SECTION
// ══════════════════════════════════════════
let nfcPolling   = false;
let nfcInterval  = null;
let nfcLockerId  = null;
let nfcAccessKey = null;
let nfcDeviceId  = null;

function selectLockerNFC(el) {
    nfcLockerId  = el.dataset.id;
    nfcAccessKey = el.dataset.key;
    nfcDeviceId  = el.dataset.device;

    document.getElementById('nfcWaitingLockerName').textContent     = el.dataset.name;
    document.getElementById('nfcWaitingLockerLocation').textContent = el.dataset.location;

    document.getElementById('nfcPickLocker').style.display    = 'none';
    document.getElementById('nfcScanSection').style.display   = 'block';
    document.getElementById('nfcWaitingSection').style.display = 'block';
    document.getElementById('nfcAccessResult').style.display   = 'none';
    document.getElementById('nfcErrorResult').style.display    = 'none';

    startNFCPolling();
}

function backToNFCPick() {
    stopNFCPolling();
    document.getElementById('nfcScanSection').style.display = 'none';
    document.getElementById('nfcPickLocker').style.display  = 'block';
    document.getElementById('nfcAccessResult').style.display = 'none';
    nfcLockerId = null; nfcAccessKey = null; nfcDeviceId = null;
}

function startNFCPolling() {
    nfcPolling = true;
    document.getElementById('nfcStatus').textContent = 'Waiting for card tap...';
    document.getElementById('nfcStatus').style.color = '#00b4ff';

    nfcInterval = setInterval(() => {
        if (!nfcPolling) return;
        fetch(`api/nfc_poll.php?locker_id=${nfcLockerId}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                stopNFCPolling();
                showNFCResult(true, d.data);
            } else if (d.scanned && !d.success) {
                stopNFCPolling();
                showNFCResult(false, d.message);
            }
        })
        .catch(e => console.log('NFC poll error:', e));
    }, 2000);
}

function stopNFCPolling() {
    nfcPolling = false;
    if (nfcInterval) { clearInterval(nfcInterval); nfcInterval = null; }
}

function showNFCResult(success, data) {
    document.getElementById('nfcWaitingSection').style.display = 'none';

    if (success) {
        document.getElementById('nfcResultLockerName').textContent = data.locker_name ?? '-';
        document.getElementById('nfcResultLocation').textContent   = data.location    ?? '-';
        document.getElementById('nfcAccessResult').style.display   = 'block';
    } else {
        // Tunjuk error, balik ke waiting lepas 5s
        document.getElementById('nfcWaitingSection').style.display = 'block';
        document.getElementById('nfcErrorResult').style.display    = 'block';
        document.getElementById('nfcErrorMsg').textContent         = data;
        document.getElementById('nfcStatus').textContent           = '';

        setTimeout(() => {
            document.getElementById('nfcErrorResult').style.display    = 'none';
            document.getElementById('nfcWaitingSection').style.display = 'block';
            document.getElementById('nfcStatus').textContent = 'Waiting for card tap...';
            document.getElementById('nfcStatus').style.color = '#00b4ff';
            startNFCPolling();
        }, 5000);
    }
}

function openLockerNFC() {
    if (!nfcAccessKey || !nfcLockerId) { alert('Tiada data locker.'); return; }
    fetch('api/open_locker.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ access_key: nfcAccessKey, locker_id: nfcLockerId })
    }).then(r => r.json()).then(d => alert(d.success ? '✅ Locker berjaya dibuka!' : '❌ ' + d.message))
      .catch(e => alert('Network error: ' + e));
}

function closeLockerNFC() {
    if (!nfcAccessKey || !nfcLockerId) { alert('Tiada data locker.'); return; }
    fetch('api/close_locker.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ access_key: nfcAccessKey, locker_id: nfcLockerId })
    }).then(r => r.json()).then(d => alert(d.success ? '🔒 Locker berjaya dikunci!' : '❌ ' + d.message))
      .catch(e => alert('Network error: ' + e));
}
</script>

<!-- PASSWORD VERIFY MODAL (Camera tab) -->
<div class="modal fade" id="passwordModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#1a1a2e;border:1px solid #333;border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-white"><i class="fas fa-lock me-2" style="color:#00ff88;"></i>Pengesahan Password</h5>
            </div>
            <div class="modal-body pt-3">
                <p class="text-white-50 small mb-3">QR Code berjaya dikesan. Masukkan password akaun anda untuk teruskan.</p>
                <div class="mb-3">
                    <label class="text-white-50 small mb-1">Password</label>
                    <div class="input-group">
                        <input type="password" id="passwordInput" class="form-control"
                               style="background:#0d0d1a;border:1px solid #333;color:white;"
                               placeholder="Masukkan password anda"
                               onkeydown="if(event.key==='Enter') verifyPassword()">
                        <button class="btn" type="button" onclick="togglePwVisibility()"
                                style="background:#1a1a2e;border:1px solid #333;color:#888;">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <div id="passwordError" class="alert alert-danger py-2 small" style="display:none;">
                    <i class="fas fa-exclamation-circle me-1"></i><span id="passwordErrorMsg">Password salah.</span>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cancelPasswordModal()">
                    <i class="fas fa-times me-1"></i>Batal
                </button>
                <button type="button" class="btn btn-success btn-sm px-4" onclick="verifyPassword()" id="verifyBtn">
                    <i class="fas fa-check me-1"></i>Verify
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function togglePwVisibility() {
    const inp  = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
    else { inp.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
}
function cancelPasswordModal() {
    bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
    passwordVerified = false;
    document.getElementById('accessKeyInput').value = '';
    document.getElementById('lockerIdInput').value  = '';
    lastScanned = ''; cooldown = false;
}
function verifyPassword() {
    const password = document.getElementById('passwordInput').value;
    if (!password) {
        document.getElementById('passwordError').style.display = 'block';
        document.getElementById('passwordErrorMsg').textContent = 'Sila masukkan password.';
        return;
    }
    const btn = document.getElementById('verifyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Verifying...';
    fetch('api/verify_password.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password })
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Verify';
        if (d.success) {
            passwordVerified = true;
            bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
            setCameraStatus('✅ Password disahkan! Tekan Open Locker untuk membuka.', '#00ff88');
        } else {
            document.getElementById('passwordError').style.display = 'block';
            document.getElementById('passwordErrorMsg').textContent = d.message || 'Password salah.';
            document.getElementById('passwordInput').value = '';
            document.getElementById('passwordInput').focus();
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Verify';
        document.getElementById('passwordError').style.display = 'block';
        document.getElementById('passwordErrorMsg').textContent = 'Network error. Cuba lagi.';
    });
}
</script>

</body>
</html>