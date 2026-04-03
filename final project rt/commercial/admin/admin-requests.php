<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locker Requests - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background:#f0f2f5; }
        .section-card { border:none; border-radius:16px; box-shadow:0 4px 16px rgba(0,0,0,0.07); }
        .badge-pending  { background:#fff3cd; color:#856404; padding:5px 14px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-approved { background:#d4edda; color:#155724; padding:5px 14px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-rejected { background:#f8d7da; color:#721c24; padding:5px 14px; border-radius:20px; font-size:12px; font-weight:600; }
        .filter-btn.active { background:#0f3460 !important; color:white !important; }
    </style>
</head>
<body>
<?php require_once 'includes/navbar.php'; ?>
<?php
$filter = $_GET['filter'] ?? 'all';
$where  = $filter !== 'all' ? "WHERE lr.status = '$filter'" : '';

$stmt = $pdo->query("
    SELECT lr.*, u.full_name as user_name, u.user_id_number, u.email,
           l.name as locker_name, l.location, l.unique_code
    FROM locker_requests lr
    LEFT JOIN users u   ON lr.user_id   = u.id
    LEFT JOIN lockers l ON lr.locker_id = l.id
    $where
    ORDER BY
        CASE lr.status WHEN 'pending' THEN 0 ELSE 1 END,
        lr.created_at DESC
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query("SELECT status, COUNT(*) as c FROM locker_requests GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$cmap = ['all' => 0];
foreach ($counts as $c) { $cmap[$c['status']] = $c['c']; $cmap['all'] += $c['c']; }
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-inbox me-2 text-warning"></i>Locker Requests</h4>
            <small class="text-muted">Review and manage user locker requests</small>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <?php foreach (['all'=>'All', 'pending'=>'⏳ Pending', 'approved'=>'✅ Approved', 'rejected'=>'❌ Rejected'] as $key => $label): ?>
        <a href="?filter=<?php echo $key; ?>"
           class="btn btn-outline-secondary btn-sm rounded-pill filter-btn <?php echo $filter===$key?'active':''; ?>">
            <?php echo $label; ?>
            <span class="badge bg-secondary ms-1"><?php echo $cmap[$key] ?? 0; ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Requests Table -->
    <div class="card section-card">
        <div class="card-body p-0">
            <?php if (empty($requests)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                <p>No requests found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>User</th>
                            <th>Locker</th>
                            <th>Note</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $i => $req): ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?php echo $i+1; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($req['user_name'] ?? 'N/A'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($req['user_id_number'] ?? ''); ?></small><br>
                                <small class="text-muted"><?php echo htmlspecialchars($req['email'] ?? ''); ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($req['locker_name'] ?? 'N/A'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($req['location'] ?? ''); ?></small><br>
                                <code class="small"><?php echo htmlspecialchars($req['unique_code'] ?? ''); ?></code>
                            </td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($req['note'] ?: '—'); ?></small></td>
                            <td><small><?php echo date('d M Y, H:i', strtotime($req['created_at'])); ?></small></td>
                            <td>
                                <span class="badge-<?php echo $req['status']; ?>">
                                    <?php $labels=['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected'];
                                          echo $labels[$req['status']] ?? ucfirst($req['status']); ?>
                                </span>
                                <?php if ($req['admin_note']): ?>
                                <div><small class="text-muted fst-italic"><?php echo htmlspecialchars($req['admin_note']); ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4">
                                <?php if ($req['status'] === 'pending'): ?>
                                <div class="d-flex gap-2">
                                    <button onclick="openAction(<?php echo $req['id']; ?>, 'approve')"
                                        class="btn btn-success btn-sm rounded-pill">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                    <button onclick="openAction(<?php echo $req['id']; ?>, 'reject')"
                                        class="btn btn-danger btn-sm rounded-pill">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; border:none;">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <p class="text-muted small mb-3" id="modalDesc"></p>
                <label class="form-label fw-semibold">Admin Note <span class="text-muted fw-normal">(optional)</span></label>
                <textarea id="adminNote" rows="3" class="form-control" style="border-radius:10px;" placeholder="Add a note for the user..."></textarea>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmBtn" class="btn rounded-pill px-4 fw-bold" onclick="confirmAction()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentId = null, currentAction = null;

function openAction(id, action) {
    currentId = id; currentAction = action;
    const title = document.getElementById('modalTitle');
    const desc  = document.getElementById('modalDesc');
    const btn   = document.getElementById('confirmBtn');
    if (action === 'approve') {
        title.textContent = '✅ Approve Request';
        desc.textContent  = 'The locker will be assigned to this user immediately.';
        btn.className     = 'btn btn-success rounded-pill px-4 fw-bold';
        btn.textContent   = 'Approve';
    } else {
        title.textContent = '❌ Reject Request';
        desc.textContent  = 'The user will be notified that their request was rejected.';
        btn.className     = 'btn btn-danger rounded-pill px-4 fw-bold';
        btn.textContent   = 'Reject';
    }
    document.getElementById('adminNote').value = '';
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}

function confirmAction() {
    const note = document.getElementById('adminNote').value.trim();
    const btn  = document.getElementById('confirmBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

    fetch('api/admin-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ request_id: currentId, action: currentAction, note })
    })
    .then(r => r.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('actionModal')).hide();
        if (data.success) {
            showToast('✅ ' + data.message, '#27ae60');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('❌ ' + data.message, '#e74c3c');
            btn.disabled  = false;
            btn.textContent = 'Confirm';
        }
    })
    .catch(() => { showToast('❌ Connection error', '#e74c3c'); btn.disabled = false; });
}

function showToast(msg, color) {
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, { position:'fixed', bottom:'30px', right:'30px', background:color,
        color:'white', padding:'14px 22px', borderRadius:'12px', fontWeight:'600',
        fontSize:'14px', zIndex:'9999', boxShadow:'0 8px 24px rgba(0,0,0,0.2)' });
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
</script>
</body>
</html>