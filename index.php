<?php
// For development: enable error display
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// IMPORTANT: Disable display_errors in production environments! Log errors to a file instead.

// IMPORTANT: Disable display_errors in production environments! Log errors to a file instead.

require_once __DIR__ . '/config.php';

// Start PHP session for admin panel state
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Retrieve Query Parameters
$view = $_GET['view'] ?? 'default';
$action = $_GET['action'] ?? null;

// --- Action Handling ---
if ($action) {
    switch ($action) {
        case 'admin_login':
            // Placeholder: Logic for admin login form submission will go here
            // Will set $_SESSION['admin_logged_in']
            // Will redirect to index.php?view=admin
            // For now, simulate successful login for testing purposes:
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_user']) && isset($_POST['admin_pass'])) {
                $submitted_user = $_POST['admin_user'];
                $submitted_pass = $_POST['admin_pass'];

                if ($submitted_user === ADMIN_USERNAME && password_verify($submitted_pass, ADMIN_PASSWORD_HASH)) {
                    $_SESSION['admin_logged_in'] = true;
                } else {
                    $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => 'Invalid admin username or password.'];
                }
            }
            header('Location: index.php?view=admin');
            exit;
        case 'admin_logout':
            unset($_SESSION['admin_logged_in']);
            session_destroy(); // Fully destroy session on admin logout
            header('Location: index.php?view=admin'); // Redirect to admin login
            exit;
        case 'add_user':
            if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
                $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => 'Admin access required.'];
                header('Location: index.php?view=admin');
                exit;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $new_username_raw = trim($_POST['new_username']);
                $new_password_raw = trim($_POST['new_password']);

                if (empty($new_username_raw)) {
                    $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => 'Username cannot be empty.'];
                } else {
                    $new_username = preg_replace('/[^a-zA-Z0-9_-]/', '', $new_username_raw);
                    if (empty($new_username) || $new_username !== $new_username_raw) {
                        $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => 'Username contains invalid characters or is empty. Only alphanumeric, underscore, and hyphen.'];
                    } elseif (ensure_directory_exists(USER_BASE_DIR) && file_exists(USER_BASE_DIR . $new_username)) {
                        $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "Error: Directory for '{$new_username}' already exists."];
                    } else {
                        $user_exists_in_htpasswd = false;
                        if (ensure_directory_exists(dirname(HTPASSWD_PATH)) && file_exists(HTPASSWD_PATH) && is_readable(HTPASSWD_PATH)) {
                            $htpasswd_lines = file(HTPASSWD_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            foreach ($htpasswd_lines as $line) {
                                if (strpos($line, $new_username . ':') === 0) {
                                    $user_exists_in_htpasswd = true;
                                    break;
                                }
                            }
                        }
                        if ($user_exists_in_htpasswd) {
                            $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "Error: Username '{$new_username}' already in .htpasswd."];
                        } elseif (empty($new_password_raw)) {
                            $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => 'Password cannot be empty.'];
                        } else {
                            $salt = '$1$' . substr(bin2hex(random_bytes(8)), 0, 8) . '$';
                            $hashed_password = crypt($new_password_raw, $salt);
                            $htpasswd_entry = $new_username . ":" . $hashed_password . "\n";

                            if (!ensure_directory_exists(dirname(HTPASSWD_PATH))) {
                                $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "Error: .htpasswd directory issue."];
                            } elseif (file_put_contents(HTPASSWD_PATH, $htpasswd_entry, FILE_APPEND | LOCK_EX) === false) {
                                $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "Error: Failed to write to .htpasswd. Check permissions."];
                            } else {
                                if (!ensure_directory_exists(USER_BASE_DIR)) {
                                    $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "Error: User base directory issue."];
                                    $ht_contents = file_get_contents(HTPASSWD_PATH);
                                    $ht_contents = str_replace($htpasswd_entry, '', $ht_contents);
                                    file_put_contents(HTPASSWD_PATH, $ht_contents, LOCK_EX);
                                } else {
                                    if (mkdir(USER_BASE_DIR . $new_username, 0755)) {
                                        $_SESSION['admin_action_message'] = ['type' => 'success', 'text' => "User '{$new_username}' added and directory created."];
                                    } else {
                                        $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "Error: Failed to create directory for '{$new_username}'. .htpasswd entry made."];
                                        $ht_contents = file_get_contents(HTPASSWD_PATH);
                                        $ht_contents = str_replace($htpasswd_entry, '', $ht_contents);
                                        file_put_contents(HTPASSWD_PATH, $ht_contents, LOCK_EX);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            header('Location: index.php?view=admin');
            exit;
        case 'delete_user':
            if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
                $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => 'Admin access required.'];
                header('Location: index.php?view=admin');
                exit;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username_to_delete = trim($_POST['username_to_delete']);
                if (empty($username_to_delete)) {
                    $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => 'Invalid username for deletion.'];
                } elseif ($username_to_delete === ADMIN_USERNAME) {
                    $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => 'Primary admin cannot be deleted here.'];
                } else {
                    $htpasswd_lines_updated = [];
                    $user_found_in_htpasswd = false;
                    $htpasswd_read_error = false;

                    if (ensure_directory_exists(dirname(HTPASSWD_PATH)) && file_exists(HTPASSWD_PATH) && is_readable(HTPASSWD_PATH)) {
                        $htpasswd_lines = file(HTPASSWD_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        foreach ($htpasswd_lines as $line) {
                            if (strpos($line, $username_to_delete . ':') === 0) {
                                $user_found_in_htpasswd = true;
                            } else {
                                $htpasswd_lines_updated[] = $line;
                            }
                        }
                    } else {
                        $htpasswd_read_error = true;
                        $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "Error: .htpasswd file not found or not readable."];
                    }

                    if (!$htpasswd_read_error) {
                        if (!$user_found_in_htpasswd) {
                            $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "Error: User '{$username_to_delete}' not found."];
                        } else {
                            if (file_put_contents(HTPASSWD_PATH, implode("\n", $htpasswd_lines_updated) . (empty($htpasswd_lines_updated) ? "" : "\n"), LOCK_EX) === false) {
                                $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "Error: Failed to update .htpasswd. Check permissions."];
                            } else {
                                $user_dir_to_delete = rtrim(USER_BASE_DIR, '/') . '/' . $username_to_delete;
                                if (is_dir($user_dir_to_delete)) {
                                    if (delete_directory_recursively($user_dir_to_delete)) {
                                        $_SESSION['admin_action_message'] = ['type' => 'success', 'text' => "User '{$username_to_delete}' and directory deleted."];
                                    } else {
                                        $_SESSION['admin_action_message'] = ['type' => 'error', 'text' => "User '{$username_to_delete}' removed from .htpasswd, but failed to delete directory."];
                                    }
                                } else {
                                    $_SESSION['admin_action_message'] = ['type' => 'success', 'text' => "User '{$username_to_delete}' removed from .htpasswd. Directory not found."];
                                }
                            }
                        }
                    }
                }
            }
            header('Location: index.php?view=admin');
            exit;
        case 'http_logout':
            // This will be handled by a separate file logout_http_auth.php as previously designed,
            // or directly here if preferred. For now, link will be to logout_http_auth.php
            // and this case can be removed if logout_http_auth.php is used.
            // If handled here:
            // header('WWW-Authenticate: Basic realm="Restricted Content - Please Login"');
            header('WWW-Authenticate: Basic realm="Restricted Content - Please Login"');
            header('HTTP/1.0 401 Unauthorized');
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Logout Attempted</title>
                <link rel="stylesheet" href="css/style.css">
                <style>
                    /* Ensure body is not flex-centered for this specific page if style.css has a general body flex rule */
                    body.logout-page { 
                        display: block; /* Override flex for normal page flow */
                        padding: 20px;
                        text-align: center;
                    }
                    .logout-container {
                        max-width: 600px;
                        margin: 50px auto;
                        padding: 20px;
                        background-color: #fff;
                        border: 1px solid #ddd;
                        border-radius: 8px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                </style>
            </head>
            <script>
                // Add a class to body when this specific logout action is triggered
                document.addEventListener('DOMContentLoaded', function() {
                    document.body.classList.add('logout-page');
                });
            </script>
            <body>
                <div class="logout-container">
                    <h1>Logout Attempted</h1>
                    <p>You have attempted to log out. Due to the nature of HTTP Basic Authentication, your browser might still cache your credentials.</p>
                    <p>To ensure you are fully logged out, please close all browser windows/tabs for this site.</p>
                    <p><a href="index.php">Return to main page</a> (You might be prompted to log in again or your browser might use cached credentials).</p>
                </div>
            </body>
            </html>
HTML;
            exit; 
        // default:
            // Optional: handle unknown actions
            // error_log("Unknown action: " . $action);
    }
    // Actions might redirect and exit, or set variables for the view.
    // If an action doesn't redirect, ensure it doesn't fall through to unintended view rendering.
}

