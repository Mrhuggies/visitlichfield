<?php
/**
 * Admin Dashboard Stats API
 * Returns overview statistics for the admin dashboard
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
    
    // Get date range (default: last 30 days)
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    // Sanitize dates
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    // 1. Total tours
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tours WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $total_tours = $stmt->get_result()->fetch_assoc()['total'];
    
    // 2. Total check-ins
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT session_id) as total 
        FROM tour_participants tp
        JOIN tours t ON tp.tour_id = t.id
        WHERE DATE(tp.joined_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $total_checkins = $stmt->get_result()->fetch_assoc()['total'];
    
    // 3. Active tours right now
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tours WHERE status = 'active'");
    $stmt->execute();
    $active_now = $stmt->get_result()->fetch_assoc()['total'];
    
    // 4. Average rating
    $stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as count
        FROM tour_feedback tf
        JOIN tours t ON tf.tour_id = t.id
        WHERE DATE(tf.submitted_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $rating_data = $stmt->get_result()->fetch_assoc();
    $avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
    $feedback_count = $rating_data['count'];
    
    // 5. Tours per day (last 30 days for chart - include all days even with 0 tours)
    $chart_days = 30;
    $chart_start = date('Y-m-d', strtotime("-" . ($chart_days - 1) . " days"));
    $chart_end = date('Y-m-d');
    
    // Get actual tour counts
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM tours
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ");
    $stmt->bind_param("ss", $chart_start, $chart_end);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Build lookup of actual data
    $tour_counts = [];
    while ($row = $result->fetch_assoc()) {
        $tour_counts[$row['date']] = (int)$row['count'];
    }
    
    // Fill in all days including zeros
    $tours_per_day = [];
    $current = new DateTime($chart_start);
    $end = new DateTime($chart_end);
    $end->modify('+1 day');
    
    while ($current < $end) {
        $date_str = $current->format('Y-m-d');
        $tours_per_day[] = [
            'date' => $date_str,
            'count' => $tour_counts[$date_str] ?? 0
        ];
        $current->modify('+1 day');
    }
    
    // 6. Top drivers
    $stmt = $conn->prepare("
        SELECT d.name, COUNT(t.id) as tour_count, AVG(COALESCE(tf.rating, 0)) as avg_rating
        FROM drivers d
        LEFT JOIN tours t ON d.id = t.driver_id AND DATE(t.created_at) BETWEEN ? AND ?
        LEFT JOIN tour_feedback tf ON t.id = tf.tour_id
        WHERE t.id IS NOT NULL
        GROUP BY d.id
        ORDER BY tour_count DESC
        LIMIT 5
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    $top_drivers = [];
    while ($row = $result->fetch_assoc()) {
        $top_drivers[] = [
            'name' => sanitizeString($row['name']),
            'tours' => (int)$row['tour_count'],
            'rating' => $row['avg_rating'] ? round($row['avg_rating'], 1) : 0
        ];
    }
    
    // 7. Recent activity
    $stmt = $conn->prepare("
        SELECT 
            t.tour_code,
            t.display_name,
            t.status,
            d.name as driver_name,
            c.name as city_name,
            t.created_at,
            (SELECT COUNT(DISTINCT session_id) FROM tour_participants WHERE tour_id = t.id) as participants
        FROM tours t
        JOIN drivers d ON t.driver_id = d.id
        JOIN cities c ON t.city_id = c.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_activity = [];
    while ($row = $result->fetch_assoc()) {
        $row['display_name'] = sanitizeString($row['display_name']);
        $row['driver_name'] = sanitizeString($row['driver_name']);
        $row['city_name'] = sanitizeString($row['city_name']);
        $recent_activity[] = $row;
    }
    
    jsonSuccess([
        'date_range' => ['from' => $date_from, 'to' => $date_to],
        'stats' => [
            'total_tours' => (int)$total_tours,
            'total_checkins' => (int)$total_checkins,
            'active_now' => (int)$active_now,
            'avg_rating' => (float)$avg_rating,
            'feedback_count' => (int)$feedback_count
        ],
        'charts' => [
            'tours_per_day' => $tours_per_day
        ],
        'top_drivers' => $top_drivers,
        'recent_activity' => $recent_activity
    ]);
    
} catch (Exception $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
