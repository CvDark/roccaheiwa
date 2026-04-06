<?php
session_start();

require_once 'config.php';
$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASS;
$dbname = DB_NAME;

if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
?>

$conn = new mysqli($servername, $username, $password, $dbname);
$db_connected = !$conn->connect_error;

if ($db_connected) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Locker System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        .tagline {
            color: #6c757d;
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        .status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
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
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 8px;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 160px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-login {
            background: #3498db;
        }
        .btn-login:hover {
            background: #2980b9;
        }
        .btn-register {
            background: #27ae60;
        }
        .btn-register:hover {
            background: #229954;
        }
        .btn-setup {
            background: #e74c3c;
        }
        .btn-setup:hover {
            background: #c0392b;
        }
        .button-group {
            margin: 2rem 0;
        }
        .demo-info {
            background: #e8f4fd;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            border-left: 4px solid #3498db;
            text-align: left;
        }
        .demo-info h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .features {
            text-align: left;
            margin: 2rem 0;
        }
        .features h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        .features ul {
            list-style: none;
            padding: 0;
        }
        .features li {
            padding: 0.5rem 0;
            padding-left: 2rem;
            position: relative;
        }
        .features li:before {
            content: "✅";
            position: absolute;
            left: 0;
        }
        .system-status {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 Smart Locker System</h1>
        <p class="tagline">Secure access control for your lockers</p>
        
        <div class="system-status">
            <h3>System Status</h3>
            <div class="status <?php echo $db_connected ? 'success' : 'error'; ?>">
                <?php
                if ($db_connected) {
                    echo "✅ Database: Connected and Ready";
                } else {
                    echo "❌ Database: Not Connected - Run Setup First";
                }
                ?>
            </div>
        </div>

        <div class="features">
            <h3>🌟 Features</h3>
            <ul>
                <li>Multi-device access support</li>
                <li>QR code generation</li>
                <li>Real-time activity logging</li>
                <li>Secure key management</li>
                <li>User access control</li>
                <li>Mobile-friendly interface</li>
            </ul>
        </div>

        <div class="button-group">
            <?php if (!$db_connected): ?>
                <a href="./setup_database.php" class="btn btn-setup">🚀 Setup Database</a>
            <?php endif; ?>
            
            <a href="./login.php" class="btn btn-login">🔑 Login</a>
            <a href="./register.php" class="btn btn-register">👤 Register</a>
        </div>

        <div class="demo-info">
            <h3>🎯 Quick Start</h3>
            <p><strong>First time?</strong> Click "Setup Database" to initialize the system.</p>
            <p><strong>Demo Access:</strong></p>
            <ul style="margin-top: 0.5rem;">
                <li>Admin: admin@demo.com / admin123</li>
                <li>User: user@demo.com / user123</li>
            </ul>
        </div>

        <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e9ecef; color: #6c757d; font-size: 0.9rem;">
            <p>Smart Locker System v1.0 • Secure • Reliable • User-Friendly</p>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn');
            
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Add click animation
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });

            // Auto-hide setup button if database is connected
            const dbStatus = document.querySelector('.status');
            if (dbStatus.textContent.includes('✅')) {
                const setupBtn = document.querySelector('.btn-setup');
                if (setupBtn) {
                    setupBtn.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>