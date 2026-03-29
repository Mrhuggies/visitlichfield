<?php
/**
 * Reset Password API
 * Step 2: Actually reset password with token
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

try {
    $conn = getDbConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $token = $input['token'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($token) || empty($password)) {
        jsonError('Token and password required');
    }
    
    // Validate password strength
    $passwordCheck = validatePassword($password);
    if (!$passwordCheck['valid']) {
        jsonError($passwordCheck['error']);
    }
    
    // Verify token and check expiry
    $stmt = $conn->prepare("SELECT id, name, email FROM drivers WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$driver = $result->fetch_assoc()) {
        logSecurityEvent('password_reset_invalid_token', ['token_prefix' => substr($token, 0, 8)]);
        jsonError('Invalid or expired reset link');
    }
    
    // Hash new password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password and clear all tokens (force re-login on all devices)
    $stmt = $conn->prepare("UPDATE drivers SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, auth_token = NULL, token_expires = NULL WHERE id = ?");
    $stmt->bind_param("si", $password_hash, $driver['id']);
    
    if ($stmt->execute()) {
        logSecurityEvent('password_reset_success', ['email' => $driver['email'], 'driver_id' => $driver['id']]);
        
        jsonSuccess([
            'message' => 'Password reset successfully',
            'driver_name' => sanitizeString($driver['name'])
        ]);
    } else {
        jsonError('Failed to reset password', 500);
    }
    
} catch (Exception $e) {
    error_log('Password reset error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
