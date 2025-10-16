<?php
session_start();
require_once "vendor/autoload.php";
require_once "app/config/config.php";
require_once "app/config/google_config.php";

// Set up Google client (same as your Exam System)
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");
$client->setPrompt('select_account consent');

// Handle Google callback
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        die("Google SSO failed: " . htmlspecialchars($token['error']));
    }

    $client->setAccessToken($token['access_token']);

    // Get user info
    $oauth = new Google_Service_Oauth2($client);
    $userInfo = $oauth->userinfo->get();

    $email = $userInfo->email;
    $name = $userInfo->name;

    // 🔐 Restrict by domain (same as Exam System)
    $allowed_domain = 'kl.his.edu.my';
    $domain = substr(strrchr($email, "@"), 1);

    if ($domain !== $allowed_domain) {
        echo "<script>alert('Access denied. Only @$allowed_domain emails are allowed.'); window.location.href='?page=login';</script>";
        exit;
    }

    // Check if teacher exists in PTM database
    $stmt = $conn->prepare("
        SELECT t.id, t.user_id, u.name 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Teacher doesn't exist - show error
        echo "<script>alert('Teacher account not found. Please contact administrator.'); window.location.href='?page=login';</script>";
        exit;
    }

    $teacher = $result->fetch_assoc();
    $stmt->close();

    // Set PTM teacher session
    $_SESSION['teacher_id'] = $teacher['id'];
    $_SESSION['user_email'] = $email;
    $_SESSION['role'] = 'teacher';
    $_SESSION['display_name'] = $teacher['name'];

    header("Location: ?page=teacher");
    exit;
}

// Step 1: Redirect to Google for login
$auth_url = $client->createAuthUrl();
header("Location: $auth_url");
exit;
?>