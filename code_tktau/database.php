<?php
session_start();
header('Content-Type: application/json');

class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "locker_system";
    public $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        
        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection() {
        return $this->conn;
    }
}

// Create database instance
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserID() {
    return $_SESSION['user_id'] ?? null;
}

function generateDeviceId() {
    return uniqid('device_', true);
}
?>