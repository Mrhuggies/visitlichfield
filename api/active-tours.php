<?php
/**
 * Active Tours API
 * Get currently active tours
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    $city_id = isset($_GET['city_id']) ? (int) $_GET['city_id'] : null;
    
    $query = "
        SELECT 
            t.id,
            t.tour_code,
            t.display_name,
            t.status,
            t.departure_time,
            t.started_at,
            c.name as city_name,
            c.slug as city_slug,
            d.name as driver_name,
            (SELECT COUNT(DISTINCT session_id) FROM tour_participants WHERE tour_id = t.id) as participant_count
        FROM tours t
        LEFT JOIN cities c ON t.city_id = c.id
        LEFT JOIN drivers d ON t.driver_id = d.id
        WHERE t.status = 'active' AND t.tour_date = CURDATE()
    ";
    
    $params = [];
    $types = "";
    
    if ($city_id) {
        $query .= " AND t.city_id = ?";
        $params[] = $city_id;
        $types .= "i";
    }
    
    $query .= " ORDER BY t.departure_time ASC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tours = [];
    while ($row = $result->fetch_assoc()) {
        $tours[] = [
            'id' => (int) $row['id'],
            'tour_code' => $row['tour_code'],
            'display_name' => sanitizeString($row['display_name']),
            'status' => $row['status'],
            'departure_time' => $row['departure_time'],
            'started_at' => $row['started_at'],
            'city_name' => sanitizeString($row['city_name']),
            'city_slug' => $row['city_slug'],
            'driver_name' => sanitizeString($row['driver_name']),
            'participant_count' => (int) $row['participant_count']
        ];
    }
    
    jsonSuccess(['tours' => $tours, 'count' => count($tours)]);
    
} catch (Exception $e) {
    error_log('Active tours API error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