// --- View Handling ---

// Determine $authenticated_user (HTTP Basic Auth) - needed for default view and admin check
$authenticated_user = $_SERVER['PHP_AUTH_USER'] ?? null;

// Determine body class based on view and state
$body_class = 'view-default'; // Default class
if ($action === 'http_logout') {
    // Note: The http_logout action already outputs its own complete HTML page and exits.
    // So, this $body_class might not be used by it unless we restructure http_logout.
    // For now, assuming http_logout is self-contained. If it were to use the main layout:
    // $body_class = 'view-logout-page';
} elseif ($view === 'admin') {
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
        $body_class = 'view-admin-panel';
    } else {
        $body_class = 'view-admin-login';
    }
}

// .htaccess should protect index.php, so $authenticated_user should generally be set.
// If not set, and not an admin action/view that bypasses HTTP auth (like admin login form),
// it's an issue. However, the primary .htaccess rule on index.php itself is the main guard.
if (is_null($authenticated_user) && $body_class !== 'view-admin-login' && $action !== 'admin_login' && $action !== 'http_logout') {
    // This case implies .htaccess might not be working or this specific route is not covered.
    // For now, we assume .htaccess protects all access to index.php initially.
    // If specific views *within* index.php were to be unprotected by .htaccess (not recommended for this app structure),
    // more complex checks would be needed here.
}


