<?php
/**
 * SECURE CONFIGURATION FILE
 *
 * Secrets are NOT stored in this repository. Put them in a PHP file OUTSIDE the
 * web root (default: three directories above this folder, named
 * visitlichfield.config.php) or point VISITLICHFIELD_CONFIG at one. See
 * config.local.example.php for the format. Environment variables of the same
 * names (DB_HOST, DB_USER, DB_PASS, DB_NAME, ...) also work.
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'config.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// ============================================
// LOCAL (UNTRACKED) CONFIGURATION
// ============================================
(function () {
    $local = getenv('VISITLICHFIELD_CONFIG') ?: dirname(__DIR__, 3) . '/visitlichfield.config.php';
    if (is_file($local)) {
        require $local;
    }
})();

/** Define $name once: local config first, then the environment, then the default. */
function configDefault($name, $default = null) {
    if (defined($name)) {
        return;
    }
    $env = getenv($name);
    define($name, $env === false ? $default : $env);
}

// ============================================
// DATABASE CONFIGURATION (no defaults for credentials)
// ============================================
configDefault('DB_HOST', 'localhost');
configDefault('DB_USER', '');
configDefault('DB_PASS', '');
configDefault('DB_NAME', 'tours');

// ============================================
// SECURITY CONFIGURATION
// ============================================

// Allowed CORS origins. Localhost origins are only allowed when APP_ENV=dev.
$__origins = [
    'https://apps.tag-art.co.uk',
    'https://tag-art.co.uk',
];
if (getenv('APP_ENV') === 'dev') {
    $__origins[] = 'http://localhost:8000';
    $__origins[] = 'http://localhost:3000';
}
define('ALLOWED_ORIGINS', $__origins);
unset($__origins);

// Reverse proxies whose X-Forwarded-For / CF-Connecting-IP headers we believe.
// Leave empty when Apache is the first hop (REMOTE_ADDR is then the real client).
configDefault('TRUSTED_PROXIES', []);

// Rate limiting settings
define('LOGIN_RATE_LIMIT', 10);        // Max login attempts
define('LOGIN_RATE_WINDOW', 3600);     // Per hour (seconds)
define('API_RATE_LIMIT', 100);         // Max API requests
define('API_RATE_WINDOW', 3600);       // Per hour (seconds)

// Token expiration
define('DRIVER_TOKEN_EXPIRY', '+24 hours');
define('ADMIN_TOKEN_EXPIRY', '+4 hours');
define('RESET_TOKEN_EXPIRY', '+1 hour');

// File upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);  // 5MB
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Application URLs
define('BASE_URL', 'https://apps.tag-art.co.uk/visitlichfield');
define('ADMIN_EMAIL', 'admin@tag-art.co.uk');

// Valid bcrypt hash of a random string, verified when the account does not exist, so that
// unknown emails and wrong passwords take the same time (login endpoints).
const DUMMY_PASSWORD_HASH = '$2y$10$7zXPO7gt5K/0pX4TnWDJcesBiOX/ELtubL7vOIaVpJpeATf.eM8Fu';

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get database connection
 */
function getDbConnection() {
    static $conn = null;

    if ($conn === null) {
        if (DB_USER === '' || DB_PASS === '') {
            error_log('Database credentials are not configured (see api/config.php)');
            throw new Exception('Database not configured');
        }

        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            error_log('Database connection failed: ' . $conn->connect_error);
            throw new Exception('Database connection failed');
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}

/**
 * Set secure CORS headers
 */
function setCorsHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    header('Vary: Origin');
    if (in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Driver-Token, X-Admin-Token');
    header('Access-Control-Max-Age: 86400');
}

/**
 * Set security headers
 */
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Type: application/json; charset=utf-8');
    // Prevent caching of API responses
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * Handle OPTIONS preflight request
 */
function handlePreflight() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        setCorsHeaders();
        http_response_code(200);
        exit();
    }
}

/**
 * Last-resort handler: any uncaught Exception or Error (TypeError etc.) becomes a
 * plain JSON 500 instead of a PHP fatal with details.
 */
function installErrorHandlers() {
    ini_set('display_errors', '0');
    set_exception_handler(function ($e) {
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'Server error']);
        exit();
    });
}

/**
 * Initialize API endpoint with security headers
 */
function initApiEndpoint() {
    installErrorHandlers();
    handlePreflight();
    setCorsHeaders();
    setSecurityHeaders();
}

/**
 * JSON request body as an array. Malformed JSON, scalars and null all become [].
 */
