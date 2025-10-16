<?php
// app/views/teacher/dashboard.php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_email = $_SESSION['user_email'];

// Get teacher info
$stmt = $conn->prepare("
    SELECT u.name, t.subject, t.grade_level 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get today's meetings
$stmt = $conn->prepare("
    SELECT m.*, u.name as parent_name, s.name as student_name 
    FROM meetings m
    JOIN parents p ON m.parent_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN students s ON m.student_id = s.id
    WHERE m.teacher_id = ? AND m.meeting_date = CURDATE() 
    ORDER BY m.start_time
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$todays_meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get upcoming meetings
$stmt = $conn->prepare("
    SELECT m.*, u.name as parent_name, s.name as student_name 
    FROM meetings m
    JOIN parents p ON m.parent_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN students s ON m.student_id = s.id
    WHERE m.teacher_id = ? AND m.meeting_date > CURDATE() 
    ORDER BY m.meeting_date, m.start_time
    LIMIT 5
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$upcoming_meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get meeting statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_meetings,
        SUM(CASE WHEN meeting_date < CURDATE() THEN 1 ELSE 0 END) as completed_meetings,
        SUM(CASE WHEN meeting_date = CURDATE() THEN 1 ELSE 0 END) as today_meetings
    FROM meetings 
    WHERE teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<?php include __DIR__ . '/../layouts/header.php'; ?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="text-center mb-3 text-primary">Teacher Dashboard</h2>
            
            <div class="row mb-4">
                <div class="col-md-8">
                    <h3>Welcome, <?= htmlspecialchars($teacher['name']) ?>!</h3>
                    <p class="mb-1"><strong>Subject:</strong> <?= htmlspecialchars($teacher['subject']) ?></p>
                    <p class="mb-1"><strong>Grade Level:</strong> <?= htmlspecialchars($teacher['grade_level']) ?></p>
                    <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($teacher_email) ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <a href="availability.php" class="btn btn-primary">Set Availability</a>
                        <a href="meetings.php" class="btn btn-success">View All Meetings</a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body text-center">
                            <h4><?= $stats['total_meetings'] ?? 0 ?></h4>
                            <p class="mb-0">Total Meetings</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <h4><?= $stats['completed_meetings'] ?? 0 ?></h4>
                            <p class="mb-0">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <h4><?= $stats['today_meetings'] ?? 0 ?></h4>
                            <p class="mb-0">Today</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Meetings -->
            <div class="mb-4">
                <h4>Today's Meetings</h4>
                <?php if (empty($todays_meetings)): ?>
                    <div class="alert alert-info">
                        No meetings scheduled for today.
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($todays_meetings as $meeting): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">Meeting with <?= htmlspecialchars($meeting['parent_name']) ?></h5>
                                    <p class="mb-1">
                                        <?php if ($meeting['student_name']): ?>
                                            About: <?= htmlspecialchars($meeting['student_name']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="mb-1"><strong><?= $meeting['start_time'] ?> - <?= $meeting['end_time'] ?></strong></p>
                                </div>
                                <div>
                                    <?php if ($meeting['google_meet_link']): ?>
                                        <a href="<?= $meeting['google_meet_link'] ?>" target="_blank" class="btn btn-success btn-sm">Join Meeting</a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Meet Link</span>
                                    <?php endif; ?>
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
                <?php if (empty($upcoming_meetings)): ?>
                    <div class="alert alert-info">
                        No upcoming meetings scheduled.
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($upcoming_meetings as $meeting): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($meeting['parent_name']) ?></h5>
                                    <p class="mb-1">
                                        <?php if ($meeting['student_name']): ?>
                                            Student: <?= htmlspecialchars($meeting['student_name']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="mb-0"><?= htmlspecialchars($meeting['title']) ?></p>
                                </div>
                                <div class="text-end">
                                    <strong><?= date('M j, Y', strtotime($meeting['meeting_date'])) ?></strong><br>
                                    <span><?= $meeting['start_time'] ?> - <?= $meeting['end_time'] ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="mt-4">
                <h5>Quick Actions</h5>
                <div class="btn-group">
                    <a href="availability.php" class="btn btn-outline-primary">Set Availability</a>
                    <a href="meetings.php" class="btn btn-outline-success">All Meetings</a>
                    <a href="schedule.php" class="btn btn-outline-info">My Schedule</a>
                    <a href="profile.php" class="btn btn-outline-secondary">Profile</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>