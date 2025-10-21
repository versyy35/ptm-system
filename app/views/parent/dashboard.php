<?php include __DIR__ . '/../layouts/header.php'; ?>

<?php
// Get parent info using session
$userId = $_SESSION['user_id'];

// Get parent details
$stmt = $pdo->prepare("
    SELECT p.*, u.name, u.email 
    FROM parents p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id = ?
");
$stmt->execute([$userId]);
$parent = $stmt->fetch();

if (!$parent) {
    echo "<div class='alert alert-danger'>Parent profile not found.</div>";
    include __DIR__ . '/../layouts/footer.php';
    exit;
}

// Get parent's children
$stmt = $pdo->prepare("
    SELECT id, name, grade, class 
    FROM students 
    WHERE parent_id = ?
");
$stmt->execute([$parent['id']]);
$children = $stmt->fetchAll();

// Upcoming meetings placeholder
$upcoming_meetings = [];
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="text-center mb-3 text-primary">Parent Dashboard</h2>
            
            <h3>Welcome, <?= htmlspecialchars($parent['name']) ?>!</h3>
            <p><strong>Email:</strong> <?= htmlspecialchars($parent['email']) ?></p>

            <!-- My Children Section -->
            <div class="mb-4">
                <h4>My Children</h4>
                <?php if (empty($children)): ?>
                    <div class="alert alert-warning">
                        No children found in your profile. Please contact the school administrator.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($children as $child): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5><?= htmlspecialchars($child['name']) ?></h5>
                                    <p class="mb-1">Grade: <?= htmlspecialchars($child['grade']) ?></p>
                                    <p>Class: <?= htmlspecialchars($child['class']) ?></p>
                                    <a href="?page=book-meeting&student_id=<?= $child['id'] ?>" 
                                       class="btn btn-primary btn-sm">Book Meeting</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Meetings -->
            <div class="mb-4">
                <h4>Upcoming Meetings</h4>
                <div class="alert alert-info">
                    No upcoming meetings scheduled. Meeting functionality coming soon!
                </div>
            </div>

            <div class="mt-4">
                <a href="?page=book-meeting" class="btn btn-primary">Book New Meeting</a>
                <a href="?page=my-meetings" class="btn btn-info">View All Meetings</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>