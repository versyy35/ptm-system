<?php
/**
 * ISAMS Data Sync Script - FINAL FIX
 * Uses FamilyId from students to link ALL students to their parents
 */
require_once __DIR__ . '/../../app/config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>ISAMS Sync - PTM Portal (FamilyId Fix)</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .warning { color: orange; }
        .summary { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin-top: 20px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h2>ISAMS Data Sync - FamilyId Solution</h2>";
echo "<p>Syncing student and parent data using FamilyId...</p>";
echo "<hr>";

// Get ISAMS XML data
$apiKey = ISAMS_API_KEY;
$url = ISAMS_API_URL . "?apiKey=$apiKey";

echo "<p class='info'>Connecting to ISAMS API...</p>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "<p class='error'>cURL error: " . curl_error($ch) . "</p>";
    echo "</div></body></html>";
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "<p class='error'>ISAMS API returned HTTP code: $httpCode</p>";
    echo "</div></body></html>";
    exit;
}

// Parse XML
$xml = simplexml_load_string($response);
if (!$xml) {
    echo "<p class='error'>Failed to parse XML from ISAMS</p>";
    echo "</div></body></html>";
    exit;
}

echo "<p class='success'>Successfully connected to ISAMS</p>";

$studentsProcessed = 0;
$studentsUpdated = 0;
$parentsProcessed = 0;
$parentsUpdated = 0;
$linksCreated = 0;

// STEP 1: Build pupil map with FamilyId
echo "<h3>Step 1: Processing Students with FamilyId...</h3>";

$pupilMap = []; // pupilId => pupil data
$currentPupilBlocks = $xml->xpath('//CurrentPupils');

foreach ($currentPupilBlocks as $pupilBlock) {
    foreach ($pupilBlock->Pupil as $pupil) {
        $pupilId = (int)$pupil['Id'];
        
        if ($pupilId) {
            $pupilMap[$pupilId] = $pupil;
        }
    }
}

echo "<p>Found " . count($pupilMap) . " students in ISAMS</p>";

// Add this right after parsing XML in sync script
echo "<p><strong>DEBUG: Students in ISAMS XML</strong></p>";
echo "<p>CurrentPupils blocks found: " . count($currentPupilBlocks) . "</p>";

$totalPupilsInXML = 0;
foreach ($currentPupilBlocks as $pupilBlock) {
    $pupilCount = count($pupilBlock->Pupil);
    $totalPupilsInXML += $pupilCount;
    echo "<p>Pupils in this block: $pupilCount</p>";
}
echo "<p><strong>Total pupils in XML: $totalPupilsInXML</strong></p>";

// STEP 2: Import/Update Students WITH FamilyId
foreach ($pupilMap as $pupilId => $pupil) {
    $forename = trim((string)$pupil->Forename);
    $surname = trim((string)$pupil->Surname);
    $form = trim((string)$pupil->Form);
    $ncYear = trim((string)$pupil->NCYear);
    $familyId = trim((string)$pupil->FamilyId); // ← GET FAMILY ID!
    
    $fullName = trim("$forename $surname");
    
    // Check if student exists
    $stmt = $pdo->prepare("SELECT id FROM students WHERE isams_pupil_id = ?");
    $stmt->execute([$pupilId]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        // Insert new student WITH family_id
        $stmt = $pdo->prepare("
            INSERT INTO students (name, grade, class, isams_pupil_id, family_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$fullName, $ncYear, $form, $pupilId, $familyId]);
        $studentsProcessed++;
    } else {
        // Update existing student WITH family_id
        $stmt = $pdo->prepare("
            UPDATE students 
            SET grade = ?, class = ?, name = ?, family_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$ncYear, $form, $fullName, $familyId, $existing['id']]);
        $studentsUpdated++;
    }
}

echo "<p class='info'>New students: $studentsProcessed, Updated: $studentsUpdated</p>";

// STEP 3: Process Parents and extract FamilyId from linked pupils
echo "<br><h3>Step 2: Processing Parents and extracting FamilyId...</h3>";

