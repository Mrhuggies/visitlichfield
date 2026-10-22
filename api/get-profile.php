<?php
/**
 * Get Driver Profile API
 * Returns profile data for authenticated driver
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    // Get token from query string
    $token = requestToken('driver');
    
    if (empty($token)) {
        jsonError('Token required', 401);
    }
    
    // Check what columns exist in drivers table
    $columns_result = $conn->query("DESCRIBE drivers");
    $existing_columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
    
    $has_bio = in_array('bio', $existing_columns);
    
    // Build query based on available columns
    $select = "SELECT id, email, name, phone, profile_photo, created_at";
    if ($has_bio) $select .= ", bio";
    $select .= " FROM drivers WHERE auth_token = ? AND token_expires > NOW()";
    
    $stmt = $conn->prepare($select);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();
    
    if (!$driver) {
        jsonError('Invalid or expired token', 401);
    }
    
    // Format profile photo URL
    $photo = null;
    if (!empty($driver['profile_photo'])) {
        if (str_starts_with($driver['profile_photo'], 'http')) {
            $photo = $driver['profile_photo'];
        } else {
            $photo = BASE_URL . '/' . $driver['profile_photo'];
        }
    }
    
    jsonSuccess([
        'driver' => [
            'id' => (int) $driver['id'],
            'name' => sanitizeString($driver['name']),
            'email' => sanitizeString($driver['email']),
            'phone' => $driver['phone'] ?? '',
            'bio' => $driver['bio'] ?? '',
            'profile_photo' => $photo,
            'created_at' => $driver['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Get profile error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
