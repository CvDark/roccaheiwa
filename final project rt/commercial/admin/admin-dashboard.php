<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .stat-card {
            border: none; border-radius: 16px;
            color: white; padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-number { font-size: 2.4rem; font-weight: 800; line-height: 1; }
        .section-card {
            border: none; border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.07);
        }
        .req-row { transition: background 0.2s; }
        .req-row:hover { background: #f8f9fa; }
        .badge-pending  { background: #fff3cd; color: #856404; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-approved { background: #d4edda; color: #155724; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-rejected { background: #f8d7da; color: #721c24; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; }
    </style>
</head>
<body>
<?php require_once 'includes/navbar.php'; ?>

<?php
// Fetch all stats
$total_users    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_assign  = $pdo->query("SELECT COUNT(*) FROM user_locker_assignments WHERE is_active=1")->fetchColumn();
$total_lockers  = $pdo->query("SELECT COUNT(*) FROM lockers")->fetchColumn();
$avail_lockers  = $pdo->query("SELECT COUNT(*) FROM lockers WHERE status='available'")->fetchColumn();
$pending_req    = $pdo->query("SELECT COUNT(*) FROM locker_requests WHERE status='pending'")->fetchColumn();

// Recent pending requests
$stmt = $pdo->query("
    SELECT lr.*, u.full_name as user_name, u.user_id_number, l.name as locker_name, l.location
    FROM locker_requests lr
    LEFT JOIN users u  ON lr.user_id  = u.id
    LEFT JOIN lockers l ON lr.locker_id = l.id
    WHERE lr.status = 'pending'
    ORDER BY lr.created_at DESC
    LIMIT 8
");
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Locker status breakdown
$locker_stats = $pdo->query("SELECT status, COUNT(*) as cnt FROM lockers GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$locker_map = [];
foreach ($locker_stats as $ls) $locker_map[$ls['status']] = $ls['cnt'];
?>

<div class="container-fluid px-4 py-4">

    <!-- Welcome bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($admin_name); ?>! 👋</h4>
            <small class="text-muted"><?php echo date('l, d F Y'); ?></small>
        </div>
        <a href="admin-requests.php" class="btn btn-primary">
            <i class="fas fa-inbox me-2"></i>View All Requests
            <?php if ($pending_req > 0): ?><span class="badge bg-danger ms-1"><?php echo $pending_req; ?></span><?php endif; ?>
        </a>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#667eea,#764ba2);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small mb-2 opacity-75">Total Users</div>
                        <div class="stat-number"><?php echo $total_users; ?></div>
                        <small class="opacity-75"><?php echo $active_assign; ?> active assignments</small>
                    </div>
                    <i class="fas fa-users fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#2d6a4f,#52b788);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small mb-2 opacity-75">Total Lockers</div>
                        <div class="stat-number"><?php echo $total_lockers; ?></div>
                        <small class="opacity-75"><?php echo $avail_lockers; ?> available</small>
                    </div>
                    <i class="fas fa-boxes fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#f77f00,#d62828);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small mb-2 opacity-75">Pending Requests</div>
                        <div class="stat-number"><?php echo $pending_req; ?></div>
                        <small class="opacity-75">Awaiting approval</small>
                    </div>
                    <i class="fas fa-clock fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#0096c7,#48cae4);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small mb-2 opacity-75">Occupied Lockers</div>
                        <div class="stat-number"><?php echo $locker_map['active'] ?? 0; ?></div>
                        <small class="opacity-75"><?php echo $locker_map['maintenance'] ?? 0; ?> in maintenance</small>
                    </div>
                    <i class="fas fa-lock fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Pending Requests Table -->
        <div class="col-lg-8">
            <div class="card section-card">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="fas fa-inbox text-warning me-2"></i>Pending Requests</h6>
                    <a href="admin-requests.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pending_requests)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-50"></i>
                        <p>No pending requests!</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">User</th>
                                    <th>Locker</th>
                                    <th>Date</th>
                                    <th class="pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $req): ?>
                                <tr class="req-row">
                                    <td class="ps-4">
                                        <div class="fw-semibold"><?php echo htmlspecialchars($req['user_name'] ?? 'N/A'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($req['user_id_number'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($req['locker_name'] ?? 'N/A'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($req['location'] ?? ''); ?></small>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('d M Y', strtotime($req['created_at'])); ?></small></td>
                                    <td class="pe-4">
                                        <div class="d-flex gap-2">
                                            <button onclick="quickAction(<?php echo $req['id']; ?>, 'approve')"
                                                class="btn btn-success btn-sm rounded-pill px-3">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                            <button onclick="quickAction(<?php echo $req['id']; ?>, 'reject')"
                                                class="btn btn-danger btn-sm rounded-pill px-3">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </div>
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

        <!-- Locker Status Breakdown -->
        <div class="col-lg-4">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h6 class="fw-bold mb-0"><i class="fas fa-chart-pie text-primary me-2"></i>Locker Status</h6>
                </div>
                <div class="card-body px-4">
                    <?php
                    $statuses = [
                        'available'   => ['label'=>'Available',   'color'=>'#52b788', 'icon'=>'fa-check-circle'],
                        'active'      => ['label'=>'Active/Used',  'color'=>'#3498db', 'icon'=>'fa-lock'],
                        'maintenance' => ['label'=>'Maintenance', 'color'=>'#e74c3c', 'icon'=>'fa-tools'],
                    ];
                    foreach ($statuses as $key => $s):
                        $count = $locker_map[$key] ?? 0;
                        $pct   = $total_lockers > 0 ? round($count / $total_lockers * 100) : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-semibold">
                                <i class="fas <?php echo $s['icon']; ?> me-1" style="color:<?php echo $s['color']; ?>"></i>
                                <?php echo $s['label']; ?>
                            </span>
                            <span class="small text-muted"><?php echo $count; ?> (<?php echo $pct; ?>%)</span>
                        </div>
                        <div class="progress" style="height:8px; border-radius:10px;">
                            <div class="progress-bar" style="width:<?php echo $pct; ?>%; background:<?php echo $s['color']; ?>; border-radius:10px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <hr>
                    <div class="d-grid gap-2 mt-3">
                        <a href="admin-lockers.php" class="btn btn-outline-primary btn-sm rounded-pill">
                            <i class="fas fa-boxes me-1"></i>Manage Lockers
                        </a>
                        <a href="admin-lockers.php?action=add" class="btn btn-primary btn-sm rounded-pill">
                            <i class="fas fa-plus me-1"></i>Add New Locker
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="actionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Admin Note <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea id="adminNote" rows="3" class="form-control" style="border-radius:10px;" placeholder="Add a note for the user..."></textarea>
                </div>
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
let currentRequestId = null;
let currentAction    = null;

function quickAction(requestId, action) {
    currentRequestId = requestId;
    currentAction    = action;

    const modal = document.getElementById('actionModal');
    const title = document.getElementById('actionModalTitle');
    const btn   = document.getElementById('confirmBtn');

    if (action === 'approve') {
        title.textContent  = '✅ Approve Request';
        btn.className      = 'btn btn-success rounded-pill px-4 fw-bold';
        btn.textContent    = 'Approve';
    } else {
        title.textContent  = '❌ Reject Request';
        btn.className      = 'btn btn-danger rounded-pill px-4 fw-bold';
        btn.textContent    = 'Reject';
    }
    document.getElementById('adminNote').value = '';
    new bootstrap.Modal(modal).show();
}

function confirmAction() {
    const note = document.getElementById('adminNote').value.trim();
    const btn  = document.getElementById('confirmBtn');
    btn.disabled   = true;
    btn.innerHTML  = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

    fetch('api/admin-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ request_id: currentRequestId, action: currentAction, note: note })
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
            btn.innerHTML = 'Confirm';
        }
    })
    .catch(() => { showToast('❌ Connection error', '#e74c3c'); btn.disabled = false; });
}

function showToast(msg, color) {
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, {
        position:'fixed', bottom:'30px', right:'30px', background:color,
        color:'white', padding:'14px 22px', borderRadius:'12px',
        fontWeight:'600', fontSize:'14px', zIndex:'9999',
        boxShadow:'0 8px 24px rgba(0,0,0,0.2)'
    });
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
</script>
</body>
</html>