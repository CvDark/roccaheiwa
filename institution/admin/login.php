<?php
require_once 'config.php';
if (isAdminLoggedIn()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_institution'] = $admin['institution'];
                $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
                redirect('dashboard.php');
            } else {
                $error = 'Invalid username or password.';
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
    <title>Admin Login — Institution</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{
            --navy:#0b1f3a;--navy-l:#122850;--teal:#0d7377;--teal-l:#e8f6f7;
            --gold:#f0a500;--white:#fff;--border:#d0e8e9;--err:#c0392b;
            --light:#f4f9f9;--mid:#3d5c5e;
        }
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--navy);
            min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:24px;position:relative;overflow:hidden;}

        /* Background grid */
        body::before{
            content:'';position:fixed;inset:0;
            background-image:linear-gradient(rgba(13,115,119,0.07) 1px,transparent 1px),
                             linear-gradient(90deg,rgba(13,115,119,0.07) 1px,transparent 1px);
            background-size:40px 40px;pointer-events:none;
        }
        .blob{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;}
        .blob-1{width:400px;height:400px;background:rgba(13,115,119,0.15);top:-80px;right:-80px;}
        .blob-2{width:300px;height:300px;background:rgba(240,165,0,0.07);bottom:-60px;left:-60px;}

        .card{
            background:rgba(255,255,255,0.04);
            border:1px solid rgba(255,255,255,0.10);
            border-radius:20px;width:100%;max-width:400px;
            backdrop-filter:blur(20px);
            box-shadow:0 32px 80px rgba(0,0,0,0.4);
            overflow:hidden;
            animation:fadeUp 0.6s ease both;
        }
        @keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}

        .card-top{
            background:linear-gradient(135deg,var(--teal),#085c60);
            padding:28px 32px;text-align:center;position:relative;overflow:hidden;
        }
        .card-top::after{
            content:'🛡️';position:absolute;right:20px;top:50%;
            transform:translateY(-50%);font-size:52px;opacity:0.12;
        }
        .admin-badge{
            display:inline-flex;align-items:center;gap:6px;
            background:rgba(240,165,0,0.2);border:1px solid rgba(240,165,0,0.35);
            color:var(--gold);font-size:10px;font-weight:700;letter-spacing:0.12em;
            text-transform:uppercase;padding:4px 12px;border-radius:20px;margin-bottom:12px;
        }
        .card-top h2{color:white;font-size:20px;font-weight:800;margin-bottom:4px;}
        .card-top p{color:rgba(255,255,255,0.6);font-size:12px;}

        .card-body{padding:28px 32px;}

        .error-box{
            background:rgba(192,57,43,0.12);border:1px solid rgba(192,57,43,0.3);
            border-radius:10px;padding:11px 14px;color:#ff8a80;
            font-size:13px;display:flex;align-items:center;gap:9px;margin-bottom:20px;
            animation:shake 0.3s ease;
        }
        @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}

        .field{margin-bottom:18px;}
        .field label{
            display:block;font-size:11px;font-weight:700;letter-spacing:0.08em;
            text-transform:uppercase;color:rgba(255,255,255,0.45);margin-bottom:7px;
        }
        .input-wrap{position:relative;}
        .input-wrap i{
            position:absolute;left:13px;top:50%;transform:translateY(-50%);
            color:rgba(255,255,255,0.2);font-size:13px;pointer-events:none;
        }
        .input-wrap input{
            width:100%;padding:12px 12px 12px 38px;
            background:rgba(255,255,255,0.06);
            border:1.5px solid rgba(255,255,255,0.10);
            border-radius:10px;color:white;
            font-family:inherit;font-size:14px;outline:none;
            transition:border-color 0.2s,background 0.2s;
        }
        .input-wrap input::placeholder{color:rgba(255,255,255,0.2);}
        .input-wrap input:focus{
            border-color:var(--teal);
            background:rgba(13,115,119,0.10);
            box-shadow:0 0 0 4px rgba(13,115,119,0.12);
        }
        .toggle-pw{
            position:absolute;right:12px;top:50%;transform:translateY(-50%);
            background:none;border:none;cursor:pointer;
            color:rgba(255,255,255,0.35);font-size:15px;transition:color 0.2s;
            z-index:5;padding:4px;line-height:1;
        }
        .toggle-pw:hover{color:rgba(255,255,255,0.8);}
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear{display:none;}

        .submit-btn{
            width:100%;padding:13px;background:var(--teal);border:none;
            border-radius:10px;color:white;font-family:inherit;
            font-size:15px;font-weight:700;cursor:pointer;
            transition:background 0.2s,transform 0.1s;
            display:flex;align-items:center;justify-content:center;gap:8px;
        }
        .submit-btn:hover{background:#085c60;}
        .submit-btn:active{transform:scale(0.99);}

        .back-link{
            text-align:center;margin-top:18px;
            font-size:12px;color:rgba(255,255,255,0.3);
        }
        .back-link a{color:rgba(255,255,255,0.5);text-decoration:none;transition:color 0.2s;}
        .back-link a:hover{color:white;}

        .footer-note{
            margin-top:28px;font-size:11px;color:rgba(255,255,255,0.2);text-align:center;
        }
    </style>
</head>
<body>
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>

<div class="card">
    <div class="card-top">
        <div class="admin-badge"><i class="fas fa-shield-alt"></i> Admin Portal</div>
        <h2>Institution Admin</h2>
        <p>Smart Locker Management System</p>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
        <div class="error-box"><i class="fas fa-exclamation-circle"></i><?php echo $error;?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="field">
                <label>Username</label>
                <div class="input-wrap">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" required placeholder="Admin username"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? '');?>">
                </div>
            </div>
            <div class="field">
                <label>Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="pw" required placeholder="Admin password">
                    <button type="button" class="toggle-pw" onclick="togglePw()">
                        <i class="fas fa-eye" id="eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="submit-btn">
                <i class="fas fa-sign-in-alt"></i> Login to Admin Panel
            </button>
        </form>
        <p class="back-link"><a href="../login.php">← Back to Institution Login</a></p>
    </div>
</div>
<p class="footer-note">© 2026 Smart Locker System — Institution Admin</p>

<script>
function togglePw(){
    const inp=document.getElementById('pw');
    const icon=document.getElementById('eye');
    if(inp.type==='password'){
        inp.type='text';
        icon.className='fas fa-eye-slash';
    } else {
        inp.type='password';
        icon.className='fas fa-eye';
    }
}
</script>
</body>
</html>