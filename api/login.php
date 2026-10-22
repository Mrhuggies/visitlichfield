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
    $input = getJsonInput();

    if (!isset($input['email']) || !isset($input['password'])) {
        jsonError('Email and password required');
    }

    // Sanitize email
    $email = sanitizeEmail($input['email']);
    if (!$email) {
        jsonError('Invalid email format');
    }

    $password = $input['password'];
    if (!is_string($password) || $password === '' || strlen($password) > 1024) {
        jsonError('Invalid credentials', 401);
    }

    // Also throttle per account so a distributed attack cannot walk one password list
    // across many IPs (generous enough that a victim is not locked out by a few typos).
    checkRateLimit('login_acct_' . hash('sha256', $email), 30, LOGIN_RATE_WINDOW);

    // Get driver - check for both 'active' column and 'status' column
    $stmt = $conn->prepare("SELECT id, email, email_verified, password_hash, name, phone, active, status FROM drivers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    // Verify the password FIRST, so account state is never revealed to someone who
    // does not know the password.
    $hash = $row['password_hash'] ?? DUMMY_PASSWORD_HASH;
    $passwordOk = password_verify($password, $hash);

    if (!$row || !$passwordOk) {
        logSecurityEvent('login_failed', ['email' => $email, 'reason' => $row ? 'invalid_password' : 'user_not_found']);
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

    // Upgrade the stored hash if PHP's default cost has moved on
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE drivers SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $newHash, $row['id']);
        $stmt->execute();
    }

    // Generate auth token (this API is token based; no PHP session is needed)
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
