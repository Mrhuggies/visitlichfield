<?php
/**
 * Create Driver Account API
 * Registration with email verification
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limit registrations (5 per hour per IP)
$ip = getClientIp();
checkRateLimit('register_' . $ip, 5, 3600);

try {
    $conn = getDbConnection();
    $input = getJsonInput();
    
    // Validate required fields
    if (!isset($input['name']) || !isset($input['email']) || !isset($input['password'])) {
        jsonError('Name, email, and password required');
    }
    
    $name = sanitizeString($input['name'], 100);
    $email = sanitizeEmail($input['email']);
    $password = $input['password'];
    $phone = isset($input['phone']) ? sanitizeString($input['phone'], 20) : null;
    
    // Validate email
    if (!$email) {
        jsonError('Invalid email format');
    }
    
    // Validate password strength
    $passwordCheck = validatePassword($password);
    if (!$passwordCheck['valid']) {
        jsonError($passwordCheck['error']);
    }
    
    // Validate name length
    if (strlen($name) < 2) {
        jsonError('Name must be at least 2 characters');
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM drivers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        // Same response as a fresh sign-up so the form cannot be used to discover which emails have accounts
        logSecurityEvent('driver_register_duplicate', ['email' => $email]);
        jsonSuccess([
            'email' => $email,
            'message' => 'Account created! Please check your email to verify your account.'
        ]);
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Create driver with email_verified = false
    $stmt = $conn->prepare("INSERT INTO drivers (email, email_verified, verification_token, verification_expires, password_hash, name, phone, active) VALUES (?, 0, ?, ?, ?, ?, ?, 0)");
    $stmt->bind_param("ssssss", $email, $verification_token, $verification_expires, $password_hash, $name, $phone);
    
    if ($stmt->execute()) {
        $driver_id = $conn->insert_id;
        
        // Send verification email
        $verification_link = BASE_URL . "/verify-email.html?token=$verification_token";
        
        $subject = "Verify Your Email - Tour Driver Account";
        $message = "Hello $name,\n\n";
        $message .= "Thank you for registering as a tour driver.\n\n";
        $message .= "Please verify your email address by clicking the link below:\n";
        $message .= "$verification_link\n\n";
        $message .= "This link will expire in 24 hours.\n\n";
        $message .= "If you didn't create this account, please ignore this email.\n\n";
        $message .= "Best regards,\nTour Management Team";
        
        $headers = "From: noreply@tag-art.co.uk\r\n";
        $headers .= "Reply-To: noreply@tag-art.co.uk\r\n";
        
        mail($email, $subject, $message, $headers);
        
        logSecurityEvent('driver_registered', ['driver_id' => $driver_id, 'email' => $email]);
        
        jsonSuccess([
            'email' => $email,
            'message' => 'Account created! Please check your email to verify your account.'
        ]);
    } else {
        jsonError('Failed to create driver account', 500);
    }
    
} catch (Exception $e) {
    error_log('Create driver error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
