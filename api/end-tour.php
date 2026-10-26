<?php
/**
 * End Tour API
 * Mark a tour as completed
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

try {
    $conn = getDbConnection();
    
    // Check auth token
    $token = requestToken('driver') ?: null;
    
    if (!$token) {
        jsonError('Authentication required', 401);
    }
    
    // Validate token and get driver
    $stmt = $conn->prepare("SELECT id FROM drivers WHERE auth_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$driver = $result->fetch_assoc()) {
        jsonError('Invalid or expired token', 403);
    }
    
    $input = getJsonInput();
    $tour_code = isset($input['tour_code']) ? sanitizeString($input['tour_code'], 20) : '';
    
    if (empty($tour_code)) {
        jsonError('Tour code required');
    }
    
    // Verify driver owns this tour
    $stmt = $conn->prepare("SELECT id, status FROM tours WHERE tour_code = ? AND driver_id = ?");
    $stmt->bind_param("si", $tour_code, $driver['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$tour = $result->fetch_assoc()) {
        jsonError('Tour not found or access denied', 404);
    }
    
    if ($tour['status'] === 'completed') {
        jsonError('Tour is already completed');
    }
    
    // End the tour
    $stmt = $conn->prepare("UPDATE tours SET status = 'completed', ended_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $tour['id']);
    
    if ($stmt->execute()) {
        logSecurityEvent('tour_ended', ['tour_code' => $tour_code, 'driver_id' => $driver['id']]);
        
        jsonSuccess([
            'message' => 'Tour ended successfully',
            'tour_code' => $tour_code
        ]);
    } else {
        jsonError('Failed to end tour', 500);
    }
    
} catch (Exception $e) {
    error_log('End tour error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
