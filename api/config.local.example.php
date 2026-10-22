<?php
/**
 * Copy this file to visitlichfield.config.php OUTSIDE the web root
 * (three directories above api/, or anywhere and set VISITLICHFIELD_CONFIG).
 * Never commit the real file.
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'CHANGE_ME');
define('DB_PASS', 'CHANGE_ME');
define('DB_NAME', 'tours');

// Only list proxies you actually run in front of Apache (e.g. Cloudflare). Leave empty otherwise.
// define('TRUSTED_PROXIES', ['203.0.113.10']);
