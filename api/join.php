<?php
/**
 * Join Tour API
 * Register tourist for a tour (creates session)
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

checkRateLimit('join_' . getClientIp(), 60, 3600);

try {
    $conn = getDbConnection();
    $input = getJsonInput();
    
    if (!isset($input['tour_code']) || !isset($input['session_id'])) {
        jsonError('tour_code and session_id required');
    }
    
    $tour_code = strtoupper(sanitizeString($input['tour_code'], 20));
    $session_id = sanitizeString($input['session_id'], 64);
    if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $session_id)) {
        jsonError('Invalid session_id');
    }
    
    // Validate tour code format
    if (!preg_match('/^[A-Z0-9-]{4,20}$/', $tour_code)) {
        jsonError('Invalid tour code format');
    }
    
    // Get tour ID
    $stmt = $conn->prepare("SELECT id, status FROM tours WHERE tour_code = ?");
    $stmt->bind_param("s", $tour_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$tour = $result->fetch_assoc()) {
        jsonError('Tour not found', 404);
    }
    
    // Check if tour is active
    if ($tour['status'] !== 'active') {
        jsonError('This tour is no longer active');
    }
    
    $tour_id = $tour['id'];
    
    // Check if already joined
    $stmt = $conn->prepare("SELECT id FROM tour_participants WHERE tour_id = ? AND session_id = ?");
    $stmt->bind_param("is", $tour_id, $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->fetch_assoc()) {
        jsonSuccess([
            'message' => 'Already joined this tour',
            'new_join' => false
        ]);
    }
    
    // Add participant
    $stmt = $conn->prepare("INSERT INTO tour_participants (tour_id, session_id) VALUES (?, ?)");
    $stmt->bind_param("is", $tour_id, $session_id);
    
    if ($stmt->execute()) {
        jsonSuccess([
            'message' => 'Joined tour successfully',
            'new_join' => true
        ]);
    } else {
        jsonError('Failed to join tour', 500);
    }
    
} catch (Exception $e) {
    error_log('Join tour error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
