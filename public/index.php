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
            <div class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['role'])): ?>
                    <a class="nav-link text-white" href="?page=<?= $_SESSION['role'] ?>">Dashboard</a>
                    <a class="nav-link text-white" href="?page=logout">Logout</a>
                <?php else: ?>
                    <a class="nav-link text-white" href="?page=login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-white text-center py-5">
        <div class="container">
            <div class="row align-items-center min-vh-80">
                <div class="col-lg-6 text-lg-start">
                    <h1 class="display-4 fw-bold mb-4">Welcome to PTM Portal</h1>
                    <p class="lead mb-4">
                        Streamline parent-teacher meetings with our easy-to-use booking system. 
                        Schedule appointments, manage availability, and stay connected.
                    </p>
                    <div class="d-grid gap-3 d-md-flex">
                        <a href="?page=login" class="btn btn-light btn-lg px-4">
                            Get Started
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-4">
                            Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-placeholder bg-white bg-opacity-10 rounded-3 p-5 mt-4 mt-lg-0">
                        <div class="text-center">
                            <h2 class="h1 mb-3">📅</h2>
                            <p class="lead">Schedule meetings effortlessly</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Key Features</h2>
                <p class="lead text-muted">Everything you need for successful parent-teacher meetings</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <span class="display-4">👨‍🏫</span>
                            </div>
                            <h5 class="card-title fw-bold">For Teachers</h5>
                            <p class="card-text text-muted">
                                Set your availability, manage appointments, and view your schedule at a glance.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <span class="display-4">👨‍👩‍👧‍👦</span>
                            </div>
                            <h5 class="card-title fw-bold">For Parents</h5>
                            <p class="card-text text-muted">
                                Book meetings with your child's teachers quickly and easily through a simple interface.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <span class="display-4">⚙️</span>
                            </div>
                            <h5 class="card-title fw-bold">For Administrators</h5>
                            <p class="card-text text-muted">
                                Manage meetings, users, and sync data from ISAMS with powerful admin tools.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">How It Works</h2>
                <p class="lead text-muted">Getting started is easy</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 60px; height: 60px; font-size: 24px; font-weight: bold;">
                            1
                        </div>
                        <h5 class="fw-bold">Login</h5>
                        <p class="text-muted">Use your school Google account or MSP credentials</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 60px; height: 60px; font-size: 24px; font-weight: bold;">
                            2
                        </div>
                        <h5 class="fw-bold">Browse</h5>
                        <p class="text-muted">View available teachers and time slots</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 60px; height: 60px; font-size: 24px; font-weight: bold;">
                            3
                        </div>
                        <h5 class="fw-bold">Book</h5>
                        <p class="text-muted">Schedule your meeting in just a few clicks</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 60px; height: 60px; font-size: 24px; font-weight: bold;">
                            4
                        </div>
                        <h5 class="fw-bold">Meet</h5>
                        <p class="text-muted">Receive confirmation and attend your meeting</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5">
        <div class="container text-center">
            <h2 class="display-5 fw-bold mb-4">Ready to Get Started?</h2>
            <p class="lead text-muted mb-4">
                Login now to schedule your first parent-teacher meeting
            </p>
            <a href="?page=login" class="btn btn-primary btn-lg px-5">
                Login Now
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p class="mb-0">© <?= date('Y') ?> <?= APP_NAME ?> - Parent Teacher Meeting Portal</p>
            <p class="small text-muted mb-0">Version <?= APP_VERSION ?></p>
        </div>
    </footer>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>