<?php
/**
 * SSO Login from MSP (MySchoolPortal)
 * Handles both parent and teacher login via MSP SSO token
 */

require_once __DIR__ . '/../app/config/config.php';

// Check if SSO parameter exists
if (empty($_GET['sso'])) {
    header('Location: ?page=sso-required');
    exit;
}

// 1️⃣ Decode the SSO payload from MSP
$sso_raw = $_GET['sso'];
$sso_json = base64_decode($sso_raw);

if ($sso_json === false) {
    die('SSO Error: Invalid Base64 payload. Please try logging in again through MySchoolPortal.');
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
    die("SSO Error: Token validation failed at MySchoolPortal (HTTP $httpCode). Please try logging in again.");
}

// Parse MSP response
list($headers, $body) = explode("\r\n\r\n", $response, 2);
$data = json_decode($body, true);

if (!isset($data['users'][0]['email'])) {
    die('SSO Error: Unexpected API response format. Please contact support.');
}

$userRec = $data['users'][0];
$email = strtolower(trim($userRec['email']));
$forename = trim($userRec['forename'] ?? '');
$surname = trim($userRec['surname'] ?? '');
$userType = trim($userRec['type'] ?? '');

// Build full name
$fullName = trim("$forename $surname");
if (empty($fullName)) {
    list($nick) = explode('@', $email, 2);
    $fullName = ucfirst($nick);
}

// 3️⃣ Determine if user is teacher or parent
try {
    // First check if user exists
    $stmt = $pdo->prepare("
        SELECT u.id, u.role, t.id as teacher_id, p.id as parent_id
        FROM users u
        LEFT JOIN teachers t ON u.id = t.user_id
        LEFT JOIN parents p ON u.id = p.user_id
        WHERE LOWER(u.email) = ?
    ");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        // User exists - log them in
        $userId = $existingUser['id'];
        $role = $existingUser['role'];
        
        // Update user info from MSP
        $stmt = $pdo->prepare("UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$fullName, $userId]);
        
        // Set session
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['display_name'] = $fullName;
        $_SESSION['role'] = $role;
        $_SESSION['sso_login'] = true;
        
        if ($role === 'teacher') {
            $_SESSION['teacher_id'] = $existingUser['teacher_id'];
            error_log("SSO Login Success - Teacher: $email (ID: {$userId})");
            header("Location: ?page=teacher");
            exit;
        } elseif ($role === 'parent') {
            $_SESSION['parent_id'] = $existingUser['parent_id'];
            error_log("SSO Login Success - Parent: $email (ID: {$userId})");
            header("Location: ?page=parent");
            exit;
        } else {
            die("Access Denied: Your account type is not authorized to access PTM Portal.");
        }
    }
    
    // User doesn't exist - determine role
    $isStaffEmail = strpos($email, '@kl.his.edu.my') !== false || 
                    $userType === 'staff' || 
                    $userType === 'teacher';
    
    if ($isStaffEmail) {
        // 4️⃣A Handle new TEACHER login
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $teacherRecord = $stmt->fetch();
        
        if (!$teacherRecord) {
            die("Access Denied: Teacher account not found. Please ensure your data has been synced from ISAMS. Contact IT support if this issue persists.");
        }
        
        // Create user account for teacher
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO users (email, name, role, is_active, created_at) VALUES (?, ?, 'teacher', 1, NOW())");
        $stmt->execute([$email, $fullName]);
        $userId = $pdo->lastInsertId();
        
        // Link user to existing teacher record
        $stmt = $pdo->prepare("UPDATE teachers SET user_id = ? WHERE id = ?");
        $stmt->execute([$userId, $teacherRecord['id']]);
        
        $pdo->commit();
        
        // Create session
        $_SESSION['user_id'] = $userId;
        $_SESSION['teacher_id'] = $teacherRecord['id'];
        $_SESSION['user_email'] = $email;
        $_SESSION['display_name'] = $fullName;
        $_SESSION['role'] = 'teacher';
        $_SESSION['sso_login'] = true;
        
        error_log("SSO First Login - Teacher: $email (ID: {$userId})");
        header("Location: ?page=teacher");
        exit;
        
    } else {
        // 4️⃣B Handle new PARENT login
        $pdo->beginTransaction();
        
        // Create user
        $stmt = $pdo->prepare("INSERT INTO users (email, name, role, is_active, created_at) VALUES (?, ?, 'parent', 1, NOW())");
        $stmt->execute([$email, $fullName]);
        $userId = $pdo->lastInsertId();
        
        // Create parent record
        $stmt = $pdo->prepare("INSERT INTO parents (user_id, name, email, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $fullName, $email]);
        $parentId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        // Create session
        $_SESSION['user_id'] = $userId;
        $_SESSION['parent_id'] = $parentId;
        $_SESSION['user_email'] = $email;
        $_SESSION['display_name'] = $fullName;
        $_SESSION['role'] = 'parent';
        $_SESSION['sso_login'] = true;
        
        error_log("SSO First Login - Parent: $email (ID: {$userId})");
        header("Location: ?page=parent");
        exit;
    }
    
} catch (Exception $e) {
    error_log("SSO Login Error: " . $e->getMessage());
    die("System Error: Unable to complete login. Please contact support. Error: " . $e->getMessage());
}