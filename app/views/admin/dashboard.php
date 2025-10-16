<?php include __DIR__ . '/../layouts/header.php'; ?>

<h2 class="mb-4">Admin Dashboard</h2>
<div class="card ptm-card dashboard-card <?php echo $_GET['page']; ?>">
    <div class="card-body text-center">
        <div class="row">
            <div class="col-md-4">
                <div class="card ptm-card admin-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">📅 Manage Meetings</h5>
                        <p class="card-text">Create and schedule PTM meetings</p>
                        <a href="#" class="btn btn-ptm btn-ptm-primary">Get Started</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card ptm-card admin-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">👥 Manage Users</h5>
                        <p class="card-text">Teachers, parents, and students</p>
                        <a href="#" class="btn btn-ptm btn-ptm-primary">Manage</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include __DIR__ . '/../layouts/footer.php'; ?>