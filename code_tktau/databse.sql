-- database.sql
-- Drop tables if they exist (optional - uncomment if needed)
-- DROP TABLE IF EXISTS access_logs;
-- DROP TABLE IF EXISTS access_keys;
-- DROP TABLE IF EXISTS user_locker_assignments;
-- DROP TABLE IF EXISTS lockers;
-- DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    phone VARCHAR(20),
    role ENUM('admin', 'manager', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create lockers table
CREATE TABLE IF NOT EXISTS lockers (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    location VARCHAR(255),
    mac_address VARCHAR(17),
    device_id VARCHAR(255),
    unique_code VARCHAR(50) UNIQUE,
    status ENUM('available', 'occupied', 'active', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create user_locker_assignments table
CREATE TABLE IF NOT EXISTS user_locker_assignments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    locker_id INT(11) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (locker_id) REFERENCES lockers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create access_keys table
CREATE TABLE IF NOT EXISTS access_keys (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create access_logs table
CREATE TABLE IF NOT EXISTS access_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert admin user
INSERT INTO users (email, password_hash, full_name, role) 
VALUES (
    'admin@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'System Administrator', 
    'admin'
);

-- Insert sample lockers
INSERT INTO lockers (name, location, mac_address, device_id, unique_code, status) VALUES
('Locker 001', 'Building A, Floor 1', '00:1A:2B:3C:4D:5E', 'DEV001', 'LKR-001', 'available'),
('Locker 002', 'Building A, Floor 1', '00:1A:2B:3C:4D:5F', 'DEV002', 'LKR-002', 'available'),
('Locker 003', 'Building B, Floor 2', '00:1A:2B:3C:4D:60', 'DEV003', 'LKR-003', 'occupied'),
('Locker 004', 'Building C, Lobby', '00:1A:2B:3C:4D:61', 'DEV004', 'LKR-004', 'available');