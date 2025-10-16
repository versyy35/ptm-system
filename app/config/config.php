<?php
// Basic configuration
define('APP_NAME', 'PTM Portal');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost:8000');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ptm_portal');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // Change this to your actual password

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the Database class
require_once __DIR__ . '/../utils/Database.php';

// Create PDO connection (for modern usage)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create MySQLi connection (for compatibility with google_login.php)
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>