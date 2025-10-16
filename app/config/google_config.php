<?php
// Use the SAME credentials from your Exam System
define('GOOGLE_CLIENT_ID', 'your-existing-client-id-from-exam-system');
define('GOOGLE_CLIENT_SECRET', 'your-existing-secret-from-exam-system');

// Update redirect URI for PTM
define('GOOGLE_REDIRECT_URI', 'http://localhost:8000/google_login.php');
?>