<?php
/**
 * Driver Tours API
 * Get tours for authenticated driver
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

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
    
    $status_filter = isset($_GET['status']) ? sanitizeString($_GET['status'], 20) : '';
    $limit = clampInt($_GET['limit'] ?? null, 1, 100, 50);
    
    $query = "
        SELECT 
            t.id,
            t.tour_code,
            t.display_name,
            t.status,
            t.tour_date,
            t.departure_time,
            t.started_at,
            t.ended_at,
            t.duration_minutes,
            c.name as city_name,
            c.slug as city_slug,
            cc.name as current_city_name,
            cc.slug as current_city_slug,
            (SELECT COUNT(DISTINCT session_id) FROM tour_participants WHERE tour_id = t.id) as participant_count
        FROM tours t
        LEFT JOIN cities c ON t.city_id = c.id
        LEFT JOIN cities cc ON t.current_city_id = cc.id
        WHERE t.driver_id = ?
          AND t.status NOT IN ('completed', 'cancelled')
          AND (t.tour_date = CURDATE() OR t.status = 'active')
    ";
    
    $params = [$driver['id']];
    $types = "i";
    
    // Only add status filter if explicitly requesting completed/cancelled/all
    if ($status_filter === 'completed' || $status_filter === 'cancelled') {
        // Override the default filter to show only this status
        $query = str_replace("AND t.status NOT IN ('completed', 'cancelled')", "AND t.status = ?", $query);
        $query = str_replace("AND (t.tour_date = CURDATE() OR t.status = 'active')", "", $query);
        $params[] = $status_filter;
        $types .= "s";
    } elseif ($status_filter === 'all') {
        // Show everything - remove the status filter
        $query = str_replace("AND t.status NOT IN ('completed', 'cancelled')", "", $query);
        $query = str_replace("AND (t.tour_date = CURDATE() OR t.status = 'active')", "", $query);
    } elseif ($status_filter && in_array($status_filter, ['active', 'scheduled'])) {
        $query .= " AND t.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    $query .= " ORDER BY t.tour_date DESC, t.departure_time DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tours = [];
    while ($row = $result->fetch_assoc()) {
        $tours[] = [
            'id' => (int) $row['id'],
            'tour_code' => $row['tour_code'],
            'display_name' => sanitizeString($row['display_name']),
            'status' => $row['status'],
            'tour_date' => $row['tour_date'],
            'departure_time' => $row['departure_time'],
            'started_at' => $row['started_at'],
            'ended_at' => $row['ended_at'],
            'duration_minutes' => (int) $row['duration_minutes'],
            'city_name' => sanitizeString($row['city_name']),
            'city_slug' => $row['city_slug'],
            'current_city_name' => sanitizeString($row['current_city_name']),
            'current_city_slug' => $row['current_city_slug'],
            'participant_count' => (int) $row['participant_count']
        ];
    }
    
    jsonSuccess(['tours' => $tours, 'count' => count($tours)]);
    
} catch (Exception $e) {
    error_log('Driver tours error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
