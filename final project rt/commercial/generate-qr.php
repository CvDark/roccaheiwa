<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

// Fetch all active lockers with key
$stmt = $pdo->prepare("
    SELECT 
        la.key_value,
        la.assigned_at,
        la.custom_name,
        la.custom_location,
        l.id as locker_id,
        l.name as locker_name,
        l.location,
        l.unique_code,
        l.device_id,
        COALESCE(la.custom_name, l.name, l.unique_code) as display_name,
        COALESCE(la.custom_location, l.location, '') as display_location
    FROM user_locker_assignments la
    LEFT JOIN lockers l ON la.locker_id = l.id
    WHERE la.user_id = ? AND la.is_active = 1 AND la.key_value IS NOT NULL
    ORDER BY la.assigned_at DESC
");
$stmt->execute([$user_id]);
$lockers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Code - Smart Locker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }

        .top-bar {
            background: linear-gradient(135deg, #1a1a2e, #0f3460);
            color: white;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }

        .qr-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            break-inside: avoid;
        }

        .qr-card-header {
            background: linear-gradient(135deg, #2d6a4f, #52b788);
            color: white;
            padding: 16px 20px;
        }

        .qr-box {
            background: white;
            padding: 12px;
            border-radius: 14px;
            display: inline-block;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            border: 3px solid #f0f2f5;
        }

        .info-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .info-item:last-child { border-bottom: none; }
        .info-label {
            display: block;
            color: #6c757d;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .info-value {
            font-weight: 500;
            color: #2c3e50;
            word-break: break-word;
            font-size: 13px;
        }

        .print-btn {
            background: linear-gradient(135deg, #00b4d8, #0096c7);
            color: white; border: none;
            border-radius: 12px;
            padding: 10px 24px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        .print-btn:hover { transform: translateY(-2px); opacity: 0.9; }

        /* Print styles */
        @media print {
            .top-bar, .no-print { display: none !important; }
            body { background: white; margin: 0; padding: 0; }
            .container { max-width: 100% !important; padding: 10px !important; }
            .row { display: flex; flex-wrap: wrap; margin: 0; }
            .col-sm-6 { 
                width: 48% !important; 
                padding: 6px !important;
                box-sizing: border-box;
            }
            .qr-card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
                border-radius: 10px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .qr-card-header {
                padding: 10px 14px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .card-body { padding: 12px !important; }
            #qr_canvas img, #qr_canvas canvas { width: 130px !important; height: 130px !important; }
            .qr-box { padding: 8px !important; }
            .info-label { font-size: 10px !important; }
            .info-value { font-size: 12px !important; }
        }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="top-bar no-print">
    <div class="d-flex align-items-center gap-3">
        <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill">
            <i class="fas fa-arrow-left me-1"></i>Dashboard
        </a>
        <span class="fw-bold"><i class="fas fa-print me-2"></i>Print QR Codes</span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print me-2"></i>Print / Save PDF
        </button>
    </div>
</div>

<div class="container py-4">

    <!-- Title -->
    <div class="text-center mb-4 no-print">
        <h4 class="fw-bold mb-1">Your Locker QR Codes</h4>
        <p class="text-muted">Print or save as PDF to use offline at the locker station</p>
    </div>

    <?php if (empty($lockers)): ?>
    <div class="text-center py-5">
        <div class="card border-0 shadow-sm p-5 mx-auto" style="max-width:400px; border-radius:20px;">
            <i class="fas fa-qrcode fa-4x text-muted mb-3 opacity-25"></i>
            <h5>No QR Codes Available</h5>
            <p class="text-muted">You don't have any active locker assignments.</p>
            <a href="assign-locker.php" class="btn btn-primary rounded-pill">
                <i class="fas fa-plus me-1"></i>Assign a Locker
            </a>
        </div>
    </div>
    <?php else: ?>

    <div class="row g-4 justify-content-center">
        <?php foreach ($lockers as $locker): ?>
        <div class="col-6 col-md-4">
            <div class="qr-card">
                <!-- Card Header -->
                <div class="qr-card-header">
                    <div class="fw-bold fs-6">
                        <i class="fas fa-box me-2"></i><?php echo htmlspecialchars($locker['display_name'] ?? $locker['unique_code'] ?? 'Locker'); ?>
                    </div>
                    <small class="opacity-75">
                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars(!empty($locker['display_location']) ? $locker['display_location'] : $locker['unique_code'] ?? 'N/A'); ?>
                    </small>
                </div>

                <div class="card-body p-4 text-center">
                    <!-- QR Code -->
                    <div class="qr-box mb-3">
                        <div id="qr_<?php echo (int)$locker['locker_id']; ?>" id="qr_canvas"></div>
                    </div>

                    <small class="text-muted d-block mb-3">
                        <i class="fas fa-info-circle me-1"></i>Scan at locker station to unlock
                    </small>

                    <!-- Info -->
                    <div class="text-start">
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-user text-primary me-1"></i> Name</span><span class="info-value"><?php echo htmlspecialchars($user_name); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-barcode text-success me-1"></i> Locker Code</span><span class="info-value"><code><?php echo htmlspecialchars($locker['unique_code'] ?? 'N/A'); ?></code></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-calendar text-warning me-1"></i> Assigned</span><span class="info-value"><?php echo date('d M Y', strtotime($locker['assigned_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer bg-light border-0 text-center py-2">
                    <small class="text-muted">Smart Locker System — <?php echo date('Y'); ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Print note -->
    <div class="text-center mt-4 no-print">
        <p class="text-muted small">
            <i class="fas fa-lightbulb text-warning me-1"></i>
            Tip: click <strong>Print / Save PDF</strong> and choose "Save as PDF" to keep a digital copy on your phone for offline use at the locker!
        </p>
    </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Generate QR for each locker
<?php foreach ($lockers as $locker): ?>
(function() {
    var el = document.getElementById('qr_<?php echo (int)$locker['locker_id']; ?>');
    if (el) {
        new QRCode(el, {
            text: JSON.stringify({
                locker_id:  <?php echo (int)$locker['locker_id']; ?>,
                access_key: <?php echo json_encode($locker['key_value']); ?>,
                user_id:    <?php echo (int)$user_id; ?>
            }),
            width:  150,
            height: 150,
            colorDark:  "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    }
})();
<?php endforeach; ?>
</script>
</body>
</html>