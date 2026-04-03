<?php
require_once 'config.php';
requireAdmin();

$filter_type   = sanitize($_GET['type'] ?? '');
$filter_search = sanitize($_GET['search'] ?? '');
$filter_date   = sanitize($_GET['date'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$per_page = 20;
$offset  = ($page-1)*$per_page;

$where  = "WHERE 1=1";
$params = [];
if ($filter_type)   { $where .= " AND al.access_method=?"; $params[] = $filter_type; }
if ($filter_search) { $where .= " AND (u.full_name LIKE ? OR u.user_id_number LIKE ?)"; $params = array_merge($params,["%$filter_search%","%$filter_search%"]); }
if ($filter_date)   { $where .= " AND DATE(al.timestamp)=?"; $params[] = $filter_date; }

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id $where");
$count_stmt->execute($params); $total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows/$per_page);

$stmt = $pdo->prepare("
    SELECT al.*, u.full_name, u.user_id_number, u.user_type,
           COALESCE(ula.custom_name, l.unique_code) as locker_name,
           COALESCE(ula.custom_location,'') as locker_location
    FROM activity_logs al
    LEFT JOIN users u ON u.id=al.user_id
    LEFT JOIN lockers l ON l.id=al.locker_id
    LEFT JOIN user_locker_assignments ula ON ula.locker_id=al.locker_id AND ula.user_id=al.user_id
    $where
    ORDER BY al.timestamp DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params); $logs = $stmt->fetchAll();

// Summary
$today_access = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(timestamp)=CURDATE() AND success=1")->fetchColumn();
$fail_count   = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE success=0")->fetchColumn();
$total_all    = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Activity Logs — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <div><h1>Activity Logs</h1><p><?php echo $total_rows; ?> records found</p></div>
        <a href="logs.php" class="btn btn-outline"><i class="fas fa-sync"></i> Refresh</a>
    </div>
    <div class="content">

        <!-- Stats -->
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
            <div class="stat-card">
                <div class="stat-icon si-teal">📊</div>
                <div><div class="stat-val"><?php echo $total_all; ?></div><div class="stat-label">Total Logs</div></div>
            </div>
            <div class="stat-card" style="animation-delay:0.06s">
                <div class="stat-icon si-blue">✅</div>
                <div><div class="stat-val"><?php echo $today_access; ?></div><div class="stat-label">Today Accesses</div></div>
            </div>
            <div class="stat-card" style="animation-delay:0.12s">
                <div class="stat-icon si-red">❌</div>
                <div><div class="stat-val"><?php echo $fail_count; ?></div><div class="stat-label">Failed Attempts</div></div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
            <input type="text" name="search" placeholder="Search user name or ID..."
                   value="<?php echo htmlspecialchars($filter_search); ?>"
                   style="padding:9px 14px;border:2px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;outline:none;flex:1;min-width:200px;">
            <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>"
                   style="padding:9px 12px;border:2px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;outline:none;">
            <select name="type" style="padding:9px 12px;border:2px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;outline:none;cursor:pointer;">
                <option value="">All Methods</option>
                <option value="qr_code"    <?php echo $filter_type==='qr_code'?'selected':''; ?>>QR Code</option>
                <option value="card_scan"  <?php echo $filter_type==='card_scan'?'selected':''; ?>>Card Scan</option>
                <option value="manual_key" <?php echo $filter_type==='manual_key'?'selected':''; ?>>Manual Key</option>
                <option value="web"        <?php echo $filter_type==='web'?'selected':''; ?>>Web</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <?php if ($filter_search||$filter_type||$filter_date): ?>
            <a href="logs.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </form>

        <div class="panel">
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>User</th><th>Locker</th><th>Method</th><th>Device</th><th>Time</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="fas fa-history"></i>No logs found.</div></td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td style="color:#aaa;font-size:11px"><?php echo $offset+$i+1; ?></td>
                        <td>
                            <div style="font-weight:600;font-size:12px"><?php echo htmlspecialchars($log['full_name'] ?? 'Unknown'); ?></div>
                            <div style="display:flex;gap:5px;align-items:center;margin-top:2px">
                                <span style="font-size:11px;color:#aaa"><?php echo htmlspecialchars($log['user_id_number'] ?? ''); ?></span>
                                <?php if ($log['user_type']): ?>
                                <span class="badge badge-<?php echo $log['user_type']; ?>" style="font-size:9px"><?php echo ucfirst($log['user_type']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:12px;font-weight:600"><?php echo htmlspecialchars($log['locker_name'] ?? '#'.$log['locker_id']); ?></div>
                            <?php if ($log['locker_location']): ?>
                            <div style="font-size:11px;color:#aaa"><i class="fas fa-map-marker-alt" style="font-size:9px"></i> <?php echo htmlspecialchars($log['locker_location']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-size:11px;background:var(--teal-l);color:var(--teal);padding:3px 9px;border-radius:20px;font-weight:600">
                                <?php echo ucfirst(str_replace('_',' ',$log['access_method'])); ?>
                            </span>
                        </td>
                        <td style="font-size:11px;color:#aaa;font-family:monospace"><?php echo htmlspecialchars($log['device_id'] ?: '—'); ?></td>
                        <td>
                            <div style="font-size:12px"><?php echo date('d M Y', strtotime($log['timestamp'])); ?></div>
                            <div style="font-size:11px;color:#aaa"><?php echo date('H:i:s', strtotime($log['timestamp'])); ?></div>
                        </td>
                        <td>
                            <span class="badge <?php echo $log['success']?'badge-ok':'badge-fail'; ?>">
                                <?php echo $log['success']?'✓ OK':'✗ FAIL'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="padding:12px 20px;">
                <div class="pagination">
                    <?php if ($page>1): ?><a href="?page=<?php echo $page-1; ?>&type=<?php echo urlencode($filter_type); ?>&search=<?php echo urlencode($filter_search); ?>&date=<?php echo urlencode($filter_date); ?>">←</a><?php endif; ?>
                    <?php for ($p=max(1,$page-2); $p<=min($total_pages,$page+2); $p++): ?>
                    <a href="?page=<?php echo $p; ?>&type=<?php echo urlencode($filter_type); ?>&search=<?php echo urlencode($filter_search); ?>&date=<?php echo urlencode($filter_date); ?>"
                       class="<?php echo $p==$page?'active':''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <?php if ($page<$total_pages): ?><a href="?page=<?php echo $page+1; ?>&type=<?php echo urlencode($filter_type); ?>&search=<?php echo urlencode($filter_search); ?>&date=<?php echo urlencode($filter_date); ?>">→</a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <footer>
        <span>© 2026 Smart Locker System — Institution Admin</span>
        <span><?php echo date('d M Y, H:i'); ?></span>
    </footer>
</div>
</body>
</html>