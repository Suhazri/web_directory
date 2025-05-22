<?php
// For development: enable error display (less critical for this simple script)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
// IMPORTANT: Disable display_errors in production.

// Note on Session Security: This script correctly destroys the session.
// Ensure overall session management (e.g., in login.php, index.php) follows best practices.
session_start(); // 1. Start Session

// 2. Unset All Session Variables
$_SESSION = array();

// 3. Destroy Session
session_destroy();

// 4. Redirect to Login Page
header('Location: login.php');

// 5. Exit
exit;
?>
