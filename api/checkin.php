<?php
/**
 * Check-in API
 * Record visitor check-in at a location
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

checkRateLimit('checkin_' . getClientIp(), 200, 3600);

try {
    $conn = getDbConnection();
    $input = getJsonInput();
    
    $tour_code = isset($input['tour_code']) ? sanitizeString($input['tour_code'], 20) : '';
    $session_id = isset($input['session_id']) ? sanitizeString($input['session_id'], 64) : '';
    $location_id = isset($input['location_id']) ? sanitizeString($input['location_id'], 50) : '';
    
    if (empty($tour_code) || empty($session_id) || empty($location_id)) {
        jsonError('tour_code, session_id, and location_id required');
    }
    
    // Get tour ID
    $stmt = $conn->prepare("SELECT id FROM tours WHERE tour_code = ? AND status = 'active'");
    $stmt->bind_param("s", $tour_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$tour = $result->fetch_assoc()) {
        jsonError('Active tour not found', 404);
    }
    
    $tour_id = $tour['id'];
    
    // Check if already checked in at this location
    $stmt = $conn->prepare("SELECT id FROM tour_checkins WHERE tour_id = ? AND session_id = ? AND location_id = ?");
    $stmt->bind_param("iss", $tour_id, $session_id, $location_id);
    $stmt->execute();
    
    if ($stmt->get_result()->fetch_assoc()) {
        jsonSuccess([
            'message' => 'Already checked in at this location',
            'new_checkin' => false
        ]);
    }
    
    // Record check-in
    $stmt = $conn->prepare("INSERT INTO tour_checkins (tour_id, session_id, location_id, checked_in_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $tour_id, $session_id, $location_id);
    
    if ($stmt->execute()) {
        jsonSuccess([
            'message' => 'Checked in successfully',
            'new_checkin' => true,
            'location_id' => $location_id
        ]);
    } else {
        jsonError('Failed to record check-in', 500);
    }
    
} catch (Exception $e) {
    error_log('Check-in error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
