<?php
// For development: enable error display
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// IMPORTANT: Disable display_errors in production environments! Log errors to a file instead.

// Note on Session Security: Consider implementing more robust session management practices for production, 
// such as session fixation protection, secure cookie flags (HttpOnly, Secure), and regular session ID regeneration.
session_start();

// Check if user is logged in, otherwise redirect to login page
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Retrieve session variables
$username = $_SESSION['username']; // Username is from session, set after successful RADIUS auth.
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;

// Define the base directory for listing
// Security Note: The visibility of directories is controlled by this script.
// Ensure that the web server itself (e.g., Apache/Nginx) is also configured to prevent
// unauthorized direct access to files within /var/www/html if sensitive data is present.
// This script primarily controls the *listing* of directories.
$base_dir = '/var/www/html'; // This is a common default, adjust if your environment differs (e.g. /app)

$accessible_dirs = [];
$error_message = null;

// Directory listing logic
if (!is_readable($base_dir) || !is_dir($base_dir)) {
    $error_message = "Error: Base directory not accessible."; // Clear and informative for admins/debugging.
} else {
    if ($is_admin) {
        // Admin can see all subdirectories in $base_dir
        $items = scandir($base_dir);
        if ($items !== false) {
            foreach ($items as $item) {
                // Directory names from scandir are generally safe but always use htmlspecialchars() for output.
                if ($item !== '.' && $item !== '..' && is_dir($base_dir . '/' . $item)) {
                    $accessible_dirs[] = $item;
                }
            }
        } else {
            $error_message = "Error: Could not scan base directory."; // Clear message.
        }
    } else {
        // Normal user can only see their own directory (if it exists)
        // The username (directory name) comes from the session, which was set from RADIUS.
        // If directory names could be arbitrary user input, more validation might be needed.
        $user_dir_path = $base_dir . '/' . $username;
        if (is_dir($user_dir_path)) {
            $accessible_dirs[] = $username; // Add the username, which is the directory name
        }
    }

    if (empty($accessible_dirs) && !$error_message) {
        // This condition is met if the user is not admin and their directory doesn't exist,
        // or if admin and base_dir is empty or contains no subdirectories.
        if (!$is_admin && !is_dir($base_dir . '/' . $username)) {
            $error_message = "No personal directory found."; // Clear user-facing message.
        } elseif (empty($accessible_dirs)) { // Handles admin case with no dirs, or user already handled by above
             $error_message = "No accessible directories found."; // Clear user-facing message.
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-view">
    <div class="container">
        <header class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($username); // Output sanitization for username ?>!</h1>
            <p><a href="logout.php">Logout</a></p>
        </header>

        <main class="dashboard-main">
            <h2>Accessible Directories:</h2>
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); // Output sanitization for error messages ?></p>
            <?php elseif (!empty($accessible_dirs)): ?>
                <ul class="directory-list">
                    <?php foreach ($accessible_dirs as $dir): ?>
                        <li><?php echo htmlspecialchars($dir); // Output sanitization for directory names ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No accessible directories found.</p> <!-- Fallback message, already covered by $error_message logic -->
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
