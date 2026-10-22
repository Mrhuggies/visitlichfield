<?php
/**
 * Admin Reset Driver Password API
 * Allows admins to reset driver passwords directly
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

try {
    $conn = getDbConnection();
    $input = getJsonInput();
    
    $admin_token = requestToken('admin', $input);
    $driver_email = $input['email'] ?? '';
    $new_password = $input['password'] ?? '';
    
    if (empty($admin_token)) {
        jsonError('Admin authorization required', 401);
    }
    
    if (empty($driver_email) || empty($new_password)) {
        jsonError('Email and password required');
    }
    
    // Verify admin token (analysts may not reset passwords)
    $admin = requireAdmin($conn, $admin_token, 'admin');
    
    // Validate password strength
    $passwordCheck = validatePassword($new_password);
    if (!$passwordCheck['valid']) {
        jsonError($passwordCheck['error']);
    }
    
    // Find driver by email
    $stmt = $conn->prepare("SELECT id, name, email FROM drivers WHERE email = ?");
    $stmt->bind_param("s", $driver_email);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();
    
    if (!$driver) {
        jsonError('Driver not found with that email');
    }
    
    // Hash new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password and clear all tokens
    $stmt = $conn->prepare("UPDATE drivers SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, auth_token = NULL, token_expires = NULL WHERE id = ?");
    $stmt->bind_param("si", $password_hash, $driver['id']);
    
    if ($stmt->execute()) {
        logSecurityEvent('admin_password_reset', [
            'admin_id' => $admin['id'],
            'driver_id' => $driver['id'],
            'driver_email' => $driver['email']
        ]);
        
        jsonSuccess([
            'message' => 'Password reset successfully',
            'driver_name' => sanitizeString($driver['name']),
            'driver_email' => $driver['email']
        ]);
    } else {
        jsonError('Failed to reset password', 500);
    }
    
} catch (Exception $e) {
    error_log('Admin password reset error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
