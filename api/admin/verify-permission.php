<?php
/**
 * Verify Admin Permission API
 * Check if admin has required permission level
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    $token = $_GET['token'] ?? '';
    $required_role = $_GET['required_role'] ?? 'admin';
    
    if (empty($token)) {
        jsonError('Token required', 401);
    }
    
    // Verify token and get role
    $stmt = $conn->prepare("SELECT id, role, name FROM admin_users WHERE auth_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$admin = $result->fetch_assoc()) {
        jsonError('Invalid or expired token', 401);
    }
    
    // Check if user has required role
    $role_hierarchy = [
        'analyst' => 1,
        'admin' => 2,
        'super_admin' => 3
    ];
    
    $user_level = $role_hierarchy[$admin['role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
    if ($user_level < $required_level) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient permissions',
            'your_role' => $admin['role'],
            'required_role' => $required_role
        ]);
        exit();
    }
    
    jsonSuccess([
        'admin_id' => $admin['id'],
        'admin_name' => sanitizeString($admin['name']),
        'role' => $admin['role'],
        'has_permission' => true
    ]);
    
} catch (Exception $e) {
    error_log('Permission check error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
