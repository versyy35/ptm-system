<?php
session_start();
require_once __DIR__ . '/../app/config/config.php';

// Get the page parameter
$page = $_GET['page'] ?? 'home';

// Route handling
switch ($page) {
    case 'home':
        include __DIR__ . '/index.php';
        break;
        
    case 'login':
        include __DIR__ . '/../app/views/auth/login.php';
        break;
        
    case 'admin-login':
        include __DIR__ . '/../app/views/auth/admin_login.php';
        break;
        
    case 'teacher':
        // Check if teacher is logged in
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
            header('Location: ?page=login');
            exit;
        }
        include __DIR__ . '/../app/views/teacher/dashboard.php';
        break;
        
    case 'parent':
        // Check if parent is logged in
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
            header('Location: ?page=login');
            exit;
        }
        include __DIR__ . '/../app/views/parent/dashboard.php';
        break;
        
    case 'admin':
        // Check if admin is logged in
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ?page=admin-login');
            exit;
        }
        include __DIR__ . '/../app/views/admin/dashboard.php';
        break;
    
    case 'sync-isams':
        // Admin only!
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ?page=admin-login');
            exit;
        }
        include __DIR__ . '/admin/sync_isams.php';
        break;
        
    case 'logout':
        session_destroy();
        header('Location: ?page=login');
        exit;
        break;

    case 'sync-subjects':
        // Admin only!
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ?page=admin-login');
            exit;
        }
        include __DIR__ . '/sync_subjects.php';
        break;
        
    default:
        include __DIR__ . '/../app/views/errors/404.php';
        break;
}
?>