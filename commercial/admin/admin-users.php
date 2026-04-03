<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background:#f0f2f5; }
        .section-card { border:none; border-radius:16px; box-shadow:0 4px 16px rgba(0,0,0,0.07); }
        .form-control { border-radius:10px; border:2px solid #e9ecef; padding:10px 14px; }
        .form-control:focus { border-color:#0f3460; box-shadow:0 0 0 3px rgba(15,52,96,0.1); }
        .avatar {
            width:38px; height:38px; border-radius:50%;
            background: linear-gradient(135deg,#667eea,#764ba2);
            display:flex; align-items:center; justify-content:center;
            color:white; font-weight:700; font-size:14px; flex-shrink:0;
        }
    </style>
</head>
<body>
<?php require_once 'includes/navbar.php'; ?>

<?php
$search = trim($_GET['search'] ?? '');
$where  = $search ? "WHERE u.full_name LIKE ? OR u.user_id_number LIKE ? OR u.email LIKE ?" : '';
$params = $search ? ["%$search%","%$search%","%$search%"] : [];

$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.user_id_number, u.user_type, u.institution, u.created_at, u.is_active,
        (SELECT COUNT(*) FROM user_locker_assignments WHERE user_id=u.id AND is_active=1) as locker_count,
        (SELECT COUNT(*) FROM locker_requests WHERE user_id=u.id AND status='pending')     as pending_count
    FROM users u
    $where
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-users me-2 text-primary"></i>Manage Users</h4>
            <small class="text-muted"><?php echo count($users); ?> users found</small>
        </div>
    </div>

    <!-- Search -->
    <div class="card section-card mb-4">
        <div class="card-body p-3">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    class="form-control" placeholder="Search by name, ID number or email...">
                <button type="submit" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-search me-1"></i>Search
                </button>
                <?php if ($search): ?>
                <a href="admin-users.php" class="btn btn-outline-secondary rounded-pill">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card section-card">
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                <p>No users found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>ID Number</th>
                            <th>Type</th>
                            <th>Lockers</th>
                            <th>Pending Req.</th>
                            <th>Joined</th>
                            <th class="pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar"><?php echo strtoupper(substr($u['full_name'] ?? 'U', 0, 1)); ?></div>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($u['full_name'] ?? 'N/A'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($u['email'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code><?php echo htmlspecialchars($u['user_id_number'] ?? '—'); ?></code></td>
                            <td>
                                <span class="badge bg-secondary rounded-pill">
                                    <?php echo ucfirst($u['user_type'] ?? 'user'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['locker_count'] > 0): ?>
                                <span class="badge bg-primary rounded-pill"><?php echo $u['locker_count']; ?> locker(s)</span>
                                <?php else: ?>
                                <span class="text-muted small">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['pending_count'] > 0): ?>
                                <span class="badge bg-warning text-dark rounded-pill"><?php echo $u['pending_count']; ?> pending</span>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo date('d M Y', strtotime($u['created_at'] ?? 'now')); ?></small></td>
                            <td class="pe-4">
                                <button onclick="viewUser(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES); ?>)"
                                    class="btn btn-outline-primary btn-sm rounded-pill">
                                    <i class="fas fa-eye me-1"></i>View
                                </button>
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

<!-- View User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; border:none;">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userModalBody"></div>
            <div class="modal-footer border-0">
                <button class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewUser(u) {
    document.getElementById('userModalBody').innerHTML = `
        <div class="row g-3">
            <div class="col-6"><small class="text-muted d-block">Full Name</small><strong>${u.full_name||'N/A'}</strong></div>
            <div class="col-6"><small class="text-muted d-block">ID Number</small><strong>${u.user_id_number||'N/A'}</strong></div>
            <div class="col-6"><small class="text-muted d-block">Email</small><small>${u.email||'N/A'}</small></div>
            <div class="col-6"><small class="text-muted d-block">User Type</small><strong>${u.user_type||'user'}</strong></div>
            <div class="col-6"><small class="text-muted d-block">Institution</small><small>${u.institution||'N/A'}</small></div>
            <div class="col-6"><small class="text-muted d-block">Active Lockers</small><strong>${u.locker_count}</strong></div>
            <div class="col-12"><small class="text-muted d-block">Joined</small><small>${u.created_at||'N/A'}</small></div>
        </div>
    `;
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>
</body>
</html>