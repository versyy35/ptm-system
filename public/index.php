<?php
// If page parameter exists, use router
if (isset($_GET['page'])) {
    require_once __DIR__ . '/router.php';
    exit;
}

// Otherwise show landing page
session_start();
require_once __DIR__ . '/../app/config/config.php';
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
                <a class="nav-link text-white" href="?page=login">Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-80">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-white mb-4">
                        Parent-Teacher Meetings Made Simple
                    </h1>
                    <p class="lead text-white mb-4">
                        Streamline communication, automate scheduling, and create meaningful connections between parents and teachers with our intuitive platform.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="?page=login" class="btn btn-light btn-lg px-4 fw-bold">
                            Get Started ›
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-4">
                            Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="hero-placeholder bg-white bg-opacity-10 rounded p-5">
                        <span style="font-size: 4rem;">👨‍👩‍👧‍👦</span>
                        <p class="text-white mt-3">Building Stronger School Communities</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h2 class="fw-bold mb-3">Everything You Need in One Platform</h2>
                    <p class="text-muted fs-5">Designed specifically for modern schools and families</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="text-primary mb-3" style="font-size: 3rem;">📅</div>
                            <h5 class="fw-bold">Easy Scheduling</h5>
                            <p class="text-muted">Book parent-teacher meetings in minutes with our intuitive calendar system</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="text-primary mb-3" style="font-size: 3rem;">🔔</div>
                            <h5 class="fw-bold">Smart Reminders</h5>
                            <p class="text-muted">Automated notifications ensure no one misses important meetings</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="text-primary mb-3" style="font-size: 3rem;">💻</div>
                            <h5 class="fw-bold">Virtual Meetings</h5>
                            <p class="text-muted">Integrated video conferencing with Google Meet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Role Selection -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h2 class="fw-bold mb-3">Get Started in Seconds</h2>
                    <p class="text-muted fs-5">Choose your role to access the platform</p>
                </div>
            </div>
            
            <div class="row g-4 justify-content-center">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center">
                        <div class="card-body p-4">
                            <div class="text-success mb-3" style="font-size: 4rem;">👨‍🏫</div>
                            <h4 class="fw-bold">Teachers</h4>
                            <p class="text-muted mb-4">Manage availability and meet with parents</p>
                            <a href="?page=login" class="btn btn-success btn-lg w-100">Teacher Login</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center">
                        <div class="card-body p-4">
                            <div class="text-primary mb-3" style="font-size: 4rem;">👪</div>
                            <h4 class="fw-bold">Parents</h4>
                            <p class="text-muted mb-4">Book meetings and track progress</p>
                            <a href="?page=login" class="btn btn-primary btn-lg w-100 disabled">Parent Login (MSP)</a>
                            <small class="text-muted">Via Main School Portal</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center">
                        <div class="card-body p-4">
                            <div class="text-info mb-3" style="font-size: 4rem;">⚙️</div>
                            <h4 class="fw-bold">Administrators</h4>
                            <p class="text-muted mb-4">Manage the entire system</p>
                            <a href="?page=admin-login" class="btn btn-info btn-lg w-100 text-white">Admin Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 PTM Portal. All rights reserved.</p>
        </div>
    </footer>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>