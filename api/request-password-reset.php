<?php
/**
 * Request Password Reset API
 * Step 1: Send reset link via email
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limit password reset requests (3 per hour per IP)
$ip = getClientIp();
checkRateLimit('reset_' . $ip, 3, 3600);

try {
    $conn = getDbConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = isset($input['email']) ? sanitizeEmail($input['email']) : '';
    
    if (empty($email)) {
        jsonError('Email required');
    }
    
    // Check if driver exists (but don't reveal this to user)
    $stmt = $conn->prepare("SELECT id, name FROM drivers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Always return same response to prevent email enumeration
    if (!$driver = $result->fetch_assoc()) {
        logSecurityEvent('password_reset_unknown_email', ['email' => $email]);
        jsonSuccess(['message' => 'If an account exists, a reset link has been sent']);
    }
    
    // Generate secure reset token
    $reset_token = bin2hex(random_bytes(32));
    $token_expires = date('Y-m-d H:i:s', strtotime(RESET_TOKEN_EXPIRY));
    
    // Store token
    $stmt = $conn->prepare("UPDATE drivers SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
    $stmt->bind_param("ssi", $reset_token, $token_expires, $driver['id']);
    $stmt->execute();
    
    // Send email with reset link
    $reset_link = BASE_URL . "/reset-password.html?token=$reset_token";
    
    $subject = "Password Reset Request - Tour System";
    $message = "Hi {$driver['name']},\n\n";
    $message .= "You requested a password reset for your tour driver account.\n\n";
    $message .= "Click this link to reset your password (valid for 1 hour):\n";
    $message .= "$reset_link\n\n";
    $message .= "If you didn't request this, ignore this email.\n\n";
    $message .= "- Tour Management System";
    
    $headers = "From: noreply@tag-art.co.uk\r\n";
    $headers .= "Reply-To: noreply@tag-art.co.uk\r\n";
    
    mail($email, $subject, $message, $headers);
    
    logSecurityEvent('password_reset_requested', ['email' => $email, 'driver_id' => $driver['id']]);
    
    jsonSuccess(['message' => 'If an account exists, a reset link has been sent']);
    
} catch (Exception $e) {
    error_log('Password reset request error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
