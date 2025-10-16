<?php
// Include the config file which should load the Database class
require_once __DIR__ . '/../app/config/config.php';

echo "<h1>Database Connection Test</h1>";

try {
    // Check if Database class exists
    if (!class_exists('Database')) {
        throw new Exception("Database class not found. Check the file path.");
    }
    
    $db = Database::getInstance();
    echo "<p style='color: green;'>✅ Database connected successfully!</p>";
    
    // Test query
    $users = $db->fetchAll("SELECT COUNT(*) as total_users FROM users");
    echo "<p>Total users in database: <strong>" . $users[0]['total_users'] . "</strong></p>";
    
    // Show all users
    $allUsers = $db->fetchAll("SELECT name, email, role FROM users");
    echo "<h3>Sample Users:</h3>";
    echo "<ul>";
    foreach ($allUsers as $user) {
        echo "<li>{$user['name']} ({$user['email']}) - {$user['role']}</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    
    // Debug information
    echo "<h3>Debug Info:</h3>";
    echo "<p>Config file path: " . __DIR__ . '/../app/config/config.php' . "</p>";
    echo "<p>Database class file exists: " . (file_exists(__DIR__ . '/../app/utils/Database.php') ? 'Yes' : 'No') . "</p>";
}
?>