<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/custom.css" rel="stylesheet">
</head>
<body class="<?php echo isset($_GET['page']) ? $_GET['page'] . '-theme' : ''; ?>">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary ptm-navbar">
        <div class="container">
            <?php 
            // Dynamic home link based on user role
            $homeLink = 'index.php';
            if (isset($_SESSION['role'])) {
                $homeLink = '?page=' . $_SESSION['role'];
            }
            ?>
            <a class="navbar-brand" href="<?php echo $homeLink; ?>">
                <strong>🏫 <?php echo APP_NAME; ?></strong>
            </a>
            
            <?php if (isset($_SESSION['role'])): ?>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    Welcome, <strong><?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?></strong>
                </span>
                <a href="?page=logout" class="btn btn-outline-light btn-sm">
                    🚪 Logout
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <?php if (!isset($_GET['page']) || $_GET['page'] === 'home'): ?>
    <div class="ptm-header">
        <div class="container text-center">
            <h1>Parent-Teacher Meeting Portal</h1>
            <p class="lead">Creating meaningful connections between parents and teachers</p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="container mt-4">