<?php include __DIR__ . '/../layouts/header.php'; ?>

<?php
// Get teacher info using session
$userId = $_SESSION['user_id'];

// Get teacher details
$stmt = $pdo->prepare("
    SELECT t.*, u.name, u.email 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.user_id = ?
");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    echo "<div class='alert alert-danger'>Teacher profile not found.</div>";
    include __DIR__ . '/../layouts/footer.php';
    exit;
}

// Get today's meetings (placeholder - add when meetings table is ready)
$todays_meetings = [];

// Get upcoming meetings (placeholder)
$upcoming_meetings = [];

// Get meeting statistics (placeholder)
$stats = [
    'total_meetings' => 0,
    'completed_meetings' => 0,
    'today_meetings' => 0
];
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="text-center mb-3 text-primary">Teacher Dashboard</h2>
            
            <div class="row mb-4">
                <div class="col-md-8">
                    <h3>Welcome, <?= htmlspecialchars($teacher['name']) ?>!</h3>
                    <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($teacher['email']) ?></p>
                    <?php if ($teacher['email']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <a href="?page=teacher-availability" class="btn btn-primary">Set Availability</a>
                        <a href="?page=teacher-meetings" class="btn btn-success">View Meetings</a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body text-center">
                            <h4><?= $stats['total_meetings'] ?></h4>
                            <p class="mb-0">Total Meetings</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <h4><?= $stats['completed_meetings'] ?></h4>
                            <p class="mb-0">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <h4><?= $stats['today_meetings'] ?></h4>
                            <p class="mb-0">Today</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Meetings -->
            <div class="mb-4">
                <h4>Today's Meetings</h4>
                <div class="alert alert-info">
                    No meetings scheduled for today. Meeting functionality coming soon!
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-4">
                <h5>Quick Actions</h5>
                <div class="btn-group">
                    <a href="?page=teacher-availability" class="btn btn-outline-primary">Set Availability</a>
                    <a href="?page=teacher-meetings" class="btn btn-outline-success">All Meetings</a>
                    <a href="?page=teacher-schedule" class="btn btn-outline-info">My Schedule</a>
                    <a href="?page=teacher-profile" class="btn btn-outline-secondary">Profile</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>