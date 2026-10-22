<?php
/**
 * Record Location Visit API
 * Tracks when tourists enter/exit landmark areas
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

// Public tourist-facing endpoint: keep it from being used to flood the table
checkRateLimit('locvisit_' . getClientIp(), 300, 3600);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

try {
    $conn = getDbConnection();
    
    // Create table if it doesn't exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS location_visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tour_id INT,
            session_id VARCHAR(64),
            location_id VARCHAR(50),
            location_name VARCHAR(100),
            city_slug VARCHAR(50),
            entered_at DATETIME,
            exited_at DATETIME,
            dwell_seconds INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tour (tour_id),
            INDEX idx_location (location_id),
            INDEX idx_city (city_slug),
            INDEX idx_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $input = getJsonInput();
    
    $session_id = isset($input['session_id']) ? sanitizeString($input['session_id'], 64) : '';
    $tour_code = isset($input['tour_code']) ? sanitizeString($input['tour_code'], 50) : '';
    $location_id = isset($input['location_id']) ? sanitizeString($input['location_id'], 50) : '';
    $location_name = isset($input['location_name']) ? sanitizeString($input['location_name'], 100) : '';
    $city_slug = isset($input['city_slug']) ? sanitizeString($input['city_slug'], 50) : '';
    $entered_at = isset($input['entered_at']) ? sanitizeString($input['entered_at'], 30) : '';
    $exited_at = isset($input['exited_at']) ? sanitizeString($input['exited_at'], 30) : '';
    $dwell_seconds = isset($input['dwell_seconds']) && is_numeric($input['dwell_seconds']) ? max(0, min(86400, (int) $input['dwell_seconds'])) : 0;
    // Timestamps: accept only things strtotime understands, store in MySQL format (or NULL)
    $toDateTime = function ($v) {
        $t = $v === '' ? false : strtotime($v);
        return $t === false ? null : date('Y-m-d H:i:s', $t);
    };
    $entered_at = $toDateTime($entered_at);
    $exited_at = $toDateTime($exited_at);
    
    if (empty($session_id) || empty($location_id)) {
        jsonError('Session ID and location ID required');
    }
    
    // Get tour_id from tour_code if provided
    $tour_id = null;
    if (!empty($tour_code)) {
        $stmt = $conn->prepare("SELECT id FROM tours WHERE tour_code = ?");
        $stmt->bind_param("s", $tour_code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $tour_id = $row['id'];
        } else {
            jsonError('Tour not found', 404);
        }
    }
    
    $stmt = $conn->prepare("
        INSERT INTO location_visits 
        (tour_id, session_id, location_id, location_name, city_slug, entered_at, exited_at, dwell_seconds)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issssssi", $tour_id, $session_id, $location_id, $location_name, $city_slug, $entered_at, $exited_at, $dwell_seconds);
    
    if ($stmt->execute()) {
        jsonSuccess(['message' => 'Location visit recorded']);
    } else {
        jsonError('Failed to record visit', 500);
    }
    
} catch (Exception $e) {
    error_log('Record location visit error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
