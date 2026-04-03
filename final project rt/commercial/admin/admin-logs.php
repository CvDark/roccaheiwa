<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background:#f0f2f5; }
        .section-card { border:none; border-radius:16px; box-shadow:0 4px 16px rgba(0,0,0,0.07); }
        .form-control, .form-select { border-radius:10px; border:2px solid #e9ecef; padding:10px 14px; }
    </style>
</head>
<body>
<?php require_once 'includes/navbar.php'; ?>

<?php
$date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to   = $_GET['to']   ?? date('Y-m-d');
$user_filter = trim($_GET['user'] ?? '');

$where = "WHERE DATE(al.timestamp) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($user_filter) {
    $where  .= " AND (u.full_name LIKE ? OR u.user_id_number LIKE ?)";
    $params[] = "%$user_filter%";
    $params[] = "%$user_filter%";
}

$stmt = $pdo->prepare("
    SELECT al.*, u.full_name as user_name, u.user_id_number, l.name as locker_name
    FROM activity_logs al
    LEFT JOIN users u   ON al.user_id   = u.id
    LEFT JOIN lockers l ON al.locker_id = l.id
    $where
    ORDER BY al.timestamp DESC
    LIMIT 200
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <h4 class="fw-bold mb-1"><i class="fas fa-history me-2 text-info"></i>Activity Logs</h4>
        <small class="text-muted">Track all locker access activities</small>
    </div>

    <!-- Filters -->
    <div class="card section-card mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">From</label>
                    <input type="date" name="from" value="<?php echo $date_from; ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">To</label>
                    <input type="date" name="to" value="<?php echo $date_to; ?>" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">User (name or ID)</label>
                    <input type="text" name="user" value="<?php echo htmlspecialchars($user_filter); ?>" class="form-control" placeholder="Filter by user...">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary rounded-pill w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card section-card">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <small class="text-muted">Showing <?php echo count($logs); ?> records</small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-history fa-3x mb-3 opacity-25"></i>
                <p>No activity found for this period.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Date/Time</th>
                            <th>User</th>
                            <th>Locker</th>
                            <th>Action</th>
                            <th class="pe-4">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="ps-4">
                                <small><?php echo date('d M Y', strtotime($log['timestamp'])); ?></small><br>
                                <small class="text-muted"><?php echo date('H:i:s', strtotime($log['timestamp'])); ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($log['user_name'] ?? 'N/A'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($log['user_id_number'] ?? ''); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($log['locker_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge bg-info rounded-pill">
                                    <?php echo strtoupper($log['action'] ?? 'ACCESS'); ?>
                                </span>
                            </td>
                            <td class="pe-4">
                                <?php if ($log['success']): ?>
                                <span class="badge bg-success rounded-pill"><i class="fas fa-check me-1"></i>Success</span>
                                <?php else: ?>
                                <span class="badge bg-danger rounded-pill"><i class="fas fa-times me-1"></i>Failed</span>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>