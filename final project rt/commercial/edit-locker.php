<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get locker ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid locker ID.';
    redirect('my-locker.php');
}

$locker_id = intval($_GET['id']);

// Check if user owns this locker
$stmt = $pdo->prepare("
    SELECT la.*, 
           l.status, l.unique_code, l.device_id,
           COALESCE(la.custom_name, l.unique_code) as display_name,
           COALESCE(la.custom_location, '') as display_location
    FROM user_locker_assignments la
    LEFT JOIN lockers l ON la.locker_id = l.id
    WHERE la.user_id = ? AND la.locker_id = ? AND la.is_active = 1
");
$stmt->execute([$user_id, $locker_id]);
$locker = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$locker) {
    $_SESSION['error'] = 'You do not have access to this locker or it does not exist.';
    redirect('my-locker.php');
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_details') {
        $custom_name = sanitize($_POST['custom_name'] ?? '');
        $custom_location = sanitize($_POST['custom_location'] ?? '');
        
        // Validate
        if (strlen($custom_name) > 100) {
            $message = 'Custom name must be less than 100 characters.';
            $message_type = 'error';
        } elseif (strlen($custom_location) > 200) {
            $message = 'Custom location must be less than 200 characters.';
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE user_locker_assignments 
                    SET custom_name = ?, custom_location = ?, updated_at = NOW() 
                    WHERE user_id = ? AND locker_id = ? AND is_active = 1
                ");
                $stmt->execute([$custom_name, $custom_location, $user_id, $locker_id]);
                
                // Update session data
                $locker['custom_name'] = $custom_name;
                $locker['custom_location'] = $custom_location;
                
                $message = 'Locker details updated successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error updating locker: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
    } elseif ($action === 'generate_new_key') {
        // Generate new access key
        $new_key = bin2hex(random_bytes(16));
        
        try {
            // Deactivate old keys
            $stmt = $pdo->prepare("
                UPDATE access_keys 
                SET is_active = 0, deactivated_at = NOW() 
                WHERE user_id = ? AND locker_id = ? AND is_active = 1
            ");
            $stmt->execute([$user_id, $locker_id]);
            
            // Create new key
            $stmt = $pdo->prepare("
                INSERT INTO access_keys 
                (user_id, locker_id, key_value, key_name, is_active, created_at, expires_at) 
                VALUES (?, ?, ?, 'Manual Regeneration', 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR))
            ");
            $stmt->execute([$user_id, $locker_id, $new_key]);
            
            // Log the action
            $stmt = $pdo->prepare("
                INSERT INTO access_logs 
                (user_id, locker_id, key_used, access_method, success, timestamp) 
                VALUES (?, ?, ?, 'key_regeneration', 1, NOW())
            ");
            $stmt->execute([$user_id, $locker_id, $new_key]);
            
            $message = 'New access key generated! Please save it securely.';
            $message_type = 'success';
            $new_key_generated = $new_key;
            
        } catch (Exception $e) {
            $message = 'Error generating new key: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get current access keys
$stmt = $pdo->prepare("
    SELECT * FROM access_keys 
    WHERE user_id = ? AND locker_id = ? AND is_active = 1 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id, $locker_id]);
$access_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Locker - Smart Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .edit-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px 0;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .card-section {
            border-left: 4px solid;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .card-section.info {
            border-color: #3498db;
        }
        
        .card-section.danger {
            border-color: #e74c3c;
        }
        
        .key-display {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px dashed #dee2e6;
            word-break: break-all;
        }
        
        .info-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .preview-card {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            border: 2px solid #3498db;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-lock me-2"></i>Smart Locker
            </a>
            <div>
                <a href="my-locker.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Lockers
                </a>
                <a href="locker-details.php?id=<?php echo $locker_id; ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-eye me-1"></i>View Details
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container py-4">
        <!-- Header -->
        <div class="edit-header text-center">
            <h1 class="display-6 fw-bold mb-3">
                <i class="fas fa-edit"></i> Edit Locker Settings
            </h1>
            <p class="lead mb-0">
                Customize your locker: <?php echo htmlspecialchars($locker['display_name'] ?? ''); ?>
            </p>
            <div class="mt-2">
                <span class="info-badge">
                    <i class="fas fa-hashtag me-1"></i>ID: <?php echo $locker_id; ?>
                </span>
                <span class="info-badge ms-2">
                    <i class="fas fa-qrcode me-1"></i>Code: <?php echo htmlspecialchars($locker['unique_code']); ?>
                </span>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            
            <?php if (isset($new_key_generated)): ?>
            <div class="mt-3">
                <strong>New Access Key:</strong>
                <div class="key-display mt-2"><?php echo htmlspecialchars($new_key_generated); ?></div>
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyToClipboard('<?php echo $new_key_generated; ?>')">
                    <i class="fas fa-copy me-1"></i>Copy Key
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Quick Preview -->
        <div class="preview-card">
            <h5><i class="fas fa-eye me-2"></i>Current Settings Preview</h5>
            <div class="row mt-3">
                <div class="col-md-6">
                    <p><strong>Locker Name:</strong> <?php echo htmlspecialchars($locker['display_name'] ?? ''); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($locker['display_location'] ?? ''); ?></p>
                </div>
                <div class="col-md-6">

                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Left Column: Edit Details -->
            <div class="col-lg-8">
                <!-- Edit Locker Details -->
                <div class="card card-section info">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Customize Locker Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_details">
                            
                            <div class="mb-3">
                                <label for="custom_name" class="form-label">Locker Name</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="custom_name" 
                                       name="custom_name" 
                                       value="<?php echo htmlspecialchars($locker['custom_name'] ?? ''); ?>"
                                       placeholder="e.g., My Laptop Locker, John's Storage"
                                       maxlength="100">
                                <div class="form-text">
                                    Leave empty if not needed
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="custom_location" class="form-label">Custom Location Description</label>
                                <textarea class="form-control" 
                                          id="custom_location" 
                                          name="custom_location" 
                                          rows="3"
                                          placeholder="e.g., Near main entrance, 2nd floor corridor, Building A"
                                          maxlength="200"><?php echo htmlspecialchars($locker['custom_location'] ?? ''); ?></textarea>
                                <div class="form-text">
                                    Leave empty if not needed
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="my-locker.php" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Access Keys Management -->
                <div class="card card-section mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i>Access Keys Management</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($access_keys): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Key Name</th>
                                        <th>Key Value</th>
                                        <th>Created</th>
                                        <th>Expires</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($access_keys as $key): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($key['key_name']); ?></td>
                                        <td>
                                            <code class="small"><?php echo substr($key['key_value'], 0, 8) . '...'; ?></code>
                                            <button class="btn btn-sm btn-outline-secondary ms-2" 
                                                    onclick="copyToClipboard('<?php echo $key['key_value']; ?>')"
                                                    title="Copy full key">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($key['created_at'])); ?></td>
                                        <td>
                                            <?php 
                                            $expiry_date = strtotime($key['expires_at']);
                                            $today = time();
                                            $days_left = floor(($expiry_date - $today) / (60 * 60 * 24));
                                            
                                            if ($days_left < 0) {
                                                echo '<span class="badge bg-danger">Expired</span>';
                                            } elseif ($days_left < 30) {
                                                echo '<span class="badge bg-warning">' . $days_left . ' days</span>';
                                            } else {
                                                echo date('d M Y', $expiry_date);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $key['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $key['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-3">No active access keys found.</p>
                        <?php endif; ?>
                        
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="action" value="generate_new_key">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> Generating a new key will deactivate all existing keys for this locker.
                            </div>
                            <button type="submit" class="btn btn-warning w-100" 
                                    onclick="return confirm('⚠️ Generate new access key?\n\nExisting keys will be deactivated immediately!')">
                                <i class="fas fa-key me-2"></i>Generate New Access Key
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Information & Actions -->
            <div class="col-lg-4">
                <!-- Locker Information -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Locker Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th>Locker ID:</th>
                                <td class="text-end">#<?php echo $locker_id; ?></td>
                            </tr>
                            <tr>
                            </tr>
                            <tr>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td class="text-end">
                                    <span class="badge bg-<?php echo $locker['status'] == 'occupied' ? 'success' : 'warning'; ?>">
                                        <?php echo strtoupper($locker['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Assigned Since:</th>
                                <td class="text-end"><?php echo date('d M Y', strtotime($locker['assigned_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="locker-details.php?id=<?php echo $locker_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-external-link-alt me-2"></i>View Details
                            </a>
                            <a href="generate-qr.php?locker_id=<?php echo $locker_id; ?>" target="_blank" class="btn btn-outline-success">
                                <i class="fas fa-qrcode me-2"></i>Generate QR
                            </a>
                            <a href="remove_access.php?id=<?php echo $locker_id; ?>" 
                               class="btn btn-outline-danger"
                               onclick="return confirm('⚠️ Remove access to this locker?\n\nYou will lose all access to this locker!')">
                                <i class="fas fa-trash me-2"></i>Remove Access
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Tips -->
                <div class="card mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Use meaningful names for easy identification
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Add location hints for physical access
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                Keep access keys secure
                            </li>
                            <li>
                                <i class="fas fa-sync-alt text-info me-2"></i>
                                Regenerate keys periodically
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">
                        <i class="fas fa-lock me-2"></i>Smart Locker System
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">
                        <small>
                            User: <?php echo htmlspecialchars($user_name); ?> | 
                            Editing: <?php echo htmlspecialchars($locker['display_name'] ?? ''); ?>
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Key copied to clipboard!');
        }).catch(function(err) {
            alert('Failed to copy: ' + err);
        });
    }
    
    // Auto-focus on first input
    document.getElementById('custom_name')?.focus();
    
    // Character counter
    document.getElementById('custom_name')?.addEventListener('input', function() {
        const counter = document.getElementById('nameCounter') || (function() {
            const div = document.createElement('div');
            div.id = 'nameCounter';
            div.className = 'form-text text-end';
            this.parentNode.appendChild(div);
            return div;
        }).bind(this)();
        
        counter.textContent = this.value.length + '/100 characters';
    });
    
    document.getElementById('custom_location')?.addEventListener('input', function() {
        const counter = document.getElementById('locationCounter') || (function() {
            const div = document.createElement('div');
            div.id = 'locationCounter';
            div.className = 'form-text text-end';
            this.parentNode.appendChild(div);
            return div;
        }).bind(this)();
        
        counter.textContent = this.value.length + '/200 characters';
    });
    
    // Initialize counters
    document.addEventListener('DOMContentLoaded', function() {
        const nameInput = document.getElementById('custom_name');
        const locationInput = document.getElementById('custom_location');
        
        if (nameInput) {
            nameInput.dispatchEvent(new Event('input'));
        }
        if (locationInput) {
            locationInput.dispatchEvent(new Event('input'));
        }
    });
    </script>
</body>
</html>