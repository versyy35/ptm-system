<?php
// Enhanced error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// Load environment variables FIRST
require_once __DIR__ . '/env_loader.php';

// Start session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 86400);
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Basic configuration - now from .env
define('APP_NAME', getenv('APP_NAME') ?: 'PTM Portal');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost:8000');

// Database configuration - now from .env
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ptm_portal');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ISAMS API - for syncing data only - now from .env
define('ISAMS_API_KEY', getenv('ISAMS_API_KEY') ?: '');
define('ISAMS_API_URL', getenv('ISAMS_API_URL') ?: '');

// Subjects/Teachers API - NEW - from .env
define('SUBJECTS_API_KEY', getenv('SUBJECTS_API_KEY') ?: '');
define('SUBJECTS_API_URL', getenv('SUBJECTS_API_URL') ?: '');

// MSP Configuration - for SSO login - now from .env
define('MSP_DOMAIN', getenv('MSP_DOMAIN') ?: 'his.myschoolportal.co.uk');
define('MSP_API_URL', getenv('MSP_API_URL') ?: '');

// SendGrid Configuration - now from .env
define('SENDGRID_API_KEY', getenv('SENDGRID_API_KEY') ?: '');

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