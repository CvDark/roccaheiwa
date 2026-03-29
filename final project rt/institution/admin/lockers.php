<?php
require_once 'config.php';
requireAdmin();

$message = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_locker') {
        $unique_code = strtoupper(sanitize($_POST['unique_code'] ?? ''));
        $device_id   = sanitize($_POST['device_id'] ?? '');
        if ($unique_code) {
            $chk = $pdo->prepare("SELECT id FROM lockers WHERE unique_code=?");
            $chk->execute([$unique_code]);
            if ($chk->fetch()) {
                $message = 'Unique code already exists.'; $msg_type = 'danger';
            } else {
                $pdo->prepare("INSERT INTO lockers (unique_code,device_id,status) VALUES (?,?,'available')")
                    ->execute([$unique_code, $device_id]);
                $message = 'Locker added!'; $msg_type = 'success';
            }
        } else { $message = 'Unique code required.'; $msg_type = 'danger'; }

    } elseif ($action === 'update_status') {
        $lid    = (int)($_POST['locker_id'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'available');
        $pdo->prepare("UPDATE lockers SET status=? WHERE id=?")->execute([$status, $lid]);
        $message = 'Locker status updated.'; $msg_type = 'success';

    } elseif ($action === 'assign_locker') {
        $lid     = (int)($_POST['locker_id'] ?? 0);
        $user_q  = sanitize($_POST['user_query'] ?? '');
        // find user
        $u = $pdo->prepare("SELECT id FROM users WHERE user_id_number=? OR email=? LIMIT 1");
        $u->execute([$user_q, $user_q]);
        $found_user = $u->fetch();
        if (!$found_user) {
            $message = 'User not found.'; $msg_type = 'danger';
        } else {
            $uid = $found_user['id'];
            $chk = $pdo->prepare("SELECT id FROM user_locker_assignments WHERE user_id=? AND locker_id=? AND is_active=1");
            $chk->execute([$uid, $lid]);
            if ($chk->fetch()) {
                $message = 'User already assigned to this locker.'; $msg_type = 'danger';
            } else {
                $key = bin2hex(random_bytes(16));
                $pdo->prepare("INSERT INTO user_locker_assignments (user_id,locker_id,key_value,assigned_at,is_active) VALUES (?,?,?,NOW(),1)")
                    ->execute([$uid, $lid, $key]);
                $pdo->prepare("UPDATE lockers SET status='occupied' WHERE id=?")->execute([$lid]);
                $message = 'Locker assigned successfully!'; $msg_type = 'success';
            }
        }

    } elseif ($action === 'delete_locker') {
        $lid = (int)($_POST['locker_id'] ?? 0);
        $pdo->prepare("DELETE FROM lockers WHERE id=?")->execute([$lid]);
        $message = 'Locker deleted.'; $msg_type = 'success';
    }
}

// Fetch lockers with assignment info
$filter_status = sanitize($_GET['status'] ?? '');
$where = $filter_status ? "WHERE l.status=?" : "";
$params = $filter_status ? [$filter_status] : [];

