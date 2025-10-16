<?php
/**
 * ISAMS Data Sync Script
 * Run this to import students and parents from ISAMS into PTM database
 * IMPORTANT: Only run this when you need to sync data!
 */
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/isams_config.php';

echo "<h2>🔄 ISAMS Data Sync</h2>";

// Get ISAMS XML data
$apiKey = ISAMS_API_KEY;
$url = ISAMS_API_URL . "?apiKey=$apiKey";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("❌ cURL error: " . curl_error($ch));
}
curl_close($ch);

// Parse XML
$xml = simplexml_load_string($response);
if (!$xml) {
    die("❌ Failed to parse XML from ISAMS");
}

echo "✅ Successfully connected to ISAMS<br><br>";

$studentsProcessed = 0;
$parentsProcessed = 0;

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

foreach ($pupilMap as $schoolId => $pupil) {
    $email = (string)$pupil->EmailAddress;
    $forename = (string)$pupil->Forename;
    $surname = (string)$pupil->Surname;
    $form = (string)$pupil->Form;
    $ncYear = (string)$pupil->NCYear;
    
    if (empty($email)) continue;
    
    // Note: We're NOT creating parent_id here - that will be linked via contacts
    $stmt = $pdo->prepare("
        INSERT INTO students (name, grade, class, created_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            grade = VALUES(grade),
            class = VALUES(class)
    ");
    
    $fullName = trim("$forename $surname");
    $stmt->execute([$fullName, $ncYear, $form]);
    $studentsProcessed++;
    
    echo "✅ Student: $fullName (Form: $form)<br>";
}

// Process Parents
echo "<br><h3>👨‍👩‍👧‍👦 Processing Parents...</h3>";

if (isset($xml->PupilManager->Contacts->Contact)) {
    foreach ($xml->PupilManager->Contacts->Contact as $contact) {
        $email = (string)$contact->EmailAddress;
        if (empty($email)) continue;
        
        $forename = (string)$contact->Forename;
        $surname = (string)$contact->Surname;
        $mobile = (string)$contact->Mobile;
        
        $fullName = trim("$forename $surname");
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'parent'");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        
        if (!$existingUser) {
            // Create new parent
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
            
            echo "✅ Parent: $fullName ($email)<br>";
            $parentsProcessed++;
            
            // TODO: Link children to this parent
            // You'll need to match students based on the Pupils->Pupil data
        }
    }
}

echo "<br><hr>";
echo "📊 <strong>Summary:</strong><br>";
echo "👨‍🎓 Students processed: $studentsProcessed<br>";
echo "👨‍👩‍👧‍👦 Parents processed: $parentsProcessed<br>";
echo "<br>✅ Sync complete!";