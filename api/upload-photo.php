<?php
/**
 * Driver Photo Upload API
 * Secure file upload with proper validation
 */
require_once __DIR__ . '/config.php';

initApiEndpoint();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

try {
    $conn = getDbConnection();
    
    // Get auth token
    $token = $_POST['token'] ?? '';
    if (!$token) {
        jsonError('Authorization required', 401);
    }
    
    // Verify token
    $stmt = $conn->prepare("SELECT id FROM drivers WHERE auth_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$driver = $result->fetch_assoc()) {
        jsonError('Invalid or expired token', 401);
    }
    
    $driver_id = $driver['id'];
    
    // Check if file uploaded
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
        jsonError('No photo uploaded');
    }
    
    $file = $_FILES['photo'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
        ];
        jsonError($errors[$file['error']] ?? 'Upload failed', 500);
    }
    
    // Validate file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        jsonError('File too large. Maximum 5MB');
    }
    
    // SECURE: Validate ACTUAL file type using finfo (not user-supplied MIME)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $actualMimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($actualMimeType, ALLOWED_IMAGE_TYPES)) {
        logSecurityEvent('upload_blocked', [
            'driver_id' => $driver_id,
            'claimed_type' => $file['type'],
            'actual_type' => $actualMimeType
        ]);
        jsonError('Invalid file type. Only JPEG, PNG, WebP, and GIF allowed');
    }
    
    // SECURE: Verify it's actually an image using getimagesize
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        logSecurityEvent('upload_blocked', [
            'driver_id' => $driver_id,
            'reason' => 'not_valid_image'
        ]);
        jsonError('File is not a valid image');
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/drivers/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        
        // Create .htaccess to prevent PHP execution
        file_put_contents($upload_dir . '.htaccess', 
            "php_flag engine off\n" .
            "AddHandler cgi-script .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .htm .shtml .sh .cgi\n" .
            "Options -ExecCGI\n" .
            "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|htm|shtml|sh|cgi)$\">\n" .
            "    Deny from all\n" .
            "</FilesMatch>"
        );
    }
    
    // SECURE: Generate random filename (don't use original filename)
    $extension = image_type_to_extension($imageInfo[2], false); // Get extension from actual image type
    $filename = 'driver_' . $driver_id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        jsonError('Failed to save photo', 500);
    }
    
    // Delete old photo if exists
    $stmt = $conn->prepare("SELECT profile_photo FROM drivers WHERE id = ?");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $oldPath = __DIR__ . '/../' . $row['profile_photo'];
        if ($row['profile_photo'] && file_exists($oldPath)) {
            unlink($oldPath);
        }
    }
    
    // Save to database (relative path)
    $db_path = 'uploads/drivers/' . $filename;
    $stmt = $conn->prepare("UPDATE drivers SET profile_photo = ? WHERE id = ?");
    $stmt->bind_param("si", $db_path, $driver_id);
    $stmt->execute();
    
    logSecurityEvent('photo_uploaded', ['driver_id' => $driver_id, 'filename' => $filename]);
    
    jsonSuccess([
        'photo_url' => BASE_URL . '/' . $db_path,
        'message' => 'Photo uploaded successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Upload error: ' . $e->getMessage());
    jsonError('Server error', 500);
}
?>
