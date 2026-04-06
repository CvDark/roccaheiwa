<?php
ob_start(); // Start output buffering

// Set unique session name for Institution edition BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_INSTITUTION');
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

// Get locker ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my-locker.php");
    exit();
}

$locker_id = $_GET['id'];

// Get locker details with access verification
$sql = "SELECT l.*, la.key_value, la.assigned_at as assigned_date,
        la.custom_name, la.custom_location,
        COALESCE(la.custom_name, l.unique_code) as display_name,
        COALESCE(la.custom_location, '') as display_location
        FROM lockers l 
        JOIN user_locker_assignments la ON l.id = la.locker_id
        WHERE la.user_id = ? 
        AND l.id = ? 
        AND la.is_active = 1
        LIMIT 1";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $locker_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // User doesn't have access to this locker
    header("Location: my-locker.php");
    exit();
}

$locker = $result->fetch_assoc();

// Get recent activity for this locker
$sql_activity = "SELECT * FROM activity_logs 
                 WHERE user_id = ? AND locker_id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT 10";
$stmt_activity = $conn->prepare($sql_activity);
$stmt_activity->bind_param("ii", $user_id, $locker_id);
$stmt_activity->execute();
$activity_result = $stmt_activity->get_result();
$activities = [];
while ($row = $activity_result->fetch_assoc()) {
    $activities[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locker Details - Smart Locker System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
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

        .section {
            margin-bottom: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }

        .section h3 {
            margin: 0 0 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-grid .info-card[style*="span 2"] {
            grid-column: span 2;
        }

        @media (max-width: 600px) {
            .info-grid .info-card[style*="span 2"] {
                grid-column: span 1;
            }
        }

        .info-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .info-card h4 {
            margin: 0 0 10px;
            color: #495057;
            font-size: 16px;
        }

        .info-card p {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }

        .status {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }

        .available { background: #d4edda; color: #155724; }
        .occupied { background: #f8d7da; color: #721c24; }
        .maintenance { background: #fff3cd; color: #856404; }
        .offline { background: #e2e3e5; color: #383d41; }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 16px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .access-key {
            font-family: monospace;
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 5px;
            margin: 5px 0;
            display: inline-block;
            font-size: 16px;
            color: #2c3e50;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .qr-container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            border: 2px dashed #bdc3c7;
            margin: 20px 0;
        }

        #qrcode {
            display: inline-block;
            padding: 20px;
            background: white;
            border-radius: 10px;
            margin: 20px 0;
        }

        .qr-placeholder {
            width: 200px;
            height: 200px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin: 0 auto;
            border: 2px solid #ddd;
        }

        .qr-placeholder i {
            font-size: 48px;
            color: #aaa;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            display: none;
            animation: slideIn 0.3s ease;
            font-weight: 600;
        }

        .notification.success {
            background: #27ae60;
        }

        .notification.error {
            background: #e74c3c;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .container {
                border-radius: 10px;
            }
            
            .content {
                padding: 20px;
            }
            
            .section {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }

        .btn-disabled {
            background: #95a5a6;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn-disabled:hover {
            transform: none;
            box-shadow: none;
            background: #95a5a6;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Notification Container -->
    <div id="notification" class="notification"></div>

    <!-- Main Container -->
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-lock"></i> Locker Details</h1>
            <p>Welcome, <?php echo htmlspecialchars($user_name); ?></p>
            <a href="my-locker.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to My Lockers
            </a>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Locker Information -->
            <div class="section">
                <h3><i class="fas fa-info-circle"></i> Locker Information</h3>
                <div class="info-grid">
                    <div class="info-card">
                        <h4><i class="fas fa-hashtag" style="color:#3498db;margin-right:6px;"></i>Locker Number</h4>
                        <p>#<?php echo $locker['id']; ?></p>
                    </div>
                    <div class="info-card">
                        <h4><i class="fas fa-box" style="color:#9b59b6;margin-right:6px;"></i>Locker Name</h4>
                        <p><?php echo htmlspecialchars($locker['display_name'] ?? $locker['unique_code'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="info-card" id="locationCard">
                        <h4>
                            <i class="fas fa-map-marker-alt" style="color:#e74c3c;margin-right:6px;"></i>Location
                            <button onclick="toggleEditLocation()" id="editLocationBtn" title="Edit Location" style="background:none;border:none;cursor:pointer;color:#3498db;font-size:13px;margin-left:8px;padding:2px 6px;border-radius:4px;" onmouseover="this.style.background='#eaf4fd'" onmouseout="this.style.background='none'">
                                <i class="fas fa-pencil"></i> Edit
                            </button>
                        </h4>
                        <p id="locationDisplay"><?php echo htmlspecialchars($locker['display_location'] ?? $locker['unique_code'] ?? 'N/A'); ?></p>
                        <div id="locationEditForm" style="display:none; margin-top:8px;">
                            <input type="text" id="locationInput" value="<?php echo htmlspecialchars($locker['display_location'] ?? ''); ?>" style="width:100%;padding:8px 12px;border:2px solid #3498db;border-radius:8px;font-size:14px;outline:none;font-family:inherit;">
                            <div style="display:flex;gap:8px;margin-top:8px;">
                                <button onclick="saveLocation()" style="background:#27ae60;color:white;border:none;padding:7px 16px;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600;">
                                    <i class="fas fa-check"></i> save
                                </button>
                                <button onclick="cancelEditLocation()" style="background:#95a5a6;color:white;border:none;padding:7px 16px;border-radius:7px;cursor:pointer;font-size:13px;">
                                    <i class="fas fa-times"></i> cancel
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="info-card">
                        <h4><i class="fas fa-circle-dot" style="color:#27ae60;margin-right:6px;"></i>Status</h4>
                        <p>
                            <span class="status <?php echo $locker['status']; ?>">
                                <?php echo ucfirst($locker['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="info-card" style="grid-column: span 2;">
                        <h4><i class="fas fa-key" style="color:#f39c12;margin-right:6px;"></i>Access Key</h4>
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:8px;">
                            <code id="accessKeyDisplay" style="
                                font-family: 'Courier New', monospace;
                                background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                                padding: 10px 16px;
                                border-radius: 8px;
                                border: 1px solid #dee2e6;
                                font-size: 14px;
                                color: #2c3e50;
                                letter-spacing: 0.5px;
                                word-break: break-all;
                                flex: 1;
                            "><?php echo htmlspecialchars($locker['key_value'] ?? 'N/A'); ?></code>
                            <button onclick="copyAccessKey()" title="Copy key" style="
                                background: #3498db;
                                color: white;
                                border: none;
                                padding: 10px 14px;
                                border-radius: 8px;
                                cursor: pointer;
                                font-size: 14px;
                                transition: all 0.2s;
                                white-space: nowrap;
                            " onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">
                                <i class="fas fa-copy" id="copyIcon"></i> Copy
                            </button>
                        </div>
                        <small style="color:#6c757d; margin-top:6px; display:block;">
                            <i class="fas fa-shield-alt" style="color:#27ae60;"></i> don't share this key with anyone!
                        </small>
                    </div>
                    <div class="info-card">
                        <h4><i class="fas fa-calendar-check" style="color:#1abc9c;margin-right:6px;"></i>Assigned Date</h4>
                        <p><?php echo isset($locker['assigned_date']) ? date('M d, Y', strtotime($locker['assigned_date'])) : 'N/A'; ?></p>
                    </div>
                    <?php if (isset($locker['device_id']) && !empty($locker['device_id'])): ?>
                    <div class="info-card">
                        <h4><i class="fas fa-microchip" style="color:#e67e22;margin-right:6px;"></i>Device ID</h4>
                        <p><?php echo htmlspecialchars($locker['device_id']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="action-buttons">
                    <!-- Generate QR Code Button -->
                    <button class="btn btn-success" onclick="generateQRCode()">
                        <i class="fas fa-qrcode"></i> Generate QR Code
                    </button>
                    
                    <!-- Remove Access Button (SIMPLE VERSION) -->
                    <a href="remove_access.php?id=<?php echo $locker['id']; ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('⚠️ Are you sure you want to remove access to this locker?\n\nThis action cannot be undone!')">
                        <i class="fas fa-trash"></i> Remove Access
                    </a>
                </div>
            </div>

            <!-- QR Code Section (Initially Hidden) -->
            <div class="section" id="qrSection" style="display: none;">
                <h3><i class="fas fa-qrcode"></i> Access QR Code</h3>
                <div class="qr-container">
                    <p style="color:#555; margin-bottom: 6px;">Tunjukkan QR ini di scanner untuk buka locker:</p>
                    <p style="color:#999; font-size:0.82rem; margin-bottom: 20px;">
                        QR mengandungi: <strong>Locker ID</strong> + <strong>Access Key</strong> + <strong>User ID</strong>
                    </p>

                    <!-- QR render di sini -->
                    <div id="qrcode" style="display:inline-block; padding:16px; background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.12); margin-bottom: 16px;"></div>

                    <!-- Loading state -->
                    <div id="qrLoading" style="display:none; padding:40px; color:#888;">
                        <div class="loading-spinner" style="display:block; margin:0 auto 12px;"></div>
                        <p>Menjana QR Code...</p>
                    </div>

                    <!-- QR Info -->
                    <div id="qrInfo" style="display:none; background:#f8f9fa; border-radius:10px; padding:16px; margin:16px 0; text-align:left;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div>
                                <small style="color:#888; text-transform:uppercase; font-size:0.72rem; letter-spacing:1px;">Locker ID</small>
                                <p style="margin:2px 0; font-weight:700; font-size:1.1rem;">#<?php echo $locker['id']; ?></p>
                            </div>
                            <div>
                                <small style="color:#888; text-transform:uppercase; font-size:0.72rem; letter-spacing:1px;">Locker Name</small>
                                <p style="margin:2px 0; font-weight:700;"><?php echo htmlspecialchars($locker['display_name'] ?? $locker['unique_code'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <small style="color:#888; text-transform:uppercase; font-size:0.72rem; letter-spacing:1px;">Access Key</small>
                                <p style="margin:2px 0;"><span class="access-key" style="font-size:0.85rem;"><?php echo htmlspecialchars($locker['key_value'] ?? 'N/A'); ?></span></p>
                            </div>
                            <div>
                                <small style="color:#888; text-transform:uppercase; font-size:0.72rem; letter-spacing:1px;">Generated</small>
                                <p style="margin:2px 0; font-size:0.88rem;" id="qrTimestamp">—</p>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons" style="justify-content: center; margin-top: 16px;">
                        <button class="btn btn-primary" onclick="printQRCode()">
                            <i class="fas fa-print"></i> Print QR
                        </button>
                        <button class="btn btn-success" onclick="downloadQRCode()">
                            <i class="fas fa-download"></i> Download QR
                        </button>
                        <button class="btn" style="background:#6c757d; color:white;" onclick="refreshQRCode()">
                            <i class="fas fa-sync-alt"></i> Refresh QR
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading Indicator -->
            <div class="loading" id="loading">
                <div class="loading-spinner"></div>
                <p>Processing...</p>
            </div>

            <!-- Recent Activity -->
            <div class="section">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <?php if (count($activities) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Device</th>
                                <th>Key Used</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $method = $activity['access_method'] ?? 'N/A';
                                    $icon = 'fas fa-key';
                                    if ($method == 'qr_code') $icon = 'fas fa-qrcode';
                                    elseif ($method == 'mobile_app') $icon = 'fas fa-mobile-alt';
                                    elseif ($method == 'web') $icon = 'fas fa-globe';
                                    ?>
                                    <i class="<?php echo $icon; ?>"></i> 
                                    <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                                </td>
                                <td><?php echo htmlspecialchars($activity['device_id'] ?? 'N/A'); ?></td>
                                <td><code><?php echo substr(htmlspecialchars($activity['key_used'] ?? ''), 0, 10) . '...'; ?></code></td>
                                <td>
                                    <?php if ($activity['success']): ?>
                                        <span style="color: green; font-weight: bold;">✅ Success</span>
                                    <?php else: ?>
                                        <span style="color: red; font-weight: bold;">❌ Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo isset($activity['timestamp']) ? date('M d, Y H:i', strtotime($activity['timestamp'])) : 'N/A'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No recent activity for this locker.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- QR Code Generator — reliable, fast -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    // Wrapper — instant, guna qrcodejs yang proven works
    window.SmartQR = {
        generate: function(text, size) {
            try {
                var div = document.createElement('div');
                new QRCode(div, {
                    text: text,
                    width: size || 220,
                    height: size || 220,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
                // qrcodejs render canvas atau img — ambil mana yang ada
                var canvas = div.querySelector('canvas');
                var img    = div.querySelector('img');
                return canvas || img || null;
            } catch(e) {
                console.error('QR error:', e);
                return null;
            }
        }
    };
    </script>
    
    <script>
        // PHP data untuk JS — selamat & betul
        const LOCKER_DATA = {
            id:         <?php echo (int)$locker['id']; ?>,
            name:       <?php echo json_encode($locker['display_name'] ?? $locker['unique_code'] ?? ''); ?>,
            access_key: <?php echo json_encode($locker['key_value'] ?? ''); ?>,
            user_id:    <?php echo (int)$user_id; ?>,
            location:   <?php echo json_encode($locker['display_location'] ?? ''); ?>
        };
        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type}`;
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

        // Show/hide loading
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }

        // Unlock locker function - SIMPLE VERSION
        function unlockLocker() {
            if (confirm('Are you sure you want to unlock Locker #<?php echo $locker['id']; ?>?')) {
                showLoading(true);
                
                // Create form to submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'unlock_locker.php';
                form.style.display = 'none';
                
                // Add locker ID
                const lockerIdInput = document.createElement('input');
                lockerIdInput.type = 'hidden';
                lockerIdInput.name = 'locker_id';
                lockerIdInput.value = '<?php echo $locker['id']; ?>';
                form.appendChild(lockerIdInput);
                
                // Add key
                const keyInput = document.createElement('input');
                keyInput.type = 'hidden';
                keyInput.name = 'key';
                keyInput.value = '<?php echo $locker['key_value']; ?>';
                form.appendChild(keyInput);
                
                // Add to body and submit
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Generate QR Code — synchronous, instant
        function generateQRCode() {
            const qrSection = document.getElementById('qrSection');
            const qrcodeDiv = document.getElementById('qrcode');
            const qrLoading = document.getElementById('qrLoading');
            const qrInfo    = document.getElementById('qrInfo');
            const qrTS      = document.getElementById('qrTimestamp');

            qrSection.style.display = 'block';
            qrcodeDiv.innerHTML     = '';
            qrLoading.style.display = 'none'; // tak perlu loading — jana terus
            qrInfo.style.display    = 'none';

            // QR payload — format yang scan-access.php expect
            const qrPayload = JSON.stringify({
                locker_id:   LOCKER_DATA.id,
                locker_name: LOCKER_DATA.name,
                access_key:  LOCKER_DATA.access_key,
                user_id:     LOCKER_DATA.user_id,
                timestamp:   Date.now()
            });

            try {
                new QRCode(qrcodeDiv, {
                    text: qrPayload,
                    width: 220,
                    height: 220,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });

                // Tag canvas untuk download/print
                setTimeout(function() {
                    var c = qrcodeDiv.querySelector('canvas');
                    var i = qrcodeDiv.querySelector('img');
                    if (c) c.id = 'qrCanvas';
                    else if (i) i.id = 'qrCanvas';
                }, 100);

                qrInfo.style.display = 'block';
                qrTS.textContent     = new Date().toLocaleString('ms-MY');
                showNotification('✅ Success Generate QR Code!', 'success');
                qrSection.scrollIntoView({ behavior: 'smooth' });

            } catch(err) {
                console.error('QR error:', err);
                qrcodeDiv.innerHTML = '<p style="color:red; padding:20px;">❌ Failed to generate QR Code: ' + err.message + '</p>';
                showNotification('Gagal jana QR Code', 'error');
            }
        }

        function refreshQRCode() {
            generateQRCode();
        }

        // Print QR Code — support canvas & img
        function printQRCode() {
            const el = document.getElementById('qrCanvas');
            if (!el) { showNotification('Generate QR Code First!', 'error'); return; }

            const imgSrc = el.tagName === 'CANVAS' ? el.toDataURL('image/png') : el.src;
            const pw = window.open('', '_blank');
            pw.document.write(`
                <html><head>
                    <title>Locker QR - #<?php echo $locker['id']; ?></title>
                    <style>
                        body{font-family:Arial,sans-serif;text-align:center;padding:40px;}
                        .box{display:inline-block;padding:20px;border:2px solid #333;border-radius:12px;margin:20px 0;}
                        .info{margin-top:16px;text-align:left;display:inline-block;font-size:14px;}
                        .info p{margin:4px 0;}
                    </style>
                </head><body>
                    <h2>Smart Locker System</h2>
                    <h3>Locker #<?php echo $locker['id']; ?> — <?php echo htmlspecialchars($locker['display_name'] ?? $locker['unique_code'] ?? ''); ?></h3>
                    <div class="box"><img src="${imgSrc}" width="220" height="220"></div>
                    <div class="info">
                        <p><strong>Access Key:</strong> <?php echo htmlspecialchars($locker['key_value'] ?? ''); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($locker['display_location'] ?? ''); ?></p>
                        <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
                        <p><strong>Arahan:</strong> Scan QR ini di scanner locker untuk buka</p>
                    </div>
                </body></html>
            `);
            pw.document.close();
            setTimeout(() => { pw.focus(); pw.print(); pw.close(); }, 400);
        }

        // Download QR Code — support canvas & img (fallback)
        function downloadQRCode() {
            const el = document.getElementById('qrCanvas');
            if (!el) { showNotification('Generate QR Code First!', 'error'); return; }

            const link = document.createElement('a');
            link.download = `locker-<?php echo $locker['id']; ?>-qr.png`;

            if (el.tagName === 'CANVAS') {
                link.href = el.toDataURL('image/png');
                link.click();
            } else {
                // img element — fetch dan convert ke blob
                fetch(el.src)
                    .then(r => r.blob())
                    .then(blob => { link.href = URL.createObjectURL(blob); link.click(); })
                    .catch(() => showNotification('Gagal muat turun', 'error'));
            }
            showNotification('QR Code dimuat turun!', 'success');
        }

        // === EDIT LOCATION ===
        function toggleEditLocation() {
            document.getElementById('locationDisplay').style.display = 'none';
            document.getElementById('locationEditForm').style.display = 'block';
            document.getElementById('editLocationBtn').style.display = 'none';
            document.getElementById('locationInput').focus();
        }

        function cancelEditLocation() {
            document.getElementById('locationDisplay').style.display = 'block';
            document.getElementById('locationEditForm').style.display = 'none';
            document.getElementById('editLocationBtn').style.display = 'inline';
            // Reset input to original
            document.getElementById('locationInput').value = document.getElementById('locationDisplay').textContent.trim();
        }

        function saveLocation() {
            const newLocation = document.getElementById('locationInput').value.trim();
            if (!newLocation) {
                showNotification('⚠️ Location tidak boleh kosong!', 'error');
                return;
            }

            fetch('api/update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    locker_id: <?php echo (int)$locker['id']; ?>,
                    location: newLocation
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('locationDisplay').textContent = newLocation;
                    cancelEditLocation();
                    showNotification('✅ Location updated successfully!', 'success');
                } else {
                    showNotification('❌ ' + (data.message || 'Failed to update location'), 'error');
                }
            })
            .catch(() => showNotification('❌ Connection error', 'error'));
        }

        // Copy Access Key
        function copyAccessKey() {
            const key = document.getElementById('accessKeyDisplay').textContent.trim();
            navigator.clipboard.writeText(key).then(function() {
                const icon = document.getElementById('copyIcon');
                icon.className = 'fas fa-check';
                showNotification('✅ Access Key copied!', 'success');
                setTimeout(() => { icon.className = 'fas fa-copy'; }, 2000);
            }).catch(function() {
                showNotification('❌ Failed to copy Access Key', 'error');
            });
        }

        // Check if URL has success message + Auto-generate QR
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                showNotification('Operation completed successfully!', 'success');
            }
            if (urlParams.has('error')) {
                showNotification('An error occurred. Please try again.', 'error');
            }
            if (urlParams.has('generate_qr')) {
                setTimeout(() => { generateQRCode(); }, 500);
            }
        });
    </script>
</body>
</html>