<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = sanitize($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login_id) || empty($password)) {
        $error = 'Please enter ID/Email and password';
    } else {
        try {
            if (!checkDatabase()) {
                $error = 'Database connection error. Please contact administrator.';
            } else {
                global $pdo;
                // Cari user dengan email ATAU user_id_number
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR user_id_number = ?");
                $stmt->execute([$login_id, $login_id]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    if (!$user['is_active']) {
                        $error = 'Account is deactivated. Please contact administrator.';
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['user_id_number'] = $user['user_id_number'];
                        $_SESSION['institution'] = $user['institution'];
                        
                        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        redirect('dashboard.php');
                    }
                } else {
                    $error = 'Invalid ID/Email or password';
                }
            }
        } catch(Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            height: 100vh; 
            display: flex; 
            align-items: center; 
        }
        .login-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
        }
        .login-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border-radius: 15px 15px 0 0; 
            padding: 30px; 
        }
        .login-options {
            border-left: 3px solid #667eea;
            padding-left: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-card">
                    <div class="login-header text-center">
                        <h2>Smart Locker System</h2>
                        <p>Secure access for institutions and public</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" autocomplete="off">
                            <div class="mb-3">
                                <label class="form-label">ID Number or Email</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="login_id" 
                                       required
                                       placeholder="Enter Matrix Number, IC or Email">
                                <small class="form-text text-muted">
                                    For students: Use Matrix Number (e.g., MAT123456)
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" 
                                       class="form-control" 
                                       name="password" 
                                       required
                                       placeholder="Enter your password">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Don't have an account? <a href="signup.php" class="text-decoration-none">Register here</a>
                                </small>
                            </div>
                        </form>
                        
                        <div class="mt-4 login-options">
                            <h6><i class="fas fa-qrcode me-2"></i>Quick Access Methods:</h6>
                            <small class="text-muted">
                                • Scan QR Code at locker station<br>
                                • Use ID Card with barcode<br>
                                • Enter ID number manually<br>
                                • Use mobile app
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3 text-white">
                    <small>For institutional use: MATRIKS University, Schools, etc.</small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>