$processedParents = [];

if (isset($xml->PupilManager->Contacts->Contact)) {
    echo "<p>Found " . count($xml->PupilManager->Contacts->Contact) . " contacts in ISAMS</p>";
    
    foreach ($xml->PupilManager->Contacts->Contact as $contact) {
        $contactId = (int)$contact['Id'];
        $email = trim((string)$contact->EmailAddress);
        
        if (empty($email) || !$contactId) continue;
        
        // Skip duplicates
        $personKey = strtolower($email);
        if (isset($processedParents[$personKey])) continue;
        $processedParents[$personKey] = true;
        
        $forename = trim((string)$contact->Forename);
        $surname = trim((string)$contact->Surname);
        $mobile = trim((string)$contact->Mobile);
        
        $fullName = trim("$forename $surname");
        if (empty($fullName)) $fullName = $email;
        
        // ============================================================
        // EXTRACT FAMILY ID FROM LINKED PUPILS
        // ============================================================
        $familyId = null;
        
        if (isset($contact->Pupils->Pupil)) {
            foreach ($contact->Pupils->Pupil as $linkedPupil) {
                $linkedPupilId = (int)$linkedPupil['Id'];
                
                // Get the FamilyId from this pupil
                if (isset($pupilMap[$linkedPupilId])) {
                    $familyId = trim((string)$pupilMap[$linkedPupilId]->FamilyId);
                    if ($familyId) {
                        break; // Got it! One pupil's FamilyId is enough
                    }
                }
            }
        }
        
        // If we couldn't get FamilyId from pupils, skip this parent
        if (!$familyId) {
            echo "<p class='warning'>⚠️ Skipping parent $fullName - no FamilyId found</p>";
            continue;
        }
        
        // ============================================================
        // INSERT/UPDATE PARENT WITH FAMILY ID
        // ============================================================
        
        // Check if parent exists
        $stmt = $pdo->prepare("
            SELECT u.id, p.id as parent_id
            FROM users u 
            LEFT JOIN parents p ON u.id = p.user_id 
            WHERE u.email = ? AND u.role = 'parent'
        ");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        
        if (!$existingUser) {
            // Create new parent WITH family_id
            try {
                $pdo->beginTransaction();
                
                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, name, role, is_active, created_at)
                    VALUES (?, ?, 'parent', 1, NOW())
                ");
                $stmt->execute([$email, $fullName]);
                $userId = $pdo->lastInsertId();
                
                // Insert parent WITH family_id
                $stmt = $pdo->prepare("
                    INSERT INTO parents (user_id, name, email, phone, isams_parent_id, family_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$userId, $fullName, $email, $mobile, $contactId, $familyId]);
                
                $pdo->commit();
                
                echo "<p class='success'>Added parent: $fullName (FamilyId: $familyId)</p>";
                $parentsProcessed++;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo "<p class='error'>Error creating parent $email: " . $e->getMessage() . "</p>";
            }
        } else {
            // Update existing parent WITH family_id
            if ($existingUser['parent_id']) {
                $stmt = $pdo->prepare("
                    UPDATE parents 
                    SET name = ?, email = ?, phone = ?, isams_parent_id = ?, family_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$fullName, $email, $mobile, $contactId, $familyId, $existingUser['parent_id']]);
                $parentsUpdated++;
                
                // Also update user table
                $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                $stmt->execute([$fullName, $existingUser['id']]);
            }
        }
    }
}

echo "<p class='info'>New parents: $parentsProcessed, Updated: $parentsUpdated</p>";

// STEP 4: Link ALL students to parents using FamilyId
echo "<br><h3>Step 3: Linking Students to Parents using FamilyId...</h3>";
echo "<p class='info'>This will link ALL students who share the same FamilyId with their parents...</p>";

