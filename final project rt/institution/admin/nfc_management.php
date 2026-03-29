<?php
// institution/admin/nfc_management.php
require_once '../config.php';
if (!isLoggedIn()) redirect('../login.php');

$admin_id   = (int)$_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Ambil device_id dari locker pertama yang ada (untuk polling register)
$nfc_device_id = '';
try {
    $dev = $pdo->query("SELECT device_id FROM lockers WHERE device_id IS NOT NULL AND device_id != '' LIMIT 1")->fetch();
    $nfc_device_id = $dev['device_id'] ?? '';
} catch(Exception $e) {}

// Fetch semua student dengan NFC status
try {
    $students = $pdo->query("
        SELECT id, full_name, user_id_number, user_type, institution,
               nfc_uid, nfc_registered_at
        FROM users
        WHERE is_active = 1
        ORDER BY full_name ASC
    ")->fetchAll();
} catch(Exception $e) { $students = []; }

// Stats
$total_students  = count($students);
$nfc_assigned    = count(array_filter($students, fn($s) => !empty($s['nfc_uid'])));
$nfc_unassigned  = $total_students - $nfc_assigned;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NFC Card Management — Smart Locker Institution</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="admin-style.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--teal:#0d7377;--teal-d:#085c60;--teal-l:#e8f6f7;--dark:#0f1f20;--mid:#3d5c5e;--light:#f4f9f9;--border:#d0e8e9;--sw:240px;}
body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--light);color:var(--dark);min-height:100vh;display:flex;}

