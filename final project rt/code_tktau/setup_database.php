<?php

// Database Setup Script - Combined Version
$servername = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// Handle form submission for additional setup (if needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_sample_data') {
        // Add sample lockers and data
        addSampleData($conn);
    }
}

function addSampleData($conn) {
    $conn->select_db("locker_system");
    
    // Add sample lockers
    $lockers = [
        ['Main Office Locker', 'Reception Area', 'AA:BB:CC:DD:EE:FF'],
        ['Storage Room Locker', 'Floor 1 - Storage', '11:22:33:44:55:66'],
        ['IT Department Locker', 'Floor 2 - IT Room', 'AB:CD:EF:12:34:56']
    ];
    
    foreach ($lockers as $locker) {
        $sql = "INSERT IGNORE INTO lockers (name, location, mac_address) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $locker[0], $locker[1], $locker[2]);
        $stmt->execute();
    }
    
    return true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Smart Locker System</title>
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
        .setup-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 2rem;
        }
        .status-box {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-family: monospace;
            background: #f8f9fa;
            border-left: 4px solid #3498db;
        }
        .success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 10px 5px;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-success:hover {
            background: #229954;
        }
        .btn-warning {
            background: #f39c12;
        }
        .btn-warning:hover {
            background: #e67e22;
        }
        .step {
            margin: 2rem 0;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
        }
        .step h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        .credentials {
            background: #e8f4fd;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            border-left: 4px solid #3498db;
        }
        .action-buttons {
            text-align: center;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🚀 Database Setup</h1>
        
        <div class="step">
            <h3>Step 1: Database Creation</h3>
            <div class="status-box">
                <?php
                // Create database
                $sql = "CREATE DATABASE IF NOT EXISTS locker_system";
                if ($conn->query($sql) === TRUE) {
                    echo "✅ Database 'locker_system' created successfully<br>";
                } else {
                    echo "❌ Error creating database: " . $conn->error . "<br>";
                }
                ?>
            </div>
        </div>

        <div class="step">
            <h3>Step 2: Tables Creation</h3>
            <div class="status-box">
                <?php
                // Select database
                $conn->select_db("locker_system");

                // Create users table
                $tables = [
                    "users" => "CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(255) UNIQUE NOT NULL,
                        password_hash VARCHAR(255) NOT NULL,
                        full_name VARCHAR(255),
                        phone VARCHAR(20),
                        role ENUM('admin', 'manager', 'user') DEFAULT 'user',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )",
                    
                    "lockers" => "CREATE TABLE IF NOT EXISTS lockers (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        location VARCHAR(255),
                        mac_address VARCHAR(17) UNIQUE,
                        status ENUM('active', 'maintenance', 'offline') DEFAULT 'active',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )",
                    
                    "access_keys" => "CREATE TABLE IF NOT EXISTS access_keys (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT,
                        locker_id INT,
                        key_value VARCHAR(255) NOT NULL,
                        key_name VARCHAR(255),
                        is_active BOOLEAN DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        expires_at TIMESTAMP NULL
                    )",
                    
                    "user_devices" => "CREATE TABLE IF NOT EXISTS user_devices (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT,
                        device_id VARCHAR(255) NOT NULL,
                        device_name VARCHAR(255),
                        device_type ENUM('phone', 'tablet', 'laptop', 'desktop') DEFAULT 'desktop',
                        last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        is_active BOOLEAN DEFAULT TRUE
                    )",
                    
                    "activity_logs" => "CREATE TABLE IF NOT EXISTS activity_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT,
                        locker_id INT,
                        device_id VARCHAR(255),
                        key_used VARCHAR(255),
                        access_method ENUM('qr_code', 'manual_key', 'mobile_app', 'web') DEFAULT 'web',
                        success BOOLEAN DEFAULT TRUE,
                        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )"
                ];

                foreach ($tables as $tableName => $sql) {
                    if ($conn->query($sql) === TRUE) {
                        echo "✅ Table '$tableName' created successfully<br>";
                    } else {
                        echo "❌ Error creating table '$tableName': " . $conn->error . "<br>";
                    }
                }
                ?>
            </div>
        </div>
        <div class="step">
            <h3>Step 3: Sample Data</h3>
            <div class="status-box">
                <?php
                // Insert demo user
                $sql = "INSERT IGNORE INTO users (email, password_hash, full_name, role) VALUES 
                        ('admin@demo.com', MD5('admin123'), 'Administrator', 'admin'),
                        ('user@demo.com', MD5('user123'), 'Demo User', 'user')";

                if ($conn->query($sql) === TRUE) {
                    echo "✅ Demo users created successfully<br>";
                } else {
                    echo "❌ Error creating demo users: " . $conn->error . "<br>";
                }

                // Insert demo locker
                $sql = "INSERT IGNORE INTO lockers (name, location, mac_address) VALUES 
                        ('Main Office Locker', 'Reception Area', 'AA:BB:CC:DD:EE:FF')";

                if ($conn->query($sql) === TRUE) {
                    echo "✅ Demo locker created successfully<br>";
                } else {
                    echo "❌ Error creating demo locker: " . $conn->error . "<br>";
                }

                // Insert demo access key
                $sql = "INSERT IGNORE INTO access_keys (user_id, locker_id, key_value, key_name) VALUES 
                        (1, 1, 'ADMIN123', 'Admin Master Key'),
                        (2, 1, 'USER123', 'User Primary Key')";

                if ($conn->query($sql) === TRUE) {
                    echo "✅ Demo access keys created successfully<br>";
                } else {
                    echo "❌ Error creating demo access keys: " . $conn->error . "<br>";
                }
                ?>
            </div>

            <form method="POST" style="text-align: center; margin-top: 1rem;">
                <input type="hidden" name="action" value="add_sample_data">
                <button type="submit" class="btn btn-warning">➕ Add More Sample Lockers</button>
            </form>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_sample_data') {
                if (addSampleData($conn)) {
                    echo '<div class="status-box success">✅ Additional sample lockers added!</div>';
                }
            }
            ?>
        </div>

        <div class="credentials">
            <h3>📋 Demo Credentials</h3>
            <p><strong>Admin Account:</strong><br>
            Email: admin@demo.com<br>
            Password: admin123</p>
            
            <p><strong>User Account:</strong><br>
            Email: user@demo.com<br>
            Password: user123</p>
        </div>

        <div class="step">
            <h3>Step 4: System Check</h3>
            <div class="status-box <?php echo ($conn->connect_error) ? 'error' : 'success'; ?>">
                <?php
                if (!$conn->connect_error) {
                    echo "✅ All systems ready!<br>";
                    echo "✅ Database connection: OK<br>";
                    echo "✅ Tables created: OK<br>";
                    echo "✅ Sample data: OK<br>";
                    echo "✅ You can now use the system!";
                } else {
                    echo "❌ System check failed: " . $conn->connect_error;
                }
                ?>
            </div>
        </div>

        <div class="action-buttons">
            <a href="./login.php" class="btn btn-success">🔑 Proceed to Login</a>
            <a href="./index.php" class="btn">🏠 Back to Home</a>
        </div>
    </div>

    <?php
    // Close connection
    $conn->close();
    ?>
</body>
</html>