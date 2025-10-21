<?php
// Handle admin login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // For development: Simple hardcoded admin check
    // TODO: Replace with proper password hashing in production
    if ($email === 'admin@school.edu' && $password === 'admin123') {
        // Get admin user from database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_email'] = $admin['email'];
            $_SESSION['role'] = 'admin';
            $_SESSION['display_name'] = $admin['name'];
            
            header("Location: ?page=admin");
            exit;
        }
    }
    
    $error = "Invalid email or password";
}

include __DIR__ . '/../layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">⚙️ Administrator Login</h4>
            </div>
            <div class="card-body p-4">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="?page=admin-login">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="admin@school.edu" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        🔐 Login as Admin
                    </button>
                    
                    <div class="alert alert-info small mb-0">
                        <strong>Development Mode:</strong><br>
                        Email: admin@school.edu<br>
                        Password: admin123
                    </div>
                </form>
                
                <hr>
                
                <div class="text-center">
                    <a href="?page=login" class="text-decoration-none">
                        ← Back to Main Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>