/* MAIN */
.main{margin-left:var(--sw);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;}
.topbar h1{font-size:18px;font-weight:800;}
.topbar p{font-size:12px;color:var(--mid);margin-top:2px;}
.content{padding:28px 32px;flex:1;}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px;}
.stat-card{background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:20px;display:flex;align-items:center;gap:14px;}
.stat-ic{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.ic-teal{background:var(--teal-l);color:var(--teal);}
.ic-green{background:#e8f7f3;color:#0a7c63;}
.ic-amber{background:#fff8e1;color:#f59e0b;}
.stat-val{font-size:24px;font-weight:800;}
.stat-lbl{font-size:12px;color:var(--mid);}

/* Scanner Panel */
.scanner-panel{background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:24px;}
.sp-header{background:linear-gradient(135deg,var(--teal),var(--teal-d));padding:18px 24px;color:#fff;display:flex;align-items:center;gap:12px;}
.sp-header h2{font-size:15px;font-weight:800;}
.sp-header p{font-size:12px;opacity:.8;}
.sp-body{padding:24px;}
.sp-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;}

/* NFC Pulse */
.nfc-wrap{position:relative;width:100px;height:100px;margin:0 auto 16px;}
.nfc-ring{position:absolute;border-radius:50%;border:2px solid var(--teal);top:50%;left:50%;transform:translate(-50%,-50%) scale(0);opacity:0;animation:nfcRing 2s ease-out infinite;}
.nfc-ring:nth-child(1){width:32px;height:32px;animation-delay:0s}
.nfc-ring:nth-child(2){width:64px;height:64px;animation-delay:.5s}
.nfc-ring:nth-child(3){width:96px;height:96px;animation-delay:1s}
@keyframes nfcRing{0%{transform:translate(-50%,-50%) scale(0);opacity:.9}100%{transform:translate(-50%,-50%) scale(1);opacity:0}}
.nfc-ic{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:30px;color:var(--teal);animation:nfcGlow 1.5s ease-in-out infinite;}
@keyframes nfcGlow{0%,100%{filter:drop-shadow(0 0 5px var(--teal))}50%{filter:drop-shadow(0 0 16px var(--teal))}}

.uid-display{background:var(--teal-l);border:1.5px solid var(--border);border-radius:10px;padding:14px 18px;font-family:monospace;font-size:1.1rem;font-weight:800;letter-spacing:3px;color:var(--dark);text-align:center;margin-bottom:16px;}
.uid-placeholder{color:#aac5c6;font-size:.9rem;letter-spacing:1px;}

/* Student Search */
.search-wrap{position:relative;margin-bottom:12px;}
.search-wrap input{width:100%;padding:11px 14px 11px 38px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:13px;outline:none;transition:all .2s;background:var(--light);}
.search-wrap input:focus{border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px rgba(13,115,119,.08);}
.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aac5c6;font-size:14px;}
.student-list{max-height:280px;overflow-y:auto;border:1.5px solid var(--border);border-radius:10px;}
.student-item{padding:11px 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .15s;}
.student-item:last-child{border-bottom:none;}
.student-item:hover{background:var(--teal-l);}
.student-item.sel{background:var(--teal-l);border-left:3px solid var(--teal);}
.s-av{width:34px;height:34px;border-radius:50%;background:var(--teal);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0;}
.s-name{font-size:13px;font-weight:700;}
.s-id{font-size:11px;color:var(--mid);}
.s-nfc{font-size:10px;margin-top:2px;}
.s-nfc.has{color:#0a7c63;}.s-nfc.none{color:#aaa;}

/* Assign Button */
.btn-assign{width:100%;padding:13px;background:var(--teal);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:14px;font-weight:800;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;}
.btn-assign:hover{background:var(--teal-d);}
.btn-assign:disabled{background:#aac5c6;cursor:not-allowed;}
.btn-scan{width:100%;padding:11px;background:#fff;color:var(--teal);border:1.5px solid var(--teal);border-radius:10px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:12px;}
.btn-scan:hover{background:var(--teal-l);}

/* Timer */
.timer-wrap{margin:12px 0;}
.timer-lbl{display:flex;justify-content:space-between;font-size:11px;color:var(--mid);margin-bottom:5px;}
.timer-bg{height:4px;background:var(--border);border-radius:2px;overflow:hidden;}
.timer-fill{height:100%;background:var(--teal);border-radius:2px;width:100%;transition:width 1s linear;}

/* Alert */
.al{padding:11px 14px;border-radius:9px;font-size:12px;display:flex;align-items:center;gap:8px;margin-bottom:12px;}
.al-s{background:#e8f7f3;border:1.5px solid #b6e8da;color:#0a7c63;}
.al-d{background:#fdf0ef;border:1.5px solid #f5c6c2;color:#c0392b;}
.al-i{background:var(--teal-l);border:1.5px solid var(--border);color:var(--teal);}
.al-w{background:#fff8e1;border:1.5px solid #ffe082;color:#f59e0b;}

/* Table */
.tbl-wrap{background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;}
.tbl-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.tbl-head h2{font-size:15px;font-weight:800;}
table{width:100%;border-collapse:collapse;}
th{padding:11px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);background:var(--light);border-bottom:1.5px solid var(--border);}
td{padding:12px 16px;font-size:13px;border-bottom:1px solid var(--border);}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#f9fcfc;}
.badge-nfc{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.b-has{background:#e8f7f3;color:#0a7c63;}
.b-none{background:var(--teal-l);color:#aac5c6;}
.uid-cell{font-family:monospace;font-size:12px;letter-spacing:1px;font-weight:700;}
.btn-revoke{padding:5px 12px;background:#fdf0ef;color:#c0392b;border:1.5px solid #f5c6c2;border-radius:7px;font-size:11px;font-weight:700;cursor:pointer;transition:all .2s;}
.btn-revoke:hover{background:#e74c3c;color:#fff;border-color:#e74c3c;}
.search-tbl{padding:10px 14px 10px 36px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s;background:var(--light);}
.search-tbl:focus{border-color:var(--teal);}
.search-tbl-wrap{position:relative;}
.search-tbl-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#aac5c6;}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
<div class="topbar">
    <div>
        <h1><i class="fas fa-wifi" style="color:var(--teal);margin-right:8px;"></i>NFC Card Management</h1>
        <p>Assign NFC sticker/card kepada student</p>
    </div>
</div>

<div class="content">

    <!-- Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-ic ic-teal"><i class="fas fa-users"></i></div>
            <div><div class="stat-val"><?php echo $total_students; ?></div><div class="stat-lbl">Total Students</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-ic ic-green"><i class="fas fa-id-card"></i></div>
            <div><div class="stat-val"><?php echo $nfc_assigned; ?></div><div class="stat-lbl">NFC Assigned</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-ic ic-amber"><i class="fas fa-exclamation-circle"></i></div>
            <div><div class="stat-val"><?php echo $nfc_unassigned; ?></div><div class="stat-lbl">Not Assigned</div></div>
        </div>
    </div>

    <?php if (empty($nfc_device_id)): ?>
    <div class="al al-w" style="margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle"></i>
        Tiada locker device dijumpai. Pastikan ESP32 online dan device_id dah dikonfigurasi dalam DB.
    </div>
    <?php endif; ?>

    <!-- Scanner Panel -->
    <div class="scanner-panel">
        <div class="sp-header">
            <div>
                <h2><i class="fas fa-wifi" style="margin-right:8px;"></i>Assign NFC Card to Student</h2>
                <p>Scan NFC card → pilih student → klik Assign</p>
            </div>
        </div>
        <div class="sp-body">
            <div id="assignAlert"></div>
            <div class="sp-grid">

                <!-- Left: NFC Scanner -->
                <div>
                    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);margin-bottom:12px;">
                        Step 1 — Scan NFC Card
                    </div>

                    <!-- Waiting state -->
                    <div id="nfcWaiting" style="text-align:center;padding:10px 0 20px;">
                        <div class="nfc-wrap">
                            <div class="nfc-ring"></div>
                            <div class="nfc-ring"></div>
                            <div class="nfc-ring"></div>
                            <i class="fas fa-wifi nfc-ic"></i>
                        </div>
                        <div style="font-weight:700;margin-bottom:4px;">Tap NFC Card / Sticker</div>
                        <div style="font-size:12px;color:var(--mid);margin-bottom:16px;">Hold card near PN532 reader on any locker</div>

                        <div class="timer-wrap" id="timerWrap" style="display:none;">
                            <div class="timer-lbl">
                                <span>Waiting...</span>
                                <span id="timerCount">60</span>s
                            </div>
                            <div class="timer-bg"><div class="timer-fill" id="timerFill"></div></div>
                        </div>

                        <button class="btn-scan" onclick="startNFCScan()" id="btnStartScan">
                            <i class="fas fa-play"></i> Start Scanning
                        </button>
                        <button class="btn-scan" onclick="stopNFCScan()" id="btnStopScan" style="display:none;border-color:#e74c3c;color:#e74c3c;">
                            <i class="fas fa-stop"></i> Stop
                        </button>
                    </div>

                    <!-- UID detected -->
                    <div id="nfcDetected" style="display:none;">
                        <div style="font-size:11px;color:var(--mid);margin-bottom:6px;">Detected UID:</div>
                        <div class="uid-display" id="uidDisplay">—</div>
                        <div class="al al-s" id="uidStatus">
                            <i class="fas fa-check-circle"></i>
                            <span id="uidStatusMsg">Card baru dikesan. Pilih student untuk assign.</span>
                        </div>
                        <button class="btn-scan" onclick="resetScan()">
                            <i class="fas fa-redo"></i> Scan Card Lain
                        </button>
                    </div>
                </div>

                <!-- Right: Student picker -->
                <div>
                    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);margin-bottom:12px;">
                        Step 2 — Choose Student
                    </div>
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="studentSearch" placeholder="Search by name or matric..." oninput="filterStudents(this.value)">
                    </div>
                    <div class="student-list" id="studentList">
                        <?php foreach ($students as $s): ?>
                        <div class="student-item"
                             data-id="<?php echo $s['id']; ?>"
                             data-name="<?php echo htmlspecialchars($s['full_name'], ENT_QUOTES); ?>"
                             data-matric="<?php echo htmlspecialchars($s['user_id_number'], ENT_QUOTES); ?>"
                             data-nfc="<?php echo htmlspecialchars($s['nfc_uid'] ?? '', ENT_QUOTES); ?>"
                             onclick="selectStudent(this)">
                            <div class="s-av"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></div>
                            <div>
                                <div class="s-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                <div class="s-id"><?php echo htmlspecialchars($s['user_id_number']); ?></div>
                                <?php if (!empty($s['nfc_uid'])): ?>
                                <div class="s-nfc has"><i class="fas fa-wifi" style="font-size:9px;margin-right:3px;"></i>NFC: <?php echo htmlspecialchars($s['nfc_uid']); ?></div>
                                <?php else: ?>
                                <div class="s-nfc none"><i class="fas fa-times" style="font-size:9px;margin-right:3px;"></i>No NFC card</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn-assign" id="btnAssign" disabled onclick="doAssign()">
                        <i class="fas fa-link"></i> Assign Card to Student
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Table -->
    <div class="tbl-wrap">
        <div class="tbl-head">
            <h2><i class="fas fa-list" style="color:var(--teal);margin-right:8px;"></i>All Students — NFC Status</h2>
            <div class="search-tbl-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-tbl" placeholder="Search table..." oninput="filterTable(this.value)">
            </div>
        </div>
        <table id="mainTable">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Matric / ID</th>
                    <th>Type</th>
                    <th>NFC UID</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($s['full_name']); ?></td>
                    <td style="font-family:monospace;font-size:12px;"><?php echo htmlspecialchars($s['user_id_number']); ?></td>
                    <td><span style="background:var(--teal-l);color:var(--teal);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;"><?php echo ucfirst($s['user_type'] ?? '—'); ?></span></td>
                    <td>
                        <?php if (!empty($s['nfc_uid'])): ?>
                        <span class="uid-cell"><?php echo htmlspecialchars($s['nfc_uid']); ?></span>
                        <?php else: ?>
                        <span style="color:#aaa;font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--mid);">
                        <?php echo !empty($s['nfc_registered_at']) ? date('d M Y', strtotime($s['nfc_registered_at'])) : '—'; ?>
                    </td>
                    <td>
                        <?php if (!empty($s['nfc_uid'])): ?>
                        <button class="btn-revoke" onclick="revokeCard(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['full_name'], ENT_QUOTES); ?>')">
                            <i class="fas fa-times"></i> Revoke
                        </button>
                        <?php else: ?>
                        <span style="font-size:11px;color:#aaa;">No card</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
const NFC_DEVICE_ID = '<?php echo addslashes($nfc_device_id); ?>';
const NFC_TIMEOUT   = 60;

let nfcInt     = null;
let nfcTimer   = null;
let nfcLeft    = NFC_TIMEOUT;
let detectedUID = '';
let selectedStudentId = null;
let selectedStudentName = '';

// ── NFC SCAN ──
function startNFCScan() {
    if (!NFC_DEVICE_ID) { showAlert('Tiada device ID. Pastikan ESP32 online.', 'w'); return; }

    // Set register mode ON di server → ESP32 akan detect dalam 3 saat
    fetch('../api/nfc_set_mode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ device_id: NFC_DEVICE_ID, mode: 1 })
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showAlert('Gagal set register mode: ' + d.message, 'd'); return; }

        showAlert('✅ Register mode ON — ESP32 akan switch dalam 3 saat. Sedia tap card.', 's');

        document.getElementById('btnStartScan').style.display = 'none';
        document.getElementById('btnStopScan').style.display  = 'flex';
        document.getElementById('timerWrap').style.display    = 'block';

        nfcLeft = NFC_TIMEOUT;
        document.getElementById('timerCount').textContent = nfcLeft;
        document.getElementById('timerFill').style.width  = '100%';

        nfcTimer = setInterval(() => {
            nfcLeft--;
            document.getElementById('timerCount').textContent = nfcLeft;
            document.getElementById('timerFill').style.width  = (nfcLeft / NFC_TIMEOUT * 100) + '%';
            if (nfcLeft <= 0) {
                stopNFCScan();
                showAlert('Masa tamat. Register mode dimatikan.', 'd');
            }
        }, 1000);

        nfcInt = setInterval(() => {
            fetch('../api/nfc_register_poll.php?device_id=' + encodeURIComponent(NFC_DEVICE_ID))
            .then(r => r.json())
            .then(d => {
                if (d.found) {
                    stopNFCScan();
                    detectedUID = d.uid;
                    showUIDDetected(d.uid, d.success, d.message);
                }
            })
            .catch(() => {});
        }, 2000);
    })
    .catch(() => showAlert('Network error. Cuba lagi.', 'd'));
}

function stopNFCScan() {
    if (nfcInt)   { clearInterval(nfcInt);   nfcInt   = null; }
    if (nfcTimer) { clearInterval(nfcTimer); nfcTimer = null; }
    document.getElementById('btnStartScan').style.display = 'flex';
    document.getElementById('btnStopScan').style.display  = 'none';
    document.getElementById('timerWrap').style.display    = 'none';

    // Set register mode OFF di server
    if (NFC_DEVICE_ID) {
        fetch('../api/nfc_set_mode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ device_id: NFC_DEVICE_ID, mode: 0 })
        }).catch(() => {});
    }
}

function showUIDDetected(uid, isNew, msg) {
    document.getElementById('nfcWaiting').style.display  = 'none';
    document.getElementById('nfcDetected').style.display = 'block';
    document.getElementById('uidDisplay').textContent    = uid;

    const statusEl  = document.getElementById('uidStatus');
    const statusMsg = document.getElementById('uidStatusMsg');

    if (isNew) {
        statusEl.className = 'al al-s';
        statusEl.querySelector('i').className = 'fas fa-check-circle';
        statusMsg.textContent = msg || 'Card baru. Pilih student untuk assign.';
    } else {
        statusEl.className = 'al al-w';
        statusEl.querySelector('i').className = 'fas fa-exclamation-triangle';
        statusMsg.textContent = msg || 'Card sudah diassign.';
    }

    checkAssignReady();
}

function resetScan() {
    detectedUID = '';
    document.getElementById('nfcWaiting').style.display  = 'block';
    document.getElementById('nfcDetected').style.display = 'none';
    document.getElementById('btnStartScan').style.display = 'flex';
    document.getElementById('btnStopScan').style.display  = 'none';
    document.getElementById('timerWrap').style.display    = 'none';
    checkAssignReady();
}

// ── STUDENT SELECTION ──
function selectStudent(el) {
    document.querySelectorAll('.student-item').forEach(i => i.classList.remove('sel'));
    el.classList.add('sel');
    selectedStudentId   = el.dataset.id;
    selectedStudentName = el.dataset.name;
    checkAssignReady();
}

function checkAssignReady() {
    document.getElementById('btnAssign').disabled = !(detectedUID && selectedStudentId);
}

function filterStudents(q) {
    const items = document.querySelectorAll('.student-item');
    q = q.toLowerCase();
    items.forEach(item => {
        const name   = item.dataset.name.toLowerCase();
        const matric = item.dataset.matric.toLowerCase();
        item.style.display = (name.includes(q) || matric.includes(q)) ? '' : 'none';
    });
}

// ── ASSIGN ──
function doAssign() {
    if (!detectedUID || !selectedStudentId) return;
    if (!confirm('Assign NFC card ' + detectedUID + ' kepada ' + selectedStudentName + '?')) return;

    document.getElementById('btnAssign').disabled = true;
    document.getElementById('btnAssign').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('../api/nfc_assign_card.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nfc_uid: detectedUID, user_id: parseInt(selectedStudentId) })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showAlert('✅ ' + d.message, 's');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('❌ ' + d.message, 'd');
            document.getElementById('btnAssign').disabled = false;
            document.getElementById('btnAssign').innerHTML = '<i class="fas fa-link"></i> Assign Card to Student';
        }
    })
    .catch(() => {
        showAlert('Network error. Cuba lagi.', 'd');
        document.getElementById('btnAssign').disabled = false;
        document.getElementById('btnAssign').innerHTML = '<i class="fas fa-link"></i> Assign Card to Student';
    });
}

// ── REVOKE ──
function revokeCard(userId, name) {
    if (!confirm('Revoke NFC card dari ' + name + '? Student tidak akan dapat akses locker menggunakan NFC sehingga card baru diassign.')) return;

    fetch('../api/nfc_assign_card.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nfc_uid: '', user_id: userId, revoke: true })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showAlert('Card berjaya direvoke.', 's');
            setTimeout(() => location.reload(), 1200);
        } else {
            showAlert(d.message, 'd');
        }
    });
}

// ── TABLE FILTER ──
function filterTable(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#mainTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── ALERT ──
function showAlert(msg, type) {
    const cls = { s:'al-s', d:'al-d', i:'al-i', w:'al-w' };
    const ic  = { s:'check-circle', d:'times-circle', i:'info-circle', w:'exclamation-triangle' };
    document.getElementById('assignAlert').innerHTML =
        `<div class="al ${cls[type]||'al-i'}"><i class="fas fa-${ic[type]||'info-circle'}"></i>${msg}</div>`;
    setTimeout(() => { document.getElementById('assignAlert').innerHTML = ''; }, 4000);
}
</script>
</div><!-- /main -->
</body>
</html>