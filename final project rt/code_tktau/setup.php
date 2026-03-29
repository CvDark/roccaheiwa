<?php
require_once 'config.php';

echo "<h2>Setting up Smart Locker Database</h2>";

try {
    // Create tables one by one to avoid SQL errors
    $tables = [
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            phone VARCHAR(20),
            role ENUM('admin', 'manager', 'user') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        "lockers" => "CREATE TABLE IF NOT EXISTS lockers (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            location VARCHAR(255),
            mac_address VARCHAR(17),
            device_id VARCHAR(255),
            unique_code VARCHAR(50) UNIQUE,
            status ENUM('available', 'occupied', 'active', 'maintenance') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        "user_locker_assignments" => "CREATE TABLE IF NOT EXISTS user_locker_assignments (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            locker_id INT(11) NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (locker_id) REFERENCES lockers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        "access_keys" => "CREATE TABLE IF NOT EXISTS access_keys (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            locker_id INT(11) NOT NULL,
            key_value VARCHAR(255) NOT NULL,
            key_name VARCHAR(255) DEFAULT 'Primary Access Key',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (locker_id) REFERENCES lockers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        "access_logs" => "CREATE TABLE IF NOT EXISTS access_logs (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            locker_id INT(11) NOT NULL,
            key_used VARCHAR(255),
            access_method ENUM('qr_code', 'manual_key', 'mobile_app', 'web'),
            success TINYINT(1) DEFAULT 0,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            device_id VARCHAR(100),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (locker_id) REFERENCES lockers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ];
    
    foreach ($tables as $table_name => $create_sql) {
        try {
            $pdo->exec($create_sql);
            echo "✅ Table '$table_name' created successfully<br>";
        } catch (PDOException $e) {
            echo "❌ Error creating table '$table_name': " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>";

    // Create admin user (with error handling)
    $email = 'admin@example.com';
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $full_name = 'System Administrator';
    
    try {
        // Check if admin already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$email, $hashed_password, $full_name]);
            echo "✅ Admin user created!<br>";
            echo "📧 Email: $email<br>";
            echo "🔑 Password: $password<br>";
        } else {
            echo "ℹ️ Admin user already exists<br>";
        }
    } catch (PDOException $e) {
        echo "❌ Error creating admin user: " . $e->getMessage() . "<br>";
    }
    
    echo "<br>";

    // Create some sample lockers (CORRECTED VERSION)
    $sample_lockers = [
        ['name' => 'Locker 001', 'location' => 'Building A, Floor 1', 'mac_address' => '00:1A:2B:3C:4D:5E', 'device_id' => 'DEV001', 'unique_code' => 'LKR001', 'status' => 'available'],
        ['name' => 'Locker 002', 'location' => 'Building A, Floor 1', 'mac_address' => '00:1A:2B:3C:4D:5F', 'device_id' => 'DEV002', 'unique_code' => 'LKR002', 'status' => 'available'],
        ['name' => 'Locker 003', 'location' => 'Building B, Floor 2', 'mac_address' => '00:1A:2B:3C:4D:60', 'device_id' => 'DEV003', 'unique_code' => 'LKR003', 'status' => 'occupied'],
        ['name' => 'Locker 004', 'location' => 'Building C, Lobby', 'mac_address' => '00:1A:2B:3C:4D:61', 'device_id' => 'DEV004', 'unique_code' => 'LKR004', 'status' => 'available'],
    ];
    
    $inserted_count = 0;
    foreach ($sample_lockers as $locker) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO lockers (name, location, mac_address, device_id, unique_code, status) 
                VALUES (:name, :location, :mac_address, :device_id, :unique_code, :status)
            ");
            $stmt->execute($locker);
            $inserted_count++;
        } catch (PDOException $e) {
            // Ignore duplicate unique_code errors
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo "❌ Error inserting locker: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    echo "✅ Sample lockers created ($inserted_count lockers)<br>";
    
    echo "<h3>🎉 Setup completed successfully!</h3>";
    echo "<p>Now you can <a href='login.php'>login</a> with:</p>";
    echo "<ul>";
    echo "<li>Email: admin@example.com</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "❌ Setup failed: " . $e->getMessage();
    echo "<pre>" . print_r($e->errorInfo, true) . "</pre>";
}
?>