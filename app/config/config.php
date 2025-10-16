<?php
// Basic configuration
define('APP_NAME', 'PTM Portal');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost:8000');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ptm_portal');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // Use your actual password

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the Database class
require_once __DIR__ . '/../utils/Database.php';

// Create database connection
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
?>