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
            <div class="col-md-4">
                <div class="card ptm-card admin-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">🔄 Sync ISAMS Data</h5>
                        <p class="card-text">Import students and parents from ISAMS</p>
                        <a href="?page=sync-isams" class="btn btn-ptm btn-ptm-primary">Run Sync</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card ptm-card admin-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">🔄 Sync Subjects</h5>
                        <p class="card-text">Import subjects and teachers' data from ISAMS</p>
                        <a href="?page=sync-subjects" class="btn btn-ptm btn-ptm-primary">Run Sync</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card ptm-card admin-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">👁️ View Imported Subjects</h5>
                        <p class="card-text">View all imported subjects pulled from ISAMS API</p>
                        <a href="?page=view-subjects" class="btn btn-ptm btn-ptm-primary">View Subjects</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card ptm-card admin-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Check API Diagnostics</h5>
                        <p class="card-text">Diagnose API thingy</p>
                        <a href="?page=api-diagnostics" class="btn btn-ptm btn-ptm-primary">APi diagnostics</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card ptm-card admin-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Explore API XML</h5>
                        <p class="card-text">Diagnose API thingy</p>
                        <a href="?page=xml_explore" class="btn btn-ptm btn-ptm-primary">APi diagnostics</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card ptm-card admin-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Fix Teachers' Emails</h5>
                        <p class="card-text">Diagnose API thingy</p>
                        <a href="?page=fix-teacher-email" class="btn btn-ptm btn-ptm-primary">APi diagnostics</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include __DIR__ . '/../layouts/footer.php'; ?>