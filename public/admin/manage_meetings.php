<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/middleware/SSOMiddleware.php';

SSOMiddleware::requireAdmin();

// Get admin info from session
$admin_info = [
    'id' => $_SESSION['user_id'] ?? 1,
    'name' => $_SESSION['name'] ?? 'Admin User'
];

// Fetch real teachers from database with their subjects
$teachersStmt = $pdo->query("
    SELECT DISTINCT
        t.id,
        t.name,
        t.email,
        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as subjects,
        COUNT(DISTINCT ts.id) as teaching_sets_count
    FROM teachers t
    INNER JOIN teaching_sets ts ON ts.teacher_id = t.id
    LEFT JOIN subjects s ON s.id = ts.subject_id
    GROUP BY t.id, t.name, t.email
    ORDER BY t.name
");
$teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch teaching sets with their students for the dynamic dropdowns
$teachingSetsStmt = $pdo->query("
    SELECT 
        ts.id as teaching_set_id,
        ts.teacher_id,
        ts.set_name,
        ts.set_code,
        s.name as subject_name,
        GROUP_CONCAT(DISTINCT st.id ORDER BY st.name SEPARATOR ',') as student_ids,
        GROUP_CONCAT(DISTINCT st.name ORDER BY st.name SEPARATOR '|||') as student_names,
        GROUP_CONCAT(DISTINCT st.grade ORDER BY st.name SEPARATOR '|||') as student_grades,
        GROUP_CONCAT(DISTINCT st.class ORDER BY st.name SEPARATOR '|||') as student_classes,
        GROUP_CONCAT(DISTINCT st.parent_id ORDER BY st.name SEPARATOR '|||') as parent_ids
    FROM teaching_sets ts
    LEFT JOIN subjects s ON s.id = ts.subject_id
    LEFT JOIN enrollments e ON e.teaching_set_id = ts.id
    LEFT JOIN students st ON st.id = e.student_id
    GROUP BY ts.id, ts.teacher_id, ts.set_name, ts.set_code, s.name
    ORDER BY ts.teacher_id, s.name, ts.set_name
");
$teachingSets = $teachingSetsStmt->fetchAll(PDO::FETCH_ASSOC);

// Organize teaching sets by teacher
$teachingSetsByTeacher = [];
foreach ($teachingSets as $set) {
    if (!isset($teachingSetsByTeacher[$set['teacher_id']])) {
        $teachingSetsByTeacher[$set['teacher_id']] = [];
    }
    $teachingSetsByTeacher[$set['teacher_id']][] = $set;
}

// Fetch real students with their parent info
$studentsStmt = $pdo->query("
    SELECT 
        s.id,
        s.name,
        s.grade,
        s.class,
        s.isams_pupil_id,
        p.id as parent_id,
        p.name as parent_name,
        p.email as parent_email
    FROM students s
    LEFT JOIN parents p ON p.id = s.parent_id
    ORDER BY s.name
");
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique parents for the parent selection
$parentsStmt = $pdo->query("
    SELECT DISTINCT
        p.id,
        p.name,
        p.email
    FROM parents p
    ORDER BY p.name
");
$parents = $parentsStmt->fetchAll(PDO::FETCH_ASSOC);

// TODO: Fetch real meetings from database when meetings table is created
// For now using empty arrays
$teacher_meetings = [];
$teacher_availability = [];

$selected_teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;
$successMessage = '';
$errorMessage = '';

// Handle meeting creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_meeting'])) {
    try {
        // TODO: Insert into meetings table when created
        // For now, just show success message
        $teacher_id = $_POST['teacher_id'];
        $student_id = $_POST['student_id'];
        $parent_id = $_POST['parent_id'];
        $title = $_POST['title'];
        $meeting_date = $_POST['meeting_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $meeting_link = $_POST['meeting_link'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        // Get teacher, student, and parent names for notification
        $teacherInfo = array_filter($teachers, fn($t) => $t['id'] == $teacher_id);
        $studentInfo = array_filter($students, fn($s) => $s['id'] == $student_id);
        $parentInfo = array_filter($parents, fn($p) => $p['id'] == $parent_id);
        
        $teacherName = !empty($teacherInfo) ? reset($teacherInfo)['name'] : 'Teacher';
        $studentName = !empty($studentInfo) ? reset($studentInfo)['name'] : 'Student';
        $parentName = !empty($parentInfo) ? reset($parentInfo)['name'] : 'Parent';
        
        // TODO: Save to database
        // $stmt = $pdo->prepare("INSERT INTO meetings ...");
        
        // TODO: Send email notifications to teacher and parent
        
        $successMessage = "Meeting created successfully! Title: $title, Teacher: $teacherName, Student: $studentName, Date: $meeting_date, Time: $start_time - $end_time";
    } catch (Exception $e) {
        $errorMessage = "Error creating meeting: " . $e->getMessage();
    }
}

// Prepare calendar events for selected teacher
$calendar_events = [];
if ($selected_teacher_id) {
    // Add booked meetings (blue) - TODO: fetch from database
    if (isset($teacher_meetings[$selected_teacher_id])) {
        foreach ($teacher_meetings[$selected_teacher_id] as $meeting) {
            $color = $meeting['status'] === 'confirmed' ? '#E91E63' : '#FF9800';
            $calendar_events[] = [
                'title' => '📅 ' . $meeting['student'],
                'start' => $meeting['start'],
                'end' => $meeting['end'],
                'backgroundColor' => $color,
                'borderColor' => $color,
                'type' => 'meeting',
                'extendedProps' => $meeting
            ];
        }
    }
    
    // Add availability slots (green) - TODO: fetch from database
    if (isset($teacher_availability[$selected_teacher_id])) {
        foreach ($teacher_availability[$selected_teacher_id] as $slot) {
            $calendar_events[] = [
                'title' => '🟢 Available',
                'start' => $slot['start'],
                'end' => $slot['end'],
                'backgroundColor' => '#4CAF50',
                'borderColor' => '#4CAF50',
                'type' => 'availability'
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Meetings</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Professional Strawberry Matcha Color Palette */
        :root {
            --strawberry-primary: #E91E63;
            --strawberry-secondary: #F8BBD0;
            --strawberry-accent: #AD1457;
            --matcha-primary: #4CAF50;
            --matcha-secondary: #C8E6C9;
            --matcha-accent: #2E7D32;
            --neutral-light: #F5F5F5;
            --neutral-dark: #424242;
            --neutral-white: #FFFFFF;
            --text-primary: #37474F;
            --text-secondary: #78909C;
            --success: #4CAF50;
            --warning: #FF9800;
            --error: #F44336;
        }

        .manage-meetings-container {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--matcha-secondary) 0%, var(--strawberry-secondary) 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .meetings-header {
            background: var(--neutral-white);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 6px solid var(--strawberry-primary);
        }

        .meetings-header h2 {
            color: var(--strawberry-primary);
            font-size: 2.2em;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .meetings-header .text-muted {
            color: var(--text-secondary) !important;
            font-size: 1.1em;
        }

        .create-meeting-btn {
            background: linear-gradient(135deg, var(--strawberry-primary) 0%, var(--strawberry-accent) 100%);
            color: var(--neutral-white);
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1.1em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
            margin-bottom: 25px;
        }

        .create-meeting-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--strawberry-accent) 0%, var(--strawberry-primary) 100%);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 25px;
            margin-top: 20px;
        }

        /* Teacher List Styles */
        .teacher-list {
            background: var(--neutral-white);
            border-radius: 20px;
            padding: 25px;
            max-height: 750px;
            overflow-y: auto;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 2px solid var(--matcha-secondary);
        }

        .teacher-list h3 {
            color: var(--matcha-primary);
            margin-bottom: 20px;
            font-size: 1.3em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            width: 100%; 
            padding: 12px 15px; 
            margin-bottom: 20px; 
            border: 2px solid var(--matcha-secondary); 
            border-radius: 12px; 
            font-size: 1em;
            transition: all 0.3s ease;
            background: var(--neutral-light);
        }

        .search-box:focus {
            outline: none;
            border-color: var(--matcha-primary);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .teacher-item {
            background: var(--neutral-light);
            padding: 18px;
            margin-bottom: 12px;
            border-radius: 15px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .teacher-item:hover {
            border-color: var(--matcha-primary);
            transform: translateX(8px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.2);
        }

        .teacher-item.active {
            background: linear-gradient(135deg, var(--matcha-primary) 0%, var(--matcha-accent) 100%);
            color: var(--neutral-white);
            border-color: var(--strawberry-primary);
        }

        .teacher-item h4 {
            font-size: 1em;
            margin-bottom: 5px;
            font-weight: 600;
            color: inherit;
        }

        .teacher-item p {
            font-size: 0.85em;
            opacity: 0.9;
            margin: 4px 0;
            color: inherit;
        }

        .teacher-email, .teacher-subjects {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Main Content Styles */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .calendar-section {
            background: var(--neutral-white);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 2px solid var(--strawberry-secondary);
        }

        .calendar-section h3 {
            color: var(--strawberry-primary);
            margin-bottom: 20px;
            font-size: 1.5em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--neutral-light);
            border-radius: 12px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .legend-box {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            border: 2px solid rgba(255,255,255,0.8);
        }

        .legend-available { 
            background: var(--matcha-primary); 
        }
        .legend-confirmed { 
            background: var(--strawberry-primary); 
        }
        .legend-pending { 
            background: var(--warning); 
        }

        #calendar { 
            background: var(--neutral-white); 
            padding: 20px; 
            border-radius: 15px;
            border: 2px solid var(--neutral-light);
        }

        /* FullCalendar Customization */
        .fc {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .fc .fc-toolbar-title {
            color: var(--strawberry-primary);
            font-weight: 600;
        }

        .fc .fc-button {
            background: var(--matcha-primary);
            border: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .fc .fc-button:hover {
            background: var(--matcha-accent);
            transform: translateY(-1px);
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active {
            background: var(--strawberry-primary);
        }

        .fc-event {
            border: none;
            border-radius: 8px;
            font-weight: 500;
            padding: 3px 6px;
            cursor: pointer;
        }

        .fc-daygrid-day, .fc-timegrid-slot {
            cursor: pointer;
        }

        .fc-daygrid-day:hover, .fc-timegrid-slot:hover {
            background: var(--strawberry-secondary) !important;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0; 
            top: 0;
            width: 100%; 
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
        }

        .modal.active { 
            display: flex; 
        }

        .modal-content {
            background: var(--neutral-white);
            padding: 35px;
            border-radius: 20px;
            width: 90%;
            max-width: 650px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border: 3px solid var(--strawberry-secondary);
        }

        .modal-content h3 {
            color: var(--strawberry-primary);
            margin-bottom: 25px;
            font-size: 1.6em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95em;
        }

        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--neutral-light);
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: var(--neutral-light);
        }

        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: var(--matcha-primary);
            background: var(--neutral-white);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 90px;
        }

        .form-group input[readonly] {
            background: var(--neutral-light);
            color: var(--text-secondary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .modal-buttons button {
            border: none;
            cursor: pointer;
            padding: 14px 25px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s ease;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--strawberry-primary) 0%, var(--strawberry-accent) 100%);
            color: var(--neutral-white);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        }

        .cancel-btn {
            background: var(--text-secondary);
            color: var(--neutral-white);
        }

        .cancel-btn:hover {
            background: var(--neutral-dark);
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: var(--text-secondary);
            background: var(--neutral-white);
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .empty-state h3 {
            color: var(--matcha-primary);
            margin-bottom: 15px;
            font-size: 1.4em;
        }

        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: var(--matcha-secondary);
            color: var(--matcha-accent);
            border-left: 4px solid var(--matcha-primary);
        }

        .alert-danger {
            background: #FFEBEE;
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .alert-info {
            background: #E3F2FD;
            color: #1565C0;
            border-left: 4px solid #2196F3;
        }

        /* Scrollbar Styling */
        .teacher-list::-webkit-scrollbar {
            width: 8px;
        }

        .teacher-list::-webkit-scrollbar-track {
            background: var(--neutral-light);
            border-radius: 10px;
        }

        .teacher-list::-webkit-scrollbar-thumb {
            background: var(--matcha-primary);
            border-radius: 10px;
        }

        .teacher-list::-webkit-scrollbar-thumb:hover {
            background: var(--matcha-accent);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .teacher-list {
                max-height: 400px;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
            
            .legend {
                flex-direction: column;
                gap: 10px;
            }
            
            .meetings-header {
                padding: 20px;
            }
            
            .meetings-header h2 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="manage-meetings-container">
        <div class="container-fluid">
            <!-- Header -->
            <div class="meetings-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h2>📅 Manage Meetings</h2>
                        <p class="text-muted mb-0">View teacher schedules and create meetings</p>
                    </div>
                    <div class="col-auto">
                        <a href="?page=admin" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Create Meeting Button -->
            <button class="create-meeting-btn" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Create New Meeting
            </button>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Left Sidebar - Teacher List -->
                <div class="teacher-list">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Select Teacher</h3>
                    
                    <!-- Search Box -->
                    <input 
                        type="text" 
                        id="teacherSearch" 
                        placeholder="🔍 Search teachers by name or subjects..."
                        class="search-box"
                    >
                    
                    <?php if (empty($teachers)): ?>
                        <div class="empty-state">
                            <p class="text-muted">No teachers found. Please sync data from ISAMS first.</p>
                        </div>
                    <?php else: ?>
                        <div id="teacherListContainer">
                            <?php foreach ($teachers as $teacher): ?>
                                <a href="?page=manage-meetings&teacher_id=<?php echo $teacher['id']; ?>" 
                                   class="teacher-item <?php echo $selected_teacher_id === (int)$teacher['id'] ? 'active' : ''; ?>"
                                   data-teacher-name="<?php echo strtolower($teacher['name']); ?>"
                                   data-teacher-subjects="<?php echo strtolower($teacher['subjects'] ?: ''); ?>">
                                    <h4><?php echo htmlspecialchars($teacher['name']); ?></h4>
                                    <p class="teacher-email">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?>
                                    </p>
                                    <?php if ($teacher['subjects']): ?>
                                        <p class="teacher-subjects">
                                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($teacher['subjects']); ?>
                                        </p>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Side - Calendar -->
                <div class="main-content">
                    <div id="calendarContainer">
                        <?php if ($selected_teacher_id): ?>
                            <?php 
                            $selected_teacher = array_filter($teachers, function($t) use ($selected_teacher_id) {
                                return (int)$t['id'] === $selected_teacher_id;
                            });
                            $selected_teacher = reset($selected_teacher);
                            ?>
                            <?php if ($selected_teacher): ?>
                                <div class="calendar-section">
                                    <h3><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($selected_teacher['name']); ?> - Schedule</h3>
                                    
                                    <div class="legend">
                                        <div class="legend-item">
                                            <div class="legend-box legend-available"></div>
                                            <span>Available Slots</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-box legend-confirmed"></div>
                                            <span>Confirmed Meetings</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-box legend-pending"></div>
                                            <span>Pending Meetings</span>
                                        </div>
                                    </div>
                                    
                                    <div id="calendar"></div>
                                    
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Note:</strong> Click on any date/time in the calendar to create a meeting for that timeslot.
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3><i class="fas fa-hand-point-left"></i> Select a Teacher</h3>
                                <p>Choose a teacher from the left to view their schedule and booked meetings.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Meeting Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-plus"></i> Create New Meeting</h3>
            <form method="POST" id="createMeetingForm">
                <div class="form-group">
                    <label for="meetingTitle">Meeting Title:</label>
                    <input type="text" name="title" id="meetingTitle" required placeholder="e.g., Progress Review Meeting">
                </div>

                <div class="form-group">
                    <label for="teacherSelect">Select Teacher:</label>
                    <select name="teacher_id" id="teacherSelect" required>
                        <option value="">-- Choose Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['name']); ?> 
                                <?php if ($teacher['subjects']): ?>
                                    - <?php echo htmlspecialchars($teacher['subjects']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="classSelect">Select Class/Teaching Set:</label>
                    <select name="teaching_set_id" id="classSelect" required>
                        <option value="">-- Choose Class --</option>
                        <!-- Dynamic options will be populated by JavaScript -->
                    </select>
                </div>

                <div class="form-group">
                    <label for="studentSelect">Select Student:</label>
                    <select name="student_id" id="studentSelect" required>
                        <option value="">-- Choose Student --</option>
                        <!-- Dynamic options will be populated by JavaScript -->
                    </select>
                </div>

                <div class="form-group">
                    <label>Parent (Auto-filled):</label>
                    <input type="text" id="parentDisplay" readonly>
                    <input type="hidden" name="parent_id" id="parentId">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="meetingDate">Meeting Date:</label>
                        <input type="date" name="meeting_date" id="meetingDate" required>
                    </div>
                    <div class="form-group">
                        <label for="meetingDuration">Duration:</label>
                        <select name="duration" id="meetingDuration">
                            <option value="30">30 minutes</option>
                            <option value="45">45 minutes</option>
                            <option value="60" selected>1 hour</option>
                            <option value="90">1.5 hours</option>
                            <option value="120">2 hours</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="startTime">Start Time:</label>
                        <input type="time" name="start_time" id="startTime" required>
                    </div>
                    <div class="form-group">
                        <label for="endTime">End Time:</label>
                        <input type="time" name="end_time" id="endTime" required readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label for="meetingLink">Meeting Link (Optional):</label>
                    <input type="url" name="meeting_link" id="meetingLink" placeholder="https://meet.google.com/xxx-xxxx-xxx">
                </div>

                <div class="form-group">
                    <label for="meetingNotes">Notes (Optional):</label>
                    <textarea name="notes" id="meetingNotes" placeholder="Additional information for the meeting..."></textarea>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeCreateModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="create_meeting" class="submit-btn">
                        <i class="fas fa-calendar-plus"></i> Create Meeting
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Meeting Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-calendar-alt"></i> Meeting Details</h3>
            <div id="meetingDetailsContent"></div>
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeDetailsModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <!-- FullCalendar -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

    <script>
        // Pass PHP data to JavaScript
        const calendarEvents = <?php echo json_encode($calendar_events); ?>;
        const parents = <?php echo json_encode($parents); ?>;
        const teachingSetsByTeacher = <?php echo json_encode($teachingSetsByTeacher); ?>;
        const students = <?php echo json_encode($students); ?>;
        const currentSelectedTeacherId = <?php echo $selected_teacher_id ?: 'null'; ?>;
        
        let calendar;
        let selectedCalendarDate = null;

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCalendar();
            bindEvents();
            setMinDate();
            restoreScrollPosition();
        });

        function initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
            if (!calendarEl) return;

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                slotMinTime: '08:00:00',
                slotMaxTime: '18:00:00',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: calendarEvents,
                eventClick: function(info) {
                    if (info.event.extendedProps.type === 'meeting') {
                        showMeetingDetails(info.event);
                    }
                },
                dateClick: function(info) {
                    selectedCalendarDate = info.date;
                    openCreateModalWithDate(info.date, currentSelectedTeacherId);
                },
                selectable: true,
                select: function(info) {
                    selectedCalendarDate = info.start;
                    openCreateModalWithDateRange(info.start, info.end, currentSelectedTeacherId);
                },
                eventColor: '#E91E63',
                eventTextColor: '#FFFFFF'
            });
            calendar.render();
        }

        function bindEvents() {
    // Teacher search functionality
    const teacherSearch = document.getElementById('teacherSearch');
    if (teacherSearch) {
        teacherSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const teacherItems = document.querySelectorAll('.teacher-item');
            
            teacherItems.forEach(item => {
                const teacherName = item.getAttribute('data-teacher-name') || '';
                const teacherSubjects = item.getAttribute('data-teacher-subjects') || '';
                
                if (teacherName.includes(searchTerm) || teacherSubjects.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Save scroll position and selected teacher ID before navigating
    const teacherItems = document.querySelectorAll('.teacher-item');
    teacherItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const teacherList = document.querySelector('.teacher-list');
            if (teacherList) {
                sessionStorage.setItem('teacherListScrollPos', teacherList.scrollTop);
                // Mark that we're staying on the same page
                sessionStorage.setItem('stayOnMeetingsPage', 'true');
            }
        });
    });

    // Form event listeners
    const teacherSelect = document.getElementById('teacherSelect');
    if (teacherSelect) {
        teacherSelect.addEventListener('change', updateClassesAndStudents);
    }

    const classSelect = document.getElementById('classSelect');
    if (classSelect) {
        classSelect.addEventListener('change', updateStudents);
    }

    const studentSelect = document.getElementById('studentSelect');
    if (studentSelect) {
        studentSelect.addEventListener('change', updateParentInfo);
    }

    const startTime = document.getElementById('startTime');
    const duration = document.getElementById('meetingDuration');
    if (startTime && duration) {
        startTime.addEventListener('change', updateEndTime);
        duration.addEventListener('change', updateEndTime);
    }

    // Modal close events
    setupModalCloseEvents();
}

function restoreScrollPosition() {
    const teacherList = document.querySelector('.teacher-list');
    const savedScrollPos = sessionStorage.getItem('teacherListScrollPos');
    const stayOnPage = sessionStorage.getItem('stayOnMeetingsPage');
    
    // Only restore scroll position if we're staying on the same page (clicking different teachers)
    if (teacherList && savedScrollPos && stayOnPage === 'true' && currentSelectedTeacherId) {
        teacherList.scrollTop = parseInt(savedScrollPos);
    } else {
        // Clear scroll position if coming fresh to the page or no teacher selected
        sessionStorage.removeItem('teacherListScrollPos');
    }
    
    // Clear the flag after restoring
    sessionStorage.removeItem('stayOnMeetingsPage');
}
    
        function openCreateModalWithDate(date, teacherId) {
            const formattedDate = date.toISOString().split('T')[0];
            const formattedTime = date.toTimeString().split(':').slice(0, 2).join(':');
            
            document.getElementById('meetingDate').value = formattedDate;
            document.getElementById('startTime').value = formattedTime;
            
            if (teacherId) {
                document.getElementById('teacherSelect').value = teacherId;
                updateClassesAndStudents();
            }
            
            updateEndTime();
            openCreateModal();
        }

        function openCreateModalWithDateRange(start, end, teacherId) {
            const formattedDate = start.toISOString().split('T')[0];
            const startTime = start.toTimeString().split(':').slice(0, 2).join(':');
            const endTime = end.toTimeString().split(':').slice(0, 2).join(':');
            
            const durationMs = end - start;
            const durationMinutes = Math.round(durationMs / (1000 * 60));
            
            document.getElementById('meetingDate').value = formattedDate;
            document.getElementById('startTime').value = startTime;
            document.getElementById('endTime').value = endTime;
            
            if (teacherId) {
                document.getElementById('teacherSelect').value = teacherId;
                updateClassesAndStudents();
            }
            
            const durationSelect = document.getElementById('meetingDuration');
            const closest = Array.from(durationSelect.options).reduce((prev, curr) => {
                return (Math.abs(curr.value - durationMinutes) < Math.abs(prev.value - durationMinutes) ? curr : prev);
            });
            durationSelect.value = closest.value;
            
            openCreateModal();
        }

        function updateClassesAndStudents() {
            const teacherSelect = document.getElementById('teacherSelect');
            const classSelect = document.getElementById('classSelect');
            const studentSelect = document.getElementById('studentSelect');
            const selectedTeacherId = teacherSelect.value;
            
            // Clear existing options
            classSelect.innerHTML = '<option value="">-- Choose Class --</option>';
            studentSelect.innerHTML = '<option value="">-- Choose Student --</option>';
            document.getElementById('parentDisplay').value = '';
            document.getElementById('parentId').value = '';
            
            if (selectedTeacherId && teachingSetsByTeacher[selectedTeacherId]) {
                teachingSetsByTeacher[selectedTeacherId].forEach(set => {
                    const option = document.createElement('option');
                    option.value = set.teaching_set_id;
                    option.textContent = `${set.set_name} (${set.subject_name})`;
                    option.setAttribute('data-student-ids', set.student_ids || '');
                    option.setAttribute('data-student-names', set.student_names || '');
                    classSelect.appendChild(option);
                });
            }
        }

        function updateStudents() {
            const classSelect = document.getElementById('classSelect');
            const studentSelect = document.getElementById('studentSelect');
            const selectedOption = classSelect.options[classSelect.selectedIndex];
            
            studentSelect.innerHTML = '<option value="">-- Choose Student --</option>';
            document.getElementById('parentDisplay').value = '';
            document.getElementById('parentId').value = '';
            
            if (selectedOption.value) {
                const studentIds = selectedOption.getAttribute('data-student-ids')?.split(',') || [];
                const studentNames = selectedOption.getAttribute('data-student-names')?.split('|||') || [];
                
                studentIds.forEach((id, index) => {
                    if (id && studentNames[index]) {
                        const option = document.createElement('option');
                        option.value = id;
                        option.textContent = studentNames[index];
                        
                        const student = students.find(s => s.id == id);
                        if (student) {
                            option.setAttribute('data-parent', student.parent_id || '');
                            option.setAttribute('data-parent-name', student.parent_name || '');
                            option.setAttribute('data-parent-email', student.parent_email || '');
                        }
                        
                        studentSelect.appendChild(option);
                    }
                });
            }
        }

        function updateParentInfo() {
            const studentSelect = document.getElementById('studentSelect');
            const selectedOption = studentSelect.options[studentSelect.selectedIndex];
            const parentId = selectedOption.getAttribute('data-parent');
            const parentName = selectedOption.getAttribute('data-parent-name');
            const parentEmail = selectedOption.getAttribute('data-parent-email');
            
            if (parentId && parentName) {
                document.getElementById('parentDisplay').value = parentName + (parentEmail ? ' (' + parentEmail + ')' : '');
                document.getElementById('parentId').value = parentId;
            } else {
                document.getElementById('parentDisplay').value = 'No parent assigned';
                document.getElementById('parentId').value = '';
            }
        }

        function updateEndTime() {
            const startTime = document.getElementById('startTime').value;
            const duration = parseInt(document.getElementById('meetingDuration').value);
            
            if (startTime && duration) {
                const [hours, minutes] = startTime.split(':').map(Number);
                const startDate = new Date();
                startDate.setHours(hours, minutes, 0, 0);
                
                const endDate = new Date(startDate.getTime() + duration * 60000);
                const endHours = endDate.getHours().toString().padStart(2, '0');
                const endMinutes = endDate.getMinutes().toString().padStart(2, '0');
                
                document.getElementById('endTime').value = `${endHours}:${endMinutes}`;
            }
        }

        function showMeetingDetails(event) {
            const props = event.extendedProps;
            const startTime = new Date(event.start).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
            const endTime = new Date(event.end).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
            const date = new Date(event.start).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'});
            
            const html = `
                <div style="background: #F8F9FA; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #E91E63;">
                    <p style="margin: 10px 0;"><strong style="color: #E91E63;">Date:</strong> ${date}</p>
                    <p style="margin: 10px 0;"><strong style="color: #E91E63;">Time:</strong> ${startTime} - ${endTime}</p>
                    <p style="margin: 10px 0;"><strong style="color: #E91E63;">Student:</strong> ${props.student}</p>
                    <p style="margin: 10px 0;"><strong style="color: #E91E63;">Parent:</strong> ${props.parent}</p>
                    <p style="margin: 10px 0;"><strong style="color: #E91E63;">Status:</strong> 
                        <span style="background: ${props.status === 'confirmed' ? '#C8E6C9' : '#FFE0B2'}; 
                              color: ${props.status === 'confirmed' ? '#2E7D32' : '#EF6C00'}; 
                              padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600;">
                            ${props.status.toUpperCase()}
                        </span>
                    </p>
                </div>
            `;
            
            document.getElementById('meetingDetailsContent').innerHTML = html;
            openDetailsModal();
        }

        function openCreateModal() {
            // If we have a current selected teacher and no calendar date was clicked, pre-fill the teacher
            if (currentSelectedTeacherId && !selectedCalendarDate) {
                document.getElementById('teacherSelect').value = currentSelectedTeacherId;
                updateClassesAndStudents();
            }
            document.getElementById('createModal').classList.add('active');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
            // Reset form but keep teacher selection if we're in a teacher context
            const teacherSelect = document.getElementById('teacherSelect');
            
            if (!currentSelectedTeacherId) {
                teacherSelect.value = '';
            } else {
                teacherSelect.value = currentSelectedTeacherId;
                updateClassesAndStudents();
            }
            
            // Reset other fields
            document.getElementById('meetingTitle').value = '';
            document.getElementById('classSelect').innerHTML = '<option value="">-- Choose Class --</option>';
            document.getElementById('studentSelect').innerHTML = '<option value="">-- Choose Student --</option>';
            document.getElementById('parentDisplay').value = '';
            document.getElementById('parentId').value = '';
            document.getElementById('meetingDate').value = '';
            document.getElementById('startTime').value = '';
            document.getElementById('endTime').value = '';
            document.getElementById('meetingDuration').value = '60';
            document.getElementById('meetingLink').value = '';
            document.getElementById('meetingNotes').value = '';
            
            selectedCalendarDate = null;
        }

        function openDetailsModal() {
            document.getElementById('detailsModal').classList.add('active');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.remove('active');
        }

        function setupModalCloseEvents() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('active');
                    }
                });
            });

            // Add escape key support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal').forEach(modal => {
                        modal.classList.remove('active');
                    });
                }
            });
        }

        function setMinDate() {
            const today = new Date().toISOString().split('T')[0];
            const meetingDateInput = document.getElementById('meetingDate');
            if (meetingDateInput) {
                meetingDateInput.min = today;
            }
        }
    </script>
</body>
</html>