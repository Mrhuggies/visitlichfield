<?php
/**
 * Demo Data Generator - Robust Version
 * RUN ONCE then DELETE this file
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: text/html');
echo "<h1>🎭 Demo Data Generator</h1><pre>";

try {
    $conn = getDbConnection();
    echo "✅ Database connected\n\n";
    
    // ============================================
    // CHECK TABLE STRUCTURES
    // ============================================
    echo "Checking table structures...\n";
    
    // Check tours columns
    $tours_cols = [];
    $result = $conn->query("DESCRIBE tours");
    while ($row = $result->fetch_assoc()) {
        $tours_cols[] = $row['Field'];
    }
    echo "Tours columns: " . implode(', ', $tours_cols) . "\n\n";
    
    // ============================================
    // CLEANUP OLD TEST DATA
    // ============================================
    echo "🧹 Cleaning up old test data...\n";
    
    // Check if tour_feedback exists
    $table_check = $conn->query("SHOW TABLES LIKE 'tour_feedback'");
    if ($table_check->num_rows > 0) {
        $conn->query("DELETE FROM tour_feedback");
        echo "  Deleted tour_feedback\n";
    }
    
    $conn->query("DELETE FROM tour_participants");
    echo "  Deleted tour_participants\n";
    
    $conn->query("DELETE FROM tours");
    echo "  Deleted tours\n\n";
    
    // ============================================
    // DATA ARRAYS
    // ============================================
    $first_names = ['James', 'Emma', 'Oliver', 'Sophie', 'William', 'Charlotte', 'Harry', 'Amelia', 
                    'George', 'Isla', 'Jack', 'Mia', 'Thomas', 'Emily', 'Charlie', 'Grace'];
    
    $last_names = ['Smith', 'Jones', 'Williams', 'Taylor', 'Brown', 'Davies', 'Wilson', 'Evans',
                   'Thomas', 'Johnson', 'Roberts', 'Walker', 'Wright', 'Robinson', 'Thompson', 'White'];
    
    $tour_names = ['Historic Tour', 'Heritage Walk', 'Cathedral Tour', 'City Explorer', 
                   'Afternoon Discovery', 'Classic Tour', 'Grand Tour', 'Scenic Route'];
    
    $good_reviews = [
        "Absolutely fantastic tour! Our guide was incredibly knowledgeable.",
        "What a wonderful experience. Highly recommend!",
        "The best tour we've taken. Professional and entertaining.",
        "Loved every minute. The driver really knows their stuff!",
        "Perfect way to spend an afternoon. Very informative.",
        "Exceeded all expectations! Will definitely book again.",
        "A must-do for anyone visiting. Brilliantly organised.",
        "Great value for money. Really enjoyed it.",
        "Made our holiday! So friendly and accommodating.",
        "Wonderful tour with stunning views.",
    ];
    
    $ok_reviews = [
        "Good tour overall. Enjoyed the experience.",
        "Nice way to see the city.",
        "Pleasant afternoon out.",
        "Decent tour for the price.",
    ];
    
    // ============================================
    // GET CITIES
    // ============================================
    $cities = [];
    $result = $conn->query("SELECT id, name, slug FROM cities");
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row;
    }
    echo "Found " . count($cities) . " cities\n\n";
    
    // ============================================
    // GET EXISTING DRIVERS
    // ============================================
    $drivers = [];
    $result = $conn->query("SELECT id, name, phone FROM drivers");
    while ($row = $result->fetch_assoc()) {
        $drivers[] = $row;
        echo "Found driver: {$row['name']}\n";
    }
    echo "\n";
    
    // ============================================
    // ADD NEW DRIVERS
    // ============================================
    echo "Creating 6 new drivers...\n";
    
    $used_emails = [];
    for ($i = 0; $i < 6; $i++) {
        $first = $first_names[array_rand($first_names)];
        $last = $last_names[array_rand($last_names)];
        $name = "$first $last";
        $email = strtolower($first . '.' . $last . rand(1,99)) . '@lichfieldtours.com';
        $phone = '+447' . rand(100, 999) . rand(100000, 999999);
        $password_hash = password_hash('Demo123!', PASSWORD_DEFAULT);
        $days_ago = rand(30, 180);
        $created = date('Y-m-d H:i:s', strtotime("-$days_ago days"));
        
        $stmt = $conn->prepare("INSERT INTO drivers (name, email, phone, password_hash, email_verified, status, created_at) VALUES (?, ?, ?, ?, 1, 'active', ?)");
        $stmt->bind_param("sssss", $name, $email, $phone, $password_hash, $created);
        
        if ($stmt->execute()) {
            $drivers[] = ['id' => $conn->insert_id, 'name' => $name, 'phone' => $phone];
            echo "  ✅ $name\n";
        } else {
            echo "  ❌ Failed: " . $conn->error . "\n";
        }
    }
    echo "\n";
    
    // ============================================
    // CREATE TOURS
    // ============================================
    echo "Creating tours...\n";
    
    $tour_ids = [];
    
    foreach ($drivers as $driver) {
        $num_tours = rand(8, 15);
        
        for ($t = 0; $t < $num_tours; $t++) {
            $city = $cities[array_rand($cities)];
            
            // 85% completed, 15% active/scheduled
            if (rand(1, 100) <= 85) {
                $days_ago = rand(1, 90);
                $tour_date = date('Y-m-d', strtotime("-$days_ago days"));
                $status = 'completed';
            } else {
                $days_ahead = rand(0, 5);
                $tour_date = date('Y-m-d', strtotime("+$days_ahead days"));
                $status = $days_ahead == 0 ? 'active' : 'scheduled';
            }
            
            $hour = rand(9, 16);
            $minute = [0, 30][rand(0, 1)];
            $departure_time = sprintf('%02d:%02d:00', $hour, $minute);
            
            $tour_code = strtoupper(substr($city['slug'], 0, 4)) . '-' . date('ga', strtotime($departure_time)) . '-' . strtoupper(date('MD', strtotime($tour_date))) . '-' . rand(1, 99);
            $display_name = $tour_names[array_rand($tour_names)];
            $duration = [60, 90, 120][rand(0, 2)];
            
            $stmt = $conn->prepare("INSERT INTO tours (city_id, current_city_id, tour_code, display_name, tour_date, departure_time, duration_minutes, status, driver_name, driver_phone, driver_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssisisi", 
                $city['id'], $city['id'], $tour_code, $display_name, $tour_date,
                $departure_time, $duration, $status, $driver['name'], $driver['phone'], $driver['id']
            );
            
            if ($stmt->execute()) {
                $tour_ids[] = ['id' => $conn->insert_id, 'status' => $status, 'date' => $tour_date];
            }
        }
        echo "  ✅ {$driver['name']}: $num_tours tours\n";
    }
    echo "Total tours: " . count($tour_ids) . "\n\n";
    
    // ============================================
    // CREATE PARTICIPANTS
    // ============================================
    echo "Creating participants...\n";
    $total_participants = 0;
    
    foreach ($tour_ids as $tour) {
        if ($tour['status'] == 'scheduled') continue;
        
        $num = rand(5, 25);
        for ($p = 0; $p < $num; $p++) {
            $session = bin2hex(random_bytes(16));
            $joined = $tour['date'] . ' ' . sprintf('%02d:%02d:00', rand(8, 17), rand(0, 59));
            $checked = $tour['status'] == 'completed' ? rand(0, 1) : 0;
            
            $stmt = $conn->prepare("INSERT INTO tour_participants (tour_id, session_id, joined_at, checked_in) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $tour['id'], $session, $joined, $checked);
            $stmt->execute();
            $total_participants++;
        }
    }
    echo "Total participants: $total_participants\n\n";
    
    // ============================================
    // CREATE FEEDBACK
    // ============================================
    echo "Creating feedback...\n";
    
    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS tour_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tour_id INT NOT NULL,
        rating INT NOT NULL,
        feedback_text TEXT,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tour (tour_id)
    )");
    
    $total_feedback = 0;
    
    foreach ($tour_ids as $tour) {
        if ($tour['status'] != 'completed') continue;
        
        $num_reviews = rand(0, 6);
        for ($r = 0; $r < $num_reviews; $r++) {
            // Weighted towards good ratings
            $rand = rand(1, 100);
            if ($rand <= 50) $rating = 5;
            elseif ($rand <= 80) $rating = 4;
            elseif ($rand <= 92) $rating = 3;
            elseif ($rand <= 97) $rating = 2;
            else $rating = 1;
            
            $text = $rating >= 4 ? $good_reviews[array_rand($good_reviews)] : $ok_reviews[array_rand($ok_reviews)];
            $submitted = date('Y-m-d H:i:s', strtotime($tour['date'] . " +" . rand(1, 48) . " hours"));
            
            $stmt = $conn->prepare("INSERT INTO tour_feedback (tour_id, rating, feedback_text, submitted_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $tour['id'], $rating, $text, $submitted);
            $stmt->execute();
            $total_feedback++;
        }
    }
    echo "Total feedback: $total_feedback\n\n";
    
    // ============================================
    // DONE
    // ============================================
    echo "============================================\n";
    echo "✅ COMPLETE!\n";
    echo "============================================\n";
    echo "Drivers: " . count($drivers) . "\n";
    echo "Tours: " . count($tour_ids) . "\n";
    echo "Participants: $total_participants\n";
    echo "Feedback: $total_feedback\n\n";
    echo "🔐 New drivers password: Demo123!\n\n";
    echo "⚠️  DELETE THIS FILE NOW!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
echo "</pre>";
?>
