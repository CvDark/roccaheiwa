<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_COMMERCIAL');
    session_start();
}

// Kalau dah login, redirect ke dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: admin-dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    require_once dirname(__DIR__) . '/config.php';

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_role'] = $admin['role'];

        $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

        header('Location: admin-dashboard.php');
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Smart Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            background: linear-gradient(135deg, #1a1a2e, #0f3460);
            color: white;
            padding: 40px 30px 30px;
            text-align: center;
        }

        .login-header .icon-wrap {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
        }

        .login-body {
            padding: 35px 30px;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            font-size: 15px;
        }

        .form-control:focus {
            border-color: #0f3460;
            box-shadow: 0 0 0 3px rgba(15, 52, 96, 0.1);
        }

        .input-group-text {
            border-radius: 10px 0 0 10px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            color: #6c757d;
        }

        .input-group .form-control {
            border-radius: 0 10px 10px 0;
            border-left: none;
        }

        .btn-login {
            background: linear-gradient(135deg, #1a1a2e, #0f3460);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 16px;
            font-weight: 700;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 52, 96, 0.4);
            color: white;
        }

        .alert {
            border-radius: 10px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-header">
            <div class="icon-wrap"><i class="fas fa-shield-alt"></i></div>
            <h4 class="fw-bold mb-1">Admin Portal</h4>
            <p class="mb-0 opacity-75 small">Smart Locker System</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Enter admin username"
                            required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="passwordInput" class="form-control"
                            placeholder="Enter password" required>
                        <button type="button" class="btn btn-outline-secondary"
                            style="border-radius:0 10px 10px 0; border:2px solid #e9ecef; border-left:none;"
                            onclick="togglePwd()"><i class="fas fa-eye" id="eyeIcon"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to Admin Panel
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="../index.php" class="text-muted small">
                    <i class="fas fa-arrow-left me-1"></i>Back to Main Site
                </a>
            </div>
        </div>
    </div>

    <script>
        function togglePwd() {
            const inp = document.getElementById('passwordInput');
            const icon = document.getElementById('eyeIcon');
            if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
            else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
        }
    </script>
</body>

</html>