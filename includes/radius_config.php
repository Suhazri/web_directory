<?php
// WARNING: Storing secrets in version control is not recommended for production. Use environment variables or a secure vault.

define('RADIUS_SERVER_IP', '127.0.0.1');
define('RADIUS_SERVER_PORT', 1812);
define('RADIUS_SHARED_SECRET', 'your_radius_secret');
define('RADIUS_TIMEOUT', 5); // seconds
define('RADIUS_MAX_RETRIES', 3);
?>
