<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'student';

// Get all lockers assigned to user
$stmt = $pdo->prepare("
    SELECT 
        la.id as assignment_id,
        la.locker_id,
        la.assigned_at,
        la.is_active,
        la.key_value,
        la.custom_name,
        la.custom_location,
        COALESCE(la.custom_name, l.unique_code) as display_name,
        COALESCE(la.custom_location, '') as display_location,
        l.status as locker_status,
        l.unique_code,
        l.device_id
    FROM user_locker_assignments la
    LEFT JOIN lockers l ON la.locker_id = l.id
    WHERE la.user_id = ? AND la.is_active = 1
    ORDER BY la.assigned_at DESC
");
$stmt->execute([$user_id]);
$lockers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($lockers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Lockers — Smart Locker Institution</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{
            --teal:#0d7377;--teal-d:#085c60;--teal-l:#e8f6f7;
            --mint:#14a98a;--dark:#0f1f20;--mid:#3d5c5e;
            --light:#f4f9f9;--white:#fff;--border:#d0e8e9;
            --sidebar-w:240px;
        }
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--light);color:var(--dark);display:flex;min-height:100vh;}

        /* ── SIDEBAR ── */
        .sidebar{
            width:var(--sidebar-w);background:#0f1f20;
            display:flex;flex-direction:column;
            position:fixed;top:0;left:0;bottom:0;z-index:50;
        }
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

        /* ── MAIN ── */
        .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
        .topbar{background:white;border-bottom:1px solid var(--border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;}
        .topbar h1{font-size:18px;font-weight:800;color:var(--dark);}
        .topbar p{font-size:12px;color:var(--mid);margin-top:2px;}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:9px;font-family:inherit;font-size:13px;font-weight:700;border:none;cursor:pointer;text-decoration:none;transition:all 0.2s;}
        .btn-primary{background:var(--teal);color:white;}
        .btn-primary:hover{background:var(--teal-d);}
        .btn-outline{background:white;color:var(--teal);border:2px solid var(--border);}
        .btn-outline:hover{border-color:var(--teal);background:var(--teal-l);}
        .btn-sm{padding:7px 14px;font-size:12px;}

        /* ── CONTENT ── */
        .content{padding:28px 32px;flex:1;}

        /* ── LOCKER GRID ── */
        .locker-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;}

        /* ── LOCKER CARD ── */
        .locker-card{
            background:white;border:1.5px solid var(--border);
            border-radius:16px;overflow:hidden;
            transition:transform 0.25s ease, box-shadow 0.25s ease;
            animation:fadeUp 0.5s ease both;
        }
        .locker-card:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(13,115,119,0.12);}
        @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

        .card-head{padding:18px 20px;position:relative;overflow:hidden;}
        .card-head::after{content:'🔒';position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:36px;opacity:0.12;}
        .card-head.status-active     {background:linear-gradient(135deg,var(--teal),var(--teal-d));}
        .card-head.status-available  {background:linear-gradient(135deg,#0096c7,#48cae4);}
        .card-head.status-occupied   {background:linear-gradient(135deg,var(--teal),var(--mint));}
        .card-head.status-maintenance{background:linear-gradient(135deg,#c0392b,#e74c3c);}

        .card-head-name{color:white;font-size:15px;font-weight:700;margin-bottom:3px;}
        .card-head-loc{color:rgba(255,255,255,0.7);font-size:12px;}
        .status-badge{
            display:inline-block;font-size:10px;font-weight:700;letter-spacing:0.07em;
            text-transform:uppercase;padding:3px 10px;border-radius:20px;
            background:rgba(255,255,255,0.2);color:white;
            position:absolute;top:14px;right:14px;
        }

        .card-body{padding:18px 20px;}
        .info-row{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px;}
        .info-row:last-child{border-bottom:none;}
        .info-row .lbl{color:var(--mid);width:100px;flex-shrink:0;font-size:12px;font-weight:600;}
        .info-row .val{color:var(--dark);font-size:12px;}
        code{background:var(--teal-l);color:var(--teal);padding:2px 8px;border-radius:6px;font-size:11px;font-family:monospace;}

        .qr-section{text-align:center;padding:16px 0 8px;}
        .qr-wrap{background:white;padding:10px;border-radius:12px;display:inline-block;border:1.5px solid var(--border);}
        .qr-label{font-size:11px;color:var(--mid);margin-top:8px;}

        .card-foot{padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;}
        .card-foot .btn{flex:1;justify-content:center;}

        /* ── ADD CARD ── */
        .add-card{
            background:white;border:2px dashed var(--border);border-radius:16px;
            min-height:280px;display:flex;align-items:center;justify-content:center;
            text-decoration:none;transition:all 0.2s;cursor:pointer;
        }
        .add-card:hover{border-color:var(--teal);background:var(--teal-l);}
        .add-card-inner{text-align:center;color:var(--mid);}
        .add-card-inner i{font-size:32px;color:var(--teal);opacity:0.5;margin-bottom:10px;display:block;}
        .add-card-inner strong{display:block;color:var(--teal);font-size:14px;margin-bottom:4px;}
        .add-card-inner span{font-size:12px;}

        /* ── EMPTY STATE ── */
        .empty-state{
            text-align:center;padding:60px 20px;
            background:white;border:1.5px solid var(--border);
            border-radius:16px;max-width:480px;margin:0 auto;
        }
        .empty-state i{font-size:48px;opacity:0.15;display:block;margin-bottom:16px;}
        .empty-state h3{font-size:18px;font-weight:700;color:var(--dark);margin-bottom:8px;}
        .empty-state p{font-size:13px;color:var(--mid);margin-bottom:24px;}

        footer{padding:16px 32px;border-top:1px solid var(--border);font-size:11px;color:#aaa;display:flex;justify-content:space-between;}

        @media(max-width:900px){
            .sidebar{display:none;}
            .main{margin-left:0;}
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
        <div class="brand-text">
            <strong>Smart Locker</strong>
            <span>Institution</span>
        </div>
    </div>
    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name,0,1)); ?></div>
            <div>
                <strong><?php echo htmlspecialchars($user_name); ?></strong>
                <span class="user-badge <?php echo $user_type==='staff'?'badge-staff':'badge-student'; ?>">
                    <?php echo ucfirst($user_type); ?>
                </span>
            </div>
        </div>
    </div>
    <div class="nav-section">Menu</div>
    <a href="dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="my-locker.php" class="nav-item active"><i class="fas fa-box"></i> My Lockers</a>
    <a href="assign-locker.php" class="nav-item"><i class="fas fa-plus-circle"></i> Assign Locker</a>
    <a href="scan-access.php" class="nav-item"><i class="fas fa-qrcode"></i> Scan Access</a>
    <a href="access-logs.php" class="nav-item"><i class="fas fa-history"></i> Activity Logs</a>
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
            <h1>My Lockers</h1>
            <p><?php echo $total; ?> locker<?php echo $total!=1?'s':''; ?> assigned to your account</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="scan-access.php" class="btn btn-outline btn-sm"><i class="fas fa-qrcode"></i> Scan</a>
            <a href="assign-locker.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Assign Locker</a>
        </div>
    </div>

    <div class="content">
        <?php if ($total === 0): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>No Lockers Assigned</h3>
            <p>You haven't assigned any locker yet.<br>Go to assign lockers to assign one.</p>
            <a href="assign-locker.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Assign First Locker
            </a>
        </div>

        <?php else: ?>
        <div class="locker-grid">
            <?php foreach ($lockers as $i => $locker): ?>
            <?php
                $status = $locker['locker_status'] ?? 'available';
                $statusLabels = [
                    'active'      => 'Active',
                    'available'   => 'Available',
                    'occupied'    => 'Occupied',
                    'maintenance' => 'Maintenance',
                ];
                $label = $statusLabels[$status] ?? ucfirst($status);
            ?>
            <div class="locker-card" style="animation-delay:<?php echo $i*0.07; ?>s">
                <!-- Header -->
                <div class="card-head status-<?php echo $status; ?>">
                    <span class="status-badge"><?php echo $label; ?></span>
                    <div class="card-head-name">
                        <i class="fas fa-box" style="margin-right:7px;opacity:0.8"></i>
                        <?php echo htmlspecialchars($locker['display_name']); ?>
                    </div>
                    <div class="card-head-loc">
                        <?php if ($locker['display_location']): ?>
                        <i class="fas fa-map-marker-alt" style="margin-right:4px"></i><?php echo htmlspecialchars($locker['display_location']); ?>
                        <?php else: ?>
                        Code: <?php echo htmlspecialchars($locker['unique_code']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Body -->
                <div class="card-body">
                    <div class="info-row">
                        <span class="lbl"><i class="fas fa-barcode" style="color:var(--teal);margin-right:5px"></i>Code</span>
                        <code><?php echo htmlspecialchars($locker['unique_code'] ?? 'N/A'); ?></code>
                    </div>
                    <div class="info-row">
                        <span class="lbl"><i class="fas fa-calendar" style="color:var(--teal);margin-right:5px"></i>Assigned</span>
                        <span class="val"><?php echo date('d M Y', strtotime($locker['assigned_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="lbl"><i class="fas fa-microchip" style="color:var(--teal);margin-right:5px"></i>Device</span>
                        <span class="val" style="color:#aaa"><?php echo htmlspecialchars($locker['device_id'] ?? '—'); ?></span>
                    </div>

                </div>

                <!-- Footer -->
                <div class="card-foot">
                    <a href="locker-details.php?id=<?php echo (int)$locker['locker_id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> Details
                    </a>
                    <a href="scan-access.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-qrcode"></i> Scan
                    </a>
                    <a href="edit-locker.php?id=<?php echo (int)$locker['locker_id']; ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Add New Locker Card -->
            <a href="assign-locker.php" class="add-card">
                <div class="add-card-inner">
                    <i class="fas fa-plus-circle"></i>
                    <strong>Assign New Locker</strong>
                    <span>Click to add a locker</span>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <footer>
        <span>© 2026 Smart Locker System — Institution Version</span>
        <span><?php echo htmlspecialchars($user_name); ?> · <?php echo date('d M Y, H:i'); ?></span>
    </footer>
</div>

<script>
</script>
</body>
</html>