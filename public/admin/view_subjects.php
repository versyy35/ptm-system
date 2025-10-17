<?php
/**
 * Subject Browser - Visual view of all subjects and teaching sets
 * Use this to understand what subject codes mean and show to your boss
 */
require_once __DIR__ . '/../../app/config/config.php';

// Get all subjects with teaching set counts
$stmt = $pdo->query("
    SELECT 
        s.id,
        s.isams_subject_id,
        s.name,
        s.code,
        COUNT(DISTINCT ts.id) as teaching_sets_count,
        COUNT(DISTINCT e.student_id) as students_enrolled
    FROM subjects s
    LEFT JOIN teaching_sets ts ON ts.subject_id = s.id
    LEFT JOIN enrollments e ON e.teaching_set_id = ts.id
    GROUP BY s.id
    ORDER BY s.isams_subject_id
");
$subjects = $stmt->fetchAll();

// Get teaching sets for each subject (we'll use this for details)
$setsStmt = $pdo->query("
    SELECT 
        ts.subject_id,
        ts.set_name,
        ts.set_code,
        ts.teacher_name,
        ts.year_group,
        COUNT(e.student_id) as student_count
    FROM teaching_sets ts
    LEFT JOIN enrollments e ON e.teaching_set_id = ts.id
    GROUP BY ts.id
    ORDER BY ts.subject_id, ts.year_group, ts.set_code
");

$setsBySubject = [];
while ($set = $setsStmt->fetch()) {
    if (!isset($setsBySubject[$set['subject_id']])) {
        $setsBySubject[$set['subject_id']] = [];
    }
    $setsBySubject[$set['subject_id']][] = $set;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Subject Browser - PTM Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
            color: #1c1e21;
        }
        
        .container {
            max-width: 1400px;
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
            color: #1c1e21;
        }
        
        .header p {
            color: #65676b;
            font-size: 15px;
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
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #65676b;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .subjects-grid {
            display: grid;
            gap: 20px;
        }
        
        .subject-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .subject-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .subject-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .subject-header:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a4093 100%);
        }
        
        .subject-title {
            font-size: 20px;
            font-weight: 600;
        }
        
        .subject-id {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .subject-meta {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .subject-body {
            padding: 25px;
            display: none;
        }
        
        .subject-body.active {
            display: block;
        }
        
        .teaching-sets {
            display: grid;
            gap: 15px;
        }
        
        .teaching-set {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr;
            gap: 15px;
            align-items: center;
        }
        
        .teaching-set:hover {
            background: #e9ecef;
        }
        
        .set-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #007bff;
            font-size: 14px;
        }
        
        .set-teacher {
            color: #495057;
            font-size: 14px;
        }
        
        .set-year {
            background: #007bff;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            width: fit-content;
        }
        
        .set-students {
            text-align: right;
            font-weight: 600;
            color: #28a745;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #65676b;
        }
        
        .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }
        
        .subject-card.expanded .toggle-icon {
            transform: rotate(180deg);
        }
        
        .actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .search-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 20px;
            font-size: 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: border-color 0.2s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Subject Browser</h1>
            <p>Visual overview of all subjects and teaching sets from ISAMS. Use this to identify what each subject code means.</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?= count($subjects) ?></div>
                <div class="stat-label">Total Subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= array_sum(array_column($subjects, 'teaching_sets_count')) ?></div>
                <div class="stat-label">Teaching Sets</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= array_sum(array_column($subjects, 'students_enrolled')) ?></div>
                <div class="stat-label">Student Enrollments</div>
            </div>
        </div>
        
        <div class="search-box">
            <input 
                type="text" 
                class="search-input" 
                id="searchInput" 
                placeholder="🔍 Search by subject name, code, teacher, or year group..."
            >
        </div>
        
        <div class="subjects-grid" id="subjectsGrid">
            <?php foreach ($subjects as $subject): ?>
                <div class="subject-card" data-search="<?= strtolower($subject['name'] . ' ' . $subject['code']) ?>">
                    <div class="subject-header" onclick="toggleSubject(this)">
                        <div>
                            <div class="subject-title">
                                <?= htmlspecialchars($subject['name']) ?>
                            </div>
                            <div class="subject-meta">
                                <span>📋 <?= $subject['teaching_sets_count'] ?> sets</span>
                                <span>👥 <?= $subject['students_enrolled'] ?> students</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span class="subject-id">ID: <?= $subject['isams_subject_id'] ?></span>
                            <span class="toggle-icon">▼</span>
                        </div>
                    </div>
                    
                    <div class="subject-body">
                        <?php if (isset($setsBySubject[$subject['id']]) && count($setsBySubject[$subject['id']]) > 0): ?>
                            <div class="teaching-sets">
                                <?php foreach ($setsBySubject[$subject['id']] as $set): ?>
                                    <div class="teaching-set">
                                        <div class="set-code"><?= htmlspecialchars($set['set_code']) ?></div>
                                        <div class="set-teacher">
                                            <?= $set['teacher_name'] ? '👤 ' . htmlspecialchars($set['teacher_name']) : '👤 No teacher assigned' ?>
                                        </div>
                                        <div class="set-year">Year <?= htmlspecialchars($set['year_group']) ?></div>
                                        <div class="set-students"><?= $set['student_count'] ?> students</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                No teaching sets found for this subject
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="actions" style="text-align: center; margin-top: 40px;">
            <a href="?page=admin" class="btn">← Back to Admin Dashboard</a>
        </div>
    </div>
    
    <script>
        function toggleSubject(header) {
            const card = header.closest('.subject-card');
            const body = card.querySelector('.subject-body');
            
            // Close all other cards
            document.querySelectorAll('.subject-card').forEach(c => {
                if (c !== card) {
                    c.classList.remove('expanded');
                    c.querySelector('.subject-body').classList.remove('active');
                }
            });
            
            // Toggle current card
            card.classList.toggle('expanded');
            body.classList.toggle('active');
        }
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const subjectsGrid = document.getElementById('subjectsGrid');
        
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = subjectsGrid.querySelectorAll('.subject-card');
            
            cards.forEach(card => {
                const searchData = card.getAttribute('data-search');
                const sets = card.querySelectorAll('.teaching-set');
                let matchFound = searchData.includes(searchTerm);
                
                // Also search within teaching sets
                sets.forEach(set => {
                    const setData = set.textContent.toLowerCase();
                    if (setData.includes(searchTerm)) {
                        matchFound = true;
                    }
                });
                
                if (matchFound || searchTerm === '') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Expand all on Ctrl+E
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                document.querySelectorAll('.subject-card').forEach(card => {
                    card.classList.add('expanded');
                    card.querySelector('.subject-body').classList.add('active');
                });
            }
        });
    </script>
</body>
</html>