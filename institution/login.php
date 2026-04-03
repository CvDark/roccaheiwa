<?php
require_once 'config.php';

if (isLoggedIn()) redirect('dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = sanitize($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_id) || empty($password)) {
        $error = 'Please enter your Student/Staff ID and password.';
    } else {
        try {
            if (!checkDatabase()) {
                $error = 'Database connection error. Please contact administrator.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM users 
                    WHERE (email = ? OR user_id_number = ?) 
                      AND user_type IN ('student','staff')
                ");
                $stmt->execute([$login_id, $login_id]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if (!$user['is_active']) {
                        $error = 'Account is deactivated. Please contact your institution administrator.';
                    } else {
                        $_SESSION['user_id']        = $user['id'];
                        $_SESSION['user_email']     = $user['email'];
                        $_SESSION['user_name']      = $user['full_name'];
                        $_SESSION['user_role']      = $user['role'];
                        $_SESSION['user_type']      = $user['user_type'];
                        $_SESSION['user_id_number'] = $user['user_id_number'];
                        $_SESSION['institution']    = $user['institution'];

                        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                            ->execute([$user['id']]);

                        redirect('dashboard.php');
                    }
                } else {
                    $error = 'Invalid ID or password. Please try again.';
                }
            }
        } catch (Exception $e) {
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
    <title>Institution Login — Smart Locker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --teal:    #0d7377;
            --teal-d:  #085c60;
            --teal-l:  #e8f6f7;
            --mint:    #14a98a;
            --dark:    #0f1f20;
            --mid:     #3d5c5e;
            --light:   #f4f9f9;
            --white:   #ffffff;
            --border:  #d0e8e9;
            --err:     #c0392b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── TOP BAR ── */
        .topbar {
            background: var(--dark);
            padding: 12px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topbar-brand {
            display: flex; align-items: center; gap: 10px;
            color: white; font-weight: 700; font-size: 15px;
            text-decoration: none;
        }
        .topbar-brand .icon {
            width: 30px; height: 30px; background: var(--teal);
            border-radius: 7px; display: grid; place-items: center;
            font-size: 14px;
        }
        .topbar-back {
            font-size: 12px; color: rgba(255,255,255,0.55);
            text-decoration: none; display: flex; align-items: center; gap: 6px;
            transition: color 0.2s;
        }
        .topbar-back:hover { color: white; }

        /* ── MAIN LAYOUT ── */
        .main {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 480px 1fr;
            align-items: center;
            padding: 48px 24px;
            gap: 0;
        }

        /* ── SIDE PANEL ── */
        .side-info {
            padding: 0 48px 0 24px;
            animation: fadeLeft 0.7s ease both;
        }
        @keyframes fadeLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .side-badge {
            display: inline-flex; align-items: center; gap: 7px;
            background: var(--teal-l); color: var(--teal);
            font-size: 11px; font-weight: 700; letter-spacing: 0.1em;
            text-transform: uppercase; padding: 5px 14px;
            border-radius: 20px; margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        .side-badge span {
            width: 6px; height: 6px; background: var(--teal);
            border-radius: 50%; animation: blink 1.5s infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }

        .side-title {
            font-size: 32px; font-weight: 800; color: var(--dark);
            line-height: 1.15; margin-bottom: 16px;
        }
        .side-title em { font-style: normal; color: var(--teal); }
        .side-desc {
            font-size: 14px; color: var(--mid); line-height: 1.8;
            margin-bottom: 28px;
        }
        .method-list { display: flex; flex-direction: column; gap: 10px; }
        .method-item {
            display: flex; align-items: center; gap: 12px;
            background: white; border: 1.5px solid var(--border);
            border-radius: 10px; padding: 12px 16px;
        }
        .method-icon {
            width: 34px; height: 34px; border-radius: 8px;
            background: var(--teal-l); color: var(--teal);
            display: grid; place-items: center; font-size: 14px;
            flex-shrink: 0;
        }
        .method-text strong { font-size: 13px; color: var(--dark); display: block; }
        .method-text span   { font-size: 12px; color: var(--mid); }

        /* ── CARD ── */
        .card-wrap {
            animation: fadeUp 0.6s ease both;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .login-card {
            background: white;
            border: 1.5px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(13,115,119,0.10);
        }
        .card-header {
            background: var(--teal);
            padding: 28px 32px;
            position: relative;
            overflow: hidden;
        }
        .card-header::after {
            content: '🏫';
            position: absolute;
            right: 24px; top: 50%;
            transform: translateY(-50%);
            font-size: 48px;
            opacity: 0.15;
        }
        .card-header h2 {
            color: white; font-size: 20px; font-weight: 800;
            margin-bottom: 4px;
        }
        .card-header p { color: rgba(255,255,255,0.75); font-size: 13px; }

        .card-body { padding: 32px; }

        .error-box {
            background: #fdf0ef; border: 1.5px solid #f5c6c2;
            border-radius: 10px; padding: 12px 16px;
            color: var(--err); font-size: 13px;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 24px;
            animation: shake 0.3s ease;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)}
        }

        .field { margin-bottom: 20px; }
        .field label {
            display: block; font-size: 12px; font-weight: 700;
            letter-spacing: 0.06em; text-transform: uppercase;
            color: var(--mid); margin-bottom: 8px;
        }
        .input-wrap { position: relative; }
        .input-wrap i {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: #aac5c6; font-size: 14px;
        }
        .input-wrap input {
            width: 100%; padding: 12px 40px 12px 40px;
            border: 2px solid var(--border); border-radius: 10px;
            font-family: inherit; font-size: 14px; color: var(--dark);
            background: var(--light); transition: border-color 0.2s, background 0.2s;
            outline: none;
        }
        .input-wrap input:focus {
            border-color: var(--teal);
            background: white;
            box-shadow: 0 0 0 4px rgba(13,115,119,0.08);
        }
        .input-wrap .toggle-pw {
            position: absolute; right: 12px;
            top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #aac5c6; font-size: 14px; line-height: 1;
            padding: 0; margin: 0; display: block;
            transition: color 0.2s;
        }
        .input-wrap .toggle-pw:hover { color: var(--teal); }
        /* Hide browser built-in password reveal button */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear,
        input[type="password"]::-webkit-contacts-auto-fill-button,
        input[type="password"]::-webkit-credentials-auto-fill-button {
            display: none !important;
            visibility: hidden;
            pointer-events: none;
        }

        .scan-btn {
            width: 100%; padding: 11px;
            background: var(--teal-l); border: 2px dashed var(--border);
            border-radius: 10px; color: var(--teal);
            font-family: inherit; font-size: 13px; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-bottom: 20px;
        }
        .scan-btn:hover {
            background: white; border-color: var(--teal);
            box-shadow: 0 0 0 4px rgba(13,115,119,0.08);
        }

        .divider {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 20px;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }
        .divider span { font-size: 11px; color: #aaa; font-weight: 600; letter-spacing: 0.06em; }

        .submit-btn {
            width: 100%; padding: 14px;
            background: var(--teal); border: none;
            border-radius: 10px; color: white;
            font-family: inherit; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: background 0.2s, transform 0.1s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .submit-btn:hover  { background: var(--teal-d); }
        .submit-btn:active { transform: scale(0.99); }

        .card-footer-link {
            text-align: center; margin-top: 20px;
            font-size: 13px; color: var(--mid);
        }
        .card-footer-link a {
            color: var(--teal); font-weight: 600; text-decoration: none;
        }
        .card-footer-link a:hover { text-decoration: underline; }

        /* ── FOOTER ── */
        footer {
            text-align: center; padding: 20px;
            font-size: 11px; color: #aaa;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 860px) {
            .main { grid-template-columns: 1fr; }
            .side-info { display: none; }
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <a class="topbar-brand" href="../index.php">
        <div class="icon">🔒</div>
        Smart Locker System
    </a>
    <a href="../index.php" class="topbar-back">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- Left Info Panel -->
    <div class="side-info">
        <div class="side-badge"><span></span> Institution Portal</div>
        <h1 class="side-title">Welcome to<br><em>Smart Locker</em><br>Institution</h1>
        <p class="side-desc">
            Secure locker access for students and staff.<br>
            Login with your Student/Staff ID or scan your card.
        </p>
        <div class="method-list">
            <div class="method-item">
                <div class="method-icon"><i class="fas fa-id-card"></i></div>
                <div class="method-text">
                    <strong>Student / Staff ID</strong>
                    <span>Enter your matric or staff number</span>
                </div>
            </div>
            <div class="method-item">
                <div class="method-icon"><i class="fas fa-qrcode"></i></div>
                <div class="method-text">
                    <strong>Scan Card QR/Barcode</strong>
                    <span>Use GM861 scanner or camera</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Centre: Login Card -->
    <div class="card-wrap">
        <div class="login-card">
            <div class="card-header">
                <h2>Institution Login</h2>
                <p>Students &amp; Staff only</p>
            </div>
            <div class="card-body">

                <?php if ($error): ?>
                <div class="error-box">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <!-- Scan Card Button -->
                <button type="button" class="scan-btn" onclick="window.location.href='scan-login.php'">
                    <i class="fas fa-qrcode"></i> Scan Student / Staff Card
                </button>

                <div class="divider"><span>or login manually</span></div>

                <form method="POST" autocomplete="off">
                    <div class="field">
                        <label>Student / Staff ID or Email</label>
                        <div class="input-wrap">
                            <i class="fas fa-id-badge"></i>
                            <input type="text" name="login_id" required
                                   placeholder="e.g. MC2516203265 or email"
                                   value="<?php echo isset($_POST['login_id']) ? htmlspecialchars($_POST['login_id']) : ''; ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="pwInput" required placeholder="Enter your password">
                            <button type="button" class="toggle-pw" onclick="togglePw()">
                                <i class="fas fa-eye" id="pwEye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>

                <p class="card-footer-link">
                    New here? <a href="signup.php">Create account</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Right spacer -->
    <div></div>
</div>

<footer>
    © 2026 Smart Locker System — Institution Version &nbsp;·&nbsp; Powered by Smart Locker
</footer>

<script>
function togglePw() {
    const inp = document.getElementById('pwInput');
    const eye = document.getElementById('pwEye');
    if (inp.type === 'password') {
        inp.type = 'text';
        eye.classList.replace('fa-eye','fa-eye-slash');
    } else {
        inp.type = 'password';
        eye.classList.replace('fa-eye-slash','fa-eye');
    }
}
</script>
</body>
</html>