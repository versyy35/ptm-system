<?php
// app/views/parent/dashboard.php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['parent_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$parent_id = $_SESSION['parent_id'];
$parent_email = $_SESSION['parent_email'];

// Get parent's children from database
$stmt = $conn->prepare("
    SELECT s.id, s.name, s.grade, s.class 
    FROM students s 
    WHERE s.parent_id = ?
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get upcoming meetings
$stmt = $conn->prepare("
    SELECT m.*, t.subject, u.name as teacher_name 
    FROM meetings m
    JOIN teachers t ON m.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE m.parent_id = ? AND m.meeting_date >= CURDATE()
    ORDER BY m.meeting_date, m.start_time
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$upcoming_meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php include '../layouts/header.php'; ?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="text-center mb-3 text-primary">Parent-Teacher Meeting Portal</h2>
            
            <h3>Welcome, Parent!</h3>
            <p><strong>Email:</strong> <?= htmlspecialchars($parent_email) ?></p>

            <!-- My Children Section -->
            <div class="mb-4">
                <h4>My Children</h4>
                <div class="row">
                    <?php foreach ($children as $child): ?>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5><?= htmlspecialchars($child['name']) ?></h5>
                                <p class="mb-1">Grade: <?= htmlspecialchars($child['grade']) ?></p>
                                <p>Class: <?= htmlspecialchars($child['class']) ?></p>
                                <a href="book_meeting.php?student_id=<?= $child['id'] ?>" 
                                   class="btn btn-primary btn-sm">Book Meeting</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Upcoming Meetings -->
            <div class="mb-4">
                <h4>Upcoming Meetings</h4>
                <?php if (empty($upcoming_meetings)): ?>
                    <p class="text-muted">No upcoming meetings scheduled.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($upcoming_meetings as $meeting): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1"><?= htmlspecialchars($meeting['teacher_name']) ?> - <?= htmlspecialchars($meeting['subject']) ?></h5>
                                <small><?= date('M j, Y', strtotime($meeting['meeting_date'])) ?> at <?= $meeting['start_time'] ?></small>
                            </div>
                            <p class="mb-1"><?= htmlspecialchars($meeting['title']) ?></p>
                            <?php if ($meeting['google_meet_link']): ?>
                                <a href="<?= $meeting['google_meet_link'] ?>" target="_blank" class="btn btn-success btn-sm">Join Meeting</a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-4">
                <a href="book_meeting.php" class="btn btn-primary">Book New Meeting</a>
                <a href="my_meetings.php" class="btn btn-info">View All Meetings</a>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>