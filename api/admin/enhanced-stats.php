<?php
/**
 * Admin Enhanced Stats API
 * Get detailed analytics
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    // Verify admin token
    $token = $_GET['token'] ?? '';
    if (!$token) {
        jsonError('Authorization required', 401);
    }
    
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE auth_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        jsonError('Invalid or expired token', 401);
    }
    
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    // Sanitize dates
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    // Tours by city
    $stmt = $conn->prepare("
        SELECT c.name as city_name, COUNT(t.id) as tour_count, 
               SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
               COUNT(DISTINCT tp.session_id) as participants
        FROM cities c
        LEFT JOIN tours t ON c.id = t.city_id AND t.tour_date BETWEEN ? AND ?
        LEFT JOIN tour_participants tp ON t.id = tp.tour_id
        WHERE c.active = 1
        GROUP BY c.id
        ORDER BY tour_count DESC
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    $by_city = [];
    while ($row = $result->fetch_assoc()) {
        $by_city[] = [
            'city' => sanitizeString($row['city_name']),
            'tours' => (int) $row['tour_count'],
            'completed' => (int) $row['completed'],
            'participants' => (int) $row['participants']
        ];
    }
    
    // Tours by day of week
    $stmt = $conn->prepare("
        SELECT DAYNAME(tour_date) as day_name, DAYOFWEEK(tour_date) as day_num, COUNT(*) as count
        FROM tours
        WHERE tour_date BETWEEN ? AND ?
        GROUP BY day_name, day_num
        ORDER BY day_num
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    $by_day = [];
    while ($row = $result->fetch_assoc()) {
        $by_day[] = [
            'day' => $row['day_name'],
            'count' => (int) $row['count']
        ];
    }
    
    // Rating breakdown
    $stmt = $conn->prepare("
        SELECT rating, COUNT(*) as count
        FROM tour_feedback tf
        JOIN tours t ON tf.tour_id = t.id
        WHERE t.tour_date BETWEEN ? AND ?
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    $ratings = [];
    while ($row = $result->fetch_assoc()) {
        $ratings[(int) $row['rating']] = (int) $row['count'];
    }
    
    // Peak hours
    $stmt = $conn->prepare("
        SELECT HOUR(departure_time) as hour, COUNT(*) as count
        FROM tours
        WHERE tour_date BETWEEN ? AND ?
        GROUP BY hour
        ORDER BY hour
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    $by_hour = [];
    while ($row = $result->fetch_assoc()) {
        $by_hour[] = [
            'hour' => (int) $row['hour'],
            'count' => (int) $row['count']
        ];
    }
    
    jsonSuccess([
        'date_range' => ['from' => $date_from, 'to' => $date_to],
        'by_city' => $by_city,
        'by_day' => $by_day,
        'by_hour' => $by_hour,
        'ratings' => $ratings
    ]);
    
} catch (Exception $e) {
    error_log('Enhanced stats error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
