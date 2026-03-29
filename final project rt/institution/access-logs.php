<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'student';

// Filter
$filter_method = $_GET['method'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search        = trim($_GET['search'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

// Build query
$where   = ["al.user_id = ?"];
$params  = [$user_id];

if ($filter_method) { $where[] = "al.access_method = ?"; $params[] = $filter_method; }
if ($filter_status === 'success') { $where[] = "al.success = 1"; }
if ($filter_status === 'failed')  { $where[] = "al.success = 0"; }
if ($search) {
    $where[]  = "(l.unique_code LIKE ? OR l.device_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM activity_logs al
    LEFT JOIN lockers l ON al.locker_id = l.id
    WHERE $whereSQL
");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages   = ceil($total_records / $per_page);

// Fetch logs
$params_paged = array_merge($params, [$per_page, $offset]);
$stmt = $pdo->prepare("
    SELECT al.*, 
           l.unique_code,
           l.device_id as locker_device,
           COALESCE(ula.custom_name, l.unique_code) as locker_display
    FROM activity_logs al
    LEFT JOIN lockers l ON al.locker_id = l.id
    LEFT JOIN user_locker_assignments ula ON (ula.locker_id = al.locker_id AND ula.user_id = al.user_id)
    WHERE $whereSQL
    ORDER BY al.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params_paged);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(success = 1) as success_count,
        SUM(success = 0) as failed_count,
        MAX(timestamp) as last_access
    FROM activity_logs WHERE user_id = ?
");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs — Smart Locker Institution</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{
            --teal:#0d7377;--teal-d:#085c60;--teal-l:#e8f6f7;
            --dark:#0f1f20;--mid:#3d5c5e;--light:#f4f9f9;
            --white:#fff;--border:#d0e8e9;--sidebar-w:240px;
        }
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--light);color:var(--dark);display:flex;min-height:100vh;}

        /* SIDEBAR */
        .sidebar{width:var(--sidebar-w);background:#0f1f20;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50;}
        .sidebar-brand{padding:22px 20px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:10px;}
        .brand-icon{width:36px;height:36px;background:var(--teal);border-radius:9px;display:grid;place-items:center;font-size:16px;flex-shrink:0;}
        .brand-text strong{display:block;color:white;font-size:13px;font-weight:700;}
        .brand-text span{font-size:10px;color:rgba(255,255,255,0.4);letter-spacing:0.08em;text-transform:uppercase;}
        .sidebar-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.07);}
        .user-info{display:flex;align-items:center;gap:10px;}
        .user-avatar{width:38px;height:38px;border-radius:50%;background:var(--teal);display:grid;place-items:center;color:white;font-size:15px;font-weight:700;flex-shrink:0;}
        .user-info strong{color:white;font-size:13px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;}
        .user-badge{display:inline-block;font-size:10px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:2px 8px;border-radius:20px;margin-top:3px;}
        .badge-student{background:rgba(13,115,119,0.3);color:#6de8ec;}
        .badge-staff{background:rgba(20,169,138,0.3);color:#6de8c0;}
        .nav-section{padding:16px 12px 8px;font-size:10px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.25);}
        .nav-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:9px;margin:2px 8px;color:rgba(255,255,255,0.55);font-size:13px;font-weight:500;text-decoration:none;transition:all 0.2s;}
        .nav-item i{width:18px;text-align:center;font-size:13px;}
        .nav-item:hover{background:rgba(255,255,255,0.07);color:white;}
        .nav-item.active{background:var(--teal);color:white;}
        .sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,0.07);}
        .logout-btn{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;color:rgba(255,255,255,0.45);font-size:13px;text-decoration:none;transition:all 0.2s;width:100%;}
        .logout-btn:hover{background:rgba(220,50,50,0.15);color:#ff7b7b;}

        /* MAIN */
        .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
        .topbar{background:white;border-bottom:1px solid var(--border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;}
        .topbar h1{font-size:18px;font-weight:800;color:var(--dark);}
        .topbar p{font-size:12px;color:var(--mid);margin-top:2px;}
        .content{padding:28px 32px;flex:1;}

        /* STATS */
        .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
        .stat-card{background:white;border:1.5px solid var(--border);border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px;animation:fadeUp 0.4s ease both;}
        .stat-card:nth-child(2){animation-delay:0.07s;}
        .stat-card:nth-child(3){animation-delay:0.14s;}
        .stat-icon{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;font-size:16px;flex-shrink:0;}
        .si-total{background:var(--teal-l);color:var(--teal);}
        .si-success{background:#e8f7f0;color:#0a7c63;}
        .si-failed{background:#fdf0ef;color:#c0392b;}
        .stat-num{font-size:22px;font-weight:800;color:var(--dark);}
        .stat-lbl{font-size:11px;color:var(--mid);margin-top:2px;}

        /* FILTER */
        .filter-bar{background:white;border:1.5px solid var(--border);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
        .filter-bar input,.filter-bar select{
            padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;
            font-family:inherit;font-size:13px;color:var(--dark);background:var(--light);
            outline:none;transition:border-color 0.2s;
        }
        .filter-bar input:focus,.filter-bar select:focus{border-color:var(--teal);}
        .filter-bar input{flex:1;min-width:180px;}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:8px;font-family:inherit;font-size:13px;font-weight:700;border:none;cursor:pointer;text-decoration:none;transition:all 0.2s;}
        .btn-primary{background:var(--teal);color:white;}
        .btn-primary:hover{background:var(--teal-d);}
        .btn-outline{background:white;color:var(--mid);border:1.5px solid var(--border);}
        .btn-outline:hover{border-color:var(--teal);color:var(--teal);}

        /* TABLE */
        .table-wrap{background:white;border:1.5px solid var(--border);border-radius:12px;overflow:hidden;animation:fadeUp 0.5s ease both;}
        @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
        table{width:100%;border-collapse:collapse;}
        thead th{background:var(--teal-l);color:var(--teal);font-size:11px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;padding:12px 16px;text-align:left;}
        tbody tr{border-bottom:1px solid var(--border);transition:background 0.15s;}
        tbody tr:hover{background:var(--teal-l);}
        tbody tr:last-child{border-bottom:none;}
        td{padding:12px 16px;font-size:13px;vertical-align:middle;}

        /* BADGES */
        .badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
        .badge-success{background:#e8f7f0;color:#0a7c63;}
        .badge-failed {background:#fdf0ef;color:#c0392b;}
        .badge-qr     {background:#e8f0fd;color:#2d4eaa;}
        .badge-web    {background:#e8f7f0;color:#0a7c63;}
        .badge-app    {background:#fff8e1;color:#856404;}
        .badge-key    {background:#f0f0f0;color:#555;}
        .badge-card   {background:var(--teal-l);color:var(--teal);}

        code{background:var(--teal-l);color:var(--teal);padding:2px 7px;border-radius:5px;font-size:11px;}

        /* PAGINATION */
        .pagination{display:flex;align-items:center;justify-content:space-between;margin-top:20px;font-size:13px;}
        .pag-info{color:var(--mid);}
        .pag-links{display:flex;gap:6px;}
        .pag-links a,.pag-links span{
            display:inline-flex;align-items:center;justify-content:center;
            width:32px;height:32px;border-radius:7px;font-size:13px;font-weight:600;
            text-decoration:none;border:1.5px solid var(--border);color:var(--mid);
            transition:all 0.2s;
        }
        .pag-links a:hover{border-color:var(--teal);color:var(--teal);}
        .pag-links span.current{background:var(--teal);color:white;border-color:var(--teal);}

        /* EMPTY */
        .empty-state{text-align:center;padding:56px 20px;color:var(--mid);}
        .empty-state i{font-size:44px;opacity:0.15;display:block;margin-bottom:14px;}
        .empty-state h3{font-size:16px;font-weight:700;color:var(--dark);margin-bottom:6px;}

        footer{padding:16px 32px;border-top:1px solid var(--border);font-size:11px;color:#aaa;display:flex;justify-content:space-between;}

        @media(max-width:900px){
            .sidebar{display:none;}.main{margin-left:0;}
            .stats-row{grid-template-columns:1fr 1fr;}
            .content{padding:20px 16px;}
            .topbar{padding:14px 16px;}
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🔒</div>
        <div class="brand-text"><strong>Smart Locker</strong><span>Institution</span></div>
    </div>
    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name,0,1)); ?></div>
            <div>
                <strong><?php echo htmlspecialchars($user_name); ?></strong>
                <span class="user-badge <?php echo $user_type==='staff'?'badge-staff':'badge-student'; ?>"><?php echo ucfirst($user_type); ?></span>
            </div>
        </div>
    </div>
    <div class="nav-section">Menu</div>
    <a href="dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="my-locker.php" class="nav-item"><i class="fas fa-box"></i> My Lockers</a>
    <a href="scan-access.php" class="nav-item"><i class="fas fa-qrcode"></i> Scan Access</a>
    <a href="access-logs.php" class="nav-item active"><i class="fas fa-history"></i> Activity Logs</a>
    <div class="nav-section">Account</div>
    <a href="profile.php" class="nav-item"><i class="fas fa-user-circle"></i> My Profile</a>
    <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <div>
            <h1>Activity Logs</h1>
            <p>Your locker access history</p>
        </div>
        <a href="access-logs.php" class="btn btn-outline"><i class="fas fa-sync"></i> Refresh</a>
    </div>

    <div class="content">

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon si-total"><i class="fas fa-history"></i></div>
                <div>
                    <div class="stat-num"><?php echo number_format($stats['total'] ?? 0); ?></div>
                    <div class="stat-lbl">Total Activities</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-success"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-num"><?php echo number_format($stats['success_count'] ?? 0); ?></div>
                    <div class="stat-lbl">Successful</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-failed"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="stat-num"><?php echo number_format($stats['failed_count'] ?? 0); ?></div>
                    <div class="stat-lbl">Failed Attempts</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" class="filter-bar">
            <input type="text" name="search" placeholder="🔍  Search locker code or device..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="method">
                <option value="">All Methods</option>
                <option value="qr_code"   <?php echo $filter_method==='qr_code'?'selected':''; ?>>QR Code</option>
                <option value="web"       <?php echo $filter_method==='web'?'selected':''; ?>>Web</option>
                <option value="mobile_app"<?php echo $filter_method==='mobile_app'?'selected':''; ?>>Mobile App</option>
                <option value="manual_key"<?php echo $filter_method==='manual_key'?'selected':''; ?>>Manual Key</option>
                <option value="card_scan" <?php echo $filter_method==='card_scan'?'selected':''; ?>>Card Scan</option>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="success" <?php echo $filter_status==='success'?'selected':''; ?>>Success</option>
                <option value="failed"  <?php echo $filter_status==='failed'?'selected':''; ?>>Failed</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search || $filter_method || $filter_status): ?>
            <a href="access-logs.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>

        <!-- Table -->
        <div class="table-wrap">
            <?php if (count($logs) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Locker</th>
                        <th>Device</th>
                        <th>Method</th>
                        <th>Key Used</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $method = $log['access_method'] ?? 'web';
                        $methodMap = [
                            'qr_code'    => ['class'=>'badge-qr',   'icon'=>'fa-qrcode',      'label'=>'QR Code'],
                            'web'        => ['class'=>'badge-web',  'icon'=>'fa-globe',        'label'=>'Web'],
                            'mobile_app' => ['class'=>'badge-app',  'icon'=>'fa-mobile-alt',   'label'=>'Mobile'],
                            'manual_key' => ['class'=>'badge-key',  'icon'=>'fa-key',          'label'=>'Manual Key'],
                            'card_scan'  => ['class'=>'badge-card', 'icon'=>'fa-id-card',      'label'=>'Card Scan'],
                        ];
                        $m = $methodMap[$method] ?? ['class'=>'badge-key','icon'=>'fa-circle','label'=>ucfirst($method)];
                        $timestamp = $log['timestamp'] ?? $log['timestamp'] ?? null;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($log['locker_display'] ?? 'Locker #'.$log['locker_id']); ?></strong>
                            <?php if (!empty($log['unique_code'])): ?>
                            <br><code><?php echo htmlspecialchars($log['unique_code']); ?></code>
                            <?php endif; ?>
                        </td>
                        <td style="font-family:monospace;font-size:12px;color:var(--mid)">
                            <?php echo htmlspecialchars($log['locker_device'] ?? $log['device_id'] ?? '—'); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $m['class']; ?>">
                                <i class="fas <?php echo $m['icon']; ?>"></i> <?php echo $m['label']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($log['key_used'])): ?>
                            <code title="<?php echo htmlspecialchars($log['key_used']); ?>">
                                <?php echo substr(htmlspecialchars($log['key_used']),0,12).'...'; ?>
                            </code>
                            <?php else: ?>
                            <span style="color:#ccc">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['success'] == 1): ?>
                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Success</span>
                            <?php else: ?>
                            <span class="badge badge-failed"><i class="fas fa-times-circle"></i> Failed</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:var(--mid)">
                            <?php echo $timestamp ? date('d M Y, H:i', strtotime($timestamp)) : '—'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="padding:16px 20px;border-top:1px solid var(--border);">
                <div class="pagination">
                    <span class="pag-info">
                        Showing <?php echo ($offset+1); ?>–<?php echo min($offset+$per_page, $total_records); ?> of <?php echo $total_records; ?> records
                    </span>
                    <div class="pag-links">
                        <?php
                        $qStr = http_build_query(array_filter(['method'=>$filter_method,'status'=>$filter_status,'search'=>$search]));
                        if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&<?php echo $qStr; ?>"><i class="fas fa-chevron-left"></i></a>
                        <?php endif;
                        for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo $qStr; ?>"><?php echo $i; ?></a>
                        <?php endif; endfor;
                        if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&<?php echo $qStr; ?>"><i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <h3>No Activity Found</h3>
                <p style="font-size:13px;margin-bottom:20px">
                    <?php echo ($search || $filter_method || $filter_status) ? 'No results match your filter.' : 'Your activity log is empty. Start using your locker!'; ?>
                </p>
                <?php if ($search || $filter_method || $filter_status): ?>
                <a href="access-logs.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear Filter</a>
                <?php else: ?>
                <a href="my-locker.php" class="btn btn-primary"><i class="fas fa-box"></i> View My Lockers</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Summary bar -->
        <?php if ($stats['last_access']): ?>
        <div style="margin-top:16px;padding:12px 16px;background:white;border:1.5px solid var(--border);border-radius:10px;font-size:12px;color:var(--mid);display:flex;gap:20px;flex-wrap:wrap;">
            <span><i class="fas fa-clock" style="margin-right:5px;color:var(--teal)"></i>Last access: <strong><?php echo date('d M Y, H:i', strtotime($stats['last_access'])); ?></strong></span>
            <span><i class="fas fa-list" style="margin-right:5px;color:var(--teal)"></i>Showing page <?php echo $page; ?> of <?php echo max(1,$total_pages); ?></span>
        </div>
        <?php endif; ?>

    </div>

    <footer>
        <span>© 2026 Smart Locker System — Institution Version</span>
        <span><?php echo htmlspecialchars($user_name); ?> · <?php echo date('d M Y, H:i'); ?></span>
    </footer>
</div>

</body>
</html>