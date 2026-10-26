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
        $input = getJsonInput();
        
        // Only the signed-in driver who owns the tour may change it
        $token = requestToken('driver', $input);
        if ($token === '') {
            jsonError('Authentication required', 401);
        }
        $stmt = $conn->prepare("SELECT id FROM drivers WHERE auth_token = ? AND token_expires > NOW() AND active = 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        if (!$driver = $stmt->get_result()->fetch_assoc()) {
            jsonError('Invalid or expired token', 401);
        }

        $stmt = $conn->prepare("SELECT id FROM tours WHERE tour_code = ? AND driver_id = ?");
        $stmt->bind_param("si", $tour_code, $driver['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$tour = $result->fetch_assoc()) {
            jsonError('Tour not found or access denied', 404);
        }

        $updates = [];
        $params = [];
        $types = "";
        
        // Handle departure_time update
        if (array_key_exists('departure_time', $input)) {
            if ($input['departure_time'] === null || $input['departure_time'] === '') {
                $updates[] = "departure_time = NULL";
            } elseif (!is_string($input['departure_time']) || strlen($input['departure_time']) > 32 || strtotime($input['departure_time']) === false) {
                jsonError('Invalid departure_time');
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
            if (!is_string($input['actual_departure']) || strlen($input['actual_departure']) > 32 || strtotime($input['actual_departure']) === false) {
                jsonError('Invalid actual_departure');
            }
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
