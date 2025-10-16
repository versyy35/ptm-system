<?php
/**
 * SSO Login Endpoint
 * Handles automatic parent login from MSP via SSO token
 */
session_start();
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/isams_config.php';
require_once __DIR__ . '/../app/services/ISAMSService.php';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    die("SSO Error: No token provided. Please login through the Main School Portal.");
}

$token = $_GET['token'];

// Initialize ISAMS service
$isamsService = new ISAMSService();

// Validate token and get parent data from ISAMS
$parentData = $isamsService->validateParentToken($token);

if (!$parentData) {
    // Token invalid or expired
    error_log("SSO Login Failed - Invalid token: $token");
    die("SSO Error: Invalid or expired token. Please try again from the Main School Portal.");
}

// At this point, we have valid parent data from ISAMS
// Now create or update parent in PTM database

try {
    $pdo->beginTransaction();
    
    // Check if user (parent) already exists
    $stmt = $pdo->prepare("
        SELECT u.id, p.id as parent_id 
        FROM users u 
        LEFT JOIN parents p ON u.id = p.user_id
        WHERE u.email = ? AND u.role = 'parent'
    ");
    $stmt->execute([$parentData['email']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Parent exists - update their info
        $userId = $existing['id'];
        $parentId = $existing['parent_id'];
        
        // Update user info
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$parentData['name'], $userId]);
        
        // Update parent info
        if ($parentId) {
            $stmt = $pdo->prepare("
                UPDATE parents 
                SET phone = ? 
                WHERE id = ?
            ");
            $stmt->execute([$parentData['phone'], $parentId]);
        } else {
            // Create parent record if missing
            $stmt = $pdo->prepare("
                INSERT INTO parents (user_id, phone) 
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $parentData['phone']]);
            $parentId = $pdo->lastInsertId();
        }
        
    } else {
        // New parent - create user and parent records
        $stmt = $pdo->prepare("
            INSERT INTO users (email, name, role) 
            VALUES (?, ?, 'parent')
        ");
        $stmt->execute([$parentData['email'], $parentData['name']]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            INSERT INTO parents (user_id, phone) 
            VALUES (?, ?)
        ");
        $stmt->execute([$userId, $parentData['phone']]);
        $parentId = $pdo->lastInsertId();
    }
    
    // Sync children from ISAMS
    syncChildrenFromISAMS($pdo, $parentId, $parentData['children']);
    
    $pdo->commit();
    
    // Create PTM session for parent
    $_SESSION['user_id'] = $userId;
    $_SESSION['parent_id'] = $parentId;
    $_SESSION['user_email'] = $parentData['email'];
    $_SESSION['display_name'] = $parentData['name'];
    $_SESSION['role'] = 'parent';
    $_SESSION['sso_login'] = true;
    $_SESSION['isams_id'] = $parentData['isams_id'];
    
    // Log successful SSO login
    error_log("SSO Login Success - Parent: {$parentData['email']} (ID: $parentId)");
    
    // Redirect to parent dashboard
    header("Location: ?page=parent");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("SSO Login Error: " . $e->getMessage());
    die("System Error: Unable to complete login. Please try again or contact support.");
}

/**
 * Sync children from ISAMS to PTM database
 * @param PDO $pdo Database connection
 * @param int $parentId Parent ID in PTM database
 * @param array $children Children data from ISAMS
 */
function syncChildrenFromISAMS($pdo, $parentId, $children) {
    if (empty($children)) {
        return;
    }
    
    foreach ($children as $child) {
        // Check if child already exists
        $stmt = $pdo->prepare("
            SELECT id FROM students 
            WHERE parent_id = ? AND name = ?
        ");
        $stmt->execute([$parentId, $child['name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing child
            $stmt = $pdo->prepare("
                UPDATE students 
                SET grade = ?, class = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $child['grade'], 
                $child['class'], 
                $existing['id']
            ]);
        } else {
            // Create new child
            $stmt = $pdo->prepare("
                INSERT INTO students (parent_id, name, grade, class) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $parentId,
                $child['name'],
                $child['grade'],
                $child['class']
            ]);
        }
    }
}