<?php
/**
 * Tours API
 * GET: Get tour information by code
 * POST: Update tour departure_time, status, etc.
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    $tour_code = isset($_GET['code']) ? sanitizeString($_GET['code'], 20) : '';
    
    if (empty($tour_code)) {
        jsonError('Tour code required');
    }
    
    // Handle POST updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Get tour ID first
        $stmt = $conn->prepare("SELECT id FROM tours WHERE tour_code = ?");
        $stmt->bind_param("s", $tour_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$tour = $result->fetch_assoc()) {
            jsonError('Tour not found', 404);
        }
        
        $updates = [];
        $params = [];
        $types = "";
        
        // Handle departure_time update
        if (array_key_exists('departure_time', $input)) {
            if ($input['departure_time'] === null || $input['departure_time'] === '') {
                $updates[] = "departure_time = NULL";
            } else {
                $updates[] = "departure_time = ?";
                $params[] = $input['departure_time'];
                $types .= "s";
            }
        }
        
        // Handle status update
        if (isset($input['status']) && in_array($input['status'], ['scheduled', 'active', 'completed', 'cancelled'])) {
            $updates[] = "status = ?";
            $params[] = $input['status'];
            $types .= "s";
        }
        
        // Handle actual_departure update
        if (isset($input['actual_departure'])) {
            $updates[] = "actual_departure = ?";
            $params[] = $input['actual_departure'];
            $types .= "s";
        }
        
        if (empty($updates)) {
            jsonError('No valid fields to update');
        }
        
        // Add tour ID to params
        $params[] = $tour['id'];
        $types .= "i";
        
        $query = "UPDATE tours SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        if ($stmt->affected_rows >= 0) {
            jsonSuccess(['message' => 'Tour updated']);
        } else {
            jsonError('Failed to update tour');
        }
    }
    
    // Handle GET request
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            c.name as city_name,
            c.slug as city_slug,
            cc.name as current_city_name,
            cc.slug as current_city_slug,
            d.name as driver_name,
            d.phone as driver_phone,
            d.profile_photo as driver_photo
        FROM tours t
        LEFT JOIN cities c ON t.city_id = c.id
        LEFT JOIN cities cc ON t.current_city_id = cc.id
        LEFT JOIN drivers d ON t.driver_id = d.id
        WHERE t.tour_code = ?
    ");
    $stmt->bind_param("s", $tour_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$tour = $result->fetch_assoc()) {
        jsonError('Tour not found', 404);
    }
    
    // Get participant count
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT session_id) as count FROM tour_participants WHERE tour_id = ?");
    $stmt->bind_param("i", $tour['id']);
    $stmt->execute();
    $participant_count = $stmt->get_result()->fetch_assoc()['count'];
    
    // Sanitize output
    $tour['display_name'] = sanitizeString($tour['display_name']);
    $tour['driver_name'] = sanitizeString($tour['driver_name']);
    $tour['city_name'] = sanitizeString($tour['city_name']);
    $tour['current_city_name'] = sanitizeString($tour['current_city_name']);
    
    echo json_encode([
        'id' => (int) $tour['id'],
        'tour_code' => $tour['tour_code'],
        'display_name' => $tour['display_name'],
        'status' => $tour['status'],
        'tour_date' => $tour['tour_date'],
        'departure_time' => $tour['departure_time'],
        'duration_minutes' => (int) $tour['duration_minutes'],
        'city_id' => (int) $tour['city_id'],
        'city_name' => $tour['city_name'],
        'city_slug' => $tour['city_slug'],
        'current_city_id' => (int) $tour['current_city_id'],
        'current_city_name' => $tour['current_city_name'],
        'current_city_slug' => $tour['current_city_slug'],
        'driver_name' => $tour['driver_name'],
        'driver_phone' => $tour['driver_phone'],
        'driver_photo' => $tour['driver_photo'] ? BASE_URL . '/' . $tour['driver_photo'] : null,
        'participant_count' => (int) $participant_count,
        'started_at' => $tour['started_at'],
        'created_at' => $tour['created_at']
    ]);
    
} catch (Exception $e) {
    error_log('Tours API error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
