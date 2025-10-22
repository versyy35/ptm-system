<?php
/**
 * Router - SSO-only authentication
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/middleware/SSOMiddleware.php';

$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'home':
        SSOMiddleware::redirectIfAuthenticated();
        header('Location: index.php');
        exit;
    
    case 'sso-login':
        include __DIR__ . '/sso_login.php';
        break;
    
    case 'sso-required':
        include __DIR__ . '/../app/views/auth/sso_required.php';
        break;
    
    case 'login':
        header('Location: ?page=sso-required');
        exit;
    
    case 'admin-login':
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            header('Location: ?page=admin');
            exit;
        }
        include __DIR__ . '/../app/views/auth/admin_login.php';
        break;
    
    case 'admin':
        SSOMiddleware::requireAdmin();
        include __DIR__ . '/../app/views/admin/dashboard.php';
        break;
    
    case 'admin-sync-subjects':
    case 'sync-subjects':
    case 'view-subjects':
    case 'sync-isams':
    case 'api-diagnostics':
    case 'xml_explore':
    case 'fix-teacher-email':
        SSOMiddleware::requireAdmin();
        
        $fileMap = [
            'sync-subjects' => 'sync_subjects.php',
            'admin-sync-subjects' => 'sync_subjects.php',
            'view-subjects' => 'view_subjects.php',
            'sync-isams' => 'sync_isams.php',
            'api-diagnostics' => 'api_diagnostics.php',
            'xml_explore' => 'explore_xml_structure.php',
            'fix-teacher-email' => 'fix_teacher_emails.php'
        ];
        
        include __DIR__ . '/admin/' . $fileMap[$page];
        break;
    
    case 'teacher':
        SSOMiddleware::requireSSO('teacher');
        include __DIR__ . '/../app/views/teacher/dashboard.php';
        break;
    
    case 'parent':
        SSOMiddleware::requireSSO('parent');
        include __DIR__ . '/../app/views/parent/dashboard.php';
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