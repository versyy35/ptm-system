<?php
/**
 * XML Structure Explorer
 * Deep dive into ISAMS XML structure to find Staff and Subjects
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
curl_close($ch);

$xml = simplexml_load_string($response);

function exploreNode($node, $path = '', $depth = 0, $maxDepth = 4) {
    if ($depth > $maxDepth) return;
    
    $indent = str_repeat('  ', $depth);
    $nodeName = $node->getName();
    $currentPath = $path ? $path . '/' . $nodeName : $nodeName;
    
    // Count children
    $childCount = count($node->children());
    $hasAttributes = count($node->attributes()) > 0;
    
    // Show this node
    echo $indent . "📁 <strong>" . htmlspecialchars($nodeName) . "</strong>";
    if ($hasAttributes) {
        echo " [Attributes: ";
        foreach ($node->attributes() as $key => $value) {
            echo htmlspecialchars($key) . "=\"" . htmlspecialchars((string)$value) . "\" ";
        }
        echo "]";
    }
    echo " - <span style='color: #007bff;'>" . $childCount . " children</span>";
    echo " - <code style='color: #28a745;'>" . htmlspecialchars($currentPath) . "</code><br>";
    
    // If this is a repeated element, show sample data
    if ($childCount > 0) {
        $firstChild = $node->children()[0];
        $childName = $firstChild->getName();
        $sameChildren = 0;
        foreach ($node->children() as $child) {
            if ($child->getName() === $childName) {
                $sameChildren++;
            }
        }
        
        if ($sameChildren > 1) {
            echo $indent . "  └─ <em style='color: #6c757d;'>Contains {$sameChildren} {$childName} elements</em><br>";
            
            // Show first element structure
            if ($depth < $maxDepth - 1) {
                echo $indent . "  └─ <em>Sample {$childName} structure:</em><br>";
                exploreNode($firstChild, $currentPath, $depth + 2, $depth + 2);
            }
        } else {
            // Explore all unique children
            foreach ($node->children() as $child) {
                exploreNode($child, $currentPath, $depth + 1, $maxDepth);
            }
        }
    } else {
        // Leaf node - show value if not empty
        $value = trim((string)$node);
        if (!empty($value) && strlen($value) < 100) {
            echo $indent . "  └─ <span style='color: #dc3545;'>Value: " . htmlspecialchars($value) . "</span><br>";
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>XML Structure Explorer - PTM Portal</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 20px;
            line-height: 1.8;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #007bff;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #28a745;
            margin-top: 30px;
            background: #e7f3ff;
            padding: 10px;
            border-left: 4px solid #007bff;
        }
        .search-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .search-box h3 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        .explorer {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 13px;
        }
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
        }
        .highlight {
            background: #fff3cd;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔬 XML Structure Explorer</h1>
        <p>Deep analysis of ISAMS API XML structure to find Staff and Subjects data</p>
        
        <div class="search-box">
            <h3>🎯 Looking For:</h3>
            <ul>
                <li><strong>Staff/Teachers:</strong> Should have Name, Email, StaffId fields</li>
                <li><strong>Subjects:</strong> Should have Id, Name, Code fields</li>
                <li><strong>Teacher Assignments:</strong> Links between Staff and Teaching Sets</li>
            </ul>
        </div>
        
        <h2>📊 Complete XML Tree Structure</h2>
        <div class="explorer">
            <?php
            exploreNode($xml, '', 0, 5);
            ?>
        </div>
        
        <h2>🔍 Specific Path Tests</h2>
        <div class="explorer">
            <?php
            $pathsToTest = [
                '//Staff',
                '//HRManager/Staff',
                '//HRManager/CurrentStaff',
                '//HRManager//Staff',
                '//TeachingManager/Subjects',
                '//TeachingManager/Subjects/Subject',
                '//TeachingManager//Subject',
                '//Subjects/Subject',
                '//Teachers',
                '//TeachingManager/Teachers'
            ];
            
            echo "<table style='width: 100%; border-collapse: collapse;'>";
            echo "<tr style='background: #007bff; color: white;'>";
            echo "<th style='padding: 10px; text-align: left;'>XPath</th>";
            echo "<th style='padding: 10px;'>Count</th>";
            echo "<th style='padding: 10px; text-align: left;'>Status</th>";
            echo "</tr>";
            
            foreach ($pathsToTest as $path) {
                $result = $xml->xpath($path);
                $count = $result ? count($result) : 0;
                $status = $count > 0 ? "<span style='color: green; font-weight: bold;'>✓ FOUND</span>" : "<span style='color: #999;'>Not found</span>";
                
                echo "<tr style='border-bottom: 1px solid #ddd;'>";
                echo "<td style='padding: 8px;'><code>" . htmlspecialchars($path) . "</code></td>";
                echo "<td style='padding: 8px; text-align: center;'><strong>{$count}</strong></td>";
                echo "<td style='padding: 8px;'>{$status}</td>";
                echo "</tr>";
            }
            echo "</table>";
            ?>
        </div>
        
        <h2>📝 HRManager Contents</h2>
        <div class="explorer">
            <?php
            $hrManager = $xml->xpath('//HRManager');
            if ($hrManager && count($hrManager) > 0) {
                echo "<p>Found HRManager section. Exploring its contents:</p>";
                exploreNode($hrManager[0], 'HRManager', 0, 3);
            } else {
                echo "<p style='color: red;'>HRManager not found</p>";
            }
            ?>
        </div>
        
        <h2>📚 TeachingManager Contents</h2>
        <div class="explorer">
            <?php
            $teachingManager = $xml->xpath('//TeachingManager');
            if ($teachingManager && count($teachingManager) > 0) {
                echo "<p>Found TeachingManager section. Exploring its contents:</p>";
                exploreNode($teachingManager[0], 'TeachingManager', 0, 3);
            } else {
                echo "<p style='color: red;'>TeachingManager not found</p>";
            }
            ?>
        </div>
        
        <h2>👥 Teacher Reference in Sets</h2>
        <div class="explorer">
            <?php
            $sets = $xml->xpath('//Sets/Set');
            if ($sets && count($sets) > 0) {
                echo "<p>Analyzing how teachers are referenced in teaching sets:</p>";
                $firstSet = $sets[0];
                
                echo "<strong>Sample Set:</strong><br>";
                echo "Set ID: " . $firstSet['Id'] . "<br>";
                echo "Set Code: " . (string)$firstSet->SetCode . "<br><br>";
                
                if (isset($firstSet->Teacher)) {
                    echo "<strong>Teacher field:</strong> <span class='highlight'>" . (string)$firstSet->Teacher . "</span><br>";
                    echo "<em>This appears to be a teacher identifier code</em><br><br>";
                }
                
                if (isset($firstSet->Teachers)) {
                    echo "<strong>Teachers section:</strong><br>";
                    echo "<pre>";
                    print_r($firstSet->Teachers);
                    echo "</pre>";
                }
            }
            ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="api_diagnostic.php" class="btn">🔍 Back to Diagnostic</a>
            <a href="?page=admin" class="btn">← Admin Dashboard</a>
        </div>
    </div>
</body>
</html>