function getJsonInput() {
    $data = json_decode((string) file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

/**
 * Auth token for a request. Prefers the X-Driver-Token / X-Admin-Token header.
 * The query-string / body fallbacks only exist for older clients; they leak into
 * access logs and Referer headers, so prefer the header.
 */
function requestToken($kind = 'driver', $input = []) {
    $header = $kind === 'admin' ? 'HTTP_X_ADMIN_TOKEN' : 'HTTP_X_DRIVER_TOKEN';
    $candidates = [
        $_SERVER[$header] ?? null,
        $input['token'] ?? null,
        $input['admin_token'] ?? null,
        $_POST['token'] ?? null,
        $_GET['token'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '') {
            return substr(trim($candidate), 0, 128);
        }
    }
    return '';
}

/**
 * Authenticate an admin token and require a minimum role.
 * Returns the admin row (id, role, name). Exits with 401 / 403 otherwise.
 */
function requireAdmin($conn, $token, $minRole = 'analyst') {
    if (!is_string($token) || $token === '') {
        jsonError('Authorization required', 401);
    }

    $stmt = $conn->prepare("SELECT id, role, name FROM admin_users WHERE auth_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    if (!$admin) {
        jsonError('Invalid or expired token', 401);
    }

    $levels = ['analyst' => 1, 'admin' => 2, 'super_admin' => 3];
    if (($levels[$admin['role']] ?? 0) < ($levels[$minRole] ?? 99)) {
        logSecurityEvent('insufficient_role', ['admin_id' => $admin['id'], 'required' => $minRole]);
        jsonError('Insufficient permissions', 403);
    }

    return $admin;
}

/**
 * Database-backed rate limiting.
 * The counter is bumped atomically (UPDATE ... SET count = count + 1) and then read
 * back, so concurrent requests cannot all slip under the limit.
 */
function checkRateLimit($identifier, $maxRequests = null, $timeWindow = null) {
    $maxRequests = $maxRequests ?? API_RATE_LIMIT;
    $timeWindow = $timeWindow ?? API_RATE_WINDOW;
    $identifier = substr((string) $identifier, 0, 190);

    try {
        $conn = getDbConnection();

        $conn->query("CREATE TABLE IF NOT EXISTS rate_limits (
            identifier VARCHAR(190) NOT NULL PRIMARY KEY,
            request_count INT NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Clean old entries
        $conn->query("DELETE FROM rate_limits WHERE expires_at < NOW()");

        // Bump an existing live window
        $stmt = $conn->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE identifier = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            // Start a new window (ignore the race where another request created it first)
            $expires = date('Y-m-d H:i:s', time() + $timeWindow);
            $stmt = $conn->prepare("INSERT INTO rate_limits (identifier, request_count, expires_at) VALUES (?, 1, ?)
                ON DUPLICATE KEY UPDATE request_count = request_count + 1");
            $stmt->bind_param("ss", $identifier, $expires);
            $stmt->execute();
        }

        $stmt = $conn->prepare("SELECT request_count FROM rate_limits WHERE identifier = ?");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && (int) $row['request_count'] > $maxRequests) {
            http_response_code(429);
            header('Retry-After: ' . (int) $timeWindow);
            echo json_encode([
                'error' => 'Too many requests. Please try again later.',
                'retry_after' => $timeWindow
            ]);
            exit();
        }
    } catch (Exception $e) {
        // A broken limiter must not take the API down, but it must be visible in the logs.
        error_log('Rate limiting error: ' . $e->getMessage());
    }

    return true;
}

/**
 * Get client IP address.
 * Forwarded-for headers are client-controlled, so they are only honoured when the
 * TCP peer is one of TRUSTED_PROXIES. Otherwise REMOTE_ADDR is used.
 */
function getClientIp() {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = is_array(TRUSTED_PROXIES) ? TRUSTED_PROXIES : array_filter(array_map('trim', explode(',', (string) TRUSTED_PROXIES)));

    if ($trusted && in_array($remote, $trusted, true)) {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Walk from the right: the first address that is not one of our proxies is the client.
            $hops = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
            foreach ($hops as $hop) {
                if (filter_var($hop, FILTER_VALIDATE_IP) && !in_array($hop, $trusted, true)) {
                    return $hop;
                }
            }
        }
    }

    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

/**
 * Log security event
 * Fails gracefully if security_logs table doesn't exist
 */
function logSecurityEvent($event_type, $details = []) {
    try {
        $conn = getDbConnection();

        // Check if table exists first
        $tableCheck = $conn->query("SHOW TABLES LIKE 'security_logs'");
        if ($tableCheck->num_rows === 0) {
            // Table doesn't exist yet - skip logging
            return;
        }

        $ip = getClientIp();
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $details_json = json_encode($details);

        $stmt = $conn->prepare("INSERT INTO security_logs (event_type, ip_address, user_agent, details, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $event_type, $ip, $user_agent, $details_json);
        $stmt->execute();
    } catch (Exception $e) {
        // If logging fails, just continue - don't break the app
        error_log('Security logging error (table may not exist): ' . $e->getMessage());
    }
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    if (!is_string($password)) {
        return ['valid' => false, 'error' => 'Password must be a string'];
    }
    if (strlen($password) < 8) {
        return ['valid' => false, 'error' => 'Password must be at least 8 characters'];
    }
    if (strlen($password) > 72) {
        // bcrypt silently ignores everything past 72 bytes
        return ['valid' => false, 'error' => 'Password must be at most 72 characters'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one uppercase letter'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one lowercase letter'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one number'];
    }
    return ['valid' => true];
}

/**
 * Sanitize email
 */
function sanitizeEmail($email) {
    if (!is_string($email)) {
        return false;
    }
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return strtolower($email);
}

/**
 * Sanitize string (non-scalars such as arrays become '')
 */
function sanitizeString($input, $maxLength = 255) {
    if (!is_scalar($input)) {
        return '';
    }
    return substr(htmlspecialchars(strip_tags(trim((string) $input)), ENT_QUOTES, 'UTF-8'), 0, $maxLength);
}

/**
 * Clamp a list limit / offset taken from the query string.
 */
function clampInt($value, $min, $max, $default) {
    if (!is_scalar($value) || !is_numeric($value)) {
        return $default;
    }
    return max($min, min($max, (int) $value));
}

/**
 * Return JSON error response
 */
function jsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit();
}

/**
 * Return JSON success response
 */
function jsonSuccess($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}
?>