$stmt = $pdo->prepare("
    UPDATE students s
    INNER JOIN parents p ON p.family_id = s.family_id
    SET s.parent_id = p.id
    WHERE s.parent_id IS NULL
      AND s.family_id IS NOT NULL
      AND p.family_id IS NOT NULL
");
$stmt->execute();
$linksCreated = $stmt->rowCount();

echo "<p class='success'>✅ Linked $linksCreated students to their parents!</p>";

// Check for remaining orphans
$stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE parent_id IS NULL");
$orphanCount = $stmt->fetch()['count'];

if ($orphanCount > 0) {
    echo "<p class='warning'>⚠️ Warning: $orphanCount students still don't have parents linked.</p>";
    echo "<p class='info'>Possible reasons:</p>";
    echo "<ul>";
    echo "<li>Parent doesn't have an email in ISAMS</li>";
    echo "<li>Parent contact not in ISAMS Contacts list</li>";
    echo "<li>FamilyId mismatch between student and available parents</li>";
    echo "</ul>";
    
    // Show sample orphans with their FamilyIds
    $stmt = $pdo->query("
        SELECT name, grade, family_id 
        FROM students 
        WHERE parent_id IS NULL 
        LIMIT 10
    ");
    echo "<p><strong>Sample orphaned students:</strong></p>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th style='padding:5px;'>Name</th><th style='padding:5px;'>Grade</th><th style='padding:5px;'>FamilyId</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td style='padding:5px;'>{$row['name']}</td>";
        echo "<td style='padding:5px;'>{$row['grade']}</td>";
        echo "<td style='padding:5px;'>{$row['family_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Summary
echo "<div class='summary'>";
echo "<h3>✅ Sync Summary</h3>";
echo "<p><strong>Students added:</strong> $studentsProcessed</p>";
echo "<p><strong>Students updated:</strong> $studentsUpdated</p>";
echo "<p><strong>Parents added:</strong> $parentsProcessed</p>";
echo "<p><strong>Parents updated:</strong> $parentsUpdated</p>";
echo "<p><strong>Parent-Child links created:</strong> <span style='font-size: 1.5em; color: green;'>$linksCreated</span></p>";
echo "<p><strong>Students still without parents:</strong> $orphanCount</p>";
echo "<br><p class='success'><strong>Sync completed successfully!</strong></p>";
echo "</div>";

// Final verification
echo "<br><h3>📊 Verification</h3>";
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM students) as total_students,
        (SELECT COUNT(*) FROM students WHERE parent_id IS NOT NULL) as linked_students,
        (SELECT COUNT(*) FROM students WHERE family_id IS NOT NULL) as students_with_family_id,
        (SELECT COUNT(*) FROM parents) as total_parents,
        (SELECT COUNT(*) FROM parents WHERE family_id IS NOT NULL) as parents_with_family_id
");
$stats = $stmt->fetch();

echo "<table border='1' style='margin: 10px 0; border-collapse: collapse;'>";
echo "<tr style='background:#f0f0f0;'><th style='padding:10px;'>Metric</th><th style='padding:10px;'>Count</th></tr>";
echo "<tr><td style='padding:10px;'>Total Students</td><td style='padding:10px; text-align:center;'>{$stats['total_students']}</td></tr>";
echo "<tr style='background:#d4edda;'><td style='padding:10px;'><strong>Students Linked to Parents</strong></td><td style='padding:10px; text-align:center;'><strong>{$stats['linked_students']}</strong></td></tr>";
echo "<tr><td style='padding:10px;'>Students with FamilyId</td><td style='padding:10px; text-align:center;'>{$stats['students_with_family_id']}</td></tr>";
echo "<tr><td style='padding:10px;'>Total Parents</td><td style='padding:10px; text-align:center;'>{$stats['total_parents']}</td></tr>";
echo "<tr><td style='padding:10px;'>Parents with FamilyId</td><td style='padding:10px; text-align:center;'>{$stats['parents_with_family_id']}</td></tr>";
echo "</table>";

echo "<br><p><a href='?page=admin'>Back to Admin Dashboard</a></p>";

echo "</div></body></html>";
?>