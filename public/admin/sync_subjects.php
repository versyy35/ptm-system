<?php
/**
 * ISAMS Subject & Enrollment Sync Script - STABLE VERSION
 * Clean and tested - syncs teachers, subjects, teaching sets and enrollments
 */
require_once __DIR__ . '/../../app/config/config.php';

// Increase execution time and memory for large datasets
set_time_limit(300);
ini_set('memory_limit', '512M');

// Enable output buffering for real-time progress
if (ob_get_level() == 0) ob_start();
ob_implicit_flush(true);

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

echo "<h2>ISAMS Subject & Enrollment Sync</h2>";
echo "<p>Importing teachers, subjects, teaching sets and enrollments...</p><hr>";

$startTime = microtime(true);

// Get ISAMS XML data
$apiKey = SUBJECTS_API_KEY;
$url = "https://isams.kl.his.edu.my/api/batch/1.0/xml.ashx?apiKey=$apiKey";

echo "<p class='info'>Connecting to ISAMS API...</p>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "<p class='error'>Failed - HTTP Code: $httpCode</p>";
    exit;
}

$xml = simplexml_load_string($response);
if (!$xml) {
    echo "<p class='error'>Failed to parse XML</p>";
    exit;
}

echo "<p class='success'>Successfully connected to ISAMS</p><hr>";

$setsProcessed = 0;
$enrollmentsProcessed = 0;
$subjectsProcessed = 0;
$teachersCreated = 0;
$teachersUpdated = 0;

// Process Staff from HRManager/CurrentStaff/StaffMember
echo "<h3>Processing Teachers...</h3>";
$staffMembers = $xml->xpath('//HRManager/CurrentStaff/StaffMember');

