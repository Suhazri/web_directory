<?php
// For development: enable error display
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// IMPORTANT: Disable display_errors in production environments! Log errors to a file instead.

// Note on Session Security: Consider implementing more robust session management practices for production, 
// such as session fixation protection, secure cookie flags (HttpOnly, Secure), and regular session ID regeneration.
session_start();

// Redirect if already logged in
if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

require_once 'includes/radius_config.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input: Username and password from POST request.
    // The RADIUS library is expected to handle encoding for its attributes.
    // If $username were to be displayed directly on a page or used in a database query here,
    // it would need sanitization (e.g., htmlspecialchars() for display, prepared statements for SQL).
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        // Reminder: The PECL RADIUS extension must be installed and enabled in php.ini for authentication to work.
        $radius_handle = radius_auth_open();

        if (!radius_add_server($radius_handle, RADIUS_SERVER_IP, RADIUS_SERVER_PORT, RADIUS_SHARED_SECRET, RADIUS_TIMEOUT, RADIUS_MAX_RETRIES)) {
            $error = "Error adding RADIUS server: " . radius_strerror($radius_handle);
        } else {
            if (!radius_create_request($radius_handle, RADIUS_ACCESS_REQUEST)) {
                 $error = "Error creating RADIUS request: " . radius_strerror($radius_handle);
            } else {
                radius_put_attr($radius_handle, RADIUS_USER_NAME, $username);
                radius_put_attr($radius_handle, RADIUS_USER_PASSWORD, $password);
                // Assuming PAP, if your server needs CHAP or MSCHAPv1/v2, the attributes and values would differ.
                // For example, for MSCHAPv1, you might use RADIUS_CHAP_PASSWORD or RADIUS_MS_CHAP_PASSWORD
                // and the password value would be a CHAP digest, not plaintext.
                // radius_put_attr($radius_handle, RADIUS_NAS_IP_ADDRESS, $_SERVER['SERVER_ADDR']); // Optional: NAS IP Address

                $result = radius_send_request($radius_handle);

                if ($result == RADIUS_ACCESS_ACCEPT) {
                    // Store username in session. It's generally safe as is for session data,
                    // but always use htmlspecialchars() when outputting it to HTML (as done in index.php).
                    $_SESSION['username'] = $username;
                    $_SESSION['is_admin'] = ($username === 'admin'); // Simple admin check for demonstration
                    header('Location: index.php');
                    exit;
                } elseif ($result == RADIUS_ACCESS_REJECT) {
                    $error = "Invalid username or password."; // Clear and user-friendly.
                } else {
                    // This could be a timeout or other error
                    $error = "Error communicating with authentication server: " . radius_strerror($radius_handle) . " (Code: " . $result . ")"; // Clear, includes technical details useful for debugging.
                }
            }
        }
        radius_close($radius_handle);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <div id="error-message-placeholder" style="display:none;"></div> <!-- Hide if no error, but keep for potential JS use -->
    <?php endif; ?>
    <form method="POST" action="login.php">
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" aria-label="Username" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" aria-label="Password" required>
        </div>
        <button type="submit">Login</button>
    </form>
</body>
</html>
