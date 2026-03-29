<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'student';
$institution = $_SESSION['institution'] ?? '';

// ── Stats ──
try {
    // Assigned lockers
    $s = $pdo->prepare("SELECT COUNT(*) FROM user_locker_assignments WHERE user_id = ? AND is_active = 1");
    $s->execute([$user_id]); $total_lockers = $s->fetchColumn();

    // Recent activity logs
    $s2 = $pdo->prepare("
        SELECT al.*, 
               COALESCE(ula.custom_name, l.unique_code) as locker_name,
               COALESCE(ula.custom_location, '') as location
        FROM activity_logs al
        LEFT JOIN lockers l ON l.id = al.locker_id
        LEFT JOIN user_locker_assignments ula ON ula.locker_id = al.locker_id AND ula.user_id = al.user_id
        WHERE al.user_id = ?
        ORDER BY al.timestamp DESC LIMIT 5
    ");
    $s2->execute([$user_id]); $recent_logs = $s2->fetchAll();

    // My lockers
    $s3 = $pdo->prepare("
        SELECT la.*, l.unique_code, l.status, l.device_id,
               COALESCE(la.custom_name, l.unique_code) as display_name,
               COALESCE(la.custom_location, '') as display_location
        FROM user_locker_assignments la
        JOIN lockers l ON l.id = la.locker_id
        WHERE la.user_id = ? AND la.is_active = 1
        ORDER BY la.assigned_at DESC
    ");
    $s3->execute([$user_id]); $my_lockers = $s3->fetchAll();

    // Total access count
    $s4 = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND success = 1");
    $s4->execute([$user_id]); $total_access = $s4->fetchColumn();

} catch (Exception $e) {
    $total_lockers = 0; $recent_logs = []; $my_lockers = []; $total_access = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Smart Locker Institution</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --teal:   #0d7377;
            --teal-d: #085c60;
            --teal-l: #e8f6f7;
            --mint:   #14a98a;
            --dark:   #0f1f20;
            --mid:    #3d5c5e;
            --light:  #f4f9f9;
            --white:  #ffffff;
            --border: #d0e8e9;
            --sidebar-w: 240px;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light);
            color: var(--dark);
            display: flex; min-height: 100vh;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--dark);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; bottom: 0;
            z-index: 50; padding: 0;
        }
        .sidebar-brand {
            padding: 22px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            display: flex; align-items: center; gap: 10px;
        }
        .brand-icon {
            width: 36px; height: 36px; background: var(--teal);
            border-radius: 9px; display: grid; place-items: center;
            font-size: 16px; flex-shrink: 0;
        }
        .brand-text strong {
            display: block; color: white; font-size: 13px; font-weight: 700;
        }
        .brand-text span {
            font-size: 10px; color: rgba(255,255,255,0.4);
            letter-spacing: 0.08em; text-transform: uppercase;
        }

        .sidebar-user {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .user-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--teal);
            display: grid; place-items: center;
            color: white; font-size: 15px; font-weight: 700;
            flex-shrink: 0;
        }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-info strong { color: white; font-size: 13px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
        .user-info span   { font-size: 11px; color: rgba(255,255,255,0.45); }
        .user-badge {
            display: inline-block; font-size: 10px; font-weight: 700;
            letter-spacing: 0.06em; text-transform: uppercase;
            padding: 2px 8px; border-radius: 20px; margin-top: 3px;
        }
        .badge-student { background: rgba(13,115,119,0.3);color:#6de8ec;}
        .badge-staff   { background: rgba(20,169,138,0.3); color: #6de8c0; }

        .nav-section {
            padding: 16px 12px 8px;
            font-size: 10px; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: rgba(255,255,255,0.25);
        }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px; border-radius: 9px; margin: 2px 8px;
            color: rgba(255,255,255,0.55); font-size: 13px; font-weight: 500;
            text-decoration: none; transition: all 0.2s;
        }
        .nav-item i { width: 18px; text-align: center; font-size: 13px; }
        .nav-item:hover { background: rgba(255,255,255,0.07); color: white; }
        .nav-item.active { background: var(--teal); color: white; }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.07);
        }
        .logout-btn {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; border-radius: 9px;
            color: rgba(255,255,255,0.45); font-size: 13px;
            text-decoration: none; transition: all 0.2s;
            width: 100%;
        }
        .logout-btn:hover { background: rgba(220,50,50,0.15); color: #ff7b7b; }

        /* ── MAIN ── */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1; display: flex; flex-direction: column;
            min-height: 100vh;
        }

        /* Top bar */
        .topbar {
            background: white; border-bottom: 1px solid var(--border);
            padding: 16px 32px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 40;
        }
        .topbar-title h1 { font-size: 18px; font-weight: 800; color: var(--dark); }
        .topbar-title p  { font-size: 12px; color: var(--mid); margin-top: 2px; }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .btn-scan {
            display: flex; align-items: center; gap: 8px;
            background: var(--teal); color: white;
            padding: 9px 18px; border-radius: 9px;
            font-family: inherit; font-size: 13px; font-weight: 700;
            border: none; cursor: pointer; text-decoration: none;
            transition: background 0.2s;
        }
        .btn-scan:hover { background: var(--teal-d); }
        .btn-outline {
            display: flex; align-items: center; gap: 8px;
            background: white; color: var(--teal);
            padding: 9px 18px; border-radius: 9px;
            font-family: inherit; font-size: 13px; font-weight: 700;
            border: 2px solid var(--border); cursor: pointer; text-decoration: none;
            transition: all 0.2s;
        }
        .btn-outline:hover { border-color: var(--teal); background: var(--teal-l); }

        /* Content */
        .content { padding: 28px 32px; }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px; margin-bottom: 28px;
        }
        .stat-card {
            background: white; border: 1.5px solid var(--border);
            border-radius: 14px; padding: 20px;
            display: flex; align-items: center; gap: 16px;
            animation: fadeUp 0.5s ease both;
        }
        .stat-card:nth-child(2) { animation-delay: 0.07s; }
        .stat-card:nth-child(3) { animation-delay: 0.14s; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 11px;
            display: grid; place-items: center; font-size: 20px;
            flex-shrink: 0;
        }
        .stat-icon.teal  { background: var(--teal-l); }
        .stat-icon.mint  { background: rgba(20,169,138,0.12); }
        .stat-icon.amber { background: rgba(245,158,11,0.10); }
        .stat-val { font-size: 26px; font-weight: 800; color: var(--dark); line-height: 1; }
        .stat-label { font-size: 12px; color: var(--mid); margin-top: 4px; }

        /* Two-col layout */
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .panel {
            background: white; border: 1.5px solid var(--border);
            border-radius: 14px; overflow: hidden;
            animation: fadeUp 0.5s 0.15s ease both;
        }
        .panel-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .panel-header h3 { font-size: 14px; font-weight: 700; color: var(--dark); }
        .panel-header a  { font-size: 12px; color: var(--teal); text-decoration: none; font-weight: 600; }

        /* Locker card */
        .locker-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 14px;
            transition: background 0.15s;
        }
        .locker-item:last-child { border-bottom: none; }
        .locker-item:hover { background: var(--light); }
        .locker-thumb {
            width: 40px; height: 40px; border-radius: 10px;
            background: var(--teal-l); display: grid;
            place-items: center; font-size: 18px; flex-shrink: 0;
        }
        .locker-meta strong { font-size: 13px; color: var(--dark); display: block; }
        .locker-meta span   { font-size: 11px; color: var(--mid); }
        .locker-status {
            margin-left: auto; font-size: 10px; font-weight: 700;
            letter-spacing: 0.06em; text-transform: uppercase;
            padding: 3px 10px; border-radius: 20px;
        }
        .status-active  { background: rgba(13,115,119,0.10); color: var(--teal); }
        .status-occupied{ background: rgba(20,169,138,0.12); color: #0a8a6e; }
        .status-idle    { background: rgba(245,158,11,0.10); color: #b45309; }

        /* Log item */
        .log-item {
            padding: 13px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 12px;
        }
        .log-item:last-child { border-bottom: none; }
        .log-dot {
            width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
        }
        .log-dot.ok  { background: var(--mint); }
        .log-dot.err { background: #e74c3c; }
        .log-text strong { font-size: 12px; color: var(--dark); display: block; }
        .log-text span   { font-size: 11px; color: var(--mid); }
        .log-time { margin-left: auto; font-size: 11px; color: #aaa; white-space: nowrap; }

        .empty-state {
            padding: 32px 20px; text-align: center;
            color: var(--mid); font-size: 13px;
        }
        .empty-state i { font-size: 28px; margin-bottom: 8px; display: block; opacity: 0.3; }

        /* Institution badge */
        .inst-banner {
            background: var(--teal); border-radius: 14px;
            padding: 18px 24px; margin-bottom: 22px;
            display: flex; align-items: center; gap: 16px;
            color: white; animation: fadeUp 0.4s ease both;
        }
        .inst-banner i { font-size: 24px; opacity: 0.8; }
        .inst-banner strong { font-size: 15px; font-weight: 700; display: block; }
        .inst-banner span   { font-size: 12px; opacity: 0.75; }

        footer {
            margin-top: auto; padding: 18px 32px;
            border-top: 1px solid var(--border);
            font-size: 11px; color: #aaa;
            display: flex; justify-content: space-between;
        }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .grid2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🔒</div>
        <div class="brand-text">
            <strong>Smart Locker</strong>
            <span>Institution</span>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <div>
                <strong><?php echo htmlspecialchars($user_name); ?></strong>
                <span class="user-badge <?php echo $user_type === 'staff' ? 'badge-staff' : 'badge-student'; ?>">
                    <?php echo ucfirst($user_type); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="nav-section">Menu</div>
    <a href="dashboard.php" class="nav-item active">
        <i class="fas fa-th-large"></i> Dashboard
    </a>
    <a href="my-locker.php" class="nav-item">
        <i class="fas fa-box"></i> My Lockers
    </a>
    <a href="scan-access.php" class="nav-item">
        <i class="fas fa-qrcode"></i> Scan Access
    </a>
    <a href="access-logs.php" class="nav-item">
        <i class="fas fa-history"></i> Activity Logs
    </a>

    <div class="nav-section">Account</div>
    <a href="profile.php" class="nav-item">
        <i class="fas fa-user-circle"></i> My Profile
    </a>
    <a href="settings.php" class="nav-item">
        <i class="fas fa-cog"></i> Settings
    </a>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main">

    <!-- Top Bar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Dashboard</h1>
            <p><?php echo date('l, d F Y'); ?></p>
        </div>
        <div class="topbar-actions">
            <a href="scan-access.php" class="btn-outline">
                <i class="fas fa-qrcode"></i> Scan
            </a>
            <a href="my-locker.php" class="btn-scan">
                <i class="fas fa-box"></i> My Lockers
            </a>
        </div>
    </div>

    <div class="content">

        <!-- Institution Banner -->
        <?php if ($institution): ?>
        <div class="inst-banner">
            <i class="fas fa-university"></i>
            <div>
                <strong><?php echo htmlspecialchars($institution); ?></strong>
                <span>Institution Smart Locker Portal · <?php echo ucfirst($user_type); ?> Account</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon teal">🔒</div>
                <div>
                    <div class="stat-val"><?php echo $total_lockers; ?></div>
                    <div class="stat-label">Assigned Locker<?php echo $total_lockers != 1 ? 's' : ''; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon mint">✅</div>
                <div>
                    <div class="stat-val"><?php echo $total_access; ?></div>
                    <div class="stat-label">Total Accesses</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber">🎓</div>
                <div>
                    <div class="stat-val"><?php echo htmlspecialchars($_SESSION['user_id_number'] ?? '-'); ?></div>
                    <div class="stat-label">Your ID Number</div>
                </div>
            </div>
        </div>

        <!-- My Lockers + Recent Activity -->
        <div class="grid2">

            <!-- Lockers -->
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fas fa-box me-2" style="color:var(--teal)"></i> My Lockers</h3>
                    <a href="my-locker.php">View all →</a>
                </div>
                <?php if (empty($my_lockers)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    No lockers assigned yet.<br>
                    <a href="assign-locker.php" style="color:var(--teal);font-weight:600;">Assign a locker →</a>
                </div>
                <?php else: ?>
                    <?php foreach ($my_lockers as $lk): ?>
                    <div class="locker-item">
                        <div class="locker-thumb">🔒</div>
                        <div class="locker-meta">
                            <strong><?php echo htmlspecialchars($lk['display_name']); ?></strong>
                            <span>
                                <?php if ($lk['display_location']): ?>
                                    <i class="fas fa-map-marker-alt" style="font-size:10px"></i>
                                    <?php echo htmlspecialchars($lk['display_location']); ?>
                                <?php else: ?>
                                    Code: <?php echo htmlspecialchars($lk['unique_code']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <span class="locker-status status-<?php echo $lk['status'] === 'occupied' ? 'occupied' : 'active'; ?>">
                            <?php echo ucfirst($lk['status']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Activity Logs -->
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fas fa-history me-2" style="color:var(--teal)"></i> Recent Activity</h3>
                    <a href="access-logs.php">View all →</a>
                </div>
                <?php if (empty($recent_logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    No recent activity found.
                </div>
                <?php else: ?>
                    <?php foreach ($recent_logs as $log): ?>
                    <div class="log-item">
                        <div class="log-dot <?php echo $log['success'] ? 'ok' : 'err'; ?>"></div>
                        <div class="log-text">
                            <strong><?php echo htmlspecialchars($log['locker_name'] ?? 'Locker #'.$log['locker_id']); ?></strong>
                            <span><?php echo ucfirst(str_replace('_',' ',$log['access_method'] ?? '')); ?></span>
                        </div>
                        <span class="log-time">
                            <?php echo date('d M, H:i', strtotime($log['timestamp'])); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <footer>
        <span>© 2026 Smart Locker System — Institution Version</span>
        <span>Logged in as: <?php echo htmlspecialchars($user_name); ?> · <?php echo date('H:i'); ?></span>
    </footer>
</div>

</body>
</html>