# Testing Guide: Single-Page PHP User Management System

This document outlines the steps to test the single-page PHP application that uses `.htaccess` for HTTP Basic Authentication and provides admin functionalities for user and directory management. The entire application is served via `index.php` using URL query parameters.

## I. Prerequisites & Setup

1.  **Web Server**:
    *   A running web server (e.g., Apache) configured to execute PHP scripts.
    *   Apache's `mod_rewrite` and `mod_authn_file` (or equivalent) must be enabled to support `.htaccess` directives for authentication.
    *   Ensure the server allows `.htaccess` overrides (`AllowOverride All` or at least `AuthConfig` and `FileInfo` for the project directory).
2.  **PHP Environment**:
    *   PHP installed (version compatible with `password_hash()` and `crypt()`).
    *   Ensure `session.save_path` in `php.ini` is writable by the web server user for admin panel sessions.
3.  **Project Files**:
    *   Deploy `index.php`, `config.php`, `.htaccess`, `css/style.css` to the web server.
    *   The `.htpasswd` file (initially can be empty or manually created with an admin user).
    *   The `users/` directory (or the path configured in `USER_BASE_DIR`).
4.  **Configuration (`config.php`)**:
    *   **`HTPASSWD_PATH`**: Must be an **absolute server path** to your `.htpasswd` file. Apache requires this.
        *   *Security*: This file should ideally be located **outside** the web document root. If it must be within, `.htaccess` attempts to protect it.
    *   **`USER_BASE_DIR`**: An absolute path to the directory where user folders will be created. Ensure this directory is writable by the PHP process.
    *   **`ADMIN_USERNAME`**: Username for accessing the admin panel's features within `index.php?view=admin`.
    *   **`ADMIN_PASSWORD_HASH`**: The hashed password for `ADMIN_USERNAME`. Use a strong password and generate its hash using `password_hash("yourpassword", PASSWORD_DEFAULT)`.
    *   **`SITE_ADMIN_USERNAME_HTACCESS`**: This username must be created in the `.htpasswd` file. This user, when logged in via HTTP Basic Authentication, will have visibility of all user directories in the default view of `index.php`. It can be the same as `ADMIN_USERNAME` or different.
5.  **`.htaccess` File**:
    *   Ensure the `AuthUserFile` directive in `.htaccess` correctly points to the absolute path of your `.htpasswd` file (this should match `HTPASSWD_PATH` from `config.php`). If `HTPASSWD_PATH` in `config.php` is changed, `.htaccess` **must be updated manually** or via a script.
    *   Ensure `config.php` and `.ht*` files are protected from web access by directives in `.htaccess`.
6.  **Initial Admin User for HTTP Basic Auth (Recommended)**:
    *   Manually add the `SITE_ADMIN_USERNAME_HTACCESS` user to your `.htpasswd` file. You can use an online htpasswd generator or a command-line tool (`htpasswd -cB .htpasswd youradminusername`). Use the `crypt` option if available, or ensure your Apache supports the hash type used.
    *   The admin features in `index.php?view=admin` are accessed via a separate PHP session login (using `ADMIN_USERNAME` and `ADMIN_PASSWORD_HASH` from `config.php`). The `SITE_ADMIN_USERNAME_HTACCESS` is for controlling directory visibility in the default view.

## II. Test Scenarios

### A. User View & HTTP Basic Authentication (Default: `index.php`)

| Test ID | Description                                   | Steps                                                                                                | Expected Result                                                                                                                                  |
| :------ | :-------------------------------------------- | :--------------------------------------------------------------------------------------------------- | :----------------------------------------------------------------------------------------------------------------------------------------------- |
| A-001   | Initial Access & Auth Prompt                  | 1. Navigate to `index.php` in a fresh browser.                                                       | Browser prompts for username/password (HTTP Basic Auth).                                                                                         |
| A-002   | Normal User Login (Valid Credentials)         | 1. Authenticate with credentials of a normal user (previously created via admin panel).              | Login successful. Welcome message: "Welcome, [username]!". Only the user's own directory (e.g., `/users/[username]/`) is listed and accessible. |
| A-003   | Site Admin (`SITE_ADMIN_USERNAME_HTACCESS`) Login | 1. Authenticate with `SITE_ADMIN_USERNAME_HTACCESS` credentials.                                     | Login successful. Welcome message: "Welcome, [SITE_ADMIN_USERNAME_HTACCESS]! (Site Administrator)". All user directories under `USER_BASE_DIR` are listed. A link "Access Admin Panel" is visible. |
| A-004   | Invalid HTTP Basic Auth Credentials           | 1. Enter incorrect username/password at the HTTP Basic Auth prompt.                                  | Browser re-prompts for credentials or shows an "Unauthorized" error.                                                                             |
| A-005   | HTTP Basic Auth Logout                        | 1. Log in. 2. Click the "Logout" link (`index.php?action=http_logout`).                              | "Logout Attempted" page is shown with instructions. Accessing `index.php` again should re-prompt for auth (browser behavior dependent).       |
| A-006   | Directory Accessibility                       | 1. Log in as normal user. 2. Try to access another user's directory (if links were guessable - not directly testable via UI). | (Conceptual) Access should be denied if not admin. UI only shows allowed directories.                                                            |

### B. Admin Panel Access & PHP Session Login (`index.php?view=admin`)

