<?php
/**
 * Request Admin Account API
 * Allow users to request admin access (requires approval)
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limit requests (2 per hour per IP)
$ip = getClientIp();
checkRateLimit('admin_request_' . $ip, 2, 3600);

try {
    $conn = getDbConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = isset($input['email']) ? sanitizeEmail($input['email']) : '';
    $name = isset($input['name']) ? sanitizeString($input['name'], 100) : '';
    $reason = isset($input['reason']) ? sanitizeString($input['reason'], 500) : '';
    
    if (empty($email) || empty($name)) {
        jsonError('Email and name required');
    }
    
    if (!$email) {
        jsonError('Invalid email format');
    }
    
    // Check if already an admin
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        jsonError('An admin account with this email already exists');
    }
    
    // Check for existing pending request
    $stmt = $conn->prepare("SELECT id FROM admin_requests WHERE email = ? AND status = 'pending'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        jsonError('A request for this email is already pending');
    }
    
    // Create request
    $stmt = $conn->prepare("INSERT INTO admin_requests (email, name, reason, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("sss", $email, $name, $reason);
    
    if ($stmt->execute()) {
        // Notify existing super admins
        $to = ADMIN_EMAIL;
        $subject = "New Admin Account Request";
        $message = "A new admin account request has been submitted:\n\n";
        $message .= "Name: $name\n";
        $message .= "Email: $email\n";
        $message .= "Reason: $reason\n\n";
        $message .= "Please review in the admin dashboard.";
        
        $headers = "From: noreply@tag-art.co.uk\r\n";
        @mail($to, $subject, $message, $headers);
        
        logSecurityEvent('admin_request_submitted', ['email' => $email, 'name' => $name]);
        
        jsonSuccess([
            'message' => 'Request submitted successfully. You will be notified once approved.'
        ]);
    } else {
        jsonError('Failed to submit request', 500);
    }
    
} catch (Exception $e) {
    error_log('Admin request error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
