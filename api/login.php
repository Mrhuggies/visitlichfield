<?php
/**
 * Driver Login API
 * Secure authentication with rate limiting
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limiting - 10 login attempts per hour per IP
$ip = getClientIp();
checkRateLimit('login_' . $ip, LOGIN_RATE_LIMIT, LOGIN_RATE_WINDOW);

try {
    $conn = getDbConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || !isset($input['password'])) {
        jsonError('Email and password required');
    }
    
    // Sanitize email
    $email = sanitizeEmail($input['email']);
    if (!$email) {
        jsonError('Invalid email format');
    }
    
    $password = $input['password'];
    
    // Get driver - check for both 'active' column and 'status' column
    $stmt = $conn->prepare("SELECT id, email, email_verified, password_hash, name, phone, active, status FROM drivers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$row = $result->fetch_assoc()) {
        logSecurityEvent('login_failed', ['email' => $email, 'reason' => 'user_not_found']);
        jsonError('Invalid credentials', 401);
    }
    
    // Check if account is active (support both column types)
    $is_active = false;
    if (isset($row['active']) && $row['active']) {
        $is_active = true;
    }
    if (isset($row['status']) && $row['status'] === 'active') {
        $is_active = true;
    }
    
    if (!$is_active) {
        logSecurityEvent('login_failed', ['email' => $email, 'reason' => 'account_disabled']);
        jsonError('Account not yet approved. Please wait for admin approval.', 403);
    }
    
    if (!$row['email_verified']) {
        jsonError('Please verify your email address or wait for admin approval.', 403);
    }
    
    // Verify password
    if (!password_verify($password, $row['password_hash'])) {
        logSecurityEvent('login_failed', ['email' => $email, 'reason' => 'invalid_password']);
        jsonError('Invalid credentials', 401);
    }
    
    // Regenerate session to prevent fixation
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
    
    // Generate auth token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime(DRIVER_TOKEN_EXPIRY));
    
    // Save token
    $stmt = $conn->prepare("UPDATE drivers SET auth_token = ?, token_expires = ? WHERE id = ?");
    $stmt->bind_param("ssi", $token, $expires, $row['id']);
    $stmt->execute();
    
    logSecurityEvent('login_success', ['email' => $email, 'driver_id' => $row['id']]);
    
    // Return driver info and token
    jsonSuccess([
        'driver' => [
            'id' => $row['id'],
            'email' => $row['email'],
            'name' => sanitizeString($row['name']),
            'phone' => $row['phone']
        ],
        'token' => $token,
        'expires' => $expires
    ]);
    
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
