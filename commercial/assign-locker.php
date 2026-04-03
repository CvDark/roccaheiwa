<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id     = $_SESSION['user_id'];
$user_name   = $_SESSION['user_name'] ?? 'User';
$user_id_num = $_SESSION['user_id_number'] ?? '';

$success_msg = '';
$error_msg   = '';

// Handle form submit — verify unique_code + locker_key
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unique_code = strtoupper(trim($_POST['unique_code'] ?? ''));
    $locker_key  = trim($_POST['locker_key'] ?? '');

    if (empty($unique_code) || empty($locker_key)) {
        $error_msg = 'Please enter both Locker Code and Locker Key.';
    } else {
        // Verify unique_code + locker_key match
        $stmt = $pdo->prepare("SELECT id, name, status FROM lockers WHERE unique_code = ? AND locker_key = ? AND status != 'maintenance'");
        $stmt->execute([$unique_code, $locker_key]);
        $locker = $stmt->fetch();

        if (!$locker) {
            $error_msg = 'Invalid Locker Code or Locker Key. Please check and try again.';
        } else {
            $locker_id = $locker['id'];
            // Check if locker already assigned to someone
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM user_locker_assignments WHERE locker_id = ? AND is_active = 1");
            $stmt2->execute([$locker_id]);
            if ($stmt2->fetchColumn() > 0) {
                $error_msg = 'This locker is already in use by another user.';
            } else {
                // Check if user already has this locker
                $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM user_locker_assignments WHERE user_id = ? AND locker_id = ? AND is_active = 1");
                $stmt3->execute([$user_id, $locker_id]);
                if ($stmt3->fetchColumn() > 0) {
                    $error_msg = 'You already have access to this locker.';
                } else {
                    // Assign locker to user
                    $key_value = bin2hex(random_bytes(16));
                    $pdo->prepare("INSERT INTO user_locker_assignments (user_id, locker_id, key_value, is_active, assigned_at) VALUES (?, ?, ?, 1, NOW())")
                        ->execute([$user_id, $locker_id, $key_value]);
                    // Update locker status
                    $pdo->prepare("UPDATE lockers SET status = 'active' WHERE id = ?")
                        ->execute([$locker_id]);
                    // Log
                    $pdo->prepare("INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, timestamp) VALUES (?, ?, 'web', 'web', ?, 1, NOW())")
                        ->execute([$user_id, $locker_id, $key_value]);
                    $success_msg = "✅ Locker <strong>{$locker['name']}</strong> successfully assigned! Go to My Locker to print your QR code.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Locker - Smart Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }

        .page-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px 0 25px;
            margin-bottom: 30px;
        }

        .locker-card {
            border: 2px solid #e9ecef;
            border-radius: 16px;
            padding: 20px;
            background: white;
            cursor: pointer;
            transition: all 0.25s;
            position: relative;
        }
        .locker-card:hover {
            border-color: #3498db;
            box-shadow: 0 6px 20px rgba(52,152,219,0.15);
            transform: translateY(-3px);
        }
        .locker-card.selected {
            border-color: #3498db;
            background: #eaf4fd;
            box-shadow: 0 6px 20px rgba(52,152,219,0.2);
        }
        .locker-card .check-badge {
            display: none;
            position: absolute;
            top: 12px; right: 12px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            width: 26px; height: 26px;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .locker-card.selected .check-badge { display: flex; }

        .locker-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 20px;
            margin-bottom: 12px;
        }

        .urgent-card {
            background: linear-gradient(135deg, #fff5f5, #ffe0e0);
            border: 2px solid #f5c6cb;
            border-radius: 16px;
            padding: 24px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending  { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        .step-badge {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px;
            flex-shrink: 0;
        }

        .call-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(231,76,60,0.3);
        }
        .call-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(231,76,60,0.4);
        }

        .whatsapp-btn {
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(37,211,102,0.3);
        }
        .whatsapp-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37,211,102,0.4);
        }

        .empty-lockers {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        #submitBtn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .section-title {
            font-weight: 700;
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="fas fa-lock me-2"></i>Smart Locker System
        </a>
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Dashboard
            </a>
            <a href="my-locker.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-boxes me-1"></i>My Lockers
            </a>
        </div>
    </div>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h3 class="fw-bold mb-1"><i class="fas fa-plus-circle me-2"></i>Assign Locker</h3>
        <p class="mb-0 opacity-75">Enter the Locker Code and Locker Key from your access card to get started</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4">

        <!-- LEFT: Main assign flow -->
        <div class="col-lg-8">

            <?php if ($success_msg): ?>
            <div class="alert alert-success d-flex align-items-center gap-3 rounded-3 mb-4">
                <i class="fas fa-check-circle fa-2x text-success"></i>
                <div><?php echo $success_msg; ?></div>
            </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
            <div class="alert alert-danger d-flex align-items-center gap-3 rounded-3 mb-4">
                <i class="fas fa-exclamation-circle fa-2x text-danger"></i>
                <div><?php echo $error_msg; ?></div>
            </div>
            <?php endif; ?>

            <!-- How it works -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                <div class="card-body p-4">
                    <div class="section-title">
                        <i class="fas fa-info-circle text-primary"></i> How to Assign a Locker
                    </div>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="step-badge">1</div>
                            <div><strong>Get your access card</strong> from the admin containing your <strong>Locker Code</strong> and <strong>Locker Key</strong></div>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div class="step-badge">2</div>
                            <div><strong>Enter the Locker Code and Locker Key</strong> in the form below</div>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div class="step-badge">3</div>
                            <div><strong>Instant access</strong> — your QR code will be generated automatically in My Locker</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assign Form -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                <div class="card-body p-4">
                    <div class="section-title">
                        <i class="fas fa-key text-warning"></i> Enter Locker Details
                    </div>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Locker Code <span class="text-danger">*</span></label>
                                <input type="text" name="unique_code" class="form-control"
                                       placeholder="e.g. LKR001" required
                                       style="border-radius:10px; text-transform:uppercase;">
                                <small class="text-muted">Locker code on your access card</small>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Locker Key <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="locker_key" id="lockerKeyInput" class="form-control"
                                           placeholder="Enter your secret key" required
                                           style="border-radius:10px 0 0 10px;">
                                    <button type="button" class="btn btn-outline-secondary" onclick="toggleKey()"
                                            style="border-radius:0 10px 10px 0;">
                                        <i class="fas fa-eye" id="keyEyeIcon"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Secret key on your access card</small>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-bold" style="border-radius:10px;">
                                <i class="fas fa-unlock me-2"></i>Assign Locker
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- RIGHT: Urgent / Contact -->
        <div class="col-lg-4">

            <!-- Urgent Contact Card -->
            <div class="urgent-card mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-bolt text-danger fa-lg"></i>
                    <h6 class="fw-bold mb-0 text-danger">Urgent? Contact Us Directly!</h6>
                </div>
                <p class="text-muted small mb-4">
                    If you need a locker urgently, contact our customer service or admin directly via phone or WhatsApp.
                </p>

                <div class="d-grid gap-3">
                    <a href="tel:+601XXXXXXXXX" class="call-btn justify-content-center">
                        <i class="fas fa-phone-alt"></i>
                        Call Customer Service
                    </a>
                    <a href="https://wa.me/601XXXXXXXXX?text=Hello%2C%20I%20urgently%20need%20a%20locker.%20Nama%3A%20<?php echo urlencode($user_name); ?>%20ID%3A%20<?php echo urlencode($user_id_num); ?>"
                       target="_blank" class="whatsapp-btn justify-content-center">
                        <i class="fab fa-whatsapp"></i>
                        WhatsApp Admin
                    </a>
                </div>

                <hr class="my-3">
                <div class="text-muted small">
                    <i class="fas fa-clock me-1"></i> Operating hours:<br>
                    <strong>Monday – Friday, 8:00am – 5:00pm</strong>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-lightbulb text-warning me-2"></i>Important Info</h6>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>A user can have more than one locker</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>QR code is auto-generated instantly after assignment</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Keep your Locker Key private and secure</li>
                        <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Each locker has a unique code and key</li>
                        <li><i class="fas fa-phone text-danger me-2"></i>Lost your key? Contact admin directly</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Toast Notification -->
<div id="toast" style="
    position: fixed; bottom: 30px; right: 30px;
    background: #2c3e50; color: white;
    padding: 14px 22px; border-radius: 12px;
    font-weight: 600; font-size: 14px;
    display: none; z-index: 9999;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    animation: fadeInUp 0.3s ease;
"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleKey() {
    const inp  = document.getElementById('lockerKeyInput');
    const icon = document.getElementById('keyEyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>