<?php
/**
 * Admin Feedback API
 * Get and manage feedback with filters
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
    
    // DELETE - Remove feedback
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $feedback_id = (int) ($_GET['id'] ?? 0);
        
        if (!$feedback_id) {
            jsonError('Feedback ID required');
        }
        
        $stmt = $conn->prepare("DELETE FROM tour_feedback WHERE id = ?");
        $stmt->bind_param("i", $feedback_id);
        
        if ($stmt->execute()) {
            logSecurityEvent('feedback_deleted', ['feedback_id' => $feedback_id]);
            jsonSuccess(['message' => 'Feedback deleted']);
        } else {
            jsonError('Failed to delete feedback', 500);
        }
    }
    
    // GET - Retrieve feedback with filters
    $rating_filter = (int) ($_GET['rating'] ?? 0);
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $tour_code = isset($_GET['tour_code']) ? sanitizeString($_GET['tour_code'], 20) : '';
    $search = isset($_GET['search']) ? sanitizeString($_GET['search'], 100) : '';
    $limit = min((int) ($_GET['limit'] ?? 100), 500);
    $offset = (int) ($_GET['offset'] ?? 0);
    
    // Sanitize dates
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    // Build query
    $query = "
        SELECT 
            tf.id,
            tf.tour_id,
            tf.rating,
            tf.feedback_text,
            tf.submitted_at,
            t.tour_code,
            t.display_name as tour_name,
            t.tour_date,
            c.name as city_name,
            d.name as driver_name
        FROM tour_feedback tf
        JOIN tours t ON tf.tour_id = t.id
        LEFT JOIN cities c ON t.city_id = c.id
        LEFT JOIN drivers d ON t.driver_id = d.id
        WHERE DATE(tf.submitted_at) BETWEEN ? AND ?
    ";
    
    $params = [$date_from, $date_to];
    $types = "ss";
    
    if ($rating_filter > 0 && $rating_filter <= 5) {
        $query .= " AND tf.rating = ?";
        $params[] = $rating_filter;
        $types .= "i";
    }
    
    if ($tour_code) {
        $query .= " AND t.tour_code LIKE ?";
        $params[] = "%$tour_code%";
        $types .= "s";
    }
    
    if ($search) {
        $query .= " AND tf.feedback_text LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    
    $query .= " ORDER BY tf.submitted_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $feedback = [];
    while ($row = $result->fetch_assoc()) {
        $row['feedback_text'] = sanitizeString($row['feedback_text'], 2000);
        $row['tour_name'] = sanitizeString($row['tour_name']);
        $row['city_name'] = sanitizeString($row['city_name']);
        $row['driver_name'] = sanitizeString($row['driver_name']);
        $feedback[] = $row;
    }
    
    // Get total count (for pagination)
    $count_query = "SELECT COUNT(*) as total FROM tour_feedback tf JOIN tours t ON tf.tour_id = t.id WHERE DATE(tf.submitted_at) BETWEEN ? AND ?";
    $count_params = [$date_from, $date_to];
    $count_types = "ss";
    
    if ($rating_filter > 0 && $rating_filter <= 5) {
        $count_query .= " AND tf.rating = ?";
        $count_params[] = $rating_filter;
        $count_types .= "i";
    }
    
    if ($tour_code) {
        $count_query .= " AND t.tour_code LIKE ?";
        $count_params[] = "%$tour_code%";
        $count_types .= "s";
    }
    
    if ($search) {
        $count_query .= " AND tf.feedback_text LIKE ?";
        $count_params[] = "%$search%";
        $count_types .= "s";
    }
    
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($count_types, ...$count_params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get rating distribution
    $stmt = $conn->prepare("
        SELECT rating, COUNT(*) as count
        FROM tour_feedback tf
        JOIN tours t ON tf.tour_id = t.id
        WHERE DATE(tf.submitted_at) BETWEEN ? AND ?
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rating_distribution = [];
    while ($row = $result->fetch_assoc()) {
        $rating_distribution[$row['rating']] = (int) $row['count'];
    }
    
    jsonSuccess([
        'feedback' => $feedback,
        'total' => (int) $total,
        'showing' => count($feedback),
        'rating_distribution' => $rating_distribution
    ]);
    
} catch (Exception $e) {
    error_log('Feedback API error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
