<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/middleware/SSOMiddleware.php';

SSOMiddleware::requireAdmin();

$successMessage = '';
$errorMessage = '';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            // Insert teacher
            $stmt = $pdo->prepare("INSERT INTO teachers (name, email, isams_teacher_id) VALUES (?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['email'],
                !empty($_POST['isams_teacher_id']) ? intval($_POST['isams_teacher_id']) : null
            ]);
            
            $teacherId = $pdo->lastInsertId();
            $teacherName = $_POST['name'];
            
            // Create teaching sets for selected subjects
            if (isset($_POST['subject_ids']) && is_array($_POST['subject_ids'])) {
                $subjectIds = array_map('intval', $_POST['subject_ids']);
                
                foreach ($subjectIds as $subjectId) {
                    // Get subject name
                    $subjectStmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
                    $subjectStmt->execute([$subjectId]);
                    $subjectName = $subjectStmt->fetchColumn();
                    
                    if ($subjectName) {
                        // Create teaching set
                        $setName = $subjectName . " - " . $teacherName;
                        $setCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $subjectName), 0, 3)) . "_" . $teacherId . "_" . time();
                        
                        $insertStmt = $pdo->prepare("
                            INSERT INTO teaching_sets (teacher_id, subject_id, subject_name, teacher_name, set_name, set_code)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $insertStmt->execute([$teacherId, $subjectId, $subjectName, $teacherName, $setName, $setCode]);
                    }
                }
            }
            
            $successMessage = "Teacher created successfully!";
            
        } elseif ($action === 'update') {
            // Update basic teacher info
            $stmt = $pdo->prepare("UPDATE teachers SET name=?, email=?, isams_teacher_id=? WHERE id=?");
            $stmt->execute([
                $_POST['name'],
                $_POST['email'],
                !empty($_POST['isams_teacher_id']) ? intval($_POST['isams_teacher_id']) : null,
                $_POST['id']
            ]);
            
            // Handle subject updates if provided
            if (isset($_POST['subject_ids']) && is_array($_POST['subject_ids'])) {
                $teacherId = $_POST['id'];
                $subjectIds = array_map('intval', $_POST['subject_ids']);
                
                // Get teacher name
                $teacherStmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
                $teacherStmt->execute([$teacherId]);
                $teacherName = $teacherStmt->fetchColumn();
                
                // Get existing teaching sets for this teacher
                $existingSetsStmt = $pdo->prepare("
                    SELECT id, subject_id 
                    FROM teaching_sets 
                    WHERE teacher_id = ?
                ");
                $existingSetsStmt->execute([$teacherId]);
                $existingSets = $existingSetsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $existingSubjectIds = array_column($existingSets, 'subject_id');
                
                // Add new subjects as teaching sets
                foreach ($subjectIds as $subjectId) {
                    if (!in_array($subjectId, $existingSubjectIds)) {
                        // Get subject name
                        $subjectStmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
                        $subjectStmt->execute([$subjectId]);
                        $subjectName = $subjectStmt->fetchColumn();
                        
                        if ($subjectName) {
                            // Create a new teaching set with proper naming
                            $setName = $subjectName . " - " . $teacherName;
                            $setCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $subjectName), 0, 3)) . "_" . $teacherId . "_" . time();
                            
                            $insertStmt = $pdo->prepare("
                                INSERT INTO teaching_sets (teacher_id, subject_id, subject_name, teacher_name, set_name, set_code)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $insertStmt->execute([$teacherId, $subjectId, $subjectName, $teacherName, $setName, $setCode]);
                        }
                    }
                }
                
                // Remove subjects that were unchecked (only if they have no enrolled students)
                foreach ($existingSubjectIds as $existingSubjectId) {
                    if (!in_array($existingSubjectId, $subjectIds)) {
                        // Check if teaching sets have students
                        $checkStudentsStmt = $pdo->prepare("
                            SELECT COUNT(*) 
                            FROM enrollments e
                            INNER JOIN teaching_sets ts ON ts.id = e.teaching_set_id
                            WHERE ts.teacher_id = ? AND ts.subject_id = ?
                        ");
                        $checkStudentsStmt->execute([$teacherId, $existingSubjectId]);
                        
                        if ($checkStudentsStmt->fetchColumn() == 0) {
                            // No students, safe to delete
                            $deleteStmt = $pdo->prepare("
                                DELETE FROM teaching_sets 
                                WHERE teacher_id = ? AND subject_id = ?
                            ");
                            $deleteStmt->execute([$teacherId, $existingSubjectId]);
                        } else {
                            $errorMessage = "Cannot remove some subjects with enrolled students. Please remove students first.";
                        }
                    }
                }
            } else {
                // No subjects selected - remove all teaching sets without students
                $teacherId = $_POST['id'];
                $checkStmt = $pdo->prepare("
                    SELECT ts.id 
                    FROM teaching_sets ts
                    LEFT JOIN enrollments e ON e.teaching_set_id = ts.id
                    WHERE ts.teacher_id = ?
                    GROUP BY ts.id
                    HAVING COUNT(e.id) = 0
                ");
                $checkStmt->execute([$teacherId]);
                $emptySetIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($emptySetIds)) {
                    $placeholders = str_repeat('?,', count($emptySetIds) - 1) . '?';
                    $deleteStmt = $pdo->prepare("DELETE FROM teaching_sets WHERE id IN ($placeholders)");
                    $deleteStmt->execute($emptySetIds);
                }
            }
            
            if (empty($errorMessage)) {
                $successMessage = "Teacher updated successfully!";
            }
            
        } elseif ($action === 'delete') {
            // Check if teacher has teaching sets
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM teaching_sets WHERE teacher_id = ?");
            $checkStmt->execute([$_POST['id']]);
            if ($checkStmt->fetchColumn() > 0) {
                $errorMessage = "Cannot delete teacher with assigned teaching sets. Please reassign or delete teaching sets first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM teachers WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $successMessage = "Teacher deleted successfully!";
            }
        }
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Fetch all teachers (excluding those with 0 teaching sets)
$teachersStmt = $pdo->query("
    SELECT 
        t.*,
        COUNT(DISTINCT ts.id) as teaching_sets_count,
        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as subjects,
        GROUP_CONCAT(DISTINCT s.id ORDER BY s.name SEPARATOR ',') as subject_ids
    FROM teachers t
    LEFT JOIN teaching_sets ts ON ts.teacher_id = t.id
    LEFT JOIN subjects s ON s.id = ts.subject_id
    GROUP BY t.id
    HAVING teaching_sets_count > 0
    ORDER BY t.name
");
$teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all subjects for the subject selection
$subjectsStmt = $pdo->query("SELECT id, name FROM subjects ORDER BY name");
$allSubjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers Management</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
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
            --error: #F44336;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--matcha-secondary) 0%, var(--strawberry-secondary) 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .management-header {
            background: var(--neutral-white);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 6px solid var(--strawberry-primary);
        }

        .management-header h2 {
            color: var(--strawberry-primary);
            font-size: 2.2em;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .nav-links a {
            padding: 12px 20px;
            background: var(--neutral-white);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .nav-links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .nav-links a.active {
            background: linear-gradient(135deg, var(--strawberry-primary) 0%, var(--strawberry-accent) 100%);
            color: var(--neutral-white);
        }

        .content-card {
            background: var(--neutral-white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 2px solid var(--matcha-secondary);
        }

        .content-card h3 {
            color: var(--matcha-primary);
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            padding: 12px 15px;
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

        .create-btn {
            background: linear-gradient(135deg, var(--strawberry-primary) 0%, var(--strawberry-accent) 100%);
            color: var(--neutral-white);
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
        }

        .create-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .data-table thead th {
            background: linear-gradient(135deg, var(--matcha-primary) 0%, var(--matcha-accent) 100%);
            color: var(--neutral-white);
            padding: 15px;
            font-weight: 600;
            text-align: left;
            font-size: 0.95em;
            text-transform: uppercase;
        }

        .data-table thead th:first-child {
            border-radius: 12px 0 0 12px;
        }

        .data-table thead th:last-child {
            border-radius: 0 12px 12px 0;
        }

        .data-table tbody tr {
            background: var(--neutral-light);
            transition: all 0.3s ease;
        }

        .data-table tbody tr:hover {
            background: var(--strawberry-secondary);
            transform: translateX(5px);
        }

        .data-table tbody td {
            padding: 18px 15px;
            color: var(--text-primary);
            border-top: 2px solid var(--neutral-white);
            border-bottom: 2px solid var(--neutral-white);
        }

        .data-table tbody td:first-child {
            border-left: 2px solid var(--neutral-white);
            border-radius: 12px 0 0 12px;
        }

        .data-table tbody td:last-child {
            border-right: 2px solid var(--neutral-white);
            border-radius: 0 12px 12px 0;
        }

        .btn-edit {
            background: var(--matcha-primary);
            color: var(--neutral-white);
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85em;
            transition: all 0.3s ease;
            margin-right: 5px;
        }

        .btn-edit:hover {
            background: var(--matcha-accent);
            transform: translateY(-2px);
        }

        .btn-delete {
            background: var(--error);
            color: var(--neutral-white);
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85em;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            background: #C62828;
            transform: translateY(-2px);
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-info {
            background: #E3F2FD;
            color: #1565C0;
        }

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

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--neutral-light);
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: var(--neutral-light);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--matcha-primary);
            background: var(--neutral-white);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            padding: 15px;
            background: var(--neutral-light);
            border-radius: 12px;
            max-height: 300px;
            overflow-y: auto;
        }

        .subject-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: var(--neutral-white);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .subject-checkbox:hover {
            background: var(--matcha-secondary);
        }

        .subject-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .subject-checkbox label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9em;
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
        }

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

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .data-table {
                display: block;
                overflow-x: auto;
            }
            
            .subjects-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="management-header">
            <div class="row align-items-center">
                <div class="col">
                    <h2><i class="fas fa-chalkboard-teacher"></i> Teachers Management</h2>
                    <p class="text-muted mb-0">Manage teacher records and subjects</p>
                </div>
                <div class="col-auto">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="nav-links">
            <a href="?page=student-management"><i class="fas fa-user-graduate"></i> Students</a>
            <a href="?page=parent-management"><i class="fas fa-users"></i> Parents</a>
            <a href="?page=teacher-management" class="active"><i class="fas fa-chalkboard-teacher"></i> Teachers</a>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <h3><i class="fas fa-chalkboard-teacher"></i> Teachers List</h3>
            
            <div class="action-bar">
                <input type="text" id="searchBox" class="search-box" placeholder="🔍 Search teachers...">
                <button class="create-btn" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Add Teacher
                </button>
            </div>

            <?php if (empty($teachers)): ?>
                <div class="empty-state">
                    <h4>No Teachers Found</h4>
                    <p>Click "Add Teacher" to create your first teacher record.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subjects</th>
                                <th>Teaching Sets</th>
                                <th>ISAMS ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php foreach ($teachers as $teacher): ?>
                            <tr class="data-row">
                                <td><strong><?php echo htmlspecialchars($teacher['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['subjects'] ?: 'No subjects'); ?></td>
                                <td><span class="badge badge-info"><?php echo $teacher['teaching_sets_count']; ?> set(s)</span></td>
                                <td><?php echo htmlspecialchars($teacher['isams_teacher_id'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn-edit" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($teacher), ENT_QUOTES, 'UTF-8'); ?>)'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn-delete" onclick="confirmDelete(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars(addslashes($teacher['name'])); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Modal -->
    <div id="teacherModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle"><i class="fas fa-chalkboard-teacher"></i> Add Teacher</h3>
            <form method="POST" id="teacherForm">
                <input type="hidden" name="action" id="action" value="create">
                <input type="hidden" name="id" id="teacherId">

                <div class="form-group">
                    <label for="teacherName">Full Name:</label>
                    <input type="text" name="name" id="teacherName" required>
                </div>

                <div class="form-group">
                    <label for="teacherEmail">Email:</label>
                    <input type="email" name="email" id="teacherEmail" required>
                </div>

                <div class="form-group">
                    <label for="teacherIsamsId">ISAMS Teacher ID:</label>
                    <input type="number" name="isams_teacher_id" id="teacherIsamsId" placeholder="Optional">
                </div>

                <div class="form-group">
                    <label>Subjects to Teach:</label>
                    <div class="subjects-grid" id="createSubjectsGrid">
                        <?php foreach ($allSubjects as $subject): ?>
                            <div class="subject-checkbox">
                                <input type="checkbox" 
                                       name="subject_ids[]" 
                                       value="<?php echo $subject['id']; ?>" 
                                       id="create_subject_<?php echo $subject['id']; ?>">
                                <label for="create_subject_<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="alert" style="background: #E3F2FD; color: #1565C0; border-left: 4px solid #2196F3; margin-top: 15px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Tip:</strong> Select subjects to automatically create teaching sets for this teacher.
                </div>

                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Save Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal with Subjects -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-edit"></i> Edit Teacher & Subjects</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editTeacherId">

                <div class="form-group">
                    <label for="editTeacherName">Full Name:</label>
                    <input type="text" name="name" id="editTeacherName" required>
                </div>

                <div class="form-group">
                    <label for="editTeacherEmail">Email:</label>
                    <input type="email" name="email" id="editTeacherEmail" required>
                </div>

                <div class="form-group">
                    <label for="editTeacherIsamsId">ISAMS Teacher ID:</label>
                    <input type="number" name="isams_teacher_id" id="editTeacherIsamsId" placeholder="Optional">
                </div>

                <div class="form-group">
                    <label>Subjects Taught:</label>
                    <div class="subjects-grid" id="subjectsGrid">
                        <?php foreach ($allSubjects as $subject): ?>
                            <div class="subject-checkbox">
                                <input type="checkbox" 
                                       name="subject_ids[]" 
                                       value="<?php echo $subject['id']; ?>" 
                                       id="edit_subject_<?php echo $subject['id']; ?>">
                                <label for="edit_subject_<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="alert" style="background: #FFF3E0; color: #E65100; border-left: 4px solid #FF9800; margin-top: 15px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> Selecting subjects automatically creates teaching sets. Deselecting only works if no students are enrolled.
                </div>

                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeModal('editModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Update Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 style="color: var(--error);"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
            <p style="font-size: 1.1em; margin: 20px 0;">
                Are you sure you want to delete <strong id="deleteItemName"></strong>?
            </p>
            <p style="color: var(--text-secondary); margin-bottom: 25px;">This action cannot be undone.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                
                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeModal('deleteModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-delete" style="flex: 1;">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Search functionality
        document.getElementById('searchBox').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.data-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Open create modal
        function openCreateModal() {
            document.getElementById('teacherForm').reset();
            document.getElementById('action').value = 'create';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-chalkboard-teacher"></i> Add Teacher';
            document.getElementById('teacherModal').classList.add('active');
        }

        // Open edit modal with data
        function openEditModal(data) {
            console.log('Opening edit modal with data:', data);
            
            // Fill basic info
            document.getElementById('editTeacherId').value = data.id;
            document.getElementById('editTeacherName').value = data.name;
            document.getElementById('editTeacherEmail').value = data.email;
            document.getElementById('editTeacherIsamsId').value = data.isams_teacher_id || '';
            
            // Uncheck all subject checkboxes first
            document.querySelectorAll('#subjectsGrid input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            
            // Check the subjects this teacher currently teaches
            if (data.subject_ids) {
                const subjectIds = data.subject_ids.toString().split(',');
                console.log('Subject IDs:', subjectIds);
                
                subjectIds.forEach(id => {
                    const checkbox = document.getElementById('edit_subject_' + id.trim());
                    if (checkbox) {
                        checkbox.checked = true;
                        console.log('Checked subject:', id);
                    }
                });
            }
            
            // Show the edit modal
            document.getElementById('editModal').classList.add('active');
        }

        // Confirm delete
        function confirmDelete(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteItemName').textContent = name;
            document.getElementById('deleteModal').classList.add('active');
        }

        // Close modal
        function closeModal(modalId = 'teacherModal') {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>