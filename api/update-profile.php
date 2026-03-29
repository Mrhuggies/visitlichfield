<?php
/**
 * Update Driver Profile API
 * Updates profile data for authenticated driver
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
    
    $token = isset($input['token']) ? sanitizeString($input['token'], 64) : '';
    $name = isset($input['name']) ? sanitizeString($input['name'], 100) : '';
    $phone = isset($input['phone']) ? sanitizeString($input['phone'], 20) : '';
    $bio = isset($input['bio']) ? sanitizeString($input['bio'], 500) : '';
    
    if (empty($token)) {
        jsonError('Token required', 401);
    }
    
    if (empty($name)) {
        jsonError('Name is required');
    }
    
    // Verify token and get driver
    $stmt = $conn->prepare("SELECT id FROM drivers WHERE auth_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();
    
    if (!$driver) {
        jsonError('Invalid or expired token', 401);
    }
    
    // Check if bio column exists
    $columns_result = $conn->query("DESCRIBE drivers");
    $existing_columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
    $has_bio = in_array('bio', $existing_columns);
    
    // Add bio column if it doesn't exist
    if (!$has_bio && !empty($bio)) {
        $conn->query("ALTER TABLE drivers ADD COLUMN bio TEXT");
        $has_bio = true;
    }
    
    // Update profile
    if ($has_bio) {
        $stmt = $conn->prepare("UPDATE drivers SET name = ?, phone = ?, bio = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $phone, $bio, $driver['id']);
    } else {
        $stmt = $conn->prepare("UPDATE drivers SET name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $phone, $driver['id']);
    }
    
    if ($stmt->execute()) {
        logSecurityEvent('profile_updated', ['driver_id' => $driver['id']]);
        jsonSuccess(['message' => 'Profile updated successfully']);
    } else {
        jsonError('Failed to update profile', 500);
    }
    
} catch (Exception $e) {
    error_log('Update profile error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
