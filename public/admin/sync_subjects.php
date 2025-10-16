<?php
/**
 * ISAMS Subject & Enrollment Sync Script
 * Imports teaching sets (classes) and student enrollments
 */
require_once __DIR__ . '/../app/config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Subject Sync - PTM Portal</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h2 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .summary { background: #e7f3ff; padding: 20px; border-left: 5px solid #007bff; margin-top: 30px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h2>📚 ISAMS Subject & Enrollment Sync</h2>";
echo "<p>Importing teaching sets and student enrollments...</p><hr>";

// Get ISAMS XML data (using the subject API key)
$apiKey = '34734E38-1175-4969-966B-960A2E928CAF'; // Use the key your boss gave you
$url = "https://isams.kl.his.edu.my/api/batch/1.0/xml.ashx?apiKey=$apiKey";

echo "<p class='info'>📡 Connecting to ISAMS API...</p>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "<p class='error'>❌ Failed - HTTP Code: $httpCode</p>";
    exit;
}

$xml = simplexml_load_string($response);
if (!$xml) {
    echo "<p class='error'>❌ Failed to parse XML</p>";
    exit;
}

echo "<p class='success'>✅ Successfully connected to ISAMS</p><hr>";

$setsProcessed = 0;
$enrollmentsProcessed = 0;
$subjectsProcessed = 0;

// First, update students with their ISAMS Pupil IDs (for linking)
echo "<h3>🔗 Updating Student ISAMS IDs...</h3>";
$pupils = $xml->xpath('//CurrentPupils/Pupil');
$studentsUpdated = 0;

foreach ($pupils as $pupil) {
    $pupilId = (int)$pupil['Id'];
    $schoolId = trim((string)$pupil->SchoolId);
    $forename = trim((string)$pupil->Forename);
    $surname = trim((string)$pupil->Surname);
    $fullName = trim("$forename $surname");
    
    if ($pupilId && $fullName) {
        $stmt = $pdo->prepare("
            UPDATE students 
            SET isams_pupil_id = ? 
            WHERE name = ? AND isams_pupil_id IS NULL
        ");
        if ($stmt->execute([$pupilId, $fullName])) {
            $studentsUpdated++;
        }
    }
}
echo "<p class='success'>✅ Updated $studentsUpdated students with ISAMS IDs</p>";

// Process Teaching Sets
echo "<h3>📚 Processing Teaching Sets (Classes)...</h3>";
$sets = $xml->xpath('//Sets/Set');

if (!$sets || count($sets) === 0) {
    echo "<p class='error'>❌ No teaching sets found in API</p>";
} else {
    echo "<p class='info'>Found " . count($sets) . " teaching sets</p>";
    
    foreach ($sets as $set) {
        $setId = (int)$set['Id'];
        $subjectId = (int)$set['SubjectId'];
        $yearId = trim((string)$set['YearId']);
        $setCode = trim((string)$set->SetCode);
        $setName = trim((string)$set->Name);
        
        // Get teacher StaffId
        $teacherStaffId = null;
        if (isset($set->Teachers->Teacher)) {
            $teacherStaffId = (int)$set->Teachers->Teacher['StaffId'];
        }
        
        // Find teacher in database by staff ID
        $teacherId = null;
        if ($teacherStaffId) {
            $stmt = $pdo->prepare("SELECT id FROM teachers WHERE isams_staff_id = ?");
            $stmt->execute([$teacherStaffId]);
            $teacher = $stmt->fetch();
            if ($teacher) {
                $teacherId = $teacher['id'];
            }
        }
        
        // Insert/update subject first
        if ($subjectId) {
            $stmt = $pdo->prepare("
                INSERT INTO subjects (isams_subject_id, name, code) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code)
            ");
            $stmt->execute([$subjectId, $setName, $setCode]);
            $subjectsProcessed++;
            
            // Get the subject's database ID
            $stmt = $pdo->prepare("SELECT id FROM subjects WHERE isams_subject_id = ?");
            $stmt->execute([$subjectId]);
            $subject = $stmt->fetch();
            $subjectDbId = $subject ? $subject['id'] : null;
        }
        
        // Insert/update teaching set
        $stmt = $pdo->prepare("
            INSERT INTO teaching_sets (isams_set_id, subject_id, teacher_id, year_group, set_code, set_name)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                subject_id = VALUES(subject_id),
                teacher_id = VALUES(teacher_id),
                year_group = VALUES(year_group),
                set_code = VALUES(set_code),
                set_name = VALUES(set_name)
        ");
        
        if ($stmt->execute([$setId, $subjectDbId, $teacherId, $yearId, $setCode, $setName])) {
            $setsProcessed++;
            if ($setsProcessed % 100 === 0) {
                echo "<p class='info'>Processed $setsProcessed sets...</p>";
            }
        }
    }
    
    echo "<p class='success'>✅ Processed $setsProcessed teaching sets</p>";
}

// Process Enrollments (SetLists)
echo "<h3>👥 Processing Student Enrollments...</h3>";
$setLists = $xml->xpath('//SetLists/SetList');

if (!$setLists || count($setLists) === 0) {
    echo "<p class='error'>❌ No enrollments found in API</p>";
} else {
    echo "<p class='info'>Found " . count($setLists) . " enrollments</p>";
    
    foreach ($setLists as $setList) {
        $setId = (int)$setList['SetId'];
        $pupilId = (int)$setList['PupilId'];
        
        // Find student by ISAMS pupil ID
        $stmt = $pdo->prepare("SELECT id FROM students WHERE isams_pupil_id = ?");
        $stmt->execute([$pupilId]);
        $student = $stmt->fetch();
        
        // Find teaching set by ISAMS set ID
        $stmt = $pdo->prepare("SELECT id FROM teaching_sets WHERE isams_set_id = ?");
        $stmt->execute([$setId]);
        $teachingSet = $stmt->fetch();
        
        if ($student && $teachingSet) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO enrollments (student_id, teaching_set_id)
                VALUES (?, ?)
            ");
            if ($stmt->execute([$student['id'], $teachingSet['id']])) {
                $enrollmentsProcessed++;
                if ($enrollmentsProcessed % 500 === 0) {
                    echo "<p class='info'>Processed $enrollmentsProcessed enrollments...</p>";
                }
            }
        }
    }
    
    echo "<p class='success'>✅ Processed $enrollmentsProcessed enrollments</p>";
}

// Summary
echo "<div class='summary'>";
echo "<h3>📊 Sync Summary</h3>";
echo "<p><strong>📚 Teaching Sets imported:</strong> $setsProcessed</p>";
echo "<p><strong>📖 Subjects imported:</strong> " . ($subjectsProcessed > 0 ? "✅" : "0") . "</p>";
echo "<p><strong>👥 Student Enrollments:</strong> $enrollmentsProcessed</p>";
echo "<br><p class='success'><strong>✅ Subject sync completed!</strong></p>";
echo "</div>";

echo "<br><p><a href='?page=admin'>← Back to Admin Dashboard</a></p>";
echo "</div></body></html>";
?>