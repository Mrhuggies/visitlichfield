<?php
/**
 * Admin Login API
 * Secure authentication with rate limiting
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limiting - stricter for admin (5 attempts per hour)
$ip = getClientIp();
checkRateLimit('admin_login_' . $ip, 5, LOGIN_RATE_WINDOW);

try {
    $conn = getDbConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = isset($input['email']) ? sanitizeEmail($input['email']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    
    if (empty($email) || empty($password)) {
        jsonError('Email and password required');
    }
    
    // Get admin user
    $stmt = $conn->prepare("SELECT id, email, password_hash, name, role FROM admin_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$admin = $result->fetch_assoc()) {
        logSecurityEvent('admin_login_failed', ['email' => $email, 'reason' => 'user_not_found']);
        jsonError('Invalid credentials', 401);
    }
    
    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        logSecurityEvent('admin_login_failed', ['email' => $email, 'reason' => 'invalid_password']);
        jsonError('Invalid credentials', 401);
    }
    
    // Generate auth token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime(ADMIN_TOKEN_EXPIRY));
    
    // Update token and last login
    $stmt = $conn->prepare("UPDATE admin_users SET auth_token = ?, token_expires = ?, last_login = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $token, $expires, $admin['id']);
    $stmt->execute();
    
    logSecurityEvent('admin_login_success', ['email' => $email, 'admin_id' => $admin['id']]);
    
    jsonSuccess([
        'token' => $token,
        'name' => sanitizeString($admin['name']),
        'email' => $admin['email'],
        'role' => $admin['role'],
        'expires' => $expires
    ]);
    
} catch (Exception $e) {
    error_log('Admin login error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
