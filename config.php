<?php
// --- Configuration Settings ---

// ** SECURITY WARNINGS **
// 1. This file contains sensitive information.
// 2. For production, MOVE THIS FILE OUTSIDE of your web server's document root.
// 3. Ensure this file has restrictive file permissions (e.g., 600 or 640).
// 4. The .htpasswd file should also be outside the web root or heavily protected.

// --- Paths ---
// IMPORTANT: For AuthUserFile, Apache typically needs an ABSOLUTE server path.
// For other paths, absolute paths are also recommended for clarity and reliability.
// Example for .htpasswd (if in project root, for development ONLY):
define('HTPASSWD_PATH', __DIR__ . '/.htpasswd'); 
// Example for .htpasswd (ideal, outside web root - adjust to your server):
// define('HTPASSWD_PATH', '/path/to/secure/directory/.htpasswd');

// User base directory for creating individual user folders
// Example for user dirs (if /app/users/ is web accessible and writable by PHP):
define('USER_BASE_DIR', __DIR__ . '/users/'); // Assuming 'users' directory in project root
// Example for user dirs (alternative, if /app/users/ is used directly):
// define('USER_BASE_DIR', '/app/users/'); 


// --- Admin User Credentials (for admin.php panel) ---
define('ADMIN_USERNAME', 'admin');
// HASHED password for 'adminpass123'. Generated using password_hash("adminpass123", PASSWORD_DEFAULT).
define('ADMIN_PASSWORD_HASH', '$2y$10$N.gL21HnC9Qcn3aZ2xZg7uP0sXyE.Nq.jL.A/v9L5i.LhO5iR0E8W');

// --- Site Admin Username (for index.php directory listing logic) ---
// This is the username (from .htpasswd) that gets full directory visibility in index.php
define('SITE_ADMIN_USERNAME_HTACCESS', 'admin'); // This user must exist in .htpasswd

// --- Error Logging ---
// define('ERROR_LOG_FILE', __DIR__ . '/error.log'); // Optional: path to a custom error log file
// ini_set('log_errors', 1);
// if (defined('ERROR_LOG_FILE')) {
//     ini_set('error_log', ERROR_LOG_FILE);
// }

// Function to ensure base directories exist (used by admin.php)
if (!function_exists('ensure_directory_exists')) {
    function ensure_directory_exists($dir_path, $permissions = 0755) {
        if (!is_dir($dir_path)) {
            // Check if parent directory is writable before attempting to create.
            // This is a simplified check; real-world scenarios might need more robust permission handling.
            $parent_dir = dirname($dir_path);
            if (!is_writable($parent_dir)) {
                // Log or handle error: "Parent directory not writable: $parent_dir"
                // error_log("Attempted to create directory '$dir_path' but parent '$parent_dir' is not writable.");
                return false;
            }
            if (!mkdir($dir_path, $permissions, true)) {
                // Log or handle error: "Failed to create directory: $dir_path"
                // error_log("Failed to create directory: $dir_path despite parent being writable.");
                return false;
            }
        } elseif (!is_writable($dir_path)) {
            // Log or handle error: "Directory exists but is not writable: $dir_path"
            // error_log("Directory '$dir_path' exists but is not writable.");
            return false;
        }
        return true;
    }
}

// Ensure the USER_BASE_DIR exists when config is loaded (for user folder creation)
// ensure_directory_exists(USER_BASE_DIR); // Decided to do this in admin.php when needed.

// Ensure the directory for HTPASSWD_PATH exists (if it's not just the file itself)
// ensure_directory_exists(dirname(HTPASSWD_PATH)); // Decided to do this in admin.php when needed.

?>
