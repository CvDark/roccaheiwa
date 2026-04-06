<?php
ob_start(); // Start output buffering

// Set unique session name for Commercial edition BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_COMMERCIAL');
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config.php';
$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASS;
$dbname = DB_NAME;

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get user activity - SESUAIKAN DENGAN STRUKTUR TABEL
$sql = "SELECT al.*, l.name as locker_name 
        FROM activity_logs al 
        LEFT JOIN lockers l ON al.locker_id = l.id 
        WHERE al.user_id = ? 
        ORDER BY al.id DESC 
        LIMIT 50";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$activities = [];
while ($row = $result->fetch_assoc()) {
    $activities[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Smart Locker System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 25px;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 15px;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .content {
            padding: 30px;
        }

        .activity-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .method {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
        }

        .method-qr {
            background: #d1ecf1;
            color: #0c5460;
        }

        .method-web {
            background: #d4edda;
            color: #155724;
        }

        .method-app {
            background: #fff3cd;
            color: #856404;
        }

        .method-key {
            background: #e2e3e5;
            color: #383d41;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #495057;
        }

        .key-used {
            font-family: monospace;
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .device-id {
            font-family: monospace;
            color: #495057;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .container {
                border-radius: 10px;
            }
            
            .content {
                padding: 15px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 10px;
                font-size: 14px;
            }
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-history"></i> Activity Log</h1>
            <p>Welcome, <?php echo htmlspecialchars($user_name); ?></p>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Filter Options (Optional) -->
            <div class="actions">
                <a href="access-logs.php" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Refresh
                </a>
                <a href="dashboard.php" class="btn btn-success">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>

            <!-- Activity Table -->
            <div class="activity-table" style="margin-top: 20px;">
                <?php if (count($activities) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Locker</th>
                                <th>Device</th>
                                <th>Access Method</th>
                                <th>Key Used</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($activity['locker_id'])): ?>
                                        <strong>Locker #<?php echo htmlspecialchars($activity['locker_id']); ?></strong>
                                        <?php if (!empty($activity['locker_name'])): ?>
                                            <br><small><?php echo htmlspecialchars($activity['locker_name']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <em>No locker</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="device-id">
                                        <?php echo !empty($activity['device_id']) ? htmlspecialchars($activity['device_id']) : 'N/A'; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $method = $activity['access_method'] ?? 'web';
                                    $method_icon = 'fas fa-globe';
                                    $method_class = 'method-web';
                                    
                                    switch ($method) {
                                        case 'qr_code':
                                            $method_icon = 'fas fa-qrcode';
                                            $method_class = 'method-qr';
                                            $method_text = 'QR Code';
                                            break;
                                        case 'mobile_app':
                                            $method_icon = 'fas fa-mobile-alt';
                                            $method_class = 'method-app';
                                            $method_text = 'Mobile App';
                                            break;
                                        case 'manual_key':
                                            $method_icon = 'fas fa-key';
                                            $method_class = 'method-key';
                                            $method_text = 'Manual Key';
                                            break;
                                        default:
                                            $method_text = 'Web';
                                    }
                                    ?>
                                    <span class="method <?php echo $method_class; ?>">
                                        <i class="<?php echo $method_icon; ?>"></i>
                                        <?php echo htmlspecialchars($method_text); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($activity['key_used'])): ?>
                                        <div class="key-used" title="<?php echo htmlspecialchars($activity['key_used']); ?>">
                                            <?php 
                                            // Tampilkan hanya 10 karakter pertama
                                            echo substr(htmlspecialchars($activity['key_used']), 0, 10) . '...';
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <em>N/A</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($activity['success'] == 1): ?>
                                        <span class="status status-success">
                                            <i class="fas fa-check-circle"></i> Success
                                        </span>
                                    <?php else: ?>
                                        <span class="status status-failed">
                                            <i class="fas fa-times-circle"></i> Failed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $timestamp = $activity['timestamp'] ?? $activity['created_at'] ?? 'N/A';
                                    if ($timestamp != 'N/A') {
                                        echo date('M d, Y H:i', strtotime($timestamp));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Activity Found</h3>
                        <p>Your activity log is empty. Start by using the locker system!</p>
                        <div style="margin-top: 20px;">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Go to Dashboard
                            </a>
                            <a href="my-locker.php" class="btn btn-success">
                                <i class="fas fa-lock"></i> View My Lockers
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Summary -->
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <p>
                    <strong>Total Activities:</strong> <?php echo count($activities); ?> 
                    <?php if (count($activities) > 0): ?>
                        | <strong>Last Activity:</strong> 
                        <?php 
                        $last = reset($activities);
                        $last_time = $last['timestamp'] ?? $last['created_at'] ?? '';
                        if ($last_time) {
                            echo date('M d, Y H:i', strtotime($last_time));
                        }
                        ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh setiap 30 detik (opsional)
        setTimeout(function() {
            window.location.reload();
        }, 30000); // 30 detik

        // Tooltip untuk key used
        document.querySelectorAll('.key-used').forEach(function(element) {
            element.addEventListener('mouseover', function() {
                const title = this.getAttribute('title');
                if (title) {
                    // Buat tooltip sederhana
                    const tooltip = document.createElement('div');
                    tooltip.style.position = 'absolute';
                    tooltip.style.background = '#333';
                    tooltip.style.color = '#fff';
                    tooltip.style.padding = '5px 10px';
                    tooltip.style.borderRadius = '4px';
                    tooltip.style.zIndex = '1000';
                    tooltip.innerText = title;
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.top = (rect.top - 30) + 'px';
                    tooltip.style.left = rect.left + 'px';
                    
                    tooltip.id = 'tooltip-' + Date.now();
                    document.body.appendChild(tooltip);
                    
                    this.dataset.tooltipId = tooltip.id;
                }
            });
            
            element.addEventListener('mouseout', function() {
                const tooltipId = this.dataset.tooltipId;
                if (tooltipId) {
                    const tooltip = document.getElementById(tooltipId);
                    if (tooltip) {
                        tooltip.remove();
                    }
                }
            });
        });
    </script>
</body>
</html>