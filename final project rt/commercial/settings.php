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
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "locker_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get user's current institution and info
$sql_user = "SELECT institution, email, phone, user_id_number, user_type 
             FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();

// Get user's assigned lockers with custom locations
$sql_lockers = "SELECT la.id as assignment_id, la.locker_id, 
                la.custom_name, la.custom_location,
                l.name as locker_name, l.location as default_location,
                l.unique_code, l.status
                FROM user_locker_assignments la
                JOIN lockers l ON la.locker_id = l.id
                WHERE la.user_id = ? AND la.is_active = 1
                ORDER BY la.assigned_at DESC";
$stmt_lockers = $conn->prepare($sql_lockers);
$stmt_lockers->bind_param("i", $user_id);
$stmt_lockers->execute();
$result_lockers = $stmt_lockers->get_result();
$assigned_lockers = [];
while ($row = $result_lockers->fetch_assoc()) {
    $assigned_lockers[] = $row;
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'update_institution') {
            // Update user institution
            $new_institution = trim($_POST['institution'] ?? '');
            
            if (!empty($new_institution)) {
                $sql = "UPDATE users SET institution = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $new_institution, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['institution'] = $new_institution;
                    $user['institution'] = $new_institution;
                    $message = 'Institution updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update institution.';
                    $message_type = 'error';
                }
            }
            
        } elseif ($action === 'update_locker_location') {
            // Update custom locker location
            $assignment_id = $_POST['assignment_id'] ?? 0;
            $custom_location = trim($_POST['custom_location'] ?? '');
            
            // Verify user owns this assignment
            $sql_check = "SELECT id FROM user_locker_assignments 
                         WHERE id = ? AND user_id = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("ii", $assignment_id, $user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                $sql = "UPDATE user_locker_assignments 
                        SET custom_location = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $custom_location, $assignment_id);
                
                if ($stmt->execute()) {
                    // Update locker array
                    foreach ($assigned_lockers as &$locker) {
                        if ($locker['assignment_id'] == $assignment_id) {
                            $locker['custom_location'] = $custom_location;
                            break;
                        }
                    }
                    $message = 'Locker location updated!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update locker location.';
                    $message_type = 'error';
                }
            } else {
                $message = 'Invalid locker assignment.';
                $message_type = 'error';
            }
            
        } elseif ($action === 'update_locker_name') {
            // Update custom locker name
            $assignment_id = $_POST['assignment_id'] ?? 0;
            $custom_name = trim($_POST['custom_name'] ?? '');
            
            // Verify user owns this assignment
            $sql_check = "SELECT id FROM user_locker_assignments 
                         WHERE id = ? AND user_id = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("ii", $assignment_id, $user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                $sql = "UPDATE user_locker_assignments 
                        SET custom_name = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $custom_name, $assignment_id);
                
                if ($stmt->execute()) {
                    // Update locker array
                    foreach ($assigned_lockers as &$locker) {
                        if ($locker['assignment_id'] == $assignment_id) {
                            $locker['custom_name'] = $custom_name;
                            break;
                        }
                    }
                    $message = 'Locker name updated!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update locker name.';
                    $message_type = 'error';
                }
            } else {
                $message = 'Invalid locker assignment.';
                $message_type = 'error';
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Smart Locker System</title>
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #495057;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
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

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .locker-list {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }

        .locker-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #27ae60;
        }

        .locker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .locker-header h4 {
            color: #2c3e50;
            font-size: 18px;
        }

        .locker-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }

        .detail-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-weight: 600;
            color: #2c3e50;
            margin-top: 5px;
        }

        .form-inline {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .form-inline input {
            flex: 1;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
        }

        .form-inline button {
            white-space: nowrap;
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
            
            .locker-details {
                grid-template-columns: 1fr;
            }
            
            .form-inline {
                flex-direction: column;
            }
            
            .locker-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-cog"></i> Settings & Preferences</h1>
            <p>Welcome, <?php echo htmlspecialchars($user_name); ?></p>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Institution Settings -->
            <div class="section">
                <h3><i class="fas fa-university"></i> Institution Settings</h3>
                <p>Update your institution/organization information:</p>
                
                <form method="POST" style="max-width: 500px;">
                    <input type="hidden" name="action" value="update_institution">
                    
                    <div class="info-grid">
                        <div class="info-card">
                            <h4>Current Institution</h4>
                            <p><?php echo htmlspecialchars($user['institution'] ?? 'Not set'); ?></p>
                        </div>
                        
                        <div class="info-card">
                            <h4>User Type</h4>
                            <p><?php echo ucfirst($user['user_type'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="institution">New Institution Name</label>
                        <input type="text" 
                               id="institution" 
                               name="institution" 
                               value="<?php echo htmlspecialchars($user['institution'] ?? ''); ?>"
                               placeholder="Enter your institution name">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Institution
                    </button>
                </form>
            </div>

            <!-- Locker Customization -->
            <div class="section">
                <h3><i class="fas fa-edit"></i> Locker Customization</h3>
                <p>Customize your assigned lockers with custom names and locations:</p>
                
                <?php if (count($assigned_lockers) > 0): ?>
                    <div class="locker-list">
                        <?php foreach ($assigned_lockers as $locker): ?>
                        <div class="locker-item">
                            <div class="locker-header">
                                <h4>
                                    <?php 
                                    $display_name = !empty($locker['custom_name']) 
                                        ? htmlspecialchars($locker['custom_name']) 
                                        : htmlspecialchars($locker['unique_code'] ?? $locker['locker_name'] ?? 'Locker');
                                    ?>
                                    <?php echo $display_name; ?>
                                </h4>
                                <span style="color: #6c757d; font-size: 14px;">
                                    Locker #<?php echo $locker['locker_id']; ?>
                                </span>
                            </div>
                            
                            <div class="locker-details">
                                <div class="detail-item">
                                    <div class="detail-label">Unique Code</div>
                                    <div class="detail-value"><code><?php echo htmlspecialchars($locker['unique_code'] ?? 'N/A'); ?></code></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Locker Name</div>
                                    <div class="detail-value"><?php echo !empty($locker['custom_name']) ? htmlspecialchars($locker['custom_name']) : htmlspecialchars($locker['unique_code'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Location</div>
                                    <div class="detail-value"><?php echo !empty($locker['custom_location']) ? htmlspecialchars($locker['custom_location']) : (!empty($locker['default_location']) ? htmlspecialchars($locker['default_location']) : '<em>Not set</em>'); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">
                                        <span style="color: <?php echo $locker['status'] == 'active' ? '#27ae60' : ($locker['status'] == 'maintenance' ? '#e74c3c' : '#3498db'); ?>; font-weight:600;">
                                            <?php echo ucfirst($locker['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Update Custom Name -->
                            <form method="POST" class="form-inline">
                                <input type="hidden" name="action" value="update_locker_name">
                                <input type="hidden" name="assignment_id" value="<?php echo $locker['assignment_id']; ?>">
                                <input type="text" 
                                       name="custom_name" 
                                       placeholder="Custom name (e.g., My Laptop Locker)"
                                       value="<?php echo htmlspecialchars($locker['custom_name'] ?? ''); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-font"></i> Rename
                                </button>
                            </form>
                            
                            <!-- Update Location -->
                            <form method="POST" class="form-inline" style="margin-top: 10px;">
                                <input type="hidden" name="action" value="update_locker_location">
                                <input type="hidden" name="assignment_id" value="<?php echo $locker['assignment_id']; ?>">
                                <input type="text" 
                                       name="custom_location" 
                                       placeholder="Location (e.g., A4.17)"
                                       value="<?php echo htmlspecialchars($locker['custom_location'] ?? ''); ?>">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-map-marker-alt"></i> Update Location
                                </button>
                            </form>
                            
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px; background: white; border-radius: 10px;">
                        <i class="fas fa-box-open fa-3x" style="color: #bdc3c7; margin-bottom: 15px;"></i>
                        <h4 style="color: #6c757d;">No Lockers Assigned</h4>
                        <p style="color: #95a5a6;">You haven't assigned any lockers yet.</p>
                        <a href="assign-locker.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-plus-circle"></i> Assign Your First Locker
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User Information -->
            <div class="section">
                <h3><i class="fas fa-user-circle"></i> Account Information</h3>
                <div class="info-grid">
                    <div class="info-card">
                        <h4>User ID</h4>
                        <p><?php echo htmlspecialchars($user['user_id_number'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Email Address</h4>
                        <p><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Phone Number</h4>
                        <p><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4>User Type</h4>
                        <p><?php echo ucfirst($user['user_type'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <a href="profile.php" class="btn btn-primary">
                        <i class="fas fa-user-edit"></i> Edit Complete Profile
                    </a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="dashboard.php" class="btn">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="my-locker.php" class="btn">
                        <i class="fas fa-box"></i> My Locker
                    </a>
                    <a href="access-logs.php" class="btn">
                        <i class="fas fa-history"></i> Activity Logs
                    </a>
                    <a href="generate-qr.php" class="btn" target="_blank">
                        <i class="fas fa-qrcode"></i> Generate QR
                    </a>
                    <a href="logout.php" class="btn" style="background: #e74c3c; color: white;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[required]');
                let valid = true;
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        valid = false;
                        input.style.borderColor = '#e74c3c';
                        setTimeout(() => {
                            input.style.borderColor = '#ddd';
                        }, 2000);
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>