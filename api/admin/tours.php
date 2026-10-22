<?php
/**
 * Admin Tours API
 * Manage tours - list, view, update status
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    // Verify admin token
    $token = requestToken('admin');
    $admin = requireAdmin($conn, $token, $_SERVER['REQUEST_METHOD'] === 'GET' ? 'analyst' : 'admin');
    
    // POST - Update tour status
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = getJsonInput();
        $tour_id = (int) ($input['tour_id'] ?? 0);
        $action = $input['action'] ?? '';
        
        if (!$tour_id) {
            jsonError('Tour ID required');
        }
        
        $valid_actions = ['activate', 'complete', 'cancel'];
        if (!in_array($action, $valid_actions)) {
            jsonError('Invalid action');
        }
        
        $status_map = [
            'activate' => 'active',
            'complete' => 'completed',
            'cancel' => 'cancelled'
        ];
        
        $new_status = $status_map[$action];
        
        $stmt = $conn->prepare("UPDATE tours SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $tour_id);
        
        if ($stmt->execute()) {
            logSecurityEvent('tour_status_changed', [
                'tour_id' => $tour_id,
                'new_status' => $new_status,
                'admin_id' => $admin['id']
            ]);
            jsonSuccess(['message' => "Tour $action" . "d successfully"]);
        }
        jsonError('Failed to update tour', 500);
    }
    
    // DELETE - Remove tour
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $tour_id = (int) ($_GET['id'] ?? 0);
        
        if (!$tour_id) {
            jsonError('Tour ID required');
        }
        
        // Check if tour has participants
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tour_participants WHERE tour_id = ?");
        $stmt->bind_param("i", $tour_id);
        $stmt->execute();
        $participant_count = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($participant_count > 0) {
            jsonError("Cannot delete tour with $participant_count participants");
        }
        
        $stmt = $conn->prepare("DELETE FROM tours WHERE id = ?");
        $stmt->bind_param("i", $tour_id);
        
        if ($stmt->execute()) {
            logSecurityEvent('tour_deleted', ['tour_id' => $tour_id, 'admin_id' => $admin['id']]);
            jsonSuccess(['message' => 'Tour deleted']);
        }
        jsonError('Failed to delete tour', 500);
    }
    
    // GET - Retrieve tours
    $status_filter = isset($_GET['status']) ? sanitizeString($_GET['status'], 20) : '';
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $search = isset($_GET['search']) ? sanitizeString($_GET['search'], 100) : '';
    $limit = clampInt($_GET['limit'] ?? null, 1, 500, 100);
    $offset = clampInt($_GET['offset'] ?? null, 0, 1000000, 0);
    
    // Sanitize dates
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    $query = "
        SELECT 
            t.*,
            c.name as city_name,
            c.slug as city_slug,
            d.name as driver_name,
            d.email as driver_email,
            (SELECT COUNT(DISTINCT session_id) FROM tour_participants WHERE tour_id = t.id) as participant_count,
            (SELECT AVG(rating) FROM tour_feedback WHERE tour_id = t.id) as avg_rating
        FROM tours t
        LEFT JOIN cities c ON t.city_id = c.id
        LEFT JOIN drivers d ON t.driver_id = d.id
        WHERE t.tour_date BETWEEN ? AND ?
    ";
    
    $params = [$date_from, $date_to];
    $types = "ss";
    
    if ($status_filter && in_array($status_filter, ['active', 'completed', 'cancelled'])) {
        $query .= " AND t.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    if ($search) {
        $query .= " AND (t.tour_code LIKE ? OR t.display_name LIKE ? OR d.name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }
    
    $query .= " ORDER BY t.tour_date DESC, t.departure_time DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
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
            'duration_minutes' => (int) $row['duration_minutes'],
            'city_name' => sanitizeString($row['city_name']),
            'city_slug' => $row['city_slug'],
            'driver_name' => sanitizeString($row['driver_name']),
            'driver_email' => $row['driver_email'],
            'participant_count' => (int) $row['participant_count'],
            'avg_rating' => $row['avg_rating'] ? round((float) $row['avg_rating'], 1) : null,
            'created_at' => $row['created_at']
        ];
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM tours t WHERE t.tour_date BETWEEN ? AND ?";
    $count_params = [$date_from, $date_to];
    $count_types = "ss";
    
    if ($status_filter && in_array($status_filter, ['active', 'completed', 'cancelled'])) {
        $count_query .= " AND t.status = ?";
        $count_params[] = $status_filter;
        $count_types .= "s";
    }
    
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($count_types, ...$count_params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    jsonSuccess([
        'tours' => $tours,
        'total' => (int) $total,
        'showing' => count($tours)
    ]);
    
} catch (Exception $e) {
    error_log('Admin tours error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
