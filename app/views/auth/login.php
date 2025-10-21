<?php include __DIR__ . '/../layouts/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Login to PTM Portal</h4>
            </div>
            <div class="card-body">
                <!-- Teacher Login -->
                <div class="mb-4">
                    <h5>Teacher Login</h5>
                    <p class="text-muted">Use your school Google account</p>
                    <a href="google_login.php" class="btn btn-danger w-100">
                        <i class="bi bi-google"></i> Sign in with Google (@kl.his.edu.my)
                    </a>
                </div>

                <hr>

                <!-- Admin Login -->
                <div class="mb-3">
                    <h5>Admin Login</h5>
                    <a href="?page=admin-login" class="btn btn-primary w-100">Admin Login</a>
                </div>

                <!-- Parent Note -->
                <div class="alert alert-info">
                    <strong>Parents:</strong> Please login through the main MSP portal.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>