$stmt = $pdo->prepare("
    SELECT l.*,
           COUNT(ula.id) as assigned_count,
           GROUP_CONCAT(u.full_name SEPARATOR ', ') as assigned_to
    FROM lockers l
    LEFT JOIN user_locker_assignments ula ON ula.locker_id=l.id AND ula.is_active=1
    LEFT JOIN users u ON u.id=ula.user_id
    $where
    GROUP BY l.id
    ORDER BY l.created_at DESC
");
$stmt->execute($params);
$lockers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage Lockers — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin-style.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
    .qr-modal-body{text-align:center;padding:24px;}
    .qr-locker-code{font-size:22px;font-weight:800;color:var(--teal);margin-bottom:4px;font-family:monospace;}
    .qr-locker-dev{font-size:12px;color:#aaa;margin-bottom:20px;}
    #qrCanvas{display:inline-block;padding:14px;background:white;border:1.5px solid var(--border);border-radius:12px;}
    .qr-hint{font-size:11px;color:#aaa;margin-top:12px;}
    .btn-print{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:9px;background:var(--teal);color:white;border:none;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;margin-top:14px;}
    .btn-print:hover{background:var(--teal-d);}
    @media print{
        body *{visibility:hidden;}
        #printArea,#printArea *{visibility:visible;}
        #printArea{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);}
    }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <div><h1>Manage Lockers</h1><p><?php echo count($lockers); ?> lockers total</p></div>
        <button class="btn btn-primary" onclick="openModal('addLockerModal')">
            <i class="fas fa-plus"></i> Add Locker
        </button>
    </div>
    <div class="content">

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Status filter -->
        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
            <?php
            $statuses = [''=> 'All', 'available'=>'Available', 'occupied'=>'Occupied', 'maintenance'=>'Maintenance'];
            foreach ($statuses as $val => $label):
            ?>
            <a href="?status=<?php echo $val; ?>"
               class="btn <?php echo $filter_status===$val?'btn-primary':'btn-outline'; ?> btn-sm">
                <?php echo $label; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="panel">
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>Code</th><th>Device ID</th><th>Status</th><th>Assigned To</th><th>Created</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($lockers)): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="fas fa-box-open"></i>No lockers found.</div></td></tr>
                    <?php else: ?>
                    <?php foreach ($lockers as $i => $lk): ?>
                    <tr>
                        <td style="color:#aaa;font-size:11px"><?php echo $i+1; ?></td>
                        <td><strong style="font-family:monospace"><?php echo htmlspecialchars($lk['unique_code']); ?></strong></td>
                        <td style="font-size:12px;color:#aaa"><?php echo htmlspecialchars($lk['device_id'] ?: '—'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $lk['status']; ?>">
                                <?php echo ucfirst($lk['status']); ?>
                            </span>
                        </td>
                        <td style="font-size:12px">
                            <?php if ($lk['assigned_count'] > 0): ?>
                                <span style="color:var(--teal)"><?php echo htmlspecialchars($lk['assigned_to']); ?></span>
                            <?php else: ?>
                                <span style="color:#ccc">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:11px;color:#aaa"><?php echo date('d M Y', strtotime($lk['created_at'])); ?></td>
                        <td>
                            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                <button class="btn btn-outline btn-sm" title="Papar QR Locker"
                                        onclick="openQR(<?php echo $lk['id']; ?>, '<?php echo htmlspecialchars($lk['unique_code']); ?>', '<?php echo htmlspecialchars($lk['device_id'] ?: ''); ?>')">
                                    <i class="fas fa-qrcode"></i>
                                </button>
                                <button class="btn btn-outline btn-sm" title="Assign to user"
                                        onclick="openAssign(<?php echo $lk['id']; ?>, '<?php echo htmlspecialchars($lk['unique_code']); ?>')">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                                <button class="btn btn-outline btn-sm" title="Change status"
                                        onclick="openStatus(<?php echo $lk['id']; ?>, '<?php echo $lk['status']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete locker <?php echo htmlspecialchars($lk['unique_code']); ?>?')">
                                    <input type="hidden" name="action" value="delete_locker">
                                    <input type="hidden" name="locker_id" value="<?php echo $lk['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <footer>
        <span>© 2026 Smart Locker System — Institution Admin</span>
        <span><?php echo date('d M Y, H:i'); ?></span>
    </footer>
</div>

<!-- ADD LOCKER MODAL -->
<div class="modal-overlay" id="addLockerModal">
    <div class="modal">
        <h3><i class="fas fa-plus-circle" style="color:var(--teal);margin-right:8px"></i>Add New Locker</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_locker">
            <div class="form-grid">
                <div class="field"><label>Unique Code *</label><input type="text" name="unique_code" required placeholder="e.g. INST006" style="text-transform:uppercase"></div>
                <div class="field"><label>Device ID (ESP32)</label><input type="text" name="device_id" placeholder="e.g. DEV006"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addLockerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Locker</button>
            </div>
        </form>
    </div>
</div>

<!-- ASSIGN MODAL -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <h3><i class="fas fa-user-plus" style="color:var(--teal);margin-right:8px"></i>Assign Locker <span id="assignCode" style="color:var(--teal)"></span></h3>
        <form method="POST">
            <input type="hidden" name="action" value="assign_locker">
            <input type="hidden" name="locker_id" id="assignLockerId">
            <div class="form-grid">
                <div class="field">
                    <label>Student/Staff ID or Email *</label>
                    <input type="text" name="user_query" required placeholder="e.g. MC2516203265 or email@...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('assignModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Assign</button>
            </div>
        </form>
    </div>
</div>

<!-- STATUS MODAL -->
<div class="modal-overlay" id="statusModal">
    <div class="modal">
        <h3><i class="fas fa-edit" style="color:var(--teal);margin-right:8px"></i>Update Locker Status</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="locker_id" id="statusLockerId">
            <div class="form-grid">
                <div class="field"><label>Status</label>
                    <select name="status" id="statusSelect">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<!-- QR MODAL -->
<div class="modal-overlay" id="qrModal">
    <div class="modal" style="max-width:360px">
        <h3><i class="fas fa-qrcode" style="color:var(--teal);margin-right:8px"></i>QR Code Locker</h3>
        <div class="qr-modal-body">
            <div id="printArea">
                <div class="qr-locker-code" id="qrLockerCode">—</div>
                <div class="qr-locker-dev" id="qrLockerDev"></div>
                <div id="qrCanvas"></div>
                <p class="qr-hint"><i class="fas fa-info-circle" style="margin-right:4px"></i>Letak QR ini pada locker fizikal</p>
            </div>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:6px">
                <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <button class="btn-print" style="background:#3d5c5e" onclick="downloadQR()"><i class="fas fa-download"></i> Download</button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('qrModal')">Tutup</button>
        </div>
    </div>
</div>

<script>
let currentQR = null;

function openQR(id, code, dev) {
    // Clear QR canvas
    const canvas = document.getElementById('qrCanvas');
    canvas.innerHTML = '';
    document.getElementById('qrLockerCode').textContent = code;
    document.getElementById('qrLockerDev').textContent = dev ? 'Device: ' + dev : '';

    // Generate QR dengan unique_code sebagai data
    currentQR = new QRCode(canvas, {
        text: code,
        width: 180,
        height: 180,
        colorDark: '#0d7377',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });

    openModal('qrModal');
}

function downloadQR() {
    setTimeout(() => {
        const canvas = document.querySelector('#qrCanvas canvas');
        if (!canvas) { alert('QR belum siap.'); return; }
        const code = document.getElementById('qrLockerCode').textContent;
        const link = document.createElement('a');
        link.download = 'QR_' + code + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }, 200);
}

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openAssign(id, code) {
    document.getElementById('assignLockerId').value = id;
    document.getElementById('assignCode').textContent = '— ' + code;
    openModal('assignModal');
}
function openStatus(id, status) {
    document.getElementById('statusLockerId').value = id;
    document.getElementById('statusSelect').value = status;
    openModal('statusModal');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target===m) m.classList.remove('open'); });
});
</script>
</body>
</html>