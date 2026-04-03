<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lockers - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background:#f0f2f5; }
        .section-card { border:none; border-radius:16px; box-shadow:0 4px 16px rgba(0,0,0,0.07); }
        .form-control, .form-select { border-radius:10px; border:2px solid #e9ecef; padding:10px 14px; }
        .form-control:focus, .form-select:focus { border-color:#0f3460; box-shadow:0 0 0 3px rgba(15,52,96,0.1); }
        .status-active      { background:#d4edda; color:#155724; }
        .status-available   { background:#cce5ff; color:#004085; }
        .status-maintenance { background:#fff3cd; color:#856404; }
    </style>
</head>
<body>
<?php require_once 'includes/navbar.php'; ?>

<?php
// Handle form submission (add/edit)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $unique_code = strtoupper(trim($_POST['unique_code'] ?? ''));
    $device_id   = trim($_POST['device_id'] ?? '');
    $status      = $_POST['status'] ?? 'available';

    if ($action === 'add') {
        // Check duplicate unique_code
        $chk = $pdo->prepare("SELECT id FROM lockers WHERE unique_code = ?");
        $chk->execute([$unique_code]);
        if ($chk->fetch()) {
            $msg = ['type'=>'danger', 'text'=>"Unique Code '$unique_code' already exists. Please use a different code."];
        } else {
            $locker_key = bin2hex(random_bytes(8)); // 16 char key
            $pdo->prepare("INSERT INTO lockers (unique_code, device_id, status, locker_key, created_at) VALUES (?,?,?,?,NOW())")
                ->execute([$unique_code, $device_id, $status, $locker_key]);
            $new_locker_id = $pdo->lastInsertId();
            $msg = ['type'=>'success', 'text'=>"Locker added! <strong>Locker Code: $unique_code</strong> | <strong>Locker Key: $locker_key</strong> — Save and give this to the user."];
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['locker_id'];
        // Check duplicate unique_code (exclude self)
        $chk = $pdo->prepare("SELECT id FROM lockers WHERE unique_code = ? AND id != ?");
        $chk->execute([$unique_code, $id]);
        if ($chk->fetch()) {
            $msg = ['type'=>'danger', 'text'=>"Unique Code '$unique_code' already exists. Please use a different code."];
        } else {
            $pdo->prepare("UPDATE lockers SET unique_code=?, device_id=?, status=? WHERE id=?")
                ->execute([$unique_code, $device_id, $status, $id]);
            $msg = ['type'=>'success', 'text'=>"Locker updated successfully!"];
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['locker_id'];
        // Check no active assignment
        $check = $pdo->prepare("SELECT COUNT(*) FROM user_locker_assignments WHERE locker_id=? AND is_active=1");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $msg = ['type'=>'danger', 'text'=>"Cannot delete: locker has active assignments."];
        } else {
            $pdo->prepare("DELETE FROM lockers WHERE id=?")->execute([$id]);
            $msg = ['type'=>'success', 'text'=>"Locker deleted."];
        }
    }
}

// Add locker_key column if not exists (safe)
try { $pdo->exec("ALTER TABLE lockers ADD COLUMN IF NOT EXISTS locker_key VARCHAR(64) NULL"); } catch(Exception $e) {}

$lockers = $pdo->query("
    SELECT l.*, 
        (SELECT COUNT(*) FROM user_locker_assignments WHERE locker_id=l.id AND is_active=1) as assigned_count
    FROM lockers l ORDER BY l.name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-boxes me-2 text-primary"></i>Manage Lockers</h4>
            <small class="text-muted">Add, edit and manage all lockers</small>
        </div>
        <button class="btn btn-primary rounded-pill" onclick="openAddModal()">
            <i class="fas fa-plus me-2"></i>Add New Locker
        </button>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg['type']; ?> alert-dismissible rounded-3 mb-4">
        <?php echo $msg['text']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Lockers Table -->
    <div class="card section-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Unique Code</th>
                            <th>Device ID</th>
                            <th>Locker Key</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th class="pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lockers as $i => $lk): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?php echo $i+1; ?></td>
                            <td><code><?php echo htmlspecialchars($lk['unique_code'] ?? '—'); ?></code></td>
                            <td><small><?php echo htmlspecialchars($lk['device_id'] ?? '—'); ?></small></td>
                            <td>
                                <?php if (!empty($lk['locker_key'])): ?>
                                <div class="d-flex align-items-center gap-1">
                                    <code id="key_<?php echo $lk['id']; ?>" style="font-size:11px; letter-spacing:1px;">
                                        ••••••••
                                    </code>
                                    <button class="btn btn-sm p-0 px-1" onclick="toggleKey(<?php echo $lk['id']; ?>, '<?php echo $lk['locker_key']; ?>')" title="Tunjuk/Sorok key">
                                        <i class="fas fa-eye text-muted" style="font-size:11px;"></i>
                                    </button>
                                    <button class="btn btn-sm p-0 px-1" onclick="copyKey('<?php echo $lk['locker_key']; ?>')" title="Copy key">
                                        <i class="fas fa-copy text-muted" style="font-size:11px;"></i>
                                    </button>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge status-<?php echo $lk['status']; ?> px-3 py-1 rounded-pill">
                                    <?php echo ucfirst($lk['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($lk['assigned_count'] > 0): ?>
                                <span class="badge bg-primary rounded-pill"><?php echo $lk['assigned_count']; ?> user(s)</span>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4">
                                <div class="d-flex gap-2">
                                    <button onclick='openEditModal(<?php echo json_encode($lk); ?>)'
                                        class="btn btn-warning btn-sm rounded-pill">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($lk['assigned_count'] == 0): ?>
                                    <form method="POST" onsubmit="return confirm('Delete this locker?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="locker_id" value="<?php echo $lk['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm rounded-pill">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="lockerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; border:none;">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="lockerModalTitle">Add New Locker</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="locker_id" id="formLockerId">
                <div class="modal-body pt-0">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Unique Code *</label>
                            <input type="text" name="unique_code" id="fCode" class="form-control" placeholder="e.g. LKR004" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Device ID</label>
                            <input type="text" name="device_id" id="fDevice" class="form-control" placeholder="e.g. DEV004">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Status *</label>
                            <select name="status" id="fStatus" class="form-select">
                                <option value="available">Available</option>
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" id="lockerSubmitBtn">Add Locker</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openAddModal() {
    document.getElementById('lockerModalTitle').textContent = 'Add New Locker';
    document.getElementById('formAction').value  = 'add';
    document.getElementById('formLockerId').value = '';
    document.getElementById('fCode').value     = '';
    document.getElementById('fDevice').value   = '';
    document.getElementById('fStatus').value   = 'available';
    document.getElementById('lockerSubmitBtn').textContent = 'Add Locker';
    new bootstrap.Modal(document.getElementById('lockerModal')).show();
}

function openEditModal(lk) {
    document.getElementById('lockerModalTitle').textContent = 'Edit Locker';
    document.getElementById('formAction').value   = 'edit';
    document.getElementById('formLockerId').value = lk.id;
    document.getElementById('fCode').value        = lk.unique_code || '';
    document.getElementById('fDevice').value      = lk.device_id   || '';
    document.getElementById('fStatus').value      = lk.status      || 'available';
    document.getElementById('lockerSubmitBtn').textContent = 'Save Changes';
    new bootstrap.Modal(document.getElementById('lockerModal')).show();
}
// Auto-open if action=add in URL
if (new URLSearchParams(location.search).get('action') === 'add') openAddModal();

function toggleKey(id, key) {
    const el = document.getElementById('key_' + id);
    if (el.textContent.trim() === '••••••••') {
        el.textContent = key;
    } else {
        el.textContent = '••••••••';
    }
}

function copyKey(key) {
    navigator.clipboard.writeText(key).then(() => {
        alert('✅ Locker Key copied to clipboard!');
    });
}
</script>
</body>
</html>