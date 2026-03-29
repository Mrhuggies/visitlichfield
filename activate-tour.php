<?php
/**
 * Activate Tour API
 * Mark a tour as departed/activated
 */
require_once __DIR__ . '/api/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

try {
    $conn = getDbConnection();
    
    // Check auth token
    $token = $_SERVER['HTTP_X_DRIVER_TOKEN'] ?? null;
    
    if (!$token) {
        jsonError('Authentication required', 401);
    }
    
    // Validate token and get driver
    $stmt = $conn->prepare("SELECT id FROM drivers WHERE auth_token = ? AND token_expires > NOW() AND active = 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$driver = $result->fetch_assoc()) {
        jsonError('Invalid or expired token', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $tour_code = isset($input['tour_code']) ? sanitizeString($input['tour_code'], 20) : '';
    
    if (empty($tour_code)) {
        jsonError('Tour code required');
    }
    
    // Verify driver owns this tour
    $stmt = $conn->prepare("SELECT id, status, started_at FROM tours WHERE tour_code = ? AND driver_id = ?");
    $stmt->bind_param("si", $tour_code, $driver['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$tour = $result->fetch_assoc()) {
        jsonError('Tour not found or access denied', 404);
    }
    
    if ($tour['started_at']) {
        jsonSuccess([
            'message' => 'Tour already activated',
            'started_at' => $tour['started_at']
        ]);
    }
    
    // Activate the tour
    $stmt = $conn->prepare("UPDATE tours SET started_at = NOW(), status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $tour['id']);
    
    if ($stmt->execute()) {
        logSecurityEvent('tour_activated', ['tour_code' => $tour_code, 'driver_id' => $driver['id']]);
        
        jsonSuccess([
            'message' => 'Tour activated successfully',
            'tour_code' => $tour_code,
            'started_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        jsonError('Failed to activate tour', 500);
    }
    
} catch (Exception $e) {
    error_log('Activate tour error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