if ($staffMembers && count($staffMembers) > 0) {
    echo "<p class='info'>Found " . count($staffMembers) . " staff members in ISAMS</p>";
    
    foreach ($staffMembers as $staff) {
        $staffId = (int)$staff['Id'];
        $title = trim((string)$staff->Title);
        $forename = trim((string)$staff->Forename);
        $surname = trim((string)$staff->Surname);
        $email = trim((string)$staff->SchoolEmailAddress);
        
        // Skip if no email (non-teaching staff)
        if (empty($email)) continue;
        
        $fullName = trim("$title $forename $surname");
        if (empty($fullName)) $fullName = trim("$forename $surname");
        
        if ($staffId && !empty($fullName)) {
            // Check if teacher exists
            $stmt = $pdo->prepare("SELECT t.id, t.user_id FROM teachers t WHERE t.isams_staff_id = ?");
            $stmt->execute([$staffId]);
            $existingTeacher = $stmt->fetch();
            
            if ($existingTeacher) {
                // Update existing teacher
                $stmt = $pdo->prepare("UPDATE teachers SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $existingTeacher['id']]);
                
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $existingTeacher['user_id']]);
                
                $teachersUpdated++;
            } else {
                // Create new teacher
                try {
                    $pdo->beginTransaction();
                    
                    // Check if user exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $existingUser = $stmt->fetch();
                    
                    if ($existingUser) {
                        $userId = $existingUser['id'];
                    } else {
                        // Create user
                        $stmt = $pdo->prepare("
                            INSERT INTO users (email, name, role, is_active, created_at)
                            VALUES (?, ?, 'teacher', 1, NOW())
                        ");
                        $stmt->execute([$email, $fullName]);
                        $userId = $pdo->lastInsertId();
                    }
                    
                    // Create teacher record
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO teachers (user_id, name, email, isams_staff_id, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    if ($stmt->execute([$userId, $fullName, $email, $staffId])) {
                        if ($stmt->rowCount() > 0) {
                            $teachersCreated++;
                        }
                    }
                    
                    $pdo->commit();
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                }
            }
        }
    }
    echo "<p class='success'>Created: $teachersCreated, Updated: $teachersUpdated teachers</p>";
}

// Process Subjects
echo "<h3>Processing Subjects...</h3>";
$subjects = $xml->xpath('//Subjects/Subject');

if ($subjects && count($subjects) > 0) {
    echo "<p class='info'>Found " . count($subjects) . " subjects in ISAMS</p>";
    
    foreach ($subjects as $subject) {
        $subjectId = (int)$subject['Id'];
        $subjectName = trim((string)$subject->Name);
        $subjectCode = trim((string)$subject->Code);
        
        if ($subjectId && !empty($subjectName)) {
            $stmt = $pdo->prepare("
                INSERT INTO subjects (isams_subject_id, name, code, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    code = VALUES(code),
                    updated_at = NOW()
            ");
            $stmt->execute([$subjectId, $subjectName, $subjectCode]);
            $subjectsProcessed++;
        }
    }
    echo "<p class='success'>Processed $subjectsProcessed subjects</p>";
}

// Update students with ISAMS IDs
echo "<h3>Updating Student ISAMS IDs...</h3>";
$pupils = $xml->xpath('//CurrentPupils/Pupil');
$studentsUpdated = 0;

foreach ($pupils as $pupil) {
    $pupilId = (int)$pupil['Id'];
    $forename = trim((string)$pupil->Forename);
    $surname = trim((string)$pupil->Surname);
    $fullName = trim("$forename $surname");
    
    if ($pupilId && $fullName) {
        $stmt = $pdo->prepare("
            UPDATE students 
            SET isams_pupil_id = ? 
            WHERE name = ? AND (isams_pupil_id IS NULL OR isams_pupil_id = 0)
        ");
        if ($stmt->execute([$pupilId, $fullName])) {
            if ($stmt->rowCount() > 0) {
                $studentsUpdated++;
            }
        }
    }
}
echo "<p class='success'>Updated $studentsUpdated students with ISAMS IDs</p>";

// Process Teaching Sets
echo "<h3>Processing Teaching Sets...</h3>";
$sets = $xml->xpath('//Sets/Set');

if ($sets && count($sets) > 0) {
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
        
        // Find teacher
        $teacherId = null;
        $teacherName = null;
        if ($teacherStaffId) {
            $stmt = $pdo->prepare("SELECT id, name FROM teachers WHERE isams_staff_id = ?");
            $stmt->execute([$teacherStaffId]);
            $teacher = $stmt->fetch();
            if ($teacher) {
                $teacherId = $teacher['id'];
                $teacherName = $teacher['name'];
            }
        }
        
        // Get subject
        $subjectName = null;
        $subjectDbId = null;
        if ($subjectId) {
            $stmt = $pdo->prepare("SELECT id, name FROM subjects WHERE isams_subject_id = ?");
            $stmt->execute([$subjectId]);
            $subject = $stmt->fetch();
            if ($subject) {
                $subjectDbId = $subject['id'];
                $subjectName = $subject['name'];
            }
        }
        
        // Insert/update teaching set
        $stmt = $pdo->prepare("
            INSERT INTO teaching_sets (
                isams_set_id, subject_id, subject_name, 
                teacher_id, teacher_name, year_group, 
                set_code, set_name, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                subject_id = VALUES(subject_id),
                subject_name = VALUES(subject_name),
                teacher_id = VALUES(teacher_id),
                teacher_name = VALUES(teacher_name),
                year_group = VALUES(year_group),
                set_code = VALUES(set_code),
                set_name = VALUES(set_name),
                updated_at = NOW()
        ");
        
        if ($stmt->execute([$setId, $subjectDbId, $subjectName, $teacherId, $teacherName, $yearId, $setCode, $setName])) {
            $setsProcessed++;
            if ($setsProcessed % 100 === 0) {
                echo "<p class='info'>Processed $setsProcessed sets...</p>";
            }
        }
    }
    
    echo "<p class='success'>Processed $setsProcessed teaching sets</p>";
}

// Process Enrollments
echo "<h3>Processing Student Enrollments...</h3>";
$setLists = $xml->xpath('//SetLists/SetList');

if ($setLists && count($setLists) > 0) {
    echo "<p class='info'>Found " . count($setLists) . " enrollments</p>";
    echo "<p class='info'>Building lookup maps...</p>";
    
    $studentMap = [];
    $stmt = $pdo->query("SELECT id, isams_pupil_id FROM students WHERE isams_pupil_id IS NOT NULL");
    while ($row = $stmt->fetch()) {
        $studentMap[$row['isams_pupil_id']] = $row['id'];
    }
    
    $setMap = [];
    $stmt = $pdo->query("SELECT id, isams_set_id FROM teaching_sets WHERE isams_set_id IS NOT NULL");
    while ($row = $stmt->fetch()) {
        $setMap[$row['isams_set_id']] = $row['id'];
    }
    
    echo "<p class='info'>Processing enrollments in batches...</p>";
    
    $batchSize = 1000;
    $enrollmentBatch = [];
    $batchCount = 0;
    
    foreach ($setLists as $setList) {
        $setId = (int)$setList['SetId'];
        $pupilId = (int)$setList['PupilId'];
        
        if (isset($studentMap[$pupilId]) && isset($setMap[$setId])) {
            $enrollmentBatch[] = "({$studentMap[$pupilId]}, {$setMap[$setId]})";
            
            if (count($enrollmentBatch) >= $batchSize) {
                $values = implode(',', $enrollmentBatch);
                $sql = "INSERT IGNORE INTO enrollments (student_id, teaching_set_id) VALUES $values";
                $pdo->exec($sql);
                
                $enrollmentsProcessed += count($enrollmentBatch);
                $batchCount++;
                
                echo "<p class='info'>Batch $batchCount: Processed $enrollmentsProcessed enrollments...</p>";
                if (ob_get_level() > 0) ob_flush();
                flush();
                
                $enrollmentBatch = [];
            }
        }
    }
    
    if (count($enrollmentBatch) > 0) {
        $values = implode(',', $enrollmentBatch);
        $sql = "INSERT IGNORE INTO enrollments (student_id, teaching_set_id) VALUES $values";
        $pdo->exec($sql);
        $enrollmentsProcessed += count($enrollmentBatch);
    }
    
    echo "<p class='success'>Processed $enrollmentsProcessed enrollments</p>";
}

// Summary
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

echo "<div class='summary'>";
echo "<h3>Sync Summary</h3>";
echo "<p><strong>Teachers created:</strong> $teachersCreated</p>";
echo "<p><strong>Teachers updated:</strong> $teachersUpdated</p>";
echo "<p><strong>Subjects processed:</strong> $subjectsProcessed</p>";
echo "<p><strong>Teaching Sets:</strong> $setsProcessed</p>";
echo "<p><strong>Enrollments:</strong> $enrollmentsProcessed</p>";
echo "<p><strong>Execution time:</strong> $executionTime seconds</p>";
echo "<br><p class='success'><strong>Sync completed!</strong></p>";
echo "</div>";

echo "<br><p><a href='?page=admin'>Back to Admin</a></p>";
echo "</div></body></html>";
?>