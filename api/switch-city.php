<?php
/**
 * Switch City API
 * Change the current city for an active tour
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
    $token = $_SERVER['HTTP_X_DRIVER_TOKEN'] ?? null;
    
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    $tour_code = isset($input['tour_code']) ? sanitizeString($input['tour_code'], 20) : '';
    $new_city_id = isset($input['city_id']) ? (int) $input['city_id'] : 0;
    $new_city_slug = isset($input['city_slug']) ? sanitizeString($input['city_slug'], 50) : '';
    $departure_time = isset($input['departure_time']) ? sanitizeString($input['departure_time'], 10) : null;
    
    if (empty($tour_code) || (!$new_city_id && empty($new_city_slug))) {
        jsonError('Tour code and city_id or city_slug required');
    }
    
    // If we have slug but not id, look up the city id
    if (!$new_city_id && !empty($new_city_slug)) {
        $stmt = $conn->prepare("SELECT id FROM cities WHERE slug = ?");
        $stmt->bind_param("s", $new_city_slug);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $new_city_id = (int) $row['id'];
        } else {
            jsonError('City not found');
        }
    }
    
    // Verify driver owns this tour and it's active
    $stmt = $conn->prepare("SELECT id, status FROM tours WHERE tour_code = ? AND driver_id = ? AND status = 'active'");
    $stmt->bind_param("si", $tour_code, $driver['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$tour = $result->fetch_assoc()) {
        jsonError('Active tour not found or access denied', 404);
    }
    
    // Verify city exists
    $stmt = $conn->prepare("SELECT id, name, slug FROM cities WHERE id = ?");
    $stmt->bind_param("i", $new_city_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$city = $result->fetch_assoc()) {
        jsonError('Invalid city');
    }
    
    // Update tour's current city and departure time if provided
    if ($departure_time) {
        $stmt = $conn->prepare("UPDATE tours SET current_city_id = ?, departure_time = ? WHERE id = ?");
        $stmt->bind_param("isi", $new_city_id, $departure_time, $tour['id']);
    } else {
        $stmt = $conn->prepare("UPDATE tours SET current_city_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_city_id, $tour['id']);
    }
    
    if ($stmt->execute()) {
        logSecurityEvent('city_switched', [
            'tour_code' => $tour_code,
            'new_city_id' => $new_city_id,
            'driver_id' => $driver['id']
        ]);
        
        jsonSuccess([
            'message' => 'City switched successfully',
            'new_city' => [
                'id' => (int) $city['id'],
                'name' => sanitizeString($city['name']),
                'slug' => $city['slug']
            ]
        ]);
    } else {
        jsonError('Failed to switch city', 500);
    }
    
} catch (Exception $e) {
    error_log('Switch city error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
