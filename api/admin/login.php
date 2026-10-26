<?php
/**
 * Admin Login API
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// 5 attempts per hour per IP
$ip = getClientIp();
checkRateLimit('admin_login_' . $ip, 5, LOGIN_RATE_WINDOW);

try {
    $conn = getDbConnection();
    $input = getJsonInput();

    $email = isset($input['email']) ? sanitizeEmail($input['email']) : '';
    $password = $input['password'] ?? '';

    if (empty($email) || !is_string($password) || $password === '' || strlen($password) > 1024) {
        jsonError('Email and password required');
    }

    // Per-account cap (generous, so a stranger cannot easily lock the real admin out,
    // but a botnet cannot try unlimited passwords across many IPs either).
    checkRateLimit('admin_login_acct_' . hash('sha256', $email), 20, LOGIN_RATE_WINDOW);

    $stmt = $conn->prepare("SELECT id, email, password_hash, name, role FROM admin_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    // Always run password_verify so unknown emails take as long as wrong passwords.
    $passwordOk = password_verify($password, $admin['password_hash'] ?? DUMMY_PASSWORD_HASH);

    if (!$admin || !$passwordOk) {
        logSecurityEvent('admin_login_failed', ['email' => $email, 'reason' => $admin ? 'invalid_password' : 'user_not_found']);
        jsonError('Invalid credentials', 401);
    }

    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $newHash, $admin['id']);
        $stmt->execute();
    }

    // Generate auth token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime(ADMIN_TOKEN_EXPIRY));

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
