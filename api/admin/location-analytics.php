<?php
/**
 * Location Analytics API
 * Returns aggregated dwell time data for admin dashboard
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    // Verify admin token
    $token = requestToken('admin');
    $admin = requireAdmin($conn, $token, 'analyst');
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'location_visits'");
    if ($table_check->num_rows === 0) {
        jsonSuccess([
            'by_location' => [],
            'by_city' => [],
            'totals' => ['total_visits' => 0, 'avg_dwell_seconds' => 0, 'avg_dwell_minutes' => 0]
        ]);
    }
    
    // Get date range from params
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $city_filter = isset($_GET['city']) ? sanitizeString($_GET['city'], 50) : '';
    
    // 1. Dwell time by location (most time spent first)
    $query = "
        SELECT 
            location_id,
            location_name,
            city_slug,
            COUNT(*) as visit_count,
            AVG(dwell_seconds) as avg_dwell,
            SUM(dwell_seconds) as total_dwell,
            MIN(dwell_seconds) as min_dwell,
            MAX(dwell_seconds) as max_dwell
        FROM location_visits
        WHERE dwell_seconds > 0 
          AND DATE(entered_at) BETWEEN ? AND ?
    ";
    if ($city_filter) {
        $query .= " AND city_slug = ?";
    }
    $query .= " GROUP BY location_id, location_name, city_slug ORDER BY avg_dwell DESC";
    
    $stmt = $conn->prepare($query);
    if ($city_filter) {
        $stmt->bind_param("sss", $date_from, $date_to, $city_filter);
    } else {
        $stmt->bind_param("ss", $date_from, $date_to);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $by_location = [];
    while ($row = $result->fetch_assoc()) {
        $by_location[] = [
            'location_id' => $row['location_id'],
            'location_name' => $row['location_name'],
            'city' => $row['city_slug'],
            'visits' => (int) $row['visit_count'],
            'avg_seconds' => round($row['avg_dwell']),
            'avg_minutes' => round($row['avg_dwell'] / 60, 1),
            'total_seconds' => (int) $row['total_dwell'],
            'min_seconds' => (int) $row['min_dwell'],
            'max_seconds' => (int) $row['max_dwell']
        ];
    }
    
    // 2. Summary by city
    $stmt = $conn->prepare("
        SELECT 
            city_slug,
            COUNT(*) as visit_count,
            COUNT(DISTINCT location_id) as unique_locations,
            AVG(dwell_seconds) as avg_dwell,
            SUM(dwell_seconds) as total_dwell
        FROM location_visits
        WHERE dwell_seconds > 0 
          AND DATE(entered_at) BETWEEN ? AND ?
        GROUP BY city_slug
        ORDER BY visit_count DESC
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $by_city = [];
    while ($row = $result->fetch_assoc()) {
        $by_city[] = [
            'city' => $row['city_slug'],
            'visits' => (int) $row['visit_count'],
            'unique_locations' => (int) $row['unique_locations'],
            'avg_seconds' => round($row['avg_dwell']),
            'avg_minutes' => round($row['avg_dwell'] / 60, 1),
            'total_seconds' => (int) $row['total_dwell']
        ];
    }
    
    // 3. Overall totals
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_visits,
            AVG(dwell_seconds) as avg_dwell,
            SUM(dwell_seconds) as total_dwell,
            COUNT(DISTINCT session_id) as unique_visitors
        FROM location_visits
        WHERE dwell_seconds > 0 
          AND DATE(entered_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    
    jsonSuccess([
        'date_range' => ['from' => $date_from, 'to' => $date_to],
        'by_location' => $by_location,
        'by_city' => $by_city,
        'totals' => [
            'total_visits' => (int) $totals['total_visits'],
            'avg_dwell_seconds' => round($totals['avg_dwell'] ?? 0),
            'avg_dwell_minutes' => round(($totals['avg_dwell'] ?? 0) / 60, 1),
            'total_dwell_seconds' => (int) $totals['total_dwell'],
            'unique_visitors' => (int) $totals['unique_visitors']
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Location analytics error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
