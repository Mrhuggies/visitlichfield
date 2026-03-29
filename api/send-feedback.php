<?php
/**
 * Send Feedback API
 * Saves tour feedback to database and sends notification
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limit feedback submissions (10 per hour per IP)
$ip = getClientIp();
checkRateLimit('feedback_' . $ip, 10, 3600);

try {
    $conn = getDbConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $rating = isset($input['rating']) ? (int) $input['rating'] : 0;
    $feedback = isset($input['feedback']) ? sanitizeString($input['feedback'], 2000) : '';
    $tour_code = isset($input['tour_code']) ? sanitizeString($input['tour_code'], 20) : 'UNKNOWN';
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        jsonError('Rating must be between 1 and 5');
    }
    
    // Feedback text required for low ratings
    if ($rating <= 3 && empty($feedback)) {
        jsonError('Feedback required for ratings 1-3 stars');
    }
    
    // Get tour ID from tour code
    $stmt = $conn->prepare("SELECT id FROM tours WHERE tour_code = ?");
    $stmt->bind_param("s", $tour_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $tour = $result->fetch_assoc();
    
    if (!$tour) {
        jsonError('Tour not found', 404);
    }
    
    $tour_id = $tour['id'];
    
    // Save to database
    $stmt = $conn->prepare("INSERT INTO tour_feedback (tour_id, rating, feedback_text, submitted_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $tour_id, $rating, $feedback);
    
    if (!$stmt->execute()) {
        jsonError('Failed to save feedback', 500);
    }
    
    $feedback_id = $stmt->insert_id;
    
    // Send notification email (to admin, not personal email)
    $to = ADMIN_EMAIL;
    $subject = "Tour Feedback - $rating Star" . ($rating != 1 ? 's' : '');
    
    $message = "New Feedback Received\n\n";
    $message .= "Rating: " . str_repeat('*', $rating) . " ($rating/5)\n";
    $message .= "Tour Code: $tour_code\n";
    $message .= "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    if (!empty($feedback)) {
        $message .= "Feedback:\n$feedback\n\n";
    } else {
        $message .= "No feedback text provided (high rating)\n\n";
    }
    
    $message .= "---\n";
    $message .= "View in admin: " . BASE_URL . "/admin/dashboard.html\n";
    
    $headers = "From: noreply@tag-art.co.uk\r\n";
    $headers .= "Reply-To: noreply@tag-art.co.uk\r\n";
    
    $email_sent = @mail($to, $subject, $message, $headers);
    
    jsonSuccess([
        'feedback_id' => $feedback_id,
        'email_sent' => $email_sent,
        'message' => 'Thank you for your feedback!'
    ]);
    
} catch (Exception $e) {
    error_log('Feedback error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
