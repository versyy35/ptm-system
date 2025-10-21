<?php
// Session is now started in config.php
require_once __DIR__ . '/../app/config/config.php';

// If page parameter exists, use router
if (isset($_GET['page'])) {
    require_once __DIR__ . '/router.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PTM Portal - Parent Teacher Meeting System</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                🏫 PTM Portal
            </a>
            <div class="navbar-nav">
                <?php if (isset($_SESSION['role'])): ?>
                    <a class="nav-link text-white" href="?page=<?= $_SESSION['role'] ?>">Dashboard</a>
                    <a class="nav-link text-white" href="?page=logout">Logout</a>
                <?php else: ?>
                    <a class="nav-link text-white" href="?page=login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Rest of your landing page HTML stays the same -->
    <!-- ... -->
    
</body>
</html>