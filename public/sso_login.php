<?php
/**
 * SSO Login from MSP (MySchoolPortal)
 * This handles parent login via MSP SSO token
 */
session_start();
require_once __DIR__ . '/../app/config/config.php';

// Check if SSO parameter exists
if (empty($_GET['sso'])) {
    die('SSO Error: Missing SSO parameter. Please login through MySchoolPortal.');
}

// 1️⃣ Decode the SSO payload from MSP
$sso_raw = $_GET['sso'];
$sso_json = base64_decode($sso_raw);

if ($sso_json === false) {
    die('SSO Error: Invalid Base64 payload.');
}

$sso = json_decode($sso_json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('SSO Error: Invalid JSON - ' . json_last_error_msg());
}

// 2️⃣ Validate token with MSP API
$msp_domain = MSP_DOMAIN;
$api_url = MSP_API_URL;

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'myschoolportal-id: ' . intval($sso['id']),
    'myschoolportal-token: ' . $sso['token'],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log("MSP SSO validation failed: HTTP $httpCode");
    die("SSO Error: Token validation failed at MySchoolPortal (HTTP $httpCode).");
}

// Parse MSP response
list($headers, $body) = explode("\r\n\r\n", $response, 2);
$data = json_decode($body, true);

if (!isset($data['users'][0]['email'])) {
    die('SSO Error: Unexpected API response format.');
}

$userRec = $data['users'][0];
$email = $userRec['email'];
$forename = $userRec['forename'] ?? '';
$surname = $userRec['surname'] ?? '';

// Fallback: if no name, use email
if (trim("$forename$surname") === '') {
    list($nick) = explode('@', $email, 2);
    $forename = $nick;
    $surname = '';
}

// 3️⃣ Look up parent and their children in PTM database
try {
    // Check if parent exists in users table
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, p.id as parent_id
        FROM users u
        LEFT JOIN parents p ON u.id = p.user_id
        WHERE u.email = ? AND u.role = 'parent'
    ");
    $stmt->execute([$email]);
    $parentUser = $stmt->fetch();
    
    if (!$parentUser) {
        // Create new parent user
        $pdo->beginTransaction();
        
        $fullName = trim("$forename $surname");
        
        $stmt = $pdo->prepare("
            INSERT INTO users (email, name, role, created_at) 
            VALUES (?, ?, 'parent', NOW())
        ");
        $stmt->execute([$email, $fullName]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            INSERT INTO parents (user_id) 
            VALUES (?)
        ");
        $stmt->execute([$userId]);
        $parentId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        error_log("SSO: Created new parent account for $email");
    } else {
        $userId = $parentUser['id'];
        $parentId = $parentUser['parent_id'];
    }
    
    // Get parent's children
    $stmt = $pdo->prepare("
        SELECT id, name, grade, class 
        FROM students 
        WHERE parent_id = ?
    ");
    $stmt->execute([$parentId]);
    $children = $stmt->fetchAll();
    
    if (empty($children)) {
        die("No children found linked to this parent account. Please contact the school office.");
    }
    
    // 4️⃣ Create PTM session
    $_SESSION['user_id'] = $userId;
    $_SESSION['parent_id'] = $parentId;
    $_SESSION['user_email'] = $email;
    $_SESSION['display_name'] = trim("$forename $surname") ?: $email;
    $_SESSION['role'] = 'parent';
    $_SESSION['sso_login'] = true;
    
    // Log successful SSO login
    error_log("SSO Login Success - Parent: $email (ID: $parentId)");
    
    // Redirect to parent dashboard
    header("Location: ?page=parent");
    exit;
    
} catch (Exception $e) {
    error_log("SSO Login Error: " . $e->getMessage());
    die("System Error: Unable to complete login. Please contact support.");
}