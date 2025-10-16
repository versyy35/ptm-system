<?php
require_once __DIR__ . '/../app/config/config.php';

echo "<h2>🧪 PTM Portal Configuration Test</h2>";

// Test database
echo "<h3>Database Connection</h3>";
try {
    $result = $pdo->query("SELECT 1");
    echo "✅ PDO Connection: <strong>SUCCESS</strong><br>";
} catch (Exception $e) {
    echo "❌ PDO Connection: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

if ($conn->ping()) {
    echo "✅ MySQLi Connection: <strong>SUCCESS</strong><br>";
} else {
    echo "❌ MySQLi Connection: <strong>FAILED</strong><br>";
}

// Test ISAMS config
echo "<br><h3>ISAMS Configuration</h3>";
echo "API URL: " . ISAMS_API_URL . "<br>";
echo "API Key: " . (strlen(ISAMS_API_KEY) > 10 ? "✅ Set (" . strlen(ISAMS_API_KEY) . " chars)" : "❌ Not set") . "<br>";

// Test MSP config
echo "<br><h3>MSP Configuration</h3>";
echo "MSP Domain: " . MSP_DOMAIN . "<br>";
echo "MSP API URL: " . MSP_API_URL . "<br>";

echo "<br><h3>Google OAuth</h3>";
echo "Client ID: " . (defined('GOOGLE_CLIENT_ID') ? "✅ Set" : "❌ Not configured") . "<br>";

echo "<br><hr>";
echo "<strong>✅ Configuration looks good!</strong>";
?>