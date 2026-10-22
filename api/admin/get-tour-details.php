<?php
/**
 * Admin Get Tour Details API
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    $token = requestToken('admin');
    $tourCode = isset($_GET['code']) ? sanitizeString($_GET['code'], 50) : '';
    
    if (empty($token) || empty($tourCode)) {
        jsonError('Token and tour code required');
    }
    
    $admin = requireAdmin($conn, $token, 'analyst');
    
    $stmt = $conn->prepare("
        SELECT t.*, c.name as city_name, d.name as driver_name, d.phone as driver_phone
        FROM tours t
        LEFT JOIN cities c ON t.city_id = c.id
        LEFT JOIN drivers d ON t.driver_id = d.id
        WHERE t.tour_code = ?
    ");
    $stmt->bind_param("s", $tourCode);
    $stmt->execute();
    $tour = $stmt->get_result()->fetch_assoc();
    
    if (!$tour) {
        jsonError('Tour not found', 404);
    }
    
    $stmt = $conn->prepare("SELECT session_id, joined_at, checked_in FROM tour_participants WHERE tour_id = ?");
    $stmt->bind_param("i", $tour['id']);
    $stmt->execute();
    $participants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $feedback = [];
    $table_check = $conn->query("SHOW TABLES LIKE 'tour_feedback'");
    if ($table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT rating, feedback_text, submitted_at FROM tour_feedback WHERE tour_id = ?");
        $stmt->bind_param("i", $tour['id']);
        $stmt->execute();
        $feedback = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    jsonSuccess(['tour' => $tour, 'participants' => $participants, 'feedback' => $feedback]);
    
} catch (Exception $e) {
    error_log('Get tour details error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
