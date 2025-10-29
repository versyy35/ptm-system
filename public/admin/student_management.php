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
            $stmt = $pdo->prepare("INSERT INTO students (name, grade, class, isams_pupil_id, isams_parent_id, parent_id, family_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['grade'],
                $_POST['class'],
                !empty($_POST['isams_pupil_id']) ? intval($_POST['isams_pupil_id']) : null,
                !empty($_POST['isams_parent_id']) ? intval($_POST['isams_parent_id']) : null,
                $_POST['parent_id'] ?: null,
                $_POST['family_id'] ?: null
            ]);
            $successMessage = "Student created successfully!";
        } elseif ($action === 'update') {
            $stmt = $pdo->prepare("UPDATE students SET name=?, grade=?, class=?, isams_pupil_id=?, isams_parent_id=?, parent_id=?, family_id=? WHERE id=?");
            $stmt->execute([
                $_POST['name'],
                $_POST['grade'],
                $_POST['class'],
                !empty($_POST['isams_pupil_id']) ? intval($_POST['isams_pupil_id']) : null,
                !empty($_POST['isams_parent_id']) ? intval($_POST['isams_parent_id']) : null,
                $_POST['parent_id'] ?: null,
                $_POST['family_id'] ?: null,
                $_POST['id']
            ]);
            $successMessage = "Student updated successfully!";
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM students WHERE id=?");
            $stmt->execute([$_POST['id']]);
            $successMessage = "Student deleted successfully!";
        }
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Fetch all students
$studentsStmt = $pdo->query("
    SELECT 
        s.*,
        p.name as parent_name,
        p.email as parent_email
    FROM students s
    LEFT JOIN parents p ON p.id = s.parent_id
    ORDER BY s.name
");
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all parents for dropdown
$parentsStmt = $pdo->query("SELECT * FROM parents ORDER BY name");
$parents = $parentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management</title>
    
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

        .badge-success {
            background: var(--matcha-secondary);
            color: var(--matcha-accent);
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--neutral-light);
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: var(--neutral-light);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--matcha-primary);
            background: var(--neutral-white);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
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
            .form-row {
                grid-template-columns: 1fr;
            }
            .data-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="management-header">
            <div class="row align-items-center">
                <div class="col">
                    <h2><i class="fas fa-user-graduate"></i> Students Management</h2>
                    <p class="text-muted mb-0">Manage student records</p>
                </div>
                <div class="col-auto">
                    <a href="?page=admin" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="nav-links">
            <a href="?page=student-management" class="active"><i class="fas fa-user-graduate"></i> Students</a>
            <a href="?page=parent-management"><i class="fas fa-users"></i> Parents</a>
            <a href="?page=teacher-management"><i class="fas fa-chalkboard-teacher"></i> Teachers</a>
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
            <h3><i class="fas fa-user-graduate"></i> Students List</h3>
            
            <div class="action-bar">
                <input type="text" id="searchBox" class="search-box" placeholder="🔍 Search students...">
                <button class="create-btn" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Add Student
                </button>
            </div>

            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <h4>No Students Found</h4>
                    <p>Click "Add Student" to create your first student record.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Grade</th>
                                <th>Class</th>
                                <th>Parent</th>
                                <th>Family ID</th>
                                <th>ISAMS Pupil ID</th>
                                <th>ISAMS Parent ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php foreach ($students as $student): ?>
                            <tr class="data-row">
                                <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($student['grade']); ?></span></td>
                                <td><span class="badge badge-success"><?php echo htmlspecialchars($student['class']); ?></span></td>
                                <td><?php echo htmlspecialchars($student['parent_name'] ?: 'Not assigned'); ?></td>
                                <td><?php echo htmlspecialchars($student['family_id'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['isams_pupil_id'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['isams_parent_id'] ?: 'N/A'); ?></td>
                                <td>
                                    <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($student); ?>)'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn-delete" onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>')">
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

    <!-- Create/Edit Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle"><i class="fas fa-user-graduate"></i> Add Student</h3>
            <form method="POST" id="studentForm">
                <input type="hidden" name="action" id="action" value="create">
                <input type="hidden" name="id" id="studentId">

                <div class="form-group">
                    <label for="studentName">Full Name:</label>
                    <input type="text" name="name" id="studentName" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="studentGrade">Grade:</label>
                        <input type="text" name="grade" id="studentGrade" required placeholder="e.g., 10">
                    </div>
                    <div class="form-group">
                        <label for="studentClass">Class:</label>
                        <input type="text" name="class" id="studentClass" required placeholder="e.g., 10A">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="studentParent">Parent:</label>
                        <select name="parent_id" id="studentParent">
                            <option value="">-- No Parent --</option>
                            <?php foreach ($parents as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>">
                                    <?php echo htmlspecialchars($parent['name']); ?>
                                    <?php if (!empty($parent['email'])): ?>
                                        (<?php echo htmlspecialchars($parent['email']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="studentFamilyId">Family ID:</label>
                        <input type="text" name="family_id" id="studentFamilyId" placeholder="Optional">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="studentIsamsId">ISAMS Pupil ID:</label>
                        <input type="number" name="isams_pupil_id" id="studentIsamsId" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label for="studentIsamsParentId">ISAMS Parent ID:</label>
                        <input type="number" name="isams_parent_id" id="studentIsamsParentId" placeholder="Optional">
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Save Student
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

        function openCreateModal() {
            document.getElementById('studentForm').reset();
            document.getElementById('action').value = 'create';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-graduate"></i> Add Student';
            document.getElementById('studentModal').classList.add('active');
        }

        function openEditModal(data) {
            document.getElementById('action').value = 'update';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-graduate"></i> Edit Student';
            document.getElementById('studentId').value = data.id;
            document.getElementById('studentName').value = data.name;
            document.getElementById('studentGrade').value = data.grade;
            document.getElementById('studentClass').value = data.class;
            document.getElementById('studentParent').value = data.parent_id || '';
            document.getElementById('studentFamilyId').value = data.family_id || '';
            document.getElementById('studentIsamsId').value = data.isams_pupil_id || '';
            document.getElementById('studentIsamsParentId').value = data.isams_parent_id || '';
            document.getElementById('studentModal').classList.add('active');
        }

        function confirmDelete(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteItemName').textContent = name;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeModal(modalId = 'studentModal') {
            document.getElementById(modalId).classList.remove('active');
        }

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });

        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
    </script>
</body>
</html>