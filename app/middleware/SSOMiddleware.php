<?php
/**
 * SSO Middleware
 * Ensures parents and teachers can only access the system via SSO
 */

class SSOMiddleware {
    
    /**
     * Check if current user is authenticated via SSO
     */
    public static function requireSSO($requiredRole = null) {
        // Check if user is logged in
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            header('Location: ?page=sso-required');
            exit;
        }
        
        // Check if logged in via SSO
        if (!isset($_SESSION['sso_login']) || $_SESSION['sso_login'] !== true) {
            session_destroy();
            header('Location: ?page=sso-required');
            exit;
        }
        
        // Check role if specified
        if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
            http_response_code(403);
            die('Access Denied: You do not have permission to access this page.');
        }
        
        return true;
    }
    
    /**
     * Check if user is admin
     */
    public static function requireAdmin() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ?page=admin-login');
            exit;
        }
        
        return true;
    }
    
    /**
     * Redirect authenticated users to their dashboard
     */
    public static function redirectIfAuthenticated() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
            $role = $_SESSION['role'];
            header("Location: ?page={$role}");
            exit;
        }
    }
}