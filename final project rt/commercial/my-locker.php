<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

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
        l.name as locker_name,
        l.location,
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
    <title>My Lockers - Smart Locker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 35px 0 30px;
            margin-bottom: 30px;
        }

        .locker-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            overflow: hidden;
        }

        .locker-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .locker-card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 18px 20px;
        }

        .locker-card-header.active    { background: linear-gradient(135deg, #2d6a4f, #52b788); }
        .locker-card-header.available { background: linear-gradient(135deg, #0096c7, #48cae4); }
        .locker-card-header.maintenance { background: linear-gradient(135deg, #e63946, #c1121f); }

        .qr-wrap {
            background: white;
            padding: 10px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.88rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .label {
            color: #6c757d;
            width: 110px;
            flex-shrink: 0;
            font-weight: 500;
        }

        .add-locker-card {
            border: 2.5px dashed #c8d0e0;
            border-radius: 18px;
            background: white;
            min-height: 260px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: border-color 0.3s, background 0.3s;
            cursor: pointer;
            text-decoration: none;
        }
        .add-locker-card:hover {
            border-color: #667eea;
            background: #f5f3ff;
        }

        .stat-pill {
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 0.8rem;
            display: inline-block;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-lock me-2"></i>Smart Locker
            </a>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Dashboard
                </a>
                <a href="assign-locker.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i>Assign Locker
                </a>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h3 class="fw-bold mb-1">
                        <i class="fas fa-boxes me-2"></i>My Lockers
                    </h3>
                    <p class="mb-0 opacity-75">All lockers assigned to your account</p>
                </div>
                <div>
                    <span class="stat-pill me-2">
                        <i class="fas fa-box me-1"></i><?php echo $total; ?> Locker<?php echo $total != 1 ? 's' : ''; ?> Active
                    </span>
                    <span class="stat-pill">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user_name); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">

        <?php if ($total === 0): ?>
        <!-- Empty State -->
        <div class="text-center py-5">
            <div class="card border-0 shadow-sm p-5 mx-auto" style="max-width:480px; border-radius:20px;">
                <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                <h4 class="mb-2">No Lockers Assigned</h4>
                <p class="text-muted mb-4">You have not assigned any locker yet. Assign one now to start using our service.</p>
                <a href="assign-locker.php" class="btn btn-primary btn-lg rounded-pill">
                    <i class="fas fa-plus-circle me-2"></i>Assign First Locker
                </a>
            </div>
        </div>

        <?php else: ?>

        <!-- Locker Grid -->
        <div class="row g-4">
            <?php foreach ($lockers as $locker): ?>
            <?php 
                $headerClass = '';
                if ($locker['locker_status'] === 'active') $headerClass = 'active';
                elseif ($locker['locker_status'] === 'available') $headerClass = 'available';
                elseif ($locker['locker_status'] === 'maintenance') $headerClass = 'maintenance';

                $statusColors = [
                    'active'      => ['bg' => 'bg-success',   'label' => 'ACTIVE'],
                    'available'   => ['bg' => 'bg-info',      'label' => 'AVAILABLE'],
                    'occupied'    => ['bg' => 'bg-primary',   'label' => 'OCCUPIED'],
                    'maintenance' => ['bg' => 'bg-danger',    'label' => 'MAINTENANCE'],
                ];
                $sc = $statusColors[$locker['locker_status']] ?? ['bg' => 'bg-secondary', 'label' => strtoupper($locker['locker_status'] ?? 'UNKNOWN')];
            ?>
            <div class="col-sm-6 col-lg-4">
                <div class="locker-card card h-100">

                    <!-- Card Header -->
                    <div class="locker-card-header <?php echo $headerClass; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold fs-6">
                                    <i class="fas fa-box me-2"></i>
                                    <?php echo htmlspecialchars($locker['display_name'] ?? $locker['unique_code'] ?? 'Locker'); ?>
                                </div>
                                <small class="opacity-75"><?php echo htmlspecialchars(!empty($locker['display_location']) ? $locker['display_location'] : $locker['unique_code'] ?? 'N/A'); ?></small>
                            </div>
                            <span class="badge <?php echo $sc['bg']; ?> text-uppercase" style="font-size:10px; padding:5px 10px; border-radius:20px;">
                                <?php echo $sc['label']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-body px-4 py-3">
                        <!-- Locker Info -->
                        <div class="mb-3">
                            <div class="info-row">
                                <span class="label"><i class="fas fa-barcode text-primary me-2"></i>Unique Code</span>
                                <code class="bg-light px-2 py-1 rounded" style="font-size:12px;">
                                    <?php echo htmlspecialchars($locker['unique_code'] ?? 'N/A'); ?>
                                </code>
                            </div>
                            <div class="info-row">
                                <span class="label"><i class="fas fa-calendar text-success me-2"></i>Assigned</span>
                                <span><?php echo date('d M Y', strtotime($locker['assigned_at'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label"><i class="fas fa-microchip text-warning me-2"></i>Device ID</span>
                                <small class="text-muted"><?php echo htmlspecialchars($locker['device_id'] ?? 'N/A'); ?></small>
                            </div>
                        </div>

                        <!-- QR Code -->
                        <?php if (!empty($locker['key_value'])): ?>
                        <div class="text-center mb-3">
                            <div class="qr-wrap">
                                <div id="qrcode_<?php echo (int)$locker['locker_id']; ?>"></div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-qrcode me-1"></i>Scan to access
                            </small>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-qrcode fa-2x mb-2 opacity-25"></i>
                            <small class="d-block">No access key</small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="card-footer bg-transparent border-0 px-4 pb-4">
                        <div class="d-grid gap-2">
                            <a href="locker-details.php?id=<?php echo (int)$locker['locker_id']; ?>" 
                               class="btn btn-primary btn-sm rounded-pill">
                                <i class="fas fa-eye me-1"></i>View Details
                            </a>
                            <a href="scan-access.php" class="btn btn-outline-success btn-sm rounded-pill">
                                <i class="fas fa-qrcode me-1"></i>Scan Access
                            </a>
                        </div>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>

            <!-- Add New Locker Card -->
            <div class="col-sm-6 col-lg-4">
                <a href="assign-locker.php" class="add-locker-card d-block h-100 p-4">
                    <div class="text-center text-muted">
                        <i class="fas fa-plus-circle fa-3x mb-3" style="color: #667eea; opacity:0.5;"></i>
                        <h6 class="fw-semibold mb-1" style="color:#667eea;">Assign New Locker</h6>
                        <small>Click to add a locker</small>
                    </div>
                </a>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small><i class="fas fa-lock me-2"></i>Smart Locker System</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>
                        User: <?php echo htmlspecialchars($user_name); ?> | 
                        <?php echo date('d M Y, H:i'); ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Generate QR Code untuk setiap locker
    <?php foreach ($lockers as $locker): ?>
    <?php if (!empty($locker['key_value'])): ?>
    (function() {
        var el = document.getElementById('qrcode_<?php echo (int)$locker['locker_id']; ?>');
        if (el) {
            new QRCode(el, {
                text: JSON.stringify({
                    locker_id:  <?php echo (int)$locker['locker_id']; ?>,
                    access_key: <?php echo json_encode($locker['key_value']); ?>,
                    user_id:    <?php echo (int)$user_id; ?>
                }),
                width: 120,
                height: 120,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    })();
    <?php endif; ?>
    <?php endforeach; ?>
    </script>
</body>
</html>