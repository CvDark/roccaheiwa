<?php
require_once 'config.php';

// Pastikan session sudah bermula (biasanya dalam config.php, jika tiada tambah session_start())
ob_start(); // Start output buffering

// Set unique session name for Commercial edition BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_COMMERCIAL');
    session_start();
}

// Redirect jika belum login
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// AMBIL user_id daripada SESSION (Penyelesaian Ralat Line 9)
$user_id = $_SESSION['user_id']; 

// Fingerprint untuk pairing
$device_fingerprint = md5($_SERVER['HTTP_USER_AGENT']);
$is_paired = false;

// AMBIL DATA USER & PAIRING (Penyelesaian Ralat Line 12)
try {
    $stmt = $pdo->prepare("SELECT paired_device_token FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Semak jika userData wujud sebelum akses array
    if ($userData) {
        if ($userData['paired_device_token'] === $device_fingerprint) {
            $is_paired = true;
        }
    }
} catch (PDOException $e) {
    // Log ralat jika perlu
    error_log($e->getMessage());
}

// Redirect jika belum login
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check session variables
if (!isset($_SESSION['user_role'])) {
    // Session tidak lengkap, logout dan redirect
    session_destroy();
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'];
$user_type = $_SESSION['user_type'] ?? 'user';
$user_id_number = $_SESSION['user_id_number'] ?? 'Not Set';
$institution = $_SESSION['institution'] ?? 'Not Specified';

// Initialize variables
$active_lockers = [];
$recent_logs = [];
$today_access = 0;
$total_assigned = 0;

// Try to get user's active lockers dengan error handling
try {
    global $pdo;
    if ($pdo) {
        // Query untuk mendapatkan semua locker yang aktif untuk user ini
        $stmt = $pdo->prepare("
            SELECT la.*, 
                   l.name as locker_name, 
                   l.location, 
                   l.status as locker_status,
                   l.unique_code,
                   l.device_id,
                   COALESCE(la.custom_name, l.name, l.unique_code) as display_name,
                   COALESCE(la.custom_location, l.location, '') as display_location
            FROM user_locker_assignments la
            LEFT JOIN lockers l ON la.locker_id = l.id
            WHERE la.user_id = ? AND la.is_active = 1
            ORDER BY la.assigned_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $active_lockers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM user_locker_assignments 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$user_id]);
        $total_assigned = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get recent access logs
        $stmt = $pdo->prepare("
            SELECT al.*, l.name as locker_name
            FROM access_logs al
            LEFT JOIN lockers l ON al.locker_id = l.id
            WHERE al.user_id = ?
            ORDER BY al.timestamp DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get today's access count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM access_logs 
            WHERE user_id = ? AND DATE(timestamp) = CURDATE()
        ");
        $stmt->execute([$user_id]);
        // Pastikan timezone selaras dengan config.php
$todayDate = date('Y-m-d'); 

try {
    // Kita gunakan 'activity_logs' kerana unlock_locker.php simpan di sini
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM activity_logs 
        WHERE user_id = ? 
        AND DATE(created_at) = ? 
        AND success = 1
    ");
    
    $stmt->execute([$user_id, $todayDate]);
    $today_access = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    // Jika error (mungkin kolum bukan created_at), cuba 'timestamp'
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND DATE(timestamp) = ? AND success = 1");
        $stmt->execute([$user_id, $todayDate]);
        $today_access = $stmt->fetchColumn();
    } catch (PDOException $e2) {
        $today_access = 0;
    }
}
    }
} catch (PDOException $e) {
    // Database error, tapi jangan crash page
    $db_error = $e->getMessage();
    // Log error jika perlu
    error_log("Dashboard error: " . $db_error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.08); 
            transition: transform 0.3s;
            padding: 5px;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .navbar-brand { 
            font-weight: bold; 
        }
        .user-info-badge {
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.85rem;
        }
        .institution-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .locker-mini-card {
            border-left: 4px solid #3498db;
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .locker-mini-card:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .qr-access-card {
            background: linear-gradient(135deg, #9c27b0, #673ab7);
            color: white;
        }
        .scan-access-card {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: white;
        }
        .badge-custom {
            font-size: 0.7rem;
            padding: 4px 10px;
            border-radius: 10px;
        }
        .stats-number {
            font-size: 2.2rem;
            font-weight: bold;
            line-height: 1;
        }
        .quick-action-btn {
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            text-align: center;
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .quick-action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-lock me-2"></i>Smart Locker System
            </a>
            <div class="navbar-nav ms-auto align-items-center">
                <div class="d-flex align-items-center">
                    <!-- Institution Badge -->
                    <div class="user-info-badge me-3">
                        <i class="fas fa-university me-1"></i>
                        <?php echo htmlspecialchars($institution); ?>
                    </div>
                    
                    <!-- User Info -->
                    <span class="nav-link text-white">
                        <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($user_name); ?>
                        <span class="badge bg-info ms-1"><?php echo $user_role; ?></span>
                    </span>

                    
                    <!-- Logout -->
                    <a class="nav-link text-warning" href="logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- Institution Header -->
        <div class="institution-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-2">
                        <i class="fas fa-user-circle me-2"></i>
                        Welcome, <?php echo htmlspecialchars($user_name); ?>!
                    </h4>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-id-card me-1"></i>ID: <?php echo htmlspecialchars($user_id_number); ?>
                        </span>
                        <span class="badge bg-info">
                            <i class="fas fa-user-tag me-1"></i><?php echo ucfirst($user_type); ?>
                        </span>
                        <span class="badge bg-secondary">
                            <i class="fas fa-calendar me-1"></i><?php echo date('l, d F Y'); ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="scan-access.php" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-qrcode me-1"></i>Scan Access
                    </a>
                    <a href="generate-qr.php" target="_blank" class="btn btn-warning btn-sm">
                        <i class="fas fa-print me-1"></i>Print QR
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Database Connection Warning -->
        <?php if (isset($db_error)): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Database connection issue. Some features may not work properly.
            <small class="d-block mt-1">Error: <?php echo htmlspecialchars(substr($db_error, 0, 100)); ?>...</small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <!-- Total Lockers Card -->
            <div class="col-md-3 mb-3">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-2">My Lockers</h6>
                                <div class="stats-number"><?php echo $total_assigned; ?></div>
                                <small>Assigned to you</small>
                            </div>
                            <i class="fas fa-box fa-3x opacity-50"></i>
                        </div>
                        <?php if ($total_assigned > 0): ?>
                        <div class="mt-3">
                            <a href="my-locker.php" class="btn btn-sm btn-light w-100">
                                <i class="fas fa-eye me-1"></i>View All
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Today's Access Card -->
            <div class="col-md-3 mb-3">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-2">Today's Access</h6>
                                <div class="stats-number"><?php echo $today_access; ?></div>
                                <small>Access count today</small>
                            </div>
                            <i class="fas fa-door-open fa-3x opacity-50"></i>
                        </div>
                        <div class="mt-3">
                            <a href="access-logs.php" class="btn btn-sm btn-light w-100">
                                <i class="fas fa-history me-1"></i>View Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- QR Access Card -->
            <div class="col-md-3 mb-3">
                <div class="card dashboard-card qr-access-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-2">QR Access</h6>
                                <div class="stats-number"><?php echo $user_id_number; ?></div>
                                <small>Your Access ID</small>
                            </div>
                            <i class="fas fa-qrcode fa-3x opacity-50"></i>
                        </div>
                        <div class="mt-3">
                            <a href="generate-qr.php" target="_blank" class="btn btn-sm btn-light w-100">
                                <i class="fas fa-external-link-alt me-1"></i>Get QR Code
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Scan Access Card -->
            <div class="col-md-3 mb-3">
                <div class="card dashboard-card scan-access-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-2">Scan Station</h6>
                                <div class="stats-number">Ready</div>
                                <small>Access via ID/QR</small>
                            </div>
                            <i class="fas fa-mobile-alt fa-3x opacity-50"></i>
                        </div>
                        <div class="mt-3">
                            <a href="scan-access.php" class="btn btn-sm btn-light w-100">
                                <i class="fas fa-camera me-1"></i>Scan Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="row">
            <!-- Left Column: My Lockers & Quick Actions -->
            <div class="col-md-8">
                <!-- My Lockers Section -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-box me-2"></i>My Assigned Lockers</h5>
                        <span class="badge bg-light text-dark"><?php echo count($active_lockers); ?> active</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($active_lockers)): ?>
                            <div class="row">
                                <?php foreach ($active_lockers as $index => $locker): ?>
                                <?php if ($index < 6): // Tampilkan maksimal 6 locker ?>
                                <div class="col-md-6 mb-3">
                                    <div class="locker-mini-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($locker['display_name'] ?? ''); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt fa-xs me-1"></i>
                                                    <?php echo htmlspecialchars($locker['display_location'] ?? ''); ?>
                                                </small>
                                                <div class="mt-2">
                                                    <span class="badge-custom bg-<?php echo $locker['locker_status'] == 'occupied' ? 'success' : 'warning'; ?>">
                                                        <?php echo strtoupper($locker['locker_status']); ?>
                                                    </span>
                                                    <?php if ($locker['custom_name']): ?>
                                                    <span class="badge-custom bg-info">
                                                        <i class="fas fa-star fa-xs"></i> Custom
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" 
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="locker-details.php?id=<?php echo $locker['locker_id']; ?>">
                                                            <i class="fas fa-eye me-2"></i>View Details
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="edit-locker.php?id=<?php echo $locker['locker_id']; ?>">
                                                            <i class="fas fa-edit me-2"></i>Customize
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" 
                                                           href="remove_access.php?id=<?php echo $locker['locker_id']; ?>"
                                                           onclick="return confirm('Remove access to this locker?')">
                                                            <i class="fas fa-trash me-2"></i>Remove Access
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-barcode fa-xs me-1"></i>
                                                <?php echo htmlspecialchars($locker['unique_code']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($active_lockers) > 6): ?>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Showing 6 of <?php echo count($active_lockers); ?> lockers.
                                    <a href="my-locker.php">View all</a>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3 text-center">
                                <a href="assign-locker.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Assign New Locker
                                </a>
                                <a href="my-locker.php" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>View All Lockers
                                </a>
                            </div>
                            
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5>No Lockers Assigned</h5>
                                <p class="text-muted mb-4">Assign a locker to start using our service.</p>
                                <a href="assign-locker.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Assign Your First Locker
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions Section -->
                <div class="card dashboard-card">
                    <div class="card-header text-white" style="background: linear-gradient(135deg, #00b4d8, #0077b6);">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <!-- Assign Locker -->
                            <div class="col-6 col-md-3">
                                <a href="assign-locker.php" class="text-decoration-none">
                                    <div class="quick-action-card text-center p-3" style="background: linear-gradient(135deg, #4361ee, #3a0ca3); border-radius: 16px; color: white; transition: all 0.3s;">
                                        <div class="mb-2" style="background: rgba(255,255,255,0.15); border-radius: 12px; width:52px; height:52px; display:flex; align-items:center; justify-content:center; margin: 0 auto;">
                                            <i class="fas fa-plus fa-lg"></i>
                                        </div>
                                        <div style="font-size:13px; font-weight:600;">Assign Locker</div>
                                    </div>
                                </a>
                            </div>

                            <!-- Scan Access -->
                            <div class="col-6 col-md-3">
                                <a href="scan-access.php" class="text-decoration-none">
                                    <div class="quick-action-card text-center p-3" style="background: linear-gradient(135deg, #2d6a4f, #52b788); border-radius: 16px; color: white; transition: all 0.3s;">
                                        <div class="mb-2" style="background: rgba(255,255,255,0.15); border-radius: 12px; width:52px; height:52px; display:flex; align-items:center; justify-content:center; margin: 0 auto;">
                                            <i class="fas fa-qrcode fa-lg"></i>
                                        </div>
                                        <div style="font-size:13px; font-weight:600;">Scan Access</div>
                                    </div>
                                </a>
                            </div>

                            <!-- Print QR -->
                            <div class="col-6 col-md-3">
                                <a href="generate-qr.php" target="_blank" class="text-decoration-none">
                                    <div class="quick-action-card text-center p-3" style="background: linear-gradient(135deg, #f77f00, #d62828); border-radius: 16px; color: white; transition: all 0.3s;">
                                        <div class="mb-2" style="background: rgba(255,255,255,0.15); border-radius: 12px; width:52px; height:52px; display:flex; align-items:center; justify-content:center; margin: 0 auto;">
                                            <i class="fas fa-print fa-lg"></i>
                                        </div>
                                        <div style="font-size:13px; font-weight:600;">Print QR</div>
                                    </div>
                                </a>
                            </div>

                            <!-- Access Logs -->
                            <div class="col-6 col-md-3">
                                <a href="access-logs.php" class="text-decoration-none">
                                    <div class="quick-action-card text-center p-3" style="background: linear-gradient(135deg, #0096c7, #48cae4); border-radius: 16px; color: white; transition: all 0.3s;">
                                        <div class="mb-2" style="background: rgba(255,255,255,0.15); border-radius: 12px; width:52px; height:52px; display:flex; align-items:center; justify-content:center; margin: 0 auto;">
                                            <i class="fas fa-history fa-lg"></i>
                                        </div>
                                        <div style="font-size:13px; font-weight:600;">Access Logs</div>
                                    </div>
                                </a>
                            </div>

                            <!-- My Profile -->
                            <div class="col-6 col-md-3">
                                <a href="profile.php" class="text-decoration-none">
                                    <div class="quick-action-card text-center p-3" style="background: linear-gradient(135deg, #6c757d, #495057); border-radius: 16px; color: white; transition: all 0.3s;">
                                        <div class="mb-2" style="background: rgba(255,255,255,0.15); border-radius: 12px; width:52px; height:52px; display:flex; align-items:center; justify-content:center; margin: 0 auto;">
                                            <i class="fas fa-user fa-lg"></i>
                                        </div>
                                        <div style="font-size:13px; font-weight:600;">My Profile</div>
                                    </div>
                                </a>
                            </div>

                            <!-- Remove Access -->
                            <div class="col-6 col-md-3">
                                <div onclick="showRemoveAccessModal()" style="cursor:pointer;">
                                    <div class="quick-action-card text-center p-3" style="background: linear-gradient(135deg, #e63946, #c1121f); border-radius: 16px; color: white; transition: all 0.3s;">
                                        <div class="mb-2" style="background: rgba(255,255,255,0.15); border-radius: 12px; width:52px; height:52px; display:flex; align-items:center; justify-content:center; margin: 0 auto;">
                                            <i class="fas fa-trash fa-lg"></i>
                                        </div>
                                        <div style="font-size:13px; font-weight:600;">Remove Access</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Settings -->
                            <div class="col-6 col-md-3">
                                <a href="settings.php" class="text-decoration-none">
                                    <div class="quick-action-card text-center p-3" style="background: linear-gradient(135deg, #212529, #343a40); border-radius: 16px; color: white; transition: all 0.3s;">
                                        <div class="mb-2" style="background: rgba(255,255,255,0.15); border-radius: 12px; width:52px; height:52px; display:flex; align-items:center; justify-content:center; margin: 0 auto;">
                                            <i class="fas fa-cog fa-lg"></i>
                                        </div>
                                        <div style="font-size:13px; font-weight:600;">Settings</div>
                                    </div>
                                </a>
                            </div>

                            <!-- Help -->
                            <div class="col-6 col-md-3">
                                <a href="help.php" class="text-decoration-none">
                                    <div class="quick-action-card text-center p-3" style="background: linear-gradient(135deg, #e9ecef, #ced4da); border-radius: 16px; color: #212529; transition: all 0.3s;">
                                        <div class="mb-2" style="background: rgba(0,0,0,0.08); border-radius: 12px; width:52px; height:52px; display:flex; align-items:center; justify-content:center; margin: 0 auto;">
                                            <i class="fas fa-question-circle fa-lg"></i>
                                        </div>
                                        <div style="font-size:13px; font-weight:600;">Help</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Recent Activity & Info -->
            <div class="col-md-4">
                <!-- Recent Activity -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_logs): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_logs as $log): ?>
                                <div class="list-group-item px-0 border-0 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong class="d-block">
                                                <?php echo htmlspecialchars($log['locker_name'] ?? 'Unknown Locker'); ?>
                                            </strong>
                                            <small class="text-muted">
                                                <?php 
                                                echo $log['access_method'] ? strtoupper($log['access_method']) : 'WEB';
                                                ?>
                                                • <?php echo date('H:i', strtotime($log['timestamp'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php if ($log['success']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times"></i>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="access-logs.php" class="btn btn-outline-success w-100 mt-2">View All Activity</a>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">No recent activity</p>
                            <div class="text-center">
                                <a href="scan-access.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-qrcode me-2"></i>Try Scanning Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-university text-primary me-2"></i>
                                <strong>Institution:</strong> <?php echo htmlspecialchars($institution); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-id-card text-info me-2"></i>
                                <strong>Your ID:</strong> <?php echo htmlspecialchars($user_id_number); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-user-tag text-success me-2"></i>
                                <strong>User Type:</strong> <?php echo ucfirst($user_type); ?>
                            </li>
                        </ul>
                        
                        <hr>
                    </div>
                </div>
                
                <!-- Quick Tips -->
                <div class="card dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Tips</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small>
                                <i class="fas fa-qrcode text-success me-2"></i>
                                Use QR code for fastest access
                            </small>
                        </div>
                        <div class="mb-2">
                            <small>
                                <i class="fas fa-trash text-danger me-2"></i>
                                Remove access when locker no longer needed
                            </small>
                        </div>
                        <div class="mb-2">
                            <small>
                                <i class="fas fa-edit text-info me-2"></i>
                                Customize locker names for easy identification
                            </small>
                        </div>
                        <div class="mb-2">
                            <small>
                                <i class="fas fa-print text-warning me-2"></i>
                                Print QR code for offline access
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        <i class="fas fa-lock me-2"></i>
                        Smart Locker System - Commercial Edition
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>
                        User: <?php echo htmlspecialchars($user_name); ?> | 
                        ID: <?php echo htmlspecialchars($user_id_number); ?> | 
                        Session: Active
                    </small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Remove Access Modal -->
    <div class="modal fade" id="removeAccessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Remove Locker Access
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove access to all your lockers?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        This action will:
                        <ul class="mb-0 mt-2">
                            <li>Remove your access to all assigned lockers</li>
                            <li>Make lockers available for other users</li>
                            <li>Cannot be undone automatically</li>
                        </ul>
                    </div>
                    
                    <?php if ($total_assigned > 0): ?>
                    <div class="alert alert-info">
                        You currently have <strong><?php echo $total_assigned; ?> locker(s)</strong> assigned.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmRemoveAccess()">
                        <i class="fas fa-trash me-2"></i>Remove All Access
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Show Remove Access Modal
    function showRemoveAccessModal() {
        <?php if ($total_assigned > 0): ?>
        const modal = new bootstrap.Modal(document.getElementById('removeAccessModal'));
        modal.show();
        <?php else: ?>
        alert('You have no active locker assignments.');
        <?php endif; ?>
    }
    
    // Confirm Remove Access
    function confirmRemoveAccess() {
        if (confirm('Final confirmation: Remove access to all your lockers?')) {
            // Show loading
            const modal = bootstrap.Modal.getInstance(document.getElementById('removeAccessModal'));
            if (modal) modal.hide();
            
            // Make AJAX call to remove access
            fetch('api/remove_access.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_number: '<?php echo $user_id_number; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Access removed successfully! ' + data.released_lockers + ' locker(s) are now available for others.');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error removing access: ' + error);
            });
        }
    }
    
    // Auto-refresh dashboard every 30 seconds
    setTimeout(function() {
        window.location.reload();
    }, 30000);
    
    // Highlight current tab/active item
    document.addEventListener('DOMContentLoaded', function() {
        const currentUrl = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentUrl.split('/').pop()) {
                link.classList.add('active');
            }
        });
    });
    </script>
</body>
</html>