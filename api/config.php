<?php
/**
 * SECURE CONFIGURATION FILE
 * 
 * IMPORTANT: In production, move this file OUTSIDE the web root
 * and update the require_once paths accordingly.
 * 
 * For maximum security, use environment variables instead:
 * define('DB_HOST', getenv('DB_HOST'));
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// ============================================
// DATABASE CONFIGURATION
// ============================================
// TODO: Move these to environment variables in production
define('DB_HOST', 'localhost');
define('DB_USER', 'Thughes');
define('DB_PASS', 'l$sfeO1vr0mW2i?C');  // CHANGE THIS PASSWORD
define('DB_NAME', 'tours');

// ============================================
// SECURITY CONFIGURATION
// ============================================

// Allowed CORS origins (add your domains here)
define('ALLOWED_ORIGINS', [
    'https://apps.tag-art.co.uk',
    'https://tag-art.co.uk',
    'http://localhost:8000',  // Remove in production
    'http://localhost:3000'   // Remove in production
]);

// Rate limiting settings
define('LOGIN_RATE_LIMIT', 10);        // Max login attempts
define('LOGIN_RATE_WINDOW', 3600);     // Per hour (seconds)
define('API_RATE_LIMIT', 100);         // Max API requests
define('API_RATE_WINDOW', 3600);       // Per hour (seconds)

// Token expiration
define('DRIVER_TOKEN_EXPIRY', '+24 hours');   // Reduced from 7 days
define('ADMIN_TOKEN_EXPIRY', '+4 hours');     // Reduced from 24 hours
define('RESET_TOKEN_EXPIRY', '+1 hour');

// File upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);  // 5MB
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Application URLs
define('BASE_URL', 'https://apps.tag-art.co.uk/visitlichfield');
define('ADMIN_EMAIL', 'admin@tag-art.co.uk');  // Change from personal email

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get database connection
 */
function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
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
    
    if (in_array($origin, ALLOWED_ORIGINS)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Driver-Token, X-Admin-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

/**
 * Set security headers
 */
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
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
 * Initialize API endpoint with security headers
 */
function initApiEndpoint() {
    handlePreflight();
    setCorsHeaders();
    setSecurityHeaders();
}

/**
 * Database-backed rate limiting
 * Fails gracefully if rate_limits table doesn't exist
 */
function checkRateLimit($identifier, $maxRequests = null, $timeWindow = null) {
    $maxRequests = $maxRequests ?? API_RATE_LIMIT;
    $timeWindow = $timeWindow ?? API_RATE_WINDOW;
    
    try {
        $conn = getDbConnection();
        
        // Check if table exists first
        $tableCheck = $conn->query("SHOW TABLES LIKE 'rate_limits'");
        if ($tableCheck->num_rows === 0) {
            // Table doesn't exist yet - skip rate limiting
            return true;
        }
        
        // Clean old entries
        $stmt = $conn->prepare("DELETE FROM rate_limits WHERE expires_at < NOW()");
        $stmt->execute();
        
        // Check current count
        $stmt = $conn->prepare("SELECT request_count FROM rate_limits WHERE identifier = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['request_count'] >= $maxRequests) {
                http_response_code(429);
                echo json_encode([
                    'error' => 'Too many requests. Please try again later.',
                    'retry_after' => $timeWindow
                ]);
                exit();
            }
            
            // Increment count
            $stmt = $conn->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE identifier = ?");
            $stmt->bind_param("s", $identifier);
            $stmt->execute();
        } else {
            // Create new entry
            $expires = date('Y-m-d H:i:s', time() + $timeWindow);
            $stmt = $conn->prepare("INSERT INTO rate_limits (identifier, request_count, expires_at) VALUES (?, 1, ?)");
            $stmt->bind_param("ss", $identifier, $expires);
            $stmt->execute();
        }
    } catch (Exception $e) {
        // If rate limiting fails, log but continue
        error_log('Rate limiting error (table may not exist): ' . $e->getMessage());
    }
    
    return true;
}

/**
 * Get client IP address
 */
function getClientIp() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Handle comma-separated IPs (X-Forwarded-For)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
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
    if (strlen($password) < 8) {
        return ['valid' => false, 'error' => 'Password must be at least 8 characters'];
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
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return strtolower($email);
}

/**
 * Sanitize string
 */
function sanitizeString($input, $maxLength = 255) {
    return substr(htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'), 0, $maxLength);
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