// HTML Document Start
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>' . ($view === 'admin' ? 'Admin Panel' : 'User Dashboard') . '</title>'; // Title can also be made more dynamic
echo '<link rel="stylesheet" href="css/style.css">';
echo '</head>';
echo '<body class="' . htmlspecialchars($body_class) . '">'; // Apply dynamic body class

// Display admin action messages if any
if (isset($_SESSION['admin_action_message'])) {
    $admin_action_message_data = $_SESSION['admin_action_message'];
    echo '<div id="admin-messages-placeholder-fixed" style="position:fixed; top:10px; left:50%; transform:translateX(-50%); z-index:1000; width:80%; max-width:500px;">'; // Basic fixed positioning
    echo '  <div class="' . ($admin_action_message_data['type'] === 'error' ? 'error-message' : 'success-message') . '">';
    echo htmlspecialchars($admin_action_message_data['text']);
    echo '  </div>';
    echo '</div>';
    unset($_SESSION['admin_action_message']);
}


// Display content based on $view
switch ($view) {
    case 'admin':
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            // Display Admin Login Form
            ?>
            <div class="admin-login-container">
                <h2>Admin Login</h2>
                <?php /* Display login error if any (though admin_action_message is usually for post-action feedback) */ ?>
                <form method="POST" action="index.php?action=admin_login">
                    <div>
                        <label for="admin_user_login">Username:</label> <?php /* Changed id to avoid conflict */ ?>
                        <input type="text" id="admin_user_login" name="admin_user" required>
                    </div>
                    <div>
                        <label for="admin_pass_login">Password:</label> <?php /* Changed id to avoid conflict */ ?>
                        <input type="password" id="admin_pass_login" name="admin_pass" required>
                    </div>
                    <button type="submit">Login</button>
                </form>
            </div>
            <?php
        } else {
            // Display Admin Panel
            $existing_users_admin_view = [];
            if (ensure_directory_exists(dirname(HTPASSWD_PATH)) && file_exists(HTPASSWD_PATH) && is_readable(HTPASSWD_PATH)) {
                $lines = file(HTPASSWD_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2 && !empty($parts[0])) {
                        $existing_users_admin_view[] = htmlspecialchars($parts[0]);
                    }
                }
            }
            ?>
            <div class="container">
                <header class="dashboard-header">
                    <h1>Admin Panel - User Management</h1>
                    <p><a href="index.php?action=admin_logout">Admin Logout</a></p>
                </header>
                <main class="dashboard-main">
                    <div id="admin-messages-placeholder">
                        <?php /* This is where messages set by actions will appear after redirect, handled by the global message display above now */ ?>
                    </div>

                    <section id="user-list-section">
                        <h2>Existing Users (.htpasswd)</h2>
                        <div id="user-list-placeholder">
                            <?php if (empty($existing_users_admin_view)): ?>
                                <p>No users found in .htpasswd file.</p>
                            <?php else: ?>
                                <ul class="user-list">
                                    <?php foreach ($existing_users_admin_view as $user_item): ?>
                                        <li>
                                            <span class="username-display"><?php echo $user_item; ?></span>
                                            <?php if ($user_item !== ADMIN_USERNAME): ?>
                                            <form method="POST" action="index.php?action=delete_user" class="delete-user-form">
                                                <input type="hidden" name="username_to_delete" value="<?php echo $user_item; ?>">
                                                <button type="submit" class="button-delete" onclick="return confirm('Are you sure you want to delete user \'<?php echo $user_item; ?>\' and their directory?');">Delete</button>
                                            </form>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section id="add-user-section">
                        <h2>Add New User</h2>
                        <form method="POST" action="index.php?action=add_user">
                            <div>
                                <label for="new_username">New Username:</label>
                                <input type="text" id="new_username" name="new_username" required>
                            </div>
                            <div>
                                <label for="new_password">New Password:</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            <button type="submit">Add User</button>
                        </form>
                    </section>
                </main>
            </div>
            <?php
        }
        break;
    case 'default':
    default:
        // This is where the current index.php logic for listing user directories goes.
        if (is_null($authenticated_user)) {
            // Fallback if .htaccess somehow didn't catch this.
            header('HTTP/1.0 401 Unauthorized');
            echo "Access Denied: HTTP Basic Authentication required for this area.";
            error_log("index.php: default view accessed without PHP_AUTH_USER. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
            exit;
        }

        $is_site_admin = ($authenticated_user === SITE_ADMIN_USERNAME_HTACCESS);
        $accessible_dirs = [];
        $error_message = null;

        if (!ensure_directory_exists(USER_BASE_DIR) || !is_readable(USER_BASE_DIR)) {
            $error_message = "Error: User base directory ('" . htmlspecialchars(USER_BASE_DIR) . "') not accessible or found. Please contact an administrator.";
        } else {
            if ($is_site_admin) {
                $items = scandir(USER_BASE_DIR);
                if ($items !== false) {
                    foreach ($items as $item) {
                        if ($item !== '.' && $item !== '..' && is_dir(USER_BASE_DIR . $item)) {
                            $accessible_dirs[] = $item;
                        }
                    }
                } else {
                    $error_message = "Error: Could not scan user base directory.";
                }
            } else {
                $user_dir_path = USER_BASE_DIR . $authenticated_user;
                if (is_dir($user_dir_path)) {
                    $accessible_dirs[] = $authenticated_user;
                }
            }
            if (empty($accessible_dirs) && !$error_message) {
                if (!$is_site_admin && !is_dir(USER_BASE_DIR . $authenticated_user)) {
                    $error_message = "No personal directory found for user '" . htmlspecialchars($authenticated_user) . "'.";
                } elseif ($is_site_admin && empty($accessible_dirs)) {
                    $error_message = "No user directories found in '" . htmlspecialchars(USER_BASE_DIR) . "'.";
                } elseif (empty($accessible_dirs)) {
                     $error_message = "No accessible directories found.";
                }
            }
        }
        // Display User Directory Listing (reintegrated from old index.php)
        ?>
        <div class="container">
            <header class="dashboard-header">
                <h1>Welcome, <?php echo htmlspecialchars($authenticated_user); ?>!</h1>
                <?php if ($is_site_admin): ?>
                    <p style="margin-left: 15px; font-weight: bold; color: #007bff;">(Site Administrator)</p>
                <?php endif; ?>
                 <p style="margin-left: auto;"><a href="index.php?action=http_logout">Logout</a></p>
            </header>
            <main class="dashboard-main">
                <h2>Accessible Directories:</h2>
                <?php if ($error_message): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <?php elseif (!empty($accessible_dirs)): ?>
                    <ul class="directory-list">
                        <?php foreach ($accessible_dirs as $dir): ?>
                            <li><?php echo htmlspecialchars($dir); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No accessible directories found.</p>
                <?php endif; ?>
                 <?php if ($is_site_admin): // Link to admin panel for site admin ?>
                    <p style="margin-top: 20px;"><a href="index.php?view=admin" class="button">Access Admin Panel</a></p>
                <?php endif; ?>
            </main>
        </div>
        <?php
        break;
}

// HTML Document End
echo '</body></html>';
