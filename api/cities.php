<?php
/**
 * Cities API
 * Get available cities
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    // Check what columns exist
    $columns_result = $conn->query("DESCRIBE cities");
    $existing_columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
    
    $has_active = in_array('active', $existing_columns);
    $has_description = in_array('description', $existing_columns);
    
    // Build query based on available columns
    $select = "SELECT id, name, slug";
    if ($has_description) $select .= ", description";
    if ($has_active) $select .= ", active";
    $select .= " FROM cities";
    if ($has_active) $select .= " WHERE active = 1";
    $select .= " ORDER BY name";
    
    $result = $conn->query($select);
    
    $cities = [];
    while ($row = $result->fetch_assoc()) {
        $cities[] = [
            'id' => (int) $row['id'],
            'name' => sanitizeString($row['name']),
            'slug' => $row['slug'],
            'description' => sanitizeString($row['description'] ?? ''),
            'active' => isset($row['active']) ? (bool) $row['active'] : true
        ];
    }
    
    jsonSuccess(['cities' => $cities]);
    
} catch (Exception $e) {
    error_log('Cities API error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
