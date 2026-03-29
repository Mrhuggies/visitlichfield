<?php
// Temporary script to clear rate limits - DELETE AFTER USE
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    // Clear all expired entries
    $conn->query("DELETE FROM rate_limits WHERE expires_at < NOW()");
    
    // Clear all rate limits (or you could target specific ones)
    $result = $conn->query("DELETE FROM rate_limits");
    
    echo json_encode([
        'success' => true,
        'message' => 'All rate limits cleared',
        'rows_deleted' => $conn->affected_rows
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
