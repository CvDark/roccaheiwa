<?php
require_once 'config.php';
requireAdmin();

// Stats
$total_users   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_students= $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='student'")->fetchColumn();
$total_staff   = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='staff'")->fetchColumn();
$total_lockers = $pdo->query("SELECT COUNT(*) FROM lockers")->fetchColumn();
$avail_lockers = $pdo->query("SELECT COUNT(*) FROM lockers WHERE status='available'")->fetchColumn();
$total_access  = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE success=1")->fetchColumn();

// Recent logs
$recent = $pdo->query("
    SELECT al.*, u.full_name, u.user_id_number, u.user_type,
           COALESCE(ula.custom_name, l.unique_code) as locker_name
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    LEFT JOIN lockers l ON l.id = al.locker_id
    LEFT JOIN user_locker_assignments ula ON ula.locker_id=al.locker_id AND ula.user_id=al.user_id
    ORDER BY al.timestamp DESC LIMIT 8
")->fetchAll();

// Recent users
$new_users = $pdo->query("
    SELECT * FROM users ORDER BY created_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Dashboard — Institution</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <div>
            <h1>Dashboard</h1>
            <p><?php echo date('l, d F Y'); ?> · <?php echo htmlspecialchars($_SESSION['admin_institution'] ?? 'Institution'); ?></p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="users.php" class="btn btn-outline"><i class="fas fa-users"></i> Users</a>
            <a href="lockers.php" class="btn btn-primary"><i class="fas fa-box"></i> Lockers</a>
        </div>
    </div>

    <div class="content">
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon si-teal">👥</div>
                <div><div class="stat-val"><?php echo $total_users; ?></div><div class="stat-label">Total Users</div></div>
            </div>
            <div class="stat-card" style="animation-delay:0.06s">
                <div class="stat-icon si-blue">🎓</div>
                <div><div class="stat-val"><?php echo $total_students; ?></div><div class="stat-label">Students</div></div>
            </div>
            <div class="stat-card" style="animation-delay:0.12s">
                <div class="stat-icon si-gold">👔</div>
                <div><div class="stat-val"><?php echo $total_staff; ?></div><div class="stat-label">Staff</div></div>
            </div>
            <div class="stat-card" style="animation-delay:0.18s">
                <div class="stat-icon si-teal">🔒</div>
                <div><div class="stat-val"><?php echo $avail_lockers; ?>/<?php echo $total_lockers; ?></div><div class="stat-label">Available Lockers</div></div>
            </div>
            <div class="stat-card" style="animation-delay:0.24s">
                <div class="stat-icon si-blue">✅</div>
                <div><div class="stat-val"><?php echo $total_access; ?></div><div class="stat-label">Total Accesses</div></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <!-- Recent Activity -->
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fas fa-history" style="color:var(--teal);margin-right:7px"></i>Recent Activity</h3>
                    <a href="logs.php" class="btn btn-outline btn-sm">View All</a>
                </div>
                <div class="tbl-wrap">
                    <table>
                        <thead><tr><th>User</th><th>Locker</th><th>Method</th><th>Time</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (empty($recent)): ?>
                        <tr><td colspan="5" style="text-align:center;color:#aaa;padding:24px">No activity yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($recent as $log): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;font-size:12px"><?php echo htmlspecialchars($log['full_name'] ?? 'Unknown'); ?></div>
                                <div style="font-size:11px;color:#aaa"><?php echo htmlspecialchars($log['user_id_number'] ?? ''); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($log['locker_name'] ?? '#'.$log['locker_id']); ?></td>
                            <td><span style="font-size:11px"><?php echo ucfirst(str_replace('_',' ',$log['access_method'])); ?></span></td>
                            <td style="font-size:11px;color:#aaa"><?php echo date('d M H:i', strtotime($log['timestamp'])); ?></td>
                            <td><span class="badge <?php echo $log['success']?'badge-ok':'badge-fail'; ?>"><?php echo $log['success']?'OK':'FAIL'; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- New Users -->
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fas fa-user-plus" style="color:var(--teal);margin-right:7px"></i>New Users</h3>
                    <a href="users.php" class="btn btn-outline btn-sm">View All</a>
                </div>
                <div class="tbl-wrap">
                    <table>
                        <thead><tr><th>Name</th><th>ID</th><th>Type</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php if (empty($new_users)): ?>
                        <tr><td colspan="4" style="text-align:center;color:#aaa;padding:24px">No users yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($new_users as $u): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;font-size:12px"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                <div style="font-size:11px;color:#aaa"><?php echo htmlspecialchars($u['email']); ?></div>
                            </td>
                            <td style="font-size:12px"><?php echo htmlspecialchars($u['user_id_number']); ?></td>
                            <td><span class="badge badge-<?php echo $u['user_type']; ?>"><?php echo ucfirst($u['user_type']); ?></span></td>
                            <td style="font-size:11px;color:#aaa"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <footer>
        <span>© 2026 Smart Locker System — Institution Admin</span>
        <span>Logged in: <?php echo htmlspecialchars($_SESSION['admin_name']); ?> · <?php echo date('H:i'); ?></span>
    </footer>
</div>
</body>
</html>