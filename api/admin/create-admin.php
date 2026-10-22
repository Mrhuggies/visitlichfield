<?php
/**
 * Admin Create Admin API
 * Creates new admin accounts (requires super_admin permission)
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
    
    $token = requestToken('admin', $input);
    $email = isset($input['email']) ? sanitizeEmail($input['email']) : '';
    $name = isset($input['name']) ? sanitizeString($input['name'], 100) : '';
    $role = $input['role'] ?? 'analyst';
    $password = $input['password'] ?? '';
    
    if (empty($token)) {
        jsonError('Authorization required', 401);
    }
    
    // Verify requester is super_admin
    $stmt = $conn->prepare("SELECT id, role FROM admin_users WHERE auth_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    
    if (!$admin || $admin['role'] !== 'super_admin') {
        logSecurityEvent('unauthorized_admin_create', ['requester_id' => $admin['id'] ?? null]);
        jsonError('Super admin permission required', 403);
    }
    
    // Validate inputs
    if (empty($email) || empty($name) || empty($password)) {
        jsonError('Email, name, and password required');
    }
    
    if (!$email) {
        jsonError('Invalid email format');
    }
    
    // Validate password strength
    $passwordCheck = validatePassword($password);
    if (!$passwordCheck['valid']) {
        jsonError($passwordCheck['error']);
    }
    
    // Validate role
    $valid_roles = ['analyst', 'admin', 'super_admin'];
    if (!in_array($role, $valid_roles)) {
        jsonError('Invalid role');
    }
    
    // Check if already exists
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        jsonError('Admin with this email already exists');
    }
    
    // Create admin
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admin_users (email, password_hash, name, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $password_hash, $name, $role);
    
    if ($stmt->execute()) {
        $new_admin_id = $conn->insert_id;
        
        logSecurityEvent('admin_created', [
            'created_by' => $admin['id'],
            'new_admin_id' => $new_admin_id,
            'email' => $email,
            'role' => $role
        ]);
        
        jsonSuccess([
            'admin_id' => $new_admin_id,
            'email' => $email,
            'name' => $name,
            'role' => $role
        ]);
    } else {
        jsonError('Failed to create admin', 500);
    }
    
} catch (Exception $e) {
    error_log('Create admin error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
