-- Objects the PHP code expects. Several endpoints used to create these on the fly with
-- CREATE/ALTER TABLE; run this once instead and give the web user's DB account only
-- SELECT/INSERT/UPDATE/DELETE (no DDL) afterwards. All statements are safe to re-run
-- except the ALTERs, which error if the column already exists (ignore that error).

CREATE TABLE IF NOT EXISTS rate_limits (
    identifier VARCHAR(190) NOT NULL PRIMARY KEY,
    request_count INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS location_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tour_id INT,
    session_id VARCHAR(64),
    location_id VARCHAR(50),
    location_name VARCHAR(100),
    city_slug VARCHAR(50),
    entered_at DATETIME,
    exited_at DATETIME,
    dwell_seconds INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tour (tour_id),
    INDEX idx_location (location_id),
    INDEX idx_city (city_slug),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Columns added at runtime by update-profile.php / admin/drivers.php:
-- ALTER TABLE drivers ADD COLUMN bio TEXT;
-- ALTER TABLE drivers ADD COLUMN status VARCHAR(20) DEFAULT 'active';
-- ALTER TABLE drivers ADD COLUMN email_verified TINYINT(1) DEFAULT 0;