| Test ID | Description                                      | Steps                                                                                                 | Expected Result                                                                                                                               |
| :------ | :----------------------------------------------- | :---------------------------------------------------------------------------------------------------- | :-------------------------------------------------------------------------------------------------------------------------------------------- |
| B-001   | Access Admin Panel (Not Logged In as Admin)      | 1. Log in via HTTP Basic Auth. 2. Navigate to `index.php?view=admin`.                                   | Admin login form (PHP session based) is displayed within `index.php`.                                                                           |
| B-002   | Admin Panel Login - Valid Credentials            | 1. On admin login form, enter `ADMIN_USERNAME` and its password (from `config.php`). 2. Click "Login". | Login successful. Redirected to `index.php?view=admin`. Full admin panel (Add User, User List) is displayed. No admin login form visible.       |
| B-003   | Admin Panel Login - Invalid Credentials          | 1. On admin login form, enter incorrect credentials. 2. Click "Login".                                  | Login fails. Redirected to `index.php?view=admin`. Admin login form is shown again with an error message.                                     |
| B-004   | Admin Panel Logout                               | 1. Log in to admin panel. 2. Click "Admin Logout" link.                                                 | Session `admin_logged_in` is cleared. Redirected to `index.php`. Accessing `index.php?view=admin` again shows admin login form.                |
| B-005   | Direct Access to Admin Action (Not Logged In)    | 1. Ensure not logged into admin panel. 2. Try to access `index.php?action=add_user` directly (e.g. via URL manipulation if possible). | Action should not proceed. User should be redirected to admin login or an error shown. (Effectively, `view=admin` shows login form).       |

### C. Admin Panel Functionality - User Management (`index.php?view=admin`)

*Assume logged into Admin Panel for these tests.*

| Test ID | Description                                      | Steps                                                                                                                                  | Expected Result                                                                                                                                                             |
| :------ | :----------------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------- | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| C-001   | Add New User - Valid Data                        | 1. Enter valid new username & password. 2. Click "Add User".                                                                           | User added to `.htpasswd`. Directory `USER_BASE_DIR/[new_username]` created. Success message. User appears in "Existing Users" list.                                        |
| C-002   | Add User - Username Already Exists               | 1. Try to add a user whose username already exists in `.htpasswd` or as a directory.                                                     | Error message: "Username already exists" or "Directory already exists". User not added.                                                                                       |
| C-003   | Add User - Invalid Username (e.g., empty, special chars) | 1. Try to add user with empty username or username with invalid characters (e.g. `!@#$%`).                                           | Error message indicating invalid username. User not added.                                                                                                                    |
| C-004   | Add User - Empty Password                        | 1. Try to add user with empty password.                                                                                                  | Error message: "Password cannot be empty". User not added.                                                                                                                  |
| C-005   | Delete Existing User                             | 1. From "Existing Users" list, click "Delete" for a normal user. 2. Confirm in JS dialog.                                              | User removed from `.htpasswd`. User's directory `USER_BASE_DIR/[username]` deleted. Success message. User removed from list.                                          |
| C-006   | Delete Non-Existent User (Manual/Race Condition) | (Difficult to test via UI) If a user is removed from `.htpasswd` manually but directory exists, or vice-versa.                       | Admin panel should handle gracefully (e.g., error on delete if not in `.htpasswd`, or only delete directory if entry already gone). Current implementation aims for atomicity. |
| C-007   | Attempt to Delete `ADMIN_USERNAME` (if listed)   | 1. If `ADMIN_USERNAME` (for panel login) is listed (e.g., it's same as `SITE_ADMIN_USERNAME_HTACCESS`), try to delete it.                 | Deletion should be prevented by UI/backend logic. Error message.                                                                                                            |
| C-008   | List Users - Verify Display                      | 1. View "Existing Users" list after adding/deleting users.                                                                             | List accurately reflects users in `.htpasswd`.                                                                                                                            |

### D. Responsiveness & UI

| Test ID | Description                        | Steps                                                                                             | Expected Result                                                                                                  |
| :------ | :--------------------------------- | :------------------------------------------------------------------------------------------------ | :--------------------------------------------------------------------------------------------------------------- |
| D-001   | Default View (User Dirs) - Mobile  | 1. Access `index.php` (as normal user & site admin) on mobile emulator.                           | Content readable, layout adapted. No horizontal scrolling.                                                       |
| D-002   | Default View (User Dirs) - Desktop | 1. Access `index.php` (as normal user & site admin) on desktop.                                   | Content readable, layout appropriate.                                                                            |
| D-003   | Admin Login Form - Mobile          | 1. Access `index.php?view=admin` (not logged into admin panel) on mobile.                         | Form centered, usable.                                                                                           |
| D-004   | Admin Login Form - Desktop         | 1. Access `index.php?view=admin` (not logged into admin panel) on desktop.                        | Form centered, usable.                                                                                           |
| D-005   | Admin Panel View - Mobile          | 1. Log into admin panel. View `index.php?view=admin` on mobile.                                   | Admin sections (add user, list users) readable and usable. Forms and buttons accessible.                         |
| D-006   | Admin Panel View - Desktop         | 1. Log into admin panel. View `index.php?view=admin` on desktop.                                  | Admin sections clear and usable.                                                                                 |
| D-007   | HTTP Logout Page - Mobile/Desktop  | 1. Access `index.php?action=http_logout` on mobile and desktop.                                   | Message readable and centered.                                                                                   |

## III. Security Notes for Testers

*   Verify that `config.php`, `.htaccess`, and `.htpasswd` files are not directly accessible via a web browser.
*   Check file permissions for `config.php` (recommend 600 or 640) and `.htpasswd` (recommend 640). `USER_BASE_DIR` needs to be writable by the PHP/web server user.
*   Ensure passwords in `.htpasswd` are properly hashed (crypt format).
*   Ensure `ADMIN_PASSWORD_HASH` in `config.php` is a strong hash.
```
