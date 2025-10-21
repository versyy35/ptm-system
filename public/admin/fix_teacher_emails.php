<?php
/**
 * Fix Missing Teacher Emails
 * Handles teachers without email addresses
 */
require_once __DIR__ . '/../../app/config/config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_placeholders') {
        $stmt = $pdo->prepare("
            UPDATE teachers 
            SET email = CONCAT(
                LOWER(
                    REPLACE(
                        REPLACE(
                            REPLACE(name, 'Ms ', ''),
                            'Mr ', ''
                        ),
                        ' ', '.'
                    )
                ),
                '@placeholder.kl.his.edu.my'
            )
            WHERE email IS NULL OR email = ''
        ");
        $stmt->execute();
        $affected = $stmt->rowCount();
        
        $success = "✅ Generated placeholder emails for {$affected} teachers";
    } elseif ($_POST['action'] === 'update_email') {
        $teacherId = (int)$_POST['teacher_id'];
        $newEmail = trim($_POST['email']);
        
        if ($teacherId && !empty($newEmail) && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("UPDATE teachers SET email = ? WHERE id = ?");
            $stmt->execute([$newEmail, $teacherId]);
            
            // Also update users table
            $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch();
            if ($teacher) {
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$newEmail, $teacher['user_id']]);
            }
            
            $success = "✅ Email updated successfully";
        } else {
            $error = "❌ Invalid email address";
        }
    } elseif ($_POST['action'] === 'delete_non_teaching') {
        // Delete teachers with no email and no classes assigned
        $stmt = $pdo->prepare("
            DELETE t FROM teachers t
            LEFT JOIN teaching_sets ts ON ts.teacher_id = t.id
            WHERE (t.email IS NULL OR t.email = '')
            AND ts.id IS NULL
        ");
        $stmt->execute();
        $affected = $stmt->rowCount();
        
        $success = "✅ Removed {$affected} non-teaching staff without emails";
    }
}

// Get teachers without emails
$stmt = $pdo->query("
    SELECT 
        t.id,
        t.name,
        t.email,
        t.isams_staff_id,
        COUNT(ts.id) as classes_count,
        GROUP_CONCAT(DISTINCT ts.set_code ORDER BY ts.set_code SEPARATOR ', ') as sample_classes
    FROM teachers t
    LEFT JOIN teaching_sets ts ON ts.teacher_id = t.id
    WHERE t.email IS NULL OR t.email = ''
    GROUP BY t.id
    ORDER BY classes_count DESC, t.name
");
$teachersWithoutEmail = $stmt->fetchAll();

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_teachers,
        SUM(CASE WHEN email IS NULL OR email = '' THEN 1 ELSE 0 END) as without_email,
        SUM(CASE WHEN email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) as with_email
    FROM teachers
")->fetch();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Teacher Emails - PTM Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-value.good { color: #28a745; }
        .stat-value.warning { color: #ffc107; }
        .stat-value.danger { color: #dc3545; }
        
        .stat-label {
            color: #65676b;
            font-size: 14px;
        }
        
        .section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #1c1e21;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            text-align: left;
            padding: 12px;
            font-weight: 600;
            color: #495057;
            font-size: 13px;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            background: #007bff;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #000;
        }
        
        .edit-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .edit-input {
            flex: 1;
            padding: 6px 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .edit-input:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #65676b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Fix Teacher Emails</h1>
            <p>Manage teachers without email addresses</p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value good"><?= $stats['with_email'] ?></div>
                <div class="stat-label">Teachers with Email</div>
            </div>
            <div class="stat-card">
                <div class="stat-value <?= $stats['without_email'] > 0 ? 'warning' : 'good' ?>">
                    <?= $stats['without_email'] ?>
                </div>
                <div class="stat-label">Teachers without Email</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_teachers'] ?></div>
                <div class="stat-label">Total Teachers</div>
            </div>
        </div>
        
        <?php if (count($teachersWithoutEmail) > 0): ?>
            <div class="section">
                <h2>⚡ Quick Actions</h2>
                <div class="actions">
                    <form method="POST" onsubmit="return confirm('Generate placeholder emails for all teachers without email?');">
                        <input type="hidden" name="action" value="generate_placeholders">
                        <button type="submit" class="btn btn-warning">
                            Generate Placeholder Emails
                        </button>
                    </form>
                    
                    <form method="POST" onsubmit="return confirm('Delete non-teaching staff without emails? This only removes staff with 0 classes assigned.');">
                        <input type="hidden" name="action" value="delete_non_teaching">
                        <button type="submit" class="btn btn-danger">
                            Remove Non-Teaching Staff
                        </button>
                    </form>
                </div>
                <p style="color: #65676b; font-size: 14px;">
                    <strong>Placeholder emails:</strong> Generates emails like "jane.smith@placeholder.kl.his.edu.my"<br>
                    <strong>Remove non-teaching:</strong> Only removes staff with no classes assigned
                </p>
            </div>
            
            <div class="section">
                <h2>📋 Teachers Without Email (<?= count($teachersWithoutEmail) ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>ISAMS ID</th>
                            <th>Classes</th>
                            <th>Sample Classes</th>
                            <th>Add Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachersWithoutEmail as $teacher): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($teacher['name']) ?></strong>
                                </td>
                                <td><?= $teacher['isams_staff_id'] ?></td>
                                <td>
                                    <?php if ($teacher['classes_count'] > 0): ?>
                                        <span class="badge badge-warning"><?= $teacher['classes_count'] ?> classes</span>
                                    <?php else: ?>
                                        <span style="color: #999;">No classes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small style="color: #65676b;">
                                        <?= htmlspecialchars($teacher['sample_classes'] ?: '-') ?>
                                    </small>
                                </td>
                                <td>
                                    <form method="POST" class="edit-form">
                                        <input type="hidden" name="action" value="update_email">
                                        <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                                        <input 
                                            type="email" 
                                            name="email" 
                                            class="edit-input" 
                                            placeholder="email@kl.his.edu.my"
                                            required
                                        >
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="section">
                <div class="empty-state">
                    <h2>✅ All Good!</h2>
                    <p>All teachers have email addresses.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="sync_subjects.php" class="btn btn-secondary">🔄 Run Sync Again</a>
            <a href="api_diagnostic.php" class="btn btn-secondary">🔍 Diagnostic</a>
            <a href="?page=admin" class="btn btn-secondary">← Admin Dashboard</a>
        </div>
    </div>
</body>
</html>