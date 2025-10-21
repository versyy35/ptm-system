<?php
// Session already started in config.php
require_once __DIR__ . '/../app/config/config.php';

// Get the page parameter
$page = $_GET['page'] ?? 'home';

// Route handling
switch ($page) {
    case 'home':
        header('Location: index.php');
        exit;
        
    case 'login':
        include __DIR__ . '/../app/views/auth/login.php';
        break;
        
    case 'admin-login':
        include __DIR__ . '/../app/views/auth/admin_login.php';
        break;
        
    case 'teacher':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
            header('Location: ?page=login');
            exit;
        }
        include __DIR__ . '/../app/views/teacher/dashboard.php';
        break;
        
    case 'parent':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
            header('Location: ?page=login');
            exit;
        }
        include __DIR__ . '/../app/views/parent/dashboard.php';
        break;
        
    case 'admin':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ?page=admin-login');
            exit;
        }
        include __DIR__ . '/../app/views/admin/dashboard.php';
        break;
    
    case 'admin-sync-subjects':
    case 'admin-view-subjects':
    case 'sync-isams':
    case 'sync-subjects':
    case 'view-subjects':
    case 'api-diagnostics':
    case 'xml_explore':
    case 'fix-teacher-email':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ?page=admin-login');
            exit;
        }
        $fileMap = [
            'sync-subjects' => 'sync_subjects.php',
            'view-subjects' => 'view_subjects.php',
            'sync-isams' => 'sync_isams.php',
            'sync-subjects' => 'sync_subjects.php',
            'view-subjects' => 'view_subjects.php',
            'api-diagnostics' => 'api_diagnostics.php',
            'xml_explore' => 'explore_xml_structure.php',
            'fix-teacher-email' => 'fix_teacher_emails.php'
        ];
        include __DIR__ . '../admin/' . $fileMap[$page];
        break;
        
    case 'logout':
        session_destroy();
        header('Location: index.php');
        exit;
        
    default:
        http_response_code(404);
        include __DIR__ . '/../app/views/errors/404.php';
        break;
}