<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $user_id_number = sanitize($_POST['user_id_number'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = sanitize($_POST['phone'] ?? '');
    $user_type = sanitize($_POST['user_type'] ?? 'student');
    $institution = sanitize($_POST['institution'] ?? '');
    
    // Validation
    if (empty($full_name) || empty($user_id_number) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            // Check if ID number already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_number = ?");
            $stmt->execute([$user_id_number]);
            
            if ($stmt->fetch()) {
                $error = 'ID Number already registered';
            } else {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = 'Email already registered';
                } else {
                    // Create user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (email, password_hash, full_name, phone, 
                                          user_id_number, user_type, institution, role, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'user', NOW())
                    ");
                    $stmt->execute([$email, $hashed_password, $full_name, $phone, 
                                   $user_id_number, $user_type, $institution]);
                    
                    $success = 'Registration successful! You can now login.';
                    echo "<script>setTimeout(function(){ window.location.href='login.php'; }, 2000);</script>";
                }
            }
        } catch(Exception $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Smart Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .signup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .signup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-8">
                <div class="signup-card">
                    <div class="signup-header text-center">
                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                        <h2>Create Account - Smart Locker</h2>
                        <p class="mb-0">For Students, Staff & Public Users</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="signupForm">
                            <!-- Personal Information -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?php echo $_POST['full_name'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo $_POST['phone'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <!-- ID Information -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ID/Matrix Number *</label>
                                    <input type="text" class="form-control" name="user_id_number" 
                                           value="<?php echo $_POST['user_id_number'] ?? ''; ?>" 
                                           placeholder="e.g., MAT123456, IC950101" required>
                                    <small class="form-text">This will be used for QR code access</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">User Type *</label>
                                    <select class="form-select" name="user_type" required>
                                        <option value="student" <?php echo ($_POST['user_type'] ?? '') == 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="staff" <?php echo ($_POST['user_type'] ?? '') == 'staff' ? 'selected' : ''; ?>>Staff</option>
                                        <option value="public" <?php echo ($_POST['user_type'] ?? '') == 'public' ? 'selected' : ''; ?>>Public User</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Institution -->
                            <div class="mb-3">
                                <label class="form-label">Institution/Organization</label>
                                <input type="text" class="form-control" name="institution" 
                                       value="<?php echo $_POST['institution'] ?? ''; ?>" 
                                       placeholder="e.g., MATRIKS University, ABC School">
                            </div>
                            
                            <!-- Email & Password -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="password" 
                                           required minlength="6">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a>
                                </label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                                <a href="login.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Already have account? Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>