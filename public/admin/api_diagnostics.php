<?php
/**
 * ISAMS API Diagnostic Tool
 * Shows what data is available in your ISAMS API
 */
require_once __DIR__ . '/../../app/config/config.php';

// Get ISAMS XML data
$apiKey = '34734E38-1175-4969-966B-960A2E928CAF';
$url = "https://isams.kl.his.edu.my/api/batch/1.0/xml.ashx?apiKey=$apiKey";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("Failed to connect to ISAMS API. HTTP Code: $httpCode");
}

$xml = simplexml_load_string($response);
if (!$xml) {
    die("Failed to parse XML from ISAMS");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ISAMS API Diagnostic - PTM Portal</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #252526;
            padding: 30px;
            border-radius: 8px;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
        }
        h3 {
            color: #dcdcaa;
            margin-top: 20px;
        }
        .section {
            background: #1e1e1e;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #007acc;
        }
        .good {
            color: #4ec9b0;
            font-weight: bold;
        }
        .warning {
            color: #ce9178;
            font-weight: bold;
        }
        .error {
            color: #f48771;
            font-weight: bold;
        }
        .code {
            background: #1e1e1e;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th {
            background: #007acc;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #3e3e3e;
        }
        tr:hover {
            background: #2d2d30;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007acc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a9e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 ISAMS API Diagnostic Tool</h1>
        <p>Analyzing your ISAMS API data structure...</p>
        
        <div class="section">
            <h2>📊 XML Structure Overview</h2>
            <?php
            $rootChildren = $xml->children();
            echo "<table>";
            echo "<tr><th>Section Name</th><th>Count</th><th>Status</th></tr>";
            foreach ($rootChildren as $child) {
                $count = count($child->children());
                $name = $child->getName();
                $status = $count > 0 ? "<span class='good'>✓ Available</span>" : "<span class='warning'>Empty</span>";
                echo "<tr><td>{$name}</td><td>{$count}</td><td>{$status}</td></tr>";
            }
            echo "</table>";
            ?>
        </div>
        
        <div class="section">
            <h2>👥 Staff (Teachers) Data</h2>
            <?php
            $staff = $xml->xpath('//Staff');
            echo "<p>Found <span class='good'>" . count($staff) . "</span> staff members</p>";
            
            if (count($staff) > 0) {
                echo "<h3>Sample Staff Record:</h3>";
                $firstStaff = $staff[0];
                echo "<div class='code'>";
                echo "Staff ID: " . $firstStaff['Id'] . "<br>";
                foreach ($firstStaff->children() as $child) {
                    $value = (string)$child;
                    if (!empty($value)) {
                        echo htmlspecialchars($child->getName()) . ": " . htmlspecialchars($value) . "<br>";
                    }
                }
                echo "</div>";
                
                // Check current database
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM teachers");
                $dbCount = $stmt->fetch()['count'];
                echo "<p>Teachers in database: <span class='" . ($dbCount > 0 ? 'good' : 'warning') . "'>{$dbCount}</span></p>";
                
                if ($dbCount === 0) {
                    echo "<p class='warning'>⚠️ No teachers in database yet. Run sync to create them.</p>";
                }
            }
            ?>
        </div>
        
        <div class="section">
            <h2>📚 Subjects Data</h2>
            <?php
            $subjects = $xml->xpath('//TeachingManager/Subjects/Subject');
            echo "<p>Found <span class='good'>" . count($subjects) . "</span> subjects</p>";
            
            if (count($subjects) > 0) {
                echo "<h3>First 10 Subjects:</h3>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Name</th><th>Code</th></tr>";
                for ($i = 0; $i < min(10, count($subjects)); $i++) {
                    $subj = $subjects[$i];
                    $id = $subj['Id'];
                    $name = htmlspecialchars((string)$subj->Name);
                    $code = htmlspecialchars((string)$subj->Code);
                    echo "<tr><td>{$id}</td><td>{$name}</td><td>{$code}</td></tr>";
                }
                echo "</table>";
                
                // Check if names are real
                $hasRealNames = false;
                foreach ($subjects as $subj) {
                    $name = (string)$subj->Name;
                    if ($name && !preg_match('/^[0-9]+[A-Z]\//', $name)) {
                        $hasRealNames = true;
                        break;
                    }
                }
                
                if ($hasRealNames) {
                    echo "<p class='good'>✓ API provides real subject names (e.g., 'Mathematics', 'Biology')</p>";
                } else {
                    echo "<p class='warning'>⚠️ API seems to return codes instead of names</p>";
                }
            } else {
                echo "<p class='warning'>⚠️ No subjects found in //TeachingManager/Subjects/Subject</p>";
            }
            ?>
        </div>
        
        <div class="section">
            <h2>🏫 Teaching Sets (Classes) Data</h2>
            <?php
            $sets = $xml->xpath('//Sets/Set');
            echo "<p>Found <span class='good'>" . count($sets) . "</span> teaching sets</p>";
            
            if (count($sets) > 0) {
                echo "<h3>Sample Teaching Set:</h3>";
                $firstSet = $sets[0];
                echo "<div class='code'>";
                echo "Attributes:<br>";
                foreach ($firstSet->attributes() as $key => $value) {
                    echo "  {$key}: {$value}<br>";
                }
                echo "<br>Children:<br>";
                foreach ($firstSet->children() as $child) {
                    $value = (string)$child;
                    echo "  " . $child->getName() . ": " . htmlspecialchars($value) . "<br>";
                }
                echo "</div>";
                
                // Check teacher assignments
                $setsWithTeachers = 0;
                foreach ($sets as $set) {
                    if (isset($set->Teachers->Teacher)) {
                        $setsWithTeachers++;
                    }
                }
                $percentage = round(($setsWithTeachers / count($sets)) * 100);
                echo "<p>Sets with teachers assigned: <span class='" . ($percentage > 50 ? 'good' : 'warning') . "'>{$setsWithTeachers} ({$percentage}%)</span></p>";
            }
            ?>
        </div>
        
        <div class="section">
            <h2>👨‍🎓 Current Database Status</h2>
            <?php
            $stats = [];
            $stats['Teachers'] = $pdo->query("SELECT COUNT(*) as c FROM teachers")->fetch()['c'];
            $stats['Subjects'] = $pdo->query("SELECT COUNT(*) as c FROM subjects")->fetch()['c'];
            $stats['Teaching Sets'] = $pdo->query("SELECT COUNT(*) as c FROM teaching_sets")->fetch()['c'];
            $stats['Sets with Teachers'] = $pdo->query("SELECT COUNT(*) as c FROM teaching_sets WHERE teacher_id IS NOT NULL")->fetch()['c'];
            $stats['Students'] = $pdo->query("SELECT COUNT(*) as c FROM students")->fetch()['c'];
            $stats['Enrollments'] = $pdo->query("SELECT COUNT(*) as c FROM enrollments")->fetch()['c'];
            
            echo "<table>";
            echo "<tr><th>Table</th><th>Count</th></tr>";
            foreach ($stats as $table => $count) {
                echo "<tr><td>{$table}</td><td>{$count}</td></tr>";
            }
            echo "</table>";
            
            // Sample subject names
            $stmt = $pdo->query("SELECT name, code FROM subjects LIMIT 5");
            echo "<h3>Sample Subject Names in DB:</h3>";
            echo "<table>";
            echo "<tr><th>Name</th><th>Code</th></tr>";
            while ($row = $stmt->fetch()) {
                echo "<tr><td>" . htmlspecialchars($row['name']) . "</td><td>" . htmlspecialchars($row['code']) . "</td></tr>";
            }
            echo "</table>";
            ?>
        </div>
        
        <div class="section">
            <h2>✅ Recommendations</h2>
            <?php
            $recommendations = [];
            
            if ($stats['Teachers'] === 0) {
                $recommendations[] = "<span class='warning'>⚠️ Run sync_subjects.php to create teacher records from API</span>";
            }
            
            if ($stats['Sets with Teachers'] === 0 && $stats['Teaching Sets'] > 0) {
                $recommendations[] = "<span class='warning'>⚠️ Teaching sets exist but have no teachers assigned</span>";
            }
            
            if (count($subjects) > 0 && $stats['Subjects'] === 0) {
                $recommendations[] = "<span class='warning'>⚠️ Subjects exist in API but not in database - run sync</span>";
            }
            
            if (count($recommendations) === 0) {
                echo "<p class='good'>✓ Everything looks good! Your data is synced properly.</p>";
            } else {
                echo "<ul>";
                foreach ($recommendations as $rec) {
                    echo "<li>{$rec}</li>";
                }
                echo "</ul>";
            }
            ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
        <a href="?page=sync-subjects" class="btn">🔄 Run Sync Now</a>
        <a href="?page=view-subjects" class="btn">📚 View Subjects</a>
        <a href="?page=admin" class="btn">← Back to Dashboard</a>
    </div>
    </div>
</body>
</html>