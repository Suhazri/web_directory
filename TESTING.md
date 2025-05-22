# Testing Guide: PHP RADIUS Authentication & Directory Listing

This document outlines the steps to test the PHP-based login system that uses OpenRADIUS for authentication and provides role-based directory access.

## I. Prerequisites & Setup

1.  **Web Server**: A running web server (e.g., Apache, Nginx) configured to execute PHP scripts.
2.  **PHP Environment**:
    *   PHP installed.
    *   The **PECL RADIUS extension** must be installed and enabled in `php.ini`. Verify with `php -m | grep radius`.
    *   Ensure `session.save_path` in `php.ini` is writable by the web server user.
3.  **OpenRADIUS Server**:
    *   A configured OpenRADIUS server (or any RADIUS server).
    *   The RADIUS server IP, port, and shared secret must match the values in `includes/radius_config.php`.
    *   **Test Users**:
        *   Create an "admin" user in the RADIUS server (e.g., username: `admin`, password: `adminpass`).
        *   Create a "normal" user in the RADIUS server (e.g., username: `user1`, password: `user1pass`).
4.  **Directory Structure**:
    *   The main application files (`login.php`, `index.php`, etc.) should be deployed to the web server's document root or a subdirectory.
    *   In `/var/www/html/` (or the path configured as `$base_dir` in `index.php`), create the following directories for testing:
        *   `/var/www/html/user1` (This directory name *must* match the normal user's username)
        *   `/var/www/html/another_folder`
        *   `/var/www/html/admin_only_folder`
5.  **Configuration**:
    *   Update `includes/radius_config.php` with the correct IP address, port, and shared secret for your RADIUS server.

## II. Test Cases

### A. Login Functionality

| Test ID | Description                                   | Steps                                                                                                | Expected Result                                                                                                | Actual Result |
| :------ | :-------------------------------------------- | :--------------------------------------------------------------------------------------------------- | :------------------------------------------------------------------------------------------------------------- | :------------ |
| A-001   | Admin Login - Valid Credentials               | 1. Navigate to `login.php`. 2. Enter admin username. 3. Enter admin password. 4. Click "Login".         | Redirected to `index.php`. Welcome message: "Welcome, admin!". All directories from `/var/www/html/` are listed. |               |
| A-002   | Normal User Login - Valid Credentials         | 1. Navigate to `login.php`. 2. Enter normal user's username (`user1`). 3. Enter normal user's password. 4. Click "Login". | Redirected to `index.php`. Welcome message: "Welcome, user1!". Only the `user1` directory is listed.         |               |
| A-003   | Invalid Username                              | 1. Navigate to `login.php`. 2. Enter an invalid username. 3. Enter any password. 4. Click "Login".     | Stays on `login.php`. Error message: "Invalid username or password." displayed.                                |               |
| A-004   | Invalid Password (for existing user)          | 1. Navigate to `login.php`. 2. Enter a valid username. 3. Enter an invalid password. 4. Click "Login". | Stays on `login.php`. Error message: "Invalid username or password." displayed.                                |               |
| A-005   | Empty Username                                | 1. Navigate to `login.php`. 2. Leave username blank. 3. Enter any password. 4. Click "Login".          | Stays on `login.php`. HTML5 validation prevents submission, or server-side error "Username and password are required." (if HTML validation bypassed) |               |
| A-006   | Empty Password                                | 1. Navigate to `login.php`. 2. Enter any username. 3. Leave password blank. 4. Click "Login".          | Stays on `login.php`. HTML5 validation prevents submission, or server-side error "Username and password are required." (if HTML validation bypassed) |               |
| A-007   | RADIUS Server Down/Unreachable (Simulate if possible) | 1. Navigate to `login.php`. 2. Enter valid credentials. 3. Click "Login".                        | Stays on `login.php`. Error message: "Error communicating with authentication server." displayed.              |               |

### B. Directory Listing & Access Control (`index.php`)

| Test ID | Description                                           | Steps                                                                | Expected Result                                                                                                                              | Actual Result |
| :------ | :---------------------------------------------------- | :------------------------------------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------- | :------------ |
| B-001   | Admin View                                            | 1. Log in as admin.                                                  | All subdirectories in `/var/www/html` are listed (e.g., `user1`, `another_folder`, `admin_only_folder`).                                   |               |
| B-002   | Normal User View                                      | 1. Log in as `user1`.                                                | Only the directory named `user1` is listed. `another_folder` and `admin_only_folder` are NOT listed.                                         |               |
| B-003   | Normal User - No Matching Directory                   | 1. Log in as a valid RADIUS user (e.g. `user2`) for whom no directory `/var/www/html/user2` exists. | Welcome message displayed. Message: "No personal directory found." or "No accessible directories found." is displayed.                 |               |
| B-004   | Direct Access to `index.php` (No Login)             | 1. Clear session/cookies or use a new browser. 2. Navigate directly to `index.php`. | Redirected to `login.php`.                                                                                                                   |               |
| B-005   | Access `index.php` after login, then session expires (manual simulation: delete session cookie) & refresh | 1. Log in. 2. Manually delete session cookie. 3. Refresh `index.php`. | Redirected to `login.php`.                                                                                                                   |               |

### C. Logout Functionality

| Test ID | Description      | Steps                                                                          | Expected Result                                                          | Actual Result |
| :------ | :--------------- | :----------------------------------------------------------------------------- | :----------------------------------------------------------------------- | :------------ |
| C-001   | Logout           | 1. Log in to the system. 2. Click the "Logout" link on `index.php`.            | Redirected to `login.php`. Session is cleared.                         |               |
| C-002   | Access `index.php` after logout | 1. Perform Logout (C-001). 2. Try to navigate directly to `index.php`. | Redirected to `login.php`.                                               |               |

### D. Responsiveness & UI

| Test ID | Description             | Steps                                                                    | Expected Result                                                                          | Actual Result |
| :------ | :---------------------- | :----------------------------------------------------------------------- | :--------------------------------------------------------------------------------------- | :------------ |
| D-001   | Login Page - Mobile     | 1. Open `login.php` in a mobile browser or dev tools mobile emulator.    | Form is centered, readable, and usable. No horizontal scrolling.                         |               |
| D-002   | Login Page - Desktop    | 1. Open `login.php` in a desktop browser.                                | Form is centered, readable, and usable.                                                  |               |
| D-003   | Index Page - Mobile     | 1. Log in. 2. View `index.php` in a mobile browser or emulator.          | Content is readable, directory list is clear. No horizontal scrolling.                 |               |
| D-004   | Index Page - Desktop    | 1. Log in. 2. View `index.php` in a desktop browser.                     | Content is readable, directory list is clear.                                            |               |

## III. Notes

*   The actual error message for empty username/password (A-005, A-006) might depend on whether client-side HTML5 validation catches it first or if it reaches server-side validation (if implemented beyond `required` attribute).
*   Simulating "RADIUS Server Down" (A-007) might require temporarily stopping your RADIUS service or misconfiguring the IP/port in `radius_config.php`.
```
