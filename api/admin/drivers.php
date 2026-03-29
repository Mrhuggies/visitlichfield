<?php
/**
 * Admin Drivers API
 * Manage drivers - list, activate, deactivate, delete
 */
require_once __DIR__ . '/../config.php';

initApiEndpoint();

try {
    $conn = getDbConnection();
    
    // Check what columns exist in drivers table
    $columns_result = $conn->query("DESCRIBE drivers");
    $existing_columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
    
    $has_status = in_array('status', $existing_columns);
    $has_email_verified = in_array('email_verified', $existing_columns);
    $has_last_login = in_array('last_login', $existing_columns);
    $has_created_at = in_array('created_at', $existing_columns);
    
    // Verify admin token
    $token = $_GET['token'] ?? '';
    if (!$token) {
        jsonError('Authorization required', 401);
    }
    
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE auth_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    if (!$admin) {
        jsonError('Invalid or expired token', 401);
    }
    
    // POST - Update driver status
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $driver_id = (int) ($input['driver_id'] ?? 0);
        $action = $input['action'] ?? '';
        
        if (!$driver_id) {
            jsonError('Driver ID required');
        }
        
        if (!$has_status) {
            $conn->query("ALTER TABLE drivers ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
            $has_status = true;
        }
        
        $valid_actions = ['activate', 'deactivate', 'approve', 'reject'];
        if (!in_array($action, $valid_actions)) {
            jsonError('Invalid action');
        }
        
        if ($action === 'activate') {
            $stmt = $conn->prepare("UPDATE drivers SET status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $driver_id);
            if ($stmt->execute()) {
                logSecurityEvent('driver_activated', ['driver_id' => $driver_id, 'admin_id' => $admin['id']]);
                jsonSuccess(['message' => 'Driver activated']);
            }
            jsonError('Failed to activate driver', 500);
        }
        
        if ($action === 'deactivate') {
            $stmt = $conn->prepare("UPDATE drivers SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param("i", $driver_id);
            if ($stmt->execute()) {
                logSecurityEvent('driver_deactivated', ['driver_id' => $driver_id, 'admin_id' => $admin['id']]);
                jsonSuccess(['message' => 'Driver deactivated']);
            }
            jsonError('Failed to deactivate driver', 500);
        }
        
        if ($action === 'approve') {
            if (!$has_email_verified) {
                $conn->query("ALTER TABLE drivers ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
            }
            // Set both status='active' AND active=1 for compatibility
            $stmt = $conn->prepare("UPDATE drivers SET status = 'active', email_verified = 1, active = 1 WHERE id = ?");
            $stmt->bind_param("i", $driver_id);
            if ($stmt->execute()) {
                logSecurityEvent('driver_approved', ['driver_id' => $driver_id, 'admin_id' => $admin['id']]);
                jsonSuccess(['message' => 'Driver approved']);
            }
            jsonError('Failed to approve driver', 500);
        }
        
        if ($action === 'reject') {
            $stmt = $conn->prepare("DELETE FROM drivers WHERE id = ?");
            $stmt->bind_param("i", $driver_id);
            if ($stmt->execute()) {
                logSecurityEvent('driver_rejected', ['driver_id' => $driver_id, 'admin_id' => $admin['id']]);
                jsonSuccess(['message' => 'Driver application rejected']);
            }
            jsonError('Failed to reject driver', 500);
        }
    }
    
    // DELETE - Remove driver
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $driver_id = (int) ($_GET['id'] ?? 0);
        
        if (!$driver_id) {
            jsonError('Driver ID required');
        }
        
        // Check if driver has any tours
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tours WHERE driver_id = ?");
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $tour_count = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($tour_count > 0) {
            jsonError("Cannot delete driver with $tour_count existing tours. Deactivate instead.");
        }
        
        $stmt = $conn->prepare("DELETE FROM drivers WHERE id = ?");
        $stmt->bind_param("i", $driver_id);
        
        if ($stmt->execute()) {
            logSecurityEvent('driver_deleted', ['driver_id' => $driver_id, 'admin_id' => $admin['id']]);
            jsonSuccess(['message' => 'Driver deleted']);
        }
        jsonError('Failed to delete driver', 500);
    }
    
    // GET - Retrieve drivers
    $status_filter = isset($_GET['status']) ? sanitizeString($_GET['status'], 20) : '';
    $search = isset($_GET['search']) ? sanitizeString($_GET['search'], 100) : '';
    
    // Get specific driver by ID
    if (isset($_GET['driver_id'])) {
        $driver_id = (int) $_GET['driver_id'];
        
        $query = "
            SELECT 
                d.*,
                COUNT(DISTINCT t.id) as total_tours,
                COUNT(DISTINCT CASE WHEN t.status = 'active' THEN t.id END) as active_tours,
                COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tours,
                COUNT(DISTINCT tp.session_id) as total_participants,
                AVG(tf.rating) as avg_rating,
                COUNT(DISTINCT tf.id) as feedback_count
            FROM drivers d
            LEFT JOIN tours t ON d.id = t.driver_id
            LEFT JOIN tour_participants tp ON t.id = tp.tour_id
            LEFT JOIN tour_feedback tf ON t.id = tf.tour_id
            WHERE d.id = ?
            GROUP BY d.id
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $driver = $stmt->get_result()->fetch_assoc();
        
        if (!$driver) {
            jsonError('Driver not found', 404);
        }
        
        // Sanitize output
        $driver['name'] = sanitizeString($driver['name']);
        $driver['email'] = sanitizeString($driver['email']);
        
        // Set defaults for missing fields
        if (!isset($driver['status'])) $driver['status'] = 'active';
        if (!isset($driver['email_verified'])) $driver['email_verified'] = 1;
        
        // Get recent tours
        $stmt = $conn->prepare("
            SELECT 
                t.id, t.tour_code, t.display_name, t.tour_date, t.status,
                c.name as city_name,
                COUNT(DISTINCT tp.session_id) as participant_count
            FROM tours t
            LEFT JOIN cities c ON t.city_id = c.id
            LEFT JOIN tour_participants tp ON t.id = tp.tour_id
            WHERE t.driver_id = ?
            GROUP BY t.id
            ORDER BY t.tour_date DESC
            LIMIT 10
        ");
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $recent_tours = [];
        while ($row = $result->fetch_assoc()) {
            $row['display_name'] = sanitizeString($row['display_name']);
            $row['city_name'] = sanitizeString($row['city_name']);
            $recent_tours[] = $row;
        }
        
        // Remove sensitive fields
        unset($driver['password_hash'], $driver['auth_token'], $driver['reset_token'], $driver['verification_token']);
        
        jsonSuccess([
            'driver' => $driver,
            'recent_tours' => $recent_tours
        ]);
    }
    
    // List all drivers
    $query = "
        SELECT 
            d.id, d.name, d.email, d.phone" .
            ($has_status ? ", d.status" : ", 'active' as status") .
            ($has_email_verified ? ", d.email_verified" : ", 1 as email_verified") .
            ($has_created_at ? ", d.created_at" : ", NOW() as created_at") .
            ($has_last_login ? ", d.last_login" : ", NULL as last_login") . ",
            COUNT(DISTINCT t.id) as total_tours,
            COUNT(DISTINCT CASE WHEN t.status = 'active' THEN t.id END) as active_tours,
            COUNT(DISTINCT tp.session_id) as total_participants,
            AVG(tf.rating) as avg_rating
        FROM drivers d
        LEFT JOIN tours t ON d.id = t.driver_id
        LEFT JOIN tour_participants tp ON t.id = tp.tour_id
        LEFT JOIN tour_feedback tf ON t.id = tf.tour_id
    ";
    
    $where_clauses = [];
    $params = [];
    $types = "";
    
    if ($status_filter && $has_status) {
        $where_clauses[] = "d.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    if ($search) {
        $where_clauses[] = "(d.name LIKE ? OR d.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }
    
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $query .= " GROUP BY d.id ORDER BY " . ($has_created_at ? "d.created_at" : "d.id") . " DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $drivers = [];
    while ($row = $result->fetch_assoc()) {
        $row['name'] = sanitizeString($row['name']);
        $row['email'] = sanitizeString($row['email']);
        $drivers[] = $row;
    }
    
    // Get pending drivers count
    $pending_count = 0;
    if ($has_status || $has_email_verified) {
        $pending_query = "SELECT COUNT(*) as count FROM drivers WHERE ";
        if ($has_status && $has_email_verified) {
            $pending_query .= "status = 'pending' OR email_verified = 0";
        } else if ($has_status) {
            $pending_query .= "status = 'pending'";
        } else if ($has_email_verified) {
            $pending_query .= "email_verified = 0";
        }
        $stmt = $conn->prepare($pending_query);
        $stmt->execute();
        $pending_count = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    jsonSuccess([
        'drivers' => $drivers,
        'total' => count($drivers),
        'pending_count' => (int) $pending_count
    ]);
    
} catch (Exception $e) {
    error_log('Drivers API error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
