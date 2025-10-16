<?php
/**
 * ISAMS Data Sync Script
 * Run this to import students and parents from ISAMS into PTM database
 * IMPORTANT: Only run this when you need to sync data!
 */
require_once __DIR__ . '/../../app/config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>ISAMS Sync - PTM Portal</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .summary { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin-top: 20px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h2>🔄 ISAMS Data Sync</h2>";
echo "<p>Syncing student and parent data from ISAMS...</p>";
echo "<hr>";

// Get ISAMS XML data
$apiKey = ISAMS_API_KEY;
$url = ISAMS_API_URL . "?apiKey=$apiKey";

echo "<p class='info'>📡 Connecting to ISAMS API...</p>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "<p class='error'>❌ cURL error: " . curl_error($ch) . "</p>";
    echo "</div></body></html>";
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "<p class='error'>❌ ISAMS API returned HTTP code: $httpCode</p>";
    echo "</div></body></html>";
    exit;
}

// Parse XML
$xml = simplexml_load_string($response);
if (!$xml) {
    echo "<p class='error'>❌ Failed to parse XML from ISAMS</p>";
    echo "</div></body></html>";
    exit;
}

echo "<p class='success'>✅ Successfully connected to ISAMS</p>";

$studentsProcessed = 0;
$parentsProcessed = 0;
$linksCreated = 0;

// Process Students
echo "<h3>👨‍🎓 Processing Students...</h3>";
$pupilMap = [];
$currentPupilBlocks = $xml->xpath('//CurrentPupils');

foreach ($currentPupilBlocks as $pupilBlock) {
    foreach ($pupilBlock->Pupil as $pupil) {
        $schoolId = (string)$pupil->SchoolId;
        if ($schoolId) {
            $pupilMap[$schoolId] = $pupil;
        }
    }
}

echo "<p>Found " . count($pupilMap) . " students in ISAMS</p>";

foreach ($pupilMap as $schoolId => $pupil) {
    $email = trim((string)$pupil->EmailAddress);
    $forename = trim((string)$pupil->Forename);
    $surname = trim((string)$pupil->Surname);
    $form = trim((string)$pupil->Form);
    $ncYear = trim((string)$pupil->NCYear);
    
    if (empty($email)) {
        echo "<p>⚠️ Skipping student $schoolId - no email</p>";
        continue;
    }
    
    $fullName = trim("$forename $surname");
    
    // Check if student exists
    $stmt = $pdo->prepare("SELECT id FROM students WHERE name = ? OR grade = ? OR class = ?");
    $stmt->execute([$fullName, $ncYear, $form]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        // Insert new student (parent_id will be NULL for now)
        $stmt = $pdo->prepare("
        INSERT INTO students (name, grade, class, parent_id, created_at)
        VALUES (?, ?, ?, NULL, NOW())
        ");
        $stmt->execute([$fullName, $ncYear, $form]);
        echo "<p class='success'>✅ Added student: $fullName (Form: $form, Year: $ncYear)</p>";
        $studentsProcessed++;
    } else {
        echo "<p class='info'>ℹ️ Student exists: $fullName</p>";
    }
}

// Process Parents
echo "<br><h3>👨‍👩‍👧‍👦 Processing Parents...</h3>";

$processedParents = [];

if (isset($xml->PupilManager->Contacts->Contact)) {
    echo "<p>Found " . count($xml->PupilManager->Contacts->Contact) . " contacts in ISAMS</p>";
    
    foreach ($xml->PupilManager->Contacts->Contact as $contact) {
        $email = trim((string)$contact->EmailAddress);
        if (empty($email)) continue;
        
        // Skip duplicates
        $personKey = strtolower($email);
        if (isset($processedParents[$personKey])) continue;
        $processedParents[$personKey] = true;
        
        $forename = trim((string)$contact->Forename);
        $surname = trim((string)$contact->Surname);
        $mobile = trim((string)$contact->Mobile);
        
        $fullName = trim("$forename $surname");
        if (empty($fullName)) $fullName = $email;
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT u.id, p.id as parent_id FROM users u LEFT JOIN parents p ON u.id = p.user_id WHERE u.email = ? AND u.role = 'parent'");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        
        if (!$existingUser) {
            // Create new parent
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, name, role, created_at)
                    VALUES (?, ?, 'parent', NOW())
                ");
                $stmt->execute([$email, $fullName]);
                $userId = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("
                    INSERT INTO parents (user_id, phone)
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $mobile]);
                $parentId = $pdo->lastInsertId();
                
                $pdo->commit();
                
                echo "<p class='success'>✅ Added parent: $fullName ($email)</p>";
                $parentsProcessed++;
                
                // Link children
                if (isset($contact->Pupils->Pupil)) {
                    foreach ($contact->Pupils->Pupil as $linkedPupil) {
                        $linkedSchoolId = trim((string)$linkedPupil->SchoolId);
                        
                        // Find student by form/name
                        if (isset($pupilMap[$linkedSchoolId])) {
                            $pupilData = $pupilMap[$linkedSchoolId];
                            $studentName = trim((string)$pupilData->Forename . " " . (string)$pupilData->Surname);
                            
                            $stmt = $pdo->prepare("SELECT id FROM students WHERE name = ?");
                            $stmt->execute([$studentName]);
                            $student = $stmt->fetch();
                            
                            if ($student) {
                                // Update student with parent_id
                                $stmt = $pdo->prepare("UPDATE students SET parent_id = ? WHERE id = ?");
                                $stmt->execute([$parentId, $student['id']]);
                                echo "<p class='info'>&nbsp;&nbsp;&nbsp;🔗 Linked to child: $studentName</p>";
                                $linksCreated++;
                            }
                        }
                    }
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo "<p class='error'>❌ Error creating parent $email: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='info'>ℹ️ Parent exists: $fullName ($email)</p>";
        }
    }
}

// Summary
echo "<div class='summary'>";
echo "<h3>📊 Sync Summary</h3>";
echo "<p><strong>👨‍🎓 Students added:</strong> $studentsProcessed</p>";
echo "<p><strong>👨‍👩‍👧‍👦 Parents added:</strong> $parentsProcessed</p>";
echo "<p><strong>🔗 Parent-Child links created:</strong> $linksCreated</p>";
echo "<br><p class='success'><strong>✅ Sync completed successfully!</strong></p>";
echo "</div>";

echo "<br><p><a href='?page=admin'>← Back to Admin Dashboard</a></p>";

echo "</div></body></html>";
?>