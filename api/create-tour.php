<?php
/**
 * Create Tour API
 * Creates a new tour for an authenticated driver
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
    $stmt = $conn->prepare("SELECT id, name, phone FROM drivers WHERE auth_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$driver = $result->fetch_assoc()) {
        jsonError('Invalid or expired token', 403);
    }
    
    $input = getJsonInput();
    
    // Validate input
    if (!isset($input['city_id']) || !isset($input['display_name']) || 
        !isset($input['departure_time']) || !isset($input['duration_minutes'])) {
        jsonError('Missing required fields');
    }
    
    // Sanitize inputs
    $city_id = (int) $input['city_id'];
    $display_name = sanitizeString($input['display_name'], 100);
    $departure_time = sanitizeString($input['departure_time'], 10);
    $duration_minutes = (int) $input['duration_minutes'];
    
    // Validate duration
    if ($duration_minutes < 15 || $duration_minutes > 480) {
        jsonError('Duration must be between 15 and 480 minutes');
    }
    
    // Get city slug for tour code
    $stmt = $conn->prepare("SELECT slug FROM cities WHERE id = ?");
    $stmt->bind_param("i", $city_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$city = $result->fetch_assoc()) {
        jsonError('Invalid city');
    }
    
    // Generate tour code: CITY-TIME-DATE
    $time_part = date('ga', strtotime($departure_time));
    $date_part = strtoupper(date('MD', strtotime('today')));
    $city_prefix = strtoupper(substr($city['slug'], 0, 4));
    $tour_code = "{$city_prefix}-{$time_part}-{$date_part}";
    
    // Ensure unique code
    $counter = 1;
    $original_code = $tour_code;
    $stmt = $conn->prepare("SELECT id FROM tours WHERE tour_code = ?");
    while (true) {
        $stmt->bind_param("s", $tour_code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) break;
        $tour_code = $original_code . '-' . $counter++;
    }
    
    // Create tour
    $today = date('Y-m-d');
    $status = 'active';
    
    $stmt = $conn->prepare("
        INSERT INTO tours 
        (city_id, current_city_id, tour_code, display_name, tour_date, departure_time, duration_minutes, 
         status, driver_name, driver_phone, driver_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "iissssisssi",
        $city_id,
        $city_id,
        $tour_code,
        $display_name,
        $today,
        $departure_time,
        $duration_minutes,
        $status,
        $driver['name'],
        $driver['phone'],
        $driver['id']
    );
    
    if ($stmt->execute()) {
        logSecurityEvent('tour_created', [
            'tour_code' => $tour_code,
            'driver_id' => $driver['id']
        ]);
        
        jsonSuccess([
            'tour_code' => $tour_code,
            'tour_id' => $conn->insert_id,
            'display_name' => $display_name,
            'message' => 'Tour created successfully'
        ]);
    } else {
        jsonError('Failed to create tour', 500);
    }
    
} catch (Exception $e) {
    error_log('Create tour error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
