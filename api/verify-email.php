<?php
/**
 * Verify Email API
 * Verify driver email address
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        jsonError('Verification token required');
    }
    
    // Check token validity
    $stmt = $conn->prepare("SELECT id, email, name FROM drivers WHERE verification_token = ? AND verification_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$driver = $result->fetch_assoc()) {
        jsonError('Invalid or expired verification link');
    }
    
    // Mark as verified and activate
    $stmt = $conn->prepare("UPDATE drivers SET email_verified = 1, active = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?");
    $stmt->bind_param("i", $driver['id']);
    
    if ($stmt->execute()) {
        logSecurityEvent('email_verified', ['email' => $driver['email'], 'driver_id' => $driver['id']]);
        
        jsonSuccess([
            'message' => 'Email verified successfully',
            'driver_name' => sanitizeString($driver['name']),
            'email' => $driver['email']
        ]);
    } else {
        jsonError('Failed to verify email', 500);
    }
    
} catch (Exception $e) {
    error_log('Email verification error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
